@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;
    use Illuminate\Support\Str;

    $badgeCounts = $loanBadgeCounts ?? LoanMenuHelper::badgeCounts();
    $businessSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::logoUrl();
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $sidebarUrl = function (string $route, array $params = []) {
        return Route::has($route) ? route($route, $params) : '#';
    };

    $lmRouteParamMatches = fn (array $item): bool => LoanMenuHelper::navigationItemActive($item);
    $menuSections = LoanMenuHelper::navigationSections($badgeCounts);
@endphp

<aside class="lm-sidebar" id="loanManagementSidebar">
    <div class="lm-brand">
        <div class="lm-brand-icon">
            @if($businessLogoUrl)
                <img src="{{ $businessLogoUrl }}" alt="{{ $businessSettings['business_name'] }}">
            @else
                <i class="fa fa-folder-open"></i>
            @endif
        </div>
        <div class="lm-brand-text">
            <span>{{ $businessSettings['business_name'] }}</span>
            <small>{{ $businessSettings['system_name'] }}</small>
        </div>
        <button type="button" class="lm-sidebar-collapse" id="loanSidebarCollapse" aria-label="Toggle sidebar">
            <i class="fa fa-angle-double-left"></i>
        </button>
        <button type="button" class="lm-sidebar-close d-lg-none" id="loanSidebarClose" aria-label="Close sidebar">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <div class="lm-sidebar-search">
        <i class="fa fa-search"></i>
        <input type="search" id="lmSidebarSearch" placeholder="{{ $lmText('Search menu...', 'ស្វែងរកម៉ឺនុយ...') }}" autocomplete="off">
        <span>{{ $lmIsKhmer ? '⌘ គ' : '⌘ K' }}</span>
    </div>

    <nav class="lm-menu" aria-label="Installment Management">
        @foreach($menuSections as $section)
            @php
                $visibleItems = collect($section['items'])->filter(function ($item) {
                    $children = $item['children'] ?? [];
                    if (empty($children)) {
                        return \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan($item['can'] ?? 'loan_management.view');
                    }

                    return collect($children)->contains(function ($child) {
                        return \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan($child['can'] ?? 'loan_management.view');
                    });
                })->values();
            @endphp

            @continue($visibleItems->isEmpty())

            <div class="lm-menu-section" data-section="{{ $section['key'] }}">
                @if(!empty($section['label']))
                    <button type="button" class="lm-menu-section-title" aria-expanded="true" aria-label="{{ $section['label'] }}">
                        <span>{{ $section['label'] }}</span><i class="fa fa-angle-down" aria-hidden="true"></i>
                    </button>
                @endif

                @foreach($visibleItems as $item)
                    @php
                        $children = collect($item['children'] ?? [])->filter(fn ($child) => \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan($child['can'] ?? 'loan_management.view'))->values();
                        $isActive = $children->isEmpty()
                            ? (empty($item['suppress_active']) && $lmRouteParamMatches($item))
                            : $children->contains(fn ($child) => $lmRouteParamMatches($child));
                        $tone = $item['tone'] ?? 'blue';
                    @endphp

                    @if($children->isEmpty())
                        <a href="{{ $sidebarUrl($item['route'], $item['params'] ?? []) }}"
                           class="lm-menu-link {{ $isActive ? 'active' : '' }} tone-{{ $tone }}"
                           data-lm-menu-text="{{ Str::lower($item['label']) }}"
                           title="{{ $item['label'] }}"
                           @if($isActive) aria-current="page" @endif
                           @if(!empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif>
                            <i class="{{ $item['icon'] }} lm-menu-icon"></i>
                            <span class="lm-menu-label">{{ $item['label'] }}</span>
                            @if(!empty($item['badge']))
                                <span class="lm-badge">{{ number_format((int) $item['badge']) }}</span>
                            @endif
                            @if(!empty($item['meta']))
                                <span class="lm-menu-meta">{{ $item['meta'] }}</span>
                            @endif
                        </a>
                    @else
                        <div class="lm-menu-group {{ $isActive ? 'open' : '' }}" data-lm-menu-text="{{ Str::lower($item['label'].' '.$children->pluck('label')->implode(' ')) }}">
                            <button class="lm-menu-link lm-menu-toggle {{ $isActive ? 'active' : '' }} tone-{{ $tone }}" type="button" title="{{ $item['label'] }}">
                                <i class="{{ $item['icon'] }} lm-menu-icon"></i>
                                <span class="lm-menu-label">{{ $item['label'] }}</span>
                                <i class="fa fa-angle-down lm-angle"></i>
                            </button>

                            <div class="lm-submenu" style="{{ $isActive ? 'display:block;' : '' }}">
                                @foreach($children as $child)
                                    @php $childActive = $lmRouteParamMatches($child); @endphp
                                    <a href="{{ $sidebarUrl($child['route'], $child['params'] ?? []) }}"
                                       class="lm-submenu-link {{ $childActive ? 'active' : '' }} {{ !empty($child['meta']) ? 'has-meta' : '' }}"
                                       title="{{ $child['label'] }}"
                                       @if(!empty($child['target'])) target="{{ $child['target'] }}" rel="noopener" @endif>
                                        <span class="lm-menu-label">{{ $child['label'] }}</span>
                                        @if(!empty($child['badge']))
                                            <span class="lm-badge">{{ number_format((int) $child['badge']) }}</span>
                                        @endif
                                        @if(!empty($child['meta']))
                                            <span class="lm-menu-meta">{{ $child['meta'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>
