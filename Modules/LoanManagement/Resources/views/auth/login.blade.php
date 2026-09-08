@php
    $businessSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();
    $businessName = $businessSettings['business_name'] ?: 'Installment Management';
    $systemName = $businessSettings['system_name'] ?: 'Installment Management';
    $systemSubtitle = $businessSettings['system_subtitle'] ?: 'Dedicated loan operation workspace';
    $isDemoAdminEnabled = !empty($businessSettings['demo_admin_login_enabled']);

    $defaultAdminLogin = 'admin@example.com';
    $defaultAdminPass = 'password';
    if ($isDemoAdminEnabled) {
        try {
            $demoAdmin = \App\User::where('status', 'active')->where('allow_login', 1)->first();
            if ($demoAdmin) {
                $defaultAdminLogin = $demoAdmin->email ?: $demoAdmin->username ?: 'admin@example.com';
            }
        } catch (\Throwable $e) {}
    }

    $currentAdmin = Auth::guard('web')->user() ?? Auth::user();
    $currentCustomer = Auth::guard('customer_loan')->user();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ $systemName }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <style>
        :root {
            --login-primary: {{ $businessSettings['theme_color'] ?? '#2563eb' }};
            @if($loginBackgroundUrl)
                --login-background: url('{{ $loginBackgroundUrl }}');
            @endif
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body { min-height: 100vh; margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111827; background: #eef3f8; }
        .login-shell {
            min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1fr) minmax(430px, .82fr);
            background: linear-gradient(135deg, #0f172a 0%, #18324f 48%, #e9eef5 48%, #f8fafc 100%);
            isolation: isolate;
        }
        .login-shell.has-photo { background-image: linear-gradient(90deg, rgba(7, 18, 33, .88), rgba(7, 18, 33, .54) 52%, rgba(248, 250, 252, .96) 52%), var(--login-background); background-size: cover; background-position: center; }
        .login-brand-panel { min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; padding: 42px 54px; color: #fff; }
        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: 0;
        }
        .brand-logo {
            width: 46px;
            height: 46px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .28);
            color: #fff;
            font-size: 19px;
        }
        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .brand-copy { max-width: 650px; padding-bottom: 34px; }
        .brand-copy h1 {
            margin: 0;
            font-size: 48px;
            line-height: 1.08;
            font-weight: 900;
            letter-spacing: 0;
        }
        .brand-copy p {
            max-width: 520px;
            margin: 18px 0 0;
            color: rgba(255, 255, 255, .82);
            font-size: 16px;
            line-height: 1.7;
        }
        .brand-points { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 28px; }
        .brand-point { min-height: 92px; padding: 14px; border: 1px solid rgba(255,255,255,.20); border-radius: 8px; background: rgba(255,255,255,.10); backdrop-filter: blur(12px); }
        .brand-point strong { display: block; font-size: 18px; }
        .brand-point span { display: block; margin-top: 6px; color: rgba(255,255,255,.76); font-size: 12px; line-height: 1.45; }
        .login-panel { min-height: 100vh; display: grid; place-items: center; padding: 36px; background: rgba(248, 250, 252, .90); backdrop-filter: blur(18px); }
        .login-box {
            width: min(440px, 100%);
            padding: 32px;
            background: rgba(255,255,255,.98);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .14);
        }
        .login-box-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .login-box-logo { width: 44px; height: 44px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; background: #eef4fb; color: var(--login-primary); border: 1px solid #dbe4ef; font-weight: 900; flex: 0 0 auto; }
        .login-box-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .login-title {
            margin: 0;
            color: #0f172a;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0;
        }
        .login-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            margin-bottom: 5px;
            padding: 0 8px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--login-primary) 10%, #fff);
            color: var(--login-primary);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .login-subtitle {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .login-box label {
            margin-bottom: 7px;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .login-box .form-control {
            height: 46px;
            border-color: #dbe4ef;
            border-radius: 6px;
            box-shadow: none;
            color: #0f172a;
            font-size: 14px;
        }
        .login-password-row { position: relative; }
        .login-password-row .form-control { padding-right: 84px; }
        .login-password-toggle { position: absolute; right: 6px; top: 6px; height: 34px; min-width: 68px; border: 0; border-radius: 6px; background: #eef4fb; color: #334155; font-weight: 800; font-size: 12px; }
        .login-box .form-control:focus {
            border-color: var(--login-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--login-primary) 16%, transparent);
        }
        .portal-nav-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #edf2f7;
            border-radius: 10px;
            padding: 4px;
            gap: 4px;
            margin-bottom: 20px;
        }
        .portal-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 12px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
            text-decoration: none;
            transition: all .15s ease-in-out;
        }
        .portal-tab:hover {
            color: #0f172a;
            background: rgba(255,255,255,.6);
            text-decoration: none;
        }
        .portal-tab.active {
            background: #fff;
            color: #0f172a;
            box-shadow: 0 2px 8px rgba(15,23,42,.08);
            text-decoration: none;
        }
        .session-card {
            margin: 0 0 16px;
            padding: 9px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 13px;
        }
        .session-card.customer { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .session-card.admin { background: #fdf4ff; border: 1px solid #f5d0fe; color: #86198f; }
        .session-card-info { min-width: 0; }
        .session-card-info strong { display: block; font-size: 13px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .session-card-info span { display: block; font-size: 11px; opacity: .8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .session-card-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .session-btn {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 6px;
            background: #fff;
            color: inherit;
            border: 1px solid currentColor;
            text-decoration: none;
            font-weight: 800;
            font-size: 11px;
            white-space: nowrap;
            cursor: pointer;
            transition: opacity .15s;
        }
        .session-btn:hover { opacity: .85; text-decoration: none; color: inherit; }
        .session-btn.danger { border-color: #fca5a5; color: #dc2626; background: #fff; }
        .session-btn.danger:hover { background: #fef2f2; color: #b91c1c; }
        @media (max-width: 520px) {
            .session-card { flex-direction: column; align-items: flex-start; gap: 8px; }
            .session-card-actions { width: 100%; justify-content: flex-end; }
        }

        /* Demo Admin Credentials Widget */
        .demo-admin-box {
            margin-top: 18px;
            background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1.5px dashed #cbd5e1;
            border-radius: 12px;
            padding: 14px 16px;
            transition: border-color .2s, box-shadow .2s, transform .2s;
            text-align: left;
        }
        .demo-admin-box:hover {
            border-color: var(--login-primary);
            box-shadow: 0 8px 24px -6px rgba(15, 23, 42, .08);
        }
        .demo-admin-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .demo-admin-title {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #334155;
        }
        .demo-admin-title svg { color: var(--login-primary); }
        .demo-admin-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(37, 99, 235, .1);
            color: var(--login-primary);
            border: 1px solid rgba(37, 99, 235, .2);
        }
        .demo-admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        @media (max-width: 360px) {
            .demo-admin-grid { grid-template-columns: 1fr; }
        }
        .demo-admin-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 7px 10px;
            cursor: pointer;
            transition: all .15s ease;
            user-select: none;
        }
        .demo-admin-chip:hover {
            border-color: var(--login-primary);
            background: #f8fafc;
            transform: translateY(-1px);
        }
        .chip-admin-content {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        .chip-admin-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .chip-admin-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btn-copy-admin-chip {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all .15s ease;
            margin-left: 6px;
            padding: 0;
        }
        .demo-admin-chip:hover .btn-copy-admin-chip {
            background: var(--login-primary);
            border-color: var(--login-primary);
            color: #fff;
        }
        .btn-demo-admin-autofill {
            width: 100%;
            height: 40px;
            background: #fff;
            border: 1.5px solid var(--login-primary);
            color: var(--login-primary);
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .2s ease;
            box-shadow: 0 2px 8px -2px rgba(37, 99, 235, .15);
        }
        .btn-demo-admin-autofill:hover {
            background: var(--login-primary);
            color: #fff;
            box-shadow: 0 6px 16px -4px rgba(37, 99, 235, .4);
            transform: translateY(-1px);
        }
        .btn-demo-admin-autofill.filled {
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: #fff !important;
            box-shadow: 0 6px 16px -4px rgba(16, 185, 129, .5) !important;
        }
        .demo-fill-highlight {
            animation: inputPulse .7s ease-in-out;
            border-color: #10b981 !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, .25) !important;
        }
        @keyframes inputPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .login-options { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 16px; color: #64748b; font-size: 12px; }
        .login-options label { display: inline-flex; align-items: center; gap: 7px; margin: 0; color: #475569; font-size: 12px; text-transform: none; }
        .login-button {
            height: 46px;
            margin-top: 8px;
            border: 0;
            border-radius: 6px;
            background: var(--login-primary);
            font-weight: 800;
            box-shadow: 0 12px 28px color-mix(in srgb, var(--login-primary) 28%, transparent);
        }
        .login-button:hover,
        .login-button:focus {
            filter: brightness(.96);
            background: var(--login-primary);
        }
        .login-meta { margin-top: 18px; color: #94a3b8; font-size: 12px; text-align: center; }
        .login-links { margin-top: 16px; display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; }
        .login-links a { color: #475569; font-size: 12px; font-weight: 800; text-decoration: none; }
        .login-links a:hover { color: var(--login-primary); }
        @supports not (color: color-mix(in srgb, #000 10%, transparent)) {
            .login-box .form-control:focus { box-shadow: 0 0 0 3px rgba(37, 99, 235, .14); }
            .login-button { box-shadow: 0 12px 28px rgba(37, 99, 235, .24); }
        }
        @media (max-width: 900px) {
            html, body { min-height: 100dvh; }
            body { overflow-x: hidden; }
            .login-shell {
                display: block;
                grid-template-columns: 1fr;
                min-height: 100dvh;
                background: #f8fafc;
            }
            .login-brand-panel { display: none; }
            .login-panel {
                min-height: auto;
                padding: 24px 16px;
                background: transparent;
                backdrop-filter: none;
                place-items: start center;
            }
            .login-box {
                padding: 28px 22px;
                margin-top: -8px;
                border-radius: 18px;
                border-color: rgba(226, 232, 240, .92);
            }
            .login-box .form-control,
            .login-button { height: 48px; border-radius: 10px; font-size: 15px; }
            .login-button { margin-top: 4px; box-shadow: 0 12px 22px rgba(37, 99, 235, .18); }
            .login-password-row .form-control { padding-right: 74px; }
            .login-password-toggle { right: 7px; top: 7px; height: 34px; min-width: 58px; border-radius: 8px; }
        }
        @media (max-width: 560px) {
            .login-links {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin-top: 12px;
            }
            .login-links a {
                min-height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 10px;
                border: 1px solid #e2e8f0;
                border-radius: 9px;
                background: #fff;
                text-align: center;
            }
        }
        @media (max-width: 360px) {
            .login-box { padding: 20px 14px; }
        }
    </style>
</head>
<body>
    <main class="login-shell {{ $loginBackgroundUrl ? 'has-photo' : '' }}">
        <section class="login-brand-panel">
            <div class="brand-mark">
                <span class="brand-logo">
                    @if($businessLogoUrl)
                        <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">
                    @else
                        <span>{{ strtoupper(mb_substr($businessName, 0, 1)) }}</span>
                    @endif
                </span>
                <span>{{ $businessName }}</span>
            </div>
            <div class="brand-copy">
                <h1>{{ $systemName }}</h1>
                <p>{{ $systemSubtitle }}</p>
                <div class="brand-points" aria-label="Workspace highlights">
                    <div class="brand-point">
                        <strong>Installments</strong>
                        <span>Approve, monitor, and collect installments from one workspace.</span>
                    </div>
                    <div class="brand-point">
                        <strong>Customers</strong>
                        <span>Keep customer profiles, documents, and chats organized.</span>
                    </div>
                    <div class="brand-point">
                        <strong>Reports</strong>
                        <span>Review payments, balances, and daily collection activity.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-box">
                <div class="login-box-head">
                    <span class="login-box-logo">
                        @if($businessLogoUrl)
                            <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">
                        @else
                            <span>{{ strtoupper(mb_substr($businessName, 0, 1)) }}</span>
                        @endif
                    </span>
                    <div>
                        <span class="login-kicker">Admin Portal</span>
                        <h2 class="login-title">Admin Login</h2>
                        <p class="login-subtitle">Sign in to continue to your secure workspace.</p>
                    </div>
                </div>

                <div class="portal-nav-tabs">
                    @if(Route::has('loan-management.public.customer-login'))
                        <a href="{{ route('loan-management.public.customer-login') }}" class="portal-tab" @if($currentAdmin) onclick="return confirm('You are currently signed in as Administrator ({{ $currentAdmin->name ?? $currentAdmin->username ?? 'Staff' }}). Are you sure you want to log out first to access Customer Portal?');" @endif>
                            👤 Customer Portal
                        </a>
                    @endif
                    <a href="{{ route('login') }}" class="portal-tab active">
                        ⚡ Admin & Staff
                    </a>
                </div>

                @if($currentAdmin)
                    <div class="session-card admin" style="border-left: 4px solid #a855f7;">
                        <div class="session-card-info">
                            <strong style="font-size: 13px;">⚡ Signed in as Admin: {{ $currentAdmin->name ?? $currentAdmin->username ?? 'Staff' }}</strong>
                            <span style="font-size: 12px; margin-top: 2px;">To switch accounts, please log out first.</span>
                        </div>
                        <div class="session-card-actions">
                            <a href="{{ route('loan-management.dashboard') }}" class="session-btn">Admin Panel</a>
                            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('login')) }}" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to log out from admin session?');">
                                @csrf
                                <button type="submit" class="session-btn danger" title="Log out admin">Log Out</button>
                            </form>
                        </div>
                    </div>
                @elseif($currentCustomer)
                    <div class="session-card customer" style="border-left: 4px solid #2563eb;">
                        <div class="session-card-info">
                            <strong style="font-size: 13px;">👤 Signed in as Customer: {{ $currentCustomer->name }}</strong>
                            <span style="font-size: 12px; margin-top: 2px;">To sign in as Admin, please log out customer first.</span>
                        </div>
                        <div class="session-card-actions">
                            <a href="{{ route('loan-management.public.customer-dashboard') }}" class="session-btn">Customer Dashboard</a>
                            <form method="POST" action="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('login')) }}" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to log out from your customer account?');">
                                @csrf
                                <button type="submit" class="session-btn danger" title="Log out customer">Log Out Customer</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="post" action="{{ route('login') }}" id="adminLoginForm">
                    @csrf
                    <div class="form-group">
                        <label for="email">Email or username</label>
                        <input id="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Enter email or username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="login-password-row">
                            <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password" placeholder="Enter password">
                            <button type="button" class="login-password-toggle" id="loginPasswordToggle">Show</button>
                        </div>
                    </div>
                    <div class="login-options">
                        <label><input type="checkbox" name="remember" value="1"> Remember me</label>
                    </div>
                    <button class="btn btn-primary btn-block login-button" type="submit">Sign In</button>
                </form>

                <div class="login-meta">{{ $businessName }} secure workspace</div>
                <div class="login-links">
                    @if(Route::has('loan-management.public.customer-login'))
                        <a href="{{ route('loan-management.public.customer-login') }}">Customer Login</a>
                    @endif
                    @if(\Modules\LoanManagement\Services\BusinessSettingsService::isCmsEnabled())
                        <a href="{{ route('loan-management.public.home') }}">Website</a>
                    @endif
                </div>

                @if($isDemoAdminEnabled)
                    <div class="demo-admin-box" id="demoAdminBox">
                        <div class="demo-admin-head">
                            <div class="demo-admin-title">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                <span>Demo Admin Login</span>
                            </div>
                            <span class="demo-admin-badge">1-Click Demo</span>
                        </div>

                        <div class="demo-admin-grid">
                            <div class="demo-admin-chip" id="chipAdminLogin" data-field="email" data-val="{{ $defaultAdminLogin }}" title="Click to copy & fill Login">
                                <div class="chip-admin-content">
                                    <span class="chip-admin-label">Email / User</span>
                                    <span class="chip-admin-val">{{ $defaultAdminLogin }}</span>
                                </div>
                                <button type="button" class="btn-copy-admin-chip" title="Copy User">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </button>
                            </div>

                            <div class="demo-admin-chip" id="chipAdminPass" data-field="password" data-val="{{ $defaultAdminPass }}" title="Click to copy & fill Password">
                                <div class="chip-admin-content">
                                    <span class="chip-admin-label">Password</span>
                                    <span class="chip-admin-val">{{ $defaultAdminPass }}</span>
                                </div>
                                <button type="button" class="btn-copy-admin-chip" title="Copy Password">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </button>
                            </div>
                        </div>

                        <button type="button" class="btn-demo-admin-autofill" id="btnQuickFillAdminDemo">
                            <svg id="btnAdminFillIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span id="btnAdminFillText">Click to Copy & Auto-Fill Admin</span>
                        </button>
                    </div>
                @endif
            </div>
        </section>
    </main>
    <script>
        (function () {
            var adminLoginForm = document.getElementById('adminLoginForm');
            var hasCustomerSession = @json((bool)$currentCustomer);
            var customerName = @json($currentCustomer ? $currentCustomer->name : '');
            var hasAdminSession = @json((bool)$currentAdmin);
            var adminName = @json($currentAdmin ? ($currentAdmin->name ?? $currentAdmin->username ?? 'Staff') : '');

            if (adminLoginForm) {
                adminLoginForm.addEventListener('submit', function (e) {
                    if (hasCustomerSession) {
                        var msg = customerName
                            ? "You are currently logged in as Customer (" + customerName + "). Are you sure you want to log out from your customer account and continue to Admin login?"
                            : "You are currently logged in as a Customer. Are you sure you want to log out and sign in as Admin?";
                        if (!confirm(msg)) {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            }

            // If admin is active and clicks any Customer Portal link, confirm logout & redirect to that link
            var customerPortalLinks = document.querySelectorAll('a[href*="customer/login"], a[href*="customer-login"]');
            customerPortalLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    if (hasAdminSession) {
                        e.preventDefault();
                        var targetHref = link.href;
                        var msg = adminName
                            ? "You are currently logged in as Admin (" + adminName + "). Are you sure you want to log out and go to Customer Portal?"
                            : "You are currently logged in as Admin. Are you sure you want to log out and go to Customer Portal?";
                        if (confirm(msg)) {
                            var logoutForm = document.createElement('form');
                            logoutForm.method = 'POST';
                            logoutForm.action = '{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect=' + encodeURIComponent(targetHref);
                            var csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = '{{ csrf_token() }}';
                            logoutForm.appendChild(csrfInput);
                            var redirectInput = document.createElement('input');
                            redirectInput.type = 'hidden';
                            redirectInput.name = 'redirect';
                            redirectInput.value = targetHref;
                            logoutForm.appendChild(redirectInput);
                            document.body.appendChild(logoutForm);
                            logoutForm.submit();
                        }
                    }
                });
            });

            var password = document.getElementById('password');
            var toggle = document.getElementById('loginPasswordToggle');
            if (toggle && password) {
                toggle.addEventListener('click', function () {
                    var showing = password.type === 'text';
                    password.type = showing ? 'password' : 'text';
                    toggle.textContent = showing ? 'Show' : 'Hide';
                });
            }

            var btnQuickFillAdmin = document.getElementById('btnQuickFillAdminDemo');
            var emailInput = document.getElementById('email');
            var passwordInput = document.getElementById('password');

            function applyDemoAdminCredentials(userVal, passVal) {
                if (emailInput) {
                    emailInput.value = userVal;
                    emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                    emailInput.classList.remove('demo-fill-highlight');
                    void emailInput.offsetWidth;
                    emailInput.classList.add('demo-fill-highlight');
                }
                if (passwordInput) {
                    passwordInput.value = passVal;
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                    passwordInput.classList.remove('demo-fill-highlight');
                    void passwordInput.offsetWidth;
                    passwordInput.classList.add('demo-fill-highlight');
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(userVal + "\n" + passVal).catch(function () {});
                }
            }

            if (btnQuickFillAdmin) {
                btnQuickFillAdmin.addEventListener('click', function () {
                    var userVal = @json($defaultAdminLogin);
                    var passVal = @json($defaultAdminPass);
                    applyDemoAdminCredentials(userVal, passVal);

                    var btnText = document.getElementById('btnAdminFillText');
                    if (btnText) {
                        var origText = btnText.textContent;
                        btnText.textContent = '✓ Admin Credentials Filled!';
                        btnQuickFillAdmin.classList.add('filled');
                        setTimeout(function () {
                            btnText.textContent = origText;
                            btnQuickFillAdmin.classList.remove('filled');
                        }, 2000);
                    }
                });
            }

            var adminChips = document.querySelectorAll('.demo-admin-chip');
            adminChips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var field = chip.getAttribute('data-field');
                    var val = chip.getAttribute('data-val') || '';
                    if (field === 'email' && emailInput) {
                        emailInput.value = val;
                        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                        emailInput.classList.remove('demo-fill-highlight');
                        void emailInput.offsetWidth;
                        emailInput.classList.add('demo-fill-highlight');
                        emailInput.focus();
                    } else if (field === 'password' && passwordInput) {
                        passwordInput.value = val;
                        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                        passwordInput.classList.remove('demo-fill-highlight');
                        void passwordInput.offsetWidth;
                        passwordInput.classList.add('demo-fill-highlight');
                        passwordInput.focus();
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(val).catch(function () {});
                    }
                });
            });
        })();
    </script>
</body>
</html>
