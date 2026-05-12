<?php

namespace Modules\LoanManagement\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Requests\StoreLoanFromSellRequest;
use Modules\LoanManagement\Services\CreateLoanFromSellService;

class LoanFromSellController extends Controller
{
    public function __construct(protected CreateLoanFromSellService $service)
    {
    }

    public function index()
    {
        $locations = DB::table('business_locations')->orderBy('name')->pluck('name', 'id');

        return view('loanmanagement::loans.create_from_sell.index', [
            'locations' => $locations,
            'paymentStatuses' => ['paid' => 'Paid', 'due' => 'Due', 'partial' => 'Partial', 'overdue' => 'Overdue'],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $filters = [
            'invoice_no' => $request->invoice_no,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'location_id' => $request->location_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'payment_status' => $request->payment_status,
            'final_total' => $request->final_total,
            'imei_or_lot' => $request->imei_or_lot,
        ];

        $rows = $this->service->searchSells($filters);

        return response()->json([
            'success' => true,
            'message' => 'Sells loaded successfully',
            'data' => $rows,
        ]);
    }

    public function clone(Request $request, $transaction_id)
    {
        if ($this->service->preventDuplicateLoan((int) $transaction_id)) {
            $loanId = $this->service->getLoanIdBySourceTransactionId((int) $transaction_id);
            if (! $request->ajax() && ! $request->wantsJson()) {
                return redirect()->route('loan-management.loans.create-from-sell')
                    ->with('duplicate_installment_warning', 'This sell already has installment loan.')
                    ->with('duplicate_loan_url', ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null);
            }
            return response()->json([
                'success' => false,
                'message' => 'This sell already has a loan.',
                'data' => [
                'loan_id' => $loanId,
                'loan_url' => ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null,
            ],
        ]);
        }

        $sell = $this->service->getSellFullData((int) $transaction_id);
        $collectors = DB::table('users')->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")->orderBy('first_name')->get();
        $paymentMethods = [];
        $defaultPaymentMethodId = null;
        if (Schema::hasTable('payment_methods')) {
            $query = DB::table('payment_methods')->select('id', 'name');
            if (Schema::hasColumn('payment_methods', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $paymentMethods = $query->orderBy('name')->get();
            $sourceMethod = strtolower(trim((string) ($sell['default_payment_method'] ?? '')));
            if ($sourceMethod !== '') {
                $normalizedSource = str_replace(['-', ' '], '_', $sourceMethod);
                $aliases = [
                    'cash' => ['cash'],
                    'card' => ['card', 'credit_card', 'debit_card', 'visa', 'mastercard'],
                    'bank_transfer' => ['bank_transfer', 'bank', 'transfer'],
                    'cheque' => ['cheque', 'check'],
                    'other' => ['other'],
                    'aba' => ['aba'],
                ];

                $matched = $paymentMethods->first(function ($m) use ($sourceMethod, $normalizedSource, $aliases) {
                    $name = strtolower(trim((string) $m->name));
                    $normalizedName = str_replace(['-', ' '], '_', $name);
                    if ($name === $sourceMethod || $normalizedName === $normalizedSource) {
                        return true;
                    }

                    foreach ($aliases as $key => $variants) {
                        if (
                            $normalizedSource === $key ||
                            in_array($normalizedSource, $variants, true) ||
                            $normalizedName === $key ||
                            in_array($normalizedName, $variants, true)
                        ) {
                            return true;
                        }
                    }

                    if (str_starts_with($normalizedSource, 'custom_pay_')) {
                        return str_contains($normalizedName, 'custom') || str_contains($normalizedName, 'other');
                    }

                    return str_contains($normalizedName, $normalizedSource) || str_contains($normalizedSource, $normalizedName);
                });
                $defaultPaymentMethodId = $matched->id ?? null;
            }
        }
        $html = view('loanmanagement::loans.create_from_sell.form', compact('sell', 'collectors', 'paymentMethods', 'defaultPaymentMethodId'))->render();

        if (! $request->ajax() && ! $request->wantsJson()) {
            return view('loanmanagement::loans.create_from_sell.clone', compact('sell', 'collectors', 'paymentMethods', 'defaultPaymentMethodId'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Sell cloned successfully',
            'data' => [
                'sell' => $sell,
                'form_html' => $html,
                'loan_url' => null,
            ],
        ]);
    }

    public function checkDuplicateLoan($transaction_id): JsonResponse
    {
        $exists = $this->service->preventDuplicateLoan((int) $transaction_id);
        $loanId = $exists ? $this->service->getLoanIdBySourceTransactionId((int) $transaction_id) : null;

        return response()->json([
            'success' => true,
            'message' => $exists ? 'This sell already has installment loan.' : 'Sell can be converted.',
            'data' => [
                'exists' => $exists,
                'loan_id' => $loanId,
                'loan_url' => ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null,
                'clone_url' => url('/loan-management/loans/sell/'.$transaction_id.'/clone'),
            ],
        ]);
    }

    public function previewSchedule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'principal_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'required|in:flat,reducing',
            'duration_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:monthly,weekly,daily',
            'first_due_date' => 'required|date',
        ]);

        $rows = $this->service->previewSchedule($payload);

        return response()->json([
            'success' => true,
            'message' => 'Schedule preview generated',
            'data' => $rows,
        ]);
    }

    public function store(StoreLoanFromSellRequest $request): JsonResponse
    {
        try {
            $loanId = $this->service->createLoanFromSell($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan created from sell successfully',
                'data' => ['loan_id' => $loanId],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }
}
