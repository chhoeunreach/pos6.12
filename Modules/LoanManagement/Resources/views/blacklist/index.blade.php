@extends('loanmanagement::layouts.app')

@php
    $isKhmer = $isKhmer ?? (session('user.language', config('app.locale')) === 'km');
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($val) => '$' . number_format((float) ($val ?? 0), 2);
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $dateRangeDisplay = $dateFrom && $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('m-d-Y').' - '.\Carbon\Carbon::parse($dateTo)->format('m-d-Y')
        : '';
    $hasActiveFilters = $dateRangeDisplay !== '' || trim((string) ($filters['search'] ?? '')) !== '';
@endphp

@section('title', $text('Blacklist & Risk Registry', 'បញ្ជីខ្មៅអតិថិជន'))

@section('loan_css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR BLACKLIST VIEW
       ========================================================= */
    .lm-blacklist-page {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Customer Search (Same as New Installment) */
    .lm-customer-search-wrap {
        position: relative;
    }
    .lm-search-input-inner {
        position: relative;
        display: flex;
        align-items: center;
    }
    .lm-search-input-field {
        height: 40px !important;
        border-radius: 8px !important;
        padding-left: 36px !important;
        padding-right: 32px !important;
        font-size: 13px !important;
        border: 1.5px solid #cbd5e1 !important;
        background: #fff !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
        width: 100% !important;
        color: #0f172a !important;
    }
    .lm-search-input-field:focus {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.18) !important;
        outline: none !important;
    }
    #modalAddBlacklist .modal-content {
        overflow: visible !important;
    }
    #modalAddBlacklist .modal-body {
        overflow: visible !important;
        position: relative;
    }
    .lm-customer-search-wrap {
        position: relative !important;
        z-index: 100;
    }
    .lm-customer-search-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        max-height: 280px;
        overflow-y: auto;
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        box-shadow: 0 14px 30px -4px rgba(15,23,42,0.28);
        z-index: 9999999 !important;
    }
    .lm-customer-search-dropdown::-webkit-scrollbar {
        width: 6px;
    }
    .lm-customer-search-dropdown::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .lm-cs-row {
        padding: 9px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .lm-cs-row:last-child {
        border-bottom: none;
    }
    .lm-cs-row:hover, .lm-cs-row.selected {
        background: #eff6ff;
    }
    .lm-cs-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #cbd5e1;
        flex-shrink: 0;
    }
    .lm-cs-avatar-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #e0f2fe;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .lm-cs-info {
        flex: 1;
        min-width: 0;
        line-height: 1.35;
    }
    .lm-cs-name {
        font-weight: 700;
        color: #0f172a;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-cs-sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr) auto;
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

    /* Table Typography & Styling */
    .lm-table-dense {
        margin-bottom: 0 !important;
        border-collapse: collapse !important;
    }
    .lm-table-dense th {
        font-size: 12.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
        color: #475569 !important;
        background: #f8fafc !important;
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 1px solid #cbd5e1 !important;
        padding: 10px 12px !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
    }
    .lm-table-dense td {
        font-size: 13px !important;
        color: #1e293b !important;
        padding: 10px 12px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-table-dense tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* Bottom Info & Pagination */
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

    /* --- KPI STATS CARDS --- */
    .lm-blacklist-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    .lm-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .lm-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .lm-stat-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .lm-stat-danger .lm-stat-card-icon { background: #fff1f2; color: #e11d48; }
    .lm-stat-amber .lm-stat-card-icon { background: #fffbeb; color: #d97706; }
    .lm-stat-indigo .lm-stat-card-icon { background: #eef2ff; color: #4f46e5; }
    .lm-stat-slate .lm-stat-card-icon { background: #f1f5f9; color: #64748b; }
    .lm-stat-card-content small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 2px;
    }
    .lm-stat-card-content strong {
        display: block;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }

    /* Badges & Tags */
    .lm-badge-blacklist {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 999px;
        background: #fff1f2;
        color: #e11d48;
        border: 1px solid #fecdd3;
        font-size: 11px;
        font-weight: 700;
    }
    .lm-reason-pill {
        display: inline-block;
        max-width: 240px;
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
        padding: 4px 9px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.3;
        white-space: normal;
        word-break: break-word;
    }
    .lm-cust-link {
        font-weight: 700;
        color: #0f172a;
        text-decoration: none !important;
    }
    .lm-cust-link:hover {
        color: #e11d48;
    }
    .lm-phone-link {
        color: #64748b;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none !important;
    }
    .lm-phone-link:hover {
        color: #0f172a;
    }

    /* Chips for quick reasons in Modal */
    .lm-quick-reason-chip {
        display: inline-block;
        padding: 5px 11px;
        margin: 0 4px 6px 0;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .lm-quick-reason-chip:hover {
        background: #fff1f2;
        color: #e11d48;
        border-color: #fecdd3;
    }

    @media (max-width: 992px) {
        .lm-blacklist-stats { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .lm-blacklist-stats { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content_body')
<div class="lm-blacklist-page">

    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $text('Blacklist & Risk Registry', 'បញ្ជីខ្មៅ និងបញ្ជីហានិភ័យ') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $text('Monitor and prevent high-risk or defaulting customers from obtaining new installment financing', 'គ្រប់គ្រង និងទប់ស្កាត់អតិថិជនមានហានិភ័យខ្ពស់ ឬខូចបំណុលពីការទទួលកម្ចីថ្មី') }}
            </small>
        </h1>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('loan-management.blacklist.export-csv', request()->query(), false) }}" class="btn btn-default btn-sm" style="font-weight: 700; border-radius: 6px;">
                <i class="fa fa-download"></i> {{ $text('Download CSV', 'ទាញយក CSV') }}
            </a>
            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#modalAddBlacklist" style="font-weight: 700; border-radius: 6px;">
                <i class="fa fa-plus-circle"></i> {{ $text('Flag Customer', 'ដាក់បញ្ចូលបញ្ជីខ្មៅ') }}
            </button>
        </div>
    </section>

    {{-- 4 KPI CARDS --}}
    <div class="lm-blacklist-stats">
        <div class="lm-stat-card lm-stat-danger">
            <div class="lm-stat-card-icon"><i class="fa fa-user-times"></i></div>
            <div class="lm-stat-card-content">
                <small>{{ $text('Total Blacklisted', 'អតិថិជនបញ្ជីខ្មៅសរុប') }}</small>
                <strong>{{ number_format($summary['total_blacklisted'] ?? 0) }}</strong>
            </div>
        </div>
        <div class="lm-stat-card lm-stat-amber">
            <div class="lm-stat-card-icon"><i class="fa fa-exclamation-triangle"></i></div>
            <div class="lm-stat-card-content">
                <small>{{ $text('Debt at Risk', 'បំណុលមានហានិភ័យ') }}</small>
                <strong>{{ $money($summary['total_debt_at_risk'] ?? 0) }}</strong>
            </div>
        </div>
        <div class="lm-stat-card lm-stat-indigo">
            <div class="lm-stat-card-icon"><i class="fa fa-shield"></i></div>
            <div class="lm-stat-card-content">
                <small>{{ $text('Linked Installments', 'កម្ចីជាប់ពាក់ព័ន្ធ') }}</small>
                <strong>{{ number_format($summary['linked_loans_count'] ?? 0) }}</strong>
            </div>
        </div>
        <div class="lm-stat-card lm-stat-slate">
            <div class="lm-stat-card-icon"><i class="fa fa-calendar-times-o"></i></div>
            <div class="lm-stat-card-content">
                <small>{{ $text('Flagged This Month', 'បានដាក់ក្នុងខែនេះ') }}</small>
                <strong>{{ number_format($summary['flagged_this_month'] ?? 0) }}</strong>
            </div>
        </div>
    </div>

    {{-- Ultimate POS Standard Collapsible Filter Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.blacklist.index', [], false) }}" id="loanBlacklistFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Date Range --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $text('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                    <input type="text" name="date_range" id="blacklistDateRange" value="{{ $dateRangeDisplay }}" class="form-control" placeholder="{{ $text('Select date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" autocomplete="off">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                </div>

                {{-- Search Keyword --}}
                <div class="lm-pos-filter-field" style="grid-column: span 2;">
                    <label>{{ $text('Search Customer / Reason', 'ស្វែងរកអតិថិជន / មូលហេតុ') }}</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="{{ $text('Customer name, phone, code, ID card, reason...', 'ឈ្មោះអតិថិជន ទូរស័ព្ទ កូដ អត្តសញ្ញាណ មូលហេតុ...') }}">
                </div>

                {{-- Action Buttons --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $text('Apply', 'អនុវត្ត') }}
                    </button>
                    <a href="{{ route('loan-management.blacklist.index', [], false) }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $text('Reset', 'សម្អាត') }}
                    </a>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $text('Blacklisted Customers', 'បញ្ជីខ្មៅអតិថិជន')])
        <div class="table-responsive">
            <table class="lm-table-dense table table-bordered table-striped table-hover" id="blacklist_table" style="width: 100%; margin-bottom: 0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th style="width: 80px;">{{ $text('Code', 'កូដ') }}</th>
                        <th>{{ $text('Customer', 'អតិថិជន') }}</th>
                        <th>{{ $text('Document / ID', 'អត្តសញ្ញាណប័ណ្ណ') }}</th>
                        <th>{{ $text('Blacklist Reason', 'មូលហេតុបញ្ជីខ្មៅ') }}</th>
                        <th>{{ $text('Flagged Date', 'កាលបរិច្ឆេទ') }}</th>
                        <th>{{ $text('Flagged By', 'អ្នករាយការណ៍') }}</th>
                        <th class="text-right">{{ $text('Debt at Risk', 'បំណុលនៅសល់') }}</th>
                        <th style="text-align: center;">{{ $text('Status', 'ស្ថានភាព') }}</th>
                        <th style="text-align: center; width: 80px;" class="no-export">{{ $text('Action', 'សកម្មភាព') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                        @php
                            $reason = trim((string) ($c->blacklist_reason ?? ''));
                            $reasonDisplay = $reason !== '' ? $reason : $text('Not specified', 'មិនបានបញ្ជាក់');
                            $flaggedDate = !empty($c->blacklist_date) ? \Carbon\Carbon::parse($c->blacklist_date)->format('d M Y') : '-';
                            $flaggedAgo = !empty($c->blacklist_date) ? \Carbon\Carbon::parse($c->blacklist_date)->diffForHumans() : '';
                            $staff = $staffNames[$c->blacklist_by] ?? ($c->blacklist_by ? 'Staff #'.$c->blacklist_by : '-');
                        @endphp
                        <tr>
                            <td>
                                <span style="font-family: ui-monospace, monospace; font-weight: 700; color: #e11d48;">
                                    {{ $c->customer_code ?: ('#' . $c->id) }}
                                </span>
                            </td>
                            <td>
                                <div>
                                    <a href="{{ route('loan-management.customers.show', $c->id, false) }}" class="lm-cust-link">
                                        {{ $c->name }}
                                    </a>
                                    @if(!empty($c->khmer_name))
                                        <small class="text-muted" style="display: block;">{{ $c->khmer_name }}</small>
                                    @endif
                                    @if(!empty($c->phone))
                                        <a href="tel:{{ $c->phone }}" class="lm-phone-link">
                                            <i class="fa fa-phone"></i> {{ $c->phone }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if(!empty($c->id_card_number))
                                    <span style="font-family: ui-monospace, monospace; font-weight: 600; color: #334155;">
                                        <i class="fa fa-id-card-o text-muted"></i> {{ $c->id_card_number }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="lm-reason-pill">
                                    <i class="fa fa-info-circle text-amber"></i> {{ $reasonDisplay }}
                                </span>
                            </td>
                            <td>
                                <div style="white-space: nowrap;">
                                    <strong>{{ $flaggedDate }}</strong>
                                    @if($flaggedAgo)
                                        <br><small class="text-muted">{{ $flaggedAgo }}</small>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #475569;">{{ $staff }}</span>
                            </td>
                            <td class="text-right">
                                <strong style="color: #e11d48; font-size: 14px;">
                                    {{ $money($c->total_debt ?? 0) }}
                                </strong>
                                <br>
                                <small class="text-muted">
                                    {{ (int) ($c->total_loans ?? 0) }} {{ $text('Installments', 'កម្ចី') }}
                                </small>
                            </td>
                            <td style="text-align: center;">
                                <span class="lm-badge-blacklist">
                                    <i class="fa fa-ban"></i> {{ $text('Blacklisted', 'បញ្ជីខ្មៅ') }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="btn-group btn-group-xs">
                                    <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" aria-expanded="false" style="border-radius: 6px; font-weight: 600;">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                        <li>
                                            <a href="{{ route('loan-management.customers.show', $c->id, false) }}">
                                                <i class="fa fa-user"></i> {{ $text('View Customer Profile', 'មើលព័ត៌មានអតិថិជន') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0)" class="js-edit-reason"
                                               data-id="{{ $c->id }}"
                                               data-name="{{ $c->name }}"
                                               data-reason="{{ e($c->blacklist_reason) }}">
                                                <i class="fa fa-pencil"></i> {{ $text('Edit Reason', 'កែប្រែមូលហេតុ') }}
                                            </a>
                                        </li>
                                        <li class="divider"></li>
                                        <li>
                                            <a href="javascript:void(0)" class="text-success js-whitelist-customer"
                                               data-id="{{ $c->id }}"
                                               data-name="{{ $c->name }}">
                                                <i class="fa fa-check-circle"></i> {{ $text('Remove from Blacklist', 'ដកចេញពីបញ្ជីខ្មៅ') }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent

</div>

{{-- MODAL: ADD CUSTOMER TO BLACKLIST --}}
<div class="modal fade" id="modalAddBlacklist" role="dialog" aria-labelledby="modalAddBlacklistLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 14px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
            <form action="{{ route('loan-management.blacklist.store', [], false) }}" method="POST" id="formAddBlacklist">
                @csrf
                <input type="hidden" name="blacklist_status" value="1">
                <input type="hidden" name="return_to_blacklist" value="1">
                <div class="modal-header" style="background: #fff1f2; border-bottom: 1px solid #fecdd3; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="color: #e11d48; font-weight: 800;">
                        <i class="fa fa-user-times"></i> {{ $text('Flag Customer to Blacklist', 'ដាក់បញ្ចូលអតិថិជនទៅក្នុងបញ្ជីខ្មៅ') }}
                    </h4>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 6px;">
                            {{ $text('Select Customer', 'ជ្រើសរើសអតិថិជន') }} <span class="text-danger">*</span>
                        </label>
                        <input type="hidden" name="customer_id" id="select_blacklist_customer" value="" required>

                        <!-- Customer Live Search Input (Exact copy from New Installment) -->
                        <div class="lm-customer-search-wrap">
                            <div class="lm-search-input-inner">
                                <i class="fa fa-search" style="position: absolute; left: 12px; color: #94a3b8; font-size: 14px; pointer-events: none; z-index: 2;"></i>
                                <input type="text" id="blacklist_customer_search_input" class="form-control lm-search-input-field" placeholder="{{ $text('Search existing customer by Name, Phone, or ID...', 'ស្វែងរកអតិថិជនចាស់ តាមឈ្មោះ លេខទូរស័ព្ទ ឬអត្តសញ្ញាណប័ណ្ណ...') }}" autocomplete="off" spellcheck="false">
                                <button type="button" id="btn_clear_blacklist_customer_search" onclick="clearBlacklistCustomerSearchOnly()" style="display: none; position: absolute; right: 8px; border: none; background: transparent; color: #94a3b8; font-size: 14px; cursor: pointer; padding: 4px; z-index: 2;" title="{{ $text('Clear search', 'សម្អាតការស្វែងរក') }}"><i class="fa fa-times-circle"></i></button>
                            </div>
                            <!-- Live Floating Dropdown Results -->
                            <div id="blacklist_customer_search_results" class="lm-customer-search-dropdown"></div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label for="blacklist_reason_input" style="font-weight: 700; color: #1e293b;">
                            {{ $text('Reason for Blacklisting', 'មូលហេតុនៃការដាក់បញ្ជីខ្មៅ') }} <span class="text-danger">*</span>
                        </label>
                        <textarea name="blacklist_reason" id="blacklist_reason_input" rows="3" class="form-control" placeholder="{{ $text('Describe default behavior, refusal to pay, fraudulent document, etc.', 'ពិពណ៌នាអំពីអាកប្បកិរិយាយឺតយ៉ាវ បដិសេធមិនបង់ប្រាក់ ក្លែងបន្លំឯកសារ...') }}" required style="border-radius: 8px;"></textarea>

                        <div style="margin-top: 8px;">
                            <small class="text-muted" style="display: block; margin-bottom: 4px; font-weight: 600;">
                                {{ $text('Quick Reason Suggestions:', 'ជម្រើសមូលហេតុរហ័ស៖') }}
                            </small>
                            <span class="lm-quick-reason-chip" data-text="{{ $text('Defaulted >90 days / Refused to settle loan', 'យឺតយ៉ាវលើសពី 90 ថ្ងៃ / បដិសេធមិនព្រមទូទាត់') }}">
                                {{ $text('Defaulted >90 days', 'យឺតយ៉ាវ >90 ថ្ងៃ') }}
                            </span>
                            <span class="lm-quick-reason-chip" data-text="{{ $text('Fraudulent identity / forged documents', 'ក្លែងបន្លំអត្តសញ្ញាណ / ឯកសារក្លែងក្លាយ') }}">
                                {{ $text('Identity Fraud', 'ក្លែងបន្លំអត្តសញ្ញាណ') }}
                            </span>
                            <span class="lm-quick-reason-chip" data-text="{{ $text('Absconded / Changed phone & unreachable', 'រត់គេចខ្លួន / ប្តូរលេខទូរស័ព្ទមិនអាចទាក់ទងបាន') }}">
                                {{ $text('Unreachable', 'មិនអាចទាក់ទងបាន') }}
                            </span>
                            <span class="lm-quick-reason-chip" data-text="{{ $text('Repossession dispute / Asset hidden', 'ជម្លោះរឹបអូសទ្រព្យ / លាក់បាំងទ្រព្យ') }}">
                                {{ $text('Asset Dispute', 'ជម្លោះទ្រព្យ') }}
                            </span>
                        </div>
                    </div>

                    <div class="alert alert-warning" style="margin-top: 16px; margin-bottom: 0; font-size: 12px; border-radius: 8px;">
                        <i class="fa fa-warning"></i>
                        {{ $text('Blacklisting a customer will restrict them from applying for new installment loans across all branches.', 'ការដាក់បញ្ចូលបញ្ជីខ្មៅនឹងរារាំងអតិថិជននេះពីការស្នើសុំកម្ចីរំលស់ថ្មីនៅគ្រប់សាខា។') }}
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ $text('Cancel', 'បោះបង់') }}</button>
                    <button type="submit" class="btn btn-danger" style="font-weight: 700;">
                        <i class="fa fa-ban"></i> {{ $text('Confirm Blacklist', 'បញ្ជាក់ការដាក់បញ្ជីខ្មៅ') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: EDIT BLACKLIST REASON --}}
<div class="modal fade" id="modalEditReason" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
            <form action="" method="POST" id="formEditReason">
                @csrf
                <input type="hidden" name="blacklist_status" value="1">
                <input type="hidden" name="return_to_blacklist" value="1">
                <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="color: #0f172a; font-weight: 800;">
                        <i class="fa fa-pencil"></i> {{ $text('Update Blacklist Reason', 'កែប្រែមូលហេតុបញ្ជីខ្មៅ') }}
                    </h4>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p style="font-size: 13px; color: #475569;">
                        {{ $text('Customer:', 'អតិថិជន៖') }} <strong id="edit_customer_name" style="color: #0f172a;"></strong>
                    </p>
                    <div class="form-group">
                        <label for="edit_reason_input" style="font-weight: 700;">{{ $text('Reason', 'មូលហេតុ') }}</label>
                        <textarea name="blacklist_reason" id="edit_reason_input" rows="3" class="form-control" required style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ $text('Cancel', 'បោះបង់') }}</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                        <i class="fa fa-save"></i> {{ $text('Save Changes', 'រក្សាទុក') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: UNBLOCK CUSTOMER (WHITELIST) --}}
<div class="modal fade" id="modalWhitelist" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
            <form action="" method="POST" id="formWhitelist">
                @csrf
                <input type="hidden" name="blacklist_status" value="0">
                <input type="hidden" name="blacklist_reason" value="">
                <input type="hidden" name="return_to_blacklist" value="1">
                <div class="modal-header" style="background: #ecfdf5; border-bottom: 1px solid #a7f3d0; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="color: #059669; font-weight: 800;">
                        <i class="fa fa-check-circle"></i> {{ $text('Remove from Blacklist', 'ដកចេញពីបញ្ជីខ្មៅ') }}
                    </h4>
                </div>
                <div class="modal-body" style="padding: 20px; text-align: center;">
                    <p style="font-size: 14px; color: #334155; margin-bottom: 8px;">
                        {{ $text('Are you sure you want to unblock', 'តើអ្នកប្រាកដជាចង់ដក') }}
                        <strong id="whitelist_customer_name" style="color: #0f172a; display: block; margin: 6px 0; font-size: 15px;"></strong>
                        {{ $text('from the blacklist?', 'ចេញពីបញ្ជីខ្មៅមែនទេ?') }}
                    </p>
                    <small class="text-muted">{{ $text('Customer will be allowed to apply for loans again.', 'អតិថិជននឹងអាចស្នើសុំកម្ចីឡើងវិញបាន។') }}</small>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ $text('Cancel', 'បោះបង់') }}</button>
                    <button type="submit" class="btn btn-success" style="font-weight: 700;">
                        <i class="fa fa-check"></i> {{ $text('Confirm Unblock', 'បញ្ជាក់ដកចេញ') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('loan_js')
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1/daterangepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function(){


    // Date Range Picker initialization
    var $dateRange = $('#blacklistDateRange');
    var $filterForm = $('#loanBlacklistFilterForm');
    var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
    var dateRangeSettings = window.dateRangeSettings ? $.extend(true, {}, window.dateRangeSettings) : {};

    if (window.moment && $.fn.daterangepicker && $dateRange.length) {
        var startDate = @json($dateFrom) ? moment(@json($dateFrom)) : moment().subtract(29, 'days');
        var endDate = @json($dateTo) ? moment(@json($dateTo)) : moment();
        var fyStart = (typeof financial_year !== 'undefined' && financial_year.start && moment(financial_year.start).isValid()) ? moment(financial_year.start) : moment().startOf('year');
        var fyEnd = (typeof financial_year !== 'undefined' && financial_year.end && moment(financial_year.end).isValid()) ? moment(financial_year.end) : moment().endOf('year');

        $dateRange.daterangepicker($.extend(true, {}, dateRangeSettings, {
            autoUpdateInput: false,
            showDropdowns: true,
            linkedCalendars: false,
            startDate: startDate,
            endDate: endDate,
            parentEl: 'body',
            opens: 'right',
            drops: 'auto',
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'This Year': [moment().startOf('year'), moment().endOf('year')],
                'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                'Current financial year': [fyStart.clone(), fyEnd.clone()],
                'Last financial year': [fyStart.clone().subtract(1, 'year'), fyEnd.clone().subtract(1, 'year')]
            },
            locale: $.extend(true, {}, dateRangeSettings.locale || {}, {
                format: displayDateFormat,
                separator: ' - ',
                applyLabel: {{ json_encode($text('Apply', 'អនុវត្ត')) }},
                cancelLabel: {{ json_encode($text('Clear', 'សម្អាត')) }},
                customRangeLabel: {{ json_encode($text('Custom Range', 'ជ្រើសរើសផ្ទាល់')) }},
                toLabel: '~'
            })
        }));

        $dateRange
            .on('apply.daterangepicker', function (event, picker) {
                $(this).val(picker.startDate.format(displayDateFormat) + ' - ' + picker.endDate.format(displayDateFormat));
                $filterForm.find('[name="date_from"]').val(picker.startDate.format('YYYY-MM-DD'));
                $filterForm.find('[name="date_to"]').val(picker.endDate.format('YYYY-MM-DD'));
                $filterForm.submit();
            })
            .on('cancel.daterangepicker', function () {
                $(this).val('');
                $filterForm.find('[name="date_from"], [name="date_to"]').val('');
                $filterForm.submit();
            });
    }

    // Initialize DataTables with Ultimate POS standard toolbar
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#blacklist_table')) {
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

        $('#blacklist_table').DataTable({
            dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
            buttons: tableButtons,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $text('All', 'ទាំងអស់') }}"]],
            order: [[4, 'desc']],
            autoWidth: false,
            language: {
                search: '',
                searchPlaceholder: 'Search ...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: '{{ $text("No blacklisted customers found.", "មិនមានអតិថិជននៅក្នុងបញ្ជីខ្មៅទេ។") }}',
                info: '{{ $text("Showing _START_ to _END_ of _TOTAL_ entries", "បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ") }}',
                infoEmpty: '{{ $text("Showing 0 to 0 of 0 entries", "បង្ហាញ 0 នៃ 0 ធាតុ") }}',
                infoFiltered: '({{ $text("filtered from _MAX_ total entries", "ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប") }})',
                paginate: {
                    first: '{{ $text("First", "ដំបូង") }}',
                    last: '{{ $text("Last", "ចុងក្រោយ") }}',
                    next: '{{ $text("Next", "បន្ទាប់") }}',
                    previous: '{{ $text("Previous", "មុន") }}'
                }
            },
            columnDefs: [
                { targets: [8], orderable: false, className: 'no-export' },
                { targets: [7, 8], className: 'text-center' },
                { targets: [6], className: 'text-right' }
            ]
        });
    }

    // ==================== LIVE CUSTOMER SEARCH IN ADD BLACKLIST MODAL (EXACT COPY FROM NEW INSTALLMENT) ====================
    var blacklistCustomerSearchUrl = "{{ route('loan-management.blacklist.search-customers', [], false) }}";
    var blacklistCustomerSearchTimer = null;
    var blacklistCustomerSearchSelectedIndex = -1;
    var blacklistCustomerSearchResultsCache = [];

    function blacklistEscapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
        });
    }

    function initBlacklistCustomerSearch() {
        var $input = $('#blacklist_customer_search_input');
        var $dropdown = $('#blacklist_customer_search_results');
        var $clearBtn = $('#btn_clear_blacklist_customer_search');
        if (!$input.length) return;

        // Trigger lookup on focus or click
        $input.off('focus click').on('focus click', function() {
            var val = $input.val();
            performBlacklistCustomerSearch(val ? val.trim() : '');
        });

        // Realtime typing search (debounced 100ms) with input and keyup support
        $input.off('input keyup').on('input keyup', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === 'Escape') return;
            var val = $input.val();
            $clearBtn.toggle(!!(val && val.length));
            clearTimeout(blacklistCustomerSearchTimer);
            blacklistCustomerSearchTimer = setTimeout(function() {
                performBlacklistCustomerSearch(val ? val.trim() : '');
            }, 100);
        });

        // Keyboard navigation (ArrowDown, ArrowUp, Enter, Escape)
        $input.off('keydown').on('keydown', function(e) {
            var $rows = $dropdown.find('.lm-cs-row');
            if (!$rows.length || $dropdown.is(':hidden')) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                blacklistCustomerSearchSelectedIndex = (blacklistCustomerSearchSelectedIndex + 1) % $rows.length;
                $rows.removeClass('selected').eq(blacklistCustomerSearchSelectedIndex).addClass('selected');
                if ($rows.eq(blacklistCustomerSearchSelectedIndex)[0]) {
                    $rows.eq(blacklistCustomerSearchSelectedIndex)[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                blacklistCustomerSearchSelectedIndex = (blacklistCustomerSearchSelectedIndex - 1 + $rows.length) % $rows.length;
                $rows.removeClass('selected').eq(blacklistCustomerSearchSelectedIndex).addClass('selected');
                if ($rows.eq(blacklistCustomerSearchSelectedIndex)[0]) {
                    $rows.eq(blacklistCustomerSearchSelectedIndex)[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                if (blacklistCustomerSearchSelectedIndex >= 0 && blacklistCustomerSearchSelectedIndex < $rows.length) {
                    e.preventDefault();
                    $rows.eq(blacklistCustomerSearchSelectedIndex).trigger('click');
                }
            } else if (e.key === 'Escape') {
                $dropdown.hide();
            }
        });

        // Close dropdown on outside click
        $(document).off('click.lmBlacklistCsOut').on('click.lmBlacklistCsOut', function(e) {
            if (!$(e.target).closest('.lm-customer-search-wrap').length) {
                $dropdown.hide();
            }
        });
    }

    // Document delegation to ensure search always works reliably in modal
    $(document).on('focus click', '#blacklist_customer_search_input', function() {
        var val = $(this).val();
        performBlacklistCustomerSearch(val ? val.trim() : '');
    });

    $(document).on('input keyup', '#blacklist_customer_search_input', function(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === 'Escape') return;
        var val = $(this).val();
        $('#btn_clear_blacklist_customer_search').toggle(!!(val && val.length));
        clearTimeout(blacklistCustomerSearchTimer);
        blacklistCustomerSearchTimer = setTimeout(function() {
            performBlacklistCustomerSearch(val ? val.trim() : '');
        }, 100);
    });

    $(document).on('click', '#blacklist_customer_search_results .lm-cs-row', function() {
        var idx = $(this).data('index');
        var customer = blacklistCustomerSearchResultsCache[idx];
        if (customer) {
            applyBlacklistSelectedCustomer(customer);
        }
    });

    function performBlacklistCustomerSearch(query) {
        var $dropdown = $('#blacklist_customer_search_results');
        if (!$dropdown.length) return;
        $dropdown.html('<div style="padding:14px; text-align:center; color:#64748b; font-size:12px;"><i class="fa fa-spinner fa-spin" style="color:#2563eb; margin-right:6px;"></i> {{ $text("Searching existing customers...", "កំពុងស្វែងរកអតិថិជន...") }}</div>').show();

        $.ajax({
            url: blacklistCustomerSearchUrl,
            method: 'GET',
            data: { q: query, term: query },
            dataType: 'json',
            success: function(res) {
                var items = (res && (res.results || res.data)) || [];
                blacklistCustomerSearchResultsCache = items;
                blacklistCustomerSearchSelectedIndex = -1;

                if (!items.length) {
                    var safeQ = $('<div>').text(query).html();
                    $dropdown.html(
                        '<div style="padding: 16px; text-align: center; color: #64748b;">' +
                            '<div style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px;">{{ $text("No existing customer found", "រកមិនឃើញអតិថិជនចាស់ទេ") }}' + (safeQ ? ' for "' + safeQ + '"' : '') + '</div>' +
                        '</div>'
                    ).show();
                    return;
                }

                var html = '';
                items.forEach(function(c, i) {
                    var khmer = c.khmer_name || '';
                    var name = c.name || '';
                    var phone = c.phone || '';
                    var idcard = c.id_card_number || '';
                    var code = c.customer_code || '';
                    var photo = c.photo_url || '';

                    var avatarHtml = photo
                        ? '<img src="' + blacklistEscapeHtml(photo) + '" class="lm-cs-avatar">'
                        : '<div class="lm-cs-avatar-icon"><i class="fa fa-user"></i></div>';

                    var primaryName = khmer || name || ('Customer #' + (c.id || ''));
                    var secondaryName = (khmer && name && khmer !== name) ? ' <span style="font-weight:400; color:#64748b; font-size:12px;">(' + blacklistEscapeHtml(name) + ')</span>' : '';

                    var metaBadges = [];
                    if (phone) metaBadges.push('<span><i class="fa fa-phone" style="color:#64748b;"></i> ' + blacklistEscapeHtml(phone) + '</span>');
                    if (idcard) metaBadges.push('<span><i class="fa fa-id-card-o" style="color:#64748b;"></i> ' + blacklistEscapeHtml(idcard) + '</span>');
                    if (code) metaBadges.push('<span class="label label-default" style="font-size:10px; font-weight:600; padding: 2px 6px;">' + blacklistEscapeHtml(code) + '</span>');

                    html += '<div class="lm-cs-row" data-index="' + i + '">' +
                        avatarHtml +
                        '<div class="lm-cs-info">' +
                            '<div class="lm-cs-name">' + blacklistEscapeHtml(primaryName) + secondaryName + '</div>' +
                            (metaBadges.length ? '<div class="lm-cs-sub">' + metaBadges.join(' &bull; ') + '</div>' : '') +
                        '</div>' +
                        '<i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:11px;"></i>' +
                    '</div>';
                });

                $dropdown.html(html).show();
            },
            error: function() {
                $dropdown.html('<div style="padding:12px; text-align:center; color:#ef4444; font-size:12px;">Failed to load customers.</div>').show();
            }
        });
    }

    function clearBlacklistCustomerSearchOnly() {
        $('#select_blacklist_customer').val('');
        $('#blacklist_customer_search_input').val('').focus();
        $('#btn_clear_blacklist_customer_search').hide();
        performBlacklistCustomerSearch('');
    }

    function applyBlacklistSelectedCustomer(customer) {
        if (!customer) return;
        $('#select_blacklist_customer').val(customer.id || '');

        var khmer = customer.khmer_name || '';
        var english = customer.name || '';
        var phone = customer.phone || '';
        var primary = khmer || english || ('Customer #' + (customer.id || ''));
        var displayText = primary + (english && english !== khmer ? ' (' + english + ')' : '') + (phone ? ' - ' + phone : '');

        $('#blacklist_customer_search_input').val(displayText);
        $('#btn_clear_blacklist_customer_search').show();
        $('#blacklist_customer_search_results').hide();

        setTimeout(function() {
            $('#blacklist_reason_input').focus();
        }, 80);
    }

    window.initBlacklistCustomerSearch = initBlacklistCustomerSearch;
    window.performBlacklistCustomerSearch = performBlacklistCustomerSearch;
    window.clearBlacklistCustomerSearchOnly = clearBlacklistCustomerSearchOnly;
    window.applyBlacklistSelectedCustomer = applyBlacklistSelectedCustomer;

    initBlacklistCustomerSearch();

    $('#modalAddBlacklist').on('shown.bs.modal', function () {
        clearBlacklistCustomerSearchOnly();
        setTimeout(function() {
            $('#blacklist_customer_search_input').focus();
        }, 100);
    });

    $('#modalAddBlacklist').on('hidden.bs.modal', function () {
        $('#select_blacklist_customer').val('');
        $('#blacklist_customer_search_input').val('');
        $('#btn_clear_blacklist_customer_search').hide();
        $('#blacklist_customer_search_results').hide();
        $('#blacklist_reason_input').val('');
    });

    // Quick chips in Add Modal
    $(document).on('click', '.lm-quick-reason-chip', function(){
        var txt = $(this).data('text');
        $('#blacklist_reason_input').val(txt);
    });

    $('#formAddBlacklist').on('submit', function(e){
        var customerId = $('#select_blacklist_customer').val();
        var reason = $('#blacklist_reason_input').val();
        if (!customerId) {
            e.preventDefault();
            alert({{ json_encode($text('Please select a customer to blacklist.', 'សូមជ្រើសរើសអតិថិជនដែលត្រូវដាក់ក្នុងបញ្ជីខ្មៅ។')) }});
            $('#blacklist_customer_search_input').focus();
            return false;
        }
        if (!reason || !reason.trim()) {
            e.preventDefault();
            alert({{ json_encode($text('Please provide a reason for blacklisting.', 'សូមបញ្ចូលមូលហេតុនៃការដាក់បញ្ជីខ្មៅ។')) }});
            $('#blacklist_reason_input').focus();
            return false;
        }
        var action = "/loan-management/customers/" + customerId + "/blacklist";
        $(this).attr('action', action);
    });

    // Edit reason modal trigger
    $(document).on('click', '.js-edit-reason', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
        var reason = $(this).data('reason');

        var actionUrl = "/loan-management/customers/" + id + "/blacklist";
        $('#formEditReason').attr('action', actionUrl);
        $('#edit_customer_name').text(name);
        $('#edit_reason_input').val(reason);
        $('#modalEditReason').modal('show');
    });

    // Whitelist / Unblock modal trigger
    $(document).on('click', '.js-whitelist-customer', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');

        var actionUrl = "/loan-management/customers/" + id + "/blacklist";
        $('#formWhitelist').attr('action', actionUrl);
        $('#whitelist_customer_name').text(name);
        $('#modalWhitelist').modal('show');
    });
});
</script>
@endsection
