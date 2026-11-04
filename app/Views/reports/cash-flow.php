<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Statement of Cash Flows</h4>
    <p class="text-secondary small mb-0">Direct cash movement showing receipts from customers and payments to vendors.</p>
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
    <form method="GET" action="<?= url('/app/reports/cash-flow') ?>" class="row g-3 align-items-end">
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
    <h5 class="text-secondary fw-semibold mb-1">Statement of Cash Flows</h5>
    <div class="text-muted small">For period <?= date_fmt($startDate) ?> to <?= date_fmt($endDate) ?></div>
  </div>

  <h6 class="fw-bold text-uppercase text-secondary small mb-3">1. Cash Flows from Operating Activities</h6>
  <table class="table table-sm align-middle mb-4">
    <tbody>
      <tr>
        <td class="ps-3">Cash received from customers (Invoice Payments)</td>
        <td class="text-end font-monospace pe-3 text-success">+<?= money((float)$report['cash_from_customers']) ?></td>
      </tr>
      <tr>
        <td class="ps-3">Cash disbursed to suppliers/vendors (Bill Payments)</td>
        <td class="text-end font-monospace pe-3 text-danger">-<?= money((float)$report['cash_to_vendors']) ?></td>
      </tr>
    </tbody>
    <tfoot>
      <tr class="table-light fw-bold border-top">
        <td class="ps-3 text-uppercase">Net Cash Provided by / (Used in) Operating Activities</td>
        <td class="text-end font-monospace pe-3 <?= (float)$report['net_operating'] >= 0 ? 'text-success' : 'text-danger' ?>">
          <?= money((float)$report['net_operating']) ?>
        </td>
      </tr>
    </tfoot>
  </table>

  <h6 class="fw-bold text-uppercase text-secondary small mb-3">2. Net Change in Cash</h6>
  <div class="p-3 rounded-3 d-flex justify-content-between align-items-center <?= (float)$report['net_cash_flow'] >= 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?>">
    <div>
      <h5 class="fw-bold mb-0">NET INCREASE / (DECREASE) IN CASH</h5>
      <span class="small opacity-75">Period net cash surplus or deficit</span>
    </div>
    <div class="fs-3 fw-bold font-monospace">
      <?= money((float)$report['net_cash_flow']) ?>
    </div>
  </div>
</div>

