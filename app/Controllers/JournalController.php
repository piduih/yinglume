<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

/**
 * JournalController
 * Double-entry General Journal management.
 */
class JournalController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $search = (string)$this->input('search', '');
        $status = (string)$this->input('status', '');
        $startDate = (string)$this->input('start_date', '');
        $endDate = (string)$this->input('end_date', '');

        $where = ['je.tenant_id = :tid'];
        $params = [':tid' => $tenantId];

        if ($search !== '') {
            $where[] = '(je.reference LIKE :search OR je.description LIKE :search2)';
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        if ($status === 'posted') {
            $where[] = 'je.is_posted = 1 AND je.is_void = 0';
        } elseif ($status === 'draft') {
            $where[] = 'je.is_posted = 0 AND je.is_void = 0';
        } elseif ($status === 'void') {
            $where[] = 'je.is_void = 1';
        }

        if ($startDate !== '') {
            $where[] = 'je.entry_date >= :start';
            $params[':start'] = $startDate;
        }

        if ($endDate !== '') {
            $where[] = 'je.entry_date <= :end';
            $params[':end'] = $endDate;
        }

        $sql = "SELECT 
                    je.*, 
                    u.name AS author_name,
                    COALESCE(SUM(jl.debit), 0) AS total_debit,
                    COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM journal_entries je
                LEFT JOIN users u ON je.created_by = u.id
                LEFT JOIN journal_lines jl ON je.id = jl.journal_entry_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY je.id
                ORDER BY je.entry_date DESC, je.id DESC";

        $entries = $db->select($sql, $params);

        $this->render('journal.index', [
            'pageTitle' => 'Journal Entries',
            'entries'   => $entries,
            'search'    => $search,
            'status'    => $status,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ], 'main');
    }

    public function create(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $accounts = $db->select(
            "SELECT ca.id, ca.code, ca.name, at.name AS type_name 
             FROM chart_of_accounts ca
             JOIN account_types at ON ca.account_type_id = at.id
             WHERE ca.tenant_id = :tid AND ca.is_active = 1
             ORDER BY ca.code ASC",
            [':tid' => $tenantId]
        );

        $this->render('journal.form', [
            'pageTitle' => 'New Journal Entry',
            'accounts'  => $accounts,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $entryDate   = (string)$this->input('entry_date');
        $reference   = (string)$this->input('reference', '');
        $description = (string)$this->input('description', '');
        $postDirect  = (bool)$this->input('post_direct', 0);

        $accountIds = $_POST['account_id'] ?? [];
        $lineDescs  = $_POST['line_description'] ?? [];
        $debits     = $_POST['debit'] ?? [];
        $credits    = $_POST['credit'] ?? [];

        if (empty($accountIds) || !is_array($accountIds)) {
            $this->flash('error', 'Journal entry must have at least two line items.');
            $this->redirect('/app/journal/create');
        }

        $totalDebit  = 0.0;
        $totalCredit = 0.0;
        $lines = [];

        for ($i = 0; $i < count($accountIds); $i++) {
            $aid = (int)($accountIds[$i] ?? 0);
            if ($aid <= 0) continue;

            $deb  = max(0.0, (float)($debits[$i] ?? 0));
            $cred = max(0.0, (float)($credits[$i] ?? 0));

            if ($deb == 0 && $cred == 0) continue;

            $totalDebit  += $deb;
            $totalCredit += $cred;

            $lines[] = [
                'account_id'  => $aid,
                'description' => (string)($lineDescs[$i] ?? ''),
                'debit'       => $deb,
                'credit'      => $cred,
            ];
        }

        if (count($lines) < 2) {
            $this->flash('error', 'A double-entry journal entry requires at least two valid lines.');
            $this->redirect('/app/journal/create');
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            $this->flash('error', sprintf('Debits (%.2f) and Credits (%.2f) must balance.', $totalDebit, $totalCredit));
            $this->redirect('/app/journal/create');
        }

        $db = Database::getInstance();
        $tenantId = Auth::tenantId();

        // Get current active fiscal year
        $fy = $db->selectOne("SELECT id FROM fiscal_years WHERE tenant_id = :tid AND is_closed = 0 ORDER BY id DESC LIMIT 1", [':tid' => $tenantId]);
        $fyId = $fy ? (int)$fy['id'] : 1;

        $db->beginTransaction();
        try {
            $uuid = generate_uuid();
            $jeId = (int)$db->insert(
                "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, is_posted, is_void, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'manual', :post, 0, :uid, NOW(), NOW())",
                [
                    ':uuid' => $uuid,
                    ':tid'  => $tenantId,
                    ':fy'   => $fyId,
                    ':ed'   => $entryDate ?: date('Y-m-d'),
                    ':ref'  => $reference ?: ('JE-' . date('Ymd-His')),
                    ':desc' => $description,
                    ':post' => $postDirect ? 1 : 0,
                    ':uid'  => Auth::id(),
                ]
            );

            foreach ($lines as $line) {
                $db->insert(
                    "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                     VALUES (:je, :aid, :desc, :deb, :cred, NOW())",
                    [
                        ':je'   => $jeId,
                        ':aid'  => $line['account_id'],
                        ':desc' => $line['description'],
                        ':deb'  => $line['debit'],
                        ':cred' => $line['credit'],
                    ]
                );
            }

            $db->commit();
            $this->flash('success', 'Journal entry saved successfully.');
            $this->redirect('/app/journal/' . $jeId);
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Error creating entry: ' . $e->getMessage());
            $this->redirect('/app/journal/create');
        }
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $entry = $db->selectOne(
            "SELECT je.*, u.name AS author_name 
             FROM journal_entries je
             LEFT JOIN users u ON je.created_by = u.id
             WHERE je.id = :id AND je.tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );

        if (!$entry) {
            $this->flash('error', 'Journal entry not found.');
            $this->redirect('/app/journal');
        }

        $lines = $db->select(
            "SELECT jl.*, ca.code, ca.name AS account_name, at.name AS type_name
             FROM journal_lines jl
             JOIN chart_of_accounts ca ON jl.account_id = ca.id
             JOIN account_types at ON ca.account_type_id = at.id
             WHERE jl.journal_entry_id = :id
             ORDER BY jl.id ASC",
            [':id' => $id]
        );

        $this->render('journal.show', [
            'pageTitle' => 'Journal Entry #' . ($entry['reference'] ?: $entry['id']),
            'entry'     => $entry,
            'lines'     => $lines,
        ], 'main');
    }

    public function post(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $db->update(
            "UPDATE journal_entries SET is_posted = 1, updated_at = NOW() WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );

        $this->flash('success', 'Journal entry has been posted.');
        $this->redirect('/app/journal/' . $id);
    }

    public function void(string $id): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $reason = (string)$this->input('void_reason', 'Voided by user');

        $db->update(
            "UPDATE journal_entries SET is_void = 1, void_reason = :reason, updated_at = NOW() WHERE id = :id AND tenant_id = :tid",
            [':reason' => $reason, ':id' => $id, ':tid' => $tenantId]
        );

        $this->flash('warning', 'Journal entry has been voided.');
        $this->redirect('/app/journal/' . $id);
    }
}
