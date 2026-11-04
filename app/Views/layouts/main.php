<?php
/**
 * Layout: main.php
 * Main authenticated application shell with sidebar, topbar, and flash messages.
 *
 * @var string $pageTitle
 * @var string $content
 */

use App\Core\Auth;

$user = Auth::user();
$tenantId = Auth::tenantId();
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>YingLume Finance</title>
  <meta name="csrf-token" content="<?= csrf_token() ?>">

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons 1.11 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- App Stylesheet -->
  <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
  <!-- Print Stylesheet -->
  <link rel="stylesheet" href="<?= url('/assets/css/print.css') ?>" media="print">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    :root {
      --sidebar-width: 250px;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #F8FAFC;
      color: #1E293B;
      min-height: 100vh;
    }
    .app-sidebar {
      width: var(--sidebar-width);
      min-height: 100vh;
      background-color: #0F172A;
      color: #94A3B8;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
      display: flex;
      flex-direction: column;
    }
    .app-sidebar .sidebar-brand {
      padding: 1.25rem 1.5rem;
      font-size: 1.1rem;
      font-weight: 800;
      color: #FFFFFF;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .sidebar-menu {
      padding: 1rem 0;
      flex-grow: 1;
      overflow-y: auto;
    }
    .sidebar-heading {
      padding: 0.5rem 1.5rem;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748B;
    }
    .sidebar-nav-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.55rem 1.5rem;
      color: #CBD5E1;
      text-decoration: none;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.15s ease-in-out;
    }
    .sidebar-nav-link:hover {
      background-color: rgba(255, 255, 255, 0.05);
      color: #FFFFFF;
    }
    .sidebar-nav-link.active {
      background-color: #2563EB;
      color: #FFFFFF;
    }
    .sidebar-nav-link i {
      font-size: 1.1rem;
    }
    .app-wrapper {
      margin-left: var(--sidebar-width);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .app-topbar {
      height: 64px;
      background-color: #FFFFFF;
      border-bottom: 1px solid #E2E8F0;
      padding: 0 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 900;
    }
    .app-main-content {
      padding: 1.5rem;
      flex-grow: 1;
    }
    .app-footer {
      padding: 1rem 1.5rem;
      border-top: 1px solid #E2E8F0;
      background-color: #FFFFFF;
      font-size: 0.8rem;
      color: #64748B;
    }
    @media (max-width: 991.98px) {
      .app-sidebar {
        transform: translateX(-100%);
        transition: transform 0.25s ease-in-out;
      }
      .app-sidebar.show {
        transform: translateX(0);
      }
      .app-wrapper {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="app-sidebar" id="appSidebar">
    <a href="<?= url('/app/dashboard') ?>" class="sidebar-brand">
      <i class="bi bi-wallet2 text-primary fs-4"></i>
      <span>YingLume</span>
    </a>

    <div class="sidebar-menu">
      <div class="sidebar-heading">Core</div>
      <a href="<?= url('/app/dashboard') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/dashboard') ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>

      <div class="sidebar-heading mt-3">General Ledger</div>
      <a href="<?= url('/app/accounts') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/accounts') ? 'active' : '' ?>">
        <i class="bi bi-diagram-3"></i> Chart of Accounts
      </a>
      <a href="<?= url('/app/journal') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/journal') ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i> Journal Entries
      </a>

      <div class="sidebar-heading mt-3">Sales & Purchases</div>
      <a href="<?= url('/app/contacts') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/contacts') ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Contacts
      </a>
      <a href="<?= url('/app/invoices') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/invoices') ? 'active' : '' ?>">
        <i class="bi bi-arrow-up-right-circle text-success"></i> Sales Invoices
      </a>
      <a href="<?= url('/app/bills') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/bills') ? 'active' : '' ?>">
        <i class="bi bi-arrow-down-left-circle text-danger"></i> Vendor Bills
      </a>

      <div class="sidebar-heading mt-3">Treasury & Stock</div>
      <a href="<?= url('/app/banking') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/banking') ? 'active' : '' ?>">
        <i class="bi bi-bank"></i> Banking & Cash
      </a>
      <a href="<?= url('/app/items') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/items') ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Items & Inventory
      </a>

      <div class="sidebar-heading mt-3">Intelligence</div>
      <a href="#reportsSubmenu" data-bs-toggle="collapse" class="sidebar-nav-link <?= str_contains($currentUri, '/reports') ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-line"></i> Reports <i class="bi bi-chevron-down ms-auto small"></i>
      </a>
      <div class="collapse <?= str_contains($currentUri, '/reports') ? 'show' : '' ?> ps-3" id="reportsSubmenu">
        <a href="<?= url('/app/reports/profit-loss') ?>" class="sidebar-nav-link py-1 small">Profit &amp; Loss</a>
        <a href="<?= url('/app/reports/balance-sheet') ?>" class="sidebar-nav-link py-1 small">Balance Sheet</a>
        <a href="<?= url('/app/reports/trial-balance') ?>" class="sidebar-nav-link py-1 small">Trial Balance</a>
        <a href="<?= url('/app/reports/cash-flow') ?>" class="sidebar-nav-link py-1 small">Cash Flow</a>
        <a href="<?= url('/app/reports/ar-aging') ?>" class="sidebar-nav-link py-1 small">AR Aging</a>
        <a href="<?= url('/app/reports/ap-aging') ?>" class="sidebar-nav-link py-1 small">AP Aging</a>
        <a href="<?= url('/app/reports/ledger') ?>" class="sidebar-nav-link py-1 small">General Ledger</a>
      </div>

      <div class="sidebar-heading mt-3">Administration</div>
      <a href="<?= url('/app/settings') ?>" class="sidebar-nav-link <?= str_contains($currentUri, '/settings') ? 'active' : '' ?>">
        <i class="bi bi-gear"></i> Settings
      </a>
    </div>

    <!-- User footnote -->
    <div class="p-3 border-top border-secondary border-opacity-25 small">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-white fw-semibold"><?= h($user['name'] ?? 'User') ?></div>
          <span class="badge bg-primary text-uppercase"><?= h($user['role'] ?? 'viewer') ?></span>
        </div>
        <a href="<?= url('/logout') ?>" class="text-secondary text-hover-white fs-5" title="Sign Out">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </aside>

  <!-- MAIN WRAPPER -->
  <div class="app-wrapper">

    <!-- TOPBAR -->
    <header class="app-topbar d-print-none">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" onclick="document.getElementById('appSidebar').classList.toggle('show')">
          <i class="bi bi-list fs-5"></i>
        </button>
        <h1 class="h5 mb-0 fw-bold text-dark"><?= h($pageTitle ?? 'Dashboard') ?></h1>
      </div>

      <div class="d-flex align-items-center gap-3">
        <a href="<?= url('/app/invoices/create') ?>" class="btn btn-sm btn-primary d-none d-sm-inline-flex align-items-center gap-1">
          <i class="bi bi-plus-circle"></i> New Invoice
        </a>
        <a href="<?= url('/app/bills/create') ?>" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
          <i class="bi bi-plus-circle"></i> New Bill
        </a>
        <div class="vr mx-1"></div>
        <div class="dropdown">
          <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i>
            <span><?= h($user['name'] ?? 'Account') ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="<?= url('/app/settings') ?>"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li><a class="dropdown-item" href="<?= url('/app/settings/users') ?>"><i class="bi bi-people me-2"></i> Team</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= url('/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- CONTENT -->
    <main class="app-main-content">
      <!-- Flash notifications -->
      <?php if (has_flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> <?= h(flash_get('success')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (has_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= h(flash_get('error')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (has_flash('info')): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
          <i class="bi bi-info-circle-fill me-2"></i> <?= h(flash_get('info')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (has_flash('warning')): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
          <i class="bi bi-exclamation-circle-fill me-2"></i> <?= h(flash_get('warning')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?= $content ?>
    </main>

    <!-- FOOTER -->
    <footer class="app-footer d-print-none text-center">
      YingLume Finance &bull; Free &amp; Open Source Multi-Tenant Finance Software &bull; MIT License &bull; <a href="https://github.com" target="_blank" class="text-decoration-none">GitHub</a>
    </footer>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- App JS -->
  <script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
