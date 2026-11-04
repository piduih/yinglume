<?php if (has_flash('success')): ?>
  <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> <?= h(flash_get('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (has_flash('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= h(flash_get('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (has_flash('info')): ?>
  <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-info-circle-fill me-2"></i> <?= h(flash_get('info')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (has_flash('warning')): ?>
  <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-exclamation-circle-fill me-2"></i> <?= h(flash_get('warning')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

