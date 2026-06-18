<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'sub_status' => $this->sub_status,
            'invoice_no' => $this->invoice_no,
            'ref_no' => $this->ref_no,
            'transaction_date' => $this->transaction_date,
            'total_before_tax' => $this->total_before_tax,
            'tax_amount' => $this->tax_amount,
            'discount_type' => $this->discount_type,
            'discount_amount' => $this->discount_amount,
            'shipping_charges' => $this->shipping_charges,
            'final_total' => $this->final_total,
            'payment_status' => $this->payment_status,
            'additional_notes' => $this->additional_notes,
            'staff_note' => $this->staff_note,
            'contact_id' => $this->contact_id,
            'location_id' => $this->location_id,
            'created_by' => $this->created_by,
            'is_direct_sale' => $this->is_direct_sale,
            'is_suspend' => $this->is_suspend,
            'pay_term_number' => $this->pay_term_number,
            'pay_term_type' => $this->pay_term_type,
            'created_at' => $this->created_at,
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->id,
                    'name' => $this->contact->name,
                    'mobile' => $this->contact->mobile,
                    'supplier_business_name' => $this->contact->supplier_business_name,
                ];
            }),
            'location' => $this->whenLoaded('location', function () {
                return ['id' => $this->location->id, 'name' => $this->location->name];
            }),
            'created_by_user' => $this->whenLoaded('createdByUser', function () {
                return ['id' => $this->createdByUser->id, 'full_name' => $this->createdByUser->user_full_name];
            }),
            'payment_lines' => PaymentResource::collection($this->whenLoaded('payment_lines')),
            'sell_lines' => SellLineResource::collection($this->whenLoaded('sell_lines')),
            'purchase_lines' => PurchaseLineResource::collection($this->whenLoaded('purchase_lines')),
        ];

        if ($this->relationLoaded('payment_lines')) {
            $paid = $this->payment_lines->sum('amount');
            $data['paid_amount'] = $paid;
            $data['due_amount'] = max(0, $this->final_total - $paid);
        }

        return $data;
    }
}
