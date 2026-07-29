<?php

namespace Modules\HrSellManagement\Http\Controllers;

use App\BusinessLocation;
use App\Exports\ArrayExport;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->canReport(), 403);
        [$rows, $summary] = $this->reportData($request, true);
        $users = User::forDropdown((int) session('user.business_id'), false, false, true);
        $businessLocations = BusinessLocation::forDropdown((int) session('user.business_id'), false);
        $statuses = ['draft', 'active', 'on_hold', 'completed', 'cancelled'];
        $approvalStatuses = ['pending', 'approved', 'rejected'];
        $followUpStatuses = ['none', 'scheduled', 'called', 'completed', 'missed'];

        return view('hrsellmanagement::reports.index', compact('rows', 'summary', 'users', 'businessLocations', 'statuses', 'approvalStatuses', 'followUpStatuses'));
    }

    public function export(Request $request)
    {
        abort_unless($this->canReport(), 403);
        [$rows] = $this->reportData($request, false);
        $exportRows = $rows->map(fn ($r) => [
            'HR Sell ID' => $r->id,
            'Invoice' => $r->invoice_no,
            'Sale Date' => $r->transaction_date,
            'Location' => $r->location_name,
            'Customer' => $r->customer,
            'HR Staff' => $r->hr_name,
            'Supervisor' => $r->supervisor_name,
            'Record Status' => $r->status,
            'Approval Status' => $r->approval_status,
            'Follow Up Date' => $r->follow_up_date,
            'Follow Up Status' => $r->follow_up_status,
            'Sale Total' => $r->sale_total,
            'Paid' => $r->paid_total,
            'Due' => $r->due_total,
            'Commission Type' => $r->commission_type,
            'Commission Value' => $r->commission_value,
            'Commission' => $r->commission_amount,
            'Internal Note' => $r->internal_note,
            'Created By' => $r->created_by_name,
            'Created At' => $r->created_at,
            'Updated At' => $r->updated_at,
        ])->all();

        return Excel::download(new ArrayExport($exportRows), 'hr_sell_report_' . now()->format('Ymd_His') . '.xlsx');
    }

    private function reportData(Request $request, bool $paginate): array
    {
        $query = $this->baseReportQuery($request);
        $summaryQuery = clone $query;
        $summary = (array) $summaryQuery->selectRaw('
            COUNT(*) as sale_count,
            COALESCE(SUM(h.sale_total), 0) as sale_total,
            COALESCE(SUM(h.paid_total), 0) as paid_total,
            COALESCE(SUM(h.due_total), 0) as due_total,
            COALESCE(SUM(h.commission_amount), 0) as commission_amount
        ')->first();

        $rowsQuery = $query->select([
            'h.*',
            't.invoice_no',
            't.transaction_date',
            'c.name as customer',
            'bl.name as location_name',
            DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) as hr_name"),
            DB::raw("TRIM(CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,''))) as supervisor_name"),
            DB::raw("TRIM(CONCAT(COALESCE(cb.first_name,''),' ',COALESCE(cb.last_name,''))) as created_by_name"),
        ])->latest('t.transaction_date')->latest('h.id');

        $rows = $paginate
            ? $rowsQuery->paginate(50)->appends($request->query())
            : $rowsQuery->get();

        return [$rows, $summary];
    }

    private function baseReportQuery(Request $request)
    {
        return DB::table('hr_sell_records as h')
            ->join('transactions as t', 'h.transaction_id', '=', 't.id')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', DB::raw('COALESCE(h.location_id, t.location_id)'))
            ->leftJoin('users as u', 'h.hr_user_id', '=', 'u.id')
            ->leftJoin('users as s', 'h.supervisor_id', '=', 's.id')
            ->leftJoin('users as cb', 'h.created_by', '=', 'cb.id')
            ->where('h.business_id', (int) session('user.business_id'))
            ->whereNull('h.deleted_at')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('t.invoice_no', 'like', $search)
                        ->orWhere('c.name', 'like', $search)
                        ->orWhere('h.internal_note', 'like', $search);
                });
            })
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('t.transaction_date', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('t.transaction_date', '<=', $request->input('end_date')))
            ->when($request->filled('location_id'), function ($q) use ($request) {
                $locationId = $request->input('location_id');
                $q->where(function ($query) use ($locationId) {
                    $query->where('h.location_id', $locationId)
                        ->orWhere('t.location_id', $locationId);
                });
            })
            ->when($request->filled('hr_user_id'), fn ($q) => $q->where('h.hr_user_id', $request->input('hr_user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('h.status', $request->input('status')))
            ->when($request->filled('approval_status'), fn ($q) => $q->where('h.approval_status', $request->input('approval_status')))
            ->when($request->filled('follow_up_status'), fn ($q) => $q->where('h.follow_up_status', $request->input('follow_up_status')));
    }

    private function canReport(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.report') || $user->can('superadmin') || $user->can('business_settings.access');
    }
}
