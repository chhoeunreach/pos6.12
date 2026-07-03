<?php

namespace Modules\Accessory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessoryPurchaseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'supplier_id' => 'nullable|exists:contacts,id',
            'supplier_name' => 'nullable|string|max:255',
            'reference_no' => 'required|string|max:255',
            'transaction_date' => 'required|date',
            'status' => 'required|in:received,ordered,pending',
            'payment_status' => 'nullable|in:paid,partial,due',
            'additional_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.accessory_id' => 'required|exists:accessories,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ];
    }
}
