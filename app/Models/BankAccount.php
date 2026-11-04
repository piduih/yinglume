<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * BankAccount Model
 * Bank / cash accounts and their transaction ledger.
 */
class BankAccount extends BaseModel
{
    protected static string $table      = 'bank_accounts';
    protected static string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // Balance
    // -------------------------------------------------------------------------

    /**
     * Calculate the running balance of a bank account as of a date.
     * Balance = opening_balance + SUM(deposits) - SUM(withdrawals) for all posted transactions up to asOf.
     */
    public function getBalance(int $id, string $asOf): string
    {
        $account = $this->find($id);
        if (!$account) {
            return '0.0000';
        }

        $row = $this->db()->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'deposit'    THEN amount ELSE 0 END), 0) AS deposits,
                COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END), 0) AS withdrawals
             FROM bank_transactions
             WHERE bank_account_id = :id
               AND tenant_id       = :tid
               AND status          = 'cleared'
               AND transaction_date <= :asof",
            [':id' => $id, ':tid' => $this->tenantId, ':asof' => $asOf]
        );

        $balance = round(
            (float) $account['opening_balance']
            + (float) ($row['deposits']    ?? 0)
            - (float) ($row['withdrawals'] ?? 0),
            4
        );

        return number_format($balance, 4, '.', '');
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /**
     * Get transactions for a bank account with optional filters and running balance.
     *
     * @param array $filters ['start_date', 'end_date', 'type', 'search']
     */
    public function getTransactions(int $id, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where  = ['bt.bank_account_id = :id', 'bt.tenant_id = :tid'];
        $params = [':id' => $id, ':tid' => $this->tenantId];

        if (!empty($filters['start_date'])) {
            $where[]          = 'bt.transaction_date >= :start';
            $params[':start'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]          = 'bt.transaction_date <= :end';
            $params[':end']   = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $where[]          = 'bt.transaction_type = :type';
            $params[':type']  = $filters['type'];
        }
        if (!empty($filters['search'])) {
            $where[]          = '(bt.reference LIKE :srch OR bt.description LIKE :srch)';
            $params[':srch']  = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM bank_transactions bt WHERE {$whereStr}", $params
        )['cnt'] ?? 0);

        $params[':lim'] = $perPage;
        $params[':off'] = $offset;

        $data = $this->db()->fetchAll(
            "SELECT bt.*
             FROM bank_transactions bt
             WHERE {$whereStr}
             ORDER BY bt.transaction_date, bt.id
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

    /**
     * Record a deposit or withdrawal transaction.
     * Also creates the matching journal entry.
     *
     * @param array $txData ['transaction_type', 'amount', 'transaction_date', 'reference',
     *                       'description', 'gl_account_id', 'created_by']
     */
    public function addTransaction(int $bankAccountId, array $txData): int
    {
        $account = $this->find($bankAccountId);
        if (!$account) {
            throw new \RuntimeException("Bank account #{$bankAccountId} not found.");
        }

        $amount = round((float) $txData['amount'], 4);
        if ($amount <= 0) {
            throw new \RuntimeException('Transaction amount must be positive.');
        }

        $type = $txData['transaction_type'];
        if (!in_array($type, ['deposit', 'withdrawal'], true)) {
            throw new \RuntimeException('Invalid transaction type.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            // Insert bank transaction record
            $db->execute(
                "INSERT INTO bank_transactions
                 (tenant_id, bank_account_id, transaction_type, amount,
                  transaction_date, reference, description, status,
                  gl_account_id, created_by, created_at)
                 VALUES
                 (:tid, :baid, :type, :amt, :date, :ref, :desc, 'cleared', :gl, :by, NOW())",
                [
                    ':tid'   => $this->tenantId,
                    ':baid'  => $bankAccountId,
                    ':type'  => $type,
                    ':amt'   => $amount,
                    ':date'  => $txData['transaction_date'],
                    ':ref'   => $txData['reference']   ?? null,
                    ':desc'  => $txData['description'] ?? null,
                    ':gl'    => $txData['gl_account_id'] ?? null,
                    ':by'    => $txData['created_by']   ?? null,
                ]
            );

            $txId = (int) $db->lastInsertId();

            // Create journal entry if gl_account_id provided
            if (!empty($txData['gl_account_id'])) {
                $bankGLAccountId = (int) $account['gl_account_id'];
                $offsetGLId      = (int) $txData['gl_account_id'];

                $lines = $type === 'deposit'
                    ? [
                        ['account_id' => $bankGLAccountId, 'debit_amount'  => $amount, 'credit_amount' => 0, 'description' => $txData['description'] ?? ''],
                        ['account_id' => $offsetGLId,      'debit_amount'  => 0, 'credit_amount' => $amount, 'description' => $txData['description'] ?? ''],
                      ]
                    : [
                        ['account_id' => $offsetGLId,      'debit_amount'  => $amount, 'credit_amount' => 0, 'description' => $txData['description'] ?? ''],
                        ['account_id' => $bankGLAccountId, 'debit_amount'  => 0, 'credit_amount' => $amount, 'description' => $txData['description'] ?? ''],
                      ];

                $jeModel = new JournalEntry();
                $jeModel->createWithLines(
                    [
                        'entry_date'  => $txData['transaction_date'],
                        'reference'   => $txData['reference'] ?? ('BT-' . $txId),
                        'description' => $txData['description'] ?? ucfirst($type),
                        'source_type' => 'bank_transaction',
                        'source_id'   => $txId,
                        'status'      => 'posted',
                        'created_by'  => $txData['created_by'] ?? null,
                    ],
                    $lines
                );
            }

            $db->commit();
            return $txId;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Transfer funds between two bank accounts.
     * Creates two transactions (withdrawal + deposit) and a journal entry.
     */
    public function transfer(int $fromId, int $toId, float $amount, string $date, string $ref = ''): bool
    {
        $from   = $this->find($fromId);
        $to     = $this->find($toId);
        $amount = round($amount, 4);

        if (!$from || !$to) {
            throw new \RuntimeException('Invalid bank account(s) for transfer.');
        }
        if ($fromId === $toId) {
            throw new \RuntimeException('Cannot transfer to the same account.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Transfer amount must be positive.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $desc = 'Transfer to ' . $to['name'];
            $db->execute(
                "INSERT INTO bank_transactions
                 (tenant_id, bank_account_id, transaction_type, amount, transaction_date,
                  reference, description, status, created_at)
                 VALUES (:tid, :baid, 'withdrawal', :amt, :date, :ref, :desc, 'cleared', NOW())",
                [':tid' => $this->tenantId, ':baid' => $fromId, ':amt' => $amount,
                 ':date' => $date, ':ref' => $ref, ':desc' => $desc]
            );

            $desc2 = 'Transfer from ' . $from['name'];
            $db->execute(
                "INSERT INTO bank_transactions
                 (tenant_id, bank_account_id, transaction_type, amount, transaction_date,
                  reference, description, status, created_at)
                 VALUES (:tid, :baid, 'deposit', :amt, :date, :ref, :desc, 'cleared', NOW())",
                [':tid' => $this->tenantId, ':baid' => $toId, ':amt' => $amount,
                 ':date' => $date, ':ref' => $ref, ':desc' => $desc2]
            );

            // Journal entry: Dr destination bank, Cr source bank
            $jeModel = new JournalEntry();
            $jeModel->createWithLines(
                [
                    'entry_date'  => $date,
                    'reference'   => $ref ?: 'TRANSFER',
                    'description' => "Transfer: {$from['name']} → {$to['name']}",
                    'source_type' => 'bank_transfer',
                    'status'      => 'posted',
                ],
                [
                    ['account_id' => (int) $to['gl_account_id'],   'debit_amount' => $amount, 'credit_amount' => 0,      'description' => 'Transfer in'],
                    ['account_id' => (int) $from['gl_account_id'],  'debit_amount' => 0,       'credit_amount' => $amount, 'description' => 'Transfer out'],
                ]
            );

            $db->commit();
            return true;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get all bank accounts with their current balance.
     */
    public function getAllWithBalance(string $asOf = ''): array
    {
        $date    = $asOf ?: date('Y-m-d');
        $accounts = $this->all([], 'name');

        foreach ($accounts as &$account) {
            $account['current_balance'] = $this->getBalance((int) $account['id'], $date);
        }

        return $accounts;
    }

    /**
     * Get unreconciled transactions for a bank account.
     */
    public function getUnreconciled(int $bankAccountId): array
    {
        return $this->db()->fetchAll(
            "SELECT * FROM bank_transactions
             WHERE bank_account_id = :id AND tenant_id = :tid AND is_reconciled = 0
             ORDER BY transaction_date, id",
            [':id' => $bankAccountId, ':tid' => $this->tenantId]
        );
    }

    /**
     * Mark a list of transaction IDs as reconciled.
     *
     * @param int[] $transactionIds
     */
    public function markReconciled(int $bankAccountId, array $transactionIds, string $statementDate, float $statementBalance): bool
    {
        if (empty($transactionIds)) {
            return false;
        }

        $ids = implode(',', array_map('intval', $transactionIds));
        $this->db()->execute(
            "UPDATE bank_transactions
             SET is_reconciled = 1, reconciled_at = NOW()
             WHERE id IN ({$ids}) AND bank_account_id = :baid AND tenant_id = :tid",
            [':baid' => $bankAccountId, ':tid' => $this->tenantId]
        );

        // Record reconciliation header
        $this->db()->execute(
            "INSERT INTO bank_reconciliations
             (tenant_id, bank_account_id, statement_date, statement_balance,
              reconciled_at, created_at)
             VALUES (:tid, :baid, :sdate, :sbal, NOW(), NOW())",
            [
                ':tid'   => $this->tenantId,
                ':baid'  => $bankAccountId,
                ':sdate' => $statementDate,
                ':sbal'  => round($statementBalance, 4),
            ]
        );

        return true;
    }
}
