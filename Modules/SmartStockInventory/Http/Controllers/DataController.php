<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    private function isReachAdmin(): bool
    {
        if (! auth()->check()) {
            return false;
        }
        $user = auth()->user();
        if ($this->hasSuperAdminAccess($user)) {
            return true;
        }

        $reachUsername = strtolower(trim((string) config('smartstockinventory.reach_username', 'Reach')));
        $username = strtolower(trim((string) ($user->username ?? '')));
        if ($reachUsername !== '' && $username === $reachUsername) {
            return true;
        }
        $roles = (array) config('smartstockinventory.admin_roles', []);
        if (method_exists($user, 'hasAnyRole') && ! empty($roles)) {
            try {
                if ($user->hasAnyRole($roles)) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        return false;
    }

    private function hasSuperAdminAccess($user): bool
    {
        if (! config('smartstockinventory.enable_super_admin_override', true)) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect($user->roles ?? []);
        foreach ($roles as $role) {
            $roleName = is_string($role) ? $role : (string) ($role->name ?? '');
            $baseRoleName = preg_replace('/#\d+$/', '', trim($roleName));
            if (strcasecmp($baseRoleName, 'Super Admin') === 0) {
                return true;
            }
        }

        return false;
    }

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
            ['value' => 'ssi.audit.view', 'label' => 'Stock Audit (view)', 'default' => false],
            ['value' => 'ssi.audit.create', 'label' => 'Stock Audit (create)', 'default' => false],
            ['value' => 'ssi.audit.update', 'label' => 'Stock Audit (update/start)', 'default' => false],
            ['value' => 'ssi.audit.scan', 'label' => 'Stock Audit (scanner)', 'default' => false],
            ['value' => 'ssi.audit.verify', 'label' => 'Stock Audit (verify)', 'default' => false],
            ['value' => 'ssi.audit.investigate', 'label' => 'Stock Audit (investigate)', 'default' => false],
            ['value' => 'ssi.audit.approve', 'label' => 'Stock Audit (approve)', 'default' => false],
            ['value' => 'ssi.audit.adjust', 'label' => 'Stock Audit (adjust)', 'default' => false],
            ['value' => 'ssi.audit.report', 'label' => 'Stock Audit (reports)', 'default' => false],
            ['value' => 'ssi.audit.settings', 'label' => 'Stock Audit (settings)', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        $user = auth()->user();
        $hasSuperAdminAccess = $this->hasSuperAdminAccess($user);
        if (! $this->isReachAdmin() && ! $hasSuperAdminAccess && ! $this->canAnySmartStockPermission($user)) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $hasSuperAdminAccess = $this->hasSuperAdminAccess(auth()->user());
            $user = auth()->user();
            $canStockView = $hasSuperAdminAccess || $user->can('stock_inventory.view');
            $root = $menu->dropdown(
                'Stock Inventory',
                function ($sub) use ($hasSuperAdminAccess, $user, $canStockView) {
                    if ($canStockView) {
                        $sub->url(ssi_route('ssi.dashboard'), 'Dashboard', ['icon' => 'fa fa-dashboard']);
                        $sub->url(ssi_route('ssi.count.index'), 'Inventory Count', ['icon' => 'fa fa-list']);
                        $sub->url(ssi_route('ssi.count.enterprise'), 'Enterprise Count', ['icon' => 'fa fa-tasks']);
                        $sub->url(ssi_route('ssi.mismatch.index'), 'Mismatch Detector', ['icon' => 'fa fa-exclamation-triangle']);
                        $sub->url(ssi_route('ssi.movement.index'), 'Movement History', ['icon' => 'fa fa-exchange']);
                        $sub->url(ssi_route('ssi.imei.index'), 'IMEI Management', ['icon' => 'fa fa-mobile']);
                        $sub->url(ssi_route('ssi.lot.index'), 'Lot Management', ['icon' => 'fa fa-tags']);
                    }
                    if ($hasSuperAdminAccess || $user->can('ssi.audit.view')) {
                        $sub->url(ssi_route('ssi.enterprise.audit.index'), 'Enterprise Audit', ['icon' => 'fa fa-clipboard']);
                    }
                    if ($hasSuperAdminAccess || $user->can('stock_inventory.verify')) {
                        $sub->url(ssi_route('ssi.verification.index'), 'Verification Report', ['icon' => 'fa fa-check-square-o']);
                    }
                    if ($hasSuperAdminAccess || $user->can('stock_inventory.logs')) {
                        $sub->url(ssi_route('ssi.fix_logs'), 'Fix Logs', ['icon' => 'fa fa-history']);
                    }
                    if ($hasSuperAdminAccess || $user->can('stock_inventory.report')) {
                        $sub->url(ssi_route('ssi.count.reports'), 'Inventory Reports', ['icon' => 'fa fa-bar-chart']);
                    }
                    if ($hasSuperAdminAccess || $user->can('ssi.audit.report')) {
                        $sub->url(ssi_route('ssi.enterprise.report.index'), 'Enterprise Reports', ['icon' => 'fa fa-pie-chart']);
                    }
                    if ($hasSuperAdminAccess || $user->can('stock_report.view')) {
                        $sub->url(ssi_route('ssi.report.stock_sell'), 'Stock Sell Report', ['icon' => 'fa fa-file-text-o']);
                        $sub->url(ssi_route('ssi.report.stock_purchase'), 'Stock Purchase Report', ['icon' => 'fa fa-file-text-o']);
                        $sub->url(ssi_route('ssi.report.stock_transfer'), 'Stock Transfer Report', ['icon' => 'fa fa-file-text-o']);
                    }
                    if ($hasSuperAdminAccess || $user->can('stock_inventory.settings')) {
                        $sub->url(ssi_route('ssi.settings.index'), 'Settings', ['icon' => 'fa fa-cogs']);
                    }
                },
                ['icon' => 'fa fa-cubes', 'active' => request()->is('smart-stock-inventory/*') || request()->is(trim(config('accessory.route_prefix', 'accessory'), '/').'/smart-stock-inventory/*')]
            );

            $root->order(35);
        });
    }

    private function canAnySmartStockPermission($user): bool
    {
        foreach ($this->user_permissions() as $permission) {
            if ($user->can($permission['value'])) {
                return true;
            }
        }

        return $user->can('stock_report.view');
    }
}
