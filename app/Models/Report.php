<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Report Model
 * Financial reporting engine: P&L, Balance Sheet, Trial Balance, Cash Flow, General Ledger, Aging.
 */
class Report
{
    protected int $tenantId;

    public function __construct()
    {
        $this->tenantId = Auth::tenantId();
    }

    protected function db(): Database
    {
        return Database::getInstance();
    }

    /**
     * Profit & Loss (Income Statement)
     */
    public function getProfitLoss(string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    at.name AS type_name,
                    at.normal_balance,
                    ca.id AS account_id,
                    ca.code,
                    ca.name AS account_name,
                    COALESCE(SUM(jl.debit), 0) AS total_debit,
                    COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM chart_of_accounts ca
                JOIN account_types at ON ca.account_type_id = at.id
                LEFT JOIN journal_lines jl ON ca.id = jl.account_id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id 
                    AND je.tenant_id = :tid 
                    AND je.is_posted = 1 
                    AND je.is_void = 0
                    AND je.entry_date BETWEEN :start AND :end
                WHERE ca.tenant_id = :tid AND at.financial_statement = 'income_statement'
                GROUP BY ca.id, at.name, at.normal_balance, ca.code, ca.name
                ORDER BY ca.code ASC";

        $rows = $this->db()->select($sql, [
            ':tid'   => $this->tenantId,
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $revenues = [];
        $expenses = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($rows as $r) {
            // Normal credit for revenue: credit - debit
            // Normal debit for expense: debit - credit
            if ($r['normal_balance'] === 'credit') {
                $net = (float)$r['total_credit'] - (float)$r['total_debit'];
                $r['net_amount'] = $net;
                $revenues[] = $r;
                $totalRevenue += $net;
            } else {
                $net = (float)$r['total_debit'] - (float)$r['total_credit'];
                $r['net_amount'] = $net;
                $expenses[] = $r;
                $totalExpense += $net;
            }
        }

        $netIncome = $totalRevenue - $totalExpense;

        return [
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'revenues'      => $revenues,
            'expenses'      => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income'    => $netIncome,
        ];
    }

    /**
     * Balance Sheet
     */
    public function getBalanceSheet(string $asOfDate): array
    {
        $sql = "SELECT 
                    at.name AS type_name,
                    at.normal_balance,
                    ca.id AS account_id,
                    ca.code,
                    ca.name AS account_name,
                    ca.opening_balance,
                    COALESCE(SUM(jl.debit), 0) AS total_debit,
                    COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM chart_of_accounts ca
                JOIN account_types at ON ca.account_type_id = at.id
                LEFT JOIN journal_lines jl ON ca.id = jl.account_id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id 
                    AND je.tenant_id = :tid 
                    AND je.is_posted = 1 
                    AND je.is_void = 0
                    AND je.entry_date <= :as_of
                WHERE ca.tenant_id = :tid AND at.financial_statement = 'balance_sheet'
                GROUP BY ca.id, at.name, at.normal_balance, ca.code, ca.name, ca.opening_balance
                ORDER BY ca.code ASC";

        $rows = $this->db()->select($sql, [
            ':tid'   => $this->tenantId,
            ':as_of' => $asOfDate,
        ]);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($rows as $r) {
            $opening = (float)$r['opening_balance'];
            if ($r['normal_balance'] === 'debit') {
                $balance = $opening + (float)$r['total_debit'] - (float)$r['total_credit'];
            } else {
                $balance = $opening + (float)$r['total_credit'] - (float)$r['total_debit'];
            }
            $r['balance'] = $balance;

            $typeName = strtolower($r['type_name']);
            if (str_contains($typeName, 'asset')) {
                $assets[] = $r;
                $totalAssets += $balance;
            } elseif (str_contains($typeName, 'liability')) {
                $liabilities[] = $r;
                $totalLiabilities += $balance;
            } else {
                $equity[] = $r;
                $totalEquity += $balance;
            }
        }

        // Net income to equity (retained earnings)
        $pnl = $this->getProfitLoss('1970-01-01', $asOfDate);
        $retainedEarnings = $pnl['net_income'];
        $totalEquity += $retainedEarnings;

        return [
            'as_of_date'        => $asOfDate,
            'assets'            => $assets,
            'liabilities'       => $liabilities,
            'equity'            => $equity,
            'total_assets'      => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity'      => $totalEquity,
            'retained_earnings' => $retainedEarnings,
            'total_liab_equity' => $totalLiabilities + $totalEquity,
            'is_balanced'       => round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2),
        ];
    }

    /**
     * Trial Balance
     */
    public function getTrialBalance(string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    ca.code,
                    ca.name,
                    at.normal_balance,
                    COALESCE(SUM(jl.debit), 0) AS total_debit,
                    COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM chart_of_accounts ca
                JOIN account_types at ON ca.account_type_id = at.id
                LEFT JOIN journal_lines jl ON ca.id = jl.account_id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id 
                    AND je.tenant_id = :tid 
                    AND je.is_posted = 1 
                    AND je.is_void = 0
                    AND je.entry_date BETWEEN :start AND :end
                WHERE ca.tenant_id = :tid
                GROUP BY ca.id, ca.code, ca.name, at.normal_balance
                ORDER BY ca.code ASC";

        $rows = $this->db()->select($sql, [
            ':tid'   => $this->tenantId,
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $items = [];
        $sumDebit = 0.0;
        $sumCredit = 0.0;

        foreach ($rows as $r) {
            $debit = (float)$r['total_debit'];
            $credit = (float)$r['total_credit'];
            if ($debit == 0 && $credit == 0) {
                continue;
            }
            $items[] = [
                'code'   => $r['code'],
                'name'   => $r['name'],
                'debit'  => $debit,
                'credit' => $credit,
            ];
            $sumDebit += $debit;
            $sumCredit += $credit;
        }

        return [
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'items'        => $items,
            'total_debit'  => $sumDebit,
            'total_credit' => $sumCredit,
            'is_balanced'  => round($sumDebit, 2) === round($sumCredit, 2),
        ];
    }

    /**
     * Cash Flow Statement (Direct/Simplified)
     */
    public function getCashFlow(string $startDate, string $endDate): array
    {
        // Operating: Inflows from sales payments, outflows to bill payments and expenses
        $inflowSql = "SELECT COALESCE(SUM(amount), 0) AS total FROM invoice_payments 
                      WHERE tenant_id = :tid AND payment_date BETWEEN :start AND :end";
        $inflowRow = $this->db()->selectOne($inflowSql, [':tid' => $this->tenantId, ':start' => $startDate, ':end' => $endDate]);
        $cashFromCustomers = (float)($inflowRow['total'] ?? 0);

        $outflowSql = "SELECT COALESCE(SUM(amount), 0) AS total FROM bill_payments 
                       WHERE tenant_id = :tid AND payment_date BETWEEN :start AND :end";
        $outflowRow = $this->db()->selectOne($outflowSql, [':tid' => $this->tenantId, ':start' => $startDate, ':end' => $endDate]);
        $cashToVendors = (float)($outflowRow['total'] ?? 0);

        $netOperating = $cashFromCustomers - $cashToVendors;

        return [
            'start_date'          => $startDate,
            'end_date'            => $endDate,
            'cash_from_customers' => $cashFromCustomers,
            'cash_to_vendors'     => $cashToVendors,
            'net_operating'       => $netOperating,
            'net_cash_flow'       => $netOperating,
        ];
    }

    /**
     * General Ledger
     */
    public function getGeneralLedger(int $accountId, string $startDate, string $endDate): array
    {
        // Opening balance before start date
        $openSql = "SELECT 
                        ca.opening_balance,
                        at.normal_balance,
                        COALESCE(SUM(jl.debit), 0) AS prior_debit,
                        COALESCE(SUM(jl.credit), 0) AS prior_credit
                    FROM chart_of_accounts ca
                    JOIN account_types at ON ca.account_type_id = at.id
                    LEFT JOIN journal_lines jl ON ca.id = jl.account_id
                    LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id 
                        AND je.tenant_id = :tid 
                        AND je.is_posted = 1 
                        AND je.is_void = 0
                        AND je.entry_date < :start
                    WHERE ca.id = :aid AND ca.tenant_id = :tid
                    GROUP BY ca.id, ca.opening_balance, at.normal_balance";

        $openRow = $this->db()->selectOne($openSql, [
            ':aid'   => $accountId,
            ':tid'   => $this->tenantId,
            ':start' => $startDate,
        ]);

        $normalBalance = $openRow['normal_balance'] ?? 'debit';
        $baseOpening = (float)($openRow['opening_balance'] ?? 0);
        $priorDebit = (float)($openRow['prior_debit'] ?? 0);
        $priorCredit = (float)($openRow['prior_credit'] ?? 0);

        $runningBalance = ($normalBalance === 'debit')
            ? ($baseOpening + $priorDebit - $priorCredit)
            : ($baseOpening + $priorCredit - $priorDebit);

        $openingBalance = $runningBalance;

        // Transactions in period
        $transSql = "SELECT 
                        je.entry_date,
                        je.reference,
                        je.description AS entry_description,
                        jl.description AS line_description,
                        jl.debit,
                        jl.credit
                     FROM journal_lines jl
                     JOIN journal_entries je ON jl.journal_entry_id = je.id
                     WHERE jl.account_id = :aid 
                       AND je.tenant_id = :tid 
                       AND je.is_posted = 1 
                       AND je.is_void = 0
                       AND je.entry_date BETWEEN :start AND :end
                     ORDER BY je.entry_date ASC, je.id ASC";

        $lines = $this->db()->select($transSql, [
            ':aid'   => $accountId,
            ':tid'   => $this->tenantId,
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $ledger = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $deb = (float)$line['debit'];
            $cred = (float)$line['credit'];
            $totalDebit += $deb;
            $totalCredit += $cred;

            if ($normalBalance === 'debit') {
                $runningBalance += ($deb - $cred);
            } else {
                $runningBalance += ($cred - $deb);
            }

            $line['running_balance'] = $runningBalance;
            $ledger[] = $line;
        }

        return [
            'account_id'      => $accountId,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'normal_balance'  => $normalBalance,
            'transactions'    => $ledger,
        ];
    }
}
