<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class LoanAbaPaywayController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesLoanFinancialActions;

    public function create(Request $request)
    {
        $this->authorizeFinancialAction(['loan_management.payments.create', 'loan_management.payment']);
        abort(503, 'Verified ABA PayWay integration is not configured. Record verified payments through the payment collection workflow.');
    }

    public function checkout(string $ref)
    {
        abort(503, 'Verified ABA PayWay checkout is not configured.');
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $this->authorizeFinancialAction(['loan_management.aba.view', 'loan_management.payments.view', 'loan_management.payment']);
        $data = $request->validate(['merchant_ref_no' => 'required|string|max:191']);
        $row = DB::connection('mysql_loan')->table('loan_aba_payway_transactions')
            ->where('merchant_ref_no', $data['merchant_ref_no'])->first();
        abort_if(! $row, 404, 'Transaction not found');

        return response()->json([
            'success' => true,
            'id' => (int) $row->id,
            'merchant_ref_no' => (string) $row->merchant_ref_no,
            'status' => (string) $row->status,
            'amount' => (float) $row->amount,
            'currency' => (string) ($row->currency ?? 'USD'),
            'verified_at' => $row->verified_at ?? null,
        ]);
    }

    public function simulateSuccess(string $ref)
    {
        abort(403, 'Simulated payments cannot post to the loan ledger.');
    }
}
