<?php

namespace Modules\LoanManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string|max:191',
            'password' => 'required|string|min:6',
            'device_name' => 'nullable|string|max:100',
        ];
    }
}

