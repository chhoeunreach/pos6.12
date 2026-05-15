<?php

namespace Modules\LoanManagement\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class CreateChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|integer',
            'staff_id' => 'nullable|integer',
            'loan_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:255',
            'type' => 'nullable|in:customer_staff,customer_collector,customer_admin,staff_admin',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'avatar_url' => 'nullable|string|max:2048',
            'is_pinned' => 'nullable|boolean',
            'is_muted' => 'nullable|boolean',
        ];
    }
}

