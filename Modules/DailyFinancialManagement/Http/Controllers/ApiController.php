<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;
use Modules\DailyFinancialManagement\Services\PosFinancialSyncService;

class ApiController extends Controller
{
    protected FinancialCalculationService $calcService;
    protected PosFinancialSyncService $syncService;

    public function __construct(
        FinancialCalculationService $calcService,
        PosFinancialSyncService $syncService
    ) {
        $this->calcService = $calcService;
        $this->syncService = $syncService;
    }

    public function summary(Request $request): JsonResponse
    {
        $businessId = auth()->user()->business_id ?? 1;
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $date, $date, $locationId);
        $cashFlow = $this->calcService->calculateCashFlow($businessId, $date, $date, $locationId);
        $reconciliation = $this->calcService->calculateReconciliation($businessId, $date, $locationId);

        return response()->json([
            'success' => true,
            'date' => $date,
            'location_id' => $locationId,
            'profit_and_loss' => $pnl,
            'cash_flow' => $cashFlow,
            'reconciliation_summary' => $reconciliation['summary'],
        ]);
    }

    public function branches(): JsonResponse
    {
        $businessId = auth()->user()->business_id ?? 1;
        $branches = BusinessLocation::where('business_id', $businessId)->get(['id', 'name', 'location_id']);

        return response()->json([
            'success' => true,
            'branches' => $branches,
        ]);
    }

    public function closingStatus(Request $request): JsonResponse
    {
        $businessId = auth()->user()->business_id ?? 1;
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $locationId = (int) $request->input('location_id', 1);

        $closing = FinancialDailyClosing::where('business_id', $businessId)
            ->where('business_location_id', $locationId)
            ->where('closing_date', $date)
            ->first();

        return response()->json([
            'success' => true,
            'is_closed' => (bool) $closing,
            'is_locked' => $closing ? $closing->isLocked() : false,
            'status' => $closing ? $closing->closing_status : 'not_started',
            'closing' => $closing,
        ]);
    }

    public function createExpense(Request $request): JsonResponse
    {
        $request->validate([
            'business_location_id' => 'required|integer',
            'category_id' => 'required|integer',
            'financial_account_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:USD,KHR',
            'description' => 'required|string',
        ]);

        $businessId = auth()->user()->business_id ?? 1;
        $amount = (float) $request->amount;
        $currency = $request->currency;
        $exchangeRate = $currency === 'KHR' ? 4100.0000 : 1.0000;
        $baseAmount = $currency === 'KHR' ? ($amount / $exchangeRate) : $amount;

        try {
            $refNo = 'EXP-API-' . date('ymd') . '-' . rand(1000, 9999);

            $expense = FinancialTransaction::create([
                'business_id' => $businessId,
                'business_location_id' => $request->business_location_id,
                'category_id' => $request->category_id,
                'financial_account_id' => $request->financial_account_id,
                'transaction_type' => 'expense',
                'transaction_date' => now()->format('Y-m-d'),
                'ref_no' => $refNo,
                'source_type' => 'api',
                'description' => $request->description,
                'amount' => $amount,
                'cogs_amount' => 0.0000,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'amount_in_base_currency' => $baseAmount,
                'payment_method' => $request->input('payment_method', 'cash'),
                'approval_status' => 'pending',
                'is_operating' => true,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expense created and submitted for approval',
                'transaction' => $expense,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function triggerSync(Request $request): JsonResponse
    {
        $businessId = auth()->user()->business_id ?? 1;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $force = (bool) $request->input('force', false);

        $res = $this->syncService->syncPosAndLoans($businessId, $startDate, $endDate, $force);

        return response()->json($res);
    }
}

