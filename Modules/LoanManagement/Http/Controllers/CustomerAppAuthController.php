<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Http\Requests\CustomerChangePasswordRequest;
use Modules\LoanManagement\Http\Requests\CustomerLoginRequest;

class CustomerAppAuthController extends Controller
{
    use ApiResponseTrait;

    public function login(CustomerLoginRequest $request)
    {
        $login = trim((string) $request->input('login'));
        $customer = LoanCustomer::query()
            ->where(function ($q) use ($login) {
                $q->where('username', $login)
                    ->orWhere('phone', $login)
                    ->orWhere('login_phone', $login);
            })
            ->where('can_login', 1)
            ->where('status', 'active')
            ->first();

        if (! $customer || empty($customer->password) || ! Hash::check($request->input('password'), (string) $customer->password)) {
            return $this->fail('Invalid credentials', 401, (object) []);
        }

        $customer->last_login_at = now();
        $customer->save();

        $tokenResult = $customer->createToken($request->input('device_name', 'customer-app'));
        $token = $tokenResult->accessToken ?? ($tokenResult->plainTextToken ?? null);
        return $this->ok('Login success', ['token' => $token]);
    }

    public function logout()
    {
        $user = auth('customer_loan_api')->user();
        if ($user && method_exists($user, 'token') && $user->token()) {
            $user->token()->revoke();
        } elseif ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        return $this->ok('Logout success', (object) []);
    }

    public function changePassword(CustomerChangePasswordRequest $request)
    {
        /** @var LoanCustomer $customer */
        $customer = auth('customer_loan_api')->user();
        if (! $customer || empty($customer->password) || ! Hash::check($request->input('current_password'), (string) $customer->password)) {
            return $this->fail('Current password is incorrect', 422, (object) []);
        }

        $customer->password = Hash::make((string) $request->input('new_password'));
        $customer->save();

        return $this->ok('Password updated', (object) []);
    }
}
