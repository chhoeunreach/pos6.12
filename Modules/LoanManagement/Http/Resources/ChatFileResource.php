<?php

namespace Modules\LoanManagement\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatFileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) ($this->id ?? 0),
            'file_id' => (int) ($this->id ?? 0),
            'url' => (string) ($this->url ?? $this->file_url ?? ''),
            'name' => (string) ($this->original_name ?? $this->file_name ?? ''),
            'mime' => (string) ($this->mime_type ?? $this->file_mime ?? ''),
            'size' => (int) ($this->size_bytes ?? $this->size ?? $this->file_size ?? 0),
            'extension' => (string) ($this->extension ?? pathinfo((string) ($this->original_name ?? $this->file_name ?? ''), PATHINFO_EXTENSION)),
        ];
    }
}

