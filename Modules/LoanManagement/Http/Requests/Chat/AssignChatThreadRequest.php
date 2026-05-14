<?php

namespace Modules\LoanManagement\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class AssignChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => 'required|integer',
        ];
    }
}

