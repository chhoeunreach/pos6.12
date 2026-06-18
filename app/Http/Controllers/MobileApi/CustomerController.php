<?php

namespace App\Http\Controllers\MobileApi;

use App\Contact;
use App\Http\Requests\MobileApi\StoreCustomerRequest;
use App\Http\Requests\MobileApi\UpdateCustomerRequest;
use App\Http\Resources\Mobile\CustomerResource;
use App\Http\Resources\Mobile\LedgerResource;
use App\Http\Resources\Mobile\PaymentResource;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\ContactUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Customers
 * Customer management
 */
class CustomerController extends BaseController
{
    protected $contactUtil;
    protected $transactionUtil;

    public function __construct(ContactUtil $contactUtil, TransactionUtil $transactionUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->active();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_id', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $customers = $query->orderBy('name')->paginate($perPage);

        return $this->success(CustomerResource::collection($customers));
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->findOrFail($id);

        $contact_info = $this->contactUtil->getContactInfo($business_id, $id);

        $data = new CustomerResource($contact);
        $additional = [
            'total_purchase_due' => $contact_info['total_purchase_due'] ?? 0,
            'total_invoice_due' => $contact_info['total_invoice_due'] ?? 0,
            'purchase_return_balance' => $contact_info['purchase_return_balance'] ?? 0,
            'sell_return_balance' => $contact_info['sell_return_balance'] ?? 0,
        ];

        return $this->success(array_merge($data->toArray($this->getRequest()), $additional));
    }

    public function store(StoreCustomerRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        try {
            DB::beginTransaction();

            $input = $request->validated();
            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;
            $input['type'] = 'customer';

            $contact = Contact::create($input);

            if (!empty($input['opening_balance'])) {
                $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $input['opening_balance'], $user_id);
            }

            DB::commit();

            return $this->success(new CustomerResource($contact), 'Customer created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create customer: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateCustomerRequest $request)
    {
        $business_id = $this->getBusinessId();
        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->findOrFail($id);

        $contact->update($request->validated());

        return $this->success(new CustomerResource($contact->fresh()->load('customer_group')), 'Customer updated successfully');
    }

    public function ledger($id, Request $request)
    {
        $business_id = $this->getBusinessId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->findOrFail($id);

        $start = $request->input('start_date', now()->subMonth()->format('Y-m-d'));
        $end = $request->input('end_date', now()->format('Y-m-d'));
        $location_id = $request->input('location_id');

        $ledger_details = $this->transactionUtil->getLedgerDetails($contact->id, $start, $end, 'format_1', $location_id);

        return $this->success([
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'mobile' => $contact->mobile,
                'balance' => $contact->balance,
            ],
            'start_date' => $start,
            'end_date' => $end,
            'opening_balance' => $ledger_details['opening_balance'] ?? 0,
            'closing_balance' => $ledger_details['closing_balance'] ?? 0,
            'transactions' => $ledger_details['ledger'] ?? [],
        ]);
    }

    public function payments($id, Request $request)
    {
        $business_id = $this->getBusinessId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->findOrFail($id);

        $query = TransactionPayment::where('payment_for', $id)
            ->with(['transaction', 'created_user', 'payment_account']);

        $perPage = $request->input('per_page', 20);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(PaymentResource::collection($payments));
    }

    public function payDue($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,cheque,bank_transfer,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7',
            'paid_on' => 'nullable|date',
            'account_id' => 'nullable|exists:accounts,id',
            'note' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $payment_data = [
                'contact_id' => $contact->id,
                'amount' => $request->amount,
                'method' => $request->method,
                'paid_on' => $request->paid_on ?? now(),
                'account_id' => $request->account_id,
                'note' => $request->note,
                'transaction_id' => null,
            ];

            $payments = $this->transactionUtil->payContact($payment_data);

            DB::commit();

            return $this->success([
                'payment' => new PaymentResource($payments->first()->load(['created_user', 'transaction'])),
                'new_balance' => $contact->fresh()->balance,
            ], 'Payment recorded successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Payment failed: ' . $e->getMessage(), 500);
        }
    }

    protected function getRequest()
    {
        return request();
    }
}
