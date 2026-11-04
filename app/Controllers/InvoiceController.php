<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Invoice;

/**
 * InvoiceController
 * Accounts Receivable invoices, payments, printing, and ledger posting.
 */
class InvoiceController extends BaseController
{
    private Invoice $invoiceModel;

    public function __construct()
    {
        parent::__construct();
        $this->invoiceModel = new Invoice();
    }

    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $status = (string)$this->input('status', 'all');
        $search = (string)$this->input('search', '');

        $where = ['i.tenant_id = :tid'];
        $params = [':tid' => $tenantId];

        if ($status !== 'all' && $status !== '') {
            $where[] = 'i.status = :st';
            $params[':st'] = $status;
        }

        if ($search !== '') {
            $where[] = '(i.invoice_number LIKE :s OR c.name LIKE :s2)';
            $params[':s']  = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
        }

        $sql = "SELECT i.*, c.name AS customer_name 
                FROM invoices i
                JOIN contacts c ON i.contact_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.invoice_date DESC, i.id DESC";

        $invoices = $db->select($sql, $params);

        // Stats summary
        $stats = $db->selectOne(
            "SELECT 
                COUNT(*) AS total_count,
                COALESCE(SUM(total), 0) AS total_amount,
                COALESCE(SUM(amount_due), 0) AS total_due,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
             FROM invoices WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        );

        $this->render('invoices.index', [
            'pageTitle' => 'Sales Invoices',
            'invoices'  => $invoices,
            'status'    => $status,
            'search'    => $search,
            'stats'     => $stats,
        ], 'main');
    }

    public function create(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $customers = $db->select("SELECT id, name, payment_terms FROM contacts WHERE tenant_id = :tid AND type IN ('customer', 'both') AND is_active = 1 ORDER BY name ASC", [':tid' => $tenantId]);
        $items     = $db->select("SELECT id, code, name, sales_price, sales_account_id FROM items WHERE tenant_id = :tid AND is_active = 1 ORDER BY name ASC", [':tid' => $tenantId]);
        $taxCodes  = $db->select("SELECT id, code, name, rate FROM tax_codes WHERE tenant_id = :tid AND is_active = 1", [':tid' => $tenantId]);
        $accounts  = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 4 ORDER BY code ASC", [':tid' => $tenantId]);

        // Default next invoice number
        $lastInv = $db->selectOne("SELECT invoice_number FROM invoices WHERE tenant_id = :tid ORDER BY id DESC LIMIT 1", [':tid' => $tenantId]);
        $nextNum = 'INV-' . date('Y') . '-' . str_pad((string)(($lastInv ? (int)preg_replace('/[^0-9]/', '', $lastInv['invoice_number']) + 1 : 1)), 4, '0', STR_PAD_LEFT);

        $this->render('invoices.form', [
            'pageTitle'       => 'New Sales Invoice',
            'invoice'         => null,
            'customers'       => $customers,
            'items'           => $items,
            'taxCodes'        => $taxCodes,
            'revenueAccounts' => $accounts,
            'nextNum'         => $nextNum,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $contactId   = (int)$this->input('contact_id');
        $invNumber   = (string)$this->input('invoice_number');
        $invDate     = (string)$this->input('invoice_date');
        $dueDate     = (string)$this->input('due_date');
        $notes       = (string)$this->input('notes', '');
        $terms       = (string)$this->input('terms', '');

        $itemIds     = $_POST['item_id'] ?? [];
        $descs       = $_POST['description'] ?? [];
        $qtys        = $_POST['quantity'] ?? [];
        $prices      = $_POST['unit_price'] ?? [];
        $taxCodeIds  = $_POST['tax_code_id'] ?? [];
        $accountIds  = $_POST['account_id'] ?? [];

        if (empty($descs)) {
            $this->flash('error', 'Please provide at least one line item.');
            $this->redirect('/app/invoices/create');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $lines = [];

        // Fetch tax rates map
        $allTaxes = $db->select("SELECT id, rate FROM tax_codes WHERE tenant_id = :tid", [':tid' => $tenantId]);
        $taxMap = [];
        foreach ($allTaxes as $t) {
            $taxMap[(int)$t['id']] = (float)$t['rate'];
        }

        // Default revenue account
        $defRevAcc = $db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '4000'", [':tid' => $tenantId])['id'] ?? 12;

        for ($i = 0; $i < count($descs); $i++) {
            $desc = trim((string)($descs[$i] ?? ''));
            if ($desc === '') continue;

            $qty   = max(0.0001, (float)($qtys[$i] ?? 1));
            $price = max(0.0, (float)($prices[$i] ?? 0));
            $lineTotal = round($qty * $price, 4);

            $tId   = !empty($taxCodeIds[$i]) ? (int)$taxCodeIds[$i] : null;
            $taxRate = $tId && isset($taxMap[$tId]) ? $taxMap[$tId] : 0.0;
            $taxAmount = round($lineTotal * $taxRate, 4);

            $subtotal += $lineTotal;
            $taxTotal += $taxAmount;

            $lines[] = [
                'item_id'        => !empty($itemIds[$i]) ? (int)$itemIds[$i] : null,
                'description'    => $desc,
                'quantity'       => $qty,
                'unit_price'     => $price,
                'discount_percent' => 0.0,
                'tax_code_id'    => $tId,
                'line_total'     => $lineTotal,
                'account_id'     => !empty($accountIds[$i]) ? (int)$accountIds[$i] : $defRevAcc,
            ];
        }

        $total = $subtotal + $taxTotal;

        $fy = $db->selectOne("SELECT id FROM fiscal_years WHERE tenant_id = :tid AND is_closed = 0 ORDER BY id DESC LIMIT 1", [':tid' => $tenantId]);
        $fyId = $fy ? (int)$fy['id'] : 1;

        $db->beginTransaction();
        try {
            $invId = (int)$db->insert(
                "INSERT INTO invoices (uuid, tenant_id, fiscal_year_id, contact_id, invoice_number, invoice_date, due_date, status, subtotal, tax_amount, discount_amount, total, amount_paid, amount_due, notes, terms, currency, exchange_rate, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :cid, :num, :idate, :ddate, 'draft', :sub, :tax, 0.00, :tot, 0.00, :tot, :notes, :terms, 'MYR', 1.0000, :uid, NOW(), NOW())",
                [
                    ':uuid'  => generate_uuid(),
                    ':tid'   => $tenantId,
                    ':fy'    => $fyId,
                    ':cid'   => $contactId,
                    ':num'   => $invNumber,
                    ':idate' => $invDate ?: date('Y-m-d'),
                    ':ddate' => $dueDate ?: date('Y-m-d', strtotime('+30 days')),
                    ':sub'   => $subtotal,
                    ':tax'   => $taxTotal,
                    ':tot'   => $total,
                    ':notes' => $notes,
                    ':terms' => $terms,
                    ':uid'   => Auth::id(),
                ]
            );

            foreach ($lines as $line) {
                $db->insert(
                    "INSERT INTO invoice_lines (invoice_id, item_id, description, quantity, unit_price, discount_percent, tax_code_id, line_total, account_id, created_at)
                     VALUES (:iid, :item, :desc, :qty, :price, :disc, :tax, :tot, :acc, NOW())",
                    [
                        ':iid'   => $invId,
                        ':item'  => $line['item_id'],
                        ':desc'  => $line['description'],
                        ':qty'   => $line['quantity'],
                        ':price' => $line['unit_price'],
                        ':disc'  => $line['discount_percent'],
                        ':tax'   => $line['tax_code_id'],
                        ':tot'   => $line['line_total'],
                        ':acc'   => $line['account_id'],
                    ]
                );
            }

            $db->commit();
            $this->flash('success', "Invoice {$invNumber} created successfully.");
            $this->redirect('/app/invoices/' . $invId);
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Error creating invoice: ' . $e->getMessage());
            $this->redirect('/app/invoices/create');
        }
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $invoice = $db->selectOne(
            "SELECT i.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address, c.tax_number AS customer_tax
             FROM invoices i
             JOIN contacts c ON i.contact_id = c.id
             WHERE i.id = :id AND i.tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );

        if (!$invoice) {
            $this->flash('error', 'Invoice not found.');
            $this->redirect('/app/invoices');
        }

        $lines = $db->select("SELECT * FROM invoice_lines WHERE invoice_id = :id ORDER BY id ASC", [':id' => $id]);
        $payments = $db->select(
            "SELECT ip.*, ba.name AS bank_name 
             FROM invoice_payments ip
             JOIN bank_accounts ba ON ip.bank_account_id = ba.id
             WHERE ip.invoice_id = :id AND ip.tenant_id = :tid
             ORDER BY ip.payment_date DESC",
            [':id' => $id, ':tid' => $tenantId]
        );

        $tenant = $db->selectOne("SELECT * FROM tenants WHERE id = :tid", [':tid' => $tenantId]);

        $this->render('invoices.show', [
            'pageTitle' => 'Invoice ' . $invoice['invoice_number'],
            'invoice'   => $invoice,
            'lines'     => $lines,
            'payments'  => $payments,
            'tenant'    => $tenant,
        ], 'main');
    }

    public function markSent(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $invoice = $db->selectOne("SELECT * FROM invoices WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if ($invoice && $invoice['status'] === 'draft') {
            $db->beginTransaction();
            try {
                // Update status to sent
                $db->update("UPDATE invoices SET status = 'sent', updated_at = NOW() WHERE id = :id", [':id' => $id]);

                // Post Journal Entry for Invoice: Dr AR, Cr Sales Revenue, Cr Tax
                $lines = $db->select("SELECT * FROM invoice_lines WHERE invoice_id = :id", [':id' => $id]);
                $arAccId = (int)($db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '1200'", [':tid' => $tenantId])['id'] ?? 4);
                $taxAccId = (int)($db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '2100'", [':tid' => $tenantId])['id'] ?? 8);

                $jeId = (int)$db->insert(
                    "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, source_id, is_posted, is_void, created_by, created_at, updated_at)
                     VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'invoice', :sid, 1, 0, :uid, NOW(), NOW())",
                    [
                        ':uuid' => generate_uuid(),
                        ':tid'  => $tenantId,
                        ':fy'   => $invoice['fiscal_year_id'],
                        ':ed'   => $invoice['invoice_date'],
                        ':ref'  => $invoice['invoice_number'],
                        ':desc' => 'Invoice ' . $invoice['invoice_number'] . ' issued',
                        ':sid'  => $id,
                        ':uid'  => Auth::id(),
                    ]
                );

                // Dr AR (total)
                $db->insert(
                    "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                     VALUES (:je, :aid, :desc, :deb, 0, NOW())",
                    [
                        ':je'   => $jeId,
                        ':aid'  => $arAccId,
                        ':desc' => 'Accounts Receivable for ' . $invoice['invoice_number'],
                        ':deb'  => $invoice['total'],
                    ]
                );

                // Cr Revenue lines
                foreach ($lines as $line) {
                    $db->insert(
                        "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                         VALUES (:je, :aid, :desc, 0, :cred, NOW())",
                        [
                            ':je'   => $jeId,
                            ':aid'  => $line['account_id'],
                            ':desc' => $line['description'],
                            ':cred' => $line['line_total'],
                        ]
                    );
                }

                // Cr Tax Payable if any
                if ((float)$invoice['tax_amount'] > 0) {
                    $db->insert(
                        "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                         VALUES (:je, :aid, :desc, 0, :cred, NOW())",
                        [
                            ':je'   => $jeId,
                            ':aid'  => $taxAccId,
                            ':desc' => 'Tax collected on ' . $invoice['invoice_number'],
                            ':cred' => $invoice['tax_amount'],
                        ]
                    );
                }

                $db->commit();
                $this->flash('success', 'Invoice sent and automatically posted to General Ledger.');
            } catch (\Throwable $e) {
                $db->rollback();
                $this->flash('error', 'Error posting invoice: ' . $e->getMessage());
            }
        }

        $this->redirect('/app/invoices/' . $id);
    }

    public function pay(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $invoice = $db->selectOne("SELECT * FROM invoices WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$invoice || $invoice['amount_due'] <= 0) {
            $this->flash('error', 'Invoice cannot receive payment.');
            $this->redirect('/app/invoices/' . $id);
        }

        $banks = $db->select("SELECT * FROM bank_accounts WHERE tenant_id = :tid AND is_active = 1", [':tid' => $tenantId]);

        $this->render('invoices.pay', [
            'pageTitle' => 'Receive Payment for ' . $invoice['invoice_number'],
            'invoice'   => $invoice,
            'banks'     => $banks,
        ], 'main');
    }

    public function recordPayment(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $invoice = $db->selectOne("SELECT * FROM invoices WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$invoice) {
            $this->flash('error', 'Invoice not found.');
            $this->redirect('/app/invoices');
        }

        $amount    = (float)$this->input('amount');
        $bankAccId = (int)$this->input('bank_account_id');
        $payDate   = (string)$this->input('payment_date', date('Y-m-d'));
        $ref       = (string)$this->input('reference', '');
        $notes     = (string)$this->input('notes', '');

        if ($amount <= 0 || $amount > (float)$invoice['amount_due']) {
            $this->flash('error', 'Payment amount must be between 0.01 and ' . $invoice['amount_due']);
            $this->redirect('/app/invoices/' . $id . '/pay');
        }

        $bank = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :bid AND tenant_id = :tid", [':bid' => $bankAccId, ':tid' => $tenantId]);
        if (!$bank) {
            $this->flash('error', 'Select a valid bank account.');
            $this->redirect('/app/invoices/' . $id . '/pay');
        }

        $db->beginTransaction();
        try {
            $newPaid = (float)$invoice['amount_paid'] + $amount;
            $newDue  = max(0.0, (float)$invoice['amount_due'] - $amount);
            $newStatus = ($newDue <= 0.0001) ? 'paid' : 'partial';

            // Journal Entry for Payment: Dr Bank, Cr AR
            $arAccId = (int)($db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '1200'", [':tid' => $tenantId])['id'] ?? 4);

            $jeId = (int)$db->insert(
                "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, source_id, is_posted, is_void, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'payment', :sid, 1, 0, :uid, NOW(), NOW())",
                [
                    ':uuid' => generate_uuid(),
                    ':tid'  => $tenantId,
                    ':fy'   => $invoice['fiscal_year_id'],
                    ':ed'   => $payDate,
                    ':ref'  => $ref ?: ('PAY-' . $invoice['invoice_number']),
                    ':desc' => 'Payment for ' . $invoice['invoice_number'],
                    ':sid'  => $id,
                    ':uid'  => Auth::id(),
                ]
            );

            // Dr Bank Account GL
            $db->insert(
                "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                 VALUES (:je, :aid, :desc, :deb, 0, NOW())",
                [
                    ':je'   => $jeId,
                    ':aid'  => $bank['account_id'],
                    ':desc' => 'Receipt via ' . $bank['name'],
                    ':deb'  => $amount,
                ]
            );

            // Cr Accounts Receivable
            $db->insert(
                "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                 VALUES (:je, :aid, :desc, 0, :cred, NOW())",
                [
                    ':je'   => $jeId,
                    ':aid'  => $arAccId,
                    ':desc' => 'Settlement against ' . $invoice['invoice_number'],
                    ':cred' => $amount,
                ]
            );

            // Insert invoice payment record
            $db->insert(
                "INSERT INTO invoice_payments (uuid, tenant_id, invoice_id, bank_account_id, payment_date, amount, reference, notes, journal_entry_id, created_at)
                 VALUES (:uuid, :tid, :iid, :bid, :pdate, :amt, :ref, :notes, :je, NOW())",
                [
                    ':uuid'  => generate_uuid(),
                    ':tid'   => $tenantId,
                    ':iid'   => $id,
                    ':bid'   => $bankAccId,
                    ':pdate' => $payDate,
                    ':amt'   => $amount,
                    ':ref'   => $ref,
                    ':notes' => $notes,
                    ':je'    => $jeId,
                ]
            );

            // Update invoice status & balances
            $db->update(
                "UPDATE invoices SET amount_paid = :paid, amount_due = :due, status = :status, updated_at = NOW() WHERE id = :id",
                [':paid' => $newPaid, ':due' => $newDue, ':status' => $newStatus, ':id' => $id]
            );

            $db->commit();
            $this->flash('success', 'Payment of ' . money($amount) . ' recorded successfully.');
            $this->redirect('/app/invoices/' . $id);
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Payment recording failed: ' . $e->getMessage());
            $this->redirect('/app/invoices/' . $id . '/pay');
        }
    }

    public function void(string $id): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $invoice = $db->selectOne("SELECT * FROM invoices WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if ($invoice && (float)$invoice['amount_paid'] == 0) {
            $db->update("UPDATE invoices SET status = 'void', updated_at = NOW() WHERE id = :id", [':id' => $id]);
            $this->flash('warning', 'Invoice has been voided.');
        } else {
            $this->flash('error', 'Cannot void an invoice that has received payments.');
        }

        $this->redirect('/app/invoices/' . $id);
    }

    public function pdf(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $invoice = $db->selectOne(
            "SELECT i.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address, c.tax_number AS customer_tax
             FROM invoices i
             JOIN contacts c ON i.contact_id = c.id
             WHERE i.id = :id AND i.tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );

        $lines = $db->select("SELECT * FROM invoice_lines WHERE invoice_id = :id ORDER BY id ASC", [':id' => $id]);
        $tenant = $db->selectOne("SELECT * FROM tenants WHERE id = :tid", [':tid' => $tenantId]);

        // Render printable view directly
        $this->render('invoices.show', [
            'pageTitle' => 'Print Invoice #' . $invoice['invoice_number'],
            'invoice'   => $invoice,
            'lines'     => $lines,
            'payments'  => [],
            'tenant'    => $tenant,
            'isPrint'   => true,
        ], 'main');
    }

    public function ajaxGetItem(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $item = $db->selectOne("SELECT * FROM items WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        $this->json(['item' => $item]);
    }
}
