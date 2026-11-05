<?php

namespace Modules\DailyFinancialManagement\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialAccountMovement;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;

class FinancialCalculationService
{
    /**
     * Compute comprehensive Profit and Loss statement metrics.
     */
    public function calculateProfitAndLoss(
        int $businessId,
        string $startDate,
        string $endDate,
        ?int $locationId = null,
        ?int $departmentId = null
    ): array {
        $baseQuery = FinancialTransaction::where('business_id', $businessId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('approval_status', ['approved']);

        if ($locationId) {
            $baseQuery->where('business_location_id', $locationId);
        }

        if ($departmentId) {
            $baseQuery->where('department_id', $departmentId);
        }

        // 1. Gross Sales (POS Sales)
        $grossSales = (float) (clone $baseQuery)
            ->where('transaction_type', 'pos_sale')
            ->sum('amount');

        // 2. Sales Returns
        $salesReturns = (float) (clone $baseQuery)
            ->where('transaction_type', 'pos_return')
            ->sum('amount');

        // 3. Sales Discounts (extracted from sell metadata or separate discount transactions)
        $salesDiscounts = (float) (clone $baseQuery)
            ->where('transaction_type', 'pos_sale')
            ->get()
            ->sum(function ($tx) {
                return (float) ($tx->meta_json['discount_amount'] ?? 0);
            });

        // Net Sales = Gross Sales - Discounts - Returns
        $netSales = max(0, $grossSales - $salesDiscounts - $salesReturns);

        // 4. Cost of Goods Sold (COGS)
        $cogs = (float) (clone $baseQuery)
            ->where('transaction_type', 'pos_sale')
            ->sum('cogs_amount');

        // Deduct returned COGS if any
        $returnedCogs = (float) (clone $baseQuery)
            ->where('transaction_type', 'pos_return')
            ->sum('cogs_amount');
        $netCogs = max(0, $cogs - $returnedCogs);

        // Gross Profit = Net Sales - COGS
        $grossProfit = $netSales - $netCogs;

        // 5. Recognized Other Income
        // A. Installment Interest Income
        $loanInterestIncome = (float) (clone $baseQuery)
            ->where('transaction_type', 'loan_interest')
            ->sum('amount');

        // B. Manual Other Operating Income
        $manualIncome = (float) (clone $baseQuery)
            ->where('transaction_type', 'income')
            ->where('is_operating', true)
            ->sum('amount');

        $totalRecognizedIncome = $loanInterestIncome + $manualIncome;

        // 6. Operating Expenses (OPEX)
        $operatingExpenses = (float) (clone $baseQuery)
            ->where('transaction_type', 'expense')
            ->where('is_operating', true)
            ->sum('amount');

        // Net Operating Profit = Gross Profit + Recognized Other Income - Operating Expenses
        $netProfit = $grossProfit + $totalRecognizedIncome - $operatingExpenses;

        // Gross & Net Margin Percentages
        $grossMarginPercent = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0.0;
        $netMarginPercent = $netSales > 0 ? ($netProfit / $netSales) * 100 : 0.0;

        return [
            'gross_sales' => round($grossSales, 4),
            'sales_discounts' => round($salesDiscounts, 4),
            'sales_returns' => round($salesReturns, 4),
            'net_sales' => round($netSales, 4),
            'cogs' => round($netCogs, 4),
            'gross_profit' => round($grossProfit, 4),
            'gross_margin_percent' => round($grossMarginPercent, 2),
            'loan_interest_income' => round($loanInterestIncome, 4),
            'manual_income' => round($manualIncome, 4),
            'total_recognized_income' => round($totalRecognizedIncome, 4),
            'operating_expenses' => round($operatingExpenses, 4),
            'net_profit' => round($netProfit, 4),
            'net_margin_percent' => round($netMarginPercent, 2),
        ];
    }

    /**
     * Compute comprehensive Cash Flow statement.
     */
    public function calculateCashFlow(
        int $businessId,
        string $startDate,
        string $endDate,
        ?int $locationId = null
    ): array {
        $movementQuery = FinancialAccountMovement::where('business_id', $businessId)
            ->whereBetween('movement_date', [$startDate, $endDate]);

        if ($locationId) {
            $movementQuery->whereHas('account', function ($q) use ($locationId) {
                $q->where('business_location_id', $locationId)
                  ->orWhereNull('business_location_id');
            });
        }

        // Cash Inflows
        // 1. POS Cash Collections
        $posCashCollections = (float) (clone $movementQuery)
            ->where('movement_type', 'inflow')
            ->where('source_type', 'pos_payment_cash')
            ->sum('amount');

        // 2. POS Bank / Digital Collections (ABA, Wing, ACLEDA, etc.)
        $posBankCollections = (float) (clone $movementQuery)
            ->where('movement_type', 'inflow')
            ->where('source_type', 'pos_payment_bank')
            ->sum('amount');

        // 3. Installment Principal Collections (Balance Sheet receivable reduction, NOT revenue)
        $loanPrincipalCollections = (float) (clone $movementQuery)
            ->where('movement_type', 'inflow')
            ->where('source_type', 'loan_payment_principal')
            ->sum('amount');

        // 4. Installment Interest Collections
        $loanInterestCollections = (float) (clone $movementQuery)
            ->where('movement_type', 'inflow')
            ->where('source_type', 'loan_payment_interest')
            ->sum('amount');

        // 5. Other Manual Inflows
        $otherInflows = (float) (clone $movementQuery)
            ->where('movement_type', 'inflow')
            ->whereIn('source_type', ['manual', 'income'])
            ->sum('amount');

        $totalInflows = $posCashCollections + $posBankCollections + $loanPrincipalCollections
            + $loanInterestCollections + $otherInflows;

        // Cash Outflows
        // 1. Operating Expense Outflows
        $operatingExpenseOutflows = (float) (clone $movementQuery)
            ->where('movement_type', 'outflow')
            ->where('source_type', 'expense')
            ->sum('amount');

        // 2. Inventory / Stock Purchase Outflows (Working Capital Outflow)
        $inventoryPurchaseOutflows = (float) (clone $movementQuery)
            ->where('movement_type', 'outflow')
            ->where('source_type', 'pos_purchase')
            ->sum('amount');

        // 3. Other Outflows / Staff Advances / Fixed Assets
        $otherOutflows = (float) (clone $movementQuery)
            ->where('movement_type', 'outflow')
            ->whereNotIn('source_type', ['expense', 'pos_purchase', 'transfer_out'])
            ->sum('amount');

        $totalOutflows = $operatingExpenseOutflows + $inventoryPurchaseOutflows + $otherOutflows;

        // Net Cash Flow
        $netCashFlow = $totalInflows - $totalOutflows;

        return [
            'pos_cash_collections' => round($posCashCollections, 4),
            'pos_bank_collections' => round($posBankCollections, 4),
            'loan_principal_collections' => round($loanPrincipalCollections, 4),
            'loan_interest_collections' => round($loanInterestCollections, 4),
            'other_inflows' => round($otherInflows, 4),
            'total_inflows' => round($totalInflows, 4),
            'operating_expense_outflows' => round($operatingExpenseOutflows, 4),
            'inventory_purchase_outflows' => round($inventoryPurchaseOutflows, 4),
            'other_outflows' => round($otherOutflows, 4),
            'total_outflows' => round($totalOutflows, 4),
            'net_cash_flow' => round($netCashFlow, 4),
        ];
    }

    /**
     * Compute Daily Balance Reconciliation for Cash and Bank accounts.
     */
    public function calculateReconciliation(
        int $businessId,
        string $date,
        ?int $locationId = null
    ): array {
        $accounts = FinancialAccount::where('business_id', $businessId)
            ->where('is_active', true);

        if ($locationId) {
            $accounts->where(function ($q) use ($locationId) {
                $q->where('business_location_id', $locationId)
                  ->orWhereNull('business_location_id');
            });
        }

        $accountList = $accounts->get();

        $cashAccounts = [];
        $bankAccounts = [];

        $totalOpeningCash = 0.0;
        $totalCashInflow = 0.0;
        $totalCashOutflow = 0.0;
        $totalExpectedCash = 0.0;

        $totalOpeningBank = 0.0;
        $totalBankInflow = 0.0;
        $totalBankOutflow = 0.0;
        $totalExpectedBank = 0.0;

        foreach ($accountList as $account) {
            // Opening balance before this date
            $inflowsBefore = (float) FinancialAccountMovement::where('financial_account_id', $account->id)
                ->where('movement_date', '<', $date)
                ->whereIn('movement_type', ['inflow', 'transfer_in'])
                ->sum('amount');

            $outflowsBefore = (float) FinancialAccountMovement::where('financial_account_id', $account->id)
                ->where('movement_date', '<', $date)
                ->whereIn('movement_type', ['outflow', 'transfer_out'])
                ->sum('amount');

            $openingBalance = (float) $account->opening_balance + $inflowsBefore - $outflowsBefore;

            // Day movements
            $dayInflow = (float) FinancialAccountMovement::where('financial_account_id', $account->id)
                ->where('movement_date', $date)
                ->whereIn('movement_type', ['inflow', 'transfer_in'])
                ->sum('amount');

            $dayOutflow = (float) FinancialAccountMovement::where('financial_account_id', $account->id)
                ->where('movement_date', $date)
                ->whereIn('movement_type', ['outflow', 'transfer_out'])
                ->sum('amount');

            $expectedClosing = $openingBalance + $dayInflow - $dayOutflow;

            $item = [
                'id' => $account->id,
                'name' => $account->account_name,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank_name,
                'currency' => $account->currency,
                'opening_balance' => round($openingBalance, 4),
                'inflow' => round($dayInflow, 4),
                'outflow' => round($dayOutflow, 4),
                'expected_closing' => round($expectedClosing, 4),
                'current_balance' => round((float) $account->current_balance, 4),
            ];

            if (in_array($account->account_type, ['cash', 'petty_cash'])) {
                $cashAccounts[] = $item;
                $totalOpeningCash += $openingBalance;
                $totalCashInflow += $dayInflow;
                $totalCashOutflow += $dayOutflow;
                $totalExpectedCash += $expectedClosing;
            } else {
                $bankAccounts[] = $item;
                $totalOpeningBank += $openingBalance;
                $totalBankInflow += $dayInflow;
                $totalBankOutflow += $dayOutflow;
                $totalExpectedBank += $expectedClosing;
            }
        }

        return [
            'date' => $date,
            'cash_accounts' => $cashAccounts,
            'bank_accounts' => $bankAccounts,
            'summary' => [
                'opening_cash' => round($totalOpeningCash, 4),
                'cash_inflow' => round($totalCashInflow, 4),
                'cash_outflow' => round($totalCashOutflow, 4),
                'expected_cash' => round($totalExpectedCash, 4),
                'opening_bank' => round($totalOpeningBank, 4),
                'bank_inflow' => round($totalBankInflow, 4),
                'bank_outflow' => round($totalBankOutflow, 4),
                'expected_bank' => round($totalExpectedBank, 4),
                'total_liquid_assets' => round($totalExpectedCash + $totalExpectedBank, 4),
            ],
        ];
    }
}

