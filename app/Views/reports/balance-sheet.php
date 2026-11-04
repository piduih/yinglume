<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Balance Sheet</h4>
    <p class="text-secondary small mb-0">Statement of financial position showing assets, liabilities, and owner equity.</p>
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
    <form method="GET" action="<?= url('/app/reports/balance-sheet') ?>" class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label small fw-semibold text-secondary">As of Date</label>
        <input type="date" class="form-control" name="as_of" value="<?= h($asOfDate) ?>" required>
      </div>
      <div class="col-md-6">
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
    <h5 class="text-secondary fw-semibold mb-1">Statement of Financial Position (Balance Sheet)</h5>
    <div class="text-muted small">As of <?= date_fmt($asOfDate) ?></div>
  </div>

  <?php if (!$report['is_balanced']): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4">
      <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
      <div><strong>Warning:</strong> Total Assets do not equal Total Liabilities + Equity. Check unposted journal entries or opening balances.</div>
    </div>
  <?php endif; ?>

  <div class="row g-5">
    <!-- Assets Side -->
    <div class="col-md-6">
      <h6 class="fw-bold text-uppercase text-primary border-bottom pb-2 mb-3">1. Assets</h6>
      <table class="table table-sm align-middle mb-4">
        <tbody>
          <?php if (empty($report['assets'])): ?>
            <tr>
              <td colspan="2" class="text-secondary small py-2 fst-italic">No asset accounts recorded.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($report['assets'] as $ast): ?>
              <tr>
                <td>
                  <span class="font-monospace text-muted small me-2"><?= h($ast['code']) ?></span>
                  <?= h($ast['account_name']) ?>
                </td>
                <td class="text-end font-monospace"><?= money((float)$ast['balance']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="table-light fw-bold border-top border-2">
            <td class="text-uppercase">Total Assets</td>
            <td class="text-end font-monospace text-primary"><?= money((float)$report['total_assets']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Liabilities & Equity Side -->
    <div class="col-md-6">
      <h6 class="fw-bold text-uppercase text-danger border-bottom pb-2 mb-3">2. Liabilities</h6>
      <table class="table table-sm align-middle mb-4">
        <tbody>
          <?php if (empty($report['liabilities'])): ?>
            <tr>
              <td colspan="2" class="text-secondary small py-2 fst-italic">No liability accounts recorded.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($report['liabilities'] as $liab): ?>
              <tr>
                <td>
                  <span class="font-monospace text-muted small me-2"><?= h($liab['code']) ?></span>
                  <?= h($liab['account_name']) ?>
                </td>
                <td class="text-end font-monospace"><?= money((float)$liab['balance']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="table-light fw-bold border-top">
            <td class="text-uppercase">Total Liabilities</td>
            <td class="text-end font-monospace text-danger"><?= money((float)$report['total_liabilities']) ?></td>
          </tr>
        </tfoot>
      </table>

      <h6 class="fw-bold text-uppercase text-success border-bottom pb-2 mb-3 mt-4">3. Equity</h6>
      <table class="table table-sm align-middle mb-4">
        <tbody>
          <?php foreach ($report['equity'] as $eq): ?>
            <tr>
              <td>
                <span class="font-monospace text-muted small me-2"><?= h($eq['code']) ?></span>
                <?= h($eq['account_name']) ?>
              </td>
              <td class="text-end font-monospace"><?= money((float)$eq['balance']) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td>
              <span class="font-monospace text-muted small me-2">3999</span>
              Current Retained Earnings / Net Income
            </td>
            <td class="text-end font-monospace"><?= money((float)$report['retained_earnings']) ?></td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="table-light fw-bold border-top">
            <td class="text-uppercase">Total Equity</td>
            <td class="text-end font-monospace text-success"><?= money((float)$report['total_equity']) ?></td>
          </tr>
          <tr class="table-secondary fw-bold border-top border-2">
            <td class="text-uppercase">Total Liabilities & Equity</td>
            <td class="text-end font-monospace"><?= money((float)$report['total_liab_equity']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="mt-4 pt-3 border-top text-center">
    <?php if ($report['is_balanced']): ?>
      <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
        <i class="bi bi-check-circle me-1"></i> Balance Sheet is perfectly in balance (Assets = Liabilities + Equity)
      </span>
    <?php else: ?>
      <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
        <i class="bi bi-x-circle me-1"></i> Out of balance by <?= money(abs((float)$report['total_assets'] - (float)$report['total_liab_equity'])) ?>
      </span>
    <?php endif; ?>
  </div>
</div>

