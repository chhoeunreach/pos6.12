@php
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();
    $businessName = \Modules\LoanManagement\Services\BusinessSettingsService::businessName();
    $headline = $settings['home_headline'] ?? 'Simple loan service for customers';
    $subtitle = $settings['home_subtitle'] ?? '';
    $body = $settings['home_body'] ?? '';
    $themeColor = $settings['theme_color'] ?? '#2563eb';

    $customerUser = Auth::guard('customer_loan')->user();
    $adminUser = Auth::guard('web')->user() ?? Auth::user();

    $customerPhotoUrl = $customerUser ? $customerUser->customer_photo_url : null;
    $adminPhotoUrl = null;
    if ($adminUser) {
        if (!empty($adminUser->profile_photo_url)) {
            $adminPhotoUrl = $adminUser->profile_photo_url;
        } elseif (!empty($adminUser->profile_photo)) {
            $adminPhotoUrl = asset('uploads/profile_photos/' . $adminUser->profile_photo);
        } elseif (session()->has('user.profile_photo_url')) {
            $adminPhotoUrl = session('user.profile_photo_url');
        }
    }
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $businessName }}</title>
    <style>
        :root { --public-primary: {{ $themeColor }}; --ink: #102033; --muted: #64748b; --line: #e2e8f0; --panel: #fff; --soft: #f5f8fc; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: var(--ink); background: var(--soft); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
        .public-shell { min-height: 100vh; display: flex; flex-direction: column; }

        /* Top Nav Bar */
        .site-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255,255,255,.88);
            border-bottom: 1px solid rgba(226,232,240,.75);
            backdrop-filter: blur(20px) saturate(1.25);
            -webkit-backdrop-filter: blur(20px) saturate(1.25);
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
        }
        .nav-inner {
            width: min(1240px, calc(100% - 32px));
            margin: 0 auto;
            min-height: 72px;
            display: grid;
            grid-template-columns: minmax(190px, .8fr) auto minmax(190px, .8fr);
            align-items: center;
            gap: 16px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 800;
            color: #0f172a;
            min-width: 0;
            justify-self: start;
            padding: 6px 8px 6px 4px;
            border-radius: 12px;
            transition: background .16s ease, transform .16s ease;
        }
        .brand:hover { background: rgba(248,250,252,.9); transform: translateY(-1px); }
        .brand-logo { width: 42px; height: 42px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; background: color-mix(in srgb, var(--public-primary) 10%, #fff); border: 1px solid color-mix(in srgb, var(--public-primary) 22%, #dbe4ef); color: var(--public-primary); flex-shrink: 0; box-shadow: 0 8px 18px color-mix(in srgb, var(--public-primary) 16%, transparent); }
        .brand-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .brand-text { font-size: 18px; font-weight: 900; letter-spacing: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 240px; }

        .menu {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 0;
            padding: 5px;
            border: 1px solid rgba(226,232,240,.9);
            border-radius: 999px;
            background: rgba(248,250,252,.82);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
            justify-self: center;
        }
        .menu::-webkit-scrollbar { display: none; }
        .menu a {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 14px;
            border-radius: 999px;
            color: #475569;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background .16s ease, color .16s ease, box-shadow .16s ease, transform .16s ease;
        }
        .menu a svg { width: 17px; height: 17px; color: #94a3b8; transition: color .16s ease; }
        .menu a:hover,
        .menu a.active {
            background: #fff;
            color: #0f172a;
            box-shadow: 0 8px 18px rgba(15,23,42,.08);
            transform: translateY(-1px);
        }
        .menu a:hover svg,
        .menu a.active svg { color: var(--public-primary); }

        .nav-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-shrink: 0; flex-wrap: nowrap; justify-self: end; }
        .button, .button-outline, .button-danger-outline { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px; border-radius: 9px; text-decoration: none; font-weight: 850; border: 1px solid transparent; cursor: pointer; transition: background .16s ease, border-color .16s ease, color .16s ease, box-shadow .16s ease, transform .16s ease; font-size: 14px; gap: 6px; white-space: nowrap; flex-shrink: 0; }
        .button { color: #fff; background: linear-gradient(135deg, var(--public-primary), color-mix(in srgb, var(--public-primary) 78%, #111827)); border-color: color-mix(in srgb, var(--public-primary) 75%, #111827); box-shadow: 0 12px 22px color-mix(in srgb, var(--public-primary) 24%, transparent); }
        .button:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 16px 28px color-mix(in srgb, var(--public-primary) 30%, transparent); }
        .button-outline { color: #0f172a; background: rgba(255,255,255,.9); border-color: #dbe4ef; box-shadow: 0 8px 18px rgba(15,23,42,.04); }
        .button-outline:hover { background: #fff; border-color: color-mix(in srgb, var(--public-primary) 30%, #cbd5e1); color: var(--public-primary); transform: translateY(-1px); }
        .button-danger-outline { color: #dc2626; background: #fff; border-color: #fee2e2; }
        .button-danger-outline:hover { background: #fef2f2; border-color: #fca5a5; }

        /* Profile Dropdown Menu */
        .user-dropdown-wrapper { position: relative; display: inline-block; flex-shrink: 0; }
        .user-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1.5px solid #dbe4ef;
            padding: 3px 10px 3px 4px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            cursor: pointer;
            transition: all .15s ease-in-out;
            height: 36px;
            white-space: nowrap;
        }
        .user-profile-btn:hover, .user-dropdown-wrapper.open .user-profile-btn {
            border-color: var(--public-primary);
            box-shadow: 0 4px 12px rgba(37,99,235,.12);
            background: #f8fafc;
        }
        .user-avatar-badge {
            width: 26px;
            height: 26px;
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
        .user-profile-name {
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
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
            top: calc(100% + 6px);
            right: 0;
            width: 220px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 16px 36px -8px rgba(15,23,42,.18);
            padding: 6px 4px;
            z-index: 1000;
            display: none;
            flex-direction: column;
            gap: 2px;
            animation: dropdownFade .15s ease-out;
        }
        .user-dropdown-wrapper.open .user-dropdown-menu {
            display: flex;
        }
        .admin-profile-btn {
            background: #faf5ff;
            border-color: #e9d5ff;
            color: #7e22ce;
        }
        .admin-profile-btn:hover, .user-dropdown-wrapper.open .admin-profile-btn {
            border-color: #a855f7;
            background: #f3e8ff;
            box-shadow: 0 4px 12px rgba(147, 51, 234, .14);
        }
        .admin-avatar-badge {
            background: #7e22ce;
            color: #fff;
        }
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .user-avatar-img {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            object-fit: cover;
            display: inline-block;
            flex-shrink: 0;
        }
        .dropdown-header-box {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
        }
        .dropdown-avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #edf2f7;
        }
        .dropdown-avatar-circle-fallback {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: #fff;
            background: var(--public-primary);
            flex-shrink: 0;
        }
        .admin-avatar-circle-fallback {
            background: #7e22ce;
        }
        .dropdown-user-name {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .dropdown-user-sub {
            font-size: 11px;
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
            gap: 8px;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 12px;
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

        /* Hero Section */
        .hero { color: #fff; background: linear-gradient(90deg, rgba(7,18,33,.88), rgba(7,18,33,.58), rgba(7,18,33,.25)), @if($loginBackgroundUrl) url('{{ $loginBackgroundUrl }}') @else linear-gradient(135deg, #12324e, #607d95) @endif; background-size: cover; background-position: center; }
        .hero-inner { width: min(1180px, calc(100% - 24px)); min-height: auto; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 32px; align-items: center; padding: 42px 0 54px; }
        .hero-copy { max-width: 680px; }
        .eyebrow { display: inline-flex; min-height: 28px; align-items: center; padding: 0 10px; border: 1px solid rgba(255,255,255,.34); border-radius: 999px; color: rgba(255,255,255,.9); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        h1 { margin: 14px 0 0; font-size: clamp(24px, 4.5vw, 46px); line-height: 1.14; font-weight: 900; letter-spacing: -.4px; }
        .subtitle { margin: 12px 0 0; max-width: 600px; font-size: clamp(14px, 1.8vw, 16px); line-height: 1.6; color: rgba(255,255,255,.90); }
        .body-copy { margin: 10px 0 0; max-width: 600px; font-size: 13px; line-height: 1.6; color: rgba(255,255,255,.78); white-space: pre-wrap; }
        .hero-cta { margin-top: 22px; display: flex; gap: 10px; flex-wrap: wrap; }
        .hero-card { background: rgba(255,255,255,.96); border: 1px solid rgba(255,255,255,.58); border-radius: 12px; color: #0f172a; padding: 18px; box-shadow: 0 16px 40px rgba(0,0,0,.16); }
        .hero-card h2 { margin: 0; font-size: 17px; font-weight: 800; letter-spacing: -.2px; }
        .hero-card p { margin: 6px 0 14px; color: var(--muted); line-height: 1.5; font-size: 13px; }
        .quick-stat { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #334155; font-size: 13px; }
        .quick-stat strong { color: #0f172a; font-weight: 800; }

        /* Sections & Feature Cards */
        .section { padding: 40px 12px; }
        .section.alt { background: #fff; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .section-inner { width: min(1180px, 100%); margin: 0 auto; }
        .section-head { margin: 0 0 18px; display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; }
        .section-head h2 { margin: 0; color: #0f172a; font-size: clamp(20px, 3.5vw, 26px); font-weight: 900; letter-spacing: -.3px; }
        .section-head p { margin: 6px 0 0; max-width: 600px; color: var(--muted); line-height: 1.55; font-size: 13px; }
        .feature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .feature { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 16px; box-shadow: 0 6px 20px rgba(15,23,42,.04); }
        .feature-icon { width: 38px; height: 38px; border-radius: 8px; display: grid; place-items: center; background: #eef6ff; color: var(--public-primary); font-weight: 900; margin-bottom: 12px; font-size: 16px; }
        .feature strong { display: block; color: #0f172a; font-size: 15px; font-weight: 800; }
        .feature span { display: block; margin-top: 6px; color: var(--muted); line-height: 1.5; font-size: 13px; }
        .shop-inner { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }

        /* Catalog Search & Category Filter Toolbar */
        .catalog-filter-bar {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(15,23,42,.02);
        }
        .catalog-search-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .search-input-box {
            position: relative;
            flex: 1;
            min-width: 180px;
        }
        .search-input-box svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            width: 14px;
            height: 14px;
        }
        .search-input-box input {
            width: 100%;
            height: 38px;
            padding: 0 30px 0 32px;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            background: #f8fafc;
        }
        .search-input-box input:focus {
            border-color: var(--public-primary);
            background: #fff;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--public-primary) 15%, transparent);
        }
        .search-clear-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .search-clear-btn:hover { color: #0f172a; }
        .sort-select-box select {
            height: 38px;
            padding: 0 10px;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            background: #fff;
            outline: none;
            cursor: pointer;
        }
        .category-chips-scroll {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .category-chips-scroll::-webkit-scrollbar { display: none; }
        .category-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all .15s ease;
            flex-shrink: 0;
        }
        .category-chip:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .category-chip.active {
            background: var(--public-primary);
            border-color: var(--public-primary);
            color: #fff;
            box-shadow: 0 3px 8px color-mix(in srgb, var(--public-primary) 25%, transparent);
        }
        .category-chip .chip-count {
            font-size: 10px;
            opacity: 0.88;
            background: rgba(0,0,0,0.08);
            padding: 1px 5px;
            border-radius: 10px;
        }
        .category-chip.active .chip-count {
            background: rgba(255,255,255,0.25);
        }

        /* Product Cards */
        .product-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .product-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(15,23,42,.03);
            display: flex;
            flex-direction: column;
            min-width: 0;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -6px rgba(15,23,42,.09);
            border-color: color-mix(in srgb, var(--public-primary) 40%, #cbd5e1);
        }
        .product-image-wrap {
            aspect-ratio: 4 / 3;
            background: #f8fafc;
            display: grid;
            place-items: center;
            color: #52657c;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #f1f5f9;
        }
        .product-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .3s ease;
        }
        .product-card:hover .product-image-wrap img {
            transform: scale(1.04);
        }
        .product-fallback-icon {
            font-size: 32px;
            color: #cbd5e1;
        }
        .card-badge-top-left {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 5px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .card-badge-top-right {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ecfdf5;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 5px;
        }
        .product-body { padding: 14px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .product-brand-tag { font-size: 11px; font-weight: 800; color: var(--public-primary); text-transform: uppercase; letter-spacing: .3px; }
        .product-title { margin: 0; color: #0f172a; font-size: 14px; font-weight: 800; line-height: 1.35; letter-spacing: -.2px; }
        .product-sku { color: var(--muted); font-size: 11px; }
        .product-price-row {
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 6px;
        }
        .price-cash { color: #0f172a; font-size: 17px; font-weight: 900; }
        .price-monthly-tag { font-size: 11px; font-weight: 800; color: #16a34a; background: #f0fdf4; padding: 2px 6px; border-radius: 4px; }

        .product-actions-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px;
            margin-top: 8px;
        }
        .cart-btn {
            min-height: 36px;
            border: 0;
            border-radius: 6px;
            background: var(--public-primary);
            color: #fff;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: background .15s, transform .1s;
        }
        .cart-btn:hover {
            opacity: 0.92;
        }
        .cart-btn:active {
            transform: scale(0.98);
        }
        .apply-btn {
            min-height: 36px;
            padding: 0 10px;
            border: 1.5px solid #dbe4ef;
            border-radius: 6px;
            background: #fff;
            color: #334155;
            font-weight: 800;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .15s ease;
        }
        .apply-btn:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Cart Panel */
        .cart-panel {
            position: sticky;
            top: 76px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15,23,42,.05);
            overflow: hidden;
        }
        .cart-panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cart-panel-head h2 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
        .cart-count-pill { background: var(--public-primary); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 999px; }
        .cart-items { padding: 12px 16px; display: grid; gap: 10px; max-height: 320px; overflow-y: auto; }
        .cart-empty { color: var(--muted); font-size: 12px; line-height: 1.5; text-align: center; padding: 18px 0; }
        .cart-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
        .cart-item strong { display: block; color: #0f172a; font-size: 12px; overflow-wrap: anywhere; font-weight: 700; }
        .cart-item span { display: block; color: var(--muted); font-size: 11px; margin-top: 1px; }
        .qty-row { display: inline-flex; align-items: center; gap: 3px; }
        .qty-row button { width: 24px; height: 24px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; cursor: pointer; font-weight: 800; color: #334155; font-size: 12px; }
        .qty-row button:hover { background: #f1f5f9; }
        .qty-row span { min-width: 18px; text-align: center; font-weight: 700; font-size: 11px; color: #0f172a; margin: 0; }
        .cart-total { padding: 12px 16px; border-top: 1px solid #edf2f7; display: flex; justify-content: space-between; gap: 10px; font-weight: 800; color: #0f172a; font-size: 14px; }
        .cart-apply { margin: 0 16px 16px; width: calc(100% - 32px); min-height: 38px; border-radius: 6px; border: 0; background: var(--public-primary); color: #fff; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 13px; }

        .footer { padding: 22px 14px; background: #0f172a; color: rgba(255,255,255,.78); font-size: 12px; }
        .footer-inner { width: min(1180px, 100%); margin: 0 auto; display: flex; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .footer a { color: #fff; text-decoration: none; font-weight: 800; }

        /* Responsive Breakpoints & Professional Mobile UI */
        @media (max-width: 1120px) {
            .nav-inner {
                width: min(100% - 24px, 1040px);
                grid-template-columns: minmax(150px, .7fr) auto minmax(150px, .7fr);
                gap: 10px;
            }
            .brand-text { max-width: 170px; }
            .menu { gap: 3px; padding: 4px; }
            .menu a { min-height: 36px; padding: 0 10px; font-size: 14px; gap: 5px; }
            .menu a svg { width: 15px; height: 15px; }
            .button, .button-outline, .button-danger-outline { min-height: 38px; padding: 0 12px; font-size: 13px; }
            .user-profile-name { max-width: 90px; }
        }
        @media (max-width: 960px) {
            .nav-inner {
                min-height: 58px;
                padding: 4px 0;
                display: flex;
                justify-content: space-between;
            }
            .menu { display: none !important; }
            .hero-inner, .shop-inner { grid-template-columns: 1fr; }
            .hero-inner { min-height: auto; padding: 32px 0 44px; }
            .feature-grid { grid-template-columns: 1fr; }
            .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cart-panel { position: static; margin-top: 10px; }
            .section-head { display: block; }
        }
        @media (max-width: 640px) {
            body { padding-bottom: calc(62px + env(safe-area-inset-bottom, 0px)); }
            .site-nav { padding: 0; }
            .nav-inner { gap: 8px; min-height: 50px; padding: 0 8px; }
            .brand-text { max-width: 150px; font-size: 14px; }
            .brand-logo { width: 32px; height: 32px; }
            .user-profile-btn { height: 34px; padding: 2px 10px 2px 3px; font-size: 12px; }
            .user-avatar-badge, .user-avatar-img { width: 24px; height: 24px; font-size: 11px; }
            .user-profile-name { max-width: 85px; }
            .button, .button-outline, .button-danger-outline { min-height: 34px; padding: 0 10px; font-size: 12px; }

            /* 2-Column Clean Mobile Products Catalog */
            .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .product-card { border-radius: 8px; }
            .product-body { padding: 10px 8px; gap: 4px; }
            .product-title { font-size: 12px; height: 32px; -webkit-line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35; }
            .product-sku { display: none; }
            .product-brand-tag { font-size: 10px; }
            .price-cash { font-size: 14px; }
            .price-monthly-tag { font-size: 10px; padding: 1px 4px; }
            .product-price-row { padding-top: 6px; }
            .product-actions-grid { grid-template-columns: 1fr; gap: 4px; margin-top: 6px; }
            .cart-btn, .apply-btn { min-height: 30px; font-size: 11px; padding: 0 6px; }

            /* Compact Mobile Search Toolbar */
            .catalog-filter-bar { padding: 8px 10px; gap: 8px; }
            .catalog-search-row { flex-direction: row; gap: 6px; flex-wrap: nowrap; }
            .search-input-box { min-width: 0; flex: 1; }
            .search-input-box input { height: 34px; font-size: 12px; padding: 0 24px 0 28px; }
            .sort-select-box { flex-shrink: 0; }
            .sort-select-box select { height: 34px; padding: 0 6px; font-size: 11px; }
            .category-chip { height: 26px; padding: 0 9px; font-size: 11px; gap: 4px; }
            .section { padding: 24px 8px; }
        }
        @media (max-width: 380px) {
            .brand-text { max-width: 105px; font-size: 12px; }
        }

        /* Modern Mobile Bottom Navigation Bar */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.96);
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.08);
            display: none;
            grid-template-columns: repeat(4, 1fr);
            padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
        }
        @media (max-width: 640px) {
            .mobile-bottom-nav { display: grid; }
        }
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            color: #64748b;
            text-decoration: none;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 0;
            transition: color .15s ease, transform .1s ease;
            position: relative;
        }
        .mobile-nav-item:active { transform: scale(0.94); }
        .mobile-nav-item.active, .mobile-nav-item:hover { color: var(--public-primary); text-decoration: none; }
        .mobile-nav-item svg { width: 18px; height: 18px; }
        .mobile-nav-badge {
            position: absolute;
            top: 2px;
            right: calc(50% - 14px);
            background: #dc2626;
            color: #fff;
            font-size: 9px;
            font-weight: 900;
            min-width: 15px;
            height: 15px;
            line-height: 15px;
            border-radius: 999px;
            text-align: center;
            padding: 0 3px;
            display: none;
        }
    </style>
</head>
<body>
    <main class="public-shell">
        <header class="site-nav">
            <div class="nav-inner">
                <a class="brand" href="{{ route('loan-management.public.home') }}">
                    <span class="brand-logo">
                        @if($businessLogoUrl)
                            <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">
                        @else
                            {{ strtoupper(mb_substr($businessName, 0, 1)) }}
                        @endif
                    </span>
                    <span class="brand-text">{{ $businessName }}</span>
                </a>
                <nav class="menu" aria-label="Main menu">
                    <a href="#home" class="active" data-section-link="home">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                        Home
                    </a>
                    <a href="#products" data-section-link="products">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 2h12l3 5v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/><path d="M3 7h18"/><path d="M16 11a4 4 0 0 1-8 0"/></svg>
                        Products
                    </a>
                    <a href="#how" data-section-link="how">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        How It Works
                    </a>
                    <a href="#cart" data-section-link="cart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2 3h3l2.4 12.2A2 2 0 0 0 9.4 17H18a2 2 0 0 0 2-1.5L22 7H6"/></svg>
                        Cart
                    </a>
                </nav>
                <div class="nav-actions">
                    @if($adminUser)
                        <div class="user-dropdown-wrapper" id="adminDropdownWrapper">
                            <button type="button" class="user-profile-btn admin-profile-btn" id="adminProfileToggle" aria-expanded="false">
                                @if($adminPhotoUrl)
                                    <img src="{{ $adminPhotoUrl }}" class="user-avatar-img" alt="Admin">
                                @else
                                    <span class="user-avatar-badge admin-avatar-badge">⚡</span>
                                @endif
                                <span class="user-profile-name">{{ $adminUser->name ?? $adminUser->username ?? 'Admin' }}</span>
                                <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="user-dropdown-menu" id="adminDropdownMenu">
                                <div class="dropdown-header-box">
                                    @if($adminPhotoUrl)
                                        <img src="{{ $adminPhotoUrl }}" class="dropdown-avatar-circle" alt="Admin">
                                    @else
                                        <div class="dropdown-avatar-circle-fallback admin-avatar-circle-fallback">⚡</div>
                                    @endif
                                    <div style="min-width: 0;">
                                        <div class="dropdown-user-name">{{ $adminUser->name ?? $adminUser->username ?? 'Administrator' }}</div>
                                        <div class="dropdown-user-sub">{{ $adminUser->email ?? 'Admin & Staff Portal' }}</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="{{ route('loan-management.dashboard') }}" class="dropdown-item">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    Admin Dashboard
                                </a>
                                <a href="{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" class="dropdown-item" onclick="return confirm('Are you sure you want to log out and switch to Customer Portal?');" title="Switch or login as customer">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Switch to Customer
                                </a>
                                <a href="{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('login')) }}" class="dropdown-item" onclick="return confirm('Are you sure you want to log out and switch admin account?');" title="Switch or login as another admin user">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    Switch / Other Admin
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}" style="margin: 0;" onsubmit="return confirm('Are you sure you want to log out?');">
                                    @csrf
                                    <button type="submit" class="dropdown-item danger">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        Logout Admin
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if($customerUser)
                        <div class="user-dropdown-wrapper" id="customerDropdownWrapper">
                            <button type="button" class="user-profile-btn" id="customerProfileToggle" aria-expanded="false">
                                @if($customerPhotoUrl)
                                    <img src="{{ $customerPhotoUrl }}" class="user-avatar-img" alt="{{ $customerUser->name ?: 'Customer' }}">
                                @else
                                    <span class="user-avatar-badge">{{ strtoupper(mb_substr($customerUser->name ?: 'C', 0, 1)) }}</span>
                                @endif
                                <span class="user-profile-name">{{ $customerUser->name ?: 'Customer' }}</span>
                                <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="user-dropdown-menu" id="customerDropdownMenu">
                                <div class="dropdown-header-box">
                                    @if($customerPhotoUrl)
                                        <img src="{{ $customerPhotoUrl }}" class="dropdown-avatar-circle" alt="{{ $customerUser->name ?: 'Customer' }}">
                                    @else
                                        <div class="dropdown-avatar-circle-fallback">{{ strtoupper(mb_substr($customerUser->name ?: 'C', 0, 1)) }}</div>
                                    @endif
                                    <div style="min-width: 0;">
                                        <div class="dropdown-user-name">{{ $customerUser->name ?: 'Customer' }}</div>
                                        <div class="dropdown-user-sub">{{ $customerUser->phone ?: $customerUser->username }}</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="{{ route('loan-management.public.customer-dashboard') }}" class="dropdown-item">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    My Dashboard
                                </a>
                                <a href="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" class="dropdown-item" onclick="return confirm('Are you sure you want to log out and switch to another account?');" title="Switch or add another customer account">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    Switch Account
                                </a>
                                @if(! $adminUser)
                                    <a href="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('login')) }}" class="dropdown-item" onclick="return confirm('Are you sure you want to log out and go to Admin Login?');">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                        Admin Login
                                    </a>
                                @endif
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('loan-management.public.customer-logout') }}" style="margin: 0;" onsubmit="return confirm('Are you sure you want to log out?');">
                                    @csrf
                                    <button type="submit" class="dropdown-item danger">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        Logout Customer
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif(! $adminUser)
                        <a class="button-outline" href="{{ route('loan-management.public.customer-login') }}">Login</a>
                        <a class="button" href="{{ route('loan-management.public.register') }}">Register</a>
                    @endif
                </div>
            </div>
        </header>

        <section class="hero" id="home">
            <div class="hero-inner">
                <div class="hero-copy">
                    <span class="eyebrow">Installment Shopping</span>
                    <h1>{{ $headline }}</h1>
                    @if($subtitle)
                        <p class="subtitle">{{ $subtitle }}</p>
                    @endif
                    @if($body)
                        <p class="body-copy">{{ $body }}</p>
                    @endif
                    <div class="hero-cta">
                        @if($customerUser)
                            <a class="button" href="{{ route('loan-management.public.customer-dashboard') }}">Go to My Dashboard</a>
                            <a class="button-outline" href="#products">Shop Products</a>
                        @elseif($adminUser)
                            <a class="button" href="{{ route('loan-management.dashboard') }}">Open Admin Dashboard</a>
                            <a class="button-outline" href="{{ route('loan-management.public.customer-login') }}" onclick="return confirm('You are currently signed in as Administrator ({{ $adminUser->name ?? $adminUser->username ?? 'Admin' }}). Are you sure you want to log out first to switch to Customer Portal?');">Customer Login / Switch</a>
                        @else
                            <a class="button" href="#products">Shop Products</a>
                            <a class="button-outline" href="{{ route('loan-management.public.register') }}">Apply Now</a>
                        @endif
                    </div>
                </div>
                <aside class="hero-card">
                    <h2>Apply in a few minutes</h2>
                    <p>Select products, submit your registration, then our team reviews your installment request.</p>
                    <div class="quick-stat"><span>Step 1</span><strong>Add products</strong></div>
                    <div class="quick-stat"><span>Step 2</span><strong>Register account</strong></div>
                    <div class="quick-stat"><span>Step 3</span><strong>Staff follow-up</strong></div>
                </aside>
            </div>
        </section>

        <section class="section alt" id="how">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>Simple customer experience</h2>
                        <p>Customers can browse products, request installment service, and return later to view their own account dashboard.</p>
                    </div>
                </div>
                <div class="feature-grid">
                    <div class="feature"><div class="feature-icon">1</div><strong>Choose product</strong><span>Add computers or other products from the catalog into your installment cart.</span></div>
                    <div class="feature"><div class="feature-icon">2</div><strong>Submit request</strong><span>Register once and send the selected cart items to the business team.</span></div>
                    <div class="feature"><div class="feature-icon">3</div><strong>Track your account</strong><span>Login to see personal information, loan records, and recent payment history.</span></div>
                </div>
            </div>
        </section>

        <section class="section" id="products">
            <div class="section-inner shop-inner">
                <div>
                    <div class="section-head">
                        <div>
                            <h2>Products for Installment</h2>
                            <p>Browse installment catalog items, calculate monthly repayments, and add products to your cart for instant application.</p>
                        </div>
                    </div>

                    {{-- Search & Category Filter Toolbar --}}
                    <div class="catalog-filter-bar">
                        <div class="catalog-search-row">
                            <div class="search-input-box">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="catalogSearchInput" placeholder="Search by product name, brand, model, SKU..." autocomplete="off">
                                <button type="button" class="search-clear-btn" id="searchClearBtn" style="display: none;" title="Clear search">&times;</button>
                            </div>
                            <div class="sort-select-box">
                                <select id="catalogSortSelect">
                                    <option value="default">✨ Featured / Newest</option>
                                    <option value="price_low">💵 Price: Low to High</option>
                                    <option value="price_high">💎 Price: High to Low</option>
                                    <option value="name_asc">🔤 Name: A to Z</option>
                                </select>
                            </div>
                        </div>

                        {{-- Category Filter Chips --}}
                        <div class="category-chips-scroll" id="categoryChipsContainer">
                            <button type="button" class="category-chip active" data-category="all">
                                All Products <span class="chip-count" id="totalCountPill">{{ count($products) }}</span>
                            </button>
                            @foreach($categories ?? [] as $cat)
                                @php
                                    $catCount = collect($products)->where('category', $cat)->count();
                                @endphp
                                <button type="button" class="category-chip" data-category="{{ strtolower($cat) }}">
                                    {{ $cat }} <span class="chip-count">{{ $catCount }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Product Grid --}}
                    @if(!empty($products))
                        <div class="product-grid" id="productsCatalogGrid">
                            @foreach($products as $product)
                                @php
                                    $pPrice = (float) ($product['price'] ?? 0);
                                    $pName = (string) ($product['name'] ?? 'Product');
                                    $pSku = (string) ($product['sku'] ?? '');
                                    $pBrand = (string) ($product['brand'] ?? '');
                                    $pCat = (string) ($product['category'] ?? 'General');
                                    $pMinDp = (float) ($product['min_down_payment_percent'] ?? 0);
                                    $estMonthly = $pPrice > 0 ? round(($pPrice * 1.18) / 12, 2) : 0;
                                @endphp
                                <article class="product-card"
                                    data-name="{{ strtolower($pName) }}"
                                    data-sku="{{ strtolower($pSku) }}"
                                    data-brand="{{ strtolower($pBrand) }}"
                                    data-category="{{ strtolower($pCat) }}"
                                    data-price="{{ $pPrice }}"
                                    data-id="{{ $product['id'] ?? $loop->index }}">

                                    <div class="product-image-wrap">
                                        @if(!empty($product['image_url']))
                                            <img src="{{ $product['image_url'] }}" alt="{{ $pName }}" loading="lazy">
                                        @else
                                            <div class="product-fallback-icon">
                                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                            </div>
                                        @endif

                                        @if($pCat && strtolower($pCat) !== 'general')
                                            <span class="card-badge-top-left">{{ $pCat }}</span>
                                        @endif

                                        @if($pMinDp > 0)
                                            <span class="card-badge-top-right">Min {{ $pMinDp }}% DP</span>
                                        @else
                                            <span class="card-badge-top-right">0% Down Payment</span>
                                        @endif
                                    </div>

                                    <div class="product-body">
                                        @if($pBrand)
                                            <div class="product-brand-tag">{{ $pBrand }}</div>
                                        @endif
                                        <h3 class="product-title">{{ $pName }}</h3>
                                        <div class="product-sku">{{ $pSku ? 'SKU: ' . $pSku : 'Installment Eligible' }}</div>

                                        <div class="product-price-row">
                                            <div class="price-cash">${{ number_format($pPrice, 2) }}</div>
                                            @if($estMonthly > 0)
                                                <div class="price-monthly-tag">From ${{ number_format($estMonthly, 2) }}/mo</div>
                                            @endif
                                        </div>

                                        <div class="product-actions-grid">
                                            <button type="button" class="cart-btn" data-product='@json($product)'>
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                                Add to Cart
                                            </button>
                                            @if($customerUser)
                                                <a href="{{ route('loan-management.public.customer-loan-request', ['product_id' => $product['id']]) }}" class="apply-btn" title="Direct Installment Request">
                                                    Apply
                                                </a>
                                            @else
                                                <a href="{{ route('loan-management.public.register') }}" class="apply-btn" title="Direct Installment Request">
                                                    Apply
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div id="noProductsFoundMsg" style="display: none; padding: 48px 20px; text-align: center; background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px; margin-top: 10px;">
                            <div style="font-size: 38px; color: #94a3b8; margin-bottom: 8px;">🔍</div>
                            <h4 style="margin: 0 0 4px; font-weight: 800; color: #0f172a;">No matching products found</h4>
                            <p style="margin: 0 0 14px; color: #64748b; font-size: 13px;">Try searching with another keyword or resetting the category filter.</p>
                            <button type="button" class="button-outline" onclick="resetAllFilters()" style="min-height: 36px; padding: 0 14px; font-size: 13px;">Reset Filters</button>
                        </div>
                    @else
                        <div class="feature">
                            <strong>No installment products available</strong>
                            <span>Add products in Admin -> Installment Products to display them here.</span>
                        </div>
                    @endif
                </div>

                <aside class="cart-panel" id="cart">
                    <div class="cart-panel-head">
                        <h2>Installment Cart</h2>
                        <span class="cart-count-pill" id="cartCountPill">0 items</span>
                    </div>
                    <div class="cart-items" id="cartItems"></div>
                    <div class="cart-total">
                        <span>Estimated Total</span>
                        <span id="cartTotal">$0.00</span>
                    </div>
                    @if($customerUser)
                        <a class="cart-apply" href="{{ route('loan-management.public.customer-loan-request') }}" id="cartApply">
                            Apply Installment Installment
                        </a>
                    @else
                        <a class="cart-apply" href="{{ route('loan-management.public.register') }}" id="cartApply">
                            Apply Installment Installment
                        </a>
                    @endif
                </aside>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-inner">
                <span>{{ $businessName }}</span>
                <span>
                    @if($customerUser)
                        <a href="{{ route('loan-management.public.customer-dashboard') }}">Customer Dashboard</a>
                        &nbsp;|&nbsp;
                    @endif
                    @if($adminUser)
                        <a href="{{ route('loan-management.dashboard') }}">Admin Dashboard</a>
                        &nbsp;|&nbsp;
                    @else
                        <a href="{{ route('login') }}" @if($customerUser) onclick="return confirm('You are currently signed in as Customer ({{ $customerUser->name }}). Are you sure you want to log out first to access Admin Login?');" @endif>Admin Login</a>
                        &nbsp;|&nbsp;
                    @endif
                    <a href="{{ route('loan-management.public.customer-login') }}" @if($adminUser) onclick="return confirm('You are currently signed in as Administrator ({{ $adminUser->name ?? 'Admin' }}). Are you sure you want to log out first to switch to Customer Portal?');" @elseif($customerUser) onclick="return confirm('You are currently signed in as Customer ({{ $customerUser->name }}). Are you sure you want to log out first to switch accounts?');" @endif>Customer Portal</a>
                </span>
            </div>
        </footer>

        <!-- Mobile Bottom Navigation Bar -->
        <nav class="mobile-bottom-nav" aria-label="Mobile Bottom Navigation">
            <a href="#home" class="mobile-nav-item active" id="mobNavHome">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Home</span>
            </a>
            <a href="#products" class="mobile-nav-item" id="mobNavProducts">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span>Products</span>
            </a>
            <a href="#cart" class="mobile-nav-item" id="mobNavCart">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="mobile-nav-badge" id="mobileCartBadge">0</span>
                <span>Cart</span>
            </a>
            @if($customerUser)
                <a href="{{ route('loan-management.public.customer-dashboard') }}" class="mobile-nav-item" id="mobNavProfile">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Account</span>
                </a>
            @elseif($adminUser)
                <a href="{{ route('loan-management.dashboard') }}" class="mobile-nav-item" id="mobNavAdmin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Admin</span>
                </a>
            @else
                <a href="{{ route('loan-management.public.customer-login') }}" class="mobile-nav-item" id="mobNavLogin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <span>Login</span>
                </a>
            @endif
        </nav>
    </main>

    <script>
        (function () {
            var cartKey = 'loan_public_installment_cart';
            var cart = [];
            var itemsBox = document.getElementById('cartItems');
            var totalBox = document.getElementById('cartTotal');
            var countPill = document.getElementById('cartCountPill');
            var apply = document.getElementById('cartApply');
            var mobCartBadge = document.getElementById('mobileCartBadge');
            var isCustomerLoggedIn = {{ $customerUser ? 'true' : 'false' }};
            var applyBaseUrl = isCustomerLoggedIn
                ? '{{ route('loan-management.public.customer-loan-request') }}'
                : '{{ route('loan-management.public.register') }}';

            function money(value) {
                return '$' + Number(value || 0).toFixed(2);
            }

            function save() {
                localStorage.setItem(cartKey, JSON.stringify(cart));
            }

            function load() {
                try {
                    cart = JSON.parse(localStorage.getItem(cartKey) || '[]') || [];
                } catch (e) {
                    cart = [];
                }
            }

            function renderCart() {
                var total = 0;
                var totalItems = 0;
                itemsBox.innerHTML = '';
                if (!cart.length) {
                    itemsBox.innerHTML = '<div class="cart-empty">No products selected yet.</div>';
                }

                cart.forEach(function (item, index) {
                    var qty = Number(item.qty || 1);
                    var price = Number(item.price || 0);
                    total += price * qty;
                    totalItems += qty;

                    var row = document.createElement('div');
                    row.className = 'cart-item';
                    row.innerHTML = '<div><strong></strong><span></span></div><div class="qty-row"><button type="button" data-action="minus" data-index="' + index + '">-</button><span>' + qty + '</span><button type="button" data-action="plus" data-index="' + index + '">+</button></div>';
                    row.querySelector('strong').textContent = item.name || 'Product';
                    row.querySelector('span').textContent = money(price * qty);
                    itemsBox.appendChild(row);
                });

                totalBox.textContent = money(total);
                if (countPill) countPill.textContent = totalItems + (totalItems === 1 ? ' item' : ' items');
                if (mobCartBadge) {
                    mobCartBadge.textContent = totalItems;
                    mobCartBadge.style.display = totalItems > 0 ? 'inline-block' : 'none';
                }
                apply.href = applyBaseUrl + (cart.length ? '?cart=1' : '');
            }

            // Add to Cart Buttons
            document.querySelectorAll('.cart-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    var product = JSON.parse(button.getAttribute('data-product') || '{}');
                    var key = String(product.id || product.product_id || product.name);
                    var existing = cart.find(function (item) { return String(item.id || item.product_id || item.name) === key; });
                    if (existing) {
                        existing.qty = Number(existing.qty || 1) + 1;
                    } else {
                        product.qty = 1;
                        cart.push(product);
                    }
                    save();
                    renderCart();

                    // Button feedback animation
                    var originalHtml = button.innerHTML;
                    button.style.background = '#16a34a';
                    button.innerHTML = '✓ Added!';
                    setTimeout(function() {
                        button.style.background = '';
                        button.innerHTML = originalHtml;
                    }, 1200);
                });
            });

            // Cart Quantity Adjustment
            itemsBox.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-action]');
                if (!button) return;
                var index = Number(button.getAttribute('data-index'));
                if (!cart[index]) return;
                if (button.getAttribute('data-action') === 'plus') {
                    cart[index].qty = Number(cart[index].qty || 1) + 1;
                } else {
                    cart[index].qty = Number(cart[index].qty || 1) - 1;
                    if (cart[index].qty <= 0) cart.splice(index, 1);
                }
                save();
                renderCart();
            });

            load();
            renderCart();

            var sectionLinks = Array.from(document.querySelectorAll('[data-section-link]'));
            var trackedSections = sectionLinks
                .map(function (link) {
                    var id = link.getAttribute('data-section-link');
                    return { id: id, link: link, section: document.getElementById(id) };
                })
                .filter(function (item) { return !!item.section; });

            function setActiveSection(id) {
                sectionLinks.forEach(function (link) {
                    link.classList.toggle('active', link.getAttribute('data-section-link') === id);
                });
            }

            if (trackedSections.length) {
                window.addEventListener('scroll', function () {
                    var activeId = trackedSections[0].id;
                    var offset = 130;

                    trackedSections.forEach(function (item) {
                        if (item.section.getBoundingClientRect().top <= offset) {
                            activeId = item.id;
                        }
                    });

                    setActiveSection(activeId);
                }, { passive: true });
            }

            // Category & Search Engine
            var currentCategory = 'all';
            var searchInput = document.getElementById('catalogSearchInput');
            var searchClearBtn = document.getElementById('searchClearBtn');
            var sortSelect = document.getElementById('catalogSortSelect');
            var productGrid = document.getElementById('productsCatalogGrid');
            var noProductsMsg = document.getElementById('noProductsFoundMsg');

            function applyFilter() {
                var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
                if (searchClearBtn) {
                    searchClearBtn.style.display = query.length > 0 ? 'block' : 'none';
                }

                var cards = document.querySelectorAll('#productsCatalogGrid .product-card');
                var visibleCount = 0;

                cards.forEach(function (card) {
                    var cCat = (card.getAttribute('data-category') || '').toLowerCase();
                    var cName = (card.getAttribute('data-name') || '').toLowerCase();
                    var cSku = (card.getAttribute('data-sku') || '').toLowerCase();
                    var cBrand = (card.getAttribute('data-brand') || '').toLowerCase();

                    var matchesCat = (currentCategory === 'all' || cCat === currentCategory);
                    var matchesSearch = (query === '' || cName.includes(query) || cSku.includes(query) || cBrand.includes(query) || cCat.includes(query));

                    if (matchesCat && matchesSearch) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (noProductsMsg) {
                    noProductsMsg.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
                }
            }

            // Category Chip Click
            var chips = document.querySelectorAll('.category-chip');
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('active'); });
                    chip.classList.add('active');
                    currentCategory = chip.getAttribute('data-category') || 'all';
                    applyFilter();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }

            if (searchClearBtn) {
                searchClearBtn.addEventListener('click', function () {
                    searchInput.value = '';
                    applyFilter();
                    searchInput.focus();
                });
            }

            window.resetAllFilters = function () {
                if (searchInput) searchInput.value = '';
                currentCategory = 'all';
                chips.forEach(function (c) {
                    if (c.getAttribute('data-category') === 'all') c.classList.add('active');
                    else c.classList.remove('active');
                });
                if (sortSelect) sortSelect.value = 'default';
                applyFilter();
            };

            // Sorting Engine
            if (sortSelect && productGrid) {
                sortSelect.addEventListener('change', function () {
                    var val = sortSelect.value;
                    var cards = Array.from(productGrid.querySelectorAll('.product-card'));

                    cards.sort(function (a, b) {
                        var priceA = parseFloat(a.getAttribute('data-price')) || 0;
                        var priceB = parseFloat(b.getAttribute('data-price')) || 0;
                        var nameA = (a.getAttribute('data-name') || '').toLowerCase();
                        var nameB = (b.getAttribute('data-name') || '').toLowerCase();
                        var idA = parseInt(a.getAttribute('data-id')) || 0;
                        var idB = parseInt(b.getAttribute('data-id')) || 0;

                        if (val === 'price_low') return priceA - priceB;
                        if (val === 'price_high') return priceB - priceA;
                        if (val === 'name_asc') return nameA.localeCompare(nameB);
                        return idB - idA; // default newest
                    });

                    cards.forEach(function (card) {
                        productGrid.appendChild(card);
                    });
                });
            }

            // Profile Dropdowns Toggle
            document.addEventListener('click', function (e) {
                var custWrapper = document.getElementById('customerDropdownWrapper');
                var custToggle = document.getElementById('customerProfileToggle');
                if (custWrapper && custToggle) {
                    if (custToggle.contains(e.target)) {
                        custWrapper.classList.toggle('open');
                        custToggle.setAttribute('aria-expanded', custWrapper.classList.contains('open') ? 'true' : 'false');
                        var admWrapper = document.getElementById('adminDropdownWrapper');
                        if (admWrapper) admWrapper.classList.remove('open');
                    } else if (!custWrapper.contains(e.target)) {
                        custWrapper.classList.remove('open');
                        custToggle.setAttribute('aria-expanded', 'false');
                    }
                }

                var admWrapper = document.getElementById('adminDropdownWrapper');
                var admToggle = document.getElementById('adminProfileToggle');
                if (admWrapper && admToggle) {
                    if (admToggle.contains(e.target)) {
                        admWrapper.classList.toggle('open');
                        admToggle.setAttribute('aria-expanded', admWrapper.classList.contains('open') ? 'true' : 'false');
                        if (custWrapper) custWrapper.classList.remove('open');
                    } else if (!admWrapper.contains(e.target)) {
                        admWrapper.classList.remove('open');
                        admToggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        })();
    </script>
</body>
</html>
