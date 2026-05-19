<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\Loan;
use Modules\LoanManagement\Http\Resources\CustomerLoanSummaryResource;
use Modules\LoanManagement\Services\CustomerLoanSummaryService;

class CustomerAppLoanController extends Controller
{
    use ApiResponseTrait;

    public function loans(CustomerLoanSummaryService $summaryService)
    {
        $customer = auth('customer_loan_api')->user();
        $rows = Loan::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $data = $rows
            ->map(fn (Loan $loan) => (new CustomerLoanSummaryResource($summaryService->buildLoanSummary($loan)))->toArray(request()))
            ->values()
            ->all();

        return $this->ok('Loans loaded', $data);
    }

    public function show(int $loanId, CustomerLoanSummaryService $summaryService)
    {
        $customer = auth('customer_loan_api')->user();
        $loan = Loan::query()->where('id', $loanId)->where('customer_id', $customer->id)->first();
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
            'loan_summary' => (new CustomerLoanSummaryResource($summaryService->buildLoanSummary($loan)))->toArray(request()),
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
