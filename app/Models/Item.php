<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Item Model
 * Products and services that appear on invoices and bills.
 */
class Item extends BaseModel
{
    protected static string $table      = 'items';
    protected static string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // Stock management
    // -------------------------------------------------------------------------

    /**
     * Apply a manual stock adjustment.
     *
     * @param string $type  'in' (increase) | 'out' (decrease) | 'set' (absolute)
     * @param float  $qty   Quantity to adjust
     * @param float  $cost  Unit cost (for costing purposes)
     * @param string $ref   Reference note (e.g. "Opening stock", "Damage write-off")
     */
    public function adjustStock(int $id, float $qty, string $type, float $cost = 0.0, string $ref = ''): bool
    {
        $item = $this->find($id);
        if (!$item) {
            throw new \RuntimeException("Item #{$id} not found.");
        }

        if (!in_array($type, ['in', 'out', 'set'], true)) {
            throw new \RuntimeException("Invalid adjustment type: {$type}.");
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $currentQty = (float) $item['qty_on_hand'];

            if ($type === 'set') {
                $newQty     = round($qty, 4);
                $adjQty     = $newQty - $currentQty;
            } elseif ($type === 'in') {
                $adjQty     = round($qty, 4);
                $newQty     = $currentQty + $adjQty;
            } else { // out
                $adjQty     = -round($qty, 4);
                $newQty     = $currentQty + $adjQty;
            }

            // Update item qty
            $db->execute(
                "UPDATE items SET qty_on_hand = :qty, updated_at = NOW() WHERE id = :id AND tenant_id = :tid",
                [':qty' => $newQty, ':id' => $id, ':tid' => $this->tenantId]
            );

            // Log the adjustment
            $db->execute(
                "INSERT INTO stock_adjustments
                 (tenant_id, item_id, adjustment_type, qty_before, qty_adjusted, qty_after,
                  unit_cost, reference, created_at)
                 VALUES (:tid, :iid, :type, :before, :adj, :after, :cost, :ref, NOW())",
                [
                    ':tid'    => $this->tenantId,
                    ':iid'    => $id,
                    ':type'   => $type,
                    ':before' => $currentQty,
                    ':adj'    => $adjQty,
                    ':after'  => $newQty,
                    ':cost'   => round($cost, 4),
                    ':ref'    => $ref,
                ]
            );

            $db->commit();
            return true;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get items below their reorder point for a tenant.
     */
    public function getLowStock(int $tenantId): array
    {
        return $this->db()->fetchAll(
            "SELECT * FROM items
             WHERE tenant_id = :tid
               AND is_active = 1
               AND track_inventory = 1
               AND qty_on_hand <= reorder_point
             ORDER BY (qty_on_hand - reorder_point)",
            [':tid' => $tenantId]
        );
    }

    /**
     * Return items for AJAX / dropdown.
     */
    public function forDropdown(string $search = ''): array
    {
        $where  = ['is_active = 1', 'tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if ($search !== '') {
            $where[]        = '(name LIKE :srch OR sku LIKE :srch)';
            $params[':srch'] = '%' . $search . '%';
        }

        return $this->db()->fetchAll(
            "SELECT id, sku, name, sale_price, purchase_price,
                    qty_on_hand, unit, tax_code_id, description
             FROM items WHERE " . implode(' AND ', $where) . " ORDER BY name LIMIT 100",
            $params
        );
    }

    /**
     * Get a single item with full details for the invoice line autofill.
     */
    public function getForLineItem(int $id): ?array
    {
        return $this->db()->fetchOne(
            "SELECT i.*, tc.rate AS tax_rate, tc.name AS tax_name
             FROM items i
             LEFT JOIN tax_codes tc ON tc.id = i.tax_code_id
             WHERE i.id = :id AND i.tenant_id = :tid AND i.is_active = 1",
            [':id' => $id, ':tid' => $this->tenantId]
        ) ?: null;
    }

    /**
     * Paginated item list with search.
     */
    public function getList(string $search = '', int $page = 1, int $perPage = 25): array
    {
        $where  = ['tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if ($search !== '') {
            $where[]        = '(name LIKE :srch OR sku LIKE :srch)';
            $params[':srch'] = '%' . $search . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM items WHERE {$whereStr}", $params
        )['cnt'] ?? 0);

        $params[':lim'] = $perPage;
        $params[':off'] = $offset;

        $data = $this->db()->fetchAll(
            "SELECT i.*, tc.name AS tax_name
             FROM items i
             LEFT JOIN tax_codes tc ON tc.id = i.tax_code_id
             WHERE {$whereStr}
             ORDER BY i.name
             LIMIT :lim OFFSET :off",
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
}
