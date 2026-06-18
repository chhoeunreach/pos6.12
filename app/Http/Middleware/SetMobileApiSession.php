<?php

namespace App\Http\Middleware;

use App\Business;
use App\Utils\BusinessUtil;
use Closure;
use Illuminate\Support\Facades\Auth;

class SetMobileApiSession
{
    public function handle($request, Closure $next)
    {
        if (Auth::guard('api')->check()) {
            try {
                if (!$request->session()->has('user')) {
                    $this->setSessionData($request);
                }
            } catch (\RuntimeException $e) {
                // Session store not available (e.g., API tests)
            }
        }

        return $next($request);
    }

    private function setSessionData($request): void
    {
        $user = Auth::guard('api')->user();
        $business_util = new BusinessUtil;

        $session_data = [
            'id' => $user->id,
            'surname' => $user->surname,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'business_id' => $user->business_id,
            'language' => $user->language,
        ];

        $business = Business::findOrFail($user->business_id);
        $currency = $business->currency;

        $currency_data = [
            'id' => $currency->id,
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'thousand_separator' => $currency->thousand_separator,
            'decimal_separator' => $currency->decimal_separator,
            'currency_precision' => $business->currency_precision,
            'quantity_precision' => $business->quantity_precision,
        ];

        session([
            'user' => $session_data,
            'business' => $business,
            'currency' => $currency_data,
        ]);

        $financial_year = $business_util->getCurrentFinancialYear($business->id);
        $request->session()->put('financial_year', $financial_year);
    }
}
