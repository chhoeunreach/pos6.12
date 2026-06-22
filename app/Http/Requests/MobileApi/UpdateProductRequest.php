<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('product.edit');
    }

    public function rules()
    {
        $business_id = auth()->user()->business_id ?? session('user.business_id');
        $product_id = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($product_id)->where(function ($query) use ($business_id) {
                    return $query->where('business_id', $business_id);
                }),
            ],
            'type' => 'sometimes|required|in:single,variable,combo',
            'unit_id' => 'nullable|exists:units,id',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'tax' => 'nullable|exists:tax_rates,id',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'enable_stock' => 'nullable|boolean',
            'alert_quantity' => 'nullable|numeric',
        ];
    }
}
