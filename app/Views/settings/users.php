<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Company & System Settings</h4>
    <p class="text-secondary small mb-0">Manage authorized users, assign roles, and configure system permissions.</p>
  </div>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inviteUserModal">
    <i class="bi bi-person-plus me-1"></i> Add User
  </button>
</div>

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="list-group shadow-sm border-0">
      <a href="<?= url('/app/settings') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-building me-2"></i> Company Profile
      </a>
      <a href="<?= url('/app/settings/users') ?>" class="list-group-item list-group-item-action active fw-semibold">
        <i class="bi bi-people me-2"></i> Users & Roles
      </a>
      <a href="<?= url('/app/settings/tax') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-receipt me-2"></i> Tax Rates
      </a>
      <a href="<?= url('/app/settings/fiscal-year') ?>" class="list-group-item list-group-item-action">
        <i class="bi bi-calendar3 me-2"></i> Fiscal Periods
      </a>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0">Authorized Team Members</h5>
        <span class="text-secondary small">Role-based access control enforces isolation and data integrity.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div class="fw-semibold text-dark"><?= h($u['name']) ?></div>
                  <div class="text-secondary small"><?= h($u['email']) ?></div>
                </td>
                <td>
                  <?php if ($u['role'] === 'owner'): ?>
                    <span class="badge bg-dark">Tenant Owner</span>
                  <?php elseif ($u['role'] === 'admin'): ?>
                    <span class="badge bg-primary">Admin</span>
                  <?php elseif ($u['role'] === 'accountant'): ?>
                    <span class="badge bg-info text-dark">Accountant</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Viewer</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($u['status'] === 'active'): ?>
                    <span class="badge bg-success-subtle text-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger">Suspended</span>
                  <?php endif; ?>
                </td>
                <td class="small text-secondary">
                  <?= !empty($u['last_login_at']) ? date_fmt($u['last_login_at']) : 'Never' ?>
                </td>
                <td class="text-end">
                  <?php if ($u['role'] !== 'owner'): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= url('/app/settings/users/' . $u['id'] . '/remove') ?>" onsubmit="return confirm('Permanently remove this user?');" class="d-inline">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>

                    <!-- Edit User Modal -->
                    <div class="modal fade text-start" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content border-0 shadow">
                          <form method="POST" action="<?= url('/app/settings/users/' . $u['id'] . '/update') ?>">
                            <?= csrf_field() ?>
                            <div class="modal-header border-0 pb-0">
                              <h5 class="modal-title fw-bold">Update Permissions: <?= h($u['name']) ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                              <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">System Role</label>
                                <select class="form-select" name="role" required>
                                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin (Full management)</option>
                                  <option value="accountant" <?= $u['role'] === 'accountant' ? 'selected' : '' ?>>Accountant (Journals, AR/AP, Banking, Reports)</option>
                                  <option value="viewer" <?= $u['role'] === 'viewer' ? 'selected' : '' ?>>Viewer (Read-only reports)</option>
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Account Status</label>
                                <select class="form-select" name="status" required>
                                  <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                  <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                              </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <span class="text-muted small fst-italic">Master account</span>
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

<!-- Add User Modal -->
<div class="modal fade" id="inviteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('/app/settings/users/invite') ?>">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Add Team Member</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" placeholder="e.g. Jane Doe" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="email" placeholder="jane@company.com" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Role / Access Level <span class="text-danger">*</span></label>
            <select class="form-select" name="role" required>
              <option value="accountant">Accountant (Journals, AR/AP, Banking, Reports)</option>
              <option value="admin">Administrator (Settings, Users, Full access)</option>
              <option value="viewer">Viewer (Read-only view of reports & records)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Initial Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control font-monospace" name="password" minlength="8" placeholder="At least 8 characters" required>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4">Create User Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

