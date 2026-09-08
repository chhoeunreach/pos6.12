@php
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $businessName = \Modules\LoanManagement\Services\BusinessSettingsService::businessName();
    $themeColor = $settings['theme_color'] ?? '#2563eb';
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();

    $currentCustomer = Auth::guard('customer_loan')->user();
    $currentAdmin = Auth::guard('web')->user() ?? Auth::user();

    $primaryDemo = (isset($demoCustomers) && $demoCustomers->isNotEmpty()) ? $demoCustomers->first() : null;
    $defaultDemoPhone = $primaryDemo ? ($primaryDemo->phone ?: $primaryDemo->username) : '010111001';
    $defaultDemoName = $primaryDemo ? $primaryDemo->name : 'Sok Dara';
    $defaultDemoPassword = 'password';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Login · {{ $businessName }}</title>
    <style>
        :root { --primary: {{ $themeColor }}; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc; }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh; color: var(--ink); display: grid; place-items: center; padding: 24px 16px;
            background: radial-gradient(1200px 600px at 15% -10%, rgba(37,99,235,.12), transparent 60%),
                        radial-gradient(1000px 500px at 110% 110%, {{ $themeColor }}1c, transparent 55%), var(--soft);
        }
        @if ($loginBackgroundUrl)
        .page-bg {
            position: fixed; inset: 0; z-index: -1;
            background-image: url('{{ $loginBackgroundUrl }}');
            background-size: cover; background-position: center; background-repeat: no-repeat;
        }
        .page-bg::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(180deg, {{ $themeColor }}33, rgba(15,23,42,.72));
            backdrop-filter: blur(2px);
        }
        @else
        .page-bg::after {
            content: ""; position: fixed; inset: 0; z-index: -1;
            background: radial-gradient(1200px 600px at 15% -10%, rgba(37,99,235,.14), transparent 60%),
                        radial-gradient(1000px 500px at 110% 110%, {{ $themeColor }}22, transparent 55%), var(--soft);
        }
        @endif
        .shell { width: min(440px, 100%); }
        .card {
            background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden;
            box-shadow: 0 24px 70px -18px rgba(15,23,42,.18);
        }
        .brand-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 20px 24px 0; }
        .brand { display: inline-flex; align-items: center; gap: 12px; color: var(--ink); font-weight: 800; text-decoration: none; }
        .btn-back-home { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 13px; font-weight: 700; text-decoration: none; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--line); }
        .btn-back-home:hover { background: var(--soft); color: var(--ink); }
        .logo {
            width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
            overflow: hidden; background: {{ $themeColor }}14; color: var(--primary); border: 1px solid {{ $themeColor }}2e; flex: 0 0 auto;
        }
        .logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .portal-nav-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
            margin: 18px 0 0;
        }
        .portal-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
            text-decoration: none;
            transition: all .15s ease-in-out;
        }
        .portal-tab:hover {
            color: #0f172a;
            background: rgba(255,255,255,.6);
        }
        .portal-tab.active {
            background: #fff;
            color: #0f172a;
            box-shadow: 0 2px 8px rgba(15,23,42,.08);
        }
        .body { padding: 22px 26px 26px; }
        h1 { margin: 0; font-size: 22px; letter-spacing: -.3px; }
        .sub { margin: 8px 0 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .banner {
            margin: 18px 0 0; padding: 12px 14px; border-radius: 10px; font-size: 14px; line-height: 1.5;
        }
        .banner.error { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }
        .banner.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
        .banner.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
        .session-card {
            margin: 18px 0 0;
            padding: 12px 14px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 13px;
        }
        .session-card.customer {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        .session-card.admin {
            background: #fdf4ff;
            border: 1px solid #f5d0fe;
            color: #86198f;
        }
        .session-card-info strong { display: block; font-size: 13px; font-weight: 800; }
        .session-card-info span { display: block; font-size: 11px; opacity: .8; }
        .session-card-actions { display: flex; align-items: center; gap: 6px; }
        .session-btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            background: #fff;
            color: inherit;
            border: 1px solid currentColor;
            text-decoration: none;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
        }
        .session-btn:hover {
            opacity: .85;
        }
        form { margin-top: 20px; }
        .field { margin-bottom: 16px; }
        .field label { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #334155; font-size: 13px; font-weight: 700; }
        .field label svg { opacity: .7; }
        .control { position: relative; }
        input[type="text"], input[type="tel"], input[type="password"] {
            width: 100%; height: 48px; border: 1px solid var(--line); border-radius: 12px; padding: 0 42px 0 14px;
            font: inherit; font-size: 15px; background: #fff; color: var(--ink); transition: border-color .15s, box-shadow .15s; outline: none;
        }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px {{ $themeColor }}1f; }
        input::placeholder { color: #94a3b8; }
        .toggle-pw {
            position: absolute; right: 4px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px;
            border: 0; background: transparent; border-radius: 8px; color: #94a3b8; cursor: pointer; display: none; align-items: center; justify-content: center;
        }
        .toggle-pw:hover { color: #475569; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 20px; flex-wrap: wrap; }
        .remember { display: inline-flex; align-items: center; gap: 8px; color: #475569; font-size: 14px; cursor: pointer; user-select: none; }
        .remember input { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
        .button {
            width: 100%; height: 50px; border: 0; border-radius: 12px; background: var(--primary); color: #fff;
            font: inherit; font-size: 15px; font-weight: 800; cursor: pointer; letter-spacing: .2px;
            box-shadow: 0 12px 24px -8px {{ $themeColor }}66; transition: transform .1s, box-shadow .15s, opacity .15s;
        }
        .button:hover:not(:disabled) { box-shadow: 0 16px 30px -8px {{ $themeColor }}80; transform: translateY(-1px); }
        .button:disabled { opacity: .7; cursor: not-allowed; }
        .spinner { display: none; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: -3px; margin-right: 8px; }
        .button.loading .spinner { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--line); }
        .links { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .link {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;
            height: 46px; border-radius: 12px; font-size: 14px; font-weight: 700; transition: background .15s, border-color .15s;
        }
        .link.primary { color: var(--primary); border: 1px solid {{ $themeColor }}45; background: {{ $themeColor }}0a; }
        .link.primary:hover { background: {{ $themeColor }}14; }
        .link.ghost { color: #475569; border: 1px solid var(--line); }
        .link.ghost:hover { background: var(--soft); }
        .foot { text-align: center; margin-top: 18px; font-size: 12px; color: #94a3b8; }

        /* Demo Credentials Widget */
        .demo-box {
            margin-top: 18px;
            background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1.5px dashed #cbd5e1;
            border-radius: 14px;
            padding: 14px 16px;
            transition: border-color .2s, box-shadow .2s, transform .2s;
        }
        .demo-box:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px -6px rgba(15, 23, 42, .08);
        }
        .demo-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .demo-title {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #334155;
        }
        .demo-title svg { color: var(--primary); }
        .demo-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(37, 99, 235, .1);
            color: var(--primary);
            border: 1px solid rgba(37, 99, 235, .2);
        }
        .demo-customer-name {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #1e293b;
        }
        .demo-customer-name strong { font-weight: 800; }
        .demo-tag {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 6px;
            background: #dcfce7;
            color: #15803d;
            font-weight: 700;
        }
        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        @media (max-width: 360px) {
            .demo-grid { grid-template-columns: 1fr; }
        }
        .demo-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 7px 10px;
            cursor: pointer;
            transition: all .15s ease;
            user-select: none;
        }
        .demo-chip:hover {
            border-color: var(--primary);
            background: #f8fafc;
            transform: translateY(-1px);
        }
        .chip-content {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        .chip-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .chip-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btn-copy-chip {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            width: 26px;
            height: 26px;
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
        .demo-chip:hover .btn-copy-chip {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .demo-switch-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #e2e8f0;
            flex-wrap: wrap;
        }
        .demo-switch-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }
        .btn-acc-pill {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            transition: all .15s ease;
        }
        .btn-acc-pill:hover, .btn-acc-pill.active {
            background: rgba(37, 99, 235, .1);
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-demo-autofill {
            width: 100%;
            height: 42px;
            background: #fff;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            border-radius: 10px;
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
        .btn-demo-autofill:hover {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 6px 16px -4px rgba(37, 99, 235, .4);
            transform: translateY(-1px);
        }
        .btn-demo-autofill.filled {
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
        .session-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 13px;
            margin-top: 14px;
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
            body {
                min-height: 100dvh;
                align-items: start;
                padding: 14px 12px 22px;
            }
            .shell { width: 100%; }
            .card { border-radius: 16px; box-shadow: 0 18px 42px -20px rgba(15,23,42,.24); }
            .brand-row { padding: 18px 18px 0; }
            .brand {
                max-width: 100%;
                min-width: 0;
            }
            .brand > span:last-child {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .logo {
                width: 40px;
                height: 40px;
                border-radius: 10px;
            }
            .body { padding: 20px 18px 22px; }
            h1 { font-size: 21px; }
            .sub { font-size: 13px; line-height: 1.5; }
            form { margin-top: 18px; }
            .field { margin-bottom: 14px; }
            input[type="text"], input[type="tel"], input[type="password"] {
                height: 50px;
                border-radius: 11px;
                font-size: 16px;
            }
            .button { height: 50px; border-radius: 11px; }
            .demo-box {
                margin-top: 16px;
                padding: 13px;
                border-radius: 13px;
            }
            .demo-head {
                align-items: flex-start;
                gap: 8px;
            }
            .demo-title { min-width: 0; line-height: 1.35; }
            .demo-badge {
                flex: 0 0 auto;
                white-space: nowrap;
            }
            .demo-customer-name {
                flex-wrap: wrap;
                line-height: 1.35;
            }
            .demo-grid {
                grid-template-columns: 1fr;
                gap: 9px;
            }
            .demo-chip {
                min-height: 54px;
                padding: 9px 10px;
            }
            .chip-content { flex: 1 1 auto; }
            .chip-val {
                white-space: normal;
                overflow-wrap: anywhere;
                text-overflow: clip;
            }
            .btn-copy-chip {
                width: 32px;
                height: 32px;
            }
            .demo-switch-row { align-items: stretch; }
            .demo-switch-label { width: 100%; }
            .btn-acc-pill {
                min-height: 34px;
                flex: 1 1 calc(50% - 6px);
            }
            .btn-demo-autofill {
                min-height: 46px;
                height: auto;
                padding: 10px 12px;
                line-height: 1.3;
            }
            .links { gap: 8px; }
            .link { min-height: 46px; height: auto; padding: 10px 8px; line-height: 1.25; }
        }
        @media (max-width: 360px) {
            .body { padding: 18px 14px 20px; }
            .links { grid-template-columns: 1fr; }
            .demo-head { display: grid; grid-template-columns: 1fr; }
            .demo-badge { width: max-content; }
            .btn-acc-pill { flex-basis: 100%; }
        }
    </style>
</head>
<body>
    <div class="page-bg"></div>
    <div class="shell">
        <div class="card">
            <div class="brand-row">
                <a class="brand" href="{{ route('loan-management.public.home') }}">
                    <span class="logo">
                        @if($businessLogoUrl)<img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">@else{{ strtoupper(mb_substr($businessName, 0, 1)) }}@endif
                    </span>
                    <span>{{ $businessName }}</span>
                </a>
                <a href="{{ route('loan-management.public.home') }}" class="btn-back-home">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Website
                </a>
            </div>
            <div class="body">
                <div class="portal-nav-tabs">
                    <a href="{{ route('loan-management.public.customer-login') }}" class="portal-tab active">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Customer Portal
                    </a>
                    <a href="{{ route('login') }}" class="portal-tab" @if($currentCustomer) onclick="return confirm('You are currently signed in as Customer ({{ $currentCustomer->name }}). Are you sure you want to log out first to access Admin & Staff Login?');" @endif>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Admin & Staff
                    </a>
                </div>

                <div style="margin-top: 18px;">
                    <h1 style="margin: 0; font-size: 22px; letter-spacing: -.3px;">Customer Sign In</h1>
                    <p class="sub">Sign in to view your loans, installment payments, and profile.</p>
                </div>

                @if ($errors->any())
                    <div class="banner error">{{ $errors->first() }}</div>
                @elseif (session('status'))
                    <div class="banner success">{{ session('status') }}</div>
                @endif

                @if($currentCustomer)
                    <div class="session-card customer" style="border-left: 4px solid #2563eb;">
                        <div class="session-card-info">
                            <strong style="font-size: 13px;">⚠️ Signed in as Customer: {{ $currentCustomer->name }}</strong>
                            <span style="font-size: 12px; margin-top: 2px;">To switch accounts, please log out first.</span>
                        </div>
                        <div class="session-card-actions">
                            <a href="{{ route('loan-management.public.customer-dashboard') }}" class="session-btn">My Dashboard</a>
                            <form method="POST" action="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to log out from this customer account?');">
                                @csrf
                                <button type="submit" class="session-btn danger" title="Log out current customer">Log Out</button>
                            </form>
                        </div>
                    </div>
                @elseif($currentAdmin)
                    <div class="session-card admin" style="border-left: 4px solid #a855f7;">
                        <div class="session-card-info">
                            <strong style="font-size: 13px;">⚡ Signed in as Admin: {{ $currentAdmin->name ?? $currentAdmin->username ?? 'Staff' }}</strong>
                            <span style="font-size: 12px; margin-top: 2px;">To sign in as customer, please log out admin first.</span>
                        </div>
                        <div class="session-card-actions">
                            <a href="{{ route('loan-management.dashboard') }}" class="session-btn">Admin Dashboard</a>
                            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to log out from admin session?');">
                                @csrf
                                <button type="submit" class="session-btn danger" title="Log out admin session">Log Out Admin</button>
                            </form>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('loan-management.public.customer-login.store') }}" id="loginForm">
                    @csrf
                    <div class="field">
                        <label for="login">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            Phone or Username
                        </label>
                        <div class="control">
                            <input id="login" name="login" type="tel" value="{{ old('login') }}" placeholder="Phone number or username" required autofocus autocomplete="username" inputmode="tel" maxlength="255">
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Password
                        </label>
                        <div class="control">
                            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="255">
                            <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password">
                                <svg id="eyeOutline" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="eyeOff" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <label class="remember" for="remember">
                            <input type="checkbox" id="remember" name="remember" value="1">
                            Keep me signed in
                        </label>
                    </div>
                    <button class="button" type="submit" id="submitBtn">
                        <span class="spinner"></span><span id="submitLabel">Sign in</span>
                    </button>

                    <!-- Demo Customer Credentials (Click to Auto-Fill & Copy) -->
                    @if(!empty($settings['demo_customer_login_enabled']))
                    <div class="demo-box" id="demoBox">
                        <div class="demo-head">
                            <div class="demo-title">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                <span>Demo Customer Login</span>
                            </div>
                            <span class="demo-badge">1-Click Demo</span>
                        </div>

                        <div class="demo-customer-name">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <strong id="demoCustomerNameDisplay">{{ $defaultDemoName }}</strong>
                            <span class="demo-tag">Active Customer</span>
                        </div>

                        <div class="demo-grid">
                            <div class="demo-chip" id="chipPhone" data-field="login" data-val="{{ $defaultDemoPhone }}" title="Click to copy & fill Phone">
                                <div class="chip-content">
                                    <span class="chip-label">Phone / User</span>
                                    <span class="chip-val" id="demoPhoneDisplay">{{ $defaultDemoPhone }}</span>
                                </div>
                                <button type="button" class="btn-copy-chip" title="Copy Phone" aria-label="Copy Phone">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </button>
                            </div>

                            <div class="demo-chip" id="chipPass" data-field="password" data-val="{{ $defaultDemoPassword }}" title="Click to copy & fill Password">
                                <div class="chip-content">
                                    <span class="chip-label">Password</span>
                                    <span class="chip-val" id="demoPassDisplay">{{ $defaultDemoPassword }}</span>
                                </div>
                                <button type="button" class="btn-copy-chip" title="Copy Password" aria-label="Copy Password">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </button>
                            </div>
                        </div>

                        @if(isset($demoCustomers) && count($demoCustomers) > 1)
                        <div class="demo-switch-row">
                            <span class="demo-switch-label">Other accounts:</span>
                            @foreach($demoCustomers as $idx => $dc)
                                @php
                                    $dcPhone = $dc->phone ?: $dc->username;
                                @endphp
                                <button type="button" class="btn-acc-pill {{ $idx === 0 ? 'active' : '' }}"
                                        data-name="{{ $dc->name }}"
                                        data-phone="{{ $dcPhone }}"
                                        data-pass="{{ $defaultDemoPassword }}">
                                    {{ $dc->name }}
                                </button>
                            @endforeach
                        </div>
                        @endif

                        <button type="button" class="btn-demo-autofill" id="btnQuickFillDemo">
                            <svg id="btnFillIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span id="btnFillText">Click to Copy & Auto-Fill Demo</span>
                        </button>
                    </div>
                    @endif
                </form>

                <div class="divider">Switch or Explore</div>
                <div class="links">
                    <a class="link primary" href="{{ route('loan-management.public.register') }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                        Create account
                    </a>
                    <a class="link ghost" href="{{ route('login') }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Admin Login
                    </a>
                </div>
            </div>
        </div>
        <p class="foot">&copy; {{ date('Y') }} {{ $businessName }}. All rights reserved.</p>
    </div>

    <script>
        (function () {
            var pw = document.getElementById('password');
            var btn = document.getElementById('togglePw');
            var eyeOn = document.getElementById('eyeOutline');
            var eyeOff = document.getElementById('eyeOff');
            btn.style.display = 'inline-flex';
            btn.addEventListener('click', function () {
                var showing = pw.type === 'text';
                pw.type = showing ? 'password' : 'text';
                eyeOn.style.display = showing ? '' : 'none';
                eyeOff.style.display = showing ? 'none' : '';
                pw.focus();
            });

            var form = document.getElementById('loginForm');
            var submit = document.getElementById('submitBtn');
            var hasAdminSession = @json((bool)$currentAdmin);
            var adminName = @json($currentAdmin ? ($currentAdmin->name ?? $currentAdmin->username ?? 'Staff') : '');
            var hasCustomerSession = @json((bool)$currentCustomer);
            var customerName = @json($currentCustomer ? $currentCustomer->name : '');

            form.addEventListener('submit', function (e) {
                if (hasAdminSession) {
                    var msg = adminName
                        ? "You are currently logged in as Admin (" + adminName + "). Are you sure you want to log out from your admin account and continue to Customer login?"
                        : "You are currently logged in as Admin. Are you sure you want to log out and sign in as Customer?";
                    if (!confirm(msg)) {
                        e.preventDefault();
                        return false;
                    }
                }
                submit.classList.add('loading');
                submit.disabled = true;
            });

            // If customer is active and clicks any Admin Login link/tab, confirm logout & redirect to that link direction
            var adminLinks = document.querySelectorAll('a[href="{{ route('login') }}"], a[href*="/login"]');
            adminLinks.forEach(function (link) {
                var href = link.getAttribute('href') || '';
                if (href.indexOf('login') !== -1 && href.indexOf('customer') === -1) {
                    link.addEventListener('click', function (e) {
                        if (hasCustomerSession) {
                            e.preventDefault();
                            var targetHref = link.href;
                            var msg = customerName
                                ? "You are currently logged in as Customer (" + customerName + "). Are you sure you want to log out and go to Admin Login?"
                                : "You are currently logged in as a Customer. Are you sure you want to log out and go to Admin Login?";
                            if (confirm(msg)) {
                                window.location.href = '{{ route('loan-management.public.customer-logout') }}?redirect=' + encodeURIComponent(targetHref);
                            }
                        }
                    });
                }
            });

            // Demo Auto-Fill & Copy Logic
            var loginInput = document.getElementById('login');
            var passwordInput = document.getElementById('password');
            var fillBtn = document.getElementById('btnQuickFillDemo');
            var fillText = document.getElementById('btnFillText');
            var fillIcon = document.getElementById('btnFillIcon');
            var customerNameEl = document.getElementById('demoCustomerNameDisplay');
            var phoneDisplay = document.getElementById('demoPhoneDisplay');
            var passDisplay = document.getElementById('demoPassDisplay');

            var currentCredentials = {
                phone: @json($defaultDemoPhone),
                password: @json($defaultDemoPassword),
                name: @json($defaultDemoName)
            };

            function copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text).catch(function () {
                        fallbackCopy(text);
                    });
                } else {
                    fallbackCopy(text);
                }
            }

            function fallbackCopy(text) {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                } catch (e) {}
                document.body.removeChild(textArea);
            }

            function autofillAndCopy(creds, showFeedback) {
                if (!loginInput || !passwordInput) return;

                loginInput.value = creds.phone;
                passwordInput.value = creds.password;

                loginInput.dispatchEvent(new Event('input', { bubbles: true }));
                loginInput.dispatchEvent(new Event('change', { bubbles: true }));
                passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                passwordInput.dispatchEvent(new Event('change', { bubbles: true }));

                copyToClipboard(creds.phone + ' / ' + creds.password);

                loginInput.classList.remove('demo-fill-highlight');
                passwordInput.classList.remove('demo-fill-highlight');
                void loginInput.offsetWidth;
                loginInput.classList.add('demo-fill-highlight');
                passwordInput.classList.add('demo-fill-highlight');

                setTimeout(function () {
                    loginInput.classList.remove('demo-fill-highlight');
                    passwordInput.classList.remove('demo-fill-highlight');
                }, 1500);

                if (showFeedback && fillBtn && fillText) {
                    var prevText = fillText.textContent;
                    fillBtn.classList.add('filled');
                    fillText.textContent = '✓ Completed & Copied to Form!';
                    setTimeout(function () {
                        fillBtn.classList.remove('filled');
                        fillText.textContent = prevText;
                    }, 2400);
                }
            }

            if (fillBtn) {
                fillBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    autofillAndCopy(currentCredentials, true);
                });
            }

            var chips = document.querySelectorAll('.demo-chip');
            chips.forEach(function (chip) {
                chip.addEventListener('click', function (e) {
                    var targetField = chip.getAttribute('data-field');
                    var val = chip.getAttribute('data-val');
                    if (targetField === 'login' && loginInput) {
                        loginInput.value = val;
                        loginInput.dispatchEvent(new Event('input', { bubbles: true }));
                        loginInput.dispatchEvent(new Event('change', { bubbles: true }));
                        loginInput.classList.add('demo-fill-highlight');
                        setTimeout(function () { loginInput.classList.remove('demo-fill-highlight'); }, 1200);
                        copyToClipboard(val);
                        showChipFeedback(chip, 'Phone Copied & Filled!');
                    } else if (targetField === 'password' && passwordInput) {
                        passwordInput.value = val;
                        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                        passwordInput.dispatchEvent(new Event('change', { bubbles: true }));
                        passwordInput.classList.add('demo-fill-highlight');
                        setTimeout(function () { passwordInput.classList.remove('demo-fill-highlight'); }, 1200);
                        copyToClipboard(val);
                        showChipFeedback(chip, 'Password Copied & Filled!');
                    }
                });
            });

            function showChipFeedback(chip, text) {
                var valEl = chip.querySelector('.chip-val');
                if (!valEl) return;
                var prevText = valEl.textContent;
                valEl.textContent = text;
                valEl.style.color = '#10b981';
                setTimeout(function () {
                    valEl.textContent = prevText;
                    valEl.style.color = '';
                }, 1600);
            }

            var accPills = document.querySelectorAll('.btn-acc-pill');
            accPills.forEach(function (pill) {
                pill.addEventListener('click', function (e) {
                    e.preventDefault();
                    accPills.forEach(function (p) { p.classList.remove('active'); });
                    pill.classList.add('active');

                    var name = pill.getAttribute('data-name');
                    var phone = pill.getAttribute('data-phone');
                    var pass = pill.getAttribute('data-pass');

                    currentCredentials = { phone: phone, password: pass, name: name };

                    if (customerNameEl) customerNameEl.textContent = name;
                    if (phoneDisplay) phoneDisplay.textContent = phone;
                    if (passDisplay) passDisplay.textContent = pass;

                    var phoneChip = document.getElementById('chipPhone');
                    autofillAndCopy(currentCredentials, true);
                });
            });

            // Form Submit Session Confirmation
            var loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    @if($currentCustomer)
                        if (!confirm('You are currently signed in as Customer ({{ $currentCustomer->name }}).\n\nProceeding will log out your current session and sign in with the new credentials. Are you sure you want to continue?')) {
                            e.preventDefault();
                            var submitBtn = loginForm.querySelector('.button');
                            if (submitBtn) {
                                submitBtn.classList.remove('loading');
                                submitBtn.disabled = false;
                            }
                            return false;
                        }
                    @elseif($currentAdmin)
                        if (!confirm('You are currently signed in as Administrator ({{ $currentAdmin->name ?? $currentAdmin->username ?? 'Staff' }}).\n\nProceeding will log out your admin session and sign in to the Customer Portal. Are you sure?')) {
                            e.preventDefault();
                            var submitBtn = loginForm.querySelector('.button');
                            if (submitBtn) {
                                submitBtn.classList.remove('loading');
                                submitBtn.disabled = false;
                            }
                            return false;
                        }
                    @endif
                });
            }
        })();
    </script>
</body>
</html>
