<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class CreatePosSaleRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('sell.create');
    }

    public function rules()
    {
        return [
            'contact_id' => 'required|exists:contacts,id',
            'location_id' => 'required|exists:business_locations,id',
            'transaction_date' => 'nullable|date',
            'status' => 'required|in:final,draft,quotation',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variation_id' => 'required|exists:variations,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.unit_price_inc_tax' => 'required|numeric|min:0',
            'products.*.item_tax' => 'nullable|numeric',
            'products.*.tax_id' => 'nullable|exists:tax_rates,id',
            'products.*.line_discount_type' => 'nullable|in:fixed,percentage',
            'products.*.line_discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'nullable|exists:tax_rates,id',
            'sale_note' => 'nullable|string',
            'staff_note' => 'nullable|string',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required_with:payments|in:cash,card,cheque,bank_transfer,advance,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.paid_on' => 'nullable|date',
            'payments.*.account_id' => 'nullable|exists:accounts,id',
            'payments.*.card_number' => 'nullable|string',
            'payments.*.card_holder_name' => 'nullable|string',
            'payments.*.card_transaction_number' => 'nullable|string',
            'payments.*.card_type' => 'nullable|string',
            'payments.*.cheque_number' => 'nullable|string',
            'is_suspend' => 'nullable|boolean',
            'shipping_charges' => 'nullable|numeric',
            'shipping_details' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'shipping_status' => 'nullable|string',
            'delivered_to' => 'nullable|string',
            'exchange_rate' => 'nullable|numeric|min:0',
            'pay_term_number' => 'nullable|numeric',
            'pay_term_type' => 'nullable|in:days,months',
        ];
    }

    public function messages()
    {
        return [
            'contact_id.required' => 'Customer is required.',
            'location_id.required' => 'Location is required.',
            'products.required' => 'At least one product is required.',
            'products.min' => 'At least one product is required.',
        ];
    }
}
