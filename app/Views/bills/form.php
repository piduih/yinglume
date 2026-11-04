<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 border-0">
    <h5 class="fw-bold mb-0">New Purchase Bill</h5>
    <p class="text-secondary small mb-0">Record a vendor invoice and auto-post to Accounts Payable.</p>
  </div>
  <div class="card-body p-4">
    <form method="POST" action="<?= url('/app/bills/create') ?>" id="billForm">
      <?= csrf_field() ?>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="contact_id" class="form-label small fw-semibold text-secondary">Vendor / Supplier <span class="text-danger">*</span></label>
          <select class="form-select" id="contact_id" name="contact_id" required>
            <option value="">Select Vendor...</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= $v['id'] ?>"><?= h($v['name']) ?> (Net <?= (int)$v['payment_terms'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="bill_number" class="form-label small fw-semibold text-secondary">Vendor Bill # <span class="text-danger">*</span></label>
          <input type="text" class="form-control font-monospace" id="bill_number" name="bill_number" placeholder="e.g. VND-INV-20241001" required>
        </div>
        <div class="col-md-2">
          <label for="bill_date" class="form-label small fw-semibold text-secondary">Bill Date <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="bill_date" name="bill_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-3">
          <label for="due_date" class="form-label small fw-semibold text-secondary">Payment Due Date</label>
          <input type="date" class="form-control" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
        </div>
      </div>

      <h6 class="fw-bold text-secondary text-uppercase small mb-2">Bill Line Items</h6>
      <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle" id="billLinesTable">
          <thead class="table-light small">
            <tr>
              <th style="min-width: 180px;">Item / Product</th>
              <th style="min-width: 250px;">Description</th>
              <th style="width: 90px;" class="text-end">Qty</th>
              <th style="width: 130px;" class="text-end">Unit Cost</th>
              <th style="width: 140px;">Expense Account</th>
              <th style="width: 140px;">Tax Rate</th>
              <th style="width: 140px;" class="text-end">Line Total</th>
              <th style="width: 40px;"></th>
            </tr>
          </thead>
          <tbody id="billLinesContainer">
            <tr class="bill-line-row">
              <td>
                <select name="item_id[]" class="form-select form-select-sm bill-item-select">
                  <option value="">Custom Expense</option>
                  <?php foreach ($items as $it): ?>
                    <option value="<?= $it['id'] ?>" data-price="<?= $it['purchase_price'] ?>" data-desc="<?= h($it['name']) ?>">
                      <?= h($it['code']) ?> — <?= h($it['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="description[]" class="form-control form-control-sm" placeholder="Description" required>
              </td>
              <td>
                <input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm text-end bill-qty" value="1.00">
              </td>
              <td>
                <input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm text-end bill-price" value="0.00">
              </td>
              <td>
                <select name="account_id[]" class="form-select form-select-sm">
                  <?php foreach ($expenseAccounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= h($acc['code']) ?> <?= h($acc['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select name="tax_code_id[]" class="form-select form-select-sm bill-tax">
                  <option value="" data-rate="0">No Tax (0%)</option>
                  <?php foreach ($taxCodes as $tc): ?>
                    <option value="<?= $tc['id'] ?>" data-rate="<?= (float)$tc['rate'] ?>">
                      <?= h($tc['code']) ?> (<?= ((float)$tc['rate'] * 100) ?>%)
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="text-end font-monospace bill-line-total">0.00</td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-bill-line p-0"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="8">
                <button type="button" class="btn btn-sm btn-outline-primary" id="addBillLineBtn">
                  <i class="bi bi-plus-circle me-1"></i> Add Expense Line
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="row justify-content-end mb-4">
        <div class="col-md-4">
          <div class="card bg-light border-0 p-3">
            <div class="d-flex justify-content-between mb-2 small">
              <span class="text-secondary">Subtotal:</span>
              <span class="font-monospace fw-semibold" id="billSubtotal">$0.00</span>
            </div>
            <div class="d-flex justify-content-between mb-2 small">
              <span class="text-secondary">Tax:</span>
              <span class="font-monospace fw-semibold" id="billTax">$0.00</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
              <span class="fw-bold">Total Payable:</span>
              <span class="font-monospace fw-bold text-danger fs-5" id="billTotal">$0.00</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <label for="notes" class="form-label small fw-semibold text-secondary">Internal Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Notes for this vendor bill..."></textarea>
      </div>

      <div class="d-flex justify-content-between">
        <a href="<?= url('/app/bills') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4 fw-semibold">Save &amp; Post Bill to GL</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('billLinesContainer');
  const addBtn = document.getElementById('addBillLineBtn');

  function calcBillTotals() {
    let sub = 0, tax = 0;
    container.querySelectorAll('.bill-line-row').forEach(row => {
      const qty = parseFloat(row.querySelector('.bill-qty').value) || 0;
      const price = parseFloat(row.querySelector('.bill-price').value) || 0;
      const taxSel = row.querySelector('.bill-tax');
      const taxRate = parseFloat(taxSel.options[taxSel.selectedIndex]?.dataset.rate) || 0;
      const lineNet = qty * price;
      row.querySelector('.bill-line-total').textContent = lineNet.toFixed(2);
      sub += lineNet;
      tax += lineNet * taxRate;
    });
    document.getElementById('billSubtotal').textContent = '$' + sub.toFixed(2);
    document.getElementById('billTax').textContent = '$' + tax.toFixed(2);
    document.getElementById('billTotal').textContent = '$' + (sub + tax).toFixed(2);
  }

  container.addEventListener('input', calcBillTotals);
  container.addEventListener('change', function(e) {
    if (e.target.classList.contains('bill-item-select')) {
      const opt = e.target.options[e.target.selectedIndex];
      if (opt.value) {
        const row = e.target.closest('.bill-line-row');
        row.querySelector('[name="description[]"]').value = opt.dataset.desc || '';
        row.querySelector('.bill-price').value = parseFloat(opt.dataset.price || 0).toFixed(2);
      }
    }
    calcBillTotals();
  });

  container.addEventListener('click', function(e) {
    if (e.target.closest('.remove-bill-line')) {
      if (container.querySelectorAll('.bill-line-row').length > 1) {
        e.target.closest('.bill-line-row').remove();
        calcBillTotals();
      }
    }
  });

  addBtn.addEventListener('click', function() {
    const newRow = container.querySelector('.bill-line-row').cloneNode(true);
    newRow.querySelector('[name="description[]"]').value = '';
    newRow.querySelector('.bill-qty').value = '1.00';
    newRow.querySelector('.bill-price').value = '0.00';
    newRow.querySelector('.bill-line-total').textContent = '0.00';
    newRow.querySelector('.bill-item-select').selectedIndex = 0;
    container.appendChild(newRow);
  });

  calcBillTotals();
});
</script>

