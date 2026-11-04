<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Items & Inventory</h4>
    <p class="text-secondary small mb-0">Manage products, billable services, sales pricing, and stock levels.</p>
  </div>
  <a href="<?= url('/app/items/create') ?>" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i> Add Item
  </a>
</div>

<!-- Search & Filter -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= url('/app/items') ?>" class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-secondary"></i></span>
          <input type="text" name="search" class="form-control border-start-0" placeholder="Search by item code or name..." value="<?= h($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="type" class="form-select" onchange="this.form.submit()">
          <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Item Types</option>
          <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>Products</option>
          <option value="service" <?= $type === 'service' ? 'selected' : '' ?>>Services</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-secondary w-100">Filter</button>
      </div>
      <?php if ($search !== '' || $type !== 'all'): ?>
        <div class="col-md-2">
          <a href="<?= url('/app/items') ?>" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Items Table -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th>Code</th>
          <th>Name / Description</th>
          <th>Type</th>
          <th class="text-end">Sales Price</th>
          <th class="text-end">Purchase Price</th>
          <th class="text-center">Stock Level</th>
          <th class="text-center">Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="8" class="text-center py-5 text-secondary">
              <i class="bi bi-box-seam display-5 text-muted d-block mb-3"></i>
              No items found. Click <strong>Add Item</strong> to create one.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <tr class="<?= !$item['is_active'] ? 'opacity-50' : '' ?>">
              <td class="font-monospace fw-bold small"><?= h($item['code']) ?></td>
              <td>
                <div class="fw-semibold text-dark"><?= h($item['name']) ?></div>
                <?php if (!empty($item['description'])): ?>
                  <div class="text-secondary small text-truncate" style="max-width: 280px;"><?= h($item['description']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($item['type'] === 'product'): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Product</span>
                <?php else: ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle">Service</span>
                <?php endif; ?>
              </td>
              <td class="text-end font-monospace fw-semibold"><?= money((float)$item['sales_price']) ?></td>
              <td class="text-end font-monospace text-secondary"><?= money((float)$item['purchase_price']) ?></td>
              <td class="text-center font-monospace">
                <?php if ($item['track_inventory']): ?>
                  <?php 
                    $qty = (float)$item['quantity_on_hand'];
                    $reorder = (float)$item['reorder_level'];
                    $badgeClass = ($qty <= $reorder) ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle';
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= $qty ?> <?= h($item['unit']) ?></span>
                <?php else: ?>
                  <span class="text-muted small">&mdash;</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($item['is_active']): ?>
                  <span class="badge bg-success-subtle text-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                  <a href="<?= url('/app/items/' . $item['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary" title="Edit Item">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="POST" action="<?= url('/app/items/' . $item['id'] . '/deactivate') ?>" onsubmit="return confirm('Toggle status for this item?');" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm <?= $item['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $item['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi <?= $item['is_active'] ? 'bi-slash-circle' : 'bi-check-circle' ?>"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

