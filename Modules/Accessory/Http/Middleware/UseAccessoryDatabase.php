<?php

namespace Modules\Accessory\Http\Middleware;

use App\System;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UseAccessoryDatabase
{
    public function handle($request, Closure $next)
    {
        if (empty(System::getProperty('accessory_version'))) {
            abort(404);
        }

        $connection = config('accessory.database_connection', 'accessory');
        $original = config('database.default');
        $mainUser = null;
        if (Schema::connection($original)->hasTable('users')) {
            $mainUser = auth()->user();
        }
        $mainSessionData = $this->captureMainSessionData($request);

        if ($mainUser) {
            $this->syncAuthenticatedUserToAccessory($mainUser, $original, $connection);
        }

        config(['database.default' => $connection]);
        DB::setDefaultConnection($connection);

        if ($request->hasSession()) {
            $request->session()->forget([
                'user',
                'business',
                'currency',
                'financial_year',
            ]);
        }

        try {
            return $next($request);
        } finally {
            $this->restoreMainSessionData($request, $mainSessionData);
            config(['database.default' => $original]);
            DB::setDefaultConnection($original);
        }
    }

    private function captureMainSessionData($request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        return $request->session()->only([
            'user',
            'business',
            'currency',
            'financial_year',
            '_session_database_connection',
        ]);
    }

    private function restoreMainSessionData($request, array $mainSessionData): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            'user',
            'business',
            'currency',
            'financial_year',
            '_session_database_connection',
        ]);

        foreach ($mainSessionData as $key => $value) {
            $request->session()->put($key, $value);
        }
    }

    private function syncAuthenticatedUserToAccessory($mainUser, string $mainConnection, string $accessoryConnection): void
    {
        $main = DB::connection($mainConnection);
        $accessory = DB::connection($accessoryConnection);

        $businessId = 1;

        $accessory->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->ensureFallbackBusiness($accessory, $businessId, $mainUser->id);

            $this->copyRowById($main, $accessory, 'users', $mainUser->id, [
                'business_id' => $businessId,
                'allow_login' => 1,
                'status' => 'active',
            ]);

            $this->ensureDefaultPosData($accessory, $businessId, $mainUser->id);

            $this->copyUserAuthorizationData($main, $accessory, $mainUser);
        } finally {
            $accessory->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function ensureDefaultPosData($accessory, int $businessId, int $userId): void
    {
        $now = now();

        if ($this->hasTable($accessory, 'contacts')) {
            $accessory->table('contacts')->updateOrInsert(
                ['id' => 1],
                [
                    'business_id' => $businessId,
                    'type' => 'customer',
                    'name' => 'Walk-In Customer',
                    'contact_id' => 'CO0001',
                    'mobile' => '',
                    'is_default' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if ($this->hasTable($accessory, 'cash_registers')) {
            $accessory->table('cash_registers')->updateOrInsert(
                [
                    'business_id' => $businessId,
                    'user_id' => $userId,
                    'status' => 'open',
                ],
                [
                    'location_id' => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function copyBusinessLocations($main, $accessory, int $businessId): void
    {
        if (! $this->hasTable($main, 'business_locations') || ! $this->hasTable($accessory, 'business_locations')) {
            return;
        }

        $locations = $main->table('business_locations')->where('business_id', $businessId)->get();
        foreach ($locations as $location) {
            $this->copyRow($accessory, 'business_locations', (array) $location);
        }
    }

    private function copyDefaultInvoiceSetup($main, $accessory, int $businessId): void
    {
        foreach (['invoice_schemes', 'invoice_layouts'] as $table) {
            if (! $this->hasTable($main, $table) || ! $this->hasTable($accessory, $table)) {
                continue;
            }

            $rows = $main->table($table)->where('business_id', $businessId)->get();
            foreach ($rows as $row) {
                $this->copyRow($accessory, $table, (array) $row);
            }
        }
    }

    private function copyRowById($main, $accessory, string $table, $id, array $overrides = []): void
    {
        if (! $this->hasTable($main, $table) || ! $this->hasTable($accessory, $table)) {
            return;
        }

        $row = $main->table($table)->where('id', $id)->first();
        if (! $row) {
            return;
        }

        $this->copyRow($accessory, $table, array_merge((array) $row, $overrides));
    }

    private function copyRow($accessory, string $table, array $data): void
    {
        $columns = Schema::connection($accessory->getName())->getColumnListing($table);
        $data = array_intersect_key($data, array_flip($columns));

        if (! isset($data['id'])) {
            return;
        }

        $accessory->table($table)->updateOrInsert(['id' => $data['id']], $data);
    }

    private function ensureFallbackBusiness($accessory, int $businessId, int $userId): void
    {
        if ($this->hasTable($accessory, 'currencies')) {
            $accessory->table('currencies')->updateOrInsert(
                ['id' => 2],
                [
                    'country' => 'America',
                    'currency' => 'Dollars',
                    'code' => 'USD',
                    'symbol' => '$',
                    'thousand_separator' => ',',
                    'decimal_separator' => '.',
                ]
            );
        }

        if ($this->hasTable($accessory, 'business')) {
            $accessory->table('business')->updateOrInsert(
                ['id' => $businessId],
                [
                    'name' => 'Accessory',
                    'currency_id' => 2,
                    'owner_id' => $userId,
                    'fy_start_month' => 1,
                    'time_zone' => config('app.timezone', 'Asia/Bangkok'),
                    'accounting_method' => 'fifo',
                    'sell_price_tax' => 'includes',
                    'enabled_modules' => json_encode(['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses', 'account']),
                    'date_format' => 'm/d/Y',
                    'time_format' => '24',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function copyUserAuthorizationData($main, $accessory, $mainUser): void
    {
        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'] as $table) {
            if (! $this->hasTable($main, $table) || ! $this->hasTable($accessory, $table)) {
                return;
            }
        }

        $modelTypes = [
            get_class($mainUser),
            'App\\User',
        ];

        $roleIds = $main->table('model_has_roles')
            ->where('model_id', $mainUser->id)
            ->whereIn('model_type', $modelTypes)
            ->pluck('role_id')
            ->unique()
            ->values();

        $permissionIds = $main->table('model_has_permissions')
            ->where('model_id', $mainUser->id)
            ->whereIn('model_type', $modelTypes)
            ->pluck('permission_id')
            ->unique()
            ->values();

        $permissionIds = $this->filterAllowedPermissionIds($main, $permissionIds);

        foreach ($roleIds as $roleId) {
            $this->copyRowById($main, $accessory, 'roles', $roleId);

            $accessory->table('model_has_roles')->insertOrIgnore([
                'role_id' => $roleId,
                'model_type' => 'App\\User',
                'model_id' => $mainUser->id,
            ]);
        }

        foreach ($permissionIds as $permissionId) {
            $this->copyRowById($main, $accessory, 'permissions', $permissionId);

            $accessory->table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'model_type' => 'App\\User',
                'model_id' => $mainUser->id,
            ]);
        }

        $rolePermissionPermissionIds = $this->filterAllowedPermissionIds(
            $main,
            $main->table('role_has_permissions')
                ->whereIn('role_id', $roleIds)
                ->pluck('permission_id')
                ->unique()
                ->values()
        );

        $rolePermissionRows = $main->table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $rolePermissionPermissionIds)
            ->get();

        foreach ($rolePermissionRows as $row) {
            $this->copyRowById($main, $accessory, 'permissions', $row->permission_id);

            $accessory->table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $row->permission_id,
                'role_id' => $row->role_id,
            ]);
        }
    }

    private function filterAllowedPermissionIds($connection, $permissionIds)
    {
        if ($permissionIds->isEmpty() || empty($this->getExcludedPermissionPrefixes())) {
            return $permissionIds;
        }

        $allowedIds = $connection->table('permissions')
            ->whereIn('id', $permissionIds)
            ->get(['id', 'name'])
            ->reject(function ($permission) {
                return $this->isExcludedPermissionName($permission->name);
            })
            ->pluck('id')
            ->values();

        return $allowedIds;
    }

    private function isExcludedPermissionName(string $permissionName): bool
    {
        foreach ($this->getExcludedPermissionPrefixes() as $prefix) {
            if (str_starts_with($permissionName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function getExcludedPermissionPrefixes(): array
    {
        $excludedModules = array_map('strtolower', config('accessory.excluded_main_modules', []));

        if (! in_array('repair', $excludedModules, true)) {
            return [];
        }

        return [
            'repair.',
            'repair_status.',
            'job_sheet.',
        ];
    }
private function hasTable($connection, string $table): bool
    {
        return Schema::connection($connection->getName())->hasTable($table);
    }
}


