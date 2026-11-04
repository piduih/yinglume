<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Trial Balance</h4>
    <p class="text-secondary small mb-0">List of all active general ledger accounts with total debits and credits.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i> Print / Export PDF
    </button>
  </div>
</div>

<!-- Date Filter -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= url('/app/reports/trial-balance') ?>" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-semibold text-secondary">Start Date</label>
        <input type="date" class="form-control" name="start_date" value="<?= h($startDate) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold text-secondary">End Date</label>
        <input type="date" class="form-control" name="end_date" value="<?= h($endDate) ?>" required>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-funnel me-1"></i> Filter
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Financial Paper -->
<div class="card border-0 shadow-sm p-4 p-md-5">
  <div class="text-center mb-4 border-bottom pb-4">
    <h3 class="fw-bold mb-1"><?= h(session('tenant_name', 'Company')) ?></h3>
    <h5 class="text-secondary fw-semibold mb-1">Trial Balance</h5>
    <div class="text-muted small">Period: <?= date_fmt($startDate) ?> to <?= date_fmt($endDate) ?></div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-4">
      <thead class="table-light small">
        <tr>
          <th style="width: 120px;">Code</th>
          <th>Account Name</th>
          <th class="text-end" style="width: 180px;">Debit</th>
          <th class="text-end" style="width: 180px;">Credit</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($report['items'])): ?>
          <tr>
            <td colspan="4" class="text-center py-4 text-secondary">No transaction activity found for this period.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($report['items'] as $item): ?>
            <tr>
              <td class="font-monospace fw-semibold small"><?= h($item['code']) ?></td>
              <td><?= h($item['name']) ?></td>
              <td class="text-end font-monospace"><?= (float)$item['debit'] > 0 ? money((float)$item['debit']) : '—' ?></td>
              <td class="text-end font-monospace"><?= (float)$item['credit'] > 0 ? money((float)$item['credit']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light fw-bold border-top border-2">
          <td colspan="2" class="text-uppercase ps-3">Total Activity</td>
          <td class="text-end font-monospace pe-3"><?= money((float)$report['total_debit']) ?></td>
          <td class="text-end font-monospace pe-3"><?= money((float)$report['total_credit']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="text-center">
    <?php if ($report['is_balanced']): ?>
      <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
        <i class="bi bi-check-circle me-1"></i> Debits equal Credits (Trial Balance is in balance)
      </span>
    <?php else: ?>
      <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
        <i class="bi bi-exclamation-triangle me-1"></i> Out of balance: Difference is <?= money(abs((float)$report['total_debit'] - (float)$report['total_credit'])) ?>
      </span>
    <?php endif; ?>
  </div>
</div>

