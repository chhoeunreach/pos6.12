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
        $filters = $this->filters($request);
        [$product, $locations, $movements] = $this->historyData($request, $inventory, $variation);

        if ($request->ajax()) {
            $locationIds = $locations->pluck('id')->all();
            $movements = app(SkuLotService::class)->attach($movements, $this->businessId(), $variation, $locationIds);
            $movements = app(SkuSalePriceService::class)->attach($movements, $this->businessId(), $variation, $locationIds);

            $user = $request->user();
            $canViewCosts = SkuPermissions::allows($user, 'view_cost');
            $canEditCosts = SkuPermissions::allows($user, 'update_cost');
            $canUpdateLot = (bool) $user->can('stock_inventory.update');
            $canUpdateSalePrice = (bool) $user->can('sell.update');

            $formatted = $this->formatHistoryRows(
                $movements,
                $product->variation_id,
                $canViewCosts,
                $canEditCosts,
                $canUpdateLot,
                $canUpdateSalePrice
            );

            return response()->json([
                'data' => $formatted,
                'summary' => [
                    'current_stock' => (float) ($product->current_stock ?? 0),
                    'unit' => (string) ($product->unit ?? ''),
                    'total_in' => round((float) $movements->sum(fn ($r) => max(0, (float) ($r['quantity_change'] ?? 0))), 4),
                    'total_out' => round((float) $movements->sum(fn ($r) => max(0, -(float) ($r['quantity_change'] ?? 0))), 4),
                    'count' => $movements->count(),
                ],
            ]);
        }

        $rows = new LengthAwarePaginator($movements->forPage(LengthAwarePaginator::resolveCurrentPage(), 100)->values(), $movements->count(), 100, null, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $rows->setCollection(app(SkuLotService::class)->attach($rows->getCollection(), $this->businessId(), $variation, $locations->pluck('id')->all()));
        $rows->setCollection(app(SkuSalePriceService::class)->attach($rows->getCollection(), $this->businessId(), $variation, $locations->pluck('id')->all()));

        return view('smartstockinventory::sku.history', compact('product', 'locations', 'rows', 'filters'));
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
        $validTypes = ['purchase', 'sell', 'opening_stock', 'stock_adjustment', 'purchase_transfer', 'sell_transfer', 'purchase_return', 'sell_return', 'production_purchase'];
        $rawLocation = $request->input('location_id');
        $rawType = $request->input('type');

        $rules = [
            'sku_search' => 'nullable|string|max:191',
            'table_search' => 'nullable|string|max:191',
            'category_id' => 'nullable|integer|min:1',
            'brand_id' => 'nullable|integer|min:1',
            'sub_category_id' => 'nullable|integer|min:1',
            'unit_id' => 'nullable|integer|min:1',
            'product_status' => 'nullable|in:active,inactive',
            'stock_status' => 'nullable|in:positive,zero,negative',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d' . ($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
            'direction' => 'nullable|in:all,in,out',
        ];

        if (is_array($rawLocation)) {
            $rules['location_id'] = 'nullable|array';
            $rules['location_id.*'] = 'nullable|string|max:20';
        } elseif ($rawLocation !== 'all') {
            $rules['location_id'] = 'nullable|integer|min:1';
        }

        if (is_array($rawType)) {
            $rules['type'] = 'nullable|array';
            $rules['type.*'] = 'nullable|in:all,' . implode(',', $validTypes);
        } elseif ($rawType !== 'all') {
            $rules['type'] = 'nullable|in:' . implode(',', $validTypes);
        }

        $filters = $request->validate($rules);

        $locationIds = [];
        foreach ((array) $rawLocation as $value) {
            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }
            $locationIds[] = (int) $value;
        }
        $filters['location_ids'] = array_values(array_unique(array_filter($locationIds, fn ($id) => $id > 0)));
        if (count($filters['location_ids']) === 1) {
            $filters['location_id'] = $filters['location_ids'][0];
        } elseif (empty($filters['location_ids'])) {
            $filters['location_id'] = null;
        }

        $filters['types'] = collect((array) $rawType)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '' && $v !== 'all')
            ->values()
            ->all();
        $filters['type'] = count($filters['types']) === 1 ? $filters['types'][0] : ($filters['types'] ?: null);
        $filters['search'] = $filters['sku_search'] ?? (is_string($request->input('search')) ? mb_substr($request->input('search'), 0, 191) : '');

        return $filters;
    }

    private function locations(array $filters): array
    {
        $businessId = $this->businessId();
        $allowed = $this->permittedLocationIds($businessId);
        $locations = $this->locationOptions($businessId)->whereIn('id', $allowed)->values();
        $requestedIds = ! empty($filters['location_ids'])
            ? array_values(array_unique(array_map('intval', $filters['location_ids'])))
            : ((int) ($filters['location_id'] ?? 0) > 0 ? [(int) $filters['location_id']] : []);

        foreach ($requestedIds as $selected) {
            abort_if($selected > 0 && ! $locations->contains('id', $selected), 403);
        }

        return [$locations, ! empty($requestedIds) ? $requestedIds : $locations->pluck('id')->all()];
    }

    private function formatHistoryRows($movements, int $variationId, bool $canViewCosts, bool $canEditCosts, bool $canUpdateLot, bool $canUpdateSalePrice): array
    {
        return collect($movements)->map(function ($row) use ($variationId, $canViewCosts, $canEditCosts, $canUpdateLot, $canUpdateSalePrice) {
            $type = (string) ($row['type'] ?? '');
            $txnId = (int) ($row['transaction_id'] ?? 0);
            $refText = (string) (($row['ref_no'] ?? '') !== '' ? $row['ref_no'] : '#' . $txnId);
            $transactionPath = match ($type) {
                'sell' => '/sells/',
                'sell_return' => '/sell-return/',
                'purchase', 'opening_stock', 'production_purchase' => '/purchases/',
                'purchase_return' => '/purchase-return/',
                'stock_adjustment' => '/stock-adjustments/',
                'sell_transfer', 'purchase_transfer' => '/stock-transfers/',
                default => null,
            };
            if ($transactionPath) {
                $modalUrl = ssi_url($transactionPath . $txnId) . (in_array($type, ['purchase', 'opening_stock', 'production_purchase'], true) ? '?sku_history=1' : '');
                $refHtml = '<a href="#" class="btn-modal" data-href="' . e($modalUrl) . '" data-container=".view_modal" data-export-value="' . e($refText) . '">' . e($refText) . '</a>';
            } else {
                $refHtml = '<span data-export-value="' . e($refText) . '">' . e($refText) . '</span>';
            }

            $lots = collect($row['lots'] ?? []);
            $lotsExport = $lots->pluck('lot_number')->filter()->unique()->implode(', ');
            $lotsHtml = $lots->map(function ($lot) use ($variationId, $canUpdateLot) {
                $lotNo = (string) ($lot['lot_number'] ?? '');
                $lotDisplay = $lotNo !== ''
                    ? '<a href="' . e(ssi_route('ssi.lot.history', ['lot' => $lotNo])) . '">' . e($lotNo) . '</a>'
                    : '<span class="text-muted">No lot number</span>';
                $btn = ($canUpdateLot && ! empty($lot['editable']))
                    ? ' <button type="button" class="btn btn-xs btn-default sku-quick-lot" data-url="' . e(ssi_route('ssi.sku.lot.update', ['variation' => $variationId, 'line' => $lot['id']])) . '" data-lot-number="' . e($lotNo) . '" data-line-id="' . e((string) $lot['id']) . '"><i class="fa fa-pencil"></i> Quick Update</button>'
                    : '';

                return '<div style="white-space:nowrap;margin-bottom:4px">' . $lotDisplay . $btn . '</div>';
            })->implode('');
            if ($lotsHtml === '') {
                $lotsHtml = '<span class="text-muted">—</span>';
            }
            $lotsHtml = '<span data-export-value="' . e($lotsExport) . '"></span>' . $lotsHtml;

            $salePrices = collect($row['sale_prices'] ?? []);
            $saleExport = $salePrices->map(fn ($line) => (count($salePrices) > 1 ? '#' . $line['id'] . ': ' : '') . $line['unit_price'])->implode('; ');
            $saleHtml = $salePrices->map(function ($salePrice) use ($salePrices) {
                $prefix = count($salePrices) > 1 ? '<small>#' . e((string) $salePrice['id']) . ':</small> ' : '';
                return '<div style="white-space:nowrap;margin-bottom:4px">' . $prefix . '<span class="display_currency" data-currency_symbol="true">' . e((string) $salePrice['unit_price']) . '</span></div>';
            })->implode('');
            if ($saleHtml === '') {
                $saleHtml = '<span class="text-muted">—</span>';
            }
            if ($salePrices->isNotEmpty() && $canUpdateSalePrice) {
                $saleHtml .= '<button type="button" class="btn btn-xs btn-default sku-quick-sale-price" data-url="' . e(ssi_route('ssi.sku.sale_price.edit', ['variation' => $variationId, 'transaction' => $txnId])) . '"><i class="fa fa-pencil"></i> Quick Update</button>';
            }
            $saleHtml = '<span data-export-value="' . e($saleExport) . '"></span>' . $saleHtml;

            $costBeforeHtml = '';
            $costIncHtml = '';
            if ($canViewCosts) {
                $beforeExport = $lots->map(fn ($lot) => ($lot['lot_number'] ?: '#' . $lot['id']) . ': ' . ($lot['unit_cost_before_discount'] ?? 'Unavailable'))->implode('; ');
                $beforeInner = $lots->map(function ($lot) use ($lots, $canEditCosts) {
                    $prefix = count($lots) > 1 ? '<small>' . e((string) ($lot['lot_number'] ?: '#' . $lot['id'])) . ':</small> ' : '';
                    $val = $lot['unit_cost_before_discount'] !== null
                        ? '<span class="display_currency" data-currency_symbol="true">' . e((string) $lot['unit_cost_before_discount']) . '</span>'
                        : '<span class="text-muted">Unavailable</span>';
                    $btn = ($canEditCosts && ! empty($lot['cost_editable']))
                        ? ' <button type="button" class="btn btn-xs btn-default sku-before-cost" data-url="' . e(ssi_route('ssi.sku.cost.edit', ['sourceName' => 'main', 'line' => $lot['id'], 'price_basis' => 'before_discount'])) . '"><i class="fa fa-pencil"></i> Quick Update</button>'
                        : '';
                    return '<div style="white-space:nowrap;margin-bottom:4px">' . $prefix . $val . $btn . '</div>';
                })->implode('');
                $costBeforeHtml = '<span data-export-value="' . e($beforeExport) . '"></span>' . ($beforeInner !== '' ? $beforeInner : '<span class="text-muted">—</span>');

                $incExport = $lots->map(fn ($lot) => ($lot['lot_number'] ?: '#' . $lot['id']) . ': ' . ($lot['unit_cost'] ?? 'Unavailable'))->implode('; ');
                $incInner = $lots->map(function ($lot) use ($lots, $canEditCosts) {
                    $prefix = count($lots) > 1 ? '<small>' . e((string) ($lot['lot_number'] ?: '#' . $lot['id'])) . ':</small> ' : '';
                    $val = $lot['unit_cost'] !== null
                        ? '<span class="display_currency" data-currency_symbol="true">' . e((string) $lot['unit_cost']) . '</span>'
                        : '<span class="text-muted">Unavailable</span>';
                    $btn = ($canEditCosts && ! empty($lot['cost_editable']))
                        ? ' <button type="button" class="btn btn-xs btn-default sku-quick-cost" data-url="' . e(ssi_route('ssi.sku.cost.edit', ['sourceName' => 'main', 'line' => $lot['id']])) . '"><i class="fa fa-pencil"></i> Update Cost</button>'
                        : '';
                    return '<div style="white-space:nowrap;margin-bottom:4px">' . $prefix . $val . $btn . '</div>';
                })->implode('');
                $costIncHtml = '<span data-export-value="' . e($incExport) . '"></span>' . ($incInner !== '' ? $incInner : '<span class="text-muted">—</span>');
            }

            $qtyChange = (float) ($row['quantity_change'] ?? 0);
            $qtyIn = max(0.0, $qtyChange);
            $qtyOut = max(0.0, -$qtyChange);
            $stock = (float) ($row['stock'] ?? 0);
            $dateText = (string) ($row['date'] ?? '');

            return [
                'date' => '<span data-export-value="' . e($dateText) . '">' . e($dateText) . '</span>',
                'date_raw' => $dateText,
                'location_name' => (string) ($row['location_name'] ?? ''),
                'type_label' => (string) ($row['type_label'] ?? $type),
                'ref_html' => $refHtml,
                'contact_name' => (string) ($row['contact_name'] ?? $row['supplier_business_name'] ?? ''),
                'lots_html' => $lotsHtml,
                'sale_price_html' => $saleHtml,
                'cost_before_html' => $costBeforeHtml,
                'cost_inc_html' => $costIncHtml,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'stock' => $stock,
                'qty_in_html' => '<span class="display_currency" data-is_quantity="true" data-currency_symbol="false" data-orig-value="' . e((string) $qtyIn) . '">' . e((string) $qtyIn) . '</span>',
                'qty_out_html' => '<span class="display_currency" data-is_quantity="true" data-currency_symbol="false" data-orig-value="' . e((string) $qtyOut) . '">' . e((string) $qtyOut) . '</span>',
                'stock_html' => '<span class="display_currency" data-is_quantity="true" data-currency_symbol="false" data-orig-value="' . e((string) $stock) . '">' . e((string) $stock) . '</span>',
            ];
        })->values()->all();
    }
}
