<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialPurchaseLineController extends FinancialPurchaseCostController
{
    private function access(Request $request, string $source): array
    {
        abort_unless($request->user()->can('purchase.update'), 403);
        $business = (int) $request->session()->get('user.business_id');
        abort_unless($business > 0, 403);
        $connections = ['main' => config('database.default'), 'accessory' => config('accessory.database_connection', 'accessory'), 'service' => config('service.database_connection', 'service')];
        abort_unless(isset($connections[$source]), 404);
        $connection = $connections[$source];
        $allowed = $request->user()->permitted_locations($business);
        if ($allowed === 'all') {
            $allowed = DB::connection($connection)->table('business_locations')->where('business_id', $business)->pluck('id')->all();
        }
        $allowed = array_map('intval', (array) $allowed);

        return [$business, $connection, $allowed];
    }

    protected function scope(Request $request, string $source, int $line): array
    {
        [$business, $connection, $allowed] = $this->access($request, $source);
        $exists = DB::connection($connection)->table('purchase_lines as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('p.id', $line)->where('t.business_id', $business)->whereIn('t.location_id', $allowed)
            ->where('t.type', 'purchase')->where('t.status', 'received')->exists();
        abort_unless($exists, 404);

        return [$business, $connection, $allowed, [$line]];
    }

    public function index(Request $request, string $sourceName, int $purchase)
    {
        [$business, $connection, $allowed] = $this->access($request, $sourceName);
        $request->validate(['q' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1']);
        $receipt = DB::connection($connection)->table('transactions')->where('id', $purchase)
            ->where('business_id', $business)->whereIn('location_id', $allowed)
            ->where('type', 'purchase')->where('status', 'received')->first();
        abort_unless($receipt, 404);
        $query = DB::connection($connection)->table('purchase_lines as p')
            ->leftJoin('variations as v', 'v.id', '=', 'p.variation_id')
            ->leftJoin('products as product', 'product.id', '=', 'v.product_id')
            ->where('p.transaction_id', $purchase);
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') $query->where(function ($query) use ($search) {
            $query->where('p.lot_number', 'like', '%'.$search.'%')->orWhere('v.sub_sku', 'like', '%'.$search.'%')
                ->orWhere('product.name', 'like', '%'.$search.'%');
            if (ctype_digit($search)) $query->orWhere('p.id', (int) $search);
        });
        $rows = $query->orderBy('p.id')->paginate(25, ['p.id', 'p.variation_id', 'p.quantity', 'p.lot_number', 'p.purchase_price_inc_tax', 'product.name as product', 'v.sub_sku as sku'])->withQueryString();

        return view('localcashierreport::purchase_line_prices', compact('receipt', 'rows', 'sourceName', 'search'));
    }

    public function show(Request $request, string $sourceName, int $line)
    {
        [, $connection] = $this->scope($request, $sourceName, $line);
        $details = parent::edit($request, $sourceName, $line)->getData(true);
        $purchaseRoute = ($sourceName === 'main' ? '' : $sourceName.'.').'purchases.edit';
        $purchaseId = DB::connection($connection)->table('purchase_lines')->where('id', $line)->value('transaction_id');
        $details['full_purchase_url'] = \Illuminate\Support\Facades\Route::has($purchaseRoute) ? route($purchaseRoute, $purchaseId) : null;
        if ($request->expectsJson()) return response()->json($details);
        $row = DB::connection($connection)->table('purchase_lines as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->leftJoin('variations as v', 'v.id', '=', 'p.variation_id')
            ->leftJoin('products as product', 'product.id', '=', 'v.product_id')
            ->where('p.id', $line)->first(['p.id', 'p.quantity', 'p.lot_number', 'p.transaction_id', 't.ref_no', 'product.name as product', 'v.sub_sku as sku']);

        return view('localcashierreport::purchase_line_price', compact('row', 'details', 'sourceName'));
    }

    public function update(Request $request, string $sourceName, int $line)
    {
        parent::update($request, $sourceName, $line);

        return response()->json(['success' => true] + parent::edit($request, $sourceName, $line)->getData(true));
    }
}
