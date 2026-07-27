<?php

namespace Modules\Ecommerce\Http\Middleware;

use Closure;
use Modules\Ecommerce\Entities\EcomApiSetting;

class EcomApi
{
    public function handle($request, Closure $next)
    {
        $token = $request->header('API-TOKEN');
        $shop_domain = $this->normalizeDomain($request->header('SHOP-DOMAIN'));

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'API-TOKEN header is required.',
            ], 401);
        }

        $api_settings = EcomApiSetting::where('api_token', $token)
            ->where('is_active', 1)
            ->first();

        if (empty($api_settings)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API token.',
            ], 401);
        }

        if (! empty($shop_domain) && ! empty($api_settings->shop_domain) && $api_settings->shop_domain !== $shop_domain) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 401);
        }

        return $next($request);
    }

    protected function normalizeDomain($domain)
    {
        $domain = trim((string) $domain);

        if ($domain === '') {
            return null;
        }

        $parsed_url = parse_url(str_contains($domain, '://') ? $domain : 'http://'.$domain);
        $host = $parsed_url['host'] ?? null;
        if (! empty($host)) {
            $domain = $host;
            if (! empty($parsed_url['port'])) {
                $domain .= ':'.$parsed_url['port'];
            }
        }

        return strtolower(rtrim($domain, '/'));
    }
}
