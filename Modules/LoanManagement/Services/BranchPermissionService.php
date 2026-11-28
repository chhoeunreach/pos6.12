<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves UltimatePOS 6.12 Spatie roles/permissions and business location (branch) scoping.
 */
class BranchPermissionService
{
    public const ALL_PERMISSIONS = [
        'loan.view_all_branches',
        'loan.customer.view',
        'loan.customer.create',
        'loan.view',
        'loan.create',
        'loan.edit',
        'loan.payment.receive',
        'loan.payment.approve_proof',
        'loan.chat.view',
        'loan.chat.reply',
        'loan.chat.assign',
        'loan.chat.close',
        'loan.chat.view_all',
        'loan.location.request',
        'loan.location.view',
        'loan.feed.view',
        'loan.feed.create',
        'loan.feed.publish',
        'loan.feed.delete',
    ];

    public function getBusinessId($user): ?int
    {
        if (!$user || !isset($user->business_id)) {
            return null;
        }
        $id = (int)$user->business_id;
        return $id > 0 ? $id : null;
    }

    public function isOwnerOrAdmin($user): bool
    {
        if (!$user) {
            return false;
        }

        $businessId = $this->getBusinessId($user);
        if (method_exists($user, 'hasRole')) {
            if ($businessId && $user->hasRole('Admin#' . $businessId)) {
                return true;
            }
            if ($user->hasRole('Admin') || $user->hasRole('Superadmin')) {
                return true;
            }
        }

        return $this->hasPermission($user, 'loan.view_all_branches')
            || $this->hasPermission($user, 'superadmin');
    }

    public function hasPermission($user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'can') && $user->can($permission)) {
            return true;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Permission might not be seeded yet in Spatie table.
            }
        }

        return false;
    }

    /**
     * Returns null when the user can access all business locations in their business,
     * or an integer array of permitted location IDs.
     */
    public function getPermittedLocationIds($user): ?array
    {
        if (!$user) {
            return [];
        }

        if ($this->isOwnerOrAdmin($user) || $this->hasPermission($user, 'access_all_locations')) {
            return null;
        }

        if (method_exists($user, 'permitted_locations')) {
            $permitted = $user->permitted_locations();
            if ($permitted === 'all') {
                return null;
            }
            if (is_array($permitted)) {
                return array_values(array_unique(array_filter(array_map('intval', $permitted), fn ($id) => $id > 0)));
            }
        }

        if (isset($user->location_id) && (int)$user->location_id > 0) {
            return [(int)$user->location_id];
        }

        return [];
    }

    public function canAccessLocation($user, ?int $locationId): bool
    {
        $permitted = $this->getPermittedLocationIds($user);
        if ($permitted === null) {
            return true;
        }
        if ($locationId === null || $locationId <= 0) {
            return count($permitted) > 0;
        }
        return in_array((int)$locationId, $permitted, true);
    }

    public function resolveRequestedLocationId($user, $requestedLocationId): ?int
    {
        $locId = $requestedLocationId === null ? 0 : (int)$requestedLocationId;
        if ($locId <= 0) {
            return null;
        }
        if (!$this->canAccessLocation($user, $locId)) {
            abort(403, 'You do not have permission to access this branch.');
        }
        return $locId;
    }

    /**
     * Fetches active business locations accessible to the staff user from UltimatePOS.
     */
    public function getAccessibleLocations($user): array
    {
        $businessId = $this->getBusinessId($user);
        if (!$businessId) {
            return [];
        }

        $permitted = $this->getPermittedLocationIds($user);

        try {
            $query = DB::table('business_locations')
                ->where('business_id', $businessId)
                ->where('is_active', 1)
                ->select(['id', 'name', 'location_id as code']);

            if (is_array($permitted)) {
                if (empty($permitted)) {
                    return [];
                }
                $query->whereIn('id', $permitted);
            }

            return $query->orderBy('name')->get()->map(fn ($row) => [
                'id' => (int)$row->id,
                'name' => (string)$row->name,
                'code' => (string)($row->code ?? ''),
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getUserPermissionsList($user): array
    {
        if (!$user) {
            return [];
        }

        if ($this->isOwnerOrAdmin($user)) {
            return self::ALL_PERMISSIONS;
        }

        $granted = [];
        foreach (self::ALL_PERMISSIONS as $perm) {
            if ($this->hasPermission($user, $perm)) {
                $granted[] = $perm;
            }
        }
        return $granted;
    }
}
