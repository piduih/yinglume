<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Bill;

/**
 * BillController
 * Accounts Payable bills, vendor payments, and ledger posting.
 */
class BillController extends BaseController
{
    private Bill $billModel;

    public function __construct()
    {
        parent::__construct();
        $this->billModel = new Bill();
    }

    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $status = (string)$this->input('status', 'all');
        $search = (string)$this->input('search', '');

        $where = ['b.tenant_id = :tid'];
        $params = [':tid' => $tenantId];

        if ($status !== 'all' && $status !== '') {
            $where[] = 'b.status = :st';
            $params[':st'] = $status;
        }

        if ($search !== '') {
            $where[] = '(b.bill_number LIKE :s OR c.name LIKE :s2)';
            $params[':s']  = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
        }

        $sql = "SELECT b.*, c.name AS vendor_name 
                FROM bills b
                JOIN contacts c ON b.contact_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY b.bill_date DESC, b.id DESC";

        $bills = $db->select($sql, $params);

        $stats = $db->selectOne(
            "SELECT 
                COUNT(*) AS total_count,
                COALESCE(SUM(total), 0) AS total_amount,
                COALESCE(SUM(amount_due), 0) AS total_due,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
             FROM bills WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        );

        $this->render('bills.index', [
            'pageTitle' => 'Vendor Bills',
            'bills'     => $bills,
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

        $vendors  = $db->select("SELECT id, name, payment_terms FROM contacts WHERE tenant_id = :tid AND type IN ('vendor', 'both') AND is_active = 1 ORDER BY name ASC", [':tid' => $tenantId]);
        $items    = $db->select("SELECT id, code, name, purchase_price, purchase_account_id FROM items WHERE tenant_id = :tid AND is_active = 1 ORDER BY name ASC", [':tid' => $tenantId]);
        $taxCodes = $db->select("SELECT id, code, name, rate FROM tax_codes WHERE tenant_id = :tid AND is_active = 1", [':tid' => $tenantId]);
        $accounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid AND account_type_id = 5 ORDER BY code ASC", [':tid' => $tenantId]);

        $this->render('bills.form', [
            'pageTitle'       => 'New Purchase Bill',
            'bill'            => null,
            'vendors'         => $vendors,
            'items'           => $items,
            'taxCodes'        => $taxCodes,
            'expenseAccounts' => $accounts,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $contactId  = (int)$this->input('contact_id');
        $billNumber = (string)$this->input('bill_number');
        $billDate   = (string)$this->input('bill_date');
        $dueDate    = (string)$this->input('due_date');
        $notes      = (string)$this->input('notes', '');

        $descs      = $_POST['description'] ?? [];
        $qtys       = $_POST['quantity'] ?? [];
        $prices     = $_POST['unit_price'] ?? [];
        $taxCodeIds = $_POST['tax_code_id'] ?? [];
        $accountIds = $_POST['account_id'] ?? [];

        if (empty($descs)) {
            $this->flash('error', 'Please provide at least one line item.');
            $this->redirect('/app/bills/create');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $lines = [];

        $allTaxes = $db->select("SELECT id, rate FROM tax_codes WHERE tenant_id = :tid", [':tid' => $tenantId]);
        $taxMap = [];
        foreach ($allTaxes as $t) {
            $taxMap[(int)$t['id']] = (float)$t['rate'];
        }

        $defExpAcc = $db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '6000'", [':tid' => $tenantId])['id'] ?? 16;

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
                'description'      => $desc,
                'quantity'         => $qty,
                'unit_price'       => $price,
                'discount_percent' => 0.0,
                'tax_code_id'      => $tId,
                'line_total'       => $lineTotal,
                'account_id'       => !empty($accountIds[$i]) ? (int)$accountIds[$i] : $defExpAcc,
            ];
        }

        $total = $subtotal + $taxTotal;

        $fy = $db->selectOne("SELECT id FROM fiscal_years WHERE tenant_id = :tid AND is_closed = 0 ORDER BY id DESC LIMIT 1", [':tid' => $tenantId]);
        $fyId = $fy ? (int)$fy['id'] : 1;

        $db->beginTransaction();
        try {
            $billId = (int)$db->insert(
                "INSERT INTO bills (uuid, tenant_id, fiscal_year_id, contact_id, bill_number, bill_date, due_date, status, subtotal, tax_amount, discount_amount, total, amount_paid, amount_due, notes, currency, exchange_rate, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :cid, :num, :bdate, :ddate, 'received', :sub, :tax, 0.00, :tot, 0.00, :tot, :notes, 'MYR', 1.0000, :uid, NOW(), NOW())",
                [
                    ':uuid'  => generate_uuid(),
                    ':tid'   => $tenantId,
                    ':fy'    => $fyId,
                    ':cid'   => $contactId,
                    ':num'   => $billNumber,
                    ':bdate' => $billDate ?: date('Y-m-d'),
                    ':ddate' => $dueDate ?: date('Y-m-d', strtotime('+30 days')),
                    ':sub'   => $subtotal,
                    ':tax'   => $taxTotal,
                    ':tot'   => $total,
                    ':notes' => $notes,
                    ':uid'   => Auth::id(),
                ]
            );

            foreach ($lines as $line) {
                $db->insert(
                    "INSERT INTO bill_lines (bill_id, description, quantity, unit_price, discount_percent, tax_code_id, line_total, account_id, created_at)
                     VALUES (:bid, :desc, :qty, :price, :disc, :tax, :tot, :acc, NOW())",
                    [
                        ':bid'   => $billId,
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

            // Post AP Journal Entry automatically: Dr Expense lines, Cr AP
            $apAccId = (int)($db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '2000'", [':tid' => $tenantId])['id'] ?? 7);

            $jeId = (int)$db->insert(
                "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, source_id, is_posted, is_void, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'bill', :sid, 1, 0, :uid, NOW(), NOW())",
                [
                    ':uuid' => generate_uuid(),
                    ':tid'  => $tenantId,
                    ':fy'   => $fyId,
                    ':ed'   => $billDate ?: date('Y-m-d'),
                    ':ref'  => $billNumber,
                    ':desc' => 'Bill ' . $billNumber . ' from vendor',
                    ':sid'  => $billId,
                    ':uid'  => Auth::id(),
                ]
            );

            // Dr Expense lines
            foreach ($lines as $line) {
                $db->insert(
                    "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                     VALUES (:je, :aid, :desc, :deb, 0, NOW())",
                    [
                        ':je'   => $jeId,
                        ':aid'  => $line['account_id'],
                        ':desc' => $line['description'],
                        ':deb'  => $line['line_total'],
                    ]
                );
            }

            // Cr Accounts Payable
            $db->insert(
                "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                 VALUES (:je, :aid, :desc, 0, :cred, NOW())",
                [
                    ':je'   => $jeId,
                    ':aid'  => $apAccId,
                    ':desc' => 'Accounts Payable for ' . $billNumber,
                    ':cred' => $total,
                ]
            );

            $db->commit();
            $this->flash('success', "Bill {$billNumber} recorded and posted to ledger.");
            $this->redirect('/app/bills/' . $billId);
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Error creating bill: ' . $e->getMessage());
            $this->redirect('/app/bills/create');
        }
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bill = $db->selectOne(
            "SELECT b.*, c.name AS vendor_name, c.email AS vendor_email, c.phone AS vendor_phone, c.address AS vendor_address, c.tax_number AS vendor_tax
             FROM bills b
             JOIN contacts c ON b.contact_id = c.id
             WHERE b.id = :id AND b.tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );

        if (!$bill) {
            $this->flash('error', 'Bill not found.');
            $this->redirect('/app/bills');
        }

        $lines = $db->select("SELECT * FROM bill_lines WHERE bill_id = :id ORDER BY id ASC", [':id' => $id]);
        $payments = $db->select(
            "SELECT bp.*, ba.name AS bank_name 
             FROM bill_payments bp
             JOIN bank_accounts ba ON bp.bank_account_id = ba.id
             WHERE bp.bill_id = :id AND bp.tenant_id = :tid
             ORDER BY bp.payment_date DESC",
            [':id' => $id, ':tid' => $tenantId]
        );

        $this->render('bills.show', [
            'pageTitle' => 'Bill ' . $bill['bill_number'],
            'bill'      => $bill,
            'lines'     => $lines,
            'payments'  => $payments,
        ], 'main');
    }

    public function pay(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bill = $db->selectOne("SELECT * FROM bills WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$bill || $bill['amount_due'] <= 0) {
            $this->flash('error', 'Bill has no outstanding amount.');
            $this->redirect('/app/bills/' . $id);
        }

        $banks = $db->select("SELECT * FROM bank_accounts WHERE tenant_id = :tid AND is_active = 1", [':tid' => $tenantId]);

        $this->render('bills.pay', [
            'pageTitle' => 'Pay Bill ' . $bill['bill_number'],
            'bill'      => $bill,
            'banks'     => $banks,
        ], 'main');
    }

    public function recordPayment(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bill = $db->selectOne("SELECT * FROM bills WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$bill) {
            $this->flash('error', 'Bill not found.');
            $this->redirect('/app/bills');
        }

        $amount    = (float)$this->input('amount');
        $bankAccId = (int)$this->input('bank_account_id');
        $payDate   = (string)$this->input('payment_date', date('Y-m-d'));
        $ref       = (string)$this->input('reference', '');
        $notes     = (string)$this->input('notes', '');

        if ($amount <= 0 || $amount > (float)$bill['amount_due']) {
            $this->flash('error', 'Payment amount must be between 0.01 and ' . $bill['amount_due']);
            $this->redirect('/app/bills/' . $id . '/pay');
        }

        $bank = $db->selectOne("SELECT * FROM bank_accounts WHERE id = :bid AND tenant_id = :tid", [':bid' => $bankAccId, ':tid' => $tenantId]);
        if (!$bank) {
            $this->flash('error', 'Select a valid bank account.');
            $this->redirect('/app/bills/' . $id . '/pay');
        }

        $db->beginTransaction();
        try {
            $newPaid = (float)$bill['amount_paid'] + $amount;
            $newDue  = max(0.0, (float)$bill['amount_due'] - $amount);
            $newStatus = ($newDue <= 0.0001) ? 'paid' : 'partial';

            $apAccId = (int)($db->selectOne("SELECT id FROM chart_of_accounts WHERE tenant_id = :tid AND code = '2000'", [':tid' => $tenantId])['id'] ?? 7);

            // Journal Entry for Bill Payment: Dr AP, Cr Bank
            $jeId = (int)$db->insert(
                "INSERT INTO journal_entries (uuid, tenant_id, fiscal_year_id, entry_date, reference, description, source, source_id, is_posted, is_void, created_by, created_at, updated_at)
                 VALUES (:uuid, :tid, :fy, :ed, :ref, :desc, 'payment', :sid, 1, 0, :uid, NOW(), NOW())",
                [
                    ':uuid' => generate_uuid(),
                    ':tid'  => $tenantId,
                    ':fy'   => $bill['fiscal_year_id'],
                    ':ed'   => $payDate,
                    ':ref'  => $ref ?: ('PAY-' . $bill['bill_number']),
                    ':desc' => 'Payment for Bill ' . $bill['bill_number'],
                    ':sid'  => $id,
                    ':uid'  => Auth::id(),
                ]
            );

            // Dr Accounts Payable
            $db->insert(
                "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                 VALUES (:je, :aid, :desc, :deb, 0, NOW())",
                [
                    ':je'   => $jeId,
                    ':aid'  => $apAccId,
                    ':desc' => 'Clear AP for ' . $bill['bill_number'],
                    ':deb'  => $amount,
                ]
            );

            // Cr Bank
            $db->insert(
                "INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit, created_at)
                 VALUES (:je, :aid, :desc, 0, :cred, NOW())",
                [
                    ':je'   => $jeId,
                    ':aid'  => $bank['account_id'],
                    ':desc' => 'Disbursement via ' . $bank['name'],
                    ':cred' => $amount,
                ]
            );

            // Insert bill payment record
            $db->insert(
                "INSERT INTO bill_payments (uuid, tenant_id, bill_id, bank_account_id, payment_date, amount, reference, notes, journal_entry_id, created_at)
                 VALUES (:uuid, :tid, :bid, :baid, :pdate, :amt, :ref, :notes, :je, NOW())",
                [
                    ':uuid'  => generate_uuid(),
                    ':tid'   => $tenantId,
                    ':bid'   => $id,
                    ':baid'  => $bankAccId,
                    ':pdate' => $payDate,
                    ':amt'   => $amount,
                    ':ref'   => $ref,
                    ':notes' => $notes,
                    ':je'    => $jeId,
                ]
            );

            $db->update(
                "UPDATE bills SET amount_paid = :paid, amount_due = :due, status = :status, updated_at = NOW() WHERE id = :id",
                [':paid' => $newPaid, ':due' => $newDue, ':status' => $newStatus, ':id' => $id]
            );

            $db->commit();
            $this->flash('success', 'Disbursement of ' . money($amount) . ' recorded.');
            $this->redirect('/app/bills/' . $id);
        } catch (\Throwable $e) {
            $db->rollback();
            $this->flash('error', 'Payment recording failed: ' . $e->getMessage());
            $this->redirect('/app/bills/' . $id . '/pay');
        }
    }

    public function void(string $id): void
    {
        $this->requireRole(['owner', 'admin']);
        $this->validateCSRF();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $bill = $db->selectOne("SELECT * FROM bills WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if ($bill && (float)$bill['amount_paid'] == 0) {
            $db->update("UPDATE bills SET status = 'void', updated_at = NOW() WHERE id = :id", [':id' => $id]);
            $this->flash('warning', 'Bill has been voided.');
        } else {
            $this->flash('error', 'Cannot void a bill that has payments recorded.');
        }

        $this->redirect('/app/bills/' . $id);
    }
}
