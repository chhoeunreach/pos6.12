<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class SellLineResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variation_id' => $this->variation_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'unit_price_inc_tax' => $this->unit_price_inc_tax,
            'unit_price_before_discount' => $this->unit_price_before_discount,
            'line_discount_type' => $this->line_discount_type,
            'line_discount_amount' => $this->line_discount_amount,
            'item_tax' => $this->item_tax,
            'tax_id' => $this->tax_id,
            'sell_line_note' => $this->sell_line_note,
            'sub_unit_id' => $this->sub_unit_id,
            'product' => $this->whenLoaded('product', function () {
                return ['id' => $this->product->id, 'name' => $this->product->name];
            }),
            'variations' => $this->whenLoaded('variations', function () {
                return ['id' => $this->variations->id, 'name' => $this->variations->name, 'sub_sku' => $this->variations->sub_sku];
            }),
        ];
    }
}
