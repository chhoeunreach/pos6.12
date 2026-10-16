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

    public function previewAll(int $businessId, int $userId, string $reason): array
    {
        return app(AuthorityFullRepairService::class)->preview($businessId, $userId, $reason);
    }

    public function reconcileAll(int $businessId, ?string $reason = null, ?int $userId = null, ?string $ipAddress = null, ?string $token = null): array
    {
        if (! $token || ! $userId) {
            throw new \App\Exceptions\PurchaseSellMismatch('Generate a full repair preview and supply its token and administrator before applying.');
        }
        return app(AuthorityFullRepairService::class)->apply($businessId, $userId, (string) $reason, $token, $ipAddress);
    }
}
