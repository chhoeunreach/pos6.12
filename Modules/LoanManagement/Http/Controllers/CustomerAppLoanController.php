<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerAppLoanController extends Controller
{
    public function loans()
    {
        $customer = auth('customer_loan_api')->user();
        $rows = DB::connection('mysql_loan')->table('loans')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows]);
    }

    public function show(int $loanId)
    {
        $customer = auth('customer_loan_api')->user();
        $loan = DB::connection('mysql_loan')->table('loans')->where('id', $loanId)->where('customer_id', $customer->id)->first();
        if (! $loan) {
            return response()->json(['success' => false, 'message' => 'Loan not found', 'data' => (object) []], 404);
        }
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $loan]);
    }

    public function schedules(int $loanId)
    {
        $customer = auth('customer_loan_api')->user();
        $loan = DB::connection('mysql_loan')->table('loans')->where('id', $loanId)->where('customer_id', $customer->id)->first();
        if (! $loan) {
            return response()->json(['success' => false, 'message' => 'Loan not found', 'data' => (object) []], 404);
        }

        $rows = Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')
            ? DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loanId)->orderBy('id')->get()
            : collect();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows]);
    }
}
