<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Report;

/**
 * ReportController
 * Financial Statements: P&L, Balance Sheet, Trial Balance, Cash Flow, AR/AP Aging, General Ledger.
 */
class ReportController extends BaseController
{
    private Report $reportModel;

    public function __construct()
    {
        parent::__construct();
        $this->reportModel = new Report();
    }

    public function profitLoss(): void
    {
        $this->requireAuth();
        $startDate = (string)$this->input('start_date', date('Y-01-01'));
        $endDate   = (string)$this->input('end_date', date('Y-m-d'));

        $report = $this->reportModel->getProfitLoss($startDate, $endDate);

        $this->render('reports.profit-loss', [
            'pageTitle' => 'Profit & Loss Statement',
            'report'    => $report,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ], 'main');
    }

    public function balanceSheet(): void
    {
        $this->requireAuth();
        $asOfDate = (string)$this->input('as_of', date('Y-m-d'));

        $report = $this->reportModel->getBalanceSheet($asOfDate);

        $this->render('reports.balance-sheet', [
            'pageTitle' => 'Balance Sheet',
            'report'    => $report,
            'asOfDate'  => $asOfDate,
        ], 'main');
    }

    public function trialBalance(): void
    {
        $this->requireAuth();
        $startDate = (string)$this->input('start_date', date('Y-01-01'));
        $endDate   = (string)$this->input('end_date', date('Y-m-d'));

        $report = $this->reportModel->getTrialBalance($startDate, $endDate);

        $this->render('reports.trial-balance', [
            'pageTitle' => 'Trial Balance',
            'report'    => $report,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ], 'main');
    }

    public function cashFlow(): void
    {
        $this->requireAuth();
        $startDate = (string)$this->input('start_date', date('Y-01-01'));
        $endDate   = (string)$this->input('end_date', date('Y-m-d'));

        $report = $this->reportModel->getCashFlow($startDate, $endDate);

        $this->render('reports.cash-flow', [
            'pageTitle' => 'Statement of Cash Flows',
            'report'    => $report,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ], 'main');
    }

    public function arAging(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();
        $asOf = (string)$this->input('as_of', date('Y-m-d'));

        $sql = "SELECT 
                    c.name AS customer_name,
                    c.payment_terms,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.total,
                    i.amount_due,
                    DATEDIFF(:as_of, i.due_date) AS days_overdue
                FROM invoices i
                JOIN contacts c ON i.contact_id = c.id
                WHERE i.tenant_id = :tid AND i.status IN ('sent', 'partial', 'overdue') AND i.invoice_date <= :as_of
                ORDER BY c.name ASC, i.due_date ASC";

        $rows = $db->select($sql, [':tid' => $tenantId, ':as_of' => $asOf]);

        // Bucket by customer
        $customers = [];
        $grandTotals = ['current' => 0.0, 'b1_30' => 0.0, 'b31_60' => 0.0, 'b61_90' => 0.0, 'b90plus' => 0.0, 'total' => 0.0];

        foreach ($rows as $r) {
            $cName = $r['customer_name'];
            if (!isset($customers[$cName])) {
                $customers[$cName] = ['current' => 0.0, 'b1_30' => 0.0, 'b31_60' => 0.0, 'b61_90' => 0.0, 'b90plus' => 0.0, 'total' => 0.0];
            }

            $due = (float)$r['amount_due'];
            $days = (int)$r['days_overdue'];

            if ($days <= 0) {
                $customers[$cName]['current'] += $due;
                $grandTotals['current'] += $due;
            } elseif ($days <= 30) {
                $customers[$cName]['b1_30'] += $due;
                $grandTotals['b1_30'] += $due;
            } elseif ($days <= 60) {
                $customers[$cName]['b31_60'] += $due;
                $grandTotals['b31_60'] += $due;
            } elseif ($days <= 90) {
                $customers[$cName]['b61_90'] += $due;
                $grandTotals['b61_90'] += $due;
            } else {
                $customers[$cName]['b90plus'] += $due;
                $grandTotals['b90plus'] += $due;
            }

            $customers[$cName]['total'] += $due;
            $grandTotals['total'] += $due;
        }

        $this->render('reports.ar-aging', [
            'pageTitle'   => 'Accounts Receivable Aging Report',
            'customers'   => $customers,
            'grandTotals' => $grandTotals,
            'asOf'        => $asOf,
        ], 'main');
    }

    public function apAging(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();
        $asOf = (string)$this->input('as_of', date('Y-m-d'));

        $sql = "SELECT 
                    c.name AS vendor_name,
                    b.bill_number,
                    b.bill_date,
                    b.due_date,
                    b.total,
                    b.amount_due,
                    DATEDIFF(:as_of, b.due_date) AS days_overdue
                FROM bills b
                JOIN contacts c ON b.contact_id = c.id
                WHERE b.tenant_id = :tid AND b.status IN ('received', 'partial', 'overdue') AND b.bill_date <= :as_of
                ORDER BY c.name ASC, b.due_date ASC";

        $rows = $db->select($sql, [':tid' => $tenantId, ':as_of' => $asOf]);

        $vendors = [];
        $grandTotals = ['current' => 0.0, 'b1_30' => 0.0, 'b31_60' => 0.0, 'b61_90' => 0.0, 'b90plus' => 0.0, 'total' => 0.0];

        foreach ($rows as $r) {
            $vName = $r['vendor_name'];
            if (!isset($vendors[$vName])) {
                $vendors[$vName] = ['current' => 0.0, 'b1_30' => 0.0, 'b31_60' => 0.0, 'b61_90' => 0.0, 'b90plus' => 0.0, 'total' => 0.0];
            }

            $due = (float)$r['amount_due'];
            $days = (int)$r['days_overdue'];

            if ($days <= 0) {
                $vendors[$vName]['current'] += $due;
                $grandTotals['current'] += $due;
            } elseif ($days <= 30) {
                $vendors[$vName]['b1_30'] += $due;
                $grandTotals['b1_30'] += $due;
            } elseif ($days <= 60) {
                $vendors[$vName]['b31_60'] += $due;
                $grandTotals['b31_60'] += $due;
            } elseif ($days <= 90) {
                $vendors[$vName]['b61_90'] += $due;
                $grandTotals['b61_90'] += $due;
            } else {
                $vendors[$vName]['b90plus'] += $due;
                $grandTotals['b90plus'] += $due;
            }

            $vendors[$vName]['total'] += $due;
            $grandTotals['total'] += $due;
        }

        $this->render('reports.ap-aging', [
            'pageTitle'   => 'Accounts Payable Aging Report',
            'vendors'     => $vendors,
            'grandTotals' => $grandTotals,
            'asOf'        => $asOf,
        ], 'main');
    }

    public function generalLedger(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $accounts = $db->select("SELECT id, code, name FROM chart_of_accounts WHERE tenant_id = :tid ORDER BY code ASC", [':tid' => $tenantId]);
        $selectedAccId = (int)$this->input('account_id', $accounts[0]['id'] ?? 1);
        $startDate = (string)$this->input('start_date', date('Y-01-01'));
        $endDate   = (string)$this->input('end_date', date('Y-m-d'));

        $report = $this->reportModel->getGeneralLedger($selectedAccId, $startDate, $endDate);
        $account = $db->selectOne("SELECT * FROM chart_of_accounts WHERE id = :id AND tenant_id = :tid", [':id' => $selectedAccId, ':tid' => $tenantId]);

        $this->render('reports.ledger', [
            'pageTitle'     => 'General Ledger',
            'accounts'      => $accounts,
            'selectedAccId' => $selectedAccId,
            'account'       => $account,
            'report'        => $report,
            'startDate'     => $startDate,
            'endDate'       => $endDate,
        ], 'main');
    }
}
