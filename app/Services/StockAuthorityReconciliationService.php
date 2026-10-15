<?php

namespace App\Services;

use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockAuthorityReconciliationService
{
    public const DETAIL_CATEGORIES = [
        'over_allocated' => 'Over-Allocated Lines',
        'missing_selected' => 'Missing Selected Lots',
        'differing_allocations' => 'Differing Allocations',
        'all_line_issues' => 'Total Sale Line Issues',
    ];

    public function __construct(
        private LotStockAuditService $auditService,
        private TransactionUtil $transactionUtil,
        private ProductUtil $productUtil
    ) {}

    public function mismatchDetails(int $businessId, string $category, string $reference = '')
    {
        if (!array_key_exists($category, self::DETAIL_CATEGORIES)) {
            throw new \InvalidArgumentException('Unknown Authority issue category.');
        }
        $issues = collect($this->auditService->audit($businessId)['sale_line_issues'])->keyBy('sell_line_id');
        $reason = ['missing_selected' => 'Selected purchase lot is missing.',
            'differing_allocations' => 'Stock allocation differs from the invoice selected lot.'][$category] ?? null;
        $ids = $issues->filter(fn ($issue) => !$reason || in_array($reason, $issue['reasons'], true))->keys();
        $query = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->leftJoin('products as p', function ($join) use ($businessId) {
                $join->on('p.id', '=', 's.product_id')->where('p.business_id', $businessId);
            })->leftJoin('variations as v', function ($join) {
                $join->on('v.id', '=', 's.variation_id')->on('v.product_id', '=', 'p.id');
            })->where('t.business_id', $businessId)->where('t.status', 'final')->whereIn('t.type', ['sell', 'sell_transfer']);
        if ($category === 'over_allocated') {
            $query->whereIn('s.id', $this->overAllocatedLines($businessId));
        } else {
            $query->whereIn('s.id', $ids);
        }
        if ($reference !== '') {
            $query->where(fn ($q) => $q->where('t.ref_no', 'like', '%'.$reference.'%')->orWhere('t.invoice_no', 'like', '%'.$reference.'%'));
        }
        $rows = $query->orderByDesc('t.id')->orderBy('s.id')
            ->select('s.id', 's.transaction_id', 's.lot_no_line_id', 's.quantity', 't.type', 't.ref_no', 't.invoice_no', 't.transaction_date', 'p.name as product_name', 'v.sub_sku')
            ->paginate(25)->withQueryString();
        $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $rows->getCollection()->pluck('id'))->orderBy('id')->get()->groupBy('sell_line_id');
        $batchIds = $rows->getCollection()->pluck('lot_no_line_id')->merge($mappings->flatten(1)->pluck('purchase_line_id'))->filter()->unique();
        $batches = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.business_id', $businessId)->whereIn('p.id', $batchIds)->get(['p.id', 'p.lot_number'])->keyBy('id');
        foreach ($rows as $row) {
            $row->selected_batch = $batches->get($row->lot_no_line_id);
            $row->allocations = $mappings->get($row->id, collect())->map(function ($mapping) use ($batches) {
                $mapping->batch = $batches->get($mapping->purchase_line_id);

                return $mapping;
            });
            $row->allocated_quantity = (float) $row->allocations->sum('quantity');
            $row->reasons = $issues->get($row->id)['reasons'] ?? [];
            if ($category === 'over_allocated' && !in_array('Allocated quantity exceeds line quantity.', $row->reasons, true)) {
                $row->reasons[] = 'Allocated quantity exceeds line quantity.';
            }
        }

        return $rows;
    }

    private function overAllocatedLines(int $businessId)
    {
        return DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->join('transaction_sell_lines_purchase_lines as m', 'm.sell_line_id', '=', 's.id')
            ->where('t.business_id', $businessId)->whereIn('t.type', ['sell', 'sell_transfer'])->where('t.status', 'final')
            ->groupBy('s.id', 's.quantity')->havingRaw('SUM(m.quantity) > s.quantity + 0.0001')->select('s.id');
    }

    public function auditMismatches(int $businessId, ?array $audit = null): array
    {
        $audit = $audit ?? $this->auditService->audit($businessId);
        $issues = collect($audit['sale_line_issues']);

        $overAllocatedCount = $this->overAllocatedLines($businessId)->get()->count();

        $missingSelectedCount = $issues->filter(fn ($i) => in_array('Selected purchase lot is missing.', $i['reasons']))->count();
        $diffAllocationCount = $issues->filter(fn ($i) => in_array('Stock allocation differs from the invoice selected lot.', $i['reasons']))->count();
        $datedAfterCount = $issues->filter(fn ($i) => in_array('Stock allocation uses a receipt dated after the transfer.', $i['reasons']))->count();

        $saleTransferMismatchIds = $issues->where('type', 'sell_transfer')->pluck('transaction_id')->all();
        $receiptMismatchIds = collect($audit['transfer_issues'])->pluck('transaction_id')->all();
        $totalMismatchTransfers = count(array_unique(array_merge($saleTransferMismatchIds, $receiptMismatchIds)));

        return [
            'total_sale_line_issues' => $issues->count(),
            'total_stock_issues' => count($audit['stock_issues']),
            'total_transfer_issues' => count($audit['transfer_issues']),
            'total_mismatch_transfers' => $totalMismatchTransfers,
            'over_allocated_count' => $overAllocatedCount,
            'missing_selected_count' => $missingSelectedCount,
            'diff_allocation_count' => $diffAllocationCount,
            'dated_after_count' => $datedAfterCount,
        ];
    }

    public function getTransferMismatchMap(int $businessId): array
    {
        $audit = $this->auditService->audit($businessId);

        $saleLineMismatchMap = [];
        foreach ($audit['sale_line_issues'] as $issue) {
            if ($issue['type'] === 'sell_transfer') {
                $id = (int) $issue['transaction_id'];
                $saleLineMismatchMap[$id] = array_merge($saleLineMismatchMap[$id] ?? [], $issue['reasons']);
            }
        }

        $receiptMismatchMap = [];
        foreach ($audit['transfer_issues'] as $issue) {
            $id = (int) $issue['transaction_id'];
            $receiptMismatchMap[$id] = array_merge($receiptMismatchMap[$id] ?? [], [$issue['reason']]);
        }

        $allMismatchMap = [];
        foreach ($saleLineMismatchMap as $id => $reasons) {
            $allMismatchMap[$id] = array_values(array_unique(array_merge($allMismatchMap[$id] ?? [], $reasons)));
        }
        foreach ($receiptMismatchMap as $id => $reasons) {
            $allMismatchMap[$id] = array_values(array_unique(array_merge($allMismatchMap[$id] ?? [], $reasons)));
        }

        return [
            'all_mismatch_ids' => array_keys($allMismatchMap),
            'receipt_mismatch_ids' => array_keys($receiptMismatchMap),
            'sale_line_mismatch_ids' => array_keys($saleLineMismatchMap),
            'mismatch_map' => $allMismatchMap,
            'total_mismatches_count' => count($allMismatchMap),
            'audit' => $audit,
        ];
    }

    public function reconcileAll(int $businessId, ?string $reason = null, ?int $userId = null, ?string $ipAddress = null): array
    {
        $reason = $reason ?: 'Full authority mismatch fix and data regeneration';
        $userId = $userId ?: (auth()->id() ?? 1);

        $initialAudit = $this->auditService->audit($businessId);
        $beforeSaleIssues = count($initialAudit['sale_line_issues']);
        $beforeStockIssues = count($initialAudit['stock_issues']);
        $beforeTransferIssues = count($initialAudit['transfer_issues']);

        $affectedPlIds = collect();
        $affectedVariations = collect(); // format: variation_id:location_id

        $fixedOverAllocated = 0;
        $fixedMissingSelected = 0;
        $fixedRealignedAllocations = 0;

        DB::transaction(function () use (
            $businessId,
            &$affectedPlIds,
            &$affectedVariations,
            &$fixedOverAllocated,
            &$fixedMissingSelected,
            &$fixedRealignedAllocations,
            $initialAudit
        ) {
            // -------------------------------------------------------------
            // STEP 1: Fix Over-allocated lines with duplicate/ghost mappings
            // (e.g. Line #2328, Txn 2343, Txn 10058, Txn 10823, Txn 22730)
            // -------------------------------------------------------------
            $overAllocated = DB::table('transaction_sell_lines as s')
                ->join('transactions as t', 't.id', '=', 's.transaction_id')
                ->join('transaction_sell_lines_purchase_lines as m', 'm.sell_line_id', '=', 's.id')
                ->where('t.business_id', $businessId)
                ->whereIn('t.type', ['sell', 'sell_transfer'])
                ->where('t.status', 'final')
                ->groupBy('s.id', 's.quantity', 's.lot_no_line_id', 's.variation_id', 't.location_id')
                ->havingRaw('SUM(m.quantity) > s.quantity + 0.0001')
                ->select('s.id', 's.quantity', 's.lot_no_line_id', 's.variation_id', 't.location_id')
                ->get();

            foreach ($overAllocated as $line) {
                $maps = DB::table('transaction_sell_lines_purchase_lines')
                    ->where('sell_line_id', $line->id)
                    ->orderBy('id')
                    ->get();

                $matchMap = $maps->first(fn ($m) => (int) $m->purchase_line_id === (int) $line->lot_no_line_id);
                if ($matchMap) {
                    $toDelete = $maps->filter(fn ($m) => $m->id !== $matchMap->id);
                    foreach ($toDelete as $del) {
                        if ($del->purchase_line_id > 0) {
                            $affectedPlIds->push((int) $del->purchase_line_id);
                        }
                        DB::table('transaction_sell_lines_purchase_lines')->where('id', $del->id)->delete();
                    }
                } else {
                    $keptQty = 0;
                    foreach ($maps as $m) {
                        if ($keptQty < (float) $line->quantity) {
                            $keptQty += (float) $m->quantity;
                        } else {
                            if ($m->purchase_line_id > 0) {
                                $affectedPlIds->push((int) $m->purchase_line_id);
                            }
                            DB::table('transaction_sell_lines_purchase_lines')->where('id', $m->id)->delete();
                        }
                    }
                }
                $affectedVariations->push($line->variation_id.':'.$line->location_id);
                $fixedOverAllocated++;
            }

            // -------------------------------------------------------------
            // STEP 2: Fix lines where selected lot is missing but valid allocation exists
            // (Update s.lot_no_line_id = allocated purchase_line_id)
            // -------------------------------------------------------------
            $missingSelectedIssues = collect($initialAudit['sale_line_issues'])->filter(fn ($i) =>
                in_array('Selected purchase lot is missing.', $i['reasons']) &&
                in_array('Stock allocation differs from the invoice selected lot.', $i['reasons'])
            );

            foreach ($missingSelectedIssues as $issue) {
                $allocs = collect($issue['allocations']);
                if ($allocs->count() === 1 && $allocs[0]->purchase_line_id > 0) {
                    $allocPlId = (int) $allocs[0]->purchase_line_id;
                    $validPl = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                        ->where('p.id', $allocPlId)->where('t.business_id', $businessId)->first(['p.id', 'p.variation_id', 't.location_id']);

                    if ($validPl) {
                        DB::table('transaction_sell_lines')->where('id', $issue['sell_line_id'])->update([
                            'lot_no_line_id' => $allocPlId,
                            'updated_at' => now(),
                        ]);
                        $affectedPlIds->push($allocPlId);
                        $affectedVariations->push($validPl->variation_id.':'.$validPl->location_id);
                        $fixedMissingSelected++;
                    }
                }
            }

            // -------------------------------------------------------------
            // STEP 3: Realign allocations for lines where allocation differs from selected lot
            // -------------------------------------------------------------
            $allDiffIssues = collect($initialAudit['sale_line_issues'])->filter(fn ($i) =>
                in_array('Stock allocation differs from the invoice selected lot.', $i['reasons'])
            );

            foreach ($allDiffIssues as $issue) {
                $selectedPlId = (int) $issue['selected_purchase_line_id'];
                $slId = (int) $issue['sell_line_id'];

                if ($selectedPlId <= 0) {
                    continue;
                }

                $selectedPl = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                    ->where('p.id', $selectedPlId)->where('t.business_id', $businessId)
                    ->first(['p.*', 't.location_id', 't.status', 't.transaction_date as receipt_date']);

                if (! $selectedPl || $selectedPl->status !== 'received' || (int) $selectedPl->location_id !== (int) $issue['location_id']) {
                    continue;
                }

                // For sell_transfer, ensure receipt arrived on or before transfer date
                if ($issue['type'] === 'sell_transfer') {
                    if (\Carbon\Carbon::parse($selectedPl->receipt_date)->gt(\Carbon\Carbon::parse($issue['transaction_date']))) {
                        continue;
                    }
                }

                $oldAllocs = DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $slId)->get();
                foreach ($oldAllocs as $oa) {
                    if ($oa->purchase_line_id > 0) {
                        $affectedPlIds->push((int) $oa->purchase_line_id);
                    }
                }

                DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $slId)->delete();
                DB::table('transaction_sell_lines_purchase_lines')->insert([
                    'sell_line_id' => $slId,
                    'purchase_line_id' => $selectedPlId,
                    'quantity' => $issue['quantity'],
                    'qty_returned' => $issue['quantity_returned'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $affectedPlIds->push($selectedPlId);
                $affectedVariations->push($selectedPl->variation_id.':'.$selectedPl->location_id);
                $fixedRealignedAllocations++;
            }

            // -------------------------------------------------------------
            // STEP 4: Recalculate purchase_lines.quantity_sold
            // -------------------------------------------------------------
            $uniquePlIds = $affectedPlIds->unique()->filter()->values();
            foreach ($uniquePlIds->chunk(200) as $chunk) {
                $counters = DB::table('purchase_lines as p')
                    ->whereIn('p.id', $chunk)
                    ->select('p.id', 'p.quantity_sold')
                    ->selectRaw($this->transactionUtil->lotQuantitySoldSql('p', true).' as verified_sold')
                    ->get();

                foreach ($counters as $c) {
                    if (abs((float) $c->quantity_sold - (float) $c->verified_sold) > 0.0001) {
                        DB::table('purchase_lines')->where('id', $c->id)->update([
                            'quantity_sold' => $c->verified_sold,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // -------------------------------------------------------------
            // STEP 5: Sync variation_location_details.qty_available
            // -------------------------------------------------------------
            $uniqueVarLocs = $affectedVariations->unique()->filter()->values();
            foreach ($uniqueVarLocs as $varLoc) {
                [$varId, $locId] = explode(':', $varLoc);
                $details = $this->productUtil->getVariationStockMisMatch($businessId, (int) $varId, (int) $locId)->first();
                if ($details && $details->enable_stock) {
                    $calculated = (float) $details->total_stock_calculated;
                    if (abs((float) $details->stock - $calculated) > 0.0001) {
                        $this->productUtil->fixVariationStockMisMatch($businessId, (int) $varId, (int) $locId, $calculated);
                    }
                }
            }
        });

        // -------------------------------------------------------------
        // STEP 6: Run final audit and log
        // -------------------------------------------------------------
        $newAudit = $this->auditService->audit($businessId);
        $afterSaleIssues = count($newAudit['sale_line_issues']);
        $afterStockIssues = count($newAudit['stock_issues']);
        $afterTransferIssues = count($newAudit['transfer_issues']);

        $line2328 = collect($newAudit['sale_line_issues'])->firstWhere('sell_line_id', 2328);

        $result = [
            'success' => 1,
            'over_allocated_fixed' => $fixedOverAllocated,
            'missing_selected_fixed' => $fixedMissingSelected,
            'realigned_allocations_fixed' => $fixedRealignedAllocations,
            'purchase_lines_recalculated' => $affectedPlIds->unique()->count(),
            'variations_recalculated' => $affectedVariations->unique()->count(),
            'before' => [
                'sale_line_issues' => $beforeSaleIssues,
                'stock_issues' => $beforeStockIssues,
                'transfer_issues' => $beforeTransferIssues,
            ],
            'after' => [
                'sale_line_issues' => $afterSaleIssues,
                'stock_issues' => $afterStockIssues,
                'transfer_issues' => $afterTransferIssues,
            ],
            'line_2328_clean' => $line2328 === null,
            'message' => "Authority mismatch fix applied: {$fixedOverAllocated} over-allocated lines cleaned, {$fixedMissingSelected} missing lots repaired, {$fixedRealignedAllocations} allocations realigned. Total issues reduced from {$beforeSaleIssues} to {$afterSaleIssues}.",
        ];

        // Log if action log exists
        if (Schema::hasTable('smart_stock_action_logs')) {
            try {
                DB::table('smart_stock_action_logs')->insert([
                    'user_id' => $userId,
                    'user_name' => auth()->user()?->username ?: 'Authority Administrator',
                    'business_id' => $businessId,
                    'module_name' => 'SmartStockInventory',
                    'table_name' => 'transaction_sell_lines_purchase_lines',
                    'record_id' => 0,
                    'location_id' => 0,
                    'action_type' => 'authority_full_mismatch_fix',
                    'reference_type' => 'authority_audit',
                    'reference_id' => 0,
                    'old_data' => json_encode($result['before']),
                    'new_data' => json_encode($result['after']),
                    'reason' => $reason,
                    'ip_address' => $ipAddress ?: request()->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Authority fix logged warning: '.$e->getMessage());
            }
        }

        return $result;
    }
}
