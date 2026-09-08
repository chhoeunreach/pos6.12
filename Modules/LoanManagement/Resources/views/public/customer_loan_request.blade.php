@php
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $businessName = \Modules\LoanManagement\Services\BusinessSettingsService::businessName();
    $systemSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
    $themeColor = $systemSettings['theme_color'] ?? '#2563eb';
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();
    $displayName = trim((string) ($customer->khmer_name ?? '')) ?: trim((string) ($customer->name ?? 'Customer'));
    $customerPhotoUrl = $customer->customer_photo_url;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply for Installment Installment · {{ $businessName }}</title>
    <style>
        :root {
            --primary: {{ $themeColor }};
            --primary-dark: color-mix(in srgb, {{ $themeColor }} 80%, #000);
            --primary-light: color-mix(in srgb, {{ $themeColor }} 10%, #fff);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --success: #16a34a;
            --warning: #d97706;
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
                background: radial-gradient(1200px 600px at 15% -10%, color-mix(in srgb, {{ $themeColor }} 14%, transparent), transparent 60%),
                            radial-gradient(1000px 500px at 110% 110%, color-mix(in srgb, {{ $themeColor }} 10%, transparent), transparent 55%),
                            #f1f5f9;
            @endif
            color: var(--ink);
            line-height: 1.5;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(15,23,42,.03);
        }
        .topbar-inner {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            min-height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            font-weight: 800;
            text-decoration: none;
            font-size: 16px;
        }
        .logo {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: color-mix(in srgb, {{ $themeColor }} 12%, #fff);
            color: var(--primary);
            border: 1px solid color-mix(in srgb, {{ $themeColor }} 25%, transparent);
        }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .topbar-actions { display: flex; align-items: center; gap: 8px; }
        .btn-topbar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--line);
            background: #fff;
            color: #475569;
            border-radius: 8px;
            height: 36px;
            padding: 0 12px;
            font-weight: 700;
            font-size: 12px;
            text-decoration: none;
            transition: all .15s ease;
        }
        .btn-topbar:hover { background: var(--soft); color: var(--ink); border-color: #cbd5e1; }
        .btn-topbar svg { width: 14px; height: 14px; }

        /* Hero Banner */
        .hero {
            background: linear-gradient(135deg, color-mix(in srgb, {{ $themeColor }} 85%, #071221) 0%, {{ $themeColor }} 100%);
            color: #fff;
            padding: 32px 0 36px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -10px color-mix(in srgb, {{ $themeColor }} 40%, transparent);
        }
        .hero::after {
            content: "";
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, color-mix(in srgb, {{ $themeColor }} 30%, transparent) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-inner {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #93c5fd;
            margin-bottom: 10px;
        }
        .hero h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.5px;
        }
        .hero p {
            margin: 8px 0 0;
            color: rgba(255,255,255,.8);
            font-size: 14px;
            max-width: 680px;
            line-height: 1.6;
        }

        /* Layout Grid */
        .wrap {
            width: min(1200px, calc(100% - 32px));
            margin: -24px auto 60px;
            position: relative;
            z-index: 2;
        }
        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(360px, 1fr);
            gap: 22px;
            align-items: start;
        }

        /* Card Elements */
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 10px 30px -10px rgba(15,23,42,.06);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-head {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #fafbfc;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 800;
            color: var(--ink);
            margin: 0;
        }
        .card-title svg { color: var(--primary); }
        .card-body { padding: 22px 20px; }

        /* Form Controls */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full { grid-column: 1 / -1; }
        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .form-label span { color: #dc2626; }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            height: 44px;
            border: 1.5px solid #cbd5e1;
            border-radius: 9px;
            padding: 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            background: #fff;
            transition: all .15s ease;
            outline: none;
        }
        .form-textarea { height: auto; padding: 10px 12px; font-family: inherit; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px rgba(37,99,235,.15);
        }

        /* Quick Pills */
        .pill-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .btn-pill {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all .15s ease;
        }
        .btn-pill:hover, .btn-pill.active {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Products Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .items-table th, .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
            font-size: 13px;
        }
        .items-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .items-table td { font-weight: 600; color: #1e293b; vertical-align: middle; }
        .qty-stepper {
            display: inline-flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }
        .qty-btn {
            width: 26px;
            height: 28px;
            border: 0;
            background: #f8fafc;
            color: #334155;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qty-btn:hover { background: #e2e8f0; }
        .qty-input {
            width: 36px;
            height: 28px;
            border: 0;
            border-left: 1px solid #cbd5e1;
            border-right: 1px solid #cbd5e1;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            outline: none;
        }
        .btn-remove-item {
            border: 0;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 6px;
            width: 26px;
            height: 26px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
        }
        .btn-remove-item:hover { background: #fca5a5; }

        .btn-add-product {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all .15s ease;
            margin-top: 10px;
        }
        .btn-add-product:hover { background: var(--primary-light); border-color: var(--primary); }

        /* Calculation Metric Cards */
        .calc-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 18px;
        }
        .calc-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            text-align: left;
        }
        .calc-card.highlight {
            background: var(--primary-light);
            border-color: rgba(37,99,235,.25);
        }
        .calc-card-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .calc-card-val {
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
            margin-top: 4px;
        }
        .calc-card.highlight .calc-card-val { color: var(--primary); }

        /* Schedule Table */
        .schedule-table-wrap {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #edf2f7;
            border-radius: 8px;
            margin-top: 12px;
        }

        /* File Upload Dropzone */
        .doc-upload-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        .doc-dropzone {
            border: 1.5px dashed #cbd5e1;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            background: #fafbfc;
            cursor: pointer;
            transition: all .15s ease;
            position: relative;
        }
        .doc-dropzone:hover { border-color: var(--primary); background: var(--primary-light); }
        .doc-dropzone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer;
        }
        .doc-icon {
            width: 34px; height: 34px; border-radius: 8px; background: #e2e8f0; color: #475569;
            display: inline-flex; align-items: center; justify-content: center; margin-bottom: 6px;
        }
        .doc-dropzone:hover .doc-icon { background: var(--primary); color: #fff; }
        .doc-label { font-size: 12px; font-weight: 800; color: #334155; display: block; }
        .doc-sub { font-size: 11px; color: #94a3b8; display: block; margin-top: 2px; }
        .doc-preview { display: none; width: 100%; height: 80px; object-fit: cover; border-radius: 6px; margin-top: 6px; }

        /* Sticky Summary Panel */
        .sticky-summary {
            position: sticky;
            top: 86px;
        }
        .review-panel {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .review-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .review-row span { color: #64748b; font-weight: 600; }
        .review-row strong { color: var(--ink); font-weight: 800; }
        .review-row.total {
            font-size: 15px;
            border-bottom: 0;
            padding-top: 8px;
        }
        .review-row.total strong { color: var(--primary); font-size: 20px; }

        .status-badge-pending {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fef3c7;
            color: #b45309;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }

        .btn-submit-loan {
            width: 100%;
            height: 50px;
            border: 0;
            background: var(--primary);
            color: #fff;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .15s ease;
            box-shadow: 0 10px 24px -6px rgba(37,99,235,.4);
        }
        .btn-submit-loan:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 14px 28px -6px rgba(37,99,235,.5);
        }
        .btn-submit-loan:active { transform: translateY(0); }

        .catalog-modal {
            position: fixed; inset: 0; z-index: 1000;
            background: rgba(15,23,42,.6); backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center; padding: 16px;
        }
        .catalog-modal.active { display: flex; }
        .catalog-modal-box {
            width: min(720px, 100%); max-height: 85vh; background: #fff; border-radius: 16px;
            box-shadow: 0 24px 70px rgba(15,23,42,.25); overflow: hidden; display: flex; flex-direction: column;
        }
        .catalog-modal-head {
            padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center;
            justify-content: space-between; background: #fafbfc;
        }
        .catalog-modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .catalog-item-card {
            border: 1px solid var(--line); border-radius: 10px; padding: 10px; text-align: center;
            cursor: pointer; transition: all .15s ease; background: #fff; display: flex; flex-direction: column;
        }
        .catalog-item-card:hover { border-color: var(--primary); box-shadow: 0 6px 18px rgba(37,99,235,.12); transform: translateY(-2px); }
        .catalog-img { width: 100%; height: 90px; object-fit: cover; border-radius: 6px; background: #f8fafc; margin-bottom: 8px; }

        @media (max-width: 960px) {
            .grid { grid-template-columns: 1fr; }
            .calc-cards { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .form-grid { grid-template-columns: 1fr; }
            .doc-upload-grid { grid-template-columns: 1fr; }
            .sticky-summary { position: static; }
        }
        @media (max-width: 640px) {
            .topbar-inner { min-height: 50px; padding: 0 8px; gap: 6px; flex-wrap: nowrap; }
            .brand { font-size: 13px; gap: 6px; }
            .logo { width: 30px; height: 30px; border-radius: 7px; }
            .btn-topbar { height: 32px; padding: 0 8px; font-size: 11px; }
            .hero { padding: 22px 0 28px; }
            .hero h1 { font-size: 20px; letter-spacing: -.3px; }
            .hero p { font-size: 12px; line-height: 1.5; margin-top: 4px; }
            .wrap { width: min(1200px, calc(100% - 16px)); margin: -16px auto 40px; }
            .card-head { padding: 12px 14px; }
            .card-body { padding: 14px; }
            .calc-cards { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .calc-card { padding: 10px; border-radius: 8px; }
            .calc-val { font-size: 15px; }
            .calc-label { font-size: 10px; }
            .form-control { height: 40px; font-size: 13px; padding: 0 10px; }
            .btn-submit-loan { height: 44px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('loan-management.public.home') }}">
                <span class="logo">
                    @if($businessLogoUrl)<img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">@else{{ strtoupper(mb_substr($businessName, 0, 1)) }}@endif
                </span>
                <span>{{ $businessName }}</span>
            </a>
            <div class="topbar-actions">
                <a href="{{ route('loan-management.public.home') }}" class="btn-topbar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    Website
                </a>
                <a href="{{ route('loan-management.public.customer-dashboard') }}" class="btn-topbar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    My Dashboard
                </a>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-inner">
            <span class="hero-badge">⚡ Instant Installment Application</span>
            <h1>Create Installment Installment Request</h1>
            <p>Customize your product installments, select flexible repayment terms, and submit your application online. Your request will be queued as <strong>Pending</strong> for quick verification.</p>
        </div>
    </section>

    <main class="wrap">
        @if($errors->any())
            <div style="padding: 12px 16px; background: #fef2f2; border: 1px solid #fee2e2; color: #dc2626; border-radius: 10px; font-weight: 700; margin-bottom: 20px;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('loan-management.public.customer-loan-request.store') }}" enctype="multipart/form-data" id="loanRequestForm">
            @csrf
            <input type="hidden" name="items_json" id="itemsJsonInput" value="[]">

            <div class="grid">
                <!-- Left Main Column -->
                <div>
                    <!-- Section 1: Product Items -->
                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                1. Selected Installment Items
                            </h2>
                            <span id="itemsCountBadge" style="font-size: 12px; font-weight: 800; color: var(--muted);">0 items</span>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x: auto;">
                                <table class="items-table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Price</th>
                                            <th style="width: 110px;">Quantity</th>
                                            <th>Subtotal</th>
                                            <th style="width: 36px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 18px;">Loading selected products...</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px;">
                                <button type="button" class="btn-add-product" id="btnOpenCatalogModal">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    Browse Product Catalog
                                </button>
                                <button type="button" class="btn-add-product" id="btnAddCustomItem" style="color: #475569;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                                    Add Custom Item
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Installment Plan & Financial Terms -->
                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                2. Installment Terms & Calculation
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Total Product Cost ($)</label>
                                    <input type="number" step="0.01" class="form-input" id="totalProductPrice" readonly value="0.00" style="background: var(--soft); font-weight: 800;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Down Payment ($)</label>
                                    <input type="number" step="0.01" min="0" name="down_payment" class="form-input" id="downPaymentInput" value="0.00">
                                    <div class="pill-group">
                                        <button type="button" class="btn-pill" data-down-percent="0">0%</button>
                                        <button type="button" class="btn-pill" data-down-percent="10">10%</button>
                                        <button type="button" class="btn-pill" data-down-percent="20">20%</button>
                                        <button type="button" class="btn-pill" data-down-percent="30">30%</button>
                                        <button type="button" class="btn-pill" data-down-percent="50">50%</button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Financed Principal ($) <span>*</span></label>
                                    <input type="number" step="0.01" min="1" name="principal_amount" class="form-input" id="principalAmountInput" required value="0.00" style="font-size: 16px; font-weight: 800; color: var(--primary);">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Duration (Months) <span>*</span></label>
                                    <select name="duration_months" class="form-select" id="durationMonthsSelect" required>
                                        <option value="1">1 Month</option>
                                        <option value="3">3 Months</option>
                                        <option value="6" selected>6 Months</option>
                                        <option value="9">9 Months</option>
                                        <option value="12">12 Months (1 Year)</option>
                                        <option value="18">18 Months (1.5 Years)</option>
                                        <option value="24">24 Months (2 Years)</option>
                                        <option value="36">36 Months (3 Years)</option>
                                    </select>
                                    <div class="pill-group">
                                        <button type="button" class="btn-pill" data-duration="3">3 Mo</button>
                                        <button type="button" class="btn-pill active" data-duration="6">6 Mo</button>
                                        <button type="button" class="btn-pill" data-duration="12">12 Mo</button>
                                        <button type="button" class="btn-pill" data-duration="24">24 Mo</button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Interest Rate (% / Month)</label>
                                    <input type="number" step="0.01" min="0" name="interest_rate" class="form-input" id="interestRateInput" value="{{ $defaultInterestRate }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Interest Model</label>
                                    <select name="interest_type" class="form-select" id="interestTypeSelect">
                                        <option value="flat" selected>Flat Rate (Equal Monthly Interest)</option>
                                        <option value="reducing_balance">Reducing Balance (Declining Principal)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Payment Frequency</label>
                                    <select name="payment_frequency" class="form-select" id="frequencySelect">
                                        <option value="monthly" selected>Monthly Installment</option>
                                        <option value="weekly">Weekly Installment</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">First Payment Date</label>
                                    <input type="date" name="first_due_date" class="form-input" id="firstDueDateInput" value="{{ \Carbon\Carbon::today()->addMonth()->toDateString() }}">
                                </div>
                            </div>

                            <!-- Live Calculation Metric Cards -->
                            <div class="calc-cards">
                                <div class="calc-card highlight">
                                    <div class="calc-card-label">Monthly Payment</div>
                                    <div class="calc-card-val" id="monthlyPaymentDisplay">$0.00</div>
                                </div>
                                <div class="calc-card">
                                    <div class="calc-card-label">Financed Principal</div>
                                    <div class="calc-card-val" id="principalDisplay">$0.00</div>
                                </div>
                                <div class="calc-card">
                                    <div class="calc-card-label">Total Interest</div>
                                    <div class="calc-card-val" id="totalInterestDisplay">$0.00</div>
                                </div>
                                <div class="calc-card">
                                    <div class="calc-card-label">Total Repayment</div>
                                    <div class="calc-card-val" id="totalPayableDisplay">$0.00</div>
                                </div>
                            </div>

                            <!-- Schedule Preview -->
                            <div style="margin-top: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <span style="font-size: 13px; font-weight: 800; color: #334155;">Repayment Schedule Preview</span>
                                    <span style="font-size: 11px; color: var(--muted); font-weight: 700;">Live Breakdown</span>
                                </div>
                                <div class="schedule-table-wrap">
                                    <table class="items-table" style="margin-top: 0;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Due Date</th>
                                                <th>Principal</th>
                                                <th>Interest</th>
                                                <th>Installment Due</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody id="scheduleTableBody">
                                            <tr><td colspan="6" style="text-align: center; color: #94a3b8;">Calculating schedule...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Applicant & Employment Details -->
                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                3. Applicant Profile & Employment
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Full Name (Latin)</label>
                                    <input type="text" class="form-input" value="{{ $customer->name }}" readonly style="background: var(--soft);">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Khmer Name (Optional)</label>
                                    <input type="text" name="khmer_name" class="form-input" value="{{ $customer->khmer_name }}" placeholder="ឈ្មោះជាភាសាខ្មែរ">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number <span>*</span></label>
                                    <input type="tel" name="phone" class="form-input" value="{{ $customer->phone ?: $customer->username }}" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Alternate Phone / Telegram</label>
                                    <input type="tel" name="alternate_phone" class="form-input" value="{{ $customer->alternate_phone }}" placeholder="Secondary contact number">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">National ID / Passport #</label>
                                    <input type="text" name="id_card_number" class="form-input" value="{{ $customer->id_card_number }}" placeholder="ID card or passport number">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Workplace / Business Name</label>
                                    <input type="text" name="workplace" class="form-input" value="{{ $customer->workplace }}" placeholder="Company or business name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Monthly Income ($)</label>
                                    <input type="number" step="0.01" name="monthly_income" class="form-input" value="{{ $customer->monthly_income }}" placeholder="e.g. 500">
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">Current Address</label>
                                    <input type="text" name="address" class="form-input" value="{{ $customer->address }}" placeholder="Street address, Village, Commune, District, Province">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Document Uploads -->
                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                4. Identity & Verification Documents
                            </h2>
                            <span style="font-size: 11px; color: var(--muted); font-weight: 700;">Fast Approval</span>
                        </div>
                        <div class="card-body">
                            <div class="doc-upload-grid">
                                <label class="doc-dropzone" id="dropIdFront">
                                    <input type="file" name="id_card_front" accept="image/*,application/pdf" class="doc-file-input" data-preview="previewIdFront">
                                    <div class="doc-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>
                                    <span class="doc-label">National ID (Front)</span>
                                    <span class="doc-sub">Click to select photo</span>
                                    <img src="" class="doc-preview" id="previewIdFront" alt="ID Front Preview">
                                </label>

                                <label class="doc-dropzone" id="dropIdBack">
                                    <input type="file" name="id_card_back" accept="image/*,application/pdf" class="doc-file-input" data-preview="previewIdBack">
                                    <div class="doc-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>
                                    <span class="doc-label">National ID (Back)</span>
                                    <span class="doc-sub">Click to select photo</span>
                                    <img src="" class="doc-preview" id="previewIdBack" alt="ID Back Preview">
                                </label>

                                <label class="doc-dropzone" id="dropIncome">
                                    <input type="file" name="income_proof" accept="image/*,application/pdf" class="doc-file-input" data-preview="previewIncome">
                                    <div class="doc-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                                    <span class="doc-label">Income Proof / Pay Slip</span>
                                    <span class="doc-sub">Work ID or salary proof</span>
                                    <img src="" class="doc-preview" id="previewIncome" alt="Income Proof Preview">
                                </label>

                                <label class="doc-dropzone" id="dropCollateral">
                                    <input type="file" name="collateral_photo" accept="image/*,application/pdf" class="doc-file-input" data-preview="previewCollateral">
                                    <div class="doc-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><circle cx="12" cy="13" r="3"/></svg></div>
                                    <span class="doc-label">Product / Receipt Photo</span>
                                    <span class="doc-sub">Optional collateral photo</span>
                                    <img src="" class="doc-preview" id="previewCollateral" alt="Collateral Preview">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sticky Summary Column -->
                <div class="sticky-summary">
                    <!-- Guarantor Details Card -->
                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                Guarantor / Contact
                            </h2>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div class="form-group">
                                    <label class="form-label">Guarantor Name</label>
                                    <input type="text" name="guarantor_name" class="form-input" placeholder="e.g. Sok Dara" value="{{ $customer->family_contact_name ?? $customer->spouse_name ?? '' }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Guarantor Phone</label>
                                    <input type="tel" name="guarantor_phone" class="form-input" placeholder="e.g. 012 345 678" value="{{ $customer->family_contact_phone ?? $customer->spouse_phone ?? '' }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Relationship</label>
                                    <select name="guarantor_relationship" class="form-select">
                                        <option value="Family / Relative" selected>Family / Relative</option>
                                        <option value="Spouse">Spouse (Husband/Wife)</option>
                                        <option value="Parent">Parent</option>
                                        <option value="Sibling">Brother / Sister</option>
                                        <option value="Friend / Colleague">Friend / Colleague</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Remarks / Special Request</label>
                                    <textarea name="note" rows="2" class="form-textarea" placeholder="Provide any additional comments or preferred collection notes..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Application Review & Submit Card -->
                    <div class="card" style="border-color: #cbd5e1; background: #fafbfc;">
                        <div class="card-head" style="background: #f1f5f9;">
                            <h2 class="card-title">Application Summary</h2>
                            <span class="status-badge-pending">🟡 Pending Review</span>
                        </div>
                        <div class="card-body">
                            <div class="review-panel">
                                <div class="review-row">
                                    <span>Total Product Cost:</span>
                                    <strong id="summaryProductCost">$0.00</strong>
                                </div>
                                <div class="review-row">
                                    <span>Down Payment:</span>
                                    <strong id="summaryDownPayment">$0.00</strong>
                                </div>
                                <div class="review-row">
                                    <span>Financed Principal:</span>
                                    <strong id="summaryPrincipal" style="color: var(--primary);">$0.00</strong>
                                </div>
                                <div class="review-row">
                                    <span>Duration:</span>
                                    <strong id="summaryDuration">6 Months</strong>
                                </div>
                                <div class="review-row">
                                    <span>Interest Rate:</span>
                                    <strong id="summaryInterestRate">1.5% / mo</strong>
                                </div>
                                <div class="review-row">
                                    <span>Estimated Monthly Due:</span>
                                    <strong id="summaryMonthly" style="color: var(--primary); font-size: 16px;">$0.00</strong>
                                </div>
                                <div class="review-row total">
                                    <span>Total Repayment:</span>
                                    <strong id="summaryTotal">$0.00</strong>
                                </div>
                            </div>

                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn-submit-loan" id="btnSubmit">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Submit Installment Request
                                </button>
                            </div>
                            <div style="margin-top: 14px; text-align: center;">
                                <span style="font-size: 12px; color: var(--muted); line-height: 1.4; display: block;">
                                    🔒 Your request will be reviewed by staff. You can track installment status directly in your Customer Dashboard.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Product Catalog Modal -->
    <div class="catalog-modal" id="catalogModal">
        <div class="catalog-modal-box">
            <div class="catalog-modal-head">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800;">Select Product for Installment</h3>
                <button type="button" class="btn-remove-item" id="btnCloseCatalogModal" style="background: #e2e8f0; color: #334155;">×</button>
            </div>
            <div class="catalog-modal-body">
                <div style="margin-bottom: 14px;">
                    <input type="text" class="form-input" id="catalogSearchInput" placeholder="🔍 Search products by name or SKU...">
                </div>
                <div class="catalog-grid" id="catalogGrid">
                    @if(!empty($catalogProducts) && count($catalogProducts) > 0)
                        @foreach($catalogProducts as $prod)
                            <div class="catalog-item-card" data-name="{{ $prod['name'] }}" data-sku="{{ $prod['sku'] }}" data-price="{{ $prod['price'] }}">
                                @if(!empty($prod['image']))
                                    <img src="{{ $prod['image'] }}" class="catalog-img" alt="{{ $prod['name'] }}">
                                @else
                                    <div class="catalog-img" style="display: flex; align-items: center; justify-content: center; color: #94a3b8; font-weight: 800;">📦</div>
                                @endif
                                <strong style="font-size: 13px; color: var(--ink); margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $prod['name'] }}</strong>
                                <span style="font-size: 11px; color: var(--muted); margin-bottom: 6px;">SKU: {{ $prod['sku'] ?: '-' }}</span>
                                <span style="font-size: 14px; font-weight: 800; color: var(--primary); margin-top: auto;">${{ number_format($prod['price'], 2) }}</span>
                            </div>
                        @endforeach
                    @else
                        <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; padding: 24px;">No catalog products available.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var cartKey = 'loan_public_installment_cart';
            var cart = [];
            try {
                cart = JSON.parse(localStorage.getItem(cartKey) || '[]') || [];
            } catch (e) {
                cart = [];
            }

            var itemsTableBody = document.getElementById('itemsTableBody');
            var itemsCountBadge = document.getElementById('itemsCountBadge');
            var itemsJsonInput = document.getElementById('itemsJsonInput');
            var totalProductPriceInput = document.getElementById('totalProductPrice');
            var downPaymentInput = document.getElementById('downPaymentInput');
            var principalAmountInput = document.getElementById('principalAmountInput');
            var durationMonthsSelect = document.getElementById('durationMonthsSelect');
            var interestRateInput = document.getElementById('interestRateInput');
            var interestTypeSelect = document.getElementById('interestTypeSelect');
            var frequencySelect = document.getElementById('frequencySelect');
            var firstDueDateInput = document.getElementById('firstDueDateInput');

            var monthlyPaymentDisplay = document.getElementById('monthlyPaymentDisplay');
            var principalDisplay = document.getElementById('principalDisplay');
            var totalInterestDisplay = document.getElementById('totalInterestDisplay');
            var totalPayableDisplay = document.getElementById('totalPayableDisplay');
            var scheduleTableBody = document.getElementById('scheduleTableBody');

            var summaryProductCost = document.getElementById('summaryProductCost');
            var summaryDownPayment = document.getElementById('summaryDownPayment');
            var summaryPrincipal = document.getElementById('summaryPrincipal');
            var summaryDuration = document.getElementById('summaryDuration');
            var summaryInterestRate = document.getElementById('summaryInterestRate');
            var summaryMonthly = document.getElementById('summaryMonthly');
            var summaryTotal = document.getElementById('summaryTotal');

            function money(val) {
                return '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderItems() {
                itemsTableBody.innerHTML = '';
                var total = 0;

                if (!cart.length) {
                    itemsTableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #64748b; padding: 18px;">No items selected yet. Click "Browse Product Catalog" or "Add Custom Item" below.</td></tr>';
                    itemsCountBadge.textContent = '0 items';
                } else {
                    itemsCountBadge.textContent = cart.length + (cart.length === 1 ? ' item' : ' items');
                    cart.forEach(function (item, index) {
                        var qty = Number(item.qty || 1);
                        var price = Number(item.price || 0);
                        var subtotal = qty * price;
                        total += subtotal;

                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td><strong>' + (item.name || 'Product') + '</strong>' + (item.sku ? '<br><small style="color:#94a3b8;">SKU: ' + item.sku + '</small>' : '') + '</td>' +
                            '<td>' + money(price) + '</td>' +
                            '<td>' +
                                '<div class="qty-stepper">' +
                                    '<button type="button" class="qty-btn" data-step="-1" data-idx="' + index + '">−</button>' +
                                    '<input type="number" min="1" class="qty-input" value="' + qty + '" data-idx="' + index + '">' +
                                    '<button type="button" class="qty-btn" data-step="1" data-idx="' + index + '">+</button>' +
                                '</div>' +
                            '</td>' +
                            '<td><strong style="color:var(--ink);">' + money(subtotal) + '</strong></td>' +
                            '<td><button type="button" class="btn-remove-item" data-remove="' + index + '" title="Remove">×</button></td>';
                        itemsTableBody.appendChild(tr);
                    });
                }

                itemsJsonInput.value = JSON.stringify(cart);
                totalProductPriceInput.value = total.toFixed(2);

                if (total > 0 && (Number(principalAmountInput.value || 0) === 0 || Number(principalAmountInput.getAttribute('data-auto-filled')) === 1)) {
                    var down = Number(downPaymentInput.value || 0);
                    principalAmountInput.value = Math.max(1, total - down).toFixed(2);
                    principalAmountInput.setAttribute('data-auto-filled', '1');
                }

                recalculate();
            }

            function recalculate() {
                var totalProduct = Number(totalProductPriceInput.value || 0);
                var downPayment = Number(downPaymentInput.value || 0);
                var principal = Number(principalAmountInput.value || 0);
                var duration = parseInt(durationMonthsSelect.value || '6', 10);
                var ratePercent = Number(interestRateInput.value || 0);
                var rateDecimal = ratePercent / 100;
                var interestType = interestTypeSelect.value || 'flat';
                var firstDueDateStr = firstDueDateInput.value || '';

                if (principal <= 0 && totalProduct > 0) {
                    principal = Math.max(1, totalProduct - downPayment);
                    principalAmountInput.value = principal.toFixed(2);
                }

                var totalInterest = 0;
                var totalPayable = 0;
                var monthlyPayment = 0;

                if (interestType === 'reducing_balance') {
                    // Amortization calculation
                    var remaining = principal;
                    var principalPerMonth = duration > 0 ? (principal / duration) : 0;
                    totalInterest = 0;
                    for (var m = 1; m <= duration; m++) {
                        var monthlyInt = remaining * rateDecimal;
                        totalInterest += monthlyInt;
                        remaining = Math.max(0, remaining - principalPerMonth);
                    }
                    totalPayable = principal + totalInterest;
                    monthlyPayment = duration > 0 ? (totalPayable / duration) : 0;
                } else {
                    // Flat Rate calculation
                    totalInterest = principal * rateDecimal * duration;
                    totalPayable = principal + totalInterest;
                    monthlyPayment = duration > 0 ? (totalPayable / duration) : 0;
                }

                totalInterest = Math.round(totalInterest * 100) / 100;
                totalPayable = Math.round(totalPayable * 100) / 100;
                monthlyPayment = Math.round(monthlyPayment * 100) / 100;

                monthlyPaymentDisplay.textContent = money(monthlyPayment);
                principalDisplay.textContent = money(principal);
                totalInterestDisplay.textContent = money(totalInterest);
                totalPayableDisplay.textContent = money(totalPayable);

                summaryProductCost.textContent = money(totalProduct);
                summaryDownPayment.textContent = money(downPayment);
                summaryPrincipal.textContent = money(principal);
                summaryDuration.textContent = duration + (duration === 1 ? ' Month' : ' Months');
                summaryInterestRate.textContent = ratePercent + '% / mo (' + (interestType === 'reducing_balance' ? 'Reducing' : 'Flat') + ')';
                summaryMonthly.textContent = money(monthlyPayment);
                summaryTotal.textContent = money(totalPayable);

                renderSchedule(principal, duration, rateDecimal, interestType, firstDueDateStr);
            }

            function renderSchedule(principal, duration, rateDecimal, interestType, firstDueDateStr) {
                scheduleTableBody.innerHTML = '';
                if (principal <= 0 || duration <= 0) {
                    scheduleTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#94a3b8; padding: 14px;">Enter principal amount to preview schedule.</td></tr>';
                    return;
                }

                var baseDate = firstDueDateStr ? new Date(firstDueDateStr) : new Date();
                var remaining = principal;
                var principalPerMonth = Math.round((principal / duration) * 100) / 100;
                var flatInterestPerMonth = Math.round(principal * rateDecimal * 100) / 100;

                for (var i = 1; i <= duration; i++) {
                    var dueDate = new Date(baseDate.getFullYear(), baseDate.getMonth() + (i - 1), baseDate.getDate());
                    var dateStr = dueDate.toISOString().split('T')[0];

                    var princ = (i === duration) ? remaining : principalPerMonth;
                    var interestPart = (interestType === 'reducing_balance')
                        ? Math.round(remaining * rateDecimal * 100) / 100
                        : flatInterestPerMonth;

                    var monthTotal = Math.round((princ + interestPart) * 100) / 100;
                    remaining = Math.max(0, Math.round((remaining - princ) * 100) / 100);

                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + i + '</td>' +
                        '<td>' + dateStr + '</td>' +
                        '<td>' + money(princ) + '</td>' +
                        '<td>' + money(interestPart) + '</td>' +
                        '<td><strong style="color: var(--primary);">' + money(monthTotal) + '</strong></td>' +
                        '<td>' + money(remaining) + '</td>';
                    scheduleTableBody.appendChild(tr);
                }
            }

            // Product quantity & remove events
            itemsTableBody.addEventListener('click', function (e) {
                if (e.target.classList.contains('btn-remove-item')) {
                    var idx = parseInt(e.target.getAttribute('data-remove'), 10);
                    cart.splice(idx, 1);
                    localStorage.setItem(cartKey, JSON.stringify(cart));
                    renderItems();
                } else if (e.target.classList.contains('qty-btn')) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    var step = parseInt(e.target.getAttribute('data-step'), 10);
                    if (cart[idx]) {
                        cart[idx].qty = Math.max(1, (cart[idx].qty || 1) + step);
                        localStorage.setItem(cartKey, JSON.stringify(cart));
                        renderItems();
                    }
                }
            });

            itemsTableBody.addEventListener('change', function (e) {
                if (e.target.classList.contains('qty-input')) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    var val = Math.max(1, parseInt(e.target.value || '1', 10));
                    if (cart[idx]) {
                        cart[idx].qty = val;
                        localStorage.setItem(cartKey, JSON.stringify(cart));
                        renderItems();
                    }
                }
            });

            // Down Payment Quick Buttons
            document.querySelectorAll('[data-down-percent]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var percent = parseFloat(btn.getAttribute('data-down-percent'));
                    var total = Number(totalProductPriceInput.value || 0);
                    var down = Math.round((total * percent / 100) * 100) / 100;
                    downPaymentInput.value = down.toFixed(2);

                    document.querySelectorAll('[data-down-percent]').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');

                    if (total > 0) {
                        principalAmountInput.value = Math.max(1, total - down).toFixed(2);
                    }
                    recalculate();
                });
            });

            // Duration Quick Buttons
            document.querySelectorAll('[data-duration]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dur = btn.getAttribute('data-duration');
                    durationMonthsSelect.value = dur;
                    document.querySelectorAll('[data-duration]').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    recalculate();
                });
            });

            durationMonthsSelect.addEventListener('change', function () {
                var currentVal = durationMonthsSelect.value;
                document.querySelectorAll('[data-duration]').forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-duration') === currentVal);
                });
                recalculate();
            });

            downPaymentInput.addEventListener('input', function () {
                var total = Number(totalProductPriceInput.value || 0);
                var down = Number(downPaymentInput.value || 0);
                if (total > 0) {
                    principalAmountInput.value = Math.max(1, total - down).toFixed(2);
                }
                recalculate();
            });

            principalAmountInput.addEventListener('input', function () {
                principalAmountInput.removeAttribute('data-auto-filled');
                recalculate();
            });

            interestRateInput.addEventListener('input', recalculate);
            interestTypeSelect.addEventListener('change', recalculate);
            frequencySelect.addEventListener('change', recalculate);
            firstDueDateInput.addEventListener('change', recalculate);

            // Document Upload Previews
            document.querySelectorAll('.doc-file-input').forEach(function (input) {
                input.addEventListener('change', function () {
                    var previewId = input.getAttribute('data-preview');
                    var previewEl = document.getElementById(previewId);
                    if (input.files && input.files[0] && previewEl) {
                        var file = input.files[0];
                        if (file.type.startsWith('image/')) {
                            var reader = new FileReader();
                            reader.onload = function (e) {
                                previewEl.src = e.target.result;
                                previewEl.style.display = 'block';
                            };
                            reader.readAsDataURL(file);
                        } else {
                            previewEl.style.display = 'none';
                        }
                    }
                });
            });

            // Product Catalog Modal
            var catalogModal = document.getElementById('catalogModal');
            var btnOpenCatalogModal = document.getElementById('btnOpenCatalogModal');
            var btnCloseCatalogModal = document.getElementById('btnCloseCatalogModal');
            var catalogSearchInput = document.getElementById('catalogSearchInput');
            var btnAddCustomItem = document.getElementById('btnAddCustomItem');

            if (btnOpenCatalogModal && catalogModal) {
                btnOpenCatalogModal.addEventListener('click', function () {
                    catalogModal.classList.add('active');
                });
            }

            if (btnCloseCatalogModal && catalogModal) {
                btnCloseCatalogModal.addEventListener('click', function () {
                    catalogModal.classList.remove('active');
                });
            }

            if (catalogSearchInput) {
                catalogSearchInput.addEventListener('input', function () {
                    var query = catalogSearchInput.value.toLowerCase();
                    document.querySelectorAll('.catalog-item-card').forEach(function (card) {
                        var name = (card.getAttribute('data-name') || '').toLowerCase();
                        var sku = (card.getAttribute('data-sku') || '').toLowerCase();
                        card.style.display = (name.indexOf(query) !== -1 || sku.indexOf(query) !== -1) ? '' : 'none';
                    });
                });
            }

            document.querySelectorAll('.catalog-item-card').forEach(function (card) {
                card.addEventListener('click', function () {
                    var name = card.getAttribute('data-name');
                    var sku = card.getAttribute('data-sku');
                    var price = parseFloat(card.getAttribute('data-price') || '0');

                    // Check if already in cart
                    var found = false;
                    for (var i = 0; i < cart.length; i++) {
                        if (cart[i].name === name) {
                            cart[i].qty = (cart[i].qty || 1) + 1;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        cart.push({ name: name, sku: sku, price: price, qty: 1 });
                    }
                    localStorage.setItem(cartKey, JSON.stringify(cart));
                    renderItems();
                    if (catalogModal) catalogModal.classList.remove('active');
                });
            });

            if (btnAddCustomItem) {
                btnAddCustomItem.addEventListener('click', function () {
                    var name = prompt('Enter custom product/item description:', 'General Installment Item');
                    if (name) {
                        var priceStr = prompt('Enter item price ($):', '100');
                        var price = parseFloat(priceStr) || 100;
                        cart.push({ name: name, sku: 'CUSTOM', price: price, qty: 1 });
                        localStorage.setItem(cartKey, JSON.stringify(cart));
                        renderItems();
                    }
                });
            }

            // Form Submit: Clear cart on successful dispatch
            document.getElementById('loanRequestForm').addEventListener('submit', function () {
                localStorage.removeItem(cartKey);
            });

            renderItems();
        })();
    </script>
</body>
</html>
