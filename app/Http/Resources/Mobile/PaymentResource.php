<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'payment_ref_no' => $this->payment_ref_no,
            'paid_on' => $this->paid_on,
            'card_transaction_number' => $this->card_transaction_number,
            'card_number' => $this->card_number,
            'card_type' => $this->card_type,
            'card_holder_name' => $this->card_holder_name,
            'cheque_number' => $this->cheque_number,
            'bank_account_number' => $this->bank_account_number,
            'note' => $this->note,
            'account_id' => $this->account_id,
            'payment_for' => $this->payment_for,
            'created_by' => $this->created_by,
            'is_return' => $this->is_return,
            'created_at' => $this->created_at,
            'payment_account' => $this->whenLoaded('payment_account', function () {
                return ['id' => $this->payment_account->id, 'name' => $this->payment_account->name];
            }),
            'created_user' => $this->whenLoaded('created_user', function () {
                return ['id' => $this->created_user->id, 'full_name' => $this->created_user->user_full_name];
            }),
            'transaction' => $this->whenLoaded('transaction', function () {
                return [
                    'id' => $this->transaction->id,
                    'invoice_no' => $this->transaction->invoice_no,
                    'type' => $this->transaction->type,
                    'final_total' => $this->transaction->final_total,
                ];
            }),
        ];
    }
}
