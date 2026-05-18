<?php

namespace Modules\LoanManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanFromSellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('loan_management.create_from_sell');
    }

    public function rules(): array
    {
        return [
            'transaction_id' => 'required|integer',
            'loan_date' => 'required|date',
            'principal_amount' => 'required|numeric|min:0.01',
            'down_payment' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'required|in:flat,reducing',
            'duration_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:monthly,weekly,daily',
            'first_due_date' => 'required|date',
            'currency' => 'required|in:USD,KHR',
            'exchange_rate' => 'nullable|numeric|min:0',
            'penalty_type' => 'nullable|string|max:50',
            'penalty_amount' => 'nullable|numeric|min:0',
            'assigned_collector_id' => 'nullable|integer',
            'customer_group_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'payment' => 'nullable|array',
            'payment.amount' => 'nullable|numeric|min:0',
            'payment.paid_date' => 'nullable|date',
            'payment.payment_method_id' => 'nullable|integer',
            'payment.currency' => 'nullable|in:USD,KHR',
            'payment.exchange_rate' => 'nullable|numeric|min:0',
            'payment.status' => 'nullable|in:completed,pending,failed',
            'payment.account_name' => 'nullable|string|max:255',
            'payment.account_number' => 'nullable|string|max:255',
            'payment.transaction_id' => 'nullable|string|max:255',
            'payment.channel' => 'nullable|string|max:100',
            'payment.reference_number' => 'nullable|string|max:255',
            'payment.note' => 'nullable|string|max:1000',
            'action_type' => 'required|in:draft,create,create_approve',
        ];
    }
}
