# 🌟 YingLume Finance

> **Modern, Free & Open-Source Full Double-Entry Accounting and Finance SaaS for Small Businesses.**
> Built with pure PHP 8.1+ & MySQL 8.0+ — **No frameworks, no Composer dependencies, shared-hosting ready.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Architecture](https://img.shields.io/badge/Architecture-Multi--Tenant%20SaaS-0EA5E9)](#multi-tenant-architecture)
[![Shared Hosting](https://img.shields.io/badge/Deploy-Shared%20Hosting%20%2F%20cPanel-22C55E)](#deployment-on-shared-hosting--cpanel)

---

## 📖 Executive Summary

**YingLume Finance** is a lightweight, secure financial management and double-entry bookkeeping platform engineered from the perspective of an experienced CTO, CFO, and Finance Director.

Most open-source accounting platforms are either bloated enterprise systems requiring heavy Docker/Node/Redis stacks or outdated PHP scripts lacking strict GAAP/IFRS accounting controls. **YingLume Finance** delivers true double-entry general ledger integrity, multi-company tenant isolation, accounts receivable, accounts payable, bank reconciliations, inventory tracking, and full financial statements — packaged into a high-performance native PHP codebase designed to run out of the box on standard shared hosting environments or VPS.

---

## ⚡ Key Features

### 1. ⚖️ Pure Double-Entry General Ledger
* **Standard Chart of Accounts (COA):** Preconfigured with 5 standard financial classes (Assets, Liabilities, Equity, Revenue, Expenses) and flexible sub-accounts.
* **Balanced Journal Entries:** Enforces strict mathematical balance ($\sum \text{Debits} = \sum \text{Credits}$) before any entry can be posted.
* **Audit Trail & Immutability:** Posted entries cannot be silently altered; reversals must be made via transparent voiding or reversing entries.

### 2. 🧾 Accounts Receivable (Sales Invoicing)
* Professional sales invoice generation with auto-numbering.
* Custom line items, quantity, unit pricing, item autocompletion, and tax calculations.
* **Automated Ledger Posting:** Invoices automatically generate debits to Accounts Receivable (1200) and credits to Sales Revenue (4000) and Tax Payable (2100).
* **Payment Collections:** Record full or partial payments deposited to specific bank accounts, automatically clearing AR.
* **AR Aging Analysis:** Dynamic aging buckets (Current, 1–30, 31–60, 61–90, 90+ days).

### 3. 📦 Accounts Payable (Purchases & Bills)
* Record supplier bills and track due dates.
* **Automated Expense Posting:** Auto-posts debits to operating expense accounts (5000/6000) and credits to Accounts Payable (2000).
* **Disbursements:** Make partial or full vendor disbursements funded from selected cash/bank accounts.
* **AP Aging Analysis:** Monitor cash outflow commitments across aging tiers.

### 4. 🏦 Banking & Cash Flow Management
* Manage multiple business bank accounts, petty cash registers, and credit lines.
* **Inter-Account Transfers:** Move money between accounts with balanced debit/credit transfer entries.
* **Bank Reconciliation:** Match bank statement balances against general ledger activity with difference detection.

### 5. 🏷️ Items & Inventory
* Catalog products (physical goods) and services.
* Stock level tracking with automated reorder level alerts.
* Linked sales income and expense ledger accounts.

### 6. 📊 Real-Time Financial Statements & Reports
* **Profit & Loss Statement (Income Statement):** Operating revenues, operating expenses, and net profit/(loss).
* **Balance Sheet (Statement of Financial Position):** Assets, liabilities, equity, and retained earnings with out-of-balance alerts.
* **Trial Balance:** Live verification of debits and credits across all accounts.
* **Statement of Cash Flows:** Operating receipts and vendor disbursements.
* **General Ledger:** Detailed chronological activity with running balances per account.
* **Print & Export Ready:** Clean CSS media print styles for paper statements and PDF export.

### 7. 🏢 Multi-Tenant SaaS Architecture
* True row-level tenant isolation: every query is scoped by `tenant_id`.
* Self-service registration creates a company workspace, sets up default Chart of Accounts, initializes fiscal periods, bank accounts, and tax codes.

### 8. 🛡️ Role-Based Access Control (RBAC) & Enterprise Security
* **User Roles:**
  * **Owner:** Full workspace control, user administration, financial operations.
  * **Admin:** System configuration, tax setup, team management.
  * **Accountant:** Journal entries, invoicing, bills, banking, reports.
  * **Viewer:** Read-only access to statements and financial reports.
* **Security Controls:**
  * 100% PDO prepared statements (zero SQL injection).
  * Synchronized CSRF token verification on all POST/PUT/DELETE requests.
  * Secure passwords with `bcrypt` (`PASSWORD_BCRYPT`).
  * Brute-force protection: automatic 15-minute account lock after 5 failed login attempts.
  * Session fixation protection: `session_regenerate_id(true)` upon authentication.
  * Context-aware HTML sanitization via `h()` helper.

---

## 📁 System Architecture & Directory Structure

```
yinglume/
├── .env.example              # Sample environment configuration
├── .htaccess                 # Apache rewrite rules for shared hosting root
├── README.md                 # Documentation
├── LICENSE                   # MIT License
├── app/
│   ├── Controllers/          # Application controllers (MVC)
│   │   ├── AccountController.php
│   │   ├── AuthController.php
│   │   ├── BankController.php
│   │   ├── BaseController.php
│   │   ├── BillController.php
│   │   ├── ContactController.php
│   │   ├── DashboardController.php
│   │   ├── HomeController.php
│   │   ├── InvoiceController.php
│   │   ├── ItemController.php
│   │   ├── JournalController.php
│   │   ├── ReportController.php
│   │   └── SettingsController.php
│   ├── Core/                 # Zero-dependency framework kernel
│   │   ├── Auth.php          # Session auth, RBAC & lockout handler
│   │   ├── Autoloader.php    # PSR-4 autoloader
│   │   ├── CSRF.php          # Timing-safe CSRF validator
│   │   ├── Database.php      # PDO database singleton
│   │   ├── Middleware.php    # Middleware pipeline
│   │   ├── Request.php       # HTTP request abstraction
│   │   ├── Response.php      # HTTP response helper
│   │   └── Router.php        # Fast URI router with parameter dispatch
│   ├── Helpers/              # Global utility functions (money, h, date_fmt, session, etc.)
│   │   └── helpers.php
│   ├── Models/               # Data access & financial calculation models
│   │   ├── Account.php
│   │   ├── ActivityLog.php
│   │   ├── BankAccount.php
│   │   ├── BaseModel.php
│   │   ├── Bill.php
│   │   ├── Contact.php
│   │   ├── Invoice.php
│   │   ├── Item.php
│   │   ├── JournalEntry.php
│   │   ├── Report.php
│   │   ├── TaxCode.php
│   │   ├── Tenant.php
│   │   └── User.php
│   └── Views/                # Clean PHP templates with Bootstrap 5.3
│       ├── accounts/
│       ├── auth/
│       ├── banking/
│       ├── bills/
│       ├── contacts/
│       ├── dashboard/
│       ├── errors/
│       ├── invoices/
│       ├── items/
│       ├── journal/
│       ├── landing/
│       ├── layouts/
│       ├── partials/
│       ├── reports/
│       └── settings/
├── config/
│   ├── config.php            # Environment loader & constants
│   └── database.php          # Database connection parameters
├── database/
│   ├── schema.sql            # Full MySQL 8.0 schema
│   └── seeds.sql             # Demo company, users & sample ledger entries
├── public/
│   ├── .htaccess             # Front-controller rewrite
│   ├── index.php             # Application entry point
│   ├── routes.php            # Route definitions
│   └── assets/               # CSS & JavaScript
│       ├── css/ (app.css, print.css)
│       └── js/ (app.js, invoice.js, journal.js)
└── storage/
    ├── logs/                 # Error logs
    └── uploads/              # Document attachments
```

---

## 🚀 Quick Start & Installation

### Requirements
* **PHP:** 8.1 or higher (with `pdo_mysql`, `mbstring`, `iconv`, `json` extensions enabled)
* **MySQL / MariaDB:** MySQL 8.0+ or MariaDB 10.5+
* **Web Server:** Apache with `mod_rewrite` enabled, Nginx, or PHP built-in CLI server.

---

### Step 1: Clone or Download
```bash
git clone https://github.com/piduih/yinglume.git
cd yinglume
```

### Step 2: Configure Environment
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
Edit `.env` with your database credentials:
```ini
APP_NAME="YingLume Finance"
APP_ENV="production"
APP_DEBUG=false
APP_URL="http://localhost:8080"

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="yinglume_finance"
DB_USER="root"
DB_PASS="your_password"

DEFAULT_CURRENCY="MYR"
DEFAULT_TIMEZONE="Asia/Kuala_Lumpur"
```

### Step 3: Initialize Database
Create your MySQL database and import the schema and seed data:
```bash
# Log in to MySQL
mysql -u root -p -e "CREATE DATABASE yinglume_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import Schema
mysql -u root -p yinglume_finance < database/schema.sql

# Import Seeds (Demo data)
mysql -u root -p yinglume_finance < database/seeds.sql
```

### Step 4: Run Application
#### Option A: PHP Built-in Server (Development)
```bash
php -S 127.0.0.1:8080 -t public
```
Visit `http://127.0.0.1:8080` in your web browser.

#### Option B: Deployment on Shared Hosting / cPanel
1. Upload the entire `yinglume` folder to your shared hosting account (e.g. into `public_html` or an adjacent folder).
2. If uploading directly to `public_html`, the root `.htaccess` will automatically route requests into the `/public` directory securely while protecting `app/`, `config/`, and `storage/`.
3. In cPanel **MySQL Databases**, create a new database and user.
4. In phpMyAdmin, import `database/schema.sql`, followed by `database/seeds.sql`.
5. Create `.env` in the root folder with your database credentials.
6. Ensure `storage/logs/` and `storage/uploads/` are writable (`chmod 775`).

---

## 🔑 Demo Login Credentials

The seed data comes with a preconfigured company workspace:

| Attribute | Value |
| :--- | :--- |
| **Email** | `admin@demo.com` |
| **Password** | `Demo@12345` |
| **Role** | Tenant Owner |
| **Company** | Demo Business Corp |

You can also click **Register** on the landing page to create your own isolated tenant company workspace with automated COA setup.

---

## 🔒 Security Best Practices

1. **Protect `.env`:** Never place `.env` inside a publicly accessible web root without server protection. The provided `.htaccess` denies access to `.env` files.
2. **HTTPS in Production:** In production, ensure SSL/TLS is active so session cookies are transmitted with the `Secure` flag.
3. **Database Permissions:** Create a dedicated MySQL user granting only `SELECT, INSERT, UPDATE, DELETE` permissions on `yinglume_finance`.

---

## 🤝 Contributing

Contributions are welcome!
1. Fork the Project.
2. Create your Feature Branch (`git checkout -b feature/NewFinancialFeature`).
3. Commit your Changes (`git commit -m 'Add new balance check metric'`).
4. Push to the Branch (`git push origin feature/NewFinancialFeature`).
5. Open a Pull Request.

---

## 📄 License

Distributed under the **MIT License**. See `LICENSE` for more information.

---

*Designed and engineered with financial rigor for small business owners and finance teams worldwide.*

