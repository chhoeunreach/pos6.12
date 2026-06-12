<?php

namespace Modules\Service\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ServiceBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection('service');
        $now = now();

        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        $connection->table('users')->updateOrInsert(
            ['id' => 1],
            [
                'user_type' => 'user',
                'surname' => null,
                'first_name' => 'Service',
                'last_name' => 'Admin',
                'username' => 'service',
                'email' => 'service@example.com',
                'password' => Hash::make('123456'),
                'language' => 'en',
                'business_id' => 1,
                'allow_login' => 1,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('business')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Service',
                'currency_id' => 2,
                'start_date' => now()->toDateString(),
                'default_profit_percent' => 25,
                'owner_id' => 1,
                'time_zone' => config('app.timezone', 'Asia/Bangkok'),
                'fy_start_month' => 1,
                'accounting_method' => 'fifo',
                'sell_price_tax' => 'includes',
                'enable_product_expiry' => 0,
                'expiry_type' => 'add_expiry',
                'on_product_expiry' => 'keep_selling',
                'stop_selling_before' => 0,
                'enable_tooltip' => 1,
                'weighing_scale_setting' => '{}',
                'enabled_modules' => json_encode([
                    'purchases',
                    'add_sale',
                    'pos_sale',
                    'stock_transfers',
                    'stock_adjustment',
                    'expenses',
                    'account',
                ]),
                'date_format' => 'm/d/Y',
                'time_format' => '24',
                'currency_precision' => 2,
                'quantity_precision' => 2,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('invoice_schemes')->updateOrInsert(
            ['id' => 1],
            [
                'business_id' => 1,
                'name' => 'Default',
                'scheme_type' => 'blank',
                'prefix' => 'SV',
                'start_number' => 1,
                'invoice_count' => 0,
                'total_digits' => 4,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('invoice_layouts')->updateOrInsert(
            ['id' => 1],
            [
                'business_id' => 1,
                'name' => 'Default',
                'header_text' => null,
                'invoice_no_prefix' => 'Invoice No.',
                'invoice_heading' => 'Invoice',
                'sub_total_label' => 'Subtotal',
                'discount_label' => 'Discount',
                'tax_label' => 'Tax',
                'total_label' => 'Total',
                'show_business_name' => 1,
                'show_location_name' => 1,
                'show_landmark' => 1,
                'show_city' => 1,
                'show_state' => 1,
                'show_country' => 1,
                'show_zip_code' => 1,
                'show_mobile_number' => 1,
                'show_alternate_number' => 1,
                'show_email' => 1,
                'show_tax_1' => 1,
                'show_tax_2' => 1,
                'show_barcode' => 1,
                'show_payments' => 1,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('business_locations')->updateOrInsert(
            ['id' => 1],
            [
                'business_id' => 1,
                'location_id' => 'SV-1',
                'name' => 'Service',
                'landmark' => null,
                'country' => 'Cambodia',
                'state' => 'Phnom Penh',
                'city' => 'Phnom Penh',
                'zip_code' => '12000',
                'invoice_scheme_id' => 1,
                'invoice_layout_id' => 1,
                'receipt_printer_type' => 'browser',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('contacts')->updateOrInsert(
            ['id' => 1],
            [
                'business_id' => 1,
                'type' => 'customer',
                'name' => 'Walk-In Customer',
                'contact_id' => 'CO0001',
                'mobile' => '',
                'is_default' => 1,
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('cash_registers')->updateOrInsert(
            ['id' => 1],
            [
                'business_id' => 1,
                'location_id' => 1,
                'user_id' => 1,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $connection->table('roles')->updateOrInsert(
            ['name' => 'Admin#1', 'guard_name' => 'web'],
            [
                'business_id' => 1,
                'is_default' => 1,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $adminRoleId = $connection->table('roles')
            ->where('name', 'Admin#1')
            ->where('guard_name', 'web')
            ->value('id');

        if ($adminRoleId) {
            $connection->table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $adminRoleId,
                    'model_type' => 'App\\User',
                    'model_id' => 1,
                ],
                []
            );

            $this->syncAllPermissionsToService($connection);

            $allPermissionIds = $connection->table('permissions')->pluck('id');
            foreach ($allPermissionIds as $permId) {
                $connection->table('role_has_permissions')->updateOrInsert(
                    [
                        'permission_id' => $permId,
                        'role_id' => $adminRoleId,
                    ],
                    []
                );
            }
        }

        $connection->table('roles')->updateOrInsert(
            ['name' => 'Cashier#1', 'guard_name' => 'web'],
            [
                'business_id' => 1,
                'is_default' => 0,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $cashierRoleId = $connection->table('roles')
            ->where('name', 'Cashier#1')
            ->where('guard_name', 'web')
            ->value('id');

        if ($cashierRoleId) {
            $cashierPermissionIds = $connection->table('permissions')
                ->whereIn('name', [
                    'sell.view',
                    'sell.create',
                    'sell.update',
                    'sell.delete',
                    'access_all_locations',
                    'view_cash_register',
                    'close_cash_register',
                ])
                ->pluck('id');

            foreach ($cashierPermissionIds as $permissionId) {
                $connection->table('role_has_permissions')->updateOrInsert(
                    [
                        'permission_id' => $permissionId,
                        'role_id' => $cashierRoleId,
                    ],
                    []
                );
            }
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function syncAllPermissionsToService($service): void
    {
        $main = DB::connection(config('database.default', 'mysql'));

        if (
            ! Schema::connection($main->getName())->hasTable('permissions') ||
            ! Schema::connection($service->getName())->hasTable('permissions')
        ) {
            return;
        }

        $mainPermissions = $main->table('permissions')->get();

        foreach ($mainPermissions as $permission) {
            $data = (array) $permission;
            $columns = Schema::connection($service->getName())->getColumnListing('permissions');
            $data = array_intersect_key($data, array_flip($columns));

            $service->table('permissions')->updateOrInsert(
                ['name' => $data['name'], 'guard_name' => $data['guard_name'] ?? 'web'],
                $data
            );
        }
    }
}
