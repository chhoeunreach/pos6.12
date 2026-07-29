<?php

namespace Modules\HrSellManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        abort_unless($this->canOpen(), 403);

        $businessId = (int) session('user.business_id');
        $today = now()->toDateString();

        $pos = $this->posHrDashboardData($today);
        $managed = $this->managedDashboardData($businessId, $today);

        return view('hrsellmanagement::dashboard.index', [
            'metrics' => array_merge($pos['metrics'], $managed['metrics']),
            'topHr' => $pos['topHr'],
            'topBranches' => $pos['topBranches'],
            'recent' => $pos['recent'],
            'hrConnectionOk' => $pos['ok'],
            'hrConnectionMessage' => $pos['message'],
        ]);
    }

    private function posHrDashboardData(string $today): array
    {
        try {
            $base = DB::connection('hr')->table('sell_out_reports');

            $metrics = [
                'pos_total_sales' => (float) (clone $base)->sum('total_amount'),
                'pos_total_count' => (int) (clone $base)->count(),
                'pos_today_sales' => (float) (clone $base)->whereDate('created_at', $today)->sum('total_amount'),
                'pos_today_count' => (int) (clone $base)->whereDate('created_at', $today)->count(),
                'pos_branch_count' => (int) (clone $base)->whereNotNull('branch_name')->where('branch_name', '!=', '')->distinct()->count('branch_name'),
                'pos_seller_count' => (int) (clone $base)->where(function ($q) {
                    $q->whereNotNull('seller_name')->where('seller_name', '!=', '')
                        ->orWhereNotNull('user_id');
                })->count(DB::raw("DISTINCT COALESCE(CAST(user_id AS CHAR), NULLIF(TRIM(seller_name), ''))")),
            ];

            $metrics['pos_average_sale'] = $metrics['pos_total_count'] > 0
                ? round($metrics['pos_total_sales'] / $metrics['pos_total_count'], 2)
                : 0;

            $topHr = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->select(
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as user_name"),
                    DB::raw('COUNT(*) as sale_count'),
                    DB::raw('COALESCE(SUM(sor.total_amount), 0) as sale_total')
                )
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown')"))
                ->orderByDesc('sale_total')
                ->limit(10)
                ->get();

            $topBranches = DB::connection('hr')
                ->table('sell_out_reports')
                ->select(
                    DB::raw("COALESCE(NULLIF(TRIM(branch_name), ''), 'Unknown') as branch_name"),
                    DB::raw('COUNT(*) as sale_count'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as sale_total')
                )
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(branch_name), ''), 'Unknown')"))
                ->orderByDesc('sale_total')
                ->limit(10)
                ->get();

            $recent = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->select(
                    'sor.invoice_no',
                    'sor.customer_name',
                    'sor.customer_phone',
                    'sor.branch_name',
                    'sor.service_type',
                    'sor.total_amount',
                    'sor.created_at',
                    DB::raw('COALESCE(u.name, sor.seller_name) as staff_name')
                )
                ->orderByDesc('sor.created_at')
                ->limit(20)
                ->get();

            return [
                'ok' => true,
                'message' => null,
                'metrics' => $metrics,
                'topHr' => $topHr,
                'topBranches' => $topBranches,
                'recent' => $recent,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sell dashboard POS data: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'metrics' => [
                    'pos_total_sales' => 0,
                    'pos_total_count' => 0,
                    'pos_today_sales' => 0,
                    'pos_today_count' => 0,
                    'pos_average_sale' => 0,
                    'pos_branch_count' => 0,
                    'pos_seller_count' => 0,
                ],
                'topHr' => collect(),
                'topBranches' => collect(),
                'recent' => collect(),
            ];
        }
    }

    private function managedDashboardData(int $businessId, string $today): array
    {
        $base = DB::table('hr_sell_records')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        return [
            'metrics' => [
                'managed_total_sales' => (float) (clone $base)->sum('sale_total'),
                'managed_count' => (int) (clone $base)->count(),
                'pending_approval' => (int) (clone $base)->where('approval_status', 'pending')->count(),
                'followups_due' => (int) (clone $base)->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', $today)->where('follow_up_status', '!=', 'completed')->count(),
                'commission_total' => (float) (clone $base)->where('approval_status', 'approved')->sum('commission_amount'),
                'due_total' => (float) (clone $base)->sum('due_total'),
            ],
        ];
    }

    private function canOpen(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.view') || $user->can('superadmin') || $user->can('business_settings.access');
    }
}
