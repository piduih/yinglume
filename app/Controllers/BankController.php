<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

/**
 * BankController
 * Bank accounts, cash flow transactions, transfers, and reconciliations.
 */
class BankController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $banks = $db->select(
            "SELECT ba.*, ca.code AS coa_code, ca.name AS coa_name,
                    ca.opening_balance + COALESCE(jl_agg.net_debit, 0) AS current_balance
             FROM bank_accounts ba
             JOIN chart_of_accounts ca ON ba.account_id = ca.id
             LEFT JOIN (
                 SELECT jl.account_id, SUM(jl.debit - jl.credit) AS net_debit
                 FROM journal_lines jl
                 JOIN journal_entries je ON jl.journal_entry_id = je.id
                 WHERE je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0
                 GROUP BY jl.account_id
             ) jl_agg ON ba.account_id = jl_agg.account_id
             WHERE ba.tenant_id = :tid AND ba.is_active = 1
             ORDER BY ba.is_default DESC, ba.name ASC",
            [':tid' => $tenantId]
        );

        $totalCash = 0.0;
        foreach ($banks as $b) {
            $totalCash += (float)$b['current_balance'];
        }

        $this->render('banking.index', [
            'pageTitle' => 'Banking & Cash Management',
            'banks'     => $banks,
            'totalCash' => $totalCash,
        ], 'main');
    }

    public function create(): void
    {
        $this->requireRole(['owner', 'admin']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        // Get asset accounts suitable for banking
        $accounts = $db->select(
            "SELECT id, code, name FROM chart_of_accounts 
             WHERE tenant_id = :tid AND account_type_id = 1 AND is_active = 1
             ORDER BY code ASC",
            [':tid' => $tenantId]
        );

        $this->render('banking.form', [
            'pageTitle' => 'Add Bank Account',
            'accounts'  => $accounts,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();

        $errors = $this->validate([
            'name'           => 'required|min:2|max:100',
            'account_number' => 'required|min:3|max:50',
            'bank_name'      => 'required|min:2|max:100',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/banking/create');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $accountId = $this->input('account_id') ? (int)$this->input('account_id') : null;
        if (!$accountId) {
            // Auto-create a COA account
            $lastCode = $db->selectOne(
                "SELECT code FROM chart_of_accounts WHERE tenant_id = :tid AND code LIKE '10%' ORDER BY code DESC LIMIT 1",
                [':tid' => $tenantId]
            );
            $newCode = (string)(($lastCode ? (int)$lastCode['code'] + 10 : 1010));

            $accountId = (int)$db->insert(
                "INSERT INTO chart_of_accounts (tenant_id, code, name, account_type_id, opening_balance, is_system, is_active, created_at, updated_at)
                 VALUES (:tid, :code, :name, 1, :open, 0, 1, NOW(), NOW())",
                [
                    ':tid'  => $tenantId,
                    ':code' => $newCode,
                    ':name' => (string)$this->input('name'),
                    ':open' => (float)$this->input('opening_balance', 0),
                ]
            );
        }

        $db->insert(
            "INSERT INTO bank_accounts (uuid, tenant_id, account_id, name, account_number, bank_name, bank_branch, swift_code, currency, opening_balance, opening_date, is_default, is_active, created_at, updated_at)
             VALUES (:uuid, :tid, :aid, :name, :acc, :bname, :branch, :swift, 'MYR', :open, :odate, :def, 1, NOW(), NOW())",
            [
                ':uuid'   => generate_uuid(),
                ':tid'    => $tenantId,
                ':aid'    => $accountId,
                ':name'   => (string)$this->input('name'),
                ':acc'    => (string)$this->input('account_number'),
                ':bname'  => (string)$this->input('bank_name'),
                ':branch' => (string)$this->input('bank_branch', ''),
                ':swift'  => (string)$this->input('swift_code', ''),
                ':open'   => (float)$this->input('opening_balance', 0),
                ':odate'  => (string)$this->input('opening_date', date('Y-m-d')),
                ':def'    => $this->input('is_default') ? 1 : 0,
            ]
        );

        $this->flash('success', 'Bank account added successfully.');
        $this->redirect('/app/banking');
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bank = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$bank) {
            $this->flash('error', 'Bank account not found.');
            $this->redirect('/app/banking');
        }

        // Transactions through GL
        $transactions = $db->select(
            "SELECT jl.*, je.entry_date, je.reference, je.description AS entry_description, je.source
             FROM journal_lines jl
             JOIN journal_entries je ON jl.journal_entry_id = je.id
             WHERE jl.account_id = :aid AND je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0
             ORDER BY je.entry_date DESC, je.id DESC LIMIT 100",
            [':aid' => $bank['account_id'], ':tid' => $tenantId]
        );

        // Running balance
        $currentBalance = (float)$bank['opening_balance'];
        $calcRows = $db->selectOne(
            "SELECT SUM(jl.debit - jl.credit) AS net
             FROM journal_lines jl
             JOIN journal_entries je ON jl.journal_entry_id = je.id
             WHERE jl.account_id = :aid AND je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0",
            [':aid' => $bank['account_id'], ':tid' => $tenantId]
        );
        $currentBalance += (float)($calcRows['net'] ?? 0);

        $this->render('banking.transactions', [
            'pageTitle'      => $bank['name'] . ' — Transactions',
            'bank'           => $bank,
            'transactions'   => $transactions,
            'currentBalance' => $currentBalance,
        ], 'main');
    }

    public function addTransaction(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bank = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$bank) {
            $this->flash('error', 'Bank account not found.');
            $this->redirect('/app/banking');
        }

        $type   = (string)$this->input('type'); // deposit or withdrawal
        $amount = (float)$this->input('amount');
        $date   = (string)$this->input('transaction_date', date('Y-m-d'));
        $desc   = (string)$this->input('description', 'Bank Transaction');
        $offsetAccId = (int)$this->input('offset_account_id');

        if ($amount <= 0 || !$offsetAccId) {
            $this->flash('error', 'Invalid amount or category account.');
            $this->redirect('/app/banking/' . $id);
        }

        $fy = $db->selectOne("SELECT id FROM fiscal_years WHERE tenant_id = :tid AND is_closed = 0 ORDER BY id DESC LIMIT 1", [':tid' => $tenantId]);
        $fyId = $fy ? (int)$fy['id'] : 1;

        $db->beginTransaction();
        try {
            $jeId = (int)$db->insert(
                "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, is_posted, is_void, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'manual', 1, 0, :uid, NOW(), NOW())",
                [
                    ':uuid' => generate_uuid(),
                    ':tid'  => $tenantId,
                    ':fy'   => $fyId,
                    ':ed'   => $date,
                    ':ref'  => 'BNK-' . date('Ymd-His'),
                    ':desc' => $desc,
                    ':uid'  => Auth::id(),
                ]
            );

            if ($type === 'deposit') {
                // Dr Bank, Cr Offset
                $db->insert("INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at) VALUES (:je, :aid, :desc, :amt, 0, NOW())", [':je' => $jeId, ':aid' => $bank['account_id'], ':desc' => $desc, ':amt' => $amount]);
                $db->insert("INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at) VALUES (:je, :aid, :desc, 0, :amt, NOW())", [':je' => $jeId, ':aid' => $offsetAccId, ':desc' => $desc, ':amt' => $amount]);
            } else {
                // Dr Offset, Cr Bank
                $db->insert("INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at) VALUES (:je, :aid, :desc, :amt, 0, NOW())", [':je' => $jeId, ':aid' => $offsetAccId, ':desc' => $desc, ':amt' => $amount]);
                $db->insert("INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at) VALUES (:je, :aid, :desc, 0, :amt, NOW())", [':je' => $jeId, ':aid' => $bank['account_id'], ':desc' => $desc, ':amt' => $amount]);
            }

            $db->commit();
            $this->flash('success', 'Transaction recorded successfully.');
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Error: ' . $e->getMessage());
        }

        $this->redirect('/app/banking/' . $id);
    }

    public function transfer(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $fromId = (int)$this->input('from_bank_id');
        $toId   = (int)$this->input('to_bank_id');
        $amount = (float)$this->input('amount');
        $date   = (string)$this->input('transfer_date', date('Y-m-d'));
        $ref    = (string)$this->input('reference', 'Transfer between accounts');

        if ($fromId === $toId || $amount <= 0) {
            $this->flash('error', 'Source and destination accounts must be different, and amount must be positive.');
            $this->redirect('/app/banking');
        }

        $fromBank = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $fromId, ':tid' => $tenantId]);
        $toBank   = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $toId, ':tid' => $tenantId]);

        if (!$fromBank || !$toBank) {
            $this->flash('error', 'Invalid bank accounts.');
            $this->redirect('/app/banking');
        }

        $fy = $db->selectOne("SELECT id FROM fiscal_years WHERE tenant_id = :tid AND is_closed = 0 ORDER BY id DESC LIMIT 1", [':tid' => $tenantId]);
        $fyId = $fy ? (int)$fy['id'] : 1;

        $db->beginTransaction();
        try {
            $jeId = (int)$db->insert(
                "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, is_posted, is_void, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'manual', 1, 0, :uid, NOW(), NOW())",
                [
                    ':uuid' => generate_uuid(),
                    ':tid'  => $tenantId,
                    ':fy'   => $fyId,
                    ':ed'   => $date,
                    ':ref'  => $ref,
                    ':desc' => 'Funds transfer: ' . $fromBank['name'] . ' -> ' . $toBank['name'],
                    ':uid'  => Auth::id(),
                ]
            );

            // Dr Destination Bank
            $db->insert("INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at) VALUES (:je, :aid, :desc, :amt, 0, NOW())", [':je' => $jeId, ':aid' => $toBank['account_id'], ':desc' => 'Transfer in from ' . $fromBank['name'], ':amt' => $amount]);
            // Cr Source Bank
            $db->insert("INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at) VALUES (:je, :aid, :desc, 0, :amt, NOW())", [':je' => $jeId, ':aid' => $fromBank['account_id'], ':desc' => 'Transfer out to ' . $toBank['name'], ':amt' => $amount]);

            $db->commit();
            $this->flash('success', 'Transfer completed successfully.');
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Transfer failed: ' . $e->getMessage());
        }

        $this->redirect('/app/banking');
    }

    public function reconcile(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bank = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$bank) {
            $this->flash('error', 'Bank account not found.');
            $this->redirect('/app/banking');
        }

        $this->render('banking.reconcile', [
            'pageTitle' => 'Bank Reconciliation — ' . $bank['name'],
            'bank'      => $bank,
        ], 'main');
    }
}
