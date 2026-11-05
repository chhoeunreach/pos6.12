<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialReconciliation;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;

class ReconciliationController extends Controller
{
    protected FinancialCalculationService $calcService;

    public function __construct(FinancialCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    public function index(Request $request)
    {
        $businessId = session('business.id', 1);
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        // Calculate theoretical system balances for the selected date
        $reconciliation = $this->calcService->calculateReconciliation($businessId, $date, $locationId);

        // Fetch recent physical reconciliation logs
        $recentReconciliations = FinancialReconciliation::with(['account', 'reconciler'])
            ->whereDate('reconciliation_date', $date)
            ->get();

        return view('dailyfinancialmanagement::reconciliation.index', compact(
            'date',
            'locationId',
            'branches',
            'reconciliation',
            'recentReconciliations'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'financial_account_id' => 'required|integer',
            'reconciliation_date' => 'required|date',
            'physical_balance' => 'required|numeric|min:0',
        ]);

        $businessId = session('business.id', 1);
        $accountId = (int) $request->financial_account_id;
        $date = $request->reconciliation_date;
        $physicalBalance = (float) $request->physical_balance;

        $account = FinancialAccount::where('business_id', $businessId)->findOrFail($accountId);
        $systemBalance = (float) $account->current_balance;
        $difference = $physicalBalance - $systemBalance;

        try {
            $rec = FinancialReconciliation::create([
                'daily_closing_id' => null,
                'financial_account_id' => $account->id,
                'reconciliation_date' => $date,
                'system_balance' => $systemBalance,
                'physical_balance' => $physicalBalance,
                'difference' => $difference,
                'denominations_json' => $request->input('denominations', []),
                'notes' => $request->notes,
                'reconciled_by' => auth()->id(),
            ]);

            FinancialAuditLog::record(
                $businessId,
                'account_reconciled',
                'FinancialReconciliation',
                $rec->id,
                null,
                $rec->toArray(),
                "Reconciled {$account->account_name}: Diff = \${$difference}"
            );

            return back()->with('status', [
                'success' => 1,
                'msg' => "Reconciliation recorded! Variance: " . ($difference >= 0 ? "+$" : "-$") . abs($difference),
            ]);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }
}

