<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Requests\MobileApi\StorePaymentRequest;
use App\Http\Requests\MobileApi\UpdatePaymentRequest;
use App\Http\Resources\Mobile\PaymentResource;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Payments
 * Payment management
 */
class PaymentController extends BaseController
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->with(['transaction', 'created_user', 'payment_account']);

        if ($request->filled('contact_id')) {
            $query->where('payment_for', $request->contact_id);
        }
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('paid_on', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('paid_on', '<=', $request->end_date);
        }
        if ($request->filled('transaction_id')) {
            $query->where('transaction_id', $request->transaction_id);
        }

        $perPage = $request->input('per_page', 20);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(PaymentResource::collection($payments));
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();

        $payment = TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->with(['transaction', 'created_user', 'payment_account'])->findOrFail($id);

        return $this->success(new PaymentResource($payment));
    }

    public function store(StorePaymentRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        try {
            DB::beginTransaction();

            $payment_data = [[
                'amount' => $request->amount,
                'method' => $request->method,
                'paid_on' => $request->paid_on ?? now(),
                'account_id' => $request->account_id,
                'card_number' => $request->card_number,
                'card_holder_name' => $request->card_holder_name,
                'card_transaction_number' => $request->card_transaction_number,
                'card_type' => $request->card_type,
                'cheque_number' => $request->cheque_number,
                'note' => $request->note,
                'transaction_id' => $request->transaction_id,
            ]];

            if ($request->filled('transaction_id')) {
                $transaction = \App\Transaction::where('business_id', $business_id)
                    ->findOrFail($request->transaction_id);

                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $payment_data, $business_id, $user_id);
                $this->transactionUtil->updatePaymentStatus($transaction->id);

                $payment = TransactionPayment::where('transaction_id', $transaction->id)
                    ->latest()
                    ->first();
            } else {
                $contact = \App\Contact::where('business_id', $business_id)
                    ->findOrFail($request->contact_id);

                $payment_data[0]['contact_id'] = $contact->id;
                $payment_data[0]['payment_for'] = $contact->id;
                $payments = $this->transactionUtil->payContact($payment_data[0]);
                $payment = $payments->first();
            }

            DB::commit();

            return $this->success(new PaymentResource($payment->load(['transaction', 'created_user', 'payment_account'])), 'Payment recorded successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Payment failed: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, UpdatePaymentRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $payment = TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->findOrFail($id);

        try {
            DB::beginTransaction();

            $payment_data = $request->only(['amount', 'method', 'paid_on', 'account_id', 'note']);

            if ($request->has('amount')) {
                $payment_data['amount'] = $request->amount;
            }
            $payment->update($payment_data);

            if ($payment->transaction_id) {
                $this->transactionUtil->updatePaymentStatus($payment->transaction_id);
            }

            DB::commit();

            return $this->success(new PaymentResource($payment->fresh()->load(['transaction', 'created_user', 'payment_account'])), 'Payment updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update payment: ' . $e->getMessage(), 500);
        }
    }
}
