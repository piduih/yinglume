<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Bill Model
 * Accounts Payable bills — mirror of Invoice for the payable side.
 */
class Bill extends BaseModel
{
    protected static string $table      = 'bills';
    protected static string $primaryKey = 'id';

    private const TRANSITIONS = [
        'draft'    => ['received', 'void'],
        'received' => ['partial', 'paid', 'void'],
        'partial'  => ['paid', 'void'],
        'paid'     => [],
        'void'     => [],
    ];

    // -------------------------------------------------------------------------
    // Complex write operations
    // -------------------------------------------------------------------------

    /**
     * Create a full bill: header + lines inside a transaction.
     */
    public function createFull(array $billData, array $lines): int
    {
        $totals = $this->calculateTotals($lines);
        $db     = $this->db();
        $db->beginTransaction();

        try {
            $billNo = $this->generateBillNumber();

            $db->execute(
                "INSERT INTO bills
                 (tenant_id, contact_id, bill_number, vendor_ref, bill_date, due_date,
                  subtotal, tax_amount, discount_amount, total_amount, amount_paid,
                  ap_account_id, notes, terms, status, created_by, created_at, updated_at)
                 VALUES
                 (:tid, :cid, :no, :vref, :bdate, :ddate,
                  :sub, :tax, :disc, :total, 0.0000,
                  :ap, :notes, :terms, 'draft', :by, NOW(), NOW())",
                [
                    ':tid'   => $this->tenantId,
                    ':cid'   => $billData['contact_id'],
                    ':no'    => $billNo,
                    ':vref'  => $billData['vendor_ref']   ?? null,
                    ':bdate' => $billData['bill_date'],
                    ':ddate' => $billData['due_date'],
                    ':sub'   => $totals['subtotal'],
                    ':tax'   => $totals['tax_amount'],
                    ':disc'  => $totals['discount_amount'],
                    ':total' => $totals['total'],
                    ':ap'    => $billData['ap_account_id'] ?? null,
                    ':notes' => $billData['notes']         ?? null,
                    ':terms' => $billData['terms']         ?? null,
                    ':by'    => $billData['created_by']    ?? null,
                ]
            );

            $billId = (int) $db->lastInsertId();
            $this->saveLines($db, $billId, $lines);

            $db->commit();
            return $billId;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Calculate bill totals from line items.
     */
    public function calculateTotals(array $lines): array
    {
        $subtotal  = 0.0;
        $taxTotal  = 0.0;
        $discTotal = 0.0;

        foreach ($lines as $line) {
            $qty      = (float) ($line['qty']        ?? 0);
            $price    = (float) ($line['unit_price']  ?? 0);
            $discPct  = (float) ($line['discount_pct'] ?? 0);
            $taxRate  = (float) ($line['tax_rate']    ?? 0);

            $lineTotal  = round($qty * $price, 4);
            $discAmount = round($lineTotal * ($discPct / 100), 4);
            $afterDisc  = $lineTotal - $discAmount;
            $taxAmount  = round($afterDisc * ($taxRate / 100), 4);

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
     * Fetch a bill with its lines, payments, and vendor info.
     */
    public function getWithLines(int $id): ?array
    {
        $bill = $this->db()->fetchOne(
            "SELECT b.*, c.name AS contact_name, c.email AS contact_email
             FROM bills b
             JOIN contacts c ON c.id = b.contact_id
             WHERE b.id = :id AND b.tenant_id = :tid",
            [':id' => $id, ':tid' => $this->tenantId]
        );

        if (!$bill) {
            return null;
        }

        $bill['lines'] = $this->db()->fetchAll(
            "SELECT bl.*, it.name AS item_name, tc.name AS tax_name
             FROM bill_lines bl
             LEFT JOIN items it ON it.id = bl.item_id
             LEFT JOIN tax_codes tc ON tc.id = bl.tax_code_id
             WHERE bl.bill_id = :id
             ORDER BY bl.sort_order, bl.id",
            [':id' => $id]
        );

        $bill['payments'] = $this->db()->fetchAll(
            "SELECT bp.*, ba.name AS bank_account_name
             FROM bill_payments bp
             LEFT JOIN bank_accounts ba ON ba.id = bp.bank_account_id
             WHERE bp.bill_id = :id
             ORDER BY bp.payment_date",
            [':id' => $id]
        );

        return $bill;
    }

    /**
     * Record a payment against a bill.
     * Journal entry: Dr AP, Cr Bank.
     */
    public function recordPayment(int $billId, array $paymentData): int
    {
        $bill = $this->getWithLines($billId);
        if (!$bill) {
            throw new \RuntimeException("Bill #{$billId} not found.");
        }

        $amountDue = round((float) $bill['total_amount'] - (float) $bill['amount_paid'], 4);
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
            $db->execute(
                "INSERT INTO bill_payments
                 (tenant_id, bill_id, amount, payment_date,
                  payment_method, bank_account_id, reference, notes, created_at)
                 VALUES (:tid, :bid, :amt, :pdate, :method, :bank, :ref, :notes, NOW())",
                [
                    ':tid'    => $this->tenantId,
                    ':bid'    => $billId,
                    ':amt'    => $payment,
                    ':pdate'  => $paymentData['payment_date'],
                    ':method' => $paymentData['payment_method']  ?? 'bank_transfer',
                    ':bank'   => $paymentData['bank_account_id'] ?? null,
                    ':ref'    => $paymentData['reference']       ?? null,
                    ':notes'  => $paymentData['notes']           ?? null,
                ]
            );

            $newPaid   = round((float) $bill['amount_paid'] + $payment, 4);
            $newStatus = ($newPaid >= (float) $bill['total_amount'] - 0.0001) ? 'paid' : 'partial';

            $db->execute(
                "UPDATE bills SET amount_paid = :paid, status = :status, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid",
                [':paid' => $newPaid, ':status' => $newStatus, ':id' => $billId, ':tid' => $this->tenantId]
            );

            // Journal: Dr AP, Cr Bank
            $bankGLAccountId = $paymentData['bank_gl_account_id']
                ?? $this->getBankAccountGLAccount((int) ($paymentData['bank_account_id'] ?? 0));

            $jeModel = new JournalEntry();
            $jeId    = $jeModel->createWithLines(
                [
                    'entry_date'  => $paymentData['payment_date'],
                    'reference'   => 'BILL-PAY-' . $bill['bill_number'],
                    'description' => 'Payment for Bill ' . $bill['bill_number'],
                    'source_type' => 'bill_payment',
                    'source_id'   => $billId,
                    'status'      => 'posted',
                    'created_by'  => $paymentData['created_by'] ?? null,
                ],
                [
                    [
                        'account_id'    => $bill['ap_account_id'],
                        'debit_amount'  => $payment,
                        'credit_amount' => 0,
                        'description'   => 'AP cleared - Bill ' . $bill['bill_number'],
                    ],
                    [
                        'account_id'    => $bankGLAccountId,
                        'debit_amount'  => 0,
                        'credit_amount' => $payment,
                        'description'   => 'Payment - Bill ' . $bill['bill_number'],
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
     * Void a bill and its associated journal entry.
     */
    public function void(int $id, string $reason = ''): bool
    {
        $bill = $this->find($id);
        if (!$bill) {
            throw new \RuntimeException("Bill #{$id} not found.");
        }

        $allowed = self::TRANSITIONS[$bill['status']] ?? [];
        if (!in_array('void', $allowed, true)) {
            throw new \RuntimeException("Cannot void bill in status: {$bill['status']}.");
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $db->execute(
                "UPDATE bills SET status = 'void', void_reason = :reason, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid",
                [':reason' => $reason, ':id' => $id, ':tid' => $this->tenantId]
            );

            if (!empty($bill['journal_entry_id'])) {
                $jeModel = new JournalEntry();
                $jeModel->void((int) $bill['journal_entry_id'], 'Bill voided: ' . $reason);
            }

            $db->commit();
            return true;

        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * AP Aging report.
     */
    public function getAgingReport(int $tenantId, string $asOf): array
    {
        return $this->db()->fetchAll(
            "SELECT
                c.id AS contact_id,
                c.name AS vendor_name,
                SUM(CASE WHEN DATEDIFF(:asof, b.due_date) <= 0  THEN b.total_amount - b.amount_paid ELSE 0 END) AS current_amount,
                SUM(CASE WHEN DATEDIFF(:asof, b.due_date) BETWEEN 1  AND 30 THEN b.total_amount - b.amount_paid ELSE 0 END) AS days_1_30,
                SUM(CASE WHEN DATEDIFF(:asof, b.due_date) BETWEEN 31 AND 60 THEN b.total_amount - b.amount_paid ELSE 0 END) AS days_31_60,
                SUM(CASE WHEN DATEDIFF(:asof, b.due_date) BETWEEN 61 AND 90 THEN b.total_amount - b.amount_paid ELSE 0 END) AS days_61_90,
                SUM(CASE WHEN DATEDIFF(:asof, b.due_date) > 90             THEN b.total_amount - b.amount_paid ELSE 0 END) AS days_over_90,
                SUM(b.total_amount - b.amount_paid) AS total_outstanding
             FROM bills b
             JOIN contacts c ON c.id = b.contact_id
             WHERE b.tenant_id = :tid
               AND b.status IN ('received', 'partial')
               AND b.bill_date <= :asof
             GROUP BY c.id, c.name
             HAVING total_outstanding > 0
             ORDER BY total_outstanding DESC",
            [':tid' => $tenantId, ':asof' => $asOf]
        );
    }

    /**
     * AP stats for dashboard.
     */
    public function getStats(int $tenantId): array
    {
        return $this->db()->fetchOne(
            "SELECT
                COUNT(*) AS total_bills,
                SUM(CASE WHEN status IN ('received','partial') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status IN ('received','partial') THEN total_amount - amount_paid ELSE 0 END) AS total_outstanding,
                SUM(CASE WHEN status IN ('received','partial') AND due_date < CURDATE() THEN total_amount - amount_paid ELSE 0 END) AS total_overdue
             FROM bills WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        ) ?: [];
    }

    /**
     * Paginated bill list with filters.
     */
    public function getList(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['b.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if (!empty($filters['status'])) {
            $where[]           = 'b.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['contact_id'])) {
            $where[]           = 'b.contact_id = :cid';
            $params[':cid']    = $filters['contact_id'];
        }
        if (!empty($filters['start_date'])) {
            $where[]           = 'b.bill_date >= :start';
            $params[':start']  = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]           = 'b.bill_date <= :end';
            $params[':end']    = $filters['end_date'];
        }
        if (!empty($filters['search'])) {
            $where[]           = '(b.bill_number LIKE :srch OR c.name LIKE :srch)';
            $params[':srch']   = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int) ($this->db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM bills b JOIN contacts c ON c.id = b.contact_id WHERE {$whereStr}",
            $params
        )['cnt'] ?? 0);

        $params[':lim'] = $perPage;
        $params[':off'] = $offset;

        $data = $this->db()->fetchAll(
            "SELECT b.*, c.name AS contact_name,
                    (b.total_amount - b.amount_paid) AS amount_due
             FROM bills b
             JOIN contacts c ON c.id = b.contact_id
             WHERE {$whereStr}
             ORDER BY b.bill_date DESC, b.id DESC
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
    // Helpers
    // -------------------------------------------------------------------------

    private function saveLines(Database $db, int $billId, array $lines): void
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
                "INSERT INTO bill_lines
                 (bill_id, tenant_id, item_id, description, qty, unit_price,
                  discount_pct, discount_amount, tax_code_id, tax_rate, tax_amount,
                  line_total, sort_order, created_at)
                 VALUES
                 (:bid, :tid, :item, :desc, :qty, :price,
                  :discpct, :discamt, :tax_code, :taxrate, :taxamt,
                  :total, :sort, NOW())",
                [
                    ':bid'     => $billId,
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

    private function generateBillNumber(): string
    {
        $row = $this->db()->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(bill_number, 6) AS UNSIGNED)) AS last_num
             FROM bills WHERE tenant_id = :tid AND bill_number LIKE 'BILL-%'",
            [':tid' => $this->tenantId]
        );
        $next = (int) ($row['last_num'] ?? 0) + 1;
        return 'BILL-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function getBankAccountGLAccount(int $bankAccountId): ?int
    {
        if ($bankAccountId <= 0) return null;
        $row = $this->db()->fetchOne(
            "SELECT gl_account_id FROM bank_accounts WHERE id = :id",
            [':id' => $bankAccountId]
        );
        return $row ? (int) $row['gl_account_id'] : null;
    }
}
