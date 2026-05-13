<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerAppLoanController extends Controller
{
    use ApiResponseTrait;

    public function loans()
    {
        $customer = auth('customer_loan_api')->user();
        $rows = DB::connection('mysql_loan')->table('loans as l')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $loanIds = $rows->pluck('id')->all();
        $nextDueMap = [];
        $overdueMap = [];
        if (! empty($loanIds) && Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            $next = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                ->orderBy('due_date')
                ->get()
                ->groupBy('loan_id');
            foreach ($next as $loanId => $list) {
                $first = $list->first();
                $nextDueMap[$loanId] = $first->due_date ?? null;
                $overdueMap[$loanId] = (float) $list->where('due_date', '<', now()->toDateString())->sum('amount_balance');
            }
        }

        $itemsMap = [];
        if (! empty($loanIds) && Schema::connection('mysql_loan')->hasTable('loan_items')) {
            $items = DB::connection('mysql_loan')->table('loan_items')
                ->whereIn('loan_id', $loanIds)->get()->groupBy('loan_id');
            foreach ($items as $loanId => $list) {
                $itemsMap[$loanId] = $list->values()->all();
            }
        }

        $data = $rows->map(function ($r) use ($nextDueMap, $overdueMap, $itemsMap) {
            $total = (float) ($r->total_amount ?? 0);
            $paid = (float) ($r->paid_amount ?? 0);
            $progress = $total > 0 ? round(($paid / $total) * 100, 2) : 0.0;
            return [
                'id' => (int) $r->id,
                'loan_number' => (string) ($r->loan_number ?? ''),
                'status' => (string) ($r->status ?? 'draft'),
                'loan_date' => ! empty($r->loan_date) ? date('Y-m-d', strtotime((string) $r->loan_date)) : null,
                'principal_amount' => $this->money($r->principal_amount ?? 0),
                'down_payment' => $this->money($r->down_payment ?? 0),
                'total_payable' => $this->money($r->total_amount ?? 0),
                'paid_amount' => $this->money($r->paid_amount ?? 0),
                'balance_amount' => $this->money($r->balance_amount ?? 0),
                'currency' => (string) ($r->currency ?? 'USD'),
                'next_due_date' => ! empty($nextDueMap[$r->id]) ? date('Y-m-d', strtotime((string) $nextDueMap[$r->id])) : null,
                'overdue_amount' => $this->money($overdueMap[$r->id] ?? 0),
                'payment_progress' => $progress,
                'items' => $itemsMap[$r->id] ?? [],
            ];
        })->values()->all();

        return $this->ok('Loans loaded', $data);
    }

    public function show(int $loanId)
    {
        $customer = auth('customer_loan_api')->user();
        $loan = DB::connection('mysql_loan')->table('loans')->where('id', $loanId)->where('customer_id', $customer->id)->first();
        if (! $loan) {
            return $this->fail('Loan not found', 404, (object) []);
        }

        $items = Schema::connection('mysql_loan')->hasTable('loan_items')
            ? DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loanId)->orderBy('id')->get()->values()->all()
            : [];
        $schedules = Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')
            ? DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loanId)->orderBy('due_date')->get()->values()->all()
            : [];
        $payments = Schema::connection('mysql_loan')->hasTable('loan_payments')
            ? DB::connection('mysql_loan')->table('loan_payments')->where('loan_id', $loanId)->orderByDesc('id')->get()->values()->all()
            : [];
        $collectionVisits = Schema::connection('mysql_loan')->hasTable('loan_collection_visits')
            ? DB::connection('mysql_loan')->table('loan_collection_visits')->where('loan_id', $loanId)->orderByDesc('id')->get()->values()->all()
            : [];

        return $this->ok('Loan loaded', [
            'loan_summary' => $loan,
            'customer_snapshot' => [
                'customer_name_snapshot' => $loan->customer_name_snapshot ?? null,
                'customer_phone_snapshot' => $loan->customer_phone_snapshot ?? null,
                'staff_name_snapshot' => $loan->staff_name_snapshot ?? null,
                'business_location_name_snapshot' => $loan->business_location_name_snapshot ?? null,
                'invoice_number_snapshot' => $loan->invoice_number_snapshot ?? null,
                'product_name_snapshot' => $loan->product_name_snapshot ?? null,
                'imei_snapshot' => $loan->imei_snapshot ?? null,
            ],
            'items' => $items,
            'schedules' => $schedules,
            'payments' => $payments,
            'collection_visits' => $collectionVisits,
        ]);
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

        return $this->ok('Schedules loaded', $rows);
    }

    public function allSchedules()
    {
        $customer = auth('customer_loan_api')->user();
        $loanIds = DB::connection('mysql_loan')->table('loans')->where('customer_id', $customer->id)->pluck('id')->all();
        if (empty($loanIds) || ! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            return $this->ok('Schedules loaded', []);
        }

        $rows = DB::connection('mysql_loan')->table('loan_payment_schedules')
            ->whereIn('loan_id', $loanIds)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'loan_id' => (int) $r->loan_id,
                    'installment_no' => (int) ($r->installment_no ?? 0),
                    'due_date' => ! empty($r->due_date) ? date('Y-m-d', strtotime((string) $r->due_date)) : null,
                    'amount_due' => $this->money($r->amount_due ?? 0),
                    'amount_paid' => $this->money($r->amount_paid ?? 0),
                    'amount_balance' => $this->money($r->amount_balance ?? 0),
                    'status' => (string) ($r->status ?? 'pending'),
                ];
            })->values()->all();

        return $this->ok('Schedules loaded', $rows);
    }
}
