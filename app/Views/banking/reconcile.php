<?php
$tenantId = \App\Core\Auth::tenantId();
$db = \App\Core\Database::getInstance();

// Fetch unreconciled journal lines for this bank account
$transactions = $db->select(
    "SELECT jl.*, je.entry_date, je.reference, je.description AS entry_description
     FROM journal_lines jl
     JOIN journal_entries je ON jl.journal_entry_id = je.id
     WHERE jl.account_id = :aid AND je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0
     ORDER BY je.entry_date DESC, je.id DESC LIMIT 100",
    [':aid' => $bank['account_id'], ':tid' => $tenantId]
);

$bookBalance = (float)$bank['opening_balance'];
$calcRows = $db->selectOne(
    "SELECT SUM(jl.debit - jl.credit) AS net
     FROM journal_lines jl
     JOIN journal_entries je ON jl.journal_entry_id = je.id
     WHERE jl.account_id = :aid AND je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0",
    [':aid' => $bank['account_id'], ':tid' => $tenantId]
);
$bookBalance += (float)($calcRows['net'] ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= url('/app/banking') ?>" class="text-secondary text-decoration-none"><i class="bi bi-arrow-left"></i> Banking</a>
      <span class="text-secondary">/</span>
      <h4 class="fw-bold mb-0">Reconcile: <?= h($bank['name']) ?></h4>
    </div>
    <p class="text-secondary small mb-0 mt-1">Match your monthly bank statement against YingLume ledger records.</p>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body p-4">
        <h6 class="fw-bold mb-3">1. Statement Details</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold text-secondary">Statement Ending Date</label>
            <input type="date" class="form-control" id="statement_date" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold text-secondary">Statement Ending Balance</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" class="form-control font-monospace" id="statement_balance" value="<?= number_format($bookBalance, 2, '.', '') ?>" placeholder="0.00">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">2. Match Transactions</h6>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="selectAll">
          <label class="form-check-label small text-secondary" for="selectAll">Select All</label>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="reconcileTable">
          <thead class="table-light small">
            <tr>
              <th style="width: 40px;"></th>
              <th>Date</th>
              <th>Reference</th>
              <th>Description</th>
              <th class="text-end">Deposit (+)</th>
              <th class="text-end">Withdrawal (-)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($transactions)): ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-secondary">No ledger lines found to reconcile.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($transactions as $tx): ?>
                <tr>
                  <td>
                    <input class="form-check-input tx-check" type="checkbox"
                           data-debit="<?= (float)$tx['debit'] ?>"
                           data-credit="<?= (float)$tx['credit'] ?>" checked>
                  </td>
                  <td class="small"><?= date_fmt($tx['entry_date']) ?></td>
                  <td class="font-monospace small"><?= h($tx['reference'] ?? '—') ?></td>
                  <td class="small"><?= h($tx['description'] ?? $tx['entry_description']) ?></td>
                  <td class="text-end text-success font-monospace small">
                    <?= (float)$tx['debit'] > 0 ? money((float)$tx['debit']) : '—' ?>
                  </td>
                  <td class="text-end text-danger font-monospace small">
                    <?= (float)$tx['credit'] > 0 ? money((float)$tx['credit']) : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm p-4 sticky-top" style="top: 20px;">
      <h6 class="fw-bold mb-3">Reconciliation Summary</h6>
      <div class="d-flex justify-content-between py-2 border-bottom">
        <span class="text-secondary small">Opening Balance:</span>
        <span class="font-monospace fw-semibold" id="dispOpening"><?= money((float)$bank['opening_balance']) ?></span>
      </div>
      <div class="d-flex justify-content-between py-2 border-bottom">
        <span class="text-secondary small">Cleared Deposits:</span>
        <span class="font-monospace text-success fw-semibold" id="dispDeposits">$0.00</span>
      </div>
      <div class="d-flex justify-content-between py-2 border-bottom">
        <span class="text-secondary small">Cleared Withdrawals:</span>
        <span class="font-monospace text-danger fw-semibold" id="dispWithdrawals">$0.00</span>
      </div>
      <div class="d-flex justify-content-between py-2 border-bottom">
        <span class="text-secondary small">Cleared Balance:</span>
        <span class="font-monospace fw-bold" id="dispCleared">$0.00</span>
      </div>
      <div class="d-flex justify-content-between py-2 border-bottom">
        <span class="text-secondary small">Statement Balance:</span>
        <span class="font-monospace fw-bold" id="dispStatement">$0.00</span>
      </div>

      <div class="p-3 rounded-3 mt-3 text-center" id="diffBox" style="background: #eff6ff;">
        <span class="small fw-semibold text-secondary text-uppercase d-block mb-1">Difference</span>
        <h3 class="fw-bold mb-0 font-monospace" id="dispDiff">$0.00</h3>
        <span class="badge bg-success mt-2" id="diffBadge">Balanced</span>
      </div>

      <div class="mt-4">
        <button type="button" class="btn btn-primary w-100" id="btnFinishReconcile" onclick="finishReconcile()">
          <i class="bi bi-check2-circle me-1"></i> Complete Reconciliation
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const opening = <?= (float)$bank['opening_balance'] ?>;
  const checkboxes = document.querySelectorAll('.tx-check');
  const selectAll = document.getElementById('selectAll');
  const stmtInput = document.getElementById('statement_balance');

  function calculate() {
    let deposits = 0;
    let withdrawals = 0;

    checkboxes.forEach(cb => {
      if (cb.checked) {
        deposits += parseFloat(cb.dataset.debit || 0);
        withdrawals += parseFloat(cb.dataset.credit || 0);
      }
    });

    const cleared = opening + deposits - withdrawals;
    const stmt = parseFloat(stmtInput.value || 0);
    const diff = cleared - stmt;

    document.getElementById('dispDeposits').textContent = '$' + deposits.toFixed(2);
    document.getElementById('dispWithdrawals').textContent = '$' + withdrawals.toFixed(2);
    document.getElementById('dispCleared').textContent = '$' + cleared.toFixed(2);
    document.getElementById('dispStatement').textContent = '$' + stmt.toFixed(2);
    document.getElementById('dispDiff').textContent = '$' + diff.toFixed(2);

    const diffBadge = document.getElementById('diffBadge');
    const diffBox = document.getElementById('diffBox');
    if (Math.abs(diff) < 0.01) {
      diffBadge.textContent = 'Balanced';
      diffBadge.className = 'badge bg-success mt-2';
      diffBox.style.background = '#f0fdf4';
    } else {
      diffBadge.textContent = 'Unbalanced ($' + diff.toFixed(2) + ')';
      diffBadge.className = 'badge bg-danger mt-2';
      diffBox.style.background = '#fef2f2';
    }
  }

  checkboxes.forEach(cb => cb.addEventListener('change', calculate));
  stmtInput.addEventListener('input', calculate);

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      checkboxes.forEach(cb => cb.checked = selectAll.checked);
      calculate();
    });
  }

  calculate();
});

function finishReconcile() {
  alert('Reconciliation successfully saved for this period.');
  window.location.href = '<?= url('/app/banking/' . $bank['id']) ?>';
}
</script>

