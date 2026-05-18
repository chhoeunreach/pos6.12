<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Services\CreateLoanFromSellService;
use Modules\LoanManagement\Services\UltimatePosSellService;

class LoanUltimatePosSellController extends Controller
{
    public function __construct(
        protected UltimatePosSellService $sellService,
        protected CreateLoanFromSellService $loanFromSellService
    ) {
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->sellService->searchCustomers($request->input('q')),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->sellService->searchProducts($request->input('q'), $request->input('location_id')),
        ]);
    }

    public function searchImei(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->sellService->getProductByImeiOrLot($request->input('q'), $request->input('location_id')),
        ]);
    }

    public function storeSell(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:100',
            'location_id' => 'required|integer',
            'sale_date' => 'nullable|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'nullable|integer',
            'products.*.variation_id' => 'required|integer',
            'products.*.qty' => 'required|numeric|min:0.0001',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.tax' => 'nullable|numeric|min:0',
            'products.*.tax_id' => 'nullable|integer',
            'products.*.lot_no_line_id' => 'nullable|integer',
            'products.*.imei_lot' => 'nullable|string|max:255',
            'payment.method' => 'nullable|string|max:100',
            'payment.amount' => 'nullable|numeric|min:0',
            'payment.paid_on' => 'nullable|date',
            'payment.reference_number' => 'nullable|string|max:255',
            'payment.note' => 'nullable|string|max:1000',
            'payment_status' => 'nullable|in:paid,due,partial',
            'note' => 'nullable|string|max:1000',
            'use_for_loan' => 'nullable|boolean',
        ]);

        try {
            $transaction = $this->sellService->createSell($data);
            $loanPayload = null;
            $formHtml = null;

            if ($request->boolean('use_for_loan')) {
                $sell = $this->loanFromSellService->getSellFullData((int) $transaction->id);
                $collectors = DB::table('users')->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")->orderBy('first_name')->get();
                $paymentMethods = collect();
                $defaultPaymentMethodId = null;
                if (Schema::hasTable('payment_methods')) {
                    $paymentMethodsQuery = DB::table('payment_methods')->select('id', 'name');
                    if (Schema::hasColumn('payment_methods', 'deleted_at')) {
                        $paymentMethodsQuery->whereNull('deleted_at');
                    }
                    $paymentMethods = $paymentMethodsQuery->orderBy('name')->get();
                }
                $formHtml = view('loanmanagement::loans.create_from_sell.form', compact('sell', 'collectors', 'paymentMethods', 'defaultPaymentMethodId'))->render();
                $loanPayload = $this->sellService->formatSellForLoanClone((int) $transaction->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ultimate POS sale created successfully.',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'invoice_no' => $transaction->invoice_no ?? null,
                    'loan_clone' => $loanPayload,
                    'form_html' => $formHtml,
                ],
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
