<?php

namespace App\Http\Middleware;

use App\Utils\Util;
use Closure;

class AdminOrSuperadmin
{
    protected Util $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Allow access if user is Admin (for current business) OR Superadmin username.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if (empty($user)) {
            abort(403, 'Unauthorized action.');
        }

        // Superadmin check (same logic as App\Http\Middleware\Superadmin)
        $administrator_list = (string) config('constants.administrator_usernames', '');
        if ($administrator_list !== '' && in_array(strtolower($user->username), explode(',', strtolower($administrator_list)))) {
            return $next($request);
        }

        // Admin check (business-scoped role)
        $business_id = session('user.business_id') ?? $user->business_id;
        if (! empty($business_id) && $this->commonUtil->is_admin($user, (int) $business_id)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}

