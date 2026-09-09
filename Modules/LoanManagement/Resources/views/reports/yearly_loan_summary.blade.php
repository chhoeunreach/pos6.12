@extends('loanmanagement::layouts.app')
@section('title', (session('user.language', config('app.locale')) === 'km') ? 'របាយការណ៍សង្ខេបកម្ចីប្រចាំឆ្នាំ' : 'Yearly Installment Summary')

@php
    $isKhmer = $isKhmer ?? session('user.language', config('app.locale')) === 'km';
    $bi = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
    $visibleTableMetrics = [
        'loan_count', 'principal_total', 'interest_total', 'loan_total',
        'paid_customer_count', 'collection_payment_total', 'deposit_payment_total', 'payment_total',
        'closed_count', 'closed_principal_total', 'closed_interest_total', 'closed_loan_total', 'closed_paid_total', 'closed_balance_total',
        'bad_count', 'bad_principal_total', 'bad_interest_total', 'bad_loan_total', 'bad_paid_total', 'bad_balance_total',
    ];
    $hasMetricValue = fn ($value) => $value !== null && $value !== '' && (float) $value != 0.0;
    $rowHasVisibleData = fn ($row) => collect($visibleTableMetrics)->contains(fn ($field) => $hasMetricValue($row[$field] ?? 0));
    $displayRows = array_values(array_filter($payload['rows'] ?? [], $rowHasVisibleData));
    $rowMoney = fn ($value) => ! $hasMetricValue($value) ? '' : $money($value);
    $rowNumber = fn ($value) => ! $hasMetricValue($value) ? '' : $number($value);
    $yearOptions = range((int) now()->format('Y'), 2000);
    $dateFrom = $filters['date_from'] ?? ($filters['start_year'].'-01-01');
    $dateTo = $filters['date_to'] ?? ($filters['end_year'].'-12-31');
    $dateRangeDisplay = \Carbon\Carbon::parse($dateFrom)->format('m-d-Y').' - '.\Carbon\Carbon::parse($dateTo)->format('m-d-Y');
    $yearlyLoanDetailFilterPayload = [
        'start_year' => $filters['start_year'],
        'end_year' => $filters['end_year'],
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'location_id' => $filters['location_id'],
        'search' => $filters['search'],
    ];
    $chartRows = collect($displayRows)->sortBy('year')->values();
    $chartData = [
        'labels' => $chartRows->pluck('year')->map(fn ($year) => (string) $year)->all(),
        'loanTotals' => $chartRows->pluck('loan_total')->map(fn ($value) => round((float) $value, 2))->all(),
        'paidTotals' => $chartRows->pluck('payment_total')->map(fn ($value) => round((float) $value, 2))->all(),
        'badBalances' => $chartRows->pluck('bad_balance_total')->map(fn ($value) => round((float) $value, 2))->all(),
        'registeredCounts' => $chartRows->pluck('loan_count')->map(fn ($value) => (int) $value)->all(),
        'paidOffCounts' => $chartRows->pluck('closed_count')->map(fn ($value) => (int) $value)->all(),
        'badCounts' => $chartRows->pluck('bad_count')->map(fn ($value) => (int) $value)->all(),
    ];
    $totals = $payload['totals'] ?? [];
    $statusChartData = [
        'labels' => [
            $bi('Active / Open', 'សកម្ម / បើក'),
            $bi('Paid Off', 'បង់ផ្ដាច់'),
            $bi('Bad / Risk', 'ខូច / ហានិភ័យ'),
        ],
        'values' => [
            max(0, (int) ($totals['loan_count'] ?? 0) - (int) ($totals['closed_count'] ?? 0) - (int) ($totals['bad_count'] ?? 0)),
            (int) ($totals['closed_count'] ?? 0),
            (int) ($totals['bad_count'] ?? 0),
        ],
    ];
    $topYears = $chartRows
        ->sortByDesc(fn ($row) => (float) ($row['payment_total'] ?? 0))
        ->take(4)
        ->values();
    $collectionRate = (float) ($totals['loan_total'] ?? 0) > 0
        ? min(100, ((float) ($totals['payment_total'] ?? 0) / (float) ($totals['loan_total'] ?? 0)) * 100)
        : 0;
    $riskRate = (float) ($totals['loan_count'] ?? 0) > 0
        ? (((float) ($totals['bad_count'] ?? 0) / (float) ($totals['loan_count'] ?? 0)) * 100)
        : 0;
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR YEARLY SUMMARY
       ========================================================= */
    .yls-wrap {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #1f2937;
    }
    .yls-page-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        padding: 16px 18px;
        margin-bottom: 16px;
        border: 1px solid #dbe4ef;
        border-radius: 8px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fbfd 58%, #eef8f6 100%);
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
    }
    .yls-page-kicker {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #0f766e;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.2;
        text-transform: uppercase;
        margin-bottom: 7px;
    }
    .yls-page-title {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        line-height: 1.22;
    }
    .yls-page-subtitle {
        display: block;
        max-width: 760px;
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.45;
        margin-top: 5px;
    }
    .yls-page-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* KPI Summary Cards Grid */
    .yls-card-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .yls-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #ffffff;
        padding: 12px 14px;
        display: flex;
        gap: 12px;
        align-items: center;
        min-height: 54px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .03);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .yls-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, .06);
    }
    .yls-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        flex: 0 0 40px;
    }
    .yls-card small {
        display: block;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 2px;
        font-size: 11px;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .yls-card strong {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
        word-break: break-word;
    }
    .yls-tone-teal { color: #0f766e; background: #f0fdfa; border: 1px solid #ccfbf1; }
    .yls-tone-blue { color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; }
    .yls-tone-green { color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; }
    .yls-tone-orange { color: #d97706; background: #fffbeb; border: 1px solid #fde68a; }
    .yls-tone-purple { color: #7c3aed; background: #f5f3ff; border: 1px solid #ddd6fe; }
    .yls-tone-red { color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; }

    .yls-analytics-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(280px, .8fr);
        gap: 14px;
        margin-bottom: 18px;
    }
    .yls-panel {
        border: 1px solid #dbe4ef;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .yls-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 13px 15px;
        border-bottom: 1px solid #eef2f7;
        background: #fbfdff;
    }
    .yls-panel-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.25;
    }
    .yls-panel-title i { color: #0284c7; }
    .yls-panel-note {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .yls-chart-box {
        position: relative;
        min-height: 320px;
        padding: 14px;
    }
    .yls-chart-box canvas {
        width: 100% !important;
        height: 290px !important;
    }
    .yls-side-stack {
        display: grid;
        gap: 14px;
    }
    .yls-mini-chart {
        min-height: 230px;
    }
    .yls-mini-chart canvas {
        height: 205px !important;
    }
    .yls-insight-list {
        display: grid;
        gap: 10px;
        padding: 14px;
    }
    .yls-insight-row {
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #eef2f7;
    }
    .yls-insight-row:last-child { border-bottom: 0; }
    .yls-year-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 50px;
        height: 30px;
        border-radius: 6px;
        background: #ecfeff;
        color: #0e7490;
        font-size: 12px;
        font-weight: 800;
    }
    .yls-insight-label {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .yls-insight-value {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
        margin-top: 2px;
    }
    .yls-insight-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }
    .yls-health-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding: 0 14px 14px;
    }
    .yls-health-item {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 11px;
        background: #f8fafc;
    }
    .yls-health-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .yls-health-value {
        color: #0f172a;
        display: block;
        font-size: 18px;
        font-weight: 800;
        margin-top: 4px;
    }

    /* Ultimate POS DataTables Toolbar Layout */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 14px !important;
        padding: 16px 18px !important;
        background: #fbfdff !important;
        border: 1px solid #e2e8f0 !important;
        border-bottom: 0 !important;
        border-radius: 8px 8px 0 0 !important;
    }
    .lm-dt-length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 10px !important;
        margin: 0 !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        color: #475569 !important;
    }
    .lm-dt-length select {
        height: 38px !important;
        padding: 4px 32px 4px 12px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 13px !important;
        color: #1e293b !important;
        background-color: #fff !important;
        outline: none !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04) !important;
    }
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        flex-wrap: wrap !important;
    }
    .lm-dt-buttons .btn {
        border-radius: 6px !important;
        padding: 8px 13px !important;
        font-size: 12.5px !important;
        font-weight: 700 !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        line-height: 1.25 !important;
    }
    .lm-dt-buttons .btn:hover {
        background: #f8fafc !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .lm-dt-buttons .btn i {
        margin-right: 4px;
    }
    .lm-dt-search label {
        margin: 0 !important;
    }
    .lm-dt-search input {
        height: 38px !important;
        min-width: 260px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 8px 14px !important;
        font-size: 13px !important;
        outline: none !important;
        background: #ffffff !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04) !important;
    }
    .lm-dt-search input:focus,
    .lm-dt-length select:focus {
        border-color: #0284c7 !important;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, .12) !important;
    }
    .lm-dt-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 14px !important;
        padding: 15px 18px !important;
        background: #fbfdff !important;
        border: 1px solid #e2e8f0 !important;
        border-top: 0 !important;
        border-radius: 0 0 8px 8px !important;
    }
    .lm-dt-info {
        font-size: 13px !important;
        color: #64748b !important;
        padding: 0 !important;
        font-weight: 700 !important;
    }
    .lm-dt-pagination .pagination {
        margin: 0 !important;
        display: inline-flex !important;
        gap: 4px !important;
    }
    .lm-dt-pagination .pagination > li > a {
        min-width: 34px !important;
        min-height: 34px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 6px !important;
        margin: 0 !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
        font-weight: 700 !important;
        padding: 7px 10px !important;
    }
    .lm-dt-pagination .pagination > .active > a {
        background-color: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1.5fr auto;
        gap: 12px 14px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1200px) {
        .yls-card-grid { grid-template-columns: repeat(3, minmax(140px, 1fr)); }
        .yls-analytics-grid { grid-template-columns: 1fr; }
        .lm-pos-filter-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .yls-page-head { padding: 14px; }
        .yls-page-title { font-size: 20px; }
        .yls-page-actions { width: 100%; }
        .yls-page-actions .btn { flex: 1 1 130px; }
        .yls-card-grid { grid-template-columns: 1fr; }
        .yls-chart-box { min-height: 260px; padding: 10px; }
        .yls-chart-box canvas { height: 235px !important; }
        .yls-health-grid { grid-template-columns: 1fr; }
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

    /* Summary Table Styling */
    .yls-data-shell {
        border: 1px solid #e2e8f0;
        border-radius: 0;
        margin: 0;
        background: #ffffff;
    }
    .dataTables_wrapper .yls-data-shell {
        border-top: 0;
        border-bottom: 0;
    }
    .yls-table {
        width: 100%;
        min-width: 1320px;
        margin-bottom: 0;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
    }
    .yls-col-index { width: 42px; }
    .yls-col-year { width: 64px; }
    .yls-col-count { width: 54px; }
    .yls-col-money { width: 60px; }
    .yls-col-total { width: 66px; }
    .yls-col-balance { width: 68px; }
    .yls-table thead th {
        text-align: center;
        vertical-align: middle !important;
        white-space: normal;
        font-size: 10.5px;
        line-height: 1.2;
        border-color: #dbe3ef !important;
        padding: 7px 5px !important;
        font-weight: 800;
        text-transform: none;
    }
    .yls-table tbody td,
    .yls-table tfoot th {
        vertical-align: middle !important;
        white-space: nowrap;
        font-size: 10.5px;
        line-height: 1.25;
        padding: 8px 5px !important;
        border-color: #e6edf5 !important;
    }
    .yls-table th:first-child,
    .yls-table td:first-child { width: 42px; }
    .yls-table th:nth-child(2),
    .yls-table td:nth-child(2) { width: 64px; }
    .yls-table th:nth-child(3),
    .yls-table th:nth-child(7),
    .yls-table th:nth-child(11),
    .yls-table th:nth-child(17),
    .yls-table td:nth-child(3),
    .yls-table td:nth-child(7),
    .yls-table td:nth-child(11),
    .yls-table td:nth-child(17) { width: 54px; }
    .yls-table th:nth-child(4),
    .yls-table th:nth-child(5),
    .yls-table th:nth-child(6),
    .yls-table th:nth-child(8),
    .yls-table th:nth-child(9),
    .yls-table th:nth-child(10),
    .yls-table th:nth-child(n+12),
    .yls-table td:nth-child(4),
    .yls-table td:nth-child(5),
    .yls-table td:nth-child(6),
    .yls-table td:nth-child(8),
    .yls-table td:nth-child(9),
    .yls-table td:nth-child(10),
    .yls-table td:nth-child(n+12) { width: 60px; }
    .yls-table th:nth-child(6),
    .yls-table th:nth-child(10),
    .yls-table th:nth-child(14),
    .yls-table th:nth-child(20),
    .yls-table td:nth-child(6),
    .yls-table td:nth-child(10),
    .yls-table td:nth-child(14),
    .yls-table td:nth-child(20) { width: 66px; }
    .yls-table th:nth-child(16),
    .yls-table th:nth-child(22),
    .yls-table td:nth-child(16),
    .yls-table td:nth-child(22) { width: 68px; }
    .yls-table thead tr:first-child th {
        color: #fff;
        height: 34px;
        border-bottom: 0 !important;
        box-shadow: inset 0 -2px 0 rgba(255, 255, 255, .35);
    }
    .yls-table thead tr:nth-child(2) th {
        height: 32px;
        color: #334155;
        background-clip: padding-box;
    }
    .yls-table thead tr:nth-child(2) th {
        font-size: 9.5px;
        letter-spacing: 0;
        text-transform: uppercase;
    }
    .yls-group-label { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
    .yls-group-loans { background: #0f766e; color: #fff; }
    .yls-group-payments { background: #15803d; color: #fff; }
    .yls-group-closed { background: #c2410c; color: #fff; }
    .yls-group-risk { background: #dc2626; color: #fff; }
    .yls-table thead tr:nth-child(2) th:nth-child(n+1):nth-child(-n+4),
    .yls-table tbody td:nth-child(n+3):nth-child(-n+6),
    .yls-table tfoot th:nth-child(n+2):nth-child(-n+5) { background: #f0fdfa; }
    .yls-table thead tr:nth-child(2) th:nth-child(n+5):nth-child(-n+8),
    .yls-table tbody td:nth-child(n+7):nth-child(-n+10),
    .yls-table tfoot th:nth-child(n+6):nth-child(-n+9) { background: #f0fdf4; }
    .yls-table thead tr:nth-child(2) th:nth-child(n+9):nth-child(-n+14),
    .yls-table tbody td:nth-child(n+11):nth-child(-n+16),
    .yls-table tfoot th:nth-child(n+10):nth-child(-n+15) { background: #fff7ed; }
    .yls-table thead tr:nth-child(2) th:nth-child(n+15):nth-child(-n+20),
    .yls-table tbody td:nth-child(n+17):nth-child(-n+22),
    .yls-table tfoot th:nth-child(n+16):nth-child(-n+21) { background: #fef2f2; }
    .yls-table thead tr:nth-child(2) th:nth-child(1),
    .yls-table tbody td:nth-child(3),
    .yls-table tfoot th:nth-child(3),
    .yls-table thead tr:nth-child(2) th:nth-child(5),
    .yls-table tbody td:nth-child(7),
    .yls-table tfoot th:nth-child(7),
    .yls-table thead tr:nth-child(2) th:nth-child(9),
    .yls-table tbody td:nth-child(11),
    .yls-table tfoot th:nth-child(11),
    .yls-table thead tr:nth-child(2) th:nth-child(15),
    .yls-table tbody td:nth-child(17),
    .yls-table tfoot th:nth-child(17) {
        border-left-width: 1px !important;
        border-left-color: #94a3b8 !important;
    }
    .yls-table tbody td:first-child,
    .yls-table tbody td:nth-child(2) {
        background: #ffffff !important;
    }
    .yls-table tbody td:first-child {
        color: #64748b;
        font-weight: 800;
    }
    .yls-table tbody td:nth-child(2) strong {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        padding: 2px 6px;
        border-radius: 5px;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        font-size: 11px;
        font-weight: 800;
    }
    .yls-table tbody td[data-group],
    .yls-table tfoot th.text-right {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0;
    }
    .yls-table tbody td[data-group] {
        color: #334155;
        font-weight: 700;
    }
    .yls-table tbody td:nth-child(3),
    .yls-table tbody td:nth-child(7),
    .yls-table tbody td:nth-child(11),
    .yls-table tbody td:nth-child(17) {
        color: #0f172a;
        font-weight: 800;
    }
    .yls-table tbody td:nth-child(6),
    .yls-table tbody td:nth-child(10),
    .yls-table tbody td:nth-child(14),
    .yls-table tbody td:nth-child(20) {
        color: #0f172a;
        font-weight: 800;
    }
    .yls-table tbody td:nth-child(16),
    .yls-table tbody td:nth-child(22) {
        color: #b45309;
        font-weight: 800;
    }
    .yls-table tbody td:empty::after {
        content: '-';
        color: #cbd5e1;
        font-weight: 700;
    }
    .yls-table tbody tr:nth-child(even) td { filter: saturate(.96) brightness(.995); }
    .yls-table tbody tr:hover td { filter: brightness(.97); }
    .yls-table tbody tr { cursor: pointer; }
    .yls-table tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 #0284c7; }
    .yls-total-row th {
        background: #e2e8f0 !important;
        color: #0f172a;
        font-weight: 800;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
    }
    .yls-generated { color: #94a3b8; font-size: 11px; padding: 12px 4px 0; text-align: right; }

    /* Modal Styling */
    .yls-loan-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none;
        background: rgba(15, 23, 42, .62);
        padding: 18px;
    }
    .yls-loan-modal.is-open { display: flex; }
    .yls-loan-modal-dialog {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
    }
    .yls-loan-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 14px;
        border-bottom: 1px solid #dbe4ef;
        background: #f8fafc;
    }
    .yls-loan-modal-title {
        color: #0f172a;
        font-size: 15px;
        font-weight: 800;
    }
    .yls-loan-modal-close {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #334155;
        height: 30px;
        padding: 0 12px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .yls-loan-modal-close:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .yls-loan-modal iframe {
        width: 100%;
        height: 100%;
        border: 0;
        background: #eef3f8;
    }
</style>
@endsection

@section('content_body')
<div class="yls-wrap">

    {{-- Content Header --}}
    <section class="content-header yls-page-head">
        <div>
            <div class="yls-page-kicker">
                <i class="fa fa-line-chart"></i> {{ $bi('Annual Performance', 'លទ្ធផលប្រចាំឆ្នាំ') }}
            </div>
            <h1 class="yls-page-title">
                {{ $bi('Yearly Installment Summary', 'របាយការណ៍សង្ខេបកម្ចីប្រចាំឆ្នាំ') }}
                <small class="yls-page-subtitle">
                    {{ $bi('Annual loan, schedule, collection, deposit, and overdue totals', 'សរុបកម្ចី កាលវិភាគ ការប្រមូលប្រាក់ ប្រាក់កក់ និងហួសកំណត់ប្រចាំឆ្នាំ') }}
                </small>
            </h1>
        </div>
        <div class="yls-page-actions">
            <a class="btn btn-success btn-sm" style="font-weight: 600; border-radius: 6px;"
               href="{{ route('loan-management.reports.yearly-loan-summary', array_merge(request()->query(), ['export' => 'csv'])) }}">
                <i class="fa fa-file-excel-o"></i> {{ $bi('Export Excel', 'នាំចេញ Excel') }}
            </a>
            <button type="button" class="btn btn-default btn-sm" onclick="window.print()" style="font-weight: 600; border-radius: 6px;">
                <i class="fa fa-print"></i> {{ $bi('Print', 'បោះពុម្ព') }}
            </button>
        </div>
    </section>

    {{-- KPI Cards --}}
    <div class="yls-card-grid">
        @foreach($payload['cards'] as $card)
            <div class="yls-card">
                <div class="yls-card-icon yls-tone-{{ $card['tone'] }}">
                    <i class="{{ $card['icon'] }}"></i>
                </div>
                <div>
                    <small>{{ $card['label'] }}</small>
                    <strong>{{ $card['value'] }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Analytics Charts --}}
    <div class="yls-analytics-grid">
        <div class="yls-panel">
            <div class="yls-panel-head">
                <h3 class="yls-panel-title"><i class="fa fa-area-chart"></i> {{ $bi('Yearly Value Trend', 'និន្នាការតម្លៃប្រចាំឆ្នាំ') }}</h3>
                <span class="yls-panel-note">{{ $dateRangeDisplay }}</span>
            </div>
            <div class="yls-chart-box">
                <canvas id="ylsTrendChart"></canvas>
            </div>
        </div>
        <div class="yls-side-stack">
            <div class="yls-panel">
                <div class="yls-panel-head">
                    <h3 class="yls-panel-title"><i class="fa fa-pie-chart"></i> {{ $bi('Portfolio Mix', 'សមាសភាពកម្ចី') }}</h3>
                </div>
                <div class="yls-chart-box yls-mini-chart">
                    <canvas id="ylsStatusChart"></canvas>
                </div>
                <div class="yls-health-grid">
                    <div class="yls-health-item">
                        <span class="yls-health-label">{{ $bi('Collection Rate', 'អត្រាប្រមូល') }}</span>
                        <strong class="yls-health-value">{{ number_format($collectionRate, 1) }}%</strong>
                    </div>
                    <div class="yls-health-item">
                        <span class="yls-health-label">{{ $bi('Risk Rate', 'អត្រាហានិភ័យ') }}</span>
                        <strong class="yls-health-value">{{ number_format($riskRate, 1) }}%</strong>
                    </div>
                </div>
            </div>
            <div class="yls-panel">
                <div class="yls-panel-head">
                    <h3 class="yls-panel-title"><i class="fa fa-trophy"></i> {{ $bi('Top Collection Years', 'ឆ្នាំប្រមូលប្រាក់ខ្ពស់') }}</h3>
                </div>
                <div class="yls-insight-list">
                    @forelse($topYears as $row)
                        <div class="yls-insight-row">
                            <span class="yls-year-pill">{{ $row['year'] }}</span>
                            <span>
                                <span class="yls-insight-label">{{ $bi('Collected', 'បានប្រមូល') }}</span>
                                <strong class="yls-insight-value">{{ $money($row['payment_total'] ?? 0) }}</strong>
                            </span>
                            <span class="yls-insight-meta">{{ $number($row['loan_count'] ?? 0) }} {{ $bi('loans', 'កម្ចី') }}</span>
                        </div>
                    @empty
                        <div class="yls-insight-row">
                            <span class="yls-year-pill">-</span>
                            <span>
                                <span class="yls-insight-label">{{ $bi('Collected', 'បានប្រមូល') }}</span>
                                <strong class="yls-insight-value">{{ $bi('No yearly data', 'មិនមានទិន្នន័យប្រចាំឆ្នាំ') }}</strong>
                            </span>
                            <span class="yls-insight-meta">0</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Ultimate POS Collapsible Filters Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.reports.yearly-loan-summary') }}" id="ylsFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Date Range --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $bi('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                    <input type="text" name="date_range" id="ylsDateRange" class="form-control" value="{{ $dateRangeDisplay }}" placeholder="{{ $bi('Select date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" autocomplete="off">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                </div>

                {{-- Location --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $bi('Location', 'សាខា') }}</label>
                    <select name="location_id" class="form-control">
                        <option value="">{{ $bi('All Locations', 'សាខាទាំងអស់') }}</option>
                        @foreach($locations as $id => $name)
                            <option value="{{ $id }}" {{ (string) $filters['location_id'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $bi('Search', 'ស្វែងរក') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $bi('Installment, customer, phone', 'កម្ចី អតិថិជន ទូរស័ព្ទ') }}">
                </div>

                {{-- Actions --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $bi('Filter', 'ចម្រោះ') }}
                    </button>
                    <a href="{{ route('loan-management.reports.yearly-loan-summary') }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $bi('Reset', 'កំណត់ឡើងវិញ') }}
                    </a>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $bi('Yearly Installment Summary Data', 'ទិន្នន័យសង្ខេបកម្ចីប្រចាំឆ្នាំ')])
        <div class="table-responsive yls-data-shell">
            <table class="table table-bordered table-hover yls-table" id="yearlyLoanSummaryTable">
                <colgroup>
                    <col class="yls-col-index">
                    <col class="yls-col-year">
                    <col class="yls-col-count">
                    <col class="yls-col-money">
                    <col class="yls-col-money">
                    <col class="yls-col-money yls-col-total">
                    <col class="yls-col-count">
                    <col class="yls-col-money">
                    <col class="yls-col-money">
                    <col class="yls-col-money yls-col-total">
                    <col class="yls-col-count">
                    <col class="yls-col-money">
                    <col class="yls-col-money">
                    <col class="yls-col-money yls-col-total">
                    <col class="yls-col-money">
                    <col class="yls-col-money yls-col-balance">
                    <col class="yls-col-count">
                    <col class="yls-col-money">
                    <col class="yls-col-money">
                    <col class="yls-col-money yls-col-total">
                    <col class="yls-col-money">
                    <col class="yls-col-money yls-col-balance">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2">{{ $bi('No.', 'ល.រ') }}</th>
                        <th rowspan="2">{{ $bi('Year', 'ឆ្នាំ') }}</th>
                        <th colspan="4" class="yls-group-loans" title="{{ $bi('Registered Installment Customers', 'អតិថិជនចុះឈ្មោះរំលស់') }}"><i class="fa fa-users"></i> <span class="yls-group-label">{{ $bi('Registered', 'ចុះឈ្មោះ') }}</span></th>
                        <th colspan="4" class="yls-group-payments" title="{{ $bi('Installment Customers Paid Total', 'អតិថិជនរំលស់បានបង់ទូរទៅ') }}"><i class="fa fa-money"></i> <span class="yls-group-label">{{ $bi('Paid Total', 'បានបង់សរុប') }}</span></th>
                        <th colspan="6" class="yls-group-closed" title="{{ $bi('Paid Off Installment Customers', 'អតិថិជនរំលស់បានបង់ផ្ដាច់') }}"><i class="fa fa-check-circle-o"></i> <span class="yls-group-label">{{ $bi('Paid Off', 'បង់ផ្ដាច់') }}</span></th>
                        <th colspan="6" class="yls-group-risk" title="{{ $bi('Bad Installment Customers', 'អតិថិជនរំលស់ខូច') }}"><i class="fa fa-warning"></i> <span class="yls-group-label">{{ $bi('Bad / Risk', 'ខូច / ហានិភ័យ') }}</span></th>
                    </tr>
                    <tr>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $bi('Interest', 'ការប្រាក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Collection', 'ប្រមូលប្រាក់') }}</th>
                        <th>{{ $bi('Deposit', 'ប្រាក់កក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $bi('Interest', 'ការប្រាក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Paid', 'បានបង់') }}</th>
                        <th>{{ $bi('Balance', 'សមតុល្យ') }}</th>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $bi('Interest', 'ការប្រាក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Paid', 'បានបង់') }}</th>
                        <th>{{ $bi('Balance', 'សមតុល្យ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($displayRows as $row)
                        <tr data-loan-detail-year="{{ $row['year'] }}" title="{{ $bi('Click to view loan details', 'ចុចដើម្បីមើលព័ត៌មានលម្អិតកម្ចី') }}">
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="text-center"><strong>{{ $row['year'] }}</strong></td>
                            <td class="text-right" data-group="registered">{{ $rowNumber($row['loan_count']) }}</td>
                            <td class="text-right" data-group="registered">{{ $rowMoney($row['principal_total']) }}</td>
                            <td class="text-right" data-group="registered">{{ $rowMoney($row['interest_total']) }}</td>
                            <td class="text-right" data-group="registered">{{ $rowMoney($row['loan_total']) }}</td>
                            <td class="text-right" data-group="generalPaid">{{ $rowNumber($row['paid_customer_count']) }}</td>
                            <td class="text-right" data-group="generalPaid">{{ $rowMoney($row['collection_payment_total']) }}</td>
                            <td class="text-right" data-group="generalPaid">{{ $rowMoney($row['deposit_payment_total']) }}</td>
                            <td class="text-right" data-group="generalPaid">{{ $rowMoney($row['payment_total']) }}</td>
                            <td class="text-right" data-group="paidOff">{{ $rowNumber($row['closed_count']) }}</td>
                            <td class="text-right" data-group="paidOff">{{ $rowMoney($row['closed_principal_total']) }}</td>
                            <td class="text-right" data-group="paidOff">{{ $rowMoney($row['closed_interest_total']) }}</td>
                            <td class="text-right" data-group="paidOff">{{ $rowMoney($row['closed_loan_total']) }}</td>
                            <td class="text-right" data-group="paidOff">{{ $rowMoney($row['closed_paid_total']) }}</td>
                            <td class="text-right" data-group="paidOff">{{ $rowMoney($row['closed_balance_total']) }}</td>
                            <td class="text-right" data-group="badDebt">{{ $rowNumber($row['bad_count']) }}</td>
                            <td class="text-right" data-group="badDebt">{{ $rowMoney($row['bad_principal_total']) }}</td>
                            <td class="text-right" data-group="badDebt">{{ $rowMoney($row['bad_interest_total']) }}</td>
                            <td class="text-right" data-group="badDebt">{{ $rowMoney($row['bad_loan_total']) }}</td>
                            <td class="text-right" data-group="badDebt">{{ $rowMoney($row['bad_paid_total']) }}</td>
                            <td class="text-right" data-group="badDebt">{{ $rowMoney($row['bad_balance_total']) }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
                <tfoot>
                    @php($total = $payload['totals'] ?? [])
                    <tr class="yls-total-row">
                        <th colspan="2" class="text-center">{{ $bi('Total', 'សរុប') }}</th>
                        <th class="text-right">{{ $number($total['loan_count'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['principal_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['interest_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['loan_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $number($total['paid_customer_count'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['collection_payment_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['deposit_payment_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['payment_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $number($total['closed_count'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['closed_principal_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['closed_interest_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['closed_loan_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['closed_paid_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['closed_balance_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $number($total['bad_count'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['bad_principal_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['bad_interest_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['bad_loan_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['bad_paid_total'] ?? 0) }}</th>
                        <th class="text-right">{{ $money($total['bad_balance_total'] ?? 0) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="yls-generated">
            <i class="fa fa-clock-o"></i> {{ $bi('Generated', 'បានបង្កើត') }} {{ now()->format('Y-m-d H:i') }}
        </div>
    @endcomponent

</div>

<div class="yls-loan-modal" id="ylsLoanModal" aria-hidden="true">
    <div class="yls-loan-modal-dialog">
        <div class="yls-loan-modal-head">
            <div class="yls-loan-modal-title" id="ylsLoanModalTitle">{{ $bi('Installment Details', 'ព័ត៌មានលម្អិតកម្ចី') }}</div>
            <button type="button" class="yls-loan-modal-close" id="ylsLoanModalClose">{{ $bi('Close', 'បិទ') }}</button>
        </div>
        <iframe id="ylsLoanModalFrame" title="{{ $bi('Installment Details', 'ព័ត៌មានលម្អិតកម្ចី') }}"></iframe>
    </div>
</div>
@endsection

@section('loan_js')
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script>
    (function ($) {
        var $form = $('#ylsFilterForm');
        $form.on('change', 'select', function () {
            $form.trigger('submit');
        });

        var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
        var dateRangeSettings = window.dateRangeSettings ? $.extend(true, {}, window.dateRangeSettings) : {};

        if (window.moment && $.fn.daterangepicker) {
            var startDate = moment(@json($dateFrom));
            var endDate = moment(@json($dateTo));
            var fyStart = (typeof financial_year !== 'undefined' && financial_year.start && moment(financial_year.start).isValid())
                ? moment(financial_year.start)
                : moment().startOf('year');
            var fyEnd = (typeof financial_year !== 'undefined' && financial_year.end && moment(financial_year.end).isValid())
                ? moment(financial_year.end)
                : moment().endOf('year');

            $('#ylsDateRange').daterangepicker($.extend(true, {}, dateRangeSettings, {
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
                    'This month last year': [moment().subtract(1, 'year').startOf('month'), moment().subtract(1, 'year').endOf('month')],
                    'This Year': [moment().startOf('year'), moment().endOf('year')],
                    'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                    'Current financial year': [fyStart.clone(), fyEnd.clone()],
                    'Last financial year': [fyStart.clone().subtract(1, 'year'), fyEnd.clone().subtract(1, 'year')]
                },
                locale: $.extend(true, {}, dateRangeSettings.locale || {}, {
                    format: displayDateFormat,
                    separator: ' - ',
                    applyLabel: @json($bi('Apply', 'អនុវត្ត')),
                    cancelLabel: @json($bi('Clear', 'សម្អាត')),
                    customRangeLabel: @json($bi('Custom Range', 'ជ្រើសរើសផ្ទាល់')),
                    toLabel: '~'
                })
            }), function (start, end) {
                $('#ylsDateRange').val(start.format(displayDateFormat) + ' - ' + end.format(displayDateFormat));
                $form.find('[name="date_from"]').val(start.format('YYYY-MM-DD'));
                $form.find('[name="date_to"]').val(end.format('YYYY-MM-DD'));
                $form.trigger('submit');
            });

            $('#ylsDateRange')
                .on('apply.daterangepicker', function (event, picker) {
                    $(this).val(picker.startDate.format(displayDateFormat) + ' - ' + picker.endDate.format(displayDateFormat));
                    $form.find('[name="date_from"]').val(picker.startDate.format('YYYY-MM-DD'));
                    $form.find('[name="date_to"]').val(picker.endDate.format('YYYY-MM-DD'));
                    $form.trigger('submit');
                })
                .on('cancel.daterangepicker', function () {
                    $(this).val('');
                    $form.find('[name="date_from"], [name="date_to"]').val('');
                    $form.trigger('submit');
                });
        } else {
            $('#ylsDateRange').prop('readonly', false).on('change', function () {
                var parts = String($(this).val()).split(/\s+-\s+|\s+~\s+/);
                if (parts.length === 2) {
                    $form.find('[name="date_from"]').val(parts[0]);
                    $form.find('[name="date_to"]').val(parts[1]);
                }
            });
        }

        var detailUrl = @json(route('loan-management.admin-loan.details'));
        var filters = @json($yearlyLoanDetailFilterPayload);
        var chartData = @json($chartData);
        var statusChartData = @json($statusChartData);
        var labels = {
            all: @json($bi('All Installments', 'កម្ចីទាំងអស់')),
            registered: @json($bi('Registered Installments', 'អតិថិជនចុះឈ្មោះរំលស់')),
            generalPaid: @json($bi('General Installments Paid', 'អតិថិជនរំលស់បានបង់ទូរទៅ')),
            paidOff: @json($bi('Paid Off', 'បង់ផ្ដាច់')),
            badDebt: @json($bi('Bad / Risk', 'ខូច / ហានិភ័យ'))
        };

        function groupForCell(index) {
            if (index >= 2 && index <= 5) return 'registered';
            if (index >= 6 && index <= 9) return 'generalPaid';
            if (index >= 10 && index <= 15) return 'paidOff';
            if (index >= 16 && index <= 21) return 'badDebt';
            return 'all';
        }

        function shortMoney(value) {
            var numberValue = Number(value || 0);
            if (Math.abs(numberValue) >= 1000000) return '$' + (numberValue / 1000000).toFixed(1) + 'M';
            if (Math.abs(numberValue) >= 1000) return '$' + (numberValue / 1000).toFixed(1) + 'K';
            return '$' + numberValue.toLocaleString(undefined, { maximumFractionDigits: 0 });
        }

        function initYearlyCharts() {
            if (!window.Chart || !chartData.labels || !chartData.labels.length) {
                return;
            }

            Chart.defaults.font.family = "'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
            Chart.defaults.color = '#64748b';

            var trendCanvas = document.getElementById('ylsTrendChart');
            if (trendCanvas) {
                var trendGradient = trendCanvas.getContext('2d').createLinearGradient(0, 0, 0, 290);
                trendGradient.addColorStop(0, 'rgba(2, 132, 199, .22)');
                trendGradient.addColorStop(1, 'rgba(2, 132, 199, .02)');

                new Chart(trendCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: @json($bi('Registered Total', 'សរុបចុះឈ្មោះ')),
                                data: chartData.loanTotals,
                                backgroundColor: trendGradient,
                                borderColor: '#0284c7',
                                borderWidth: 1,
                                borderRadius: 5,
                                maxBarThickness: 42
                            },
                            {
                                type: 'line',
                                label: @json($bi('Paid Total', 'បានបង់សរុប')),
                                data: chartData.paidTotals,
                                borderColor: '#16a34a',
                                backgroundColor: 'rgba(22, 163, 74, .12)',
                                borderWidth: 3,
                                tension: .35,
                                fill: false,
                                pointRadius: 3,
                                pointHoverRadius: 5
                            },
                            {
                                type: 'line',
                                label: @json($bi('Bad Balance', 'សមតុល្យខូច')),
                                data: chartData.badBalances,
                                borderColor: '#dc2626',
                                backgroundColor: 'rgba(220, 38, 38, .10)',
                                borderDash: [6, 4],
                                borderWidth: 2,
                                tension: .35,
                                fill: false,
                                pointRadius: 2,
                                pointHoverRadius: 5
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, boxWidth: 8, padding: 18 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + shortMoney(context.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { weight: '700' } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#eef2f7' },
                                ticks: {
                                    callback: function (value) { return shortMoney(value); }
                                }
                            }
                        }
                    }
                });
            }

            var statusCanvas = document.getElementById('ylsStatusChart');
            if (statusCanvas) {
                new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: statusChartData.labels,
                        datasets: [{
                            data: statusChartData.values,
                            backgroundColor: ['#0f766e', '#d97706', '#dc2626'],
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, boxWidth: 8, padding: 12 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        var total = context.dataset.data.reduce(function (sum, value) {
                                            return sum + Number(value || 0);
                                        }, 0);
                                        var percent = total > 0 ? ((Number(context.parsed || 0) / total) * 100).toFixed(1) : '0.0';
                                        return context.label + ': ' + Number(context.parsed || 0).toLocaleString() + ' (' + percent + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        function openLoanModal(year, group) {
            var params = new URLSearchParams();
            params.set('year', year);
            params.set('group', group);
            Object.keys(filters).forEach(function (key) {
                if (filters[key] !== null && filters[key] !== undefined && String(filters[key]) !== '') {
                    params.set(key, filters[key]);
                }
            });

            $('#ylsLoanModalTitle').text((labels[group] || labels.all) + ' - ' + year);
            $('#ylsLoanModalFrame').attr('src', detailUrl + '?' + params.toString());
            $('#ylsLoanModal').addClass('is-open').attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
        }

        function closeLoanModal() {
            $('#ylsLoanModal').removeClass('is-open').attr('aria-hidden', 'true');
            $('#ylsLoanModalFrame').attr('src', 'about:blank');
            $('body').css('overflow', '');
        }

        $('#yearlyLoanSummaryTable tbody').on('click', 'td', function (event) {
            if ($(event.target).closest('a, button, input, select, textarea').length) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            var $cell = $(this);
            var $row = $cell.closest('tr');
            var year = $row.data('loan-detail-year');
            if (!year) {
                return;
            }
            var group = $cell.data('group') || groupForCell(this.cellIndex);
            openLoanModal(year, group);
        });

        $('#ylsLoanModalClose').on('click', closeLoanModal);
        $('#ylsLoanModal').on('click', function (event) {
            if (event.target === this) {
                closeLoanModal();
            }
        });
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closeLoanModal();
            }
        });

        initYearlyCharts();

        // Initialize Ultimate POS Standard DataTables
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#yearlyLoanSummaryTable')) {
            var tableButtons = [];
            if ($.fn.dataTable.Buttons) {
                tableButtons = [
                    {
                        extend: 'copy',
                        text: '<i class="fa fa-copy"></i> ' + @json($bi('Copy', 'ចម្លង')),
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fa fa-file-text-o"></i> ' + @json($bi('Export CSV', 'នាំចេញ CSV')),
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel-o"></i> ' + @json($bi('Export Excel', 'នាំចេញ Excel')),
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print"></i> ' + @json($bi('Print', 'បោះពុម្ព')),
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible', stripHtml: true }
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fa fa-columns"></i> ' + @json($bi('Column Visibility', 'បង្ហាញជួរឈរ')),
                        className: 'btn btn-default btn-sm'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fa fa-file-pdf-o"></i> ' + @json($bi('Export PDF', 'នាំចេញ PDF')),
                        className: 'btn btn-default btn-sm',
                        orientation: 'landscape',
                        pageSize: 'A3',
                        exportOptions: { columns: ':visible' }
                    }
                ];
            }

            $('#yearlyLoanSummaryTable').DataTable({
                dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
                buttons: tableButtons,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, @json($bi('All', 'ទាំងអស់'))]],
                order: [],
                autoWidth: false,
                language: {
                    search: '',
                    searchPlaceholder: @json($bi('Search in summary...', 'ស្វែងរកក្នុងតារាង...')),
                    lengthMenu: @json($bi('Show _MENU_ entries', 'បង្ហាញ _MENU_ ធាតុ')),
                    emptyTable: @json($bi('No data found for this date range.', 'រកមិនឃើញទិន្នន័យសម្រាប់ចន្លោះថ្ងៃនេះទេ។')),
                    info: @json($bi('Showing _START_ to _END_ of _TOTAL_ entries', 'បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ')),
                    infoEmpty: @json($bi('Showing 0 to 0 of 0 entries', 'បង្ហាញ 0 នៃ 0 ធាតុ')),
                    infoFiltered: @json($bi('(filtered from _MAX_ total entries)', '(ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប)')),
                    paginate: {
                        first: @json($bi('First', 'ដំបូង')),
                        last: @json($bi('Last', 'ចុងក្រោយ')),
                        next: @json($bi('Next', 'បន្ទាប់')),
                        previous: @json($bi('Previous', 'មុន'))
                    }
                },
                columnDefs: [
                    { targets: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21], className: 'text-right' }
                ]
            });
        }
    })(jQuery);
</script>
@endsection
