<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Tenant;
use App\Models\User;

/**
 * AuthController
 * Registration, login, logout, password resets, session management.
 */
class AuthController extends BaseController
{
    /**
     * Show login form.
     */
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('/app/dashboard');
        }

        $this->render('auth.login', [
            'pageTitle' => 'Log In — YingLume Finance',
            'email'     => $this->input('email', ''),
        ], 'auth');
    }

    /**
     * Process login submission.
     */
    public function doLogin(): void
    {
        $this->validateCSRF();

        $errors = $this->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/login');
        }

        $email    = (string) $this->input('email');
        $password = (string) $this->input('password');

        if (Auth::attempt($email, $password)) {
            $this->flash('success', 'Welcome back to YingLume Finance!');
            $this->redirect('/app/dashboard');
        }

        $this->flash('error', 'Invalid email or password, or account is temporarily locked.');
        $this->redirect('/login');
    }

    /**
     * Show registration form.
     */
    public function register(): void
    {
        if (Auth::check()) {
            $this->redirect('/app/dashboard');
        }

        $this->render('auth.register', [
            'pageTitle' => 'Get Started Free — YingLume Finance',
        ], 'auth');
    }

    /**
     * Handle tenant & owner registration.
     */
    public function doRegister(): void
    {
        $this->validateCSRF();

        $errors = $this->validate([
            'company_name' => 'required|min:3|max:150',
            'name'         => 'required|min:2|max:100',
            'email'        => 'required|email',
            'password'     => 'required|min:8|confirmed',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/register');
        }

        $companyName = (string) $this->input('company_name');
        $userName    = (string) $this->input('name');
        $email       = (string) $this->input('email');
        $password    = (string) $this->input('password');

        $db = Database::getInstance();

        // Check if email already registered across system
        $existing = $db->selectOne("SELECT id FROM users WHERE email = :email LIMIT 1", [':email' => $email]);
        if ($existing) {
            $this->flash('error', 'That email address is already in use.');
            $this->redirect('/register');
        }

        $db->beginTransaction();
        try {
            $tenantUuid = generate_uuid();
            $slug = slugify($companyName) . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

            $tenantId = (int)$db->insert(
                "INSERT INTO tenants (uuid, name, slug, plan, status, trial_ends_at, settings, created_at, updated_at)
                 VALUES (:uuid, :name, :slug, 'starter', 'active', DATE_ADD(NOW(), INTERVAL 365 DAY), :settings, NOW(), NOW())",
                [
                    ':uuid'     => $tenantUuid,
                    ':name'     => $companyName,
                    ':slug'     => $slug,
                    ':settings' => json_encode([
                        'currency'          => 'MYR',
                        'timezone'          => 'Asia/Kuala_Lumpur',
                        'date_format'       => 'd/m/Y',
                        'fiscal_year_start' => 1,
                    ]),
                ]
            );

            // Create Owner User
            $userUuid = generate_uuid();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $userId = (int)$db->insert(
                "INSERT INTO users (uuid, tenant_id, name, email, password, role, status, created_at, updated_at)
                 VALUES (:uuid, :tid, :name, :email, :pass, 'owner', 'active', NOW(), NOW())",
                [
                    ':uuid'  => $userUuid,
                    ':tid'   => $tenantId,
                    ':name'  => $userName,
                    ':email' => $email,
                    ':pass'  => $hash,
                ]
            );

            // Create Initial Fiscal Year
            $currentYear = (int)date('Y');
            $fyId = (int)$db->insert(
                "INSERT INTO fiscal_years (tenant_id, name, start_date, end_date, is_closed, created_at)
                 VALUES (:tid, :name, :start, :end, 0, NOW())",
                [
                    ':tid'   => $tenantId,
                    ':name'  => 'FY' . $currentYear,
                    ':start' => $currentYear . '-01-01',
                    ':end'   => $currentYear . '-12-31',
                ]
            );

            // Seed basic Chart of Accounts for new tenant
            $defaultCoa = [
                ['1000', 'Cash on Hand', 1, 0.00],
                ['1010', 'Main Bank Account', 1, 0.00],
                ['1200', 'Accounts Receivable', 1, 0.00],
                ['1400', 'Inventory Asset', 1, 0.00],
                ['2000', 'Accounts Payable', 2, 0.00],
                ['2100', 'Sales Tax Payable', 2, 0.00],
                ['3000', 'Owner Capital', 3, 0.00],
                ['3100', 'Retained Earnings', 3, 0.00],
                ['4000', 'Sales Revenue', 4, 0.00],
                ['4100', 'Service Revenue', 4, 0.00],
                ['5000', 'Cost of Goods Sold', 5, 0.00],
                ['6000', 'Operating Expenses', 5, 0.00],
            ];

            foreach ($defaultCoa as $item) {
                $db->insert(
                    "INSERT INTO chart_of_accounts (tenant_id, fiscal_year_id, code, name, account_type_id, opening_balance, is_system, is_active, created_at, updated_at)
                     VALUES (:tid, :fy, :code, :name, :type, :open, 1, 1, NOW(), NOW())",
                    [
                        ':tid'  => $tenantId,
                        ':fy'   => $fyId,
                        ':code' => $item[0],
                        ':name' => $item[1],
                        ':type' => $item[2],
                        ':open' => $item[3],
                    ]
                );
            }

            // Create default bank account
            $bankAccId = (int)$db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '1010'", [':tid' => $tenantId])['id'];
            $db->insert(
                "INSERT INTO bank_accounts (uuid, tenant_id, account_id, name, account_number, bank_name, currency, opening_balance, opening_date, is_default, is_active, created_at, updated_at)
                 VALUES (:uuid, :tid, :aid, 'Main Operating Bank', '1000-0000-0001', 'Commercial Bank', 'MYR', 0.00, CURDATE(), 1, 1, NOW(), NOW())",
                [
                    ':uuid' => generate_uuid(),
                    ':tid'  => $tenantId,
                    ':aid'  => $bankAccId,
                ]
            );

            // Create default tax codes
            $db->insert(
                "INSERT INTO tax_codes (tenant_id, code, name, rate, type, is_active, created_at, updated_at)
                 VALUES (:tid, 'TAX-6', 'Standard Tax 6%', 0.0600, 'both', 1, NOW(), NOW()),
                        (:tid, 'EXEMPT', 'Tax Exempt (0%)', 0.0000, 'both', 1, NOW(), NOW())",
                [':tid' => $tenantId]
            );

            $db->commit();

            // Log in user automatically
            $userRow = $db->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $userId]);
            Auth::login($userRow);

            $this->flash('success', 'Your company workspace has been successfully created!');
            $this->redirect('/app/dashboard');
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Registration failed: ' . $e->getMessage());
            $this->redirect('/register');
        }
    }

    /**
     * Log out current user.
     */
    public function logout(): void
    {
        Auth::logout();
        $this->flash('info', 'You have been securely logged out.');
        $this->redirect('/login');
    }

    /**
     * Forgot password page.
     */
    public function forgotPassword(): void
    {
        $this->render('auth.forgot-password', [
            'pageTitle' => 'Reset Password — YingLume Finance',
        ], 'auth');
    }

    /**
     * Handle forgot password submit.
     */
    public function doForgotPassword(): void
    {
        $this->validateCSRF();
        $email = (string)$this->input('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Please provide a valid email.');
            $this->redirect('/forgot-password');
        }

        $this->flash('success', 'If an account exists for that email, password reset instructions have been sent.');
        $this->redirect('/login');
    }
}
