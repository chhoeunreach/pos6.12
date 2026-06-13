<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartInventoryApproval;
use Modules\SmartStockInventory\Models\SmartInventoryRecount;
use Modules\SmartStockInventory\Models\SmartStockInventorySession;

class VerificationController extends BaseSmartStockController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.verify'), 403);

        $businessId = $this->businessId();

        $rows = DB::table('smart_stock_inventory_lines as l')
            ->join('smart_stock_inventory_sessions as s', 's.id', '=', 'l.session_id')
            ->where('s.business_id', $businessId)
            ->when($request->filled('session_id'), fn ($q) => $q->where('s.id', (int) $request->session_id))
            ->when($request->filled('location_id'), fn ($q) => $q->where('s.location_id', (int) $request->location_id))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('l.created_at', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('l.created_at', '<=', $request->end_date))
            ->select(
                's.id as session_id',
                'l.product_name as product',
                'l.sku',
                's.location_id',
                'l.system_qty',
                'l.actual_qty as count_qty',
                'l.difference_qty as difference',
                DB::raw('0 as stock_value_difference'),
                'l.status'
            )
            ->orderByDesc('l.id')
            ->paginate(100);

        $totalMissingValue = 0.0;
        $totalOverValue = 0.0;
        $netDifference = 0.0;

        foreach ($rows as $row) {
            $d = (float) $row->difference;
            if ($d < 0) {
                $totalMissingValue += abs($d);
            } else {
                $totalOverValue += $d;
            }
            $netDifference += $d;
        }

        return view('smartstockinventory::verification.index', compact('rows', 'totalMissingValue', 'totalOverValue', 'netDifference'));
    }

    public function approve(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.approve'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        SmartInventoryApproval::create(['business_id' => $this->businessId(), 'session_id' => $session->id, 'approved_by' => auth()->id(), 'approval_level' => 'manager', 'status' => 'approved', 'note' => (string) $request->input('note', ''), 'approved_at' => now()]);
        $session->status = 'approved'; $session->approved_by = auth()->id(); $session->approved_at = now(); $session->save();
        return back()->with('status', ['success' => 1, 'msg' => 'Verification approved']);
    }

    public function reject(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.approve'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        SmartInventoryApproval::create(['business_id' => $this->businessId(), 'session_id' => $session->id, 'approved_by' => auth()->id(), 'approval_level' => 'manager', 'status' => 'rejected', 'note' => (string) $request->input('note', ''), 'approved_at' => now()]);
        $session->status = 'recount_required'; $session->save();
        return back()->with('status', ['success' => 1, 'msg' => 'Rejected and sent to recount']);
    }

    public function recount(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.recount'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        SmartInventoryRecount::create(['business_id' => $this->businessId(), 'session_id' => $session->id, 'line_id' => 0, 'recount_reason' => (string) $request->input('reason', 'manual_recount'), 'recount_by' => auth()->id(), 'recount_date' => now(), 'before_qty' => 0, 'after_qty' => 0]);
        $session->status = 'recount_required'; $session->save();
        return back()->with('status', ['success' => 1, 'msg' => 'Recount required']);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.export'), 403);
        $rows = DB::table('smart_stock_inventory_lines as l')->join('smart_stock_inventory_sessions as s', 's.id', '=', 'l.session_id')->where('s.business_id', $this->businessId())->select('l.product_name', 'l.sku', 's.location_id', 'l.system_qty', 'l.actual_qty', 'l.difference_qty', 'l.status')->get()->map(fn ($r) => (array) $r)->all();
        return Excel::download(new ArrayExport($rows), 'verification_report_' . now()->format('Ymd_His') . '.xlsx');
    }
    public function print(Request $request) { return $this->index($request); }
}
