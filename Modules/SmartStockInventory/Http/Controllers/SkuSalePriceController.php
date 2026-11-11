<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Utils\ModuleUtil;
use Modules\SmartStockInventory\Support\SkuPermissions;
use Illuminate\Http\Request;
use Modules\SmartStockInventory\Services\SkuSalePriceService;

class SkuSalePriceController extends BaseSmartStockController
{
    protected function permittedLocationIds(int $businessId): array
    {
        return SkuPermissions::locationIds($this->currentUser(), $businessId);
    }

    public function edit(Request $request, SkuSalePriceService $prices, int $variation, int $transaction)
    {
        SkuPermissions::authorize($request->user(), 'update_sale_price');
        [$sale, $lines] = $prices->context($this->businessId(), $variation, $transaction, $this->permittedLocationIds($this->businessId()), $request->user());

        return response()->json(['lots' => $lines->map(fn ($line) => [
            'id' => $line->id, 'lot' => 'Sale line #' . $line->id, 'reference' => $sale->invoice_no,
            'unit_price' => $line->unit_price_before_discount,
        ]), 'cost_version' => $prices->version($sale, $lines), 'update_url' => $request->fullUrl()]);
    }

    public function update(Request $request, SkuSalePriceService $prices, int $variation, int $transaction)
    {
        SkuPermissions::authorize($request->user(), 'update_sale_price');
        abort_unless(app(ModuleUtil::class)->isSubscribed($this->businessId()), 403);
        $data = $request->validate([
            'cost_version' => 'required|string|size:64', 'prices' => 'required|array|min:1|max:100',
            'prices.*.id' => 'required|integer|distinct', 'prices.*.unit_price' => 'required|numeric|min:0|max:999999999999',
        ]);
        $prices->update($this->businessId(), $variation, $transaction, $this->permittedLocationIds($this->businessId()), $request->user(), $data, (string) $request->ip());

        return response()->json(['success' => true]);
    }
}
