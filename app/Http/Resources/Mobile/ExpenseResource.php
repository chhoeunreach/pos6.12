<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'ref_no' => $this->ref_no,
            'transaction_date' => $this->transaction_date,
            'final_total' => $this->final_total,
            'payment_status' => $this->payment_status,
            'additional_notes' => $this->additional_notes,
            'expense_category_id' => $this->expense_category_id,
            'expense_for' => $this->expense_for,
            'location_id' => $this->location_id,
            'created_by' => $this->created_by,
            'tax_id' => $this->tax_id,
            'tax_amount' => $this->tax_amount,
            'created_at' => $this->created_at,
            'expense_category' => null,
            'location' => $this->whenLoaded('location', function () {
                return ['id' => $this->location->id, 'name' => $this->location->name];
            }),
            'transaction_for' => $this->whenLoaded('transaction_for', function () {
                return ['id' => $this->transaction_for->id, 'full_name' => $this->transaction_for->user_full_name];
            }),
            'payment_lines' => PaymentResource::collection($this->whenLoaded('payment_lines')),
        ];
    }
}
