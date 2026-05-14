<?php

namespace Modules\LoanManagement\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatFileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'file_id' => (int) ($this->id ?? 0),
            'url' => (string) ($this->url ?? $this->file_url ?? ''),
            'name' => (string) ($this->original_name ?? $this->file_name ?? ''),
            'mime' => (string) ($this->mime_type ?? $this->file_mime ?? ''),
            'size' => (int) ($this->size_bytes ?? $this->file_size ?? 0),
        ];
    }
}

