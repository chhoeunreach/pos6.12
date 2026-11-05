<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\DailyClosingService;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;

class DailyClosingController extends Controller
{
    protected DailyClosingService $closingService;
    protected FinancialCalculationService $calcService;

    public function __construct(DailyClosingService $closingService, FinancialCalculationService $calcService)
    {
        $this->closingService = $closingService;
        $this->calcService = $calcService;
    }

    public function index(Request $request)
    {
        $businessId = session('business.id', 1);

        $query = FinancialDailyClosing::with(['location', 'closer', 'approver'])
            ->where('business_id', $businessId);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('closing_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('location_id')) {
            $query->where('business_location_id', $request->location_id);
        }

        if ($request->filled('status')) {
            $query->where('closing_status', $request->status);
        }

        $closings = $query->orderBy('closing_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        return view('dailyfinancialmanagement::closing.index', compact('closings', 'branches'));
    }

    public function create(Request $request)
    {
        $businessId = session('business.id', 1);
        $closingDate = $request->input('closing_date', Carbon::now()->format('Y-m-d'));
        $locationId = (int) $request->input('location_id', 1);

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        // Check if unapproved transactions exist for this branch/date
        $pendingTransactionsCount = FinancialTransaction::where('business_id', $businessId)
            ->where('business_location_id', $locationId)
            ->where('transaction_date', $closingDate)
            ->whereIn('approval_status', ['draft', 'pending', 'reviewed'])
            ->count();

        // Calculate theoretical figures
        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $closingDate, $closingDate, $locationId);
        $reconciliation = $this->calcService->calculateReconciliation($businessId, $closingDate, $locationId);

        // Check if already created
        $existing = FinancialDailyClosing::where('business_id', $businessId)
            ->where('business_location_id', $locationId)
            ->where('closing_date', $closingDate)
            ->first();

        return view('dailyfinancialmanagement::closing.create', compact(
            'branches',
            'closingDate',
            'locationId',
            'pendingTransactionsCount',
            'pnl',
            'reconciliation',
            'existing'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'closing_date' => 'required|date',
            'business_location_id' => 'required|integer',
            'actual_cash_counted' => 'required|numeric|min:0',
            'actual_bank_balance' => 'required|numeric|min:0',
        ]);

        $businessId = session('business.id', 1);
        $date = $request->closing_date;
        $locationId = (int) $request->business_location_id;

        try {
            $result = $this->closingService->generateClosingSummary(
                $businessId,
                $date,
                $locationId,
                [
                    'actual_cash_counted' => (float) $request->actual_cash_counted,
                    'actual_bank_balance' => (float) $request->actual_bank_balance,
                    'closing_notes' => $request->closing_notes,
                    'reconciliation_notes' => $request->reconciliation_notes,
                    'denominations' => $request->input('denominations', []),
                ],
                auth()->user()
            );

            if (! $result['success']) {
                return back()->with('status', ['success' => 0, 'msg' => $result['error']]);
            }

            return redirect()->route('daily-financial.closing.show', $result['closing']->id)
                ->with('status', ['success' => 1, 'msg' => 'Daily closing summary generated successfully!']);
        } catch (Exception $e) {
            return back()->withInput()->with('status', ['success' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $businessId = session('business.id', 1);

        $closing = FinancialDailyClosing::with(['location', 'closer', 'approver', 'reconciliations.account'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $pnl = $this->calcService->calculateProfitAndLoss(
            $businessId,
            $closing->closing_date->format('Y-m-d'),
            $closing->closing_date->format('Y-m-d'),
            $closing->business_location_id
        );

        $cashFlow = $this->calcService->calculateCashFlow(
            $businessId,
            $closing->closing_date->format('Y-m-d'),
            $closing->closing_date->format('Y-m-d'),
            $closing->business_location_id
        );

        return view('dailyfinancialmanagement::closing.show', compact('closing', 'pnl', 'cashFlow'));
    }

    public function approve($id)
    {
        $businessId = session('business.id', 1);
        $closing = FinancialDailyClosing::where('business_id', $businessId)->findOrFail($id);

        $closing->update([
            'closing_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'Daily closing approved by management!']);
    }

    public function lockPeriod($id)
    {
        $businessId = session('business.id', 1);
        $closing = FinancialDailyClosing::where('business_id', $businessId)->findOrFail($id);

        try {
            $this->closingService->lockClosingPeriod($closing, auth()->user());

            return back()->with('status', ['success' => 1, 'msg' => 'Closing period locked. All transactions for this date and branch are now frozen.']);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Lock error: ' . $e->getMessage()]);
        }
    }
}

