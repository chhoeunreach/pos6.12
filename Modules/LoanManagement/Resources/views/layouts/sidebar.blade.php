@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    $badgeCounts = $loanBadgeCounts ?? LoanMenuHelper::badgeCounts();

    $menu = [
        ['label' => 'Dashboard', 'icon' => 'fa fa-dashboard', 'route' => 'loan-management.dashboard', 'can' => 'loan_management.dashboard.view'],
        ['label' => 'Customers', 'icon' => 'fa fa-users', 'children' => [
            ['label' => 'Customers', 'route' => 'loan-management.customers', 'can' => 'loan_management.view'],
            ['label' => 'Guarantors', 'route' => 'loan-management.guarantors.index', 'can' => 'loan_management.guarantors.view'],
            ['label' => 'Blacklist', 'route' => 'loan-management.blacklist.index', 'can' => 'loan_management.blacklist.view'],
        ]],
        ['label' => 'Loans', 'icon' => 'fa fa-credit-card', 'children' => [
            ['label' => 'Loans', 'route' => 'loan-management.loans', 'can' => 'loan_management.loans.view'],
            ['label' => 'Create Loan', 'route' => 'loan-management.loans.create-from-sell', 'can' => 'loan_management.create_from_sell'],
            ['label' => 'Create Loan From Sell', 'route' => 'loan-management.sell-list', 'can' => 'loan_management.sell_convert'],
            ['label' => 'Installment Schedules', 'route' => 'loan-management.schedules.index', 'can' => 'loan_management.view'],
            ['label' => 'Monthly Payments', 'route' => 'loan-management.monthly-payments.index', 'can' => 'loan_management.monthly_payments.view'],
            ['label' => 'Overdue / Late Payments', 'route' => 'loan-management.overdue.index', 'can' => 'loan_management.overdue.view', 'badge' => $badgeCounts['overdue'] ?? 0],
        ]],
        ['label' => 'Collections', 'icon' => 'fa fa-map-marker', 'children' => [
            ['label' => 'Payments', 'icon' => 'fa fa-money', 'route' => 'loan-management.payments.index', 'can' => 'loan_management.view'],
            ['label' => 'Payment History', 'icon' => 'fa fa-history', 'route' => 'loan-management.payment-history.index', 'can' => 'loan_management.view'],
            ['label' => 'Collection Visits', 'icon' => 'fa fa-street-view', 'route' => 'loan-management.collection-visits.index', 'can' => 'loan_management.view', 'badge' => $badgeCounts['pending_visits'] ?? 0],
            ['label' => 'GPS Tracking', 'icon' => 'fa fa-location-arrow', 'route' => 'loan-management.gps.index', 'can' => 'loan_management.gps.view'],
            ['label' => 'Live Chat', 'icon' => 'fa fa-comments', 'route' => 'loan-management.chat.index', 'can' => 'loan_management.chat.view', 'badge' => $badgeCounts['unread_chat'] ?? 0],
        ]],
        ['label' => 'Finance', 'icon' => 'fas fa-money-bill-alt', 'children' => [
            ['label' => 'ABA Transactions', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.aba.index', 'can' => 'loan_management.aba.view'],
            ['label' => 'Reports', 'icon' => 'fa fa-bar-chart', 'route' => 'loan-management.reports.index', 'can' => 'loan_management.reports.view'],
        ]],
        ['label' => 'Tools', 'icon' => 'fa fa-wrench', 'children' => [
            ['label' => 'Import Excel', 'route' => 'loan-management.import.index', 'can' => 'loan_management.import.view'],
            ['label' => 'Settings', 'route' => 'loan-management.settings', 'can' => 'loan_management.settings.view'],
        ]],
    ];
@endphp

<aside class="lm-sidebar" id="loanManagementSidebar">
    <div class="lm-brand">
        <div class="lm-brand-icon">
            <i class="fa fa-credit-card"></i>
        </div>
        <div class="lm-brand-text">
            <span>Loan Management</span>
            <small>Loans & collections</small>
        </div>
    </div>

    <nav class="lm-menu">
        @foreach($menu as $item)
            @php
                $children = $item['children'] ?? [];
                $visibleChildren = collect($children)->filter(fn ($child) => loan_user_can($child['can'] ?? 'loan_management.view'))->values();
                $isVisible = empty($children)
                    ? loan_user_can($item['can'] ?? 'loan_management.view')
                    : $visibleChildren->isNotEmpty();
                if (! $isVisible) {
                    continue;
                }

                $routes = empty($children)
                    ? [$item['route'] ?? '']
                    : $visibleChildren->pluck('route')->all();
                $isActive = LoanMenuHelper::activeRoute($routes);
            @endphp

            @if(empty($children))
                <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}" class="lm-menu-link {{ $isActive ? 'active' : '' }}">
                    <i class="{{ $item['icon'] }} lm-menu-icon"></i>
                    <span class="lm-menu-label">{{ $item['label'] }}</span>
                </a>
            @else
                <div class="lm-menu-group {{ $isActive ? 'open' : '' }}">
                    <button class="lm-menu-link lm-menu-toggle {{ $isActive ? 'active' : '' }}" type="button">
                        <i class="{{ $item['icon'] }} lm-menu-icon"></i>
                        <span class="lm-menu-label">{{ $item['label'] }}</span>
                        <i class="fa fa-angle-down lm-angle"></i>
                    </button>

                    <div class="lm-submenu" style="{{ $isActive ? 'display:block;' : '' }}">
                        @foreach($visibleChildren as $child)
                            @php $childActive = LoanMenuHelper::activeRoute([$child['route']]); @endphp
                            <a href="{{ Route::has($child['route']) ? route($child['route']) : '#' }}" class="lm-submenu-link {{ $childActive ? 'active' : '' }}">
                                @if(!empty($child['icon']))
                                    <i class="{{ $child['icon'] }} lm-submenu-icon"></i>
                                @else
                                    <span class="lm-submenu-dot"></span>
                                @endif
                                <span class="lm-menu-label">{{ $child['label'] }}</span>
                                @if(!empty($child['badge']))
                                    <span class="lm-badge">{{ (int) $child['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
