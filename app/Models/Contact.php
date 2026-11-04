<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Contact Model
 * Represents customers and/or vendors.
 */
class Contact extends BaseModel
{
    protected static string $table      = 'contacts';
    protected static string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // Type-scoped lookups
    // -------------------------------------------------------------------------

    /**
     * Get all active customers.
     */
    public function getCustomers(string $search = ''): array
    {
        $params = [':tid' => $this->tenantId];
        $where  = "tenant_id = :tid AND (contact_type = 'customer' OR contact_type = 'both') AND is_active = 1";

        if ($search !== '') {
            $where            .= " AND (name LIKE :srch OR email LIKE :srch OR phone LIKE :srch)";
            $params[':srch']   = '%' . $search . '%';
        }

        return $this->db()->fetchAll(
            "SELECT * FROM contacts WHERE {$where} ORDER BY name",
            $params
        );
    }

    /**
     * Get all active vendors.
     */
    public function getVendors(string $search = ''): array
    {
        $params = [':tid' => $this->tenantId];
        $where  = "tenant_id = :tid AND (contact_type = 'vendor' OR contact_type = 'both') AND is_active = 1";

        if ($search !== '') {
            $where            .= " AND (name LIKE :srch OR email LIKE :srch OR phone LIKE :srch)";
            $params[':srch']   = '%' . $search . '%';
        }

        return $this->db()->fetchAll(
            "SELECT * FROM contacts WHERE {$where} ORDER BY name",
            $params
        );
    }

    /**
     * Get paginated contacts with type and search filters.
     */
    public function getList(string $type = '', string $search = '', int $page = 1, int $perPage = 25): array
    {
        $where  = ['tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if ($type !== '' && in_array($type, ['customer', 'vendor', 'both'], true)) {
            $where[]          = "(contact_type = :type OR contact_type = 'both')";
            $params[':type']  = $type;
        }
        if ($search !== '') {
            $where[]          = "(name LIKE :srch OR email LIKE :srch OR phone LIKE :srch)";
            $params[':srch']  = '%' . $search . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM contacts WHERE {$whereStr}", $params
        )['cnt'] ?? 0);

        $params[':lim'] = $perPage;
        $params[':off'] = $offset;

        $data = $this->db()->fetchAll(
            "SELECT * FROM contacts WHERE {$whereStr} ORDER BY name LIMIT :lim OFFSET :off",
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

    // -------------------------------------------------------------------------
    // AR / AP balances
    // -------------------------------------------------------------------------

    /**
     * Get outstanding Accounts Receivable balance for a customer.
     * Sum of (total_amount - amount_paid) for open/partial invoices.
     */
    public function getARBalance(int $contactId): string
    {
        $row = $this->db()->fetchOne(
            "SELECT COALESCE(SUM(total_amount - amount_paid), 0) AS balance
             FROM invoices
             WHERE contact_id = :cid AND tenant_id = :tid
               AND status IN ('sent', 'partial')",
            [':cid' => $contactId, ':tid' => $this->tenantId]
        );
        return number_format((float) ($row['balance'] ?? 0), 4, '.', '');
    }

    /**
     * Get outstanding Accounts Payable balance for a vendor.
     */
    public function getAPBalance(int $contactId): string
    {
        $row = $this->db()->fetchOne(
            "SELECT COALESCE(SUM(total_amount - amount_paid), 0) AS balance
             FROM bills
             WHERE contact_id = :cid AND tenant_id = :tid
               AND status IN ('received', 'partial')",
            [':cid' => $contactId, ':tid' => $this->tenantId]
        );
        return number_format((float) ($row['balance'] ?? 0), 4, '.', '');
    }

    /**
     * Return lightweight list for AJAX dropdowns.
     */
    public function forDropdown(string $type = ''): array
    {
        $where  = ['is_active = 1', 'tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if ($type !== '') {
            $where[]         = "(contact_type = :type OR contact_type = 'both')";
            $params[':type'] = $type;
        }

        return $this->db()->fetchAll(
            "SELECT id, name, email, contact_type FROM contacts WHERE " . implode(' AND ', $where) . " ORDER BY name",
            $params
        );
    }
}
