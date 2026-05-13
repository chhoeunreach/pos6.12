<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerAppPaymentController extends Controller
{
    use ApiResponseTrait;

    public function payments()
    {
        $customer = auth('customer_loan_api')->user();
        $rows = DB::connection('mysql_loan')->table('loan_payments')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($r) {
                $r->amount = $this->money($r->amount ?? 0);
                if (! empty($r->paid_at)) {
                    $r->paid_at = date('Y-m-d H:i:s', strtotime((string) $r->paid_at));
                }
                return $r;
            })->values()->all();

        return $this->ok('Payments loaded', $rows);
    }

    public function summary()
    {
        $customer = auth('customer_loan_api')->user();
        $loanTable = DB::connection('mysql_loan')->table('loans')->where('customer_id', $customer->id);
        $totalBalance = (float) (clone $loanTable)->sum('balance_amount');
        $lateAmount = 0.0;
        $nextDueDate = null;

        if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            $loanIds = (clone $loanTable)->pluck('id')->all();
            if (! empty($loanIds)) {
                $lateAmount = (float) DB::connection('mysql_loan')->table('loan_payment_schedules')
                    ->whereIn('loan_id', $loanIds)
                    ->whereIn('status', ['unpaid', 'partial', 'late'])
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->sum('balance_amount');
                $nextDueDate = DB::connection('mysql_loan')->table('loan_payment_schedules')
                    ->whereIn('loan_id', $loanIds)
                    ->whereIn('status', ['unpaid', 'partial', 'late'])
                    ->orderBy('due_date')
                    ->value('due_date');
            }
        }

        return $this->ok('Summary loaded', [
            'total_balance' => $this->money($totalBalance),
            'next_due_date' => $nextDueDate ? date('Y-m-d', strtotime((string) $nextDueDate)) : null,
            'late_amount' => $this->money($lateAmount),
        ]);
    }

    public function uploadProof(Request $request, int $paymentId)
    {
        $customer = auth('customer_loan_api')->user();
        $request->validate(['proof_file_id' => 'required|integer|min:1']);

        $payment = DB::connection('mysql_loan')->table('loan_payments')
            ->where('id', $paymentId)
            ->where('customer_id', $customer->id)
            ->first();
        if (! $payment) {
            return $this->fail('Payment not found', 404, (object) []);
        }

        DB::connection('mysql_loan')->table('loan_payments')->where('id', $paymentId)->update([
            'proof_file_id' => (int) $request->input('proof_file_id'),
            'updated_at' => now(),
        ]);

        return $this->ok('Payment proof uploaded', (object) []);
    }

    public function uploadPaymentProof(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer|min:1',
            'proof_file_id' => 'required|integer|min:1',
        ]);
        return $this->uploadProof($request, (int) $request->input('payment_id'));
    }
}
