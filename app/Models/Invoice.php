<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Invoice Model
 * Accounts Receivable invoices with line items and payment tracking.
 */
class Invoice extends BaseModel
{
    protected static string $table      = 'invoices';
    protected static string $primaryKey = 'id';

    /**
     * Valid invoice status transitions.
     * Key = current status; Value = allowed next statuses.
     */
    private const TRANSITIONS = [
        'draft'   => ['sent', 'void'],
        'sent'    => ['partial', 'paid', 'void'],
        'partial' => ['paid', 'void'],
        'paid'    => [],
        'void'    => [],
    ];

    // -------------------------------------------------------------------------
    // Complex write operations
    // -------------------------------------------------------------------------

    /**
     * Create a full invoice: header + lines + (optional) draft journal entry.
     * Wrapped in a transaction.
     *
     * @param array $invoiceData ['contact_id', 'invoice_date', 'due_date', 'notes', 'terms', 'ar_account_id', ...]
     * @param array $lines       [['item_id', 'description', 'qty', 'unit_price', 'discount_pct', 'tax_code_id'], ...]
     * @return int  New invoice ID
     */
    public function createFull(array $invoiceData, array $lines): int
    {
        $totals = $this->calculateTotals($lines);
        $db     = $this->db();
        $db->beginTransaction();

        try {
            // Generate invoice number
            $invoiceNo = $this->generateInvoiceNumber();

            $db->execute(
                "INSERT INTO invoices
                 (tenant_id, contact_id, invoice_number, invoice_date, due_date,
                  subtotal, tax_amount, discount_amount, total_amount, amount_paid,
                  ar_account_id, notes, terms, status, created_by, created_at, updated_at)
                 VALUES
                 (:tid, :cid, :no, :idate, :ddate,
                  :sub, :tax, :disc, :total, 0.0000,
                  :ar, :notes, :terms, 'draft', :by, NOW(), NOW())",
                [
                    ':tid'   => $this->tenantId,
                    ':cid'   => $invoiceData['contact_id'],
                    ':no'    => $invoiceNo,
                    ':idate' => $invoiceData['invoice_date'],
                    ':ddate' => $invoiceData['due_date'],
                    ':sub'   => $totals['subtotal'],
                    ':tax'   => $totals['tax_amount'],
                    ':disc'  => $totals['discount_amount'],
                    ':total' => $totals['total'],
                    ':ar'    => $invoiceData['ar_account_id'] ?? null,
                    ':notes' => $invoiceData['notes']         ?? null,
                    ':terms' => $invoiceData['terms']         ?? null,
                    ':by'    => $invoiceData['created_by']    ?? null,
                ]
            );

            $invoiceId = (int) $db->lastInsertId();
            $this->saveLines($db, $invoiceId, $lines);

            $db->commit();
            return $invoiceId;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Calculate invoice totals from line items.
     *
     * @return array{subtotal: string, tax_amount: string, discount_amount: string, total: string}
     */
    public function calculateTotals(array $lines): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $discTotal = 0.0;

        foreach ($lines as $line) {
            $qty      = (float) ($line['qty']        ?? 0);
            $price    = (float) ($line['unit_price']  ?? 0);
            $discPct  = (float) ($line['discount_pct'] ?? 0);
            $taxRate  = (float) ($line['tax_rate']    ?? 0);  // as percentage e.g. 10 = 10%

            $lineTotal   = round($qty * $price, 4);
            $discAmount  = round($lineTotal * ($discPct / 100), 4);
            $afterDisc   = $lineTotal - $discAmount;
            $taxAmount   = round($afterDisc * ($taxRate / 100), 4);

            $subtotal  += $lineTotal;
            $discTotal += $discAmount;
            $taxTotal  += $taxAmount;
        }

        return [
            'subtotal'        => number_format($subtotal,  4, '.', ''),
            'tax_amount'      => number_format($taxTotal,  4, '.', ''),
            'discount_amount' => number_format($discTotal, 4, '.', ''),
            'total'           => number_format(round($subtotal - $discTotal + $taxTotal, 4), 4, '.', ''),
        ];
    }

    /**
     * Fetch an invoice with its line items and contact info.
     */
    public function getWithLines(int $id): ?array
    {
        $invoice = $this->db()->fetchOne(
            "SELECT i.*, c.name AS contact_name, c.email AS contact_email,
                    c.address AS contact_address, c.phone AS contact_phone
             FROM invoices i
             JOIN contacts c ON c.id = i.contact_id
             WHERE i.id = :id AND i.tenant_id = :tid",
            [':id' => $id, ':tid' => $this->tenantId]
        );

        if (!$invoice) {
            return null;
        }

        $invoice['lines'] = $this->db()->fetchAll(
            "SELECT il.*, it.name AS item_name, tc.name AS tax_name
             FROM invoice_lines il
             LEFT JOIN items it ON it.id = il.item_id
             LEFT JOIN tax_codes tc ON tc.id = il.tax_code_id
             WHERE il.invoice_id = :id
             ORDER BY il.sort_order, il.id",
            [':id' => $id]
        );

        $invoice['payments'] = $this->db()->fetchAll(
            "SELECT ip.*, ba.name AS bank_account_name
             FROM invoice_payments ip
             LEFT JOIN bank_accounts ba ON ba.id = ip.bank_account_id
             WHERE ip.invoice_id = :id
             ORDER BY ip.payment_date",
            [':id' => $id]
        );

        return $invoice;
    }

    /**
     * Record a payment against an invoice.
     * Creates: invoice_payments row + journal entry (Dr Bank / Cr AR).
     *
     * @param array $paymentData ['amount', 'payment_date', 'payment_method', 'bank_account_id', 'reference', 'notes']
     */
    public function recordPayment(int $invoiceId, array $paymentData): int
    {
        $invoice = $this->getWithLines($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException("Invoice #{$invoiceId} not found.");
        }

        $amountDue = round((float) $invoice['total_amount'] - (float) $invoice['amount_paid'], 4);
        $payment   = round((float) $paymentData['amount'], 4);

        if ($payment <= 0) {
            throw new \RuntimeException('Payment amount must be greater than zero.');
        }
        if ($payment > $amountDue + 0.0001) {
            throw new \RuntimeException(
                sprintf('Payment %.4f exceeds amount due %.4f.', $payment, $amountDue)
            );
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            // Record payment row
            $db->execute(
                "INSERT INTO invoice_payments
                 (tenant_id, invoice_id, amount, payment_date,
                  payment_method, bank_account_id, reference, notes, created_at)
                 VALUES (:tid, :iid, :amt, :pdate, :method, :bank, :ref, :notes, NOW())",
                [
                    ':tid'    => $this->tenantId,
                    ':iid'    => $invoiceId,
                    ':amt'    => $payment,
                    ':pdate'  => $paymentData['payment_date'],
                    ':method' => $paymentData['payment_method'] ?? 'bank_transfer',
                    ':bank'   => $paymentData['bank_account_id'] ?? null,
                    ':ref'    => $paymentData['reference']       ?? null,
                    ':notes'  => $paymentData['notes']           ?? null,
                ]
            );

            // Update invoice amount_paid and status
            $newPaid   = round((float) $invoice['amount_paid'] + $payment, 4);
            $newStatus = ($newPaid >= (float) $invoice['total_amount'] - 0.0001) ? 'paid' : 'partial';

            $db->execute(
                "UPDATE invoices SET amount_paid = :paid, status = :status, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid",
                [':paid' => $newPaid, ':status' => $newStatus, ':id' => $invoiceId, ':tid' => $this->tenantId]
            );

            // Create journal entry: Dr Bank, Cr AR
            $jeModel = new JournalEntry();
            $jeId    = $jeModel->createWithLines(
                [
                    'entry_date'  => $paymentData['payment_date'],
                    'reference'   => 'INV-PAY-' . $invoice['invoice_number'],
                    'description' => 'Payment received for Invoice ' . $invoice['invoice_number'],
                    'source_type' => 'invoice_payment',
                    'source_id'   => $invoiceId,
                    'status'      => 'posted',
                    'created_by'  => $paymentData['created_by'] ?? null,
                ],
                [
                    [
                        'account_id'    => $paymentData['bank_account_id'] ? $this->getBankAccountGLAccount($paymentData['bank_account_id']) : ($paymentData['bank_gl_account_id'] ?? null),
                        'debit_amount'  => $payment,
                        'credit_amount' => 0,
                        'description'   => 'Receipt - Invoice ' . $invoice['invoice_number'],
                    ],
                    [
                        'account_id'    => $invoice['ar_account_id'],
                        'debit_amount'  => 0,
                        'credit_amount' => $payment,
                        'description'   => 'AR cleared - Invoice ' . $invoice['invoice_number'],
                    ],
                ]
            );

            $db->commit();
            return $jeId;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Void an invoice: reverse the draft JE, mark invoice voided.
     */
    public function void(int $id, string $reason = ''): bool
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            throw new \RuntimeException("Invoice #{$id} not found.");
        }

        $allowed = self::TRANSITIONS[$invoice['status']] ?? [];
        if (!in_array('void', $allowed, true)) {
            throw new \RuntimeException("Cannot void invoice in status: {$invoice['status']}.");
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $db->execute(
                "UPDATE invoices SET status = 'void', void_reason = :reason, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid",
                [':reason' => $reason, ':id' => $id, ':tid' => $this->tenantId]
            );

            // If there's a linked journal entry, void it
            if (!empty($invoice['journal_entry_id'])) {
                $jeModel = new JournalEntry();
                $jeModel->void((int) $invoice['journal_entry_id'], 'Invoice voided: ' . $reason);
            }

            $db->commit();
            return true;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Reports / Stats
    // -------------------------------------------------------------------------

    /**
     * AR Aging report as-of a date.
     * Returns rows grouped by customer with buckets: current, 1_30, 31_60, 61_90, over_90.
     */
    public function getAgingReport(int $tenantId, string $asOf): array
    {
        return $this->db()->fetchAll(
            "SELECT
                c.id AS contact_id,
                c.name AS customer_name,
                SUM(CASE WHEN DATEDIFF(:asof, i.due_date) <= 0  THEN i.total_amount - i.amount_paid ELSE 0 END) AS current_amount,
                SUM(CASE WHEN DATEDIFF(:asof, i.due_date) BETWEEN 1  AND 30 THEN i.total_amount - i.amount_paid ELSE 0 END) AS days_1_30,
                SUM(CASE WHEN DATEDIFF(:asof, i.due_date) BETWEEN 31 AND 60 THEN i.total_amount - i.amount_paid ELSE 0 END) AS days_31_60,
                SUM(CASE WHEN DATEDIFF(:asof, i.due_date) BETWEEN 61 AND 90 THEN i.total_amount - i.amount_paid ELSE 0 END) AS days_61_90,
                SUM(CASE WHEN DATEDIFF(:asof, i.due_date) > 90             THEN i.total_amount - i.amount_paid ELSE 0 END) AS days_over_90,
                SUM(i.total_amount - i.amount_paid) AS total_outstanding
             FROM invoices i
             JOIN contacts c ON c.id = i.contact_id
             WHERE i.tenant_id = :tid
               AND i.status IN ('sent', 'partial')
               AND i.invoice_date <= :asof
             GROUP BY c.id, c.name
             HAVING total_outstanding > 0
             ORDER BY total_outstanding DESC",
            [':tid' => $tenantId, ':asof' => $asOf]
        );
    }

    /**
     * Get stats for the dashboard.
     */
    public function getStats(int $tenantId): array
    {
        return $this->db()->fetchOne(
            "SELECT
                COUNT(*) AS total_invoices,
                SUM(CASE WHEN status IN ('sent','partial') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status IN ('sent','partial') THEN total_amount - amount_paid ELSE 0 END) AS total_outstanding,
                SUM(CASE WHEN status IN ('sent','partial') AND due_date < CURDATE() THEN total_amount - amount_paid ELSE 0 END) AS total_overdue
             FROM invoices
             WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        ) ?: [];
    }

    /**
     * Get paginated invoice list with filters.
     */
    public function getList(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['i.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if (!empty($filters['status'])) {
            $where[]           = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['contact_id'])) {
            $where[]              = 'i.contact_id = :cid';
            $params[':cid']       = $filters['contact_id'];
        }
        if (!empty($filters['start_date'])) {
            $where[]              = 'i.invoice_date >= :start';
            $params[':start']     = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]              = 'i.invoice_date <= :end';
            $params[':end']       = $filters['end_date'];
        }
        if (!empty($filters['search'])) {
            $where[]              = '(i.invoice_number LIKE :srch OR c.name LIKE :srch)';
            $params[':srch']      = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM invoices i
             JOIN contacts c ON c.id = i.contact_id
             WHERE {$whereStr}",
            $params
        )['cnt'] ?? 0);

        $params[':lim'] = $perPage;
        $params[':off'] = $offset;

        $data = $this->db()->fetchAll(
            "SELECT i.*, c.name AS contact_name,
                    (i.total_amount - i.amount_paid) AS amount_due
             FROM invoices i
             JOIN contacts c ON c.id = i.contact_id
             WHERE {$whereStr}
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT :lim OFFSET :off",
            $params
        );

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function saveLines(Database $db, int $invoiceId, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $qty       = round((float) ($line['qty']        ?? 0), 4);
            $price     = round((float) ($line['unit_price']  ?? 0), 4);
            $discPct   = round((float) ($line['discount_pct'] ?? 0), 4);
            $taxRate   = round((float) ($line['tax_rate']    ?? 0), 4);
            $lineTotal = round($qty * $price, 4);
            $discAmt   = round($lineTotal * ($discPct / 100), 4);
            $afterDisc = $lineTotal - $discAmt;
            $taxAmt    = round($afterDisc * ($taxRate / 100), 4);

            $db->execute(
                "INSERT INTO invoice_lines
                 (invoice_id, tenant_id, item_id, description, qty, unit_price,
                  discount_pct, discount_amount, tax_code_id, tax_rate, tax_amount,
                  line_total, sort_order, created_at)
                 VALUES
                 (:iid, :tid, :item, :desc, :qty, :price,
                  :discpct, :discamt, :tax_code, :taxrate, :taxamt,
                  :total, :sort, NOW())",
                [
                    ':iid'     => $invoiceId,
                    ':tid'     => $this->tenantId,
                    ':item'    => $line['item_id']     ?? null,
                    ':desc'    => $line['description'] ?? '',
                    ':qty'     => $qty,
                    ':price'   => $price,
                    ':discpct' => $discPct,
                    ':discamt' => $discAmt,
                    ':tax_code'=> $line['tax_code_id'] ?? null,
                    ':taxrate' => $taxRate,
                    ':taxamt'  => $taxAmt,
                    ':total'   => $afterDisc + $taxAmt,
                    ':sort'    => $i,
                ]
            );
        }
    }

    private function generateInvoiceNumber(): string
    {
        $row = $this->db()->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)) AS last_num
             FROM invoices WHERE tenant_id = :tid AND invoice_number LIKE 'INV-%'",
            [':tid' => $this->tenantId]
        );
        $next = (int) ($row['last_num'] ?? 0) + 1;
        return 'INV-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function getBankAccountGLAccount(int $bankAccountId): ?int
    {
        $row = $this->db()->fetchOne(
            "SELECT gl_account_id FROM bank_accounts WHERE id = :id",
            [':id' => $bankAccountId]
        );
        return $row ? (int) $row['gl_account_id'] : null;
    }
}
