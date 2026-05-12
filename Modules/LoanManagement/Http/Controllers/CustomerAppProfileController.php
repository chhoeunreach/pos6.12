<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;

class CustomerAppProfileController extends Controller
{
    public function profile()
    {
        $customer = auth('customer_loan_api')->user();
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'login_phone' => $customer->login_phone,
                'email' => $customer->email,
                'allow_gps_tracking' => (bool) $customer->allow_gps_tracking,
                'last_login_at' => $customer->last_login_at,
            ],
        ]);
    }
}

