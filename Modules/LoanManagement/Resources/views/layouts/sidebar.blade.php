@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    $badgeCounts = $loanBadgeCounts ?? LoanMenuHelper::badgeCounts();
    $canCreateLoan = LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create|loan_management.create_from_sell');
    $canViewLoans = LoanMenuHelper::loanUserCan('loan_management.loans.view|loan_management.view');
    $canViewPayments = LoanMenuHelper::loanUserCan('loan_management.payments.view|loan_management.payment|loan_management.view');

    $sidebarUrl = function (string $route, array $params = []) {
        return Route::has($route) ? route($route, $params) : '#';
    };

    $menu = [
        ['label' => 'Dashboard', 'icon' => 'fa fa-dashboard', 'route' => 'loan-management.dashboard', 'can' => 'loan_management.dashboard.view|loan_management.view'],
        ['label' => 'Loan Operations', 'icon' => 'fa fa-credit-card', 'children' => [
            ['label' => 'All Loans', 'route' => 'loan-management.loans', 'can' => 'loan_management.loans.view|loan_management.view'],
            ['label' => 'Create Loan', 'icon' => 'fa fa-plus-circle', 'route' => 'loan-management.loans.create', 'can' => 'loan_management.loans.create|loan_management.create'],
            ['label' => 'Create From POS', 'icon' => 'fa fa-shopping-cart', 'route' => 'loan-management.loans.create-from-sell', 'can' => 'loan_management.create_from_sell|loan_management.loans.create|loan_management.create'],
            ['label' => 'Loan Calculator', 'icon' => 'fa fa-calculator', 'route' => 'loan-management.loans.calculator', 'can' => 'loan_management.loans.create|loan_management.create'],
            ['label' => 'Due Today', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'can' => 'loan_management.view'],
            ['label' => 'Partial Payments', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'partial-payments'], 'can' => 'loan_management.view'],
            ['label' => 'Closed Accounts', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'closed-accounts'], 'can' => 'loan_management.view'],
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
            ['label' => 'Clone From POS', 'icon' => 'fa fa-copy', 'route' => 'loan-management.customers.clone-from-pos', 'can' => 'loan_management.create'],
            ['label' => 'Guarantors', 'route' => 'loan-management.guarantors.index', 'can' => 'loan_management.guarantors.view|loan_management.view'],
            ['label' => 'Blacklist', 'icon' => 'fa fa-ban', 'route' => 'loan-management.blacklist.index', 'can' => 'loan_management.blacklist.view|loan_management.view'],
            ['label' => 'Contact History', 'route' => 'loan-management.customer-workflow.page', 'params' => ['page' => 'contact-history'], 'can' => 'loan_management.view'],
            ['label' => 'Collection Visits', 'route' => 'loan-management.collection-visits.index', 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Communication', 'icon' => 'fa fa-comments', 'children' => [
            ['label' => 'Live Chat', 'route' => 'loan-management.chat.index', 'can' => 'loan_management.chat.view', 'badge' => $badgeCounts['unread_chat'] ?? 0],
            ['label' => 'Voice Calls', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'voice-calls'], 'can' => 'loan_management.view'],
            ['label' => 'Notifications', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'notifications'], 'can' => 'loan_management.view'],
            ['label' => 'SMS/Telegram Logs', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'sms-telegram-logs'], 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Finance', 'icon' => 'fas fa-money-bill-alt', 'children' => [
            ['label' => 'Payments', 'icon' => 'fa fa-money', 'route' => 'loan-management.payments.index', 'can' => 'loan_management.view'],
            ['label' => 'Payment History', 'icon' => 'fa fa-history', 'route' => 'loan-management.payment-history.index', 'can' => 'loan_management.view'],
            ['label' => 'Customer Deposit Payments', 'icon' => 'fa fa-bank', 'route' => 'loan-management.payments.index', 'params' => ['payment_type' => 'loan'], 'can' => 'loan_management.view'],
            ['label' => 'Interest / Collection Payments', 'icon' => 'fa fa-calendar-check-o', 'route' => 'loan-management.payments.index', 'params' => ['payment_type' => 'monthly'], 'can' => 'loan_management.view'],
            ['label' => 'ABA Transactions', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.aba.index', 'can' => 'loan_management.aba.view|loan_management.view', 'meta' => 'Soon'],
        ]],
        ['label' => 'Reports', 'icon' => 'fa fa-bar-chart', 'children' => [
            ['label' => 'Installment Reports', 'icon' => 'fa fa-list-alt', 'route' => 'loan-management.reports.index', 'can' => 'loan_management.reports.view|loan_management.view', 'meta' => 'Soon'],
            ['label' => 'Collection Payment Reports', 'icon' => 'fa fa-money', 'route' => 'loan-management.payments.index', 'params' => ['payment_type' => 'monthly'], 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Deposit Payment Reports', 'icon' => 'fa fa-bank', 'route' => 'loan-management.payments.index', 'params' => ['payment_type' => 'loan'], 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Collection Reports', 'icon' => 'fa fa-phone-square', 'route' => 'loan-management.collection.reports', 'can' => 'loan_management.reports.view|loan_management.view'],
        ]],
        ['label' => 'Tools', 'icon' => 'fa fa-wrench', 'children' => [
            ['label' => 'Loan Import/Export', 'route' => 'loan-management.tools.loan-import-export', 'can' => 'loan_management.import.view|loan_management.export.view'],
            ['label' => 'Monthly Payments Import/Export', 'route' => 'loan-management.tools.monthly-import-export', 'can' => 'loan_management.import.view|loan_management.export.view'],
            ['label' => 'Send Notification', 'icon' => 'fa fa-bell', 'route' => 'loan-management.tools.send-notification', 'can' => 'loan_management.view', 'meta' => 'Soon'],
            ['label' => 'GPS Tracking', 'route' => 'loan-management.gps.index', 'can' => 'loan_management.gps.view'],
            ['label' => 'Activity Logs', 'icon' => 'fa fa-history', 'route' => 'loan-management.activity-logs.index', 'can' => 'loan_management.view'],
        ]],
        ['label' => 'Settings', 'icon' => 'fa fa-cog', 'children' => [
            ['label' => 'Invoice Prefix', 'icon' => 'fa fa-file-text-o', 'route' => 'loan-management.settings', 'can' => 'loan_management.view'],
            ['label' => 'Locations', 'icon' => 'fa fa-map-marker', 'route' => 'loan-management.locations.index', 'can' => 'loan_management.view'],
            ['label' => 'Payment Methods', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.settings.payment-methods', 'can' => 'loan_management.view'],
            ['label' => 'Currencies', 'icon' => 'fa fa-money', 'route' => 'loan-management.settings.currencies', 'can' => 'loan_management.view', 'meta' => 'Soon'],
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
        <button type="button" class="lm-sidebar-close d-lg-none" id="loanSidebarClose"
                style="margin-left: auto; border: 0; background: none; color: #94a3b8; font-size: 18px; padding: 4px 8px; cursor: pointer; border-radius: 6px;"
                aria-label="Close sidebar">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <div class="lm-sidebar-actions">
        @if($canCreateLoan && Route::has('loan-management.loans.create'))
            <a href="{{ route('loan-management.loans.create') }}" class="lm-sidebar-action primary">
                <i class="fa fa-plus-circle"></i>
                <span>New Loan</span>
            </a>
        @endif

        <div class="lm-sidebar-action-grid">
            @if($canCreateLoan && Route::has('loan-management.loans.create-from-sell'))
                <a href="{{ route('loan-management.loans.create-from-sell') }}" class="lm-sidebar-mini-action">
                    <i class="fa fa-shopping-cart"></i>
                    <span>From POS</span>
                </a>
            @endif
            @if($canViewPayments && Route::has('loan-management.payments.index'))
                <a href="{{ route('loan-management.payments.index') }}" class="lm-sidebar-mini-action">
                    <i class="fa fa-money"></i>
                    <span>Payments</span>
                </a>
            @endif
            @if($canViewLoans && Route::has('loan-management.collection.page'))
                <a href="{{ route('loan-management.collection.page', ['page' => 'overdue-accounts']) }}" class="lm-sidebar-mini-action danger">
                    <i class="fa fa-exclamation-triangle"></i>
                    <span>Overdue</span>
                    @if(($badgeCounts['overdue'] ?? 0) > 0)
                        <b>{{ (int) $badgeCounts['overdue'] }}</b>
                    @endif
                </a>
            @endif
            @if(Route::has('loan-management.loans.calculator') && $canCreateLoan)
                <a href="{{ route('loan-management.loans.calculator') }}" class="lm-sidebar-mini-action">
                    <i class="fa fa-calculator"></i>
                    <span>Calculator</span>
                </a>
            @endif
        </div>
    </div>

    <nav class="lm-menu">
        @foreach($menu as $item)
            @php
                $children = $item['children'] ?? [];
                $visibleChildren = collect($children)->filter(fn ($child) => \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan($child['can'] ?? 'loan_management.view'))->values();
                $isVisible = empty($children)
                    ? \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan($item['can'] ?? 'loan_management.view')
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
                <a href="{{ $sidebarUrl($item['route'], $item['params'] ?? []) }}" class="lm-menu-link {{ $isActive ? 'active' : '' }}">
                    <i class="{{ $item['icon'] }} lm-menu-icon"></i>
                    <span class="lm-menu-label">{{ $item['label'] }}</span>
                    @if(!empty($item['meta']))
                        <span class="lm-menu-meta">{{ $item['meta'] }}</span>
                    @endif
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
                            <a href="{{ $sidebarUrl($child['route'], $child['params'] ?? []) }}" class="lm-submenu-link {{ $childActive ? 'active' : '' }} {{ !empty($child['meta']) ? 'has-meta' : '' }}">
                                @if(!empty($child['icon']))
                                    <i class="{{ $child['icon'] }} lm-submenu-icon"></i>
                                @else
                                    <span class="lm-submenu-dot"></span>
                                @endif
                                <span class="lm-menu-label">{{ $child['label'] }}</span>
                                @if(!empty($child['badge']))
                                    <span class="lm-badge">{{ (int) $child['badge'] }}</span>
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
    </nav>
</aside>
