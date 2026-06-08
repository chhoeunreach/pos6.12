<?php

namespace Modules\Accessory\Http\Middleware;

use App\Http\Controllers\SellPosController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class InjectAccessoryTopNav
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (strpos($content, 'data-accessory-top-nav="true"') !== false) {
            return $response;
        }

        $posUrl = action([SellPosController::class, 'create']);
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
        if (
            $request->ajax() ||
            ! auth()->check() ||
            ! Route::has('accessory.home') ||
            ! method_exists($response, 'getContent') ||
            ! method_exists($response, 'setContent')
        ) {
            return false;
        }

        $user = auth()->user();
        if (! $user->can('accessory.access') && ! $user->can('superadmin') && ! $user->can('product.view')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return $response->isSuccessful() && (empty($contentType) || str_contains($contentType, 'text/html'));
    }

    protected function topNavButton(): string
    {
        $url = route('accessory.home');

        return <<<HTML
                        <a href="{$url}" data-accessory-top-nav="true"
                            class="sm:tw-inline-flex tw-transition-all tw-duration-200 tw-gap-2 tw-bg-primary-800 hover:tw-bg-primary-700 tw-py-1.5 tw-px-3 tw-rounded-lg tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-ring-1 tw-ring-white/10 hover:tw-text-white tw-text-white">
                            <i class="fa fa-mobile tw-size-5 tw-hidden md:tw-block"></i>
                            Accessory
                        </a>

HTML;
    }
}
