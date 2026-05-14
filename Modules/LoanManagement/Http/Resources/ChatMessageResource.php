<?php

namespace Modules\LoanManagement\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $file = null;
        if (! empty($this->file_id)) {
            $file = new ChatFileResource((object) [
                'id' => $this->file_id,
                'file_url' => $this->file_url,
                'file_name' => $this->file_name,
                'file_mime' => $this->file_mime,
                'file_size' => $this->file_size,
            ]);
        }

        return [
            'id' => (int) $this->id,
            'thread_id' => (int) $this->thread_id,
            'sender_type' => (string) ($this->sender_type ?? ''),
            'sender_id' => (int) ($this->sender_id ?? 0),
            'sender_name_snapshot' => (string) ($this->sender_name_snapshot ?? ''),
            'message' => (string) ($this->message ?? ''),
            'message_type' => (string) ($this->message_type ?? 'text'),
            'file' => $file ? $file->toArray($request) : null,
            'audio_duration_seconds' => $this->audio_duration_seconds === null ? null : (int) $this->audio_duration_seconds,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'location_address' => $this->location_address === null ? null : (string) $this->location_address,
            'is_read' => (bool) ($this->is_read ?? false),
            'read_at' => $this->read_at ? $this->read_at->toISOString() : null,
            'metadata' => $this->metadata ?? (object) [],
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
        ];
    }
}

