<?php

namespace Modules\HrSellManagement\Http\Controllers;

use App\Transaction;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\HrSellManagement\Entities\HrSellRecord;
use Modules\HrSellManagement\Services\HrSellService;

class HrSellController extends Controller
{
    public function __construct(private HrSellService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->canOpen(), 403);
        $businessId = (int) session('user.business_id');
        $records = HrSellRecord::with(['transaction.contact', 'hrUser'])
            ->where('business_id', $businessId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('internal_note', 'like', $search)
                        ->orWhereHas('transaction', fn ($transaction) => $transaction->where('invoice_no', 'like', $search))
                        ->orWhereHas('transaction.contact', fn ($contact) => $contact->where('name', 'like', $search));
                });
            })
            ->when($request->filled('hr_user_id'), fn ($q) => $q->where('hr_user_id', $request->input('hr_user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('approval_status'), fn ($q) => $q->where('approval_status', $request->input('approval_status')))
            ->when($request->filled('follow_up_status'), fn ($q) => $q->where('follow_up_status', $request->input('follow_up_status')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereHas('transaction', fn ($transaction) => $transaction->whereDate('transaction_date', '>=', $request->input('start_date'))))
            ->when($request->filled('end_date'), fn ($q) => $q->whereHas('transaction', fn ($transaction) => $transaction->whereDate('transaction_date', '<=', $request->input('end_date'))))
            ->latest()
            ->paginate(50)
            ->appends($request->query());

        $users = User::forDropdown($businessId, false, false, true);
        $statuses = ['draft', 'active', 'on_hold', 'completed', 'cancelled'];
        $approvalStatuses = ['pending', 'approved', 'rejected'];
        $followUpStatuses = ['none', 'scheduled', 'called', 'completed', 'missed'];
        $unlinkedSales = Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('hr_sell_records as h')->whereColumn('h.transaction_id', 'transactions.id')->whereNull('h.deleted_at');
            })
            ->latest('transaction_date')
            ->limit(100)
            ->get(['id', 'invoice_no', 'transaction_date', 'final_total']);

        return view('hrsellmanagement::sales.index', compact('records', 'users', 'unlinkedSales', 'statuses', 'approvalStatuses', 'followUpStatuses'));
    }

    public function link(Request $request)
    {
        abort_unless(auth()->user()->can('hr_sell.create'), 403);
        $data = $request->validate([
            'transaction_id' => 'required|integer',
            'hr_user_id' => 'required|integer',
            'supervisor_id' => 'nullable|integer',
            'commission_type' => 'nullable|string|in:percent,fixed',
            'commission_value' => 'nullable|numeric|min:0',
            'follow_up_date' => 'nullable|date',
            'internal_note' => 'nullable|string|max:2000',
        ]);

        $record = $this->service->linkTransaction((int) session('user.business_id'), (int) $data['transaction_id'], (int) $data['hr_user_id'], (int) auth()->id(), $data, $request);

        return redirect()->route('hr-sell.sales.show', $record->id)->with('status', ['success' => 1, 'msg' => 'Sale linked to HR']);
    }

    public function show(HrSellRecord $hrSell)
    {
        abort_unless($this->canOpen(), 403);
        $this->authorizeBusiness($hrSell);
        $hrSell->load(['transaction.contact', 'transaction.sell_lines.product', 'hrUser', 'notes', 'approvals']);
        $users = User::forDropdown((int) session('user.business_id'), false, false, true);
        $logs = DB::table('hr_sell_logs')->where('business_id', $hrSell->business_id)->where('hr_sell_record_id', $hrSell->id)->latest()->limit(100)->get();

        return view('hrsellmanagement::sales.show', compact('hrSell', 'users', 'logs'));
    }

    public function update(Request $request, HrSellRecord $hrSell)
    {
        abort_unless(auth()->user()->can('hr_sell.update'), 403);
        $this->authorizeBusiness($hrSell);
        $data = $request->validate([
            'hr_user_id' => 'nullable|integer',
            'supervisor_id' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,active,on_hold,completed,cancelled',
            'commission_type' => 'nullable|string|in:percent,fixed',
            'commission_value' => 'nullable|numeric|min:0',
            'follow_up_date' => 'nullable|date',
            'follow_up_status' => 'nullable|string|in:none,scheduled,called,completed,missed',
            'internal_note' => 'nullable|string|max:2000',
        ]);
        $this->service->updateRecord($hrSell, $data, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'HR sale updated']);
    }

    public function approve(Request $request, HrSellRecord $hrSell)
    {
        abort_unless(auth()->user()->can('hr_sell.approve'), 403);
        $this->authorizeBusiness($hrSell);
        $data = $request->validate(['level' => 'required|string|max:40', 'status' => 'required|string|in:approved,rejected', 'note' => 'nullable|string|max:2000']);
        $this->service->approve($hrSell, $data['level'], $data['status'], $data['note'] ?? null, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Approval updated']);
    }

    public function storeNote(Request $request, HrSellRecord $hrSell)
    {
        abort_unless(auth()->user()->can('hr_sell.update'), 403);
        $this->authorizeBusiness($hrSell);
        $data = $request->validate(['note_type' => 'required|string|in:note,call,visit,problem,promise', 'note' => 'required|string|max:3000', 'next_follow_up_date' => 'nullable|date']);
        $this->service->addNote($hrSell, $data, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Note saved']);
    }

    private function authorizeBusiness(HrSellRecord $record): void
    {
        abort_unless((int) $record->business_id === (int) session('user.business_id'), 403);
    }

    private function canOpen(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.view') || $user->can('superadmin') || $user->can('business_settings.access');
    }
}
