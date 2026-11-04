<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0">Add Bank Account</h5>
        <span class="text-secondary small">Set up a business bank account, cash register, or credit card wallet.</span>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('/app/banking/create') ?>">
          <?= csrf_field() ?>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="name" class="form-label small fw-semibold text-secondary">Account Display Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Maybank Operating Account" required>
            </div>
            <div class="col-md-6">
              <label for="bank_name" class="form-label small fw-semibold text-secondary">Bank / Financial Institution <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="e.g. Malayan Banking Berhad" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="account_number" class="form-label small fw-semibold text-secondary">Account Number <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="account_number" name="account_number" placeholder="e.g. 514012345678" required>
            </div>
            <div class="col-md-6">
              <label for="bank_branch" class="form-label small fw-semibold text-secondary">Branch Name / Location</label>
              <input type="text" class="form-control" id="bank_branch" name="bank_branch" placeholder="e.g. KL Main Branch">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="swift_code" class="form-label small fw-semibold text-secondary">SWIFT / BIC Code</label>
              <input type="text" class="form-control" id="swift_code" name="swift_code" placeholder="e.g. MBBEMYKL">
            </div>
            <div class="col-md-6">
              <label for="account_id" class="form-label small fw-semibold text-secondary">Link to Chart of Accounts</label>
              <select class="form-select" id="account_id" name="account_id">
                <option value="">-- Create new COA account automatically --</option>
                <?php foreach ($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= h($acc['code']) ?> &mdash; <?= h($acc['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small">Select an existing Cash/Bank Asset account, or leave blank to auto-create.</div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="opening_balance" class="form-label small fw-semibold text-secondary">Opening Balance</label>
              <div class="input-group">
                <span class="input-group-text bg-light">$</span>
                <input type="number" step="0.01" class="form-control font-monospace" id="opening_balance" name="opening_balance" value="0.00">
              </div>
            </div>
            <div class="col-md-6">
              <label for="opening_date" class="form-label small fw-semibold text-secondary">Opening Balance Date</label>
              <input type="date" class="form-control" id="opening_date" name="opening_date" value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1">
              <label class="form-check-label fw-semibold" for="is_default">Set as default account for transactions</label>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= url('/app/banking') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Bank Account</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

