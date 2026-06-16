<?php

namespace Modules\Accessory\Http\Middleware;

use Closure;
use App\Business;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class UseAccessoryDatabase
{
    public function handle($request, Closure $next)
    {
        $connection = config('accessory.database_connection', 'accessory');
        $original = config('database.default');
        $mainUser = null;
        $mainUserId = null;
        if (Schema::connection($original)->hasTable('users')) {
            $mainUser = Auth::user();
            $mainUserId = $mainUser?->id;
        }
        $mainSessionData = $this->captureMainSessionData($request);

        if ($mainUser) {
            $this->syncAuthenticatedUserToAccessory($mainUser, $original, $connection);
        }

        config(['database.default' => $connection]);
        DB::setDefaultConnection($connection);
        $accessoryUser = $this->useAccessoryAuthenticatedUser($mainUser, $mainUserId, $connection);

        if ($request->hasSession()) {
            $request->session()->forget([
                'user',
                'business',
                'currency',
                'financial_year',
            ]);

            if ($accessoryUser) {
                $this->setAccessorySessionData($request, $accessoryUser);
            }
        }

        try {
            return $next($request);
        } finally {
            if ($mainUser) {
                Auth::setUser($mainUser);
            }
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
            $this->syncModuleState($main, $accessory, $businessId);

            $this->copyRowById($main, $accessory, 'users', $mainUser->id, [
                'business_id' => $businessId,
                'allow_login' => 1,
                'status' => 'active',
            ]);

            $this->ensureDefaultPosData($accessory, $businessId, $mainUser->id);

            $this->copyUserAuthorizationData($main, $accessory, $mainUser);
            $this->forgetPermissionCache();
        } finally {
            $accessory->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function useAccessoryAuthenticatedUser($mainUser, ?int $mainUserId, string $accessoryConnection)
    {
        if (! $mainUser || ! $mainUserId || ! Schema::connection($accessoryConnection)->hasTable('users')) {
            return null;
        }

        $userClass = get_class($mainUser);
        $accessoryUser = (new $userClass)
            ->setConnection($accessoryConnection)
            ->newQuery()
            ->find($mainUserId);

        if ($accessoryUser) {
            Auth::setUser($accessoryUser);
        }

        return $accessoryUser;
    }

    private function setAccessorySessionData($request, $user): void
    {
        $business = Business::find($user->business_id);

        if (! $business) {
            return;
        }

        $sessionData = [
            'id' => $user->id,
            'surname' => $user->surname,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'business_id' => $user->business_id,
            'language' => $user->language,
        ];

        $currency = $business->currency;
        $currencyData = [
            'id' => $currency->id,
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'thousand_separator' => $currency->thousand_separator,
            'decimal_separator' => $currency->decimal_separator,
        ];

        $request->session()->put('user', $sessionData);
        $request->session()->put('business', $business);
        $request->session()->put('currency', $currencyData);
        $request->session()->put('_session_database_connection', config('database.default'));
        $request->session()->put('financial_year', (new BusinessUtil)->getCurrentFinancialYear($business->id));
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
                    'created_by' => $userId,
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

        if ($table === 'users') {
            $data = $this->prepareUserDataForCopy($accessory, $data);
        }

        $accessory->table($table)->updateOrInsert(['id' => $data['id']], $data);
    }

    private function prepareUserDataForCopy($accessory, array $data): array
    {
        if (empty($data['username'])) {
            return $data;
        }

        $conflictId = $accessory->table('users')
            ->where('username', $data['username'])
            ->where('id', '!=', $data['id'])
            ->value('id');

        if (! $conflictId) {
            return $data;
        }

        if ((int) $conflictId === 1 && (int) $data['id'] !== 1) {
            $accessory->table('users')->where('id', 1)->update([
                'user_type' => 'user',
                'surname' => null,
                'first_name' => 'Accessory',
                'last_name' => 'Admin',
                'username' => 'accessory',
                'email' => 'accessory@example.com',
                'business_id' => 1,
                'allow_login' => 1,
                'status' => 'active',
                'updated_at' => now(),
            ]);

            return $data;
        }

        $baseUsername = substr($data['username'] . '_accessory_' . $data['id'], 0, 240);
        $username = $baseUsername;
        $counter = 1;

        while (
            $accessory->table('users')
                ->where('username', $username)
                ->where('id', '!=', $data['id'])
                ->exists()
        ) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }

        $data['username'] = $username;

        return $data;
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

        if (
            $this->hasTable($accessory, 'business') &&
            ! $accessory->table('business')->where('id', $businessId)->exists()
        ) {
            $accessory->table('business')->insert([
                'id' => $businessId,
                'name' => 'Accessory',
                'currency_id' => 2,
                'owner_id' => $userId,
                'fy_start_month' => 1,
                'time_zone' => config('app.timezone', 'Asia/Bangkok'),
                'accounting_method' => 'fifo',
                'sell_price_tax' => 'includes',
                'enable_product_expiry' => 0,
                'expiry_type' => 'add_expiry',
                'on_product_expiry' => 'keep_selling',
                'stop_selling_before' => 0,
                'weighing_scale_setting' => '{}',
                'enabled_modules' => json_encode(['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses', 'account']),
                'date_format' => 'm/d/Y',
                'time_format' => '24',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function syncModuleState($main, $accessory, int $businessId): void
    {
        if ($this->hasTable($main, 'business') && $this->hasTable($accessory, 'business')) {
            $enabledModules = $main->table('business')->where('id', $businessId)->value('enabled_modules');
            if (! empty($enabledModules)) {
                $accessory->table('business')->where('id', $businessId)->update([
                    'enabled_modules' => $enabledModules,
                    'updated_at' => now(),
                ]);
            }
        }

        if (! $this->hasTable($main, 'system') || ! $this->hasTable($accessory, 'system')) {
            return;
        }

        $versionKeys = [
            'accessory_version',
            'service_version',
            'smart_stock_inventory_version',
            'local_cashier_report_version',
            'notification_center_version',
            'warranty_card_print_version',
            'loan_management_version',
        ];

        $systemColumns = Schema::connection($accessory->getName())->getColumnListing('system');

        $main->table('system')
            ->whereIn('key', $versionKeys)
            ->get()
            ->each(function ($row) use ($accessory, $systemColumns) {
                $values = [
                    'value' => $row->value,
                ];
                if (in_array('created_at', $systemColumns, true)) {
                    $values['created_at'] = $row->created_at ?? now();
                }
                if (in_array('updated_at', $systemColumns, true)) {
                    $values['updated_at'] = now();
                }

                $accessory->table('system')->updateOrInsert(
                    ['key' => $row->key],
                    $values
                );
            });
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

        $accessoryRoleIds = [];
        foreach ($roleIds as $roleId) {
            $accessoryRoleId = $this->copyRoleForAccessory($main, $accessory, $roleId);
            if (! $accessoryRoleId) {
                continue;
            }

            $accessoryRoleIds[$roleId] = $accessoryRoleId;

            $accessory->table('model_has_roles')->insertOrIgnore([
                'role_id' => $accessoryRoleId,
                'model_type' => 'App\\User',
                'model_id' => $mainUser->id,
            ]);
        }

        $this->grantAllAllowedPermissionsToAccessoryAdmins($main, $accessory, $accessoryRoleIds);

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
            if (empty($accessoryRoleIds[$row->role_id])) {
                continue;
            }

            $this->copyRowById($main, $accessory, 'permissions', $row->permission_id);

            $accessory->table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $row->permission_id,
                'role_id' => $accessoryRoleIds[$row->role_id],
            ]);
        }
    }

    private function copyRoleForAccessory($main, $accessory, $roleId): ?int
    {
        if (! $this->hasTable($main, 'roles') || ! $this->hasTable($accessory, 'roles')) {
            return null;
        }

        $role = $main->table('roles')->where('id', $roleId)->first();
        if (! $role) {
            return null;
        }

        $roleData = (array) $role;
        $roleData['business_id'] = 1;

        if (! empty($roleData['name']) && str_contains($roleData['name'], '#')) {
            $roleData['name'] = preg_replace('/#\d+$/', '#1', $roleData['name']);
        }

        $existingRoleId = $accessory->table('roles')
            ->where('name', $roleData['name'])
            ->where('guard_name', $roleData['guard_name'] ?? 'web')
            ->where('business_id', 1)
            ->value('id');

        if ($existingRoleId) {
            $roleData['id'] = $existingRoleId;
        }

        $this->copyRow($accessory, 'roles', $roleData);

        return (int) $roleData['id'];
    }

    private function grantAllAllowedPermissionsToAccessoryAdmins($main, $accessory, array $accessoryRoleIds): void
    {
        if (empty($accessoryRoleIds)) {
            return;
        }

        $adminRoleIds = $accessory->table('roles')
            ->whereIn('id', array_values($accessoryRoleIds))
            ->where('name', 'like', 'Admin#%')
            ->pluck('id');

        if ($adminRoleIds->isEmpty()) {
            return;
        }

        $permissionIds = $this->filterAllowedPermissionIds(
            $main,
            $main->table('permissions')->pluck('id')->unique()->values()
        );

        foreach ($permissionIds as $permissionId) {
            $this->copyRowById($main, $accessory, 'permissions', $permissionId);

            foreach ($adminRoleIds as $adminRoleId) {
                $accessory->table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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
