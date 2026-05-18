@php
    $loanUser = auth()->user();
    $locationName = null;

    try {
        $locationName = session('user.business_location_name')
            ?? session('business.name')
            ?? optional(session('user'))->business_location_name;
    } catch (\Throwable $e) {
        $locationName = null;
    }

    $backToPosUrl = Route::has('home') ? route('home') : url('/');
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
