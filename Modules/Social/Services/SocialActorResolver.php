<?php

namespace Modules\Social\Services;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\LoanManagement\Entities\LoanCustomer;

/**
 * Resolves the active Social Platform actor (customer or staff user) without colliding guard IDs.
 */
class SocialActorResolver
{
    public const ACTOR_CUSTOMER = 'customer';
    public const ACTOR_STAFF = 'staff';

    /**
     * Resolve the currently authenticated actor or return null for guests.
     *
     * @return array{type: string, id: int, name: string, user: object}|null
     */
    public function resolve(Request $request, ?string $preferredType = null): ?array
    {
        $hint = strtolower(trim((string) ($preferredType ?: $request->header('X-Social-Actor', ''))));

        if ($hint === self::ACTOR_STAFF) {
            return $this->fromStaff($this->resolveGuardUser($request, 'api'));
        }

        if ($hint === self::ACTOR_CUSTOMER) {
            return $this->fromCustomer($this->resolveGuardUser($request, 'customer_loan_api'));
        }

        $boundUser = $request->user();
        if ($boundUser instanceof User) {
            return $this->fromStaff($boundUser);
        }
        if ($boundUser instanceof LoanCustomer) {
            $tokenProvider = $this->resolveTokenProvider($boundUser);
            if ($tokenProvider === 'users') {
                return $this->fromStaff($this->resolveGuardUser($request, 'api'));
            }
            return $this->fromCustomer($boundUser);
        }

        $customer = $this->resolveGuardUser($request, 'customer_loan_api');
        if ($customer instanceof LoanCustomer) {
            if ($this->resolveTokenProvider($customer) === 'users') {
                $staff = $this->resolveGuardUser($request, 'api');
                if ($staff instanceof User) {
                    return $this->fromStaff($staff);
                }
            }
            return $this->fromCustomer($customer);
        }

        $staff = $this->resolveGuardUser($request, 'api');
        if ($staff instanceof User) {
            return $this->fromStaff($staff);
        }

        return null;
    }

    /**
     * Resolve authenticated actor or abort with 401.
     *
     * @return array{type: string, id: int, name: string, user: object}
     */
    public function requireActor(Request $request, ?string $preferredType = null): array
    {
        $actor = $this->resolve($request, $preferredType);
        if ($actor === null || ($actor['id'] ?? 0) <= 0) {
            abort(401, 'Authentication required.');
        }

        return $actor;
    }

    protected function resolveGuardUser(Request $request, string $guard): ?object
    {
        try {
            $user = $request->user($guard);
            if ($user) {
                return $user;
            }
        } catch (\Throwable $e) {
            // Fall through to Auth::guard.
        }

        try {
            return Auth::guard($guard)->user();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function resolveTokenProvider(object $user): ?string
    {
        try {
            if (method_exists($user, 'token')) {
                $token = $user->token();
                if ($token && isset($token->client) && isset($token->client->provider)) {
                    return (string) $token->client->provider;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    protected function fromCustomer(?object $customer): ?array
    {
        if (! $customer) {
            return null;
        }

        $name = trim((string) ($customer->khmer_name ?: ($customer->name ?: ($customer->phone ?: 'Customer'))));

        return [
            'type' => self::ACTOR_CUSTOMER,
            'id' => (int) $customer->id,
            'name' => $name !== '' ? $name : 'Customer',
            'user' => $customer,
        ];
    }

    protected function fromStaff(?object $staff): ?array
    {
        if (! $staff) {
            return null;
        }

        $fullName = trim((string) (
            $staff->name
            ?? trim(((string) ($staff->first_name ?? '')) . ' ' . ((string) ($staff->last_name ?? '')))
            ?: ($staff->username ?? 'Staff')
        ));

        return [
            'type' => self::ACTOR_STAFF,
            'id' => (int) $staff->id,
            'name' => $fullName !== '' ? $fullName : 'Staff',
            'user' => $staff,
        ];
    }
}
