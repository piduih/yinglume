<div class="row justify-content-center py-5">
  <div class="col-md-6 text-center">
    <div class="display-1 fw-bold text-danger mb-3">500</div>
    <h3 class="fw-bold mb-2">Internal Server Error</h3>
    <p class="text-secondary mb-4">An unexpected system error occurred while processing your financial transaction. Database integrity safeguards have prevented uncommitted changes.</p>
    <div class="d-flex justify-content-center gap-2">
      <a href="<?= url('/app') ?>" class="btn btn-primary px-4">
        <i class="bi bi-house-door me-1"></i> Return to Dashboard
      </a>
      <button onclick="location.reload()" class="btn btn-outline-secondary px-4">
        <i class="bi bi-arrow-clockwise me-1"></i> Retry
      </button>
    </div>
  </div>
</div>

