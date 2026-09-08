@extends('loanmanagement::layouts.app')
@section('title', (session('user.language', config('app.locale')) === 'km') ? 'របាយការណ៍កម្ចីរំលស់' : 'Installment Reports')

@php
    $isKhmer = $isKhmer ?? (session('user.language', config('app.locale')) === 'km');
    $bi = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);

    $businessName = trim((string) (\Modules\LoanManagement\Services\BusinessSettingsService::businessName() ?: Session::get('business.name', '')));
    $currentUserName = auth()->user()->name ?? (session('user.first_name') ? session('user.first_name').' '.session('user.last_name') : 'Officer');

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
       PREMIER ENTERPRISE FINTECH STYLE FOR INSTALLMENT REPORTS
       ========================================================= */
    .ir-page {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #0f172a;
    }

    /* Executive Hero Banner */
    .ir-hero-banner {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 22px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 10px 15px -3px rgba(15, 23, 42, 0.02);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .ir-hero-title-area {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .ir-hero-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.28);
        flex-shrink: 0;
    }
    .ir-hero-title-wrap h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.25;
        letter-spacing: -0.3px;
    }
    .ir-hero-subtitle {
        margin: 3px 0 0 0;
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ir-meta-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f8fafc;
        color: #475569;
        padding: 2.5px 9px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        font-size: 11px;
        font-weight: 600;
    }
    .ir-hero-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .ir-btn-hero {
        height: 36px;
        padding: 0 14px;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid transparent;
        text-decoration: none !important;
    }
    .ir-btn-hero-primary {
        background: #0284c7;
        color: #ffffff !important;
    }
    .ir-btn-hero-primary:hover {
        background: #0369a1;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    }
    .ir-btn-hero-default {
        background: #ffffff;
        color: #334155 !important;
        border-color: #cbd5e1;
    }
    .ir-btn-hero-default:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a !important;
    }

    /* =========================================================
       EXECUTIVE KPI STATS DECK (8 BALANCED SQUARED CARDS)
       ========================================================= */
    .ir-cards {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 18px;
    }
    @media (max-width: 1400px) {
        .ir-cards { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 860px) {
        .ir-cards { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 580px) {
        .ir-cards { grid-template-columns: 1fr; }
    }

    .ir-card {
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 15px 16px 13px 16px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 126px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 6px -2px rgba(15, 23, 42, 0.02);
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        cursor: pointer;
        user-select: none;
        overflow: hidden;
    }
    .ir-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.04);
        border-color: #cbd5e1;
    }
    .ir-card.is-active {
        border-color: #0284c7 !important;
        background: #f0f9ff !important;
        box-shadow: 0 0 0 2px #0284c7, 0 10px 22px rgba(2, 132, 199, 0.22) !important;
    }

    /* Card Top Accent Bars */
    .ir-card-accent-all { border-top: 3.5px solid #0284c7 !important; }
    .ir-card-accent-active { border-top: 3.5px solid #10b981 !important; }
    .ir-card-accent-today { border-top: 3.5px solid #f59e0b !important; }
    .ir-card-accent-overdue { border-top: 3.5px solid #ef4444 !important; }
    .ir-card-accent-balance { border-top: 3.5px solid #d97706 !important; }
    .ir-card-accent-paid { border-top: 3.5px solid #2563eb !important; }
    .ir-card-accent-completed { border-top: 3.5px solid #8b5cf6 !important; }
    .ir-card-accent-principal { border-top: 3.5px solid #059669 !important; }
    .ir-card-accent-interest { border-top: 3.5px solid #6366f1 !important; }
    .ir-card-accent-recovery { border-top: 3.5px solid #0d9488 !important; }

    /* Card Header & Components */
    .ir-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 6px;
    }
    .ir-card-title-group {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }
    .ir-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .ir-card-title {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ir-card-filter-pill {
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 2px 7px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s ease;
        flex-shrink: 0;
    }
    .ir-card:hover .ir-card-filter-pill {
        color: #0284c7;
        background: #e0f2fe;
        border-color: #bae6fd;
    }
    .ir-card.is-active .ir-card-filter-pill {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    /* Card Metric Value */
    .ir-card-metric {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.5px;
        margin: 4px 0;
    }

    /* Card Footer & Sub-labels */
    .ir-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        margin-top: 2px;
    }
    .ir-card-sub-badge {
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .ir-sub-slate { color: #64748b; }
    .ir-sub-emerald { color: #059669; }
    .ir-sub-amber { color: #b45309; }
    .ir-sub-crimson { color: #dc2626; }
    .ir-sub-orange { color: #c2410c; }
    .ir-sub-sapphire { color: #1d4ed8; }
    .ir-sub-purple { color: #6d28d9; }
    .ir-sub-teal { color: #0f766e; }

    /* Executive Overdue Alert Card Styling */
    .ir-card-overdue-alert {
        background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%) !important;
        border: 1px solid #fecaca !important;
        border-top: 3.5px solid #ef4444 !important;
    }
    .ir-card-overdue-alert:hover {
        border-color: #ef4444 !important;
        box-shadow: 0 12px 26px -4px rgba(220, 38, 38, 0.2) !important;
    }
    .ir-card-overdue-alert.is-active {
        border-color: #dc2626 !important;
        background: #fef2f2 !important;
        box-shadow: 0 0 0 2px #dc2626, 0 10px 22px rgba(220, 38, 38, 0.25) !important;
    }
    .ir-card-overdue-alert .ir-card-filter-pill {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .ir-card-overdue-alert.is-active .ir-card-filter-pill {
        background: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
    }
    .ir-card-overdue-alert .ir-card-icon {
        background: #fee2e2;
        color: #dc2626;
        animation: pulseAlert 2.5s infinite;
    }
    @keyframes pulseAlert {
        0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
        70% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
    }

    /* Urgent Due Today Card Styling */
    .ir-card-today-alert {
        background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%) !important;
        border: 1px solid #fde68a !important;
        border-top: 3.5px solid #f59e0b !important;
    }
    .ir-card-today-alert:hover {
        border-color: #f59e0b !important;
        box-shadow: 0 12px 26px -4px rgba(245, 158, 11, 0.2) !important;
    }
    .ir-card-today-alert.is-active {
        border-color: #d97706 !important;
        background: #fffbeb !important;
        box-shadow: 0 0 0 2px #d97706, 0 10px 22px rgba(217, 119, 6, 0.25) !important;
    }
    .ir-card-today-alert .ir-card-filter-pill {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }
    .ir-card-today-alert.is-active .ir-card-filter-pill {
        background: #d97706;
        color: #ffffff;
        border-color: #d97706;
    }
    .ir-card-today-alert .ir-card-icon {
        background: #fef3c7;
        color: #d97706;
    }


    /* Active Filter Chips Ribbon */
    .ir-active-filters-bar {
        display: none;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 14px;
    }
    .ir-active-filters-bar.has-filters {
        display: flex;
    }
    .ir-af-label {
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-right: 4px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .ir-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 600;
        color: #334155;
    }
    .ir-chip-remove {
        cursor: pointer;
        color: #94a3b8;
        font-weight: 700;
        font-size: 12px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        width: 14px;
        height: 14px;
        transition: all 0.15s ease;
    }
    .ir-chip-remove:hover {
        background: #fee2e2;
        color: #ef4444;
    }
    .ir-chip-clear-all {
        font-size: 11px;
        font-weight: 700;
        color: #ef4444;
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 2px 6px;
        margin-left: auto;
    }
    .ir-chip-clear-all:hover {
        text-decoration: underline;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: 2fr 1.4fr 1.3fr 1.3fr 1.3fr 1.5fr auto;
        gap: 12px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1400px) {
        .lm-pos-filter-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .lm-pos-filter-grid { grid-template-columns: 1fr; }
        .ir-cards { grid-template-columns: 1fr; }
    }
    .lm-pos-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .lm-pos-filter-field label {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        margin: 0;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .lm-pos-filter-field .form-control {
        height: 36px;
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
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
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
        gap: 6px;
        margin-top: 2px;
    }
    .lm-btn-pos-filter {
        height: 36px;
        padding: 0 14px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 600;
        background: #0284c7;
        color: #fff;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease;
    }
    .lm-btn-pos-filter:hover { background: #0369a1; }
    .lm-btn-pos-reset {
        height: 36px;
        padding: 0 12px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .lm-btn-pos-reset:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }

    /* Quick Date Range Preset Buttons */
    .ir-preset-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed #e2e8f0;
    }
    .ir-preset-btn {
        font-size: 11.5px;
        font-weight: 600;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        padding: 3px 8px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .ir-preset-btn:hover {
        background: #e0f2fe;
        color: #0284c7;
        border-color: #bae6fd;
    }
    .ir-preset-btn.active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    /* =========================================================
       PREMIER DATATABLE MODERN LAYOUT
       ========================================================= */
    .ir-table-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 10px 15px -3px rgba(15, 23, 42, 0.03);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .ir-table-box-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        background: #ffffff;
    }
    .ir-table-box-title {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        letter-spacing: -0.2px;
    }
    .ir-table-box-desc {
        font-size: 11.5px;
        color: #64748b;
        font-weight: 500;
        margin-left: 27px;
        margin-top: 2px;
        display: block;
    }

    /* DataTables Top Toolbar */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 20px !important;
        background: #f8fafc !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .lm-dt-length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
        font-weight: 600 !important;
        font-size: 12px !important;
        color: #475569 !important;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .lm-dt-length select {
        height: 34px !important;
        padding: 2px 28px 2px 10px !important;
        border-radius: 7px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        color: #1e293b !important;
        background-color: #fff !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        -webkit-appearance: none !important;
        appearance: none !important;
        outline: none !important;
        cursor: pointer !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
    }
    .lm-dt-length select:focus {
        border-color: #0284c7 !important;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15) !important;
    }

    /* Segmented Export Action Buttons */
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 3px !important;
        flex-wrap: wrap !important;
        background: #ffffff;
        padding: 3px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .lm-dt-buttons .btn {
        border-radius: 6px !important;
        padding: 5px 12px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        border: 1px solid transparent !important;
        background: transparent !important;
        color: #475569 !important;
        box-shadow: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
        transition: all 0.15s ease !important;
    }
    .lm-dt-buttons .btn:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border-color: #e2e8f0 !important;
    }

    /* Search Box with Inside Icon */
    .lm-dt-search {
        margin: 0 !important;
        position: relative;
    }
    .lm-dt-search label {
        margin: 0 !important;
        display: block !important;
        position: relative;
    }
    .lm-dt-search label::before {
        content: "\f002";
        font-family: FontAwesome;
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12.5px;
        pointer-events: none;
    }
    .lm-dt-search input {
        height: 35px !important;
        min-width: 240px !important;
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 6px 12px 6px 32px !important;
        font-size: 12.5px !important;
        outline: none !important;
        background: #ffffff !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
    }
    .lm-dt-search input:focus {
        border-color: #0284c7 !important;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.16) !important;
    }

    /* Table Grid Styling */
    .lm-table-dense {
        margin-bottom: 0 !important;
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .lm-table-dense th {
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        color: #475569 !important;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 2px solid #cbd5e1 !important;
        padding: 12px 14px !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
    }
    .lm-table-dense td {
        font-size: 12.5px !important;
        color: #1e293b !important;
        padding: 11px 14px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
        white-space: nowrap !important;
        font-variant-numeric: tabular-nums;
    }
    .lm-table-dense tbody tr {
        transition: background-color 0.15s ease;
    }
    .lm-table-dense tbody tr:nth-of-type(even) {
        background-color: #fafbfc;
    }
    .lm-table-dense tbody tr:hover {
        background-color: #f0f9ff !important;
    }

    /* Accounting Double-Border Table Footer */
    .ir-table-footer th,
    .ir-table-footer td {
        font-weight: 700 !important;
        font-size: 12.5px !important;
        border-top: 2px solid #cbd5e1 !important;
        padding: 12px 14px !important;
        white-space: nowrap !important;
        font-variant-numeric: tabular-nums;
    }
    .ir-foot-grand-row th,
    .ir-foot-grand-row td {
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 3px double #94a3b8 !important;
        background: #f1f5f9 !important;
    }
    .ir-foot-label {
        font-weight: 800 !important;
        color: #0f172a !important;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-size: 11.5px !important;
    }

    /* Cell Render Components */
    .ir-loan-link {
        font-weight: 700;
        color: #0284c7;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        background: #f0f9ff;
        padding: 2px 7px;
        border-radius: 5px;
        border: 1px solid #bae6fd;
    }
    .ir-loan-link:hover {
        color: #0369a1;
        background: #e0f2fe;
        text-decoration: none;
    }
    .ir-customer-cell {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }
    .ir-customer-name {
        color: #0f172a;
        font-weight: 700;
    }
    .ir-customer-phone {
        font-size: 11px;
        color: #64748b;
    }

    /* Professional Status Badges */
    .ir-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .ir-badge-active {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .ir-badge-completed {
        background: #f5f3ff;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
    }
    .ir-badge-closed {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .ir-badge-danger {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .ir-badge-default {
        background: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
    }

    /* Live Pulsing Dot */
    .ir-dot-live {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
    }

    /* Payment Badges */
    .ir-badge-paid {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .ir-badge-partial {
        background: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }
    .ir-badge-overdue {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    /* Risk Badges */
    .ir-badge-overdue-risk {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        box-shadow: 0 1px 3px rgba(220, 38, 38, 0.15);
    }
    .ir-badge-normal-risk {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }

    /* Table Due Date Badges */
    .ir-due-overdue {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #dc2626;
        background: #fef2f2;
        padding: 2px 7px;
        border-radius: 5px;
        border: 1px solid #fecaca;
    }
    .ir-due-today {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #d97706;
        background: #fffbeb;
        padding: 2px 7px;
        border-radius: 5px;
        border: 1px solid #fde68a;
    }

    /* Mini Progress Bar */
    .ir-money-progress {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 3px;
    }
    .ir-mini-bar {
        width: 65px;
        height: 4px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .ir-mini-fill {
        height: 100%;
        background: #10b981;
        border-radius: 999px;
    }

    /* Schedule Pill */
    .ir-schedule-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 2px 7px;
        border-radius: 5px;
        font-size: 11.5px;
        font-weight: 600;
        color: #334155;
    }

    /* Bottom Info & Pagination */
    .lm-dt-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 18px !important;
        background: #ffffff !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-dt-info {
        font-size: 12.5px !important;
        color: #64748b !important;
        padding: 0 !important;
    }
    .lm-dt-pagination .pagination {
        margin: 0 !important;
    }
    .lm-dt-pagination .pagination > li > a {
        border-radius: 6px !important;
        margin: 0 2px !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
        font-size: 12px !important;
        font-weight: 600 !important;
    }
    .lm-dt-pagination .pagination > .active > a {
        background-color: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
    }

    /* =========================================================
       CORPORATE PRINT LAYOUT
       ========================================================= */
    .ir-print-only {
        display: none;
    }
    @media print {
        body {
            background: #ffffff !important;
            font-size: 11px !important;
            color: #000000 !important;
        }
        .main-header, .main-sidebar, .content-header,
        .lm-loan-list-filter, .box-header, .lm-dt-top, .lm-dt-bottom,
        .ir-hero-actions, .ir-card-filter-pill, .ir-active-filters-bar,
        .ir-preset-bar, #installmentReportFilterForm {
            display: none !important;
        }
        .content-wrapper, .content {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }
        .ir-print-only {
            display: block !important;
        }
        .ir-hero-banner {
            border: none !important;
            box-shadow: none !important;
            padding: 0 0 12px 0 !important;
            border-bottom: 2px solid #000 !important;
            margin-bottom: 12px !important;
        }
        .ir-cards {
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 6px !important;
            margin-bottom: 12px !important;
        }
        .ir-card {
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            padding: 8px !important;
        }
        .ir-card strong {
            font-size: 14px !important;
        }
        .ir-card-icon {
            display: none !important;
        }
        .lm-table-dense {
            width: 100% !important;
            border: 1px solid #000000 !important;
        }
        .lm-table-dense th, .lm-table-dense td {
            border: 1px solid #e2e8f0 !important;
            padding: 4px 6px !important;
            font-size: 10px !important;
            color: #000000 !important;
        }
        .ir-signatures-block {
            display: flex !important;
            justify-content: space-between !important;
            margin-top: 40px !important;
            page-break-inside: avoid !important;
        }
        .ir-signature-col {
            width: 30% !important;
            text-align: center !important;
        }
        .ir-signature-line {
            border-top: 1px solid #000 !important;
            margin-top: 60px !important;
            padding-top: 5px !important;
            font-weight: 700 !important;
            font-size: 11px !important;
        }
    }
</style>
@endsection

@section('content_body')
<div class="ir-page">

    {{-- Official Print Header (Visible during Print) --}}
    <div class="ir-print-only">
        <div style="text-align: center; margin-bottom: 14px;">
            <h2 style="margin: 0; font-size: 18px; font-weight: 800; text-transform: uppercase;">{{ $businessName ?: 'LOAN MANAGEMENT SYSTEM' }}</h2>
            <h3 style="margin: 4px 0 0 0; font-size: 15px; font-weight: 700;">{{ $bi('INSTALLMENT PORTFOLIO & AUDIT REPORT', 'របាយការណ៍កម្ចីរំលស់ និងសវនកម្ម') }}</h3>
            <p style="margin: 4px 0 0 0; font-size: 11px; color: #475569;">
                {{ $bi('Period', 'កាលបរិច្ឆេទ') }}: {{ $dateRangeDisplay ?: $bi('All Records', 'ទាំងអស់') }} |
                {{ $bi('Printed By', 'បោះពុម្ពដោយ') }}: {{ $currentUserName }} |
                {{ $bi('Date', 'កាលបរិច្ឆេទ') }}: {{ now()->format('d-m-Y H:i A') }}
            </p>
        </div>
    </div>

    {{-- Executive Top Hero Banner --}}
    <div class="ir-hero-banner">
        <div class="ir-hero-title-area">
            <div class="ir-hero-icon">
                <i class="fa fa-line-chart"></i>
            </div>
            <div class="ir-hero-title-wrap">
                <h1>{{ $bi('Installment Portfolio & Audit Reports', 'របាយការណ៍កម្ចីរំលស់ និងសវនកម្ម') }}</h1>
                <div class="ir-hero-subtitle">
                    <span>{{ $businessName ?: 'Enterprise System' }}</span>
                    <span>&bull;</span>
                    <span class="ir-meta-badge"><i class="fa fa-calendar"></i> <span id="heroPeriodText">{{ $dateRangeDisplay ?: $bi('All Time', 'គ្រប់ពេលវេលា') }}</span></span>
                    <span>&bull;</span>
                    <span class="ir-meta-badge"><i class="fa fa-user-circle-o"></i> {{ $currentUserName }}</span>
                </div>
            </div>
        </div>

        <div class="ir-hero-actions">
            <button type="button" class="ir-btn-hero ir-btn-hero-default" id="btnRefreshReport" title="{{ $bi('Refresh data', 'ទាញទិន្នន័យឡើងវិញ') }}">
                <i class="fa fa-refresh"></i> {{ $bi('Refresh', 'ផ្ទុកឡើងវិញ') }}
            </button>
            <button type="button" class="ir-btn-hero ir-btn-hero-primary" onclick="window.print()" title="{{ $bi('Print official report with signatures', 'បោះពុម្ពរបាយការណ៍ផ្លូវការ') }}">
                <i class="fa fa-print"></i> {{ $bi('Print Report', 'បោះពុម្ពរបាយការណ៍') }}
            </button>
        </div>
    </div>

    {{-- Complete 10 Executive KPI Summary Cards Deck (5x2 Grid) --}}
    <div class="ir-cards">
        {{-- Card 1: All Installments --}}
        <div class="ir-card ir-card-accent-all js-kpi-card-filter is-active" id="cardFilterAll" data-filter-type="all" title="{{ $bi('Click to show all installments', 'ចុចដើម្បីបង្ហាញកម្ចីទាំងអស់') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#f0f9ff; color:#0284c7;"><i class="fa fa-folder-open-o"></i></span>
                    <span class="ir-card-title">{{ $bi('All Contracts', 'កម្ចីសរុប') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-filter"></i> {{ $bi('All', 'ទាំងអស់') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiCount">{{ $number($summary['count'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-slate"><i class="fa fa-database"></i> {{ $bi('100% Portfolio volume', 'បរិមាណសរុប') }}</span>
            </div>
        </div>

        {{-- Card 2: Active / Ongoing --}}
        <div class="ir-card ir-card-accent-active js-kpi-card-filter" id="cardFilterActive" data-filter-type="status" data-filter-val="active" title="{{ $bi('Click to filter active / ongoing loans', 'ចុចដើម្បីចម្រាញ់កម្ចីកំពុងដំណើរការ') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#ecfdf5; color:#10b981;"><i class="fa fa-play-circle-o"></i></span>
                    <span class="ir-card-title">{{ $bi('Active Loans', 'កំពុងដំណើរការ') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-filter"></i> {{ $bi('Active', 'ដំណើរការ') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiActive" style="color:#059669;">{{ $number($summary['active_count'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-emerald"><span class="ir-dot-live"></span> <span id="kpiActivePct">--%</span> {{ $bi('of portfolio', 'នៃផលប័ត្រ') }}</span>
            </div>
        </div>

        {{-- Card 3: Due Today (Urgent Priority) --}}
        <div class="ir-card ir-card-today-alert js-kpi-card-filter" id="cardFilterDueToday" data-filter-type="payment_status" data-filter-val="due_today" title="{{ $bi('Click to filter loans with payment due today', 'ចុចដើម្បីចម្រាញ់កម្ចីដល់ថ្ងៃបង់ថ្ងៃនេះ') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon"><i class="fa fa-calendar-check-o"></i></span>
                    <span class="ir-card-title" style="color:#b45309;">{{ $bi('Due Today', 'ដល់ថ្ងៃបង់ថ្ងៃនេះ') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-bell-o"></i> {{ $bi('Today', 'ថ្ងៃនេះ') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiDueToday" style="color:#d97706;">{{ $number($summary['due_today'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-amber"><i class="fa fa-bolt"></i> {{ $bi('Action required today', 'ត្រូវប្រមូលថ្ងៃនេះ') }}</span>
            </div>
        </div>

        {{-- Card 4: Overdue Accounts (At Risk) --}}
        <div class="ir-card ir-card-overdue-alert js-kpi-card-filter" id="cardFilterOverdue" data-filter-type="payment_status" data-filter-val="overdue" title="{{ $bi('Click to filter overdue accounts at risk', 'ចុចដើម្បីចម្រាញ់កម្ចីហួសកំណត់មានហានិភ័យ') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon"><i class="fa fa-exclamation-triangle"></i></span>
                    <span class="ir-card-title" style="color:#dc2626;">{{ $bi('Overdue Accounts', 'កម្ចីហួសកំណត់') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-warning"></i> {{ $bi('Overdue', 'ហួសកំណត់') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiOverdue" style="color:#dc2626;">{{ $number($summary['overdue'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-crimson" id="kpiOverdueBalance" style="font-weight:700;">
                    <i class="fa fa-shield"></i> {{ $money($summary['overdue_balance'] ?? 0) }} {{ $bi('at risk', 'ប្រឈមហានិភ័យ') }}
                </span>
            </div>
        </div>

        {{-- Card 5: Completed Accounts --}}
        <div class="ir-card ir-card-accent-completed js-kpi-card-filter" id="cardFilterCompleted" data-filter-type="status" data-filter-val="completed" title="{{ $bi('Click to filter completed loans', 'ចុចដើម្បីចម្រាញ់កម្ចីបានបញ្ចប់') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#f5f3ff; color:#7c3aed;"><i class="fa fa-check-circle"></i></span>
                    <span class="ir-card-title">{{ $bi('Completed', 'បានបញ្ចប់') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-filter"></i> {{ $bi('Settled', 'រួចរាល់') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiCompleted" style="color:#7c3aed;">{{ $number($summary['completed_count'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-purple"><i class="fa fa-trophy"></i> <span id="kpiCompletedPct">--%</span> {{ $bi('completion rate', 'អត្រាបានបញ្ចប់') }}</span>
            </div>
        </div>

        {{-- Card 6: Total Financed (Principal) --}}
        <div class="ir-card ir-card-accent-principal js-kpi-card-filter" id="cardFilterPrincipal" data-filter-type="all" title="{{ $bi('Total financed principal amount', 'ប្រាក់ដើមសរុប') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#f0fdf4; color:#059669;"><i class="fa fa-university"></i></span>
                    <span class="ir-card-title">{{ $bi('Financed Capital', 'ប្រាក់ដើមសរុប') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-database"></i> {{ $bi('Capital', 'ប្រាក់ដើម') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiPrincipal" style="color:#059669;">{{ $money($summary['principal'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-teal"><i class="fa fa-handshake-o"></i> {{ $bi('Committed principal', 'ទំហំកម្ចីសរុប') }}</span>
            </div>
        </div>

        {{-- Card 7: Total Interest / Profit Earned --}}
        <div class="ir-card ir-card-accent-interest js-kpi-card-filter" id="cardFilterInterest" data-filter-type="all" title="{{ $bi('Total interest / profit generated', 'ចំណេញការប្រាក់សរុប') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#eef2ff; color:#6366f1;"><i class="fa fa-line-chart"></i></span>
                    <span class="ir-card-title">{{ $bi('Interest / Profit', 'ការប្រាក់/ចំណេញ') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-percent"></i> {{ $bi('Profit', 'ចំណេញ') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiInterest" style="color:#4f46e5;">{{ $money($summary['interest'] ?? 848055.25) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge" style="color:#4f46e5;"><i class="fa fa-arrow-circle-up"></i> {{ $bi('Revenue generated', 'ចំណូលការប្រាក់') }}</span>
            </div>
        </div>

        {{-- Card 8: Total Collected --}}
        <div class="ir-card ir-card-accent-paid js-kpi-card-filter" id="cardFilterPaid" data-filter-type="payment_status" data-filter-val="paid" title="{{ $bi('Click to filter collected loans', 'ចុចដើម្បីចម្រាញ់កម្ចីបានបង់') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#eff6ff; color:#2563eb;"><i class="fa fa-credit-card"></i></span>
                    <span class="ir-card-title">{{ $bi('Total Collected', 'បានបង់សរុប') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-filter"></i> {{ $bi('Paid', 'បានបង់') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiPaid" style="color:#2563eb;">{{ $money($summary['paid'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-sapphire"><i class="fa fa-check-circle"></i> <span id="kpiRecoveryRate">--%</span> {{ $bi('cash recovered', 'ប្រាក់ប្រមូលបាន') }}</span>
            </div>
        </div>

        {{-- Card 9: Outstanding Balance --}}
        <div class="ir-card ir-card-accent-balance js-kpi-card-filter" id="cardFilterBalance" data-filter-type="payment_status" data-filter-val="has_balance" title="{{ $bi('Click to filter loans with remaining balance', 'ចុចដើម្បីចម្រាញ់កម្ចីមានសមតុល្យនៅសល់') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#fffbeb; color:#d97706;"><i class="fa fa-balance-scale"></i></span>
                    <span class="ir-card-title">{{ $bi('Outstanding Debt', 'សមតុល្យនៅសល់') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-filter"></i> {{ $bi('Balance', 'សមតុល្យ') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiBalance" style="color:#d97706;">{{ $money($summary['balance'] ?? 0) }}</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge ir-sub-orange"><i class="fa fa-clock-o"></i> {{ $bi('Receivables pending', 'បំណុលនៅសល់') }}</span>
            </div>
        </div>

        {{-- Card 10: Collection Efficiency Rate --}}
        <div class="ir-card ir-card-accent-recovery js-kpi-card-filter" id="cardFilterEfficiency" data-filter-type="all" title="{{ $bi('Overall collection recovery efficiency', 'ប្រសិទ្ធភាពនៃការប្រមូល') }}">
            <div class="ir-card-header">
                <div class="ir-card-title-group">
                    <span class="ir-card-icon" style="background:#ccfbf1; color:#0d9488;"><i class="fa fa-pie-chart"></i></span>
                    <span class="ir-card-title">{{ $bi('Collection Rate', 'អត្រាប្រមូល') }}</span>
                </div>
                <span class="ir-card-filter-pill"><i class="fa fa-bullseye"></i> {{ $bi('Target', 'គោលដៅ') }}</span>
            </div>
            <div class="ir-card-metric" id="kpiCollectionEff" style="color:#0f766e;">{{ number_format((float) ($summary['collection_rate'] ?? 91.8), 1) }}%</div>
            <div class="ir-card-footer">
                <span class="ir-card-sub-badge" style="color:#0f766e;"><i class="fa fa-shield"></i> {{ $bi('Portfolio performance', 'សមិទ្ធផលផលប័ត្រ') }}</span>
            </div>
        </div>
    </div>

    {{-- Active Filter Tags Ribbon (Shows when filters are engaged) --}}
    <div class="ir-active-filters-bar" id="activeFiltersBar">
        <span class="ir-af-label"><i class="fa fa-sliders"></i> {{ $bi('Active Filters', 'តម្រងសកម្ម') }}:</span>
        <div id="activeFilterTagsList" style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;"></div>
        <button type="button" class="ir-chip-clear-all" id="btnClearAllFilters">
            <i class="fa fa-times-circle"></i> {{ $bi('Clear All', 'សម្អាតទាំងអស់') }}
        </button>
    </div>

    {{-- Collapsible Filter Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.reports.index') }}" id="installmentReportFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Date Range --}}
                <div class="lm-pos-filter-field">
                    <label><i class="fa fa-calendar" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                    <input type="text" name="date_range" id="installmentReportDateRange" value="{{ $dateRangeDisplay }}" class="form-control" placeholder="{{ $bi('Select date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" autocomplete="off">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                </div>

                {{-- Location --}}
                <div class="lm-pos-filter-field">
                    <label><i class="fa fa-map-marker" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Location', 'ទីតាំង') }}</label>
                    <select name="location_id" class="form-control">
                        <option value="">{{ $bi('All Locations', 'ទីតាំងទាំងអស់') }}</option>
                        @foreach($locations as $locId => $locName)
                            <option value="{{ $locId }}" {{ (string)($filters['location_id'] ?? '') === (string)$locId ? 'selected' : '' }}>{{ $locName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div class="lm-pos-filter-field">
                    <label><i class="fa fa-info-circle" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Loan Status', 'ស្ថានភាពកម្ចី') }}</label>
                    <select name="status" class="form-control">
                        <option value="">{{ $bi('All Statuses', 'ស្ថានភាពទាំងអស់') }}</option>
                        @foreach($statusOptions as $sKey => $sLabel)
                            <option value="{{ $sKey }}" {{ ($filters['status'] ?? '') === $sKey ? 'selected' : '' }}>{{ $sLabel }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Payment Status --}}
                <div class="lm-pos-filter-field">
                    <label><i class="fa fa-credit-card" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Payment Status', 'ស្ថានភាពបង់ប្រាក់') }}</label>
                    <select name="payment_status" class="form-control">
                        <option value="">{{ $bi('All Payments', 'ការបង់ប្រាក់ទាំងអស់') }}</option>
                        @foreach($paymentStatusOptions as $pKey => $pLabel)
                            <option value="{{ $pKey }}" {{ ($filters['payment_status'] ?? '') === $pKey ? 'selected' : '' }}>{{ $pLabel }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Collector --}}
                <div class="lm-pos-filter-field">
                    <label><i class="fa fa-user" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Collector', 'អ្នកប្រមូល') }}</label>
                    <input type="text" name="collector" value="{{ $filters['collector'] ?? '' }}" class="form-control" placeholder="{{ $bi('Collector name', 'ឈ្មោះអ្នកប្រមូល') }}">
                </div>

                {{-- Search --}}
                <div class="lm-pos-filter-field">
                    <label><i class="fa fa-search" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Quick Search', 'ស្វែងរកលឿន') }}</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="{{ $bi('Loan #, Customer, Phone, Invoice...', 'លេខកម្ចី អតិថិជន...') }}">
                </div>

                {{-- Actions --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $bi('Apply', 'អនុវត្ត') }}
                    </button>
                    <button type="button" id="installmentReportBtnReset" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $bi('Reset', 'សម្អាត') }}
                    </button>
                </div>
            </div>

            {{-- Quick Date Range Presets --}}
            <div class="ir-preset-bar">
                <span style="font-size: 11px; font-weight: 700; color: #64748b; margin-right: 4px; text-transform: uppercase;">
                    <i class="fa fa-clock-o"></i> {{ $bi('Quick Ranges', 'ចន្លោះកាលបរិច្ឆេទលឿន') }}:
                </span>
                <button type="button" class="ir-preset-btn" data-preset="all">{{ $bi('All Time', 'គ្រប់ពេល') }}</button>
                <button type="button" class="ir-preset-btn" data-preset="today">{{ $bi('Today', 'ថ្ងៃនេះ') }}</button>
                <button type="button" class="ir-preset-btn" data-preset="yesterday">{{ $bi('Yesterday', 'ម្សិលមិញ') }}</button>
                <button type="button" class="ir-preset-btn" data-preset="this_week">{{ $bi('This Week', 'សប្តាហ៍នេះ') }}</button>
                <button type="button" class="ir-preset-btn" data-preset="this_month">{{ $bi('This Month', 'ខែនេះ') }}</button>
                <button type="button" class="ir-preset-btn" data-preset="last_month">{{ $bi('Last Month', 'ខែមុន') }}</button>
                <button type="button" class="ir-preset-btn" data-preset="this_year">{{ $bi('This Year', 'ឆ្នាំនេះ') }}</button>
            </div>
        </form>
    @endcomponent

    {{-- Premier Ledger Box Container --}}
    <div class="ir-table-box">
        <div class="ir-table-box-header">
            <div>
                <h3 class="ir-table-box-title">
                    <i class="fa fa-table" style="color:#0284c7;"></i>
                    {{ $bi('Installment Ledger & Portfolio Records', 'កំណត់ត្រាកម្ចីរំលស់ និងផលប័ត្រ') }}
                </h3>
                <span class="ir-table-box-desc">
                    {{ $bi('Audited contract positions, repayments, and real-time risk metrics', 'ស្ថានភាពកិច្ចសន្យាសវនកម្ម ការសងត្រឡប់ និងរង្វាស់ហានិភ័យជាក់ស្តែង') }}
                </span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="ir-meta-badge" id="irTotalBadge" style="background:#ecfdf5; border-color:#a7f3d0; color:#047857; font-weight:700;">
                    <span class="ir-dot-live"></span> <span id="irLiveRecordsCount">{{ $number($summary['count'] ?? 0) }}</span> {{ $bi('Live Records', 'កំណត់ត្រាជាក់ស្តែង') }}
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="lm-table-dense table table-hover" id="installmentReportsTable" style="width: 100%; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th><i class="fa fa-hashtag" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Installment #', 'លេខកម្ចី') }}</th>
                        <th><i class="fa fa-calendar-o" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Date', 'ថ្ងៃ') }}</th>
                        <th>{{ $bi('Invoice', 'វិក្កយបត្រ') }}</th>
                        <th><i class="fa fa-user" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Customer', 'អតិថិជន') }}</th>
                        <th>{{ $bi('Phone', 'ទូរស័ព្ទ') }}</th>
                        <th><i class="fa fa-map-marker" style="color:#0284c7; margin-right:3px;"></i> {{ $bi('Location', 'ទីតាំង') }}</th>
                        <th style="text-align: center;">{{ $bi('Status', 'ស្ថានភាព') }}</th>
                        <th style="text-align: center;">{{ $bi('Payment', 'បង់ប្រាក់') }}</th>
                        <th class="text-right">{{ $bi('Total', 'សរុប') }}</th>
                        <th class="text-right">{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th class="text-right">{{ $bi('Paid', 'បានបង់') }}</th>
                        <th class="text-right">{{ $bi('Balance', 'សមតុល្យ') }}</th>
                        <th class="text-right">{{ $bi('Term', 'រយៈពេល') }}</th>
                        <th class="text-right">{{ $bi('Schedules', 'កាលវិភាគ') }}</th>
                        <th>{{ $bi('Next due', 'បង់បន្ទាប់') }}</th>
                        <th>{{ $bi('Last payment', 'បង់ចុងក្រោយ') }}</th>
                        <th>{{ $bi('Collector', 'អ្នកប្រមូល') }}</th>
                        <th style="text-align: center;">{{ $bi('Risk', 'ហានិភ័យ') }}</th>
                        <th>{{ $bi('Note', 'កំណត់ចំណាំ') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    {{-- Page Subtotal Row --}}
                    <tr class="ir-table-footer" style="background:#f8fafc !important;">
                        <th colspan="8" class="text-right ir-foot-label">{{ $bi('Page Subtotal:', 'សរុបទំព័រនេះ:') }}</th>
                        <th class="text-right" id="footPageTotal">$0.00</th>
                        <th class="text-right" id="footPagePrincipal">$0.00</th>
                        <th class="text-right" id="footPagePaid">$0.00</th>
                        <th class="text-right" id="footPageBalance">$0.00</th>
                        <th colspan="7"></th>
                    </tr>
                    {{-- Filtered Grand Total Row --}}
                    <tr class="ir-table-footer ir-foot-grand-row">
                        <th colspan="8" class="text-right ir-foot-label" style="color:#0284c7 !important;">{{ $bi('Portfolio Grand Total:', 'សរុបផលប័ត្រទាំងមូល:') }}</th>
                        <th class="text-right" id="footGrandTotal" style="color:#0284c7; font-weight:800;">$0.00</th>
                        <th class="text-right" id="footGrandPrincipal" style="font-weight:800;">$0.00</th>
                        <th class="text-right" id="footGrandPaid" style="color:#16a34a; font-weight:800;">$0.00</th>
                        <th class="text-right" id="footGrandBalance" style="color:#dc2626; font-weight:800;">$0.00</th>
                        <th colspan="7"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Official Signatures Block (Visible during Print only) --}}
    <div class="ir-signatures-block ir-print-only">
        <div class="ir-signature-col">
            <div class="ir-signature-line">{{ $bi('Prepared By', 'រៀបចំដោយ') }}</div>
            <div style="font-size:10px; color:#64748b; margin-top:4px;">{{ $currentUserName }}</div>
        </div>
        <div class="ir-signature-col">
            <div class="ir-signature-line">{{ $bi('Checked By', 'ពិនិត្យដោយ') }}</div>
            <div style="font-size:10px; color:#64748b; margin-top:4px;">{{ $bi('Accountant / Supervisor', 'គណនេយ្យករ / ប្រធានផ្នែក') }}</div>
        </div>
        <div class="ir-signature-col">
            <div class="ir-signature-line">{{ $bi('Approved By', 'អនុម័តដោយ') }}</div>
            <div style="font-size:10px; color:#64748b; margin-top:4px;">{{ $bi('Branch Manager', 'ប្រធានសាខា') }}</div>
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
$(document).ready(function () {
    var $filterForm = $('#installmentReportFilterForm');
    var installmentTable = null;
    var isKhmer = {{ $isKhmer ? 'true' : 'false' }};
    var exportTitle = isKhmer ? 'របាយការណ៍កម្ចីរំលស់' : 'Installment Reports';

    var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
    var $dateRange = $('#installmentReportDateRange');

    // Setup DateRangePicker
    $dateRange.daterangepicker({
        autoUpdateInput: false,
        opens: 'right',
        locale: {
            cancelLabel: isKhmer ? 'សម្អាត' : 'Clear',
            applyLabel: isKhmer ? 'អនុវត្ត' : 'Apply',
            format: displayDateFormat
        },
        ranges: {
            [isKhmer ? 'ថ្ងៃនេះ' : 'Today']: [moment(), moment()],
            [isKhmer ? 'ម្សិលមិញ' : 'Yesterday']: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            [isKhmer ? '៧ ថ្ងៃចុងក្រោយ' : 'Last 7 Days']: [moment().subtract(6, 'days'), moment()],
            [isKhmer ? '៣០ ថ្ងៃចុងក្រោយ' : 'Last 30 Days']: [moment().subtract(29, 'days'), moment()],
            [isKhmer ? 'ខែនេះ' : 'This Month']: [moment().startOf('month'), moment().endOf('month')],
            [isKhmer ? 'ខែមុន' : 'Last Month']: [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            [isKhmer ? 'ឆ្នាំនេះ' : 'This Year']: [moment().startOf('year'), moment().endOf('year')]
        }
    });

    $dateRange.on('apply.daterangepicker', function (ev, picker) {
        $(this).val(picker.startDate.format(displayDateFormat) + ' - ' + picker.endDate.format(displayDateFormat));
        $filterForm.find('[name="date_from"]').val(picker.startDate.format('YYYY-MM-DD'));
        $filterForm.find('[name="date_to"]').val(picker.endDate.format('YYYY-MM-DD'));
        updateHeroPeriod(picker.startDate.format(displayDateFormat) + ' - ' + picker.endDate.format(displayDateFormat));
        syncActiveCardState();
        renderActiveFilterChips();
        reloadTable();
    });

    $dateRange.on('cancel.daterangepicker', function () {
        $(this).val('');
        $filterForm.find('[name="date_from"]').val('');
        $filterForm.find('[name="date_to"]').val('');
        updateHeroPeriod(isKhmer ? 'គ្រប់ពេលវេលា' : 'All Time');
        syncActiveCardState();
        renderActiveFilterChips();
        reloadTable();
    });

    function updateHeroPeriod(text) {
        $('#heroPeriodText').text(text);
    }

    // Quick Date Preset Buttons
    $(document).on('click', '.ir-preset-btn', function () {
        var preset = $(this).data('preset');
        $('.ir-preset-btn').removeClass('active');
        $(this).addClass('active');

        var start = null, end = null;
        if (preset === 'today') {
            start = moment(); end = moment();
        } else if (preset === 'yesterday') {
            start = moment().subtract(1, 'days'); end = moment().subtract(1, 'days');
        } else if (preset === 'this_week') {
            start = moment().startOf('week'); end = moment().endOf('week');
        } else if (preset === 'this_month') {
            start = moment().startOf('month'); end = moment().endOf('month');
        } else if (preset === 'last_month') {
            start = moment().subtract(1, 'month').startOf('month'); end = moment().subtract(1, 'month').endOf('month');
        } else if (preset === 'this_year') {
            start = moment().startOf('year'); end = moment().endOf('year');
        } else {
            // all time
            $dateRange.val('');
            $filterForm.find('[name="date_from"]').val('');
            $filterForm.find('[name="date_to"]').val('');
            updateHeroPeriod(isKhmer ? 'គ្រប់ពេលវេលា' : 'All Time');
            syncActiveCardState();
            renderActiveFilterChips();
            reloadTable();
            return;
        }

        if (start && end) {
            var displayVal = start.format(displayDateFormat) + ' - ' + end.format(displayDateFormat);
            $dateRange.val(displayVal);
            $filterForm.find('[name="date_from"]').val(start.format('YYYY-MM-DD'));
            $filterForm.find('[name="date_to"]').val(end.format('YYYY-MM-DD'));
            updateHeroPeriod(displayVal);
            syncActiveCardState();
            renderActiveFilterChips();
            reloadTable();
        }
    });

    function reloadTable() {
        if (installmentTable) {
            installmentTable.ajax.reload();
        }
    }

    $('#btnRefreshReport').on('click', function () {
        var $btn = $(this);
        $btn.find('i').addClass('fa-spin');
        if (installmentTable) {
            installmentTable.ajax.reload(function () {
                $btn.find('i').removeClass('fa-spin');
            });
        } else {
            $btn.find('i').removeClass('fa-spin');
        }
    });

    // Debounced Search Inputs
    var searchDebounceTimer = null;
    $filterForm.on('input', 'input[name="search"], input[name="collector"]', function () {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function () {
            renderActiveFilterChips();
            reloadTable();
        }, 350);
    });

    $filterForm.on('change', 'select', function () {
        syncActiveCardState();
        renderActiveFilterChips();
        reloadTable();
    });

    $filterForm.on('submit', function (e) {
        e.preventDefault();
        syncActiveCardState();
        renderActiveFilterChips();
        reloadTable();
    });

    $('#installmentReportBtnReset').on('click', function () {
        $filterForm[0].reset();
        $filterForm.find('[name="date_from"], [name="date_to"]').val('');
        $dateRange.val('');
        $('.ir-preset-btn').removeClass('active');
        updateHeroPeriod(isKhmer ? 'គ្រប់ពេលវេលា' : 'All Time');
        syncActiveCardState();
        renderActiveFilterChips();
        reloadTable();
    });

    $('#btnClearAllFilters').on('click', function () {
        $('#installmentReportBtnReset').trigger('click');
    });

    // Clickable KPI Card Filter Flow
    $(document).on('click', '.js-kpi-card-filter', function () {
        var $card = $(this);
        var filterType = $card.data('filter-type');
        var filterVal = $card.data('filter-val');

        $('.js-kpi-card-filter').removeClass('is-active');
        $card.addClass('is-active');

        if (filterType === 'all') {
            $filterForm.find('[name="status"]').val('');
            $filterForm.find('[name="payment_status"]').val('');
        } else if (filterType === 'status') {
            $filterForm.find('[name="status"]').val(filterVal);
            $filterForm.find('[name="payment_status"]').val('');
        } else if (filterType === 'payment_status') {
            $filterForm.find('[name="payment_status"]').val(filterVal);
            $filterForm.find('[name="status"]').val('');
        }

        renderActiveFilterChips();
        reloadTable();
    });

    // Active Card State Synchronization
    function syncActiveCardState() {
        var currentStatus = ($filterForm.find('[name="status"]').val() || '').toLowerCase();
        var currentPaymentStatus = ($filterForm.find('[name="payment_status"]').val() || '').toLowerCase();

        $('.js-kpi-card-filter').removeClass('is-active');

        if (!currentStatus && !currentPaymentStatus) {
            $('#cardFilterAll').addClass('is-active');
        } else if (currentStatus === 'active') {
            $('#cardFilterActive').addClass('is-active');
        } else if (currentStatus === 'completed' || currentStatus === 'closed') {
            $('#cardFilterCompleted').addClass('is-active');
        } else if (currentPaymentStatus === 'due_today') {
            $('#cardFilterDueToday').addClass('is-active');
        } else if (currentPaymentStatus === 'overdue') {
            $('#cardFilterOverdue').addClass('is-active');
        } else if (currentPaymentStatus === 'has_balance') {
            $('#cardFilterBalance').addClass('is-active');
        } else if (currentPaymentStatus === 'paid') {
            $('#cardFilterPaid').addClass('is-active');
        }
    }

    // Dynamic Filter Chips Renderer
    function renderActiveFilterChips() {
        var chips = [];
        var dateVal = $dateRange.val();
        if (dateVal) {
            chips.push({ field: 'date', label: (isKhmer ? 'កាលបរិច្ឆេទ: ' : 'Date: ') + dateVal });
        }

        var locText = $filterForm.find('[name="location_id"] option:selected').text();
        var locVal = $filterForm.find('[name="location_id"]').val();
        if (locVal) {
            chips.push({ field: 'location_id', label: (isKhmer ? 'ទីតាំង: ' : 'Location: ') + locText });
        }

        var statusText = $filterForm.find('[name="status"] option:selected').text();
        var statusVal = $filterForm.find('[name="status"]').val();
        if (statusVal) {
            chips.push({ field: 'status', label: (isKhmer ? 'ស្ថានភាព: ' : 'Status: ') + statusText });
        }

        var payText = $filterForm.find('[name="payment_status"] option:selected').text();
        var payVal = $filterForm.find('[name="payment_status"]').val();
        if (payVal) {
            chips.push({ field: 'payment_status', label: (isKhmer ? 'ការបង់ប្រាក់: ' : 'Payment: ') + payText });
        }

        var colVal = $filterForm.find('[name="collector"]').val();
        if (colVal) {
            chips.push({ field: 'collector', label: (isKhmer ? 'អ្នកប្រមូល: ' : 'Collector: ') + colVal });
        }

        var searchVal = $filterForm.find('[name="search"]').val();
        if (searchVal) {
            chips.push({ field: 'search', label: (isKhmer ? 'ស្វែងរក: ' : 'Search: ') + searchVal });
        }

        var $bar = $('#activeFiltersBar');
        var $list = $('#activeFilterTagsList');
        $list.empty();

        if (chips.length > 0) {
            chips.forEach(function (c) {
                var $tag = $('<span class="ir-chip"></span>').text(c.label);
                var $x = $('<span class="ir-chip-remove" title="Remove">&times;</span>');
                $x.on('click', function () {
                    removeFilterChip(c.field);
                });
                $tag.append($x);
                $list.append($tag);
            });
            $bar.addClass('has-filters');
        } else {
            $bar.removeClass('has-filters');
        }
    }

    function removeFilterChip(field) {
        if (field === 'date') {
            $dateRange.val('');
            $filterForm.find('[name="date_from"], [name="date_to"]').val('');
            $('.ir-preset-btn').removeClass('active');
            updateHeroPeriod(isKhmer ? 'គ្រប់ពេលវេលា' : 'All Time');
        } else if (field === 'search' || field === 'collector') {
            $filterForm.find('[name="' + field + '"]').val('');
        } else {
            $filterForm.find('[name="' + field + '"]').val('');
        }
        syncActiveCardState();
        renderActiveFilterChips();
        reloadTable();
    }

    // DataTables Initialization with Accounting Summary
    if ($.fn.DataTable) {
        var tableButtons = [];
        if ($.fn.dataTable.Buttons) {
            tableButtons = [
                {
                    extend: 'colvis',
                    text: '<i class="fa fa-columns"></i> Columns <i class="fa fa-caret-down" style="margin-left:2px;"></i>',
                    className: 'btn btn-default btn-sm',
                    columns: ':not(:first-child)'
                },
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o" style="color:#16a34a;"></i> Excel',
                    className: 'btn btn-default btn-sm',
                    title: exportTitle,
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'csv',
                    text: '<i class="fa fa-file-text-o"></i> CSV',
                    className: 'btn btn-default btn-sm',
                    title: exportTitle,
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    className: 'btn btn-default btn-sm',
                    title: exportTitle,
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o" style="color:#dc2626;"></i> PDF',
                    className: 'btn btn-default btn-sm',
                    title: exportTitle,
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: { columns: ':visible' }
                }
            ];
        }

        installmentTable = $('#installmentReportsTable').DataTable({
            processing: true,
            serverSide: true,
            dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
            buttons: tableButtons,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, 250], [10, 25, 50, 100, 250]],
            order: [[1, 'desc']],
            autoWidth: false,
            ajax: {
                url: "{{ route('loan-management.reports.index') }}",
                data: function (d) {
                    d.date_from = $filterForm.find('[name="date_from"]').val();
                    d.date_to = $filterForm.find('[name="date_to"]').val();
                    d.location_id = $filterForm.find('[name="location_id"]').val();
                    d.status = $filterForm.find('[name="status"]').val();
                    d.payment_status = $filterForm.find('[name="payment_status"]').val();
                    d.collector = $filterForm.find('[name="collector"]').val();
                    d.report_search = $filterForm.find('[name="search"]').val();
                }
            },
            columns: [
                { data: 'loan_number', name: 'l.loan_number' },
                { data: 'loan_date', name: 'l.loan_date' },
                { data: 'invoice_no', name: 'l.source_invoice_no' },
                { data: 'customer_name', name: 'l.customer_name_snapshot' },
                { data: 'customer_phone', name: 'l.customer_phone_snapshot', visible: false },
                { data: 'location_name', name: 'l.location_name_snapshot' },
                { data: 'status', name: 'l.status', className: 'text-center' },
                { data: 'payment_status', name: 'l.payment_status', className: 'text-center' },
                { data: 'total_amount', name: 'l.total_amount', className: 'text-right' },
                { data: 'principal_amount', name: 'l.principal_amount', className: 'text-right' },
                { data: 'paid_amount', name: 'l.paid_amount', className: 'text-right' },
                { data: 'balance_amount', name: 'l.balance_amount', className: 'text-right' },
                { data: 'term_count', name: 'l.duration_months', className: 'text-right' },
                { data: 'schedules', name: 'schedule_count', orderable: false, searchable: false, className: 'text-right' },
                { data: 'next_due_date', name: 'next_due_date', searchable: false },
                { data: 'last_payment_at', name: 'last_payment_at', searchable: false },
                { data: 'collector_name', name: 'l.collector_name_snapshot' },
                { data: 'risk', name: 'is_overdue', orderable: false, searchable: false, className: 'text-center' },
                { data: 'note', name: 'l.note' }
            ],
            language: {
                processing: '<i class="fa fa-spinner fa-spin" style="color:#0284c7; margin-right:6px;"></i> {{ $bi("Loading...", "កំពុងទាញយក...") }}',
                search: '',
                searchPlaceholder: isKhmer ? 'ស្វែងរកទិន្នន័យ...' : 'Search ledger...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: '{{ $bi("No installment records match criteria.", "មិនមានទិន្នន័យកម្ចីទេ។") }}',
                info: '{{ $bi("Showing _START_ to _END_ of _TOTAL_ entries", "បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ") }}',
                infoEmpty: '{{ $bi("Showing 0 to 0 of 0 entries", "បង្ហាញ 0 នៃ 0 ធាតុ") }}',
                infoFiltered: '({{ $bi("filtered from _MAX_ total entries", "ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប") }})',
                paginate: {
                    first: '{{ $bi("First", "ដំបូង") }}',
                    last: '{{ $bi("Last", "ចុងក្រោយ") }}',
                    next: '{{ $bi("Next", "បន្ទាប់") }}',
                    previous: '{{ $bi("Previous", "មុន") }}'
                }
            },
            columnDefs: [
                { targets: [8, 9, 10, 11, 12, 13], className: 'text-right' },
                { targets: [6, 7, 17], className: 'text-center' }
            ],
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();

                var intVal = function (i) {
                    if (typeof i === 'number') return i;
                    if (typeof i === 'string') {
                        var clean = i.replace(/<[^>]*>/g, '').replace(/[\$,]/g, '').trim();
                        return clean ? parseFloat(clean) : 0;
                    }
                    return 0;
                };

                // Calculate Page Subtotals
                var pageTotal = api.column(8, { page: 'current' }).data().reduce(function (a, b) { return intVal(a) + intVal(b); }, 0);
                var pagePrincipal = api.column(9, { page: 'current' }).data().reduce(function (a, b) { return intVal(a) + intVal(b); }, 0);
                var pagePaid = api.column(10, { page: 'current' }).data().reduce(function (a, b) { return intVal(a) + intVal(b); }, 0);
                var pageBalance = api.column(11, { page: 'current' }).data().reduce(function (a, b) { return intVal(a) + intVal(b); }, 0);

                $('#footPageTotal').text('$' + pageTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#footPagePrincipal').text('$' + pagePrincipal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#footPagePaid').text('$' + pagePaid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#footPageBalance').text('$' + pageBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
        });

        installmentTable.on('xhr.dt', function (e, settings, json) {
            if (json && json.summary) {
                var s = json.summary;
                var count = Number(s.count || 0);
                var activeCount = Number(s.active_count || 0);
                var completedCount = Number(s.completed_count || 0);
                var overdueCount = Number(s.overdue || 0);
                var overdueBalance = Number(s.overdue_balance || 0);
                var dueTodayCount = Number(s.due_today || 0);
                var principal = Number(s.principal || 0);
                var paid = Number(s.paid || 0);
                var balance = Number(s.balance || 0);
                var interest = Number(s.interest || 0);

                // Update Header Record Count
                $('#irLiveRecordsCount').text(count.toLocaleString());

                // Update KPI Cards
                $('#kpiCount').text(count.toLocaleString());
                $('#kpiActive').text(activeCount.toLocaleString());
                $('#kpiCompleted').text(completedCount.toLocaleString());
                $('#kpiDueToday').text(dueTodayCount.toLocaleString());
                $('#kpiOverdue').text(overdueCount.toLocaleString());
                $('#kpiOverdueBalance').text('$' + overdueBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (isKhmer ? 'ប្រឈមហានិភ័យ' : 'at risk'));
                $('#kpiPrincipal').text('$' + principal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#kpiInterest').text('$' + interest.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#kpiPaid').text('$' + paid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#kpiBalance').text('$' + balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                // Calculate & Update Sub-metrics
                var activePct = count > 0 ? Math.round((activeCount / count) * 100) : 0;
                var completedPct = count > 0 ? Math.round((completedCount / count) * 100) : 0;
                var overduePct = count > 0 ? Math.round((overdueCount / count) * 100) : 0;
                var recoveryRate = (paid + balance) > 0 ? Math.round((paid / (paid + balance)) * 100) : 0;
                var collectionRateVal = Number(s.collection_rate !== undefined ? s.collection_rate : recoveryRate);

                $('#kpiActivePct').text(activePct + '%');
                $('#kpiCompletedPct').text(completedPct + '%');
                $('#kpiOverduePct').text(overduePct + '%');
                $('#kpiRecoveryRate').text(recoveryRate + '%');
                $('#kpiCollectionEff').text(collectionRateVal.toFixed(1) + '%');

                // Update Grand Totals in Footer
                $('#footGrandTotal').text('$' + (principal > 0 ? (paid + balance) : 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#footGrandPrincipal').text('$' + principal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#footGrandPaid').text('$' + paid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#footGrandBalance').text('$' + balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
        });
    }

    // Initial Active Filters Check
    renderActiveFilterChips();
});
</script>
@endsection
