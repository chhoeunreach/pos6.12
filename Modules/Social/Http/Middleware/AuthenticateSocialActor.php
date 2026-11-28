<?php

namespace Modules\Social\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Social\Services\SocialActorResolver;

/**
 * Authenticates Social Platform actions for either Customers (`customer_loan_api`) or Staff (`api`).
 * Rejects unauthenticated / guest callers cleanly with HTTP 401.
 */
class AuthenticateSocialActor
{
    public function __construct(
        protected SocialActorResolver $actors,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $actor = $this->actors->resolve($request);
        if ($actor === null || ($actor['id'] ?? 0) <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in or create an account to continue.',
            ], 401);
        }

        $guard = $actor['type'] === SocialActorResolver::ACTOR_STAFF ? 'api' : 'customer_loan_api';
        Auth::shouldUse($guard);
        $request->setUserResolver(fn () => $actor['user']);

        return $next($request);
    }
}
