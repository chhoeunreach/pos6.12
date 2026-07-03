<?php

namespace Modules\Accessory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccessoryPurchaseItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'accessory_id' => $this->accessory_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
