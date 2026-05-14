<?php

namespace Modules\LoanManagement\Helpers;

use Modules\LoanManagement\Services\LoanSidebarBadgeService;

class LoanMenuHelper
{
    public static function activeRoute(array $routeNames): bool
    {
        $current = request()->route() ? request()->route()->getName() : '';
        if (empty($current)) {
            return false;
        }

        foreach ($routeNames as $name) {
            if ($current === $name || str_starts_with($current, $name.'.')) {
                return true;
            }
        }

        return false;
    }

    public static function loanUserCan(string $permission): bool
    {
        try {
            return auth()->check() && auth()->user()->can($permission);
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function badgeCounts(): array
    {
        try {
            $service = app(LoanSidebarBadgeService::class);

            return [
                'overdue' => (int) $service->overdueCount(),
                'unread_chat' => (int) $service->unreadChatCount(),
                'pending_visits' => (int) $service->pendingVisitsCount(),
            ];
        } catch (\Throwable $e) {
            return [
                'overdue' => 0,
                'unread_chat' => 0,
                'pending_visits' => 0,
            ];
        }
    }
}
