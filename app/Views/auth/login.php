<div class="card border-0 shadow-sm mx-auto" style="max-width: 440px;">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 52px; height: 52px;">
        <i class="bi bi-wallet2 fs-3"></i>
      </div>
      <h3 class="fw-bold mb-1">Welcome back</h3>
      <p class="text-secondary small">Sign in to your YingLume workspace</p>
    </div>

    <?php if (has_flash('error')): ?>
      <div class="alert alert-danger py-2 px-3 small mb-3">
        <?= h(flash_get('error')) ?>
      </div>
    <?php endif; ?>

    <?php if (has_flash('success')): ?>
      <div class="alert alert-success py-2 px-3 small mb-3">
        <?= h(flash_get('success')) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/login') ?>">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Email address</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-secondary"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control" id="email" name="email" value="<?= h($email ?? '') ?>" placeholder="name@company.com" required autofocus>
        </div>
      </div>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
          <a href="<?= url('/forgot-password') ?>" class="text-decoration-none small">Forgot?</a>
        </div>
        <div class="input-group">
          <span class="input-group-text bg-light text-secondary"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
        </div>
      </div>

      <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label small text-secondary" for="remember">Keep me logged in</label>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        Sign in to Workspace
      </button>
    </form>

    <div class="mt-4 p-3 bg-light rounded text-center small text-secondary">
      <strong>Demo Login:</strong> admin@demo.com<br>
      <strong>Password:</strong> Demo@12345
    </div>

    <div class="text-center mt-4 pt-2 border-top">
      <span class="text-secondary small">Don't have a workspace?</span>
      <a href="<?= url('/register') ?>" class="text-decoration-none small fw-semibold ms-1">Register for free</a>
    </div>
  </div>
</div>
