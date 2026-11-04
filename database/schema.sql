-- ============================================================
-- YingLume Finance System — Complete MySQL 8.0 Schema
-- ============================================================
-- Engine:  InnoDB
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- Multi-tenant: every business table has a `tenant_id` FK
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+08:00';

-- ── Database ─────────────────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `yinglume_finance`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `yinglume_finance`;

-- ============================================================
-- SECTION 1: TENANTS & USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS `tenants` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `uuid`           CHAR(36)         NOT NULL,
    `name`           VARCHAR(150)     NOT NULL,
    `slug`           VARCHAR(80)      NOT NULL,
    `plan`           ENUM('trial','starter','professional','enterprise') NOT NULL DEFAULT 'trial',
    `status`         ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    `trial_ends_at`  DATETIME         NULL DEFAULT NULL,
    `settings`       JSON             NULL DEFAULT NULL COMMENT 'Tenant-specific configuration overrides',
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tenants_uuid` (`uuid`),
    UNIQUE KEY `uq_tenants_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Top-level tenant (company) registry';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
    `id`                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `uuid`               CHAR(36)         NOT NULL,
    `tenant_id`          INT UNSIGNED     NOT NULL,
    `name`               VARCHAR(150)     NOT NULL,
    `email`              VARCHAR(191)     NOT NULL,
    `password`           VARCHAR(255)     NOT NULL,
    `role`               ENUM('owner','admin','accountant','viewer') NOT NULL DEFAULT 'viewer',
    `status`             ENUM('active','inactive','invited') NOT NULL DEFAULT 'active',
    `remember_token`     VARCHAR(100)     NULL DEFAULT NULL,
    `login_attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`       DATETIME         NULL DEFAULT NULL,
    `last_login_at`      DATETIME         NULL DEFAULT NULL,
    `email_verified_at`  DATETIME         NULL DEFAULT NULL,
    `created_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_uuid` (`uuid`),
    UNIQUE KEY `uq_users_tenant_email` (`tenant_id`, `email`),
    KEY `idx_users_tenant` (`tenant_id`),
    KEY `idx_users_email`  (`email`),
    CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application users, scoped to a tenant';

-- ============================================================
-- SECTION 2: CORE ACCOUNTING
-- ============================================================

CREATE TABLE IF NOT EXISTS `fiscal_years` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `name`       VARCHAR(60)  NOT NULL COMMENT 'e.g. FY2024/2025',
    `start_date` DATE         NOT NULL,
    `end_date`   DATE         NOT NULL,
    `is_closed`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fy_tenant` (`tenant_id`),
    CONSTRAINT `fk_fy_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Accounting periods (financial years)';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `account_types` (
    `id`                  TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                VARCHAR(50)      NOT NULL,
    `normal_balance`      ENUM('debit','credit') NOT NULL,
    `financial_statement` ENUM('balance_sheet','income_statement') NOT NULL,
    `display_order`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_account_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Standard account type definitions (global, not tenant-scoped)';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `tenant_id`       INT UNSIGNED     NOT NULL,
    `fiscal_year_id`  INT UNSIGNED     NULL DEFAULT NULL,
    `parent_id`       INT UNSIGNED     NULL DEFAULT NULL,
    `code`            VARCHAR(20)      NOT NULL,
    `name`            VARCHAR(150)     NOT NULL,
    `account_type_id` TINYINT UNSIGNED NOT NULL,
    `description`     TEXT             NULL DEFAULT NULL,
    `is_system`       TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = created by system, cannot be deleted',
    `is_active`       TINYINT(1)       NOT NULL DEFAULT 1,
    `opening_balance` DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coa_tenant_code` (`tenant_id`, `code`),
    KEY `idx_coa_tenant`     (`tenant_id`),
    KEY `idx_coa_parent`     (`parent_id`),
    KEY `idx_coa_type`       (`account_type_id`),
    KEY `idx_coa_fy`         (`fiscal_year_id`),
    CONSTRAINT `fk_coa_tenant`   FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`        (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_coa_parent`   FOREIGN KEY (`parent_id`)       REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_coa_type`     FOREIGN KEY (`account_type_id`) REFERENCES `account_types`  (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_coa_fy`       FOREIGN KEY (`fiscal_year_id`)  REFERENCES `fiscal_years`   (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Chart of Accounts — tenant-scoped account ledger';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `journal_entries` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `uuid`           CHAR(36)       NOT NULL,
    `tenant_id`      INT UNSIGNED   NOT NULL,
    `fiscal_year_id` INT UNSIGNED   NOT NULL,
    `entry_date`     DATE           NOT NULL,
    `reference`      VARCHAR(50)    NULL DEFAULT NULL,
    `description`    VARCHAR(500)   NOT NULL,
    `source`         ENUM('manual','invoice','bill','payment','adjustment') NOT NULL DEFAULT 'manual',
    `source_id`      INT UNSIGNED   NULL DEFAULT NULL COMMENT 'FK to the originating document ID',
    `is_posted`      TINYINT(1)     NOT NULL DEFAULT 0,
    `is_void`        TINYINT(1)     NOT NULL DEFAULT 0,
    `void_reason`    VARCHAR(300)   NULL DEFAULT NULL,
    `created_by`     INT UNSIGNED   NULL DEFAULT NULL,
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_je_uuid` (`uuid`),
    KEY `idx_je_tenant`     (`tenant_id`),
    KEY `idx_je_fy`         (`fiscal_year_id`),
    KEY `idx_je_date`       (`entry_date`),
    KEY `idx_je_created_by` (`created_by`),
    CONSTRAINT `fk_je_tenant`     FOREIGN KEY (`tenant_id`)      REFERENCES `tenants`      (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_je_fy`         FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_je_created_by` FOREIGN KEY (`created_by`)     REFERENCES `users`        (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Double-entry journal entry header';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `journal_lines` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `journal_entry_id` INT UNSIGNED  NOT NULL,
    `account_id`       INT UNSIGNED  NOT NULL,
    `description`      VARCHAR(300)  NULL DEFAULT NULL,
    `debit`            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `credit`           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jl_entry`   (`journal_entry_id`),
    KEY `idx_jl_account` (`account_id`),
    CONSTRAINT `fk_jl_entry`   FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`    (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jl_account` FOREIGN KEY (`account_id`)       REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual debit/credit lines of a journal entry';

-- ============================================================
-- SECTION 3: CONTACTS (CUSTOMERS & VENDORS)
-- ============================================================

CREATE TABLE IF NOT EXISTS `contacts` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`          CHAR(36)      NOT NULL,
    `tenant_id`     INT UNSIGNED  NOT NULL,
    `type`          ENUM('customer','vendor','both') NOT NULL DEFAULT 'customer',
    `name`          VARCHAR(200)  NOT NULL,
    `email`         VARCHAR(191)  NULL DEFAULT NULL,
    `phone`         VARCHAR(30)   NULL DEFAULT NULL,
    `address`       VARCHAR(300)  NULL DEFAULT NULL,
    `city`          VARCHAR(100)  NULL DEFAULT NULL,
    `state`         VARCHAR(100)  NULL DEFAULT NULL,
    `country`       VARCHAR(100)  NULL DEFAULT 'Malaysia',
    `postal_code`   VARCHAR(20)   NULL DEFAULT NULL,
    `tax_number`    VARCHAR(50)   NULL DEFAULT NULL COMMENT 'SST / GST registration no.',
    `credit_limit`  DECIMAL(15,2) NULL DEFAULT NULL,
    `payment_terms` TINYINT       NOT NULL DEFAULT 30 COMMENT 'Net days',
    `notes`         TEXT          NULL DEFAULT NULL,
    `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_contacts_uuid` (`uuid`),
    KEY `idx_contacts_tenant` (`tenant_id`),
    KEY `idx_contacts_type`   (`type`),
    KEY `idx_contacts_email`  (`email`),
    CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customers, vendors, or both';

-- ============================================================
-- SECTION 4: TAX CODES
-- ============================================================

CREATE TABLE IF NOT EXISTS `tax_codes` (
    `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `tenant_id`  INT UNSIGNED   NOT NULL,
    `code`       VARCHAR(20)    NOT NULL,
    `name`       VARCHAR(100)   NOT NULL,
    `rate`       DECIMAL(5,4)   NOT NULL DEFAULT 0.0000 COMMENT 'e.g. 0.0600 = 6%',
    `type`       ENUM('sales','purchase','both') NOT NULL DEFAULT 'both',
    `account_id` INT UNSIGNED   NULL DEFAULT NULL,
    `is_active`  TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tax_tenant_code` (`tenant_id`, `code`),
    KEY `idx_tax_tenant` (`tenant_id`),
    CONSTRAINT `fk_tax_tenant`  FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`           (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tax_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tax rates (SST, GST, etc.)';

-- ============================================================
-- SECTION 5: INVENTORY ITEMS
-- ============================================================

CREATE TABLE IF NOT EXISTS `items` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`                 CHAR(36)      NOT NULL,
    `tenant_id`            INT UNSIGNED  NOT NULL,
    `code`                 VARCHAR(50)   NULL DEFAULT NULL,
    `name`                 VARCHAR(200)  NOT NULL,
    `description`          TEXT          NULL DEFAULT NULL,
    `type`                 ENUM('product','service') NOT NULL DEFAULT 'product',
    `unit`                 VARCHAR(20)   NULL DEFAULT NULL COMMENT 'e.g. pcs, kg, hr',
    `sales_price`          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `purchase_price`       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `sales_account_id`     INT UNSIGNED  NULL DEFAULT NULL,
    `purchase_account_id`  INT UNSIGNED  NULL DEFAULT NULL,
    `inventory_account_id` INT UNSIGNED  NULL DEFAULT NULL,
    `track_inventory`      TINYINT(1)    NOT NULL DEFAULT 0,
    `quantity_on_hand`     DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    `reorder_level`        DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    `is_active`            TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_items_uuid` (`uuid`),
    UNIQUE KEY `uq_items_tenant_code` (`tenant_id`, `code`),
    KEY `idx_items_tenant`            (`tenant_id`),
    KEY `idx_items_sales_account`     (`sales_account_id`),
    KEY `idx_items_purchase_account`  (`purchase_account_id`),
    KEY `idx_items_inventory_account` (`inventory_account_id`),
    CONSTRAINT `fk_items_tenant`              FOREIGN KEY (`tenant_id`)            REFERENCES `tenants`           (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_items_sales_account`       FOREIGN KEY (`sales_account_id`)     REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_items_purchase_account`    FOREIGN KEY (`purchase_account_id`)  REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_items_inventory_account`   FOREIGN KEY (`inventory_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Products and services sold or purchased';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tenant_id`  INT UNSIGNED  NOT NULL,
    `item_id`    INT UNSIGNED  NOT NULL,
    `type`       ENUM('in','out','adjustment') NOT NULL,
    `quantity`   DECIMAL(10,4) NOT NULL,
    `cost_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `reference`  VARCHAR(100)  NULL DEFAULT NULL,
    `notes`      VARCHAR(300)  NULL DEFAULT NULL,
    `created_by` INT UNSIGNED  NULL DEFAULT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sm_tenant`      (`tenant_id`),
    KEY `idx_sm_item`        (`item_id`),
    KEY `idx_sm_created_by`  (`created_by`),
    CONSTRAINT `fk_sm_tenant`     FOREIGN KEY (`tenant_id`)  REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sm_item`       FOREIGN KEY (`item_id`)    REFERENCES `items`   (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sm_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`   (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inventory stock movement log';

-- ============================================================
-- SECTION 6: BANKING
-- ============================================================

CREATE TABLE IF NOT EXISTS `bank_accounts` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)      NOT NULL,
    `tenant_id`       INT UNSIGNED  NOT NULL,
    `account_id`      INT UNSIGNED  NOT NULL COMMENT 'Linked GL chart_of_accounts entry',
    `name`            VARCHAR(100)  NOT NULL,
    `account_number`  VARCHAR(50)   NULL DEFAULT NULL,
    `bank_name`       VARCHAR(100)  NULL DEFAULT NULL,
    `bank_branch`     VARCHAR(100)  NULL DEFAULT NULL,
    `swift_code`      VARCHAR(20)   NULL DEFAULT NULL,
    `currency`        CHAR(3)       NOT NULL DEFAULT 'MYR',
    `opening_balance` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `opening_date`    DATE          NULL DEFAULT NULL,
    `is_default`      TINYINT(1)    NOT NULL DEFAULT 0,
    `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bank_uuid` (`uuid`),
    KEY `idx_bank_tenant`  (`tenant_id`),
    KEY `idx_bank_account` (`account_id`),
    CONSTRAINT `fk_bank_tenant`  FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`           (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bank_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Company bank account registry';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `bank_transactions` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)      NOT NULL,
    `tenant_id`       INT UNSIGNED  NOT NULL,
    `bank_account_id` INT UNSIGNED  NOT NULL,
    `transaction_date` DATE         NOT NULL,
    `type`            ENUM('deposit','withdrawal','transfer') NOT NULL,
    `amount`          DECIMAL(15,4) NOT NULL,
    `description`     VARCHAR(300)  NULL DEFAULT NULL,
    `reference`       VARCHAR(100)  NULL DEFAULT NULL,
    `is_reconciled`   TINYINT(1)    NOT NULL DEFAULT 0,
    `reconciled_at`   DATETIME      NULL DEFAULT NULL,
    `journal_entry_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bt_uuid` (`uuid`),
    KEY `idx_bt_tenant`       (`tenant_id`),
    KEY `idx_bt_bank_account` (`bank_account_id`),
    KEY `idx_bt_date`         (`transaction_date`),
    KEY `idx_bt_je`           (`journal_entry_id`),
    CONSTRAINT `fk_bt_tenant`       FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`         (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bt_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`  (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bt_je`           FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual bank statement transactions';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `bank_reconciliations` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tenant_id`       INT UNSIGNED  NOT NULL,
    `bank_account_id` INT UNSIGNED  NOT NULL,
    `period_start`    DATE          NOT NULL,
    `period_end`      DATE          NOT NULL,
    `closing_balance` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `status`          ENUM('draft','completed') NOT NULL DEFAULT 'draft',
    `completed_at`    DATETIME      NULL DEFAULT NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_br_tenant`       (`tenant_id`),
    KEY `idx_br_bank_account` (`bank_account_id`),
    CONSTRAINT `fk_br_tenant`       FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`        (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_br_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bank reconciliation sessions';

-- ============================================================
-- SECTION 7: ACCOUNTS RECEIVABLE (AR)
-- ============================================================

CREATE TABLE IF NOT EXISTS `invoices` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)      NOT NULL,
    `tenant_id`       INT UNSIGNED  NOT NULL,
    `fiscal_year_id`  INT UNSIGNED  NOT NULL,
    `contact_id`      INT UNSIGNED  NOT NULL,
    `invoice_number`  VARCHAR(30)   NOT NULL,
    `invoice_date`    DATE          NOT NULL,
    `due_date`        DATE          NOT NULL,
    `status`          ENUM('draft','sent','partial','paid','overdue','void') NOT NULL DEFAULT 'draft',
    `subtotal`        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `tax_amount`      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total`           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `amount_paid`     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `amount_due`      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `notes`           TEXT          NULL DEFAULT NULL,
    `terms`           TEXT          NULL DEFAULT NULL,
    `currency`        CHAR(3)       NOT NULL DEFAULT 'MYR',
    `exchange_rate`   DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    `created_by`      INT UNSIGNED  NULL DEFAULT NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inv_uuid` (`uuid`),
    UNIQUE KEY `uq_inv_tenant_number` (`tenant_id`, `invoice_number`),
    KEY `idx_inv_tenant`     (`tenant_id`),
    KEY `idx_inv_contact`    (`contact_id`),
    KEY `idx_inv_fy`         (`fiscal_year_id`),
    KEY `idx_inv_status`     (`status`),
    KEY `idx_inv_due_date`   (`due_date`),
    KEY `idx_inv_created_by` (`created_by`),
    CONSTRAINT `fk_inv_tenant`     FOREIGN KEY (`tenant_id`)      REFERENCES `tenants`      (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_contact`    FOREIGN KEY (`contact_id`)     REFERENCES `contacts`     (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_fy`         FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by`)     REFERENCES `users`        (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sales invoices (AR)';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `invoice_lines` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `invoice_id`       INT UNSIGNED  NOT NULL,
    `item_id`          INT UNSIGNED  NULL DEFAULT NULL,
    `description`      VARCHAR(300)  NOT NULL,
    `quantity`         DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    `unit_price`       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `discount_percent` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `tax_code_id`      INT UNSIGNED  NULL DEFAULT NULL,
    `line_total`       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `account_id`       INT UNSIGNED  NOT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invline_invoice`   (`invoice_id`),
    KEY `idx_invline_item`      (`item_id`),
    KEY `idx_invline_tax`       (`tax_code_id`),
    KEY `idx_invline_account`   (`account_id`),
    CONSTRAINT `fk_invline_invoice`  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`          (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invline_item`     FOREIGN KEY (`item_id`)    REFERENCES `items`             (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invline_tax`      FOREIGN KEY (`tax_code_id`) REFERENCES `tax_codes`        (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invline_account`  FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items on a sales invoice';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `invoice_payments` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`             CHAR(36)      NOT NULL,
    `tenant_id`        INT UNSIGNED  NOT NULL,
    `invoice_id`       INT UNSIGNED  NOT NULL,
    `bank_account_id`  INT UNSIGNED  NOT NULL,
    `payment_date`     DATE          NOT NULL,
    `amount`           DECIMAL(15,4) NOT NULL,
    `reference`        VARCHAR(100)  NULL DEFAULT NULL,
    `notes`            VARCHAR(300)  NULL DEFAULT NULL,
    `journal_entry_id` INT UNSIGNED  NULL DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invpay_uuid` (`uuid`),
    KEY `idx_invpay_tenant`       (`tenant_id`),
    KEY `idx_invpay_invoice`      (`invoice_id`),
    KEY `idx_invpay_bank_account` (`bank_account_id`),
    KEY `idx_invpay_je`           (`journal_entry_id`),
    CONSTRAINT `fk_invpay_tenant`       FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`         (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invpay_invoice`      FOREIGN KEY (`invoice_id`)      REFERENCES `invoices`        (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invpay_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`  (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_invpay_je`           FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Payments received against sales invoices';

-- ============================================================
-- SECTION 8: ACCOUNTS PAYABLE (AP)
-- ============================================================

CREATE TABLE IF NOT EXISTS `bills` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)      NOT NULL,
    `tenant_id`       INT UNSIGNED  NOT NULL,
    `fiscal_year_id`  INT UNSIGNED  NOT NULL,
    `contact_id`      INT UNSIGNED  NOT NULL,
    `bill_number`     VARCHAR(50)   NOT NULL,
    `bill_date`       DATE          NOT NULL,
    `due_date`        DATE          NOT NULL,
    `status`          ENUM('draft','received','partial','paid','overdue','void') NOT NULL DEFAULT 'draft',
    `subtotal`        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `tax_amount`      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total`           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `amount_paid`     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `amount_due`      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `notes`           TEXT          NULL DEFAULT NULL,
    `currency`        CHAR(3)       NOT NULL DEFAULT 'MYR',
    `exchange_rate`   DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    `created_by`      INT UNSIGNED  NULL DEFAULT NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bill_uuid` (`uuid`),
    KEY `idx_bill_tenant`     (`tenant_id`),
    KEY `idx_bill_contact`    (`contact_id`),
    KEY `idx_bill_fy`         (`fiscal_year_id`),
    KEY `idx_bill_status`     (`status`),
    KEY `idx_bill_due_date`   (`due_date`),
    KEY `idx_bill_created_by` (`created_by`),
    CONSTRAINT `fk_bill_tenant`     FOREIGN KEY (`tenant_id`)      REFERENCES `tenants`      (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bill_contact`    FOREIGN KEY (`contact_id`)     REFERENCES `contacts`     (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_bill_fy`         FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_bill_created_by` FOREIGN KEY (`created_by`)     REFERENCES `users`        (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Supplier bills/purchase invoices (AP)';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `bill_lines` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `bill_id`          INT UNSIGNED  NOT NULL,
    `item_id`          INT UNSIGNED  NULL DEFAULT NULL,
    `description`      VARCHAR(300)  NOT NULL,
    `quantity`         DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    `unit_price`       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `discount_percent` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `tax_code_id`      INT UNSIGNED  NULL DEFAULT NULL,
    `line_total`       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `account_id`       INT UNSIGNED  NOT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_billline_bill`    (`bill_id`),
    KEY `idx_billline_item`    (`item_id`),
    KEY `idx_billline_tax`     (`tax_code_id`),
    KEY `idx_billline_account` (`account_id`),
    CONSTRAINT `fk_billline_bill`    FOREIGN KEY (`bill_id`)    REFERENCES `bills`             (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_billline_item`    FOREIGN KEY (`item_id`)    REFERENCES `items`             (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_billline_tax`     FOREIGN KEY (`tax_code_id`) REFERENCES `tax_codes`       (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_billline_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items on a supplier bill';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `bill_payments` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`             CHAR(36)      NOT NULL,
    `tenant_id`        INT UNSIGNED  NOT NULL,
    `bill_id`          INT UNSIGNED  NOT NULL,
    `bank_account_id`  INT UNSIGNED  NOT NULL,
    `payment_date`     DATE          NOT NULL,
    `amount`           DECIMAL(15,4) NOT NULL,
    `reference`        VARCHAR(100)  NULL DEFAULT NULL,
    `notes`            VARCHAR(300)  NULL DEFAULT NULL,
    `journal_entry_id` INT UNSIGNED  NULL DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_billpay_uuid` (`uuid`),
    KEY `idx_billpay_tenant`       (`tenant_id`),
    KEY `idx_billpay_bill`         (`bill_id`),
    KEY `idx_billpay_bank_account` (`bank_account_id`),
    KEY `idx_billpay_je`           (`journal_entry_id`),
    CONSTRAINT `fk_billpay_tenant`       FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`          (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_billpay_bill`         FOREIGN KEY (`bill_id`)         REFERENCES `bills`            (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_billpay_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`   (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_billpay_je`           FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Payments made against supplier bills';

-- ============================================================
-- SECTION 9: SETTINGS & AUDIT
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         NULL DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_tenant_key` (`tenant_id`, `key`),
    KEY `idx_settings_tenant` (`tenant_id`),
    CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Key-value settings store, tenant-scoped';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED    NULL DEFAULT NULL,
    `user_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `action`      VARCHAR(100)    NOT NULL COMMENT 'e.g. created, updated, deleted',
    `model`       VARCHAR(100)    NOT NULL COMMENT 'Model/table name',
    `model_id`    INT UNSIGNED    NULL DEFAULT NULL,
    `old_values`  JSON            NULL DEFAULT NULL,
    `new_values`  JSON            NULL DEFAULT NULL,
    `ip_address`  VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`  VARCHAR(300)    NULL DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_tenant`  (`tenant_id`),
    KEY `idx_al_user`    (`user_id`),
    KEY `idx_al_model`   (`model`, `model_id`),
    KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit / activity log';

-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(191) NOT NULL,
    `token`      VARCHAR(100) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_email` (`email`),
    KEY `idx_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset tokens (expire after 60 minutes)';

-- ============================================================
-- SEED: account_types (global reference data)
-- ============================================================

INSERT INTO `account_types` (`id`, `name`, `normal_balance`, `financial_statement`, `display_order`)
VALUES
    (1, 'Assets',      'debit',  'balance_sheet',     1),
    (2, 'Liabilities', 'credit', 'balance_sheet',     2),
    (3, 'Equity',      'credit', 'balance_sheet',     3),
    (4, 'Revenue',     'credit', 'income_statement',  4),
    (5, 'Expenses',    'debit',  'income_statement',  5)
ON DUPLICATE KEY UPDATE
    `name`                = VALUES(`name`),
    `normal_balance`      = VALUES(`normal_balance`),
    `financial_statement` = VALUES(`financial_statement`),
    `display_order`       = VALUES(`display_order`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SCHEMA
-- ============================================================
