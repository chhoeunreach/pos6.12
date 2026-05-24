@php
    $loanUser = auth()->user();
    $locationName = null;
    $headerBadgeCounts = $loanBadgeCounts ?? \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts();
    $unreadChatCount = (int) ($headerBadgeCounts['unread_chat'] ?? 0);
    $pendingVisitCount = (int) ($headerBadgeCounts['pending_visits'] ?? 0);
    $overdueCount = (int) ($headerBadgeCounts['overdue'] ?? 0);
    $notificationCount = $unreadChatCount + $pendingVisitCount + $overdueCount;

    try {
        $locationName = session('user.business_location_name')
            ?? session('business.name')
            ?? optional(session('user'))->business_location_name;
    } catch (\Throwable $e) {
        $locationName = null;
    }

    $backToPosUrl = Route::has('products.index') ? route('products.index') : url('/products');
@endphp

<header class="lm-header sticky-top" id="loanManagementHeader">
    <div class="lm-header-left">
        <button type="button" class="lm-sidebar-toggle" id="loanSidebarToggle" aria-label="Toggle sidebar">
            <i class="fa fa-bars"></i>
        </button>
        <div>
            <h1 class="lm-title">Loan Management</h1>
            <p class="lm-subtitle">Dedicated loan operation workspace</p>
        </div>
    </div>

    <div class="lm-header-right">
        @if(Route::has('loan-management.chat.index') && loan_user_can('loan_management.chat.view'))
            <a href="{{ route('loan-management.chat.index') }}" class="btn btn-default btn-sm lm-header-action">
                <i class="fa fa-comments"></i> Chat
                @if($unreadChatCount > 0)
                    <span class="lm-badge lm-header-badge">{{ $unreadChatCount }}</span>
                @endif
            </a>
        @endif

        @if(Route::has('loan-management.communication.page') && loan_user_can('loan_management.view'))
            <a href="{{ route('loan-management.communication.page', ['page' => 'notifications']) }}" class="btn btn-default btn-sm lm-header-action">
                <i class="fa fa-bell"></i> Notifications
                @if($notificationCount > 0)
                    <span class="lm-badge lm-header-badge">{{ $notificationCount }}</span>
                @endif
            </a>
        @endif

        <div class="lm-user-meta">
            <span class="lm-user-name">{{ $loanUser->username ?? $loanUser->first_name ?? 'Staff' }}</span>
            @if(!empty($locationName))
                <span class="lm-location">{{ $locationName }}</span>
            @endif
        </div>

        <a href="{{ $backToPosUrl }}" class="btn btn-primary btn-sm lm-btn-back">
            <i class="fa fa-arrow-left"></i> Back to Ultimate POS
        </a>

        @if (Route::has('logout'))
            <a href="{{ route('logout') }}" class="btn btn-default btn-sm"
               onclick="event.preventDefault(); document.getElementById('loanLogoutForm').submit();">
                <i class="fa fa-sign-out"></i> Logout
            </a>
            <form id="loanLogoutForm" action="{{ route('logout') }}" method="POST" style="display:none;">
                @csrf
            </form>
        @endif
    </div>
</header>
