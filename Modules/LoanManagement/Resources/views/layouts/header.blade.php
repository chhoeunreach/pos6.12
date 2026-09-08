@php
    $loanUser = auth()->user();
    $locationName = null;
    $loanLanguage = session('user.language', config('app.locale'));
    $welcomeName = trim(collect([
        optional($loanUser)->first_name,
        optional($loanUser)->last_name,
    ])->filter()->implode(' '));
    $welcomeName = $welcomeName ?: (optional($loanUser)->username ?? optional($loanUser)->email ?? 'Staff');
    $welcomeText = $loanLanguage === 'km' ? 'សូមស្វាគមន៍, '.$welcomeName : 'Welcome, '.$welcomeName;
    $userInitial = strtoupper(substr($welcomeName, 0, 1));

    try {
        $locationName = session('user.business_location_name')
            ?? session('business.name')
            ?? optional(session('user'))->business_location_name;
    } catch (\Throwable $e) {
        $locationName = null;
    }

    $adminPhotoUrl = null;
    if ($loanUser) {
        if (!empty($loanUser->profile_photo_url)) {
            $adminPhotoUrl = $loanUser->profile_photo_url;
        } elseif (!empty($loanUser->profile_photo)) {
            $adminPhotoUrl = asset('uploads/profile_photos/' . $loanUser->profile_photo);
        } elseif (session()->has('user.profile_photo_url')) {
            $adminPhotoUrl = session('user.profile_photo_url');
        }
    }
@endphp

<header class="lm-header sticky-top" id="loanManagementHeader">
    <div class="lm-header-left">
        <button type="button" class="lm-sidebar-toggle" id="loanSidebarToggle" aria-label="Toggle sidebar">
            <i class="fa fa-bars"></i>
        </button>
        <div>
            <h1 class="lm-title">{{ $welcomeText }}</h1>
        </div>
    </div>

    <div class="lm-header-right">
        @if(Route::has('loan-management.loans.calculator') && \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
            <a href="{{ route('loan-management.loans.calculator', ['_lm_modal' => 1]) }}"
               class="btn btn-default btn-sm lm-header-action js-loan-calculator-modal"
               data-title="Installment Calculator">
                <i class="fa fa-calculator"></i> <span class="hidden-xs">Installment Calculator</span><span class="visible-xs-inline"> Calc</span>
            </a>
        @endif

        @if(Route::has('loan-management.loans.create-standalone-modal') && \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
            <button type="button" class="btn btn-success btn-sm lm-header-action lm-standalone-loan-trigger"
                    data-url="{{ route('loan-management.loans.create-standalone-modal') }}"
                    data-target="#standaloneLoanModal"
                    title="{{ $loanLanguage === 'km' ? 'បង្កើតកម្ចីថ្មី' : 'Create Installment' }}"
                    style="font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25); border-radius: 6px;">
                <i class="fa fa-plus-circle"></i>
                <span class="hidden-xs">{{ $loanLanguage === 'km' ? 'បង្កើតកម្ចី' : 'Create Installment' }}</span>
                <span class="visible-xs-inline">{{ $loanLanguage === 'km' ? 'បង្កើត' : 'Create' }}</span>
            </button>
        @endif

        @if(\Modules\LoanManagement\Services\BusinessSettingsService::isCmsEnabled())
            <a href="{{ config('loanmanagement.website_url') ?: url('/') }}" class="btn btn-info btn-sm lm-header-action" target="_blank" rel="noopener" title="Open home page">
                <i class="fa fa-globe"></i> <span class="hidden-xs">Website</span><span class="visible-xs-inline">Web</span>
            </a>
        @endif

        @if(Route::has('loan-management.language.switch'))
            <div class="lm-language-switch" title="Installment language">
                @foreach(['en' => 'EN', 'km' => 'ខ្មែរ'] as $languageKey => $languageLabel)
                    <form method="POST" action="{{ route('loan-management.language.switch') }}">
                        @csrf
                        <input type="hidden" name="language" value="{{ $languageKey }}">
                        <button type="submit" class="{{ $loanLanguage === $languageKey ? 'active' : '' }}" {{ $loanLanguage === $languageKey ? 'disabled' : '' }}>
                            {{ $languageLabel }}
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="dropdown lm-user-profile">
            <button type="button" class="lm-user-profile-toggle dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                @if($adminPhotoUrl)
                    <img src="{{ $adminPhotoUrl }}" class="lm-user-avatar" style="object-fit: cover;" alt="{{ $welcomeName }}">
                @else
                    <span class="lm-user-avatar">{{ $userInitial }}</span>
                @endif
                <span class="lm-user-profile-text">
                    <span class="lm-user-name">{{ $loanUser->username ?? $loanUser->first_name ?? 'Staff' }}</span>
                    @if(!empty($locationName))
                        <span class="lm-location">{{ $locationName }}</span>
                    @endif
                </span>
                <i class="fa fa-angle-down"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-right lm-user-profile-menu">
                <li class="lm-user-profile-summary" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px;">
                    @if($adminPhotoUrl)
                        <img src="{{ $adminPhotoUrl }}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="{{ $welcomeName }}">
                    @else
                        <span class="lm-user-avatar" style="width: 38px; height: 38px; font-size: 14px; flex-shrink: 0;">{{ $userInitial }}</span>
                    @endif
                    <div style="min-width: 0;">
                        <span class="lm-user-name" style="font-weight: 700; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $loanUser->username ?? $loanUser->first_name ?? 'Staff' }}</span>
                        @if(!empty($locationName))
                            <span class="lm-location" style="font-size: 11px; color: #64748b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $locationName }}</span>
                        @endif
                    </div>
                </li>
                <li role="separator" class="divider"></li>
                @if(\Illuminate\Support\Facades\Auth::guard('customer_loan')->check())
                    <li>
                        <a href="{{ route('loan-management.public.customer-dashboard') }}" style="color: #0284c7; font-weight: 600;">
                            <i class="fa fa-user-circle"></i> Customer Dashboard (Active)
                        </a>
                    </li>
                @endif
                @if(Route::has('loan-management.public.customer-login'))
                    <li>
                        <a href="{{ route('loan-management.public.customer-login') }}" onclick="event.preventDefault(); if(confirm('Are you sure you want to log out and switch to Customer Portal?')) { var form = document.getElementById('loanLogoutForm'); form.action = '{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}'; form.submit(); }">
                            <i class="fa fa-user"></i> Switch to Customer Login
                        </a>
                    </li>
                @endif
                @if(Route::has('login'))
                    <li>
                        <a href="{{ route('login') }}" onclick="event.preventDefault(); if(confirm('Are you sure you want to log out and switch admin account?')) { var form = document.getElementById('loanLogoutForm'); form.action = '{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('login')) }}'; form.submit(); }">
                            <i class="fa fa-refresh"></i> Switch / Other Admin
                        </a>
                    </li>
                @endif
                @if(\Modules\LoanManagement\Services\BusinessSettingsService::isCmsEnabled())
                    <li>
                        <a href="{{ config('loanmanagement.website_url') ?: url('/') }}">
                            <i class="fa fa-globe"></i> Website
                        </a>
                    </li>
                @endif
                @if (Route::has('logout'))
                    <li role="separator" class="divider"></li>
                    <li>
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); if(confirm('Are you sure you want to log out?')) { document.getElementById('loanLogoutForm').submit(); }" style="color: #dc2626; font-weight: 600;">
                            <i class="fa fa-sign-out"></i> Logout
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        @if (Route::has('logout'))
            <form id="loanLogoutForm" action="{{ route('logout') }}" method="POST" style="display:none;">
                @csrf
            </form>
        @endif
    </div>
</header>
