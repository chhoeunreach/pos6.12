<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('customer.edit');
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'mobile' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string',
            'address_line_2' => 'nullable|string',
            'zip_code' => 'nullable|string|max:20',
            'land_mark' => 'nullable|string|max:255',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'pay_term_number' => 'nullable|numeric',
            'pay_term_type' => 'nullable|in:days,months',
            'credit_limit' => 'nullable|numeric',
        ];
    }
}
