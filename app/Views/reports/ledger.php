<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">General Ledger</h4>
    <p class="text-secondary small mb-0">Detailed transaction ledger and running balances per account.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i> Print / Export PDF
    </button>
  </div>
</div>

<!-- Account & Date Filter -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= url('/app/reports/ledger') ?>" class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-semibold text-secondary">Select Account</label>
        <select name="account_id" class="form-select" required>
          <?php foreach ($accounts as $acc): ?>
            <option value="<?= $acc['id'] ?>" <?= $selectedAccId == $acc['id'] ? 'selected' : '' ?>>
              <?= h($acc['code']) ?> &mdash; <?= h($acc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary">Start Date</label>
        <input type="date" class="form-control" name="start_date" value="<?= h($startDate) ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary">End Date</label>
        <input type="date" class="form-control" name="end_date" value="<?= h($endDate) ?>" required>
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-funnel"></i>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Ledger Sheet Paper -->
<div class="card border-0 shadow-sm p-4">
  <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
    <div>
      <h5 class="fw-bold mb-0">
        <span class="font-monospace text-primary"><?= h($account['code'] ?? '') ?></span>
        &mdash; <?= h($account['name'] ?? 'Account') ?>
      </h5>
      <span class="text-secondary small">Period: <?= date_fmt($startDate) ?> to <?= date_fmt($endDate) ?></span>
    </div>
    <div class="text-end">
      <span class="text-secondary small d-block">Normal Balance: <strong class="text-uppercase"><?= h($report['normal_balance']) ?></strong></span>
      <span class="text-secondary small">Opening: <strong class="font-monospace"><?= money((float)$report['opening_balance']) ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-4">
      <thead class="table-light small">
        <tr>
          <th style="width: 110px;">Date</th>
          <th style="width: 140px;">Reference</th>
          <th>Description</th>
          <th class="text-end" style="width: 130px;">Debit</th>
          <th class="text-end" style="width: 130px;">Credit</th>
          <th class="text-end" style="width: 150px;">Running Balance</th>
        </tr>
      </thead>
      <tbody>
        <!-- Opening Balance Row -->
        <tr class="table-light text-secondary">
          <td colspan="3" class="fw-semibold ps-3">Opening Balance Brought Forward</td>
          <td class="text-end">&mdash;</td>
          <td class="text-end">&mdash;</td>
          <td class="text-end font-monospace fw-bold pe-3"><?= money((float)$report['opening_balance']) ?></td>
        </tr>

        <?php if (empty($report['transactions'])): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-secondary small fst-italic">
              No transactions recorded for this account during the selected date range.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($report['transactions'] as $tx): ?>
            <tr>
              <td class="small"><?= date_fmt($tx['entry_date']) ?></td>
              <td class="font-monospace small fw-semibold"><?= h($tx['reference'] ?? '—') ?></td>
              <td>
                <div class="small fw-semibold"><?= h($tx['line_description'] ?: $tx['entry_description']) ?></div>
                <?php if (!empty($tx['entry_description']) && $tx['line_description'] !== $tx['entry_description']): ?>
                  <div class="text-secondary small"><?= h($tx['entry_description']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end font-monospace text-success small">
                <?= (float)$tx['debit'] > 0 ? money((float)$tx['debit']) : '—' ?>
              </td>
              <td class="text-end font-monospace text-danger small">
                <?= (float)$tx['credit'] > 0 ? money((float)$tx['credit']) : '—' ?>
              </td>
              <td class="text-end font-monospace fw-bold small">
                <?= money((float)$tx['running_balance']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light fw-bold border-top border-2">
          <td colspan="3" class="ps-3 text-uppercase">Period Total Activity</td>
          <td class="text-end font-monospace text-success"><?= money((float)$report['total_debit']) ?></td>
          <td class="text-end font-monospace text-danger"><?= money((float)$report['total_credit']) ?></td>
          <td class="text-end font-monospace text-primary fs-6 pe-3"><?= money((float)$report['closing_balance']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

