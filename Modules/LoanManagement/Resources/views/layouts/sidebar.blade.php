@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;
    use Illuminate\Support\Str;

    $badgeCounts = $loanBadgeCounts ?? LoanMenuHelper::badgeCounts();
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $sidebarUrl = function (string $route, array $params = []) {
        return Route::has($route) ? route($route, $params) : '#';
    };

    $menuSections = [
        [
            'items' => [
                ['label' => $lmText('Dashboard', 'ផ្ទាំងគ្រប់គ្រង'), 'icon' => 'fa fa-home', 'route' => 'loan-management.dashboard', 'can' => 'loan_management.dashboard.view|loan_management.view', 'tone' => 'blue'],
            ],
        ],
        [
            'label' => $lmText('Loan Management', 'ការគ្រប់គ្រងកម្ចី'),
            'items' => [
                ['label' => $lmText('New Loan', 'កម្ចីថ្មី'), 'icon' => 'fa fa-plus-circle', 'route' => 'loan-management.loans.create', 'can' => 'loan_management.loans.create|loan_management.create', 'tone' => 'blue'],
                ['label' => $lmText('Loan Applications', 'ពាក្យស្នើសុំកម្ចី'), 'icon' => 'fa fa-file-text-o', 'tone' => 'slate', 'children' => [
                    ['label' => $lmText('All Loans', 'បញ្ជីកម្ចីទាំងអស់'), 'route' => 'loan-management.loans', 'can' => 'loan_management.loans.view|loan_management.view'],
                    ['label' => $lmText('Create From POS', 'បង្កើតពីការលក់'), 'route' => 'loan-management.loans.create-from-sell', 'can' => 'loan_management.create_from_sell|loan_management.loans.create|loan_management.create'],
                    ['label' => $lmText('Loan Calculator', 'ម៉ាស៊ីនគណនាកម្ចី'), 'route' => 'loan-management.loans.calculator', 'can' => 'loan_management.loans.create|loan_management.create'],
                ]],
                ['label' => $lmText('Loan Operations', 'ប្រតិបត្តិការកម្ចី'), 'icon' => 'fa fa-database', 'tone' => 'slate', 'children' => [
                    ['label' => $lmText('Due Today', 'ត្រូវបង់ថ្ងៃនេះ'), 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Partial Payments', 'ការបង់ប្រាក់មិនពេញ'), 'route' => 'loan-management.operations.page', 'params' => ['page' => 'partial-payments'], 'can' => 'loan_management.view'],
                    ['label' => $lmText('Closed Accounts', 'គណនីបិទរួច'), 'route' => 'loan-management.operations.page', 'params' => ['page' => 'closed-accounts'], 'can' => 'loan_management.view'],
                ]],
                ['label' => $lmText('Loan Schedule', 'កាលវិភាគកម្ចី'), 'icon' => 'fa fa-calendar', 'route' => 'loan-management.schedules.index', 'can' => 'loan_management.view', 'tone' => 'slate'],
                ['label' => $lmText('Closed Loans', 'កម្ចីបានបិទ'), 'icon' => 'fa fa-archive', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'closed-accounts'], 'can' => 'loan_management.view', 'tone' => 'slate'],
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
                ['label' => $lmText("Today's Collection", 'ប្រមូលប្រាក់ថ្ងៃនេះ'), 'icon' => 'fa fa-usd', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'can' => 'loan_management.view', 'tone' => 'violet'],
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
                ['label' => $lmText('Expenses', 'ចំណាយ'), 'icon' => 'fa fa-level-down', 'route' => 'loan-management.reports.payments', 'can' => 'loan_management.view', 'tone' => 'orange'],
                ['label' => $lmText('Accounting', 'គណនេយ្យ'), 'icon' => 'fa fa-book', 'route' => 'loan-management.payment-history.index', 'can' => 'loan_management.view', 'tone' => 'cyan'],
            ],
        ],
        [
            'label' => $lmText('Reports', 'របាយការណ៍'),
            'items' => [
                ['label' => $lmText('Loan Reports', 'របាយការណ៍កម្ចី'), 'icon' => 'fa fa-pie-chart', 'tone' => 'blue', 'children' => [
                    ['label' => $lmText('Installment Reports', 'របាយការណ៍រំលស់'), 'route' => 'loan-management.reports.index', 'can' => 'loan_management.reports.view|loan_management.view'],
                    ['label' => $lmText('Yearly Loan Summary', 'សង្ខេបកម្ចីប្រចាំឆ្នាំ'), 'route' => 'loan-management.reports.yearly-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
                    ['label' => $lmText('Admin Loan', 'រដ្ឋបាលកម្ចី'), 'route' => 'loan-management.admin-loan', 'can' => 'loan_management.dashboard.view|loan_management.view', 'target' => '_blank'],
                ]],
                ['label' => $lmText('Collection Reports', 'របាយការណ៍ប្រមូលប្រាក់'), 'icon' => 'fa fa-bar-chart', 'route' => 'loan-management.collection.reports', 'can' => 'loan_management.reports.view|loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('Financial Reports', 'របាយការណ៍ហិរញ្ញវត្ថុ'), 'icon' => 'fa fa-file-excel-o', 'route' => 'loan-management.reports.payments', 'can' => 'loan_management.reports.view|loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('Performance', 'សមិទ្ធផល'), 'icon' => 'fa fa-line-chart', 'route' => 'loan-management.dashboard', 'can' => 'loan_management.dashboard.view|loan_management.view', 'tone' => 'blue'],
            ],
        ],
        [
            'label' => $lmText('Administration', 'រដ្ឋបាល'),
            'items' => [
                ['label' => $lmText('Users & Roles', 'អ្នកប្រើប្រាស់ និងតួនាទី'), 'icon' => 'fa fa-user-o', 'tone' => 'blue', 'children' => [
                    ['label' => $lmText('Manage Users', 'គ្រប់គ្រងអ្នកប្រើប្រាស់'), 'route' => 'users.index', 'can' => 'user.view|user.create'],
                ]],
                ['label' => $lmText('Branches', 'សាខា'), 'icon' => 'fa fa-building-o', 'route' => 'loan-management.locations.index', 'can' => 'loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('Audit Logs', 'កំណត់ហេតុសវនកម្ម'), 'icon' => 'fa fa-check-circle-o', 'route' => 'loan-management.activity-logs.index', 'can' => 'loan_management.view', 'tone' => 'blue'],
                ['label' => $lmText('System Settings', 'ការកំណត់ប្រព័ន្ធ'), 'icon' => 'fa fa-cog', 'tone' => 'blue', 'children' => [
                    ['label' => $lmText('Invoice Prefix', 'លេខកូដវិក្កយបត្រ'), 'route' => 'loan-management.settings', 'can' => 'loan_management.view'],
                    ['label' => $lmText('Payment Methods', 'វិធីបង់ប្រាក់'), 'route' => 'loan-management.settings.payment-methods', 'can' => 'loan_management.view'],
                    ['label' => $lmText('Currencies', 'រូបិយប័ណ្ណ'), 'route' => 'loan-management.settings.currencies', 'can' => 'loan_management.view'],
                    ['label' => $lmText('Telegram Bot', 'ប៊ូតតេឡេក្រាម'), 'route' => 'loan-management.settings.telegram', 'can' => 'loan_management.view'],
                ]],
            ],
        ],
    ];

    $currentUser = auth()->user();
    $adminName = optional($currentUser)->first_name ?? optional($currentUser)->username ?? optional($currentUser)->email ?? 'Admin Loan';
@endphp

<aside class="lm-sidebar" id="loanManagementSidebar">
    <div class="lm-brand">
        <div class="lm-brand-icon">
            <i class="fa fa-folder-open"></i>
        </div>
        <div class="lm-brand-text">
            <span>KY Store</span>
            <small>{{ $lmText('Loan Management', 'ការគ្រប់គ្រងកម្ចី') }}</small>
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

    <nav class="lm-menu" aria-label="Loan Management">
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
                        $routes = $children->isEmpty()
                            ? [$item['route'] ?? '']
                            : $children->flatMap(fn ($child) => $child['active_routes'] ?? [$child['route']])->all();
                        $isActive = LoanMenuHelper::activeRoute($routes, false);
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
                                    @php $childActive = LoanMenuHelper::activeRoute($child['active_routes'] ?? [$child['route']], false); @endphp
                                    <a href="{{ $sidebarUrl($child['route'], $child['params'] ?? []) }}"
                                       class="lm-submenu-link {{ $childActive ? 'active' : '' }} {{ !empty($child['meta']) ? 'has-meta' : '' }}"
                                       title="{{ $child['label'] }}"
                                       @if(!empty($child['target'])) target="{{ $child['target'] }}" rel="noopener" @endif>
                                        @if(!empty($child['icon']))
                                            <i class="{{ $child['icon'] }} lm-submenu-icon"></i>
                                        @else
                                            <span class="lm-submenu-dot"></span>
                                        @endif
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

    <a href="{{ $sidebarUrl('loan-management.admin-loan') }}" class="lm-admin-card" target="_blank" rel="noopener">
        <span class="lm-admin-avatar">AD<span></span></span>
        <span class="lm-admin-info">
            <strong>{{ $lmText('Admin Loan', 'រដ្ឋបាលកម្ចី') }}</strong>
            <small>{{ $adminName }}</small>
        </span>
        <i class="fa fa-cog lm-admin-gear"></i>
        <i class="fa fa-angle-down lm-admin-chevron"></i>
    </a>
</aside>
