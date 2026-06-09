<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            $servicePrefix = trim(config('service.route_prefix', 'service'), '/');
            if (
                $servicePrefix !== '' &&
                ($request->is($servicePrefix) || $request->is($servicePrefix . '/*')) &&
                app('router')->has('service.login')
            ) {
                return route('service.login');
            }

            return route('login');
        }
    }
}
