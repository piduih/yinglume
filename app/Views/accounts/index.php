<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Chart of Accounts</h4>
    <p class="text-secondary small mb-0">Master ledger accounts categorized by GAAP standard types.</p>
  </div>
  <a href="<?= url('/app/accounts/create') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
    <i class="bi bi-plus-lg"></i> New Account
  </a>
</div>

<?php if (empty($grouped)): ?>
  <div class="card border-0 shadow-sm p-5 text-center text-muted">
    <i class="bi bi-diagram-3 fs-1 mb-2"></i>
    <h5>No accounts defined yet</h5>
    <p class="small">Add your first account to begin recording transactions.</p>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $typeName => $accounts): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-primary">
          <i class="bi bi-folder2-open me-1"></i> <?= h($typeName) ?>
        </h6>
        <span class="badge bg-light text-secondary border small"><?= count($accounts) ?> accounts</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small text-secondary">
            <tr>
              <th style="width: 120px;">Code</th>
              <th>Account Name</th>
              <th>Parent</th>
              <th class="text-end" style="width: 180px;">Current Balance</th>
              <th class="text-center" style="width: 100px;">Status</th>
              <th class="text-end" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody class="small">
            <?php foreach ($accounts as $acc): ?>
              <tr class="<?= $acc['is_active'] ? '' : 'text-muted opacity-75' ?>">
                <td class="font-monospace fw-bold text-dark"><?= h($acc['code']) ?></td>
                <td>
                  <span class="fw-semibold"><?= h($acc['name']) ?></span>
                  <?php if (!empty($acc['description'])): ?>
                    <div class="text-muted small"><?= h($acc['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-muted"><?= h($acc['parent_name'] ?? '—') ?></td>
                <td class="text-end fw-bold <?= $acc['current_balance'] < 0 ? 'text-danger' : 'text-dark' ?>">
                  <?= money((float)$acc['current_balance']) ?>
                </td>
                <td class="text-center">
                  <?php if ($acc['is_active']): ?>
                    <span class="badge bg-success-subtle text-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a href="<?= url('/app/accounts/' . $acc['id'] . '/edit') ?>" class="btn btn-sm btn-light border p-1" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <?php if (!$acc['is_system']): ?>
                    <form method="POST" action="<?= url('/app/accounts/' . $acc['id'] . '/deactivate') ?>" class="d-inline" onsubmit="return confirm('Toggle account status?');">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-light border p-1 text-danger" title="<?= $acc['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="bi bi-power"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
