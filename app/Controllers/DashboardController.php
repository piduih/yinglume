<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

/**
 * DashboardController
 * Executive dashboard: KPIs, cashflow chart, recent activity, quick actions.
 */
class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $startOfMonth = date('Y-m-01');
        $endOfMonth   = date('Y-m-t');

        // 1. Total Revenue MTD (from posted journal entries on Revenue accounts)
        $revRow = $db->selectOne(
            "SELECT COALESCE(SUM(jl.credit - jl.debit), 0) AS mtd_rev
             FROM journal_lines jl
             JOIN journal_entries je ON jl.journal_entry_id = je.id
             JOIN chart_of_accounts ca ON jl.account_id = ca.id
             JOIN account_types at ON ca.account_type_id = at.id
             WHERE je.tenant_id = :tid 
               AND je.is_posted = 1 
               AND je.is_void = 0 
               AND at.name = 'Revenue'
               AND je.entry_date BETWEEN :start AND :end",
            [':tid' => $tenantId, ':start' => $startOfMonth, ':end' => $endOfMonth]
        );
        $revenueMtd = (float)($revRow['mtd_rev'] ?? 0);

        // 2. Total Expense MTD
        $expRow = $db->selectOne(
            "SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS mtd_exp
             FROM journal_lines jl
             JOIN journal_entries je ON jl.journal_entry_id = je.id
             JOIN chart_of_accounts ca ON jl.account_id = ca.id
             JOIN account_types at ON ca.account_type_id = at.id
             WHERE je.tenant_id = :tid 
               AND je.is_posted = 1 
               AND je.is_void = 0 
               AND at.name = 'Expenses'
               AND je.entry_date BETWEEN :start AND :end",
            [':tid' => $tenantId, ':start' => $startOfMonth, ':end' => $endOfMonth]
        );
        $expenseMtd = (float)($expRow['mtd_exp'] ?? 0);

        // 3. AR Outstanding (total amount_due on sent/partial/overdue invoices)
        $arRow = $db->selectOne(
            "SELECT COALESCE(SUM(amount_due), 0) AS total_ar
             FROM invoices 
             WHERE tenant_id = :tid AND status IN ('sent', 'partial', 'overdue')",
            [':tid' => $tenantId]
        );
        $totalAr = (float)($arRow['total_ar'] ?? 0);

        // 4. Cash & Bank Balance
        $cashRow = $db->selectOne(
            "SELECT COALESCE(SUM(ca.opening_balance + COALESCE(jl_agg.net_debit, 0)), 0) AS total_cash
             FROM chart_of_accounts ca
             JOIN account_types at ON ca.account_type_id = at.id
             LEFT JOIN (
                 SELECT jl.account_id, SUM(jl.debit - jl.credit) AS net_debit
                 FROM journal_lines jl
                 JOIN journal_entries je ON jl.journal_entry_id = je.id
                 WHERE je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0
                 GROUP BY jl.account_id
             ) jl_agg ON ca.id = jl_agg.account_id
             WHERE ca.tenant_id = :tid AND (ca.code LIKE '100%' OR ca.code LIKE '101%' OR ca.code LIKE '102%')",
            [':tid' => $tenantId]
        );
        $totalCash = (float)($cashRow['total_cash'] ?? 0);

        // 5. Recent Invoices
        $recentInvoices = $db->select(
            "SELECT i.*, c.name AS customer_name 
             FROM invoices i
             LEFT JOIN contacts c ON i.contact_id = c.id
             WHERE i.tenant_id = :tid
             ORDER BY i.id DESC LIMIT 5",
            [':tid' => $tenantId]
        );

        // 6. Recent Bills
        $recentBills = $db->select(
            "SELECT b.*, c.name AS vendor_name 
             FROM bills b
             LEFT JOIN contacts c ON b.contact_id = c.id
             WHERE b.tenant_id = :tid
             ORDER BY b.id DESC LIMIT 5",
            [':tid' => $tenantId]
        );

        // 7. 6-Month Chart Data (Revenue vs Expenses)
        $months = [];
        $revData = [];
        $expData = [];

        for ($i = 5; $i >= 0; $i--) {
            $mStart = date('Y-m-01', strtotime("-$i months"));
            $mEnd   = date('Y-m-t', strtotime("-$i months"));
            $label  = date('M Y', strtotime("-$i months"));
            $months[] = $label;

            $mRev = $db->selectOne(
                "SELECT COALESCE(SUM(jl.credit - jl.debit), 0) AS val
                 FROM journal_lines jl
                 JOIN journal_entries je ON jl.journal_entry_id = je.id
                 JOIN chart_of_accounts ca ON jl.account_id = ca.id
                 JOIN account_types at ON ca.account_type_id = at.id
                 WHERE je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0 
                   AND at.name = 'Revenue' AND je.entry_date BETWEEN :s AND :e",
                [':tid' => $tenantId, ':s' => $mStart, ':e' => $mEnd]
            )['val'] ?? 0;

            $mExp = $db->selectOne(
                "SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS val
                 FROM journal_lines jl
                 JOIN journal_entries je ON jl.journal_entry_id = je.id
                 JOIN chart_of_accounts ca ON jl.account_id = ca.id
                 JOIN account_types at ON ca.account_type_id = at.id
                 WHERE je.tenant_id = :tid AND je.is_posted = 1 AND je.is_void = 0 
                   AND at.name = 'Expenses' AND je.entry_date BETWEEN :s AND :e",
                [':tid' => $tenantId, ':s' => $mStart, ':e' => $mEnd]
            )['val'] ?? 0;

            $revData[] = round((float)$mRev, 2);
            $expData[] = round((float)$mExp, 2);
        }

        $chartData = [
            'labels'   => $months,
            'revenues' => $revData,
            'expenses' => $expData,
        ];

        $this->render('dashboard.index', [
            'pageTitle'      => 'Financial Dashboard',
            'revenueMtd'     => $revenueMtd,
            'expenseMtd'     => $expenseMtd,
            'totalAr'        => $totalAr,
            'totalCash'      => $totalCash,
            'recentInvoices' => $recentInvoices,
            'recentBills'    => $recentBills,
            'chartData'      => $chartData,
        ], 'main');
    }
}
