<?php

namespace Modules\LoanManagement\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatLocationResource extends JsonResource
{
    public function toArray($request): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'address' => $this->location_address === null ? null : (string) $this->location_address,
        ];
    }
}
