<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Account Model
 * Chart of Accounts — the backbone of the general ledger.
 */
class Account extends BaseModel
{
    protected static string $table      = 'accounts';
    protected static string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // Balance queries
    // -------------------------------------------------------------------------

    /**
     * Calculate the balance for an account over a date range by summing journal lines.
     * Debits are positive, credits are negative in the raw sum;
     * the calling code should negate for liability/equity/revenue accounts.
     *
     * @return string DECIMAL(15,4) as string
     */
    public function getBalance(int $accountId, string $startDate, string $endDate): string
    {
        $row = $this->db()->fetchOne(
            "SELECT
                COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0.0000) AS balance
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_entry_id
             WHERE jl.account_id = :account_id
               AND jl.tenant_id  = :tid
               AND je.status      = 'posted'
               AND je.entry_date  BETWEEN :start AND :end",
            [
                ':account_id' => $accountId,
                ':tid'        => $this->tenantId,
                ':start'      => $startDate,
                ':end'        => $endDate,
            ]
        );

        return number_format((float) ($row['balance'] ?? 0), 4, '.', '');
    }

    /**
     * Get the running balance of an account up to (and including) a given date.
     * Used for Balance Sheet (cumulative).
     */
    public function getBalanceAsOf(int $accountId, string $asOfDate): string
    {
        $row = $this->db()->fetchOne(
            "SELECT
                COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0.0000) AS balance
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_entry_id
             WHERE jl.account_id = :account_id
               AND jl.tenant_id  = :tid
               AND je.status      = 'posted'
               AND je.entry_date <= :as_of",
            [
                ':account_id' => $accountId,
                ':tid'        => $this->tenantId,
                ':as_of'      => $asOfDate,
            ]
        );

        return number_format((float) ($row['balance'] ?? 0), 4, '.', '');
    }

    // -------------------------------------------------------------------------
    // Structural queries
    // -------------------------------------------------------------------------

    /**
     * Return accounts grouped in a hierarchical tree (parent → children).
     * Result is keyed by account type name.
     */
    public function getTree(): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT a.*, at.name AS type_name, at.normal_balance
             FROM accounts a
             JOIN account_types at ON at.id = a.account_type_id
             WHERE a.tenant_id = :tid
             ORDER BY at.sort_order, a.code",
            [':tid' => $this->tenantId]
        );

        $byId   = [];
        $tree   = [];
        $byType = [];

        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }

        foreach ($rows as $row) {
            if ($row['parent_id'] && isset($byId[$row['parent_id']])) {
                $byId[$row['parent_id']]['children'][] = &$byId[$row['id']];
            } else {
                $byType[$row['type_name']][] = &$byId[$row['id']];
            }
        }

        return $byType;
    }

    /**
     * Get all active accounts of a given account_type_id.
     */
    public function getByType(int $typeId): array
    {
        return $this->db()->fetchAll(
            "SELECT a.*, at.name AS type_name, at.normal_balance
             FROM accounts a
             JOIN account_types at ON at.id = a.account_type_id
             WHERE a.tenant_id = :tid AND a.account_type_id = :type AND a.is_active = 1
             ORDER BY a.code",
            [':tid' => $this->tenantId, ':type' => $typeId]
        );
    }

    /**
     * Return a lightweight list for dropdowns.
     * [{id, code, name, type_name, normal_balance}]
     */
    public function forDropdown(): array
    {
        return $this->db()->fetchAll(
            "SELECT a.id, a.code, a.name, at.name AS type_name, at.normal_balance
             FROM accounts a
             JOIN account_types at ON at.id = a.account_type_id
             WHERE a.tenant_id = :tid AND a.is_active = 1
             ORDER BY at.sort_order, a.code",
            [':tid' => $this->tenantId]
        );
    }

    /**
     * Search accounts by code or name (for AJAX).
     */
    public function search(string $term, int $limit = 30): array
    {
        $like = '%' . $term . '%';
        return $this->db()->fetchAll(
            "SELECT a.id, a.code, a.name, at.name AS type_name
             FROM accounts a
             JOIN account_types at ON at.id = a.account_type_id
             WHERE a.tenant_id = :tid AND a.is_active = 1
               AND (a.code LIKE :term OR a.name LIKE :term)
             ORDER BY a.code
             LIMIT :lim",
            [':tid' => $this->tenantId, ':term' => $like, ':lim' => $limit]
        );
    }

    /**
     * Check whether an account has any posted journal lines (used before deactivation).
     */
    public function hasTransactions(int $accountId): bool
    {
        $row = $this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM journal_lines
             WHERE account_id = :id AND tenant_id = :tid",
            [':id' => $accountId, ':tid' => $this->tenantId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Get all accounts with their current balance for the fiscal year.
     */
    public function getAllWithBalance(string $startDate, string $endDate): array
    {
        return $this->db()->fetchAll(
            "SELECT a.id, a.code, a.name, at.name AS type_name, at.normal_balance,
                    COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS net_balance
             FROM accounts a
             JOIN account_types at ON at.id = a.account_type_id
             LEFT JOIN journal_lines jl ON jl.account_id = a.id AND jl.tenant_id = a.tenant_id
             LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id
                 AND je.status = 'posted'
                 AND je.entry_date BETWEEN :start AND :end
             WHERE a.tenant_id = :tid AND a.is_active = 1
             GROUP BY a.id, a.code, a.name, at.name, at.normal_balance
             ORDER BY at.sort_order, a.code",
            [':tid' => $this->tenantId, ':start' => $startDate, ':end' => $endDate]
        );
    }
}
