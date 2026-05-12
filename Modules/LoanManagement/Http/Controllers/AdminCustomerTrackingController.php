<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Services\CustomerLocationTrackingService;

class AdminCustomerTrackingController extends Controller
{
    public function __construct(protected CustomerLocationTrackingService $trackingService)
    {
    }

    public function index()
    {
        return view('loanmanagement::tracking.customer_map');
    }

    public function data()
    {
        $rows = DB::connection('mysql_loan')->table('loan_customer_location_latest as l')
            ->join('loan_customers as c', 'c.id', '=', 'l.customer_id')
            ->leftJoin('loans as lo', 'lo.id', '=', 'l.loan_id')
            ->selectRaw('c.id as customer_id, c.name as customer_name, c.phone, c.allow_gps_tracking, l.loan_id, lo.loan_number, lo.balance_amount, l.latitude, l.longitude, l.speed, l.battery_level, l.recorded_at')
            ->where('c.allow_gps_tracking', 1)
            ->orderByDesc('l.recorded_at')
            ->limit(500)
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows]);
    }

    public function history(Request $request, int $customerId)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $rows = $this->trackingService->getLocationHistory($customerId, $from, $to);
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows]);
    }

    public function toggle(Request $request, int $customerId)
    {
        $request->validate([
            'allow_gps_tracking' => 'required|boolean',
            'note' => 'nullable|string|max:1000',
        ]);
        if (! auth()->user()->can('loan_management.customer_gps.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $customer = LoanCustomer::query()->findOrFail($customerId);
        if ((bool) $request->input('allow_gps_tracking')) {
            $this->trackingService->enableTracking($customer, $request->input('note'));
        } else {
            $this->trackingService->disableTracking($customer, $request->input('note'));
        }

        return redirect()->back()->with('status', ['success' => 1, 'msg' => 'Customer GPS tracking updated']);
    }
}
