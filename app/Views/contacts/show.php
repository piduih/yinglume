<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h4 class="fw-bold mb-0"><?= h($contact['name']) ?></h4>
      <span class="badge bg-primary-subtle text-primary text-uppercase"><?= h($contact['type']) ?></span>
    </div>
    <span class="text-secondary small">Terms: Net <?= (int)$contact['payment_terms'] ?> days &bull; Added <?= date_fmt($contact['created_at']) ?></span>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('/app/contacts') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Directory
    </a>
    <a href="<?= url('/app/contacts/' . $contact['id'] . '/edit') ?>" class="btn btn-light border btn-sm">
      <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <?php if ($contact['type'] === 'customer' || $contact['type'] === 'both'): ?>
      <a href="<?= url('/app/invoices/create') ?>" class="btn btn-primary btn-sm fw-semibold">
        <i class="bi bi-receipt me-1"></i> New Invoice
      </a>
    <?php endif; ?>
    <?php if ($contact['type'] === 'vendor' || $contact['type'] === 'both'): ?>
      <a href="<?= url('/app/bills/create') ?>" class="btn btn-outline-danger btn-sm fw-semibold">
        <i class="bi bi-file-earmark-plus me-1"></i> New Bill
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- METRIC CARDS -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Receivable Balance (Due from them)</span>
      <h3 class="fw-bold mb-0 text-success"><?= money($arBalance) ?></h3>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Payable Balance (Due to them)</span>
      <h3 class="fw-bold mb-0 text-danger"><?= money($apBalance) ?></h3>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Approved Credit Limit</span>
      <h3 class="fw-bold mb-0 text-dark"><?= money((float)$contact['credit_limit']) ?></h3>
    </div>
  </div>
</div>

<!-- DETAILS CARD -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 border-0">
    <h6 class="fw-bold mb-0">Contact Information</h6>
  </div>
  <div class="card-body p-4">
    <div class="row g-3">
      <div class="col-md-4">
        <span class="text-secondary small d-block">Billing Email</span>
        <span class="fw-semibold"><?= h($contact['email'] ?: '—') ?></span>
      </div>
      <div class="col-md-4">
        <span class="text-secondary small d-block">Phone Number</span>
        <span class="fw-semibold"><?= h($contact['phone'] ?: '—') ?></span>
      </div>
      <div class="col-md-4">
        <span class="text-secondary small d-block">Tax Registration (VAT/SST)</span>
        <span class="fw-semibold font-monospace"><?= h($contact['tax_number'] ?: '—') ?></span>
      </div>
      <div class="col-md-8">
        <span class="text-secondary small d-block">Physical Address</span>
        <span class="fw-semibold">
          <?= h(implode(', ', array_filter([$contact['address'], $contact['city'], $contact['state'], $contact['country']]))) ?: '—' ?>
        </span>
      </div>
      <div class="col-md-4">
        <span class="text-secondary small d-block">Internal Notes</span>
        <span class="text-muted small"><?= h($contact['notes'] ?: 'No notes recorded.') ?></span>
      </div>
    </div>
  </div>
</div>

<!-- RECENT INVOICES & BILLS TABLES -->
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0">Sales Invoices History</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light text-secondary">
            <tr>
              <th># Invoice</th>
              <th>Date</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="4" class="text-center py-3 text-muted">No invoices found.</td></tr>
            <?php else: ?>
              <?php foreach ($invoices as $inv): ?>
                <tr>
                  <td><a href="<?= url('/app/invoices/' . $inv['id']) ?>" class="fw-semibold text-decoration-none"><?= h($inv['invoice_number']) ?></a></td>
                  <td><?= date_fmt($inv['invoice_date']) ?></td>
                  <td class="text-end fw-bold"><?= money((float)$inv['total']) ?></td>
                  <td class="text-center"><span class="badge bg-light text-dark border text-uppercase"><?= h($inv['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0">Vendor Bills History</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light text-secondary">
            <tr>
              <th># Bill</th>
              <th>Date</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bills)): ?>
              <tr><td colspan="4" class="text-center py-3 text-muted">No bills found.</td></tr>
            <?php else: ?>
              <?php foreach ($bills as $b): ?>
                <tr>
                  <td><a href="<?= url('/app/bills/' . $b['id']) ?>" class="fw-semibold text-decoration-none"><?= h($b['bill_number']) ?></a></td>
                  <td><?= date_fmt($b['bill_date']) ?></td>
                  <td class="text-end fw-bold"><?= money((float)$b['total']) ?></td>
                  <td class="text-center"><span class="badge bg-light text-dark border text-uppercase"><?= h($b['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
