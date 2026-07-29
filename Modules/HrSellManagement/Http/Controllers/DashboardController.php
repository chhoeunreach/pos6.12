<?php

namespace Modules\HrSellManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('hr_sell.view'), 403);
        $businessId = (int) session('user.business_id');
        $today = now()->toDateString();

        $base = DB::table('hr_sell_records')->where('business_id', $businessId)->whereNull('deleted_at');
        $metrics = [
            'total_sales' => (clone $base)->sum('sale_total'),
            'today_sales' => (clone $base)->whereDate('created_at', $today)->sum('sale_total'),
            'pending_approval' => (clone $base)->where('approval_status', 'pending')->count(),
            'followups_due' => (clone $base)->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', $today)->where('follow_up_status', '!=', 'completed')->count(),
            'commission_total' => (clone $base)->where('approval_status', 'approved')->sum('commission_amount'),
            'due_total' => (clone $base)->sum('due_total'),
        ];

        $topHr = DB::table('hr_sell_records as h')
            ->leftJoin('users as u', 'h.hr_user_id', '=', 'u.id')
            ->where('h.business_id', $businessId)
            ->whereNull('h.deleted_at')
            ->groupBy('h.hr_user_id', 'u.first_name', 'u.last_name')
            ->orderByDesc('sale_total')
            ->limit(10)
            ->get([
                'h.hr_user_id',
                DB::raw("CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as user_name"),
                DB::raw('COUNT(*) as sale_count'),
                DB::raw('SUM(h.sale_total) as sale_total'),
                DB::raw('SUM(h.commission_amount) as commission_total'),
            ]);

        $recent = DB::table('hr_sell_records as h')
            ->join('transactions as t', 'h.transaction_id', '=', 't.id')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->leftJoin('users as u', 'h.hr_user_id', '=', 'u.id')
            ->where('h.business_id', $businessId)
            ->whereNull('h.deleted_at')
            ->latest('h.created_at')
            ->limit(20)
            ->get(['h.id', 't.invoice_no', 'c.name as customer', 'h.sale_total', 'h.approval_status', 'h.follow_up_date', DB::raw("CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as hr_name")]);

        return view('hrsellmanagement::dashboard.index', compact('metrics', 'topHr', 'recent'));
    }
}
