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

        $serviceLocations = [
            ['id' => 1, 'location_id' => 'SV-1', 'name' => 'Service', 'sell_type' => ''],
            ['id' => 2, 'location_id' => 'BL0060', 'name' => 'កម្ពុជាក្រោម-ជួសជុល', 'sell_type' => 'ជួសជុល'],
            ['id' => 3, 'location_id' => 'BL0061', 'name' => 'កម្ពុជាក្រោម-អ៊ុត', 'sell_type' => 'អ៊ុត'],
            ['id' => 4, 'location_id' => 'BL0062', 'name' => 'វីអាយភី-ជួសជុល', 'sell_type' => 'ជួសជុល'],
            ['id' => 5, 'location_id' => 'BL0063', 'name' => 'វីអាយភី-អ៊ុត', 'sell_type' => 'អ៊ុត'],
            ['id' => 6, 'location_id' => 'BL0064', 'name' => 'វីអាយភី-ការហ្វេរ', 'sell_type' => 'ការហ្វេរ'],
            ['id' => 7, 'location_id' => 'BL0065', 'name' => 'សាខាកាប់គោ-ជួសជុល', 'sell_type' => 'ជួសជុល'],
            ['id' => 8, 'location_id' => 'BL0066', 'name' => 'សាខាកាប់គោ-អ៊ុត', 'sell_type' => 'អ៊ុត'],
            ['id' => 9, 'location_id' => 'BL0067', 'name' => 'សាខាអ៊ីអន-ជួសជុល', 'sell_type' => 'ជួសជុល'],
            ['id' => 10, 'location_id' => 'BL0068', 'name' => 'សាខាអ៊ីអន-ការហ្វេរ', 'sell_type' => 'ការហ្វេរ'],
            ['id' => 11, 'location_id' => 'BL0069', 'name' => 'ស្តុកធំ-ជួសជុល', 'sell_type' => 'ជួសជុល'],
            ['id' => 12, 'location_id' => 'BL0070', 'name' => 'ស្តុកធំ-អ៊ុត', 'sell_type' => 'អ៊ុត'],
        ];

        foreach ($serviceLocations as $loc) {
            $connection->table('business_locations')->updateOrInsert(
                ['id' => $loc['id']],
                [
                    'business_id' => 1,
                    'location_id' => $loc['location_id'],
                    'name' => $loc['name'],
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
        }

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
        $mainConnection = env('DB_CONNECTION', 'mysql');

        if ($mainConnection === $service->getName()) {
            return;
        }

        $main = DB::connection($mainConnection);

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
            unset($data['id']);

            $service->table('permissions')->updateOrInsert(
                ['name' => $data['name'], 'guard_name' => $data['guard_name'] ?? 'web'],
                $data
            );
        }
    }
}
