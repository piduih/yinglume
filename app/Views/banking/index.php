<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Banking & Cash Management</h4>
    <p class="text-secondary small mb-0">Track all bank accounts, cash registers, transfers, and reconcile balances.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
      <i class="bi bi-arrow-left-right me-1"></i> Transfer Funds
    </button>
    <a href="<?= url('/app/banking/create') ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Add Bank Account
    </a>
  </div>
</div>

<!-- Total Cash Card -->
<div class="row g-4 mb-4">
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm bg-primary text-white p-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-white-50 small fw-semibold text-uppercase">Total Cash & Bank Balance</span>
        <i class="bi bi-wallet2 fs-4"></i>
      </div>
      <h2 class="fw-bold mb-0"><?= money((float)$totalCash) ?></h2>
      <div class="text-white-50 small mt-2">Across <?= count($banks) ?> active account(s)</div>
    </div>
  </div>
</div>

<!-- Bank Accounts Grid -->
<div class="row g-4">
  <?php if (empty($banks)): ?>
    <div class="col-12">
      <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
          <i class="bi bi-bank display-4 text-muted mb-3"></i>
          <h5>No bank accounts found</h5>
          <p class="text-secondary small mb-3">Start by adding your company bank account or petty cash drawer.</p>
          <a href="<?= url('/app/banking/create') ?>" class="btn btn-primary">Add Bank Account</a>
        </div>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($banks as $bank): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body p-4 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold mb-0 text-dark"><?= h($bank['name']) ?></h5>
                <?php if ($bank['is_default']): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Default</span>
                <?php endif; ?>
              </div>
              <div class="text-secondary small mb-3">
                <span><?= h($bank['bank_name']) ?></span>
                <?php if (!empty($bank['account_number'])): ?>
                  &bull; <span class="font-monospace"><?= h($bank['account_number']) ?></span>
                <?php endif; ?>
              </div>

              <div class="bg-light p-3 rounded-3 mb-4">
                <div class="text-secondary small">Current Balance</div>
                <div class="fs-4 fw-bold text-dark font-monospace"><?= money((float)$bank['current_balance']) ?></div>
                <div class="text-muted small mt-1">GL Account: <?= h($bank['coa_code']) ?> &mdash; <?= h($bank['coa_name']) ?></div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <a href="<?= url('/app/banking/' . $bank['id']) ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                <i class="bi bi-list-check me-1"></i> View Ledger
              </a>
              <a href="<?= url('/app/banking/' . $bank['id'] . '/reconcile') ?>" class="btn btn-outline-primary btn-sm flex-fill">
                <i class="bi bi-check2-circle me-1"></i> Reconcile
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Modal Transfer Funds -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('/app/banking/transfer') ?>">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="transferModalLabel">Transfer Funds Between Accounts</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">From Account <span class="text-danger">*</span></label>
            <select class="form-select" name="from_bank_id" required>
              <option value="">Select source account...</option>
              <?php foreach ($banks as $b): ?>
                <option value="<?= $b['id'] ?>"><?= h($b['name']) ?> (<?= money((float)$b['current_balance']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">To Account <span class="text-danger">*</span></label>
            <select class="form-select" name="to_bank_id" required>
              <option value="">Select destination account...</option>
              <?php foreach ($banks as $b): ?>
                <option value="<?= $b['id'] ?>"><?= h($b['name']) ?> (<?= money((float)$b['current_balance']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary">Transfer Amount <span class="text-danger">*</span></label>
              <input type="number" step="0.01" class="form-control" name="amount" placeholder="0.00" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary">Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Reference / Description</label>
            <input type="text" class="form-control" name="reference" placeholder="e.g. TRF-INTERNAL-01">
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4">Execute Transfer</button>
        </div>
      </form>
    </div>
  </div>
</div>

