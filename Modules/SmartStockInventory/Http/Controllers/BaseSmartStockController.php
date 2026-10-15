<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Utils\Util;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

abstract class BaseSmartStockController extends Controller
{
    public function __construct(protected Util $util)
    {
    }

    protected function businessId(): int
    {
        return (int) session('user.business_id');
    }

    protected function defaultDateRange(array $input): array
    {
        $start = ! empty($input['start_date']) ? Carbon::parse($input['start_date']) : Carbon::today();
        $end = ! empty($input['end_date']) ? Carbon::parse($input['end_date']) : Carbon::today();

        return [$start->startOfDay(), $end->endOfDay()];
    }

    protected function currentUser()
    {
        return request()->user() ?? auth()->user();
    }

    protected function isSuperAdminOrAdmin($user): bool
    {
        if (! $user) {
            return false;
        }
        try {
            if ($user->can('superadmin')) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        try {
            if (method_exists($user, 'getRoleNames')) {
                foreach ($user->getRoleNames() as $roleName) {
                    if (stripos((string) $roleName, 'admin') !== false) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    protected function permittedLocationIds(int $businessId): array
    {
        $user = $this->currentUser();
        if ($this->isSuperAdminOrAdmin($user)) {
            return DB::table('business_locations')->where('business_id', $businessId)->pluck('id')->all();
        }

        $permitted = $user && method_exists($user, 'permitted_locations') ? $user->permitted_locations($businessId) : 'all';
        if ($permitted === 'all') {
            return DB::table('business_locations')->where('business_id', $businessId)->pluck('id')->all();
        }

        return array_map('intval', (array) $permitted);
    }

    protected function locationOptions(int $businessId)
    {
        return DB::table('business_locations')->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']);
    }
}
