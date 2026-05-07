<?php

namespace Modules\ClearDataByDate\Services;

use App\AccountTransaction;
use App\Events\ExpenseCreatedOrModified;
use App\Events\PurchaseCreatedOrModified;
use App\Events\StockAdjustmentCreatedOrModified;
use App\Events\StockTransferCreatedOrModified;
use Modules\ClearDataByDate\Exceptions\ClearDataByDateBlockedException;
use Modules\ClearDataByDate\Exceptions\PurchaseSellMismatch;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDataByDateService
{
    protected $transactionUtil;

    protected $productUtil;

    protected bool $continueOnBlocked = true;

    protected array $skipped = [];

    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
    }

    public function allowedModules(): array
    {
        return [
            'sales',
            'repair',
            'purchases',
            'sell_returns',
            'purchase_returns',
            'expenses',
            'stock_adjustments',
            'stock_transfers',
            'drafts_quotations_suspended',
            'payments_only',
            'activity_logs',
        ];
    }

    public function previewCounts(int $businessId, string $startDate, string $endDate, ?int $locationId, array $modules): array
    {
        $modules = array_values(array_intersect($modules, $this->allowedModules()));

        $results = [];

        foreach ($modules as $module) {
            if ($module === 'payments_only') {
                $results[$module] = $this->previewPaymentOnlyCounts($businessId, $startDate, $endDate, $locationId);
            } elseif ($module === 'activity_logs') {
                $results[$module] = $this->previewActivityLogCounts($businessId, $startDate, $endDate);
            } else {
                $transaction_filter = $this->transactionFilterForModule($module);
                $results[$module] = $this->previewTransactionModuleCounts($businessId, $startDate, $endDate, $locationId, $transaction_filter);
            }
        }

        return $results;
    }

    public function transactionIdSubqueryForModules(
        int $businessId,
        string $startDate,
        string $endDate,
        ?int $locationId,
        array $modules
    ) {
        $modules = array_values(array_intersect($modules, $this->allowedModules()));
        $transactionModules = array_values(array_diff($modules, ['payments_only', 'activity_logs']));
        if (empty($transactionModules)) {
            return null;
        }

        $q = $this->baseTransactionQuery($businessId, $startDate, $endDate, $locationId);
        $q->where(function ($outer) use ($transactionModules) {
            foreach ($transactionModules as $module) {
                $filter = $this->transactionFilterForModule($module);
                $outer->orWhere(function ($inner) use ($filter) {
                    $filter($inner);
                });
            }
        });

        return $q->select('transactions.id');
    }

    public function deleteSelectedData(
        int $businessId,
        int $userId,
        string $startDate,
        string $endDate,
        ?int $locationId,
        array $modules,
        array $previewCounts = [],
        ?callable $progressCallback = null,
        bool $continueOnBlocked = true
    ): array
    {
        $modules = array_values(array_intersect($modules, $this->allowedModules()));

        $deleted = [];
        $this->continueOnBlocked = $continueOnBlocked;
        $this->skipped = [];
        $progress = $this->makeProgressTracker($modules, $previewCounts, $progressCallback);

        //Returns first (safer when deleting their parents later).
        $module_order = [
            'sell_returns',
            'purchase_returns',
            'stock_adjustments',
            'expenses',
            'purchases',
            'sales',
            'drafts_quotations_suspended',
            //Delete stock transfers after sales, because transferred stocks may be sold (quantity_sold > 0)
            //and deleting the sales first can decrement mappings and allow transfer deletion.
            'stock_transfers',
            'payments_only',
            'activity_logs',
        ];

        $ordered_modules = array_values(array_intersect($module_order, $modules));

        foreach ($ordered_modules as $module) {
            $progress['setMessage']('Deleting '.str_replace('_', ' ', $module).'...');

            if ($module === 'payments_only') {
                $deleted[$module] = $this->deletePaymentsOnly(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'activity_logs') {
                $deleted[$module] = $this->deleteActivityLogs(
                    $businessId,
                    $startDate,
                    $endDate,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'sales') {
                $deleted[$module] = $this->deleteSales(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'repair') {
                $deleted[$module] = $this->deleteRepairs(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'drafts_quotations_suspended') {
                $deleted[$module] = $this->deleteDraftsQuotationsSuspended(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'purchases') {
                $deleted[$module] = $this->deletePurchases(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'sell_returns') {
                $deleted[$module] = $this->deleteSellReturns(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'purchase_returns') {
                $deleted[$module] = $this->deletePurchaseReturns(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'expenses') {
                $deleted[$module] = $this->deleteExpenses(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'stock_adjustments') {
                $deleted[$module] = $this->deleteStockAdjustments(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            } elseif ($module === 'stock_transfers') {
                $deleted[$module] = $this->deleteStockTransfers(
                    $businessId,
                    $startDate,
                    $endDate,
                    $locationId,
                    $progress['stepBased'] ? null : $progress['tick']
                );
            }

            if ($progress['stepBased']) {
                $progress['tick'](1, null);
            }
        }

        if (! empty($this->skipped)) {
            $deleted['_skipped'] = $this->skipped;
        }

        return $deleted;
    }

    public function fixBlockedStockTransferByDeletingDependentSells(
        int $businessId,
        string $startDate,
        string $endDate,
        ?int $locationId,
        int $sellTransferId,
        int $purchaseTransferId
    ): array {
        // Find purchase lines that were already sold
        $purchase_line_ids = PurchaseLine::where('transaction_id', $purchaseTransferId)
            ->where('quantity_sold', '>', 0)
            ->pluck('id')
            ->toArray();

        if (empty($purchase_line_ids)) {
            return [
                'fixed_sells' => 0,
                'message' => 'No dependent sells found. Try retry delete.',
            ];
        }

        $sell_tx_ids = $this->dependentSellIdsForPurchaseLines($purchase_line_ids);

        if (empty($sell_tx_ids)) {
            throw new ClearDataByDateBlockedException(
                'Could not find the sells that consumed this transfer. Please delete related sales manually.',
                [
                    ['label' => 'Open stock transfer', 'url' => url('/stock-transfers/'.$sellTransferId)],
                ],
                [
                    'sell_transfer_id' => $sellTransferId,
                    'purchase_transfer_id' => $purchaseTransferId,
                ]
            );
        }

        // Only delete dependent sells within selected range & location.
        $sell_query = Transaction::where('business_id', $businessId)
            ->whereIn('id', $sell_tx_ids)
            ->where('type', 'sell')
            ->whereBetween(DB::raw('date(transaction_date)'), [$startDate, $endDate]);

        if (! empty($locationId)) {
            $sell_query->where('location_id', $locationId);
        }

        $sell_ids_in_range = $sell_query->pluck('id')->toArray();

        if (empty($sell_ids_in_range)) {
            throw new ClearDataByDateBlockedException(
                'Dependent sells exist but are outside the selected date range/location. Expand the date range or clear sells first, then retry.',
                [
                    ['label' => 'Open stock transfer', 'url' => url('/stock-transfers/'.$sellTransferId)],
                ],
                [
                    'sell_transfer_id' => $sellTransferId,
                    'purchase_transfer_id' => $purchaseTransferId,
                ]
            );
        }

        $deleted_sells = 0;
        foreach (Transaction::where('business_id', $businessId)->whereIn('id', $sell_ids_in_range)->lazyByIdDesc(50, 'id') as $sell) {
            if ($this->transactionUtil->isReturnExist($sell->id)) {
                $links = $this->returnFixLinks($businessId, $sell->id, 'sell_return', 'sell-return');
                throw new ClearDataByDateBlockedException(__('lang_v1.return_exist'), $links, [
                    'transaction_id' => $sell->id,
                    'type' => 'sell',
                ]);
            }

            $output = $this->transactionUtil->deleteSale($businessId, $sell->id);
            if (empty($output['success'])) {
                throw new \Exception($output['msg'] ?? __('messages.something_went_wrong'));
            }
            $deleted_sells++;
        }

        // Attempt to delete transfer pair now.
        $this->deleteStockTransferPair($businessId, $startDate, $endDate, $locationId, $sellTransferId, $purchaseTransferId);

        return [
            'fixed_sells' => $deleted_sells,
            'message' => 'Deleted dependent sells and removed the stock transfer.',
        ];
    }

    protected function previewTransactionModuleCounts(int $businessId, string $startDate, string $endDate, ?int $locationId, callable $transactionFilter): array
    {
        $tq = $this->baseTransactionQuery($businessId, $startDate, $endDate, $locationId);
        $transactionFilter($tq);

        $transactions_count = (clone $tq)->count('transactions.id');
        $total_amount = (clone $tq)->sum('transactions.final_total');

        $transaction_ids_subquery = (clone $tq)->select('transactions.id');

        $sell_lines_count = 0;
        if (Schema::hasTable('transaction_sell_lines')) {
            $sell_lines_count = DB::table('transaction_sell_lines')
                ->whereIn('transaction_id', $transaction_ids_subquery)
                ->count('id');
        }

        $purchase_lines_count = 0;
        if (Schema::hasTable('purchase_lines')) {
            $purchase_lines_count = DB::table('purchase_lines')
                ->whereIn('transaction_id', $transaction_ids_subquery)
                ->count('id');
        }

        $payments_count = 0;
        $payments_amount = 0;
        if (Schema::hasTable('transaction_payments')) {
            $payments_count = DB::table('transaction_payments')
                ->whereIn('transaction_id', $transaction_ids_subquery)
                ->count('id');

            $payments_amount = DB::table('transaction_payments')
                ->whereIn('transaction_id', $transaction_ids_subquery)
                ->sum('amount');
        }

        return [
            'transactions' => (int) $transactions_count,
            'sell_lines' => (int) $sell_lines_count,
            'purchase_lines' => (int) $purchase_lines_count,
            'payments' => (int) $payments_count,
            'total_amount' => (float) $total_amount,
            'payments_amount' => (float) $payments_amount,
        ];
    }

    protected function previewPaymentOnlyCounts(int $businessId, string $startDate, string $endDate, ?int $locationId): array
    {
        if (! Schema::hasTable('transaction_payments')) {
            return [
                'payments' => 0,
                'payments_amount' => 0,
            ];
        }

        $query = DB::table('transaction_payments as tp')
            ->where('tp.business_id', $businessId);

        if (Schema::hasColumn('transaction_payments', 'paid_on')) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween(DB::raw('date(tp.paid_on)'), [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNull('tp.paid_on')
                            ->whereBetween(DB::raw('date(tp.created_at)'), [$startDate, $endDate]);
                    });
            });
        } else {
            $query->whereBetween(DB::raw('date(tp.created_at)'), [$startDate, $endDate]);
        }

        if (! empty($locationId)) {
            $query->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                ->where('t.business_id', $businessId)
                ->where('t.location_id', $locationId);
        }

        return [
            'payments' => (int) (clone $query)->count('tp.id'),
            'payments_amount' => (float) (clone $query)->sum('tp.amount'),
        ];
    }

    protected function previewActivityLogCounts(int $businessId, string $startDate, string $endDate): array
    {
        $table = config('activitylog.table_name');
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'business_id')) {
            return ['activity_logs' => 0];
        }

        $count = DB::table($table)
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('date(created_at)'), [$startDate, $endDate])
            ->count('id');

        return ['activity_logs' => (int) $count];
    }

    protected function deleteSales(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'sell')->where('status', 'final');
                if (Schema::hasColumn('transactions', 'sub_type')) {
                    $q->where(function ($q2) {
                        $q2->whereNull('sub_type')->orWhere('sub_type', '!=', 'repair');
                    });
                }
                if (Schema::hasColumn('transactions', 'is_suspend')) {
                    $q->where('is_suspend', 0);
                }
                if (Schema::hasColumn('transactions', 'is_quotation')) {
                    $q->where('is_quotation', 0);
                }
                if (Schema::hasColumn('transactions', 'sub_status')) {
                    $q->where(function ($q2) {
                        $q2->whereNull('sub_status')->orWhere('sub_status', '!=', 'quotation');
                    });
                }
            },
            function ($transactionId) use ($businessId) {
                if ($this->transactionUtil->isReturnExist($transactionId)) {
                    $links = $this->returnFixLinks($businessId, $transactionId, 'sell_return', 'sell-return');
                    throw new ClearDataByDateBlockedException(__('lang_v1.return_exist'), $links, [
                        'transaction_id' => $transactionId,
                        'type' => 'sell',
                    ]);
                }

                $output = $this->transactionUtil->deleteSale($businessId, $transactionId);
                if (empty($output['success'])) {
                    throw new \Exception($output['msg'] ?? __('messages.something_went_wrong'));
                }
            },
            $progressTick,
            'sales'
        );
    }

    protected function deleteRepairs(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        if (! Schema::hasColumn('transactions', 'sub_type')) {
            return ['transactions' => 0];
        }

        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            fn ($q) => $q->where('type', 'sell')->where('status', 'final')->where('sub_type', 'repair'),
            function ($transactionId) use ($businessId) {
                if ($this->transactionUtil->isReturnExist($transactionId)) {
                    $links = $this->returnFixLinks($businessId, $transactionId, 'sell_return', 'sell-return');
                    throw new ClearDataByDateBlockedException(__('lang_v1.return_exist'), $links, [
                        'transaction_id' => $transactionId,
                        'type' => 'repair',
                    ]);
                }

                $output = $this->transactionUtil->deleteSale($businessId, $transactionId);
                if (empty($output['success'])) {
                    throw new \Exception($output['msg'] ?? __('messages.something_went_wrong'));
                }
            },
            $progressTick,
            'repair'
        );
    }

    protected function deleteDraftsQuotationsSuspended(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'sell')->where(function ($q2) {
                    $q2->where('status', 'draft');
                    if (Schema::hasColumn('transactions', 'is_suspend')) {
                        $q2->orWhere('is_suspend', 1);
                    }
                    if (Schema::hasColumn('transactions', 'is_quotation')) {
                        $q2->orWhere('is_quotation', 1);
                    }
                    if (Schema::hasColumn('transactions', 'sub_status')) {
                        $q2->orWhere('sub_status', 'quotation');
                    }
                });
            },
            function ($transactionId) use ($businessId) {
                $output = $this->transactionUtil->deleteSale($businessId, $transactionId);
                if (empty($output['success'])) {
                    throw new \Exception($output['msg'] ?? __('messages.something_went_wrong'));
                }
            },
            $progressTick,
            'drafts/quotations/suspended'
        );
    }

    protected function deletePurchases(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'purchase');
            },
            function ($transactionId) use ($businessId) {
                $transaction = Transaction::where('id', $transactionId)
                    ->where('business_id', $businessId)
                    ->with(['purchase_lines'])
                    ->first();

                if (empty($transaction)) {
                    return;
                }

                if ($this->transactionUtil->isReturnExist($transactionId)) {
                    $links = $this->returnFixLinks($businessId, $transactionId, 'purchase_return', 'purchase-return');
                    throw new ClearDataByDateBlockedException(__('lang_v1.return_exist'), $links, [
                        'transaction_id' => $transactionId,
                        'type' => 'purchase',
                    ]);
                }

                if (request()->session()->get('business.enable_lot_number') == 1 && $this->transactionUtil->isLotUsed($transaction)) {
                    $sell_links = $this->lotUsedFixLinks($businessId, $transaction);
                    throw new ClearDataByDateBlockedException(__('lang_v1.lot_numbers_are_used_in_sale'), $sell_links, [
                        'transaction_id' => $transactionId,
                        'type' => 'purchase',
                    ]);
                }

                $log_properities = [
                    'id' => $transaction->id,
                    'ref_no' => $transaction->ref_no,
                ];
                $this->transactionUtil->activityLog($transaction, 'purchase_deleted', $log_properities);

                $transaction_status = $transaction->status;
                $delete_purchase_lines = $transaction->purchase_lines;

                if ($transaction_status == 'received') {
                    $delete_purchase_line_ids = [];
                    foreach ($delete_purchase_lines as $purchase_line) {
                        $delete_purchase_line_ids[] = $purchase_line->id;
                        $this->productUtil->decreaseProductQuantity(
                            $purchase_line->product_id,
                            $purchase_line->variation_id,
                            $transaction->location_id,
                            $purchase_line->quantity
                        );
                    }

                    PurchaseLine::where('transaction_id', $transaction->id)
                        ->whereIn('id', $delete_purchase_line_ids)
                        ->delete();

                    $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($transaction_status, $transaction, $delete_purchase_lines);
                }

                $transaction->delete();

                AccountTransaction::where('transaction_id', $transactionId)->delete();

                if (Schema::hasTable('transaction_payments')) {
                    $payment_ids = DB::table('transaction_payments')->where('transaction_id', $transactionId)->pluck('id')->toArray();
                    if (! empty($payment_ids) && Schema::hasTable('account_transactions')) {
                        AccountTransaction::whereIn('transaction_payment_id', $payment_ids)->delete();
                    }
                }

                PurchaseCreatedOrModified::dispatch($transaction, true);
            },
            $progressTick,
            'purchases'
        );
    }

    protected function deleteSellReturns(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'sell_return');
            },
            function ($transactionId) use ($businessId) {
                $sell_return = Transaction::where('id', $transactionId)
                    ->where('business_id', $businessId)
                    ->where('type', 'sell_return')
                    ->with(['sell_lines', 'payment_lines'])
                    ->first();

                if (empty($sell_return)) {
                    return;
                }

                $sell_lines = TransactionSellLine::where('transaction_id', $sell_return->return_parent_id)->get();

                $transaction_payments = $sell_return->payment_lines;

                foreach ($sell_lines as $sell_line) {
                    if ($sell_line->quantity_returned > 0) {
                        $quantity_before = $this->transactionUtil->num_f($sell_line->quantity_returned);
                        $sell_line->quantity_returned = 0;
                        $sell_line->save();

                        $this->transactionUtil->updateQuantitySoldFromSellLine($sell_line, 0, $quantity_before);

                        $this->productUtil->updateProductQuantity(
                            $sell_return->location_id,
                            $sell_line->product_id,
                            $sell_line->variation_id,
                            0,
                            $quantity_before
                        );
                    }
                }

                $sell_return->delete();

                foreach ($transaction_payments as $payment) {
                    event(new \App\Events\TransactionPaymentDeleted($payment));
                }

                AccountTransaction::where('transaction_id', $transactionId)->delete();
            },
            $progressTick,
            'sell returns'
        );
    }

    protected function deletePurchaseReturns(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'purchase_return');
            },
            function ($transactionId) use ($businessId) {
                $purchase_return = Transaction::where('id', $transactionId)
                    ->where('business_id', $businessId)
                    ->where('type', 'purchase_return')
                    ->with(['purchase_lines'])
                    ->first();

                if (empty($purchase_return)) {
                    return;
                }

                if (empty($purchase_return->return_parent_id)) {
                    $delete_purchase_lines = $purchase_return->purchase_lines;
                    $delete_purchase_line_ids = [];
                    foreach ($delete_purchase_lines as $purchase_line) {
                        $delete_purchase_line_ids[] = $purchase_line->id;
                        $this->productUtil->updateProductQuantity(
                            $purchase_return->location_id,
                            $purchase_line->product_id,
                            $purchase_line->variation_id,
                            $purchase_line->quantity_returned,
                            0,
                            null,
                            false
                        );
                    }
                    PurchaseLine::where('transaction_id', $purchase_return->id)
                        ->whereIn('id', $delete_purchase_line_ids)
                        ->delete();
                } else {
                    $parent_purchase = Transaction::where('id', $purchase_return->return_parent_id)
                        ->where('business_id', $businessId)
                        ->where('type', 'purchase')
                        ->with(['purchase_lines'])
                        ->first();

                    if (! empty($parent_purchase)) {
                        foreach ($parent_purchase->purchase_lines as $purchase_line) {
                            $this->productUtil->updateProductQuantity(
                                $parent_purchase->location_id,
                                $purchase_line->product_id,
                                $purchase_line->variation_id,
                                $purchase_line->quantity_returned,
                                0,
                                null,
                                false
                            );
                            $purchase_line->quantity_returned = 0;
                            $purchase_line->save();
                        }
                    }
                }

                $purchase_return->delete();

                AccountTransaction::where('transaction_id', $transactionId)->delete();
            },
            $progressTick,
            'purchase returns'
        );
    }

    protected function deleteExpenses(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->whereIn('type', ['expense', 'expense_refund']);
            },
            function ($transactionId) use ($businessId) {
                $expense = Transaction::where('business_id', $businessId)
                    ->whereIn('type', ['expense', 'expense_refund'])
                    ->where('id', $transactionId)
                    ->first();

                if (empty($expense)) {
                    return;
                }

                $expense->cash_register_payments()->delete();
                $expense->delete();

                AccountTransaction::where('transaction_id', $transactionId)->delete();

                event(new ExpenseCreatedOrModified($expense, true));
            },
            $progressTick,
            'expenses'
        );
    }

    protected function deleteStockAdjustments(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'stock_adjustment');
            },
            function ($transactionId) use ($businessId) {
                $stock_adjustment = Transaction::where('id', $transactionId)
                    ->where('business_id', $businessId)
                    ->where('type', 'stock_adjustment')
                    ->with(['stock_adjustment_lines'])
                    ->first();

                if (empty($stock_adjustment)) {
                    return;
                }

                $stock_adjustment_lines = $stock_adjustment->stock_adjustment_lines;
                if (! empty($stock_adjustment_lines)) {
                    $line_ids = [];
                    foreach ($stock_adjustment_lines as $stock_adjustment_line) {
                        $this->productUtil->updateProductQuantity(
                            $stock_adjustment->location_id,
                            $stock_adjustment_line->product_id,
                            $stock_adjustment_line->variation_id,
                            $this->productUtil->num_f($stock_adjustment_line->quantity)
                        );
                        $line_ids[] = $stock_adjustment_line->id;
                    }

                    $this->transactionUtil->mapPurchaseQuantityForDeleteStockAdjustment($line_ids);
                }

                $stock_adjustment->delete();

                event(new StockAdjustmentCreatedOrModified($stock_adjustment, 'deleted'));
            },
            $progressTick,
            'stock adjustments'
        );
    }

    protected function deleteStockTransfers(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        return $this->deleteTransactionsByFilter(
            $businessId,
            $startDate,
            $endDate,
            $locationId,
            function ($q) {
                $q->where('type', 'sell_transfer');
            },
            function ($transactionId) use ($businessId, $startDate, $endDate) {
                $sell_transfer = Transaction::where('business_id', $businessId)
                    ->where('id', $transactionId)
                    ->where('type', 'sell_transfer')
                    ->with(['sell_lines'])
                    ->first();

                if (empty($sell_transfer)) {
                    return;
                }

                $purchase_transfer = Transaction::where('business_id', $businessId)
                    ->where('transfer_parent_id', $sell_transfer->id)
                    ->where('type', 'purchase_transfer')
                    ->with(['purchase_lines'])
                    ->first();

                if (empty($purchase_transfer)) {
                    throw new \Exception(__('messages.something_went_wrong'));
                }

                //Safety: never delete the pair if the linked transaction_date is outside the requested range.
                $purchase_transfer_date = Carbon::parse($purchase_transfer->transaction_date)->format('Y-m-d');
                if ($purchase_transfer_date < $startDate || $purchase_transfer_date > $endDate) {
                    throw new \Exception(__('messages.something_went_wrong'));
                }

                foreach ($purchase_transfer->purchase_lines as $purchase_line) {
                    if ($purchase_line->quantity_sold > 0) {
                        $links = $this->stockTransferSoldFixLinks($businessId, $purchase_transfer->id);
                        array_unshift($links, [
                            'label' => 'Open stock transfer',
                            'url' => url('/stock-transfers/'.$sell_transfer->id),
                        ]);
                        throw new ClearDataByDateBlockedException(__('lang_v1.stock_transfer_cannot_be_deleted'), $links, [
                            'sell_transfer_id' => $sell_transfer->id,
                            'purchase_transfer_id' => $purchase_transfer->id,
                        ]);
                    }
                }

                event(new StockTransferCreatedOrModified($sell_transfer, 'deleted'));

                $sell_lines = $sell_transfer->sell_lines;
                $deleted_sell_purchase_ids = [];
                $products = [];

                foreach ($sell_lines as $sell_line) {
                    $purchase_sell_line = TransactionSellLinesPurchaseLines::where('sell_line_id', $sell_line->id)->first();

                    if (! empty($purchase_sell_line)) {
                        PurchaseLine::where('id', $purchase_sell_line->purchase_line_id)
                            ->decrement('quantity_sold', $sell_line->quantity);

                        $deleted_sell_purchase_ids[] = $purchase_sell_line->id;

                        if (isset($products[$sell_line->variation_id])) {
                            $products[$sell_line->variation_id]['quantity'] += $sell_line->quantity;
                            $products[$sell_line->variation_id]['product_id'] = $sell_line->product_id;
                        } else {
                            $products[$sell_line->variation_id] = [
                                'quantity' => $sell_line->quantity,
                                'product_id' => $sell_line->product_id,
                            ];
                        }
                    }
                }

                if (! empty($products)) {
                    foreach ($products as $variation_id => $value) {
                        $this->productUtil->decreaseProductQuantity(
                            $value['product_id'],
                            $variation_id,
                            $purchase_transfer->location_id,
                            $value['quantity']
                        );

                        $this->productUtil->updateProductQuantity(
                            $sell_transfer->location_id,
                            $value['product_id'],
                            $variation_id,
                            $value['quantity']
                        );
                    }
                }

                if (! empty($deleted_sell_purchase_ids)) {
                    TransactionSellLinesPurchaseLines::whereIn('id', $deleted_sell_purchase_ids)->delete();
                }

                $sell_transfer->delete();
                $purchase_transfer->delete();

                AccountTransaction::whereIn('transaction_id', [$sell_transfer->id, $purchase_transfer->id])->delete();
            },
            $progressTick,
            'stock transfers'
        );
    }

    protected function deletePaymentsOnly(int $businessId, string $startDate, string $endDate, ?int $locationId, ?callable $progressTick): array
    {
        if (! Schema::hasTable('transaction_payments') || ! Schema::hasColumn('transaction_payments', 'business_id')) {
            return ['payments' => 0];
        }

        $query = TransactionPayment::where('business_id', $businessId);

        if (Schema::hasColumn('transaction_payments', 'paid_on')) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween(DB::raw('date(paid_on)'), [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNull('paid_on')
                            ->whereBetween(DB::raw('date(created_at)'), [$startDate, $endDate]);
                    });
            });
        } else {
            $query->whereBetween(DB::raw('date(created_at)'), [$startDate, $endDate]);
        }

        if (! empty($locationId)) {
            $query->whereIn('transaction_id', function ($q) use ($businessId, $locationId) {
                $q->select('id')
                    ->from('transactions')
                    ->where('business_id', $businessId)
                    ->where('location_id', $locationId);
            });
        }

        //Delete from latest to oldest to reduce dependency issues
        $deleted_count = 0;
        foreach ($query->lazyByIdDesc(200, 'id') as $payment) {
            TransactionPayment::deletePayment($payment);
            $deleted_count++;
            if (! empty($progressTick)) {
                $progressTick(1, 'Deleting payments...');
            }
        }

        return ['payments' => (int) $deleted_count];
    }

    protected function deleteActivityLogs(int $businessId, string $startDate, string $endDate, ?callable $progressTick): array
    {
        $table = config('activitylog.table_name');
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'business_id')) {
            return ['activity_logs' => 0];
        }

        $count = DB::table($table)
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('date(created_at)'), [$startDate, $endDate])
            ->count('id');

        DB::table($table)
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('date(created_at)'), [$startDate, $endDate])
            ->delete();

        if (! empty($progressTick)) {
            $progressTick($count, 'Deleting activity logs...');
        }

        return ['activity_logs' => (int) $count];
    }

    protected function deleteTransactionsByFilter(
        int $businessId,
        string $startDate,
        string $endDate,
        ?int $locationId,
        callable $transactionFilter,
        callable $deleter,
        ?callable $progressTick,
        string $label
    ): array {
        $deleted = [
            'transactions' => 0,
            'sell_lines' => 0,
            'purchase_lines' => 0,
            'payments' => 0,
            'total_amount' => 0,
            'payments_amount' => 0,
        ];

        $query = Transaction::where('business_id', $businessId)
            ->whereBetween(DB::raw('date(transaction_date)'), [$startDate, $endDate]);

        if (! empty($locationId)) {
            $query->where('location_id', $locationId);
        }

        $transactionFilter($query);

        $query->select('id', 'final_total');

        //Process from latest to oldest to reduce dependency issues (e.g. stock transfer consumed by later sells)
        foreach ($query->lazyByIdDesc(100, 'id') as $tx) {
            $counts = $this->countsForSingleTransaction($businessId, $tx->id);
            $was_skipped = false;
            try {
                $deleter($tx->id);
            } catch (ClearDataByDateBlockedException $e) {
                if ($this->continueOnBlocked) {
                    $this->skipped[] = [
                        'module' => $label,
                        'transaction_id' => $tx->id,
                        'message' => $e->getMessage(),
                        'fix_links' => $e->fixLinks(),
                        'context' => $e->context(),
                    ];
                    $was_skipped = true;
                } else {
                    throw $e;
                }
            } catch (PurchaseSellMismatch $e) {
                throw new \Exception($e->getMessage());
            }

            //Progress should move even if blocked/skipped
            if (! empty($progressTick)) {
                $progressTick(1, 'Deleting '.$label.'...');
            }

            if ($was_skipped) {
                continue;
            }

            $deleted['transactions']++;
            $deleted['sell_lines'] += $counts['sell_lines'];
            $deleted['purchase_lines'] += $counts['purchase_lines'];
            $deleted['payments'] += $counts['payments'];
            $deleted['total_amount'] += $counts['total_amount'];
            $deleted['payments_amount'] += $counts['payments_amount'];
        }

        return $deleted;
    }

    protected function countsForSingleTransaction(int $businessId, int $transactionId): array
    {
        $sell_lines = Schema::hasTable('transaction_sell_lines')
            ? (int) DB::table('transaction_sell_lines')->where('transaction_id', $transactionId)->count('id')
            : 0;

        $purchase_lines = Schema::hasTable('purchase_lines')
            ? (int) DB::table('purchase_lines')->where('transaction_id', $transactionId)->count('id')
            : 0;

        $payments = Schema::hasTable('transaction_payments')
            ? (int) DB::table('transaction_payments')->where('transaction_id', $transactionId)->count('id')
            : 0;

        $payments_amount = Schema::hasTable('transaction_payments')
            ? (float) DB::table('transaction_payments')->where('transaction_id', $transactionId)->sum('amount')
            : 0.0;

        $total_amount = (float) DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('id', $transactionId)
            ->sum('final_total');

        return [
            'sell_lines' => $sell_lines,
            'purchase_lines' => $purchase_lines,
            'payments' => $payments,
            'total_amount' => $total_amount,
            'payments_amount' => $payments_amount,
        ];
    }

    protected function baseTransactionQuery(int $businessId, string $startDate, string $endDate, ?int $locationId)
    {
        return DB::table('transactions')
            ->where('transactions.business_id', $businessId)
            ->whereBetween(DB::raw('date(transactions.transaction_date)'), [$startDate, $endDate])
            ->when(! empty($locationId), function ($q) use ($locationId) {
                $q->where('transactions.location_id', $locationId);
            });
    }

    protected function transactionFilterForModule(string $module): callable
    {
        return match ($module) {
            'sales' => function ($q) {
                $q->where('type', 'sell')->where('status', 'final');
                if (Schema::hasColumn('transactions', 'sub_type')) {
                    $q->where(function ($q2) {
                        $q2->whereNull('sub_type')->orWhere('sub_type', '!=', 'repair');
                    });
                }
                if (Schema::hasColumn('transactions', 'is_suspend')) {
                    $q->where('is_suspend', 0);
                }
                if (Schema::hasColumn('transactions', 'is_quotation')) {
                    $q->where('is_quotation', 0);
                }
                if (Schema::hasColumn('transactions', 'sub_status')) {
                    $q->where(function ($q2) {
                        $q2->whereNull('sub_status')->orWhere('sub_status', '!=', 'quotation');
                    });
                }
            },
            'repair' => fn ($q) => $q->where('type', 'sell')->where('status', 'final')->where('sub_type', 'repair'),
            'purchases' => fn ($q) => $q->where('type', 'purchase'),
            'sell_returns' => fn ($q) => $q->where('type', 'sell_return'),
            'purchase_returns' => fn ($q) => $q->where('type', 'purchase_return'),
            'expenses' => fn ($q) => $q->whereIn('type', ['expense', 'expense_refund']),
            'stock_adjustments' => fn ($q) => $q->where('type', 'stock_adjustment'),
            'stock_transfers' => fn ($q) => $q->where('type', 'sell_transfer'),
            'drafts_quotations_suspended' => function ($q) {
                $q->where('type', 'sell')->where(function ($q2) {
                    $q2->where('status', 'draft');
                    if (Schema::hasColumn('transactions', 'is_suspend')) {
                        $q2->orWhere('is_suspend', 1);
                    }
                    if (Schema::hasColumn('transactions', 'is_quotation')) {
                        $q2->orWhere('is_quotation', 1);
                    }
                    if (Schema::hasColumn('transactions', 'sub_status')) {
                        $q2->orWhere('sub_status', 'quotation');
                    }
                });
            },
            default => fn ($q) => $q,
        };
    }

    protected function makeProgressTracker(array $modules, array $previewCounts, ?callable $progressCallback): array
    {
        $total_units = 0;

        foreach ($modules as $module) {
            if (! isset($previewCounts[$module]) || ! is_array($previewCounts[$module])) {
                continue;
            }
            $counts = $previewCounts[$module];
            if (isset($counts['transactions'])) {
                $total_units += (int) $counts['transactions'];
            } elseif ($module === 'payments_only' && isset($counts['payments'])) {
                $total_units += (int) $counts['payments'];
            } elseif ($module === 'activity_logs' && isset($counts['activity_logs'])) {
                $total_units += (int) $counts['activity_logs'];
            }
        }

        $step_based = $total_units <= 0;
        if ($step_based) {
            $total_units = max(1, count($modules));
        }

        $completed_units = 0;
        $last_percent = -1;
        $last_message = null;

        $tick = function (int $addUnits, ?string $message = null) use (
            &$completed_units,
            &$last_percent,
            &$last_message,
            $total_units,
            $progressCallback
        ) {
            $completed_units += max(0, $addUnits);
            $percent = (int) floor(($completed_units / max(1, $total_units)) * 100);
            $percent = max(0, min(100, $percent));

            $should_call = false;
            if ($percent !== $last_percent) {
                $last_percent = $percent;
                $should_call = true;
            }
            if (! empty($message) && $message !== $last_message) {
                $last_message = $message;
                $should_call = true;
            }

            if ($should_call && ! empty($progressCallback)) {
                $progressCallback($percent, $message);
            }
        };

        $setMessage = function (?string $message) use ($tick) {
            if (! empty($message)) {
                $tick(0, $message);
            }
        };

        return [
            'stepBased' => $step_based,
            'tick' => $tick,
            'setMessage' => $setMessage,
        ];
    }

    protected function lotUsedFixLinks(int $businessId, Transaction $purchase): array
    {
        if (empty($purchase->purchase_lines)) {
            return [];
        }

        $purchase_line_ids = $purchase->purchase_lines->pluck('id')->toArray();
        if (empty($purchase_line_ids)) {
            return [];
        }

        $sell_tx_ids = TransactionSellLine::whereIn('lot_no_line_id', $purchase_line_ids)
            ->distinct()
            ->pluck('transaction_id')
            ->take(10)
            ->toArray();

        if (empty($sell_tx_ids)) {
            return [];
        }

        $sells = Transaction::where('business_id', $businessId)
            ->whereIn('id', $sell_tx_ids)
            ->select('id', 'invoice_no', 'transaction_date')
            ->orderByDesc('id')
            ->get();

        $links = [];
        foreach ($sells as $sell) {
            $label = ! empty($sell->invoice_no) ? ('Invoice '.$sell->invoice_no) : ('Sell #'.$sell->id);
            $links[] = [
                'label' => $label,
                'url' => url('/sells/'.$sell->id),
            ];
        }

        return $links;
    }

    protected function returnFixLinks(int $businessId, int $parentId, string $returnType, string $returnPath): array
    {
        $return_tx = Transaction::where('business_id', $businessId)
            ->where('return_parent_id', $parentId)
            ->where('type', $returnType)
            ->select('id', 'invoice_no', 'ref_no')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        $links = [];
        foreach ($return_tx as $tx) {
            $label = ! empty($tx->invoice_no) ? ($tx->invoice_no) : (! empty($tx->ref_no) ? $tx->ref_no : ('#'.$tx->id));
            $links[] = [
                'label' => $returnType.' '.$label,
                'url' => url('/'.$returnPath.'/'.$tx->id),
            ];
        }

        return $links;
    }

    protected function stockTransferSoldFixLinks(int $businessId, int $purchaseTransferId): array
    {
        $purchase_line_ids = PurchaseLine::where('transaction_id', $purchaseTransferId)
            ->where('quantity_sold', '>', 0)
            ->pluck('id')
            ->toArray();

        if (empty($purchase_line_ids)) {
            return [];
        }

        $sell_tx_ids = TransactionSellLinesPurchaseLines::whereIn('purchase_line_id', $purchase_line_ids)
            ->join('transaction_sell_lines as tsl', 'tsl.id', '=', 'transaction_sell_lines_purchase_lines.sell_line_id')
            ->distinct()
            ->pluck('tsl.transaction_id')
            ->take(10)
            ->toArray();

        //Fallback: some installs rely on lot_no_line_id mapping instead of tspl mapping.
        if (empty($sell_tx_ids)) {
            $sell_tx_ids = TransactionSellLine::whereIn('lot_no_line_id', $purchase_line_ids)
                ->distinct()
                ->pluck('transaction_id')
                ->take(10)
                ->toArray();
        }

        if (empty($sell_tx_ids)) {
            return [];
        }

        $sells = Transaction::where('business_id', $businessId)
            ->whereIn('id', $sell_tx_ids)
            ->select('id', 'invoice_no')
            ->orderByDesc('id')
            ->get();

        $links = [];
        foreach ($sells as $sell) {
            $label = ! empty($sell->invoice_no) ? ('Invoice '.$sell->invoice_no) : ('Sell #'.$sell->id);
            $links[] = [
                'label' => $label,
                'url' => url('/sells/'.$sell->id),
            ];
        }

        return $links;
    }

    protected function dependentSellIdsForPurchaseLines(array $purchaseLineIds): array
    {
        $sell_tx_ids = TransactionSellLinesPurchaseLines::whereIn('purchase_line_id', $purchaseLineIds)
            ->join('transaction_sell_lines as tsl', 'tsl.id', '=', 'transaction_sell_lines_purchase_lines.sell_line_id')
            ->distinct()
            ->pluck('tsl.transaction_id')
            ->take(100)
            ->toArray();

        if (! empty($sell_tx_ids)) {
            return $sell_tx_ids;
        }

        return TransactionSellLine::whereIn('lot_no_line_id', $purchaseLineIds)
            ->distinct()
            ->pluck('transaction_id')
            ->take(100)
            ->toArray();
    }

    protected function deleteStockTransferPair(
        int $businessId,
        string $startDate,
        string $endDate,
        ?int $locationId,
        int $sellTransferId,
        int $purchaseTransferId
    ): void {
        $sell_transfer = Transaction::where('business_id', $businessId)
            ->where('id', $sellTransferId)
            ->where('type', 'sell_transfer')
            ->whereBetween(DB::raw('date(transaction_date)'), [$startDate, $endDate])
            ->when(! empty($locationId), function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            })
            ->with(['sell_lines'])
            ->first();

        if (empty($sell_transfer)) {
            throw new \Exception('Stock transfer is not in selected date range/location.');
        }

        $purchase_transfer = Transaction::where('business_id', $businessId)
            ->where('id', $purchaseTransferId)
            ->where('type', 'purchase_transfer')
            ->where('transfer_parent_id', $sell_transfer->id)
            ->with(['purchase_lines'])
            ->first();

        if (empty($purchase_transfer)) {
            throw new \Exception(__('messages.something_went_wrong'));
        }

        $purchase_transfer_date = Carbon::parse($purchase_transfer->transaction_date)->format('Y-m-d');
        if ($purchase_transfer_date < $startDate || $purchase_transfer_date > $endDate) {
            throw new \Exception(__('messages.something_went_wrong'));
        }

        foreach ($purchase_transfer->purchase_lines as $purchase_line) {
            if ($purchase_line->quantity_sold > 0) {
                $links = $this->stockTransferSoldFixLinks($businessId, $purchase_transfer->id);
                array_unshift($links, [
                    'label' => 'Open stock transfer',
                    'url' => url('/stock-transfers/'.$sell_transfer->id),
                ]);
                throw new ClearDataByDateBlockedException(__('lang_v1.stock_transfer_cannot_be_deleted'), $links, [
                    'sell_transfer_id' => $sell_transfer->id,
                    'purchase_transfer_id' => $purchase_transfer->id,
                ]);
            }
        }

        $sell_lines = $sell_transfer->sell_lines;
        $deleted_sell_purchase_ids = [];
        $products = [];

        foreach ($sell_lines as $sell_line) {
            $purchase_sell_line = TransactionSellLinesPurchaseLines::where('sell_line_id', $sell_line->id)->first();

            if (! empty($purchase_sell_line)) {
                PurchaseLine::where('id', $purchase_sell_line->purchase_line_id)
                    ->decrement('quantity_sold', $sell_line->quantity);

                $deleted_sell_purchase_ids[] = $purchase_sell_line->id;

                if (isset($products[$sell_line->variation_id])) {
                    $products[$sell_line->variation_id]['quantity'] += $sell_line->quantity;
                    $products[$sell_line->variation_id]['product_id'] = $sell_line->product_id;
                } else {
                    $products[$sell_line->variation_id] = [
                        'quantity' => $sell_line->quantity,
                        'product_id' => $sell_line->product_id,
                    ];
                }
            }
        }

        if (! empty($products)) {
            foreach ($products as $variation_id => $value) {
                $this->productUtil->decreaseProductQuantity(
                    $value['product_id'],
                    $variation_id,
                    $purchase_transfer->location_id,
                    $value['quantity']
                );

                $this->productUtil->updateProductQuantity(
                    $sell_transfer->location_id,
                    $value['product_id'],
                    $variation_id,
                    $value['quantity']
                );
            }
        }

        if (! empty($deleted_sell_purchase_ids)) {
            TransactionSellLinesPurchaseLines::whereIn('id', $deleted_sell_purchase_ids)->delete();
        }

        $sell_transfer->delete();
        $purchase_transfer->delete();

        AccountTransaction::whereIn('transaction_id', [$sell_transfer->id, $purchase_transfer->id])->delete();
    }
}
