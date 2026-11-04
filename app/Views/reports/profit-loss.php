<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Profit & Loss Statement</h4>
    <p class="text-secondary small mb-0">Income statement reflecting revenues, costs, and net earnings.</p>
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
    <form method="GET" action="<?= url('/app/reports/profit-loss') ?>" class="row g-3 align-items-end">
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
          <i class="bi bi-funnel me-1"></i> Update Report
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Financial Statement Paper -->
<div class="card border-0 shadow-sm p-4 p-md-5">
  <div class="text-center mb-4 border-bottom pb-4">
    <h3 class="fw-bold mb-1"><?= h(session('tenant_name', 'Company')) ?></h3>
    <h5 class="text-secondary fw-semibold mb-1">Statement of Profit & Loss</h5>
    <div class="text-muted small">For the period <?= date_fmt($startDate) ?> to <?= date_fmt($endDate) ?></div>
  </div>

  <!-- Revenue Section -->
  <h6 class="fw-bold text-uppercase text-secondary small mb-3">Operating Revenue</h6>
  <table class="table table-sm align-middle mb-4">
    <tbody>
      <?php if (empty($report['revenues'])): ?>
        <tr>
          <td colspan="2" class="text-secondary ps-3 py-2 small fst-italic">No revenue recorded in this period.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($report['revenues'] as $rev): ?>
          <tr>
            <td class="ps-3">
              <span class="font-monospace text-muted small me-2"><?= h($rev['code']) ?></span>
              <?= h($rev['account_name']) ?>
            </td>
            <td class="text-end font-monospace pe-3"><?= money((float)$rev['net_amount']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="table-light fw-bold">
        <td class="ps-3 text-uppercase">Total Revenue</td>
        <td class="text-end font-monospace pe-3 text-success"><?= money((float)$report['total_revenue']) ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- Expense Section -->
  <h6 class="fw-bold text-uppercase text-secondary small mb-3">Operating Expenses</h6>
  <table class="table table-sm align-middle mb-4">
    <tbody>
      <?php if (empty($report['expenses'])): ?>
        <tr>
          <td colspan="2" class="text-secondary ps-3 py-2 small fst-italic">No expenses recorded in this period.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($report['expenses'] as $exp): ?>
          <tr>
            <td class="ps-3">
              <span class="font-monospace text-muted small me-2"><?= h($exp['code']) ?></span>
              <?= h($exp['account_name']) ?>
            </td>
            <td class="text-end font-monospace pe-3"><?= money((float)$exp['net_amount']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="table-light fw-bold">
        <td class="ps-3 text-uppercase">Total Expenses</td>
        <td class="text-end font-monospace pe-3 text-danger"><?= money((float)$report['total_expense']) ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- Net Income Highlight Box -->
  <div class="p-3 rounded-3 mt-2 d-flex justify-content-between align-items-center <?= $report['net_income'] >= 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?>">
    <div>
      <h5 class="fw-bold mb-0">NET PROFIT / (LOSS)</h5>
      <span class="small opacity-75">Operating Income minus Operating Expenses</span>
    </div>
    <div class="fs-3 fw-bold font-monospace">
      <?= money((float)$report['net_income']) ?>
    </div>
  </div>
</div>

