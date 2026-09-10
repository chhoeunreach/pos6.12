@php
    $mobileNavBadges = $loanBadgeCounts ?? \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts();
    $unreadChatCount = (int) ($mobileNavBadges['unread_chat'] ?? 0);
    $overdueCount = (int) ($mobileNavBadges['overdue'] ?? 0);

    $currentRoute = optional(request()->route())->getName() ?? '';
    $canViewChats = \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.chat.view');
@endphp

<nav class="lm-mobile-nav d-lg-none" id="loanMobileNav">
    <a href="{{ route('loan-management.dashboard') }}" class="{{ str_starts_with($currentRoute, 'loan-management.dashboard') ? 'active' : '' }}">
        <i class="fa fa-dashboard"></i>
        <span>Home</span>
    </a>

    <a href="{{ route('loan-management.loans') }}" class="{{ str_starts_with($currentRoute, 'loan-management.loans') ? 'active' : '' }}">
        <i class="fa fa-credit-card"></i>
        <span>Installments</span>
    </a>

    <a href="{{ route('loan-management.monthly-payments.index') }}" class="{{ str_starts_with($currentRoute, 'loan-management.monthly-payments') ? 'active' : '' }}">
        <i class="fa fa-money"></i>
        <span>Collection</span>
    </a>

    @if($canViewChats)
        <a href="{{ route('loan-management.chat.index') }}" class="{{ str_starts_with($currentRoute, 'loan-management.chat') || str_starts_with($currentRoute, 'loan-management.live-chat') ? 'active' : '' }}">
            <i class="fa fa-telegram"></i>
            <span>Chats</span>
            @if($unreadChatCount > 0)
                <em class="lm-mobile-nav-badge">{{ $unreadChatCount > 99 ? '99+' : $unreadChatCount }}</em>
            @endif
        </a>
    @endif

    <button type="button" id="loanMobileSidebarToggle" aria-label="Open full menu">
        <i class="fa fa-users"></i>
        <span>More</span>
    </button>
</nav>
