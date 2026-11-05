<?php

namespace Modules\DailyFinancialManagement\Services;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialAccountMovement;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialCategory;
use Modules\DailyFinancialManagement\Entities\FinancialPosImport;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;

class PosFinancialSyncService
{
    /**
     * Run synchronisation for POS transactions and Loan installments.
     */
    public function syncPosAndLoans(
        int $businessId,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $force = false
    ): array {
        $importRecord = FinancialPosImport::create([
            'business_id' => $businessId,
            'source_module' => 'pos_and_loan',
            'sync_type' => $startDate ? 'manual_range' : 'incremental',
            'date_from' => $startDate ? Carbon::parse($startDate)->startOfDay() : null,
            'date_to' => $endDate ? Carbon::parse($endDate)->endOfDay() : null,
            'records_processed' => 0,
            'records_imported' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
            'status' => 'running',
            'executed_by' => auth()->id(),
        ]);

        $stats = [
            'success' => true,
            'sales_count' => 0,
            'returns_count' => 0,
            'expenses_count' => 0,
            'payments_count' => 0,
            'loan_payments_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
        ];

        try {
            DB::beginTransaction();

            // 1. Ensure default financial accounts exist
            $defaultCashAccount = $this->ensureDefaultAccount($businessId, 'cash', 'Default Cash Vault');
            $defaultBankAccount = $this->ensureDefaultAccount($businessId, 'bank', 'Default ABA Bank Account', 'ABA Bank');

            // 2. Ensure default categories exist
            $salesCategory = $this->ensureDefaultCategory($businessId, 'REV_POS_SALES', 'POS Retail Sales', 'ការលក់រាយ POS', 'income');
            $returnCategory = $this->ensureDefaultCategory($businessId, 'REV_POS_RETURNS', 'POS Sales Returns', 'ការទូទាត់ទំនិញត្រឡប់មកវិញ', 'income');
            $loanInterestCategory = $this->ensureDefaultCategory($businessId, 'REV_LOAN_INTEREST', 'Loan Interest Income', 'ចំណូលការប្រាក់បង់រំលស់', 'income');
            $posExpenseCategory = $this->ensureDefaultCategory($businessId, 'EXP_POS_SYNC', 'Synced POS Expenses', 'ចំណាយប្រតិបត្តិការ POS', 'operating_expense');

            // 3. Determine sync date bounds
            if (! $startDate) {
                $lastSyncTx = FinancialTransaction::where('business_id', $businessId)
                    ->whereIn('source_type', ['pos_sale', 'pos_return', 'loan_payment'])
                    ->max('transaction_date');

                $startDate = $lastSyncTx ? Carbon::parse($lastSyncTx)->subDays(2)->format('Y-m-d') : Carbon::now()->subDays(30)->format('Y-m-d');
            }

            if (! $endDate) {
                $endDate = Carbon::now()->format('Y-m-d');
            }

            // 4. Sync POS Sales
            $this->syncPosSales($businessId, $startDate, $endDate, $salesCategory->id, $defaultCashAccount, $defaultBankAccount, $stats);

            // 5. Sync POS Sales Returns
            $this->syncPosReturns($businessId, $startDate, $endDate, $returnCategory->id, $defaultCashAccount, $defaultBankAccount, $stats);

            // 6. Sync POS Expenses
            $this->syncPosExpenses($businessId, $startDate, $endDate, $posExpenseCategory->id, $defaultCashAccount, $defaultBankAccount, $stats);

            // 7. Sync Installment / Loan Payments
            $this->syncLoanPayments($businessId, $startDate, $endDate, $loanInterestCategory->id, $defaultCashAccount, $defaultBankAccount, $stats);

            // Update import batch record
            $totalProcessed = $stats['sales_count'] + $stats['returns_count'] + $stats['expenses_count'] + $stats['loan_payments_count'] + $stats['skipped_count'];
            $totalImported = $stats['sales_count'] + $stats['returns_count'] + $stats['expenses_count'] + $stats['loan_payments_count'];

            $importRecord->update([
                'records_processed' => $totalProcessed,
                'records_imported' => $totalImported,
                'records_skipped' => $stats['skipped_count'],
                'records_failed' => $stats['failed_count'],
                'status' => 'completed',
            ]);

            FinancialAuditLog::record(
                $businessId,
                'pos_sync',
                'FinancialPosImport',
                $importRecord->id,
                null,
                $stats,
                "Synchronized POS and Loan data from {$startDate} to {$endDate}"
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('POS Sync Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $importRecord->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            $stats['success'] = false;
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Read-only sync of POS Sales from ky.transactions.
     */
    protected function syncPosSales(
        int $businessId,
        string $startDate,
        string $endDate,
        int $categoryId,
        FinancialAccount $cashAccount,
        FinancialAccount $bankAccount,
        array &$stats
    ): void {
        $sales = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween(DB::raw('DATE(transaction_date)'), [$startDate, $endDate])
            ->select([
                'id',
                'business_id',
                'location_id',
                'invoice_no',
                'transaction_date',
                'total_before_tax',
                'tax_amount',
                'discount_type',
                'discount_amount',
                'final_total',
                'created_by',
            ])
            ->cursor();

        foreach ($sales as $sale) {
            $existing = FinancialTransaction::where('business_id', $businessId)
                ->where('source_type', 'pos_sale')
                ->where('source_id', $sale->id)
                ->first();

            if ($existing) {
                $stats['skipped_count']++;
                continue;
            }

            // Compute exact COGS for this sale
            $cogs = $this->calculateSaleCogs($sale->id);

            $finTx = FinancialTransaction::create([
                'business_id' => $businessId,
                'business_location_id' => $sale->location_id ?? 1,
                'category_id' => $categoryId,
                'transaction_type' => 'pos_sale',
                'transaction_date' => Carbon::parse($sale->transaction_date)->format('Y-m-d'),
                'ref_no' => $sale->invoice_no ?? ('POS-' . $sale->id),
                'source_type' => 'pos_sale',
                'source_id' => $sale->id,
                'description' => "POS Retail Sale #{$sale->invoice_no}",
                'amount' => $sale->final_total,
                'cogs_amount' => $cogs,
                'currency' => 'USD',
                'exchange_rate' => 1.0000,
                'amount_in_base_currency' => $sale->final_total,
                'payment_method' => 'multiple',
                'approval_status' => 'approved',
                'is_operating' => true,
                'created_by' => $sale->created_by,
                'approved_by' => $sale->created_by,
                'approved_at' => $sale->transaction_date,
                'meta_json' => [
                    'total_before_tax' => $sale->total_before_tax,
                    'tax_amount' => $sale->tax_amount,
                    'discount_amount' => $sale->discount_amount,
                    'discount_type' => $sale->discount_type,
                ],
            ]);

            // Sync payments associated with this sale
            $this->syncTransactionPayments($sale->id, $finTx, $cashAccount, $bankAccount);

            $stats['sales_count']++;
        }
    }

    /**
     * Read-only sync of POS Sales Returns from ky.transactions.
     */
    protected function syncPosReturns(
        int $businessId,
        string $startDate,
        string $endDate,
        int $categoryId,
        FinancialAccount $cashAccount,
        FinancialAccount $bankAccount,
        array &$stats
    ): void {
        $returns = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell_return')
            ->where('status', 'final')
            ->whereBetween(DB::raw('DATE(transaction_date)'), [$startDate, $endDate])
            ->select(['id', 'business_id', 'location_id', 'invoice_no', 'ref_no', 'return_parent_id', 'transaction_date', 'final_total', 'created_by'])
            ->cursor();

        foreach ($returns as $ret) {
            $existing = FinancialTransaction::where('business_id', $businessId)
                ->where('source_type', 'pos_return')
                ->where('source_id', $ret->id)
                ->first();

            if ($existing) {
                $stats['skipped_count']++;
                continue;
            }

            $finTx = FinancialTransaction::create([
                'business_id' => $businessId,
                'business_location_id' => $ret->location_id ?? 1,
                'category_id' => $categoryId,
                'transaction_type' => 'pos_return',
                'transaction_date' => Carbon::parse($ret->transaction_date)->format('Y-m-d'),
                'ref_no' => $ret->invoice_no ?: ($ret->ref_no ?: 'RET-' . $ret->id),
                'source_type' => 'pos_return',
                'source_id' => $ret->id,
                'description' => "POS Sales Return #{$ret->invoice_no}",
                'amount' => $ret->final_total,
                'cogs_amount' => 0.0000,
                'currency' => 'USD',
                'exchange_rate' => 1.0000,
                'amount_in_base_currency' => $ret->final_total,
                'payment_method' => 'cash',
                'approval_status' => 'approved',
                'is_operating' => true,
                'created_by' => $ret->created_by,
                'approved_by' => $ret->created_by,
                'approved_at' => $ret->transaction_date,
                'meta_json' => [
                    'return_parent_id' => $ret->return_parent_id,
                ],
            ]);

            $stats['returns_count']++;
        }
    }

    /**
     * Read-only sync of POS Expenses from ky.transactions.
     */
    protected function syncPosExpenses(
        int $businessId,
        string $startDate,
        string $endDate,
        int $defaultCategoryId,
        FinancialAccount $cashAccount,
        FinancialAccount $bankAccount,
        array &$stats
    ): void {
        $expenses = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'expense')
            ->where('status', 'final')
            ->whereBetween(DB::raw('DATE(transaction_date)'), [$startDate, $endDate])
            ->select(['id', 'business_id', 'location_id', 'ref_no', 'transaction_date', 'final_total', 'additional_notes', 'created_by', 'expense_category_id'])
            ->cursor();

        foreach ($expenses as $expense) {
            $existing = FinancialTransaction::where('business_id', $businessId)
                ->where('source_type', 'pos_expense')
                ->where('source_id', $expense->id)
                ->first();

            if ($existing) {
                $stats['skipped_count']++;
                continue;
            }

            $finTx = FinancialTransaction::create([
                'business_id' => $businessId,
                'business_location_id' => $expense->location_id ?? 1,
                'category_id' => $defaultCategoryId,
                'transaction_type' => 'expense',
                'transaction_date' => Carbon::parse($expense->transaction_date)->format('Y-m-d'),
                'ref_no' => $expense->ref_no ?: ('EXP-' . $expense->id),
                'source_type' => 'pos_expense',
                'source_id' => $expense->id,
                'description' => $expense->additional_notes ?: "POS Expense #{$expense->ref_no}",
                'amount' => $expense->final_total,
                'cogs_amount' => 0.0000,
                'currency' => 'USD',
                'exchange_rate' => 1.0000,
                'amount_in_base_currency' => $expense->final_total,
                'payment_method' => 'cash',
                'approval_status' => 'approved',
                'is_operating' => true,
                'created_by' => $expense->created_by,
                'approved_by' => $expense->created_by,
                'approved_at' => $expense->transaction_date,
            ]);

            // Create account movement for cash outflow
            FinancialAccountMovement::create([
                'business_id' => $businessId,
                'financial_account_id' => $cashAccount->id,
                'financial_transaction_id' => $finTx->id,
                'movement_type' => 'outflow',
                'movement_date' => $finTx->transaction_date,
                'amount' => $expense->final_total,
                'balance_before' => $cashAccount->current_balance,
                'balance_after' => $cashAccount->current_balance - $expense->final_total,
                'ref_no' => $finTx->ref_no,
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'notes' => $finTx->description,
                'created_by' => $expense->created_by,
            ]);

            $cashAccount->decrement('current_balance', $expense->final_total);

            $stats['expenses_count']++;
        }
    }

    /**
     * Read-only sync of Installment Payments from s_loanmanagementv2.loan_payments.
     */
    protected function syncLoanPayments(
        int $businessId,
        string $startDate,
        string $endDate,
        int $interestCategoryId,
        FinancialAccount $cashAccount,
        FinancialAccount $bankAccount,
        array &$stats
    ): void {
        try {
            $loanPayments = DB::connection('mysql_loan')
                ->table('loan_payments')
                ->where('status', 'confirmed')
                ->whereBetween(DB::raw('COALESCE(payment_date, DATE(paid_at))'), [$startDate, $endDate])
                ->select([
                    'id',
                    'loan_id',
                    'payment_ref_no',
                    'reference_no',
                    'payment_type',
                    'channel',
                    'amount',
                    'principal_paid',
                    'interest_paid',
                    'penalty_amount',
                    'payment_date',
                    'paid_at',
                    'received_by',
                ])
                ->cursor();

            foreach ($loanPayments as $lp) {
                $existing = FinancialTransaction::where('business_id', $businessId)
                    ->where('source_type', 'loan_payment')
                    ->where('source_id', $lp->id)
                    ->first();

                if ($existing) {
                    $stats['skipped_count']++;
                    continue;
                }

                $txDate = $lp->payment_date ?: ($lp->paid_at ? Carbon::parse($lp->paid_at)->format('Y-m-d') : Carbon::now()->format('Y-m-d'));
                $totalPaid = (float) $lp->amount;
                $principal = (float) $lp->principal_paid;
                $interest = (float) $lp->interest_paid;
                $penalty = (float) $lp->penalty_amount;

                $targetAccount = str_contains(strtolower($lp->channel ?? ''), 'cash') ? $cashAccount : $bankAccount;

                // 1. Record Recognized Interest Income (P&L Income)
                if ($interest > 0) {
                    $interestTx = FinancialTransaction::create([
                        'business_id' => $businessId,
                        'business_location_id' => 1,
                        'category_id' => $interestCategoryId,
                        'financial_account_id' => $targetAccount->id,
                        'transaction_type' => 'loan_interest',
                        'transaction_date' => $txDate,
                        'ref_no' => $lp->payment_ref_no ?: ('LOAN-INT-' . $lp->id),
                        'source_type' => 'loan_payment',
                        'source_id' => $lp->id,
                        'description' => "Recognized Installment Interest [Loan #{$lp->loan_id}]",
                        'amount' => $interest,
                        'cogs_amount' => 0.0000,
                        'currency' => 'USD',
                        'exchange_rate' => 1.0000,
                        'amount_in_base_currency' => $interest,
                        'payment_method' => $lp->channel ?: 'bank',
                        'approval_status' => 'approved',
                        'is_operating' => true,
                        'created_by' => $lp->received_by,
                        'approved_by' => $lp->received_by,
                        'approved_at' => $lp->paid_at ?? now(),
                        'meta_json' => [
                            'loan_id' => $lp->loan_id,
                            'principal_collected' => $principal,
                            'interest_collected' => $interest,
                            'penalty_collected' => $penalty,
                        ],
                    ]);

                    // Interest Cash Inflow Movement
                    FinancialAccountMovement::create([
                        'business_id' => $businessId,
                        'financial_account_id' => $targetAccount->id,
                        'financial_transaction_id' => $interestTx->id,
                        'movement_type' => 'inflow',
                        'movement_date' => $txDate,
                        'amount' => $interest,
                        'balance_before' => $targetAccount->current_balance,
                        'balance_after' => $targetAccount->current_balance + $interest,
                        'ref_no' => $interestTx->ref_no,
                        'source_type' => 'loan_payment_interest',
                        'source_id' => $lp->id,
                        'notes' => "Installment Interest Inflow [Loan #{$lp->loan_id}]",
                        'created_by' => $lp->received_by,
                    ]);

                    $targetAccount->increment('current_balance', $interest);
                }

                // 2. Record Principal Collection (Debt collection; Cash Flow Inflow only, NOT P&L Revenue)
                if ($principal > 0) {
                    $principalTx = FinancialTransaction::create([
                        'business_id' => $businessId,
                        'business_location_id' => 1,
                        'category_id' => null,
                        'financial_account_id' => $targetAccount->id,
                        'transaction_type' => 'loan_principal',
                        'transaction_date' => $txDate,
                        'ref_no' => ($lp->payment_ref_no ?: 'LOAN-PRIN-' . $lp->id),
                        'source_type' => 'loan_payment_principal',
                        'source_id' => $lp->id,
                        'description' => "Installment Principal Collection [Loan #{$lp->loan_id}] - Debt Recovery",
                        'amount' => $principal,
                        'cogs_amount' => 0.0000,
                        'currency' => 'USD',
                        'exchange_rate' => 1.0000,
                        'amount_in_base_currency' => $principal,
                        'payment_method' => $lp->channel ?: 'bank',
                        'approval_status' => 'approved',
                        'is_operating' => false,
                        'created_by' => $lp->received_by,
                        'approved_by' => $lp->received_by,
                        'approved_at' => $lp->paid_at ?? now(),
                        'meta_json' => [
                            'loan_id' => $lp->loan_id,
                            'is_principal_collection' => true,
                        ],
                    ]);

                    // Principal Cash Inflow Movement
                    FinancialAccountMovement::create([
                        'business_id' => $businessId,
                        'financial_account_id' => $targetAccount->id,
                        'financial_transaction_id' => $principalTx->id,
                        'movement_type' => 'inflow',
                        'movement_date' => $txDate,
                        'amount' => $principal,
                        'balance_before' => $targetAccount->current_balance,
                        'balance_after' => $targetAccount->current_balance + $principal,
                        'ref_no' => $principalTx->ref_no,
                        'source_type' => 'loan_payment_principal',
                        'source_id' => $lp->id,
                        'notes' => "Installment Principal Collection Inflow [Loan #{$lp->loan_id}]",
                        'created_by' => $lp->received_by,
                    ]);

                    $targetAccount->increment('current_balance', $principal);
                }

                $stats['loan_payments_count']++;
            }
        } catch (Exception $e) {
            Log::warning('Installment DB sync notice: ' . $e->getMessage());
        }
    }

    /**
     * Compute COGS for a given POS sale transaction.
     */
    protected function calculateSaleCogs(int $transactionId): float
    {
        $cogs = DB::table('transaction_sell_lines as tsl')
            ->leftJoin('transaction_sell_lines_purchase_lines as tslpl', 'tsl.id', '=', 'tslpl.sell_line_id')
            ->leftJoin('purchase_lines as pl', 'tslpl.purchase_line_id', '=', 'pl.id')
            ->leftJoin('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->where('tsl.transaction_id', $transactionId)
            ->selectRaw('
                COALESCE(
                    SUM(COALESCE(tslpl.quantity, tsl.quantity) * COALESCE(pl.purchase_price_inc_tax, v.dpp_inc_tax, 0)),
                    0
                ) as total_cogs
            ')
            ->value('total_cogs');

        return (float) ($cogs ?: 0.0);
    }

    /**
     * Extract payments for a sale and record into financial account movements.
     */
    protected function syncTransactionPayments(
        int $posTransactionId,
        FinancialTransaction $finTx,
        FinancialAccount $cashAccount,
        FinancialAccount $bankAccount
    ): void {
        $payments = DB::table('transaction_payments')
            ->where('transaction_id', $posTransactionId)
            ->get();

        foreach ($payments as $payment) {
            $isCash = ($payment->method === 'cash');
            $account = $isCash ? $cashAccount : $bankAccount;
            $sourceType = $isCash ? 'pos_payment_cash' : 'pos_payment_bank';

            FinancialAccountMovement::create([
                'business_id' => $finTx->business_id,
                'financial_account_id' => $account->id,
                'financial_transaction_id' => $finTx->id,
                'movement_type' => 'inflow',
                'movement_date' => Carbon::parse($payment->paid_on ?? $finTx->transaction_date)->format('Y-m-d'),
                'amount' => $payment->amount,
                'balance_before' => $account->current_balance,
                'balance_after' => $account->current_balance + $payment->amount,
                'ref_no' => $payment->payment_ref_no ?: ('PAY-' . $payment->id),
                'source_type' => $sourceType,
                'source_id' => $payment->id,
                'notes' => "POS Collection [Method: {$payment->method}] for Invoice #{$finTx->ref_no}",
                'created_by' => $payment->created_by ?: $finTx->created_by,
            ]);

            $account->increment('current_balance', $payment->amount);
        }
    }

    protected function ensureDefaultAccount(
        int $businessId,
        string $type,
        string $name,
        ?string $bankName = null
    ): FinancialAccount {
        return FinancialAccount::firstOrCreate(
            [
                'business_id' => $businessId,
                'account_name' => $name,
            ],
            [
                'account_type' => $type,
                'bank_name' => $bankName,
                'currency' => 'USD',
                'opening_balance' => 0.0000,
                'current_balance' => 0.0000,
                'is_active' => true,
            ]
        );
    }

    protected function ensureDefaultCategory(
        int $businessId,
        string $code,
        string $nameEn,
        string $nameKh,
        string $type
    ): FinancialCategory {
        return FinancialCategory::firstOrCreate(
            [
                'business_id' => $businessId,
                'code' => $code,
            ],
            [
                'name_en' => $nameEn,
                'name_kh' => $nameKh,
                'type' => $type,
                'is_operating' => true,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }
}

