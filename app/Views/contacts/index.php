<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Contacts Directory</h4>
    <p class="text-secondary small mb-0">Customers, suppliers, and third-party commercial contacts.</p>
  </div>
  <a href="<?= url('/app/contacts/create') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
    <i class="bi bi-plus-lg"></i> New Contact
  </a>
</div>

<!-- TABS & SEARCH -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <div class="row g-2 align-items-center">
      <div class="col-md-6">
        <div class="btn-group btn-group-sm w-100 w-md-auto" role="group">
          <a href="<?= url('/app/contacts?type=all') ?>" class="btn <?= $type === 'all' ? 'btn-primary' : 'btn-light border' ?>">All</a>
          <a href="<?= url('/app/contacts?type=customer') ?>" class="btn <?= $type === 'customer' ? 'btn-primary' : 'btn-light border' ?>">Customers</a>
          <a href="<?= url('/app/contacts?type=vendor') ?>" class="btn <?= $type === 'vendor' ? 'btn-primary' : 'btn-light border' ?>">Vendors</a>
        </div>
      </div>
      <div class="col-md-6">
        <form method="GET" action="<?= url('/app/contacts') ?>" class="d-flex gap-2">
          <input type="hidden" name="type" value="<?= h($type) ?>">
          <input type="text" class="form-control form-control-sm" name="search" value="<?= h($search) ?>" placeholder="Search name, email, phone...">
          <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- CONTACTS TABLE -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small text-secondary">
        <tr>
          <th>Name</th>
          <th style="width: 120px;">Type</th>
          <th>Contact Info</th>
          <th>Location</th>
          <th class="text-center" style="width: 120px;">Payment Terms</th>
          <th class="text-end" style="width: 100px;">Action</th>
        </tr>
      </thead>
      <tbody class="small">
        <?php if (empty($contacts)): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">No contacts found.</td></tr>
        <?php else: ?>
          <?php foreach ($contacts as $c): ?>
            <tr>
              <td>
                <a href="<?= url('/app/contacts/' . $c['id']) ?>" class="fw-bold text-decoration-none text-dark">
                  <?= h($c['name']) ?>
                </a>
                <?php if (!empty($c['tax_number'])): ?>
                  <div class="text-muted small">Tax ID: <?= h($c['tax_number']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $badge = match($c['type']) {
                    'customer' => 'bg-success-subtle text-success',
                    'vendor' => 'bg-danger-subtle text-danger',
                    default => 'bg-primary-subtle text-primary',
                  };
                ?>
                <span class="badge <?= $badge ?> text-uppercase"><?= h($c['type']) ?></span>
              </td>
              <td>
                <?php if (!empty($c['email'])): ?><div><i class="bi bi-envelope me-1 text-muted"></i> <?= h($c['email']) ?></div><?php endif; ?>
                <?php if (!empty($c['phone'])): ?><div><i class="bi bi-telephone me-1 text-muted"></i> <?= h($c['phone']) ?></div><?php endif; ?>
              </td>
              <td class="text-secondary"><?= h($c['city'] ?: $c['country']) ?></td>
              <td class="text-center">Net <?= (int)$c['payment_terms'] ?> days</td>
              <td class="text-end">
                <a href="<?= url('/app/contacts/' . $c['id']) ?>" class="btn btn-sm btn-light border p-1" title="View Profile">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="<?= url('/app/contacts/' . $c['id'] . '/edit') ?>" class="btn btn-sm btn-light border p-1" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
