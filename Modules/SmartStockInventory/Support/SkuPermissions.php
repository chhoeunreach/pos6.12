<?php

namespace Modules\SmartStockInventory\Support;

use Illuminate\Support\Facades\DB;

class SkuPermissions
{
    public const ACTIONS = [
        'view' => ['stock_inventory.view'],
        'history' => ['stock_inventory.view'],
        'export' => ['stock_inventory.view', 'stock_inventory.export'],
        'view_cost' => ['stock_inventory.view', 'view_purchase_price'],
        'update_lot' => ['stock_inventory.view', 'stock_inventory.update'],
        'update_cost' => ['stock_inventory.view', 'view_purchase_price', 'purchase.update'],
        'update_sale_price' => ['stock_inventory.view', 'sell.update'],
    ];

    public const ROUTES = [
        'ssi.sku.index' => 'view', 'ssi.sku.history' => 'history',
        'ssi.sku.export' => 'export', 'ssi.sku.products.export' => 'export',
        'ssi.sku.lot.update' => 'update_lot',
        'ssi.sku.cost.edit' => 'update_cost', 'ssi.sku.cost.update' => 'update_cost',
        'ssi.sku.sale_price.edit' => 'update_sale_price', 'ssi.sku.sale_price.update' => 'update_sale_price',
    ];

    public static function allows($user, string $action): bool
    {
        if (!$user || !isset(self::ACTIONS[$action])) return false;
        foreach (self::ACTIONS[$action] as $permission) {
            if (!$user->can($permission)) return false;
        }
        return true;
    }

    public static function authorize($user, string $action): void
    {
        abort_unless(self::allows($user, $action), 403, 'Unauthorized action.');
    }

    public static function locationIds($user, int $business): array
    {
        if (!$user || !method_exists($user, 'permitted_locations')) return [];
        $allowed = $user->permitted_locations($business);
        return DB::table('business_locations')->where('business_id', $business)
            ->when($allowed !== 'all', fn ($q) => $q->whereIn('id', array_map('intval', (array) $allowed)))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
