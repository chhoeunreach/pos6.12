<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;

class DashboardController extends Controller
{
    protected FinancialCalculationService $calcService;

    public function __construct(FinancialCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    public function index(Request $request)
    {
        $businessId = session('business.id', 1);

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        // 1. Profit & Loss metrics
        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $locationId);

        // 2. Cash Flow metrics
        $cashFlow = $this->calcService->calculateCashFlow($businessId, $startDate, $endDate, $locationId);

        // 3. Current Liquid Balances (Cash & Bank)
        $accounts = FinancialAccount::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        $cashBalance = (float) $accounts->whereIn('account_type', ['cash', 'petty_cash'])->sum('current_balance');
        $bankBalance = (float) $accounts->whereIn('account_type', ['bank', 'digital_wallet'])->sum('current_balance');

        // 4. Pending Approvals count & amount
        $pendingQuery = FinancialTransaction::where('business_id', $businessId)
            ->whereIn('approval_status', ['pending', 'reviewed']);
        if ($locationId) {
            $pendingQuery->where('business_location_id', $locationId);
        }
        $pendingCount = (int) (clone $pendingQuery)->count();
        $pendingAmount = (float) (clone $pendingQuery)->sum('amount');

        // 5. Reconciliation Differences (from recent closings)
        $reconciliationDiff = (float) FinancialDailyClosing::where('business_id', $businessId)
            ->whereBetween('closing_date', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->sum('cash_difference');

        // 6. Branch Locations for filter & branch performance cards
        $branches = BusinessLocation::where('business_id', $businessId)->get();

        $branchPerformance = [];
        foreach ($branches as $branch) {
            $branchPnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $branch->id);
            $branchPerformance[] = [
                'id' => $branch->id,
                'name' => $branch->name,
                'net_sales' => $branchPnl['net_sales'],
                'gross_profit' => $branchPnl['gross_profit'],
                'operating_expenses' => $branchPnl['operating_expenses'],
                'net_profit' => $branchPnl['net_profit'],
                'net_margin_percent' => $branchPnl['net_margin_percent'],
            ];
        }

        // 7. Recent Transactions for Dashboard Feed
        $recentTransactions = FinancialTransaction::with(['category', 'location', 'account', 'creator'])
            ->where('business_id', $businessId)
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return view('dailyfinancialmanagement::dashboard.index', compact(
            'pnl',
            'cashFlow',
            'cashBalance',
            'bankBalance',
            'pendingCount',
            'pendingAmount',
            'reconciliationDiff',
            'branches',
            'branchPerformance',
            'recentTransactions',
            'startDate',
            'endDate',
            'locationId'
        ));
    }

    public function ajaxMetrics(Request $request): JsonResponse
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $locationId);
        $cashFlow = $this->calcService->calculateCashFlow($businessId, $startDate, $endDate, $locationId);

        return response()->json([
            'success' => true,
            'pnl' => $pnl,
            'cash_flow' => $cashFlow,
        ]);
    }
}

