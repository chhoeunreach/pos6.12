@php
    $badgeOverdue = $badgeOverdue ?? 0;
    $badgeChat = $badgeChat ?? 0;
    $badgeVisits = $badgeVisits ?? 0;
@endphp

<li class="treeview {{ request()->segment(1) === 'loan-management' ? 'active menu-open' : '' }}">
    <a href="#">
        <i class="fa fa-handshake-o"></i> <span>Installment / Loan</span>
        <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
    </a>
    <ul class="treeview-menu">
        <li><a href="{{ route('loan-management.dashboard.index') }}"><i class="fa fa-dashboard"></i> Dashboard</a></li>

        <li class="treeview">
            <a href="#"><i class="fa fa-users"></i> Customers <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ route('loan-management.customers.index') }}"><i class="fa fa-user"></i> Customers</a></li>
                <li><a href="{{ route('loan-management.guarantors.index') }}"><i class="fa fa-handshake-o"></i> Guarantors</a></li>
                <li><a href="{{ route('loan-management.blacklist.index') }}"><i class="fa fa-ban"></i> Blacklist</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-credit-card"></i> Loans <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ route('loan-management.loans.index') }}"><i class="fa fa-money"></i> Loans</a></li>
                <li><a href="{{ route('loan-management.schedules.index') }}"><i class="fa fa-calendar"></i> Installment Schedules</a></li>
                <li><a href="{{ route('loan-management.monthly-payments.index') }}"><i class="fa fa-calendar-check-o"></i> Monthly Payments</a></li>
                <li><a href="{{ route('loan-management.overdue.index') }}"><i class="fa fa-exclamation-triangle"></i> Overdue / Late Payments @if($badgeOverdue > 0)<span class="label label-danger pull-right">{{ $badgeOverdue }}</span>@endif</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-map-marker"></i> Collections <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ route('loan-management.payments.index') }}"><i class="fa fa-dollar"></i> Payments</a></li>
                <li><a href="{{ route('loan-management.payment-history.index') }}"><i class="fa fa-history"></i> Payment History</a></li>
                <li><a href="{{ route('loan-management.collection-visits.index') }}"><i class="fa fa-street-view"></i> Collection Visits @if($badgeVisits > 0)<span class="label label-info pull-right">{{ $badgeVisits }}</span>@endif</a></li>
                <li><a href="{{ route('loan-management.gps.index') }}"><i class="fa fa-map"></i> GPS Tracking</a></li>
                <li><a href="{{ route('loan-management.chat.index') }}"><i class="fa fa-comments"></i> Live Chat @if($badgeChat > 0)<span class="label label-warning pull-right">{{ $badgeChat }}</span>@endif</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-bank"></i> Finance <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ route('loan-management.aba.index') }}"><i class="fa fa-qrcode"></i> ABA Transactions</a></li>
                <li><a href="{{ route('loan-management.reports.index') }}"><i class="fa fa-line-chart"></i> Reports</a></li>
            </ul>
        </li>

        <li class="treeview">
            <a href="#"><i class="fa fa-cogs"></i> Tools <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
            <ul class="treeview-menu">
                <li><a href="{{ route('loan-management.import.index') }}"><i class="fa fa-upload"></i> Import Excel</a></li>
                <li><a href="{{ route('loan-management.settings.index') }}"><i class="fa fa-cog"></i> Settings</a></li>
            </ul>
        </li>
    </ul>
</li>

