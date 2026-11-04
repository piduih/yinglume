<?php
/**
 * Layout: landing.php
 * Marketing landing page layout for YingLume Finance
 *
 * @var string $pageTitle
 * @var string $content
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>YingLume Finance</title>
  <meta name="description" content="YingLume Finance — Free double-entry accounting system for small businesses. Invoicing, bills, banking, reports, multi-user.">

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons 1.11 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- Inter font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">

  <style>
    .landing-nav .navbar-brand { font-weight: 800; letter-spacing: -0.02em; }
    .section-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary); margin-bottom: 0.5rem; }
    .section-heading { font-size: clamp(1.6rem, 3vw, 2.5rem); font-weight: 800; color: #0F172A; line-height: 1.1; }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark landing-nav sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('/') ?>">
        <i class="bi bi-graph-up-arrow text-primary fs-5"></i>
        YingLume Finance
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="landingNav">
        <ul class="navbar-nav ms-auto me-3 gap-1">
          <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
          <li class="nav-item"><a class="nav-link" href="#testimonials">Reviews</a></li>
        </ul>
        <div class="d-flex gap-2">
          <a href="<?= url('/login') ?>" class="btn btn-outline-light btn-sm">Sign In</a>
          <a href="<?= url('/register') ?>" class="btn btn-primary btn-sm">Get Started Free</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- PAGE CONTENT -->
  <?= $content ?>

  <!-- LANDING FOOTER -->
  <footer class="bg-dark text-light py-5 mt-0">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-graph-up-arrow text-primary fs-5"></i>
            <span class="fw-bold fs-5">YingLume Finance</span>
          </div>
          <p class="text-secondary small">Free, open-source double-entry accounting for small businesses. No subscriptions. No limits. MIT License.</p>
          <div class="d-flex gap-3">
            <a href="https://github.com" class="text-secondary fs-5" target="_blank"><i class="bi bi-github"></i></a>
            <a href="#" class="text-secondary fs-5"><i class="bi bi-twitter-x"></i></a>
          </div>
        </div>
        <div class="col-6 col-lg-2 offset-lg-2">
          <h6 class="fw-bold text-white mb-3">Product</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="#features" class="text-secondary text-decoration-none">Features</a></li>
            <li class="mb-2"><a href="#how-it-works" class="text-secondary text-decoration-none">How It Works</a></li>
            <li class="mb-2"><a href="<?= url('/register') ?>" class="text-secondary text-decoration-none">Get Started</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-white mb-3">Legal</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="#" class="text-secondary text-decoration-none">MIT License</a></li>
            <li class="mb-2"><a href="#" class="text-secondary text-decoration-none">Privacy Policy</a></li>
            <li class="mb-2"><a href="#" class="text-secondary text-decoration-none">Terms of Use</a></li>
          </ul>
        </div>
        <div class="col-lg-2">
          <h6 class="fw-bold text-white mb-3">Support</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="#" class="text-secondary text-decoration-none">Documentation</a></li>
            <li class="mb-2"><a href="https://github.com" class="text-secondary text-decoration-none">GitHub Issues</a></li>
          </ul>
        </div>
      </div>
      <hr class="border-secondary mt-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <p class="text-secondary small mb-0">&copy; <?= date('Y') ?> YingLume Finance. Free for small business | MIT License</p>
        <a href="https://github.com" class="btn btn-outline-secondary btn-sm" target="_blank">
          <i class="bi bi-github me-1"></i> View on GitHub
        </a>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
