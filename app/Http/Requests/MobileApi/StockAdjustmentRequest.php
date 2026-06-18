<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('stock_adjustment.create');
    }

    public function rules()
    {
        return [
            'location_id' => 'required|exists:business_locations,id',
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variation_id' => 'required|exists:variations,id',
            'products.*.quantity' => 'required|numeric',
            'products.*.type' => 'required|in:normal,abnormal',
            'products.*.unit_cost' => 'nullable|numeric',
            'products.*.reason' => 'nullable|string',
            'additional_notes' => 'nullable|string',
            'total_amount' => 'nullable|numeric',
        ];
    }
}
