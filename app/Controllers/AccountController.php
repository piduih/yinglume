<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Account;

/**
 * AccountController
 * Chart of Accounts management.
 */
class AccountController extends BaseController
{
    private Account $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->accountModel = new Account();
    }

    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $accounts = $db->select(
            "SELECT 
                ca.*, 
                at.name AS type_name, 
                at.normal_balance,
                p.name AS parent_name,
                COALESCE(SUM(jl.debit), 0) AS total_debit,
                COALESCE(SUM(jl.credit), 0) AS total_credit
             FROM chart_of_accounts ca
             JOIN account_types at ON ca.account_type_id = at.id
             LEFT JOIN chart_of_accounts p ON ca.parent_id = p.id
             LEFT JOIN journal_lines jl ON ca.id = jl.account_id
             LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id 
                AND je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0
             WHERE ca.tenant_id = :tid
             GROUP BY ca.id, at.name, at.normal_balance, p.name
             ORDER BY ca.code ASC",
            [':tid' => $tenantId]
        );

        // Group by account type
        $grouped = [];
        foreach ($accounts as $a) {
            $opening = (float)$a['opening_balance'];
            if ($a['normal_balance'] === 'debit') {
                $balance = $opening + (float)$a['total_debit'] - (float)$a['total_credit'];
            } else {
                $balance = $opening + (float)$a['total_credit'] - (float)$a['total_debit'];
            }
            $a['current_balance'] = $balance;
            $grouped[$a['type_name']][] = $a;
        }

        $this->render('accounts.index', [
            'pageTitle' => 'Chart of Accounts',
            'grouped'   => $grouped,
        ], 'main');
    }

    public function create(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $db = Database::getInstance();
        $tenantId = Auth::tenantId();

        $types = $db->select("SELECT * FROM account_types ORDER BY display_order ASC");
        $parents = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid ORDER BY code ASC", [':tid' => $tenantId]);

        $this->render('accounts.form', [
            'pageTitle' => 'New Account',
            'account'   => null,
            'types'     => $types,
            'parents'   => $parents,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $errors = $this->validate([
            'code'            => 'required|min:2|max:20',
            'name'            => 'required|min:2|max:150',
            'account_type_id' => 'required|integer',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/accounts/create');
        }

        $db = Database::getInstance();
        $tenantId = Auth::tenantId();
        $code = (string)$this->input('code');

        // Check uniqueness within tenant
        $exists = $db->selectOne(
            "SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = :code",
            [':tid' => $tenantId, ':code' => $code]
        );
        if ($exists) {
            $this->flash('error', 'Account code already exists.');
            $this->redirect('/app/accounts/create');
        }

        $parentId = $this->input('parent_id') ? (int)$this->input('parent_id') : null;

        $db->insert(
            "INSERT INTO chart_of_accounts (tenant_id, code, name, account_type_id, parent_id, description, opening_balance, is_active, created_at, updated_at)
             VALUES (:tid, :code, :name, :type, :parent, :desc, :open, 1, NOW(), NOW())",
            [
                ':tid'    => $tenantId,
                ':code'   => $code,
                ':name'   => (string)$this->input('name'),
                ':type'   => (int)$this->input('account_type_id'),
                ':parent' => $parentId,
                ':desc'   => (string)$this->input('description', ''),
                ':open'   => (float)$this->input('opening_balance', 0),
            ]
        );

        $this->flash('success', 'Account created successfully.');
        $this->redirect('/app/accounts');
    }

    public function edit(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $account = $db->selectOne("SELECT * FROM chart_of_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$account) {
            $this->flash('error', 'Account not found.');
            $this->redirect('/app/accounts');
        }

        $types = $db->select("SELECT * FROM account_types ORDER BY display_order ASC");
        $parents = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND id != :id ORDER BY code ASC", [':tid' => $tenantId, ':id' => $id]);

        $this->render('accounts.form', [
            'pageTitle' => 'Edit Account — ' . $account['name'],
            'account'   => $account,
            'types'     => $types,
            'parents'   => $parents,
        ], 'main');
    }

    public function update(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $errors = $this->validate([
            'code'            => 'required|min:2|max:20',
            'name'            => 'required|min:2|max:150',
            'account_type_id' => 'required|integer',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/accounts/' . $id . '/edit');
        }

        $db = Database::getInstance();
        $tenantId = Auth::tenantId();
        $code = (string)$this->input('code');

        $exists = $db->selectOne(
            "SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = :code AND id != :id",
            [':tid' => $tenantId, ':code' => $code, ':id' => $id]
        );
        if ($exists) {
            $this->flash('error', 'Account code already used by another account.');
            $this->redirect('/app/accounts/' . $id . '/edit');
        }

        $parentId = $this->input('parent_id') ? (int)$this->input('parent_id') : null;

        $db->update(
            "UPDATE chart_of_accounts 
             SET code = :code, name = :name, account_type_id = :type, parent_id = :parent, description = :desc, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid",
            [
                ':code'   => $code,
                ':name'   => (string)$this->input('name'),
                ':type'   => (int)$this->input('account_type_id'),
                ':parent' => $parentId,
                ':desc'   => (string)$this->input('description', ''),
                ':id'     => $id,
                ':tid'    => $tenantId,
            ]
        );

        $this->flash('success', 'Account updated successfully.');
        $this->redirect('/app/accounts');
    }

    public function deactivate(string $id): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $account = $db->selectOne("SELECT * FROM chart_of_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if ($account && $account['is_system']) {
            $this->flash('error', 'System core accounts cannot be deactivated.');
            $this->redirect('/app/accounts');
        }

        $newStatus = $account['is_active'] ? 0 : 1;
        $db->update(
            "UPDATE chart_of_accounts SET is_active = :status WHERE id = :id AND tenant_id = :tid",
            [':status' => $newStatus, ':id' => $id, ':tid' => $tenantId]
        );

        $this->flash('success', 'Account status updated.');
        $this->redirect('/app/accounts');
    }

    public function ajax_search(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $q = '%' . (string)$this->input('q', '') . '%';

        $db = Database::getInstance();
        $accounts = $db->select(
            "SELECT ca.id, ca.code, ca.name, at.name AS type_name 
             FROM chart_of_accounts ca
             JOIN account_types at ON ca.account_type_id = at.id
             WHERE ca.tenant_id = :tid AND ca.is_active = 1 AND (ca.code LIKE :q1 OR ca.name LIKE :q2)
             ORDER BY ca.code ASC LIMIT 25",
            [':tid' => $tenantId, ':q1' => $q, ':q2' => $q]
        );

        $this->json(['accounts' => $accounts]);
    }
}
