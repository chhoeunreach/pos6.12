<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseLineResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variation_id' => $this->variation_id,
            'quantity' => $this->quantity,
            'pp_without_discount' => $this->pp_without_discount,
            'purchase_price' => $this->purchase_price,
            'purchase_price_inc_tax' => $this->purchase_price_inc_tax,
            'unit_cost_before_discount' => $this->pp_without_discount,
            'unit_cost' => $this->purchase_price,
            'unit_cost_inc_tax' => $this->purchase_price_inc_tax,
            'discount_percent' => $this->discount_percent,
            'item_tax' => $this->item_tax,
            'tax_id' => $this->tax_id,
            'lot_number' => $this->lot_number,
            'mfg_date' => $this->mfg_date,
            'exp_date' => $this->exp_date,
            'sub_unit_id' => $this->sub_unit_id,
            'purchase_line_note' => $this->purchase_line_note,
            'quantity_sold' => $this->quantity_sold,
            'quantity_adjusted' => $this->quantity_adjusted,
            'quantity_returned' => $this->quantity_returned,
            'product' => $this->whenLoaded('product', function () {
                return ['id' => $this->product->id, 'name' => $this->product->name];
            }),
            'variations' => $this->whenLoaded('variations', function () {
                return ['id' => $this->variations->id, 'name' => $this->variations->name, 'sub_sku' => $this->variations->sub_sku];
            }),
        ];
    }
}
