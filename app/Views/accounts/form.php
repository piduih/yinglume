<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0"><?= $account ? 'Edit Account' : 'New General Ledger Account' ?></h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= $account ? url('/app/accounts/' . $account['id'] . '/edit') : url('/app/accounts/create') ?>">
          <?= csrf_field() ?>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="code" class="form-label small fw-semibold text-secondary">Account Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control font-monospace" id="code" name="code" value="<?= h($account['code'] ?? '') ?>" placeholder="e.g. 1010" required>
            </div>
            <div class="col-md-8">
              <label for="name" class="form-label small fw-semibold text-secondary">Account Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" value="<?= h($account['name'] ?? '') ?>" placeholder="e.g. HSBC Operating Account" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="account_type_id" class="form-label small fw-semibold text-secondary">Account Type <span class="text-danger">*</span></label>
              <select class="form-select" id="account_type_id" name="account_type_id" required>
                <option value="">Select Account Category...</option>
                <?php foreach ($types as $t): ?>
                  <option value="<?= $t['id'] ?>" <?= (isset($account['account_type_id']) && $account['account_type_id'] == $t['id']) ? 'selected' : '' ?>>
                    <?= h($t['name']) ?> (Normal Balance: <?= ucfirst($t['normal_balance']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="parent_id" class="form-label small fw-semibold text-secondary">Parent Account (Optional)</label>
              <select class="form-select" id="parent_id" name="parent_id">
                <option value="">None (Top Level)</option>
                <?php foreach ($parents as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= (isset($account['parent_id']) && $account['parent_id'] == $p['id']) ? 'selected' : '' ?>>
                    <?= h($p['code']) ?> — <?= h($p['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if (!$account): ?>
            <div class="mb-3">
              <label for="opening_balance" class="form-label small fw-semibold text-secondary">Opening Balance (Optional)</label>
              <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance" value="0.00">
              <div class="form-text small">Initial balance as of fiscal year start.</div>
            </div>
          <?php endif; ?>

          <div class="mb-4">
            <label for="description" class="form-label small fw-semibold text-secondary">Description / Usage Notes</label>
            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief note on when this account should be debited/credited..."><?= h($account['description'] ?? '') ?></textarea>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= url('/app/accounts') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
              <?= $account ? 'Update Account' : 'Save Account' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
