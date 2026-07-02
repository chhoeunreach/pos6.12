<?php

namespace Modules\Accessory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MobileAccessoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'model' => $this->model,
            'price' => (float) $this->price,
            'cost' => (float) $this->cost,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
