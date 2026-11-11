<?php

namespace Modules\SmartStockInventory\Services;

use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;

class SkuInventoryService
{
    public function __construct(private ProductUtil $productUtil)
    {
    }

    public function products(int $businessId, array $locationIds, array $filters = [])
    {
        $stock = DB::table('variation_location_details')
            ->whereIn('location_id', $locationIds)
            ->selectRaw('variation_id, SUM(qty_available) as current_stock')
            ->groupBy('variation_id');

        return DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoinSub($stock, 'stock', 'stock.variation_id', '=', 'v.id')
            ->where('p.business_id', $businessId)
            ->whereNull('v.deleted_at')
            ->when(trim($filters['search'] ?? '') !== '', function ($query) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('v.sub_sku', 'like', $term)->orWhere('p.sku', 'like', $term)
                        ->orWhere('p.name', 'like', $term)->orWhere('v.name', 'like', $term);
                });
            })
            ->when(trim($filters['table_search'] ?? '') !== '', function ($query) use ($filters) {
                $term = '%' . trim($filters['table_search']) . '%';
                $query->where(fn ($q) => $q->where('v.sub_sku', 'like', $term)->orWhere('p.sku', 'like', $term)->orWhere('p.name', 'like', $term)->orWhere('v.name', 'like', $term));
            })
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('p.category_id', $filters['category_id']))
            ->when(! empty($filters['sub_category_id']), fn ($q) => $q->where('p.sub_category_id', $filters['sub_category_id']))
            ->when(! empty($filters['unit_id']), fn ($q) => $q->where('p.unit_id', $filters['unit_id']))
            ->when(in_array($filters['product_status'] ?? '', ['active', 'inactive'], true), fn ($q) => $q->where('p.is_inactive', $filters['product_status'] === 'inactive' ? 1 : 0))
            ->when(! empty($filters['brand_id']), fn ($q) => $q->where('p.brand_id', $filters['brand_id']))
            ->when(($filters['stock_status'] ?? '') === 'positive', fn ($q) => $q->whereRaw('COALESCE(stock.current_stock, 0) > 0'))
            ->when(($filters['stock_status'] ?? '') === 'zero', fn ($q) => $q->whereRaw('COALESCE(stock.current_stock, 0) = 0'))
            ->when(($filters['stock_status'] ?? '') === 'negative', fn ($q) => $q->whereRaw('COALESCE(stock.current_stock, 0) < 0'))
            ->select('v.id as variation_id', 'v.sub_sku as sku', 'p.sku as product_sku', 'p.name as product_name', 'v.name as variation_name', 'u.short_name as unit')
            ->selectRaw('COALESCE(stock.current_stock, 0) as current_stock');
    }

    public function history(int $businessId, int $variationId, array $locationIds, array $filters = [])
    {
        // Calculate each location's full history before date filtering, preserving opening balances.
        return DB::table('business_locations')->where('business_id', $businessId)->whereIn('id', $locationIds)
            ->get(['id', 'name'])->flatMap(function ($location) use ($businessId, $variationId) {
                return collect($this->productUtil->getVariationStockHistory($businessId, $variationId, $location->id))
                    ->map(fn ($row) => array_merge($row, ['location_id' => $location->id, 'location_name' => $location->name]));
            })->filter(function ($row) use ($filters) {
                $date = substr((string) $row['date'], 0, 10);
                return (empty($filters['start_date']) || $date >= $filters['start_date'])
                    && (empty($filters['end_date']) || $date <= $filters['end_date'])
                    && (empty($filters['type']) || $row['type'] === $filters['type']);
            })->sort(function ($a, $b) {
                return [$b['date'], $b['transaction_id'], $b['location_id']] <=> [$a['date'], $a['transaction_id'], $a['location_id']];
            })->values();
    }
}
