<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Brands;
use App\Category;
use App\Unit;
use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Services\SkuInventoryService;
use Modules\SmartStockInventory\Services\SkuLotService;
use Modules\SmartStockInventory\Services\SkuSalePriceService;
use Yajra\DataTables\Facades\DataTables;
use Modules\SmartStockInventory\Support\SkuPermissions;

class SkuController extends BaseSmartStockController
{
    protected function permittedLocationIds(int $businessId): array
    {
        return SkuPermissions::locationIds($this->currentUser(), $businessId);
    }

    public function index(Request $request, SkuInventoryService $inventory)
    {
        SkuPermissions::authorize($request->user(), 'view');
        $filters = $this->filters($request);
        [$locations, $locationIds] = $this->locations($filters);
        if ($request->ajax()) {
            return DataTables::of($inventory->products($this->businessId(), $locationIds, $filters))
                ->filterColumn('product_display', function ($query, $keyword) {
                    $query->where(fn ($q) => $q->where('p.name', 'like', '%' . $keyword . '%')->orWhere('v.name', 'like', '%' . $keyword . '%'));
                })
                ->orderColumn('product_display', 'p.name $1')
                ->addColumn('action', function ($product) use ($filters) {
                    $url = ssi_route('ssi.sku.history', array_filter(['variation' => $product->variation_id, 'location_id' => $filters['location_id'] ?? null]));
                    return '<a class="btn btn-xs btn-info" href="' . e($url) . '"><i class="fa fa-history"></i> History</a>';
                })
                ->escapeColumns([])->toJson();
        }
        $categories = Category::forDropdown($this->businessId(), 'product');
        $brands = Brands::forDropdown($this->businessId());
        $units = Unit::where('business_id', $this->businessId())->orderBy('short_name')->pluck('short_name', 'id');
        $subcategories = Category::where('business_id', $this->businessId())->where('category_type', 'product')->where('parent_id', '>', 0)->orderBy('name')->get(['id', 'name', 'parent_id']);

        return view('smartstockinventory::sku.index', compact('locations', 'categories', 'brands', 'units', 'subcategories'));
    }

    public function exportProducts(Request $request, SkuInventoryService $inventory)
    {
        SkuPermissions::authorize($request->user(), 'export');
        $filters = $this->filters($request);
        [, $locationIds] = $this->locations($filters);
        $rows = $inventory->products($this->businessId(), $locationIds, $filters)->orderBy('p.name')->orderBy('v.id')->get()
            ->map(fn ($row) => [
                'SKU' => $row->sku ?: $row->product_sku, 'Product SKU' => $row->product_sku,
                'Product' => $row->product_name, 'Variation' => $row->variation_name === 'DUMMY' ? '' : $row->variation_name,
                'Current Stock' => $row->current_stock, 'Unit' => $row->unit,
            ])->all();

        return Excel::download(new ArrayExport($rows), 'sku_products_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function history(Request $request, SkuInventoryService $inventory, int $variation)
    {
        SkuPermissions::authorize($request->user(), 'history');
        [$product, $locations, $movements] = $this->historyData($request, $inventory, $variation);
        $rows = new LengthAwarePaginator($movements->forPage(LengthAwarePaginator::resolveCurrentPage(), 100)->values(), $movements->count(), 100, null, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $rows->setCollection(app(SkuLotService::class)->attach($rows->getCollection(), $this->businessId(), $variation, $locations->pluck('id')->all()));
        $rows->setCollection(app(SkuSalePriceService::class)->attach($rows->getCollection(), $this->businessId(), $variation, $locations->pluck('id')->all()));

        return view('smartstockinventory::sku.history', compact('product', 'locations', 'rows'));
    }

    public function export(Request $request, SkuInventoryService $inventory, int $variation)
    {
        SkuPermissions::authorize($request->user(), 'export');
        [$product, $locations, $movements] = $this->historyData($request, $inventory, $variation);
        $movements = app(SkuLotService::class)->attach($movements, $this->businessId(), $variation, $locations->pluck('id')->all());
        $movements = app(SkuSalePriceService::class)->attach($movements, $this->businessId(), $variation, $locations->pluck('id')->all());
        $canViewCosts = SkuPermissions::allows($request->user(), 'view_cost');
        $rows = $movements->map(fn ($row) => array_merge([
            'SKU' => $product->sku, 'Product' => $product->product_name,
            'Date' => $row['date'], 'Location' => $row['location_name'],
            'Type' => $row['type_label'], 'Reference' => $row['ref_no'] ?? '',
            'Contact' => $row['contact_name'] ?? '',
            'Lots' => collect($row['lots'])->pluck('lot_number')->filter()->unique()->implode(', '),
            'Unit Price (Before Discount)' => collect($row['sale_prices'])->map(fn ($line) => '#' . $line['id'] . ': ' . $line['unit_price'])->implode('; '),
            'Quantity In' => max(0, $row['quantity_change']),
            'Quantity Out' => max(0, -$row['quantity_change']),
            'Location Balance' => $row['stock'],
        ], $canViewCosts ? [
            'Unit Cost (Before Discount)' => collect($row['lots'])->map(fn ($lot) => ($lot['lot_number'] ?: '#' . $lot['id']) . ': ' . ($lot['unit_cost_before_discount'] ?? 'Unavailable'))->implode('; '),
            'Unit Cost (incl. tax)' => collect($row['lots'])->map(fn ($lot) => ($lot['lot_number'] ?: '#' . $lot['id']) . ': ' . ($lot['unit_cost'] ?? 'Unavailable'))->implode('; '),
        ] : []))->all();

        return Excel::download(new ArrayExport($rows), 'sku_history_' . $variation . '_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function updateLot(Request $request, SkuLotService $lots, int $variation, int $line)
    {
        SkuPermissions::authorize($request->user(), 'update_lot');
        $data = $request->validate([
            'lot_number' => 'required|string|max:191', 'original_lot_number' => 'nullable|string|max:191',
            'reason' => 'required|string|max:500',
        ]);
        [, $locationIds] = $this->locations([]);
        $lots->update($this->businessId(), $variation, $line, $locationIds, $data, $request->user(), (string) $request->ip());

        return response()->json(['success' => true, 'msg' => 'Lot updated successfully.']);
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
        $filters = $request->validate([
            'location_id' => 'nullable|integer|min:1', 'sku_search' => 'nullable|string|max:191', 'table_search' => 'nullable|string|max:191',
            'category_id' => 'nullable|integer|min:1', 'brand_id' => 'nullable|integer|min:1',
            'sub_category_id' => 'nullable|integer|min:1', 'unit_id' => 'nullable|integer|min:1',
            'product_status' => 'nullable|in:active,inactive',
            'stock_status' => 'nullable|in:positive,zero,negative',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d' . ($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
            'type' => 'nullable|in:purchase,sell,opening_stock,stock_adjustment,purchase_transfer,sell_transfer,purchase_return,sell_return,production_purchase',
        ]);
        $filters['search'] = $filters['sku_search'] ?? (is_string($request->input('search')) ? mb_substr($request->input('search'), 0, 191) : '');

        return $filters;
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
