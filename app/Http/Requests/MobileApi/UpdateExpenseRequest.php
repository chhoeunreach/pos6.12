<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('expense.edit');
    }

    public function rules()
    {
        return [
            'location_id' => 'sometimes|required|exists:business_locations,id',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'expense_for' => 'nullable|exists:users,id',
            'transaction_date' => 'sometimes|required|date',
            'final_total' => 'sometimes|required|numeric|min:0',
            'additional_notes' => 'nullable|string',
        ];
    }
}
