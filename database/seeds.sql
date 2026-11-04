-- ============================================================
-- YingLume Finance System — Demo Seeds
-- ============================================================
-- Tenant: Demo Company Sdn Bhd
-- Login:  admin@demo.com / Demo@12345
-- ============================================================

USE `yinglume_finance`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Demo Tenant
INSERT INTO `tenants` (`id`, `uuid`, `name`, `slug`, `plan`, `status`, `trial_ends_at`, `settings`, `created_at`, `updated_at`)
VALUES (
    1,
    'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d',
    'Demo Company Sdn Bhd',
    'demo',
    'enterprise',
    'active',
    '2030-12-31 23:59:59',
    '{"currency":"MYR","timezone":"Asia/Kuala_Lumpur","date_format":"d/m/Y","fiscal_year_start":1}',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- 2. Owner User (Password: Demo@12345)
INSERT INTO `users` (`id`, `uuid`, `tenant_id`, `name`, `email`, `password`, `role`, `status`, `login_attempts`, `locked_until`, `last_login_at`, `email_verified_at`, `created_at`, `updated_at`)
VALUES (
    1,
    'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e',
    1,
    'Finance Director Admin',
    'admin@demo.com',
    '$2y$10$xzisQjUujKZXQMVqw6/OIu0yOxbb9EzWLC4vccOGzhw8.t.3Ssjuy',
    'owner',
    'active',
    0,
    NULL,
    NOW(),
    NOW(),
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- 3. Fiscal Year 2024
INSERT INTO `fiscal_years` (`id`, `tenant_id`, `name`, `start_date`, `end_date`, `is_closed`, `created_at`)
VALUES (
    1,
    1,
    'FY2024',
    '2024-01-01',
    '2024-12-31',
    0,
    NOW()
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- 4. Chart of Accounts
-- Types: 1=Assets, 2=Liabilities, 3=Equity, 4=Revenue, 5=Expenses
INSERT INTO `chart_of_accounts` (`id`, `tenant_id`, `fiscal_year_id`, `parent_id`, `code`, `name`, `account_type_id`, `description`, `is_system`, `is_active`, `opening_balance`, `created_at`, `updated_at`)
VALUES
-- Assets (1000 - 1999)
(1,  1, 1, NULL, '1000', 'Cash on Hand',             1, 'Physical cash and petty cash', 1, 1, 5000.00,  NOW(), NOW()),
(2,  1, 1, NULL, '1010', 'Maybank Operating Account', 1, 'Main bank current account',    1, 1, 45000.00, NOW(), NOW()),
(3,  1, 1, NULL, '1020', 'CIMB Savings Account',      1, 'Reserve bank account',         1, 1, 20000.00, NOW(), NOW()),
(4,  1, 1, NULL, '1200', 'Accounts Receivable',       1, 'Trade receivables from sales', 1, 1, 0.00,     NOW(), NOW()),
(5,  1, 1, NULL, '1400', 'Inventory Asset',           1, 'Inventory of goods for resale',1, 1, 15000.00, NOW(), NOW()),
(6,  1, 1, NULL, '1500', 'Office Equipment',          1, 'Computers and furniture',      0, 1, 12000.00, NOW(), NOW()),

-- Liabilities (2000 - 2999)
(7,  1, 1, NULL, '2000', 'Accounts Payable',          2, 'Trade payables to suppliers',  1, 1, 0.00,     NOW(), NOW()),
(8,  1, 1, NULL, '2100', 'SST / Sales Tax Payable',   2, 'Tax collected on sales',       1, 1, 0.00,     NOW(), NOW()),
(9,  1, 1, NULL, '2200', 'Salaries Payable',          2, 'Accrued wages and salaries',   0, 1, 0.00,     NOW(), NOW()),

-- Equity (3000 - 3999)
(10, 1, 1, NULL, '3000', 'Owner Equity / Capital',    3, 'Initial paid up capital',      1, 1, 75000.00, NOW(), NOW()),
(11, 1, 1, NULL, '3100', 'Retained Earnings',         3, 'Accumulated earnings',         1, 1, 22000.00, NOW(), NOW()),

-- Revenue (4000 - 4999)
(12, 1, 1, NULL, '4000', 'Sales Revenue',             4, 'Income from product sales',    1, 1, 0.00,     NOW(), NOW()),
(13, 1, 1, NULL, '4100', 'Service & Consulting Revenue', 4, 'Income from IT services',   0, 1, 0.00,     NOW(), NOW()),
(14, 1, 1, NULL, '4900', 'Other Income',              4, 'Miscellaneous revenue',        0, 1, 0.00,     NOW(), NOW()),

-- Expenses (5000 - 6999)
(15, 1, 1, NULL, '5000', 'Cost of Goods Sold (COGS)', 5, 'Direct product costs',        1, 1, 0.00,     NOW(), NOW()),
(16, 1, 1, NULL, '6000', 'Salaries & Wages',          5, 'Staff payroll and allowances', 0, 1, 0.00,     NOW(), NOW()),
(17, 1, 1, NULL, '6100', 'Office Rent Expense',       5, 'Premises rental',              0, 1, 0.00,     NOW(), NOW()),
(18, 1, 1, NULL, '6200', 'Utilities & Internet',      5, 'Electricity, water, broadband',0, 1, 0.00,     NOW(), NOW()),
(19, 1, 1, NULL, '6300', 'Software Subscriptions',    5, 'Cloud SaaS and tools',         0, 1, 0.00,     NOW(), NOW()),
(20, 1, 1, NULL, '6400', 'Marketing & Advertising',   5, 'Online ads and promotion',     0, 1, 0.00,     NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 5. Tax Codes
INSERT INTO `tax_codes` (`id`, `tenant_id`, `code`, `name`, `rate`, `type`, `account_id`, `is_active`, `created_at`, `updated_at`)
VALUES
(1, 1, 'SST-6',  'Sales & Service Tax 6%',  0.0600, 'both',     8, 1, NOW(), NOW()),
(2, 1, 'SST-10', 'Sales Tax 10%',           0.1000, 'sales',    8, 1, NOW(), NOW()),
(3, 1, 'EXEMPT', 'Tax Exempt (0%)',         0.0000, 'both',  NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 6. Bank Accounts
INSERT INTO `bank_accounts` (`id`, `uuid`, `tenant_id`, `account_id`, `name`, `account_number`, `bank_name`, `bank_branch`, `swift_code`, `currency`, `opening_balance`, `opening_date`, `is_default`, `is_active`, `created_at`, `updated_at`)
VALUES
(1, 'c3d4e5f6-a7b8-9c0d-1e2f-3a4b5c6d7e8f', 1, 2, 'Maybank Main Current', '5140-1234-5678', 'Malayan Banking Berhad', 'KL Main', 'MBBEMYKL', 'MYR', 45000.00, '2024-01-01', 1, 1, NOW(), NOW()),
(2, 'd4e5f6a7-b89c-0d1e-2f3a-4b5c6d7e8f9a', 1, 3, 'CIMB Business Reserve', '8001-9876-5432', 'CIMB Bank Berhad', 'Petaling Jaya', 'CIBBMYKL', 'MYR', 20000.00, '2024-01-01', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 7. Contacts (Customers & Vendors)
INSERT INTO `contacts` (`id`, `uuid`, `tenant_id`, `type`, `name`, `email`, `phone`, `address`, `city`, `state`, `country`, `postal_code`, `tax_number`, `credit_limit`, `payment_terms`, `notes`, `is_active`, `created_at`, `updated_at`)
VALUES
-- Customers
(1, 'e5f6a7b8-9c0d-1e2f-3a4b-5c6d7e8f9a0b', 1, 'customer', 'Acme Corporation Sdn Bhd', 'finance@acme.com.my', '+603-2111-2222', 'Level 15, Menara Acme, Jalan Ampang', 'Kuala Lumpur', 'Wilayah Persekutuan', 'Malaysia', '50450', 'C1234567890', 50000.00, 30, 'Key enterprise client', 1, NOW(), NOW()),
(2, 'f6a7b89c-0d1e-2f3a-4b5c-6d7e8f9a0b1c', 1, 'customer', 'Global Tech Solutions', 'billing@globaltech.io', '+603-7890-1234', 'Block B, Cyberjaya Tech Park', 'Cyberjaya', 'Selangor', 'Malaysia', '63000', 'C9876543210', 30000.00, 15, 'SaaS subscription client', 1, NOW(), NOW()),
(3, 'a7b89c0d-1e2f-3a4b-5c6d-7e8f9a0b1c2d', 1, 'customer', 'Nexus Retail Holdings', 'accounts@nexusretail.com', '+603-8888-9999', '12, Jalan Sultan Ismail', 'Kuala Lumpur', 'Wilayah Persekutuan', 'Malaysia', '50250', 'C5555566666', 20000.00, 30, 'Retail chains client', 1, NOW(), NOW()),

-- Vendors
(4, 'b89c0d1e-2f3a-4b5c-6d7e-8f9a0b1c2d3e', 1, 'vendor', 'Office Depot Supplies Sdn Bhd', 'sales@officedepot.my', '+603-5555-1111', '88 Jalan Industri USJ 1', 'Subang Jaya', 'Selangor', 'Malaysia', '47600', 'V1111222233', 10000.00, 30, 'Office stationery and hardware', 1, NOW(), NOW()),
(5, 'c90d1e2f-3a4b-5c6d-7e8f-9a0b1c2d3e4f', 1, 'vendor', 'Cloud Hosting Infrastructure Inc', 'billing@cloudhost.com', '+1-800-555-0199', '100 Silicon Way', 'San Jose', 'CA', 'United States', '95110', 'US999888777', 5000.00, 15, 'AWS & hosting provider', 1, NOW(), NOW()),
(6, 'd01e2f3a-4b5c-6d7e-8f9a-0b1c2d3e4f5a', 1, 'vendor', 'LogiTrans Delivery Services', 'support@logitrans.my', '+603-3344-5566', 'Jalan Pelabuhan Barat', 'Port Klang', 'Selangor', 'Malaysia', '42000', 'V4444555566', 15000.00, 30, 'Courier and freight services', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 8. Items
INSERT INTO `items` (`id`, `uuid`, `tenant_id`, `code`, `name`, `description`, `type`, `unit`, `sales_price`, `purchase_price`, `sales_account_id`, `purchase_account_id`, `inventory_account_id`, `track_inventory`, `quantity_on_hand`, `reorder_level`, `is_active`, `created_at`, `updated_at`)
VALUES
(1, 'e1f2a3b4-c5d6-e7f8-a9b0-c1d2e3f4a5b6', 1, 'SRV-DEV', 'Software Development Consulting', 'Full-stack custom software development per hour', 'service', 'hour', 250.00, 100.00, 13, 15, NULL, 0, 0.00, 0.00, 1, NOW(), NOW()),
(2, 'f2a3b4c5-d6e7-f8a9-b0c1-d2e3f4a5b6c7', 1, 'SRV-HOST', 'Dedicated Cloud Server Hosting', 'Monthly managed high performance cloud server', 'service', 'month', 650.00, 350.00, 13, 19, NULL, 0, 0.00, 0.00, 1, NOW(), NOW()),
(3, 'a3b4c5d6-e7f8-a9b0-c1d2-e3f4a5b6c7d8', 1, 'PRD-ROUTER', 'Enterprise Wi-Fi 6 Router', 'Multi-gigabit secure wireless router', 'product', 'unit', 1200.00, 750.00, 12, 15, 5, 1, 24.00, 5.00, 1, NOW(), NOW()),
(4, 'b4c5d6e7-f8a9-b0c1-d2e3-f4a5b6c7d8e9', 1, 'PRD-SWITCH', 'Managed Gigabit PoE+ Switch 24-Port', 'Enterprise 24 port rack-mount switch', 'product', 'unit', 1850.00, 1200.00, 12, 15, 5, 1, 15.00, 3.00, 1, NOW(), NOW()),
(5, 'c5d6e7f8-a9b0-c1d2-e3f4-a5b6c7d8e9f0', 1, 'SRV-AUDIT', 'IT Infrastructure & Security Audit', 'Full vulnerability and network architecture review', 'service', 'project', 5000.00, 2000.00, 13, 15, NULL, 0, 0.00, 0.00, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 9. Sample Invoices
INSERT INTO `invoices` (`id`, `uuid`, `tenant_id`, `fiscal_year_id`, `contact_id`, `invoice_number`, `invoice_date`, `due_date`, `status`, `subtotal`, `tax_amount`, `discount_amount`, `total`, `amount_paid`, `amount_due`, `notes`, `terms`, `currency`, `exchange_rate`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'd6e7f8a9-b0c1-d2e3-f4a5-b6c7d8e9f0a1', 1, 1, 1, 'INV-2024-0001', '2024-10-01', '2024-10-31', 'paid', 10000.00, 600.00, 0.00, 10600.00, 10600.00, 0.00, 'Phase 1 ERP integration milestone', 'Net 30 days', 'MYR', 1.0000, 1, NOW(), NOW()),
(2, 'e7f8a9b0-c1d2-e3f4-a5b6-c7d8e9f0a1b2', 1, 1, 2, 'INV-2024-0002', '2024-10-15', '2024-10-30', 'sent', 3700.00, 222.00, 0.00, 3922.00, 0.00, 3922.00, 'Cloud setup and hardware provisioning', 'Net 15 days', 'MYR', 1.0000, 1, NOW(), NOW()),
(3, 'f8a9b0c1-d2e3-f4a5-b6c7-d8e9f0a1b2c3', 1, 1, 3, 'INV-2024-0003', '2024-10-20', '2024-11-19', 'partial', 5000.00, 300.00, 0.00, 5300.00, 2500.00, 2800.00, 'Quarterly IT maintenance retainer', 'Net 30 days', 'MYR', 1.0000, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Invoice Lines
INSERT INTO `invoice_lines` (`id`, `invoice_id`, `item_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `tax_code_id`, `line_total`, `account_id`, `created_at`)
VALUES
(1, 1, 1, 'Software Development Consulting - 40 hrs', 40.00, 250.00, 0.00, 1, 10000.00, 13, NOW()),
(2, 2, 3, 'Enterprise Wi-Fi 6 Router', 2.00, 1200.00, 0.00, 1, 2400.00, 12, NOW()),
(3, 2, 2, 'Dedicated Cloud Server Hosting', 2.00, 650.00, 0.00, 1, 1300.00, 13, NOW()),
(4, 3, 5, 'IT Infrastructure & Security Audit', 1.00, 5000.00, 0.00, 1, 5000.00, 13, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Invoice Payments
INSERT INTO `invoice_payments` (`id`, `uuid`, `tenant_id`, `invoice_id`, `bank_account_id`, `payment_date`, `amount`, `reference`, `notes`, `journal_entry_id`, `created_at`)
VALUES
(1, 'a9b0c1d2-e3f4-a5b6-c7d8-e9f0a1b2c3d4', 1, 1, 1, '2024-10-10', 10600.00, 'MBK-PAY-001', 'Full payment via Maybank transfer', NULL, NOW()),
(2, 'b0c1d2e3-f4a5-b6c7-d8e9-f0a1b2c3d4e5', 1, 3, 1, '2024-10-25', 2500.00, 'MBK-PAY-002', 'First milestone partial payment', NULL, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 10. Sample Bills
INSERT INTO `bills` (`id`, `uuid`, `tenant_id`, `fiscal_year_id`, `contact_id`, `bill_number`, `bill_date`, `due_date`, `status`, `subtotal`, `tax_amount`, `discount_amount`, `total`, `amount_paid`, `amount_due`, `notes`, `currency`, `exchange_rate`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'c1d2e3f4-a5b6-c7d8-e9f0-a1b2c3d4e5f6', 1, 1, 5, 'BILL-AWS-9081', '2024-10-05', '2024-10-20', 'paid', 1400.00, 84.00, 0.00, 1484.00, 1484.00, 0.00, 'AWS Cloud server usage for September', 'MYR', 1.0000, 1, NOW(), NOW()),
(2, 'd2e3f4a5-b6c7-d8e9-f0a1-b2c3d4e5f6a7', 1, 1, 4, 'BILL-OD-4421', '2024-10-18', '2024-11-17', 'received', 3200.00, 192.00, 0.00, 3392.00, 0.00, 3392.00, 'Hardware stock replenishment', 'MYR', 1.0000, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Bill Lines
INSERT INTO `bill_lines` (`id`, `bill_id`, `item_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `tax_code_id`, `line_total`, `account_id`, `created_at`)
VALUES
(1, 1, NULL, 'AWS Cloud infrastructure charges', 1.00, 1400.00, 0.00, 1, 1400.00, 19, NOW()),
(2, 2, 3, 'Enterprise Wi-Fi 6 Router stock order', 4.00, 800.00, 0.00, 1, 3200.00, 5, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Bill Payments
INSERT INTO `bill_payments` (`id`, `uuid`, `tenant_id`, `bill_id`, `bank_account_id`, `payment_date`, `amount`, `reference`, `notes`, `journal_entry_id`, `created_at`)
VALUES
(1, 'e3f4a5b6-c7d8-e9f0-a1b2-c3d4e5f6a7b8', 1, 1, 1, '2024-10-15', 1484.00, 'MBK-OUT-001', 'Settled via auto debit Maybank', NULL, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 11. Sample Journal Entries (Posted)
INSERT INTO `journal_entries` (`id`, `uuid`, `tenant_id`, `fiscal_year_id`, `entry_date`, `reference`, `description`, `source`, `source_id`, `is_posted`, `is_void`, `void_reason`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'f4a5b6c7-d8e9-f0a1-b2c3-d4e5f6a7b8c9', 1, 1, '2024-10-01', 'JE-2024-001', 'Initial opening balance migration', 'manual', NULL, 1, 0, NULL, 1, NOW(), NOW()),
(2, 'a5b6c7d8-e9f0-a1b2-c3d4-e5f6a7b8c9d0', 1, 1, '2024-10-01', 'INV-2024-0001', 'Sales Invoice INV-2024-0001', 'invoice', 1, 1, 0, NULL, 1, NOW(), NOW()),
(3, 'b6c7d8e9-f0a1-b2c3-d4e5-f6a7b8c9d0e1', 1, 1, '2024-10-10', 'PAY-2024-0001', 'Payment received for INV-2024-0001', 'payment', 1, 1, 0, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Journal Lines for JE 1 (Capital and Bank opening)
INSERT INTO `journal_lines` (`id`, `journal_entry_id`, `account_id`, `description`, `debit`, `credit`, `created_at`)
VALUES
(1, 1, 2, 'Maybank opening balance', 45000.00, 0.00, NOW()),
(2, 1, 3, 'CIMB opening balance', 20000.00, 0.00, NOW()),
(3, 1, 10, 'Owner Equity opening', 0.00, 65000.00, NOW()),

-- Journal Lines for JE 2 (Sales Invoice: Dr AR 10600, Cr Revenue 10000, Cr Tax 600)
(4, 2, 4, 'Invoice INV-2024-0001 receivable', 10600.00, 0.00, NOW()),
(5, 2, 13, 'Service & Consulting Revenue', 0.00, 10000.00, NOW()),
(6, 2, 8, 'SST Tax Payable 6%', 0.00, 600.00, NOW()),

-- Journal Lines for JE 3 (Payment receipt: Dr Bank 10600, Cr AR 10600)
(7, 3, 2, 'Maybank cash collection', 10600.00, 0.00, NOW()),
(8, 3, 4, 'Clear Accounts Receivable', 0.00, 10600.00, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

SET FOREIGN_KEY_CHECKS = 1;
