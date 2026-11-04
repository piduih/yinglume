<div class="text-center mb-4">
  <h4 class="fw-bold mb-1">Set New Password</h4>
  <p class="text-secondary small">Choose a strong, unique password to secure your accounting workspace.</p>
</div>

<form method="POST" action="<?= url('/reset-password') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= h($token ?? '') ?>">

  <div class="mb-3">
    <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
    <div class="input-group">
      <span class="input-group-text bg-light"><i class="bi bi-key text-secondary"></i></span>
      <input type="password" class="form-control" id="password" name="password" minlength="8" placeholder="At least 8 characters" required autofocus>
    </div>
  </div>

  <div class="mb-4">
    <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
    <div class="input-group">
      <span class="input-group-text bg-light"><i class="bi bi-shield-check text-secondary"></i></span>
      <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" placeholder="Re-enter password" required>
    </div>
  </div>

  <div class="d-grid mb-3">
    <button type="submit" class="btn btn-primary py-2 fw-semibold">
      Update Password
    </button>
  </div>

  <div class="text-center">
    <a href="<?= url('/login') ?>" class="text-decoration-none small text-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to sign in
    </a>
  </div>
</form>

