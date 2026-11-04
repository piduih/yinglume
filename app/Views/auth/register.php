<div class="card border-0 shadow-sm mx-auto" style="max-width: 480px;">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 52px; height: 52px;">
        <i class="bi bi-building-add fs-3"></i>
      </div>
      <h3 class="fw-bold mb-1">Create Free Workspace</h3>
      <p class="text-secondary small">Set up your company finance platform in seconds</p>
    </div>

    <?php if (has_flash('error')): ?>
      <div class="alert alert-danger py-2 px-3 small mb-3">
        <?= h(flash_get('error')) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/register') ?>">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label for="company_name" class="form-label small fw-semibold text-secondary">Company / Business Name</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-secondary"><i class="bi bi-briefcase"></i></span>
          <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Acme Global Solutions" required autofocus>
        </div>
      </div>

      <div class="mb-3">
        <label for="name" class="form-label small fw-semibold text-secondary">Your Full Name</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-secondary"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required>
        </div>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Work Email Address</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-secondary"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control" id="email" name="email" placeholder="john@company.com" required>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
          <input type="password" class="form-control" id="password" name="password" placeholder="Min. 8 chars" required>
        </div>
        <div class="col-md-6">
          <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirm Password</label>
          <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" required>
        </div>
      </div>

      <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="terms" required>
        <label class="form-check-label small text-secondary" for="terms">
          I agree to the Terms of Service and Privacy Policy
        </label>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        Create Company Workspace
      </button>
    </form>

    <div class="text-center mt-4 pt-2 border-top">
      <span class="text-secondary small">Already have an account?</span>
      <a href="<?= url('/login') ?>" class="text-decoration-none small fw-semibold ms-1">Sign In</a>
    </div>
  </div>
</div>
