<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Company & System Settings</h4>
    <p class="text-secondary small mb-0">Configure your business profile, base currency, formatting, and fiscal preferences.</p>
  </div>
</div>

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="list-group shadow-sm border-0">
      <a href="<?= url('/app/settings') ?>" class="list-group-item list-group-item-action active fw-semibold">
        <i class="bi bi-building me-2"></i> Company Profile
      </a>
      <a href="<?= url('/app/settings/users') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-people me-2"></i> Users & Roles
      </a>
      <a href="<?= url('/app/settings/tax') ?>" class="list-group-item list-group-item-action">
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
        <h5 class="fw-bold mb-0">Organization Profile</h5>
        <span class="text-secondary small">This information will appear on official client invoices and reports.</span>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('/app/settings') ?>">
          <?= csrf_field() ?>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label for="name" class="form-label small fw-semibold text-secondary">Company / Organization Legal Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control fs-5 fw-bold" id="name" name="name" value="<?= h($tenant['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label for="tax_number" class="form-label small fw-semibold text-secondary">Tax ID / SST / Business Reg #</label>
              <input type="text" class="form-control" id="tax_number" name="tax_number" value="<?= h($settings['tax_number'] ?? '') ?>" placeholder="e.g. 202401001234 (W10-1808-32000018)">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="email" class="form-label small fw-semibold text-secondary">Official Finance Email</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= h($settings['email'] ?? '') ?>" placeholder="finance@yourcompany.com">
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label small fw-semibold text-secondary">Phone / Contact #</label>
              <input type="text" class="form-control" id="phone" name="phone" value="<?= h($settings['phone'] ?? '') ?>" placeholder="+60 3-1234 5678">
            </div>
          </div>

          <div class="mb-4">
            <label for="address" class="form-label small fw-semibold text-secondary">Registered Business Address</label>
            <textarea class="form-control" id="address" name="address" rows="3" placeholder="Suite 10.01, Level 10, Menara ABC..."><?= h($settings['address'] ?? '') ?></textarea>
          </div>

          <hr class="my-4 text-muted">
          <h6 class="fw-bold text-uppercase small text-secondary mb-3">Localization & Accounting Standards</h6>

          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label for="currency" class="form-label small fw-semibold text-secondary">Base Reporting Currency</label>
              <select class="form-select font-monospace" id="currency" name="currency">
                <option value="MYR" <?= ($settings['currency'] ?? 'MYR') === 'MYR' ? 'selected' : '' ?>>MYR &mdash; Malaysian Ringgit (RM)</option>
                <option value="USD" <?= ($settings['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD &mdash; US Dollar ($)</option>
                <option value="SGD" <?= ($settings['currency'] ?? '') === 'SGD' ? 'selected' : '' ?>>SGD &mdash; Singapore Dollar (S$)</option>
                <option value="EUR" <?= ($settings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR &mdash; Euro (€)</option>
                <option value="GBP" <?= ($settings['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP &mdash; British Pound (£)</option>
                <option value="IDR" <?= ($settings['currency'] ?? '') === 'IDR' ? 'selected' : '' ?>>IDR &mdash; Indonesian Rupiah (Rp)</option>
                <option value="AUD" <?= ($settings['currency'] ?? '') === 'AUD' ? 'selected' : '' ?>>AUD &mdash; Australian Dollar (A$)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="date_format" class="form-label small fw-semibold text-secondary">Date Display Format</label>
              <select class="form-select" id="date_format" name="date_format">
                <option value="d/m/Y" <?= ($settings['date_format'] ?? 'd/m/Y') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (e.g. 31/12/2026)</option>
                <option value="Y-m-d" <?= ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (e.g. 2026-12-31)</option>
                <option value="m/d/Y" <?= ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (e.g. 12/31/2026)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="fiscal_year_start" class="form-label small fw-semibold text-secondary">Fiscal Year Starts In</label>
              <select class="form-select" id="fiscal_year_start" name="fiscal_year_start">
                <option value="1" <?= (int)($settings['fiscal_year_start'] ?? 1) === 1 ? 'selected' : '' ?>>January</option>
                <option value="4" <?= (int)($settings['fiscal_year_start'] ?? 1) === 4 ? 'selected' : '' ?>>April</option>
                <option value="7" <?= (int)($settings['fiscal_year_start'] ?? 1) === 7 ? 'selected' : '' ?>>July</option>
                <option value="10" <?= (int)($settings['fiscal_year_start'] ?? 1) === 10 ? 'selected' : '' ?>>October</option>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Profile Settings</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

