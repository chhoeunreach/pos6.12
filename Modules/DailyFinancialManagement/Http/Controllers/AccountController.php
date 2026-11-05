<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialAccountMovement;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Services\AccountMovementService;

class AccountController extends Controller
{
    protected AccountMovementService $movementService;

    public function __construct(AccountMovementService $movementService)
    {
        $this->movementService = $movementService;
    }

    public function index()
    {
        $businessId = session('business.id', 1);

        $accounts = FinancialAccount::with('location')
            ->where('business_id', $businessId)
            ->orderBy('account_type')
            ->orderBy('account_name')
            ->get();

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        $totalCash = (float) $accounts->whereIn('account_type', ['cash', 'petty_cash'])->sum('current_balance');
        $totalBank = (float) $accounts->whereIn('account_type', ['bank', 'digital_wallet'])->sum('current_balance');

        return view('dailyfinancialmanagement::accounts.index', compact(
            'accounts',
            'branches',
            'totalCash',
            'totalBank'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_name' => 'required|string|max:191',
            'account_type' => 'required|string|in:cash,bank,digital_wallet,petty_cash',
            'currency' => 'required|string|in:USD,KHR',
            'opening_balance' => 'required|numeric|min:0',
            'business_location_id' => 'nullable|integer',
        ]);

        $businessId = session('business.id', 1);
        $openingBalance = (float) $request->opening_balance;

        try {
            $account = FinancialAccount::create([
                'business_id' => $businessId,
                'business_location_id' => $request->business_location_id ?: null,
                'account_code' => $request->account_code,
                'account_name' => $request->account_name,
                'account_type' => $request->account_type,
                'account_number' => $request->account_number,
                'bank_name' => $request->bank_name,
                'currency' => $request->currency,
                'opening_balance' => $openingBalance,
                'current_balance' => $openingBalance,
                'is_active' => true,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            FinancialAuditLog::record(
                $businessId,
                'account_created',
                'FinancialAccount',
                $account->id,
                null,
                $account->toArray(),
                "Created financial account: {$account->account_name} ({$account->account_type})"
            );

            return back()->with('status', ['success' => 1, 'msg' => 'Account created successfully!']);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|integer',
            'to_account_id' => 'required|integer|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'ref_no' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $businessId = session('business.id', 1);

        try {
            $res = $this->movementService->transfer(
                $businessId,
                (int) $request->from_account_id,
                (int) $request->to_account_id,
                (float) $request->amount,
                $request->transfer_date,
                $request->ref_no,
                $request->notes,
                auth()->user()
            );

            return back()->with('status', [
                'success' => 1,
                'msg' => "Transfer completed successfully! Ref: {$res['transfer_ref']}",
            ]);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Transfer failed: ' . $e->getMessage()]);
        }
    }

    public function movements(Request $request, $id)
    {
        $businessId = session('business.id', 1);
        $account = FinancialAccount::where('business_id', $businessId)->findOrFail($id);

        $query = FinancialAccountMovement::with(['relatedAccount', 'transaction', 'creator'])
            ->where('financial_account_id', $account->id);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('movement_date', [$request->start_date, $request->end_date]);
        }

        $movements = $query->orderBy('movement_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(30);

        return view('dailyfinancialmanagement::accounts.movements', compact('account', 'movements'));
    }
}

