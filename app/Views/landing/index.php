<div class="hero-section py-5 bg-white border-bottom">
  <div class="container py-5 text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-3">
      100% Free & Open Source &bull; Multi-Tenant SaaS
    </span>
    <h1 class="display-4 fw-extrabold text-dark mb-3">
      Modern Financial Accounting <br><span class="text-primary">Engineered for Small Businesses</span>
    </h1>
    <p class="lead text-secondary max-w-700 mx-auto mb-4" style="max-width: 650px;">
      Double-entry general ledger, automated sales invoicing, vendor bills, multi-account banking, and GAAP-compliant financial reporting. Run on shared hosting or cloud servers with pure PHP & MySQL.
    </p>
    <div class="d-flex justify-content-center gap-3">
      <a href="<?= url('/register') ?>" class="btn btn-primary btn-lg px-4 fw-semibold shadow-sm">
        Start Free Workspace <i class="bi bi-arrow-right ms-1"></i>
      </a>
      <a href="<?= url('/login') ?>" class="btn btn-outline-secondary btn-lg px-4 fw-semibold">
        Sign In Demo Account
      </a>
    </div>

    <div class="mt-4 text-muted small">
      Demo account: <span class="badge bg-light text-dark border">admin@demo.com</span> &bull; Password: <span class="badge bg-light text-dark border">Demo@12345</span>
    </div>
  </div>
</div>

<div class="py-5" id="features">
  <div class="container py-4">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Enterprise Accounting Architecture Made Accessible</h2>
      <p class="text-muted">Designed by experienced CTOs, CFOs, and finance directors.</p>
    </div>

    <div class="row g-4">
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4">
          <div class="icon-box mb-3 text-primary fs-2">
            <i class="bi bi-journal-bookmark"></i>
          </div>
          <h5 class="fw-bold">True Double-Entry Ledger</h5>
          <p class="text-secondary small mb-0">Every transaction updates debits and credits automatically. Balance checks prevent out-of-balance entries at database level.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4">
          <div class="icon-box mb-3 text-success fs-2">
            <i class="bi bi-receipt"></i>
          </div>
          <h5 class="fw-bold">Accounts Receivable (AR)</h5>
          <p class="text-secondary small mb-0">Create professional printable invoices, track due dates, partial payments, and calculate aged debtor buckets automatically.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4">
          <div class="icon-box mb-3 text-danger fs-2">
            <i class="bi bi-file-earmark-spreadsheet"></i>
          </div>
          <h5 class="fw-bold">Accounts Payable (AP)</h5>
          <p class="text-secondary small mb-0">Record vendor bills, manage payment terms, track cash disbursements, and avoid missed supplier deadlines.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4">
          <div class="icon-box mb-3 text-info fs-2">
            <i class="bi bi-bank2"></i>
          </div>
          <h5 class="fw-bold">Multi-Bank Treasury</h5>
          <p class="text-secondary small mb-0">Manage multiple bank accounts and petty cash funds. Internal inter-bank transfers, running balances, and reconciliation workflows.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4">
          <div class="icon-box mb-3 text-warning fs-2">
            <i class="bi bi-pie-chart"></i>
          </div>
          <h5 class="fw-bold">Financial Statements</h5>
          <p class="text-secondary small mb-0">Generate instant Profit & Loss (Income Statement), Balance Sheet, Trial Balance, and Cash Flow statements at any point in time.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm p-4">
          <div class="icon-box mb-3 text-secondary fs-2">
            <i class="bi bi-shield-check"></i>
          </div>
          <h5 class="fw-bold">Multi-Tenant Isolation</h5>
          <p class="text-secondary small mb-0">Strict row-level security scopes every record by tenant ID. Prepared statements, CSRF tokens, and bcrypt password hashing.</p>
        </div>
      </div>
    </div>
  </div>
</div>
