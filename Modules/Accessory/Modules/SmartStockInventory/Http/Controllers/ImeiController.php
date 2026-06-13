<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartStockActionLog;

class ImeiController extends BaseSmartStockController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $q = trim((string) $request->input('q', ''));
        $rows = DB::table('smart_imei_histories')
            ->where('business_id', $this->businessId())
            ->whereNull('deleted_at')
            ->when($q !== '', fn ($query) => $query->where('imei', 'like', "%{$q}%"))
            ->orderByDesc('movement_date')
            ->paginate(100);

        $duplicates = DB::table('smart_imei_histories')
            ->select('imei', DB::raw('COUNT(*) as total'))
            ->where('business_id', $this->businessId())
            ->whereNull('deleted_at')
            ->groupBy('imei')
            ->havingRaw('COUNT(*) > 1')
            ->limit(50)
            ->get();

        return view('smartstockinventory::imei.index', compact('rows', 'duplicates', 'q'));
    }

    public function updateStatus(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);
        $d = $request->validate(['id' => 'required|integer', 'status' => 'required|string|max:50', 'reason' => 'required|string|max:500']);
        $row = DB::table('smart_imei_histories')->where('business_id', $this->businessId())->whereNull('deleted_at')->where('id', $d['id'])->first();
        abort_unless($row, 404);
        DB::table('smart_imei_histories')->where('id', $d['id'])->update(['status' => $d['status']]);
        $userName = trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')));
        if ($userName === '') { $userName = (string) (auth()->user()->username ?? ''); }
        SmartStockActionLog::create(['user_id' => auth()->id(), 'user_name' => $userName, 'business_id' => $this->businessId(), 'module_name' => 'SmartStockInventory', 'table_name' => 'smart_imei_histories', 'record_id' => $row->id, 'location_id' => $row->location_id, 'action_type' => 'update_imei_status', 'reference_type' => 'smart_imei_histories', 'reference_id' => $row->id, 'old_data' => json_encode($row), 'new_data' => json_encode(['status' => $d['status']]), 'reason' => $d['reason'], 'ip_address' => $request->ip()]);
        return back()->with('status', ['success' => 1, 'msg' => 'IMEI status updated']);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.export'), 403);
        $rows = DB::table('smart_imei_histories')->where('business_id', $this->businessId())->whereNull('deleted_at')->orderByDesc('movement_date')->limit(10000)->get()->map(fn ($r) => (array) $r)->all();
        return Excel::download(new ArrayExport($rows), 'imei_report_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function history(Request $request, string $imei)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $rows = DB::table('smart_imei_histories')->where('business_id', $this->businessId())->whereNull('deleted_at')->where('imei', $imei)->orderByDesc('movement_date')->paginate(200);
        return view('smartstockinventory::imei.index', ['rows' => $rows, 'duplicates' => collect(), 'q' => $imei]);
    }
}
