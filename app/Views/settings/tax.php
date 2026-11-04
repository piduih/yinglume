<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Company & System Settings</h4>
    <p class="text-secondary small mb-0">Configure value-added tax, sales tax (SST), and goods exemption codes.</p>
  </div>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taxModal" onclick="resetTaxModal()">
    <i class="bi bi-plus-lg me-1"></i> Add Tax Rate
  </button>
</div>

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="list-group shadow-sm border-0">
      <a href="<?= url('/app/settings') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-building me-2"></i> Company Profile
      </a>
      <a href="<?= url('/app/settings/users') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-people me-2"></i> Users & Roles
      </a>
      <a href="<?= url('/app/settings/tax') ?>" class="list-group-item list-group-item-action active fw-semibold">
        <i class="bi bi-receipt me-2"></i> Tax Rates
      </a>
      <a href="<?= url('/app/settings/fiscal-year') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-calendar3 me-2"></i> Fiscal Periods
      </a>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0">Configured Tax Rates</h5>
        <span class="text-secondary small">These tax rates apply to line items on invoices and bills.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th class="text-center">Rate (%)</th>
              <th>Applicable Scope</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($taxCodes)): ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-secondary">No tax codes set up yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($taxCodes as $tc): ?>
                <tr>
                  <td class="font-monospace fw-bold small"><?= h($tc['code']) ?></td>
                  <td class="fw-semibold text-dark"><?= h($tc['name']) ?></td>
                  <td class="text-center font-monospace fw-bold text-primary">
                    <?= number_format((float)$tc['rate'] * 100, 2) ?>%
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border text-uppercase small"><?= h($tc['type']) ?></span>
                  </td>
                  <td>
                    <?php if ($tc['is_active']): ?>
                      <span class="badge bg-success-subtle text-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editTax(<?= htmlspecialchars(json_encode($tc), ENT_QUOTES) ?>)">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Tax Rate Modal (Create / Edit) -->
<div class="modal fade" id="taxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('/app/settings/tax') ?>" id="taxForm">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="taxId" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="taxModalTitle">New Tax Rate</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Tax Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control font-monospace" name="code" id="taxCode" placeholder="e.g. SST-6" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Display Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="taxName" placeholder="e.g. Sales & Service Tax (6%)" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary">Rate Percentage (%) <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" step="0.01" class="form-control font-monospace" name="rate" id="taxRate" placeholder="6.00" required>
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary">Scope <span class="text-danger">*</span></label>
              <select class="form-select" name="type" id="taxType" required>
                <option value="both">Sales & Purchases (Both)</option>
                <option value="sales">Sales Invoices Only</option>
                <option value="purchase">Purchase Bills Only</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4">Save Tax Rate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetTaxModal() {
  document.getElementById('taxId').value = '0';
  document.getElementById('taxCode').value = '';
  document.getElementById('taxName').value = '';
  document.getElementById('taxRate').value = '';
  document.getElementById('taxType').value = 'both';
  document.getElementById('taxModalTitle').textContent = 'New Tax Rate';
}

function editTax(tax) {
  document.getElementById('taxId').value = tax.id;
  document.getElementById('taxCode').value = tax.code;
  document.getElementById('taxName').value = tax.name;
  document.getElementById('taxRate').value = (parseFloat(tax.rate) * 100).toFixed(2);
  document.getElementById('taxType').value = tax.type;
  document.getElementById('taxModalTitle').textContent = 'Edit Tax Rate: ' + tax.code;
  const modal = new bootstrap.Modal(document.getElementById('taxModal'));
  modal.show();
}
</script>

