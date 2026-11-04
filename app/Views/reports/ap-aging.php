<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Accounts Payable Aging</h4>
    <p class="text-secondary small mb-0">Overview of unpaid vendor purchase bills categorized by age.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i> Print / Export PDF
    </button>
  </div>
</div>

<!-- As of Date Filter -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= url('/app/reports/ap-aging') ?>" class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label small fw-semibold text-secondary">Aging As Of Date</label>
        <input type="date" class="form-control" name="as_of" value="<?= h($asOf) ?>" required>
      </div>
      <div class="col-md-6">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-funnel me-1"></i> Update Aging
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th>Vendor / Supplier</th>
          <th class="text-end">Current (Not Due)</th>
          <th class="text-end">1 - 30 Days</th>
          <th class="text-end">31 - 60 Days</th>
          <th class="text-end">61 - 90 Days</th>
          <th class="text-end">90+ Days</th>
          <th class="text-end">Total Due</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vendors)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-secondary">
              <i class="bi bi-check2-circle display-6 text-success d-block mb-2"></i>
              No outstanding supplier bills payable as of this date.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($vendors as $vName => $bucket): ?>
            <tr>
              <td class="fw-semibold text-dark"><?= h($vName) ?></td>
              <td class="text-end font-monospace"><?= (float)$bucket['current'] > 0 ? money((float)$bucket['current']) : '—' ?></td>
              <td class="text-end font-monospace text-warning"><?= (float)$bucket['b1_30'] > 0 ? money((float)$bucket['b1_30']) : '—' ?></td>
              <td class="text-end font-monospace text-warning-emphasis"><?= (float)$bucket['b31_60'] > 0 ? money((float)$bucket['b31_60']) : '—' ?></td>
              <td class="text-end font-monospace text-danger"><?= (float)$bucket['b61_90'] > 0 ? money((float)$bucket['b61_90']) : '—' ?></td>
              <td class="text-end font-monospace fw-bold text-danger"><?= (float)$bucket['b90plus'] > 0 ? money((float)$bucket['b90plus']) : '—' ?></td>
              <td class="text-end font-monospace fw-bold text-dark"><?= money((float)$bucket['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light fw-bold border-top border-2">
          <td>Grand Total AP</td>
          <td class="text-end font-monospace"><?= money((float)$grandTotals['current']) ?></td>
          <td class="text-end font-monospace text-warning"><?= money((float)$grandTotals['b1_30']) ?></td>
          <td class="text-end font-monospace text-warning-emphasis"><?= money((float)$grandTotals['b31_60']) ?></td>
          <td class="text-end font-monospace text-danger"><?= money((float)$grandTotals['b61_90']) ?></td>
          <td class="text-end font-monospace text-danger"><?= money((float)$grandTotals['b90plus']) ?></td>
          <td class="text-end font-monospace text-danger fs-6"><?= money((float)$grandTotals['total']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

