<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CustomerAppDashboardController extends Controller
{
    use ApiResponseTrait;

    public function dashboard()
    {
        $customer = auth('customer_loan_api')->user();
        $loans = DB::connection('mysql_loan')->table('loans')->where('customer_id', $customer->id);
        $loanIds = (clone $loans)->pluck('id')->all();
        $nextDue = null;
        $lateAmount = 0;
        $upcomingPayments = [];
        if (! empty($loanIds) && DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_payment_schedules')) {
            $nextDue = DB::connection('mysql_loan')->table('loan_payment_schedules')->whereIn('loan_id', $loanIds)->whereIn('status', ['unpaid', 'partial', 'late'])->orderBy('due_date')->value('due_date');
            $lateAmount = (float) DB::connection('mysql_loan')->table('loan_payment_schedules')->whereIn('loan_id', $loanIds)->whereIn('status', ['unpaid', 'partial', 'late'])->whereDate('due_date', '<', now()->toDateString())->sum('balance_amount');
            $upcomingPayments = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                ->orderBy('due_date')
                ->limit(10)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => (int) $r->id,
                        'loan_id' => (int) $r->loan_id,
                        'installment_no' => (int) ($r->installment_no ?? 0),
                        'due_date' => ! empty($r->due_date) ? date('Y-m-d', strtotime((string) $r->due_date)) : null,
                        'amount_due' => $this->money($r->amount_due ?? 0),
                        'amount_balance' => $this->money($r->amount_balance ?? 0),
                        'status' => (string) ($r->status ?? 'pending'),
                    ];
                })->values()->all();
        }

        $activeLoansRows = (clone $loans)->whereIn('status', ['active', 'approved', 'pending', 'late'])->orderByDesc('id')->limit(10)->get();
        $recentPayments = DB::connection('mysql_loan')->table('loan_payments')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'loan_id' => (int) $r->loan_id,
                    'amount' => $this->money($r->amount ?? 0),
                    'paid_at' => ! empty($r->paid_at) ? date('Y-m-d H:i:s', strtotime((string) $r->paid_at)) : null,
                    'status' => (string) ($r->status ?? 'confirmed'),
                ];
            })->values()->all();

        $unreadChats = 0;
        if (DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_chat_messages')
            && DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_chat_threads')) {
            $threadIds = DB::connection('mysql_loan')->table('loan_chat_threads')->where('customer_id', $customer->id)->pluck('id')->all();
            if (! empty($threadIds)) {
                $unreadChats = (int) DB::connection('mysql_loan')->table('loan_chat_messages')
                    ->whereIn('thread_id', $threadIds)
                    ->where('sender_type', '!=', 'customer')
                    ->where(function ($q) {
                        $q->whereNull('is_read')->orWhere('is_read', 0);
                    })->count();
            }
        }

        $summary = [
            'active_loans' => (int) (clone $loans)->whereIn('status', ['active', 'approved', 'pending', 'late'])->count(),
            'total_balance' => $this->money((clone $loans)->sum('balance_amount')),
            'total_paid' => $this->money((clone $loans)->sum('paid_amount')),
            'late_amount' => $this->money($lateAmount),
            'next_due_date' => $nextDue ? date('Y-m-d', strtotime((string) $nextDue)) : null,
            'last_payment_amount' => ! empty($recentPayments) ? (string) $recentPayments[0]['amount'] : $this->money(0),
            'unread_chats' => $unreadChats,
        ];

        return $this->ok('Dashboard loaded', [
            'summary' => $summary,
            'active_loans' => $activeLoansRows,
            'upcoming_payments' => $upcomingPayments,
            'recent_payments' => $recentPayments,
        ]);
    }
}
