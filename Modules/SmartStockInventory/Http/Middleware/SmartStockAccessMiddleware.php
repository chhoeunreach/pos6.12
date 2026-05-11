<?php

namespace Modules\SmartStockInventory\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\SmartInventoryAssignment;
use Modules\SmartStockInventory\Models\SmartStockInventorySession;

class SmartStockAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Unauthorized action.');
        }

        $routeName = (string) optional($request->route())->getName();
        $isReachAdmin = $this->isReachAdmin($user);
        $staffAllowedRoutes = (array) config('smartstockinventory.staff_allowed_routes', []);

        if (! $isReachAdmin) {
            if (! in_array($routeName, $staffAllowedRoutes, true)) {
                abort(403, 'Unauthorized action.');
            }

            if (! $this->isAssignedCounter($request, (int) $user->id)) {
                abort(403, 'You are not assigned for this inventory counting session.');
            }
        }

        $requiredPermission = $this->requiredPermissionForRoute($routeName);
        if ($requiredPermission !== null && ! $user->can($requiredPermission)) {
            if (! ($isReachAdmin && (bool) config('smartstockinventory.enable_super_admin_override', true))) {
                abort(403, 'Unauthorized action.');
            }

            $this->requireOverrideReasonAndAudit($request, $requiredPermission);
        }

        return $next($request);
    }

    private function isReachAdmin($user): bool
    {
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

    private function isAssignedCounter(Request $request, int $userId): bool
    {
        $sessionParam = $request->route('session');
        $sessionId = is_object($sessionParam) ? (int) ($sessionParam->id ?? 0) : (int) $sessionParam;
        if ($sessionId <= 0) {
            return false;
        }

        $businessId = (int) session('user.business_id');
        $session = SmartStockInventorySession::where('business_id', $businessId)->find($sessionId);
        if (! $session) {
            return false;
        }

        return SmartInventoryAssignment::where('business_id', $businessId)
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function requiredPermissionForRoute(string $routeName): ?string
    {
        $map = [
            'ssi.dashboard' => 'stock_inventory.view',
            'ssi.dashboard.detail' => 'stock_inventory.view',
            'ssi.dashboard.export' => 'stock_inventory.export',
            'ssi.dashboard.print' => 'stock_inventory.view',
            'ssi.dashboard.refresh' => 'stock_inventory.view',
            'ssi.count.index' => 'stock_inventory.view',
            'ssi.count.enterprise' => 'stock_inventory.view',
            'ssi.count.enterprise.session' => 'stock_inventory.create',
            'ssi.count.enterprise.assign' => 'stock_inventory.update',
            'ssi.count.enterprise.line' => 'stock_inventory.update',
            'ssi.count.enterprise.verify' => 'stock_inventory.verify',
            'ssi.count.enterprise.approve' => 'stock_inventory.approve',
            'ssi.count.enterprise.freeze' => 'stock_inventory.freeze',
            'ssi.count.enterprise.dashboard' => 'stock_inventory.view',
            'ssi.count.enterprise.adjustment_preview' => 'stock_inventory.adjust',
            'ssi.count.enterprise.mobile' => 'stock_inventory.mobile',
            'ssi.count.reports' => 'stock_inventory.report',
            'ssi.count.store' => 'stock_inventory.create',
            'ssi.count.session.update' => 'stock_inventory.update',
            'ssi.count.line.update' => 'stock_inventory.update',
            'ssi.count.session.delete' => 'stock_inventory.delete',
            'ssi.count.line.delete' => 'stock_inventory.delete',
            'ssi.count.imported.delete' => 'stock_inventory.delete',
            'ssi.count.complete' => 'stock_inventory.edit',
            'ssi.count.export' => 'stock_inventory.export',
            'ssi.count.print' => 'stock_inventory.view',
            'ssi.count.import' => 'stock_inventory.create',
            'ssi.verification.index' => 'stock_inventory.verify',
            'ssi.verification.approve' => 'stock_inventory.approve',
            'ssi.verification.reject' => 'stock_inventory.verify',
            'ssi.verification.recount' => 'stock_inventory.recount',
            'ssi.verification.export' => 'stock_inventory.export',
            'ssi.verification.print' => 'stock_inventory.verify',
            'ssi.mismatch.index' => 'stock_inventory.view',
            'ssi.mismatch.preview_fix' => 'stock_inventory.fix',
            'ssi.mismatch.fix_auto' => 'stock_inventory.fix',
            'ssi.mismatch.rollback' => 'stock_inventory.rollback',
            'ssi.mismatch.logs' => 'stock_inventory.logs',
            'ssi.fix_logs' => 'stock_inventory.logs',
            'ssi.fix_logs.delete' => 'stock_inventory.delete',
            'ssi.movement.index' => 'stock_inventory.view',
            'ssi.movement.export' => 'stock_inventory.export',
            'ssi.movement.print' => 'stock_inventory.view',
            'ssi.movement.edit_modal' => 'stock_inventory.update',
            'ssi.movement.update_modal' => 'stock_inventory.update',
            'ssi.movement.void' => 'stock_inventory.update',
            'ssi.movement.restore' => 'stock_inventory.update',
            'ssi.imei.index' => 'stock_inventory.view',
            'ssi.imei.export' => 'stock_inventory.export',
            'ssi.imei.history' => 'stock_inventory.view',
            'ssi.imei.update' => 'stock_inventory.update',
            'ssi.lot.index' => 'stock_inventory.view',
            'ssi.lot.export' => 'stock_inventory.export',
            'ssi.lot.history' => 'stock_inventory.view',
            'ssi.lot.update' => 'stock_inventory.update',
            'ssi.settings.index' => 'stock_inventory.settings',
            'ssi.settings.update' => 'stock_inventory.settings',
            'ssi.settings.test_telegram' => 'stock_inventory.settings',
            'ssi.settings.reset_default' => 'stock_inventory.settings',
            'ssi.settings.export' => 'stock_inventory.settings',
        ];

        return $map[$routeName] ?? null;
    }

    private function requireOverrideReasonAndAudit(Request $request, string $permission): void
    {
        $overrideReason = trim((string) ($request->input('override_reason') ?? $request->input('reason') ?? ''));
        if ($overrideReason === '') {
            abort(422, 'Override reason is required.');
        }

        DB::table('smart_stock_action_logs')->insert([
            'user_id' => auth()->id(),
            'user_name' => trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? ''))) ?: (string) (auth()->user()->username ?? ''),
            'business_id' => (int) session('user.business_id'),
            'module_name' => 'SmartStockInventory',
            'table_name' => 'route_guard',
            'record_id' => null,
            'location_id' => null,
            'action_type' => 'super_admin_override',
            'reference_type' => $request->route()?->getName(),
            'reference_id' => null,
            'old_data' => null,
            'new_data' => json_encode(['required_permission' => $permission, 'path' => $request->path()]),
            'reason' => $overrideReason,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

