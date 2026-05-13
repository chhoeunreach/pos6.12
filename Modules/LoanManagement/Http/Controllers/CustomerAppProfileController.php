<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class CustomerAppProfileController extends Controller
{
    use ApiResponseTrait;

    public function profile()
    {
        $customer = auth('customer_loan_api')->user();
        $photoUrl = null;
        if (! empty($customer->customer_photo_file_id)) {
            $file = \Illuminate\Support\Facades\DB::connection('mysql_loan')->table('loan_files')->where('id', $customer->customer_photo_file_id)->first();
            if ($file && ! empty($file->path)) {
                $photoUrl = Storage::disk($file->disk ?? 'public')->url($file->path);
            }
        }

        return $this->ok('Profile loaded', [
            'id' => (int) $customer->id,
            'customer_code' => (string) ($customer->customer_code ?? ''),
            'name' => (string) ($customer->name ?? ''),
            'khmer_name' => (string) ($customer->khmer_name ?? ''),
            'phone' => (string) ($customer->phone ?? ''),
            'alternate_phone' => (string) ($customer->alternate_phone ?? ''),
            'login_phone' => (string) ($customer->login_phone ?? ''),
            'username' => (string) ($customer->username ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'gender' => (string) ($customer->gender ?? ''),
            'date_of_birth' => ! empty($customer->date_of_birth) ? date('Y-m-d', strtotime((string) $customer->date_of_birth)) : null,
            'id_card_number' => (string) ($customer->id_card_number ?? ''),
            'passport_number' => (string) ($customer->passport_number ?? ''),
            'address' => (string) ($customer->address ?? ''),
            'province' => (string) ($customer->province ?? ''),
            'district' => (string) ($customer->district ?? ''),
            'commune' => (string) ($customer->commune ?? ''),
            'village' => (string) ($customer->village ?? ''),
            'workplace' => (string) ($customer->workplace ?? ''),
            'monthly_income' => $this->money($customer->monthly_income ?? 0),
            'customer_type' => (string) ($customer->customer_type ?? ''),
            'status' => (string) ($customer->status ?? 'active'),
            'blacklist_status' => (bool) ($customer->blacklist_status ?? false),
            'can_login' => (bool) ($customer->can_login ?? false),
            'allow_gps_tracking' => (bool) ($customer->allow_gps_tracking ?? false),
            'last_login_at' => $customer->last_login_at ? date('Y-m-d H:i:s', strtotime((string) $customer->last_login_at)) : null,
            'customer_photo_url' => $photoUrl,
        ]);
    }
}
