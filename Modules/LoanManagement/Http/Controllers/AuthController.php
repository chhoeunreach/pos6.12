<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    use ApiResponseTrait;

    protected function applyLoginScope($query)
    {
        if (Schema::hasColumn('users', 'allow_login')) {
            $query->where('allow_login', 1);
        }
        if (Schema::hasColumn('users', 'status')) {
            $query->whereIn('status', ['active', 1, '1']);
        }

        return $query;
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'nullable|string',
            'email' => 'nullable|email',
            'mobile' => 'nullable|string',
            'password' => 'required|string',
        ]);

        $field = null;
        $value = null;
        if (! empty($data['email'])) {
            $field = 'email';
            $value = $data['email'];
        } elseif (! empty($data['username'])) {
            $field = 'username';
            $value = $data['username'];
        } elseif (! empty($data['mobile'])) {
            $field = 'mobile';
            $value = $data['mobile'];
        }

        if (empty($field) || empty($value)) {
            return $this->fail('username or email or mobile is required', 422, (object) []);
        }

        if ($field === 'mobile') {
            $userQuery = $this->applyLoginScope(User::query());
            $hasContactNo = Schema::hasColumn('users', 'contact_no');
            $hasContactNumber = Schema::hasColumn('users', 'contact_number');

            if ($hasContactNo || $hasContactNumber) {
                $userQuery->where(function ($query) use ($value, $hasContactNo, $hasContactNumber) {
                    if ($hasContactNo) {
                        $query->orWhere('contact_no', $value);
                    }
                    if ($hasContactNumber) {
                        $query->orWhere('contact_number', $value);
                    }
                });
            }

            $user = ($hasContactNo || $hasContactNumber) ? $userQuery->first() : null;

            if (! empty($user) && Hash::check($data['password'], (string) $user->password)) {
                auth()->login($user);
                $token = null;
                if (method_exists($user, 'createToken')) {
                    $tokenResult = $user->createToken('loan-management');
                    $token = $tokenResult->accessToken ?? ($tokenResult->plainTextToken ?? null);
                }

                return $this->ok('Login success', ['token' => $token, 'user' => $user]);
            }
        }

        if ($field !== 'mobile') {
            $user = $this->applyLoginScope(User::query())->where($field, $value)->first();
            if (! empty($user) && Hash::check($data['password'], (string) $user->password)) {
                auth()->login($user);
                $token = null;
                if (method_exists($user, 'createToken')) {
                    $tokenResult = $user->createToken('loan-management');
                    $token = $tokenResult->accessToken ?? ($tokenResult->plainTextToken ?? null);
                }

                return $this->ok('Login success', ['token' => $token, 'user' => $user]);
            }
        }

        return $this->fail('Invalid credentials', 401, (object) []);
    }

    public function logout(Request $request)
    {
        if ($request->user() && method_exists($request->user(), 'token') && $request->user()->token()) {
            $request->user()->token()->revoke();
        } elseif ($request->user() && method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        auth()->logout();

        return $this->ok('Logout success', (object) []);
    }

    public function profile(Request $request)
    {
        return $this->ok('Profile loaded', $request->user() ?: (object) []);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->fail('Current password is incorrect', 422, (object) []);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return $this->ok('Password changed', (object) []);
    }
}
