<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * JournalEntry Model
 * Double-entry bookkeeping journal entries and their lines.
 */
class JournalEntry extends BaseModel
{
    protected static string $table      = 'journal_entries';
    protected static string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // Complex write operations
    // -------------------------------------------------------------------------

    /**
     * Create a journal entry with its lines inside a transaction.
     * Validates that total debits == total credits before saving.
     *
     * @param array $entryData  ['entry_date', 'reference', 'description', 'source_type', 'source_id', 'status']
     * @param array $linesData  [['account_id', 'debit_amount', 'credit_amount', 'description'], ...]
     * @return int  New journal_entry ID
     * @throws \RuntimeException on imbalance or DB error
     */
    public function createWithLines(array $entryData, array $linesData): int
    {
        $this->assertBalanced($linesData);

        $db = $this->db();
        $db->beginTransaction();

        try {
            $db->execute(
                "INSERT INTO journal_entries
                 (tenant_id, entry_date, reference, description,
                  source_type, source_id, status, created_by, created_at, updated_at)
                 VALUES
                 (:tid, :date, :ref, :desc, :stype, :sid, :status, :by, NOW(), NOW())",
                [
                    ':tid'    => $this->tenantId,
                    ':date'   => $entryData['entry_date'],
                    ':ref'    => $entryData['reference']    ?? null,
                    ':desc'   => $entryData['description']  ?? null,
                    ':stype'  => $entryData['source_type']  ?? null,
                    ':sid'    => $entryData['source_id']    ?? null,
                    ':status' => $entryData['status']       ?? 'draft',
                    ':by'     => $entryData['created_by']   ?? null,
                ]
            );

            $jeId = (int) $db->lastInsertId();
            $this->insertLines($db, $jeId, $linesData);

            $db->commit();
            return $jeId;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetch a journal entry with all its lines and account names.
     */
    public function getWithLines(int $id): ?array
    {
        $entry = $this->db()->fetchOne(
            "SELECT je.*, u.name AS created_by_name
             FROM journal_entries je
             LEFT JOIN users u ON u.id = je.created_by
             WHERE je.id = :id AND je.tenant_id = :tid",
            [':id' => $id, ':tid' => $this->tenantId]
        );

        if (!$entry) {
            return null;
        }

        $entry['lines'] = $this->db()->fetchAll(
            "SELECT jl.*, a.code AS account_code, a.name AS account_name
             FROM journal_lines jl
             JOIN accounts a ON a.id = jl.account_id
             WHERE jl.journal_entry_id = :jeid AND jl.tenant_id = :tid
             ORDER BY jl.sort_order, jl.id",
            [':jeid' => $id, ':tid' => $this->tenantId]
        );

        return $entry;
    }

    /**
     * Post (finalise) a journal entry. Cannot be undone except by voiding.
     *
     * @throws \RuntimeException if already posted or voided
     */
    public function post(int $id): bool
    {
        $entry = $this->find($id);
        if (!$entry) {
            throw new \RuntimeException("Journal entry #{$id} not found.");
        }
        if ($entry['status'] !== 'draft') {
            throw new \RuntimeException("Only draft entries can be posted (current status: {$entry['status']}).");
        }

        $this->db()->execute(
            "UPDATE journal_entries SET status = 'posted', posted_at = NOW(), updated_at = NOW() WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $this->tenantId]
        );
        return true;
    }

    /**
     * Void a journal entry by creating an auto-reversing entry.
     * Sets original to 'voided' and links to the reversing entry.
     *
     * @throws \RuntimeException if not posted
     */
    public function void(int $id, string $reason = ''): int
    {
        $original = $this->getWithLines($id);
        if (!$original) {
            throw new \RuntimeException("Journal entry #{$id} not found.");
        }
        if ($original['status'] !== 'posted') {
            throw new \RuntimeException("Only posted entries can be voided (current status: {$original['status']}).");
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            // Build reversing lines (swap debit/credit)
            $reversingLines = [];
            foreach ($original['lines'] as $line) {
                $reversingLines[] = [
                    'account_id'    => $line['account_id'],
                    'debit_amount'  => $line['credit_amount'],
                    'credit_amount' => $line['debit_amount'],
                    'description'   => 'VOID: ' . $line['description'],
                    'sort_order'    => $line['sort_order'] ?? 0,
                ];
            }

            // Create reversing entry
            $db->execute(
                "INSERT INTO journal_entries
                 (tenant_id, entry_date, reference, description,
                  source_type, source_id, status, created_by, created_at, updated_at)
                 VALUES
                 (:tid, :date, :ref, :desc, 'void', :src_id, 'posted', :by, NOW(), NOW())",
                [
                    ':tid'    => $this->tenantId,
                    ':date'   => date('Y-m-d'),
                    ':ref'    => 'VOID-' . ($original['reference'] ?? $id),
                    ':desc'   => 'Void of JE#' . $id . ($reason ? ': ' . $reason : ''),
                    ':src_id' => $id,
                    ':by'     => $original['created_by'] ?? null,
                ]
            );

            $reversingId = (int) $db->lastInsertId();
            $this->insertLines($db, $reversingId, $reversingLines);

            // Mark original as voided
            $db->execute(
                "UPDATE journal_entries
                 SET status = 'voided', void_reason = :reason,
                     voided_by_je = :rev_id, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid",
                [
                    ':reason'  => $reason,
                    ':rev_id'  => $reversingId,
                    ':id'      => $id,
                    ':tid'     => $this->tenantId,
                ]
            );

            $db->commit();
            return $reversingId;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Calculate account balance from posted journal lines for a date range.
     * Returns net (debit - credit) amount.
     */
    public function getAccountBalance(int $accountId, string $startDate, string $endDate): string
    {
        $row = $this->db()->fetchOne(
            "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS balance
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_entry_id
             WHERE jl.account_id = :acct
               AND jl.tenant_id  = :tid
               AND je.status      = 'posted'
               AND je.entry_date  BETWEEN :start AND :end",
            [
                ':acct'  => $accountId,
                ':tid'   => $this->tenantId,
                ':start' => $startDate,
                ':end'   => $endDate,
            ]
        );

        return number_format((float) ($row['balance'] ?? 0), 4, '.', '');
    }

    // -------------------------------------------------------------------------
    // List / search
    // -------------------------------------------------------------------------

    /**
     * Get paginated journal entries with optional filters.
     *
     * @param array $filters ['start_date', 'end_date', 'status', 'search']
     */
    public function getList(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['je.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if (!empty($filters['start_date'])) {
            $where[]               = 'je.entry_date >= :start';
            $params[':start']      = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]               = 'je.entry_date <= :end';
            $params[':end']        = $filters['end_date'];
        }
        if (!empty($filters['status'])) {
            $where[]               = 'je.status = :status';
            $params[':status']     = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]               = '(je.reference LIKE :srch OR je.description LIKE :srch)';
            $params[':srch']       = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM journal_entries je WHERE {$whereStr}",
            $params
        )['cnt'] ?? 0);

        $params[':lim'] = $perPage;
        $params[':off'] = $offset;

        $data = $this->db()->fetchAll(
            "SELECT je.*,
                    COALESCE(SUM(jl.debit_amount), 0) AS total_debits
             FROM journal_entries je
             LEFT JOIN journal_lines jl ON jl.journal_entry_id = je.id
             WHERE {$whereStr}
             GROUP BY je.id
             ORDER BY je.entry_date DESC, je.id DESC
             LIMIT :lim OFFSET :off",
            $params
        );

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Insert journal lines for a given journal entry.
     */
    private function insertLines(Database $db, int $jeId, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $db->execute(
                "INSERT INTO journal_lines
                 (journal_entry_id, tenant_id, account_id,
                  debit_amount, credit_amount, description, sort_order,
                  created_at, updated_at)
                 VALUES
                 (:je, :tid, :acct, :dr, :cr, :desc, :sort, NOW(), NOW())",
                [
                    ':je'   => $jeId,
                    ':tid'  => $this->tenantId,
                    ':acct' => $line['account_id'],
                    ':dr'   => round((float) ($line['debit_amount']  ?? 0), 4),
                    ':cr'   => round((float) ($line['credit_amount'] ?? 0), 4),
                    ':desc' => $line['description'] ?? null,
                    ':sort' => $line['sort_order']  ?? $i,
                ]
            );
        }
    }

    /**
     * Assert that the sum of debits equals the sum of credits.
     *
     * @throws \RuntimeException on imbalance
     */
    private function assertBalanced(array $lines): void
    {
        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit  += round((float) ($line['debit_amount']  ?? 0), 4);
            $totalCredit += round((float) ($line['credit_amount'] ?? 0), 4);
        }

        if (round(abs($totalDebit - $totalCredit), 4) > 0.0001) {
            throw new \RuntimeException(
                sprintf(
                    'Journal entry is not balanced: debits=%.4f, credits=%.4f.',
                    $totalDebit,
                    $totalCredit
                )
            );
        }

        if ($totalDebit == 0) {
            throw new \RuntimeException('Journal entry must have at least one non-zero line.');
        }
    }
}
