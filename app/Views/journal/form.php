<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 border-0">
    <h5 class="fw-bold mb-0">Create General Journal Entry</h5>
  </div>
  <div class="card-body p-4">
    <form method="POST" action="<?= url('/app/journal/create') ?>" id="journalForm">
      <?= csrf_field() ?>

      <!-- HEADER FIELDS -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <label for="entry_date" class="form-label small fw-semibold text-secondary">Entry Date <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="entry_date" name="entry_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-3">
          <label for="reference" class="form-label small fw-semibold text-secondary">Reference #</label>
          <input type="text" class="form-control font-monospace" id="reference" name="reference" placeholder="e.g. JE-<?= date('Ymd-His') ?>">
        </div>
        <div class="col-md-6">
          <label for="description" class="form-label small fw-semibold text-secondary">Journal Narration / Description <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="description" name="description" placeholder="e.g. Month-end payroll accrual" required>
        </div>
      </div>

      <!-- LINES TABLE -->
      <h6 class="fw-bold text-secondary text-uppercase small mb-2">Transaction Line Items</h6>
      <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle" id="linesTable">
          <thead class="table-light small">
            <tr>
              <th style="min-width: 250px;">Account <span class="text-danger">*</span></th>
              <th>Line Narration</th>
              <th style="width: 160px;" class="text-end">Debit (Dr)</th>
              <th style="width: 160px;" class="text-end">Credit (Cr)</th>
              <th style="width: 50px;"></th>
            </tr>
          </thead>
          <tbody id="linesContainer">
            <!-- Line 1 -->
            <tr class="line-row">
              <td>
                <select name="account_id[]" class="form-select form-select-sm account-select" required>
                  <option value="">Select Account...</option>
                  <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= h($a['code']) ?> &mdash; <?= h($a['name']) ?> (<?= h($a['type_name']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="line_description[]" class="form-control form-control-sm" placeholder="Narration (optional)">
              </td>
              <td>
                <input type="number" step="0.01" name="debit[]" class="form-control form-control-sm text-end debit-input" value="0.00">
              </td>
              <td>
                <input type="number" step="0.01" name="credit[]" class="form-control form-control-sm text-end credit-input" value="0.00">
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-line p-0"><i class="bi bi-trash"></i></button>
              </td>
            </tr>

            <!-- Line 2 -->
            <tr class="line-row">
              <td>
                <select name="account_id[]" class="form-select form-select-sm account-select" required>
                  <option value="">Select Account...</option>
                  <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= h($a['code']) ?> &mdash; <?= h($a['name']) ?> (<?= h($a['type_name']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="line_description[]" class="form-control form-control-sm" placeholder="Narration (optional)">
              </td>
              <td>
                <input type="number" step="0.01" name="debit[]" class="form-control form-control-sm text-end debit-input" value="0.00">
              </td>
              <td>
                <input type="number" step="0.01" name="credit[]" class="form-control form-control-sm text-end credit-input" value="0.00">
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-line p-0"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                  <i class="bi bi-plus-circle me-1"></i> Add Another Line
                </button>
              </td>
              <td class="text-end font-monospace" id="totalDebitCell">0.00</td>
              <td class="text-end font-monospace" id="totalCreditCell">0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- BALANCE STATUS ALERT -->
      <div id="balanceAlert" class="alert alert-info py-2 d-flex justify-content-between align-items-center mb-4 small">
        <div>
          <span class="fw-bold" id="balanceStatusText">Balanced (Difference: 0.00)</span>
        </div>
        <div class="small text-muted">A valid entry requires Total Debits = Total Credits.</div>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="<?= url('/app/journal') ?>" class="btn btn-outline-secondary">Cancel</a>
        <div class="d-flex gap-2">
          <button type="submit" name="post_direct" value="0" class="btn btn-light border px-4">Save as Draft</button>
          <button type="submit" name="post_direct" value="1" class="btn btn-primary px-4 fw-semibold" id="postBtn">Post to General Ledger</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('linesContainer');
  const addBtn = document.getElementById('addLineBtn');
  const postBtn = document.getElementById('postBtn');
  const totalDebCell = document.getElementById('totalDebitCell');
  const totalCredCell = document.getElementById('totalCreditCell');
  const balanceAlert = document.getElementById('balanceAlert');
  const balanceText = document.getElementById('balanceStatusText');

  function calculateTotals() {
    let sumDeb = 0;
    let sumCred = 0;

    container.querySelectorAll('.line-row').forEach(row => {
      const d = parseFloat(row.querySelector('.debit-input').value) || 0;
      const c = parseFloat(row.querySelector('.credit-input').value) || 0;
      sumDeb += d;
      sumCred += c;
    });

    totalDebCell.textContent = sumDeb.toFixed(2);
    totalCredCell.textContent = sumCred.toFixed(2);

    const diff = Math.abs(sumDeb - sumCred);
    if (diff < 0.001 && sumDeb > 0) {
      balanceAlert.className = 'alert alert-success py-2 d-flex justify-content-between align-items-center mb-4 small';
      balanceText.textContent = 'Balanced: Debits match Credits ($' + sumDeb.toFixed(2) + ')';
      postBtn.disabled = false;
    } else {
      balanceAlert.className = 'alert alert-danger py-2 d-flex justify-content-between align-items-center mb-4 small';
      balanceText.textContent = 'Out of balance by ' + diff.toFixed(2) + ' (Debits: ' + sumDeb.toFixed(2) + ', Credits: ' + sumCred.toFixed(2) + ')';
    }
  }

  container.addEventListener('input', calculateTotals);

  container.addEventListener('click', function(e) {
    if (e.target.closest('.remove-line')) {
      if (container.querySelectorAll('.line-row').length > 2) {
        e.target.closest('.line-row').remove();
        calculateTotals();
      } else {
        alert('A journal entry must have at least 2 lines.');
      }
    }
  });

  addBtn.addEventListener('click', function() {
    const firstRow = container.querySelector('.line-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('input').forEach(inp => {
      if (inp.type === 'number') inp.value = '0.00';
      else inp.value = '';
    });
    newRow.querySelector('select').selectedIndex = 0;
    container.appendChild(newRow);
  });

  calculateTotals();
});
</script>
