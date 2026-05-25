<?php

namespace Modules\LoanManagement\Helpers;

use Illuminate\Support\Facades\Cache;
use Modules\LoanManagement\Services\LoanSidebarBadgeService;

class LoanMenuHelper
{
    protected static array $permissionCache = [];

    public static function activeRoute(array $routeNames, bool $matchChildren = true): bool
    {
        $current = request()->route() ? request()->route()->getName() : '';
        if (empty($current)) {
            return false;
        }

        foreach ($routeNames as $name) {
            if ($current === $name || ($matchChildren && str_starts_with($current, $name.'.'))) {
                return true;
            }
        }

        return false;
    }

    public static function loanUserCan(string $permission): bool
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return false;
            }

            $cacheKey = ($user->id ?? 'guest').'|'.$permission;
            if (array_key_exists($cacheKey, self::$permissionCache)) {
                return self::$permissionCache[$cacheKey];
            }

            $permissions = preg_split('/[|,]/', $permission) ?: [];

            return self::$permissionCache[$cacheKey] = collect($permissions)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->contains(fn ($item) => $user->can($item));
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function badgeCounts(): array
    {
        try {
            return Cache::remember('loan_management.sidebar_badges', now()->addSeconds(30), function () {
                $service = app(LoanSidebarBadgeService::class);

                return [
                    'overdue' => (int) $service->overdueCount(),
                    'unread_chat' => (int) $service->unreadChatCount(),
                    'pending_visits' => (int) $service->pendingVisitsCount(),
                ];
            });
        } catch (\Throwable $e) {
            return [
                'overdue' => 0,
                'unread_chat' => 0,
                'pending_visits' => 0,
            ];
        }
    }
}
