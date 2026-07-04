<?php

namespace Modules\KneayerngLinks\Providers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class InjectKneayerngLinksTopNav
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (strpos($content, 'data-kneayerng-links-top-nav="true"') !== false) {
            return $response;
        }

        $posUrl = action("App\\Http\\Controllers\\SellPosController@create");
        $pattern = '#<a\s+href="' . preg_quote($posUrl, '#') . '"\s+class="[^"]*"\s*>#';

        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $response;
        }

        $content = substr_replace($content, $this->topNavButton(), $matches[0][1], 0);
        $response->setContent($content);

        return $response;
    }

    protected function shouldInject(Request $request, $response): bool
    {
        if ($this->isModuleRoute($request)) {
            return false;
        }

        if (
            $request->ajax() ||
            ! auth()->check() ||
            ! Route::has('kneayernglinks.home') ||
            ! method_exists($response, 'getContent') ||
            ! method_exists($response, 'setContent')
        ) {
            return false;
        }

        $user = auth()->user();
        if (! $user->can('kneayernglinks.access') && ! $user->can('superadmin') && ! $user->can('product.view')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return $response->isSuccessful() && (empty($contentType) || str_contains($contentType, 'text/html'));
    }

    protected function isModuleRoute(Request $request): bool
    {
        $routeName = optional($request->route())->getName();
        if (
            is_string($routeName) &&
            (str_starts_with($routeName, 'kneayernglinks.') || str_starts_with($routeName, 'modules/kneayernglinks'))
        ) {
            return true;
        }

        $modulePrefixes = array_filter([
            trim(config('kneayernglinks.route_prefix', 'kneayernglinks'), '/'),
            'modules/kneayernglinks',
            'modules/kneayernglinksV7',
        ]);

        foreach ($modulePrefixes as $prefix) {
            if ($request->is($prefix) || $request->is($prefix . '/*')) {
                return true;
            }
        }

        return false;
    }

    protected function topNavButton(): string
    {
        $url = route('kneayernglinks.home');

        return <<<HTML
                        <a href="{$url}" data-kneayerng-links-top-nav="true"
                            class="sm:tw-inline-flex tw-transition-all tw-duration-200 tw-gap-2 tw-bg-primary-800 hover:tw-bg-primary-700 tw-py-1.5 tw-px-3 tw-rounded-lg tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-ring-1 tw-ring-white/10 hover:tw-text-white tw-text-white">
                            <i class="fa fa-external-link tw-size-5 tw-hidden md:tw-block"></i>
                            Kneayerng Links
                        </a>

HTML;
    }
}