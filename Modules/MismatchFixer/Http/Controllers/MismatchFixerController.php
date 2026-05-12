<?php

namespace Modules\MismatchFixer\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MismatchFixer\Entities\MismatchFixLog;
use Yajra\DataTables\Facades\DataTables;

class MismatchFixerController extends Controller
{
    public function index()
    {
        $this->authorizePermission('mismatch_fixer.view');
        $business_id = session()->get('user.business_id');

        $locations = BusinessLocation::forDropdown($business_id);
        $products = DB::table('products')->where('business_id', $business_id)->orderBy('name')->limit(200)->pluck('name', 'id');

        return view('mismatchfixer::index', compact('locations', 'products'));
    }

    public function scan(Request $request)
    {
        $this->authorizePermission('mismatch_fixer.view');

        $business_id = $request->session()->get('user.business_id');
        $query = $this->baseScanQuery($business_id);

        if ($request->filled('location_id')) $query->where('pl.location_id', $request->location_id);
        if ($request->filled('product_id')) $query->where('p.id', $request->product_id);
        if ($request->filled('variation_id')) $query->where('pl.variation_id', $request->variation_id);
        if ($request->filled('sku')) {
            $sku = trim((string) $request->sku);
            $query->where(function ($q) use ($sku) { $q->where('p.sku', $sku)->orWhere('v.sub_sku', $sku); });
        }
        if ($request->filled('date_from')) $query->whereDate('t.transaction_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('t.transaction_date', '<=', $request->date_to);
        if ($request->filled('transaction_type')) $query->where('t.type', $request->transaction_type);
        if ($request->filled('mismatch_type')) $query->where('mismatch_rows.problem_type', $request->mismatch_type);

        return DataTables::of($query)
            ->addColumn('action', function ($row) {
                if (!auth()->user()->can('mismatch_fixer.fix')) {
                    return '-';
                }
                return '<button type="button" class="btn btn-xs btn-danger js-fix-row" data-id="'.$row->purchase_line_id.'">Fix</button>';
            })
            ->editColumn('problem_type', function ($row) {
                $class = in_array($row->problem_type, ['mismatch', 'fake_sold', 'broken_transfer'], true) ? 'label-danger' : 'label-default';
                return '<span class="label '.$class.'">'.e($row->problem_type).'</span>';
            })
            ->rawColumns(['action', 'problem_type'])
            ->make(true);
    }

    public function fix($purchase_line_id, Request $request)
    {
        $this->authorizePermission('mismatch_fixer.fix');

        $business_id = (int) $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');

        try {
            return DB::transaction(function () use ($purchase_line_id, $request, $business_id, $user_id) {
                $pl = DB::table('purchase_lines as pl')
                    ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                    ->where('pl.id', $purchase_line_id)
                    ->where('t.business_id', $business_id)
                    ->lockForUpdate()
                    ->select('pl.*', 't.type as transaction_type', 't.status as transaction_status')
                    ->first();

                if (!$pl) return response()->json(['success' => 0, 'msg' => 'Purchase line not found']);

                $is_mismatch = (float)$pl->available_quantity !== $this->calculatedAvailable($pl);
                $real_sale = $this->hasRealSale((int)$pl->id);
                $safe_types = ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'];

                if (!$is_mismatch && !((float)$pl->quantity_sold > 0 && !$real_sale)) {
                    return response()->json(['success' => 0, 'msg' => 'No fixable mismatch on selected row']);
                }

                if (!in_array($pl->transaction_type, $safe_types, true)) {
                    return response()->json(['success' => 0, 'msg' => 'Blocked: transaction type not safe for mismatch fixer']);
                }

                $old = [
                    'quantity_sold' => (float)$pl->quantity_sold,
                    'available_quantity' => (float)$pl->available_quantity,
                ];

                $problem_type = $is_mismatch ? 'mismatch' : 'fake_sold';
                $message = 'Fixed mismatch';

                if (!$real_sale && (float)$pl->quantity_sold > 0) {
                    DB::table('purchase_lines')->where('id', $pl->id)->update(['quantity_sold' => 0]);
                    DB::table('transaction_sell_lines_purchase_lines as tslpl')
                        ->leftJoin('transaction_sell_lines as tsl', 'tsl.id', '=', 'tslpl.sell_line_id')
                        ->where('tslpl.purchase_line_id', $pl->id)
                        ->whereNull('tsl.id')
                        ->delete();
                    $problem_type = 'fake_sold';
                    $message = 'Reset fake sold and removed orphan links';
                }

                $new_available = $this->recalculatePurchaseLine((int)$pl->id);

                $new = [
                    'quantity_sold' => (float)DB::table('purchase_lines')->where('id', $pl->id)->value('quantity_sold'),
                    'available_quantity' => (float)$new_available,
                ];

                $this->createFixLog([
                    'business_id' => $business_id,
                    'user_id' => $user_id,
                    'purchase_line_id' => (int)$pl->id,
                    'transaction_id' => (int)$pl->transaction_id,
                    'variation_id' => (int)$pl->variation_id,
                    'location_id' => (int)$pl->location_id,
                    'problem_type' => $problem_type,
                    'old_values' => $old,
                    'new_values' => $new,
                    'reason' => (string)$request->input('reason', 'manual row fix'),
                    'status' => 'fixed',
                    'message' => $message,
                ]);

                return response()->json(['success' => 1, 'msg' => $message]);
            });
        } catch (\Throwable $e) {
            Log::error('MismatchFixer fix error', ['purchase_line_id' => $purchase_line_id, 'error' => $e->getMessage()]);
            return response()->json(['success' => 0, 'msg' => 'Fix failed: '.$e->getMessage()]);
        }
    }

    public function bulkFix(Request $request)
    {
        $this->authorizePermission('mismatch_fixer.fix');

        $ids = array_values(array_unique(array_filter((array)$request->input('purchase_line_ids', []))));
        if (empty($ids)) return response()->json(['success' => 0, 'msg' => 'Please select rows manually']);
        if (count($ids) > (int)config('mismatchfixer.max_bulk_fix_rows', 100)) {
            return response()->json(['success' => 0, 'msg' => 'Max 100 rows per request']);
        }

        $fixed = 0; $errors = [];
        foreach ($ids as $id) {
            $res = $this->fix((int)$id, $request);
            $body = $res->getData(true);
            if (!empty($body['success'])) $fixed++; else $errors[] = "#{$id}: ".($body['msg'] ?? 'failed');
        }

        return response()->json(['success' => 1, 'msg' => "Bulk fix done. Fixed {$fixed} rows.", 'errors' => $errors]);
    }

    public function logs(Request $request)
    {
        $this->authorizePermission('mismatch_fixer.logs');
        $business_id = $request->session()->get('user.business_id');
        $logs = MismatchFixLog::where('business_id', $business_id)->latest()->paginate(30);
        return view('mismatchfixer::logs', compact('logs'));
    }

    public function settings()
    {
        $this->authorizePermission('mismatch_fixer.settings');
        return view('mismatchfixer::settings', ['max_bulk_fix_rows' => (int)config('mismatchfixer.max_bulk_fix_rows', 100)]);
    }

    public function hasRealSale($purchase_line_id): bool
    {
        return DB::table('transaction_sell_lines_purchase_lines as tslpl')
            ->join('transaction_sell_lines as tsl', 'tsl.id', '=', 'tslpl.sell_line_id')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('tslpl.purchase_line_id', $purchase_line_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->exists();
    }

    public function recalculatePurchaseLine($purchase_line_id): float
    {
        $pl = DB::table('purchase_lines')->where('id', $purchase_line_id)->lockForUpdate()->first();
        if (!$pl) {
            throw new \RuntimeException('Purchase line not found in recalculate');
        }

        $calc = $this->calculatedAvailable($pl);
        DB::table('purchase_lines')->where('id', $purchase_line_id)->update(['available_quantity' => $calc, 'updated_at' => now()]);
        return $calc;
    }

    public function createFixLog(array $payload): MismatchFixLog
    {
        $payload['fixed_at'] = now();
        return MismatchFixLog::create($payload);
    }

    private function calculatedAvailable($pl): float
    {
        return (float)$pl->quantity - (float)$pl->quantity_sold - (float)$pl->quantity_adjusted - (float)$pl->quantity_returned;
    }

    private function baseScanQuery(int $business_id)
    {
        $realSaleSub = "EXISTS (
            SELECT 1 FROM transaction_sell_lines_purchase_lines tslpl
            JOIN transaction_sell_lines tsl ON tsl.id = tslpl.sell_line_id
            JOIN transactions st ON st.id = tsl.transaction_id
            WHERE tslpl.purchase_line_id = pl.id AND st.type='sell' AND st.status='final'
        )";

        return DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 'pl.location_id')
            ->leftJoin('transactions as tx_pair', function ($join) {
                $join->on('tx_pair.id', '=', 't.transfer_parent_id')->orOn('tx_pair.transfer_parent_id', '=', 't.id');
            })
            ->join(DB::raw('(SELECT pl2.id, 
                CASE
                    WHEN ROUND(COALESCE(pl2.available_quantity,0) - (COALESCE(pl2.quantity,0)-COALESCE(pl2.quantity_sold,0)-COALESCE(pl2.quantity_adjusted,0)-COALESCE(pl2.quantity_returned,0)), 4) <> 0 THEN "mismatch"
                    WHEN COALESCE(pl2.quantity_sold,0) > 0 AND NOT EXISTS (
                        SELECT 1 FROM transaction_sell_lines_purchase_lines x
                        JOIN transaction_sell_lines y ON y.id=x.sell_line_id
                        JOIN transactions z ON z.id=y.transaction_id
                        WHERE x.purchase_line_id = pl2.id AND z.type="sell" AND z.status="final"
                    ) THEN "fake_sold"
                    ELSE "ok"
                END AS problem_type
            FROM purchase_lines pl2) as mismatch_rows'), 'mismatch_rows.id', '=', 'pl.id')
            ->where('t.business_id', $business_id)
            ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'sell_transfer', 'production_purchase'])
            ->whereIn('t.status', ['received', 'final', 'completed'])
            ->where('mismatch_rows.problem_type', '!=', 'ok')
            ->select([
                'p.name as product_name',
                DB::raw('COALESCE(v.sub_sku, p.sku) as sku'),
                'pl.variation_id',
                'bl.name as location',
                'pl.id as purchase_line_id',
                'pl.transaction_id',
                't.type',
                't.status',
                'pl.quantity',
                'pl.quantity_sold',
                'pl.quantity_adjusted',
                'pl.quantity_returned',
                'pl.available_quantity',
                DB::raw('(COALESCE(pl.quantity,0)-COALESCE(pl.quantity_sold,0)-COALESCE(pl.quantity_adjusted,0)-COALESCE(pl.quantity_returned,0)) as calculated_available'),
                DB::raw('(COALESCE(pl.available_quantity,0) - (COALESCE(pl.quantity,0)-COALESCE(pl.quantity_sold,0)-COALESCE(pl.quantity_adjusted,0)-COALESCE(pl.quantity_returned,0))) as difference'),
                DB::raw('CASE
                    WHEN mismatch_rows.problem_type = "fake_sold" AND t.type IN ("purchase_transfer","sell_transfer") THEN "broken_transfer"
                    ELSE mismatch_rows.problem_type
                END as problem_type'),
                DB::raw('tx_pair.location_id as source_location_id'),
                DB::raw('t.location_id as destination_location_id'),
                DB::raw("$realSaleSub as has_real_sale")
            ]);
    }
    private function authorizePermission(string $permission): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        abort_unless(auth()->check() && auth()->user()->can($permission), 403, 'Unauthorized action.');
    }
}
