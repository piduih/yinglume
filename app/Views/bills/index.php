<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Vendor Bills</h4>
    <p class="text-secondary small mb-0">Manage supplier invoices, purchase accruals, and cash disbursements.</p>
  </div>
  <a href="<?= url('/app/bills/create') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
    <i class="bi bi-plus-lg"></i> New Bill
  </a>
</div>

<!-- STATS SUMMARY CARDS -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Total Bills</span>
      <h4 class="fw-bold mb-0 text-dark"><?= (int)($stats['total_count'] ?? 0) ?></h4>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Total Billed</span>
      <h4 class="fw-bold mb-0 text-dark"><?= money((float)($stats['total_amount'] ?? 0)) ?></h4>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Accounts Payable (AP)</span>
      <h4 class="fw-bold mb-0 text-danger"><?= money((float)($stats['total_due'] ?? 0)) ?></h4>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Fully Settled</span>
      <h4 class="fw-bold mb-0 text-success"><?= (int)($stats['paid_count'] ?? 0) ?></h4>
    </div>
  </div>
</div>

<!-- TABS & FILTER -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <div class="row g-2 align-items-center">
      <div class="col-md-7">
        <div class="btn-group btn-group-sm w-100 w-md-auto" role="group">
          <a href="<?= url('/app/bills?status=all') ?>" class="btn <?= $status === 'all' ? 'btn-primary' : 'btn-light border' ?>">All</a>
          <a href="<?= url('/app/bills?status=received') ?>" class="btn <?= $status === 'received' ? 'btn-primary' : 'btn-light border' ?>">Pending</a>
          <a href="<?= url('/app/bills?status=partial') ?>" class="btn <?= $status === 'partial' ? 'btn-primary' : 'btn-light border' ?>">Partial</a>
          <a href="<?= url('/app/bills?status=paid') ?>" class="btn <?= $status === 'paid' ? 'btn-primary' : 'btn-light border' ?>">Settled</a>
        </div>
      </div>
      <div class="col-md-5">
        <form method="GET" action="<?= url('/app/bills') ?>" class="d-flex gap-2">
          <input type="hidden" name="status" value="<?= h($status) ?>">
          <input type="text" class="form-control form-control-sm" name="search" value="<?= h($search) ?>" placeholder="Search bill # or supplier...">
          <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- BILLS TABLE -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small text-secondary">
        <tr>
          <th># Bill</th>
          <th>Vendor / Supplier</th>
          <th>Bill Date</th>
          <th>Due Date</th>
          <th class="text-end">Total Amount</th>
          <th class="text-end">Amount Due</th>
          <th class="text-center">Status</th>
          <th class="text-end" style="width: 140px;">Actions</th>
        </tr>
      </thead>
      <tbody class="small">
        <?php if (empty($bills)): ?>
          <tr><td colspan="8" class="text-center py-4 text-muted">No vendor bills recorded.</td></tr>
        <?php else: ?>
          <?php foreach ($bills as $b): ?>
            <tr>
              <td class="font-monospace fw-bold">
                <a href="<?= url('/app/bills/' . $b['id']) ?>" class="text-decoration-none">
                  <?= h($b['bill_number']) ?>
                </a>
              </td>
              <td class="fw-semibold"><?= h($b['vendor_name'] ?? '—') ?></td>
              <td><?= date_fmt($b['bill_date']) ?></td>
              <td>
                <span class="<?= (strtotime($b['due_date']) < time() && $b['status'] !== 'paid') ? 'text-danger fw-semibold' : '' ?>">
                  <?= date_fmt($b['due_date']) ?>
                </span>
              </td>
              <td class="text-end fw-semibold"><?= money((float)$b['total']) ?></td>
              <td class="text-end fw-bold <?= (float)$b['amount_due'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= money((float)$b['amount_due']) ?>
              </td>
              <td class="text-center">
                <?php
                  $bClass = match($b['status']) {
                    'paid' => 'bg-success-subtle text-success',
                    'received' => 'bg-danger-subtle text-danger',
                    'partial' => 'bg-warning-subtle text-warning',
                    'void' => 'bg-secondary-subtle text-secondary',
                    default => 'bg-light text-dark border',
                  };
                ?>
                <span class="badge <?= $bClass ?> text-uppercase"><?= h($b['status']) ?></span>
              </td>
              <td class="text-end">
                <a href="<?= url('/app/bills/' . $b['id']) ?>" class="btn btn-sm btn-light border p-1" title="View">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if ($b['status'] !== 'paid' && $b['status'] !== 'void'): ?>
                  <a href="<?= url('/app/bills/' . $b['id'] . '/pay') ?>" class="btn btn-sm btn-danger p-1 text-white" title="Pay Bill">
                    <i class="bi bi-cash-coin"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
