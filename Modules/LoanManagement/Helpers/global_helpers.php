<?php

use Modules\LoanManagement\Helpers\LoanMenuHelper;

if (! function_exists('loan_user_can')) {
    function loan_user_can(string $permission): bool
    {
        return LoanMenuHelper::loanUserCan($permission);
    }
}

if (! function_exists('lm_label')) {
    function lm_label(?string $key = null, ?string $en = null, ?string $km = null): string
    {
        $loanLanguage = session('user.language', config('app.locale'));
        $isKhmer = request('lang') === 'km'
            || $loanLanguage === 'km'
            || request()->cookie('lm_lang') === 'km';

        if ($isKhmer && $km !== null && $km !== '') {
            return $km;
        }

        if ($en !== null && $en !== '') {
            return $en;
        }

        if (is_string($key) && $key !== '') {
            $translated = trans($key);
            if (is_string($translated) && $translated !== $key) {
                return $translated;
            }

            return $key;
        }

        return (string) $en;
    }
}
