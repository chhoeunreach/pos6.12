<?php

namespace Modules\LoanManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerLocationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_id' => 'nullable|integer',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|min:0|max:360',
            'battery_level' => 'nullable|numeric|min:0|max:100',
            'device_id' => 'nullable|string|max:191',
            'app_version' => 'nullable|string|max:50',
            'recorded_at' => 'nullable|date',
        ];
    }
}

