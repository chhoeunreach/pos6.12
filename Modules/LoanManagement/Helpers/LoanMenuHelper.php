<?php

namespace Modules\LoanManagement\Helpers;

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
}

