<?php

namespace Modules\LoanManagement\Services;

use App\User;
use Illuminate\Support\Facades\Hash;
use Modules\LoanManagement\Entities\LoanCustomer;

class PortalDemoService
{
    public static function credentials(string $portal): ?array
    {
        $customer = $portal === 'customer';
        $settings = BusinessSettingsService::get();
        if (empty($settings[$customer ? 'demo_customer_login_enabled' : 'demo_admin_login_enabled'])) {
            return null;
        }

        // Only show the designated demo account, never arbitrary customer records.
        $identifier = config('loanmanagement.demo_'.$portal.'_identifier', $customer ? '010111001' : 'admin@example.com');
        $password = config('loanmanagement.demo_'.$portal.'_password', 'password');
        $query = $customer ? LoanCustomer::query()->where(fn ($query) => $query->where('login_phone', $identifier)->orWhere('phone', $identifier)->orWhere('username', $identifier))->where('can_login', 1)
            : User::query()->where('email', $identifier)->where('allow_login', 1);
        $user = $query->where('status', 'active')->first();
        if (!$user || !Hash::check($password, $user->getAuthPassword())) {
            return null;
        }

        return ['login' => $identifier, 'password' => $password, 'name' => $user->name ?: 'Demo Account'];
    }
}
