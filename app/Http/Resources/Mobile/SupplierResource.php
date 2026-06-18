<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'supplier_business_name' => $this->supplier_business_name,
            'contact_id' => $this->contact_id,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'tax_number' => $this->tax_number,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'zip_code' => $this->zip_code,
            'land_mark' => $this->land_mark,
            'pay_term_number' => $this->pay_term_number,
            'pay_term_type' => $this->pay_term_type,
            'balance' => $this->balance,
            'total_purchase' => $this->total_purchase ?? 0,
            'total_paid' => $this->total_paid ?? 0,
            'opening_balance' => $this->opening_balance,
            'created_at' => $this->created_at,
        ];
    }
}
