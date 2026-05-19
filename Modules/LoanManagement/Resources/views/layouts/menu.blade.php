@php
    $badgeOverdue = $badgeOverdue ?? 0;
    $badgeChat = $badgeChat ?? 0;
    $badgeVisits = $badgeVisits ?? 0;
    $lmUrl = function (string $route, array $params = [], string $fallback = '#') {
        return Route::has($route) ? route($route, $params) : url($fallback);
    };
@endphp

<li class="treeview {{ request()->segment(1) === 'loan-management' ? 'active menu-open' : '' }}">
    <a href="#">
        <i class="fa fa-handshake-o"></i> <span>Installment / Loan</span>
        <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
    </a>
    <ul class="treeview-menu">
        <li><a href="{{ $lmUrl('loan-management.dashboard.index', [], '/loan-management/dashboard/main') }}"><i class="fa fa-dashboard"></i> Dashboard</a></li>

        <li class="treeview">
            <a href="#"><i class="fa fa-credit-card"></i> Loan Operations <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.loans.create-from-sell', [], '/loan-management/loans/create-from-sell') }}"><i class="fa fa-plus"></i> New Loans</a></li>
                <li><a href="{{ $lmUrl('loan-management.operations.page', ['page' => 'active-loans'], '/loan-management/operations/active-loans') }}"><i class="fa fa-check"></i> Active Loans</a></li>
                <li><a href="{{ $lmUrl('loan-management.operations.page', ['page' => 'due-today'], '/loan-management/operations/due-today') }}"><i class="fa fa-calendar-check-o"></i> Due Today</a></li>
                <li><a href="{{ $lmUrl('loan-management.operations.page', ['page' => 'partial-payments'], '/loan-management/operations/partial-payments') }}"><i class="fa fa-adjust"></i> Partial Payments</a></li>
                <li><a href="{{ $lmUrl('loan-management.operations.page', ['page' => 'closed-accounts'], '/loan-management/operations/closed-accounts') }}"><i class="fa fa-lock"></i> Closed Accounts</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-phone"></i> Collection Cases <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'overdue-accounts'], '/loan-management/collection/overdue-accounts') }}"><i class="fa fa-exclamation-triangle"></i> Overdue Accounts @if($badgeOverdue > 0)<span class="label label-danger pull-right">{{ $badgeOverdue }}</span>@endif</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'promise-to-pay'], '/loan-management/collection/promise-to-pay') }}"><i class="fa fa-calendar"></i> Promise To Pay</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'broken-promise'], '/loan-management/collection/broken-promise') }}"><i class="fa fa-chain-broken"></i> Broken Promise</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'field-visit-required'], '/loan-management/collection/field-visit-required') }}"><i class="fa fa-street-view"></i> Field Visit Required @if($badgeVisits > 0)<span class="label label-info pull-right">{{ $badgeVisits }}</span>@endif</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'skip-customers'], '/loan-management/collection/skip-customers') }}"><i class="fa fa-phone-square"></i> Skip Customers</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'delinquent-accounts'], '/loan-management/collection/delinquent-accounts') }}"><i class="fa fa-warning"></i> Delinquent Accounts</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'recovery-management'], '/loan-management/collection/recovery-management') }}"><i class="fa fa-refresh"></i> Recovery Management</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.page', ['page' => 'debt-collection'], '/loan-management/collection/debt-collection') }}"><i class="fa fa-briefcase"></i> Debt Collection</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-balance-scale"></i> Risk & Legal <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.risk.page', ['page' => 'high-risk-customers'], '/loan-management/risk/high-risk-customers') }}"><i class="fa fa-user-times"></i> High Risk Customers</a></li>
                <li><a href="{{ $lmUrl('loan-management.risk.page', ['page' => 'fraud-risk'], '/loan-management/risk/fraud-risk') }}"><i class="fa fa-ban"></i> Fraud Risk</a></li>
                <li><a href="{{ $lmUrl('loan-management.risk.page', ['page' => 'legal-cases'], '/loan-management/risk/legal-cases') }}"><i class="fa fa-gavel"></i> Legal Cases</a></li>
                <li><a href="{{ $lmUrl('loan-management.risk.page', ['page' => 'blacklisted-customers'], '/loan-management/risk/blacklisted-customers') }}"><i class="fa fa-black-tie"></i> Blacklisted Customers</a></li>
                <li><a href="{{ $lmUrl('loan-management.risk.page', ['page' => 'repossessions'], '/loan-management/risk/repossessions') }}"><i class="fa fa-truck"></i> Repossessions</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-users"></i> Customer Management <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.customers.index', [], '/loan-management/customers/list') }}"><i class="fa fa-user"></i> Customers</a></li>
                <li><a href="{{ $lmUrl('loan-management.guarantors.index', [], '/loan-management/guarantors') }}"><i class="fa fa-handshake-o"></i> Guarantors</a></li>
                <li><a href="{{ $lmUrl('loan-management.customer-workflow.page', ['page' => 'contact-history'], '/loan-management/customers-workflow/contact-history') }}"><i class="fa fa-history"></i> Contact History</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection-visits.index', [], '/loan-management/collection-visits') }}"><i class="fa fa-street-view"></i> Collection Visits</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-comments"></i> Communication <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.chat.index', [], '/loan-management/chat') }}"><i class="fa fa-comments"></i> Live Chat @if($badgeChat > 0)<span class="label label-warning pull-right">{{ $badgeChat }}</span>@endif</a></li>
                <li><a href="{{ $lmUrl('loan-management.communication.page', ['page' => 'voice-calls'], '/loan-management/communication/voice-calls') }}"><i class="fa fa-phone"></i> Voice Calls</a></li>
                <li><a href="{{ $lmUrl('loan-management.communication.page', ['page' => 'notifications'], '/loan-management/communication/notifications') }}"><i class="fa fa-bell"></i> Notifications</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-bank"></i> Finance <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.payments.index', [], '/loan-management/payments/index') }}"><i class="fa fa-dollar"></i> Payments</a></li>
                <li><a href="{{ $lmUrl('loan-management.payment-history.index', [], '/loan-management/payment-history') }}"><i class="fa fa-history"></i> Payment History</a></li>
                <li><a href="{{ $lmUrl('loan-management.aba.index', [], '/loan-management/finance/aba-transactions') }}"><i class="fa fa-qrcode"></i> ABA Transactions</a></li>
                <li><a href="{{ $lmUrl('loan-management.collection.reports', [], '/loan-management/collection-reports') }}"><i class="fa fa-line-chart"></i> Reports</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-cogs"></i> Tools <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.import.index', [], '/loan-management/tools/import') }}"><i class="fa fa-upload"></i> Import Excel</a></li>
                <li><a href="{{ $lmUrl('loan-management.gps.index', [], '/loan-management/gps') }}"><i class="fa fa-map"></i> GPS Tracking</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-cog"></i> Settings <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ $lmUrl('loan-management.settings.index', [], '/loan-management/settings/index') }}"><i class="fa fa-building"></i> Business Setting</a></li>
                <li><a href="{{ $lmUrl('loan-management.locations.index', [], '/loan-management/locations') }}"><i class="fa fa-map-marker"></i> Locations</a></li>
                <li><a href="{{ $lmUrl('loan-management.settings.payment-methods', [], '/loan-management/settings/payment-methods') }}"><i class="fa fa-credit-card"></i> Payment Methods</a></li>
                <li><a href="{{ $lmUrl('loan-management.settings.currencies', [], '/loan-management/settings/currencies') }}"><i class="fa fa-money"></i> Currencies</a></li>
            </ul>
        </li>
    </ul>
</li>
