<?php

namespace Modules\ClearDataByDate\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClearDataByDatePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'location_id' => ['nullable', 'integer'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string'],
        ];
    }
}

