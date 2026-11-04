<div class="card border-0 shadow-sm mx-auto" style="max-width: 440px;">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 52px; height: 52px;">
        <i class="bi bi-key fs-3"></i>
      </div>
      <h3 class="fw-bold mb-1">Reset Password</h3>
      <p class="text-secondary small">Enter your email to receive recovery instructions</p>
    </div>

    <?php if (has_flash('error')): ?>
      <div class="alert alert-danger py-2 px-3 small mb-3">
        <?= h(flash_get('error')) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/forgot-password') ?>">
      <?= csrf_field() ?>

      <div class="mb-4">
        <label for="email" class="form-label small fw-semibold text-secondary">Registered Email</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-secondary"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required autofocus>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        Send Reset Link
      </button>
    </form>

    <div class="text-center mt-4 pt-2 border-top">
      <a href="<?= url('/login') ?>" class="text-decoration-none small fw-semibold">
        <i class="bi bi-arrow-left me-1"></i> Back to Login
      </a>
    </div>
  </div>
</div>
