<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 border-0">
    <h5 class="fw-bold mb-0">Create Sales Invoice</h5>
  </div>
  <div class="card-body p-4">
    <form method="POST" action="<?= url('/app/invoices/create') ?>" id="invoiceForm">
      <?= csrf_field() ?>

      <!-- HEADER INFO -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="contact_id" class="form-label small fw-semibold text-secondary">Customer <span class="text-danger">*</span></label>
          <select class="form-select" id="contact_id" name="contact_id" required>
            <option value="">Select Customer...</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?> (Net <?= (int)$c['payment_terms'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="invoice_number" class="form-label small fw-semibold text-secondary">Invoice # <span class="text-danger">*</span></label>
          <input type="text" class="form-control font-monospace" id="invoice_number" name="invoice_number" value="<?= h($nextNum) ?>" required>
        </div>
        <div class="col-md-2">
          <label for="invoice_date" class="form-label small fw-semibold text-secondary">Invoice Date <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-3">
          <label for="due_date" class="form-label small fw-semibold text-secondary">Payment Due Date <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
        </div>
      </div>

      <!-- LINE ITEMS -->
      <h6 class="fw-bold text-secondary text-uppercase small mb-2">Invoice Line Items</h6>
      <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle" id="invLinesTable">
          <thead class="table-light small">
            <tr>
              <th style="min-width: 180px;">Item / Product</th>
              <th style="min-width: 250px;">Description</th>
              <th style="width: 100px;" class="text-end">Qty</th>
              <th style="width: 130px;" class="text-end">Price</th>
              <th style="width: 140px;">Tax Rate</th>
              <th style="width: 140px;" class="text-end">Line Total</th>
              <th style="width: 40px;"></th>
            </tr>
          </thead>
          <tbody id="invLinesContainer">
            <tr class="inv-line-row">
              <td>
                <select name="item_id[]" class="form-select form-select-sm item-select">
                  <option value="">Custom Item</option>
                  <?php foreach ($items as $it): ?>
                    <option value="<?= $it['id'] ?>" data-price="<?= $it['sales_price'] ?>" data-desc="<?= h($it['name']) ?>">
                      <?= h($it['code']) ?> &mdash; <?= h($it['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="description[]" class="form-control form-control-sm line-desc" placeholder="Service / item description" required>
              </td>
              <td>
                <input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm text-end line-qty" value="1.00">
              </td>
              <td>
                <input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm text-end line-price" value="0.00">
              </td>
              <td>
                <select name="tax_code_id[]" class="form-select form-select-sm line-tax">
                  <option value="" data-rate="0">No Tax (0%)</option>
                  <?php foreach ($taxCodes as $tc): ?>
                    <option value="<?= $tc['id'] ?>" data-rate="<?= (float)$tc['rate'] ?>">
                      <?= h($tc['code']) ?> (<?= ((float)$tc['rate'] * 100) ?>%)
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="text-end font-monospace line-total-display">0.00</td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-inv-line p-0"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="7">
                <button type="button" class="btn btn-sm btn-outline-primary" id="addInvLineBtn">
                  <i class="bi bi-plus-circle me-1"></i> Add Item Line
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- TOTALS SUMMARY -->
      <div class="row justify-content-end mb-4">
        <div class="col-md-5">
          <div class="card bg-light border-0 p-3">
            <div class="d-flex justify-content-between mb-2 small">
              <span class="text-secondary">Subtotal (Net):</span>
              <span class="font-monospace fw-semibold" id="dispSubtotal">$0.00</span>
            </div>
            <div class="d-flex justify-content-between mb-2 small">
              <span class="text-secondary">Tax Total:</span>
              <span class="font-monospace fw-semibold" id="dispTax">$0.00</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
              <span class="fw-bold">Total Payable:</span>
              <span class="font-monospace fw-bold text-primary fs-5" id="dispTotal">$0.00</span>
            </div>
          </div>
        </div>
      </div>

      <!-- NOTES & TERMS -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="notes" class="form-label small fw-semibold text-secondary">Customer Notes</label>
          <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Thank you for your business! Bank transfer details..."></textarea>
        </div>
        <div class="col-md-6">
          <label for="terms" class="form-label small fw-semibold text-secondary">Payment Terms &amp; Conditions</label>
          <textarea class="form-control" id="terms" name="terms" rows="2" placeholder="Payment due within 30 days. Late charges may apply.">Payment due within 30 days of invoice date.</textarea>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="<?= url('/app/invoices') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Sales Invoice</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('invLinesContainer');
  const addBtn = document.getElementById('addInvLineBtn');

  function calculateInvoiceTotals() {
    let subtotal = 0;
    let taxTotal = 0;

    container.querySelectorAll('.inv-line-row').forEach(row => {
      const qty = parseFloat(row.querySelector('.line-qty').value) || 0;
      const price = parseFloat(row.querySelector('.line-price').value) || 0;
      const taxSelect = row.querySelector('.line-tax');
      const taxRate = parseFloat(taxSelect.options[taxSelect.selectedIndex]?.dataset.rate) || 0;

      const lineNet = qty * price;
      const lineTax = lineNet * taxRate;

      row.querySelector('.line-total-display').textContent = lineNet.toFixed(2);

      subtotal += lineNet;
      taxTotal += lineTax;
    });

    const total = subtotal + taxTotal;

    document.getElementById('dispSubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('dispTax').textContent = '$' + taxTotal.toFixed(2);
    document.getElementById('dispTotal').textContent = '$' + total.toFixed(2);
  }

  container.addEventListener('input', calculateInvoiceTotals);
  container.addEventListener('change', function(e) {
    if (e.target.classList.contains('item-select')) {
      const opt = e.target.options[e.target.selectedIndex];
      if (opt.value) {
        const row = e.target.closest('.inv-line-row');
        row.querySelector('.line-desc').value = opt.dataset.desc || '';
        row.querySelector('.line-price').value = parseFloat(opt.dataset.price || 0).toFixed(2);
      }
    }
    calculateInvoiceTotals();
  });

  container.addEventListener('click', function(e) {
    if (e.target.closest('.remove-inv-line')) {
      if (container.querySelectorAll('.inv-line-row').length > 1) {
        e.target.closest('.inv-line-row').remove();
        calculateInvoiceTotals();
      }
    }
  });

  addBtn.addEventListener('click', function() {
    const firstRow = container.querySelector('.inv-line-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.line-desc').value = '';
    newRow.querySelector('.line-qty').value = '1.00';
    newRow.querySelector('.line-price').value = '0.00';
    newRow.querySelector('.line-total-display').textContent = '0.00';
    newRow.querySelector('.item-select').selectedIndex = 0;
    container.appendChild(newRow);
  });

  calculateInvoiceTotals();
});
</script>
