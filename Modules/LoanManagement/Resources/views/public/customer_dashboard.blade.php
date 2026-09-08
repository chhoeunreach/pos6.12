@php
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $businessName = \Modules\LoanManagement\Services\BusinessSettingsService::businessName();
    $systemSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
    $themeColor = $systemSettings['theme_color'] ?? '#2563eb';
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();
    $money = function ($value) { return '$' . number_format((float) ($value ?? 0), 2); };
    $displayName = trim((string) ($customer->khmer_name ?? '')) ?: trim((string) ($customer->name ?? 'Customer'));
    $adminUser = Auth::guard('web')->user() ?? Auth::user();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Dashboard - {{ $businessName }}</title>
    <style>
        :root {
            --public-primary: {{ $themeColor }};
            --primary-dark: color-mix(in srgb, {{ $themeColor }} 80%, #000);
            --primary-light: color-mix(in srgb, {{ $themeColor }} 10%, #fff);
            --primary-border: color-mix(in srgb, {{ $themeColor }} 25%, transparent);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            @if($loginBackgroundUrl)
                --page-bg-img: url('{{ $loginBackgroundUrl }}');
            @endif
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            @if($loginBackgroundUrl)
                background: linear-gradient(180deg, rgba(241, 245, 249, 0.94) 0%, rgba(241, 245, 249, 0.98) 100%), var(--page-bg-img);
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
            @else
                background: radial-gradient(1200px 700px at 15% 0%, color-mix(in srgb, {{ $themeColor }} 16%, #f8fafc) 0%, transparent 60%),
                            radial-gradient(1000px 500px at 85% 10%, color-mix(in srgb, {{ $themeColor }} 12%, #f1f5f9) 0%, transparent 55%),
                            radial-gradient(1100px 600px at 50% 100%, color-mix(in srgb, {{ $themeColor }} 8%, #ffffff) 0%, transparent 65%),
                            #f0f4f8;
                background-attachment: fixed;
            @endif
            color: var(--ink);
            line-height: 1.5;
            min-height: 100vh;
        }
        .topbar { background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(12px); border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(15,23,42,.03); }
        .topbar-inner { width: min(1200px, calc(100% - 32px)); margin: 0 auto; min-height: 66px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--ink); font-weight: 800; text-decoration: none; font-size: 16px; }
        .logo { width: 38px; height: 38px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; background: color-mix(in srgb, {{ $themeColor }} 12%, #fff); color: var(--public-primary); border: 1px solid color-mix(in srgb, {{ $themeColor }} 25%, transparent); }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .topbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .btn-topbar { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--line); background: #fff; color: #475569; border-radius: 8px; height: 38px; padding: 0 12px; font-weight: 700; font-size: 13px; text-decoration: none; transition: all .15s ease; }
        .btn-topbar:hover { background: var(--soft); color: var(--ink); border-color: #cbd5e1; }
        .btn-topbar svg { width: 14px; height: 14px; }
        .btn-topbar-cta { display: inline-flex; align-items: center; gap: 6px; border: none; background: linear-gradient(135deg, var(--public-primary), var(--primary-dark)); color: #fff; border-radius: 8px; height: 38px; padding: 0 14px; font-weight: 800; font-size: 13px; text-decoration: none; transition: all .15s ease; box-shadow: 0 4px 12px color-mix(in srgb, var(--public-primary) 30%, transparent); }
        .btn-topbar-cta:hover { opacity: 0.94; transform: translateY(-1px); color: #fff; }

        /* Profile Dropdown in Topbar */
        .user-dropdown-wrapper { position: relative; display: inline-block; }
        .user-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1.5px solid #dbe4ef;
            padding: 3px 12px 3px 4px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            cursor: pointer;
            transition: all .15s ease-in-out;
            height: 38px;
        }
        .user-profile-btn:hover, .user-dropdown-wrapper.open .user-profile-btn {
            border-color: var(--public-primary);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--public-primary) 18%, transparent);
            background: #f8fafc;
        }
        .user-avatar-badge {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--public-primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 12px;
            flex-shrink: 0;
        }
        .user-avatar-img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            display: inline-block;
        }
        .user-profile-name {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .chevron-icon {
            color: #64748b;
            transition: transform .2s ease;
            flex-shrink: 0;
        }
        .user-dropdown-wrapper.open .chevron-icon {
            transform: rotate(180deg);
        }
        .user-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 240px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 16px 36px -8px rgba(15,23,42,.18);
            padding: 8px 6px;
            z-index: 1000;
            display: none;
            flex-direction: column;
            gap: 2px;
            animation: dropdownFade .15s ease-out;
        }
        .user-dropdown-wrapper.open .user-dropdown-menu {
            display: flex;
        }
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dropdown-header-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
        }
        .dropdown-avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #edf2f7;
        }
        .dropdown-avatar-circle-fallback {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            background: var(--public-primary);
            flex-shrink: 0;
        }
        .dropdown-user-name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .dropdown-user-sub {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .dropdown-divider {
            height: 1px;
            background: #edf2f7;
            margin: 4px 6px;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            text-decoration: none;
            border: 0;
            background: transparent;
            width: 100%;
            cursor: pointer;
            text-align: left;
            transition: background .15s, color .15s;
        }
        .dropdown-item:hover {
            background: #f1f5f9;
            color: #0f172a;
            text-decoration: none;
        }
        .dropdown-item.danger {
            color: #dc2626;
        }
        .dropdown-item.danger:hover {
            background: #fef2f2;
            color: #b91c1c;
        }

        .hero {
            background: linear-gradient(135deg, color-mix(in srgb, {{ $themeColor }} 94%, #020617) 0%, color-mix(in srgb, {{ $themeColor }} 80%, #09172d) 50%, {{ $themeColor }} 100%);
            color: #fff;
            padding: 38px 0 44px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 40px -10px color-mix(in srgb, {{ $themeColor }} 45%, transparent);
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 140%;
            height: 200%;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.18) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, color-mix(in srgb, {{ $themeColor }} 50%, #fff) 0%, transparent 40%);
            pointer-events: none;
        }
        .hero-inner { width: min(1200px, calc(100% - 32px)); margin: 0 auto; position: relative; z-index: 1; }
        .hero h1 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -.3px; }
        .hero p { margin: 6px 0 0; color: rgba(255,255,255,.90); font-size: 14px; }

        .wrap { width: min(1200px, calc(100% - 32px)); margin: -20px auto 60px; position: relative; z-index: 2; }
        .grid { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 20px; align-items: start; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 10px 30px -10px rgba(15,23,42,.06); overflow: hidden; margin-bottom: 20px; }
        .card-head { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; gap: 12px; background: linear-gradient(180deg, #fcfdfe 0%, #f8fafc 100%); }
        .card-head h2, .card h2 { margin: 0; font-size: 15px; font-weight: 800; color: var(--ink); display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 18px; }

        /* Stat Metrics Grid with Radiant Gradients */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
        .metric-box {
            background: linear-gradient(145deg, #ffffff 0%, color-mix(in srgb, var(--public-primary) 5%, #fff) 100%);
            border: 1px solid color-mix(in srgb, var(--public-primary) 18%, #e2e8f0);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 8px 24px -6px rgba(15,23,42,.06);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .metric-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -6px rgba(15,23,42,.12);
        }
        .metric-icon { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,.04); }
        .metric-icon.blue { background: linear-gradient(135deg, color-mix(in srgb, var(--public-primary) 18%, #fff), color-mix(in srgb, var(--public-primary) 8%, #fff)); color: var(--public-primary); border: 1px solid color-mix(in srgb, var(--public-primary) 25%, transparent); }
        .metric-icon.green { background: linear-gradient(135deg, #dcfce7, #ecfdf5); color: #16a34a; border: 1px solid #bbf7d0; }
        .metric-icon.amber { background: linear-gradient(135deg, #fef3c7, #fffbeb); color: #d97706; border: 1px solid #fde68a; }
        .metric-icon.slate { background: linear-gradient(135deg, #f1f5f9, #f8fafc); color: #64748b; border: 1px solid #e2e8f0; }
        .metric-meta { min-width: 0; }
        .metric-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; display: block; }
        .metric-val { font-size: 24px; font-weight: 900; color: var(--ink); line-height: 1.1; margin-top: 2px; }
        .metric-box.highlight { background: linear-gradient(145deg, #ffffff 0%, #fffbeb 100%); border-color: #fde68a; box-shadow: 0 8px 24px -6px rgba(217,119,6,.15); }

        /* Customer Profile Card */
        .profile-hero-card { display: flex; align-items: center; gap: 16px; padding: 20px 18px; background: linear-gradient(135deg, color-mix(in srgb, var(--public-primary) 10%, #f8fafc) 0%, color-mix(in srgb, var(--public-primary) 4%, #fff) 100%); border-bottom: 1px solid #edf2f7; }
        .avatar-wrap { position: relative; width: 74px; height: 74px; flex-shrink: 0; }
        .customer-avatar { width: 74px; height: 74px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 6px 16px rgba(15,23,42,.14); display: block; }
        .customer-avatar-fallback { width: 74px; height: 74px; border-radius: 50%; background: linear-gradient(135deg, var(--public-primary), color-mix(in srgb, var(--public-primary) 70%, #000)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800; border: 3px solid #fff; box-shadow: 0 6px 16px rgba(15,23,42,.14); }
        .avatar-edit-btn { position: absolute; bottom: 0; right: 0; width: 26px; height: 26px; border-radius: 50%; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,.2); font-size: 11px; }
        .avatar-edit-btn:hover { background: var(--public-primary); }
        .profile-meta h3 { margin: 0; font-size: 17px; color: #0f172a; font-weight: 800; }
        .profile-meta p { margin: 3px 0 0; color: #64748b; font-size: 12px; }
        .photo-upload-box { display: none; padding: 14px 18px; background: #fff; border-bottom: 1px solid #edf2f7; }
        .photo-upload-box.open { display: block; }
        .photo-upload-form { display: flex; flex-direction: column; gap: 8px; }
        .photo-upload-input { font-size: 12px; }
        .photo-upload-actions { display: flex; gap: 8px; }
        .btn-upload-save { background: linear-gradient(135deg, var(--public-primary), var(--primary-dark)); color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px color-mix(in srgb, var(--public-primary) 30%, transparent); }
        .btn-upload-cancel { background: #e2e8f0; color: #334155; border: 0; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; }
        .info { display: grid; gap: 10px; }
        .info-row { display: grid; gap: 2px; }
        .info-row span { color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        .info-row strong { color: #0f172a; font-size: 13px; overflow-wrap: anywhere; }
        .profile-actions { padding: 14px 18px; border-top: 1px solid #edf2f7; background: #fafbfc; }
        .profile-actions-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .btn-profile-switch { width: 100%; min-height: 40px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid #dbe4ef; background: #fff; color: #334155; border-radius: 8px; font-weight: 800; font-size: 12px; text-decoration: none; transition: all .15s ease; }
        .btn-profile-switch:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
        .btn-profile-switch svg { width: 14px; height: 14px; }
        .btn-profile-logout { width: 100%; min-height: 40px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid #fee2e2; background: #fff; color: #dc2626; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; transition: all .15s ease; }
        .btn-profile-logout:hover { background: #fef2f2; border-color: #fca5a5; }
        .btn-profile-logout svg { width: 14px; height: 14px; }

        /* Tables & Lists */
        .stack { display: grid; gap: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #edf2f7; text-align: left; font-size: 13px; vertical-align: middle; }
        th { color: #475569; font-size: 11px; font-weight: 800; text-transform: uppercase; background: #f8fafc; letter-spacing: .4px; }
        .table-scroll { overflow-x: auto; }
        .empty { margin: 0; color: #64748b; font-size: 13px; text-align: center; padding: 24px 0; }
        .note-box { margin: 0; color: #334155; line-height: 1.6; white-space: pre-wrap; font-size: 13px; }

        /* Status Pills */
        .status { display: inline-flex; min-height: 22px; align-items: center; border-radius: 999px; padding: 2px 10px; background: #e8f2ff; color: #1d4ed8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
        .status.pending, .status.draft, .status.pending_approval { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .status.active, .status.approved, .status.in_progress { background: #dcfce7; color: #15803d; }
        .status.completed, .status.paid, .status.closed { background: #f0fdf4; color: #166534; }
        .status.rejected, .status.declined, .status.defaulted { background: #fee2e2; color: #dc2626; }
        .status.cancelled { background: #f1f5f9; color: #64748b; }

        .btn-cancel-req {
            background: #fee2e2; color: #dc2626; border: 1px solid #fecdd3; border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 800; cursor: pointer; transition: all .15s ease;
        }
        .btn-cancel-req:hover { background: #fca5a5; color: #991b1b; }

        @media (max-width: 960px) {
            .grid { grid-template-columns: 1fr; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .topbar-inner { min-height: 54px; padding: 4px 0; flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: nowrap; }
            .topbar-actions { width: auto; justify-content: flex-end; flex-wrap: nowrap; }
            table { min-width: 600px; }
        }
        @media (max-width: 640px) {
            .topbar-inner { min-height: 50px; gap: 6px; padding: 0 4px; }
            .brand span:not(.logo) { max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
            .logo { width: 30px; height: 30px; }
            .btn-topbar { height: 32px; padding: 0 8px; font-size: 11px; }
            .hero { padding: 24px 0 28px; }
            .hero h1 { font-size: 20px; }
            .hero p { font-size: 12px; }
            .wrap { width: min(1200px, calc(100% - 16px)); margin: -16px auto 40px; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .metric-box { padding: 12px; border-radius: 10px; }
            .metric-val { font-size: 18px; }
            .metric-label { font-size: 10px; }
            .card-head { padding: 12px 14px; }
            .card-body { padding: 14px; }
        }
        @media (max-width: 380px) {
            .brand span:not(.logo) { display: none; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('loan-management.public.home') }}">
                <span class="logo">@if($businessLogoUrl)<img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">@else{{ strtoupper(mb_substr($businessName, 0, 1)) }}@endif</span>
                <span>{{ $businessName }}</span>
            </a>
            <div class="topbar-actions">
                <a href="{{ route('loan-management.public.home') }}" class="btn-topbar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    Website
                </a>
                @if($adminUser)
                    <a href="{{ route('loan-management.dashboard') }}" class="btn-admin-pill" title="Go back to Admin Dashboard">
                        ⚡ Admin Panel
                    </a>
                @endif

                <!-- User Profile Dropdown containing Switch Account & Logout -->
                <div class="user-dropdown-wrapper" id="customerDropdownWrap">
                    <button type="button" class="user-profile-btn" id="customerProfileToggle" aria-haspopup="true" aria-expanded="false" title="Click to view profile menu">
                        @if($customer->customer_photo_url)
                            <img src="{{ $customer->customer_photo_url }}" class="user-avatar-img" alt="{{ $displayName }}">
                        @else
                            <span class="user-avatar-initial">{{ strtoupper(mb_substr($customer->name ?: 'C', 0, 1)) }}</span>
                        @endif
                        <span class="user-profile-name">{{ $displayName }}</span>
                        <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>

                    <div class="user-dropdown-menu" id="customerDropdownMenu">
                        <div class="dropdown-header-box">
                            @if($customer->customer_photo_url)
                                <img src="{{ $customer->customer_photo_url }}" class="dropdown-avatar-circle" alt="{{ $displayName }}">
                            @else
                                <div class="dropdown-avatar-circle-fallback">{{ strtoupper(mb_substr($customer->name ?: 'C', 0, 1)) }}</div>
                            @endif
                            <div style="min-width: 0; flex: 1;">
                                <div class="dropdown-user-name">{{ $displayName }}</div>
                                <div class="dropdown-user-sub">{{ $customer->mobile ?? $customer->contact_id ?? 'Customer Account' }}</div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" class="dropdown-item" onclick="return confirm('Are you sure you want to log out and switch account?');">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Switch Account
                        </a>
                        @if($adminUser)
                            <a href="{{ route('loan-management.dashboard') }}" class="dropdown-item">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                Admin Dashboard
                            </a>
                        @endif
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('loan-management.public.customer-logout') }}" style="margin: 0; padding: 0;" onsubmit="return confirm('Are you sure you want to log out?');">
                            @csrf
                            <button type="submit" class="dropdown-item danger">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-inner">
            <h1>Welcome, {{ $displayName }}</h1>
            <p>Track your active installment loans, pending applications, and collection payments.</p>
        </div>
    </section>

    <main class="wrap">
        <!-- Metric Summary Cards -->
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-icon blue">📁</div>
                <div class="metric-meta">
                    <span class="metric-label">Total Installments</span>
                    <div class="metric-val">{{ $totalLoanCount ?? $loans->count() }}</div>
                </div>
            </div>

            <div class="metric-box">
                <div class="metric-icon green">⚡</div>
                <div class="metric-meta">
                    <span class="metric-label">Active Installments</span>
                    <div class="metric-val">{{ $activeCount ?? 0 }}</div>
                </div>
            </div>

            <div class="metric-box {{ ($pendingCount ?? 0) > 0 ? 'highlight' : '' }}">
                <div class="metric-icon amber">🟡</div>
                <div class="metric-meta">
                    <span class="metric-label">Pending Requests</span>
                    <div class="metric-val" style="color: #b45309;">{{ $pendingCount ?? 0 }}</div>
                </div>
            </div>

            <div class="metric-box">
                <div class="metric-icon slate">💰</div>
                <div class="metric-meta">
                    <span class="metric-label">Completed</span>
                    <div class="metric-val">{{ $completedCount ?? 0 }}</div>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="card" style="margin-bottom:18px; border-color: #a7f3d0; background: #ecfdf5;">
                <div class="card-body" style="color: #047857; font-weight: 700;">✓ {{ session('status') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="card" style="margin-bottom:18px; border-color: #fecdd3; background: #fff1f2;">
                <div class="card-body" style="color: #be123c; font-weight: 700;">⚠ {{ $errors->first() }}</div>
            </div>
        @endif

        <div class="grid">
            <!-- Left Column: Customer Profile -->
            <section class="card">
                <div class="card-head">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </h2>
                </div>
                <div class="profile-hero-card">
                    <div class="avatar-wrap">
                        @if($customer->customer_photo_url)
                            <img src="{{ $customer->customer_photo_url }}" class="customer-avatar" alt="{{ $displayName }}">
                        @else
                            <div class="customer-avatar-fallback">{{ strtoupper(mb_substr($customer->name ?: 'C', 0, 1)) }}</div>
                        @endif
                        <button type="button" class="avatar-edit-btn" id="togglePhotoUpload" title="Upload/Change Profile Photo">📷</button>
                    </div>
                    <div class="profile-meta">
                        <h3>{{ $displayName }}</h3>
                        <p>{{ $customer->customer_code ?: 'Customer' }} &bull; <span class="status">{{ ucfirst($customer->status ?: 'active') }}</span></p>
                    </div>
                </div>
                <div class="photo-upload-box" id="photoUploadBox">
                    <form method="POST" action="{{ route('loan-management.public.customer-profile-photo') }}" enctype="multipart/form-data" class="photo-upload-form">
                        @csrf
                        <label style="font-size: 12px; font-weight: 700; color: #475569;">Select new profile picture:</label>
                        <input type="file" name="profile_photo" accept="image/*" required class="photo-upload-input">
                        <div class="photo-upload-actions">
                            <button type="submit" class="btn-upload-save">Upload Photo</button>
                            <button type="button" class="btn-upload-cancel" id="cancelPhotoUpload">Cancel</button>
                        </div>
                    </form>
                </div>
                <div class="card-body info">
                    <div class="info-row"><span>Customer Code</span><strong>{{ $customer->customer_code ?: '-' }}</strong></div>
                    <div class="info-row"><span>Name</span><strong>{{ $customer->name ?: '-' }}</strong></div>
                    <div class="info-row"><span>Khmer Name</span><strong>{{ $customer->khmer_name ?: '-' }}</strong></div>
                    <div class="info-row"><span>Phone</span><strong>{{ $customer->phone ?: '-' }}</strong></div>
                    <div class="info-row"><span>Alternative Phone</span><strong>{{ $customer->alternate_phone ?: '-' }}</strong></div>
                    <div class="info-row"><span>Email</span><strong>{{ $customer->email ?: '-' }}</strong></div>
                    <div class="info-row"><span>Telegram</span><strong>{{ $customer->telegram ?: ($customer->telegram_username ?: '-') }}</strong></div>
                    <div class="info-row"><span>ID Card</span><strong>{{ $customer->id_card_number ?: '-' }}</strong></div>
                    <div class="info-row"><span>Address</span><strong>{{ $customer->address ?: '-' }}</strong></div>
                    <div class="info-row"><span>Workplace</span><strong>{{ $customer->workplace ?: '-' }}</strong></div>
                    <div class="info-row"><span>Monthly Income</span><strong>{{ $money($customer->monthly_income) }}</strong></div>
                </div>
                <div class="profile-actions">
                    <div class="profile-actions-grid">
                        <a href="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" class="btn-profile-switch" onclick="return confirm('Are you sure you want to log out and switch account?');" title="Switch to another customer account">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Switch Account
                        </a>
                        <form method="POST" action="{{ route('loan-management.public.customer-logout') }}" style="margin: 0;" onsubmit="return confirm('Are you sure you want to log out?');">
                            @csrf
                            <button class="btn-profile-logout" type="submit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Right Column: Installments & Pending Requests -->
            <div class="stack">
                <!-- Section: Pending Installment Applications (if any) -->
                @if(isset($pendingLoans) && $pendingLoans->isNotEmpty())
                    <section class="card" style="border-color: #fde68a; background: #fffdfa;">
                        <div class="card-head" style="background: #fefce8; border-bottom-color: #fef08a;">
                            <h2>
                                <span style="color: #b45309;">🟡</span> Pending Installment Requests
                            </h2>
                            <span class="status pending">{{ $pendingLoans->count() }} Awaiting Approval</span>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Installment #</th>
                                        <th>Requested Date</th>
                                        <th>Principal</th>
                                        <th>Total Repayment</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingLoans as $pLoan)
                                        <tr>
                                            <td><strong>{{ $pLoan->loan_number ?? ('#'.$pLoan->id) }}</strong></td>
                                            <td>{{ $pLoan->loan_date ?? $pLoan->created_at ?? '-' }}</td>
                                            <td><strong>{{ $money($pLoan->principal_amount ?? $pLoan->loan_amount ?? 0) }}</strong></td>
                                            <td>{{ $money($pLoan->total_amount ?? $pLoan->total_payable_amount ?? 0) }}</td>
                                            <td>
                                                <span class="status pending">🟡 Pending Review</span>
                                            </td>
                                            <td style="text-align: right;">
                                                <form method="POST" action="{{ route('loan-management.public.customer-loan-request.cancel', ['id' => $pLoan->id]) }}" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to cancel this pending loan request?');">
                                                    @csrf
                                                    <button type="submit" class="btn-cancel-req" title="Cancel this pending request">
                                                        Cancel Request
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                <!-- Section: My Installments -->
                <section class="card">
                    <div class="card-head">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            My Installments
                        </h2>
                        <a href="{{ route('loan-management.public.customer-loan-request') }}" style="display: inline-flex; align-items: center; gap: 6px; background: var(--public-primary); color: #fff; text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 800;">
                            + New Installment Request
                        </a>
                    </div>
                    <div class="table-scroll">
                        @if($loans->count())
                            <table>
                                <thead>
                                    <tr>
                                        <th>Installment Number</th>
                                        <th>Status</th>
                                        <th>Total Amount</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Installment Date</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loans as $loan)
                                        @php
                                            $st = strtolower($loan->status ?? 'pending');
                                            $isPending = in_array($st, ['pending', 'draft', 'pending_approval']);
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $loan->loan_number ?? ('#'.$loan->id) }}</strong></td>
                                            <td><span class="status {{ $st }}">{{ ucfirst($st) }}</span></td>
                                            <td>{{ $money($loan->total_amount ?? $loan->loan_amount ?? 0) }}</td>
                                            <td>{{ $money($loan->paid_amount ?? $loan->total_paid ?? 0) }}</td>
                                            <td><strong style="color: {{ ($loan->balance_amount ?? 0) > 0 ? 'var(--ink)' : '#16a34a' }};">{{ $money($loan->balance_amount ?? $loan->remaining_balance ?? 0) }}</strong></td>
                                            <td>{{ $loan->loan_date ?? $loan->created_at ?? '-' }}</td>
                                            <td style="text-align: right;">
                                                @if($isPending)
                                                    <form method="POST" action="{{ route('loan-management.public.customer-loan-request.cancel', ['id' => $loan->id]) }}" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to cancel this pending loan request?');">
                                                        @csrf
                                                        <button type="submit" class="btn-cancel-req" title="Cancel this pending request">
                                                            Cancel
                                                        </button>
                                                    </form>
                                                @else
                                                    <span style="color: var(--muted); font-size: 11px;">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="card-body"><p class="empty">No loans found yet. Click "+ New Installment Request" above to apply.</p></div>
                        @endif
                    </div>
                </section>

                <!-- Section: Recent Payments -->
                <section class="card">
                    <div class="card-head">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                            Recent Payments
                        </h2>
                    </div>
                    <div class="table-scroll">
                        @if($payments->count())
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Installment Number</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->paid_at ?? $payment->paid_date ?? $payment->created_at ?? '-' }}</td>
                                            <td><strong>{{ $payment->loan_number_snapshot ?? ('#'.($payment->loan_id ?? '-')) }}</strong></td>
                                            <td><strong style="color: #16a34a;">{{ $money($payment->amount ?? $payment->total_paid_base ?? 0) }}</strong></td>
                                            <td>{{ $payment->payment_method_snapshot ?? $payment->payment_method ?? 'Cash' }}</td>
                                            <td><span class="status active">{{ ucfirst($payment->status ?? 'confirmed') }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="card-body"><p class="empty">No payment transactions recorded yet.</p></div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        (function() {
            var toggleBtn = document.getElementById('togglePhotoUpload');
            var cancelBtn = document.getElementById('cancelPhotoUpload');
            var uploadBox = document.getElementById('photoUploadBox');

            if (toggleBtn && uploadBox) {
                toggleBtn.addEventListener('click', function() {
                    uploadBox.classList.toggle('open');
                });
            }
            if (cancelBtn && uploadBox) {
                cancelBtn.addEventListener('click', function() {
                    uploadBox.classList.remove('open');
                });
            }

            // User Profile dropdown toggle
            var dropdownWrap = document.getElementById('customerDropdownWrap');
            var profileToggleBtn = document.getElementById('customerProfileToggle');

            if (dropdownWrap && profileToggleBtn) {
                profileToggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isOpen = dropdownWrap.classList.toggle('open');
                    profileToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });

                document.addEventListener('click', function(e) {
                    if (!dropdownWrap.contains(e.target)) {
                        dropdownWrap.classList.remove('open');
                        profileToggleBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && dropdownWrap.classList.contains('open')) {
                        dropdownWrap.classList.remove('open');
                        profileToggleBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        })();
    </script>
</body>
</html>
