<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            ['value' => 'stock_inventory.view', 'label' => 'Stock Inventory (view)', 'default' => false],
            ['value' => 'stock_inventory.create', 'label' => 'Stock Inventory (create)', 'default' => false],
            ['value' => 'stock_inventory.edit', 'label' => 'Stock Inventory (edit)', 'default' => false],
            ['value' => 'stock_inventory.delete', 'label' => 'Stock Inventory (delete)', 'default' => false],
            ['value' => 'stock_inventory.fix', 'label' => 'Stock Inventory (fix)', 'default' => false],
            ['value' => 'stock_inventory.verify', 'label' => 'Stock Inventory (verify)', 'default' => false],
            ['value' => 'stock_inventory.export', 'label' => 'Stock Inventory (export)', 'default' => false],
            ['value' => 'stock_inventory.settings', 'label' => 'Stock Inventory (settings)', 'default' => false],
            ['value' => 'stock_inventory.update', 'label' => 'Stock Inventory (update)', 'default' => false],
            ['value' => 'stock_inventory.rollback', 'label' => 'Stock Inventory (rollback)', 'default' => false],
            ['value' => 'stock_inventory.logs', 'label' => 'Stock Inventory (logs)', 'default' => false],
            ['value' => 'stock_inventory.approve', 'label' => 'Stock Inventory (approve)', 'default' => false],
            ['value' => 'stock_inventory.recount', 'label' => 'Stock Inventory (recount)', 'default' => false],
            ['value' => 'stock_inventory.mobile', 'label' => 'Stock Inventory (mobile)', 'default' => false],
            ['value' => 'stock_inventory.freeze', 'label' => 'Stock Inventory (freeze)', 'default' => false],
            ['value' => 'stock_inventory.report', 'label' => 'Stock Inventory (report)', 'default' => false],
            ['value' => 'stock_inventory.adjust', 'label' => 'Stock Inventory (adjust)', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! auth()->check() || ! auth()->user()->can('stock_inventory.view')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $root = $menu->dropdown(
                'Stock Inventory',
                function ($sub) {
                    $sub->url(route('ssi.dashboard'), 'Dashboard', ['icon' => 'fa fa-dashboard']);
                    $sub->url(route('ssi.count.index'), 'Inventory Count', ['icon' => 'fa fa-list']);
                    $sub->url(route('ssi.count.enterprise'), 'Enterprise Count', ['icon' => 'fa fa-tasks']);
                    $sub->url(route('ssi.verification.index'), 'Verification Report', ['icon' => 'fa fa-check-square-o']);
                    $sub->url(route('ssi.mismatch.index'), 'Mismatch Detector', ['icon' => 'fa fa-exclamation-triangle']);
                    $sub->url(route('ssi.movement.index'), 'Movement History', ['icon' => 'fa fa-exchange']);
                    $sub->url(route('ssi.imei.index'), 'IMEI Management', ['icon' => 'fa fa-mobile']);
                    $sub->url(route('ssi.lot.index'), 'Lot Management', ['icon' => 'fa fa-tags']);
                    if (auth()->user()->can('stock_inventory.logs')) {
                        $sub->url(route('ssi.fix_logs'), 'Fix Logs', ['icon' => 'fa fa-history']);
                    }
                    if (auth()->user()->can('stock_inventory.report')) {
                        $sub->url(route('ssi.count.reports'), 'Inventory Reports', ['icon' => 'fa fa-bar-chart']);
                    }
                    if (auth()->user()->can('stock_inventory.settings')) {
                        $sub->url(route('ssi.settings.index'), 'Settings', ['icon' => 'fa fa-cogs']);
                    }
                },
                ['icon' => 'fa fa-cubes', 'active' => request()->segment(1) === 'smart-stock-inventory']
            );

            $root->order(35);
        });
    }
}
