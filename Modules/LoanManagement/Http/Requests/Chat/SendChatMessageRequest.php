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
            'audio_duration_seconds' => 'nullable|numeric|min:0|max:3600',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'local_uuid' => 'nullable|string|max:80',
            'reply_to_message_id' => 'nullable|integer',
            'reaction' => 'nullable|string|max:80',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('message_type') !== 'audio') {
                return;
            }

            $file = $this->file('file');
            if (! $file) {
                $validator->errors()->add('file', 'Voice file is required.');
                return;
            }

            $allowedMimes = [
                'audio/mpeg',
                'audio/mp3',
                'audio/mp4',
                'audio/x-m4a',
                'audio/aac',
                'audio/wav',
                'audio/x-wav',
                'audio/ogg',
                'audio/webm',
                'audio/3gpp',
            ];
            $allowedExtensions = ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'webm', '3gp'];
            $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
            $clientMime = strtolower((string) $file->getClientMimeType());
            $extension = strtolower((string) $file->getClientOriginalExtension());

            if (
                ! in_array($extension, $allowedExtensions, true)
                || (! in_array($mime, $allowedMimes, true) && ! in_array($clientMime, $allowedMimes, true))
            ) {
                $validator->errors()->add('file', 'Unsupported voice file type.');
            }

            if ($file->getSize() > 20 * 1024 * 1024) {
                $validator->errors()->add('file', 'Voice file may not be greater than 20MB.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.file' => 'Unsupported voice file type.',
            'file.max' => 'Voice file may not be greater than 20MB.',
        ];
    }
}

