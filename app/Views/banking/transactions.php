<?php
$categories = \App\Core\Database::getInstance()->select(
    "SELECT id, code, name, account_type_id FROM chart_of_accounts WHERE tenant_id = :tid AND is_active = 1 ORDER BY code ASC",
    [':tid' => \App\Core\Auth::tenantId()]
);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= url('/app/banking') ?>" class="text-secondary text-decoration-none"><i class="bi bi-arrow-left"></i> Banking</a>
      <span class="text-secondary">/</span>
      <h4 class="fw-bold mb-0"><?= h($bank['name']) ?></h4>
    </div>
    <p class="text-secondary small mb-0 mt-1"><?= h($bank['bank_name']) ?> &bull; Account: <span class="font-monospace"><?= h($bank['account_number']) ?></span></p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#depositModal">
      <i class="bi bi-plus-circle me-1"></i> Deposit
    </button>
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdrawalModal">
      <i class="bi bi-dash-circle me-1"></i> Withdrawal
    </button>
    <a href="<?= url('/app/banking/' . $bank['id'] . '/reconcile') ?>" class="btn btn-primary">
      <i class="bi bi-check2-circle me-1"></i> Reconcile Account
    </a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm p-3">
      <span class="text-secondary small fw-semibold text-uppercase">Opening Balance</span>
      <h4 class="fw-bold mb-0 text-muted mt-1"><?= money((float)$bank['opening_balance']) ?></h4>
      <span class="text-secondary small">As of <?= date_fmt($bank['opening_date']) ?></span>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm p-3 bg-light">
      <span class="text-secondary small fw-semibold text-uppercase">Current Book Balance</span>
      <h3 class="fw-bold mb-0 text-primary mt-1"><?= money((float)$currentBalance) ?></h3>
      <span class="text-secondary small">Calculated from posted ledger entries</span>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
    <h6 class="fw-bold mb-0">General Ledger Activity (Recent 100 Transactions)</h6>
    <span class="badge bg-secondary-subtle text-secondary"><?= count($transactions) ?> entries</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th>Date</th>
          <th>Reference</th>
          <th>Description</th>
          <th>Source</th>
          <th class="text-end">Money In (Debit)</th>
          <th class="text-end">Money Out (Credit)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-secondary">
              No transactions found for this account yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><?= date_fmt($tx['entry_date']) ?></td>
              <td class="font-monospace fw-semibold small"><?= h($tx['reference'] ?? '—') ?></td>
              <td><?= h($tx['description'] ?? $tx['entry_description']) ?></td>
              <td>
                <span class="badge bg-light text-dark border"><?= h(ucfirst($tx['source'] ?? 'general')) ?></span>
              </td>
              <td class="text-end text-success fw-semibold font-monospace">
                <?= (float)$tx['debit'] > 0 ? '+' . money((float)$tx['debit']) : '—' ?>
              </td>
              <td class="text-end text-danger fw-semibold font-monospace">
                <?= (float)$tx['credit'] > 0 ? '-' . money((float)$tx['credit']) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Deposit -->
<div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('/app/banking/' . $bank['id'] . '/transaction') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="deposit">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-success"><i class="bi bi-plus-circle me-1"></i> Record Deposit (Money In)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Deposit Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" class="form-control font-monospace" name="amount" placeholder="0.00" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Credit Category / Income Account <span class="text-danger">*</span></label>
            <select class="form-select" name="offset_account_id" required>
              <option value="">Select Account (Offset)...</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= h($cat['code']) ?> &mdash; <?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text small">e.g. Sales Revenue, Owner Equity / Capital Contribution, Interest Income.</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Description / Remarks</label>
            <input type="text" class="form-control" name="description" placeholder="e.g. Client wire deposit" required>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success px-4">Record Deposit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Withdrawal -->
<div class="modal fade" id="withdrawalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('/app/banking/' . $bank['id'] . '/transaction') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="withdrawal">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i> Record Withdrawal (Money Out)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Withdrawal Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" class="form-control font-monospace" name="amount" placeholder="0.00" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Debit Category / Expense Account <span class="text-danger">*</span></label>
            <select class="form-select" name="offset_account_id" required>
              <option value="">Select Account (Offset)...</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= h($cat['code']) ?> &mdash; <?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text small">e.g. Office Supplies, Bank Service Charges, Drawings.</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Description / Remarks</label>
            <input type="text" class="form-control" name="description" placeholder="e.g. Bank charges or cash withdrawal" required>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-4">Record Withdrawal</button>
        </div>
      </form>
    </div>
  </div>
</div>

