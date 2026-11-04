<?php
/**
 * Layout: auth.php
 * Centered card layout for login/register/reset-password pages
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

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons 1.11 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- Inter font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
</head>
<body>

  <div class="auth-wrapper">
    <div class="w-100">
      <?= $content ?>

      <!-- Footer link -->
      <p class="text-center mt-4 text-secondary" style="font-size:0.75rem">
        &copy; <?= date('Y') ?> YingLume Finance &mdash; Free for small business &mdash; MIT License
      </p>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
