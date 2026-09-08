@extends('loanmanagement::layouts.app')
@section('title', 'Field Collection Visits')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $resultBadge = function ($result) {
        $result = strtolower((string) $result);
        return match ($result) {
            'visited', 'completed', 'success', 'paid', 'promise_to_pay' => 'success',
            'pending', 'scheduled', 'open' => 'warning',
            'failed', 'cancelled', 'not_home', 'no_answer' => 'danger',
            default => 'default',
        };
    };

    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $dateRangeDisplay = $dateFrom && $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('m-d-Y').' - '.\Carbon\Carbon::parse($dateTo)->format('m-d-Y')
        : '';
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR FIELD COLLECTION VISITS
       ========================================================= */
    .lm-visit-content {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: 2fr 1.2fr 1.2fr 1.5fr auto;
        gap: 14px 18px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1200px) {
        .lm-pos-filter-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
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
    .lm-visit-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    .lm-visit-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        text-decoration: none !important;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .lm-visit-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .lm-visit-card.is-active {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
    }
    .lm-visit-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .lm-visit-blue .lm-visit-card-icon { background: #eff6ff; color: #2563eb; }
    .lm-visit-green .lm-visit-card-icon { background: #ecfdf5; color: #16a34a; }
    .lm-visit-amber .lm-visit-card-icon { background: #fffbeb; color: #d97706; }
    .lm-visit-purple .lm-visit-card-icon { background: #faf5ff; color: #9333ea; }
    .lm-visit-card-content small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 2px;
    }
    .lm-visit-card-content strong {
        display: block;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }

    @media (max-width: 992px) {
        .lm-visit-summary-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-visit-summary-grid { grid-template-columns: 1fr; }
    }

    /* =========================================================
       MOBILE CARD GRID VIEW
       ========================================================= */
    .lm-mobile-cards {
        display: none;
    }
    @media (max-width: 768px) {
        .lm-desktop-table {
            display: none !important;
        }
        .lm-mobile-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            padding: 4px 0;
        }
    }
    @media (max-width: 400px) {
        .lm-mobile-cards {
            grid-template-columns: 1fr;
        }
    }

    .lm-mcard {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        position: relative;
        overflow: hidden;
    }
    .lm-mcard:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
    }
    .lm-mcard::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #0284c7, #7c3aed);
        border-radius: 12px 12px 0 0;
    }

    .lm-mcard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        padding-top: 4px;
    }
    .lm-mcard-date {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }
    .lm-mcard-date small {
        display: block;
        font-size: 10.5px;
        font-weight: 400;
        color: #94a3b8;
    }
    .lm-mcard-badge {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 3px 8px;
        border-radius: 20px;
        font-weight: 700;
    }

    .lm-mcard-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .lm-mcard-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 12.5px;
        color: #334155;
        line-height: 1.4;
    }
    .lm-mcard-row i {
        width: 16px;
        text-align: center;
        color: #94a3b8;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .lm-mcard-row strong {
        color: #0f172a;
    }
    .lm-mcard-row a {
        color: #0284c7;
        font-weight: 700;
        text-decoration: none;
    }

    .lm-mcard-notes {
        margin-top: 4px;
        padding-top: 8px;
        border-top: 1px dashed #e2e8f0;
        font-size: 11.5px;
        color: #64748b;
        line-height: 1.45;
    }

    .lm-mcard-footer {
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }
    .lm-mcard-footer .btn {
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
    }

    .lm-mobile-empty {
        text-align: center;
        padding: 32px 16px;
        color: #94a3b8;
        font-size: 13px;
    }
    .lm-mobile-empty i {
        display: block;
        font-size: 32px;
        margin-bottom: 8px;
        color: #cbd5e1;
    }
</style>
@endsection

@section('content_body')
<div class="lm-visit-content">

    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $lmText('Field Collection Visits', 'កំណត់ត្រាចុះជួបអតិថិជន') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $lmText('Track field collector visit history, GPS locations, customer encounters, and follow-up notes', 'តាមដានប្រវត្តិចុះជួបអតិថិជន ទីតាំង GPS និងកំណត់សម្គាល់ការទារបំណុល') }}
            </small>
        </h1>
    </section>

    {{-- 4 KPI Metric Cards --}}
    @php
        $activeResult = $filters['result'] ?? '';
        $isTodayActive = ($filters['date_from'] ?? '') === \Carbon\Carbon::today()->toDateString() && ($filters['date_to'] ?? '') === \Carbon\Carbon::today()->toDateString();
        $isAllActive = empty($activeResult) && !$isTodayActive && empty($filters['search']) && empty($filters['collector']);
    @endphp
    <div class="lm-visit-summary-grid">
        <a href="{{ route('loan-management.collection-visits.index') }}" 
           class="lm-visit-card lm-visit-blue {{ $isAllActive ? 'is-active' : '' }}"
           title="{{ $lmText('View all field visits', 'មើលការចុះជួបទាំងអស់') }}">
            <div class="lm-visit-card-icon"><i class="fa fa-street-view"></i></div>
            <div class="lm-visit-card-content">
                <small>{{ $lmText('Total Visits', 'ការចុះជួបសរុប') }}</small>
                <strong>{{ number_format($summary['total'] ?? 0) }}</strong>
            </div>
        </a>

        <a href="{{ route('loan-management.collection-visits.index', ['date_from' => \Carbon\Carbon::today()->toDateString(), 'date_to' => \Carbon\Carbon::today()->toDateString()]) }}" 
           class="lm-visit-card lm-visit-green {{ $isTodayActive ? 'is-active' : '' }}"
           title="{{ $lmText('View visits scheduled for today', 'មើលការចុះជួបថ្ងៃនេះ') }}">
            <div class="lm-visit-card-icon"><i class="fa fa-calendar-check-o"></i></div>
            <div class="lm-visit-card-content">
                <small>{{ $lmText('Today', 'ថ្ងៃនេះ') }}</small>
                <strong>{{ number_format($summary['today'] ?? 0) }}</strong>
            </div>
        </a>

        <a href="{{ route('loan-management.collection-visits.index', ['result' => 'pending']) }}" 
           class="lm-visit-card lm-visit-amber {{ $activeResult === 'pending' ? 'is-active' : '' }}"
           title="{{ $lmText('View pending or open visits', 'មើលការចុះជួបរង់ចាំ') }}">
            <div class="lm-visit-card-icon"><i class="fa fa-clock-o"></i></div>
            <div class="lm-visit-card-content">
                <small>{{ $lmText('Pending / Scheduled', 'រង់ចាំ / គ្រោងទុក') }}</small>
                <strong>{{ number_format($summary['pending'] ?? 0) }}</strong>
            </div>
        </a>

        <a href="{{ route('loan-management.collection-visits.index', ['result' => 'visited']) }}" 
           class="lm-visit-card lm-visit-purple {{ in_array($activeResult, ['visited', 'completed']) ? 'is-active' : '' }}"
           title="{{ $lmText('View completed visits', 'មើលការចុះជួបដែលបានបញ្ចប់') }}">
            <div class="lm-visit-card-icon"><i class="fa fa-check-circle"></i></div>
            <div class="lm-visit-card-content">
                <small>{{ $lmText('Completed', 'បានបញ្ចប់') }}</small>
                <strong>{{ number_format($summary['completed'] ?? 0) }}</strong>
            </div>
        </a>
    </div>

    {{-- Ultimate POS Standard Collapsible Filters Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.collection-visits.index') }}" id="loanVisitFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Search Keyword --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Search Keyword', 'ស្វែងរក') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $lmText('Installment #, customer, phone, address...', 'លេខកិច្ចសន្យា ឈ្មោះ ទូរស័ព្ទ អាសយដ្ឋាន...') }}">
                </div>

                {{-- Collector Filter --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Collector', 'បុគ្គលិកទារប្រាក់') }}</label>
                    <select name="collector" class="form-control">
                        <option value="">{{ $lmText('All Collectors', 'បុគ្គលិកទាំងអស់') }}</option>
                        @foreach($collectors as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['collector'] ?? '') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Result Filter --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Result Status', 'លទ្ធផល') }}</label>
                    <select name="result" class="form-control">
                        <option value="">{{ $lmText('All Results', 'លទ្ធផលទាំងអស់') }}</option>
                        @foreach($results as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['result'] ?? '') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date Range --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Visit Date Range', 'ចន្លោះថ្ងៃចុះជួប') }}</label>
                    <input type="text" name="date_range" id="visitDateRange" value="{{ $dateRangeDisplay }}" class="form-control" placeholder="{{ $lmText('Select date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" autocomplete="off">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                </div>

                {{-- Action Buttons --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $lmText('Apply', 'អនុវត្ត') }}
                    </button>
                    <a href="{{ route('loan-management.collection-visits.index') }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'សម្អាត') }}
                    </a>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $lmText('All Field Collection Visits', 'កំណត់ត្រាចុះជួបអតិថិជនទាំងអស់')])
        {{-- DESKTOP: Table View --}}
        <div class="table-responsive lm-desktop-table">
            <table class="lm-table-dense table table-bordered table-striped table-hover" id="loanVisitsTable" style="width: 100%; margin-bottom: 0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th style="width: 130px;">{{ $lmText('Visit Date', 'កាលបរិច្ឆេទ') }}</th>
                        <th>{{ $lmText('Installment #', 'លេខកិច្ចសន្យា') }}</th>
                        <th>{{ $lmText('Customer', 'អតិថិជន') }}</th>
                        <th>{{ $lmText('Collector', 'បុគ្គលិកទារប្រាក់') }}</th>
                        <th style="text-align: center;">{{ $lmText('Result', 'លទ្ធផល') }}</th>
                        <th>{{ $lmText('Location / Address', 'ទីតាំង / អាសយដ្ឋាន') }}</th>
                        <th>{{ $lmText('Notes', 'កំណត់ចំណាំ') }}</th>
                        <th style="width: 80px; text-align: center;" class="no-export">{{ $lmText('Action', 'សកម្មភាព') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $visit)
                        @php
                            $mapUrl = !empty($visit->latitude) && !empty($visit->longitude)
                                ? 'https://www.google.com/maps?q='.$visit->latitude.','.$visit->longitude
                                : null;
                        @endphp
                        <tr>
                            <td style="white-space: nowrap; font-size: 12px; color: #475569;">
                                <strong>{{ !empty($visit->visited_at) ? \Carbon\Carbon::parse($visit->visited_at)->format('d-m-Y') : '-' }}</strong>
                                @if(!empty($visit->visited_at))
                                    <div style="font-size: 11px; color: #94a3b8;">{{ \Carbon\Carbon::parse($visit->visited_at)->format('H:i') }}</div>
                                @endif
                            </td>
                            <td>
                                @if(Route::has('loan-management.loans.view') && !empty($visit->loan_id))
                                    <a href="{{ route('loan-management.loans.view', $visit->loan_id) }}" style="font-weight: 700; color: #0284c7; text-decoration: none;">
                                        {{ $visit->loan_number ?? ('Installment #'.$visit->loan_id) }}
                                    </a>
                                @else
                                    <strong>{{ $visit->loan_number ?? '-' }}</strong>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $visit->customer_name ?? '-' }}</strong>
                                @if(!empty($visit->customer_phone))
                                    <div style="font-size: 11.5px; color: #64748b;">
                                        <i class="fa fa-phone text-muted" style="margin-right: 2px;"></i> {{ $visit->customer_phone }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #334155;">{{ $visit->collector_name ?? '-' }}</span>
                            </td>
                            <td style="text-align: center;">
                                <span class="label label-{{ $resultBadge($visit->result ?? '') }}" style="font-size: 10.5px; text-transform: uppercase;">
                                    {{ ucwords(str_replace('_', ' ', $visit->result ?? 'pending')) }}
                                </span>
                            </td>
                            <td>
                                {{ $visit->address_snapshot ?? '-' }}
                                @if($mapUrl)
                                    <div>
                                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" style="font-size: 11px; font-weight: 700; color: #0284c7; text-decoration: none;">
                                            <i class="fa fa-map-marker text-danger"></i> Google Maps
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td style="font-size: 12px; color: #475569;">
                                {{ \Illuminate\Support\Str::limit($visit->note ?? '-', 90) }}
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                @if($mapUrl)
                                    <a class="btn btn-xs btn-default" href="{{ $mapUrl }}" target="_blank" rel="noopener" title="{{ $lmText('Open Map Location', 'បើកផែនទី') }}" style="border-radius: 4px;">
                                        <i class="fa fa-location-arrow text-primary"></i> Map
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE: Card Grid View --}}
        <div class="lm-mobile-cards">
            @forelse($visits as $visit)
                @php
                    $mapUrl = !empty($visit->latitude) && !empty($visit->longitude)
                        ? 'https://www.google.com/maps?q='.$visit->latitude.','.$visit->longitude
                        : null;
                @endphp
                <div class="lm-mcard">
                    <div class="lm-mcard-header">
                        <div class="lm-mcard-date">
                            {{ !empty($visit->visited_at) ? \Carbon\Carbon::parse($visit->visited_at)->format('d-m-Y') : '-' }}
                            @if(!empty($visit->visited_at))
                                <small>{{ \Carbon\Carbon::parse($visit->visited_at)->format('H:i') }}</small>
                            @endif
                        </div>
                        <span class="label label-{{ $resultBadge($visit->result ?? '') }} lm-mcard-badge">
                            {{ ucwords(str_replace('_', ' ', $visit->result ?? 'pending')) }}
                        </span>
                    </div>

                    <div class="lm-mcard-body">
                        {{-- Installment # --}}
                        <div class="lm-mcard-row">
                            <i class="fa fa-file-text-o"></i>
                            <span>
                                @if(Route::has('loan-management.loans.view') && !empty($visit->loan_id))
                                    <a href="{{ route('loan-management.loans.view', $visit->loan_id) }}">{{ $visit->loan_number ?? ('Installment #'.$visit->loan_id) }}</a>
                                @else
                                    <strong>{{ $visit->loan_number ?? '-' }}</strong>
                                @endif
                            </span>
                        </div>

                        {{-- Customer --}}
                        <div class="lm-mcard-row">
                            <i class="fa fa-user"></i>
                            <span>
                                <strong>{{ $visit->customer_name ?? '-' }}</strong>
                                @if(!empty($visit->customer_phone))
                                    <br><span style="font-size: 11px; color: #64748b;"><i class="fa fa-phone"></i> {{ $visit->customer_phone }}</span>
                                @endif
                            </span>
                        </div>

                        {{-- Collector --}}
                        <div class="lm-mcard-row">
                            <i class="fa fa-id-badge"></i>
                            <span>{{ $visit->collector_name ?? '-' }}</span>
                        </div>

                        {{-- Location --}}
                        <div class="lm-mcard-row">
                            <i class="fa fa-map-marker"></i>
                            <span>
                                {{ \Illuminate\Support\Str::limit($visit->address_snapshot ?? '-', 60) }}
                                @if($mapUrl)
                                    <br><a href="{{ $mapUrl }}" target="_blank" rel="noopener" style="font-size: 11px;"><i class="fa fa-external-link"></i> {{ $lmText('Open Map', 'បើកផែនទី') }}</a>
                                @endif
                            </span>
                        </div>
                    </div>

                    @if(!empty($visit->note))
                        <div class="lm-mcard-notes">
                            <i class="fa fa-sticky-note-o" style="margin-right: 3px;"></i> {{ \Illuminate\Support\Str::limit($visit->note, 120) }}
                        </div>
                    @endif

                    @if($mapUrl)
                        <div class="lm-mcard-footer">
                            <a class="btn btn-xs btn-default" href="{{ $mapUrl }}" target="_blank" rel="noopener">
                                <i class="fa fa-location-arrow text-primary"></i> {{ $lmText('Map', 'ផែនទី') }}
                            </a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="lm-mobile-empty">
                    <i class="fa fa-street-view"></i>
                    {{ $lmText('No collection visits found.', 'មិនមានកំណត់ត្រាចុះជួបអតិថិជនទេ។') }}
                </div>
            @endforelse
        </div>

        @if(method_exists($visits, 'links') && $visits->hasPages())
            <div style="margin-top: 10px;">
                {{ $visits->links() }}
            </div>
        @endif
    @endcomponent

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
    var $dateRange = $('#visitDateRange');
    var $filterForm = $('#loanVisitFilterForm');
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
                applyLabel: @json($lmText('Apply', 'អនុវត្ត')),
                cancelLabel: @json($lmText('Clear', 'សម្អាត')),
                customRangeLabel: @json($lmText('Custom Range', 'ជ្រើសរើសផ្ទាល់')),
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
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanVisitsTable')) {
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

        $('#loanVisitsTable').DataTable({
            dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
            buttons: tableButtons,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $lmText('All', 'ទាំងអស់') }}"]],
            order: [[0, 'desc']],
            autoWidth: false,
            language: {
                search: '',
                searchPlaceholder: 'Search ...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: '{{ $lmText("No collection visits found.", "មិនមានកំណត់ត្រាចុះជួបអតិថិជនទេ។") }}',
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
                { targets: [7], orderable: false, className: 'no-export' },
                { targets: [4, 7], className: 'text-center' }
            ]
        });
    }
});
</script>
@endsection
