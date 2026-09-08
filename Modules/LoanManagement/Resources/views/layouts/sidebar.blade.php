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

    $lmRouteParamMatches = function (array $item): bool {
        $route = request()->route();
        if (! $route || empty($item['route']) || $route->getName() !== $item['route']) {
            return false;
        }

        foreach (($item['params'] ?? []) as $key => $value) {
            $current = $route->parameter($key, request()->query($key));
            if ((string) $current !== (string) $value) {
                return false;
            }
        }

        return true;
    };

    $menuSections = [
        [
            'items' => [
                ['label' => $lmText('Dashboard', 'ផ្ទាំងគ្រប់គ្រង'), 'icon' => 'fa fa-home', 'route' => 'loan-management.dashboard', 'can' => 'loan_management.dashboard.view|loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('Dashboard Reports', 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង'), 'icon' => 'fa fa-line-chart', 'route' => 'loan-management.reports.dashboard', 'can' => 'loan_management.reports.view|loan_management.view', 'tone' => 'green'],
            ],
        ],
        [
            'label' => $lmText('Installment Management', 'ការគ្រប់គ្រងកម្ចី'),
            'items' => [
                ['label' => $lmText('Installment Applications', 'ពាក្យស្នើសុំកម្ចី'), 'icon' => 'fa fa-file-text-o', 'tone' => 'slate', 'children' => [
                    ['label' => $lmText('New Installment', 'កម្ចីថ្មី'), 'route' => 'loan-management.loans.create', 'can' => 'loan_management.loans.create|loan_management.create'],
                    ['label' => $lmText('All Installments', 'បញ្ជីកម្ចីទាំងអស់'), 'route' => 'loan-management.loans', 'can' => 'loan_management.loans.view|loan_management.view'],
                    ['label' => $lmText('Installment Calculator', 'ម៉ាស៊ីនគណនាកម្ចី'), 'route' => 'loan-management.loans.calculator', 'can' => 'loan_management.loans.create|loan_management.create'],
                ]],
                ['label' => $lmText('Installment Operations', 'ប្រតិបត្តិការកម្ចី'), 'icon' => 'fa fa-database', 'tone' => 'slate', 'children' => [
                    ['label' => $lmText('Due Today', 'ត្រូវបង់ថ្ងៃនេះ'), 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Partial Payments', 'ការបង់ប្រាក់មិនពេញ'), 'route' => 'loan-management.operations.page', 'params' => ['page' => 'partial-payments'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Closed Accounts', 'គណនីបិទរួច'), 'route' => 'loan-management.operations.page', 'params' => ['page' => 'closed-accounts'], 'can' => 'loan_management.view'],
                ]],
                ['label' => $lmText('Installment Schedule', 'កាលវិភាគកម្ចី'), 'icon' => 'fa fa-calendar', 'route' => 'loan-management.schedules.index', 'can' => 'loan_management.view', 'tone' => 'slate'],
                ['label' => $lmText('Installment Calendar', 'ប្រតិទិនបង់ប្រាក់'), 'icon' => 'fa fa-calendar-check-o', 'route' => 'loan-management.schedules.calendar', 'can' => 'loan_management.view', 'tone' => 'teal'],
                ['label' => $lmText('Installment Products', 'ទំនិញបង់រំលស់'), 'icon' => 'fa fa-cubes', 'route' => 'loan-management.products.index', 'can' => 'loan_management.view', 'tone' => 'blue'],
            ],
        ],
        [
            'label' => $lmText('Customers', 'អតិថិជន'),
            'items' => [
                ['label' => $lmText('Customers', 'អតិថិជន'), 'icon' => 'fa fa-users', 'route' => 'loan-management.customers', 'can' => 'loan_management.view', 'tone' => 'green'],
                ['label' => $lmText('Guarantors', 'អ្នកធានា'), 'icon' => 'fa fa-shield', 'route' => 'loan-management.guarantors.index', 'can' => 'loan_management.guarantors.view|loan_management.view', 'tone' => 'green'],
                ['label' => $lmText('Blacklist', 'បញ្ជីខ្មៅ'), 'icon' => 'fa fa-user-times', 'route' => 'loan-management.blacklist.index', 'can' => 'loan_management.blacklist.view|loan_management.view', 'tone' => 'red'],
            ],
        ],
        [
            'label' => $lmText('Collections', 'ការប្រមូលប្រាក់'),
            'items' => [
                ['label' => $lmText("Today's Collection", 'ប្រមូលប្រាក់ថ្ងៃនេះ'), 'icon' => 'fa fa-usd', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'today-collection'], 'can' => 'loan_management.view', 'tone' => 'violet'],
                ['label' => $lmText('Payments', 'ការបង់ប្រាក់'), 'icon' => 'fa fa-money', 'route' => 'loan-management.payments.index', 'can' => 'loan_management.view', 'tone' => 'violet'],
                ['label' => $lmText('Overdue', 'ហួសកំណត់'), 'icon' => 'fa fa-exclamation-triangle', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'overdue-accounts'], 'can' => 'loan_management.view', 'badge' => $badgeCounts['overdue'] ?? 0, 'tone' => 'red'],
                ['label' => $lmText('Collection Cases', 'ករណីប្រមូលប្រាក់'), 'icon' => 'fa fa-shield', 'tone' => 'violet', 'children' => [
                    ['label' => $lmText('Promise To Pay', 'សន្យាបង់ប្រាក់'), 'route' => 'loan-management.collection.page', 'params' => ['page' => 'promise-to-pay'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Broken Promise', 'ខកខានសន្យា'), 'route' => 'loan-management.collection.page', 'params' => ['page' => 'broken-promise'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Delinquent Accounts', 'គណនីយឺតយ៉ាវ'), 'route' => 'loan-management.collection.page', 'params' => ['page' => 'delinquent-accounts'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Recovery Management', 'គ្រប់គ្រងការស្ដារបំណុល'), 'route' => 'loan-management.collection.page', 'params' => ['page' => 'recovery-management'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Debt Collection', 'ប្រមូលបំណុល'), 'route' => 'loan-management.collection.page', 'params' => ['page' => 'debt-collection'], 'can' => 'loan_management.view'],
                ]],
                ['label' => $lmText('Field Visits', 'ចុះជួបអតិថិជន'), 'icon' => 'fa fa-map-marker', 'route' => 'loan-management.collection-visits.index', 'can' => 'loan_management.view', 'badge' => $badgeCounts['pending_visits'] ?? 0, 'tone' => 'violet'],
            ],
        ],
        [
            'label' => $lmText('Finance', 'ហិរញ្ញវត្ថុ'),
            'items' => [
                ['label' => $lmText('Cash & Bank', 'សាច់ប្រាក់ និងធនាគារ'), 'icon' => 'fa fa-bank', 'route' => 'loan-management.payments.index', 'params' => ['payment_type' => 'loan'], 'can' => 'loan_management.view', 'tone' => 'cyan'],
                ['label' => $lmText('Income', 'ចំណូល'), 'icon' => 'fa fa-level-up', 'route' => 'loan-management.payments.index', 'params' => ['payment_type' => 'monthly'], 'can' => 'loan_management.view', 'tone' => 'cyan'],
            ],
        ],
        [
            'label' => $lmText('Reports', 'របាយការណ៍'),
            'items' => [
                ['label' => $lmText('Installment Reports', 'របាយការណ៍កម្ចី'), 'icon' => 'fa fa-pie-chart', 'tone' => 'blue', 'children' => [
                    ['label' => $lmText('Installment Reports', 'របាយការណ៍កម្ចី'), 'route' => 'loan-management.reports.index', 'can' => 'loan_management.reports.view|loan_management.view'],
                    ['label' => $lmText('Daily Installment Summary', 'សង្ខេបកម្ចីប្រចាំថ្ងៃ'), 'route' => 'loan-management.reports.daily-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
                    ['label' => $lmText('Monthly Installment Summary', 'សង្ខេបកម្ចីប្រចាំខែ'), 'route' => 'loan-management.reports.monthly-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
                    ['label' => $lmText('Yearly Installment Summary', 'សង្ខេបកម្ចីប្រចាំឆ្នាំ'), 'route' => 'loan-management.reports.yearly-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
                ]],
                ['label' => $lmText('Collection Reports', 'របាយការណ៍ប្រមូលប្រាក់'), 'icon' => 'fa fa-bar-chart', 'route' => 'loan-management.collection.reports', 'can' => 'loan_management.reports.view|loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('Payment Channels & Methods', 'របាយការណ៍បណ្តាញបង់ប្រាក់'), 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.reports.payments', 'can' => 'loan_management.reports.view|loan_management.view', 'tone' => 'blue'],
            ],
        ],
        [
            'label' => $lmText('Administration', 'រដ្ឋបាល'),
            'items' => [
                ['label' => $lmText('Users & Roles', 'អ្នកប្រើប្រាស់ និងតួនាទី'), 'icon' => 'fa fa-user-o', 'tone' => 'blue', 'children' => [
                    ['label' => $lmText('Manage Users', 'គ្រប់គ្រងអ្នកប្រើប្រាស់'), 'route' => 'loan-management.users.index', 'can' => 'user.view|user.create'],
                    ['label' => $lmText('Roles', 'តួនាទី'), 'route' => 'loan-management.roles.index', 'can' => 'roles.view|roles.create'],
                ]],
                ['label' => $lmText('Branches', 'សាខា'), 'icon' => 'fa fa-building-o', 'route' => 'loan-management.locations.index', 'can' => 'loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('Audit Logs', 'កំណត់ហេតុសវនកម្ម'), 'icon' => 'fa fa-check-circle-o', 'route' => 'loan-management.activity-logs.index', 'can' => 'loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('System Settings', 'ការកំណត់ប្រព័ន្ធ'), 'icon' => 'fa fa-cog', 'tone' => 'blue', 'children' => [
                    ['label' => $lmText('Business Settings', 'ការកំណត់អាជីវកម្ម'), 'route' => 'loan-management.settings.business', 'can' => 'loan_management.view'],
                    ['label' => $lmText('Telegram Bot', 'ប៊ូតតេឡេក្រាម'), 'route' => 'loan-management.settings.telegram', 'can' => 'loan_management.view'],
                ]],
            ],
        ],
    ];

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

            <div class="lm-menu-section">
                @if(!empty($section['label']))
                    <div class="lm-menu-section-title"><span>{{ $section['label'] }}</span></div>
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
