<?php

namespace Modules\SmartStockInventory\Http\Controllers\Enterprise;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Services\Enterprise\SsiDashboardService;

class SsiReportController extends Controller
{
    public function __construct(private SsiDashboardService $dashboard)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('ssi.audit.report'), 403);
        $businessId = (int) session('user.business_id');
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $metrics = $this->dashboard->metrics($businessId, $locationId);
        $differenceRows = DB::table('ssi_audit_items as i')
            ->join('ssi_audits as a', 'i.audit_id', '=', 'a.id')
            ->where('i.business_id', $businessId)
            ->when($locationId, fn ($q) => $q->where('i.location_id', $locationId))
            ->where('i.difference_qty', '!=', 0)
            ->latest('i.updated_at')
            ->limit(500)
            ->get(['a.audit_no', 'a.name as audit_name', 'i.sku', 'i.product_name', 'i.imei', 'i.lot_number', 'i.expected_qty', 'i.counted_qty', 'i.difference_qty', 'i.mismatch_type']);

        return view('smartstockinventory::enterprise.report.index', compact('metrics', 'differenceRows'));
    }
}
