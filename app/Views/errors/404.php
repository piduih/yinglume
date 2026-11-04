<div class="row justify-content-center py-5">
  <div class="col-md-6 text-center">
    <div class="display-1 fw-bold text-primary mb-3">404</div>
    <h3 class="fw-bold mb-2">Page Not Found</h3>
    <p class="text-secondary mb-4">The accounting page or record you requested could not be located or may have been archived.</p>
    <div class="d-flex justify-content-center gap-2">
      <a href="<?= url('/app') ?>" class="btn btn-primary px-4">
        <i class="bi bi-house-door me-1"></i> Return to Dashboard
      </a>
      <button onclick="history.back()" class="btn btn-outline-secondary px-4">
        <i class="bi bi-arrow-left me-1"></i> Go Back
      </button>
    </div>
  </div>
</div>

