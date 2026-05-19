@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    $badgeCounts = $loanBadgeCounts ?? LoanMenuHelper::badgeCounts();

    $menu = [
        ['label' => 'Dashboard', 'icon' => 'fa fa-dashboard', 'route' => 'loan-management.dashboard', 'can' => 'loan_management.dashboard.view'],
        ['label' => 'Loan Operations', 'icon' => 'fa fa-credit-card', 'children' => [
            ['label' => 'New Loans', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'new-loans'], 'can' => 'loan_management.view'],
            ['label' => 'Active Loans', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'active-loans'], 'can' => 'loan_management.view'],
            ['label' => 'Due Today', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'can' => 'loan_management.view'],
            ['label' => 'Partial Payments', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'partial-payments'], 'can' => 'loan_management.view'],
            ['label' => 'Closed Accounts', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'closed-accounts'], 'can' => 'loan_management.view'],
            ['label' => 'Create Loan', 'route' => 'loan-management.loans.create-from-sell', 'can' => 'loan_management.create_from_sell'],
            ['label' => 'All Loans', 'route' => 'loan-management.loans', 'can' => 'loan_management.loans.view'],
        ]],
        ['label' => 'Collection Cases', 'icon' => 'fa fa-phone', 'children' => [
            ['label' => 'Overdue Accounts', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'overdue-accounts'], 'can' => 'loan_management.view', 'badge' => $badgeCounts['overdue'] ?? 0],
            ['label' => 'Promise To Pay', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'promise-to-pay'], 'can' => 'loan_management.view'],
            ['label' => 'Broken Promise', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'broken-promise'], 'can' => 'loan_management.view'],
            ['label' => 'Field Visit Required', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'field-visit-required'], 'can' => 'loan_management.view', 'badge' => $badgeCounts['pending_visits'] ?? 0],
            ['label' => 'Skip Customers', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'skip-customers'], 'can' => 'loan_management.view'],
            ['label' => 'Delinquent Accounts', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'delinquent-accounts'], 'can' => 'loan_management.view'],
            ['label' => 'Recovery Management', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'recovery-management'], 'can' => 'loan_management.view'],
            ['label' => 'Debt Collection', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'debt-collection'], 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Risk & Legal', 'icon' => 'fa fa-balance-scale', 'children' => [
            ['label' => 'High Risk Customers', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'high-risk-customers'], 'can' => 'loan_management.view'],
            ['label' => 'Fraud Risk', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'fraud-risk'], 'can' => 'loan_management.view'],
            ['label' => 'Legal Cases', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'legal-cases'], 'can' => 'loan_management.view'],
            ['label' => 'Blacklisted Customers', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'blacklisted-customers'], 'can' => 'loan_management.blacklist.view'],
            ['label' => 'Repossessions', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'repossessions'], 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Customer Management', 'icon' => 'fa fa-users', 'children' => [
            ['label' => 'Customers', 'route' => 'loan-management.customers', 'can' => 'loan_management.view'],
            ['label' => 'Guarantors', 'route' => 'loan-management.guarantors.index', 'can' => 'loan_management.guarantors.view'],
            ['label' => 'Contact History', 'route' => 'loan-management.customer-workflow.page', 'params' => ['page' => 'contact-history'], 'can' => 'loan_management.view'],
            ['label' => 'Collection Visits', 'route' => 'loan-management.collection-visits.index', 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Communication', 'icon' => 'fa fa-comments', 'children' => [
            ['label' => 'Live Chat', 'route' => 'loan-management.chat.index', 'can' => 'loan_management.chat.view', 'badge' => $badgeCounts['unread_chat'] ?? 0],
            ['label' => 'Voice Calls', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'voice-calls'], 'can' => 'loan_management.view'],
            ['label' => 'Notifications', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'notifications'], 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Finance', 'icon' => 'fas fa-money-bill-alt', 'children' => [
            ['label' => 'Payments', 'icon' => 'fa fa-money', 'route' => 'loan-management.payments.index', 'can' => 'loan_management.view'],
            ['label' => 'Payment History', 'icon' => 'fa fa-history', 'route' => 'loan-management.payment-history.index', 'can' => 'loan_management.view'],
            ['label' => 'ABA Transactions', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.aba.index', 'can' => 'loan_management.aba.view'],
            ['label' => 'Reports', 'icon' => 'fa fa-bar-chart', 'route' => 'loan-management.collection.reports', 'can' => 'loan_management.reports.view'],
        ]],
        ['label' => 'Tools', 'icon' => 'fa fa-wrench', 'children' => [
            ['label' => 'Import Excel', 'route' => 'loan-management.import.index', 'can' => 'loan_management.import.view'],
            ['label' => 'GPS Tracking', 'route' => 'loan-management.gps.index', 'can' => 'loan_management.gps.view'],
        ]],
        ['label' => 'Settings', 'icon' => 'fa fa-cog', 'children' => [
            ['label' => 'Business Setting', 'icon' => 'fa fa-building', 'route' => 'loan-management.settings', 'can' => 'loan_management.settings.view', 'active_routes' => ['loan-management.settings', 'loan-management.settings.index']],
            ['label' => 'Locations', 'icon' => 'fa fa-map-marker', 'route' => 'loan-management.locations.index', 'can' => 'loan_management.view'],
            ['label' => 'Payment Methods', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.settings.payment-methods', 'can' => 'loan_management.view'],
            ['label' => 'Currencies', 'icon' => 'fa fa-money', 'route' => 'loan-management.settings.currencies', 'can' => 'loan_management.view'],
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
                    : $visibleChildren->flatMap(fn ($child) => $child['active_routes'] ?? [$child['route']])->all();
                $isActive = LoanMenuHelper::activeRoute($routes, false);
            @endphp

            @if(empty($children))
                <a href="{{ Route::has($item['route']) ? route($item['route'], $item['params'] ?? []) : '#' }}" class="lm-menu-link {{ $isActive ? 'active' : '' }}">
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
                            @php $childActive = LoanMenuHelper::activeRoute($child['active_routes'] ?? [$child['route']], false); @endphp
                            <a href="{{ Route::has($child['route']) ? route($child['route'], $child['params'] ?? []) : '#' }}" class="lm-submenu-link {{ $childActive ? 'active' : '' }}">
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
