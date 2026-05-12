<?php

namespace Modules\LoanManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'main_contact_id' => 'nullable|integer',
            'business_location_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'khmer_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'alternate_phone' => 'nullable|string|max:50',
            'login_phone' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:8|confirmed',
            'can_login' => 'nullable|boolean',
            'telegram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'id_card_number' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'province' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'commune' => 'nullable|string|max:255',
            'village' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'family_contact_name' => 'nullable|string|max:255',
            'family_contact_phone' => 'nullable|string|max:50',
            'spouse_name' => 'nullable|string|max:255',
            'spouse_phone' => 'nullable|string|max:50',
            'workplace' => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric',
            'customer_type' => 'nullable|string|max:100',
            'customer_photo_file_id' => 'nullable|integer',
            'id_front_file_id' => 'nullable|integer',
            'id_back_file_id' => 'nullable|integer',
            'blacklist_status' => 'nullable|boolean',
            'blacklist_reason' => 'nullable|string|max:1000',
            'note' => 'nullable|string|max:5000',
            'allow_gps_tracking' => 'nullable|boolean',
            'gps_tracking_note' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive',
            'create_mode' => 'nullable|in:new,clone',
        ];
    }
}

