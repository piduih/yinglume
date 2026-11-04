<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Company & System Settings</h4>
    <p class="text-secondary small mb-0">Manage accounting periods, annual closing dates, and fiscal year intervals.</p>
  </div>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fyModal">
    <i class="bi bi-calendar-plus me-1"></i> New Fiscal Year
  </button>
</div>

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="list-group shadow-sm border-0">
      <a href="<?= url('/app/settings') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-building me-2"></i> Company Profile
      </a>
      <a href="<?= url('/app/settings/users') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-people me-2"></i> Users & Roles
      </a>
      <a href="<?= url('/app/settings/tax') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-receipt me-2"></i> Tax Rates
      </a>
      <a href="<?= url('/app/settings/fiscal-year') ?>" class="list-group-item list-group-item-action active fw-semibold">
        <i class="bi bi-calendar3 me-2"></i> Fiscal Periods
      </a>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0">Fiscal Years</h5>
        <span class="text-secondary small">Financial entries are linked to active fiscal periods.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Period Name</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($years as $fy): ?>
              <tr>
                <td class="fw-bold text-dark"><?= h($fy['name']) ?></td>
                <td><?= date_fmt($fy['start_date']) ?></td>
                <td><?= date_fmt($fy['end_date']) ?></td>
                <td class="text-center">
                  <?php if (!$fy['is_closed']): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Open (Active)</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Closed</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Create FY -->
<div class="modal fade" id="fyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('/app/settings/fiscal-year') ?>">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Create Fiscal Year</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Fiscal Year Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" placeholder="e.g. FY <?= date('Y') ?>" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary">Start Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="start_date" value="<?= date('Y-01-01') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary">End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="end_date" value="<?= date('Y-12-31') ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4">Create Period</button>
        </div>
      </form>
    </div>
  </div>
</div>

