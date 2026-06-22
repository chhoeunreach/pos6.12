<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('product.create');
    }

    public function rules()
    {
        $business_id = auth()->user()->business_id ?? session('user.business_id');
        $uniqueSku = function () use ($business_id) {
            return Rule::unique('products', 'sku')->where(function ($query) use ($business_id) {
                return $query->where('business_id', $business_id);
            });
        };

        return [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:255', $uniqueSku()],
            'type' => 'required|in:single,variable,combo',
            'unit_id' => 'nullable|exists:units,id',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'tax' => 'nullable|exists:tax_rates,id',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'enable_stock' => 'nullable|boolean',
            'alert_quantity' => 'nullable|numeric',
            'sku_manual' => ['nullable', 'string', 'max:255', $uniqueSku()],
            'purchase_price' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'product_description' => 'nullable|string',
            'sub_unit_ids' => 'nullable|array',
            'barcode_type' => 'nullable|string',
            'weight' => 'nullable|string',
            'product_custom_field1' => 'nullable|string',
            'product_custom_field2' => 'nullable|string',
            'product_custom_field3' => 'nullable|string',
            'product_custom_field4' => 'nullable|string',
            'product_locations' => 'nullable|array',
            'product_locations.*' => 'exists:business_locations,id',
        ];
    }
}
