<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CustomerAppDashboardController extends Controller
{
    public function dashboard()
    {
        $customer = auth('customer_loan_api')->user();
        $loans = DB::connection('mysql_loan')->table('loans')->where('customer_id', $customer->id);
        $loanIds = (clone $loans)->pluck('id')->all();
        $nextDue = null;
        $lateAmount = 0;
        if (! empty($loanIds) && DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_payment_schedules')) {
            $nextDue = DB::connection('mysql_loan')->table('loan_payment_schedules')->whereIn('loan_id', $loanIds)->whereIn('status', ['unpaid', 'partial', 'late'])->orderBy('due_date')->value('due_date');
            $lateAmount = (float) DB::connection('mysql_loan')->table('loan_payment_schedules')->whereIn('loan_id', $loanIds)->whereIn('status', ['unpaid', 'partial', 'late'])->whereDate('due_date', '<', now()->toDateString())->sum('balance_amount');
        }
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'total_loans' => (clone $loans)->count(),
                'active_loans' => (clone $loans)->whereIn('status', ['active', 'approved', 'pending'])->count(),
                'total_balance' => (float) (clone $loans)->sum('balance_amount'),
                'next_due_date' => $nextDue,
                'late_amount' => $lateAmount,
            ],
        ]);
    }
}

