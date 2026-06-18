<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('expense.add');
    }

    public function rules()
    {
        return [
            'location_id' => 'required|exists:business_locations,id',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'expense_for' => 'nullable|exists:users,id',
            'ref_no' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'final_total' => 'required|numeric|min:0',
            'tax_id' => 'nullable|exists:tax_rates,id',
            'additional_notes' => 'nullable|string',
            'payment' => 'nullable|array',
            'payment.0.method' => 'required_with:payment|string',
            'payment.0.amount' => 'required_with:payment|numeric|min:0',
            'payment.0.account_id' => 'nullable|exists:accounts,id',
        ];
    }
}
