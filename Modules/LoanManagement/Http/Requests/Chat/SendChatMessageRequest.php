<?php

namespace Modules\LoanManagement\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_type' => 'required|in:text,image,file,audio,location',
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:51200',
            'audio_duration_seconds' => 'nullable|integer|min:0|max:86400',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'local_uuid' => 'nullable|string|max:80',
            'reply_to_message_id' => 'nullable|integer',
            'reaction' => 'nullable|string|max:80',
        ];
    }
}

