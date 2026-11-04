<?php
$isEdit = !empty($item);
$action = $isEdit ? url('/app/items/' . $item['id'] . '/edit') : url('/app/items/create');
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0"><?= $isEdit ? 'Edit Item' : 'New Item / Service' ?></h5>
        <span class="text-secondary small">Define products or services for sales invoicing and expense billing.</span>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= $action ?>">
          <?= csrf_field() ?>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="code" class="form-label small fw-semibold text-secondary">Item Code / SKU <span class="text-danger">*</span></label>
              <input type="text" class="form-control font-monospace" id="code" name="code" value="<?= h($item['code'] ?? '') ?>" placeholder="e.g. ITM-001" required>
            </div>
            <div class="col-md-5">
              <label for="name" class="form-label small fw-semibold text-secondary">Item Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name'] ?? '') ?>" placeholder="e.g. Standard Web Hosting" required>
            </div>
            <div class="col-md-3">
              <label for="type" class="form-label small fw-semibold text-secondary">Item Type <span class="text-danger">*</span></label>
              <select class="form-select" id="type" name="type" required>
                <option value="product" <?= ($item['type'] ?? '') === 'product' ? 'selected' : '' ?>>Product (Goods)</option>
                <option value="service" <?= ($item['type'] ?? '') === 'service' ? 'selected' : '' ?>>Service</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="description" class="form-label small fw-semibold text-secondary">Description</label>
            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Item description for invoice line items..."><?= h($item['description'] ?? '') ?></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="sales_price" class="form-label small fw-semibold text-secondary">Sales Price (Excl. Tax)</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" class="form-control font-monospace" id="sales_price" name="sales_price" value="<?= (float)($item['sales_price'] ?? 0.00) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <label for="purchase_price" class="form-label small fw-semibold text-secondary">Purchase Cost (Excl. Tax)</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" class="form-control font-monospace" id="purchase_price" name="purchase_price" value="<?= (float)($item['purchase_price'] ?? 0.00) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <label for="unit" class="form-label small fw-semibold text-secondary">Unit of Measurement</label>
              <input type="text" class="form-control" id="unit" name="unit" value="<?= h($item['unit'] ?? 'unit') ?>" placeholder="e.g. unit, hr, pcs, month">
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="sales_account_id" class="form-label small fw-semibold text-secondary">Sales Income Account</label>
              <select class="form-select" id="sales_account_id" name="sales_account_id">
                <option value="">Default Sales Account (4000)</option>
                <?php foreach ($salesAccounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>" <?= ($item['sales_account_id'] ?? '') == $acc['id'] ? 'selected' : '' ?>>
                    <?= h($acc['code']) ?> &mdash; <?= h($acc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="purchase_account_id" class="form-label small fw-semibold text-secondary">Purchase Expense Account</label>
              <select class="form-select" id="purchase_account_id" name="purchase_account_id">
                <option value="">Default Expense Account (5000/6000)</option>
                <?php foreach ($purchaseAccounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>" <?= ($item['purchase_account_id'] ?? '') == $acc['id'] ? 'selected' : '' ?>>
                    <?= h($acc['code']) ?> &mdash; <?= h($acc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="card bg-light border-0 p-3 mb-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="track_inventory" name="track_inventory" value="1" <?= !empty($item['track_inventory']) ? 'checked' : '' ?> onchange="toggleInventory(this.checked)">
              <label class="form-check-label fw-semibold" for="track_inventory">Track Inventory Stock for this item</label>
            </div>

            <div id="inventorySection" style="display: <?= !empty($item['track_inventory']) ? 'block' : 'none' ?>;">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="inventory_account_id" class="form-label small fw-semibold text-secondary">Inventory Asset Account</label>
                  <select class="form-select" id="inventory_account_id" name="inventory_account_id">
                    <option value="">Select Asset Account...</option>
                    <?php foreach ($inventoryAccounts as $acc): ?>
                      <option value="<?= $acc['id'] ?>" <?= ($item['inventory_account_id'] ?? '') == $acc['id'] ? 'selected' : '' ?>>
                        <?= h($acc['code']) ?> &mdash; <?= h($acc['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="quantity_on_hand" class="form-label small fw-semibold text-secondary">Quantity On Hand</label>
                  <input type="number" step="0.01" class="form-control font-monospace" id="quantity_on_hand" name="quantity_on_hand" value="<?= (float)($item['quantity_on_hand'] ?? 0) ?>" <?= $isEdit ? 'readonly' : '' ?>>
                  <?php if ($isEdit): ?><div class="form-text small">Use adjustments to modify existing stock.</div><?php endif; ?>
                </div>
                <div class="col-md-4">
                  <label for="reorder_level" class="form-label small fw-semibold text-secondary">Reorder Alert Level</label>
                  <input type="number" step="0.01" class="form-control font-monospace" id="reorder_level" name="reorder_level" value="<?= (float)($item['reorder_level'] ?? 5) ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= url('/app/items') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold"><?= $isEdit ? 'Update Item' : 'Create Item' ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function toggleInventory(enable) {
  document.getElementById('inventorySection').style.display = enable ? 'block' : 'none';
}
</script>

