<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Services\StockFixService;
use Modules\SmartStockInventory\Services\TelegramAlertService;

class MismatchController extends BaseSmartStockController
{
    public function __construct(\App\Utils\Util $util, private StockFixService $fixService, private TelegramAlertService $telegram) { parent::__construct($util); }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $filters = [
            'business_id' => $this->businessId(),
            'location_ids' => $this->permittedLocationIds($this->businessId()),
            'purchase_ref' => trim((string) $request->input('purchase_ref', '')),
            'purchase_date_from' => $request->input('purchase_date_from'),
            'purchase_date_to' => $request->input('purchase_date_to'),
        ];
        $rows = $this->fixService->detectMismatch($filters);

        return view('smartstockinventory::mismatch.index', compact('rows', 'filters'));
    }

    public function previewFix(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.fix'), 403);
        $d = $request->validate(['product_id' => 'required|integer', 'variation_id' => 'required|integer', 'location_id' => 'required|integer', 'problem_type' => 'nullable|string|max:100']);
        return response()->json(['success' => 1, 'data' => $this->fixService->previewFix((int) $d['product_id'], (int) $d['variation_id'], (int) $d['location_id'], (string) ($d['problem_type'] ?? 'negative_stock'))]);
    }

    public function fixAuto(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.fix'), 403);
        $d = $request->validate(['product_id' => 'required|integer', 'variation_id' => 'required|integer', 'location_id' => 'required|integer', 'problem_type' => 'required|string|max:100', 'reason' => 'required|string|max:500']);
        $map = ['negative_stock' => 'fixNegativeStock', 'mismatch_stock' => 'fixMismatchStock', 'duplicate_imei' => 'fixDuplicateIMEI', 'duplicate_lot' => 'fixDuplicateLot', 'wrong_location_stock' => 'fixWrongLocationStock', 'broken_purchase_sell_mapping' => 'fixBrokenPurchaseSellMapping', 'broken_stock_adjustment' => 'fixBrokenStockAdjustment', 'pending_transfer_issue' => 'fixPendingTransferIssue', 'orphan_sell_lines' => 'fixOrphanSellLines', 'rebuild_stock_by_location' => 'rebuildStockByLocation', 'recalculate_available_qty' => 'recalculateAvailableQty'];
        $method = $map[$d['problem_type']] ?? 'fixNegativeStock';
        $log = $this->fixService->{$method}((int) $d['product_id'], (int) $d['variation_id'], (int) $d['location_id'], $d['reason']);
        $this->telegram->send('Auto fix completed', ['Fix Log ID' => $log->id, 'Type' => $log->fix_type]);
        return back()->with('status', ['success' => 1, 'msg' => 'Fix completed']);
    }

    public function rollback(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.rollback'), 403);
        $d = $request->validate(['fix_log_id' => 'required|integer', 'reason' => 'nullable|string|max:500']);
        $result = $this->fixService->rollbackFix((int) $d['fix_log_id'], $d['reason'] ?? null);
        return back()->with('status', ['success' => $result['ok'] ? 1 : 0, 'msg' => $result['ok'] ? 'Rollback completed' : $result['msg']]);
    }

    public function logs(Request $request) { abort_unless($request->user()->can('stock_inventory.logs'), 403); $logs = DB::table('smart_stock_fix_logs')->where('business_id', $this->businessId())->latest()->paginate(50); return view('smartstockinventory::mismatch.logs', compact('logs')); }
    public function fixLogs(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.logs'), 403);
        $logs = DB::table('smart_stock_fix_logs as f')->leftJoin('variations as v', 'v.id', '=', 'f.variation_id')->leftJoin('products as p', 'p.id', '=', 'f.product_id')->leftJoin('business_locations as bl', 'bl.id', '=', 'f.location_id')->where('f.business_id', $this->businessId())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('f.created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('f.created_at', '<=', $request->to))
            ->when($request->filled('user_id'), fn ($q) => $q->where('f.created_by', (int) $request->user_id))
            ->when($request->filled('location_id'), fn ($q) => $q->where('f.location_id', (int) $request->location_id))
            ->when($request->filled('problem_type'), fn ($q) => $q->where('f.problem_type', $request->problem_type))
            ->select('f.*', 'p.name as product_name', 'v.sub_sku', 'bl.name as location_name')->latest('f.id')->paginate(100);
        return view('smartstockinventory::mismatch.logs', compact('logs'));
    }

    public function deleteLog(Request $request, int $id)
    {
        abort_unless($request->user()->can('stock_inventory.delete'), 403);
        $reason = (string) $request->input('reason', 'delete_incorrect_module_log');
        $log = DB::table('smart_stock_fix_logs')->where('business_id', $this->businessId())->where('id', $id)->first();
        abort_unless($log, 404);
        DB::table('smart_stock_action_logs')->insert([
            'user_id' => auth()->id(), 'business_id' => $this->businessId(), 'location_id' => $log->location_id,
            'action_type' => 'delete_incorrect_module_log', 'reference_type' => 'smart_stock_fix_logs', 'reference_id' => $log->id,
            'old_data' => json_encode($log), 'new_data' => null, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('smart_stock_fix_logs')->where('id', $id)->delete();
        return back()->with('status', ['success' => 1, 'msg' => 'Module log deleted']);
    }
}
