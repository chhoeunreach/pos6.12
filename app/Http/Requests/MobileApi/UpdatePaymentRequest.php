<?php

namespace App\Http\Requests\MobileApi;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('payment.edit');
    }

    public function rules()
    {
        return [
            'amount' => 'sometimes|required|numeric|min:0',
            'method' => 'sometimes|required|in:cash,card,cheque,bank_transfer,advance,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7',
            'paid_on' => 'nullable|date',
            'account_id' => 'nullable|exists:accounts,id',
            'note' => 'nullable|string',
        ];
    }
}
