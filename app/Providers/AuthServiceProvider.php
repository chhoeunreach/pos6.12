<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            $administrator_usernames = array_filter(array_map('trim', explode(',',
                strtolower((string) config('constants.administrator_usernames', '')))));
            if (in_array(strtolower(trim((string) $user->username)), $administrator_usernames, true)) {
                return true;
            }

            if (! in_array($ability, ['backup', 'superadmin', 'manage_modules'], true)
                && $user->hasRole('Admin#'.$user->business_id)) {
                return true;
            }
        });
    }
}
