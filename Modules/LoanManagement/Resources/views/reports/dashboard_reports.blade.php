@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង' : 'Dashboard Reports')

@php
    $t = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$ '.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
    $shortMethod = function ($value) {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || $value === '-') {
            return '-';
        }

        return collect(preg_split('/\s*,\s*/', $value))
            ->filter()
            ->map(function ($part) {
                $part = preg_replace('/\s+\$\s*/', ' $', trim($part));
                $part = preg_replace('/^.*\((ABA|ACLEDA|WING|E&T|CARD|CASH)\)/iu', '$1', $part);
                $part = preg_replace('/.*ABA.*/iu', 'ABA'.(preg_match('/\$\s*[\d,.]+/', $part, $m) ? ' '.$m[0] : ''), $part);
                $part = preg_replace('/.*ACLEDA.*/iu', 'ACLEDA'.(preg_match('/\$\s*[\d,.]+/', $part, $m) ? ' '.$m[0] : ''), $part);
                $part = preg_replace('/.*WING.*/iu', 'WING'.(preg_match('/\$\s*[\d,.]+/', $part, $m) ? ' '.$m[0] : ''), $part);
                $part = preg_replace('/.*E&T.*/iu', 'E&T'.(preg_match('/\$\s*[\d,.]+/', $part, $m) ? ' '.$m[0] : ''), $part);
                $part = preg_replace('/.*CARD.*/iu', 'CARD'.(preg_match('/\$\s*[\d,.]+/', $part, $m) ? ' '.$m[0] : ''), $part);
                $part = preg_replace('/\.00\b/', '', $part);
                $part = preg_replace('/\s+/', ' ', $part);

                return $part;
            })
            ->implode(' + ');
    };
    $normalizeDuplicateKey = function ($value) {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || $value === '-') {
            return '';
        }

        return strtolower(preg_replace('/\s+/', ' ', $value));
    };
    $duplicateCounts = function ($rows, $field) use ($normalizeDuplicateKey) {
        return collect($rows ?? [])
            ->map(fn ($row) => $normalizeDuplicateKey(data_get($row, $field)))
            ->filter()
            ->countBy();
    };
    $duplicateReason = function ($row, $loanCounts, $customerCounts) use ($normalizeDuplicateKey, $t) {
        $reasons = [];
        if (($loanCounts[$normalizeDuplicateKey($row->loan_number ?? '')] ?? 0) > 1) {
            $reasons[] = $t('Duplicate Loan #', 'លេខកម្ចីស្ទួន');
        }
        if (($customerCounts[$normalizeDuplicateKey($row->customer_name ?? '')] ?? 0) > 1) {
            $reasons[] = $t('Duplicate Customer', 'អតិថិជនស្ទួន');
        }

        return implode(' / ', $reasons);
    };
    $cards = $payload['cards'] ?? [];
    $recentPaymentLoanCounts = $duplicateCounts($payload['recentPayments'] ?? [], 'loan_number');
    $recentPaymentCustomerCounts = $duplicateCounts($payload['recentPayments'] ?? [], 'customer_name');
    $recentLoanLoanCounts = $duplicateCounts($payload['recentLoans'] ?? [], 'loan_number');
    $recentLoanCustomerCounts = $duplicateCounts($payload['recentLoans'] ?? [], 'customer_name');
    $period = $filters['period'] ?? 'daily';
    $periodLabel = $payload['collectionPeriodLabel'] ?? $t('Date', 'ថ្ងៃ');
    $periodTitle = ['daily' => $t('Daily', 'ប្រចាំថ្ងៃ'), 'monthly' => $t('Monthly', 'ប្រចាំខែ'), 'yearly' => $t('Yearly', 'ប្រចាំឆ្នាំ')][$period] ?? $t('Daily', 'ប្រចាំថ្ងៃ');
    $recentActivityFilters = $recentActivityFilters ?? [
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
        'location_id' => null,
        'search' => '',
    ];
    $recentActivityFilterQuery = array_merge(request()->query(), [
        'date_from' => $recentActivityFilters['date_from'],
        'date_to' => $recentActivityFilters['date_to'],
        'location_id' => $recentActivityFilters['location_id'],
        'search' => $recentActivityFilters['search'],
    ]);
    $recentActivityDateRange = \Carbon\Carbon::parse($recentActivityFilters['date_from'])->format('d-M-Y').' ~ '.\Carbon\Carbon::parse($recentActivityFilters['date_to'])->format('d-M-Y');
    $recentActivityDateFrom = \Carbon\Carbon::parse($recentActivityFilters['date_from']);
    $recentActivityDateTo = \Carbon\Carbon::parse($recentActivityFilters['date_to']);
    $recentActivityReportTitle = $recentActivityDateFrom->isSameDay($recentActivityDateTo)
        ? $t('Daily Activity Report for '.$recentActivityDateFrom->format('d-M-Y'), 'របាយការណ៍សកម្មភាពប្រចាំថ្ងៃ ថ្ងៃទី '.$recentActivityDateFrom->format('d-m-Y'))
        : $t('Daily Activity Report from '.$recentActivityDateFrom->format('d-M-Y').' to '.$recentActivityDateTo->format('d-M-Y'), 'របាយការណ៍សកម្មភាពប្រចាំថ្ងៃ ចាប់ពី '.$recentActivityDateFrom->format('d-m-Y').' ដល់ '.$recentActivityDateTo->format('d-m-Y'));
    $formatPeriod = function ($value) use ($period) {
        if (empty($value)) {
            return '-';
        }
        if ($period === 'monthly') {
            return \Carbon\Carbon::createFromFormat('Y-m', (string) $value)->format('m-Y');
        }
        if ($period === 'yearly') {
            return (string) $value;
        }
        return \Carbon\Carbon::parse($value)->format('d-m-Y');
    };
@endphp

@section('loan_css')
@parent
<style>
    .lm-report-tabs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        margin-bottom: 12px;
    }
    .lm-report-tab {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 8px 15px;
        border-radius: 8px;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }
    .lm-report-tab:hover,
    .lm-report-tab:focus {
        color: #0f172a;
        text-decoration: none;
    }
    .lm-report-tab.is-active {
        background: #0f172a;
        color: #fff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
    }
    .lm-recent-panel-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .lm-recent-table-wrap {
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
    }
    .lm-recent-panel-heading {
        margin: 0 0 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
        color: #1f2937;
        font-size: 14px;
        font-weight: 800;
    }
    .lm-report-table {
        margin-bottom: 0;
        background: #fff;
        font-size: 11px;
    }
    .lm-report-table > thead > tr > th,
    .lm-report-table > tbody > tr > td,
    .lm-report-table > tfoot > tr > th {
        padding: 5px 6px !important;
        line-height: 1.25;
    }
    .lm-report-table > thead > tr > th {
        background: #eef6fc;
        color: #111827;
        border-color: #cbd5e1 !important;
        font-size: 11px;
        font-weight: 800;
        vertical-align: middle !important;
        white-space: nowrap;
    }
    .lm-report-table > tbody > tr > td,
    .lm-report-table > tfoot > tr > th {
        border-color: #e5e7eb !important;
        vertical-align: top !important;
    }
    .lm-report-table > tbody > tr:nth-child(even) > td {
        background: #f8fafc;
    }
    .lm-report-table > tbody > tr.lm-duplicate-row > td,
    .lm-report-table > tbody > tr.lm-duplicate-row:nth-child(even) > td {
        background: #fff7ed;
        border-top-color: #fdba74 !important;
        border-bottom-color: #fdba74 !important;
    }
    .lm-report-table > tbody > tr.lm-duplicate-row > td:first-child {
        border-left: 4px solid #f97316 !important;
    }
    .lm-report-table > tfoot > tr > th {
        background: #eaf4ff;
        font-weight: 800;
    }
    .lm-report-table .text-right {
        font-variant-numeric: tabular-nums;
    }
    .lm-report-table .lm-col-no {
        width: 26px !important;
        min-width: 26px;
        max-width: 26px;
        text-align: center;
        white-space: nowrap;
    }
    .lm-report-table .lm-action-col {
        width: 32px !important;
        min-width: 32px;
        max-width: 32px;
        text-align: center;
        white-space: nowrap;
    }
    .lm-report-table .lm-view-detail-btn {
        width: 24px;
        height: 24px;
        padding: 0;
        border-radius: 4px;
        line-height: 24px;
    }
    .lm-report-table td {
        word-break: break-word;
    }
    .lm-report-table small {
        font-size: 10px;
    }
    .lm-loan-ref-line {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 100%;
        white-space: nowrap;
    }
    .lm-loan-ref-line small {
        color: #64748b;
    }
    .lm-payment-method-summary th {
        background: #dbeafe;
        color: #0f172a;
        text-align: center;
        vertical-align: middle !important;
    }
    .lm-payment-method-summary td {
        vertical-align: middle !important;
    }
    .lm-payment-method-summary tfoot th {
        background: #eff6ff;
    }
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child {
        display: flex;
        align-items: center;
        gap: 5px;
        margin: 0 0 6px;
    }
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child:before,
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child:after {
        display: none;
    }
    .lm-recent-table-wrap .dataTables_length select,
    .lm-recent-table-wrap .dataTables_filter input {
        height: 28px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        box-shadow: none;
        font-size: 11px;
    }
    .lm-recent-table-wrap .dataTables_filter input {
        min-width: 135px;
    }
    .lm-recent-table-wrap .dt-buttons {
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 3px;
    }
    .lm-recent-table-wrap .dt-buttons .dt-button,
    .lm-recent-table-wrap .dt-buttons a.dt-button,
    .lm-recent-table-wrap .dt-buttons button.dt-button {
        padding: 3px 7px !important;
        font-size: 11px !important;
        line-height: 1.3 !important;
    }
    .lm-panel-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        justify-content: flex-end;
    }
    .lm-panel-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 28px;
        border-radius: 6px;
        font-weight: 700;
    }
    .lm-recent-report-title {
        margin: 0 0 12px;
        padding: 12px 16px;
        border-top: 1px solid #94a3b8;
        border-bottom: 2px solid #111827;
        background: #dbeafe;
        color: #0000ff;
        text-align: center;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.35;
    }
    .lm-recent-filter-card {
        margin-bottom: 12px;
        border: 1px solid #dce7f0;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .lm-recent-filter-card.box-primary {
        border-top: 0;
    }
    .lm-recent-filter-card > .box-header {
        min-height: 52px;
        border-bottom: 1px solid #edf2f7;
        background: #fff;
        padding: 13px 15px;
    }
    .lm-recent-filter-card > .box-header .box-title {
        color: #5da8d6;
        font-size: 20px;
        font-weight: 500;
    }
    .lm-recent-filter-card > .box-header .box-title .fa {
        color: #5da8d6;
        margin-right: 6px;
    }
    .lm-recent-filter-card > .box-body {
        padding: 22px 16px 26px;
        background: #fff;
    }
    .lm-report-filter-box .form-group,
    .lm-recent-filter-card .form-group {
        margin-bottom: 18px;
    }
    .lm-recent-filter-card label {
        color: #111827;
        font-size: 13px;
        font-weight: 700;
    }
    .lm-recent-filter-card .form-control {
        height: 36px;
        border-color: #d1d9e6;
        border-radius: 0;
        box-shadow: none;
    }
    .lm-report-print-title {
        display: none;
    }
    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            background: #fff !important;
            color: #111827 !important;
        }
        .lm-sidebar,
        .lm-header,
        .lm-breadcrumb-wrap,
        .lm-report-tabs,
        .lm-report-filter-box,
        .lm-recent-filter-card,
        .box-tools,
        .main-footer,
        .no-print {
            display: none !important;
        }
        .collapsed-box .box-body {
            display: block !important;
        }
        body.lm-print-recent-only .content-header,
        body.lm-print-recent-only .content > * {
            display: none !important;
        }
        body.lm-print-recent-only .content > .lm-recent-activity-row {
            display: block !important;
        }
        body.lm-print-recent-only .lm-recent-activity-row .col-md-12,
        body.lm-print-recent-only .lm-recent-activity-panel {
            display: block !important;
            width: 100% !important;
            border: 0 !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }
        body.lm-print-recent-only .lm-recent-activity-panel,
        body.lm-print-recent-only .lm-recent-activity-panel .box-body,
        body.lm-print-recent-only .lm-recent-activity-panel .table-responsive {
            padding: 0 !important;
            margin: 0 !important;
            overflow: visible !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }
        body.lm-print-recent-only .lm-recent-report-title {
            margin: 0 0 4mm !important;
            padding: 5mm 4mm !important;
            font-size: 20px !important;
            page-break-after: avoid !important;
            break-after: avoid !important;
        }
        body.lm-print-recent-only .lm-payment-method-summary {
            margin-bottom: 5mm !important;
            page-break-after: auto !important;
            break-after: auto !important;
        }
        body.lm-print-recent-only .lm-recent-panel-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 5mm !important;
            align-items: start !important;
            page-break-before: auto !important;
            break-before: auto !important;
        }
        body.lm-print-recent-only .lm-recent-panel-grid > .table-responsive {
            display: block !important;
            width: 100% !important;
            overflow: visible !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }
        body.lm-print-recent-only .lm-recent-panel-grid .table {
            width: 100% !important;
            table-layout: fixed;
        }
        body.lm-print-recent-only .lm-recent-panel-grid th,
        body.lm-print-recent-only .lm-recent-panel-grid td {
            white-space: normal !important;
            word-break: break-word;
        }
        body.lm-print-recent-only .dataTables_wrapper .row:first-child,
        body.lm-print-recent-only .dataTables_info,
        body.lm-print-recent-only .dataTables_paginate {
            display: none !important;
        }
        body.lm-print-recent-only .lm-recent-table-wrap {
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: #fff !important;
        }
        body.lm-print-recent-only .lm-recent-panel-heading {
            margin: 0 0 2mm !important;
            padding: 2mm 3mm !important;
            border: 1px solid #9ca3af !important;
            background: #e5f0fb !important;
            color: #111827 !important;
            text-align: center;
            font-size: 12px !important;
            font-weight: 800 !important;
        }
        body.lm-print-recent-only .lm-report-table {
            border-collapse: collapse !important;
            font-size: 9px !important;
        }
        body.lm-print-recent-only .lm-report-table > thead > tr > th {
            background: #dbeafe !important;
            color: #111827 !important;
            font-size: 9px !important;
            font-weight: 800 !important;
            text-align: center;
        }
        body.lm-print-recent-only .lm-report-table > tbody > tr > td,
        body.lm-print-recent-only .lm-report-table > tfoot > tr > th {
            border: 1px solid #9ca3af !important;
        }
        body.lm-print-recent-only .lm-report-table > tbody > tr:nth-child(even) > td {
            background: #f8fafc !important;
        }
        body.lm-print-recent-only .lm-report-table > tbody > tr.lm-duplicate-row > td,
        body.lm-print-recent-only .lm-report-table > tbody > tr.lm-duplicate-row:nth-child(even) > td {
            background: #fff2cc !important;
        }
        .lm-main,
        .lm-content,
        .lm-workspace,
        .content,
        .container-fluid {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
        }
        .content-header {
            padding: 0 0 8px !important;
        }
        .lm-report-print-title {
            display: block;
            margin: 0 0 10px;
            text-align: center;
        }
        .lm-report-print-title h2 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 700;
        }
        .lm-report-print-title p {
            margin: 0;
            font-size: 11px;
            color: #4b5563;
        }
        .info-box,
        .box {
            border: 1px solid #d1d5db !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        .box-header,
        .box-body {
            padding: 8px !important;
        }
        .table > thead > tr > th,
        .table > tbody > tr > td {
            padding: 4px 5px !important;
            font-size: 10px !important;
            border-color: #d1d5db !important;
        }
        a[href]:after {
            content: "" !important;
        }
    }
    @media (max-width: 991px) {
        .lm-recent-panel-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content_body')
<section class="content-header">
    <h1>{{ $t('Dashboard Reports', 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង') }}</h1>
</section>

<section class="content">
    <div class="lm-report-tabs" role="navigation" aria-label="Loan dashboard pages">
        <a href="{{ route('loan-management.dashboard') }}" class="lm-report-tab">{{ $t('Dashboard', 'ផ្ទាំងគ្រប់គ្រង') }}</a>
        <a href="{{ route('loan-management.reports.dashboard') }}" class="lm-report-tab is-active">{{ $t('Dashboard Reports', 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង') }}</a>
    </div>

    <div class="lm-report-print-title">
        <h2>{{ $periodTitle }} {{ $t('Loan and Collection Report', 'របាយការណ៍កម្ចី និងការប្រមូលប្រាក់') }}</h2>
        <p>{{ \Carbon\Carbon::parse($filters['date_from'])->format('d-m-Y') }} - {{ \Carbon\Carbon::parse($filters['date_to'])->format('d-m-Y') }}</p>
    </div>

    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Loans', 'កម្ចី') }}</span>
                    <span class="info-box-number">{{ $number($cards['loan_count'] ?? 0) }}</span>
                    <small>{{ $money($cards['principal_total'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Collected Payments', 'ប្រាក់ប្រមូលបាន') }}</span>
                    <span class="info-box-number">{{ $money($cards['collection_total'] ?? 0) }}</span>
                    <small>{{ $number($cards['payment_count'] ?? 0) }} {{ $t('payments', 'ការបង់ប្រាក់') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-blue"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Loan/Deposit Payments', 'ប្រាក់កក់កម្ចី') }}</span>
                    <span class="info-box-number">{{ $money($cards['deposit_total'] ?? 0) }}</span>
                    <small>{{ $t('Total paid', 'សរុបបានបង់') }} {{ $money($cards['payment_total'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-balance-scale"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Outstanding Balance', 'សមតុល្យនៅសល់') }}</span>
                    <span class="info-box-number">{{ $money($cards['balance_total'] ?? 0) }}</span>
                    <small>{{ $t('Loan total', 'សរុបកម្ចី') }} {{ $money($cards['loan_total'] ?? 0) }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid collapsed-box lm-dashboard-report-collapse">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Loan Report by Status', 'របាយការណ៍កម្ចីតាមស្ថានភាព') }}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse" title="{{ $t('Expand / Collapse', 'ពង្រីក / បង្រួម') }}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ $t('Status', 'ស្ថានភាព') }}</th>
                                <th class="text-right">{{ $t('Loans', 'កម្ចី') }}</th>
                                <th class="text-right">{{ $t('Principal', 'ប្រាក់ដើម') }}</th>
                                <th class="text-right">{{ $t('Paid', 'បានបង់') }}</th>
                                <th class="text-right">{{ $t('Balance', 'សមតុល្យ') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['loanStatusRows'] as $row)
                                <tr>
                                    <td><span class="label label-default">{{ ucfirst(str_replace('_', ' ', $row->status ?? 'unknown')) }}</span></td>
                                    <td class="text-right">{{ $number($row->loan_count ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->principal_total ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->paid_total ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->balance_total ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ $t('No loan data found.', 'រកមិនឃើញទិន្នន័យកម្ចី') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-solid collapsed-box lm-dashboard-report-collapse">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $periodTitle }} {{ $t('Collected Payment Report', 'របាយការណ៍ប្រមូលប្រាក់') }}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse" title="{{ $t('Expand / Collapse', 'ពង្រីក / បង្រួម') }}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ $periodLabel }}</th>
                                <th class="text-right">{{ $t('Payments', 'ការបង់ប្រាក់') }}</th>
                                <th class="text-right">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['collectionRows'] as $row)
                                <tr>
                                    <td>{{ $formatPeriod($row->period_key ?? null) }}</td>
                                    <td class="text-right">{{ $number($row->payment_count ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->payment_total ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">{{ $t('No collection data found.', 'រកមិនឃើញទិន្នន័យប្រមូលប្រាក់') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row lm-recent-activity-row">
        <div class="col-md-12 lm-report-no-print">
            <div class="box box-solid lm-recent-activity-panel">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Recent Activity', 'សកម្មភាពថ្មីៗ') }}</h3>
                    <div class="box-tools pull-right lm-panel-actions">
                        <button type="button" class="btn btn-success btn-sm lm-panel-action-btn" onclick="window.loanPrintRecentActivity()">
                            <i class="fa fa-print"></i> {{ $t('Print', 'បោះពុម្ព') }}
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="collapse" title="{{ $t('Expand / Collapse', 'ពង្រីក / បង្រួម') }}">
                            <i class="fa fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <div class="box lm-recent-filter-card">
                        <div class="box-header with-border" data-toggle="collapse" data-target="#loanRecentActivityFilterCollapse" aria-expanded="false" style="cursor:pointer;">
                            <h3 class="box-title"><i class="fa fa-filter"></i>{{ $t('Filters', 'តម្រង') }}</h3>
                        </div>
                        <div class="box-body collapse" id="loanRecentActivityFilterCollapse">
                            <form method="GET" action="{{ route('loan-management.reports.dashboard') }}" id="loanRecentActivityFilterForm">
                                <input type="hidden" name="period" value="{{ $period }}">
                                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                <input type="hidden" name="location_id" value="{{ $filters['location_id'] ?? '' }}">
                                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>{{ $t('Location', 'ទីតាំង') }}</label>
                                            <select name="recent_location_id" class="form-control" style="width:100%">
                                                <option value="">{{ $t('All', 'ទាំងអស់') }}</option>
                                                @foreach($locations as $id => $name)
                                                    <option value="{{ $id }}" {{ (string) ($recentActivityFilters['location_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>{{ $t('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                                            <input type="text" name="recent_date_range" id="loanRecentActivityDateRange" class="form-control" value="{{ $recentActivityDateRange }}" readonly>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h2 class="lm-recent-report-title">{{ $recentActivityReportTitle }}</h2>

                    <div class="table-responsive">
                        <h4 class="lm-recent-panel-heading">{{ $t('Payment Summary by Type', 'សង្ខេបការបង់ប្រាក់តាមប្រភេទ') }}</h4>
                        <table class="table table-bordered lm-payment-method-summary lm-report-table">
                            <thead>
                                <tr>
                                    <th>{{ $t('Type', 'ប្រភេទ') }}</th>
                                    <th class="text-right">{{ $t('Count', 'ចំនួន') }}</th>
                                    <th>{{ $t('Cash', 'លុយសុទ្ធ') }}</th>
                                    <th>ABA</th>
                                    <th>ACLEDA</th>
                                    <th>WING</th>
                                    <th>E&amp;T</th>
                                    <th>CARD</th>
                                    <th>{{ $t('Other', 'ផ្សេងៗ') }}</th>
                                    <th>{{ $t('Total', 'បង់សរុប') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payload['paymentMethodRows'] as $row)
                                    <tr>
                                        <td><strong>{{ $row['label'] }}</strong></td>
                                        <td class="text-right">{{ $number($row['count'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['cash'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['aba'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['acleda'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['wing'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['et'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['card'] ?? 0) }}</td>
                                        <td class="text-right">{{ $money($row['other'] ?? 0) }}</td>
                                        <td class="text-right"><strong>{{ $money($row['total'] ?? 0) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="text-center text-muted">{{ $t('No payment summary found.', 'រកមិនឃើញសង្ខេបការបង់ប្រាក់') }}</td></tr>
                                @endforelse
                            </tbody>
                            @if(!empty($payload['paymentMethodRows']))
                                <tfoot>
                                    <tr>
                                        <th>{{ $t('Total', 'សរុប') }}</th>
                                        <th class="text-right">{{ $number(collect($payload['paymentMethodRows'])->sum('count')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('cash')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('aba')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('acleda')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('wing')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('et')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('card')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('other')) }}</th>
                                        <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('total')) }}</th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <div class="lm-recent-panel-grid">
                        <div class="table-responsive lm-recent-table-wrap">
                            <h4 class="lm-recent-panel-heading">{{ $t('Recent Collected Payments', 'ការបង់ប្រាក់ថ្មីៗ') }}</h4>
                            <table class="table table-bordered table-hover loan-recent-activity-datatable lm-report-table" id="loan_recent_payments_table">
                                <thead>
                                    <tr>
                                        <th class="lm-col-no">{{ $t('No', 'ល.រ') }}</th>
                                        <th class="lm-action-col no-print">{{ $t('View', 'មើល') }}</th>
                                        <th>{{ $t('Loan #', 'លេខកម្ចី') }}</th>
                                        <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                                        <th>{{ $t('Method Type', 'ប្រភេទវិធីបង់') }}</th>
                                        <th class="text-right">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                                        <th>{{ $t('Note', 'ចំណាំ') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payload['recentPayments'] as $paymentIndex => $payment)
                                        @php($paymentDuplicateReason = $duplicateReason($payment, $recentPaymentLoanCounts, $recentPaymentCustomerCounts))
                                        <tr class="{{ $paymentDuplicateReason ? 'lm-duplicate-row' : '' }}" title="{{ $paymentDuplicateReason }}">
                                            <td class="lm-col-no">{{ $paymentIndex + 1 }}</td>
                                            <td class="lm-action-col no-print">
                                                <button type="button"
                                                        class="btn btn-xs btn-info lm-view-detail-btn js-loan-recent-detail-modal"
                                                        data-url="{{ route('loan-management.payments.show', $payment->id) }}"
                                                        data-title="{{ $t('Payment Detail', 'ព័ត៌មានលម្អិតការបង់ប្រាក់') }}">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <span class="lm-loan-ref-line">
                                                    <span>{{ $payment->loan_number ?? '-' }}</span>
                                                    <small>{{ ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('d-m-Y') : '-' }}</small>
                                                </span>
                                            </td>
                                            <td>{{ $payment->customer_name ?: '-' }}</td>
                                            <td title="{{ $payment->payment_method ?: '-' }}">{{ $shortMethod($payment->payment_method ?? '-') }}</td>
                                            <td class="text-right">{{ $money($payment->amount ?? 0) }}</td>
                                            <td>{{ $payment->note ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive lm-recent-table-wrap">
                            <h4 class="lm-recent-panel-heading">{{ $t('Recent Loans', 'កម្ចីថ្មីៗ') }}</h4>
                            <table class="table table-bordered table-hover loan-recent-activity-datatable lm-report-table" id="loan_recent_loans_table">
                                <thead>
                                    <tr>
                                        <th class="lm-col-no">{{ $t('No', 'ល.រ') }}</th>
                                        <th class="lm-action-col no-print">{{ $t('View', 'មើល') }}</th>
                                        <th>{{ $t('Loan #', 'លេខកម្ចី') }}</th>
                                        <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                                        <th>{{ $t('Product', 'ទំនិញ') }}</th>
                                        <th>{{ $t('Method Type', 'ប្រភេទវិធីបង់') }}</th>
                                        <th class="text-right">{{ $t('Balance', 'សមតុល្យ') }}</th>
                                        <th>{{ $t('Note', 'ចំណាំ') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payload['recentLoans'] as $loanIndex => $loan)
                                        @php($loanDuplicateReason = $duplicateReason($loan, $recentLoanLoanCounts, $recentLoanCustomerCounts))
                                        <tr class="{{ $loanDuplicateReason ? 'lm-duplicate-row' : '' }}" title="{{ $loanDuplicateReason }}">
                                            <td class="lm-col-no">{{ $loanIndex + 1 }}</td>
                                            <td class="lm-action-col no-print">
                                                <button type="button"
                                                        class="btn btn-xs btn-info lm-view-detail-btn js-loan-recent-detail-modal"
                                                        data-url="{{ route('loan-management.loans.view', ['loan' => $loan->id, '_lm_modal' => 1]) }}"
                                                        data-title="{{ $t('Loan Detail', 'ព័ត៌មានលម្អិតកម្ចី') }}">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <span class="lm-loan-ref-line">
                                                    <a href="{{ route('loan-management.loans.view', $loan->id) }}">{{ $loan->loan_number ?? ('#'.$loan->id) }}</a>
                                                    <small>{{ ! empty($loan->loan_date) ? \Carbon\Carbon::parse($loan->loan_date)->format('d-m-Y') : '-' }}</small>
                                                </span>
                                                <br><small class="text-muted">{{ ucfirst($loan->status ?? '-') }}</small>
                                            </td>
                                            <td>{{ $loan->customer_name ?: '-' }}</td>
                                            <td>{{ $loan->product_name ?: '-' }}</td>
                                            <td title="{{ $loan->payment_method ?: '-' }}">{{ $shortMethod($loan->payment_method ?? '-') }}</td>
                                            <td class="text-right">{{ $money($loan->balance_amount ?? 0) }}</td>
                                            <td>{{ $loan->payment_note ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    (function () {
        var form = document.getElementById('loanRecentActivityFilterForm');
        if (!form) {
            return;
        }

        var submitTimer = null;
        var scheduleSubmit = function (delay) {
            window.clearTimeout(submitTimer);
            submitTimer = window.setTimeout(function () {
                form.submit();
            }, delay || 150);
        };

        form.querySelectorAll('select').forEach(function (field) {
            field.addEventListener('change', function () {
                scheduleSubmit(100);
            });
        });

        if (window.jQuery && jQuery.fn.daterangepicker && window.moment) {
            jQuery('#loanRecentActivityDateRange').daterangepicker(
                {
                    autoUpdateInput: true,
                    startDate: moment('{{ $recentActivityFilters['date_from'] }}'),
                    endDate: moment('{{ $recentActivityFilters['date_to'] }}'),
                    locale: {
                        format: 'DD-MMM-YYYY',
                        cancelLabel: 'Clear'
                    }
                },
                function (start, end) {
                    jQuery('#loanRecentActivityDateRange').val(start.format('DD-MMM-YYYY') + ' ~ ' + end.format('DD-MMM-YYYY'));
                    scheduleSubmit(100);
                }
            );
        }

        if (window.jQuery && jQuery.fn.DataTable) {
            var recentActivityExportTitle = @json($recentActivityReportTitle);
            var recentActivityButtons = [];
            if (jQuery.fn.dataTable.Buttons) {
                recentActivityButtons = [
                    {
                        extend: 'copy',
                        text: '<i class="fa fa-copy" aria-hidden="true"></i> Copy',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible:not(.lm-action-col)'}
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fa fa-file-csv" aria-hidden="true"></i> Export CSV',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible:not(.lm-action-col)'}
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel" aria-hidden="true"></i> Export Excel',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible:not(.lm-action-col)'}
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print" aria-hidden="true"></i> Print',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible:not(.lm-action-col)', stripHtml: true}
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fa fa-columns" aria-hidden="true"></i> Column visibility',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fa fa-file-pdf" aria-hidden="true"></i> Export PDF',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible:not(.lm-action-col)'}
                    }
                ];
            }

            jQuery('.loan-recent-activity-datatable').each(function () {
                if (jQuery.fn.DataTable.isDataTable(this)) {
                    return;
                }

                jQuery(this).DataTable({
                    dom: '<"row margin-bottom-20 text-center"<"col-sm-1"l><"col-sm-8"B><"col-sm-3"f> r>tip',
                    buttons: recentActivityButtons,
                    pageLength: parseInt(window.__default_datatable_page_entries || 25, 10),
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
                    order: [],
                    scrollX: true,
                    columnDefs: [
                        {targets: [0, 1], orderable: false, searchable: false}
                    ],
                    language: {
                        search: '',
                        searchPlaceholder: 'Search ...'
                    }
                });
            });
        }

        if (window.jQuery) {
            jQuery(document).off('click.loanRecentDetailModal')
                .on('click.loanRecentDetailModal', '.js-loan-recent-detail-modal', function () {
                    var url = jQuery(this).data('url');
                    var title = jQuery(this).data('title') || 'Detail';

                    if (!url || !jQuery('.view_modal').length) {
                        return;
                    }

                    jQuery('.view_modal').html(
                        '<div class="modal-dialog modal-xl" role="document" style="width:96%;max-width:1280px;">' +
                            '<div class="modal-content">' +
                                '<div class="modal-header">' +
                                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                        '<span aria-hidden="true">&times;</span>' +
                                    '</button>' +
                                    '<h4 class="modal-title">' + jQuery('<div>').text(title).html() + '</h4>' +
                                '</div>' +
                                '<div class="modal-body" style="padding:0;height:86vh;">' +
                                    '<iframe src="' + jQuery('<div>').text(url).html() + '" style="width:100%;height:100%;border:0;" title="' + jQuery('<div>').text(title).html() + '"></iframe>' +
                                '</div>' +
                            '</div>' +
                        '</div>'
                    ).modal('show');
                });
        }

        if (window.jQuery) {
            jQuery('.lm-dashboard-report-collapse').each(function () {
                var $box = jQuery(this);
                var $body = $box.children('.box-body');
                var $icon = $box.find('[data-widget="collapse"] .fa').first();

                if ($box.hasClass('collapsed-box')) {
                    $body.hide();
                    $icon.removeClass('fa-minus').addClass('fa-plus');
                }
            });

            jQuery(document).off('click.loanDashboardReportCollapse')
                .on('click.loanDashboardReportCollapse', '.lm-dashboard-report-collapse [data-widget="collapse"]', function (event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    var $box = jQuery(this).closest('.lm-dashboard-report-collapse');
                    var $body = $box.children('.box-body');
                    var $icon = jQuery(this).find('.fa').first();
                    var isCollapsed = $box.hasClass('collapsed-box');

                    $body.stop(true, true).slideToggle(160);
                    $box.toggleClass('collapsed-box', ! isCollapsed);
                    $icon.toggleClass('fa-plus', ! isCollapsed).toggleClass('fa-minus', isCollapsed);
                });
        }

    })();

    window.loanPrintRecentActivity = function () {
        document.body.classList.add('lm-print-recent-only');
        window.print();
        window.setTimeout(function () {
            document.body.classList.remove('lm-print-recent-only');
        }, 500);
    };
</script>
@endsection
