<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('payment.create');
    }

    public function rules()
    {
        return [
            'transaction_id' => 'nullable|exists:transactions,id',
            'contact_id' => 'required_without:transaction_id|exists:contacts,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,card,cheque,bank_transfer,advance,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7',
            'paid_on' => 'nullable|date',
            'account_id' => 'nullable|exists:accounts,id',
            'card_number' => 'nullable|string',
            'card_holder_name' => 'nullable|string',
            'card_transaction_number' => 'nullable|string',
            'card_type' => 'nullable|string',
            'cheque_number' => 'nullable|string',
            'note' => 'nullable|string',
        ];
    }
}
