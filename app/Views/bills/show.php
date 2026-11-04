<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h4 class="fw-bold mb-0">Bill: <?= h($bill['bill_number']) ?></h4>
      <?php
        $bc = match($bill['status']) {
          'paid'     => 'bg-success-subtle text-success',
          'received' => 'bg-danger-subtle text-danger',
          'partial'  => 'bg-warning-subtle text-warning',
          'void'     => 'bg-secondary-subtle text-secondary',
          default    => 'bg-light text-dark border',
        };
      ?>
      <span class="badge <?= $bc ?> text-uppercase"><?= h($bill['status']) ?></span>
    </div>
    <span class="text-secondary small">Vendor: <strong><?= h($bill['vendor_name']) ?></strong> &bull; Entered <?= date_fmt($bill['bill_date']) ?></span>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('/app/bills') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Bills</a>
    <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'void'): ?>
      <a href="<?= url('/app/bills/' . $bill['id'] . '/pay') ?>" class="btn btn-danger btn-sm fw-semibold">
        <i class="bi bi-cash-coin me-1"></i> Record Payment
      </a>
    <?php endif; ?>
    <?php if ((float)$bill['amount_paid'] == 0 && $bill['status'] !== 'void'): ?>
      <form method="POST" action="<?= url('/app/bills/' . $bill['id'] . '/void') ?>" class="d-inline"
            onsubmit="return confirm('Void this bill and reverse GL entries?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i> Void</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <span class="text-secondary small d-block">Vendor</span>
        <span class="fw-bold"><?= h($bill['vendor_name']) ?></span>
        <div class="text-muted small"><?= h($bill['vendor_email'] ?? '') ?></div>
      </div>
      <div class="col-md-2">
        <span class="text-secondary small d-block">Bill Date</span>
        <span class="fw-semibold"><?= date_fmt($bill['bill_date']) ?></span>
      </div>
      <div class="col-md-2">
        <span class="text-secondary small d-block">Due Date</span>
        <span class="fw-semibold text-danger"><?= date_fmt($bill['due_date']) ?></span>
      </div>
      <div class="col-md-4">
        <span class="text-secondary small d-block">Notes</span>
        <span class="text-muted small"><?= h($bill['notes'] ?: '—') ?></span>
      </div>
    </div>

    <div class="table-responsive mb-4">
      <table class="table table-bordered align-middle">
        <thead class="table-light small text-secondary">
          <tr>
            <th>Description</th>
            <th class="text-center" style="width:90px;">Qty</th>
            <th class="text-end" style="width:130px;">Unit Cost</th>
            <th class="text-end" style="width:130px;">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lines as $l): ?>
            <tr>
              <td class="fw-semibold"><?= h($l['description']) ?></td>
              <td class="text-center font-monospace"><?= number_format((float)$l['quantity'], 2) ?></td>
              <td class="text-end font-monospace"><?= money((float)$l['unit_price']) ?></td>
              <td class="text-end font-monospace fw-semibold"><?= money((float)$l['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="3" class="text-end text-muted small">Subtotal:</td><td class="text-end font-monospace"><?= money((float)$bill['subtotal']) ?></td></tr>
          <tr><td colspan="3" class="text-end text-muted small">Tax:</td><td class="text-end font-monospace"><?= money((float)$bill['tax_amount']) ?></td></tr>
          <tr class="table-light"><td colspan="3" class="text-end fw-bold">Total:</td><td class="text-end font-monospace fw-bold text-dark fs-5"><?= money((float)$bill['total']) ?></td></tr>
          <tr><td colspan="3" class="text-end text-success fw-semibold">Paid:</td><td class="text-end font-monospace text-success fw-semibold"><?= money((float)$bill['amount_paid']) ?></td></tr>
          <tr class="table-light"><td colspan="3" class="text-end fw-bold text-danger">Balance Due:</td><td class="text-end font-monospace fw-bold text-danger fs-5"><?= money((float)$bill['amount_due']) ?></td></tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php if (!empty($payments)): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0">
      <h6 class="fw-bold mb-0">Payment History</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light text-secondary">
          <tr><th>Date</th><th>Bank Account</th><th>Reference</th><th class="text-end">Amount</th></tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= date_fmt($p['payment_date']) ?></td>
              <td><?= h($p['bank_name']) ?></td>
              <td class="font-monospace"><?= h($p['reference'] ?: '—') ?></td>
              <td class="text-end font-monospace fw-bold text-danger"><?= money((float)$p['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

