<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;

class ReportController extends Controller
{
    protected FinancialCalculationService $calcService;

    public function __construct(FinancialCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    public function executiveReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $date, $date, $locationId);
        $cashFlow = $this->calcService->calculateCashFlow($businessId, $date, $date, $locationId);
        $reconciliation = $this->calcService->calculateReconciliation($businessId, $date, $locationId);

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        // Closings for this date
        $closings = FinancialDailyClosing::with(['location', 'closer', 'approver'])
            ->where('business_id', $businessId)
            ->whereDate('closing_date', $date)
            ->get();

        return view('dailyfinancialmanagement::reports.executive', compact(
            'date',
            'locationId',
            'branches',
            'pnl',
            'cashFlow',
            'reconciliation',
            'closings'
        ));
    }

    public function profitLossReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;

        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $locationId, $departmentId);
        $branches = BusinessLocation::where('business_id', $businessId)->get();

        // Detailed expenses grouped by category
        $expenseBreakdown = FinancialTransaction::with('category')
            ->where('business_id', $businessId)
            ->where('transaction_type', 'expense')
            ->where('approval_status', 'approved')
            ->where('is_operating', true)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->selectRaw('category_id, count(*) as count, sum(amount_in_base_currency) as total_amount')
            ->groupBy('category_id')
            ->get();

        return view('dailyfinancialmanagement::reports.profit_loss', compact(
            'startDate',
            'endDate',
            'locationId',
            'departmentId',
            'branches',
            'pnl',
            'expenseBreakdown'
        ));
    }

    public function cashFlowReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $cashFlow = $this->calcService->calculateCashFlow($businessId, $startDate, $endDate, $locationId);
        $branches = BusinessLocation::where('business_id', $businessId)->get();

        return view('dailyfinancialmanagement::reports.cash_flow', compact(
            'startDate',
            'endDate',
            'locationId',
            'branches',
            'cashFlow'
        ));
    }

    public function incomeReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $incomes = FinancialTransaction::with(['category', 'location', 'account', 'creator'])
            ->where('business_id', $businessId)
            ->whereIn('transaction_type', ['income', 'loan_interest'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->orderBy('transaction_date', 'desc')
            ->paginate(50);

        $branches = BusinessLocation::where('business_id', $businessId)->get();
        $totalAmount = (float) FinancialTransaction::where('business_id', $businessId)
            ->whereIn('transaction_type', ['income', 'loan_interest'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->sum('amount_in_base_currency');

        return view('dailyfinancialmanagement::reports.income_report', compact(
            'startDate',
            'endDate',
            'locationId',
            'branches',
            'incomes',
            'totalAmount'
        ));
    }

    public function expenseReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $expenses = FinancialTransaction::with(['category', 'location', 'account', 'creator'])
            ->where('business_id', $businessId)
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->orderBy('transaction_date', 'desc')
            ->paginate(50);

        $branches = BusinessLocation::where('business_id', $businessId)->get();
        $totalAmount = (float) FinancialTransaction::where('business_id', $businessId)
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->sum('amount_in_base_currency');

        return view('dailyfinancialmanagement::reports.expense_report', compact(
            'startDate',
            'endDate',
            'locationId',
            'branches',
            'expenses',
            'totalAmount'
        ));
    }

    public function branchComparisonReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        $comparison = [];
        foreach ($branches as $branch) {
            $pnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $branch->id);
            $cashFlow = $this->calcService->calculateCashFlow($businessId, $startDate, $endDate, $branch->id);

            $comparison[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'location_id' => $branch->location_id,
                'net_sales' => $pnl['net_sales'],
                'cogs' => $pnl['cogs'],
                'gross_profit' => $pnl['gross_profit'],
                'gross_margin' => $pnl['gross_margin_percent'],
                'operating_expenses' => $pnl['operating_expenses'],
                'net_profit' => $pnl['net_profit'],
                'net_margin' => $pnl['net_margin_percent'],
                'total_inflow' => $cashFlow['total_inflows'],
                'total_outflow' => $cashFlow['total_outflows'],
                'net_cash_flow' => $cashFlow['net_cash_flow'],
            ];
        }

        return view('dailyfinancialmanagement::reports.branch_comparison', compact(
            'startDate',
            'endDate',
            'comparison'
        ));
    }

    public function departmentSummaryReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $departments = FinancialTransaction::where('business_id', $businessId)
            ->whereNotNull('department_id')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw('department_id, sum(case when transaction_type in ("income","pos_sale") then amount_in_base_currency else 0 end) as total_revenue, sum(case when transaction_type="expense" then amount_in_base_currency else 0 end) as total_expense')
            ->groupBy('department_id')
            ->get();

        return view('dailyfinancialmanagement::reports.department_summary', compact(
            'startDate',
            'endDate',
            'departments'
        ));
    }

    public function pendingReport(Request $request)
    {
        $businessId = session('business.id', 1);

        $pending = FinancialTransaction::with(['category', 'location', 'account', 'creator'])
            ->where('business_id', $businessId)
            ->whereIn('approval_status', ['draft', 'pending', 'reviewed'])
            ->orderBy('transaction_date', 'asc')
            ->get();

        return view('dailyfinancialmanagement::reports.pending_transactions', compact('pending'));
    }

    public function monthlySummaryReport(Request $request)
    {
        $businessId = session('business.id', 1);
        $year = $request->input('year', date('Y'));

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = Carbon::createFromDate($year, $m, 1)->format('Y-m-d');
            $monthEnd = Carbon::createFromDate($year, $m, 1)->endOfMonth()->format('Y-m-d');

            $pnl = $this->calcService->calculateProfitAndLoss($businessId, $monthStart, $monthEnd);
            $cashFlow = $this->calcService->calculateCashFlow($businessId, $monthStart, $monthEnd);

            $months[] = [
                'month_name' => Carbon::createFromDate($year, $m, 1)->format('M Y'),
                'net_sales' => $pnl['net_sales'],
                'gross_profit' => $pnl['gross_profit'],
                'operating_expenses' => $pnl['operating_expenses'],
                'net_profit' => $pnl['net_profit'],
                'inflows' => $cashFlow['total_inflows'],
                'outflows' => $cashFlow['total_outflows'],
                'net_cash_flow' => $cashFlow['net_cash_flow'],
            ];
        }

        return view('dailyfinancialmanagement::reports.monthly_summary', compact('year', 'months'));
    }
}

