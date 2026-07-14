@php
    $mobileNavBadges = $loanBadgeCounts ?? \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts();
    $unreadChatCount = (int) ($mobileNavBadges['unread_chat'] ?? 0);
    $overdueCount = (int) ($mobileNavBadges['overdue'] ?? 0);

    $currentRoute = request()->route()->getName() ?? '';
@endphp

<nav class="lm-mobile-nav d-lg-none" id="loanMobileNav">
    <a href="{{ route('loan-management.dashboard') }}" class="{{ str_starts_with($currentRoute, 'loan-management.dashboard') ? 'active' : '' }}">
        <i class="fa fa-dashboard"></i>
        <span>Home</span>
    </a>

    <a href="{{ route('loan-management.loans') }}" class="{{ str_starts_with($currentRoute, 'loan-management.loans') ? 'active' : '' }}">
        <i class="fa fa-credit-card"></i>
        <span>Loans</span>
    </a>

    <a href="{{ route('loan-management.monthly-payments.index') }}" class="{{ str_starts_with($currentRoute, 'loan-management.monthly-payments') ? 'active' : '' }}">
        <i class="fa fa-money"></i>
        <span>Collection</span>
    </a>

    <a href="{{ route('loan-management.chat.index') }}" class="{{ str_starts_with($currentRoute, 'loan-management.chat') ? 'active' : '' }}" style="position: relative;">
        <i class="fa fa-comments"></i>
        <span>Chat</span>
        @if($unreadChatCount > 0)
            <span class="lm-mobile-nav-badge">{{ $unreadChatCount }}</span>
        @endif
    </a>

    <a href="{{ route('loan-management.customers') }}" class="{{ str_starts_with($currentRoute, 'loan-management.customers') ? 'active' : '' }}">
        <i class="fa fa-users"></i>
        <span>More</span>
    </a>
</nav>
