<?php

namespace App\Http\Controllers\MobileApi;

use App\Contact;
use App\Http\Resources\Mobile\LedgerResource;
use App\Http\Resources\Mobile\PaymentResource;
use App\Http\Resources\Mobile\SupplierResource;
use App\Utils\ContactUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Suppliers
 * Supplier management
 */
class SupplierController extends BaseController
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
            ->whereIn('type', ['supplier', 'both'])
            ->active();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('supplier_business_name', 'like', "%{$search}%")
                    ->orWhere('contact_id', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $suppliers = $query->orderBy('name')->paginate($perPage);

        return $this->success(SupplierResource::collection($suppliers));
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->findOrFail($id);

        $contact_info = $this->contactUtil->getContactInfo($business_id, $id);

        $data = new SupplierResource($contact);
        $additional = [
            'total_purchase_due' => $contact_info['total_purchase_due'] ?? 0,
            'total_purchase_return_due' => $contact_info['total_purchase_return_due'] ?? 0,
            'total_sell_return' => $contact_info['total_sell_return'] ?? 0,
        ];

        return $this->success(array_merge($data->toArray(request()), $additional));
    }

    public function store(Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $request->validate([
            'name' => 'required|string|max:255',
            'supplier_business_name' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string',
            'address_line_2' => 'nullable|string',
            'zip_code' => 'nullable|string|max:20',
            'contact_id' => 'nullable|string|max:255',
            'pay_term_number' => 'nullable|numeric',
            'pay_term_type' => 'nullable|in:days,months',
            'opening_balance' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $input = $request->only(['name', 'supplier_business_name', 'mobile', 'email', 'tax_number', 'city', 'state', 'country', 'address_line_1', 'address_line_2', 'zip_code', 'contact_id', 'pay_term_number', 'pay_term_type', 'opening_balance']);
            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;
            $input['type'] = 'supplier';

            $contact = Contact::create($input);

            if (!empty($input['opening_balance'])) {
                $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $input['opening_balance'], $user_id);
            }

            DB::commit();

            return $this->success(new SupplierResource($contact), 'Supplier created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create supplier: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, Request $request)
    {
        $business_id = $this->getBusinessId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->findOrFail($id);

        $contact->update($request->all());

        return $this->success(new SupplierResource($contact->fresh()), 'Supplier updated successfully');
    }

    public function ledger($id, Request $request)
    {
        $business_id = $this->getBusinessId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['supplier', 'both'])
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

    public function payDue($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $contact = Contact::where('business_id', $business_id)
            ->whereIn('type', ['supplier', 'both'])
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
}
