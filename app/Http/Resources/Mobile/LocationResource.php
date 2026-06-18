<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location_id' => $this->location_id,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'zip_code' => $this->zip_code,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'invoice_scheme_id' => $this->invoice_scheme_id,
            'invoice_layout_id' => $this->invoice_layout_id,
            'sale_invoice_scheme_id' => $this->sale_invoice_scheme_id,
            'selling_price_group_id' => $this->selling_price_group_id,
            'default_payment_accounts' => $this->default_payment_accounts,
            'receipt_printer_type' => $this->receipt_printer_type,
            'website' => $this->website,
            'custom_field1' => $this->custom_field1,
            'custom_field2' => $this->custom_field2,
            'custom_field3' => $this->custom_field3,
            'custom_field4' => $this->custom_field4,
            'address' => $this->location_address,
        ];
    }
}
