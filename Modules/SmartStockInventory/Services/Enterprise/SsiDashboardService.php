<?php

namespace Modules\SmartStockInventory\Services\Enterprise;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Repositories\Enterprise\SsiAuditRepository;

class SsiDashboardService
{
    public function __construct(private SsiAuditRepository $audits)
    {
    }

    public function metrics(int $businessId, ?int $locationId = null): array
    {
        return Cache::remember($this->cacheKey($businessId, $locationId), now()->addMinutes(5), function () use ($businessId, $locationId) {
            $stock = DB::table('variation_location_details as vld')
                ->join('products as p', 'vld.product_id', '=', 'p.id')
                ->leftJoin('variations as v', 'vld.variation_id', '=', 'v.id')
                ->where('p.business_id', $businessId)
                ->when($locationId, fn ($q) => $q->where('vld.location_id', $locationId));

            $currentInventory = (clone $stock)->sum('vld.qty_available');
            $negativeStock = (clone $stock)->where('vld.qty_available', '<', 0)->count();
            $lowStock = (clone $stock)->whereColumn('vld.qty_available', '<=', 'p.alert_quantity')->count();
            $inventoryValue = (clone $stock)->sum(DB::raw('vld.qty_available * COALESCE(v.default_purchase_price, 0)'));
            $audit = $this->audits->auditSummary($businessId, $locationId);

            $deadStock = DB::table('variation_location_details as vld')
                ->join('products as p', 'vld.product_id', '=', 'p.id')
                ->where('p.business_id', $businessId)
                ->when($locationId, fn ($q) => $q->where('vld.location_id', $locationId))
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('transaction_sell_lines as tsl')
                        ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
                        ->whereColumn('tsl.variation_id', 'vld.variation_id')
                        ->where('t.type', 'sell')
                        ->where('t.status', 'final')
                        ->where('t.transaction_date', '>=', now()->subDays(90));
                })
                ->count();

            return array_merge($audit, [
                'current_inventory' => (float) $currentInventory,
                'inventory_value' => (float) $inventoryValue,
                'low_stock' => $lowStock,
                'negative_stock' => $negativeStock,
                'dead_stock' => $deadStock,
                'fast_moving' => $this->movingProducts($businessId, $locationId, 'desc'),
                'slow_moving' => $this->movingProducts($businessId, $locationId, 'asc'),
            ]);
        });
    }

    private function movingProducts(int $businessId, ?int $locationId, string $direction)
    {
        return DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.transaction_date', '>=', now()->subDays(30))
            ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId))
            ->groupBy('tsl.product_id', 'p.name')
            ->orderBy('qty', $direction)
            ->limit(5)
            ->get(['p.name', DB::raw('SUM(tsl.quantity) as qty')]);
    }

    private function cacheKey(int $businessId, ?int $locationId): string
    {
        return 'ssi_dashboard_' . $businessId . '_' . ($locationId ?: 'all');
    }
}
