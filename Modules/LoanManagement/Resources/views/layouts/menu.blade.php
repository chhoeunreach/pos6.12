@php
    $businessSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
@endphp
<li class="treeview {{ request()->segment(1) === 'loan-management' ? 'active menu-open' : '' }}">
    <a href="#"><i class="fa fa-handshake-o"></i><span>{{ $businessSettings['system_name'] }}</span><i class="fa fa-angle-left pull-right" aria-hidden="true"></i></a>
    <ul class="treeview-menu">
        @include('loanmanagement::layouts.partials.navigation_tree')
    </ul>
</li>
