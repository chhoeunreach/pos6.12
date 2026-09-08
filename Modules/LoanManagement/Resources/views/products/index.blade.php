@extends('loanmanagement::layouts.app')
@section('title', 'Products for Installment')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR PRODUCTS VIEW
       ========================================================= */
    .lm-prod-content {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Filters Component Styling */
    .lm-pos-filter-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px 18px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1024px) {
        .lm-pos-filter-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-pos-filter-grid { grid-template-columns: 1fr; }
    }
    .lm-pos-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .lm-pos-filter-field label {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }
    .lm-pos-filter-field .form-control {
        height: 38px;
        padding: 6px 12px;
        font-size: 13px;
        color: #1e293b;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        outline: none;
        width: 100%;
        box-shadow: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .lm-pos-filter-field .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }
    .lm-pos-filter-field select.form-control {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 28px;
        -webkit-appearance: none;
        appearance: none;
    }

    .lm-pos-filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
    }
    .lm-btn-pos-filter {
        height: 38px;
        padding: 0 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        background: #0284c7;
        color: #fff;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-btn-pos-filter:hover { background: #0369a1; }
    .lm-btn-pos-reset {
        height: 38px;
        padding: 0 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    .lm-btn-pos-reset:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }

    /* Ultimate POS DataTables Toolbar Layout */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        background: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .lm-dt-length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
        font-weight: 500 !important;
        font-size: 13px !important;
        color: #475569 !important;
    }
    .lm-dt-length select {
        height: 34px !important;
        padding: 2px 28px 2px 10px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 13px !important;
        color: #1e293b !important;
        background-color: #fff !important;
        outline: none !important;
    }
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        flex-wrap: wrap !important;
    }
    .lm-dt-buttons .btn {
        border-radius: 6px !important;
        padding: 6px 12px !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        transition: all 0.15s ease !important;
    }
    .lm-dt-buttons .btn:hover {
        background: #f8fafc !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .lm-dt-search {
        margin: 0 !important;
    }
    .lm-dt-search label {
        margin: 0 !important;
        display: block !important;
    }
    .lm-dt-search input {
        height: 34px !important;
        min-width: 220px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 6px 12px !important;
        font-size: 13px !important;
        outline: none !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transition: border-color 0.15s ease !important;
    }
    .lm-dt-search input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
    }

    .lm-dt-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        background: #ffffff !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-dt-info {
        font-size: 13px !important;
        color: #64748b !important;
        padding: 0 !important;
    }
    .lm-dt-pagination .pagination {
        margin: 0 !important;
    }
    .lm-dt-pagination .pagination > li > a {
        border-radius: 4px !important;
        margin: 0 2px !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
    }
    .lm-dt-pagination .pagination > .active > a {
        background-color: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
    }

    .product-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .product-kpi-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(15,23,42,0.03);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .product-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15,23,42,0.06);
    }
    .kpi-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .kpi-icon-blue { background: #eff6ff; color: #2563eb; }
    .kpi-icon-green { background: #ecfdf5; color: #16a34a; }
    .kpi-icon-amber { background: #fffbeb; color: #d97706; }
    .kpi-icon-red { background: #fef2f2; color: #dc2626; }
    .kpi-icon-purple { background: #faf5ff; color: #9333ea; }
    .kpi-meta { min-width: 0; }
    .kpi-title { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .kpi-val { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 1px; line-height: 1.15; }
    .kpi-sub { font-size: 11px; color: #94a3b8; font-weight: 400; margin-top: 2px; }

    .product-thumb {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        object-fit: cover;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: transform .15s ease;
    }
    .product-thumb:hover {
        transform: scale(1.15);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .product-thumb-fallback {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        background: #f1f5f9;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 700;
        white-space: nowrap;
    }
    .stock-badge.in-stock { background: #dcfce7; color: #15803d; }
    .stock-badge.low-stock { background: #fef3c7; color: #b45309; }
    .stock-badge.out-of-stock { background: #fee2e2; color: #dc2626; }

    .quick-qty-stepper {
        display: inline-flex;
        align-items: center;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .quick-qty-btn {
        background: #f8fafc;
        border: none;
        padding: 3px 8px;
        cursor: pointer;
        font-weight: 800;
        color: #334155;
        transition: background .12s ease;
        line-height: 1;
    }
    .quick-qty-btn:hover { background: #e2e8f0; color: #0f172a; }
    .quick-qty-val {
        padding: 2px 8px;
        font-weight: 700;
        min-width: 30px;
        text-align: center;
        font-size: 12px;
        cursor: pointer;
    }
    .quick-qty-val:hover { background: #eff6ff; text-decoration: underline; }

    .bulk-bar {
        display: none;
        background: #1e293b;
        color: #fff;
        padding: 10px 16px;
        border-radius: 8px;
        margin-bottom: 14px;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .sim-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 12px;
    }
    .sim-pill {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #fff;
        font-weight: 700;
        cursor: pointer;
        margin: 0 4px 6px 0;
        font-size: 12px;
        transition: all .15s ease;
    }
    .sim-pill.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    /* Modal z-index guarantee over any overlays or floating widgets */
    #simulatorModal, #photoModal {
        z-index: 10500 !important;
    }
    .modal-backdrop {
        z-index: 10490 !important;
    }
</style>
@endsection

@section('content_body')
<div class="lm-prod-content">
    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $lmText('Products for Installment', 'ទំនិញបង់រំលស់') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $lmText('Manage installment catalog products, cash pricing, available stock units, and loan terms', 'គ្រប់គ្រងទំនិញបង់រំលស់ តម្លៃលក់ ស្តុក និងលក្ខខណ្ឌស្នើសុំកម្ចី') }}
            </small>
        </h1>
    </section>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible" style="border-radius: 8px; margin-bottom: 16px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-check-circle"></i> {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible" style="border-radius: 8px; margin-bottom: 16px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-exclamation-triangle"></i> {{ $errors->first() }}
        </div>
    @endif

    {{-- KPI Metric Cards --}}
    <div class="product-kpi-grid">
        <div class="product-kpi-card">
            <div class="kpi-icon-wrap kpi-icon-blue"><i class="fa fa-cubes"></i></div>
            <div class="kpi-meta">
                <div class="kpi-title">{{ $lmText('Total Catalog', 'ទំនិញសរុប') }}</div>
                <div class="kpi-val">{{ number_format($totalProducts) }}</div>
                <div class="kpi-sub">{{ number_format($totalStockQty) }} units available</div>
            </div>
        </div>
        <div class="product-kpi-card">
            <div class="kpi-icon-wrap kpi-icon-green"><i class="fa fa-check-circle"></i></div>
            <div class="kpi-meta">
                <div class="kpi-title">{{ $lmText('In Stock', 'មានក្នុងស្តុក') }}</div>
                <div class="kpi-val text-success">{{ number_format($inStockCount) }}</div>
                <div class="kpi-sub">> 5 units available</div>
            </div>
        </div>
        <div class="product-kpi-card">
            <div class="kpi-icon-wrap kpi-icon-amber"><i class="fa fa-exclamation-triangle"></i></div>
            <div class="kpi-meta">
                <div class="kpi-title">{{ $lmText('Low Stock', 'ស្តុកជិតអស់') }}</div>
                <div class="kpi-val text-warning">{{ number_format($lowStockCount) }}</div>
                <div class="kpi-sub">1 - 5 units left</div>
            </div>
        </div>
        <div class="product-kpi-card">
            <div class="kpi-icon-wrap kpi-icon-red"><i class="fa fa-times-circle"></i></div>
            <div class="kpi-meta">
                <div class="kpi-title">{{ $lmText('Out of Stock', 'អស់ពីស្តុក') }}</div>
                <div class="kpi-val text-danger">{{ number_format($outOfStockCount) }}</div>
                <div class="kpi-sub">0 units available</div>
            </div>
        </div>
        <div class="product-kpi-card">
            <div class="kpi-icon-wrap kpi-icon-purple"><i class="fa fa-archive"></i></div>
            <div class="kpi-meta">
                <div class="kpi-title">{{ $lmText('Retail Stock Value', 'តម្លៃលក់សរុប') }}</div>
                <div class="kpi-val">${{ number_format($totalRetailValue, 2) }}</div>
                <div class="kpi-sub">Cost: ${{ number_format($totalCostValue, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Ultimate POS Standard Filters Component --}}
    @component('components.filters', ['title' => $lmText('Filters', 'តម្រងស្វែងរក')])
        <form method="GET" action="{{ route('loan-management.products.index') }}" id="loanProductsFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Row 1, Col 1: Business Location --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Business Location:', 'ទីតាំងសាខា:') }}</label>
                    <select name="location_id" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ (string) request('location_id') === (string) $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Row 1, Col 2: Category --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Category:', 'ប្រភេទ:') }}</label>
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Row 1, Col 3: Brand --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Brand:', 'ម៉ាកយីហោ:') }}</label>
                    <select name="brand" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                        @foreach($brands as $br)
                            <option value="{{ $br }}" {{ request('brand') === $br ? 'selected' : '' }}>{{ $br }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Row 1, Col 4: Stock Status --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Stock Status:', 'ស្ថានភាពស្តុក:') }}</label>
                    <select name="stock_status" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                        <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>{{ $lmText('In Stock (> 0)', 'មានក្នុងស្តុក') }}</option>
                        <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>{{ $lmText('Low Stock (1-5)', 'ស្តុកជិតអស់') }}</option>
                        <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>{{ $lmText('Out of Stock (0)', 'អស់ពីស្តុក') }}</option>
                    </select>
                </div>

                {{-- Row 2, Col 1: Search Name / SKU / IMEI --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Search Keyword:', 'ពាក្យស្វែងរក:') }}</label>
                    <div class="input-group" style="width: 100%;">
                        <span class="input-group-addon" style="background: #f8fafc; border-color: #cbd5e1;"><i class="fa fa-search"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ $lmText('Name, SKU, IMEI...', 'ឈ្មោះ, SKU, IMEI...') }}">
                    </div>
                </div>

                {{-- Row 2, Col 2: Sort By --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Sort By:', 'តម្រៀបតាម:') }}</label>
                    <select name="sort" class="form-control" onchange="this.form.submit()">
                        <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>{{ $lmText('Newest Added', 'ទើបបញ្ចូលថ្មី') }}</option>
                        <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>{{ $lmText('Name (A - Z)', 'ឈ្មោះ (A - Z)') }}</option>
                        <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>{{ $lmText('Price: Low to High', 'តម្លៃ: ទាប ទៅ ខ្ពស់') }}</option>
                        <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>{{ $lmText('Price: High to Low', 'តម្លៃ: ខ្ពស់ ទៅ ទាប') }}</option>
                        <option value="stock_desc" {{ request('sort') === 'stock_desc' ? 'selected' : '' }}>{{ $lmText('Stock Quantity: High', 'ស្តុក: ច្រើន ទៅ តិច') }}</option>
                        <option value="stock_asc" {{ request('sort') === 'stock_asc' ? 'selected' : '' }}>{{ $lmText('Stock Quantity: Low', 'ស្តុក: តិច ទៅ ច្រើន') }}</option>
                    </select>
                </div>

                {{-- Row 2, Col 3: Filter Action Buttons --}}
                <div class="lm-pos-filter-field">
                    <label>&nbsp;</label>
                    <div class="lm-pos-filter-actions">
                        <button type="submit" class="lm-btn-pos-filter">
                            <i class="fa fa-filter"></i> {{ $lmText('Filter', 'ចម្រាញ់') }}
                        </button>
                        <a href="{{ route('loan-management.products.index') }}" class="lm-btn-pos-reset">
                            <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}
                        </a>
                    </div>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $lmText('All Products for Installment', 'ទំនិញបង់រំលស់ទាំងអស់')])
        @slot('tool')
            <div class="box-tools pull-right" style="display: flex; align-items: center; gap: 8px;">
                <a href="{{ route('loan-management.products.export-csv', request()->query()) }}" class="btn btn-default btn-sm" style="border-radius: 6px; font-weight: 600;">
                    <i class="fa fa-download"></i> {{ $lmText('Export CSV', 'ទាញយក CSV') }}
                </a>
                <a href="{{ route('loan-management.products.create') }}" class="btn btn-primary btn-sm" style="border-radius: 20px; padding: 5px 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(2,132,199,0.2);">
                    <i class="fa fa-plus"></i> {{ $lmText('Add Product', 'បន្ថែមទំនិញ') }}
                </a>
            </div>
        @endslot

        {{-- Bulk Action Bar --}}
        <form id="bulkActionForm" method="POST" action="{{ route('loan-management.products.bulk-action') }}">
            @csrf
            <div id="bulkBar" class="bulk-bar">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 800; font-size: 13px;"><i class="fa fa-check-square-o"></i> <span id="selectedCount">0</span> selected</span>
                    <select name="bulk_action" id="bulkActionSelect" class="form-control input-sm" style="width: 170px; display: inline-block; background: #334155; color: #fff; border-color: #475569;">
                        <option value="">-- Choose Action --</option>
                        <option value="delete">Delete Selected</option>
                        <option value="stock_in_stock">Set Available (Qty: 1)</option>
                        <option value="assign_location">Assign Branch Location</option>
                    </select>
                    <select name="bulk_location_id" id="bulkLocationSelect" class="form-control input-sm" style="width: 150px; display: none; background: #334155; color: #fff; border-color: #475569;">
                        <option value="">None / All Branches</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="submitBulkAction()" style="font-weight: 700;">
                        <i class="fa fa-bolt"></i> Apply Bulk Action
                    </button>
                    <button type="button" class="btn btn-default btn-sm" onclick="deselectAll()" style="color: #333; margin-left: 6px;">
                        Cancel
                    </button>
                </div>
            </div>

            {{-- Product List Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="loanProductsTable" style="width: 100%; margin-bottom: 0;">
                    <thead>
                        <tr style="background: #f8fafc; color: #475569; font-size: 12px; font-weight: 700;">
                            <th style="width: 30px; text-align: center; vertical-align: middle;" class="no-export">
                                <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)">
                            </th>
                            <th style="width: 50px; text-align: center;" class="no-export">{{ $lmText('Photo', 'រូបភាព') }}</th>
                            <th>{{ $lmText('Product Name & Code', 'ឈ្មោះទំនិញ & កូដ') }}</th>
                            <th>{{ $lmText('Category & Brand', 'ប្រភេទ & ម៉ាក') }}</th>
                            <th>{{ $lmText('Selling Price', 'តម្លៃលក់') }}</th>
                            <th>{{ $lmText('Cost & Margin', 'ថ្លៃដើម & ចំណេញ') }}</th>
                            <th style="text-align: center;">{{ $lmText('Stock Qty', 'ចំនួនស្តុក') }}</th>
                            <th>{{ $lmText('Location', 'សាខា') }}</th>
                            <th style="width: 160px; text-align: center;" class="no-export">{{ $lmText('Actions', 'សកម្មភាព') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $p)
                            @php
                                $qty = (int) ($p->qty_available ?? 0);
                                $img = $p->image_url;
                                $brand = $p->brand;
                                $cat = $p->category;
                                $cost = (float) ($p->cost_price ?? 0);
                                $selling = (float) $p->selling_price;
                                $margin = $selling - $cost;
                                $marginPercent = $selling > 0 ? ($margin / $selling) * 100 : 0;
                            @endphp
                            <tr id="productRow-{{ $p->id }}">
                                <td style="text-align: center; vertical-align: middle;">
                                    <input type="checkbox" name="selected_ids[]" value="{{ $p->id }}" class="product-checkbox" onchange="updateSelectedCount()">
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    @if($img)
                                        <img src="{{ $img }}" alt="{{ $p->name }}" class="product-thumb" onclick="openPhotoModal('{{ $img }}', '{{ addslashes($p->name) }}')">
                                    @else
                                        <div class="product-thumb-fallback"><i class="fa fa-cube"></i></div>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    <a href="{{ route('loan-management.products.show', $p->id) }}" style="font-weight: 800; font-size: 13.5px; color: #1e293b; text-decoration: none;">
                                        {{ $p->name }}
                                    </a>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                        <strong>SKU:</strong> <code>{{ $p->sku ?: '-' }}</code>
                                        @if($p->imei)
                                            · <strong>IMEI:</strong> <span class="text-muted">{{ $p->imei }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($cat)
                                        <span class="label label-info" style="font-size: 10.5px; margin-right: 2px;">{{ $cat }}</span>
                                    @endif
                                    @if($brand)
                                        <span class="label label-default" style="font-size: 10.5px;">{{ $brand }}</span>
                                    @endif
                                    @if(! $cat && ! $brand)
                                        <span class="text-muted" style="font-size: 12px;">-</span>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    <span style="font-size: 14px; font-weight: 800; color: #0f172a;">${{ number_format($selling, 2) }}</span>
                                    @if($p->min_down_payment_percent > 0)
                                        <div style="font-size: 11px; color: #d97706; font-weight: 700;">
                                            Min DP: {{ $p->min_down_payment_percent }}% (${{ number_format($selling * ($p->min_down_payment_percent / 100), 2) }})
                                        </div>
                                    @endif
                                </td>
                                <td style="vertical-align: middle; font-size: 12px;">
                                    <div style="color: #64748b;">${{ number_format($cost, 2) }}</div>
                                    <span class="label {{ $margin >= 0 ? 'label-success' : 'label-danger' }}" style="font-size: 10px;">
                                        +${{ number_format($margin, 2) }} ({{ number_format($marginPercent, 1) }}%)
                                    </span>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <div style="margin-bottom: 4px;">
                                        <span id="stockBadge-{{ $p->id }}" class="stock-badge {{ $qty > 5 ? 'in-stock' : ($qty > 0 ? 'low-stock' : 'out-of-stock') }}">
                                            <i class="fa {{ $qty > 5 ? 'fa-check' : ($qty > 0 ? 'fa-exclamation-triangle' : 'fa-times-circle') }}"></i>
                                            <span id="stockBadgeText-{{ $p->id }}">{{ $qty > 5 ? "{$qty} units" : ($qty > 0 ? "{$qty} low" : '0 Out') }}</span>
                                        </span>
                                    </div>
                                    {{-- Quick Stepper --}}
                                    <div class="quick-qty-stepper" title="Click to adjust quantity">
                                        <button type="button" class="quick-qty-btn" onclick="adjustStock({{ $p->id }}, -1)">-</button>
                                        <span class="quick-qty-val" id="qtyVal-{{ $p->id }}" onclick="promptExactStock({{ $p->id }}, {{ $qty }})">{{ $qty }}</span>
                                        <button type="button" class="quick-qty-btn" onclick="adjustStock({{ $p->id }}, 1)">+</button>
                                    </div>
                                </td>
                                <td style="vertical-align: middle; font-size: 12px; color: #475569;">
                                    <i class="fa fa-map-marker text-muted" style="margin-right: 3px;"></i>
                                    {{ $p->location->name ?? $lmText('All Branches', 'គ្រប់សាខា') }}
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <div class="btn-group" style="display: inline-flex; gap: 3px;">
                                        {{-- Create Installment for this product --}}
                                        <a href="{{ route('loan-management.loans.create', ['product_id' => $p->id, 'product_name' => $p->name, 'principal_amount' => $p->selling_price]) }}" class="btn btn-xs btn-success" title="{{ $lmText('Create Installment for this Product', 'បង្កើតកម្ចីសម្រាប់ទំនិញនេះ') }}">
                                            <i class="fa fa-plus-circle"></i>
                                        </a>
                                        {{-- Simulator Modal trigger --}}
                                        <button type="button" class="btn btn-xs btn-info" onclick="openSimulatorModal({{ $p->id }})" title="{{ $lmText('Instant Installment Calculator', 'គណនាបង់រំលស់រហ័ស') }}">
                                            <i class="fa fa-calculator"></i>
                                        </button>
                                        {{-- Show details --}}
                                        <a href="{{ route('loan-management.products.show', $p->id) }}" class="btn btn-xs btn-default" title="{{ $lmText('View Details', 'មើលព័ត៌មាន') }}">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        {{-- Edit --}}
                                        <a href="{{ route('loan-management.products.edit', $p->id) }}" class="btn btn-xs btn-primary" title="{{ $lmText('Edit Product', 'កែប្រែ') }}">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        {{-- Delete --}}
                                        <button type="button" class="btn btn-xs btn-danger" onclick="deleteSingleProduct({{ $p->id }}, '{{ addslashes($p->name) }}')" title="{{ $lmText('Delete Product', 'លុប') }}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    @endcomponent
</div>

{{-- Hidden Single Delete Form --}}
<form id="singleDeleteForm" method="POST" action="" style="display:none;">
    @csrf
    @method('DELETE')
</form>

{{-- Installment Calculator Modal --}}
<div class="modal fade" id="simulatorModal" tabindex="-1" role="dialog" aria-labelledby="simulatorModalLabel">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: #1e293b; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: .8;">&times;</button>
                <h4 class="modal-title" id="simulatorModalLabel" style="font-weight: 800; font-size: 16px;">
                    <i class="fa fa-calculator text-primary"></i> <span id="modalProductName">Installment Calculator</span>
                </h4>
            </div>
            <div class="modal-body" style="padding: 20px; background: #fff;">
                <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid #e2e8f0;">
                    <img id="modalProductImg" src="" alt="" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; display: none;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;" id="modalProductSku"></div>
                        <div style="font-size: 22px; font-weight: 800; color: #0f172a;" id="modalProductPrice">$0.00</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label style="font-weight: 700; font-size: 12px;">Down Payment ($)</label>
                            <input type="number" id="modalDownPayment" class="form-control input-sm" value="0" min="0" step="1" oninput="recalcModalSim()">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label style="font-weight: 700; font-size: 12px;">Monthly Interest Rate (%)</label>
                            <input type="number" id="modalInterestRate" class="form-control input-sm" value="1.5" min="0" max="100" step="0.1" oninput="recalcModalSim()">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-weight: 700; font-size: 12px; display: block;">Select Loan Term</label>
                    <div id="modalTermPills">
                        <span class="sim-pill" onclick="selectModalTerm(3, this)">3 Months</span>
                        <span class="sim-pill" onclick="selectModalTerm(6, this)">6 Months</span>
                        <span class="sim-pill active" onclick="selectModalTerm(12, this)">12 Months</span>
                        <span class="sim-pill" onclick="selectModalTerm(18, this)">18 Months</span>
                        <span class="sim-pill" onclick="selectModalTerm(24, this)">24 Months</span>
                    </div>
                </div>

                <div class="sim-card">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                        <span style="color: #64748b;">Financed Principal:</span>
                        <strong id="simFinancedAmount" style="color: #0f172a;">$0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                        <span style="color: #64748b;">Total Interest:</span>
                        <strong id="simTotalInterest" style="color: #d97706;">$0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                        <span style="color: #64748b;">Total Repayment:</span>
                        <strong id="simTotalPayable" style="color: #0f172a;">$0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px dashed #cbd5e1; font-size: 16px; font-weight: 800;">
                        <span style="color: #2563eb;">Estimated Monthly:</span>
                        <span id="simMonthlyAmount" style="color: #2563eb; font-size: 20px;">$0.00 / mo</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <a id="modalCreateLoanBtn" href="#" class="btn btn-success" style="font-weight: 700;">
                    <i class="fa fa-arrow-right"></i> Proceed to Create Loan
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Photo Lightbox Modal --}}
<div class="modal fade" id="photoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document" style="margin-top: 80px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; text-align: center; background: #0f172a; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
            <div class="modal-body" style="padding: 10px;">
                <img id="lightboxImg" src="" alt="" style="max-width: 100%; max-height: 400px; border-radius: 6px;">
                <div id="lightboxCaption" style="color: #fff; font-weight: 700; margin-top: 8px; font-size: 13px;"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('loan_js')
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
    var currentSimProduct = null;
    var currentSelectedMonths = 12;

    // Ensure modals are appended to <body> so they escape any parent overflow / stacking context
    $(document).ready(function() {
        $('#simulatorModal').appendTo('body');
        $('#photoModal').appendTo('body');

        // Initialize DataTables with Ultimate POS toolbar
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanProductsTable')) {
            var tableButtons = [];
            if ($.fn.dataTable.Buttons) {
                tableButtons = [
                    {
                        extend: 'copy',
                        text: 'Copy',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fa fa-file-text-o"></i> Export CSV',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel-o"></i> Export Excel',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print"></i> Print',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)', stripHtml: true }
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fa fa-columns"></i> Column visibility',
                        className: 'btn btn-default btn-sm'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fa fa-file-pdf-o"></i> Export PDF <i class="fa fa-caret-down" style="margin-left:2px;"></i>',
                        className: 'btn btn-default btn-sm',
                        orientation: 'landscape',
                        pageSize: 'A4',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    }
                ];
            }

            $('#loanProductsTable').DataTable({
                dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
                buttons: tableButtons,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $lmText('All', 'ទាំងអស់') }}"]],
                order: [[2, 'asc']],
                autoWidth: false,
                language: {
                    search: '',
                    searchPlaceholder: 'Search ...',
                    lengthMenu: 'Show _MENU_ entries',
                    emptyTable: '{{ $lmText("No installment products found.", "មិនមានទំនិញបង់រំលស់នៅឡើយទេ។") }}',
                    info: '{{ $lmText("Showing _START_ to _END_ of _TOTAL_ entries", "បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ") }}',
                    infoEmpty: '{{ $lmText("Showing 0 to 0 of 0 entries", "បង្ហាញ 0 នៃ 0 ធាតុ") }}',
                    infoFiltered: '({{ $lmText("filtered from _MAX_ total entries", "ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប") }})',
                    paginate: {
                        first: '{{ $lmText("First", "ដំបូង") }}',
                        last: '{{ $lmText("Last", "ចុងក្រោយ") }}',
                        next: '{{ $lmText("Next", "បន្ទាប់") }}',
                        previous: '{{ $lmText("Previous", "មុន") }}'
                    }
                },
                columnDefs: [
                    { targets: [0, 1, 8], orderable: false, className: 'no-export' },
                    { targets: [0, 1, 6, 8], className: 'text-center' },
                    { targets: [4, 5], className: 'text-right' }
                ]
            });
        }
    });

    // Checkbox and Bulk Actions
    function toggleSelectAll(master) {
        var checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = master.checked;
        });
        updateSelectedCount();
    }

    function deselectAll() {
        var master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = false;
        var checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(function(cb) { cb.checked = false; });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var checked = document.querySelectorAll('.product-checkbox:checked');
        var count = checked.length;
        var countSpan = document.getElementById('selectedCount');
        var bulkBar = document.getElementById('bulkBar');
        if (countSpan) countSpan.textContent = count;
        if (bulkBar) bulkBar.style.display = count > 0 ? 'flex' : 'none';
    }

    var bulkSelectEl = document.getElementById('bulkActionSelect');
    if (bulkSelectEl) {
        bulkSelectEl.addEventListener('change', function() {
            var locSelect = document.getElementById('bulkLocationSelect');
            if (this.value === 'assign_location') {
                locSelect.style.display = 'inline-block';
            } else {
                locSelect.style.display = 'none';
            }
        });
    }

    function submitBulkAction() {
        var action = document.getElementById('bulkActionSelect').value;
        if (!action) {
            alert('Please select an action to perform.');
            return;
        }
        var count = document.querySelectorAll('.product-checkbox:checked').length;
        if (count === 0) {
            alert('Please select at least one product.');
            return;
        }
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete ' + count + ' selected product(s)? Products with active loans will be protected.')) {
                return;
            }
        }
        document.getElementById('bulkActionForm').submit();
    }

    // Single Delete
    function deleteSingleProduct(id, name) {
        if (confirm('Are you sure you want to delete "' + name + '"?')) {
            var form = document.getElementById('singleDeleteForm');
            form.action = '/loan-management/products/' + id;
            form.submit();
        }
    }

    // Quick Live Stock Adjust
    function adjustStock(productId, delta) {
        fetch('/loan-management/products/' + productId + '/stock-adjust', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ change_qty: delta })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                var valEl = document.getElementById('qtyVal-' + productId);
                if (valEl) valEl.textContent = data.new_qty;

                var badgeEl = document.getElementById('stockBadge-' + productId);
                var textEl = document.getElementById('stockBadgeText-' + productId);
                if (badgeEl && textEl) {
                    badgeEl.className = 'stock-badge ' + data.status_class;
                    textEl.textContent = data.status_text;
                    var iconEl = badgeEl.querySelector('i');
                    if (iconEl) {
                        iconEl.className = 'fa ' + (data.status_class === 'in-stock' ? 'fa-check' : (data.status_class === 'low-stock' ? 'fa-exclamation-triangle' : 'fa-times-circle'));
                    }
                }
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('Failed to update stock.');
        });
    }

    function promptExactStock(productId, currentVal) {
        var input = prompt('Enter exact stock quantity for this product:', currentVal);
        if (input === null) return;
        var newQty = parseInt(input, 10);
        if (isNaN(newQty) || newQty < 0) {
            alert('Please enter a valid positive number.');
            return;
        }

        fetch('/loan-management/products/' + productId + '/stock-adjust', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ set_qty: newQty })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                var valEl = document.getElementById('qtyVal-' + productId);
                if (valEl) valEl.textContent = data.new_qty;

                var badgeEl = document.getElementById('stockBadge-' + productId);
                var textEl = document.getElementById('stockBadgeText-' + productId);
                if (badgeEl && textEl) {
                    badgeEl.className = 'stock-badge ' + data.status_class;
                    textEl.textContent = data.status_text;
                }
            }
        })
        .catch(function(err) {
            console.error(err);
        });
    }

    // Photo Lightbox
    function openPhotoModal(imgUrl, caption) {
        $('#photoModal').appendTo('body');
        document.getElementById('lightboxImg').src = imgUrl;
        document.getElementById('lightboxCaption').textContent = caption;
        $('#photoModal').modal('show');
    }

    // Interactive Simulator Modal
    function openSimulatorModal(productId) {
        $('#simulatorModal').appendTo('body');
        fetch('/loan-management/products/' + productId + '/calculator-data')
            .then(function(res) { return res.json(); })
            .then(function(product) {
                currentSimProduct = product;
                document.getElementById('modalProductName').textContent = product.name;
                document.getElementById('modalProductSku').textContent = 'SKU: ' + (product.sku || '-');
                document.getElementById('modalProductPrice').textContent = '$' + Number(product.selling_price).toFixed(2);
                
                var imgEl = document.getElementById('modalProductImg');
                if (product.image_url) {
                    imgEl.src = product.image_url;
                    imgEl.style.display = 'block';
                } else {
                    imgEl.style.display = 'none';
                }

                var defaultDp = 0;
                if (product.min_down_payment_percent > 0) {
                    defaultDp = Math.round(product.selling_price * (product.min_down_payment_percent / 100));
                }
                document.getElementById('modalDownPayment').value = defaultDp;
                document.getElementById('modalDownPayment').max = product.selling_price;
                document.getElementById('modalCreateLoanBtn').href = product.create_loan_url;

                recalcModalSim();
                $('#simulatorModal').modal('show');
            })
            .catch(function(err) {
                console.error(err);
                alert('Could not load calculator data.');
            });
    }

    function selectModalTerm(months, pillEl) {
        currentSelectedMonths = months;
        var pills = document.querySelectorAll('#modalTermPills .sim-pill');
        pills.forEach(function(p) { p.classList.remove('active'); });
        pillEl.classList.add('active');
        recalcModalSim();
    }

    function recalcModalSim() {
        if (!currentSimProduct) return;
        var price = parseFloat(currentSimProduct.selling_price) || 0;
        var dp = parseFloat(document.getElementById('modalDownPayment').value) || 0;
        var rate = (parseFloat(document.getElementById('modalInterestRate').value) || 0) / 100;
        var months = currentSelectedMonths || 12;

        var financed = Math.max(0, price - dp);
        var monthlyPrincipal = financed / months;
        var monthlyInterest = financed * rate;
        var monthlyTotal = monthlyPrincipal + monthlyInterest;
        var totalInterest = monthlyInterest * months;
        var totalPayable = financed + totalInterest;

        document.getElementById('simFinancedAmount').textContent = '$' + financed.toFixed(2);
        document.getElementById('simTotalInterest').textContent = '$' + totalInterest.toFixed(2);
        document.getElementById('simTotalPayable').textContent = '$' + totalPayable.toFixed(2);
        document.getElementById('simMonthlyAmount').textContent = '$' + monthlyTotal.toFixed(2) + ' / mo';

        // Update Create Loan URL with down payment and term
        var baseUrl = currentSimProduct.create_loan_url;
        var updatedUrl = baseUrl + '&down_payment=' + dp + '&duration_months=' + months + '&interest_rate=' + (rate * 100);
        document.getElementById('modalCreateLoanBtn').href = updatedUrl;
    }
</script>
@endsection
