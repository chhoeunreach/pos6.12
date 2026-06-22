<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('customer.create');
    }

    public function rules()
    {
        $business_id = auth()->user()->business_id ?? session('user.business_id');
        $uniqueContact = function ($column) use ($business_id) {
            return Rule::unique('contacts', $column)->where(function ($query) use ($business_id) {
                return $query->where('business_id', $business_id)
                    ->whereNull('deleted_at');
            });
        };

        return [
            'name' => 'required|string|max:255',
            'mobile' => ['nullable', 'string', 'max:50', $uniqueContact('mobile')],
            'email' => ['nullable', 'email', 'max:255', $uniqueContact('email')],
            'tax_number' => ['nullable', 'string', 'max:255', $uniqueContact('tax_number')],
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string',
            'address_line_2' => 'nullable|string',
            'zip_code' => 'nullable|string|max:20',
            'land_mark' => 'nullable|string|max:255',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'contact_id' => ['nullable', 'string', 'max:255', $uniqueContact('contact_id')],
            'pay_term_number' => 'nullable|numeric',
            'pay_term_type' => 'nullable|in:days,months',
            'credit_limit' => 'nullable|numeric',
            'opening_balance' => 'nullable|numeric',
        ];
    }
}
