<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">General Journal</h4>
    <p class="text-secondary small mb-0">Record and review manual double-entry financial journals.</p>
  </div>
  <a href="<?= url('/app/journal/create') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
    <i class="bi bi-plus-lg"></i> New Journal Entry
  </a>
</div>

<!-- FILTER CARD -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= url('/app/journal') ?>" class="row g-2 align-items-center">
      <div class="col-md-3">
        <input type="text" class="form-control form-control-sm" name="search" value="<?= h($search) ?>" placeholder="Search reference or description...">
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status">
          <option value="">All Statuses</option>
          <option value="posted" <?= $status === 'posted' ? 'selected' : '' ?>>Posted</option>
          <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="void" <?= $status === 'void' ? 'selected' : '' ?>>Void</option>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" class="form-control form-control-sm" name="start_date" value="<?= h($startDate) ?>" placeholder="Start date">
      </div>
      <div class="col-md-2">
        <input type="date" class="form-control form-control-sm" name="end_date" value="<?= h($endDate) ?>" placeholder="End date">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
        <a href="<?= url('/app/journal') ?>" class="btn btn-sm btn-light border">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- ENTRIES TABLE -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small text-secondary">
        <tr>
          <th style="width: 110px;">Date</th>
          <th style="width: 140px;">Reference</th>
          <th>Description</th>
          <th style="width: 100px;">Source</th>
          <th class="text-end" style="width: 140px;">Total Amount</th>
          <th class="text-center" style="width: 100px;">Status</th>
          <th class="text-end" style="width: 100px;">Action</th>
        </tr>
      </thead>
      <tbody class="small">
        <?php if (empty($entries)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">No journal entries found matching filters.</td></tr>
        <?php else: ?>
          <?php foreach ($entries as $je): ?>
            <tr>
              <td><?= date_fmt($je['entry_date']) ?></td>
              <td class="font-monospace fw-semibold">
                <a href="<?= url('/app/journal/' . $je['id']) ?>" class="text-decoration-none">
                  <?= h($je['reference'] ?: ('JE-' . $je['id'])) ?>
                </a>
              </td>
              <td><?= h($je['description']) ?></td>
              <td><span class="badge bg-light text-secondary border text-uppercase"><?= h($je['source']) ?></span></td>
              <td class="text-end fw-bold"><?= money((float)$je['total_debit']) ?></td>
              <td class="text-center">
                <?php if ($je['is_void']): ?>
                  <span class="badge bg-danger-subtle text-danger">Void</span>
                <?php elseif ($je['is_posted']): ?>
                  <span class="badge bg-success-subtle text-success">Posted</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary">Draft</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="<?= url('/app/journal/' . $je['id']) ?>" class="btn btn-sm btn-light border p-1" title="View Details">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
