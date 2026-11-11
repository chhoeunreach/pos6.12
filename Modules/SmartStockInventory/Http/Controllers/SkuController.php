<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Brands;
use App\Category;
use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Services\SkuInventoryService;

class SkuController extends BaseSmartStockController
{
    public function index(Request $request, SkuInventoryService $inventory)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $filters = $this->filters($request);
        [$locations, $locationIds] = $this->locations($filters);
        $products = $inventory->products($this->businessId(), $locationIds, $filters)
            ->orderBy('p.name')->orderBy('v.id')->paginate(50)->withQueryString();
        $categories = Category::forDropdown($this->businessId(), 'product');
        $brands = Brands::forDropdown($this->businessId());

        return view('smartstockinventory::sku.index', compact('products', 'locations', 'categories', 'brands'));
    }

    public function history(Request $request, SkuInventoryService $inventory, int $variation)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        [$product, $locations, $movements] = $this->historyData($request, $inventory, $variation);
        $rows = new LengthAwarePaginator($movements->forPage(LengthAwarePaginator::resolveCurrentPage(), 100)->values(), $movements->count(), 100, null, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('smartstockinventory::sku.history', compact('product', 'locations', 'rows'));
    }

    public function export(Request $request, SkuInventoryService $inventory, int $variation)
    {
        abort_unless($request->user()->can('stock_inventory.view') && $request->user()->can('stock_inventory.export'), 403);
        [$product, , $movements] = $this->historyData($request, $inventory, $variation);
        $rows = $movements->map(fn ($row) => [
            'SKU' => $product->sku, 'Product' => $product->product_name,
            'Date' => $row['date'], 'Location' => $row['location_name'],
            'Type' => $row['type_label'], 'Reference' => $row['ref_no'] ?? '',
            'Contact' => $row['contact_name'] ?? '',
            'Quantity In' => max(0, $row['quantity_change']),
            'Quantity Out' => max(0, -$row['quantity_change']),
            'Location Balance' => $row['stock'],
        ])->all();

        return Excel::download(new ArrayExport($rows), 'sku_history_' . $variation . '_' . now()->format('Ymd_His') . '.xlsx');
    }

    private function historyData(Request $request, SkuInventoryService $inventory, int $variation): array
    {
        $filters = $this->filters($request);
        [$locations, $locationIds] = $this->locations($filters);
        $product = $inventory->products($this->businessId(), $locationIds)->where('v.id', $variation)->first();
        abort_unless($product, 404);
        $movements = $inventory->history($this->businessId(), $variation, $locationIds, $filters);

        return [$product, $locations, $movements];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'location_id' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:191',
            'category_id' => 'nullable|integer|min:1', 'brand_id' => 'nullable|integer|min:1',
            'stock_status' => 'nullable|in:positive,zero,negative',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d' . ($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
            'type' => 'nullable|in:purchase,sell,opening_stock,stock_adjustment,purchase_transfer,sell_transfer,purchase_return,sell_return,production_purchase',
        ]);
    }

    private function locations(array $filters): array
    {
        $businessId = $this->businessId();
        $allowed = $this->permittedLocationIds($businessId);
        $locations = $this->locationOptions($businessId)->whereIn('id', $allowed)->values();
        $selected = (int) ($filters['location_id'] ?? 0);
        abort_if($selected > 0 && ! $locations->contains('id', $selected), 403);

        return [$locations, $selected > 0 ? [$selected] : $locations->pluck('id')->all()];
    }
}
