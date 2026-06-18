<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'type' => $this->type,
            'unit_id' => $this->unit_id,
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'sub_category_id' => $this->sub_category_id,
            'tax' => $this->tax,
            'tax_type' => $this->tax_type,
            'enable_stock' => $this->enable_stock,
            'alert_quantity' => $this->alert_quantity,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'product_description' => $this->product_description,
            'weight' => $this->weight,
            'barcode_type' => $this->barcode_type,
            'not_for_selling' => $this->not_for_selling,
            'created_at' => $this->created_at,
            'brand' => $this->whenLoaded('brand', function () {
                return ['id' => $this->brand->id, 'name' => $this->brand->name];
            }),
            'category' => $this->whenLoaded('category', function () {
                return ['id' => $this->category->id, 'name' => $this->category->name];
            }),
            'unit' => $this->whenLoaded('unit', function () {
                return ['id' => $this->unit->id, 'name' => $this->unit->name];
            }),
            'variations' => VariationResource::collection($this->whenLoaded('variations')),
            'product_locations' => $this->whenLoaded('product_locations'),
        ];

        if ($this->relationLoaded('variations') && $this->variations->isNotEmpty()) {
            $firstVar = $this->variations->first();
            $data['default_selling_price'] = $firstVar->sell_price_inc_tax;
            $data['default_purchase_price'] = $firstVar->dpp_inc_tax;
        }

        return $data;
    }
}
