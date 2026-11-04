<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Tenant Model
 * Represents a SaaS tenant (company/organisation).
 * Note: Tenant itself does NOT filter by tenant_id (it IS the tenant record).
 */
class Tenant extends BaseModel
{
    protected static string $table      = 'tenants';
    protected static string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // Override base methods — Tenants are global (no tenant_id scoping on self)
    // -------------------------------------------------------------------------

    public function find(int $id): ?array
    {
        return $this->db()->fetchOne(
            "SELECT * FROM tenants WHERE id = :id LIMIT 1",
            [':id' => $id]
        ) ?: null;
    }

    // -------------------------------------------------------------------------
    // Tenant-specific lookups
    // -------------------------------------------------------------------------

    /**
     * Find a tenant by its unique slug.
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db()->fetchOne(
            "SELECT * FROM tenants WHERE slug = :slug LIMIT 1",
            [':slug' => $slug]
        ) ?: null;
    }

    /**
     * Find a tenant by UUID.
     */
    public function findByUuid(string $uuid): ?array
    {
        return $this->db()->fetchOne(
            "SELECT * FROM tenants WHERE uuid = :uuid LIMIT 1",
            [':uuid' => $uuid]
        ) ?: null;
    }

    // -------------------------------------------------------------------------
    // Settings (stored as JSON in settings column)
    // -------------------------------------------------------------------------

    /**
     * Get a specific setting value with optional default.
     */
    public function getSetting(int $tenantId, string $key, mixed $default = null): mixed
    {
        $row = $this->db()->fetchOne(
            "SELECT settings FROM tenants WHERE id = :id LIMIT 1",
            [':id' => $tenantId]
        );

        if (!$row || empty($row['settings'])) {
            return $default;
        }

        $settings = json_decode($row['settings'], true) ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Update a single setting key within the JSON blob.
     */
    public function updateSetting(int $tenantId, string $key, mixed $value): bool
    {
        $row = $this->db()->fetchOne(
            "SELECT settings FROM tenants WHERE id = :id LIMIT 1",
            [':id' => $tenantId]
        );

        $settings       = json_decode($row['settings'] ?? '{}', true) ?? [];
        $settings[$key] = $value;

        $this->db()->execute(
            "UPDATE tenants SET settings = :settings, updated_at = NOW() WHERE id = :id",
            [':settings' => json_encode($settings), ':id' => $tenantId]
        );

        return true;
    }

    /**
     * Update multiple settings at once.
     *
     * @param array<string, mixed> $newSettings
     */
    public function updateSettings(int $tenantId, array $newSettings): bool
    {
        $row      = $this->db()->fetchOne(
            "SELECT settings FROM tenants WHERE id = :id LIMIT 1",
            [':id' => $tenantId]
        );
        $existing = json_decode($row['settings'] ?? '{}', true) ?? [];
        $merged   = array_merge($existing, $newSettings);

        $this->db()->execute(
            "UPDATE tenants SET settings = :settings, updated_at = NOW() WHERE id = :id",
            [':settings' => json_encode($merged), ':id' => $tenantId]
        );

        return true;
    }

    /**
     * Create a brand-new tenant record. Returns tenant ID.
     */
    public function createTenant(string $name, string $slug, string $plan = 'free'): int
    {
        $uuid = $this->generateUuid();
        $this->db()->execute(
            "INSERT INTO tenants (uuid, name, slug, plan, status, settings, created_at, updated_at)
             VALUES (:uuid, :name, :slug, :plan, 'active', '{}', NOW(), NOW())",
            [':uuid' => $uuid, ':name' => $name, ':slug' => $slug, ':plan' => $plan]
        );
        return (int) $this->db()->lastInsertId();
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
