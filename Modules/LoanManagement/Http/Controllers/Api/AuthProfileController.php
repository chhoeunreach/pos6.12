<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\BranchPermissionService;

/**
 * Exposes authenticated staff roles, permissions, and accessible POS 6.12 branches.
 */
class AuthProfileController extends Controller
{
    public function __construct(
        protected BranchPermissionService $branchPerms,
    ) {
    }

    public function staffProfile(Request $request)
    {
        $user = $request->user();
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [];

        return response()->json([
            'success' => true,
            'message' => 'Staff profile loaded',
            'data' => [
                'user' => $user,
                'business_id' => $this->branchPerms->getBusinessId($user),
                'roles' => $roles,
                'permissions' => $this->branchPerms->getUserPermissionsList($user),
                'locations' => $this->branchPerms->getAccessibleLocations($user),
            ],
        ]);
    }
}
