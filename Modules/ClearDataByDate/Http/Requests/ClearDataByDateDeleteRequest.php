<?php

namespace Modules\ClearDataByDate\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClearDataByDateDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'preview_token' => ['required', 'string'],
            'confirm_text' => ['required', 'string'],
            'password' => ['required', 'string'],
            'dry_run' => ['nullable', 'boolean'],
            'continue_on_blocked' => ['nullable', 'boolean'],
        ];
    }
}

