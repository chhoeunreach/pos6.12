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
            $reasons[] = $t('Duplicate Installment #', 'លេខកម្ចីស្ទួន');
        }
        if (($customerCounts[$normalizeDuplicateKey($row->customer_name ?? '')] ?? 0) > 1) {
            $reasons[] = $t('Duplicate Customer', 'អតិថិជនស្ទួន');
        }

        return implode(' / ', $reasons);
    };
    $recentPaymentLoanCounts = $duplicateCounts($payload['recentPayments'] ?? [], 'loan_number');
    $recentPaymentCustomerCounts = $duplicateCounts($payload['recentPayments'] ?? [], 'customer_name');
    $recentLoanLoanCounts = $duplicateCounts($payload['recentLoans'] ?? [], 'loan_number');
    $recentLoanCustomerCounts = $duplicateCounts($payload['recentLoans'] ?? [], 'customer_name');
    $period = $filters['period'] ?? 'daily';
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
    $recentActivityDateRange = \Carbon\Carbon::parse($recentActivityFilters['date_from'])->format('m-d-Y').' - '.\Carbon\Carbon::parse($recentActivityFilters['date_to'])->format('m-d-Y');
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
    $reportBusinessName = trim((string) (\Modules\LoanManagement\Services\BusinessSettingsService::businessName() ?: Session::get('business.name', '')));
    $reportBusinessBrand = $reportBusinessName !== '' ? $reportBusinessName : 'គ្នាយើង';
    $selectedLocationName = ! empty($recentActivityFilters['location_id']) && ! empty($locations[$recentActivityFilters['location_id']])
        ? trim((string) $locations[$recentActivityFilters['location_id']])
        : '';
    $recentActivityReportPrefix = $reportBusinessBrand.($selectedLocationName !== '' ? '-'.$selectedLocationName : '').' ';
    $recentActivityReportTitle = $recentActivityDateFrom->isSameDay($recentActivityDateTo)
        ? $recentActivityReportPrefix.'របាយការណ៍រំលស់ថ្ងៃទី'.$khmerReportDate($recentActivityDateFrom)
        : $recentActivityReportPrefix.'របាយការណ៍រំលស់ថ្ងៃទី'.$khmerReportDate($recentActivityDateFrom).' ដល់ថ្ងៃទី'.$khmerReportDate($recentActivityDateTo);
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Moul&family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<style>
    /* Modern Compact Dashboard Reports Styles */
    .lm-reports-shell {
        padding: 2px 0 16px;
        font-family: inherit;
    }
    /* Period Switcher Pills */
    .lm-period-pill-group {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 2px;
        gap: 2px;
    }
    .lm-period-pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .lm-period-pill:hover {
        color: #0f172a;
        text-decoration: none;
    }
    .lm-period-pill.is-active {
        background: #ffffff;
        color: #2563eb;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
        text-decoration: none;
    }

    /* --- STANDARD FILTER PANEL (matching All Installments) --- */
    .lm-loan-list-filter {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        margin-bottom: 14px;
        transition: all 0.2s ease;
    }
    .lm-loan-list-filter-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 14px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        cursor: default;
        user-select: none;
    }
    .lm-loan-list-filter-toggle-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        font-weight: 800;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .lm-loan-list-filter-toggle-label .fa {
        color: #2563eb;
        font-size: 13px;
    }
    .lm-loan-list-filter-toggle-actions {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-loan-list-reset {
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-decoration: none;
        padding: 3px 8px;
        border-radius: 6px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .lm-loan-list-reset:hover {
        color: #ef4444;
        background: #fef2f2;
        border-color: #fca5a5;
        text-decoration: none;
    }
    .lm-loan-list-filter-body {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 14px;
        align-items: flex-end;
    }
    .lm-loan-list-field.date-range-field {
        width: 250px !important;
        max-width: 250px !important;
        flex: 0 0 250px !important;
    }
    .lm-loan-list-field.location-field {
        width: 210px !important;
        max-width: 210px !important;
        flex: 0 0 210px !important;
    }
    .lm-loan-list-field.period-field {
        width: 140px !important;
        max-width: 140px !important;
        flex: 0 0 140px !important;
    }
    .lm-loan-list-field.search-field {
        width: 230px !important;
        max-width: 230px !important;
        flex: 1 1 230px !important;
    }
    .lm-loan-list-field.lm-loan-list-field-actions {
        width: 95px !important;
        max-width: 95px !important;
        flex: 0 0 95px !important;
    }
    @media (max-width: 768px) {
        .lm-loan-list-field.date-range-field,
        .lm-loan-list-field.location-field,
        .lm-loan-list-field.period-field,
        .lm-loan-list-field.search-field,
        .lm-loan-list-field.lm-loan-list-field-actions {
            width: 100% !important;
            max-width: 100% !important;
            flex: 1 1 100% !important;
        }
    }
    .lm-loan-list-field label {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 4px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .lm-loan-list-field .form-control,
    .lm-loan-list-field .select2-container--default .select2-selection--single {
        height: 36px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 12px !important;
        padding: 5px 10px !important;
        box-shadow: none !important;
        transition: border-color 0.2s, box-shadow 0.2s;
        line-height: 24px !important;
        background: #fff;
    }
    .lm-loan-list-field .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px !important;
        padding-left: 0 !important;
        color: #0f172a !important;
    }
    .lm-loan-list-field .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 34px !important;
    }
    .lm-loan-list-field .form-control:focus,
    .lm-loan-list-field .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15) !important;
        outline: none !important;
    }
    .lm-loan-list-field-actions .btn {
        height: 36px !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }
    .daterangepicker {
        z-index: 999999 !important;
    }
    .lm-filter-actions-bar {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .lm-btn-filter {
        height: 35px;
        padding: 0 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11.5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        white-space: nowrap;
    }
    .lm-btn-filter-excel {
        background: #059669;
        color: #ffffff;
    }
    .lm-btn-filter-excel:hover {
        background: #047857;
        color: #ffffff;
    }
    .lm-btn-filter-print {
        background: #f1f5f9;
        color: #334155;
        border-color: #cbd5e1;
    }
    .lm-btn-filter-print:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .lm-recent-panel-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .lm-recent-table-wrap {
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    .lm-recent-table-wrap .dataTables_wrapper,
    .lm-recent-table-wrap .dataTables_scroll,
    .lm-recent-table-wrap .dataTables_scrollHead,
    .lm-recent-table-wrap .dataTables_scrollBody {
        width: 100% !important;
    }
    .lm-recent-panel-heading {
        margin: 0 0 12px;
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border-left: 4px solid #2563eb;
        color: #1f2937;
        font-size: 13px;
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
        padding: 7px 8px !important;
        line-height: 1.3;
    }
    .lm-report-table > thead > tr > th {
        background: #f1f5f9;
        color: #0f172a;
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
        background: #fffbeb !important;
        border-top-color: #fde68a !important;
        border-bottom-color: #fde68a !important;
    }
    .lm-report-table > tbody > tr.lm-duplicate-row > td:first-child {
        border-left: 4px solid #f59e0b !important;
    }
    .lm-duplicate-customer-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .lm-duplicate-customer-name {
        font-weight: 700;
        color: #92400e;
    }
    .lm-duplicate-customer-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 999px;
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(245, 158, 11, 0.15);
    }
    .lm-duplicate-customer-badge i {
        color: #d97706;
        font-size: 9px;
    }
    .lm-report-table > tfoot > tr > th {
        background: #f8fafc;
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
        background: #f1f5f9;
        color: #0f172a;
        text-align: center;
        vertical-align: middle !important;
    }
    .lm-payment-method-summary td {
        vertical-align: middle !important;
    }
    .lm-payment-method-summary tfoot th {
        background: #f8fafc;
    }
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin: 0 0 8px;
    }
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child:before,
    .lm-recent-table-wrap .dataTables_wrapper .row:first-child:after {
        display: none;
    }
    .lm-recent-table-wrap .dataTables_length select,
    .lm-recent-table-wrap .dataTables_filter input {
        height: 30px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: none;
        font-size: 12px;
    }
    .lm-recent-table-wrap .dataTables_filter input {
        min-width: 140px;
    }
    .lm-recent-table-wrap .dt-buttons {
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px;
    }
    .lm-recent-table-wrap .dt-buttons .dt-button,
    .lm-recent-table-wrap .dt-buttons a.dt-button,
    .lm-recent-table-wrap .dt-buttons button.dt-button {
        padding: 4px 8px !important;
        font-size: 11px !important;
        border-radius: 6px !important;
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
    .lm-recent-report-title {
        margin: 0 0 16px;
        padding: 14px 18px;
        border-radius: 10px;
        background: #eff6ff;
        border-left: 5px solid #2563eb;
        color: #1e40af;
        text-align: center;
        font-family: "Khmer OS Muol Light", "Khmer OS Muol", "Khmer OS Moul", "Moul", "Noto Sans Khmer", "Kantumruy Pro", serif;
        font-size: 20px;
        font-weight: 400;
        line-height: 1.4;
    }
    @media print {
        @page {
            size: A4 portrait;
            margin: 8mm;
        }
        .lm-sidebar,
        .lm-header,
        .lm-breadcrumb-wrap,
        .main-footer,
        .no-print {
            display: none !important;
        }
        .collapsed-box .box-body {
            display: block !important;
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
<section class="content lm-reports-shell">
    <!-- Recent Activity & Detailed Tables Section -->
    <div class="row lm-recent-activity-row">
        <div class="col-md-12">
            <div class="box box-solid lm-recent-activity-panel" style="border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 18px -2px rgba(15,23,42,0.05);overflow:hidden;">
                <div class="box-body" style="padding:18px;">
                    <!-- Standard Filter for Detailed Report -->
                    <div class="lm-loan-list-filter" id="loanFilterPanel">
                        <div class="lm-loan-list-filter-toggle">
                            <span class="lm-loan-list-filter-toggle-label"><i class="fa fa-filter"></i> {{ $t('Filters', 'តម្រង') }}</span>
                            <span class="lm-loan-list-filter-toggle-actions">
                                <a href="{{ route('loan-management.reports.dashboard') }}" class="lm-loan-list-reset">
                                    <i class="fa fa-refresh"></i> {{ $t('Reset', 'កំណត់ឡើងវិញ') }}
                                </a>
                                <button type="button" class="btn btn-success btn-xs" onclick="window.loanExportRecentActivityExcel()">
                                    <i class="fa fa-file-excel"></i> {{ $t('Export Excel', 'នាំចេញ Excel') }}
                                </button>
                                <button type="button" class="btn btn-default btn-xs" onclick="window.loanPrintRecentActivity()">
                                    <i class="fa fa-print"></i> {{ $t('Print', 'បោះពុម្ព') }}
                                </button>
                            </span>
                        </div>
                        <div class="lm-loan-list-filter-body" id="loanFilterBody">
                            <form method="GET" action="{{ route('loan-management.reports.dashboard') }}" id="loanRecentActivityFilterForm" style="display:contents;">
                                <input type="hidden" name="period" value="{{ $period }}">
                                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                <input type="hidden" name="location_id" value="{{ $filters['location_id'] ?? '' }}">
                                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                                <input type="hidden" id="recent_date_from" name="recent_date_from" value="{{ $recentActivityFilters['date_from'] ?? '' }}">
                                <input type="hidden" id="recent_date_to" name="recent_date_to" value="{{ $recentActivityFilters['date_to'] ?? '' }}">

                                <div class="lm-loan-list-field date-range-field">
                                    <label for="loanRecentActivityDateRange">{{ $t('Date Range', 'ចន្លោះកាលបរិច្ឆេទ') }}</label>
                                    <input type="text" name="recent_date_range" id="loanRecentActivityDateRange" placeholder="{{ $t('Select date range', 'ជ្រើសរើសចន្លោះកាលបរិច្ឆេទ') }}" class="form-control" autocomplete="off" value="{{ $recentActivityDateRange }}">
                                </div>
                                <div class="lm-loan-list-field location-field">
                                    <label for="recent_location_id">{{ $t('Location', 'សាខា') }}</label>
                                    <select name="recent_location_id" id="recent_location_id" class="form-control select2" style="width:100%">
                                        <option value="">{{ $t('All Locations', 'សាខាទាំងអស់') }}</option>
                                        @foreach($locations as $id => $name)
                                            <option value="{{ $id }}" {{ (string) ($recentActivityFilters['location_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="lm-loan-list-field search-field">
                                    <label for="recent_search">{{ $t('Search', 'ស្វែងរក') }}</label>
                                    <input type="text" name="recent_search" id="recent_search" class="form-control" value="{{ $recentActivityFilters['search'] ?? '' }}" placeholder="{{ $t('Customer, phone, or loan #', 'អតិថិជន ទូរស័ព្ទ ឬលេខកម្ចី') }}">
                                </div>
                                <div class="lm-loan-list-field lm-loan-list-field-actions">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-filter"></i> {{ $t('Apply', 'អនុវត្ត') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive lm-payment-method-summary" style="margin-bottom:20px;">
                        <table class="table table-bordered table-striped lm-report-table">
                            <thead>
                                <tr>
                                    <th>{{ $t('Method', 'វិធីសាស្ត្រទូទាត់') }}</th>
                                    <th>{{ $t('Count', 'ចំនួន') }}</th>
                                    <th>CASH</th>
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
                                        <th class="lm-col-payment-loan">{{ $t('Installment #', 'លេខកម្ចី') }}</th>
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
                                        @php($custKey = $normalizeDuplicateKey($payment->customer_name ?? ''))
                                        @php($isCustDuplicate = $custKey !== '' && ($recentPaymentCustomerCounts[$custKey] ?? 0) > 1)
                                        @php($custDupCount = $recentPaymentCustomerCounts[$custKey] ?? 0)
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
                                            <td>
                                                @if($isCustDuplicate)
                                                    <div class="lm-duplicate-customer-wrap">
                                                        <span class="lm-duplicate-customer-name">{{ $payment->customer_name ?: '-' }}</span>
                                                        <span class="lm-duplicate-customer-badge" title="{{ $t('Duplicate customer in collected payments: :count records', 'អតិថិជនស្ទួនក្នុងការបង់ប្រាក់៖ :count កំណត់ត្រា', ['count' => $custDupCount]) }}">
                                                            <i class="fa fa-clone"></i> {{ $t('Duplicate', 'ស្ទួន') }} ({{ $custDupCount }}x)
                                                        </span>
                                                    </div>
                                                @else
                                                    {{ $payment->customer_name ?: '-' }}
                                                @endif
                                            </td>
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
                            <h4 class="lm-recent-panel-heading">{{ $t('Recent Installments', 'កម្ចីថ្មីៗ') }}</h4>
                            <table class="table table-bordered table-hover loan-recent-activity-datatable lm-report-table" id="loan_recent_loans_table">
                                <thead>
                                    <tr>
                                        <th class="lm-col-no">{{ $t('No', 'ល.រ') }}</th>
                                        <th class="lm-col-date">{{ $t('Date', 'ថ្ងៃ') }}</th>
                                        <th class="lm-col-loan">{{ $t('Installment #', 'លេខកម្ចី') }}</th>
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
                                        @php($loanCustKey = $normalizeDuplicateKey($loan->customer_name ?? ''))
                                        @php($isLoanCustDuplicate = $loanCustKey !== '' && ($recentLoanCustomerCounts[$loanCustKey] ?? 0) > 1)
                                        @php($loanCustDupCount = $recentLoanCustomerCounts[$loanCustKey] ?? 0)
                                        <tr class="{{ $loanDuplicateReason ? 'lm-duplicate-row' : '' }}" title="{{ $loanDuplicateReason }}">
                                            <td class="lm-col-no">{{ $loanIndex + 1 }}</td>
                                            <td class="lm-col-date">{{ ! empty($loan->loan_date) ? \Carbon\Carbon::parse($loan->loan_date)->format('d-m-y') : '-' }}</td>
                                            <td class="lm-col-loan">
                                                <span class="lm-loan-ref-line">
                                                    <span class="lm-detail-link js-loan-recent-detail-modal"
                                                          data-url="{{ route('loan-management.loans.view', ['loan' => $loan->id, '_lm_modal' => 1]) }}"
                                                          data-title="{{ $t('Installment Detail', 'ព័ត៌មានលម្អិតកម្ចី') }}">{{ $loan->loan_number ?? ('#'.$loan->id) }}</span>
                                                </span>
                                            </td>
                                            <td>
                                                @if($isLoanCustDuplicate)
                                                    <div class="lm-duplicate-customer-wrap">
                                                        <span class="lm-duplicate-customer-name">{{ $loan->customer_name ?: '-' }}</span>
                                                        <span class="lm-duplicate-customer-badge" title="{{ $t('Duplicate customer in installments: :count records', 'អតិថិជនស្ទួនក្នុងកម្ចី៖ :count កំណត់ត្រា', ['count' => $loanCustDupCount]) }}">
                                                            <i class="fa fa-clone"></i> {{ $t('Duplicate', 'ស្ទួន') }} ({{ $loanCustDupCount }}x)
                                                        </span>
                                                    </div>
                                                @else
                                                    {{ $loan->customer_name ?: '-' }}
                                                @endif
                                            </td>
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
        var loanDateFormat = window.moment_date_format || 'MM-DD-YYYY';
        var loanDrs = window.dateRangeSettings ? jQuery.extend(true, {}, window.dateRangeSettings) : {};
        var hasMoment = typeof moment !== 'undefined';
        var loanDateRanges = {};

        if (hasMoment) {
            var fyStart = (typeof financial_year !== 'undefined' && financial_year.start && moment(financial_year.start).isValid())
                ? moment(financial_year.start)
                : moment().startOf('year');
            var fyEnd = (typeof financial_year !== 'undefined' && financial_year.end && moment(financial_year.end).isValid())
                ? moment(financial_year.end)
                : moment().endOf('year');
            loanDateRanges = {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [
                    moment().subtract(1, 'month').startOf('month'),
                    moment().subtract(1, 'month').endOf('month')
                ],
                'This month last year': [
                    moment().subtract(1, 'year').startOf('month'),
                    moment().subtract(1, 'year').endOf('month')
                ],
                'This Year': [moment().startOf('year'), moment().endOf('year')],
                'Last Year': [
                    moment().subtract(1, 'year').startOf('year'),
                    moment().subtract(1, 'year').endOf('year')
                ],
                'Current financial year': [fyStart.clone(), fyEnd.clone()],
                'Last financial year': [
                    fyStart.clone().subtract(1, 'year'),
                    fyEnd.clone().subtract(1, 'year')
                ]
            };

            if (window.dateRangeSettings && window.dateRangeSettings.ranges) {
                loanDateRanges = window.dateRangeSettings.ranges;
            }
        }

        var bindDateRangeFilter = function (options) {
            var $range = jQuery(options.range);
            if (!$range.length) {
                return;
            }

            var $from = jQuery(options.from);
            var $to = jQuery(options.to);
            var $form = jQuery(options.form);

            var setRange = function (start, end) {
                if (!start || !end) return;
                var s = moment.isMoment(start) ? start : moment(start);
                var e = moment.isMoment(end) ? end : moment(end);
                if (!s.isValid() || !e.isValid()) return;
                $from.val(s.format('YYYY-MM-DD'));
                $to.val(e.format('YYYY-MM-DD'));
                $range.val(s.format(loanDateFormat) + ' - ' + e.format(loanDateFormat));
            };

            if (hasMoment && jQuery.fn.daterangepicker) {
                var currentStartDate = options.start ? moment(options.start) : moment().startOf('month');
                var currentEndDate = options.end ? moment(options.end) : moment();

                $range.daterangepicker(jQuery.extend(true, {}, loanDrs, {
                    autoUpdateInput: false,
                    showDropdowns: true,
                    linkedCalendars: false,
                    startDate: currentStartDate.isValid() ? currentStartDate : moment().startOf('month'),
                    endDate: currentEndDate.isValid() ? currentEndDate : moment(),
                    parentEl: 'body',
                    opens: 'right',
                    drops: 'auto',
                    ranges: loanDateRanges,
                    locale: jQuery.extend(true, {}, loanDrs.locale || {}, {
                        format: loanDateFormat,
                        separator: ' - ',
                        applyLabel: @json($t('Apply', 'អនុវត្ត')),
                        cancelLabel: @json($t('Clear', 'សម្អាត')),
                        customRangeLabel: @json($t('Custom Range', 'ជ្រើសរើសផ្ទាល់')),
                        toLabel: '~'
                    })
                }), function (s, e) {
                    setRange(s, e);
                });

                setRange(currentStartDate, currentEndDate);

                $range.on('apply.daterangepicker', function (event, picker) {
                    setRange(picker.startDate, picker.endDate);
                });

                $range.on('cancel.daterangepicker', function () {
                    $range.val('');
                    $from.val('');
                    $to.val('');
                });
            } else {
                $range.prop('readonly', false).on('change', function () {
                    var raw = String(jQuery(this).val() || '').trim();
                    var parts = raw.split(/\s+-\s+|\s+~\s+/);
                    if (parts.length === 2 && hasMoment) {
                        var s = moment(parts[0], loanDateFormat, true);
                        var e = moment(parts[1], loanDateFormat, true);
                        if (s.isValid() && e.isValid()) {
                            $from.val(s.format('YYYY-MM-DD'));
                            $to.val(e.format('YYYY-MM-DD'));
                        }
                    }
                });
            }
        };

        bindDateRangeFilter({
            range: '#loanRecentActivityDateRange',
            from: '#recent_date_from',
            to: '#recent_date_to',
            form: '#loanRecentActivityFilterForm',
            start: @json($recentActivityFilters['date_from'] ?? null),
            end: @json($recentActivityFilters['date_to'] ?? null)
        });

        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#recent_location_id').select2({
                width: '100%'
            }).on('change', function () {
                jQuery('#loanRecentActivityFilterForm').submit();
            });
        }

        if (window.jQuery) {
            jQuery('#loanRecentActivityFilterForm').off('submit.loanRecentActivityFilters').on('submit.loanRecentActivityFilters', function () {
                var raw = String(jQuery('#loanRecentActivityDateRange').val() || '').trim();
                var parts = raw.split(/\s+-\s+|\s+~\s+/);
                if (parts.length === 2 && window.moment) {
                    var start = moment(parts[0], loanDateFormat, true);
                    var end = moment(parts[1], loanDateFormat, true);
                    if (start.isValid() && end.isValid()) {
                        jQuery('#recent_date_from').val(start.format('YYYY-MM-DD'));
                        jQuery('#recent_date_to').val(end.format('YYYY-MM-DD'));
                    }
                }
            });
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

    var loanDashboardLoadScript = function (src, isReady, done) {
        if (isReady()) {
            done();
            return;
        }

        var existing = document.querySelector('script[src="' + src + '"]');
        if (existing) {
            existing.addEventListener('load', done, {once: true});
            existing.addEventListener('error', done, {once: true});
            return;
        }

        var script = document.createElement('script');
        script.src = src;
        script.async = false;
        script.onload = done;
        script.onerror = done;
        document.head.appendChild(script);
    };

    var loanDashboardBoot = function () {
        if (!window.jQuery) {
            window.setTimeout(loanDashboardBoot, 50);
            return;
        }

        loanDashboardLoadScript('https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js', function () {
            return typeof window.moment !== 'undefined';
        }, function () {
            loanDashboardLoadScript('https://cdn.jsdelivr.net/npm/daterangepicker@3.1/daterangepicker.min.js', function () {
                return !!(window.jQuery && jQuery.fn && jQuery.fn.daterangepicker);
            }, function () {
                jQuery(initLoanDashboardReports);
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loanDashboardBoot);
    } else {
        loanDashboardBoot();
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
        var readDomData = function () {
            var domData = {header: [], body: []};
            jQuery(table).find('thead th').each(function () {
                domData.header.push(jQuery(this).text().replace(/\s+/g, ' ').trim());
            });
            jQuery(table).find('tbody tr').each(function () {
                var row = [];
                jQuery(this).find('td').each(function () {
                    row.push(jQuery(this).text().replace(/\s+/g, ' ').trim());
                });
                if (row.length) {
                    domData.body.push(row);
                }
            });

            return domData;
        };

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
                : readDomData();
        } else {
            data = readDomData();
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
        html += '@import url("https://fonts.googleapis.com/css2?family=Moul&family=Inter:wght@400;600;700;800&display=swap");';
        html += '@page{size:A4 portrait;margin:8mm;}';
        html += '*{box-sizing:border-box;}';
        html += 'body{font-family:"Inter","Noto Sans Khmer","Khmer OS Siemreap",Arial,sans-serif;color:#000;background:#fff;font-size:10px;margin:0;padding:0;}';
        html += '.report-title{margin:0 0 12px;padding:9px 12px;border:1px solid #8fa1ba;border-bottom:4px solid #111827;background:#d9e8fa;color:#0000ff;text-align:center;font-family:"Khmer OS Muol Light","Khmer OS Muol","Khmer OS Moul","Moul","Noto Sans Khmer",serif;font-size:25px;font-weight:400;line-height:1.35;}';
        html += '.summary{margin-bottom:12px;}';
        html += '.recent-grid{display:block;}';
        html += '.print-section{min-width:0;}';
        html += '.print-section + .print-section{margin-top:10mm;}';
        html += 'h3{margin:0 0 5px;padding:7px 8px;border:1px solid #9aa8ba;background:#e6f0fb;color:#000;text-align:center;font-size:13px;font-weight:800;line-height:1.2;}';
        html += 'table{width:100%;border-collapse:collapse;table-layout:fixed;margin:0 0 8px;}';
        html += 'th,td{border:1px solid #9aa3b2;padding:4px 6px;vertical-align:top;font-size:9.5px;line-height:1.2;word-break:break-word;}';
        html += 'th{background:#d9e8fa;color:#000;text-align:center;font-weight:800;}';
        html += 'td{text-align:left;}';
        html += 'tfoot th, tfoot td{background:#d9e8fa;font-weight:800;}';
        html += '.summary table{table-layout:auto;}';
        html += '.summary th,.summary td{font-size:11px;padding:4px 7px;}';
        html += '.summary td:not(:first-child),.summary th:not(:first-child){text-align:center;white-space:nowrap;}';
        html += '.recent-grid th,.recent-grid td{font-size:9px;padding:4px 5px;}';
        html += '.recent-grid th:nth-child(1){width:5%;}.recent-grid th:nth-child(2){width:10%;}.recent-grid th:nth-child(3){width:15%;}.recent-grid th:nth-child(4){width:18%;}.recent-grid th:nth-child(5){width:17%;}.recent-grid th:nth-child(6){width:12%;}.recent-grid th:nth-child(7){width:23%;}';
        html += '.recent-grid .print-section:nth-child(2) th:nth-child(1){width:5%;}.recent-grid .print-section:nth-child(2) th:nth-child(2){width:9%;}.recent-grid .print-section:nth-child(2) th:nth-child(3){width:14%;}.recent-grid .print-section:nth-child(2) th:nth-child(4){width:15%;}.recent-grid .print-section:nth-child(2) th:nth-child(5){width:19%;}.recent-grid .print-section:nth-child(2) th:nth-child(6){width:18%;}.recent-grid .print-section:nth-child(2) th:nth-child(7){width:10%;}.recent-grid .print-section:nth-child(2) th:nth-child(8){width:10%;}';
        html += '.text-right{text-align:right;}';
        html += 'a{color:#000;text-decoration:none;}';
        html += '</style></head><body>';
        html += '<h1 class="report-title">' + loanRecentActivityEsc(@json($recentActivityReportTitle)) + '</h1>';
        html += '<div class="summary">' + loanRecentActivityTableFromDom('.lm-payment-method-summary', '') + '</div>';
        html += '<div class="recent-grid">';
        html += '<div class="print-section">' + loanRecentActivityTableFromDataTable('#loan_recent_payments_table', @json($t('Recent Collected Payments', 'ការបង់ប្រាក់ថ្មីៗ'))) + '</div>';
        html += '<div class="print-section">' + loanRecentActivityTableFromDataTable('#loan_recent_loans_table', @json($t('Recent Installments', 'កម្ចីថ្មីៗ'))) + '</div>';
        html += '</div></body></html>';

        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.print();
            return;
        }

        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();

        var returnToSystemPage = function () {
            try {
                if (window && !window.closed) {
                    window.focus();
                }
                if (printWindow && !printWindow.closed) {
                    printWindow.close();
                }
            } catch (e) {
                if (window && !window.closed) {
                    window.focus();
                }
            }
        };

        printWindow.onafterprint = returnToSystemPage;
        window.setTimeout(function () {
            printWindow.print();
            window.setTimeout(returnToSystemPage, 1000);
        }, 500);
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
        html += tableFromDataTable('#loan_recent_loans_table', @json($t('Recent Installments', 'កម្ចីថ្មីៗ')));
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
