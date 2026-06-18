<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Resources\Mobile\PaymentResource;
use App\Http\Resources\Mobile\TransactionResource;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Sales
 * Sales transaction management
 */
class SaleController extends BaseController
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['sell', 'sell_return'])
            ->with(['contact', 'location', 'createdByUser', 'payment_lines']);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('contact_id', $request->customer_id);
        }
        if ($request->filled('cashier_id')) {
            $query->where('created_by', $request->cashier_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $sales = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(TransactionResource::collection($sales));
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();

        $sale = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['sell', 'sell_return'])
            ->with([
                'contact', 'location', 'createdByUser', 'payment_lines.payment_account',
                'sell_lines.product', 'sell_lines.variations', 'sell_lines.line_tax',
            ])
            ->findOrFail($id);

        return $this->success(new TransactionResource($sale));
    }

    public function payment($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $transaction = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['sell', 'sell_return'])
            ->findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,cheque,bank_transfer,advance,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7',
            'paid_on' => 'nullable|date',
            'account_id' => 'nullable|exists:accounts,id',
            'card_number' => 'nullable|string',
            'card_holder_name' => 'nullable|string',
            'card_transaction_number' => 'nullable|string',
            'card_type' => 'nullable|string',
            'cheque_number' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

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
            ]];

            $payments = $this->transactionUtil->createOrUpdatePaymentLines($transaction, $payment_data, $business_id, $user_id);

            $this->transactionUtil->updatePaymentStatus($transaction->id);

            DB::commit();

            return $this->success([
                'payment_status' => $transaction->fresh()->payment_status,
            ], 'Payment added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Payment failed: ' . $e->getMessage(), 500);
        }
    }

    public function sellReturn($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->findOrFail($id);

        $request->validate([
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.sell_line_id' => 'required|exists:transaction_sell_lines,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $input = $request->only(['transaction_date', 'products']);
            $input['location_id'] = $transaction->location_id;
            $input['contact_id'] = $transaction->contact_id;

            $return_transaction = $this->transactionUtil->addSellReturn($input, $business_id, $user_id);

            DB::commit();

            return $this->success(new TransactionResource($return_transaction->load(['contact', 'location', 'sell_lines.product'])), 'Return processed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Return failed: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $business_id = $this->getBusinessId();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->findOrFail($id);

        try {
            DB::beginTransaction();
            $this->transactionUtil->deleteSale($business_id, $transaction->id);
            DB::commit();

            return $this->success(null, 'Sale deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Delete failed: ' . $e->getMessage(), 500);
        }
    }
}
