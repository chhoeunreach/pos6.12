@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $dateRangeDisplay = $filters['date_range'] ?? ($dateFrom && $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('m-d-Y').' - '.\Carbon\Carbon::parse($dateTo)->format('m-d-Y')
        : '');
@endphp
@extends('loanmanagement::layouts.app')
@section('title', $lmText('Payments & Collection Ledger', 'បញ្ជីការទូទាត់ និងប្រមូលប្រាក់'))

@section('loan_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR PAYMENTS LEDGER
       ========================================================= */
    .lm-pay-content {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Executive KPI Metric Cards for Payments */
    .lm-pay-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    @media (max-width: 1400px) {
        .lm-pay-summary-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 900px) {
        .lm-pay-summary-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-pay-summary-grid { grid-template-columns: 1fr; }
    }
    .lm-pay-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        text-decoration: none !important;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .lm-pay-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }
    .lm-pay-card.is-active {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
    }
    .lm-pay-icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        flex-shrink: 0;
    }
    .lm-pay-copy {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .lm-pay-copy small {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #64748b;
    }
    .lm-pay-copy strong {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }
    .lm-pay-copy span {
        font-size: 11px;
        color: #94a3b8;
    }
    .lm-pay-green .lm-pay-icon { background: #f0fdf4; color: #16a34a; }
    .lm-pay-blue .lm-pay-icon { background: #eff6ff; color: #2563eb; }
    .lm-pay-purple .lm-pay-icon { background: #faf5ff; color: #9333ea; }
    .lm-pay-amber .lm-pay-icon { background: #fffbeb; color: #d97706; }
    .lm-pay-slate .lm-pay-icon { background: #f8fafc; color: #475569; }

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
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-btn-pos-reset:hover { background: #e2e8f0; color: #0f172a; text-decoration: none; }

    /* Add Button Styling */
    .lm-btn-pos-add {
        background: #4f46e5 !important;
        color: #ffffff !important;
        border-radius: 999px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        padding: 6px 20px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        border: none !important;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35) !important;
        text-decoration: none !important;
        transition: all 0.15s ease !important;
    }
    .lm-btn-pos-add:hover {
        background: #4338ca !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.45) !important;
        color: #ffffff !important;
        text-decoration: none !important;
    }

    /* =========================================================
       DATATABLES TOOLBAR (Exact layout from Ultimate POS)
       ========================================================= */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        gap: 12px !important;
        margin-bottom: 16px !important;
        width: 100% !important;
    }

    /* Left: Show [25 v] entries */
    .lm-dt-length {
        flex: 0 0 auto !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    .lm-dt-length .dataTables_length {
        display: inline-flex !important;
        align-items: center !important;
        margin: 0 !important;
        float: none !important;
    }
    .lm-dt-length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        margin: 0 !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        color: #334155 !important;
        white-space: nowrap !important;
    }
    .lm-dt-length select {
        height: 32px !important;
        min-width: 60px !important;
        padding: 2px 8px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        background: #ffffff !important;
        color: #1e293b !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        outline: none !important;
        cursor: pointer !important;
    }

    /* Middle: [ Copy ] [ Export CSV ] [ Export Excel ] [ Print ] [ Column visibility ] [ Export PDF v ] */
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 1 1 auto !important;
        gap: 6px !important;
    }
    .lm-dt-buttons .dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
        float: none !important;
        margin: 0 !important;
    }
    .lm-dt-buttons .btn,
    .lm-dt-buttons .dt-button {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        color: #64748b !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        padding: 4px 10px !important;
        height: 32px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
        transition: all 0.15s ease !important;
        white-space: nowrap !important;
    }
    .lm-dt-buttons .btn:hover,
    .lm-dt-buttons .dt-button:hover {
        background: #f8fafc !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .lm-dt-buttons .btn i,
    .lm-dt-buttons .dt-button i {
        font-size: 12px !important;
        color: #64748b !important;
    }
    .lm-dt-buttons .btn:hover i,
    .lm-dt-buttons .dt-button:hover i {
        color: #0f172a !important;
    }

    /* Right: Search ... */
    .lm-dt-search {
        flex: 0 0 auto !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
    }
    .lm-dt-search .dataTables_filter {
        display: inline-flex !important;
        align-items: center !important;
        margin: 0 !important;
        float: none !important;
    }
    .lm-dt-search label {
        margin: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    .lm-dt-search input {
        height: 32px !important;
        width: 200px !important;
        padding: 4px 10px !important;
        font-size: 12px !important;
        color: #1e293b !important;
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        outline: none !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
    }
    .lm-dt-search input:focus {
        border-color: #4f46e5 !important;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15) !important;
    }

    /* Table styling */
    .lm-table-dense {
        width: 100% !important;
        margin-bottom: 0 !important;
        border-collapse: collapse;
        font-size: 12px;
    }
    .lm-table-dense th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.2px;
        padding: 8px 10px;
        border-bottom: 1px solid #cbd5e1 !important;
        white-space: nowrap;
    }
    .lm-table-dense td {
        padding: 8px 10px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle !important;
    }
    .lm-table-dense tr:hover td { background-color: #f8fafc; }

    /* Badges & Buttons */
    .lm-badge {
        font-size: 10.5px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .lm-badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .lm-badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .lm-badge-info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .lm-badge-gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    /* Professional Payment Type Badges */
    .lm-type-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        line-height: 1.2;
        letter-spacing: 0.2px;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: all 0.15s ease;
    }
    .lm-type-monthly {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }
    .lm-type-payoff {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .lm-type-deposit, .lm-type-loan {
        background: #f5f3ff;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
    }
    .lm-type-advance {
        background: #f0fdfa;
        color: #0f766e;
        border: 1px solid #99f6e4;
    }
    .lm-type-penalty {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }
    .lm-type-default {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    /* Professional Payment Method Badges */
    .lm-method-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
        line-height: 1.2;
    }
    .lm-method-cash { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .lm-method-bank { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
    .lm-method-aba { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
    .lm-method-acleda { background: #f0fdfa; color: #115e59; border-color: #99f6e4; }
    .lm-method-wing { background: #fefce8; color: #854d0e; border-color: #fef08a; }

    .lm-btn-tbl {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 5px;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .lm-btn-tbl-info { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
    .lm-btn-tbl-info:hover { background: #bae6fd; color: #0369a1; text-decoration: none; }
    .lm-btn-tbl-edit { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    .lm-btn-tbl-edit:hover { background: #e2e8f0; color: #0f172a; text-decoration: none; }
    .lm-btn-tbl-del { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .lm-btn-tbl-del:hover { background: #fecaca; color: #991b1b; }

    /* Bottom footer in DataTable */
    .lm-dt-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
        margin-top: 14px !important;
        padding-top: 10px !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-dt-info { font-size: 12px; color: #64748b; font-weight: 500; }
    .lm-dt-pagination .pagination { margin: 0 !important; }

    @media (max-width: 991px) {
        .lm-dt-top { flex-wrap: wrap !important; }
        .lm-dt-search { width: 100%; justify-content: flex-start !important; }
        .lm-dt-search input { width: 100% !important; }
    }
</style>
@endsection

@section('content_body')
<div class="lm-pay-content">
    <!-- Header Section (Ultimate POS Standard) -->
    <section class="content-header no-print">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            {{ $lmText('Payments', 'ការទូទាត់ប្រាក់') }}
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">{{ $lmText('Manage your payments & collection ledger', 'គ្រប់គ្រងបញ្ជីការទូទាត់ និងប្រមូលប្រាក់') }}</small>
        </h1>
    </section>

    <!-- Main Content Section -->
    <section class="content no-print" style="padding-top: 10px;">

        {{-- 5 Executive KPI Summary Cards --}}
        @if(!empty($summary))
            @php
                $activeType = $filters['payment_type'] ?? '';
                $avgTicket = ($summary['count'] ?? 0) > 0 ? ((float)$summary['amount'] / (float)$summary['count']) : 0;
            @endphp
            <div class="lm-pay-summary-grid">
                <a href="{{ route('loan-management.payments.index', array_merge(request()->except('payment_type', 'page'))) }}"
                   class="lm-pay-card lm-pay-green {{ empty($activeType) ? 'is-active' : '' }}"
                   title="{{ $lmText('Click to view all payments', 'ចុចដើម្បីមើលការបង់ប្រាក់ទាំងអស់') }}">
                    <div class="lm-pay-icon"><i class="fa fa-money"></i></div>
                    <div class="lm-pay-copy">
                        <small>{{ $lmText('Total Collected', 'ប្រមូលបានសរុប') }}</small>
                        <strong>${{ number_format((float)($summary['amount'] ?? 0), 2) }}</strong>
                        <span>{{ number_format((int)($summary['count'] ?? 0)) }} {{ $lmText('Receipts', 'បង្កាន់ដៃសរុប') }}</span>
                    </div>
                </a>

                <a href="{{ route('loan-management.payments.index', array_merge(request()->except('page'), ['payment_type' => 'monthly'])) }}"
                   class="lm-pay-card lm-pay-blue {{ $activeType === 'monthly' ? 'is-active' : '' }}"
                   title="{{ $lmText('Filter by Monthly Installments', 'ចម្រាញ់ការបង់ប្រចាំខែ') }}">
                    <div class="lm-pay-icon"><i class="fa fa-calendar-check-o"></i></div>
                    <div class="lm-pay-copy">
                        <small>{{ $lmText('Monthly Collections', 'បង់ប្រចាំខែ') }}</small>
                        <strong>${{ number_format((float)($summary['monthly_amount'] ?? 0), 2) }}</strong>
                        <span>{{ number_format((int)($summary['monthly_count'] ?? 0)) }} {{ $lmText('Installment records', 'កាលវិភាគបង់') }}</span>
                    </div>
                </a>

                <a href="{{ route('loan-management.payments.index', array_merge(request()->except('page'), ['payment_type' => 'loan'])) }}"
                   class="lm-pay-card lm-pay-purple {{ $activeType === 'loan' ? 'is-active' : '' }}"
                   title="{{ $lmText('Filter by Loan Capital Recoveries', 'ចម្រាញ់ការបង់ប្រាក់ដើម') }}">
                    <div class="lm-pay-icon"><i class="fa fa-credit-card"></i></div>
                    <div class="lm-pay-copy">
                        <small>{{ $lmText('Installment Capital', 'រំលស់ប្រាក់ដើម') }}</small>
                        <strong>${{ number_format((float)($summary['loan_amount'] ?? 0), 2) }}</strong>
                        <span>{{ number_format((int)($summary['loan_count'] ?? 0)) }} {{ $lmText('Contract payments', 'ការបង់កិច្ចសន្យា') }}</span>
                    </div>
                </a>

                <a href="{{ route('loan-management.payments.index', array_merge(request()->except('page'), ['payment_type' => 'payoff'])) }}"
                   class="lm-pay-card lm-pay-amber {{ $activeType === 'payoff' ? 'is-active' : '' }}"
                   title="{{ $lmText('Filter by Pay-Off Settlements', 'ចម្រាញ់ការបង់ផ្តាច់') }}">
                    <div class="lm-pay-icon"><i class="fa fa-check-circle"></i></div>
                    <div class="lm-pay-copy">
                        <small>{{ $lmText('Pay-Offs Completed', 'បង់ផ្តាច់កិច្ចសន្យា') }}</small>
                        <strong>${{ number_format((float)($summary['payoff_amount'] ?? 0), 2) }}</strong>
                        <span>{{ number_format((int)($summary['payoff_count'] ?? 0)) }} {{ $lmText('Full settlements', 'បង់ផ្តាច់រួចរាល់') }}</span>
                    </div>
                </a>

                <div class="lm-pay-card lm-pay-slate">
                    <div class="lm-pay-icon"><i class="fa fa-pie-chart"></i></div>
                    <div class="lm-pay-copy">
                        <small>{{ $lmText('Avg Receipt Size', 'មធ្យមភាគបង្កាន់ដៃ') }}</small>
                        <strong>${{ number_format($avgTicket, 2) }}</strong>
                        <span>{{ $lmText('Per transaction average', 'មធ្យមភាគក្នុង១ប្រតិបត្តិការ') }}</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- =========================================================
             1. FILTERS WIDGET (Ultimate POS Component Filters)
             ========================================================= -->
        @component('components.filters', ['title' => $lmText('Filters', 'តម្រងស្វែងរក')])
            <form method="GET" action="{{ route('loan-management.payments.index') }}" id="loanPaymentFilterForm">
                <div class="lm-pos-filter-grid">
                    <!-- Row 1, Col 1: Business Location -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Business Location:', 'ទីតាំងសាខា:') }}</label>
                        <select name="location_id" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                            @foreach($locations as $id => $name)
                                <option value="{{ $id }}" {{ (string)($filters['location_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Row 1, Col 2: Customer -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Customer:', 'អតិថិជន:') }}</label>
                        <select name="customer" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                            @foreach($customers as $cName)
                                <option value="{{ $cName }}" {{ ($filters['customer'] ?? '') === $cName ? 'selected' : '' }}>{{ $cName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Row 1, Col 3: Payment Status -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Payment Status:', 'ស្ថានភាពទូទាត់:') }}</label>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                            @foreach($statuses as $statusKey => $label)
                                <option value="{{ $statusKey }}" {{ ($filters['status'] ?? '') == $statusKey ? 'selected' : '' }}>{{ ucfirst($label) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Row 1, Col 4: Date Range -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Date Range:', 'ចន្លោះកាលបរិច្ឆេទ:') }}</label>
                        <input type="text" name="date_range" id="loanPaymentDateRange" class="form-control" value="{{ $dateRangeDisplay }}" placeholder="MM-DD-YYYY - MM-DD-YYYY" autocomplete="off">
                        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                        <input type="hidden" name="date_to" value="{{ $dateTo }}">
                    </div>

                    <!-- Row 2, Col 1: User -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('User:', 'អ្នកប្រើប្រាស់ / បុគ្គលិក:') }}</label>
                        <select name="user_id" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                            @foreach($users as $uId => $uName)
                                <option value="{{ $uId }}" {{ (string)($filters['user_id'] ?? '') === (string)$uId ? 'selected' : '' }}>{{ $uName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Row 2, Col 2: Payment Type -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Payment Type:', 'ប្រភេទនៃការបង់:') }}</label>
                        <select name="payment_type" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                            <option value="loan" {{ ($filters['payment_type'] ?? '') === 'loan' ? 'selected' : '' }}>{{ $lmText('Installment', 'រំលស់') }}</option>
                            <option value="monthly" {{ ($filters['payment_type'] ?? '') === 'monthly' ? 'selected' : '' }}>{{ $lmText('Monthly', 'ប្រចាំខែ') }}</option>
                            <option value="payoff" {{ ($filters['payment_type'] ?? '') === 'payoff' ? 'selected' : '' }}>{{ $lmText('Pay Off', 'បង់ផ្តាច់') }}</option>
                        </select>
                    </div>

                    <!-- Row 2, Col 3: Filter / Reset Actions -->
                    <div class="lm-pos-filter-field">
                        <label>&nbsp;</label>
                        <div class="lm-pos-filter-actions">
                            <button type="submit" class="lm-btn-pos-filter">
                                <i class="fa fa-filter"></i> {{ $lmText('Filter', 'ចម្រាញ់') }}
                            </button>
                            <a href="{{ route('loan-management.payments.index') }}" class="lm-btn-pos-reset">
                                <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}
                            </a>
                        </div>
                    </div>

                    <!-- Row 2, Col 4: Payment Method -->
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Payment Method:', 'វិធីសាស្ត្រទូទាត់:') }}</label>
                        <select name="method" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                            @foreach($methods as $key => $label)
                                @php
                                    $displayFilterMethod = (string) $label;
                                    if (str_starts_with($displayFilterMethod, 'lang_v1.') || str_starts_with($displayFilterMethod, 'messages.')) {
                                        $rawFilterKey = str_replace(['lang_v1.', 'messages.'], '', $displayFilterMethod);
                                        $displayFilterMethod = $rawFilterKey === 'advance' ? $lmText('Advance Payment', 'ប្រាក់បង់មុន / បុរេប្រទាន') : ucfirst(str_replace('_', ' ', $rawFilterKey));
                                    }
                                @endphp
                                <option value="{{ $label }}" {{ ($filters['method'] ?? '') == $label || ($filters['method'] ?? '') == $key ? 'selected' : '' }}>{{ $displayFilterMethod }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        @endcomponent

        <!-- =========================================================
             2. TABLE WIDGET (Ultimate POS Component Widget)
             ========================================================= -->
        @component('components.widget', ['class' => 'box-primary', 'title' => $lmText('All payments', 'បញ្ជីការទូទាត់ទាំងអស់')])
            <div class="table-responsive">
                <table class="lm-table-dense table table-bordered table-striped table-hover" id="loanPaymentsTable">
                    <thead>
                        <tr>
                            <th>{{ $lmText('Receipt #', 'លេខបង្កាន់ដៃ') }}</th>
                            <th>{{ $lmText('Paid Date', 'កាលបរិច្ឆេទ') }}</th>
                            <th>{{ $lmText('Installment #', 'លេខកិច្ចសន្យា') }}</th>
                            <th>{{ $lmText('Customer', 'អតិថិជន') }}</th>
                            <th>{{ $lmText('Type', 'ប្រភេទ') }}</th>
                            <th>{{ $lmText('Method', 'វិធីទូទាត់') }}</th>
                            <th class="text-right">{{ $lmText('Amount Paid', 'ចំនួនទឹកប្រាក់') }}</th>
                            <th class="text-center">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                            <th>{{ $lmText('Reference', 'លេខយោង') }}</th>
                            <th>{{ $lmText('Received By', 'អ្នកទទួលប្រាក់') }}</th>
                            <th class="text-center no-export" style="width: 100px;">{{ $lmText('Action', 'សកម្មភាព') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($payments as $payment)
                        @php
                            $paymentStatus = strtolower((string)($payment->status ?? 'confirmed'));
                            $isPaid = in_array($paymentStatus, ['paid', 'confirmed', 'completed']);

                            $rawType = strtolower(trim((string)($payment->payment_type ?? 'monthly')));
                            $typeLabel = \Modules\LoanManagement\Http\Controllers\LoanPaymentController::paymentTypeLabel($rawType);
                            $typeClass = match($rawType) {
                                'payoff', 'pay_off' => 'lm-type-payoff',
                                'loan', 'down_payment', 'downpayment', 'deposit', 'initial' => 'lm-type-deposit',
                                'advance', 'prepayment' => 'lm-type-advance',
                                'penalty', 'late_fee' => 'lm-type-penalty',
                                default => 'lm-type-monthly',
                            };
                            $typeIcon = match($rawType) {
                                'payoff', 'pay_off' => 'fa fa-check-circle',
                                'loan', 'down_payment', 'downpayment', 'deposit', 'initial' => 'fa fa-bookmark',
                                'advance', 'prepayment' => 'fa fa-forward',
                                'penalty', 'late_fee' => 'fa fa-exclamation-circle',
                                default => 'fa fa-calendar-check-o',
                            };

                            $rawMethod = strtolower(trim((string)($payment->payment_method ?? 'cash')));
                            $methodClass = match(true) {
                                str_contains($rawMethod, 'cash') => 'lm-method-cash',
                                str_contains($rawMethod, 'aba') => 'lm-method-aba',
                                str_contains($rawMethod, 'acleda') => 'lm-method-acleda',
                                str_contains($rawMethod, 'wing') => 'lm-method-wing',
                                str_contains($rawMethod, 'bank') || str_contains($rawMethod, 'transfer') => 'lm-method-bank',
                                default => '',
                            };
                            $methodIcon = match(true) {
                                str_contains($rawMethod, 'cash') => 'fa fa-money',
                                str_contains($rawMethod, 'aba') || str_contains($rawMethod, 'acleda') || str_contains($rawMethod, 'bank') || str_contains($rawMethod, 'transfer') => 'fa fa-university',
                                str_contains($rawMethod, 'wing') => 'fa fa-mobile',
                                str_contains($rawMethod, 'card') => 'fa fa-credit-card',
                                default => 'fa fa-credit-card-alt',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('loan-management.payments.show', $payment->id) }}" style="font-weight: 700; color: #2563eb;">
                                    {{ $payment->receipt_number ?? ('#'.$payment->id) }}
                                </a>
                            </td>
                            <td>{{ ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('Y-m-d') : '-' }}</td>
                            <td>
                                @if(Route::has('loan-management.loans.view') && ! empty($payment->loan_id))
                                    <a href="{{ route('loan-management.loans.view', $payment->loan_id) }}" target="_blank" style="font-weight: 600; color: #0f172a;">
                                        {{ $payment->loan_number ?? ('#'.$payment->loan_id) }}
                                    </a>
                                @else
                                    {{ $payment->loan_number ?? '-' }}
                                @endif
                            </td>
                            <td>
                                <strong>{{ $payment->customer_name ?? '-' }}</strong>
                                @if(!empty($payment->customer_phone))
                                    <small class="text-muted" style="display: block; font-size: 10px;">{{ $payment->customer_phone }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="lm-type-badge {{ $typeClass }}">
                                    <i class="{{ $typeIcon }}"></i> {{ $typeLabel }}
                                </span>
                            </td>
                            <td>
                                <span class="lm-method-badge {{ $methodClass }}">
                                    <i class="{{ $methodIcon }}"></i> {{ $payment->payment_method ?? '-' }}
                                </span>
                            </td>
                            <td class="text-right" style="font-weight: 700; color: #16a34a; font-size: 13px;">
                                $ {{ number_format((float) ($payment->amount ?? 0), 2) }}
                            </td>
                            <td class="text-center">
                                <span class="lm-badge {{ $isPaid ? 'lm-badge-success' : 'lm-badge-warning' }}">
                                    {{ ucfirst($payment->status ?? '-') }}
                                </span>
                            </td>
                            <td>{{ $payment->reference_number ?? '-' }}</td>
                            <td>{{ $payment->received_by ?? '-' }}</td>
                            <td class="text-center" style="white-space: nowrap;">
                                <a href="{{ route('loan-management.payments.show', $payment->id) }}" class="lm-btn-tbl lm-btn-tbl-info" title="{{ $lmText('View Receipt', 'មើល') }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.payment|loan_management.payments.create|loan_management.edit'))
                                    <a href="{{ route('loan-management.payments.edit', $payment->id) }}" class="lm-btn-tbl lm-btn-tbl-edit" title="{{ $lmText('Edit', 'កែប្រែ') }}">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('loan-management.payments.destroy', $payment->id) }}" style="display:inline;" onsubmit="return confirm('{{ $lmText('Delete this payment? This will update loan balance.', 'តើអ្នកពិតជាចង់លុបការទូទាត់នេះមែនទេ?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="lm-btn-tbl lm-btn-tbl-del" title="{{ $lmText('Delete', 'លុប') }}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endcomponent

    </section>
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
    jQuery(document).ready(function ($) {
        // Date Range Picker initialization
        var $dateRange = $('#loanPaymentDateRange');
        var $filterForm = $('#loanPaymentFilterForm');
        var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
        var dateRangeSettings = window.dateRangeSettings ? $.extend(true, {}, window.dateRangeSettings) : {};

        if (window.moment && $.fn.daterangepicker && $dateRange.length) {
            var startDate = @json($dateFrom) ? moment(@json($dateFrom)) : moment().subtract(29, 'days');
            var endDate = @json($dateTo) ? moment(@json($dateTo)) : moment();

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
                    'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
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

        // Initialize DataTables with exact toolbar
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanPaymentsTable')) {
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

            $('#loanPaymentsTable').DataTable({
                dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
                buttons: tableButtons,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $lmText('All', 'ទាំងអស់') }}"]],
                order: [[1, 'desc']],
                autoWidth: false,
                language: {
                    search: '',
                    searchPlaceholder: 'Search ...',
                    lengthMenu: 'Show _MENU_ entries',
                    emptyTable: '{{ $lmText("No payments found matching your criteria.", "រកមិនឃើញទិន្នន័យការទូទាត់ទេ។") }}',
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
                    { targets: [6], className: 'text-right' },
                    { targets: [7, 10], className: 'text-center' },
                    { targets: [10], orderable: false, className: 'no-export' }
                ]
            });
        }
    });
</script>
@endsection
