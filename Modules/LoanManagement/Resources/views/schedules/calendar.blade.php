@extends('loanmanagement::layouts.app')
@section('title', (session('user.language', config('app.locale')) === 'km') ? 'ប្រតិទិនបង់ប្រាក់រំលស់' : 'Installment Calendar')

@php
    $isKhmer = $isKhmer ?? (session('user.language', config('app.locale')) === 'km');
    $bi = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);

    $monthNamesEn = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $monthNamesKm = ['', 'មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];

    $curMonthNum = (int) $currentMonth->month;
    $curYearNum = (int) $currentMonth->year;
    $displayMonthName = $isKhmer ? ($monthNamesKm[$curMonthNum] ?? '') : ($monthNamesEn[$curMonthNum] ?? '');

    $daysOfWeekEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $daysOfWeekKm = ['អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'];
    $daysOfWeek = $isKhmer ? $daysOfWeekKm : $daysOfWeekEn;

    $collectionRate = $kpi['collection_rate'] ?? 0;

    $prevMonthUrl = route('loan-management.schedules.calendar', array_merge($filters, ['year' => $prevMonth['year'], 'month' => $prevMonth['month']]));
    $nextMonthUrl = route('loan-management.schedules.calendar', array_merge($filters, ['year' => $nextMonth['year'], 'month' => $nextMonth['month']]));
    $todayUrl = route('loan-management.schedules.calendar', ['year' => now()->year, 'month' => now()->month]);
@endphp

@section('loan_css')
<style>
    /* =========================================================
       Installment Calendar Professional Theme
       ========================================================= */
    .cal-wrap {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #1e293b;
    }

    /* Header & Action Bar */
    .cal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 20px;
    }
    .cal-title-box h1 {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: -0.3px;
    }
    .cal-title-box p {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }
    .cal-header-actions {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .cal-view-switch {
        display: inline-flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 9px;
        border: 1px solid #e2e8f0;
    }
    .cal-view-switch a, .cal-view-switch button {
        padding: 6px 14px;
        font-size: 12.5px;
        font-weight: 600;
        border-radius: 7px;
        text-decoration: none;
        border: none;
        background: transparent;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .cal-view-switch a.is-active, .cal-view-switch button.is-active {
        background: #ffffff;
        color: #0284c7;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        font-weight: 700;
    }
    .cal-btn-action {
        height: 36px;
        padding: 0 13px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        font-size: 12.5px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .cal-btn-action:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
        text-decoration: none;
    }

    /* KPI Summary Cards (5-column Grid) */
    .cal-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    @media (max-width: 1400px) {
        .cal-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .cal-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
        .cal-kpi-grid { grid-template-columns: 1fr; }
    }

    .cal-kpi-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 15px 16px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 6px rgba(15,23,42,0.03);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .cal-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(15,23,42,0.06);
    }
    .cal-kpi-top {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    .cal-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .cal-kpi-info small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #64748b;
        margin-bottom: 2px;
    }
    .cal-kpi-info strong {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .cal-kpi-sub {
        font-size: 11.5px;
        color: #64748b;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .cal-tone-blue { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .cal-tone-green { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .cal-tone-amber { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
    .cal-tone-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .cal-tone-purple { background: #faf5ff; color: #7c3aed; border: 1px solid #ddd6fe; }

    /* Progress bar on Rate Card */
    .cal-rate-bar-wrap {
        height: 7px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 6px;
        margin-bottom: 4px;
    }
    .cal-rate-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        border-radius: 999px;
        transition: width 0.4s ease;
    }

    /* Interactive Status Filter Pills (Legend) */
    .cal-legend-bar {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .cal-legend-title {
        font-size: 12.5px;
        font-weight: 700;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .cal-legend-pills {
        display: inline-flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .cal-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 11px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
        border: 1px solid transparent;
        background: #f1f5f9;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
    }
    .cal-filter-pill:hover {
        background: #e2e8f0;
    }
    .cal-filter-pill.is-active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
    }
    .cal-filter-pill .pill-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .pill-dot-all { background: #64748b; }
    .pill-dot-prior { background: #e11d48; }
    .pill-dot-overdue { background: #dc2626; }
    .pill-dot-today { background: #d97706; }
    .pill-dot-open { background: #0284c7; }
    .pill-dot-paid { background: #16a34a; }

    /* Calendar Control Bar */
    .cal-toolbar {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 12px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .cal-nav-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .cal-month-title {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        min-width: 200px;
        text-align: center;
        letter-spacing: -0.2px;
    }
    .cal-btn-nav {
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .cal-btn-nav:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
        text-decoration: none;
    }
    .cal-btn-today {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }
    .cal-btn-today:hover {
        background: #0369a1;
        border-color: #0369a1;
        color: #ffffff;
    }
    .cal-filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .cal-select {
        height: 36px;
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        font-size: 13px;
        font-weight: 500;
        color: #1e293b;
        outline: none;
    }
    .cal-select:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    }

    /* Main Month Calendar Grid */
    .cal-grid-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(15,23,42,0.04);
        margin-bottom: 24px;
    }
    .cal-days-head {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .cal-day-header {
        padding: 12px 8px;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #475569;
    }
    .cal-day-header.is-weekend {
        color: #ef4444;
        background: #fff5f5;
    }

    .cal-grid-body {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #e2e8f0;
        gap: 1px;
    }
    .cal-cell {
        background: #ffffff;
        min-height: 160px; /* Generous height for previewing customer cards */
        padding: 8px 7px 7px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: all 0.15s ease;
    }
    .cal-cell.is-weekend {
        background: #fcfdfe;
    }
    .cal-cell.is-other-month {
        background: #f8fafc;
        opacity: 0.55;
    }
    .cal-cell.is-clickable {
        cursor: pointer;
    }
    .cal-cell.is-clickable:hover {
        background: #f0f9ff;
        z-index: 2;
        box-shadow: inset 0 0 0 2px #0284c7, 0 4px 12px rgba(2, 132, 199, 0.15);
    }
    .cal-cell.is-dimmed {
        opacity: 0.25 !important;
        filter: grayscale(80%);
    }
    .cal-cell.is-highlighted {
        box-shadow: inset 0 0 0 2px #0284c7;
        background: #f0f9ff !important;
    }

    /* Today Cell Design */
    .cal-cell.is-today {
        background: #fefce8;
    }
    .cal-cell.is-today:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #0284c7;
    }
    .cal-cell.is-today .cal-date-num {
        background: #0284c7;
        color: #ffffff;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(2, 132, 199, 0.3);
    }

    .cal-cell-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    .cal-date-num {
        font-size: 13.5px;
        font-weight: 700;
        color: #1e293b;
    }
    .cal-cell.is-other-month .cal-date-num {
        color: #94a3b8;
    }
    .cal-today-badge {
        font-size: 9px;
        font-weight: 700;
        background: #fef08a;
        color: #854d0e;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .cal-today-badge:before {
        content: '';
        width: 5px;
        height: 5px;
        background: #ca8a04;
        border-radius: 50%;
        display: inline-block;
    }

    /* Micro-Progress Bar inside Day Cell */
    .cal-cell-progress {
        height: 3.5px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        display: flex;
        margin: 2px 0 4px;
    }
    .cal-bar-paid { background: #16a34a; }
    .cal-bar-overdue { background: #dc2626; }
    .cal-bar-open { background: #d97706; }

    /* Customer Chips inside Day Cell */
    .cal-day-summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 10.5px;
        font-weight: 700;
        color: #64748b;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 2px 5px;
        margin-bottom: 4px;
    }
    .cal-day-summary-row strong {
        color: #0f172a;
    }

    .cal-customers-list {
        display: flex;
        flex-direction: column;
        gap: 3px;
        flex: 1;
    }
    .cal-customer-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 3px 6px;
        border-radius: 5px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.25;
        transition: all 0.15s ease;
        border: 1px solid transparent;
        text-decoration: none;
    }
    .cal-customer-chip:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    }
    .cal-chip-left {
        display: flex;
        align-items: center;
        gap: 5px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        max-width: 72%;
    }
    .cal-chip-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .cal-dot-paid { background: #16a34a; }
    .cal-dot-overdue { background: #dc2626; }
    .cal-dot-today { background: #d97706; }
    .cal-dot-open { background: #0284c7; }

    .cal-chip-paid {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #166534;
    }
    .cal-chip-overdue {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
        border-left: 3px solid #dc2626;
    }
    .cal-chip-today {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
        border-left: 3px solid #d97706;
    }
    .cal-chip-open {
        background: #f0f9ff;
        border-color: #bae6fd;
        color: #0369a1;
    }
    .cal-chip-name {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: 11px;
    }
    .cal-chip-amount {
        font-weight: 700;
        font-size: 10.5px;
        flex-shrink: 0;
    }
    .cal-chip-prior-tag {
        font-size: 9px;
        font-weight: 800;
        color: #be123c;
        background: #ffe4e6;
        border: 1px solid #fecdd3;
        border-radius: 3px;
        padding: 0 4px;
        margin-left: 2px;
        flex-shrink: 0;
        line-height: 1.3;
    }
    .cal-chip-total-sub {
        display: block;
        font-size: 8.5px;
        font-weight: 800;
        color: #be123c;
        line-height: 1;
        margin-top: 1px;
    }
    .cal-more-chip {
        font-size: 10px;
        font-weight: 700;
        color: #0284c7;
        padding: 2px 5px;
        text-align: center;
        border-radius: 4px;
        background: #e0f2fe;
        cursor: pointer;
        transition: all 0.15s ease;
        margin-top: 2px;
        display: block;
    }
    .cal-more-chip:hover {
        background: #bae6fd;
        color: #0369a1;
    }

    /* =========================================================
       Week View Styling
       ========================================================= */
    .cal-week-view {
        display: none; /* toggled via JS */
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(15,23,42,0.04);
        margin-bottom: 24px;
    }
    .cal-week-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #e2e8f0;
        gap: 1px;
    }
    @media (max-width: 992px) {
        .cal-week-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 576px) {
        .cal-week-grid { grid-template-columns: 1fr; }
    }
    .cal-week-col {
        background: #ffffff;
        min-height: 480px;
        padding: 12px;
        display: flex;
        flex-direction: column;
    }
    .cal-week-col-head {
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
        margin-bottom: 10px;
        text-align: center;
    }
    .cal-week-col-name {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
    }
    .cal-week-col-date {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 2px 0;
    }
    .cal-week-col.is-today .cal-week-col-date {
        color: #0284c7;
    }
    .cal-week-col-summary {
        font-size: 11.5px;
        color: #64748b;
    }
    .cal-week-items-wrap {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .cal-week-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 12px;
        transition: all 0.15s ease;
    }
    .cal-week-card:hover {
        background: #f0f9ff;
        border-color: #0284c7;
        transform: translateY(-1px);
    }
    .cal-week-card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 3px;
    }

    /* =========================================================
       Modal Enhancements: Tabs, Search, Contact Links
       ========================================================= */
    .cal-modal-tabs {
        display: flex;
        gap: 6px;
        border-bottom: 1px solid #e2e8f0;
        padding: 0 0 10px 0;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .cal-modal-tab-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .cal-modal-tab-btn:hover {
        background: #e2e8f0;
    }
    .cal-modal-tab-btn.is-active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    .cal-modal-header-stats {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
        flex-wrap: wrap;
    }
    .cal-modal-badge {
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .cal-customer-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .cal-customer-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0284c7;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .cal-customer-info a {
        font-weight: 700;
        color: #0284c7;
        font-size: 13px;
        text-decoration: none;
    }
    .cal-customer-info small {
        display: block;
        color: #64748b;
        font-size: 11.5px;
    }
    .cal-contact-actions {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 6px;
    }
    .cal-contact-btn {
        width: 22px;
        height: 22px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e2e8f0;
        color: #334155;
        font-size: 10px;
        text-decoration: none;
        transition: all 0.15s ease;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    .cal-contact-btn:hover {
        background: #0284c7;
        color: #ffffff;
        text-decoration: none;
    }
    .cal-copy-mini {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #64748b;
        font-size: 9.5px;
        cursor: pointer;
        padding: 0;
        margin-left: 5px;
        transition: all 0.15s ease;
        vertical-align: middle;
    }
    .cal-copy-mini:hover {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    /* Print Stylesheet */
    @media print {
        .cal-header-actions, .cal-toolbar, .cal-legend-bar, .modal-footer, .btn, .cal-view-switch {
            display: none !important;
        }
        .cal-grid-card, .cal-cell {
            box-shadow: none !important;
            border-color: #999 !important;
        }
        .cal-cell.is-clickable {
            background: #fff !important;
        }
    }
</style>
@endsection

@section('content_body')
<div class="cal-wrap">

    {{-- Top Header --}}
    <div class="cal-header">
        <div class="cal-title-box">
            <h1>
                <i class="fa fa-calendar-check-o" style="color: #0284c7;"></i>
                {{ $bi('Installment Calendar', 'ប្រតិទិនបង់ប្រាក់រំលស់') }}
            </h1>
            <p>{{ $bi('Live schedule overview showing customers due on each day with direct collection actions', 'ទិដ្ឋភាពទូទៅនៃកាលវិភាគបង់ប្រាក់ បង្ហាញអតិថិជនដល់ថ្ងៃបង់តាមថ្ងៃនីមួយៗ និងសកម្មភាពប្រមូលប្រាក់ផ្ទាល់') }}</p>
        </div>

        {{-- Action Buttons & View Toggles --}}
        <div class="cal-header-actions">
            {{-- Month / Week View Toggle --}}
            <div class="cal-view-switch" style="margin-right: 4px;">
                <button type="button" class="js-view-mode-btn is-active" data-view="month">
                    <i class="fa fa-th"></i> {{ $bi('Month', 'ខែ') }}
                </button>
                <button type="button" class="js-view-mode-btn" data-view="week">
                    <i class="fa fa-columns"></i> {{ $bi('Week', 'សប្ដាហ៍') }}
                </button>
            </div>

            {{-- Table View Switcher --}}
            <div class="cal-view-switch">
                <a href="{{ route('loan-management.schedules.index') }}">
                    <i class="fa fa-list"></i> {{ $bi('Table View', 'ទម្រង់តារាង') }}
                </a>
                <button type="button" class="is-active">
                    <i class="fa fa-calendar"></i> {{ $bi('Calendar View', 'ទម្រង់ប្រតិទិន') }}
                </button>
            </div>

            {{-- Export Month CSV --}}
            <a href="{{ route('loan-management.schedules.calendar', array_merge($filters, ['export' => 'csv'])) }}" class="cal-btn-action" title="{{ $bi('Export Month to CSV', 'ទាញយកទិន្នន័យប្រចាំខែជា CSV') }}">
                <i class="fa fa-download text-primary"></i> {{ $bi('Export CSV', 'ទាញយក CSV') }}
            </a>

            {{-- Print Calendar --}}
            <button type="button" class="cal-btn-action" onclick="window.print();" title="{{ $bi('Print Calendar', 'បោះពុម្ពប្រតិទិន') }}">
                <i class="fa fa-print"></i> {{ $bi('Print', 'បោះពុម្ព') }}
            </button>
        </div>
    </div>

    {{-- Collection Summary KPI Cards (All Due / Today / Last Month / Paid / Unpaid) --}}
    <div class="cal-kpi-grid">
        {{-- Card 1: All Due (Due Today + Last Month Not Yet Paid) --}}
        <div class="cal-kpi-card" style="border-left: 3px solid #e11d48; background: linear-gradient(180deg, #ffffff 0%, #fff1f2 100%);">
            <div class="cal-kpi-top">
                <div class="cal-kpi-icon cal-tone-red" style="background:#ffe4e6;color:#e11d48;border-color:#fecdd3;">
                    <i class="fa fa-bell"></i>
                </div>
                <div class="cal-kpi-info">
                    <small style="color:#be123c;">{{ $bi('All Due', 'សរុបត្រូវបង់') }}</small>
                    <strong style="color:#be123c;">{{ $money($kpi['all_due_total'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="cal-kpi-sub">
                <span>{{ $bi('Due Today + Last Month Unpaid', 'ថ្ងៃនេះ + ខែមុន') }}:</span>
                <strong style="color: #0f172a;">{{ $number(($kpi['due_today_count'] ?? 0) + ($kpi['last_month_unpaid_count'] ?? 0)) }} {{ $bi('inst.', 'វគ្គ') }}</strong>
            </div>
        </div>

        {{-- Card 2: Today Due --}}
        <div class="cal-kpi-card" style="border-left: 3px solid #0284c7;">
            <div class="cal-kpi-top">
                <div class="cal-kpi-icon cal-tone-blue">
                    <i class="fa fa-calendar-check-o"></i>
                </div>
                <div class="cal-kpi-info">
                    <small>{{ $bi('Today Due', 'ដល់ថ្ងៃបង់ថ្ងៃនេះ') }}</small>
                    <strong>{{ $money($kpi['due_today_total'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="cal-kpi-sub">
                <span>{{ $bi('Unpaid Installments', 'វគ្គមិនទាន់បង់') }}:</span>
                <strong style="color: #0f172a;">{{ $number($kpi['due_today_count'] ?? 0) }} / {{ $number($kpi['due_today_customers'] ?? 0) }} {{ $bi('cust.', 'អតិថិជន') }}</strong>
            </div>
        </div>

        {{-- Card 3: Last Month Due (Not Yet Paid) --}}
        <div class="cal-kpi-card" style="border-left: 3px solid #e11d48;">
            <div class="cal-kpi-top">
                <div class="cal-kpi-icon cal-tone-red" style="background:#ffe4e6;color:#e11d48;border-color:#fecdd3;">
                    <i class="fa fa-history"></i>
                </div>
                <div class="cal-kpi-info">
                    <small style="color:#be123c;">{{ $bi('Last Month Due (Unpaid)', 'បំណុលខែមុនមិនទាន់បង់') }}</small>
                    <strong style="color:#e11d48;">{{ $money($kpi['last_month_due_total'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="cal-kpi-sub">
                <span>{{ $bi('Unpaid Installments', 'វគ្គមិនទាន់បង់') }}:</span>
                <strong style="color: #be123c;">{{ $number($kpi['last_month_unpaid_count'] ?? 0) }} / {{ $number($kpi['last_month_unpaid_customers'] ?? 0) }} {{ $bi('cust.', 'អតិថិជន') }}</strong>
            </div>
        </div>

        {{-- Card 4: Total Paid (This Month) --}}
        <div class="cal-kpi-card">
            <div class="cal-kpi-top">
                <div class="cal-kpi-icon cal-tone-green">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div class="cal-kpi-info">
                    <small>{{ $bi('Total Paid (This Month)', 'បានបង់សរុប (ខែនេះ)') }}</small>
                    <strong style="color:#15803d;">{{ $money($kpi['total_paid'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="cal-kpi-sub">
                <span>{{ $bi('Paid Installments', 'វគ្គបានបង់') }}:</span>
                <strong style="color: #16a34a;">{{ $number($kpi['paid_count'] ?? 0) }}</strong>
            </div>
        </div>

        {{-- Card 5: Total Unpaid (This Month Left) --}}
        <div class="cal-kpi-card">
            <div class="cal-kpi-top">
                <div class="cal-kpi-icon cal-tone-amber">
                    <i class="fa fa-hourglass-half"></i>
                </div>
                <div class="cal-kpi-info">
                    <small>{{ $bi('Total Unpaid (This Month)', 'នៅសល់មិនទាន់បង់ (ខែនេះ)') }}</small>
                    <strong style="color:#b45309;">{{ $money($kpi['total_unpaid'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="cal-kpi-sub">
                <span>{{ $bi('Open Installments', 'វគ្គមិនទាន់បង់') }}:</span>
                <strong style="color: #92400e;">{{ $number($kpi['open_count'] ?? 0) }}</strong>
            </div>
        </div>
    </div>

    {{-- Interactive Status Filter Pills (Legend Toolbar) --}}
    <div class="cal-legend-bar">
        <div class="cal-legend-title">
            <i class="fa fa-filter text-primary"></i>
            {{ $bi('Quick Status Filter:', 'ចម្រោះស្ថានភាពរហ័ស:') }}
        </div>
        <div class="cal-legend-pills">
            <span class="cal-filter-pill is-active" data-status-filter="all">
                <span class="pill-dot pill-dot-all"></span> {{ $bi('All Days', 'គ្រប់ថ្ងៃ') }}
            </span>
            <span class="cal-filter-pill" data-status-filter="prior">
                <span class="pill-dot pill-dot-prior"></span> {{ $bi('With Prior Arrears', 'មានជំពាក់ខែមុន') }}
            </span>
            <span class="cal-filter-pill" data-status-filter="overdue">
                <span class="pill-dot pill-dot-overdue"></span> {{ $bi('Has Overdue', 'មានហួសកំណត់') }}
            </span>
            <span class="cal-filter-pill" data-status-filter="today">
                <span class="pill-dot pill-dot-today"></span> {{ $bi('Due Today', 'ត្រូវបង់ថ្ងៃនេះ') }}
            </span>
            <span class="cal-filter-pill" data-status-filter="open">
                <span class="pill-dot pill-dot-open"></span> {{ $bi('Pending / Open', 'មិនទាន់បង់') }}
            </span>
            <span class="cal-filter-pill" data-status-filter="paid">
                <span class="pill-dot pill-dot-paid"></span> {{ $bi('100% Paid', 'បានបង់ពេញ') }}
            </span>
        </div>
        <div class="text-muted" style="font-size: 11.5px;">
            <i class="fa fa-info-circle"></i> {{ $bi('Customer cards shown on each day. Click any customer or day to view details & collect.', 'អតិថិជនត្រូវបានបង្ហាញលើថ្ងៃនីមួយៗ។ ចុចលើអតិថិជន ឬថ្ងៃដើម្បីប្រមូលប្រាក់។') }}
        </div>
    </div>

    {{-- Month Navigation & Filter Toolbar --}}
    <form method="GET" action="{{ route('loan-management.schedules.calendar') }}" id="calFilterForm" class="cal-toolbar">
        {{-- Navigation Left --}}
        <div class="cal-nav-group">
            <a href="{{ $prevMonthUrl }}" class="cal-btn-nav" title="Shortcut: Left Arrow (←)">
                <i class="fa fa-chevron-left"></i> {{ $bi('Prev', 'មុន') }}
            </a>

            <div class="cal-month-title">
                {{ $displayMonthName }} {{ $curYearNum }}
            </div>

            <a href="{{ $nextMonthUrl }}" class="cal-btn-nav" title="Shortcut: Right Arrow (→)">
                {{ $bi('Next', 'បន្ទាប់') }} <i class="fa fa-chevron-right"></i>
            </a>

            <a href="{{ $todayUrl }}" class="cal-btn-nav cal-btn-today" title="Shortcut: Key 'T'">
                {{ $bi('Today', 'ថ្ងៃនេះ') }}
            </a>
        </div>

        {{-- Filters Right --}}
        <div class="cal-filter-group">
            {{-- Month Select --}}
            <select name="month" class="cal-select" onchange="document.getElementById('calFilterForm').submit();">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $curMonthNum === $m ? 'selected' : '' }}>
                        {{ $isKhmer ? ($monthNamesKm[$m] ?? $m) : ($monthNamesEn[$m] ?? $m) }}
                    </option>
                @endfor
            </select>

            {{-- Year Select --}}
            <select name="year" class="cal-select" onchange="document.getElementById('calFilterForm').submit();">
                @for($y = now()->year + 2; $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ $curYearNum === $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            {{-- Location Select --}}
            @if(!empty($locations))
                <select name="location_id" class="cal-select" onchange="document.getElementById('calFilterForm').submit();">
                    <option value="">{{ $bi('All Locations', 'សាខាទាំងអស់') }}</option>
                    @foreach($locations as $id => $name)
                        <option value="{{ $id }}" {{ (string)($filters['location_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            @endif

            <noscript>
                <button type="submit" class="cal-btn-nav">{{ $bi('Filter', 'ចម្រោះ') }}</button>
            </noscript>
        </div>
    </form>

    {{-- =========================================================
         MONTH VIEW: 7-Column Grid with Real Customer Chips on Each Day
         ========================================================= --}}
    <div class="cal-grid-card" id="calMonthView">
        {{-- Days of week head --}}
        <div class="cal-days-head">
            @foreach($daysOfWeek as $index => $dayName)
                <div class="cal-day-header {{ ($index === 0 || $index === 6) ? 'is-weekend' : '' }}">
                    {{ $dayName }}
                </div>
            @endforeach
        </div>

        {{-- Month Day Cells --}}
        <div class="cal-grid-body">
            @foreach($calendarDays as $cell)
                @php
                    $stats = $cell['stats'];
                    $hasSchedules = $stats && ($stats['loan_count'] ?? 0) > 0;
                    $isClickable = $hasSchedules;
                    $dayOfWeekIndex = \Carbon\Carbon::parse($cell['date'])->dayOfWeek;
                    $isWeekend = ($dayOfWeekIndex === 0 || $dayOfWeekIndex === 6);

                    // Micro-progress bar calculations
                    $totalDueCell = (float) ($stats['total_due'] ?? 0);
                    $paidPct = 0;
                    $overduePct = 0;
                    $openPct = 0;
                    if ($totalDueCell > 0) {
                        $paidPct = round((($stats['total_paid'] ?? 0) / $totalDueCell) * 100);
                        if (($stats['overdue_count'] ?? 0) > 0) {
                            $overduePct = min(100 - $paidPct, round((($stats['overdue_count'] ?? 0) / ($stats['schedule_count'] ?? 1)) * 100));
                        }
                        $openPct = max(0, 100 - $paidPct - $overduePct);
                    }

                    $isFullyPaid = $hasSchedules && ($stats['total_balance'] ?? 0) <= 0;
                    $hasOverdue = ($stats['overdue_count'] ?? 0) > 0;
                    $hasOpen = ($stats['open_count'] ?? 0) > 0;
                    $customers = $cell['customers_preview'] ?? [];
                    $hasPriorUnpaid = collect($customers)->contains('has_prior_unpaid', true);
                @endphp
                <div class="cal-cell {{ !$cell['is_current_month'] ? 'is-other-month' : '' }} {{ $isWeekend ? 'is-weekend' : '' }} {{ $cell['is_today'] ? 'is-today' : '' }} {{ $isClickable ? 'is-clickable js-cal-day-cell' : '' }}"
                     data-date="{{ $cell['date'] }}"
                     data-is-current="{{ $cell['is_current_month'] ? '1' : '0' }}"
                     data-is-today="{{ $cell['is_today'] ? '1' : '0' }}"
                     data-has-overdue="{{ $hasOverdue ? '1' : '0' }}"
                     data-has-open="{{ $hasOpen ? '1' : '0' }}"
                     data-has-prior="{{ $hasPriorUnpaid ? '1' : '0' }}"
                     data-is-paid="{{ $isFullyPaid ? '1' : '0' }}"
                     @if($isClickable)
                         data-loans="{{ $stats['loan_count'] }}"
                         data-due="{{ $stats['total_due'] }}"
                         data-balance="{{ $stats['total_balance'] }}"
                     @endif>
                    
                    {{-- Cell Top Header --}}
                    <div>
                        <div class="cal-cell-top">
                            <span class="cal-date-num">{{ $cell['day'] }}</span>
                            @if($cell['is_today'])
                                <span class="cal-today-badge">{{ $bi('Today', 'ថ្ងៃនេះ') }}</span>
                            @endif
                        </div>

                        {{-- Micro Progress Bar --}}
                        @if($hasSchedules)
                            <div class="cal-cell-progress" title="{{ $bi('Paid', 'បានបង់') }}: {{ $paidPct }}% | {{ $bi('Open', 'នៅសល់') }}: {{ $openPct }}% | {{ $bi('Overdue', 'ហួស') }}: {{ $overduePct }}%">
                                @if($paidPct > 0)
                                    <div class="cal-bar-paid" style="width: {{ $paidPct }}%;"></div>
                                @endif
                                @if($openPct > 0)
                                    <div class="cal-bar-open" style="width: {{ $openPct }}%;"></div>
                                @endif
                                @if($overduePct > 0)
                                    <div class="cal-bar-overdue" style="width: {{ $overduePct }}%;"></div>
                                @endif
                            </div>

                            {{-- Daily Summary Bar --}}
                            <div class="cal-day-summary-row">
                                <span><i class="fa fa-users text-primary"></i> {{ $stats['loan_count'] }} {{ $bi('Due', 'នាក់') }}</span>
                                <strong>{{ $money($stats['total_due']) }}</strong>
                            </div>
                        @endif
                    </div>

                    {{-- Customer Cards Directly on the Day --}}
                    @if(!empty($customers))
                        <div class="cal-customers-list">
                            @foreach($customers as $cust)
                                @php
                                    $chipClass = 'cal-chip-open';
                                    $dotClass = 'cal-dot-open';
                                    if ($cust['status'] === 'paid') {
                                        $chipClass = 'cal-chip-paid';
                                        $dotClass = 'cal-dot-paid';
                                    } elseif ($cust['status'] === 'overdue') {
                                        $chipClass = 'cal-chip-overdue';
                                        $dotClass = 'cal-dot-overdue';
                                    } elseif ($cust['status'] === 'due_today') {
                                        $chipClass = 'cal-chip-today';
                                        $dotClass = 'cal-dot-today';
                                    }
                                @endphp
                                <div class="cal-customer-chip {{ $chipClass }}"
                                     title="{{ $cust['customer_name'] }} ({{ $cust['loan_number'] }}) - {{ $bi('Due', 'ត្រូវបង់') }}: ${{ number_format($cust['amount_due'], 2) }}@if(!empty($cust['has_prior_unpaid'])) | {{ $bi('Prior Arrears', 'ជំពាក់ពីមុន') }}: +${{ number_format($cust['prior_balance'], 2) }} ({{ $cust['prior_count'] }} {{ $bi('inst.', 'វគ្គ') }}) | {{ $bi('Total Payable', 'សរុបត្រូវបង់') }}: ${{ number_format($cust['total_payable'], 2) }}@endif ({{ ucfirst($cust['status']) }})">
                                    <div class="cal-chip-left">
                                        <span class="cal-chip-dot {{ $dotClass }}"></span>
                                        <span class="cal-chip-name">{{ $cust['customer_name'] }}</span>
                                        @if(!empty($cust['has_prior_unpaid']))
                                            <span class="cal-chip-prior-tag" title="{{ $bi('Prior Arrears', 'ជំពាក់សល់ពីខែមុន') }}: ${{ number_format($cust['prior_balance'], 2) }}">
                                                +${{ number_format($cust['prior_balance'], 0) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div style="text-align:right; flex-shrink:0;">
                                        <span class="cal-chip-amount">${{ number_format($cust['amount_due'], 0) }}</span>
                                        @if(!empty($cust['has_prior_unpaid']))
                                            <small class="cal-chip-total-sub" title="{{ $bi('Total Payable (Prior + This Month)', 'សរុបត្រូវបង់ (ខែមុន + ខែនេះ)') }}">
                                                &Sigma;${{ number_format($cust['total_payable'], 0) }}
                                            </small>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            @if(($cell['more_customers_count'] ?? 0) > 0)
                                <div class="cal-more-chip js-cal-open-modal-trigger" data-date="{{ $cell['date'] }}" title="Click to view all {{ $cell['total_customers_count'] }} customers">
                                    +{{ $cell['more_customers_count'] }} {{ $bi('more...', 'នាក់ទៀត...') }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- =========================================================
         WEEK VIEW: 7 Expanded Columns with In-column Action Cards
         ========================================================= --}}
    <div class="cal-week-view" id="calWeekView">
        <div style="padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; color: #0f172a;" id="calWeekRangeTitle">
                <i class="fa fa-columns text-primary"></i> {{ $bi('Current Week Overview', 'ទិដ្ឋភាពទូទៅនៃសប្ដាហ៍នេះ') }}
            </div>
            <div style="display: flex; gap: 6px;">
                <button type="button" class="btn btn-default btn-xs" id="calPrevWeekBtn"><i class="fa fa-chevron-left"></i> {{ $bi('Prev Week', 'សប្ដាហ៍មុន') }}</button>
                <button type="button" class="btn btn-default btn-xs" id="calThisWeekBtn">{{ $bi('Current Week', 'សប្ដាហ៍នេះ') }}</button>
                <button type="button" class="btn btn-default btn-xs" id="calNextWeekBtn">{{ $bi('Next Week', 'សប្ដាហ៍បន្ទាប់') }} <i class="fa fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="cal-week-grid" id="calWeekGrid">
            {{-- Populated dynamically via JS from calendarDays --}}
        </div>
    </div>

</div>

{{-- =========================================================
     Day Schedule Customer Drilldown Modal (Supercharged UX)
     ========================================================= --}}
<div class="modal fade" id="installmentDayModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 980px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff; padding: 16px 22px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <h4 class="modal-title" id="installmentDayModalTitle" style="font-weight: 700; font-size: 18px;">
                    <i class="fa fa-calendar"></i> <span id="calModalDateText">{{ $bi('Installments Due', 'កាលវិភាគបង់ប្រាក់') }}</span>
                </h4>
                <div class="cal-modal-header-stats">
                    <span class="cal-modal-badge" style="background: rgba(255,255,255,0.22);" id="calModalCustomerCount">0 {{ $bi('Customers', 'អតិថិជន') }}</span>
                    <span class="cal-modal-badge" style="background: rgba(255,255,255,0.22);" id="calModalTotalDue">$0.00 {{ $bi('This Day Due', 'ត្រូវបង់ថ្ងៃនេះ') }}</span>
                    <span class="cal-modal-badge" style="background: rgba(225,29,72,0.4); border: 1px solid rgba(255,255,255,0.3);" id="calModalPriorBalance">+$0.00 {{ $bi('Prior Arrears', 'ជំពាក់ពីមុន') }}</span>
                    <span class="cal-modal-badge" style="background: #ffffff; color: #0284c7; font-weight: 800;" id="calModalGrandTotal">$0.00 {{ $bi('Total Payable', 'សរុបត្រូវបង់') }}</span>
                </div>
            </div>

            <div class="modal-body" style="padding: 16px 22px;">
                {{-- Status Filter Tabs inside Modal --}}
                <div class="cal-modal-tabs">
                    <button type="button" class="cal-modal-tab-btn is-active" data-modal-filter="all">
                        {{ $bi('All', 'ទាំងអស់') }} (<span id="modalTabCountAll">0</span>)
                    </button>
                    <button type="button" class="cal-modal-tab-btn" data-modal-filter="prior_unpaid" style="color:#be123c;">
                        <i class="fa fa-history text-danger"></i> {{ $bi('Has Prior Arrears', 'មានជំពាក់ខែមុន') }} (<span id="modalTabCountPrior">0</span>)
                    </button>
                    <button type="button" class="cal-modal-tab-btn" data-modal-filter="overdue">
                        <i class="fa fa-exclamation-triangle text-danger"></i> {{ $bi('Overdue', 'ហួសកំណត់') }} (<span id="modalTabCountOverdue">0</span>)
                    </button>
                    <button type="button" class="cal-modal-tab-btn" data-modal-filter="due_today">
                        <i class="fa fa-bell text-warning"></i> {{ $bi('Due Today', 'ត្រូវបង់ថ្ងៃនេះ') }} (<span id="modalTabCountDueToday">0</span>)
                    </button>
                    <button type="button" class="cal-modal-tab-btn" data-modal-filter="upcoming">
                        <i class="fa fa-clock-o text-info"></i> {{ $bi('Upcoming', 'មិនទាន់ដល់ថ្ងៃ') }} (<span id="modalTabCountUpcoming">0</span>)
                    </button>
                    <button type="button" class="cal-modal-tab-btn" data-modal-filter="paid">
                        <i class="fa fa-check text-success"></i> {{ $bi('Paid', 'បានបង់') }} (<span id="modalTabCountPaid">0</span>)
                    </button>
                </div>

                {{-- In-Modal Search & Action Toolbar --}}
                <div style="display: flex; gap: 10px; margin-bottom: 14px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 260px;">
                        <div class="input-group">
                            <span class="input-group-addon" style="background: #f8fafc; border-color: #cbd5e1;"><i class="fa fa-search"></i></span>
                            <input type="text" id="calDaySearch" class="form-control" placeholder="{{ $bi('Filter customer name, phone, loan #, invoice...', 'ស្វែងរកឈ្មោះអតិថិជន លេខទូរស័ព្ទ លេខកម្ចី...') }}" style="border-color: #cbd5e1;">
                        </div>
                    </div>
                    <div style="display: inline-flex; gap: 8px;">
                        <button type="button" class="btn btn-default btn-sm" id="calPrintDailySheetBtn" style="font-weight: 600; border-radius: 6px;">
                            <i class="fa fa-print"></i> {{ $bi('Print Collection Sheet', 'បោះពុម្ពតារាងប្រមូលប្រាក់') }}
                        </button>
                        <button type="button" class="btn btn-default btn-sm" id="calExportDayCsvBtn" style="font-weight: 600; border-radius: 6px;">
                            <i class="fa fa-file-excel-o text-success"></i> {{ $bi('Export Day CSV', 'ទាញយក CSV ថ្ងៃនេះ') }}
                        </button>
                    </div>
                </div>

                {{-- Table Container --}}
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table class="table table-hover table-striped" id="calDayDetailsTable" style="margin-bottom: 0;">
                        <thead style="background: #f1f5f9; position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>{{ $bi('Customer & Contact', 'អតិថិជន & ទំនាក់ទំនង') }}</th>
                                <th>{{ $bi('Loan Reference', 'លេខកម្ចី / វគ្គ') }}</th>
                                <th class="text-right">{{ $bi('This Mo Due', 'ត្រូវបង់ខែនេះ') }}</th>
                                <th class="text-right" style="color: #be123c;">{{ $bi('Prior Arrears', 'ជំពាក់សល់ពីខែមុន') }}</th>
                                <th class="text-right" style="color: #0369a1; font-weight: 800;">{{ $bi('Total Payable', 'សរុបត្រូវបង់') }}</th>
                                <th class="text-right">{{ $bi('Paid', 'បានបង់') }}</th>
                                <th class="text-right">{{ $bi('Balance', 'សមតុល្យ') }}</th>
                                <th class="text-center">{{ $bi('Status', 'ស្ថានភាព') }}</th>
                                <th class="text-center">{{ $bi('Action', 'សកម្មភាព') }}</th>
                            </tr>
                        </thead>
                        <tbody id="calDayDetailsTbody">
                            {{-- Dynamically populated via AJAX --}}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 22px; display: flex; justify-content: space-between; align-items: center;">
                <div class="text-muted" style="font-size: 12px;">
                    <i class="fa fa-keyboard-o"></i> {{ $bi('Press ESC to close', 'ចុច ESC ដើម្បីបិទផ្ទាំង') }}
                </div>
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 600; border-radius: 6px; padding: 6px 16px;">
                    {{ $bi('Close', 'បិទ') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('loan_js')
<script>
    (function ($) {
        var dayDetailsUrl = @json(route('loan-management.schedules.calendar-day-details'));
        var locationFilter = @json($filters['location_id'] ?? '');
        var isKhmer = @json($isKhmer);
        var calendarDays = @json($calendarDays ?? []);
        var prevUrl = @json($prevMonthUrl);
        var nextUrl = @json($nextMonthUrl);
        var todayUrl = @json($todayUrl);
        var currentActiveModalFilter = 'all';

        var i18n = {
            collectPayment: isKhmer ? 'ប្រមូលប្រាក់' : 'Collect',
            viewLoan: isKhmer ? 'មើលកម្ចី' : 'View',
            paid: isKhmer ? 'បានបង់' : 'Paid',
            dueToday: isKhmer ? 'ដល់ថ្ងៃបង់' : 'Due Today',
            overdue: isKhmer ? 'ហួសកំណត់' : 'Overdue',
            upcoming: isKhmer ? 'មិនទាន់ដល់ថ្ងៃ' : 'Upcoming',
            noCustomersFound: isKhmer ? 'មិនមានអតិថិជនត្រូវបង់ក្នុងលក្ខខណ្ឌនេះទេ។' : 'No customers found for this criteria.',
            daysOverdueSuffix: isKhmer ? ' ថ្ងៃ' : 'd late',
            installment: isKhmer ? 'វគ្គ ' : 'Inst #',
            call: isKhmer ? 'ហៅទូរស័ព្ទ' : 'Call'
        };

        function money(val) {
            return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function esc(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        var currentDayRecords = [];
        var activeModalDate = '';

        // -------------------------------------------------------------
        // Modal Table Rendering with Tabs & Debounced Search
        // -------------------------------------------------------------
        function renderDayRecords(records) {
            var query = String($('#calDaySearch').val() || '').toLowerCase().trim();
            var $tbody = $('#calDayDetailsTbody');

            var filtered = (records || []).filter(function (row) {
                // Tab filter
                if (currentActiveModalFilter === 'prior_unpaid') {
                    if (!row.has_prior_unpaid) return false;
                } else if (currentActiveModalFilter !== 'all' && row.status !== currentActiveModalFilter) {
                    return false;
                }
                // Text search
                if (!query) return true;
                return (
                    String(row.customer_name).toLowerCase().indexOf(query) !== -1 ||
                    String(row.customer_phone).toLowerCase().indexOf(query) !== -1 ||
                    String(row.loan_number).toLowerCase().indexOf(query) !== -1 ||
                    String(row.invoice_no).toLowerCase().indexOf(query) !== -1 ||
                    String(row.collector_name || '').toLowerCase().indexOf(query) !== -1
                );
            });

            if (!filtered.length) {
                $tbody.html('<tr><td colspan="9" class="text-center text-muted" style="padding: 32px;"><i class="fa fa-info-circle" style="font-size:20px;display:block;margin-bottom:6px;opacity:0.5;"></i>' + i18n.noCustomersFound + '</td></tr>');
                return;
            }

            var html = '';
            filtered.forEach(function (row) {
                var initial = row.customer_name ? row.customer_name.charAt(0).toUpperCase() : 'C';
                var statusBadge = '';
                if (row.status === 'paid') {
                    statusBadge = '<span class="label label-success" style="font-size:11px;padding:3px 8px;">' + i18n.paid + '</span>';
                } else if (row.status === 'due_today') {
                    statusBadge = '<span class="label label-warning" style="font-size:11px;padding:3px 8px;">' + i18n.dueToday + '</span>';
                } else if (row.status === 'overdue') {
                    statusBadge = '<span class="label label-danger" style="font-size:11px;padding:3px 8px;">' + i18n.overdue + ' (' + row.overdue_days + i18n.daysOverdueSuffix + ')</span>';
                } else {
                    statusBadge = '<span class="label label-info" style="font-size:11px;padding:3px 8px;">' + i18n.upcoming + '</span>';
                }

                var payBtn = (row.balance_amount > 0 || (row.prior_balance && row.prior_balance > 0))
                    ? '<button type="button" class="btn btn-success btn-xs btn-modal" data-href="' + esc(row.payment_url) + '" data-container=".view_modal" style="font-weight:600;border-radius:4px;padding:3px 8px;"><i class="fa fa-money"></i> ' + i18n.collectPayment + '</button>'
                    : '<button type="button" class="btn btn-default btn-xs" disabled style="opacity: 0.6;border-radius:4px;"><i class="fa fa-check"></i> ' + i18n.paid + '</button>';

                var contactButtons = '';
                if (row.customer_phone && row.customer_phone !== '-') {
                    var cleanPhone = String(row.customer_phone).replace(/[^0-9+]/g, '');
                    contactButtons = '<span class="cal-contact-actions">' +
                        '<a href="tel:' + esc(cleanPhone) + '" class="cal-contact-btn" title="' + i18n.call + '"><i class="fa fa-phone"></i></a>' +
                    '</span>';
                }

                var priorHtml = row.has_prior_unpaid
                    ? '<span class="text-danger" style="font-weight:700;">+' + money(row.prior_balance) + '</span><small class="text-muted" style="display:block;font-size:10px;">(' + row.prior_count + ' ' + (isKhmer ? 'វគ្គមុន' : 'mos') + ')</small>'
                    : '<span class="text-muted" style="font-size:11px;">$0.00</span>';

                var totalPayableHtml = '<strong style="font-size:13px;' + (row.has_prior_unpaid ? 'color:#be123c;' : 'color:#0f172a;') + '">' + money(row.total_payable) + '</strong>';

                html += '<tr>' +
                    '<td>' +
                        '<div class="cal-customer-cell">' +
                            '<div class="cal-customer-avatar">' + esc(initial) + '</div>' +
                            '<div class="cal-customer-info">' +
                                '<a href="' + esc(row.detail_url) + '" class="js-loan-detail-modal" data-title="' + esc(row.customer_name) + '">' + esc(row.customer_name) + '</a>' +
                                '<small><i class="fa fa-phone text-muted"></i> ' + esc(row.customer_phone) + contactButtons + '</small>' +
                            '</div>' +
                        '</div>' +
                    '</td>' +
                    '<td>' +
                        '<strong style="color:#0f172a;">' + esc(row.loan_number) + '</strong>' +
                        '<small class="text-muted" style="display:block;">' + i18n.installment + esc(row.installment_no) + (row.location_name && row.location_name !== '-' ? ' &bull; ' + esc(row.location_name) : '') + '</small>' +
                    '</td>' +
                    '<td class="text-right" style="font-weight: 700; color: #0f172a;">' + money(row.amount_due) + '</td>' +
                    '<td class="text-right">' + priorHtml + '</td>' +
                    '<td class="text-right">' + totalPayableHtml + '</td>' +
                    '<td class="text-right text-success" style="font-weight: 600;">' + money(row.paid_amount) + '</td>' +
                    '<td class="text-right" style="font-weight: 800; color: ' + (row.balance_amount > 0 ? '#dc2626' : '#15803d') + ';">' + money(row.balance_amount) + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td class="text-center">' + payBtn + '</td>' +
                '</tr>';
            });

            $tbody.html(html);
        }

        // -------------------------------------------------------------
        // Cell Click Handler to Open Day Drilldown Modal
        // -------------------------------------------------------------
        function openDayModal(date) {
            if (!date) return;

            activeModalDate = date;
            currentActiveModalFilter = 'all';
            $('.cal-modal-tab-btn').removeClass('is-active');
            $('.cal-modal-tab-btn[data-modal-filter="all"]').addClass('is-active');

            var dateObj = new Date(date + 'T00:00:00');
            var formattedDate = date;
            try {
                formattedDate = dateObj.toLocaleDateString(isKhmer ? 'km-KH' : 'en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch (e) {}

            $('#calModalDateText').text(formattedDate);
            $('#calModalCustomerCount').text('...');
            $('#calModalTotalDue').text('...');
            $('#calModalPriorBalance').text('...');
            $('#calModalGrandTotal').text('...');
            $('#calDaySearch').val('');
            $('#calDayDetailsTbody').html('<tr><td colspan="9" class="text-center text-muted" style="padding: 32px;"><i class="fa fa-spinner fa-spin" style="font-size: 20px;"></i><span style="display:block;margin-top:6px;">Loading scheduled customers...</span></td></tr>');
            $('#installmentDayModal').modal('show');

            $.ajax({
                url: dayDetailsUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    date: date,
                    location_id: locationFilter
                },
                success: function (res) {
                    if (res && res.success) {
                        currentDayRecords = res.rows || [];
                        $('#calModalCustomerCount').text(res.count + ' ' + (isKhmer ? 'អតិថិជន' : 'Customers'));
                        $('#calModalTotalDue').text(money(res.total_due) + ' ' + (isKhmer ? 'ត្រូវបង់ថ្ងៃនេះ' : 'This Day Due'));
                        $('#calModalPriorBalance').text('+' + money(res.total_prior_balance || 0) + ' ' + (isKhmer ? 'ជំពាក់ពីមុន' : 'Prior Arrears'));
                        $('#calModalGrandTotal').text(money(res.grand_total_payable || res.total_due) + ' ' + (isKhmer ? 'សរុបត្រូវបង់' : 'Total Payable'));

                        // Update Tab Counts
                        $('#modalTabCountAll').text(res.count);
                        $('#modalTabCountPrior').text(res.prior_unpaid_count || 0);
                        $('#modalTabCountOverdue').text(res.overdue_count || 0);
                        $('#modalTabCountDueToday').text(res.due_today_count || 0);
                        $('#modalTabCountUpcoming').text(res.upcoming_count || 0);
                        $('#modalTabCountPaid').text(res.paid_count || 0);

                        renderDayRecords(currentDayRecords);
                    } else {
                        $('#calDayDetailsTbody').html('<tr><td colspan="9" class="text-center text-danger" style="padding: 24px;">Failed to load data.</td></tr>');
                    }
                },
                error: function () {
                    $('#calDayDetailsTbody').html('<tr><td colspan="9" class="text-center text-danger" style="padding: 24px;">Network error loading day details.</td></tr>');
                }
            });
        }

        $(document).on('click', '.js-cal-day-cell', function (e) {
            var date = $(this).data('date');
            openDayModal(date);
        });

        $(document).on('click', '.js-cal-open-modal-trigger', function (e) {
            e.stopPropagation();
            var date = $(this).data('date');
            openDayModal(date);
        });

        // -------------------------------------------------------------
        // In-Modal Filter Tabs
        // -------------------------------------------------------------
        $('.cal-modal-tab-btn').on('click', function () {
            $('.cal-modal-tab-btn').removeClass('is-active');
            $(this).addClass('is-active');
            currentActiveModalFilter = $(this).data('modal-filter') || 'all';
            renderDayRecords(currentDayRecords);
        });

        $('#calDaySearch').on('input', function () {
            renderDayRecords(currentDayRecords);
        });

        // -------------------------------------------------------------
        // Print Daily Collection Sheet
        // -------------------------------------------------------------
        $('#calPrintDailySheetBtn').on('click', function () {
            if (!currentDayRecords.length) return;
            var printWin = window.open('', '_blank');
            var dateTitle = $('#calModalDateText').text();

            var rowsHtml = '';
            currentDayRecords.forEach(function (r, idx) {
                rowsHtml += '<tr>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:center;">' + (idx + 1) + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;"><strong>' + esc(r.customer_name) + '</strong><br><small>' + esc(r.customer_phone) + '</small></td>' +
                    '<td style="padding:6px;border:1px solid #ddd;">' + esc(r.loan_number) + ' (Inst #' + esc(r.installment_no) + ')</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:right;">$' + Number(r.amount_due).toFixed(2) + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:right;color:#be123c;">' + (r.has_prior_unpaid ? '+$' + Number(r.prior_balance).toFixed(2) + ' (' + r.prior_count + ' mos)' : '$0.00') + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:right;font-weight:bold;background:#f8fafc;">$' + Number(r.total_payable).toFixed(2) + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:right;">$' + Number(r.paid_amount).toFixed(2) + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:right;font-weight:bold;">$' + Number(r.balance_amount).toFixed(2) + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;text-align:center;">' + (r.balance_amount <= 0 ? 'Paid' : (r.status === 'overdue' ? 'Overdue' : 'Due')) + '</td>' +
                    '<td style="padding:6px;border:1px solid #ddd;width:110px;"></td>' +
                '</tr>';
            });

            var doc = '<!DOCTYPE html><html><head><title>Collection Sheet - ' + esc(dateTitle) + '</title>' +
                '<style>body{font-family:Arial,sans-serif;font-size:11.5px;margin:20px;}table{width:100%;border-collapse:collapse;}th{background:#f0f4f8;padding:6px 8px;border:1px solid #ddd;font-weight:bold;text-align:left;}</style>' +
                '</head><body>' +
                '<h2>Installment Collection Sheet</h2>' +
                '<p><strong>Date:</strong> ' + esc(dateTitle) + ' | <strong>Total Customers:</strong> ' + currentDayRecords.length + '</p>' +
                '<table><thead><tr>' +
                '<th>#</th><th>Customer Name / Phone</th><th>Loan Ref</th><th style="text-align:right;">This Mo Due</th><th style="text-align:right;color:#be123c;">Prior Arrears</th><th style="text-align:right;">Total Payable</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Balance</th><th>Status</th><th>Borrower Signature</th>' +
                '</tr></thead><tbody>' + rowsHtml + '</tbody></table>' +
                '<p style="margin-top:30px;">Collector Signature: _______________________ Date: ___________________</p>' +
                '<script>window.onload=function(){window.print();}<\/script></body></html>';

            printWin.document.write(doc);
            printWin.document.close();
        });

        // -------------------------------------------------------------
        // Export Day Customers to CSV
        // -------------------------------------------------------------
        $('#calExportDayCsvBtn').on('click', function () {
            if (!currentDayRecords.length) return;
            var csv = 'Customer,Phone,Loan Ref,Invoice,Installment No,This Month Due,Prior Arrears,Prior Unpaid Months,Total Payable,Paid Amount,Balance Amount,Status,Days Overdue,Location\n';
            currentDayRecords.forEach(function (r) {
                csv += '"' + (r.customer_name || '').replace(/"/g, '""') + '",' +
                    '"' + (r.customer_phone || '').replace(/"/g, '""') + '",' +
                    '"' + (r.loan_number || '').replace(/"/g, '""') + '",' +
                    '"' + (r.invoice_no || '').replace(/"/g, '""') + '",' +
                    r.installment_no + ',' +
                    r.amount_due + ',' +
                    r.prior_balance + ',' +
                    r.prior_count + ',' +
                    r.total_payable + ',' +
                    r.paid_amount + ',' +
                    r.balance_amount + ',' +
                    '"' + r.status + '",' +
                    r.overdue_days + ',' +
                    '"' + (r.location_name || '').replace(/"/g, '""') + '"\n';
            });

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', 'collection-day-' + activeModalDate + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // -------------------------------------------------------------
        // Interactive Calendar Status Filter Pills (Legend)
        // -------------------------------------------------------------
        $('.cal-filter-pill').on('click', function () {
            $('.cal-filter-pill').removeClass('is-active');
            $(this).addClass('is-active');

            var filter = $(this).data('status-filter');
            var $cells = $('.cal-cell');

            if (filter === 'all') {
                $cells.removeClass('is-dimmed is-highlighted');
                return;
            }

            $cells.each(function () {
                var $c = $(this);
                var match = false;

                if (filter === 'prior' && $c.data('has-prior') == '1') match = true;
                if (filter === 'overdue' && $c.data('has-overdue') == '1') match = true;
                if (filter === 'today' && $c.data('is-today') == '1') match = true;
                if (filter === 'open' && $c.data('has-open') == '1') match = true;
                if (filter === 'paid' && $c.data('is-paid') == '1') match = true;

                if (match) {
                    $c.removeClass('is-dimmed').addClass('is-highlighted');
                } else {
                    $c.removeClass('is-highlighted').addClass('is-dimmed');
                }
            });
        });

        // -------------------------------------------------------------
        // View Mode Switcher: Month View ⇄ Week View
        // -------------------------------------------------------------
        var currentWeekIndex = 0;
        var weeksList = [];

        // Chunk calendarDays into 7-day weeks
        for (var i = 0; i < calendarDays.length; i += 7) {
            weeksList.push(calendarDays.slice(i, i + 7));
        }

        // Find which week contains "today" or first current-month day
        for (var w = 0; w < weeksList.length; w++) {
            var hasToday = weeksList[w].some(function (d) { return d.is_today; });
            if (hasToday) {
                currentWeekIndex = w;
                break;
            }
        }

        function renderWeekView() {
            var week = weeksList[currentWeekIndex] || [];
            if (!week.length) return;

            var startDay = week[0].date;
            var endDay = week[week.length - 1].date;
            $('#calWeekRangeTitle').html('<i class="fa fa-columns text-primary"></i> ' + startDay + ' &mdash; ' + endDay);

            var html = '';
            var dayNames = isKhmer
                ? ['អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍']
                : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            week.forEach(function (d, idx) {
                var stats = d.stats;
                var hasSchedules = stats && stats.loan_count > 0;
                var isToday = d.is_today;
                var custs = d.customers_preview || [];

                html += '<div class="cal-week-col ' + (isToday ? 'is-today' : '') + '">' +
                    '<div class="cal-week-col-head">' +
                        '<div class="cal-week-col-name">' + dayNames[idx] + '</div>' +
                        '<div class="cal-week-col-date">' + d.day + (isToday ? ' <span class="cal-today-badge">' + (isKhmer ? 'ថ្ងៃនេះ' : 'Today') + '</span>' : '') + '</div>' +
                        '<div class="cal-week-col-summary">' +
                            (hasSchedules ? stats.loan_count + ' ' + (isKhmer ? 'អតិថិជន' : 'due') + ' &bull; ' + money(stats.total_due) : '<span class="text-muted">' + (isKhmer ? 'គ្មានកម្ចីដល់ថ្ងៃ' : 'No schedules') + '</span>') +
                        '</div>' +
                    '</div>' +
                    '<div class="cal-week-items-wrap">';

                if (hasSchedules) {
                    html += '<button type="button" class="btn btn-primary btn-block btn-xs js-open-day-direct" data-date="' + esc(d.date) + '" style="font-weight:600;margin-bottom:8px;border-radius:6px;">' +
                        '<i class="fa fa-list"></i> ' + (isKhmer ? 'មើលអតិថិជនទាំងអស់ (' + stats.loan_count + ')' : 'View All (' + stats.loan_count + ')') +
                    '</button>';

                    custs.forEach(function (c) {
                        var statusDot = c.status === 'paid' ? 'cal-dot-paid' : (c.status === 'overdue' ? 'cal-dot-overdue' : (c.status === 'due_today' ? 'cal-dot-today' : 'cal-dot-open'));
                        var statusBorder = c.status === 'paid' ? '#16a34a' : (c.status === 'overdue' ? '#dc2626' : (c.status === 'due_today' ? '#d97706' : '#0284c7'));
                        var priorTag = c.has_prior_unpaid ? ' <span class="cal-chip-prior-tag" title="Prior: $' + Number(c.prior_balance).toFixed(2) + '">+$' + Number(c.prior_balance).toFixed(0) + '</span>' : '';
                        
                        html += '<div class="cal-week-card" style="border-left: 3px solid ' + statusBorder + ';">' +
                            '<div class="cal-week-card-top">' +
                                '<strong style="color:#0f172a;"><span class="cal-chip-dot ' + statusDot + '" style="display:inline-block;margin-right:4px;"></span>' + esc(c.customer_name) + priorTag + '</strong>' +
                                '<div style="text-align:right;">' +
                                    '<strong style="color:#0f172a;">$' + Number(c.amount_due).toFixed(0) + '</strong>' +
                                    (c.has_prior_unpaid ? '<small class="cal-chip-total-sub">&Sigma;$' + Number(c.total_payable).toFixed(0) + '</small>' : '') +
                                '</div>' +
                            '</div>' +
                            '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">' +
                                '<small class="text-muted">' + esc(c.loan_number) + '</small>' +
                                (c.balance_amount > 0 ? '<button type="button" class="btn btn-success btn-xs btn-modal" data-href="' + esc(c.payment_url) + '" data-container=".view_modal" style="font-size:10px;padding:1px 6px;border-radius:3px;">' + i18n.collectPayment + '</button>' : '<span class="text-success" style="font-size:10.5px;font-weight:700;"><i class="fa fa-check"></i> ' + i18n.paid + '</span>') +
                            '</div>' +
                        '</div>';
                    });

                    if (d.more_customers_count > 0) {
                        html += '<button type="button" class="btn btn-default btn-block btn-xs js-open-day-direct" data-date="' + esc(d.date) + '" style="font-weight:600;border-radius:6px;margin-top:4px;color:#0284c7;">' +
                            '+' + d.more_customers_count + ' ' + (isKhmer ? 'នាក់ទៀត...' : 'more...') +
                        '</button>';
                    }
                } else {
                    html += '<div style="text-align:center;padding:24px 8px;color:#94a3b8;font-size:11.5px;">-</div>';
                }

                html += '</div></div>';
            });

            $('#calWeekGrid').html(html);
        }

        $('.js-view-mode-btn').on('click', function () {
            $('.js-view-mode-btn').removeClass('is-active');
            $(this).addClass('is-active');
            var mode = $(this).data('view');

            if (mode === 'week') {
                $('#calMonthView').hide();
                $('#calWeekView').show();
                renderWeekView();
            } else {
                $('#calWeekView').hide();
                $('#calMonthView').show();
            }
        });

        $('#calPrevWeekBtn').on('click', function () {
            if (currentWeekIndex > 0) {
                currentWeekIndex--;
                renderWeekView();
            }
        });

        $('#calNextWeekBtn').on('click', function () {
            if (currentWeekIndex < weeksList.length - 1) {
                currentWeekIndex++;
                renderWeekView();
            }
        });

        $('#calThisWeekBtn').on('click', function () {
            for (var w = 0; w < weeksList.length; w++) {
                if (weeksList[w].some(function (d) { return d.is_today; })) {
                    currentWeekIndex = w;
                    break;
                }
            }
            renderWeekView();
        });

        $(document).on('click', '.js-open-day-direct', function () {
            var date = $(this).data('date');
            openDayModal(date);
        });

        // -------------------------------------------------------------
        // Keyboard Shortcuts: Left Arrow, Right Arrow, 'T' for Today
        // -------------------------------------------------------------
        $(document).on('keydown', function (e) {
            if ($(e.target).is('input, select, textarea')) return;

            if (e.key === 'ArrowLeft') {
                window.location.href = prevUrl;
            } else if (e.key === 'ArrowRight') {
                window.location.href = nextUrl;
            } else if (e.key === 't' || e.key === 'T') {
                window.location.href = todayUrl;
            }
        });

    })(jQuery);
</script>
@endsection
