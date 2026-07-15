<?php

use Modules\LoanManagement\Helpers\LoanMenuHelper;

if (! function_exists('loan_user_can')) {
    function loan_user_can(string $permission): bool
    {
        return LoanMenuHelper::loanUserCan($permission);
    }
}
