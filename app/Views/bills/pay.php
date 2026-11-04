<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0">Record Payment for Bill</h5>
        <span class="text-secondary small"># <?= h($bill['bill_number']) ?> &bull; Current Due: <strong><?= money((float)$bill['amount_due']) ?></strong></span>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('/app/bills/' . $bill['id'] . '/pay') ?>">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label for="amount" class="form-label small fw-semibold text-secondary">Disbursement Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light">$</span>
              <input type="number" step="0.01" class="form-control fs-5 fw-bold font-monospace" id="amount" name="amount" value="<?= (float)$bill['amount_due'] ?>" max="<?= (float)$bill['amount_due'] ?>" required>
            </div>
            <div class="form-text small">Enter full balance or partial payment amount.</div>
          </div>

          <div class="mb-3">
            <label for="bank_account_id" class="form-label small fw-semibold text-secondary">Pay From Bank Account <span class="text-danger">*</span></label>
            <select class="form-select" id="bank_account_id" name="bank_account_id" required>
              <option value="">Select Funding Bank Account...</option>
              <?php foreach ($banks as $b): ?>
                <option value="<?= $b['id'] ?>"><?= h($b['name']) ?> (<?= h($b['bank_name']) ?> &mdash; <?= h($b['account_number']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="payment_date" class="form-label small fw-semibold text-secondary">Payment Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label for="reference" class="form-label small fw-semibold text-secondary">Reference / Cheque / Transfer #</label>
              <input type="text" class="form-control" id="reference" name="reference" placeholder="e.g. TRF-VEND-7762">
            </div>
          </div>

          <div class="mb-4">
            <label for="notes" class="form-label small fw-semibold text-secondary">Payment Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Online bank transfer to vendor..."></textarea>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= url('/app/bills/' . $bill['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">Disburse Payment</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

