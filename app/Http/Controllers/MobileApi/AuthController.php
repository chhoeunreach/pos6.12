<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Requests\MobileApi\LoginRequest;
use App\Http\Resources\Mobile\AuthUserResource;
use App\Http\Resources\Mobile\LocationResource;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;

/**
 * @group Authentication
 * APIs for user authentication and authorization
 */
class AuthController extends BaseController
{
    /**
     * Login
     * 
     * Authenticate user and return Bearer token.
     * 
     * @unauthenticated
     * @response 200 {
     *   "success": true,
     *   "message": "Login successful",
     *   "data": {
     *     "token": "eyJ0eXAiOiJKV1Qi...",
     *     "token_type": "Bearer",
     *     "user": {
     *       "id": 1,
     *       "username": "admin",
     *       "email": "admin@example.com"
     *     }
     *   }
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Invalid credentials",
     *   "data": null
     * }
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        if ($user->business_id) {
            $business = \App\Business::find($user->business_id);
            if (!$business) {
                return $this->error('Business not found', 404);
            }
            if (method_exists($business, 'is_active') && !$business->is_active) {
                return $this->error('Business is inactive', 403);
            }
        }

        $token = $user->createToken('MobileApp')->accessToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AuthUserResource($user),
        ], 'Login successful');
    }

    /**
     * Logout
     * 
     * Revoke current access token.
     * 
     * @authenticated
     * @response 200 {"success": true, "message": "Logged out successfully", "data": null}
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->token()->revoke();
        }

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Current User
     * 
     * Get authenticated user details.
     * 
     * @authenticated
     */
    public function me(Request $request)
    {
        return $this->success(new AuthUserResource($request->user()));
    }

    /**
     * User Permissions
     * 
     * Get all permissions and roles for authenticated user.
     * 
     * @authenticated
     */
    public function permissions(Request $request)
    {
        $user = $request->user();
        $permissions = $user->getAllPermissions()->pluck('name');

        $all_permissions = $permissions->toArray();

        $can_access_all_locations = $user->can('access_all_locations');

        return $this->success([
            'all_permissions' => $all_permissions,
            'can_access_all_locations' => $can_access_all_locations,
            'role' => $user->getRoleNames()->first(),
        ]);
    }

    /**
     * User Locations
     * 
     * Get accessible business locations for authenticated user.
     * 
     * @authenticated
     */
    public function locations(Request $request)
    {
        $user = $request->user();
        $business_id = $user->business_id;

        $permitted_locations = $user->permitted_locations();

        $query = \App\BusinessLocation::where('business_id', $business_id)->active();

        if ($permitted_locations != 'all') {
            $query->whereIn('id', $permitted_locations);
        }

        $locations = $query->get();

        return $this->success(LocationResource::collection($locations));
    }
}
