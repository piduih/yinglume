<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h4 class="fw-bold mb-0">Invoice: <?= h($invoice['invoice_number']) ?></h4>
      <?php
        $badgeClass = match($invoice['status']) {
          'paid' => 'bg-success-subtle text-success',
          'sent' => 'bg-primary-subtle text-primary',
          'partial' => 'bg-warning-subtle text-warning',
          'void' => 'bg-secondary-subtle text-secondary',
          default => 'bg-light text-dark border',
        };
      ?>
      <span class="badge <?= $badgeClass ?> text-uppercase"><?= h($invoice['status']) ?></span>
    </div>
    <span class="text-secondary small">Customer: <?= h($invoice['customer_name']) ?></span>
  </div>

  <div class="d-flex gap-2">
    <a href="<?= url('/app/invoices') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Invoices
    </a>
    <button onclick="window.print()" class="btn btn-light border btn-sm">
      <i class="bi bi-printer me-1"></i> Print / PDF
    </button>

    <?php if ($invoice['status'] === 'draft'): ?>
      <form method="POST" action="<?= url('/app/invoices/' . $invoice['id'] . '/sent') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
          <i class="bi bi-send me-1"></i> Approve &amp; Post to GL
        </button>
      </form>
    <?php endif; ?>

    <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'void'): ?>
      <a href="<?= url('/app/invoices/' . $invoice['id'] . '/pay') ?>" class="btn btn-success btn-sm fw-semibold">
        <i class="bi bi-cash me-1"></i> Record Payment
      </a>
    <?php endif; ?>

    <?php if ((float)$invoice['amount_paid'] == 0 && $invoice['status'] !== 'void'): ?>
      <form method="POST" action="<?= url('/app/invoices/' . $invoice['id'] . '/void') ?>" class="d-inline" onsubmit="return confirm('Void this invoice?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-x-circle me-1"></i> Void
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- INVOICE DOCUMENT PAPER -->
<div class="card border-0 shadow-sm p-4 p-md-5 mb-4" id="invoicePaper">
  <!-- LETTERHEAD -->
  <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
    <div>
      <h3 class="fw-extrabold text-primary mb-1"><?= h($tenant['name'] ?? 'Company Name') ?></h3>
      <div class="text-secondary small">
        <?= h($tenant['address'] ?? 'Official Commercial Registration') ?><br>
        Email: <?= h($tenant['email'] ?? 'support@company.com') ?> &bull; Phone: <?= h($tenant['phone'] ?? '+603-0000-0000') ?>
      </div>
    </div>
    <div class="text-end">
      <h2 class="fw-bold text-uppercase tracking-wide text-dark mb-1">INVOICE</h2>
      <div class="font-monospace fw-bold fs-5 text-secondary"># <?= h($invoice['invoice_number']) ?></div>
    </div>
  </div>

  <!-- BILL TO & DATES -->
  <div class="row g-4 mb-4">
    <div class="col-sm-6">
      <span class="text-secondary small text-uppercase fw-semibold d-block mb-1">Invoiced To:</span>
      <h5 class="fw-bold mb-1 text-dark"><?= h($invoice['customer_name']) ?></h5>
      <div class="text-secondary small">
        <?= nl2br(h($invoice['customer_address'] ?? '')) ?><br>
        <?php if (!empty($invoice['customer_tax'])): ?>
          Tax ID: <?= h($invoice['customer_tax']) ?><br>
        <?php endif; ?>
        Email: <?= h($invoice['customer_email'] ?? '') ?>
      </div>
    </div>

    <div class="col-sm-6 text-sm-end">
      <div class="mb-2">
        <span class="text-secondary small">Invoice Date:</span>
        <span class="fw-semibold ms-2"><?= date_fmt($invoice['invoice_date']) ?></span>
      </div>
      <div class="mb-2">
        <span class="text-secondary small">Due Date:</span>
        <span class="fw-semibold ms-2 text-danger"><?= date_fmt($invoice['due_date']) ?></span>
      </div>
      <div>
        <span class="text-secondary small">Payment Terms:</span>
        <span class="fw-semibold ms-2"><?= h($invoice['terms'] ?: 'Net 30') ?></span>
      </div>
    </div>
  </div>

  <!-- LINE ITEMS TABLE -->
  <div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
      <thead class="table-light small text-secondary">
        <tr>
          <th>Description</th>
          <th class="text-center" style="width: 100px;">Qty</th>
          <th class="text-end" style="width: 140px;">Unit Price</th>
          <th class="text-end" style="width: 140px;">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
          <tr>
            <td>
              <div class="fw-semibold text-dark"><?= h($l['description']) ?></div>
            </td>
            <td class="text-center font-monospace"><?= number_format((float)$l['quantity'], 2) ?></td>
            <td class="text-end font-monospace"><?= money((float)$l['unit_price']) ?></td>
            <td class="text-end font-monospace fw-semibold"><?= money((float)$l['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="text-end text-secondary small">Subtotal:</td>
          <td class="text-end font-monospace fw-semibold"><?= money((float)$invoice['subtotal']) ?></td>
        </tr>
        <tr>
          <td colspan="3" class="text-end text-secondary small">Tax Amount:</td>
          <td class="text-end font-monospace fw-semibold"><?= money((float)$invoice['tax_amount']) ?></td>
        </tr>
        <tr class="table-light">
          <td colspan="3" class="text-end fw-bold">Total Invoiced:</td>
          <td class="text-end font-monospace fw-bold text-dark fs-5"><?= money((float)$invoice['total']) ?></td>
        </tr>
        <tr>
          <td colspan="3" class="text-end text-success fw-semibold">Amount Paid:</td>
          <td class="text-end font-monospace text-success fw-semibold"><?= money((float)$invoice['amount_paid']) ?></td>
        </tr>
        <tr class="table-light">
          <td colspan="3" class="text-end fw-bold text-danger">Balance Due:</td>
          <td class="text-end font-monospace fw-bold text-danger fs-5"><?= money((float)$invoice['amount_due']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- NOTES & FOOTER -->
  <?php if (!empty($invoice['notes'])): ?>
    <div class="p-3 bg-light rounded small mb-3">
      <strong>Notes:</strong> <?= nl2br(h($invoice['notes'])) ?>
    </div>
  <?php endif; ?>

  <div class="text-center text-muted small mt-4 pt-3 border-top">
    Thank you for your business! Please settle payments by the stated due date.
  </div>
</div>

<!-- PAYMENTS HISTORY (Screen Only) -->
<?php if (!empty($payments)): ?>
  <div class="card border-0 shadow-sm d-print-none mb-4">
    <div class="card-header bg-white py-3 border-0">
      <h6 class="fw-bold mb-0">Receipt &amp; Payment History</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light text-secondary">
          <tr>
            <th>Date</th>
            <th>Bank Account</th>
            <th>Reference</th>
            <th>Notes</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= date_fmt($p['payment_date']) ?></td>
              <td><?= h($p['bank_name']) ?></td>
              <td class="font-monospace"><?= h($p['reference'] ?: '—') ?></td>
              <td class="text-muted"><?= h($p['notes'] ?: '—') ?></td>
              <td class="text-end font-monospace fw-bold text-success"><?= money((float)$p['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
