<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * User Model
 * Represents an authenticated user belonging to a tenant.
 */
class User extends BaseModel
{
    protected static string $table      = 'users';
    protected static string $primaryKey = 'id';

    /** Max login attempts before account is locked */
    private const MAX_ATTEMPTS   = 5;
    /** Lock duration in minutes */
    private const LOCK_MINUTES   = 15;

    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /**
     * Find a user by email address (global — email is unique across system).
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db()->fetchOne(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            [':email' => strtolower(trim($email))]
        ) ?: null;
    }

    /**
     * Find a user by email within a specific tenant.
     */
    public function findByEmailAndTenant(string $email, int $tenantId): ?array
    {
        return $this->db()->fetchOne(
            "SELECT * FROM users WHERE email = :email AND tenant_id = :tid LIMIT 1",
            [':email' => strtolower(trim($email)), ':tid' => $tenantId]
        ) ?: null;
    }

    /**
     * Find a user by password-reset token.
     */
    public function findByResetToken(string $token): ?array
    {
        return $this->db()->fetchOne(
            "SELECT * FROM users WHERE reset_token = :token AND reset_expires_at > NOW() LIMIT 1",
            [':token' => $token]
        ) ?: null;
    }

    // -------------------------------------------------------------------------
    // Brute-force protection
    // -------------------------------------------------------------------------

    /**
     * Check whether a user account is currently locked out.
     */
    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return strtotime($user['locked_until']) > time();
    }

    /**
     * Increment failed login attempts. Auto-locks after MAX_ATTEMPTS.
     */
    public function incrementLoginAttempts(int $userId): void
    {
        $this->db()->execute(
            "UPDATE users
             SET login_attempts = login_attempts + 1,
                 locked_until   = IF(login_attempts + 1 >= :max,
                                     DATE_ADD(NOW(), INTERVAL :mins MINUTE),
                                     locked_until),
                 updated_at     = NOW()
             WHERE id = :id",
            [':max' => self::MAX_ATTEMPTS, ':mins' => self::LOCK_MINUTES, ':id' => $userId]
        );
    }

    /**
     * Reset login attempt counter and clear any lock.
     */
    public function resetLoginAttempts(int $userId): void
    {
        $this->db()->execute(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id",
            [':id' => $userId]
        );
    }

    /**
     * Manually lock a user for $minutes.
     */
    public function lock(int $userId, int $minutes = self::LOCK_MINUTES): void
    {
        $this->db()->execute(
            "UPDATE users
             SET locked_until = DATE_ADD(NOW(), INTERVAL :mins MINUTE), updated_at = NOW()
             WHERE id = :id",
            [':mins' => $minutes, ':id' => $userId]
        );
    }

    // -------------------------------------------------------------------------
    // User management
    // -------------------------------------------------------------------------

    /**
     * Create a new user record. Returns the new user ID.
     */
    public function createUser(array $data): int
    {
        $uuid = $this->generateUuid();
        $this->db()->execute(
            "INSERT INTO users
             (uuid, tenant_id, name, email, password_hash, role, status,
              login_attempts, created_at, updated_at)
             VALUES
             (:uuid, :tid, :name, :email, :pass, :role, 'active', 0, NOW(), NOW())",
            [
                ':uuid' => $uuid,
                ':tid'  => $data['tenant_id'],
                ':name' => $data['name'],
                ':email'=> strtolower(trim($data['email'])),
                ':pass' => $data['password_hash'],
                ':role' => $data['role'] ?? 'owner',
            ]
        );
        return (int) $this->db()->lastInsertId();
    }

    /**
     * Store a password-reset token for a user (expires in 60 minutes).
     */
    public function storeResetToken(int $userId, string $token): void
    {
        $this->db()->execute(
            "UPDATE users
             SET reset_token = :token,
                 reset_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE),
                 updated_at = NOW()
             WHERE id = :id",
            [':token' => $token, ':id' => $userId]
        );
    }

    /**
     * Update a user's password and clear reset token.
     */
    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->db()->execute(
            "UPDATE users
             SET password_hash = :hash, reset_token = NULL,
                 reset_expires_at = NULL, updated_at = NOW()
             WHERE id = :id",
            [':hash' => $passwordHash, ':id' => $userId]
        );
    }

    /**
     * List all users belonging to a tenant.
     */
    public function getByTenant(int $tenantId): array
    {
        return $this->db()->fetchAll(
            "SELECT id, uuid, name, email, role, status, created_at FROM users WHERE tenant_id = :tid ORDER BY name",
            [':tid' => $tenantId]
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
