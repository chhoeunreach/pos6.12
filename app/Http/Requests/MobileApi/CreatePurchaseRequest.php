<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('purchase.create');
    }

    public function rules()
    {
        return [
            'contact_id' => 'required_without:supplier_id|exists:contacts,id',
            'supplier_id' => 'required_without:contact_id|exists:contacts,id',
            'location_id' => 'required|exists:business_locations,id',
            'transaction_date' => 'required|date',
            'status' => 'required|in:received,pending,ordered',
            'ref_no' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variation_id' => 'required|exists:variations,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_cost_before_discount' => 'nullable|numeric|min:0',
            'products.*.unit_cost' => 'nullable|numeric|min:0',
            'products.*.unit_cost_inc_tax' => 'nullable|numeric|min:0',
            'products.*.pp_without_discount' => 'nullable|numeric|min:0',
            'products.*.purchase_price' => 'nullable|numeric|min:0',
            'products.*.purchase_price_inc_tax' => 'nullable|numeric|min:0',
            'products.*.item_tax' => 'nullable|numeric',
            'products.*.tax_id' => 'nullable|exists:tax_rates,id',
            'products.*.purchase_line_tax_id' => 'nullable|exists:tax_rates,id',
            'products.*.line_discount_type' => 'nullable|in:fixed,percentage',
            'products.*.line_discount_amount' => 'nullable|numeric|min:0',
            'products.*.discount_percent' => 'nullable|numeric|min:0',
            'products.*.lot_number' => 'nullable|string',
            'products.*.mfg_date' => 'nullable|date',
            'products.*.exp_date' => 'nullable|date',
            'products.*.default_sell_price' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|exists:tax_rates,id',
            'tax_rate_id' => 'nullable|exists:tax_rates,id',
            'shipping_charges' => 'nullable|numeric',
            'additional_notes' => 'nullable|string',
            'total_before_tax' => 'nullable|numeric',
            'tax_amount' => 'nullable|numeric',
            'final_total' => 'nullable|numeric',
            'pay_term_number' => 'nullable|numeric',
            'pay_term_type' => 'nullable|in:days,months',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required_with:payments|string',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.paid_on' => 'nullable|date',
            'payments.*.account_id' => 'nullable|exists:accounts,id',
            'payments.*.note' => 'nullable|string',
            'payments.*.transaction_no_1' => 'nullable|string',
            'payments.*.transaction_no_2' => 'nullable|string',
            'payments.*.transaction_no_3' => 'nullable|string',
            'payments.*.transaction_no_4' => 'nullable|string',
            'payments.*.transaction_no_5' => 'nullable|string',
            'payments.*.transaction_no_6' => 'nullable|string',
            'payments.*.transaction_no_7' => 'nullable|string',
        ];
    }
}
