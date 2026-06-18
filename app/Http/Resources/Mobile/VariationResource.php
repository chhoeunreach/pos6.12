<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class VariationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'product_id' => $this->product_id,
            'sub_sku' => $this->sub_sku,
            'product_variation_id' => $this->product_variation_id,
            'variation_value_id' => $this->variation_value_id,
            'default_purchase_price' => $this->default_purchase_price,
            'dpp_inc_tax' => $this->dpp_inc_tax,
            'profit_percent' => $this->profit_percent,
            'default_sell_price' => $this->default_sell_price,
            'sell_price_inc_tax' => $this->sell_price_inc_tax,
            'product_variation' => $this->whenLoaded('product_variation', function () {
                return ['id' => $this->product_variation->id, 'name' => $this->product_variation->name];
            }),
            'stock' => $this->whenLoaded('variation_location_details', function () {
                return $this->variation_location_details->map(function ($vld) {
                    return [
                        'location_id' => $vld->location_id,
                        'qty_available' => $vld->qty_available,
                    ];
                });
            }),
        ];
    }
}
