@extends('loanmanagement::layouts.app')
@section('title', 'Installment List')
@section('hide_breadcrumb', '1')

@section('loan_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* ==========================================================================
       MODERN LOAN MANAGEMENT DESIGN SYSTEM - ALL LOANS
       Colors automatically derive from system BusinessSettingsService (--lm-primary)
       ========================================================================== */
    :root {
        --lm-font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .lm-loan-list-shell {
        font-family: var(--lm-font-family);
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    /* --- HERO HEADER --- */
    .lm-loan-list-hero {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 26px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-left: 4px solid var(--lm-primary);
        box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.06);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .lm-loan-list-hero::before {
        display: none;
    }
    .lm-loan-list-hero h1 {
        margin: 0;
        color: #0f172a;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lm-loan-list-hero h1 i {
        display: inline-grid;
        place-items: center;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(var(--lm-primary-rgb), 0.1);
        color: var(--lm-primary);
        font-size: 18px;
        flex-shrink: 0;
    }
    .lm-loan-list-hero p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.5;
    }
    .lm-loan-list-hero-actions {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .lm-loan-list-hero-actions .btn-primary {
        background: linear-gradient(135deg, var(--lm-primary) 0%, var(--lm-primary-dark, var(--lm-primary)) 100%) !important;
        border: none !important;
        border-radius: 10px !important;
        padding: 9px 18px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(var(--lm-primary-rgb), 0.38) !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
    .lm-loan-list-hero-actions .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(var(--lm-primary-rgb), 0.48) !important;
        filter: brightness(1.05);
    }
    .lm-loan-list-hero-actions .btn-default {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 10px !important;
        padding: 9px 16px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #475569 !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
        transition: all 0.2s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
    .lm-loan-list-hero-actions .btn-default:hover {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
        transform: translateY(-1px);
    }

    /* --- STATISTIC CARDS --- */
    .lm-loan-list-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(200px, 1fr));
        gap: 16px;
    }
    .lm-loan-list-stat {
        position: relative;
        min-height: 105px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 16px;
        padding: 18px 20px;
        background: #ffffff;
        box-shadow: 0 4px 20px -3px rgba(15, 23, 42, 0.05), 0 0 0 1px rgba(0, 0, 0, 0.02);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.25s ease;
    }
    .lm-loan-list-stat:hover {
        transform: translateY(-3px);
        border-color: var(--stat-color, var(--lm-primary));
        box-shadow: 0 14px 30px -4px rgba(15, 23, 42, 0.1), 0 0 0 1px var(--stat-glow, rgba(var(--lm-primary-rgb), 0.15));
    }
    .lm-loan-list-stat::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--stat-color, var(--lm-primary));
        border-radius: 4px 0 0 4px;
    }
    .lm-loan-list-stat::after {
        content: "";
        position: absolute;
        right: -25px;
        top: -25px;
        width: 95px;
        height: 95px;
        border-radius: 50%;
        background: var(--stat-glow, rgba(var(--lm-primary-rgb), 0.1));
        pointer-events: none;
        filter: blur(8px);
    }
    .lm-loan-list-stat-icon {
        position: relative;
        z-index: 1;
        width: 48px;
        height: 48px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: var(--stat-bg, rgba(var(--lm-primary-rgb), 0.1));
        color: var(--stat-color, var(--lm-primary));
        font-size: 20px;
        border: 1px solid rgba(255, 255, 255, 0.85);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        flex-shrink: 0;
    }
    .lm-loan-list-stat-body {
        position: relative;
        z-index: 1;
        min-width: 0;
        flex: 1;
    }
    .lm-loan-list-stat span:not(.lm-loan-list-stat-icon) {
        color: #64748b;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        display: block;
    }
    .lm-loan-list-stat strong {
        display: block;
        margin-top: 5px;
        color: #0f172a;
        font-size: 26px;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }
    .lm-loan-list-stat small {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 100%;
        margin-top: 8px;
        padding: 3px 9px;
        border-radius: 999px;
        background: var(--stat-bg, #f1f5f9);
        color: #64748b;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        border: 1px solid rgba(0, 0, 0, 0.04);
    }
    .lm-loan-list-stat small::before {
        content: "";
        width: 6px;
        height: 6px;
        flex: 0 0 6px;
        border-radius: 50%;
        background: var(--stat-color, var(--lm-primary));
        box-shadow: 0 0 0 2px var(--stat-glow, rgba(var(--lm-primary-rgb), 0.2));
    }
    /* --- FILTER PANEL --- */
    .lm-loan-list-filter {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
        overflow: hidden;
        transition: all 0.25s ease;
    }
    .lm-loan-list-filter-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        user-select: none;
    }
    .lm-loan-list-filter.collapsed .lm-loan-list-filter-toggle {
        border-bottom: none;
    }
    .lm-loan-list-filter-toggle-label {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
    }
    .lm-loan-list-filter-toggle-label::before {
        content: "\f0b0";
        font-family: FontAwesome;
        color: var(--lm-primary);
        font-size: 14px;
    }
    .lm-loan-list-filter-toggle-actions {
        display: inline-flex;
        align-items: center;
        gap: 12px;
    }
    .lm-loan-list-reset {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        padding: 3px 8px;
        border-radius: 6px;
        transition: all 0.15s ease;
    }
    .lm-loan-list-reset:hover {
        color: #ef4444;
        background: #fef2f2;
    }
    .lm-loan-list-collapse-btn {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }
    .lm-loan-list-collapse-btn:hover {
        color: var(--lm-primary);
    }
    .lm-loan-list-filter-body {
        display: grid;
        grid-template-columns: minmax(260px, 1.4fr) repeat(4, minmax(150px, 1fr)) minmax(110px, 0.8fr);
        gap: 14px;
        padding: 18px 20px;
        align-items: flex-end;
    }
    .lm-loan-list-filter.collapsed .lm-loan-list-filter-body {
        display: none;
    }
    .lm-loan-list-field label {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 6px;
        display: block;
    }
    .lm-loan-list-field .form-control,
    .lm-loan-list-field .select2-container--default .select2-selection--single {
        height: 38px !important;
        border-radius: 10px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 13px !important;
        padding: 6px 12px !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .lm-loan-list-field .form-control:focus,
    .lm-loan-list-field .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--lm-primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--lm-primary-rgb), 0.15) !important;
        outline: none !important;
    }
    .lm-loan-list-field-actions .btn {
        height: 38px !important;
        border-radius: 10px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        background: linear-gradient(135deg, var(--lm-primary), var(--lm-primary-dark, var(--lm-primary))) !important;
        border: none !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(var(--lm-primary-rgb), 0.3) !important;
        transition: all 0.2s ease !important;
    }
    .lm-loan-list-field-actions .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(var(--lm-primary-rgb), 0.4) !important;
    }

    /* --- TABLE CARD & DATATABLES --- */
    .lm-loan-list-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.06), 0 0 0 1px rgba(0,0,0,0.02);
        padding: 14px;
        overflow: hidden;
    }
    .lm-loan-list-table-card .lm-dt-top {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
        margin-bottom: 12px !important;
        width: 100% !important;
    }
    .lm-loan-list-table-card .lm-dt-length {
        display: inline-flex !important;
        align-items: center !important;
        flex: 0 0 auto !important;
    }
    .lm-loan-list-table-card .dataTables_length {
        display: inline-flex !important;
        align-items: center !important;
        margin: 0 !important;
        float: none !important;
    }
    .lm-loan-list-table-card .dataTables_length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        margin: 0 !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #475569 !important;
        white-space: nowrap !important;
    }
    .lm-loan-list-table-card .dataTables_length select {
        display: inline-block !important;
        width: auto !important;
        min-width: 60px !important;
        height: 32px !important;
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 3px 8px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
        outline: none !important;
        cursor: pointer !important;
        transition: border-color 0.2s, box-shadow 0.2s !important;
    }
    .lm-loan-list-table-card .dataTables_length select:focus {
        border-color: var(--lm-primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--lm-primary-rgb), 0.15) !important;
    }

    .lm-loan-list-table-card .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 1 1 auto !important;
    }
    .lm-loan-list-table-card .dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
        float: none !important;
        margin: 0 !important;
    }
    .lm-loan-list-table-card .dt-buttons .btn,
    .lm-loan-list-table-card .dt-button {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        color: #475569 !important;
        font-size: 11.5px !important;
        font-weight: 700 !important;
        padding: 5px 9px !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
        transition: all 0.2s ease !important;
        white-space: nowrap !important;
    }
    .lm-loan-list-table-card .dt-buttons .btn:hover,
    .lm-loan-list-table-card .dt-button:hover {
        background: var(--lm-primary-50, #f0f4ff) !important;
        border-color: rgba(var(--lm-primary-rgb), 0.3) !important;
        color: var(--lm-primary) !important;
        transform: translateY(-1px);
    }

    .lm-loan-list-table-card .lm-dt-search {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        flex: 0 0 auto !important;
    }
    .lm-loan-list-table-card .dataTables_filter {
        display: inline-flex !important;
        align-items: center !important;
        margin: 0 !important;
        float: none !important;
        text-align: right;
    }
    .lm-loan-list-table-card .dataTables_filter label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        margin: 0 !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #475569 !important;
        white-space: nowrap !important;
    }
    .lm-loan-list-table-card .dataTables_filter input {
        width: 190px !important;
        height: 32px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 5px 10px !important;
        font-size: 12px !important;
        outline: none !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
        transition: all 0.2s ease !important;
        margin-left: 0 !important;
    }
    .lm-loan-list-table-card .dataTables_filter input:focus {
        border-color: var(--lm-primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--lm-primary-rgb), 0.15) !important;
    }

    /* --- THE TABLE ITSELF --- */
    .lm-loan-list-table-card #loan_list_table {
        border-collapse: separate !important;
        border-spacing: 0;
        width: 100% !important;
        border: none !important;
        table-layout: fixed;
    }
    .lm-loan-list-table-card #loan_list_table thead th {
        background: #f8fafc !important;
        color: #334155 !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0 !important;
        line-height: 1.2 !important;
        padding: 9px 8px !important;
        border: none !important;
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 2px solid #cbd5e1 !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
        transition: all 0.15s ease !important;
    }
    .lm-loan-list-table-card #loan_list_table thead th:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
    }
    .lm-loan-list-table-card #loan_list_table thead th:first-child {
        border-top-left-radius: 10px;
    }
    .lm-loan-list-table-card #loan_list_table thead th:last-child {
        border-top-right-radius: 10px;
    }
    .lm-loan-list-table-card #loan_list_table tbody td {
        padding: 8px 8px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
        border-bottom: none !important;
        border-left: none !important;
        border-right: none !important;
        font-size: 12px;
        line-height: 1.25;
        color: #334155;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-loan-list-table-card #loan_list_table tbody tr:nth-child(even) td {
        background: #fbfcfe;
    }
    .lm-loan-list-table-card #loan_list_table tbody tr:hover td {
        background: rgba(var(--lm-primary-rgb), 0.05) !important;
    }

    /* --- STATUS SELECT BADGE PILLS --- */
    .loan-status-select {
        appearance: none;
        -webkit-appearance: none;
        padding: 4px 20px 4px 9px !important;
        border-radius: 14px !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        line-height: 1.2 !important;
        border: 1px solid transparent !important;
        cursor: pointer;
        background-repeat: no-repeat;
        background-position: right 7px center;
        background-size: 8px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2364748b'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E");
        transition: all 0.2s ease;
    }
    .loan-status-select.status-success,
    .label-success {
        background-color: #ecfdf5 !important;
        color: #065f46 !important;
        border-color: #a7f3d0 !important;
    }
    .loan-status-select.status-primary,
    .label-primary {
        background-color: rgba(var(--lm-primary-rgb), 0.09) !important;
        color: var(--lm-primary-dark, var(--lm-primary)) !important;
        border-color: rgba(var(--lm-primary-rgb), 0.3) !important;
    }
    .loan-status-select.status-warning,
    .label-warning {
        background-color: #fffbeb !important;
        color: #92400e !important;
        border-color: #fde68a !important;
    }
    .loan-status-select.status-danger,
    .label-danger {
        background-color: #fef2f2 !important;
        color: #991b1b !important;
        border-color: #fecaca !important;
    }
    .loan-status-select.status-info,
    .label-info {
        background-color: #f0f9ff !important;
        color: #075985 !important;
        border-color: #bae6fd !important;
    }
    .loan-status-select.status-default,
    .label-default {
        background-color: #f1f5f9 !important;
        color: #475569 !important;
        border-color: #cbd5e1 !important;
    }

    /* --- ACTION BUTTON DROPDOWN --- */
    .lm-loan-list-table-card #loan_list_table th.no-export,
    .lm-loan-list-table-card #loan_list_table td.lm-action-col {
        position: sticky;
        left: 0;
        z-index: 3;
        width: 62px !important;
        min-width: 62px !important;
        max-width: 62px !important;
        text-align: center !important;
        background: #ffffff !important;
        box-shadow: 1px 0 0 #e2e8f0;
    }
    .lm-loan-list-table-card #loan_list_table th.no-export {
        z-index: 5;
        background: #f8fafc !important;
    }
    .lm-loan-list-table-card .btn-group .btn-primary.js-loan-action-toggle {
        background: #ffffff !important;
        border: 1px solid rgba(var(--lm-primary-rgb), 0.35) !important;
        border-radius: 8px !important;
        width: 38px;
        height: 32px;
        justify-content: center;
        padding: 5px 6px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        color: var(--lm-primary) !important;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06) !important;
        transition: all 0.2s ease !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-loan-list-table-card td.lm-action-col .js-loan-action-toggle .hidden-xs,
    .lm-loan-list-table-card td.lm-action-col .js-loan-action-toggle .caret {
        display: none !important;
    }
    .lm-loan-list-table-card .btn-group .btn-primary.js-loan-action-toggle:hover,
    .lm-loan-list-table-card .lm-loan-action-menu.open .btn-primary.js-loan-action-toggle {
        box-shadow: 0 4px 12px rgba(var(--lm-primary-rgb), 0.4) !important;
        transform: translateY(-1px);
        background: var(--lm-primary) !important;
        color: #ffffff !important;
    }
    .lm-loan-list-table-card .dropdown-menu {
        border-radius: 12px !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0,0,0,0.05) !important;
        border: 1px solid #e2e8f0 !important;
        padding: 6px !important;
        min-width: 178px !important;
        z-index: 10080 !important;
    }
    .lm-loan-action-menu.open {
        z-index: 10080;
    }
    .lm-loan-action-dropdown.is-floating {
        display: block !important;
        position: fixed !important;
        right: auto !important;
        max-width: min(240px, calc(100vw - 24px));
        max-height: min(420px, calc(100vh - 24px));
        overflow-y: auto;
    }
    .lm-loan-list-table-card .dropdown-menu > li > a {
        border-radius: 6px !important;
        padding: 6px 10px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #334155 !important;
        display: flex !important;
        align-items: center !important;
        gap: 7px !important;
        transition: all 0.15s ease !important;
    }
    .lm-loan-list-table-card .dropdown-menu > li > a:hover {
        background: var(--lm-primary-50, #f0f4ff) !important;
        color: var(--lm-primary) !important;
    }

    /* --- DATATABLES PAGINATION --- */
    .lm-loan-list-table-card .pagination > .active > a,
    .lm-loan-list-table-card .pagination > .active > a:hover,
    .lm-loan-list-table-card .pagination > .active > span {
        background: var(--lm-primary) !important;
        border-color: var(--lm-primary) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        border-radius: 8px !important;
    }
    .lm-loan-list-table-card .pagination > li > a {
        border-radius: 8px !important;
        margin: 0 2px !important;
        color: #475569 !important;
        border-color: #e2e8f0 !important;
        font-weight: 600 !important;
    }
    .lm-loan-list-table-card .pagination > li > a:hover {
        background: var(--lm-primary-50, #f0f4ff) !important;
        color: var(--lm-primary) !important;
    }

    /* --- MOBILE SECTION TABS --- */
    .lm-mobile-section-tabs {
        display: none;
        gap: 8px;
        margin-bottom: 12px;
    }
    .lm-mobile-section-tabs a {
        padding: 8px 16px;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-mobile-section-tabs a.active {
        background: var(--lm-primary);
        color: #ffffff;
        border-color: var(--lm-primary);
        box-shadow: 0 4px 12px rgba(var(--lm-primary-rgb), 0.3);
    }

    /* --- COLUMN SPECIFIC STYLING --- */
    .lm-loan-customer-cell,
    .lm-loan-product-wrap {
        min-width: 0;
        max-width: 205px;
    }
    .lm-loan-customer-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .lm-loan-customer-avatar,
    .lm-loan-customer-avatar-fallback {
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
    }
    .lm-loan-customer-avatar {
        display: block;
        object-fit: cover;
        background: #f8fafc;
    }
    .lm-loan-customer-avatar-fallback {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--lm-primary-rgb), 0.1);
        color: var(--lm-primary);
        font-size: 12px;
        font-weight: 800;
    }
    .lm-loan-customer-info {
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .lm-loan-customer-name,
    .lm-loan-product-cell {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #0f172a;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-loan-customer-name:hover {
        color: var(--lm-primary);
    }
    .lm-loan-customer-phone,
    .lm-loan-product-price,
    .lm-loan-product-imei {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 3px;
        color: #64748b;
        font-size: 10.5px;
        font-weight: 700;
        text-decoration: none !important;
        white-space: nowrap;
    }
    .lm-loan-product-price {
        color: #0284c7;
    }
    .lm-loan-list-table-card #loan_list_table th:nth-child(1),
    .lm-loan-list-table-card #loan_list_table td:nth-child(1) { width: 62px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(2),
    .lm-loan-list-table-card #loan_list_table td:nth-child(2) { width: 124px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(3),
    .lm-loan-list-table-card #loan_list_table td:nth-child(3) { width: 94px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(4),
    .lm-loan-list-table-card #loan_list_table td:nth-child(4) { width: 160px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(5),
    .lm-loan-list-table-card #loan_list_table td:nth-child(5) { width: 205px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(6),
    .lm-loan-list-table-card #loan_list_table td:nth-child(6),
    .lm-loan-list-table-card #loan_list_table th:nth-child(9),
    .lm-loan-list-table-card #loan_list_table td:nth-child(9),
    .lm-loan-list-table-card #loan_list_table th:nth-child(10),
    .lm-loan-list-table-card #loan_list_table td:nth-child(10),
    .lm-loan-list-table-card #loan_list_table th:nth-child(11),
    .lm-loan-list-table-card #loan_list_table td:nth-child(11),
    .lm-loan-list-table-card #loan_list_table th:nth-child(12),
    .lm-loan-list-table-card #loan_list_table td:nth-child(12) { width: 88px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(7),
    .lm-loan-list-table-card #loan_list_table td:nth-child(7) { width: 76px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(8),
    .lm-loan-list-table-card #loan_list_table td:nth-child(8) { width: 92px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(13),
    .lm-loan-list-table-card #loan_list_table td:nth-child(13) { width: 86px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(14),
    .lm-loan-list-table-card #loan_list_table td:nth-child(14) { width: 112px !important; }
    .lm-loan-list-table-card #loan_list_table th:nth-child(15),
    .lm-loan-list-table-card #loan_list_table td:nth-child(15),
    .lm-loan-list-table-card #loan_list_table th:nth-child(16),
    .lm-loan-list-table-card #loan_list_table td:nth-child(16) { width: 118px !important; }
    .lm-loan-product-imei,
    .lm-loan-product-price {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .lm-next-due-cell {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        max-width: 100%;
        white-space: nowrap;
    }
    .lm-next-due-cell strong {
        color: #334155;
        font-size: 11.5px;
        font-weight: 800;
        line-height: 1.15;
    }
    .lm-next-due-cell small {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.1;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .lm-next-due-overdue strong,
    .lm-next-due-overdue small {
        color: #dc2626;
    }
    .lm-next-due-today strong,
    .lm-next-due-today small {
        color: #d97706;
    }
    .lm-next-due-upcoming small {
        color: #2563eb;
    }
    .lm-next-due-paid strong,
    .lm-next-due-paid small {
        color: #059669;
    }
    .lm-next-due-empty strong,
    .lm-next-due-empty small {
        color: #94a3b8;
    }
    #loan_list_table tbody td:nth-child(2) {
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        color: var(--lm-primary);
        white-space: nowrap;
    }
    #loan_list_table tbody td:nth-child(4) {
        font-weight: 700;
        color: #0f172a;
    }
    #loan_list_table tbody td:nth-child(6) {
        font-weight: 700;
        color: #0284c7;
        text-align: right !important;
    }
    #loan_list_table tbody td:nth-child(9) {
        font-weight: 700;
        color: #475569;
        text-align: right !important;
    }
    #loan_list_table tbody td:nth-child(10) {
        font-weight: 700;
        color: #1e293b;
        text-align: right !important;
    }
    #loan_list_table tbody td:nth-child(11) {
        font-weight: 700;
        color: #059669;
        text-align: right !important;
    }
    #loan_list_table tbody td:nth-child(12) {
        font-weight: 800;
        color: #d97706;
        text-align: right !important;
    }
    #loan_list_table thead th:nth-child(6),
    #loan_list_table thead th:nth-child(9),
    #loan_list_table thead th:nth-child(10),
    #loan_list_table thead th:nth-child(11),
    #loan_list_table thead th:nth-child(12) {
        text-align: right !important;
    }

    /* --- STATUS QUICK-FILTER CARDS GRID --- */
    .lm-status-cards-grid {
        display: grid;
        grid-template-columns: repeat(8, minmax(110px, 1fr));
        gap: 12px;
    }
    .lm-status-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 13px 15px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        overflow: hidden;
    }
    .lm-status-card:hover {
        transform: translateY(-2px);
        border-color: var(--card-color, var(--lm-primary));
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    }
    .lm-status-card.active {
        background: #ffffff;
        border-color: var(--card-color, var(--lm-primary));
        box-shadow: 0 0 0 2px var(--card-color, var(--lm-primary)), 0 8px 22px -4px rgba(15, 23, 42, 0.12);
        transform: translateY(-2px);
    }
    .lm-status-card-indicator {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3.5px;
        background: transparent;
        transition: background 0.2s;
    }
    .lm-status-card.active .lm-status-card-indicator {
        background: var(--card-color, var(--lm-primary));
    }
    .lm-status-card-icon {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: var(--card-bg, rgba(var(--lm-primary-rgb), 0.08));
        color: var(--card-color, var(--lm-primary));
        font-size: 16px;
        flex-shrink: 0;
        transition: all 0.2s;
    }
    .lm-status-card:hover .lm-status-card-icon,
    .lm-status-card.active .lm-status-card-icon {
        transform: scale(1.08);
    }
    .lm-status-card-content {
        min-width: 0;
        flex: 1;
    }
    .lm-status-card-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-status-card.active .lm-status-card-label {
        color: #0f172a;
        font-weight: 800;
    }
    .lm-status-card-count {
        display: block;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        margin-top: 3px;
    }
    .lm-status-card.active .lm-status-card-count {
        color: var(--card-color, var(--lm-primary));
    }

    /* Status Card Themes */
    .status-card-all {
        --card-color: var(--lm-primary);
        --card-bg: rgba(var(--lm-primary-rgb), 0.1);
    }
    .status-card-pending {
        --card-color: #f59e0b;
        --card-bg: #fffbeb;
    }
    .status-card-approved {
        --card-color: #0284c7;
        --card-bg: #f0f9ff;
    }
    .status-card-active {
        --card-color: var(--lm-primary);
        --card-bg: rgba(var(--lm-primary-rgb), 0.1);
    }
    .status-card-completed {
        --card-color: #10b981;
        --card-bg: #ecfdf5;
    }
    .status-card-rejected {
        --card-color: #ef4444;
        --card-bg: #fef2f2;
    }
    .status-card-cancelled {
        --card-color: #64748b;
        --card-bg: #f1f5f9;
    }
    .status-card-blacklist {
        --card-color: #e11d48;
        --card-bg: #fff1f2;
        text-decoration: none !important;
    }
    .status-card-blacklist .lm-status-card-count {
        color: #e11d48 !important;
    }
    .status-card-blacklist:hover {
        border-color: #e11d48 !important;
        box-shadow: 0 8px 20px rgba(225, 29, 72, 0.18) !important;
        transform: translateY(-2px);
    }

    @media (max-width: 1400px) {
        .lm-status-cards-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    @media (max-width: 992px) {
        .lm-status-cards-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 640px) {
        .lm-status-cards-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* --- RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 1200px) {
        .lm-loan-list-stats {
            grid-template-columns: repeat(2, minmax(180px, 1fr));
        }
        .lm-loan-list-filter-body {
            grid-template-columns: repeat(2, minmax(180px, 1fr));
        }
    }
    @media (max-width: 768px) {
        .lm-mobile-section-tabs {
            display: flex;
        }
        .lm-loan-list-hero {
            align-items: flex-start;
            flex-direction: column;
            padding: 16px;
        }
        .lm-loan-list-hero-actions {
            justify-content: flex-start;
            width: 100%;
        }
        .lm-loan-list-stats {
            grid-template-columns: 1fr;
        }
        .lm-loan-list-filter-body {
            grid-template-columns: 1fr;
        }
        .lm-loan-list-table-card .lm-dt-top {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 12px !important;
        }
        .lm-loan-list-table-card .lm-dt-length,
        .lm-loan-list-table-card .lm-dt-buttons,
        .lm-loan-list-table-card .lm-dt-search {
            width: 100% !important;
            justify-content: space-between !important;
        }
        .lm-loan-list-table-card .dataTables_filter {
            text-align: left;
            width: 100% !important;
        }
        .lm-loan-list-table-card .dataTables_filter label {
            width: 100% !important;
            display: flex !important;
        }
        .lm-loan-list-table-card .dataTables_filter input {
            width: 100% !important;
            margin-left: 0 !important;
            margin-top: 6px !important;
            flex: 1 1 auto !important;
        }
    }
</style>
@endsection

@section('content_body')
@php
    $isKhmer = session('user.language', config('app.locale')) === 'km';
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
@endphp
<section class="content no-print">
    <div class="lm-mobile-section-tabs">
        <a href="{{ route('loan-management.loans') }}" class="active">
            <i class="fa fa-credit-card"></i> {{ $text('Installments', 'កម្ចី') }}
        </a>
        <a href="{{ route('loan-management.monthly-payments.index') }}">
            <i class="fa fa-money"></i> {{ $text('Collection', 'ការប្រមូលប្រាក់') }}
        </a>
    </div>

    <div class="lm-loan-list-shell">
        {{-- HERO HEADER --}}
        <div class="lm-loan-list-hero">
            <div>
                <h1><i class="fa fa-credit-card"></i> {{ $text('All Installments', 'កិច្ចសន្យាបង់រំលស់ទាំងអស់') }}</h1>
                <p>{{ $text('Manage installment agreements, track repayments, approval statuses, and customer balances.', 'គ្រប់គ្រងកិច្ចព្រមព្រៀងបង់រំលស់ តាមដានការទូទាត់ប្រាក់ ស្ថានភាពអនុម័ត និងសមតុល្យអតិថិជន') }}</p>
            </div>
            <div class="lm-loan-list-hero-actions">
                @if(Route::has('loan-management.loans.calculator'))
                    <a href="{{ route('loan-management.loans.calculator') }}" class="btn btn-default" target="_blank">
                        <i class="fa fa-calculator text-primary"></i> {{ $text('Calculator', 'ម៉ាស៊ីនគណនា') }}
                    </a>
                @endif
                <a href="{{ route('loan-management.reports.index') }}" class="btn btn-default">
                    <i class="fa fa-bar-chart text-info"></i> {{ $text('Reports', 'របាយការណ៍') }}
                </a>
                @if(Route::has('loan-management.loans.create-standalone-modal') && \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
                    <button type="button" class="btn btn-primary lm-standalone-loan-trigger"
                            data-url="{{ route('loan-management.loans.create-standalone-modal') }}"
                            data-target="#standaloneLoanModal">
                        <i class="fa fa-plus-circle"></i> {{ $text('New Installment', 'បង្កើតកិច្ចព្រមព្រៀងថ្មី') }}
                    </button>
                @elseif(Route::has('loan-management.loans.create-standalone'))
                    <a href="{{ route('loan-management.loans.create-standalone') }}" class="btn btn-primary">
                        <i class="fa fa-plus-circle"></i> {{ $text('New Installment', 'បង្កើតកិច្ចព្រមព្រៀងថ្មី') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- STATUS QUICK-FILTER CARDS --}}
        <div class="lm-status-cards-grid">
            <div class="lm-status-card status-card-all active" data-status="" title="{{ $text('Show All Installments', 'បង្ហាញកម្ចីទាំងអស់') }}">
                <div class="lm-status-card-icon"><i class="fa fa-list-alt"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('All Agreements', 'កិច្ចសន្យាទាំងអស់') }}</span>
                    <strong class="lm-status-card-count" id="count_all">{{ $statusCounts['all'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <div class="lm-status-card status-card-pending" data-status="pending" title="{{ $text('Filter by Pending', 'ច្រោះតាមកំពុងរង់ចាំ') }}">
                <div class="lm-status-card-icon"><i class="fa fa-clock-o"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Pending', 'កំពុងរង់ចាំ') }}</span>
                    <strong class="lm-status-card-count" id="count_pending">{{ $statusCounts['pending'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <div class="lm-status-card status-card-approved" data-status="approved" title="{{ $text('Filter by Approved', 'ច្រោះតាមបានអនុម័ត') }}">
                <div class="lm-status-card-icon"><i class="fa fa-thumbs-o-up"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Approved', 'បានអនុម័ត') }}</span>
                    <strong class="lm-status-card-count" id="count_approved">{{ $statusCounts['approved'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <div class="lm-status-card status-card-active" data-status="active" title="{{ $text('Filter by Active', 'ច្រោះតាមកំពុងដំណើរការ') }}">
                <div class="lm-status-card-icon"><i class="fa fa-bolt"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Active', 'កំពុងដំណើរការ') }}</span>
                    <strong class="lm-status-card-count" id="count_active">{{ $statusCounts['active'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <div class="lm-status-card status-card-completed" data-status="completed" title="{{ $text('Filter by Completed', 'ច្រោះតាមបានបញ្ចប់') }}">
                <div class="lm-status-card-icon"><i class="fa fa-check-circle-o"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Completed', 'បានបញ្ចប់') }}</span>
                    <strong class="lm-status-card-count" id="count_completed">{{ $statusCounts['completed'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <div class="lm-status-card status-card-rejected" data-status="rejected" title="{{ $text('Filter by Rejected', 'ច្រោះតាមបានបដិសេធ') }}">
                <div class="lm-status-card-icon"><i class="fa fa-times-circle-o"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Rejected', 'បានបដិសេធ') }}</span>
                    <strong class="lm-status-card-count" id="count_rejected">{{ $statusCounts['rejected'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <div class="lm-status-card status-card-cancelled" data-status="cancelled" title="{{ $text('Filter by Cancelled', 'ច្រោះតាមបានបោះបង់') }}">
                <div class="lm-status-card-icon"><i class="fa fa-ban"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Cancelled', 'បានបោះបង់') }}</span>
                    <strong class="lm-status-card-count" id="count_cancelled">{{ $statusCounts['cancelled'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </div>
            <a href="{{ url('loan-management/blacklist') }}" class="lm-status-card status-card-blacklist" title="{{ $text('View Blacklist', 'មើលបញ្ជីខ្មៅ') }}">
                <div class="lm-status-card-icon"><i class="fa fa-user-times"></i></div>
                <div class="lm-status-card-content">
                    <span class="lm-status-card-label">{{ $text('Blacklist', 'បញ្ជីខ្មៅ') }}</span>
                    <strong class="lm-status-card-count" id="count_blacklist">{{ $statusCounts['blacklist'] ?? 0 }}</strong>
                </div>
                <div class="lm-status-card-indicator"></div>
            </a>
        </div>

        <div class="lm-loan-list-filter collapsed" id="loanFilterPanel">
            <div class="lm-loan-list-filter-toggle">
                <span class="lm-loan-list-filter-toggle-label">{{ $text('Filters', 'តម្រង') }}</span>
                <span class="lm-loan-list-filter-toggle-actions">
                    <a href="javascript:void(0)" id="loanFilterReset" class="lm-loan-list-reset">{{ $text('Reset', 'កំណត់ឡើងវិញ') }}</a>
                    <button type="button" class="lm-loan-list-collapse-btn" id="loanFilterToggle" aria-expanded="false" aria-controls="loanFilterBody">
                        <span id="loanFilterToggleText">{{ $text('Expand', 'ពង្រីក') }}</span>
                        <i class="fa fa-chevron-down" id="loanFilterToggleIcon" aria-hidden="true"></i>
                    </button>
                </span>
            </div>
            <div class="lm-loan-list-filter-body" id="loanFilterBody">
            <div class="lm-loan-list-field date-range-field">
                {!! Form::label('allLoansDateRange', $text('Date Range', 'ចន្លោះកាលបរិច្ឆេទ')) !!}
                {!! Form::text('date_range', null, ['id' => 'allLoansDateRange', 'placeholder' => $text('Select date range', 'ជ្រើសរើសចន្លោះកាលបរិច្ឆេទ'), 'class' => 'form-control', 'readonly', 'autocomplete' => 'off']) !!}
                <input type="hidden" id="date_from" name="date_from">
                <input type="hidden" id="date_to" name="date_to">
            </div>
            <div class="lm-loan-list-field">
                <label for="status">{{ $text('Status', 'ស្ថានភាព') }}</label>
                <select id="status" class="form-control select2" style="width:100%">
                    <option value="">{{ $text('All Statuses', 'ស្ថានភាពទាំងអស់') }}</option>
                    <option value="draft">{{ $text('Draft', 'ព្រាង') }}</option>
                    <option value="pending">{{ $text('Pending', 'កំពុងរង់ចាំ') }}</option>
                    <option value="approved">{{ $text('Approved', 'បានអនុម័ត') }}</option>
                    <option value="active">{{ $text('Active', 'កំពុងដំណើរការ') }}</option>
                    <option value="completed">{{ $text('Completed', 'បានបញ្ចប់') }}</option>
                    <option value="rejected">{{ $text('Rejected', 'បានបដិសេធ') }}</option>
                    <option value="cancelled">{{ $text('Cancelled', 'បានបោះបង់') }}</option>
                    <option value="defaulted">{{ $text('Defaulted', 'ខូចបំណុល') }}</option>
                </select>
            </div>
            <div class="lm-loan-list-field">
                <label for="location_name">{{ $text('Location', 'សាខា') }}</label>
                {!! Form::select('location_name', $locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => $text('All Locations', 'សាខាទាំងអស់'), 'id' => 'location_name']) !!}
            </div>
            <div class="lm-loan-list-field">
                <label for="collector_name">{{ $text('Collector', 'អ្នកប្រមូលប្រាក់') }}</label>
                {!! Form::select('collector_name', $collectors, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => $text('All Collectors', 'អ្នកប្រមូលប្រាក់ទាំងអស់'), 'id' => 'collector_name']) !!}
            </div>
            <div class="lm-loan-list-field">
                <label for="customer">{{ $text('Customer', 'អតិថិជន') }}</label>
                <input id="customer" class="form-control" placeholder="{{ $text('Customer name', 'ឈ្មោះអតិថិជន') }}">
            </div>
            <div class="lm-loan-list-field lm-loan-list-field-actions">
                <button type="button" class="btn btn-primary btn-block" id="loanFilterApply">
                    <i class="fa fa-filter"></i> {{ $text('Apply', 'អនុវត្ត') }}
                </button>
            </div>
            </div>
        </div>

        <div class="lm-loan-list-table-card">
            <div class="lm-mobile-loan-list" id="loan_mobile_list">
                <div class="text-center text-muted" style="padding: 16px;">{{ $text('Loading loans...', 'កំពុងផ្ទុកកម្ចី...') }}</div>
            </div>
            <table class="table table-bordered table-striped" id="loan_list_table" width="100%">
                <thead>
                    <tr>
                        <th class="no-export">{{ $text('Action', 'សកម្មភាព') }}</th>
                        <th>{{ $text('Installment #', 'លេខកម្ចី') }}</th>
                        <th>{{ $text('Date', 'កាលបរិច្ឆេទ') }}</th>
                        <th>{{ $text('Customer', 'អតិថិជន') }}</th>
                        <th>{{ $text('Product', 'ទំនិញ/ផលិតផល') }}</th>
                        <th>{{ $text('Customer Deposit', 'ប្រាក់កក់អតិថិជន') }}</th>
                        <th>{{ $text('Terms', 'រយៈពេល') }}</th>
                        <th>{{ $text('Next Due', 'ថ្ងៃត្រូវបង់បន្ទាប់') }}</th>
                        <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $text('Total Amount', 'ចំនួនសរុប') }}</th>
                        <th>{{ $text('Paid', 'បានបង់') }}</th>
                        <th>{{ $text('Balance', 'សមតុល្យ') }}</th>
                        <th>{{ $text('Progress', 'វឌ្ឍនភាព') }}</th>
                        <th>{{ $text('Status', 'ស្ថានភាព') }}</th>
                        <th>{{ $text('Location', 'សាខា') }}</th>
                        <th>{{ $text('Collector', 'អ្នកប្រមូល') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="loanAddBlacklistModal" tabindex="-1" role="dialog" aria-labelledby="loanAddBlacklistModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
            <form method="POST" action="" id="loanAddBlacklistForm">
                @csrf
                <input type="hidden" name="blacklist_status" value="1">
                <div class="modal-header" style="background: #fff1f2; border-bottom: 1px solid #fecdd3;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="loanAddBlacklistModalLabel" style="color: #be123c; font-weight: 800;">
                        <i class="fa fa-user-times"></i> {{ $text('Add Customer to Blacklist', 'ដាក់អតិថិជនក្នុងបញ្ជីខ្មៅ') }}
                    </h4>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 12px; color: #475569;">
                        {{ $text('Customer', 'អតិថិជន') }}:
                        <strong id="loanBlacklistCustomerName" style="color: #0f172a;"></strong>
                    </p>
                    <div class="form-group">
                        <label for="loanBlacklistReason" style="font-weight: 700;">{{ $text('Blacklist Reason', 'មូលហេតុបញ្ជីខ្មៅ') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="blacklist_reason" id="loanBlacklistReason" rows="3" required placeholder="{{ $text('Describe default behavior, refusal to pay, fraudulent document, etc.', 'ពិពណ៌នាអំពីអាកប្បកិរិយាយឺតយ៉ាវ បដិសេធមិនបង់ប្រាក់ ក្លែងបន្លំឯកសារ...') }}" style="border-radius: 8px;"></textarea>
                    </div>
                    <div class="alert alert-warning" style="margin-bottom: 0; border-radius: 8px;">
                        <i class="fa fa-warning"></i>
                        {{ $text('This customer will be restricted from applying for new installment loans.', 'អតិថិជននេះនឹងត្រូវបានរារាំងពីការស្នើសុំកម្ចីរំលស់ថ្មី។') }}
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ $text('Cancel', 'បោះបង់') }}</button>
                    <button type="submit" class="btn btn-danger" style="font-weight: 700;">
                        <i class="fa fa-ban"></i> {{ $text('Confirm Blacklist', 'បញ្ជាក់បញ្ជីខ្មៅ') }}
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
    if ($.fn.select2) {
        $('.select2').select2();
    }
    var loanBaseUrl = "/loan-management/loans";
    var loanDateFormat = typeof moment_date_format !== 'undefined' ? moment_date_format : 'YYYY-MM-DD';
    var loanListText = {
        processing: @json($text('Loading loans...', 'កំពុងផ្ទុកកម្ចី...')),
        search: @json($text('Search', 'ស្វែងរក')),
        lengthMenu: @json($text('Show _MENU_ loans', 'បង្ហាញ _MENU_ កម្ចី')),
        info: @json($text('Showing _START_ to _END_ of _TOTAL_ loans', 'បង្ហាញ _START_ ដល់ _END_ នៃ _TOTAL_ កម្ចី')),
        infoEmpty: @json($text('No loans to show', 'មិនមានកម្ចីសម្រាប់បង្ហាញ')),
        emptyTable: @json($text('No loans found for the selected filters.', 'រកមិនឃើញកម្ចីសម្រាប់តម្រងដែលបានជ្រើស។')),
        zeroRecords: @json($text('No matching loans found.', 'រកមិនឃើញកម្ចីដែលត្រូវគ្នា។')),
        paginateNext: @json($text('Next', 'បន្ទាប់')),
        paginatePrevious: @json($text('Previous', 'មុន')),
        statusUpdated: @json($text('Status updated', 'បានធ្វើបច្ចុប្បន្នភាពស្ថានភាព')),
        statusFailed: @json($text('Failed to update status', 'ធ្វើបច្ចុប្បន្នភាពស្ថានភាពមិនបានសម្រេច')),
        deleteConfirm: @json($text('Delete this loan?', 'លុបកម្ចីនេះឬ?')),
        deleteFailed: @json($text('Failed to delete loan.', 'លុបកម្ចីមិនបានសម្រេច។')),
        noLoans: @json($text('No loans found.', 'រកមិនឃើញកម្ចី។')),
        copied: @json($text('Copied loan information', 'បានចម្លងព័ត៌មានកម្ចី')),
        copyFailed: @json($text('Unable to copy loan information', 'មិនអាចចម្លងព័ត៌មានកម្ចីបានទេ')),
        customer: @json($text('Customer', 'អតិថិជន')),
        phone: @json($text('Phone', 'ទូរស័ព្ទ')),
        location: @json($text('Location', 'សាខា')),
        collector: @json($text('Collector', 'អ្នកប្រមូល')),
        principal: @json($text('Principal', 'ប្រាក់ដើម')),
        paid: @json($text('Paid', 'បានបង់')),
        balance: @json($text('Balance', 'សមតុល្យ')),
        view: @json($text('View', 'មើល')),
        pay: @json($text('Pay', 'បង់ប្រាក់')),
        telegram: @json($text('Telegram', 'តេឡេក្រាម')),
        connectTelegram: @json($text('Connect Telegram', 'ភ្ជាប់ Telegram')),
        addBlacklist: @json($text('Blacklist', 'បញ្ជីខ្មៅ')),
        blacklistReasonRequired: @json($text('Please enter a blacklist reason.', 'សូមបញ្ចូលមូលហេតុបញ្ជីខ្មៅ។'))
    };

    function plainText(value) {
        return $('<div>').html(value || '').text().trim() || '-';
    }

function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function formatLmExpiry(value) {
        if (!value) {
            return '';
        }
        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return String(value);
        }
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function copyLoanText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        var deferred = $.Deferred();
        var textarea = document.createElement('textarea');
        textarea.value = text || '';
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            deferred.resolve();
        } catch (e) {
            deferred.reject(e);
        }

        document.body.removeChild(textarea);
        return deferred.promise();
    }

    function debounce(fn, wait) {
        var timer = null;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(context, args);
            }, wait);
        };
    }

    function mobileLoanCard(row) {
        var id = row.id || '';
        var customerId = row.customer_id || '';
        var telegramLinked = !!row.telegram_chat_id;
        var loanNumber = plainText(row.loan_number);
        var customer = plainText(row.customer_name_snapshot);
        var phone = plainText(row.customer_phone_snapshot);
        var product = row.product_name_snapshot ? plainText(row.product_name_snapshot) : '';
        var nextDue = row.next_due_date || '';
        var date = plainText(row.loan_date);
        var statusText = plainText(row.status).toLowerCase();
        var statusClass = statusText.replace(/[^a-z0-9_-]+/g, '-');
        var location = plainText(row.location_name_snapshot);
        var collector = plainText(row.collector_name_snapshot);
        var principal = plainText(row.principal_amount);
        var paid = plainText(row.paid_amount);
        var balance = plainText(row.balance_amount);
        var viewUrl = loanBaseUrl + '/' + id + '/view';
        var quickPayUrl = loanBaseUrl + '/' + id + '/payment/quick-pay';
        var telegramUrl = customerId ? "/loan-management/customers/" + customerId + "/telegram/link" : '';
        var blacklistUrl = customerId ? "/loan-management/customers/" + customerId + "/blacklist" : '';

        return ''
            + '<article class="lm-mobile-loan-card">'
            + '  <div class="lm-mobile-loan-card-header">'
            + '    <div><div class="lm-mobile-loan-card-title">' + escapeHtml(loanNumber) + '</div><div class="lm-mobile-loan-card-date">' + escapeHtml(date) + '</div></div>'
            + '    <span class="lm-mobile-loan-card-status status-' + escapeHtml(statusClass) + '">' + escapeHtml(statusText || 'status') + '</span>'
            + '  </div>'
            + '  <div class="lm-mobile-loan-card-body">'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">' + escapeHtml(loanListText.customer) + '</span><span class="value">' + escapeHtml(customer) + '</span></div>'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">' + escapeHtml(loanListText.phone) + '</span><span class="value">' + escapeHtml(phone) + '</span></div>'
            + (product ? '    <div class="lm-mobile-loan-card-row"><span class="label">' + escapeHtml(@json($text('Product', 'ទំនិញ'))) + '</span><span class="value"><strong>' + escapeHtml(product) + '</strong>' + (row.item_price ? ' <span style="color:var(--lm-primary); font-weight:700;">(' + escapeHtml(plainText(row.item_price)) + ')</span>' : '') + '</span></div>' : '')
            + (nextDue ? '    <div class="lm-mobile-loan-card-row"><span class="label">' + escapeHtml(@json($text('Next Due', 'ត្រូវបង់បន្ទាប់'))) + '</span><span class="value">' + nextDue + '</span></div>' : '')
            + '    <div class="lm-mobile-loan-card-row"><span class="label">' + escapeHtml(loanListText.location) + '</span><span class="value">' + escapeHtml(location) + '</span></div>'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">' + escapeHtml(loanListText.collector) + '</span><span class="value">' + escapeHtml(collector) + '</span></div>'
            + '  </div>'
            + '  <div class="lm-mobile-loan-card-balance">'
            + '    <div class="lm-mobile-loan-card-balance-item"><small>' + escapeHtml(loanListText.principal) + '</small><strong>' + escapeHtml(principal) + '</strong></div>'
            + '    <div class="lm-mobile-loan-card-balance-item paid"><small>' + escapeHtml(loanListText.paid) + '</small><strong>' + escapeHtml(paid) + '</strong></div>'
            + '    <div class="lm-mobile-loan-card-balance-item due"><small>' + escapeHtml(loanListText.balance) + '</small><strong>' + escapeHtml(balance) + '</strong></div>'
            + '  </div>'
            + '  <div class="lm-mobile-loan-card-actions">'
            + '    <a href="' + viewUrl + '" class="btn btn-default btn-sm"><i class="fa fa-eye"></i> ' + escapeHtml(loanListText.view) + '</a>'
            + '    <a href="#" class="btn btn-success btn-sm btn-modal" data-href="' + quickPayUrl + '" data-container=".view_modal"><i class="fa fa-money"></i> ' + escapeHtml(loanListText.pay) + '</a>'
            + (telegramUrl ? (telegramLinked ? '    <button type="button" class="btn btn-default btn-sm" disabled><i class="fa fa-check-circle"></i> ' + escapeHtml(loanListText.telegram) + '</button>' : '    <a href="#" class="btn btn-info btn-sm js-loan-telegram-link" data-url="' + telegramUrl + '" data-customer="' + escapeHtml(customer) + '"><i class="fa fa-paper-plane"></i> ' + escapeHtml(loanListText.telegram) + '</a>') : '')
            + (blacklistUrl ? '    <a href="#" class="btn btn-danger btn-sm js-loan-blacklist-customer" data-url="' + blacklistUrl + '" data-customer="' + escapeHtml(customer) + '"><i class="fa fa-user-times"></i> ' + escapeHtml(loanListText.addBlacklist) + '</a>' : '')
            + '  </div>'
            + '</article>';
    }

    function renderMobileLoanList(rows) {
        var $list = $('#loan_mobile_list');
        if (!$list.length) return;
        if (!rows || !rows.length) {
            $list.html('<div class="lm-mobile-loan-empty">' + escapeHtml(loanListText.noLoans) + '</div>');
            return;
        }

        $list.html(rows.map(mobileLoanCard).join(''));
    }

    function moneyToNumber(value) {
        return parseFloat(String(plainText(value)).replace(/[^0-9.-]+/g, '')) || 0;
    }

    function formatMoney(value) {
        return '$' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function updateLoanStats(api) {
    }

    var loanTable = null;
    var exportTitle = @json($text('All Installments', 'កម្ចីទាំងអស់'));
    var tableButtons = [];
    if ($.fn.dataTable && $.fn.dataTable.Buttons) {
        tableButtons = [
            {
                extend: 'copy',
                text: '<i class="fa fa-copy" aria-hidden="true"></i> Copy',
                className: 'btn btn-default btn-sm',
                title: exportTitle,
                exportOptions: {columns: ':visible:not(.no-export)', stripHtml: true}
            },
            {
                extend: 'csv',
                text: '<i class="fa fa-file-text-o" aria-hidden="true"></i> Export CSV',
                className: 'btn btn-default btn-sm',
                title: exportTitle,
                exportOptions: {columns: ':visible:not(.no-export)', stripHtml: true}
            },
            {
                extend: 'excel',
                text: '<i class="fa fa-file-excel-o" aria-hidden="true"></i> Export Excel',
                className: 'btn btn-default btn-sm',
                title: exportTitle,
                exportOptions: {columns: ':visible:not(.no-export)', stripHtml: true}
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print" aria-hidden="true"></i> Print',
                className: 'btn btn-default btn-sm',
                title: exportTitle,
                exportOptions: {columns: ':visible:not(.no-export)', stripHtml: true}
            },
            {
                extend: 'colvis',
                text: '<i class="fa fa-columns" aria-hidden="true"></i> Column visibility',
                className: 'btn btn-default btn-sm',
                columns: ':not(.no-export)'
            },
            {
                extend: 'pdf',
                text: '<i class="fa fa-file-pdf-o" aria-hidden="true"></i> Export PDF',
                className: 'btn btn-default btn-sm',
                title: exportTitle,
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {columns: ':visible:not(.no-export)', stripHtml: true}
            }
        ];
    }

    function reloadLoanTable() {
        if (loanTable && loanTable.ajax) {
            loanTable.ajax.reload();
        }
    }

    function setRange(s, e){
        $('#date_from').val(s.format('YYYY-MM-DD'));
        $('#date_to').val(e.format('YYYY-MM-DD'));
        $('#allLoansDateRange').val(s.format(loanDateFormat) + ' - ' + e.format(loanDateFormat));
    }

    function clearRange(){
        $('#allLoansDateRange,#date_from,#date_to').val('');
    }

    if (typeof moment !== 'undefined' && $.fn.daterangepicker) {
        var loanDrs = (typeof dateRangeSettings !== 'undefined') ? dateRangeSettings : {};
        var defaultStartDate = moment().startOf('month');
        var defaultEndDate = moment();
        var fyStart = (typeof financial_year !== 'undefined' && financial_year.start && moment(financial_year.start).isValid())
            ? moment(financial_year.start)
            : moment().startOf('year');
        var fyEnd = (typeof financial_year !== 'undefined' && financial_year.end && moment(financial_year.end).isValid())
            ? moment(financial_year.end)
            : moment().endOf('year');
        var loanDateRanges = {
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

        $('#allLoansDateRange').daterangepicker($.extend(true, {}, loanDrs, {
            autoUpdateInput: false,
            showDropdowns: true,
            linkedCalendars: false,
            startDate: defaultStartDate,
            endDate: defaultEndDate,
            ranges: loanDateRanges,
            locale: $.extend(true, {}, loanDrs.locale || {}, {
                format: loanDateFormat,
                separator: ' - ',
                applyLabel: @json($text('Apply', 'អនុវត្ត')),
                cancelLabel: @json($text('Clear', 'សម្អាត')),
                customRangeLabel: @json($text('Custom Range', 'ជ្រើសរើសផ្ទាល់')),
                toLabel: '~'
            })
        }), function(s, e){
            setRange(s, e);
        });

        $('#allLoansDateRange').on('apply.daterangepicker', function(event, picker){
            setRange(picker.startDate, picker.endDate);
            reloadLoanTable();
        });

        $('#allLoansDateRange').on('cancel.daterangepicker', function(){
            clearRange();
            reloadLoanTable();
        });
    } else {
        $('#allLoansDateRange').prop('readonly', false).on('change', function(){
            var raw = String($(this).val() || '').trim();
            var parts = raw.split(/\s*(?:~|\s-\s)\s*/);
            if (parts.length >= 2) {
                $('#date_from').val(parts[0]);
                $('#date_to').val(parts[1]);
            } else {
                clearRange();
            }
            reloadLoanTable();
        });
    }

    if (!$.fn.DataTable) {
        $('#loan_mobile_list').html('<div class="lm-mobile-loan-empty text-danger">DataTable library is not loaded.</div>');
        return;
    }

    $.fn.dataTable.ext.errMode = 'none';
    $('#loan_list_table').on('error.dt', function(e, settings, techNote, message) {
        $('#loan_mobile_list').html('<div class="lm-mobile-loan-empty text-danger">' + escapeHtml(message || loanListText.emptyTable) + '</div>');
        if (window.toastr) {
            toastr.error(message || loanListText.emptyTable);
        }
    });

    loanTable = $('#loan_list_table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        scrollX: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: "<'lm-dt-top'<'lm-dt-length'l><'lm-dt-buttons'B><'lm-dt-search'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row lm-dt-bottom'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: tableButtons,
        language: {
            processing: loanListText.processing,
            search: loanListText.search + ':',
            searchPlaceholder: @json($text('Search ...', 'ស្វែងរក ...')),
            lengthMenu: loanListText.lengthMenu,
            info: loanListText.info,
            infoEmpty: loanListText.infoEmpty,
            emptyTable: loanListText.emptyTable,
            zeroRecords: loanListText.zeroRecords,
            paginate: {
                next: loanListText.paginateNext,
                previous: loanListText.paginatePrevious
            }
        },
        order: [[2, 'desc']],
        ajax: {
            url: "{{ route('loan-management.loans.list-data', [], false) }}",
            data: function(d){
                d.date_from = $('#date_from').val();
                d.date_to = $('#date_to').val();
                d.date_range = $('#allLoansDateRange').val();
                d.status = $('#status').val();
                d.location_name = $('#location_name').val();
                d.collector_name = $('#collector_name').val();
                d.customer = $('#customer').val();
            },
            error: function(xhr) {
                var message = (xhr.responseJSON && xhr.responseJSON.message) || xhr.responseText || loanListText.emptyTable;
                $('#loan_mobile_list').html('<div class="lm-mobile-loan-empty text-danger">' + escapeHtml(message) + '</div>');
            }
        },
        columns: [
            {data:'action', name:'action', orderable:false, searchable:false, className:'no-export text-center lm-action-col'},
            {data:'loan_number', name:'loan_number'},
            {data:'loan_date', name:'loan_date'},
            {data:'customer_name_snapshot', name:'customer_name_snapshot'},
            {data:'product_name_snapshot', name:'product_name_snapshot'},
            {data:'down_payment', name:'down_payment'},
            {data:'installment_terms', name:'installment_terms', orderable:false, searchable:false},
            {data:'next_due_date', name:'next_due_date', searchable:false},
            {data:'principal_amount', name:'principal_amount'},
            {data:'total_amount', name:'total_amount'},
            {data:'paid_amount', name:'paid_amount'},
            {data:'balance_amount', name:'balance_amount'},
            {data:'repayment_progress', name:'repayment_progress', orderable:false, searchable:false},
            {data:'status', name:'status'},
            {data:'location_name_snapshot', name:'location_name_snapshot', searchable:false},
            {data:'collector_name_snapshot', name:'collector_name_snapshot'}
        ],
        fnDrawCallback: function(){
            if (typeof __currency_convert_recursively === 'function') {
                __currency_convert_recursively($('#loan_list_table'));
            }
            var api = this.api();
            var rows = api.rows({page: 'current'}).data().toArray();
            renderMobileLoanList(rows);
            updateLoanStats(api);

            var json = api.ajax.json();
            if (json && json.status_counts) {
                var sc = json.status_counts;
                $('#count_all').text(Number(sc.all || 0).toLocaleString());
                $('#count_pending').text(Number(sc.pending || 0).toLocaleString());
                $('#count_approved').text(Number(sc.approved || 0).toLocaleString());
                $('#count_active').text(Number(sc.active || 0).toLocaleString());
                $('#count_completed').text(Number(sc.completed || 0).toLocaleString());
                $('#count_rejected').text(Number(sc.rejected || 0).toLocaleString());
                $('#count_cancelled').text(Number(sc.cancelled || 0).toLocaleString());
                if (sc.blacklist !== undefined) {
                    $('#count_blacklist').text(Number(sc.blacklist || 0).toLocaleString());
                }
            }
        }
    });

    // Quick-filter when clicking a status card
    $(document).on('click', '.lm-status-card', function(e){
        if ($(this).attr('href')) {
            return; // Allow normal link navigation for direct action cards like Blacklist
        }
        var status = $(this).data('status') || '';
        $('.lm-status-card').removeClass('active');
        $(this).addClass('active');

        $('#status').val(status).trigger('change.select2');
        loanTable.ajax.reload();
    });

    $(document).on('change', '#status', function(){
        var currentStatus = $(this).val() || '';
        $('.lm-status-card').removeClass('active');
        if (currentStatus) {
            $('.lm-status-card[data-status="' + currentStatus + '"]').addClass('active');
        } else {
            $('.lm-status-card[data-status=""]').addClass('active');
        }
    });

    $(document).on('change', '#status,#location_name,#collector_name', function(){
        loanTable.ajax.reload();
    });

    $('#customer').on('input', debounce(function(){
        loanTable.ajax.reload();
    }, 300));

    $(document).on('click', '#loanFilterReset', function(){
        clearRange();
        $('#customer').val('');
        $('#status,#location_name,#collector_name').val('').trigger('change.select2');
        $('.lm-status-card').removeClass('active');
        $('.lm-status-card[data-status=""]').addClass('active');
        loanTable.search('');
        $('#loan_list_table_filter input[type="search"]').val('');
        loanTable.ajax.reload();
        setLoanFilterCollapsed(true);
    });

    var $loanFilter = $('#loanFilterPanel');
    var $loanFilterToggle = $('#loanFilterToggle');
    var $loanFilterToggleText = $('#loanFilterToggleText');
    var $loanFilterToggleIcon = $('#loanFilterToggleIcon');
    var loanFilterStateKey = 'lm_loan_filter_collapsed_v2';

    function setLoanFilterCollapsed(isCollapsed) {
        $loanFilter.toggleClass('collapsed', isCollapsed);
        $loanFilterToggle.attr('aria-expanded', isCollapsed ? 'false' : 'true');
        $loanFilterToggleText.text(isCollapsed ? @json($text('Expand', 'ពង្រីក')) : @json($text('Collapse', 'បង្រួម')));
        $loanFilterToggleIcon
            .toggleClass('fa-chevron-down', isCollapsed)
            .toggleClass('fa-chevron-up', ! isCollapsed);
        try { window.localStorage.setItem(loanFilterStateKey, isCollapsed ? '1' : '0'); } catch(err){}
    }

    if ($loanFilter.length) {
        var initialCollapsed = true;
        try {
            var savedLoanFilterState = window.localStorage.getItem(loanFilterStateKey);
            initialCollapsed = savedLoanFilterState === null ? true : savedLoanFilterState === '1';
        } catch(err){}
        setLoanFilterCollapsed(initialCollapsed);

        function toggleLoanFilterPanel() {
            setLoanFilterCollapsed(! $loanFilter.hasClass('collapsed'));
        }

        $loanFilterToggle.on('click', function(){
            toggleLoanFilterPanel();
        });

        $loanFilter.find('.lm-loan-list-filter-toggle-label').on('click', function(){
            toggleLoanFilterPanel();
        });

        $('#loanFilterApply').on('click', function(){
            loanTable.ajax.reload();
            setLoanFilterCollapsed(true);
        });
    }

    $(document).on('click', '.btn-delete-loan', function(){
        if(!confirm(loanListText.deleteConfirm)) return;
        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: {_token: $('meta[name=\"csrf-token\"]').attr('content')},
            success: function(){ loanTable.ajax.reload(); },
            error: function(){ alert(loanListText.deleteFailed); }
        });
    });

    var $openLoanActionOwner = null;

    function closeLoanActionMenu() {
        var $dropdown = $('body > .lm-loan-action-dropdown.is-floating');
        if ($dropdown.length && $openLoanActionOwner && $openLoanActionOwner.length) {
            $dropdown.removeClass('is-floating').hide().appendTo($openLoanActionOwner);
        } else {
            $dropdown.removeClass('is-floating').hide();
        }
        if ($openLoanActionOwner && $openLoanActionOwner.length) {
            $openLoanActionOwner.removeClass('open').find('.js-loan-action-toggle').attr('aria-expanded', 'false');
        }
        $openLoanActionOwner = null;
    }

    function positionLoanActionMenu($menu) {
        var $button = $menu.find('.js-loan-action-toggle').first();
        var $dropdown = $('body > .lm-loan-action-dropdown.is-floating').first();
        if (!$button.length || !$dropdown.length || !$menu.hasClass('open')) return;

        var rect = $button[0].getBoundingClientRect();
        var width = Math.max($dropdown.outerWidth() || 210, 190);
        var height = Math.max($dropdown.outerHeight() || 260, 120);
        var left = rect.right + 8;
        var top = rect.top;

        if (left + width > window.innerWidth - 12) left = Math.max(12, rect.left - width - 8);
        if (top + height > window.innerHeight - 12) top = Math.max(12, window.innerHeight - height - 12);

        $dropdown.css({ top: top + 'px', left: left + 'px' });
    }

    $(document).on('click', '.js-loan-action-toggle', function(e){
        e.preventDefault();
        e.stopPropagation();

        var $menu = $(this).closest('.lm-loan-action-menu');
        var wasOpen = $menu.hasClass('open');
        closeLoanActionMenu();
        if (wasOpen) return;

        $openLoanActionOwner = $menu;
        $menu.addClass('open');
        $(this).attr('aria-expanded', 'true');
        $menu.find('.lm-loan-action-dropdown').first().addClass('is-floating').appendTo('body').show();
        positionLoanActionMenu($menu);
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('.lm-loan-action-dropdown, .js-loan-action-toggle').length) {
            closeLoanActionMenu();
        }
    });

    $(window).on('resize', closeLoanActionMenu);
    $(document).on('scroll', '.dataTables_scrollBody', closeLoanActionMenu);

    $(document).on('click', '.js-loan-blacklist-customer', function(e){
        e.preventDefault();
        var url = $(this).data('url') || '';
        var customer = $(this).data('customer') || 'Customer';
        if (!url) {
            return;
        }

        $('#loanAddBlacklistForm').attr('action', url);
        $('#loanBlacklistCustomerName').text(customer);
        $('#loanBlacklistReason').val('');
        $('#loanAddBlacklistModal').modal('show');
        closeLoanActionMenu();
    });

    $('#loanAddBlacklistForm').on('submit', function(e){
        var reason = $.trim($('#loanBlacklistReason').val() || '');
        if (!reason) {
            e.preventDefault();
            alert(loanListText.blacklistReasonRequired);
            $('#loanBlacklistReason').focus();
        }
    });


    $(document).on('click', '.btn-change-status', function(e){
        e.preventDefault();
        $.post($(this).data('url'), {
            _token: $('meta[name=\"csrf-token\"]').attr('content'),
            status: $(this).data('status')
        }, function(){ loanTable.ajax.reload(); }).fail(function(){ alert(loanListText.statusFailed + '.'); });
    });

    $(document).on('change', '.js-loan-status-select', function(){
        var $select = $(this);
        var oldStatus = $select.data('original-status') || '';
        var newStatus = $select.val();
        var url = $select.data('url');

        if (!url || !newStatus || newStatus === oldStatus) {
            return;
        }

        $select.prop('disabled', true);
        $.post(url, {
            _token: $('meta[name=\"csrf-token\"]').attr('content'),
            status: newStatus
        }, function(){
            if (window.toastr) {
                toastr.success(loanListText.statusUpdated);
            }
            loanTable.ajax.reload(null, false);
        }).fail(function(){
            $select.val(oldStatus);
            if (window.toastr) {
                toastr.error(loanListText.statusFailed);
            } else {
                alert(loanListText.statusFailed + '.');
            }
        }).always(function(){
            $select.prop('disabled', false);
        });
    });

    $(document).on('click', '.js-copy-loan-payment-info', function(e){
        e.preventDefault();

        var $button = $(this);
        var url = $button.data('url');
        if (!url) return;

        $button.prop('disabled', true);
        $.getJSON(url)
            .done(function(res) {
                $.when(copyLoanText(res && res.data ? (res.data.text || '') : ''))
                    .done(function() {
                        if (window.toastr) {
                            toastr.success(loanListText.copied);
                        }
                    })
                    .fail(function() {
                        alert(loanListText.copyFailed);
                    });
            })
            .fail(function() {
                alert(loanListText.copyFailed);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.js-loan-telegram-link', function(e){
        e.preventDefault();

        var $button = $(this);
        var url = $button.data('url');
        var customer = $button.data('customer') || 'customer';
        if (!url) return;

        $button.prop('disabled', true).addClass('disabled');
        $.post(url, {_token: $('meta[name="csrf-token"]').attr('content')})
            .done(function(res) {
                var link = res && res.link ? res.link : '';
                var expires = res && res.expires_at ? res.expires_at : '';
                var qrUrl = link ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(link) : '';
                var safeLink = escapeHtml(link);
                var safeCustomer = escapeHtml(customer);
                var safeExpires = escapeHtml(expires ? formatLmExpiry(expires) : '');

                $('.view_modal').html(
                    '<div class="modal-dialog modal-sm" role="document">' +
                        '<div class="modal-content">' +
                            '<div class="modal-header">' +
                                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                                '<h4 class="modal-title"><i class="fa fa-paper-plane"></i> Connect Telegram</h4>' +
                            '</div>' +
                            '<div class="modal-body text-center">' +
                                '<p class="text-muted" style="margin-bottom:12px;">Share this link with ' + safeCustomer + '. Valid for a limited time and can only be used once.</p>' +
                                (qrUrl ? '<img src="' + qrUrl + '" alt="Telegram QR code" style="width:220px;height:220px;max-width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff;margin-bottom:12px;">' : '') +
                                '<input class="form-control text-center" readonly value="' + safeLink + '" style="margin-bottom:8px;">' +
                                (safeExpires ? '<div class="text-muted small">Expires: ' + safeExpires + '</div>' : '') +
                            '</div>' +
                            '<div class="modal-footer">' +
                                '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>' +
                                '<a href="' + safeLink + '" target="_blank" rel="noopener" class="btn btn-primary">Open Link</a>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                ).modal('show');
            })
            .fail(function(xhr) {
                var message = (xhr.responseJSON && xhr.responseJSON.message) || xhr.responseText || 'Unable to create Telegram link.';
                alert(message);
            })
            .always(function() {
                $button.prop('disabled', false).removeClass('disabled');
            });
    });
});
</script>
@endsection
