@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង' : 'Dashboard Reports')
@section('hide_breadcrumb', '1')

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
                $amount = preg_match('/\$\s*[\d,.]+/', $part, $m) ? ' '.preg_replace('/\s+/', '', $m[0]) : '';

                foreach (['ABA', 'ACLEDA', 'WING', 'E&T', 'CARD', 'CASH'] as $method) {
                    if (preg_match('/'.preg_quote($method, '/').'/iu', $part)) {
                        return $method.$amount;
                    }
                }

                if (preg_match('/\(([^)]+)\)/u', $part, $m)) {
                    return trim($m[1]).$amount;
                }

                return trim(preg_replace('/\$\s*[\d,.]+/', '', $part)).$amount;
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
    $khmerMonths = [
        1 => 'មករា',
        2 => 'កុម្ភៈ',
        3 => 'មីនា',
        4 => 'មេសា',
        5 => 'ឧសភា',
        6 => 'មិថុនា',
        7 => 'កក្កដា',
        8 => 'សីហា',
        9 => 'កញ្ញា',
        10 => 'តុលា',
        11 => 'វិច្ឆិកា',
        12 => 'ធ្នូ',
    ];
    $khmerDigits = ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩'];
    $toKhmerNumber = fn ($value) => strtr((string) $value, $khmerDigits);
    $khmerReportDate = function ($date) use ($khmerMonths, $toKhmerNumber) {
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);

        return $toKhmerNumber($date->format('j')).' ខែ'.($khmerMonths[(int) $date->format('n')] ?? $date->format('m')).' ឆ្នាំ'.$toKhmerNumber($date->format('Y'));
    };
    $recentActivityLocationName = trim((string) ($locations[$recentActivityFilters['location_id'] ?? null] ?? Session::get('business.name', 'កម្ពុជាក្រោម')));
    $recentActivityLocationName = $recentActivityLocationName !== '' ? $recentActivityLocationName : 'កម្ពុជាក្រោម';
    $recentActivityReportPrefix = 'គ្នាយើង-'.$recentActivityLocationName.' ';
    $recentActivityReportTitle = $recentActivityDateFrom->isSameDay($recentActivityDateTo)
        ? $recentActivityReportPrefix.'របាយការណ៍រំលស់ថ្ងៃទី'.$khmerReportDate($recentActivityDateFrom)
        : $recentActivityReportPrefix.'របាយការណ៍រំលស់ថ្ងៃទី'.$khmerReportDate($recentActivityDateFrom).' ដល់ថ្ងៃទី'.$khmerReportDate($recentActivityDateTo);
    $dashboardDateRange = \Carbon\Carbon::parse($filters['date_from'])->format('d-M-Y').' ~ '.\Carbon\Carbon::parse($filters['date_to'])->format('d-M-Y');
    $recentPaymentExportRows = collect($payload['recentPayments'] ?? [])->map(function ($payment) {
        $amount = (float) ($payment->amount ?? 0);
        $principal = (float) ($payment->principal_amount ?? 0);
        $interest = (float) ($payment->interest_amount ?? 0);
        $penalty = (float) ($payment->penalty_amount ?? 0);
        $other = round(max(0, $amount - $principal - $interest - $penalty), 2);
        $paymentType = strtolower(trim((string) ($payment->payment_type ?? '')));
        $loanStatus = strtolower(trim((string) ($payment->loan_status ?? '')));
        $paidOff = in_array($paymentType, ['loan', 'payoff', 'paid_off', 'settlement'], true)
            || in_array($loanStatus, ['closed', 'paid_off', 'completed', 'settled'], true);

        return [
            'កាលបរិច្ឆេទ' => ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('d-m-Y') : '',
            'វិក័យប័ត្រ' => $payment->loan_number ?? '',
            'ឈ្មោះអតិថិជន' => $payment->customer_name ?? '',
            'លេខទូរស័ព្ទ' => $payment->customer_phone ?? '',
            'ចំនួនខែត្រូវបង់' => $payment->month_count ?? '',
            'បង់ផ្ដាច់' => $paidOff ? 'បង់ផ្ដាច់' : '',
            'បង់-លុយសុទ្ធ' => (float) ($payment->cash_amount ?? 0),
            'បង់-តាមធនាគា' => (float) ($payment->bank_amount ?? 0),
            'តាមរយៈ' => $payment->payment_channel ?? $payment->payment_method ?? '',
            'សរុប' => $amount,
            'ប្រាក់ដើម' => $principal,
            'ការប្រាក់' => $interest,
            'ពិន័យ' => $penalty,
            'ផ្សេងៗ' => $other,
            'Email' => $payment->customer_email ?? '',
            'Name' => $payment->received_by_name ?? '',
            'លេខប្រតិបត្តិ' => $payment->transaction_no ?? '',
            'Number of Month' => $payment->number_of_month ?? '',
        ];
    })->values()->all();
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Moul&display=swap">
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
    .lm-dashboard-report-filter .box-body {
        padding-bottom: 8px;
    }
    .lm-dashboard-report-filter .form-group {
        margin-bottom: 10px;
    }
    .lm-dashboard-report-filter .filter-actions {
        display: flex;
        gap: 6px;
        padding-top: 24px;
    }
    .lm-recent-filter-card .filter-actions {
        display: flex;
        gap: 6px;
        padding-top: 24px;
    }
    .lm-recent-filter-card .filter-actions.pull-right {
        justify-content: flex-end;
        width: 100%;
    }
    .lm-recent-filter-card .box-header .filter-actions {
        align-items: center;
        padding-top: 0;
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
    .lm-recent-table-wrap .dataTables_wrapper,
    .lm-recent-table-wrap .dataTables_scroll,
    .lm-recent-table-wrap .dataTables_scrollHead,
    .lm-recent-table-wrap .dataTables_scrollBody {
        width: 100% !important;
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
        width: 100% !important;
        margin-bottom: 0;
        background: #fff;
        font-size: 11px;
        table-layout: auto;
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
    .lm-report-table .lm-col-method {
        width: auto !important;
        min-width: 0;
        max-width: none;
        white-space: nowrap;
        word-break: normal;
        overflow-wrap: normal;
    }
    .lm-report-table .lm-col-date {
        width: 60px !important;
        min-width: 60px;
        max-width: 60px;
        white-space: nowrap;
    }
    .lm-report-table .lm-col-loan {
        width: auto !important;
        min-width: 0;
        max-width: none;
        white-space: nowrap;
    }
    .lm-report-table .lm-col-payment-loan {
        width: auto !important;
        min-width: 0;
        white-space: nowrap;
    }
    .lm-report-table .lm-col-payment-loan .lm-loan-ref-line,
    .lm-report-table .lm-col-payment-loan .lm-loan-ref-line > span:first-child {
        max-width: none;
        overflow: visible;
        text-overflow: clip;
    }
    .lm-report-table .lm-col-payment-amount {
        width: 1% !important;
        min-width: 72px;
        white-space: nowrap;
    }
    .lm-report-table td.lm-col-method,
    .lm-report-table td.lm-col-loan,
    .lm-report-table td.lm-col-payment-loan {
        width: 1%;
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
        overflow: hidden;
    }
    .lm-loan-ref-line > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-loan-ref-line small {
        flex: 0 0 auto;
        color: #64748b;
    }
    .lm-detail-link {
        color: #2563eb;
        cursor: pointer;
        font-weight: 700;
        text-decoration: none;
    }
    .lm-detail-link:hover,
    .lm-detail-link:focus {
        color: #1d4ed8;
        text-decoration: underline;
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
        justify-content: space-between;
        gap: 8px;
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
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child > [class*="col-sm-"] {
        float: none;
        width: auto;
        padding-left: 0;
        padding-right: 0;
    }
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child > .col-sm-8 {
        flex: 1;
        text-align: center;
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
        font-family: "Khmer OS Muol Light", "Khmer OS Muol", "Khmer OS Moul", "Moul", "Noto Sans Khmer", "Kantumruy Pro", serif;
        font-size: 24px;
        font-weight: 400;
        line-height: 1.35;
    }
    .lm-recent-filter-card {
        margin-bottom: 15px;
        border-top-color: #3c8dbc;
        box-shadow: none;
    }
    .lm-recent-filter-card > .box-header {
        border-bottom: 1px solid #f4f4f4;
        cursor: pointer;
    }
    .lm-recent-filter-card > .box-header .box-title a {
        color: #444;
        text-decoration: none;
    }
    .lm-recent-filter-card > .box-header .box-title .fa {
        margin-right: 5px;
    }
    .lm-recent-filter-card > .box-body {
        padding: 15px;
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
        body.lm-print-recent-only .lm-loan-ref-line small {
            display: none !important;
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
<section class="content">
    <div class="box box-primary lm-dashboard-report-filter lm-recent-filter-card lm-report-no-print">
        <div class="box-header with-border">
            <h3 class="box-title">
                <a data-toggle="collapse" href="#loanDashboardReportFilterCollapse" aria-expanded="false">
                    <i class="fa fa-filter" aria-hidden="true"></i>{{ $t('Filters', 'តម្រង') }}
                </a>
            </h3>
        </div>
        <div class="box-body panel-collapse collapse" id="loanDashboardReportFilterCollapse">
            <form method="GET" action="{{ route('loan-management.reports.dashboard') }}" id="loanDashboardReportFilterForm">
                <input type="hidden" name="recent_location_id" value="{{ $recentActivityFilters['location_id'] ?? '' }}">
                <input type="hidden" name="recent_search" value="{{ $recentActivityFilters['search'] ?? '' }}">
                <input type="hidden" name="recent_date_from" value="{{ $recentActivityFilters['date_from'] ?? '' }}">
                <input type="hidden" name="recent_date_to" value="{{ $recentActivityFilters['date_to'] ?? '' }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                <input type="hidden" name="period" value="{{ $filters['period'] ?? 'daily' }}">
                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>{{ $t('Location', 'ទីតាំង') }}</label>
                            <select name="location_id" class="form-control select2" style="width:100%">
                                <option value="">{{ $t('All', 'ទាំងអស់') }}</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}" {{ (string) ($filters['location_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>{{ $t('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                            <input type="text" name="date_range" id="loanDashboardReportDateRange" class="form-control" placeholder="{{ $t('Select a date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" value="{{ $dashboardDateRange }}" readonly>
                        </div>
                    </div>
                </div>
            </form>
        </div>
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
                    <small>{{ $number($cards['collection_count'] ?? 0) }} {{ $t('payments', 'ការបង់ប្រាក់') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-blue"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Loan/Deposit Payments', 'ប្រាក់កក់កម្ចី') }}</span>
                    <span class="info-box-number">{{ $money($cards['deposit_total'] ?? 0) }}</span>
                    <small>{{ $number($cards['deposit_count'] ?? 0) }} {{ $t('payments', 'ការបង់ប្រាក់') }}</small>
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

    <div class="row lm-recent-activity-row">
        <div class="col-md-12 lm-report-no-print">
            <div class="box box-solid lm-recent-activity-panel">
                <div class="box-body">
                    <div class="box box-primary lm-recent-filter-card">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                <a data-toggle="collapse" href="#loanRecentActivityFilterCollapse" aria-expanded="false">
                                    <i class="fa fa-filter" aria-hidden="true"></i>{{ $t('Filters', 'តម្រង') }}
                                </a>
                            </h3>
                            <div class="box-tools pull-right filter-actions">
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.loanExportRecentActivityExcel()">
                                    <i class="fa fa-file-excel-o"></i> {{ $t('Export Excel', 'នាំចេញ Excel') }}
                                </button>
                                <button type="button" class="btn btn-success btn-sm" onclick="window.loanPrintRecentActivity()">
                                    <i class="fa fa-print"></i> {{ $t('Print', 'បោះពុម្ព') }}
                                </button>
                            </div>
                        </div>
                        <div class="box-body panel-collapse collapse" id="loanRecentActivityFilterCollapse">
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
                                            <select name="recent_location_id" class="form-control select2" style="width:100%">
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
                                            <input type="text" name="recent_date_range" id="loanRecentActivityDateRange" class="form-control" placeholder="{{ $t('Select a date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" value="{{ $recentActivityDateRange }}" readonly>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h2 class="lm-recent-report-title">{{ $recentActivityReportTitle }}</h2>

                    <div class="table-responsive">
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
                                        <th class="lm-col-date">{{ $t('Date', 'ថ្ងៃ') }}</th>
                                        <th class="lm-col-payment-loan">{{ $t('Loan #', 'លេខកម្ចី') }}</th>
                                        <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                                        <th class="lm-col-method">{{ $t('Method', 'ប្រភេទវិធីបង់') }}</th>
                                        <th class="text-right lm-col-payment-amount">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                                        <th>{{ $t('Note', 'ចំណាំ') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payload['recentPayments'] as $paymentIndex => $payment)
                                        @php($paymentDuplicateReason = $duplicateReason($payment, $recentPaymentLoanCounts, $recentPaymentCustomerCounts))
                                        @php($paymentDocUrl = $payment->payment_doc_url ?? null)
                                        <tr class="{{ $paymentDuplicateReason ? 'lm-duplicate-row' : '' }}" title="{{ $paymentDuplicateReason }}">
                                            <td class="lm-col-no">{{ $paymentIndex + 1 }}</td>
                                            <td class="lm-col-date">{{ ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('d-m-y') : '-' }}</td>
                                            <td class="lm-col-payment-loan">
                                                <span class="lm-loan-ref-line">
                                                    <a href="{{ route('loan-management.payments.show', $payment->id) }}"
                                                       class="lm-detail-link js-loan-recent-detail-modal"
                                                       data-url="{{ route('loan-management.payments.show', $payment->id) }}"
                                                       data-title="{{ $t('Payment Detail', 'ព័ត៌មានលម្អិតការបង់ប្រាក់') }}">{{ $payment->loan_number ?? '-' }}</a>
                                                </span>
                                            </td>
                                            <td>{{ $payment->customer_name ?: '-' }}</td>
                                            <td class="lm-col-method" title="{{ $payment->payment_method ?: '-' }}">
                                                <a href="{{ $paymentDocUrl ?: route('loan-management.payments.show', $payment->id) }}"
                                                   class="lm-detail-link js-loan-recent-detail-modal"
                                                   data-url="{{ $paymentDocUrl ?: route('loan-management.payments.show', $payment->id) }}"
                                                   data-title="{{ $t('Payment Doc', 'ឯកសារបង់ប្រាក់') }}">{{ $shortMethod($payment->payment_method ?? '-') }}</a>
                                            </td>
                                            <td class="text-right lm-col-payment-amount">{{ $money($payment->amount ?? 0) }}</td>
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
                                        <th class="lm-col-date">{{ $t('Date', 'ថ្ងៃ') }}</th>
                                        <th class="lm-col-loan">{{ $t('Loan #', 'លេខកម្ចី') }}</th>
                                        <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                                        <th>{{ $t('Product', 'ទំនិញ') }}</th>
                                        <th class="lm-col-method">{{ $t('Method', 'ប្រភេទវិធីបង់') }}</th>
                                        <th class="text-right">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                                        <th>{{ $t('Note', 'ចំណាំ') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payload['recentLoans'] as $loanIndex => $loan)
                                        @php($loanDuplicateReason = $duplicateReason($loan, $recentLoanLoanCounts, $recentLoanCustomerCounts))
                                        <tr class="{{ $loanDuplicateReason ? 'lm-duplicate-row' : '' }}" title="{{ $loanDuplicateReason }}">
                                            <td class="lm-col-no">{{ $loanIndex + 1 }}</td>
                                            <td class="lm-col-date">{{ ! empty($loan->loan_date) ? \Carbon\Carbon::parse($loan->loan_date)->format('d-m-y') : '-' }}</td>
                                            <td class="lm-col-loan">
                                                <span class="lm-loan-ref-line">
                                                    <span class="lm-detail-link js-loan-recent-detail-modal"
                                                          data-url="{{ route('loan-management.loans.view', ['loan' => $loan->id, '_lm_modal' => 1]) }}"
                                                          data-title="{{ $t('Loan Detail', 'ព័ត៌មានលម្អិតកម្ចី') }}">{{ $loan->loan_number ?? ('#'.$loan->id) }}</span>
                                                </span>
                                            </td>
                                            <td>{{ $loan->customer_name ?: '-' }}</td>
                                            <td>{{ $loan->product_name ?: '-' }}</td>
                                            <td class="lm-col-method" title="{{ $loan->payment_method ?: '-' }}">{{ $shortMethod($loan->payment_method ?? '-') }}</td>
                                            <td class="text-right">{{ $money($loan->payment_amount ?? 0) }}</td>
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
    var initLoanDashboardReports = function () {
        var displayDateFormat = window.moment_date_format || 'DD-MMM-YYYY';
        var bindFilterForm = function (formId, rangeId, startDate, endDate, eventNamespace) {
            var form = document.getElementById(formId);
            if (!form) {
                return;
            }
            var fromField = formId === 'loanRecentActivityFilterForm' ? 'recent_date_from' : 'date_from';
            var toField = formId === 'loanRecentActivityFilterForm' ? 'recent_date_to' : 'date_to';

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

            if (window.jQuery) {
                jQuery('#' + formId + ' .select2').select2({
                    width: '100%'
                }).on('change', function () {
                    scheduleSubmit(100);
                });
            }

            if (window.jQuery && jQuery.fn.daterangepicker && window.moment) {
                var dateRangeSettings = window.dateRangeSettings
                    ? jQuery.extend(true, {}, window.dateRangeSettings)
                    : {};

                dateRangeSettings = jQuery.extend(true, dateRangeSettings, {
                    autoUpdateInput: true,
                    startDate: moment(startDate),
                    endDate: moment(endDate),
                    parentEl: 'body',
                    opens: 'right',
                    drops: 'auto',
                    locale: jQuery.extend(true, {}, dateRangeSettings.locale || {}, {
                        format: displayDateFormat,
                        cancelLabel: 'Clear'
                    })
                });

                jQuery('#' + rangeId)
                    .daterangepicker(dateRangeSettings, function (start, end) {
                        jQuery('#' + rangeId).val(start.format(displayDateFormat) + ' ~ ' + end.format(displayDateFormat));
                        jQuery(form).find('[name="' + fromField + '"]').val(start.format('YYYY-MM-DD'));
                        jQuery(form).find('[name="' + toField + '"]').val(end.format('YYYY-MM-DD'));
                        scheduleSubmit(100);
                    })
                    .on('apply.daterangepicker', function (event, picker) {
                        jQuery(this).val(picker.startDate.format(displayDateFormat) + ' ~ ' + picker.endDate.format(displayDateFormat));
                        jQuery(form).find('[name="' + fromField + '"]').val(picker.startDate.format('YYYY-MM-DD'));
                        jQuery(form).find('[name="' + toField + '"]').val(picker.endDate.format('YYYY-MM-DD'));
                        scheduleSubmit(100);
                    })
                    .on('cancel.daterangepicker', function () {
                        jQuery(this).val('');
                        jQuery(form).find('[name="' + fromField + '"]').val('');
                        jQuery(form).find('[name="' + toField + '"]').val('');
                        scheduleSubmit(100);
                    });

                jQuery('#' + rangeId).off('click.' + eventNamespace).on('click.' + eventNamespace, function () {
                    var picker = jQuery(this).data('daterangepicker');
                    if (picker) {
                        picker.show();
                    }
                });
            }
        };

        bindFilterForm('loanDashboardReportFilterForm', 'loanDashboardReportDateRange', '{{ $filters['date_from'] }}', '{{ $filters['date_to'] }}', 'loanDashboardDateRange');
        bindFilterForm('loanRecentActivityFilterForm', 'loanRecentActivityDateRange', '{{ $recentActivityFilters['date_from'] }}', '{{ $recentActivityFilters['date_to'] }}', 'loanRecentDateRange');

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
                        exportOptions: {columns: ':visible'}
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel" aria-hidden="true"></i> Export Excel',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible'}
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print" aria-hidden="true"></i> Print',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                        title: recentActivityExportTitle,
                        exportOptions: {columns: ':visible', stripHtml: true}
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fa fa-columns" aria-hidden="true"></i> Column visibility',
                        className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2'
                    },
                ];
            }

            jQuery('.loan-recent-activity-datatable').each(function () {
                if (jQuery.fn.DataTable.isDataTable(this)) {
                    return;
                }

                jQuery(this).DataTable({
                    dom: '<"row margin-bottom-20 text-center"<"col-sm-1"l><"col-sm-8"B><"col-sm-3"f> r>tip',
                    buttons: recentActivityButtons,
                    pageLength: -1,
                    lengthMenu: [[-1, 25, 50, 100], ['All', 25, 50, 100]],
                    order: [],
                    autoWidth: true,
                    scrollX: false,
                    columnDefs: [
                        {targets: 0, orderable: false, searchable: false}
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
                .on('click.loanRecentDetailModal', '.js-loan-recent-detail-modal', function (event) {
                    var url = jQuery(this).data('url');
                    var title = jQuery(this).data('title') || 'Detail';

                    if (!url || !jQuery('.view_modal').length) {
                        return;
                    }

                    event.preventDefault();

                    if (url.indexOf('_lm_modal=1') === -1) {
                        url += (url.indexOf('?') === -1 ? '?' : '&') + '_lm_modal=1';
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

    };

    if (window.jQuery) {
        jQuery(initLoanDashboardReports);
    } else {
        document.addEventListener('DOMContentLoaded', initLoanDashboardReports);
    }
    })();

    var loanRecentActivityEsc = function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    var loanRecentActivityTableFromDataTable = function (selector, title) {
        var table = window.jQuery ? jQuery(selector)[0] : null;
        if (!table) {
            return '';
        }

        var data = {header: [], body: []};
        if (jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(table)) {
            var api = jQuery(table).DataTable();
            data = api.buttons && api.buttons.exportData
                ? api.buttons.exportData({
                    columns: ':visible',
                    modifier: {search: 'applied', order: 'applied', page: 'all'},
                    stripHtml: true,
                    format: {
                        body: function (cell) {
                            return jQuery('<div>').html(cell == null ? '' : cell).text().replace(/\s+/g, ' ').trim();
                        },
                        header: function (cell) {
                            return jQuery('<div>').html(cell == null ? '' : cell).text().replace(/\s+/g, ' ').trim();
                        }
                    }
                })
                : data;
        } else {
            jQuery(table).find('thead th').each(function () {
                data.header.push(jQuery(this).text().replace(/\s+/g, ' ').trim());
            });
            jQuery(table).find('tbody tr').each(function () {
                var row = [];
                jQuery(this).find('td').each(function () {
                    row.push(jQuery(this).text().replace(/\s+/g, ' ').trim());
                });
                data.body.push(row);
            });
        }

        var html = (title ? '<h3>' + loanRecentActivityEsc(title) + '</h3>' : '') + '<table><thead><tr>';
        data.header.forEach(function (heading) {
            html += '<th>' + loanRecentActivityEsc(heading) + '</th>';
        });
        html += '</tr></thead><tbody>';
        data.body.forEach(function (row) {
            html += '<tr>';
            row.forEach(function (cell) {
                html += '<td>' + loanRecentActivityEsc(cell) + '</td>';
            });
            html += '</tr>';
        });

        return html + '</tbody></table>';
    };

    var loanRecentActivityTableFromDom = function (selector, title) {
        var table = window.jQuery ? jQuery(selector)[0] : null;
        if (!table) {
            return '';
        }

        var html = (title ? '<h3>' + loanRecentActivityEsc(title) + '</h3>' : '') + '<table><thead><tr>';
        jQuery(table).find('thead th').each(function () {
            html += '<th>' + loanRecentActivityEsc(jQuery(this).text().replace(/\s+/g, ' ').trim()) + '</th>';
        });
        html += '</tr></thead><tbody>';
        jQuery(table).find('tbody tr').each(function () {
            html += '<tr>';
            jQuery(this).find('td').each(function () {
                html += '<td>' + loanRecentActivityEsc(jQuery(this).text().replace(/\s+/g, ' ').trim()) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody>';
        var footer = jQuery(table).find('tfoot tr');
        if (footer.length) {
            html += '<tfoot>';
            footer.each(function () {
                html += '<tr>';
                jQuery(this).find('th,td').each(function () {
                    html += '<th>' + loanRecentActivityEsc(jQuery(this).text().replace(/\s+/g, ' ').trim()) + '</th>';
                });
                html += '</tr>';
            });
            html += '</tfoot>';
        }

        return html + '</table>';
    };

    window.loanPrintRecentActivity = function () {
        var html = '<!doctype html><html><head><meta charset="UTF-8"><title>' + loanRecentActivityEsc(@json($recentActivityReportTitle)) + '</title>';
        html += '<style>';
        html += '@import url("https://fonts.googleapis.com/css2?family=Moul&display=swap");@page{size:A4 landscape;margin:10mm}body{font-family:Arial,Helvetica,sans-serif;color:#111827;background:#fff;font-size:11px}';
        html += 'h2{margin:0 0 10px;padding:12px 14px;border-top:1px solid #94a3b8;border-bottom:2px solid #111827;background:#dbeafe;color:#0000ff;text-align:center;font-family:"Khmer OS Muol Light","Khmer OS Muol","Khmer OS Moul","Moul","Noto Sans Khmer","Kantumruy Pro",serif;font-size:22px;font-weight:400;line-height:1.3}';
        html += 'h3{margin:12px 0 5px;padding:5px 8px;border:1px solid #9ca3af;background:#e5f0fb;text-align:center;font-size:13px}';
        html += 'table{width:100%;border-collapse:collapse;margin:0 0 10px;table-layout:auto}th,td{border:1px solid #9ca3af;padding:4px 5px;vertical-align:top;word-break:break-word}';
        html += 'th{background:#dbeafe;text-align:center;font-weight:700}td{text-align:left}td.amount,td.count{text-align:right}.recent-grid{display:grid;grid-template-columns:1fr 1fr;gap:8mm;align-items:start}.recent-grid td,.recent-grid th{font-size:9px}';
        html += '</style></head><body>';
        html += '<h2>' + loanRecentActivityEsc(@json($recentActivityReportTitle)) + '</h2>';
        html += loanRecentActivityTableFromDom('.lm-payment-method-summary', '');
        html += '<div class="recent-grid">';
        html += '<div>' + loanRecentActivityTableFromDataTable('#loan_recent_payments_table', @json($t('Recent Collected Payments', 'ការបង់ប្រាក់ថ្មីៗ'))) + '</div>';
        html += '<div>' + loanRecentActivityTableFromDataTable('#loan_recent_loans_table', @json($t('Recent Loans', 'កម្ចីថ្មីៗ'))) + '</div>';
        html += '</div></body></html>';

        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            document.body.classList.add('lm-print-recent-only');
            window.print();
            window.setTimeout(function () {
                document.body.classList.remove('lm-print-recent-only');
            }, 500);
            return;
        }

        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();
        window.setTimeout(function () {
            printWindow.print();
            printWindow.close();
        }, 300);
    };

    window.loanExportRecentActivityExcel = function () {
        var esc = function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        };
        var fullPaymentRows = @json($recentPaymentExportRows, JSON_UNESCAPED_UNICODE);
        var fullPaymentTable = function (title) {
            var headings = [
                'កាលបរិច្ឆេទ',
                'វិក័យប័ត្រ',
                'ឈ្មោះអតិថិជន',
                'លេខទូរស័ព្ទ',
                'ចំនួនខែត្រូវបង់',
                'បង់ផ្ដាច់',
                'បង់-លុយសុទ្ធ',
                'បង់-តាមធនាគា',
                'តាមរយៈ',
                'សរុប',
                'ប្រាក់ដើម',
                'ការប្រាក់',
                'ពិន័យ',
                'ផ្សេងៗ',
                'Email',
                'Name',
                'លេខប្រតិបត្តិ',
                'Number of Month'
            ];
            var html = '<h3>' + esc(title) + '</h3><table border="1"><thead><tr>';
            headings.forEach(function (heading) {
                html += '<th>' + esc(heading) + '</th>';
            });
            html += '</tr></thead><tbody>';
            fullPaymentRows.forEach(function (row) {
                html += '<tr>';
                headings.forEach(function (heading) {
                    html += '<td>' + esc(row[heading]) + '</td>';
                });
                html += '</tr>';
            });

            return html + '</tbody></table><br>';
        };
        var tableFromDataTable = function (selector, title) {
            var table = window.jQuery ? jQuery(selector)[0] : null;
            if (!table || !jQuery.fn.DataTable || !jQuery.fn.DataTable.isDataTable(table)) {
                return '';
            }

            var api = jQuery(table).DataTable();
            var data = api.buttons && api.buttons.exportData
                ? api.buttons.exportData({
                    columns: ':visible',
                    modifier: {search: 'applied', order: 'applied', page: 'all'},
                    stripHtml: true,
                    format: {
                        body: function (data) {
                            return jQuery('<div>').html(data == null ? '' : data).text().replace(/\s+/g, ' ').trim();
                        },
                        header: function (data) {
                            return jQuery('<div>').html(data == null ? '' : data).text().replace(/\s+/g, ' ').trim();
                        }
                    }
                })
                : {header: [], body: []};
            var html = '<h3>' + esc(title) + '</h3><table border="1"><thead><tr>';

            data.header.forEach(function (heading) {
                html += '<th>' + esc(heading) + '</th>';
            });
            html += '</tr></thead><tbody>';
            data.body.forEach(function (row) {
                html += '<tr>';
                row.forEach(function (cell) {
                    html += '<td>' + esc(cell) + '</td>';
                });
                html += '</tr>';
            });

            return html + '</tbody></table><br>';
        };
        var html = '<html><head><meta charset="UTF-8"></head><body>';

        html += '<h2>' + esc(@json($recentActivityReportTitle)) + '</h2>';
        html += fullPaymentTable(@json($t('Recent Collected Payments', 'ការបង់ប្រាក់ថ្មីៗ')));
        html += tableFromDataTable('#loan_recent_loans_table', @json($t('Recent Loans', 'កម្ចីថ្មីៗ')));
        html += '</body></html>';

        var blob = new Blob([html], {type: 'application/vnd.ms-excel;charset=utf-8;'});
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'daily-activity-report-{{ now()->format('YmdHis') }}.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(function () {
            URL.revokeObjectURL(link.href);
        }, 1000);
    };
</script>
@endsection
