<?php
/**
 * YingLume Finance — Authentication Helper
 *
 * Static helper for session-based multi-tenant authentication.
 * Handles login/logout, session regeneration, role checks, and
 * permission resolution using a simple role → permission map.
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class Auth
{
    /** Session key used to store the authenticated user data. */
    private const SESSION_KEY = '_yl_auth_user';

    /**
     * Role → permission map.
     * Extend as the permission set grows.
     *
     * @var array<string, string[]>
     */
    private static array $rolePermissions = [
        'owner'      => ['*'],  // wildcard — all permissions
        'admin'      => [
            'dashboard.view',
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'journal.view',  'journal.create',  'journal.edit',  'journal.post',
            'contacts.view', 'contacts.create', 'contacts.edit', 'contacts.delete',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.void',  'invoices.pay',
            'bills.view',    'bills.create',    'bills.edit',    'bills.pay',
            'banking.view',  'banking.create',  'banking.reconcile',
            'items.view',    'items.create',    'items.edit',    'items.delete',
            'reports.view',
            'settings.view', 'settings.edit',
            'users.view',    'users.invite',
            'tax.view',      'tax.create',      'tax.edit',
        ],
        'accountant' => [
            'dashboard.view',
            'accounts.view',
            'journal.view',  'journal.create',  'journal.edit',  'journal.post',
            'contacts.view', 'contacts.create', 'contacts.edit',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.pay',
            'bills.view',    'bills.create',    'bills.edit',    'bills.pay',
            'banking.view',  'banking.reconcile',
            'items.view',
            'reports.view',
            'tax.view',
        ],
        'viewer'     => [
            'dashboard.view',
            'accounts.view',
            'journal.view',
            'contacts.view',
            'invoices.view',
            'bills.view',
            'banking.view',
            'items.view',
            'reports.view',
        ],
    ];

    // ── Session Lifecycle ────────────────────────────────────────────────────

    /**
     * Persist user data in the session and regenerate the session ID
     * to prevent session fixation attacks.
     *
     * @param array<string, mixed> $user  Row from the `users` table (password excluded).
     */
    public static function login(array $user): void
    {
        // Never store the password in the session
        unset($user['password'], $user['remember_token']);

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user;
    }

    /**
     * Destroy the current session and clear the cookie.
     */
    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();
    }

    // ── Authentication State ─────────────────────────────────────────────────

    /**
     * Check whether a user is currently authenticated.
     */
    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]) &&
               is_array($_SESSION[self::SESSION_KEY]) &&
               !empty($_SESSION[self::SESSION_KEY]['id']);
    }

    /**
     * Return the full authenticated user array, or null if not logged in.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Return the authenticated user's primary ID, or null.
     */
    public static function id(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY]['id'])
            ? (int) $_SESSION[self::SESSION_KEY]['id']
            : null;
    }

    /**
     * Return the authenticated user's tenant ID, or null.
     */
    public static function tenantId(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY]['tenant_id'])
            ? (int) $_SESSION[self::SESSION_KEY]['tenant_id']
            : null;
    }

    // ── Role & Permission Checks ─────────────────────────────────────────────

    /**
     * Check whether the current user holds a specific role.
     *
     * @param  string|string[] $role  Single role or array of roles (OR logic).
     */
    public static function hasRole(string|array $role): bool
    {
        if (!self::check()) {
            return false;
        }

        $userRole = $_SESSION[self::SESSION_KEY]['role'] ?? '';
        $roles    = is_array($role) ? $role : [$role];

        return in_array($userRole, $roles, true);
    }

    /**
     * Check whether the current user holds a specific permission.
     * Owners (wildcard '*') always return true.
     *
     * @param  string $permission  Dot-notation permission, e.g. 'invoices.create'
     */
    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        $userRole = $_SESSION[self::SESSION_KEY]['role'] ?? '';

        $allowed = self::$rolePermissions[$userRole] ?? [];

        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($permission, $allowed, true);
    }

    // ── Credential Verification ──────────────────────────────────────────────

    /**
     * Attempt to authenticate a user by email and password.
     *
     * Checks for account lock-out before verifying the password.
     * Increments `login_attempts` on failure; resets on success.
     * Updates `last_login_at` on success.
     *
     * @param  string $email
     * @param  string $password  Plain-text password
     * @return array<string,mixed>|false  User row on success, false on failure.
     */
    public static function attempt(string $email, string $password): array|false
    {
        $db = Database::getInstance();

        $user = $db->selectOne(
            "SELECT * FROM `users`
              WHERE `email` = :email
                AND `status` = 'active'
              LIMIT 1",
            [':email' => strtolower(trim($email))]
        );

        if (!$user) {
            return false;
        }

        // Check account lock-out
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return false;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment attempts; lock after 5 failures for 15 minutes
            $attempts = (int) $user['login_attempts'] + 1;
            $lockedUntil = $attempts >= 5
                ? date('Y-m-d H:i:s', time() + 900)
                : null;

            $db->update(
                "UPDATE `users`
                    SET `login_attempts` = :attempts,
                        `locked_until`   = :locked
                  WHERE `id` = :id",
                [':attempts' => $attempts, ':locked' => $lockedUntil, ':id' => $user['id']]
            );

            return false;
        }

        // Success — reset attempts, update last_login_at
        $db->update(
            "UPDATE `users`
                SET `login_attempts`  = 0,
                    `locked_until`    = NULL,
                    `last_login_at`   = NOW()
              WHERE `id` = :id",
            [':id' => $user['id']]
        );

        // Strip sensitive fields before returning
        unset($user['password'], $user['remember_token']);

        // Log the user into session
        self::login($user);

        return $user;
    }
}
