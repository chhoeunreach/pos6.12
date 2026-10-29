<?php

namespace Modules\LoanManagement\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
            return false;
        }
    }

    public static function navigationSections(array $badges = []): array
    {
        $definition = require __DIR__.'/../Config/navigation.php';
        $sections = [];
        foreach ($definition['sections'] as $section) {
            $section['label'] = self::navigationLabel($section);
            $section['items'] = self::visibleNavigationItems($section['items']);
            foreach ($section['items'] as &$item) {
                $item['badge'] = $badges[$item['badge_key'] ?? ''] ?? 0;
                $item['tone'] = !empty($item['badge']) ? 'red' : 'slate';
            }
            unset($item);
            if ($section['items']) {
                $sections[] = $section;
            }
        }
        return $sections;
    }

    public static function workspaceTabs(string $workspace): array
    {
        $definition = require __DIR__.'/../Config/navigation.php';
        return self::visibleNavigationItems($definition['workspaces'][$workspace] ?? []);
    }

    private static function navigationLabel(array $item): string
    {
        return session('user.language', config('app.locale')) === 'km' ? ($item['km'] ?? $item['label']) : $item['label'];
    }

    private static function visibleNavigationItems(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (! self::loanUserCan($item['can'])) {
                return null;
            }
            if (isset($item['fallback_route']) && ! self::loanUserCan($item['primary_can'])) {
                $item['route'] = $item['fallback_route'];
            }
            if (! Route::has($item['route'])) {
                return null;
            }
            $item['label'] = self::navigationLabel($item);
            return $item;
        }, $items)));
    }

    public static function navigationItemActive(array $item): bool
    {
        $route = request()->route();
        if (! $route || ! Str::is($item['active_routes'] ?? [$item['route']], $route->getName() ?? '')) {
            return false;
        }
        if (isset($item['active_pages']) && $route->parameter('page') !== null) {
            return in_array($route->parameter('page'), $item['active_pages'], true);
        }
        foreach (($item['params'] ?? []) as $key => $value) {
            if (isset($item['active_pages']) && $key === 'page') {
                continue;
            }
            if ((string) $route->parameter($key, request()->query($key)) !== (string) $value) {
                return false;
            }
        }
        return true;
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
