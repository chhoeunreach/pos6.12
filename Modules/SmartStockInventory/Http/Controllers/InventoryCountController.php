<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartStockActionLog;
use Modules\SmartStockInventory\Models\SmartStockInventoryLine;
use Modules\SmartStockInventory\Models\SmartStockInventorySession;

class InventoryCountController extends BaseSmartStockController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $businessId = $this->businessId();
        $locationIds = $this->permittedLocationIds($businessId);
        $sessions = SmartStockInventorySession::where('business_id', $businessId)->latest()->limit(50)->get();
        $lines = DB::table('smart_stock_inventory_lines as l')->join('smart_stock_inventory_sessions as s', 's.id', '=', 'l.session_id')
            ->whereNull('l.deleted_at')->whereNull('s.deleted_at')->where('s.business_id', $businessId)->orderByDesc('l.id')->limit(1000)->get();

        return view('smartstockinventory::count.index', ['locations' => $this->locationOptions($businessId)->whereIn('id', $locationIds)->values(), 'sessions' => $sessions, 'lines' => $lines]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.create'), 403);
        $data = $request->validate(['session_name' => 'required|string|max:255', 'location_id' => 'required|integer', 'lines' => 'nullable|array', 'lines.*.actual_qty' => 'nullable|numeric', 'lines.*.system_qty' => 'nullable|numeric', 'lines.*.remark' => 'nullable|string|max:500']);
        $session = SmartStockInventorySession::create(['business_id' => $this->businessId(), 'location_id' => $data['location_id'], 'name' => $data['session_name'], 'status' => 'draft', 'created_by' => auth()->id()]);
        foreach (($data['lines'] ?? []) as $line) {
            $systemQty = (float) ($line['system_qty'] ?? 0); $actualQty = (float) ($line['actual_qty'] ?? 0); $diff = $actualQty - $systemQty;
            SmartStockInventoryLine::create(['session_id' => $session->id, 'sku' => $line['sku'] ?? null, 'product_name' => $line['product_name'] ?? null, 'variation_name' => $line['variation_name'] ?? null, 'imei' => $line['imei'] ?? null, 'lot_number' => $line['lot_number'] ?? null, 'system_qty' => $systemQty, 'actual_qty' => $actualQty, 'difference_qty' => $diff, 'status' => $diff == 0 ? 'matched' : ($diff > 0 ? 'over_stock' : 'missing'), 'remark' => $line['remark'] ?? null]);
        }
        return redirect()->route('ssi.count.index')->with('status', ['success' => 1, 'msg' => 'Inventory count draft saved']);
    }

    public function updateSession(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);
        abort_unless($session->business_id === $this->businessId() && $session->status !== 'completed', 422);
        $data = $request->validate(['name' => 'required|string|max:255', 'status' => 'required|string|in:draft,in_progress', 'remark' => 'nullable|string|max:500', 'reason' => 'required|string|max:500']);
        $old = $session->toArray();
        $session->name = $data['name']; $session->status = $data['status']; $session->save();
        $this->logAction('update_inventory_session', $session->location_id, 'session', $session->id, $old, $session->toArray(), $data['reason']);
        return back()->with('status', ['success' => 1, 'msg' => 'Session updated']);
    }

    public function updateLine(Request $request, SmartStockInventoryLine $line)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);
        $session = SmartStockInventorySession::findOrFail($line->session_id);
        abort_unless($session->business_id === $this->businessId() && $session->status !== 'completed', 422);
        $data = $request->validate(['actual_qty' => 'required|numeric', 'remark' => 'nullable|string|max:500', 'status' => 'nullable|string|max:40', 'imei' => 'nullable|string|max:191', 'lot_number' => 'nullable|string|max:191', 'reason' => 'required|string|max:500']);
        $old = $line->toArray();
        $line->actual_qty = (float) $data['actual_qty']; $line->difference_qty = $line->actual_qty - (float) $line->system_qty;
        $line->status = $data['status'] ?? ($line->difference_qty == 0 ? 'matched' : ($line->difference_qty > 0 ? 'over_stock' : 'missing'));
        $line->remark = $data['remark'] ?? null; $line->imei = $data['imei'] ?? $line->imei; $line->lot_number = $data['lot_number'] ?? $line->lot_number; $line->save();
        $this->logAction('update_inventory_line', $session->location_id, 'line', $line->id, $old, $line->toArray(), $data['reason']);
        return back()->with('status', ['success' => 1, 'msg' => 'Line updated']);
    }

    public function deleteSession(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.delete'), 403);
        abort_unless($session->business_id === $this->businessId() && $session->status === 'draft', 422);
        $reason = (string) $request->input('reason', 'manual_delete_draft_session');
        $old = $session->toArray();
        $lines = SmartStockInventoryLine::where('session_id', $session->id)->get()->toArray();
        SmartStockInventoryLine::where('session_id', $session->id)->delete(); $session->delete();
        $this->logAction('delete_inventory_session', $session->location_id, 'session', $session->id, ['session' => $old, 'lines' => $lines], null, $reason);
        return back()->with('status', ['success' => 1, 'msg' => 'Draft session deleted']);
    }

    public function deleteLine(Request $request, SmartStockInventoryLine $line)
    {
        abort_unless($request->user()->can('stock_inventory.delete'), 403);
        $session = SmartStockInventorySession::findOrFail($line->session_id);
        abort_unless($session->business_id === $this->businessId() && $session->status !== 'completed', 422);
        $reason = (string) $request->input('reason', 'manual_delete_line');
        $old = $line->toArray(); $line->delete();
        $this->logAction('delete_inventory_line', $session->location_id, 'line', $line->id, $old, null, $reason);
        return back()->with('status', ['success' => 1, 'msg' => 'Line deleted']);
    }

    public function deleteImported(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.delete'), 403);
        $data = $request->validate(['session_id' => 'required|integer', 'reason' => 'required|string|max:500']);
        $session = SmartStockInventorySession::where('business_id', $this->businessId())->findOrFail($data['session_id']);
        abort_unless($session->status !== 'completed', 422);
        $rows = SmartStockInventoryLine::where('session_id', $session->id)->where('remark', 'like', '%import%')->get();
        foreach ($rows as $row) {
            $old = $row->toArray(); $row->delete();
            $this->logAction('delete_wrong_imported_data', $session->location_id, 'line', $row->id, $old, null, $data['reason']);
        }
        return back()->with('status', ['success' => 1, 'msg' => 'Imported lines cleaned']);
    }

    public function complete(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.edit'), 403);
        $request->validate(['session_id' => 'required|integer']);
        $session = SmartStockInventorySession::where('business_id', $this->businessId())->findOrFail($request->session_id);
        $session->status = 'completed'; $session->completed_by = auth()->id(); $session->completed_at = now(); $session->save();
        return back()->with('status', ['success' => 1, 'msg' => 'Inventory count completed']);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.export'), 403);
        $lines = SmartStockInventoryLine::where('session_id', (int) $request->input('session_id'))->get();
        $rows = $lines->map(fn ($l) => ['SKU' => $l->sku, 'Product' => $l->product_name, 'Variation' => $l->variation_name, 'IMEI' => $l->imei, 'Lot Number' => $l->lot_number, 'System Qty' => $l->system_qty, 'Actual Qty' => $l->actual_qty, 'Difference' => $l->difference_qty, 'Status' => $l->status, 'Remark' => $l->remark])->all();
        return Excel::download(new ArrayExport($rows), 'smart_stock_count_' . now()->format('Ymd_His') . '.xlsx');
    }
    public function print(Request $request, SmartStockInventorySession $session) { abort_unless($request->user()->can('stock_inventory.view'), 403); abort_unless($session->business_id === $this->businessId(), 403); $lines = SmartStockInventoryLine::where('session_id', $session->id)->get(); return view('smartstockinventory::count.print', compact('session', 'lines')); }
    public function import(Request $request) { abort_unless($request->user()->can('stock_inventory.create'), 403); return back()->with('status', ['success' => 1, 'msg' => 'Import endpoint ready.']); }

    private function logAction(string $actionType, ?int $locationId, ?string $refType, ?int $refId, $old, $new, ?string $reason): void
    {
        SmartStockActionLog::create(['user_id' => auth()->id(), 'business_id' => $this->businessId(), 'location_id' => $locationId, 'action_type' => $actionType, 'reference_type' => $refType, 'reference_id' => $refId, 'old_data' => $old ? json_encode($old) : null, 'new_data' => $new ? json_encode($new) : null, 'reason' => $reason]);
    }
}

