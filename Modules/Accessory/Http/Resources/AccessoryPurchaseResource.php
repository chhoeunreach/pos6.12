<?php

namespace Modules\Accessory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccessoryPurchaseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name ?? $this->supplier?->name,
            'reference_no' => $this->reference_no,
            'transaction_date' => $this->transaction_date?->toIso8601String(),
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'total_cost' => (float) $this->total_cost,
            'additional_notes' => $this->additional_notes,
            'created_by' => $this->createdBy?->name,
            'items' => AccessoryPurchaseItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
