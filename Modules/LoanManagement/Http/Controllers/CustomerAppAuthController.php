<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Http\Requests\CustomerChangePasswordRequest;
use Modules\LoanManagement\Http\Requests\CustomerLoginRequest;

class CustomerAppAuthController extends Controller
{
    use ApiResponseTrait;

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:6', 'max:30'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $rawPhone = trim((string) $validated['phone']);
        $normalizedPhone = $this->normalizePhone($rawPhone);
        $phoneCandidates = array_values(array_unique(array_filter([$rawPhone, $normalizedPhone])));

        if ($normalizedPhone === '' || strlen($normalizedPhone) < 6) {
            return $this->fail('Please enter a valid phone number.', 422, (object) []);
        }

        $exists = LoanCustomer::query()
            ->where(function ($q) use ($phoneCandidates) {
                $q->whereIn('phone', $phoneCandidates)
                    ->orWhereIn('login_phone', $phoneCandidates)
                    ->orWhereIn('username', $phoneCandidates);
            })
            ->exists();

        if ($exists) {
            return $this->fail('Phone number is already registered.', 422, (object) []);
        }

        $schema = Schema::connection('mysql_loan');
        $attrs = [
            'name' => trim((string) $validated['name']),
            'phone' => $normalizedPhone,
            'password' => (string) $validated['password'],
            'status' => 'active',
            'can_login' => true,
            'last_login_at' => now(),
        ];

        if ($schema->hasColumn('loan_customers', 'login_phone')) {
            $attrs['login_phone'] = $normalizedPhone;
        }
        if ($schema->hasColumn('loan_customers', 'username')) {
            $attrs['username'] = $normalizedPhone;
        }
        if (! empty($validated['email']) && $schema->hasColumn('loan_customers', 'email')) {
            $attrs['email'] = trim((string) $validated['email']);
        }
        if ($schema->hasColumn('loan_customers', 'customer_code')) {
            $attrs['customer_code'] = 'REG-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        }
        if ($schema->hasColumn('loan_customers', 'customer_type')) {
            $attrs['customer_type'] = 'community';
        }
        if ($schema->hasColumn('loan_customers', 'created_by_name_snapshot')) {
            $attrs['created_by_name_snapshot'] = 'Mobile Self-Register';
        }

        $customer = DB::connection('mysql_loan')->transaction(fn () => LoanCustomer::create($attrs));

        $tokenResult = $customer->createToken($request->input('device_name', 'customer-app'));
        $token = $tokenResult->accessToken ?? ($tokenResult->plainTextToken ?? null);

        return $this->ok('Registration success', [
            'token' => $token,
            'user' => $this->formatCustomerUser($customer),
        ], 201);
    }

    public function login(CustomerLoginRequest $request)
    {
        $login = trim((string) $request->input('login'));
        $normalized = $this->normalizePhone($login);
        $candidates = array_values(array_unique(array_filter([$login, $normalized])));

        $customer = LoanCustomer::query()
            ->where(function ($q) use ($candidates) {
                $q->whereIn('username', $candidates)
                    ->orWhereIn('phone', $candidates)
                    ->orWhereIn('login_phone', $candidates);
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
        return $this->ok('Login success', [
            'token' => $token,
            'user' => $this->formatCustomerUser($customer),
        ]);
    }

    protected function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        if (str_starts_with($digits, '855') && strlen($digits) >= 11) {
            $digits = '0' . substr($digits, 3);
        }

        return $digits;
    }

    protected function formatCustomerUser(LoanCustomer $customer): array
    {
        return [
            'id' => (int) $customer->id,
            'customer_code' => (string) ($customer->customer_code ?? ''),
            'name' => (string) ($customer->name ?? ''),
            'khmer_name' => (string) ($customer->khmer_name ?? ''),
            'phone' => (string) ($customer->phone ?? ''),
            'login_phone' => (string) ($customer->login_phone ?? ''),
            'username' => (string) ($customer->username ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'customer_type' => (string) ($customer->customer_type ?? ''),
            'status' => (string) ($customer->status ?? 'active'),
            'can_login' => (bool) ($customer->can_login ?? true),
            'customer_photo_url' => $customer->customer_photo_url,
        ];
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
