<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

/**
 * SettingsController
 * Company profile, user management & RBAC, tax rules, and fiscal year closure.
 */
class SettingsController extends BaseController
{
    public function index(): void
    {
        $this->requireRole(['owner', 'admin']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $tenant = $db->selectOne("SELECT * FROM tenants WHERE id = :tid", [':tid' => $tenantId]);
        $settings = json_decode($tenant['settings'] ?? '{}', true) ?: [];

        $this->render('settings.index', [
            'pageTitle' => 'Company & System Settings',
            'tenant'    => $tenant,
            'settings'  => $settings,
        ], 'main');
    }

    public function update(): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();

        $errors = $this->validate([
            'name' => 'required|min:2|max:150',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/settings');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $settings = [
            'currency'          => (string)$this->input('currency', 'MYR'),
            'timezone'          => (string)$this->input('timezone', 'Asia/Kuala_Lumpur'),
            'date_format'       => (string)$this->input('date_format', 'd/m/Y'),
            'fiscal_year_start' => (int)$this->input('fiscal_year_start', 1),
            'address'           => (string)$this->input('address', ''),
            'phone'             => (string)$this->input('phone', ''),
            'email'             => (string)$this->input('email', ''),
            'tax_number'        => (string)$this->input('tax_number', ''),
        ];

        $db->update(
            "UPDATE tenants SET name = :name, settings = :settings, updated_at = NOW() WHERE id = :tid",
            [
                ':name'     => (string)$this->input('name'),
                ':settings' => json_encode($settings),
                ':tid'      => $tenantId,
            ]
        );

        $this->flash('success', 'Company profile and settings updated.');
        $this->redirect('/app/settings');
    }

    public function users(): void
    {
        $this->requireRole(['owner', 'admin']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $users = $db->select("SELECT * FROM users WHERE tenant_id = :tid ORDER BY id ASC", [':tid' => $tenantId]);

        $this->render('settings.users', [
            'pageTitle' => 'User Management & Permissions',
            'users'     => $users,
        ], 'main');
    }

    public function inviteUser(): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();

        $errors = $this->validate([
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email',
            'role'     => 'required|in:admin,accountant,viewer',
            'password' => 'required|min:8',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/settings/users');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();
        $email = (string)$this->input('email');

        $exists = $db->selectOne("SELECT id FROM users WHERE email = :email LIMIT 1", [':email' => $email]);
        if ($exists) {
            $this->flash('error', 'That email address is already in use.');
            $this->redirect('/app/settings/users');
        }

        $db->insert(
            "INSERT INTO users (uuid, tenant_id, name, email, password, role, status, created_at, updated_at)
             VALUES (:uuid, :tid, :name, :email, :pass, :role, 'active', NOW(), NOW())",
            [
                ':uuid'  => generate_uuid(),
                ':tid'   => $tenantId,
                ':name'  => (string)$this->input('name'),
                ':email' => $email,
                ':pass'  => password_hash((string)$this->input('password'), PASSWORD_BCRYPT),
                ':role'  => (string)$this->input('role'),
            ]
        );

        $this->flash('success', "User {$email} added successfully.");
        $this->redirect('/app/settings/users');
    }

    public function updateUser(string $id): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $role   = (string)$this->input('role');
        $status = (string)$this->input('status');

        if (!in_array($role, ['admin', 'accountant', 'viewer'], true) || !in_array($status, ['active', 'inactive'], true)) {
            $this->flash('error', 'Invalid role or status.');
            $this->redirect('/app/settings/users');
        }

        $user = $db->selectOne("SELECT * FROM users WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if ($user && $user['role'] === 'owner') {
            $this->flash('error', 'Owner role and status cannot be modified.');
            $this->redirect('/app/settings/users');
        }

        $db->update(
            "UPDATE users SET role = :role, status = :status, updated_at = NOW() WHERE id = :id AND tenant_id = :tid",
            [':role' => $role, ':status' => $status, ':id' => $id, ':tid' => $tenantId]
        );

        $this->flash('success', 'User profile updated.');
        $this->redirect('/app/settings/users');
    }

    public function removeUser(string $id): void
    {
        $this->requireRole(['owner']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $user = $db->selectOne("SELECT * FROM users WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$user || $user['role'] === 'owner') {
            $this->flash('error', 'Owner account cannot be deleted.');
            $this->redirect('/app/settings/users');
        }

        $db->delete("DELETE FROM users WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        $this->flash('success', 'User removed successfully.');
        $this->redirect('/app/settings/users');
    }

    public function tax(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $taxCodes = $db->select("SELECT * FROM tax_codes WHERE tenant_id = :tid ORDER BY code ASC", [':tid' => $tenantId]);

        $this->render('settings.tax', [
            'pageTitle' => 'Tax Settings & Rates',
            'taxCodes'  => $taxCodes,
        ], 'main');
    }

    public function saveTax(): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();

        $errors = $this->validate([
            'code' => 'required|min:2|max:20',
            'name' => 'required|min:2|max:100',
            'rate' => 'numeric',
            'type' => 'required|in:sales,purchase,both',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/settings/tax');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();
        $code = (string)$this->input('code');
        $rate = (float)$this->input('rate') / 100.0; // convert 6 to 0.06

        $id = (int)$this->input('id', 0);
        if ($id > 0) {
            $db->update(
                "UPDATE tax_codes SET code = :code, name = :name, rate = :rate, type = :type, updated_at = NOW() WHERE id = :id AND tenant_id = :tid",
                [':code' => $code, ':name' => (string)$this->input('name'), ':rate' => $rate, ':type' => (string)$this->input('type'), ':id' => $id, ':tid' => $tenantId]
            );
        } else {
            $db->insert(
                "INSERT INTO tax_codes (tenant_id, code, name, rate, type, is_active, created_at, updated_at)
                 VALUES (:tid, :code, :name, :rate, :type, 1, NOW(), NOW())",
                [':tid' => $tenantId, ':code' => $code, ':name' => (string)$this->input('name'), ':rate' => $rate, ':type' => (string)$this->input('type')]
            );
        }

        $this->flash('success', 'Tax code saved successfully.');
        $this->redirect('/app/settings/tax');
    }

    public function fiscalYear(): void
    {
        $this->requireRole(['owner', 'admin']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $years = $db->select("SELECT * FROM fiscal_years WHERE tenant_id = :tid ORDER BY start_date DESC", [':tid' => $tenantId]);

        $this->render('settings.fiscal-year', [
            'pageTitle' => 'Fiscal Years & Accounting Periods',
            'years'     => $years,
        ], 'main');
    }

    public function saveFiscalYear(): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();

        $errors = $this->validate([
            'name'       => 'required|min:3|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/settings/fiscal-year');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $db->insert(
            "INSERT INTO fiscal_years (tenant_id, name, start_date, end_date, is_closed, created_at)
             VALUES (:tid, :name, :start, :end, 0, NOW())",
            [
                ':tid'   => $tenantId,
                ':name'  => (string)$this->input('name'),
                ':start' => (string)$this->input('start_date'),
                ':end'   => (string)$this->input('end_date'),
            ]
        );

        $this->flash('success', 'New fiscal year period created.');
        $this->redirect('/app/settings/fiscal-year');
    }
}
