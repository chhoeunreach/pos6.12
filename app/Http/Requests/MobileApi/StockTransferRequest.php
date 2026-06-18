<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('stock_transfer.create');
    }

    public function rules()
    {
        return [
            'location_id' => 'required|exists:business_locations,id',
            'transfer_location_id' => 'required|exists:business_locations,id|different:location_id',
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variation_id' => 'required|exists:variations,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_cost' => 'nullable|numeric',
            'additional_notes' => 'nullable|string',
            'shipping_charges' => 'nullable|numeric',
        ];
    }
}
