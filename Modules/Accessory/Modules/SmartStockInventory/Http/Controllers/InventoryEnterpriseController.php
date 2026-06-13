<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartInventoryAssignment;
use Modules\SmartStockInventory\Models\SmartStockInventoryLine;
use Modules\SmartStockInventory\Models\SmartStockInventorySession;
use Modules\SmartStockInventory\Models\SmartStockSetting;
use Modules\SmartStockInventory\Services\InventoryWorkflowService;
use Modules\SmartStockInventory\Services\TelegramAlertService;

class InventoryEnterpriseController extends BaseSmartStockController
{
    public function __construct(\App\Utils\Util $util, private InventoryWorkflowService $workflow, private TelegramAlertService $telegram)
    {
        parent::__construct($util);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $businessId = $this->businessId();
        $sessions = SmartStockInventorySession::where('business_id', $businessId)->latest()->paginate(30);
        $settings = SmartStockSetting::firstOrCreate(['business_id' => $businessId]);

        return view('smartstockinventory::count.enterprise', [
            'locations' => $this->locationOptions($businessId),
            'sessions' => $sessions,
            'settings' => $settings,
        ]);
    }

    public function createSession(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.create'), 403);
        $data = $request->validate([
            'session_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'location_id' => 'required|integer',
            'warehouse' => 'nullable|string|max:191',
            'count_type' => 'required|string|in:full_count,partial_count,cycle_count,blind_count,imei_count,lot_count',
            'count_method' => 'required|string|in:manual,barcode_scan,imei_scan,import_excel',
            'count_by' => 'required|string|in:product,category,brand,supplier,location,rack,sku_range',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'blind_count' => 'nullable|boolean',
        ]);
        abort_unless(in_array((int) $data['location_id'], $this->permittedLocationIds($this->businessId()), true), 403);

        $session = $this->workflow->createSession($this->businessId(), (int) auth()->id(), $data);
        $this->workflow->logAudit($this->businessId(), $session->id, null, 'create_session', $request, null, $session->toArray());

        return back()->with('status', ['success' => 1, 'msg' => 'Advanced inventory session created']);
    }

    public function assignCounter(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        $data = $request->validate([
            'user_id' => 'required|integer',
            'category_id' => 'nullable|integer',
            'brand_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'rack' => 'nullable|string|max:191',
        ]);

        $assignment = SmartInventoryAssignment::create([
            'business_id' => $this->businessId(),
            'session_id' => $session->id,
            'user_id' => $data['user_id'],
            'location_id' => $session->location_id,
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'rack' => $data['rack'] ?? null,
            'status' => 'active',
        ]);

        $this->workflow->logAudit($this->businessId(), $session->id, null, 'assign_counter', $request, null, $assignment->toArray());
        return back()->with('status', ['success' => 1, 'msg' => 'Counter assigned']);
    }

    public function countLine(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        $data = $request->validate([
            'product_id' => 'nullable|integer',
            'variation_id' => 'nullable|integer',
            'sku' => 'nullable|string|max:191',
            'product_name' => 'nullable|string|max:255',
            'variation_name' => 'nullable|string|max:255',
            'imei' => 'nullable|string|max:191',
            'lot_number' => 'nullable|string|max:191',
            'rack' => 'nullable|string|max:191',
            'actual_qty' => 'required|numeric',
            'system_qty' => 'nullable|numeric',
            'remark' => 'nullable|string|max:500',
        ]);
        abort_unless(in_array((string) $session->status, ['draft', 'in_progress', 'recount_required'], true), 422);

        $line = SmartStockInventoryLine::create([
            'session_id' => $session->id,
            'counted_by_user_id' => auth()->id(),
            'product_id' => $data['product_id'] ?? null,
            'variation_id' => $data['variation_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'variation_name' => $data['variation_name'] ?? null,
            'imei' => $data['imei'] ?? null,
            'lot_number' => $data['lot_number'] ?? null,
            'rack' => $data['rack'] ?? null,
            'system_qty' => (float) ($data['system_qty'] ?? 0),
            'actual_qty' => (float) $data['actual_qty'],
            'difference_qty' => (float) $data['actual_qty'] - (float) ($data['system_qty'] ?? 0),
            'status' => 'first_count_done',
            'verification_status' => 'verification_pending',
            'remark' => $data['remark'] ?? null,
        ]);

        $session->status = 'in_progress';
        $session->save();
        $this->workflow->logAudit($this->businessId(), $session->id, $line->id, 'count_line', $request, null, $line->toArray());

        return response()->json(['success' => 1, 'line_id' => $line->id]);
    }

    public function verifyLine(Request $request, SmartStockInventorySession $session, SmartStockInventoryLine $line)
    {
        abort_unless($request->user()->can('stock_inventory.verify'), 403);
        abort_unless($session->business_id === $this->businessId() && (int) $line->session_id === (int) $session->id, 403);
        $data = $request->validate(['verified_qty' => 'required|numeric']);
        $setting = SmartStockSetting::firstOrCreate(['business_id' => $this->businessId()]);
        $result = $this->workflow->verifyLine($this->businessId(), $line, (float) $data['verified_qty'], (int) auth()->id(), (float) $setting->recount_threshold);

        if ($result['need_recount']) {
            $session->status = 'recount_required';
            $session->save();
            $this->telegram->send('Recount required', ['Session' => $session->name, 'Line ID' => $line->id]);
        }

        $this->workflow->logAudit($this->businessId(), $session->id, $line->id, 'verify_line', $request, $result['old'], $result['new']);
        return back()->with('status', ['success' => 1, 'msg' => 'Verification updated']);
    }

    public function approve(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.approve'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        $data = $request->validate(['approval_level' => 'required|string|in:supervisor,manager', 'note' => 'nullable|string|max:500']);
        $approval = $this->workflow->approveSession($session, $data['approval_level'], (string) ($data['note'] ?? ''));
        $this->workflow->logAudit($this->businessId(), $session->id, null, 'approve_session', $request, null, $approval->toArray());

        if ($data['approval_level'] === 'manager') {
            $this->telegram->send('Inventory approved', ['Session' => $session->name, 'Approval ID' => $approval->id]);
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Approval saved']);
    }

    public function freeze(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.freeze'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        $data = $request->validate(['freeze_type' => 'required|string|in:full_location,selected_product', 'product_id' => 'nullable|integer']);
        $freeze = $this->workflow->freezeSession($this->businessId(), $session->id, $data['freeze_type'], (int) $session->location_id, $data['product_id'] ?? null);
        $session->freeze_mode = 1;
        $session->save();

        $this->workflow->logAudit($this->businessId(), $session->id, null, 'freeze_inventory', $request, null, $freeze->toArray());
        return back()->with('status', ['success' => 1, 'msg' => 'Inventory freeze activated']);
    }

    public function dashboard(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        $lines = SmartStockInventoryLine::where('session_id', $session->id)->whereNull('deleted_at')->get();
        $total = $lines->count();
        $counted = $lines->where('status', 'first_count_done')->count();
        $mismatch = $lines->where('difference_qty', '!=', 0)->count();
        $missingQty = (float) $lines->where('difference_qty', '<', 0)->sum('difference_qty');
        $overQty = (float) $lines->where('difference_qty', '>', 0)->sum('difference_qty');

        return response()->json([
            'total_products' => $total,
            'counted_products' => $counted,
            'remaining_products' => max(0, $total - $counted),
            'mismatch_count' => $mismatch,
            'missing_qty' => abs($missingQty),
            'over_qty' => $overQty,
            'progress_percent' => $total > 0 ? round(($counted / $total) * 100, 2) : 0,
        ]);
    }

    public function adjustmentPreview(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.adjust'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        $rows = $this->workflow->adjustmentPreview($session->id);

        if ($request->input('export') == '1') {
            return Excel::download(new ArrayExport($rows), 'adjustment_preview_' . $session->id . '.xlsx');
        }

        return view('smartstockinventory::count.adjustment_preview', compact('session', 'rows'));
    }

    public function mobile(Request $request, SmartStockInventorySession $session)
    {
        abort_unless($request->user()->can('stock_inventory.mobile') || $request->user()->can('stock_inventory.update'), 403);
        abort_unless($session->business_id === $this->businessId(), 403);
        return view('smartstockinventory::count.mobile', compact('session'));
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.report'), 403);
        $businessId = $this->businessId();

        $full = DB::table('smart_stock_inventory_lines as l')->join('smart_stock_inventory_sessions as s', 's.id', '=', 'l.session_id')
            ->where('s.business_id', $businessId)->whereNull('l.deleted_at')
            ->select('s.name as session', 'l.sku', 'l.product_name', 'l.imei', 'l.lot_number', 'l.system_qty', 'l.actual_qty', 'l.difference_qty', 'l.counted_by_user_id')->get();

        $mismatch = $full->where('difference_qty', '!=', 0)->values();
        $missing = $full->where('difference_qty', '<', 0)->values();
        $over = $full->where('difference_qty', '>', 0)->values();
        $imeiMissing = $full->where('imei', null)->values();
        $lotDiff = $full->where('lot_number', '!=', null)->where('difference_qty', '!=', 0)->values();

        return view('smartstockinventory::count.reports', compact('full', 'mismatch', 'missing', 'over', 'imeiMissing', 'lotDiff'));
    }
}
