<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Sales Invoices</h4>
    <p class="text-secondary small mb-0">Manage customer billings, monitor aged debtors, and track incoming receipts.</p>
  </div>
  <a href="<?= url('/app/invoices/create') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
    <i class="bi bi-plus-lg"></i> New Invoice
  </a>
</div>

<!-- STATS SUMMARY CARDS -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Total Invoices</span>
      <h4 class="fw-bold mb-0 text-dark"><?= (int)($stats['total_count'] ?? 0) ?></h4>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Total Invoiced</span>
      <h4 class="fw-bold mb-0 text-dark"><?= money((float)($stats['total_amount'] ?? 0)) ?></h4>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Total Outstanding (AR)</span>
      <h4 class="fw-bold mb-0 text-danger"><?= money((float)($stats['total_due'] ?? 0)) ?></h4>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Paid In Full</span>
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
          <a href="<?= url('/app/invoices?status=all') ?>" class="btn <?= $status === 'all' ? 'btn-primary' : 'btn-light border' ?>">All</a>
          <a href="<?= url('/app/invoices?status=draft') ?>" class="btn <?= $status === 'draft' ? 'btn-primary' : 'btn-light border' ?>">Draft</a>
          <a href="<?= url('/app/invoices?status=sent') ?>" class="btn <?= $status === 'sent' ? 'btn-primary' : 'btn-light border' ?>">Sent</a>
          <a href="<?= url('/app/invoices?status=partial') ?>" class="btn <?= $status === 'partial' ? 'btn-primary' : 'btn-light border' ?>">Partial</a>
          <a href="<?= url('/app/invoices?status=paid') ?>" class="btn <?= $status === 'paid' ? 'btn-primary' : 'btn-light border' ?>">Paid</a>
        </div>
      </div>
      <div class="col-md-5">
        <form method="GET" action="<?= url('/app/invoices') ?>" class="d-flex gap-2">
          <input type="hidden" name="status" value="<?= h($status) ?>">
          <input type="text" class="form-control form-control-sm" name="search" value="<?= h($search) ?>" placeholder="Search invoice # or customer...">
          <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- INVOICES TABLE -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small text-secondary">
        <tr>
          <th># Invoice</th>
          <th>Customer</th>
          <th>Issue Date</th>
          <th>Due Date</th>
          <th class="text-end">Total Amount</th>
          <th class="text-end">Amount Due</th>
          <th class="text-center">Status</th>
          <th class="text-end" style="width: 140px;">Actions</th>
        </tr>
      </thead>
      <tbody class="small">
        <?php if (empty($invoices)): ?>
          <tr><td colspan="8" class="text-center py-4 text-muted">No invoices found.</td></tr>
        <?php else: ?>
          <?php foreach ($invoices as $inv): ?>
            <tr>
              <td class="font-monospace fw-bold">
                <a href="<?= url('/app/invoices/' . $inv['id']) ?>" class="text-decoration-none">
                  <?= h($inv['invoice_number']) ?>
                </a>
              </td>
              <td class="fw-semibold"><?= h($inv['customer_name'] ?? '—') ?></td>
              <td><?= date_fmt($inv['invoice_date']) ?></td>
              <td>
                <span class="<?= (strtotime($inv['due_date']) < time() && $inv['status'] !== 'paid') ? 'text-danger fw-semibold' : '' ?>">
                  <?= date_fmt($inv['due_date']) ?>
                </span>
              </td>
              <td class="text-end fw-semibold"><?= money((float)$inv['total']) ?></td>
              <td class="text-end fw-bold <?= (float)$inv['amount_due'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= money((float)$inv['amount_due']) ?>
              </td>
              <td class="text-center">
                <?php
                  $badge = match($inv['status']) {
                    'paid' => 'bg-success-subtle text-success',
                    'sent' => 'bg-primary-subtle text-primary',
                    'partial' => 'bg-warning-subtle text-warning',
                    'void' => 'bg-secondary-subtle text-secondary',
                    default => 'bg-light text-dark border',
                  };
                ?>
                <span class="badge <?= $badge ?> text-uppercase"><?= h($inv['status']) ?></span>
              </td>
              <td class="text-end">
                <a href="<?= url('/app/invoices/' . $inv['id']) ?>" class="btn btn-sm btn-light border p-1" title="View">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'void'): ?>
                  <a href="<?= url('/app/invoices/' . $inv['id'] . '/pay') ?>" class="btn btn-sm btn-success p-1 text-white" title="Record Receipt">
                    <i class="bi bi-cash"></i>
                  </a>
                <?php endif; ?>
                <a href="<?= url('/app/invoices/' . $inv['id'] . '/pdf') ?>" target="_blank" class="btn btn-sm btn-light border p-1" title="Print/PDF">
                  <i class="bi bi-printer"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
