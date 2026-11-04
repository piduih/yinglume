<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Item;

/**
 * ItemController
 * Product catalogue, service pricing, and stock inventory.
 */
class ItemController extends BaseController
{
    private Item $itemModel;

    public function __construct()
    {
        parent::__construct();
        $this->itemModel = new Item();
    }

    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $search = (string)$this->input('search', '');
        $type   = (string)$this->input('type', 'all');

        $where = ['tenant_id = :tid'];
        $params = [':tid' => $tenantId];

        if ($type !== 'all' && in_array($type, ['product', 'service'], true)) {
            $where[] = 'type = :type';
            $params[':type'] = $type;
        }

        if ($search !== '') {
            $where[] = '(code LIKE :s OR name LIKE :s2)';
            $params[':s']  = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
        }

        $items = $db->select("SELECT * FROM items WHERE " . implode(' AND ', $where) . " ORDER BY code ASC", $params);

        $this->render('items.index', [
            'pageTitle' => 'Items & Inventory',
            'items'     => $items,
            'search'    => $search,
            'type'      => $type,
        ], 'main');
    }

    public function create(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $salesAccounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 4 ORDER BY code ASC", [':tid' => $tenantId]);
        $purchaseAccounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 5 ORDER BY code ASC", [':tid' => $tenantId]);
        $inventoryAccounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 1 ORDER BY code ASC", [':tid' => $tenantId]);

        $this->render('items.form', [
            'pageTitle'          => 'Add Item',
            'item'               => null,
            'salesAccounts'      => $salesAccounts,
            'purchaseAccounts'   => $purchaseAccounts,
            'inventoryAccounts'  => $inventoryAccounts,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $errors = $this->validate([
            'code'        => 'required|min:2|max:50',
            'name'        => 'required|min:2|max:150',
            'type'        => 'required|in:product,service',
            'sales_price' => 'numeric',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/items/create');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();
        $code = (string)$this->input('code');

        $exists = $db->selectOne("SELECT id FROM items WHERE tenant_id = :tid AND code = :code", [':tid' => $tenantId, ':code' => $code]);
        if ($exists) {
            $this->flash('error', 'Item code already exists.');
            $this->redirect('/app/items/create');
        }

        $trackInventory = $this->input('track_inventory') ? 1 : 0;

        $db->insert(
            "INSERT INTO items (uuid, tenant_id, code, name, description, type, unit, sales_price, purchase_price, sales_account_id, purchase_account_id, inventory_account_id, track_inventory, quantity_on_hand, reorder_level, is_active, created_at, updated_at)
             VALUES (:uuid, :tid, :code, :name, :desc, :type, :unit, :sprice, :pprice, :sacc, :pacc, :iacc, :track, :qty, :reorder, 1, NOW(), NOW())",
            [
                ':uuid'    => generate_uuid(),
                ':tid'     => $tenantId,
                ':code'    => $code,
                ':name'    => (string)$this->input('name'),
                ':desc'    => (string)$this->input('description', ''),
                ':type'    => (string)$this->input('type'),
                ':unit'    => (string)$this->input('unit', 'unit'),
                ':sprice'  => (float)$this->input('sales_price', 0),
                ':pprice'  => (float)$this->input('purchase_price', 0),
                ':sacc'    => (int)$this->input('sales_account_id') ?: null,
                ':pacc'    => (int)$this->input('purchase_account_id') ?: null,
                ':iacc'    => $trackInventory ? ((int)$this->input('inventory_account_id') ?: null) : null,
                ':track'   => $trackInventory,
                ':qty'     => $trackInventory ? (float)$this->input('quantity_on_hand', 0) : 0,
                ':reorder' => $trackInventory ? (float)$this->input('reorder_level', 0) : 0,
            ]
        );

        $this->flash('success', 'Item created successfully.');
        $this->redirect('/app/items');
    }

    public function edit(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $item = $db->selectOne("SELECT * FROM items WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$item) {
            $this->flash('error', 'Item not found.');
            $this->redirect('/app/items');
        }

        $salesAccounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 4 ORDER BY code ASC", [':tid' => $tenantId]);
        $purchaseAccounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 5 ORDER BY code ASC", [':tid' => $tenantId]);
        $inventoryAccounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 1 ORDER BY code ASC", [':tid' => $tenantId]);

        $this->render('items.form', [
            'pageTitle'          => 'Edit Item — ' . $item['name'],
            'item'               => $item,
            'salesAccounts'      => $salesAccounts,
            'purchaseAccounts'   => $purchaseAccounts,
            'inventoryAccounts'  => $inventoryAccounts,
        ], 'main');
    }

    public function update(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $errors = $this->validate([
            'code'        => 'required|min:2|max:50',
            'name'        => 'required|min:2|max:150',
            'type'        => 'required|in:product,service',
            'sales_price' => 'numeric',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/items/' . $id . '/edit');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();
        $code = (string)$this->input('code');

        $exists = $db->selectOne("SELECT id FROM items WHERE tenant_id = :tid AND code = :code AND id != :id", [':tid' => $tenantId, ':code' => $code, ':id' => $id]);
        if ($exists) {
            $this->flash('error', 'Item code already in use.');
            $this->redirect('/app/items/' . $id . '/edit');
        }

        $trackInventory = $this->input('track_inventory') ? 1 : 0;

        $db->update(
            "UPDATE items 
             SET code = :code, name = :name, description = :desc, type = :type, unit = :unit, sales_price = :sprice, 
                 purchase_price = :pprice, sales_account_id = :sacc, purchase_account_id = :pacc, inventory_account_id = :iacc,
                 track_inventory = :track, reorder_level = :reorder, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid",
            [
                ':code'    => $code,
                ':name'    => (string)$this->input('name'),
                ':desc'    => (string)$this->input('description', ''),
                ':type'    => (string)$this->input('type'),
                ':unit'    => (string)$this->input('unit', 'unit'),
                ':sprice'  => (float)$this->input('sales_price', 0),
                ':pprice'  => (float)$this->input('purchase_price', 0),
                ':sacc'    => (int)$this->input('sales_account_id') ?: null,
                ':pacc'    => (int)$this->input('purchase_account_id') ?: null,
                ':iacc'    => $trackInventory ? ((int)$this->input('inventory_account_id') ?: null) : null,
                ':track'   => $trackInventory,
                ':reorder' => $trackInventory ? (float)$this->input('reorder_level', 0) : 0,
                ':id'      => $id,
                ':tid'     => $tenantId,
            ]
        );

        $this->flash('success', 'Item updated successfully.');
        $this->redirect('/app/items');
    }

    public function deactivate(string $id): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $item = $db->selectOne("SELECT * FROM items WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if ($item) {
            $newStatus = $item['is_active'] ? 0 : 1;
            $db->update("UPDATE items SET is_active = :st WHERE id = :id AND tenant_id = :tid", [':st' => $newStatus, ':id' => $id, ':tid' => $tenantId]);
            $this->flash('success', 'Item status updated.');
        }

        $this->redirect('/app/items');
    }
}
