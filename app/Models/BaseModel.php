<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * BaseModel
 * Abstract base for all Eloquent-lite models.
 * All queries automatically scope to the current tenant via tenant_id.
 */
abstract class BaseModel
{
    /** @var string DB table name — must be overridden by child classes */
    protected static string $table;

    /** @var string Primary key column name */
    protected static string $primaryKey = 'id';

    /** Current tenant ID, auto-set from Auth in constructor */
    protected int $tenantId;

    public function __construct()
    {
        $this->tenantId = Auth::tenantId();
    }

    // -------------------------------------------------------------------------
    // DB helper
    // -------------------------------------------------------------------------

    protected function db(): Database
    {
        return Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Core CRUD
    // -------------------------------------------------------------------------

    /**
     * Find a single record by primary key, scoped to current tenant.
     */
    public function find(int $id): ?array
    {
        $table = static::$table;
        $pk    = static::$primaryKey;

        return $this->db()->fetchOne(
            "SELECT * FROM `{$table}` WHERE `{$pk}` = :id AND tenant_id = :tid LIMIT 1",
            [':id' => $id, ':tid' => $this->tenantId]
        ) ?: null;
    }

    /**
     * Find a record by an arbitrary column, scoped to tenant.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $table  = static::$table;
        $column = $this->safeColumn($column);

        return $this->db()->fetchOne(
            "SELECT * FROM `{$table}` WHERE `{$column}` = :val AND tenant_id = :tid LIMIT 1",
            [':val' => $value, ':tid' => $this->tenantId]
        ) ?: null;
    }

    /**
     * Return all records matching optional conditions, scoped to tenant.
     *
     * @param array<string, mixed> $conditions  Column => value pairs (AND joined)
     * @param string $orderBy  Optional ORDER BY clause (e.g. 'name ASC')
     */
    public function all(array $conditions = [], string $orderBy = ''): array
    {
        $table  = static::$table;
        $where  = ['tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];
        $i      = 0;

        foreach ($conditions as $col => $val) {
            $placeholder          = ':cond' . $i++;
            $col                  = $this->safeColumn($col);
            $where[]              = "`{$col}` = {$placeholder}";
            $params[$placeholder] = $val;
        }

        $sql = "SELECT * FROM `{$table}` WHERE " . implode(' AND ', $where);
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return $this->db()->fetchAll($sql, $params);
    }

    /**
     * Insert a new record.  tenant_id is automatically injected.
     *
     * @param  array<string, mixed> $data
     * @return int  Last insert ID
     */
    public function create(array $data): int
    {
        $table = static::$table;
        $data['tenant_id']  = $this->tenantId;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');

        $columns = array_keys($data);
        $safeColumns = array_map(fn($c) => '`' . $this->safeColumn($c) . '`', $columns);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = "INSERT INTO `{$table}` (" . implode(', ', $safeColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }

        $this->db()->execute($sql, $params);
        return (int) $this->db()->lastInsertId();
    }

    /**
     * Update a record by primary key, scoped to tenant.
     *
     * @param  array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $table = static::$table;
        $pk    = static::$primaryKey;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $sets   = [];
        $params = [':pk' => $id, ':tid' => $this->tenantId];

        foreach ($data as $k => $v) {
            $placeholder          = ':upd_' . $k;
            $sets[]               = '`' . $this->safeColumn($k) . '` = ' . $placeholder;
            $params[$placeholder] = $v;
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets)
             . " WHERE `{$pk}` = :pk AND tenant_id = :tid";

        $this->db()->execute($sql, $params);
        return true;
    }

    /**
     * Delete a record by primary key, scoped to tenant.
     * Note: Prefer soft-delete patterns over hard delete for financial records.
     */
    public function delete(int $id): bool
    {
        $table = static::$table;
        $pk    = static::$primaryKey;

        $this->db()->execute(
            "DELETE FROM `{$table}` WHERE `{$pk}` = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $this->tenantId]
        );
        return true;
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    /**
     * Paginate records.
     *
     * @param int   $page
     * @param int   $perPage
     * @param array $conditions  Column => value pairs
     * @param string $orderBy
     * @return array{data: array, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginate(int $page, int $perPage = 20, array $conditions = [], string $orderBy = ''): array
    {
        $table  = static::$table;
        $where  = ['tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];
        $i      = 0;

        foreach ($conditions as $col => $val) {
            $placeholder          = ':cond' . $i++;
            $col                  = $this->safeColumn($col);
            $where[]              = "`{$col}` = {$placeholder}";
            $params[$placeholder] = $val;
        }

        $whereStr = implode(' AND ', $where);
        $orderStr = $orderBy ? " ORDER BY {$orderBy}" : '';

        $total  = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `{$table}` WHERE {$whereStr}",
            $params
        )['cnt'] ?? 0);

        $offset          = ($page - 1) * $perPage;
        $params[':lim']  = $perPage;
        $params[':off']  = $offset;

        $data = $this->db()->fetchAll(
            "SELECT * FROM `{$table}` WHERE {$whereStr}{$orderStr} LIMIT :lim OFFSET :off",
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
     * Count records matching optional conditions, scoped to tenant.
     */
    public function count(array $conditions = []): int
    {
        $table  = static::$table;
        $where  = ['tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];
        $i      = 0;

        foreach ($conditions as $col => $val) {
            $placeholder          = ':cond' . $i++;
            $col                  = $this->safeColumn($col);
            $where[]              = "`{$col}` = {$placeholder}";
            $params[$placeholder] = $val;
        }

        $result = $this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `{$table}` WHERE " . implode(' AND ', $where),
            $params
        );

        return (int) ($result['cnt'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Safety
    // -------------------------------------------------------------------------

    /**
     * Strip any character not valid in a column/table name.
     * Prevents second-order injection via dynamic column names.
     */
    private function safeColumn(string $col): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $col);
    }
}
