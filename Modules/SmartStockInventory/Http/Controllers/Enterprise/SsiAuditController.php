<?php

namespace Modules\SmartStockInventory\Http\Controllers\Enterprise;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\Enterprise\SsiAudit;
use Modules\SmartStockInventory\Services\Enterprise\SsiAuditService;
use Modules\SmartStockInventory\Services\Enterprise\SsiDashboardService;

class SsiAuditController extends Controller
{
    public function __construct(private Util $util, private SsiAuditService $audits, private SsiDashboardService $dashboard)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('ssi.audit.view'), 403);
        $businessId = $this->businessId();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $audits = SsiAudit::where('business_id', $businessId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->latest()
            ->paginate(25);
        $metrics = $this->dashboard->metrics($businessId, $locationId);
        $locations = DB::table('business_locations')->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']);

        return view('smartstockinventory::enterprise.audit.index', compact('audits', 'metrics', 'locations', 'locationId'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('ssi.audit.create'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'location_id' => 'nullable|integer',
            'audit_type' => 'required|string|in:cycle,blind,spot,full,recount',
            'count_mode' => 'required|string|in:normal,blind',
            'scheduled_at' => 'nullable|date',
            'assigned_to' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
            'scope' => 'nullable|array',
        ]);

        $audit = $this->audits->create($this->businessId(), (int) $request->user()->id, $data, $request);

        return redirect()->to(ssi_route('ssi.enterprise.audit.show', $audit->id))->with('status', ['success' => 1, 'msg' => 'Enterprise audit created']);
    }

    public function show(Request $request, SsiAudit $audit)
    {
        abort_unless($request->user()->can('ssi.audit.view'), 403);
        $this->authorizeBusiness($audit);
        $audit->load(['items' => fn ($q) => $q->latest()->limit(500), 'approvals', 'investigations']);
        $logs = DB::table('ssi_logs')->where('business_id', $audit->business_id)->where('audit_id', $audit->id)->latest()->limit(100)->get();

        return view('smartstockinventory::enterprise.audit.show', compact('audit', 'logs'));
    }

    public function start(Request $request, SsiAudit $audit)
    {
        abort_unless($request->user()->can('ssi.audit.update'), 403);
        $this->authorizeBusiness($audit);
        $this->audits->start($audit, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Audit started']);
    }

    public function verifyItem(Request $request, SsiAudit $audit, int $item)
    {
        abort_unless($request->user()->can('ssi.audit.verify'), 403);
        $this->authorizeBusiness($audit);
        $data = $request->validate(['verified_qty' => 'required|numeric']);
        $this->audits->verifyItem($audit->business_id, $item, (float) $data['verified_qty'], (int) $request->user()->id, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Item verified']);
    }

    public function approve(Request $request, SsiAudit $audit)
    {
        abort_unless($request->user()->can('ssi.audit.approve'), 403);
        $this->authorizeBusiness($audit);
        $data = $request->validate([
            'approval_level' => 'required|string|max:60',
            'note' => 'nullable|string|max:2000',
        ]);
        $this->audits->approve($audit, $data['approval_level'], (int) $request->user()->id, $data['note'] ?? null, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Approval recorded']);
    }

    private function businessId(): int
    {
        return (int) session('user.business_id');
    }

    private function authorizeBusiness(SsiAudit $audit): void
    {
        abort_unless((int) $audit->business_id === $this->businessId(), 403);
    }
}
