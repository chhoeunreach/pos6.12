<?php

namespace Modules\DailyFinancialManagement\Services;

use App\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialReconciliation;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;

class DailyClosingService
{
    protected FinancialCalculationService $calcService;

    public function __construct(FinancialCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    /**
     * Compute and create or update a Daily Financial Closing summary.
     */
    public function generateClosingSummary(
        int $businessId,
        string $date,
        int $locationId,
        ?array $countedData = null,
        ?User $closer = null
    ): array {
        $existing = FinancialDailyClosing::where('business_id', $businessId)
            ->where('business_location_id', $locationId)
            ->where('closing_date', $date)
            ->first();

        if ($existing && $existing->isLocked()) {
            return [
                'success' => false,
                'error' => "Daily closing for date {$date} and branch {$locationId} is already locked.",
                'closing' => $existing,
            ];
        }

        // 1. Calculate P&L for that date & location
        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $date, $date, $locationId);

        // 2. Calculate Cash & Bank Reconciliation
        $reconciliation = $this->calcService->calculateReconciliation($businessId, $date, $locationId);
        $summary = $reconciliation['summary'];

        $actualCash = isset($countedData['actual_cash_counted']) ? (float) $countedData['actual_cash_counted'] : ($existing ? (float) $existing->actual_cash_counted : $summary['expected_cash']);
        $actualBank = isset($countedData['actual_bank_balance']) ? (float) $countedData['actual_bank_balance'] : ($existing ? (float) $existing->actual_bank_balance : $summary['expected_bank']);

        $cashDiff = $actualCash - $summary['expected_cash'];
        $bankDiff = $actualBank - $summary['expected_bank'];

        $closingData = [
            'business_id' => $businessId,
            'business_location_id' => $locationId,
            'closing_date' => $date,
            'closing_status' => $existing ? $existing->closing_status : 'draft',
            'total_pos_sales' => $pnl['gross_sales'],
            'total_pos_returns' => $pnl['sales_returns'],
            'total_net_sales' => $pnl['net_sales'],
            'total_cogs' => $pnl['cogs'],
            'total_gross_profit' => $pnl['gross_profit'],
            'total_manual_income' => $pnl['manual_income'],
            'total_loan_interest' => $pnl['loan_interest_income'],
            'total_operating_expenses' => $pnl['operating_expenses'],
            'net_profit' => $pnl['net_profit'],
            'opening_cash_balance' => $summary['opening_cash'],
            'total_cash_inflow' => $summary['cash_inflow'],
            'total_cash_outflow' => $summary['cash_outflow'],
            'expected_cash_balance' => $summary['expected_cash'],
            'actual_cash_counted' => $actualCash,
            'cash_difference' => $cashDiff,
            'expected_bank_balance' => $summary['expected_bank'],
            'actual_bank_balance' => $actualBank,
            'bank_difference' => $bankDiff,
            'closing_notes' => $countedData['closing_notes'] ?? ($existing ? $existing->closing_notes : null),
            'reconciliation_notes' => $countedData['reconciliation_notes'] ?? ($existing ? $existing->reconciliation_notes : null),
            'closed_by' => $closer ? $closer->id : ($existing ? $existing->closed_by : auth()->id()),
        ];

        $closing = FinancialDailyClosing::updateOrCreate(
            [
                'business_id' => $businessId,
                'business_location_id' => $locationId,
                'closing_date' => $date,
            ],
            $closingData
        );

        // Store account-by-account reconciliations and denomination breakdown
        if (! empty($countedData['denominations'])) {
            $firstCashAccount = $reconciliation['cash_accounts'][0]['id'] ?? 1;

            FinancialReconciliation::updateOrCreate(
                [
                    'daily_closing_id' => $closing->id,
                    'financial_account_id' => $firstCashAccount,
                    'reconciliation_date' => $date,
                ],
                [
                    'system_balance' => $summary['expected_cash'],
                    'physical_balance' => $actualCash,
                    'difference' => $cashDiff,
                    'denominations_json' => $countedData['denominations'],
                    'notes' => $countedData['reconciliation_notes'] ?? null,
                    'reconciled_by' => $closer ? $closer->id : auth()->id(),
                ]
            );
        }

        FinancialAuditLog::record(
            $businessId,
            'daily_closing_calculated',
            'FinancialDailyClosing',
            $closing->id,
            null,
            $closingData,
            "Daily financial closing computed for {$date} (Branch {$locationId})"
        );

        return [
            'success' => true,
            'closing' => $closing->fresh(['location', 'reconciliations']),
            'pnl' => $pnl,
            'reconciliation' => $reconciliation,
        ];
    }

    /**
     * Lock closing period. Freezes all transactions for this branch and date.
     */
    public function lockClosingPeriod(FinancialDailyClosing $closing, User $approver): bool
    {
        return DB::transaction(function () use ($closing, $approver) {
            $closing->update([
                'closing_status' => 'locked',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'locked_at' => now(),
            ]);

            // Lock all financial transactions for this date and branch
            FinancialTransaction::where('business_id', $closing->business_id)
                ->where('business_location_id', $closing->business_location_id)
                ->where('transaction_date', $closing->closing_date)
                ->update([
                    'is_locked' => true,
                    'daily_closing_id' => $closing->id,
                ]);

            FinancialAuditLog::record(
                $closing->business_id,
                'daily_closing_locked',
                'FinancialDailyClosing',
                $closing->id,
                ['status' => 'submitted'],
                ['status' => 'locked', 'approved_by' => $approver->id],
                "Daily closing period locked for {$closing->closing_date} at branch {$closing->business_location_id}"
            );

            return true;
        });
    }
}

