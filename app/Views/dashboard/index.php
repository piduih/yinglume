<!-- KPI CARDS -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-secondary small fw-semibold text-uppercase">Revenue (MTD)</span>
          <span class="badge bg-success-subtle text-success p-2 rounded"><i class="bi bi-graph-up fs-6"></i></span>
        </div>
        <h3 class="fw-bold mb-1 text-dark"><?= money($revenueMtd) ?></h3>
        <span class="text-muted small">Month-to-date billed</span>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-secondary small fw-semibold text-uppercase">Expenses (MTD)</span>
          <span class="badge bg-danger-subtle text-danger p-2 rounded"><i class="bi bi-cash-stack fs-6"></i></span>
        </div>
        <h3 class="fw-bold mb-1 text-dark"><?= money($expenseMtd) ?></h3>
        <span class="text-muted small">Operating expenses</span>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-secondary small fw-semibold text-uppercase">Receivables (AR)</span>
          <span class="badge bg-warning-subtle text-warning p-2 rounded"><i class="bi bi-clock-history fs-6"></i></span>
        </div>
        <h3 class="fw-bold mb-1 text-dark"><?= money($totalAr) ?></h3>
        <span class="text-muted small">Outstanding from clients</span>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-secondary small fw-semibold text-uppercase">Cash & Bank</span>
          <span class="badge bg-primary-subtle text-primary p-2 rounded"><i class="bi bi-bank fs-6"></i></span>
        </div>
        <h3 class="fw-bold mb-1 text-dark"><?= money($totalCash) ?></h3>
        <span class="text-muted small">Total liquid reserves</span>
      </div>
    </div>
  </div>
</div>

<!-- CASH FLOW CHART -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
    <div>
      <h6 class="fw-bold mb-0">Financial Performance</h6>
      <span class="text-muted small">6-Month Trend: Revenue vs Expenses</span>
    </div>
    <div class="badge bg-light text-secondary border">Real-time GL sync</div>
  </div>
  <div class="card-body p-3">
    <div style="height: 280px; position: relative;">
      <canvas id="financialChart"></canvas>
    </div>
  </div>
</div>

<!-- RECENT INVOICES & RECENT BILLS -->
<div class="row g-4">
  <!-- Recent Invoices -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Recent Sales Invoices</h6>
        <a href="<?= url('/app/invoices') ?>" class="text-decoration-none small">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small text-secondary">
            <tr>
              <th># Invoice</th>
              <th>Customer</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody class="small">
            <?php if (empty($recentInvoices)): ?>
              <tr><td colspan="4" class="text-center py-4 text-muted">No invoices found.</td></tr>
            <?php else: ?>
              <?php foreach ($recentInvoices as $inv): ?>
                <tr>
                  <td><a href="<?= url('/app/invoices/' . $inv['id']) ?>" class="fw-semibold text-decoration-none"><?= h($inv['invoice_number']) ?></a></td>
                  <td><?= h($inv['customer_name'] ?? 'N/A') ?></td>
                  <td class="text-end fw-semibold"><?= money((float)$inv['total']) ?></td>
                  <td class="text-center">
                    <?php
                      $badgeClass = match($inv['status']) {
                        'paid' => 'bg-success-subtle text-success',
                        'sent' => 'bg-primary-subtle text-primary',
                        'partial' => 'bg-warning-subtle text-warning',
                        'void' => 'bg-secondary-subtle text-secondary',
                        default => 'bg-light text-dark border',
                      };
                    ?>
                    <span class="badge <?= $badgeClass ?> text-uppercase"><?= h($inv['status']) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Bills -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Recent Vendor Bills</h6>
        <a href="<?= url('/app/bills') ?>" class="text-decoration-none small">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small text-secondary">
            <tr>
              <th># Bill</th>
              <th>Vendor</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody class="small">
            <?php if (empty($recentBills)): ?>
              <tr><td colspan="4" class="text-center py-4 text-muted">No bills found.</td></tr>
            <?php else: ?>
              <?php foreach ($recentBills as $bill): ?>
                <tr>
                  <td><a href="<?= url('/app/bills/' . $bill['id']) ?>" class="fw-semibold text-decoration-none"><?= h($bill['bill_number']) ?></a></td>
                  <td><?= h($bill['vendor_name'] ?? 'N/A') ?></td>
                  <td class="text-end fw-semibold"><?= money((float)$bill['total']) ?></td>
                  <td class="text-center">
                    <?php
                      $bClass = match($bill['status']) {
                        'paid' => 'bg-success-subtle text-success',
                        'received' => 'bg-danger-subtle text-danger',
                        'partial' => 'bg-warning-subtle text-warning',
                        default => 'bg-light text-dark border',
                      };
                    ?>
                    <span class="badge <?= $bClass ?> text-uppercase"><?= h($bill['status']) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('financialChart');
  if (!ctx) return;

  const chartData = <?= json_encode($chartData) ?>;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: 'Revenue',
          data: chartData.revenues,
          backgroundColor: '#2563EB',
          borderRadius: 4,
        },
        {
          label: 'Expenses',
          data: chartData.expenses,
          backgroundColor: '#EF4444',
          borderRadius: 4,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(val) {
              return '$' + val.toLocaleString();
            }
          }
        }
      }
    }
  });
});
</script>
