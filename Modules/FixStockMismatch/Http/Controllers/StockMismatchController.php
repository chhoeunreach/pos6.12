<?php

namespace Modules\FixStockMismatch\Http\Controllers;

use App\BusinessLocation;
use App\Utils\ProductUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\FixStockMismatch\Entities\StockMismatchFixLog;
use Yajra\DataTables\Facades\DataTables;

class StockMismatchController extends Controller
{
    public function __construct(private ProductUtil $productUtil, private Util $commonUtil)
    {
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('stock_mismatch.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $locations = BusinessLocation::forDropdown($business_id);
        // Use query builder for max compatibility (some installs use App\Brands model)
        $brands = DB::table('brands')->where('business_id', $business_id)->pluck('name', 'id');
        $categories = DB::table('categories')->where('business_id', $business_id)->whereNull('parent_id')->pluck('name', 'id');
        $products = DB::table('products')->where('business_id', $business_id)->pluck('name', 'id');

        $is_admin = $this->commonUtil->is_admin(auth()->user(), $business_id);

        return view('fixstockmismatch::index', compact('locations', 'brands', 'categories', 'products', 'is_admin'));
    }

    public function data(Request $request)
    {
        if (! auth()->user()->can('stock_mismatch.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');
        $category_id = $request->input('category_id');
        $brand_id = $request->input('brand_id');
        $only_mismatch = $request->input('only_mismatch', '1') === '1';

        $query = $this->mismatchQuery($business_id);

        if (! empty($location_id)) {
            $query->where('vld.location_id', $location_id);
        }
        if (! empty($product_id)) {
            $query->where('p.id', $product_id);
        }
        if (! empty($category_id)) {
            $query->where('p.category_id', $category_id);
        }
        if (! empty($brand_id)) {
            $query->where('p.brand_id', $brand_id);
        }
        if ($only_mismatch) {
            $query->havingRaw('ROUND(difference, 4) != 0');
        }

        return DataTables::of($query)
            ->addColumn('status', function ($row) {
                $diff = (float) $row->difference;
                if (abs($diff) < 0.0001) {
                    return '<span class="label label-success">Matched</span>';
                }
                return '<span class="label label-danger">Mismatch</span>';
            })
            ->addColumn('action', function ($row) {
                $detail_url = url('stock-mismatch/' . $row->variation_id . '/' . $row->location_id . '/detail');
                $btn_detail = '<a href="' . $detail_url . '" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> View Detail</a>';

                $btn_fix = '';
                if (auth()->user()->can('stock_mismatch.fix')) {
                    $btn_fix = ' <button class="btn btn-xs btn-primary fix_mismatch_btn" data-variation_id="' . $row->variation_id . '" data-location_id="' . $row->location_id . '"><i class="fa fa-wrench"></i> Fix</button>';
                }

                return $btn_detail . $btn_fix;
            })
            ->addColumn('current_stock', fn ($row) => $this->productUtil->num_f($row->stock))
            ->addColumn('calculated_stock', fn ($row) => $this->productUtil->num_f($row->total_stock_calculated))
            ->editColumn('difference', fn ($row) => $this->productUtil->num_f($row->difference))
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function detail(Request $request, $variation_id, $location_id)
    {
        if (! auth()->user()->can('stock_mismatch.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $details = $this->productUtil->getVariationStockDetails($business_id, $variation_id, $location_id);
        $history = $this->productUtil->getVariationStockHistory($business_id, $variation_id, $location_id);
        $mismatch = $this->productUtil->getVariationStockMisMatch($business_id, $variation_id, $location_id)->first();

        $location = BusinessLocation::where('business_id', $business_id)->findOrFail($location_id);

        return view('fixstockmismatch::detail', compact('details', 'history', 'mismatch', 'location', 'variation_id', 'location_id'));
    }

    public function fix(Request $request)
    {
        if (! auth()->user()->can('stock_mismatch.fix')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'variation_id' => 'required|integer',
            'location_id' => 'required|integer',
            'note' => 'nullable|string|max:1000',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $variation_id = (int) $request->input('variation_id');
        $location_id = (int) $request->input('location_id');
        $note = $request->input('note');

        try {
            $result = DB::transaction(function () use ($business_id, $variation_id, $location_id, $note) {
                $row = $this->productUtil->getVariationStockMisMatch($business_id, $variation_id, $location_id)->first();
                if (empty($row)) {
                    throw new \Exception('Variation/location not found.');
                }

                $vld = DB::table('variation_location_details')
                    ->where('variation_id', $variation_id)
                    ->where('location_id', $location_id)
                    ->lockForUpdate()
                    ->first();

                $old_qty = (float) ($vld->qty_available ?? 0);
                $new_qty = (float) $row->total_stock_calculated;
                $difference = $new_qty - $old_qty;

                $this->productUtil->fixVariationStockMisMatch($business_id, $variation_id, $location_id, $new_qty);

                StockMismatchFixLog::create([
                    'business_id' => $business_id,
                    'location_id' => $location_id,
                    'product_id' => $row->product_id,
                    'variation_id' => $variation_id,
                    'old_qty' => $old_qty,
                    'new_qty' => $new_qty,
                    'difference' => $difference,
                    'fixed_by' => request()->session()->get('user.id'),
                    'note' => $note,
                ]);

                return [
                    'old_qty' => $old_qty,
                    'new_qty' => $new_qty,
                    'difference' => $difference,
                ];
            });

            return [
                'success' => 1,
                'msg' => 'Stock mismatch fixed successfully',
                'data' => $result,
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }
    }

    public function fixAll(Request $request)
    {
        if (! auth()->user()->can('stock_mismatch.fix')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $is_admin = $this->commonUtil->is_admin(auth()->user(), $business_id);
        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');
        $category_id = $request->input('category_id');
        $brand_id = $request->input('brand_id');

        try {
            $fixed = 0;
            $query = $this->mismatchQuery($business_id)->havingRaw('ROUND(difference, 4) != 0');

            if (! empty($location_id)) {
                $query->where('vld.location_id', $location_id);
            }
            if (! empty($product_id)) {
                $query->where('p.id', $product_id);
            }
            if (! empty($category_id)) {
                $query->where('p.category_id', $category_id);
            }
            if (! empty($brand_id)) {
                $query->where('p.brand_id', $brand_id);
            }

            $rows = $query->limit(1000)->get(); // safety cap

            DB::transaction(function () use ($rows, $business_id, &$fixed) {
                foreach ($rows as $row) {
                    $variation_id = (int) $row->variation_id;
                    $location_id = (int) $row->location_id;

                    $vld = DB::table('variation_location_details')
                        ->where('variation_id', $variation_id)
                        ->where('location_id', $location_id)
                        ->lockForUpdate()
                        ->first();

                    $old_qty = (float) ($vld->qty_available ?? 0);
                    $new_qty = (float) $row->total_stock_calculated;
                    $difference = $new_qty - $old_qty;

                    $this->productUtil->fixVariationStockMisMatch($business_id, $variation_id, $location_id, $new_qty);

                    StockMismatchFixLog::create([
                        'business_id' => $business_id,
                        'location_id' => $location_id,
                        'product_id' => $row->product_id,
                        'variation_id' => $variation_id,
                        'old_qty' => $old_qty,
                        'new_qty' => $new_qty,
                        'difference' => $difference,
                        'fixed_by' => request()->session()->get('user.id'),
                        'note' => 'Fix all',
                    ]);

                    $fixed++;
                }
            });

            return [
                'success' => 1,
                'msg' => "Fixed {$fixed} mismatch rows.",
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }
    }

    private function mismatchQuery(int $business_id)
    {
        $totalSold = "(SELECT SUM(COALESCE(TSL.quantity, 0)) FROM transactions 
                    LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id
                    WHERE transactions.status='final' AND transactions.type='sell' AND transactions.location_id=vld.location_id 
                    AND TSL.variation_id=v.id)";

        $totalSellReturn = "(SELECT SUM(COALESCE(TSL.quantity_returned, 0)) FROM transactions 
                    LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id
                    WHERE transactions.status='final' AND transactions.type='sell' AND transactions.location_id=vld.location_id 
                    AND TSL.variation_id=v.id)";

        $totalSellTransferred = "(SELECT SUM(COALESCE(TSL.quantity,0)) FROM transactions 
                    LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id
                    WHERE transactions.status='final' AND transactions.type='sell_transfer' AND transactions.location_id=vld.location_id 
                    AND TSL.variation_id=v.id)";

        $totalPurchaseTransferred = "(SELECT SUM(COALESCE(PL.quantity,0)) FROM transactions 
                    LEFT JOIN purchase_lines AS PL ON transactions.id=PL.transaction_id
                    WHERE transactions.status='received' AND transactions.type='purchase_transfer' AND transactions.location_id=vld.location_id 
                    AND PL.variation_id=v.id)";

        $totalAdjusted = "(SELECT SUM(COALESCE(SAL.quantity, 0)) FROM transactions 
                    LEFT JOIN stock_adjustment_lines AS SAL ON transactions.id=SAL.transaction_id
                    WHERE transactions.type='stock_adjustment' AND transactions.location_id=vld.location_id 
                    AND SAL.variation_id=v.id)";

        $totalPurchased = "(SELECT SUM(COALESCE(PL.quantity, 0)) FROM transactions 
                    LEFT JOIN purchase_lines AS PL ON transactions.id=PL.transaction_id
                    WHERE transactions.status='received' AND transactions.type='purchase' AND transactions.location_id=vld.location_id
                    AND PL.variation_id=v.id)";

        $totalPurchaseReturn = "(SELECT SUM(COALESCE(PL.quantity_returned, 0)) FROM transactions 
                    LEFT JOIN purchase_lines AS PL ON transactions.id=PL.transaction_id
                    WHERE transactions.status='received' AND transactions.type='purchase' AND transactions.location_id=vld.location_id
                    AND PL.variation_id=v.id)";

        $totalCombinedPurchaseReturn = "(SELECT SUM(COALESCE(PL.quantity_returned, 0)) FROM transactions 
                    LEFT JOIN purchase_lines AS PL ON transactions.id=PL.transaction_id
                    WHERE transactions.type='purchase_return' AND transactions.location_id=vld.location_id
                    AND PL.variation_id=v.id)";

        $totalOpeningStock = "(SELECT SUM(COALESCE(PL.quantity, 0)) FROM transactions 
                    LEFT JOIN purchase_lines AS PL ON transactions.id=PL.transaction_id
                    WHERE transactions.type='opening_stock' AND transactions.status='received' AND transactions.location_id=vld.location_id
                    AND PL.variation_id=v.id)";

        $totalManufactured = "(SELECT SUM(COALESCE(PL.quantity, 0)) FROM transactions 
                    LEFT JOIN purchase_lines AS PL ON transactions.id=PL.transaction_id
                    WHERE transactions.status='received' AND transactions.type='production_purchase' AND transactions.location_id=vld.location_id
                    AND PL.variation_id=v.id)";

        $totalIngredientsUsed = "(SELECT SUM(COALESCE(TSL.quantity, 0)) FROM transactions 
                    LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id
                    WHERE transactions.status='final' AND transactions.type='production_sell' AND transactions.location_id=vld.location_id 
                    AND TSL.variation_id=v.id)";

        $calculated = "(
            COALESCE(($totalOpeningStock),0) + COALESCE(($totalPurchased),0) + COALESCE(($totalPurchaseTransferred),0) + COALESCE(($totalSellReturn),0) + COALESCE(($totalManufactured),0)
            - (COALESCE(($totalSold),0) + COALESCE(($totalSellTransferred),0) + COALESCE(($totalAdjusted),0) + COALESCE(($totalPurchaseReturn),0) + COALESCE(($totalCombinedPurchaseReturn),0) + COALESCE(($totalIngredientsUsed),0))
        )";

        $base = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
            ->leftJoin('brands as br', 'br.id', '=', 'p.brand_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->where('p.business_id', $business_id)
            ->select([
                'vld.location_id',
                'bl.name as business_location',
                'p.name as product_name',
                'p.sku as sku',
                'v.name as variation',
                'v.sub_sku as sub_sku',
                'p.id as product_id',
                'v.id as variation_id',
                DB::raw('SUM(vld.qty_available) as stock'),
                DB::raw($calculated . ' as total_stock_calculated'),
                DB::raw('(SUM(vld.qty_available) - ' . $calculated . ') as difference'),
            ])
            ->groupBy('v.id')
            ->groupBy('vld.location_id')
            ->groupBy('bl.name')
            ->groupBy('p.name')
            ->groupBy('p.sku')
            ->groupBy('v.name')
            ->groupBy('v.sub_sku')
            ->groupBy('p.id');

        return $base;
    }
}
