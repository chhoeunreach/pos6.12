@extends('loanmanagement::layouts.app')
@section('title', 'Business Settings')

@section('loan_css')
<link rel="stylesheet" href="{{ asset('css/loan-settings.css') }}?v=20261029">
<style>
    /* ========================================================
       ENTERPRISE UNIFIED SETTINGS HUB - MODERN EXECUTIVE REDESIGN
       ======================================================== */
    .lm-settings-wrapper {
        color: #0f172a;
        padding-bottom: 60px;
    }

    /* Page Header */
    .lm-settings-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }
    .lm-settings-header-left h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .lm-settings-header-left p {
        margin: 4px 0 0;
        font-size: 13px;
        color: #64748b;
    }
    .lm-settings-header-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .lm-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
    }
    .lm-chip i { font-size: 13px; color: var(--lm-primary, #6366f1); }
    .lm-chip-success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .lm-chip-success i { color: #059669; }

    /* Search Bar */
    .lm-settings-searchbar {
        position: relative;
        margin-bottom: 20px;
    }
    .lm-settings-searchbar input {
        width: 100%;
        height: 46px;
        padding: 0 44px 0 46px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #fff;
        font-size: 14px;
        color: #0f172a;
        box-shadow: 0 1px 3px rgba(15,23,42,0.04);
        transition: all 0.2s ease;
    }
    .lm-settings-searchbar input:focus {
        border-color: var(--lm-primary, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }
    .lm-settings-searchbar .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 16px;
        pointer-events: none;
    }
    .lm-settings-searchbar .clear-search {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 14px;
        display: none;
    }

    /* Main Container Layout */
    .lm-settings-shell {
        display: grid;
        grid-template-columns: 290px minmax(0, 1fr);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    /* Nav Sidebar */
    .lm-settings-nav {
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        padding: 20px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .lm-nav-group-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        padding: 6px 12px 8px;
        margin: 0;
    }
    .lm-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        margin-bottom: 4px;
        border-radius: 8px;
        color: #475569;
        font-size: 13.5px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }
    .lm-nav-item:hover {
        background: #f1f5f9;
        color: #0f172a;
        text-decoration: none;
    }
    .lm-nav-item.active {
        background: #fff;
        color: var(--lm-primary, #6366f1);
        border-color: #e2e8f0;
        box-shadow: 0 2px 6px rgba(15,23,42,0.04);
        font-weight: 800;
    }
    .lm-nav-item i {
        font-size: 16px;
        width: 20px;
        text-align: center;
        color: #64748b;
        transition: color 0.15s ease;
    }
    .lm-nav-item.active i {
        color: var(--lm-primary, #6366f1);
    }
    .lm-nav-item .nav-badge {
        margin-left: auto;
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 999px;
        background: #e2e8f0;
        color: #475569;
        font-weight: 700;
    }
    .lm-nav-item.active .nav-badge {
        background: var(--lm-primary-50, #eef2ff);
        color: var(--lm-primary, #6366f1);
    }
    .lm-nav-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 14px 6px;
    }

    /* Content Area */
    .lm-settings-content {
        padding: 32px 36px;
        min-height: 640px;
    }
    .lm-tab-pane {
        display: none;
        animation: fadeIn 0.18s ease-out;
    }
    .lm-tab-pane.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Section Headers */
    .lm-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }
    .lm-section-head h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
    }
    .lm-section-head p {
        margin: 4px 0 0;
        font-size: 13px;
        color: #64748b;
    }

    /* Form Grids */
    .lm-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px 24px;
    }
    .lm-col-full { grid-column: 1 / -1; }

    /* Form Controls */
    .lm-field {
        position: relative;
    }
    .lm-field-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
        color: #1e293b;
        font-size: 13px;
        font-weight: 800;
    }
    .lm-field-label .req { color: #ef4444; margin-left: 2px; }
    .lm-input, select.lm-input, textarea.lm-input {
        width: 100%;
        height: 42px;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 13.5px;
        box-shadow: none;
        transition: all 0.15s ease;
    }
    .lm-input:focus, select.lm-input:focus, textarea.lm-input:focus {
        border-color: var(--lm-primary, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }
    textarea.lm-input {
        height: auto;
        min-height: 90px;
        resize: vertical;
        line-height: 1.55;
    }
    .lm-input-group {
        display: flex;
        position: relative;
        width: 100%;
    }
    .lm-input-group .lm-addon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 14px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-right: 0;
        border-radius: 8px 0 0 8px;
        color: #64748b;
        font-size: 13.5px;
        font-weight: 700;
    }
    .lm-input-group .lm-input {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    .lm-addon-right {
        border-right: 1px solid #cbd5e1 !important;
        border-left: 0 !important;
        border-radius: 0 8px 8px 0 !important;
    }
    .lm-input-group .lm-input-with-addon-right {
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }
    .lm-field-hint {
        margin-top: 6px;
        font-size: 11.5px;
        color: #64748b;
        line-height: 1.4;
    }

    /* Toggle Switch */
    .lm-toggle-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        transition: all 0.15s ease;
    }
    .lm-toggle-card:hover {
        background: #fff;
        border-color: #cbd5e1;
    }
    .lm-toggle-info {
        padding-right: 16px;
    }
    .lm-toggle-title {
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .lm-toggle-desc {
        font-size: 12px;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
    }
    .lm-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }
    .lm-switch input { opacity: 0; width: 0; height: 0; }
    .lm-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .2s;
        border-radius: 34px;
    }
    .lm-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .2s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    .lm-switch input:checked + .lm-slider {
        background-color: var(--lm-primary, #6366f1);
    }
    .lm-switch input:checked + .lm-slider:before {
        transform: translateX(20px);
    }

    /* Media Upload Cards */
    .lm-upload-box {
        display: grid;
        grid-template-columns: 100px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        padding: 16px;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
    }
    .lm-preview-thumb {
        width: 100px;
        height: 100px;
        border-radius: 10px;
        background: #fff;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: #94a3b8;
        font-size: 32px;
    }
    .lm-preview-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    .lm-upload-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .lm-file-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        cursor: pointer;
        width: fit-content;
        transition: all 0.15s ease;
    }
    .lm-file-btn:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
    }
    .lm-file-btn input[type="file"] { display: none; }

    /* Color Swatches */
    .lm-color-picker-box {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lm-color-picker-box input[type="color"] {
        width: 44px;
        height: 42px;
        padding: 2px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        background: #fff;
    }
    .lm-swatches-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    .lm-swatch-circle {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px #cbd5e1;
        cursor: pointer;
        transition: transform 0.12s ease;
    }
    .lm-swatch-circle:hover {
        transform: scale(1.15);
    }

    /* Payment Methods Grid */
    .lm-methods-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .lm-method-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(15,23,42,0.03);
        transition: all 0.2s ease;
    }
    .lm-method-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(15,23,42,0.06);
    }
    .lm-method-card.inactive {
        background: #f8fafc;
        opacity: 0.75;
    }
    .lm-method-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .lm-method-header-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
    }
    .lm-method-icon-circle {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--lm-primary-50, #eef2ff);
        color: var(--lm-primary, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }
    .lm-method-row-2 {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 90px;
        gap: 10px;
        margin-top: 10px;
    }
    .lm-method-stats {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
        font-size: 12px;
        color: #64748b;
    }
    .lm-method-stats strong { color: #0f172a; font-size: 13px; }
    .lm-add-method-card {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        padding: 20px;
        margin-bottom: 24px;
    }

    /* CMS Accordions & Mockup */
    .lm-cms-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(340px, 0.9fr);
        gap: 24px;
        align-items: start;
    }
    .lm-cms-group {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        margin-bottom: 14px;
        overflow: hidden;
    }
    .lm-cms-group summary {
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 800;
        color: #1e293b;
        cursor: pointer;
        background: #f8fafc;
    }
    .lm-cms-group[open] summary {
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
    }
    .lm-cms-group-body {
        padding: 16px;
        background: #fff;
    }
    .lm-preview-box {
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 10px 25px -5px rgba(15,23,42,0.1);
        position: sticky;
        top: 20px;
    }
    .lm-preview-browser-bar {
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .lm-browser-dot { width: 9px; height: 9px; border-radius: 50%; background: #cbd5e1; }
    .lm-browser-address {
        flex: 1;
        background: #fff;
        border-radius: 4px;
        padding: 3px 10px;
        font-size: 11px;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .lm-preview-hero {
        min-height: 260px;
        padding: 22px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: #fff;
        background: linear-gradient(135deg, rgba(15,23,42,0.8), rgba(99,102,241,0.5)), var(--lm-hero-preview, #1e293b) center/cover;
    }
    .lm-preview-brand {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        font-size: 14px;
    }
    .lm-preview-logo {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        overflow: hidden;
        background: rgba(255,255,255,0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 800;
    }
    .lm-preview-logo img { width: 100%; height: 100%; object-fit: contain; }

    /* Social Media & Follow Us Cards */
    .lm-social-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-top: 18px;
    }
    @media (max-width: 900px) {
        .lm-social-grid {
            grid-template-columns: 1fr;
        }
    }
    .lm-social-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 18px;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .lm-social-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
    }
    .lm-social-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .lm-social-meta {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lm-social-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }
    .lm-social-info h4 {
        margin: 0;
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
    }
    .lm-social-info p {
        margin: 2px 0 0;
        font-size: 11.5px;
        color: #64748b;
    }
    .lm-social-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .lm-social-badge.active {
        background: #ecfdf5;
        color: #059669;
    }
    .lm-social-badge.inactive {
        background: #f1f5f9;
        color: #94a3b8;
    }
    .lm-social-input-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .lm-social-test-btn {
        height: 40px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.15s ease;
    }
    .lm-social-test-btn:hover:not(.disabled) {
        background: #0f172a;
        color: #fff;
        border-color: #0f172a;
    }
    .lm-social-test-btn.disabled {
        opacity: 0.45;
        pointer-events: none;
    }
    .lm-social-preview-container {
        background: #18181b;
        border: 1px solid #27272a;
        border-radius: 12px;
        padding: 20px;
        color: #fff;
        margin-top: 20px;
    }
    .lm-social-preview-title {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #a1a1aa;
        margin-bottom: 12px;
    }
    .lm-social-preview-icons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .lm-social-preview-link {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #27272a;
        border: 1px solid #3f3f46;
        color: #e4e4e7;
        display: inline-grid;
        place-items: center;
        font-size: 16px;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .lm-social-preview-link:hover {
        background: var(--lm-primary, #6366f1);
        border-color: var(--lm-primary, #6366f1);
        color: #fff;
        transform: translateY(-2px);
    }

    /* Sticky Bottom Action Bar */
    .lm-sticky-actions {
        position: sticky;
        bottom: 16px;
        z-index: 100;
        margin-top: 24px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 14px 24px;
        box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .lm-save-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }
    .lm-save-status .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
    }

    @media (max-width: 1024px) {
        .lm-settings-shell { grid-template-columns: 1fr; }
        .lm-settings-nav {
            border-right: 0;
            border-bottom: 1px solid #e2e8f0;
            flex-direction: row;
            overflow-x: auto;
            padding: 12px;
            gap: 6px;
        }
        .lm-nav-item { white-space: nowrap; margin-bottom: 0; }
        .lm-nav-divider, .lm-nav-group-title { display: none; }
        .lm-form-grid, .lm-methods-grid, .lm-cms-workspace { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content_body')
@php
    $canSaveSettings = auth()->user()->can('loan_management.setting');
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $themePresets = ['#6366f1', '#2563eb', '#0891b2', '#059669', '#dc2626', '#7c3aed', '#0f172a'];
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::logoUrl();
    $stampUrl = \Modules\LoanManagement\Services\BusinessSettingsService::stampUrl();
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();
    $monthOptions = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    $dateFormatOptions = [
        'd-m-Y' => 'dd-mm-yyyy (e.g. 31-12-2026)',
        'm-d-Y' => 'mm-dd-yyyy (e.g. 12-31-2026)',
        'Y-m-d' => 'yyyy-mm-dd (e.g. 2026-12-31)',
        'd/m/Y' => 'dd/mm/yyyy (e.g. 31/12/2026)',
        'm/d/Y' => 'mm/dd/yyyy (e.g. 12/31/2026)',
    ];
    $currencySymbolMap = ['USD' => '$', 'KHR' => '៛', 'THB' => '฿'];
    $savedCurrencyCode = old('currency_code', $settings['currency_code']);
    $savedCurrencySymbol = old('currency_symbol', $settings['currency_symbol'] ?: ($currencySymbolMap[$savedCurrencyCode] ?? $savedCurrencyCode));
    $currentInterestRate = old('default_interest_rate', $settings['default_interest_rate'] ?? ($settings['default_profit_percent'] ?? '1.50'));

    $paymentMethods = $paymentMethods ?? collect([]);
    $methodUsage = collect($usage ?? []);
    $activeMethodsCount = $paymentMethods->where('is_active', 1)->count();
@endphp

<div class="lm-settings-wrapper" data-can-save="{{ $canSaveSettings ? '1' : '0' }}" @if($loginBackgroundUrl) style="--lm-login-background: url('{{ $loginBackgroundUrl }}');" @endif>

    <!-- Top Header -->
    <div class="lm-settings-header">
        <div class="lm-settings-header-left">
            <h1><i class="fa fa-sliders" style="color:var(--lm-primary); margin-right:8px;"></i> {{ $lmText('Business Settings', 'មជ្ឈមណ្ឌលកំណត់ប្រព័ន្ធ និងអាជីវកម្ម') }}</h1>
            <p>{{ $lmText('Unified control center for company profile, loan policies, payment channels, CMS website, and alert gateways.', 'មជ្ឈមណ្ឌលគ្រប់គ្រងព័ត៌មានក្រុមហ៊ុន គោលការណ៍ឥណទាន វិធីបង់ប្រាក់ គេហទំព័រ CMS និងការជូនដំណឹង។') }}</p>
        </div>
        <div class="lm-settings-header-chips">
            <span class="lm-chip"><i class="fa fa-money"></i> {{ $savedCurrencyCode }} ({{ $savedCurrencySymbol }})</span>
            <span class="lm-chip"><i class="fa fa-percent"></i> {{ $currentInterestRate }}% / {{ ucfirst($settings['interest_rate_period'] ?? 'monthly') }}</span>
            <span class="lm-chip"><i class="fa fa-credit-card"></i> {{ $activeMethodsCount }} {{ $lmText('Active Channels', 'វិធីបង់ប្រាក់') }}</span>
            @if(!empty($settings['telegram_bot_token']))
                <span class="lm-chip lm-chip-success"><i class="fa fa-telegram"></i> Telegram Configured</span>
            @endif
        </div>
    </div>

    <!-- Real-time Search Box -->
    <div class="lm-settings-searchbar">
        <i class="fa fa-search search-icon"></i>
        <input type="search" id="businessSettingsSearch" aria-label="Search settings" placeholder="{{ $lmText('Search any setting (e.g. interest rate, ABA bank, logo, telegram, penalty, CMS)...', 'ស្វែងរកការកំណត់ (ឧ. អត្រាការប្រាក់, ធនាគារ ABA, រូបសញ្ញា, តេឡេក្រាម, CMS)...') }}" autocomplete="off">
        <button type="button" class="clear-search" id="clearSearchBtn" aria-label="Clear search"><i class="fa fa-times-circle"></i></button>
    </div>

    @php
        $loanSessionStatus = session('status');
        $loanSessionStatusMessage = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'msg') : $loanSessionStatus;
        $loanSessionStatusSuccess = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'success', 1) : 1;
    @endphp
    @if($loanSessionStatusMessage)
        <div class="alert alert-{{ $loanSessionStatusSuccess ? 'success' : 'danger' }} alert-dismissible" style="border-radius:10px; margin-bottom:18px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-{{ $loanSessionStatusSuccess ? 'check-circle' : 'exclamation-circle' }}"></i> {{ $loanSessionStatusMessage }}
        </div>
    @endif

    <div id="settingsSearchSummary" class="lm-settings-search-summary" role="status" aria-live="polite" hidden></div>
    @unless($canSaveSettings)
        <div class="alert alert-info"><i class="fa fa-lock" aria-hidden="true"></i> {{ $lmText('You can view settings. Ask an administrator for permission to save changes.', 'អ្នកអាចមើលការកំណត់។ សូមទាក់ទងអ្នកគ្រប់គ្រងដើម្បីរក្សាទុកការផ្លាស់ប្តូរ។') }}</div>
    @endunless
    @if($errors->any())
        <div class="alert alert-danger" style="border-radius:10px; margin-bottom:18px;">
            <strong><i class="fa fa-exclamation-triangle"></i> {{ $lmText('Please check the following settings:', 'សូមពិនិត្យការកំណត់ខាងក្រោម៖') }}</strong>
            <ul id="settingsErrors">@foreach($errors->messages() as $field => $messages)<li><button type="button" class="lm-error-link" data-error-field="{{ $field }}">{{ $messages[0] }}</button></li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('loan-management.settings.business.update') }}" enctype="multipart/form-data" id="businessSettingsForm">
        @csrf

        <input type="hidden" name="active_tab" id="activeTabInput" value="{{ old('active_tab', 'tab-profile') }}">
        <!-- Hidden inputs preserving legacy compatibility -->
        <input type="hidden" name="stock_accounting_method" value="{{ $settings['stock_accounting_method'] ?? 'fifo' }}">
        <input type="hidden" name="quantity_precision" value="{{ $settings['quantity_precision'] ?? 2 }}">
        <input type="hidden" name="default_profit_percent" id="hiddenDefaultProfitPercent" value="{{ $currentInterestRate }}">

        <div class="lm-settings-shell">
            <!-- Sidebar Navigation Tabs (Unified Client-Side Switching & URLs) -->
            @include('loanmanagement::settings.partials.settings_nav', [
                'activeTab' => 'tab-profile',
                'isSinglePage' => true,
                'currentInterestRate' => $currentInterestRate,
                'savedCurrencyCode' => $savedCurrencyCode,
                'activeMethodsCount' => $activeMethodsCount,
                'settings' => $settings,
            ])

            <!-- Main Content Area -->
            <main class="lm-settings-content">
                <fieldset class="lm-settings-fields" {{ $canSaveSettings ? '' : 'disabled' }}>

                <!-- TAB 1: COMPANY PROFILE -->
                <div class="lm-tab-pane active" id="tab-profile">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Company & Organization Profile', 'ព័ត៌មានក្រុមហ៊ុន និងស្ថាប័ន') }}</h2>
                            <p>{{ $lmText('Official entity identity, tax TIN, and legal contact details used on contracts and statements.', 'ព័ត៌មានផ្លូវការរបស់ស្ថាប័ន លេខអត្តសញ្ញាណកម្មសារពើពន្ធ និងព័ត៌មានទំនាក់ទំនងសម្រាប់កិច្ចសន្យា។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="businessNameInput">
                                <span>{{ $lmText('Trading / Business Name', 'ឈ្មោះអាជីវកម្ម (ពាណិជ្ជកម្ម)') }}<span class="req">*</span></span>
                            </label>
                            <input type="text" id="businessNameInput" name="business_name" class="lm-input"
                                   value="{{ old('business_name', $settings['business_name']) }}" required maxlength="80">
                            <div class="lm-field-hint">{{ $lmText('Display name shown on header and receipts.', 'ឈ្មោះដែលបង្ហាញលើក្បាលទំព័រ និងវិក្កយបត្រ។') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="legalNameInput">
                                <span>{{ $lmText('Official Legal Entity Name', 'ឈ្មោះនីតិបុគ្គលផ្លូវការ') }}</span>
                            </label>
                            <input type="text" id="legalNameInput" name="legal_name" class="lm-input"
                                   value="{{ old('legal_name', $settings['legal_name'] ?? 'NTL CO., LTD') }}" maxlength="120" placeholder="e.g. NTL CO., LTD">
                            <div class="lm-field-hint">{{ $lmText('Used on formal legal agreements and loan contracts.', 'ប្រើប្រាស់ក្នុងកិច្ចសន្យាកម្ចី និងលិខិតផ្លូវការ។') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="taxNumberInput">
                                <span>{{ $lmText('Tax Identification Number (VAT/TIN)', 'លេខអត្តសញ្ញាណកម្មសារពើពន្ធ (TIN / VAT)') }}</span>
                            </label>
                            <input type="text" id="taxNumberInput" name="tax_number" class="lm-input"
                                   value="{{ old('tax_number', $settings['tax_number'] ?? '') }}" maxlength="50" placeholder="K000-000000000">
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="licenseNumberInput">
                                <span>{{ $lmText('NBC / MFI Operating License No.', 'លេខអាជ្ញាប័ណ្ណប្រតិបត្តិការ (NBC / MFI)') }}</span>
                            </label>
                            <input type="text" id="licenseNumberInput" name="license_number" class="lm-input"
                                   value="{{ old('license_number', $settings['license_number'] ?? '') }}" maxlength="50" placeholder="e.g. NBC-MFI-2024-001">
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="companyPhoneInput">
                                <span>{{ $lmText('Official Hotline / Phone', 'លេខទូរស័ព្ទទាក់ទងផ្លូវការ') }}</span>
                            </label>
                            <div class="lm-input-group">
                                <span class="lm-addon"><i class="fa fa-phone"></i></span>
                                <input type="text" id="companyPhoneInput" name="company_phone" class="lm-input"
                                       value="{{ old('company_phone', $settings['company_phone'] ?? '') }}" maxlength="50" placeholder="+855 23 888 999">
                            </div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="companyEmailInput">
                                <span>{{ $lmText('Official Support Email', 'អ៊ីមែលទំនាក់ទំនងផ្លូវការ') }}</span>
                            </label>
                            <div class="lm-input-group">
                                <span class="lm-addon"><i class="fa fa-envelope-o"></i></span>
                                <input type="email" id="companyEmailInput" name="company_email" class="lm-input"
                                       value="{{ old('company_email', $settings['company_email'] ?? '') }}" maxlength="100" placeholder="contact@company.com">
                            </div>
                        </div>

                        <div class="lm-field lm-col-full" data-search-target>
                            <label class="lm-field-label" for="companyAddressInput">
                                <span>{{ $lmText('Headquarters Address', 'អាសយដ្ឋានការិយាល័យកណ្តាល') }}</span>
                            </label>
                            <textarea id="companyAddressInput" name="company_address" class="lm-input" rows="2" maxlength="250" placeholder="Street, Sangkat, Khan, Phnom Penh, Cambodia">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="startDateInput">
                                <span>{{ $lmText('Operations Start Date', 'កាលបរិច្ឆេទចាប់ផ្តើមប្រតិបត្តិការ') }}</span>
                            </label>
                            <input type="date" id="startDateInput" name="start_date" class="lm-input"
                                   value="{{ old('start_date', $settings['start_date']) }}">
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="fyStartMonthInput">
                                <span>{{ $lmText('Financial Year Start Month', 'ខែចាប់ផ្តើមឆ្នាំហិរញ្ញវត្ថុ') }}<span class="req">*</span></span>
                            </label>
                            <select id="fyStartMonthInput" name="fy_start_month" class="lm-input" required>
                                @foreach($monthOptions as $monthNumber => $monthName)
                                    <option value="{{ $monthNumber }}" {{ (int) old('fy_start_month', $settings['fy_start_month']) === $monthNumber ? 'selected' : '' }}>{{ $monthName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: LOAN POLICIES -->
                <div class="lm-tab-pane" id="tab-policies">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Loan & Credit Policies', 'គោលការណ៍កម្ចី និងឥណទាន') }}</h2>
                            <p>{{ $lmText('Global rules governing interest calculations, grace periods, overdue penalties, and lending thresholds.', 'វិធានសកលកំណត់ការគណនាការប្រាក់ រយៈពេលអនុគ្រោះ ការផាកពិន័យយឺតយ៉ាវ និងដែនកំណត់ឥណទាន។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="defaultInterestRateInput">
                                <span>{{ $lmText('Default Loan Interest Rate', 'អត្រាការប្រាក់កម្ចីលំនាំដើម') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-input-group">
                                <input type="number" id="defaultInterestRateInput" name="default_interest_rate" class="lm-input lm-input-with-addon-right"
                                       value="{{ $currentInterestRate }}" required min="0" max="1000" step="0.01">
                                <span class="lm-addon lm-addon-right">%</span>
                            </div>
                            <div class="lm-field-hint">{{ $lmText('Applied as default when creating new loans and quotations.', 'ប្រើជាតម្លៃលំនាំដើមពេលបង្កើតកម្ចី ឬសម្រង់តម្លៃថ្មី។') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="interestRatePeriodInput">
                                <span>{{ $lmText('Interest Rate Period', 'កាលកំណត់អត្រាការប្រាក់') }}<span class="req">*</span></span>
                            </label>
                            <select id="interestRatePeriodInput" name="interest_rate_period" class="lm-input" required>
                                <option value="monthly" {{ old('interest_rate_period', $settings['interest_rate_period'] ?? 'monthly') === 'monthly' ? 'selected' : '' }}>
                                    {{ $lmText('Per Month (% / month)', 'ប្រចាំខែ (% ក្នុងមួយខែ)') }}
                                </option>
                                <option value="yearly" {{ old('interest_rate_period', $settings['interest_rate_period'] ?? 'monthly') === 'yearly' ? 'selected' : '' }}>
                                    {{ $lmText('Per Year (% / annum)', 'ប្រចាំឆ្នាំ (% ក្នុងមួយឆ្នាំ)') }}
                                </option>
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="defaultInterestMethodInput">
                                <span>{{ $lmText('Default Interest Method', 'វិធីសាស្ត្រគណនាការប្រាក់លំនាំដើម') }}<span class="req">*</span></span>
                            </label>
                            <select id="defaultInterestMethodInput" name="default_interest_method" class="lm-input" required>
                                <option value="flat" {{ old('default_interest_method', $settings['default_interest_method'] ?? 'flat') === 'flat' ? 'selected' : '' }}>
                                    Flat Rate (ការប្រាក់ថេរ) - Equal monthly interest
                                </option>
                                <option value="declining" {{ old('default_interest_method', $settings['default_interest_method'] ?? 'flat') === 'declining' ? 'selected' : '' }}>
                                    Declining Balance (ការប្រាក់ថយ) - Interest on outstanding principal
                                </option>
                                <option value="annuity" {{ old('default_interest_method', $settings['default_interest_method'] ?? 'flat') === 'annuity' ? 'selected' : '' }}>
                                    Equal Installment / Annuity (បង់រំលស់ស្មើ) - Amortized schedule
                                </option>
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="gracePeriodDaysInput">
                                <span>{{ $lmText('Overdue Grace Period', 'រយៈពេលអនុគ្រោះមុនគិតពិន័យ') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-input-group">
                                <input type="number" id="gracePeriodDaysInput" name="grace_period_days" class="lm-input lm-input-with-addon-right"
                                       value="{{ old('grace_period_days', $settings['grace_period_days'] ?? 3) }}" required min="0" max="365" step="1">
                                <span class="lm-addon lm-addon-right">{{ $lmText('Days', 'ថ្ងៃ') }}</span>
                            </div>
                            <div class="lm-field-hint">{{ $lmText('Days after due date before penalties begin calculating.', 'ចំនួនថ្ងៃក្រោយថ្ងៃកំណត់ មុនពេលចាប់ផ្តើមគិតប្រាក់ពិន័យ។') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="penaltyTypeInput">
                                <span>{{ $lmText('Late Penalty Calculation Type', 'ប្រភេទគណនាប្រាក់ពិន័យយឺតយ៉ាវ') }}<span class="req">*</span></span>
                            </label>
                            <select id="penaltyTypeInput" name="penalty_type" class="lm-input" required>
                                <option value="percentage" {{ old('penalty_type', $settings['penalty_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' }}>
                                    {{ $lmText('Percentage per day on overdue amount', 'ភាគរយក្នុងមួយថ្ងៃលើប្រាក់យឺត') }}
                                </option>
                                <option value="fixed" {{ old('penalty_type', $settings['penalty_type'] ?? 'percentage') === 'fixed' ? 'selected' : '' }}>
                                    {{ $lmText('Fixed flat penalty fee per installment', 'កម្រៃពិន័យថេរក្នុងមួយវគ្គ') }}
                                </option>
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="penaltyValueInput">
                                <span>{{ $lmText('Late Penalty Rate / Amount', 'កម្រិតពិន័យ (% ឬ ចំនួនទឹកប្រាក់)') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-input-group">
                                <input type="number" id="penaltyValueInput" name="penalty_value" class="lm-input lm-input-with-addon-right"
                                       value="{{ old('penalty_value', $settings['penalty_value'] ?? '0.10') }}" required min="0" max="100000" step="0.01">
                                <span class="lm-addon lm-addon-right" id="penaltyUnitLabel">% / day</span>
                            </div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="minLoanAmountInput">
                                <span>{{ $lmText('Minimum Loan Principal', 'ទំហំកម្ចីអប្បបរមា') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-input-group">
                                <span class="lm-addon">{{ $savedCurrencySymbol }}</span>
                                <input type="number" id="minLoanAmountInput" name="min_loan_amount" class="lm-input"
                                       value="{{ old('min_loan_amount', $settings['min_loan_amount'] ?? '100.00') }}" required min="0" step="0.01">
                            </div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="maxLoanAmountInput">
                                <span>{{ $lmText('Maximum Loan Principal', 'ទំហំកម្ចីអតិបរមា') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-input-group">
                                <span class="lm-addon">{{ $savedCurrencySymbol }}</span>
                                <input type="number" id="maxLoanAmountInput" name="max_loan_amount" class="lm-input"
                                       value="{{ old('max_loan_amount', $settings['max_loan_amount'] ?? '50000.00') }}" required min="0" step="0.01">
                            </div>
                        </div>

                        <div class="lm-field lm-col-full" data-search-target>
                            <label class="lm-field-label" for="transactionEditDaysInput">
                                <span>{{ $lmText('Backdated Transaction Edit Window', 'ចំនួនថ្ងៃអាចកែសម្រួលប្រតិបត្តិការថយក្រោយ') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-input-group">
                                <input type="number" id="transactionEditDaysInput" name="transaction_edit_days" class="lm-input lm-input-with-addon-right"
                                       value="{{ old('transaction_edit_days', $settings['transaction_edit_days']) }}" required min="0" max="3650" step="1">
                                <span class="lm-addon lm-addon-right">{{ $lmText('Days', 'ថ្ងៃ') }}</span>
                            </div>
                            <div class="lm-field-hint">{{ $lmText('Protects closed ledger periods. Payments older than this cannot be altered by staff.', 'ការពារការកែប្រែទិន្នន័យចាស់ៗហួសកាលកំណត់ ដើម្បីតម្លាភាពគណនេយ្យ។') }}</div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: BRANDING & APPEARANCE -->
                <div class="lm-tab-pane" id="tab-branding">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Branding, Colors & White-Labeling', 'រូបរាង ពណ៌រចនាប័ទ្ម និងស្លាកសញ្ញា') }}</h2>
                            <p>{{ $lmText('Customize system headers, logo assets, primary theme colors, and the employee sign-in wallpaper.', 'កំណត់ឈ្មោះប្រព័ន្ធ ស្លាកសញ្ញា ពណ៌ចម្បង និងផ្ទាំងរូបភាពផ្ទៃខាងក្រោយចូលប្រើ។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="systemNameInput">
                                <span>{{ $lmText('System App Name', 'ឈ្មោះប្រព័ន្ធ') }}<span class="req">*</span></span>
                            </label>
                            <input type="text" id="systemNameInput" name="system_name" class="lm-input"
                                   value="{{ old('system_name', $settings['system_name']) }}" required maxlength="80">
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="systemSubtitleInput">
                                <span>{{ $lmText('System Subtitle / Tagline', 'អត្ថបទរងប្រព័ន្ធ') }}</span>
                            </label>
                            <input type="text" id="systemSubtitleInput" name="system_subtitle" class="lm-input"
                                   value="{{ old('system_subtitle', $settings['system_subtitle']) }}" maxlength="120">
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label">
                                <span>{{ $lmText('Primary Theme Accent Color', 'ពណ៌រចនាប័ទ្មចម្បង') }}<span class="req">*</span></span>
                            </label>
                            <div class="lm-color-picker-box">
                                <input type="color" id="themeColorPicker" value="{{ old('theme_color', $settings['theme_color']) }}">
                                <input type="text" id="themeColorInput" name="theme_color" class="lm-input" style="max-width:130px;"
                                       value="{{ old('theme_color', $settings['theme_color']) }}" required maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                            <div class="lm-swatches-row">
                                @foreach($themePresets as $preset)
                                    <button type="button" class="lm-swatch-circle" data-color="{{ $preset }}" style="background: {{ $preset }};" title="{{ $preset }}"></button>
                                @endforeach
                            </div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="logoInput">
                                <span>{{ $lmText('Company Logo Asset', 'រូបសញ្ញាក្រុមហ៊ុន') }}</span>
                            </label>
                            <div class="lm-upload-box">
                                <div class="lm-preview-thumb" id="logoPreviewBox">
                                    @if($businessLogoUrl)
                                        <img src="{{ $businessLogoUrl }}" alt="{{ $settings['business_name'] }}" id="logoPreviewImage">
                                    @else
                                        <i class="fa fa-building-o"></i>
                                    @endif
                                </div>
                                <div class="lm-upload-actions">
                                    <label class="lm-file-btn">
                                        <i class="fa fa-upload"></i> {{ $lmText('Select Image...', 'ជ្រើសរើសរូបភាព...') }}
                                        <input type="file" id="logoInput" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
                                    </label>
                                    <div class="lm-field-hint">{{ $lmText('PNG, JPG, or WEBP (Max 2 MB). Recommended transparent PNG.', 'ទម្រង់ PNG, JPG ឬ WEBP (អតិបរមា 2 MB)។') }}</div>
                                    @if($businessLogoUrl)
                                        <label style="font-size:12px; font-weight:700; color:#ef4444; margin-top:4px; cursor:pointer;">
                                            <input type="checkbox" name="remove_logo" value="1"> {{ $lmText('Remove current logo', 'លុបរូបសញ្ញាចេញ') }}
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="lm-field lm-col-full" data-search-target>
                            <label class="lm-field-label" for="loginBackgroundInput">
                                <span>{{ $lmText('Sign-in Screen Wallpaper Photo', 'រូបភាពផ្ទៃខាងក្រោយទំព័រចូលប្រើប្រាស់') }}</span>
                            </label>
                            <div style="min-height:160px; border-radius:10px; border:1px solid #cbd5e1; background:linear-gradient(135deg, rgba(15,23,42,0.7), rgba(99,102,241,0.5)), var(--lm-login-background, #1e293b); background-size:cover; background-position:center; padding:20px; color:#fff; display:flex; flex-direction:column; justify-content:flex-end;" id="loginBackgroundPreview">
                                <strong id="backgroundPreviewBusinessName" style="font-size:20px;">{{ old('business_name', $settings['business_name']) }}</strong>
                                <span style="font-size:12px; opacity:0.85;">{{ old('system_subtitle', $settings['system_subtitle']) }}</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:14px; margin-top:10px;">
                                <label class="lm-file-btn">
                                    <i class="fa fa-picture-o"></i> {{ $lmText('Upload Wallpaper Image...', 'បង្ហោះរូបភាពផ្ទៃខាងក្រោយ...') }}
                                    <input type="file" id="loginBackgroundInput" name="login_background" accept="image/png,image/jpeg,image/webp">
                                </label>
                                @if($loginBackgroundUrl)
                                    <label style="font-size:12px; font-weight:700; color:#ef4444; cursor:pointer; margin:0;">
                                        <input type="checkbox" name="remove_login_background" value="1"> {{ $lmText('Remove wallpaper', 'លុបផ្ទៃខាងក្រោយ') }}
                                    </label>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Live Brand Header Preview -->
                    <div class="lm-preview-card">
                        <div class="lm-preview-card-title">{{ $lmText('Live Header Preview', 'គំរូបង្ហាញក្បាលទំព័រផ្ទាល់') }}</div>
                        <div style="display:flex; align-items:center; gap:16px; padding:12px 16px; background:#fff; border-radius:8px; border:1px solid #e2e8f0;">
                            <div style="width:44px; height:44px; border-radius:8px; background:var(--lm-primary, #6366f1); display:flex; align-items:center; justify-content:center; color:#fff; overflow:hidden;" id="sidebarLogoPreview">
                                @if($businessLogoUrl)
                                    <img src="{{ $businessLogoUrl }}" alt="" style="width:100%; height:100%; object-fit:contain;">
                                @else
                                    <i class="fa fa-handshake-o"></i>
                                @endif
                            </div>
                            <div>
                                <div style="font-weight:800; font-size:15px; color:#0f172a;" id="previewBusinessName">{{ old('business_name', $settings['business_name']) }}</div>
                                <div style="font-size:12px; color:#64748b;" id="previewSystemName">{{ old('system_name', $settings['system_name']) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: CURRENCY & NUMBERING -->
                <div class="lm-tab-pane" id="tab-currency">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Currency, Dates & Code Sequences', 'រូបិយប័ណ្ណ កាលបរិច្ឆេទ និងលេខកូដសម្គាល់') }}</h2>
                            <p>{{ $lmText('Standardized formats for money display, time zones, and automated account numbering prefixes.', 'ទម្រង់ស្តង់ដារសម្រាប់រូបិយប័ណ្ណ តំបន់ម៉ោង និងបុព្វបទលេខកូដស្វ័យប្រវត្តិនានា។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="currencyCodeInput">
                                <span>{{ $lmText('Primary Operating Currency', 'រូបិយប័ណ្ណប្រតិបត្តិការចម្បង') }}<span class="req">*</span></span>
                            </label>
                            <select id="currencyCodeInput" name="currency_code" class="lm-input" required>
                                @foreach($currencies as $currency)
                                    @php
                                        $currencyCode = strtoupper((string) ($currency->code ?? ''));
                                        $currencyName = (string) ($currency->name ?? $currencyCode);
                                    @endphp
                                    @if($currencyCode !== '')
                                        <option value="{{ $currencyCode }}" data-symbol="{{ $currencySymbolMap[$currencyCode] ?? $currencyCode }}" {{ $savedCurrencyCode === $currencyCode ? 'selected' : '' }}>
                                            {{ $currencyName }} ({{ $currencyCode }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="currencySymbolInput">
                                <span>{{ $lmText('Currency Symbol', 'និមិត្តសញ្ញារូបិយប័ណ្ណ') }}</span>
                            </label>
                            <input type="text" id="currencySymbolInput" name="currency_symbol" class="lm-input"
                                   value="{{ $savedCurrencySymbol }}" maxlength="10">
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="currencySymbolPlacementInput">
                                <span>{{ $lmText('Symbol Placement', 'ទីតាំងនិមិត្តសញ្ញា') }}<span class="req">*</span></span>
                            </label>
                            <select id="currencySymbolPlacementInput" name="currency_symbol_placement" class="lm-input" required>
                                <option value="before" {{ old('currency_symbol_placement', $settings['currency_symbol_placement']) === 'before' ? 'selected' : '' }}>
                                    {{ $lmText('Before amount (e.g. $ 1,000)', 'មុនចំនួនទឹកប្រាក់ (ឧ. $ 1,000)') }}
                                </option>
                                <option value="after" {{ old('currency_symbol_placement', $settings['currency_symbol_placement']) === 'after' ? 'selected' : '' }}>
                                    {{ $lmText('After amount (e.g. 1,000 ៛)', 'ក្រោយចំនួនទឹកប្រាក់ (ឧ. 1,000 ៛)') }}
                                </option>
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="currencyPrecisionInput">
                                <span>{{ $lmText('Currency Decimals Precision', 'ចំនួនខ្ទង់ទសភាគរូបិយប័ណ្ណ') }}<span class="req">*</span></span>
                            </label>
                            <select id="currencyPrecisionInput" name="currency_precision" class="lm-input" required>
                                <option value="0" {{ (int) old('currency_precision', $settings['currency_precision']) === 0 ? 'selected' : '' }}>0 decimals (e.g. 1,000 - for KHR Riel)</option>
                                <option value="2" {{ (int) old('currency_precision', $settings['currency_precision']) === 2 ? 'selected' : '' }}>2 decimals (e.g. 1,000.00 - for USD)</option>
                                <option value="3" {{ (int) old('currency_precision', $settings['currency_precision']) === 3 ? 'selected' : '' }}>3 decimals</option>
                                <option value="4" {{ (int) old('currency_precision', $settings['currency_precision']) === 4 ? 'selected' : '' }}>4 decimals</option>
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="timeZoneInput">
                                <span>{{ $lmText('System Time Zone', 'តំបន់ពេលវេលា') }}<span class="req">*</span></span>
                            </label>
                            <select id="timeZoneInput" name="time_zone" class="lm-input" required>
                                @foreach($timezones as $timezone)
                                    <option value="{{ $timezone }}" {{ old('time_zone', $settings['time_zone']) === $timezone ? 'selected' : '' }}>{{ $timezone }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="dateFormatInput">
                                <span>{{ $lmText('System Date Format', 'ទម្រង់កាលបរិច្ឆេទ') }}<span class="req">*</span></span>
                            </label>
                            <select id="dateFormatInput" name="date_format" class="lm-input" required>
                                @foreach($dateFormatOptions as $formatValue => $formatLabel)
                                    <option value="{{ $formatValue }}" {{ old('date_format', $settings['date_format']) === $formatValue ? 'selected' : '' }}>{{ $formatLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="timeFormatInput">
                                <span>{{ $lmText('System Time Format', 'ទម្រង់ម៉ោង') }}<span class="req">*</span></span>
                            </label>
                            <select id="timeFormatInput" name="time_format" class="lm-input" required>
                                <option value="24" {{ (string) old('time_format', $settings['time_format']) === '24' ? 'selected' : '' }}>24 Hours (e.g. 17:30)</option>
                                <option value="12" {{ (string) old('time_format', $settings['time_format']) === '12' ? 'selected' : '' }}>12 Hours AM/PM (e.g. 05:30 PM)</option>
                            </select>
                        </div>

                        <!-- Numbering Prefixes Header -->
                        <div class="lm-col-full" style="padding-top:14px; margin-top:10px; border-top:1px solid #f1f5f9;">
                            <h3 style="margin:0 0 4px; font-size:16px; font-weight:800; color:#0f172a;">{{ $lmText('Document Auto-Numbering Prefixes', 'បុព្វបទលេខកូដឯកសារស្វ័យប្រវត្តិ') }}</h3>
                            <p style="margin:0 0 14px; font-size:12px; color:#64748b;">{{ $lmText('Defines prefix strings prepended when creating new loan contracts, customer accounts, and receipts.', 'កំណត់ពាក្យខាងដើមសម្រាប់កិច្ចសន្យាកម្ចី គណនីអតិថិជន និងបង្កាន់ដៃបង់ប្រាក់។') }}</p>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="loanPrefixInput">
                                <span>{{ $lmText('Loan Account Prefix', 'កូដកម្ចី') }}</span>
                            </label>
                            <input type="text" id="loanPrefixInput" name="loan_prefix" class="lm-input"
                                   value="{{ old('loan_prefix', $settings['loan_prefix'] ?? 'LN-') }}" maxlength="20" placeholder="e.g. LN-">
                            <div class="lm-field-hint">{{ $lmText('Generated: LN-0001, LN-0002...', 'លទ្ធផល៖ LN-0001, LN-0002...') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="customerPrefixInput">
                                <span>{{ $lmText('Customer ID Prefix', 'កូដអតិថិជន') }}</span>
                            </label>
                            <input type="text" id="customerPrefixInput" name="customer_prefix" class="lm-input"
                                   value="{{ old('customer_prefix', $settings['customer_prefix'] ?? 'CUST-') }}" maxlength="20" placeholder="e.g. CUST-">
                            <div class="lm-field-hint">{{ $lmText('Generated: CUST-0001...', 'លទ្ធផល៖ CUST-0001...') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="receiptPrefixInput">
                                <span>{{ $lmText('Payment Receipt Prefix', 'កូដបង្កាន់ដៃបង់ប្រាក់') }}</span>
                            </label>
                            <input type="text" id="receiptPrefixInput" name="receipt_prefix" class="lm-input"
                                   value="{{ old('receipt_prefix', $settings['receipt_prefix'] ?? 'REC-') }}" maxlength="20" placeholder="e.g. REC-">
                            <div class="lm-field-hint">{{ $lmText('Generated: REC-0001...', 'លទ្ធផល៖ REC-0001...') }}</div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="quotationPrefixInput">
                                <span>{{ $lmText('Quotation / Proposal Prefix', 'កូដសម្រង់តម្លៃ') }}</span>
                            </label>
                            <input type="text" id="quotationPrefixInput" name="quotation_prefix" class="lm-input"
                                   value="{{ old('quotation_prefix', $settings['quotation_prefix'] ?? 'QUO-') }}" maxlength="20" placeholder="e.g. QUO-">
                        </div>
                    </div>
                </div>

                <!-- TAB 5: PAYMENT METHODS (EMBEDDED NATIVELY) -->
                <div class="lm-tab-pane" id="tab-payment">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Custom Collection Payment Channels', 'ច្រកប្រមូលប្រាក់ និងវិធីទូទាត់') }}</h2>
                            <p>{{ $lmText('Configure display names, channel identifiers, and enable or disable methods for cashiers and field agents.', 'កំណត់ឈ្មោះបង្ហាញ កូដសម្គាល់ និងបើក/បិទវិធីបង់ប្រាក់សម្រាប់បេឡា និងភ្នាក់ងារ។') }}</p>
                        </div>
                    </div>

                    <!-- Payment Methods Grid -->
                    <div class="lm-methods-grid" id="embeddedPaymentMethodsGrid">
                        @forelse($paymentMethods as $index => $method)
                            @php
                                $usageRow = $methodUsage->get($method->name, ['payments_count' => 0, 'total_amount' => 0]);
                                $number = $index + 1;
                                $isActive = (bool) old('methods.'.$method->id.'.is_active', $method->is_active);
                                $code = $method->code ?? '';
                            @endphp
                            <div class="lm-method-card {{ $isActive ? '' : 'inactive' }}" data-search-target>
                                <div class="lm-method-topline">
                                    <div class="lm-method-header-title">
                                        <div class="lm-method-icon-circle">
                                            @if(stripos($method->name, 'aba') !== false)
                                                <i class="fa fa-bank"></i>
                                            @elseif(stripos($method->name, 'cash') !== false)
                                                <i class="fa fa-money"></i>
                                            @elseif(stripos($method->name, 'card') !== false)
                                                <i class="fa fa-credit-card"></i>
                                            @else
                                                <i class="fa fa-exchange"></i>
                                            @endif
                                        </div>
                                        <span>#{{ $number }} {{ $method->name }}</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-size:11.5px; font-weight:700; color:{{ $isActive ? '#059669' : '#94a3b8' }};">
                                            {{ $isActive ? $lmText('Active', 'ដំណើរការ') : $lmText('Inactive', 'បិទ') }}
                                        </span>
                                        <label class="lm-switch" title="Toggle active status">
                                            <input type="hidden" name="methods[{{ $method->id }}][is_active]" value="0">
                                            <input type="checkbox" name="methods[{{ $method->id }}][is_active]" value="1" {{ $isActive ? 'checked' : '' }} onchange="this.closest('.lm-method-card').classList.toggle('inactive', !this.checked)">
                                            <span class="lm-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div style="margin-top:10px;">
                                    <label class="lm-field-label">{{ $lmText('Display Name', 'ឈ្មោះបង្ហាញលើប្រព័ន្ធ') }}</label>
                                    <input type="text" name="methods[{{ $method->id }}][name]" class="lm-input" value="{{ old('methods.'.$method->id.'.name', $method->name) }}" maxlength="191" {{ ($usageRow['payments_count'] ?? 0) > 0 ? 'readonly' : '' }} placeholder="e.g. ABA Bank / Cash">
                                </div>

                                <div class="lm-method-row-2">
                                    <div>
                                        <label class="lm-field-label">{{ $lmText('Channel Code', 'កូដសម្គាល់') }}</label>
                                        <input type="text" name="methods[{{ $method->id }}][code]" class="lm-input" value="{{ old('methods.'.$method->id.'.code', $code) }}" maxlength="60" placeholder="e.g. aba, cash">
                                    </div>
                                    <div>
                                        <label class="lm-field-label">{{ $lmText('Sort Order', 'លំដាប់') }}</label>
                                        <input type="number" name="methods[{{ $method->id }}][sort_order]" class="lm-input" value="{{ old('methods.'.$method->id.'.sort_order', $method->sort_order ?? 0) }}" min="0" max="999">
                                    </div>
                                </div>

                                <div class="lm-method-stats">
                                    <span>
                                        <i class="fa fa-history" style="margin-right:4px;"></i>
                                        <strong>{{ number_format($usageRow['payments_count'] ?? 0) }}</strong> {{ $lmText('payments', 'ប្រតិបត្តិការ') }}
                                    </span>
                                    <span>
                                        <strong>{{ $savedCurrencySymbol }}{{ number_format((float) ($usageRow['total_amount'] ?? 0), (int) $settings['currency_precision']) }}</strong> {{ $lmText('collected', 'ប្រមូលបាន') }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="lm-col-full text-center" style="padding:40px 20px; color:#64748b;">
                                <i class="fa fa-credit-card" style="font-size:36px; opacity:0.3; margin-bottom:10px;"></i>
                                <p>{{ $lmText('No payment channels configured yet.', 'មិនទាន់មានវិធីបង់ប្រាក់នៅឡើយ។') }}</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Add New Payment Method Card -->
                    <div class="lm-add-method-card" data-search-target>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                            <div style="width:32px; height:32px; border-radius:8px; background:var(--lm-primary-50, #eef2ff); color:var(--lm-primary, #6366f1); display:flex; align-items:center; justify-content:center; font-size:14px;">
                                <i class="fa fa-plus"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:15px; font-weight:800; color:#0f172a;">{{ $lmText('Add New Payment Channel', 'បន្ថែមវិធីបង់ប្រាក់ថ្មី') }}</h3>
                                <p style="margin:2px 0 0; font-size:12px; color:#64748b;">{{ $lmText('Create an additional payment option for field agents or loan cashiers.', 'បង្កើតជម្រើសបង់ប្រាក់បន្ថែមសម្រាប់បុគ្គលិក ឬអតិថិជន។') }}</p>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:minmax(0, 1.5fr) minmax(0, 1fr) 100px; gap:14px;">
                            <div>
                                <label class="lm-field-label">{{ $lmText('Method Name', 'ឈ្មោះវិធីបង់ប្រាក់') }}</label>
                                <input type="text" name="new_method[name]" value="{{ old('new_method.name') }}" maxlength="191" class="lm-input" placeholder="e.g. ACLEDA Unity / Wing / Bakong">
                            </div>
                            <div>
                                <label class="lm-field-label">{{ $lmText('System Code', 'កូដប្រព័ន្ធ') }}</label>
                                <input type="text" name="new_method[code]" value="{{ old('new_method.code') }}" maxlength="60" class="lm-input" placeholder="e.g. acleda_bank">
                            </div>
                            <div>
                                <label class="lm-field-label">{{ $lmText('Sort Order', 'លំដាប់') }}</label>
                                <input type="number" name="new_method[sort_order]" class="lm-input" value="{{ old('new_method.sort_order', $paymentMethods->count() + 1) }}" min="0" max="999">
                            </div>
                        </div>
                    </div>

                    @if(isset($legacyRows) && $legacyRows->isNotEmpty())
                        <details style="margin-top:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px;">
                            <summary style="cursor:pointer; font-weight:700; color:#475569; font-size:13px;">
                                <i class="fa fa-database" style="margin-right:6px;"></i> {{ $lmText('Legacy Payment Method Rows (Audit Archive)', 'ទិន្នន័យប្រវត្តិវិធីបង់ប្រាក់ចាស់ (Audit Archive)') }}
                            </summary>
                            <div style="margin-top:12px; overflow-x:auto;">
                                <table class="table table-bordered table-striped" style="margin:0; font-size:12.5px; background:#fff;">
                                    <thead>
                                        <tr><th style="width:80px;">ID</th><th>Name</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($legacyRows as $row)
                                            <tr>
                                                <td>{{ $row->id ?? '-' }}</td>
                                                <td>{{ $row->name ?? '-' }}</td>
                                                <td><span class="label label-{{ !empty($row->is_active) ? 'success' : 'default' }}">{{ !empty($row->is_active) ? 'Active' : 'Inactive' }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                </div>

                <!-- TAB 6: CONTRACTS & PRINTING -->
                <div class="lm-tab-pane" id="tab-templates">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Contracts, Receipts & Digital Seal', 'កិច្ចសន្យា បង្កាន់ដៃ និងត្រាក្រុមហ៊ុន') }}</h2>
                            <p>{{ $lmText('Official digital stamp, receipt layout, message templates, and standard contract clauses.', 'ត្រាឌីជីថលផ្លូវការ ទម្រង់បោះពុម្ពបង្កាន់ដៃ គំរូសារ និងលក្ខខណ្ឌកិច្ចសន្យាស្តង់ដារ។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="stampInput">
                                <span>{{ $lmText('Official Company Stamp / Seal (PNG)', 'ត្រាផ្លូវការរបស់ក្រុមហ៊ុន (PNG)') }}</span>
                            </label>
                            <div class="lm-upload-box">
                                <div class="lm-preview-thumb" id="stampPreviewBox" style="background:#fff;">
                                    @if($stampUrl)
                                        <img src="{{ $stampUrl }}" alt="Stamp" id="stampPreviewImage">
                                    @else
                                        <i class="fa fa-shield"></i>
                                    @endif
                                </div>
                                <div class="lm-upload-actions">
                                    <label class="lm-file-btn">
                                        <i class="fa fa-upload"></i> {{ $lmText('Upload Seal Image...', 'ជ្រើសរើសរូបត្រា...') }}
                                        <input type="file" id="stampInput" name="stamp" accept="image/png,image/webp">
                                    </label>
                                    <div class="lm-field-hint">{{ $lmText('Transparent PNG recommended for clean contract stamp placement.', 'ណែនាំប្រើរូបភាព PNG គ្មានផ្ទៃខាងក្រោយ ដើម្បីបោះត្រាលើកិច្ចសន្យាបានច្បាស់។') }}</div>
                                    @if($stampUrl)
                                        <label style="font-size:12px; font-weight:700; color:#ef4444; margin-top:4px; cursor:pointer;">
                                            <input type="checkbox" name="remove_stamp" value="1"> {{ $lmText('Remove digital stamp', 'លុបរូបត្រាចេញ') }}
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="receiptPrinterTypeInput">
                                <span>{{ $lmText('Receipt Printer Format', 'ទម្រង់បោះពុម្ពបង្កាន់ដៃ') }}<span class="req">*</span></span>
                            </label>
                            <select id="receiptPrinterTypeInput" name="receipt_printer_type" class="lm-input" required>
                                <option value="thermal_80mm" {{ old('receipt_printer_type', $settings['receipt_printer_type'] ?? 'thermal_80mm') === 'thermal_80mm' ? 'selected' : '' }}>
                                    {{ $lmText('80mm POS Thermal Receipt (Field Agents & Mobile)', 'បង្កាន់ដៃកម្តៅ 80mm (ភ្នាក់ងារ និងម៉ាស៊ីនចល័ត)') }}
                                </option>
                                <option value="a4" {{ old('receipt_printer_type', $settings['receipt_printer_type'] ?? 'thermal_80mm') === 'a4' ? 'selected' : '' }}>
                                    {{ $lmText('A4 Full Page Voucher (Desk Cashier & Office)', 'ទម្រង់ក្រដាស A4 (បេឡាការិយាល័យ)') }}
                                </option>
                                <option value="a5" {{ old('receipt_printer_type', $settings['receipt_printer_type'] ?? 'thermal_80mm') === 'a5' ? 'selected' : '' }}>
                                    {{ $lmText('A5 Half Page Slip', 'ទម្រង់ក្រដាស A5') }}
                                </option>
                            </select>
                            <div class="lm-field-hint">{{ $lmText('Controls default print layout when generating collection receipts.', 'កំណត់ទំហំបោះពុម្ពពេលចេញវិក្កយបត្រប្រមូលប្រាក់។') }}</div>
                        </div>

                        <div class="lm-field lm-col-full" data-search-target>
                            <label class="lm-field-label" for="invoiceMessageTemplateInput">
                                <span>{{ $lmText('Customer Thank-You Receipt Message Template', 'គំរូសារអរគុណលើបង្កាន់ដៃអតិថិជន') }}<span class="req">*</span></span>
                            </label>
                            <textarea id="invoiceMessageTemplateInput" name="invoice_message_template" class="lm-input" rows="3" required maxlength="2000">{{ old('invoice_message_template', $settings['invoice_message_template']) }}</textarea>
                            <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                <span class="lm-field-hint">{{ $lmText('Insert Dynamic Placeholders:', 'ចុចបញ្ចូលទិន្នន័យស្វ័យប្រវត្តិ៖') }}</span>
                                <button type="button" class="btn btn-xs btn-default insert-tag" data-tag="{Customer Name}">{Customer Name}</button>
                                <button type="button" class="btn btn-xs btn-default insert-tag" data-tag="{Business Name}">{Business Name}</button>
                            </div>

                            <!-- Live Real-Time Message Preview -->
                            <div class="lm-preview-card" style="margin-top:10px;">
                                <div class="lm-preview-card-title">{{ $lmText('Receipt Message Preview', 'គំរូបង្ហាញសារជាក់ស្តែង') }}</div>
                                <div id="invoiceMessageTemplatePreview" style="white-space:pre-wrap; font-size:13px; line-height:1.6; color:#1e293b;"></div>
                            </div>
                        </div>

                        <div class="lm-field lm-col-full" data-search-target>
                            <label class="lm-field-label" for="contractTermsInput">
                                <span>{{ $lmText('Default Loan Contract Clauses / Terms & Conditions', 'លក្ខខណ្ឌ និងកាតព្វកិច្ចក្នុងកិច្ចសន្យាកម្ចី') }}</span>
                            </label>
                            <textarea id="contractTermsInput" name="contract_terms" class="lm-input" rows="5" maxlength="5000" placeholder="{{ $lmText('Enter terms that will automatically print on loan agreements...', 'បញ្ចូលប្រការកិច្ចសន្យាដែលត្រូវបោះពុម្ពលើលិខិតកម្ចី...') }}">{{ old('contract_terms', $settings['contract_terms'] ?? '') }}</textarea>
                            <div class="lm-field-hint">{{ $lmText('Appears on formal printed loan agreements and schedule contracts.', 'បង្ហាញនៅលើកិច្ចសន្យាកម្ចីផ្លូវការពេលបោះពុម្ព។') }}</div>
                        </div>
                    </div>
                </div>

                <!-- TAB 7: TELEGRAM ALERTS -->
                <div class="lm-tab-pane" id="tab-notifications">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Telegram Bot Alerts & Event Gateways', 'ការជូនដំណឹងតាម Telegram Bot និងប្រព័ន្ធស្វ័យប្រវត្តិ') }}</h2>
                            <p>{{ $lmText('Receive real-time instant alerts for loan applications, collection payments, and morning overdue summaries.', 'ទទួលការជូនដំណឹងភ្លាមៗតាម Telegram ពេលមានសំណើកម្ចីថ្មី ការបង់ប្រាក់ និងរបាយការណ៍ហួសកាលកំណត់ប្រចាំថ្ងៃ។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="telegramBotTokenInput">
                                <span>{{ $lmText('Telegram Bot Token', 'Telegram Bot Token') }}</span>
                            </label>
                            <div class="lm-input-group">
                                <span class="lm-addon"><i class="fa fa-key"></i></span>
                                <input type="password" autocomplete="new-password" spellcheck="false" id="telegramBotTokenInput" name="telegram_bot_token" class="lm-input"
                                       value="{{ $canSaveSettings ? old('telegram_bot_token', $settings['telegram_bot_token'] ?? '') : '' }}" maxlength="120" placeholder="123456789:ABCdefGHIjk-lmnOPQrstUVwxyZ">
                            </div>
                            <div class="lm-field-hint">{{ $lmText('Created via @BotFather on Telegram.', 'បង្កើតឡើងតាមរយៈ @BotFather ក្នុង Telegram។') }}</div>
                            @if($canSaveSettings)
                            <div class="lm-settings-inline-actions">
                                <button type="button" class="btn btn-default btn-sm" id="toggleTelegramToken" aria-controls="telegramBotTokenInput" aria-pressed="false"><i class="fa fa-eye"></i> {{ $lmText('Show token', 'បង្ហាញ Token') }}</button>
                                <button type="button" class="btn btn-default btn-sm" id="testTelegramBot" data-url="{{ route('loan-management.settings.telegram.test') }}"><i class="fa fa-plug"></i> {{ $lmText('Check connection', 'ពិនិត្យការតភ្ជាប់') }}</button>
                            </div>
                            <div id="telegramTestResult" class="lm-field-hint" role="status" aria-live="polite">{{ $lmText('Checks bot credentials without sending a message. Save to keep changes.', 'ពិនិត្យ Bot ដោយមិនផ្ញើសារ។ រក្សាទុកដើម្បីរក្សាការផ្លាស់ប្តូរ។') }}</div>
                            @endif
                        </div>

                        <div class="lm-field" data-search-target>
                            <label class="lm-field-label" for="telegramChatIdInput">
                                <span>{{ $lmText('Target Chat ID / Channel ID', 'Chat ID ឬ Channel ID ទទួលដំណឹង') }}</span>
                            </label>
                            <div class="lm-input-group">
                                <span class="lm-addon"><i class="fa fa-comment-o"></i></span>
                                <input type="text" id="telegramChatIdInput" name="telegram_chat_id" class="lm-input"
                                       value="{{ old('telegram_chat_id', $settings['telegram_chat_id'] ?? '') }}" maxlength="120" placeholder="e.g. -100123456789 or @channelname">
                            </div>
                            <div class="lm-field-hint">{{ $lmText('Group Chat ID or Channel ID where alerts should be sent.', 'លេខសម្គាល់ក្រុម ឬ Channel ដែលត្រូវទទួលការជូនដំណឹង។') }}</div>
                        </div>

                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Alert on New Customer Loan Application', 'ជូនដំណឹងពេលមានសំណើកម្ចីថ្មីពីអតិថិជន') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('Sends instant notification when a customer submits an installment request online.', 'ផ្ញើសារភ្លាមៗនៅពេលមានអតិថិជនដាក់ពាក្យស្នើសុំកម្ចី ឬរំលស់តាមអនឡាញ។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="notify_new_loan" value="0">
                                    <input type="checkbox" name="notify_new_loan" value="1" {{ old('notify_new_loan', $settings['notify_new_loan'] ?? true) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Alert on Payment Receipt Confirmed', 'ជូនដំណឹងពេលកត់ត្រាការបង់ប្រាក់បានជោគជ័យ') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('Notifies management chat when cashiers or field agents collect and confirm a payment.', 'ផ្ញើសារជូនដំណឹងពេលបេឡា ឬភ្នាក់ងារប្រមូលប្រាក់កត់ត្រាការទូទាត់។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="notify_payment_received" value="0">
                                    <input type="checkbox" name="notify_payment_received" value="1" {{ old('notify_payment_received', $settings['notify_payment_received'] ?? true) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Daily Morning Overdue & Collection Summary', 'របាយការណ៍សង្ខេបប្រចាំព្រឹក (កម្ចីត្រូវប្រមូល & ហួសកំណត់)') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('Broadcasts a morning briefing of loans due today and delinquent overdue accounts.', 'ផ្ញើរបាយការណ៍សង្ខេបពេលព្រឹកអំពីចំនួនកម្ចីត្រូវប្រមូលថ្ងៃនេះ និងគណនីយឺតយ៉ាវ។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="notify_overdue_daily" value="0">
                                    <input type="checkbox" name="notify_overdue_daily" value="1" {{ old('notify_overdue_daily', $settings['notify_overdue_daily'] ?? true) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: SOCIAL MEDIA & FOLLOW US LINKS -->
                <div class="lm-tab-pane" id="tab-social">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Social Media Channels & Follow Us', 'បណ្ដាញសង្គម និងតំណភ្ជាប់ Follow Us') }}</h2>
                            <p>{{ $lmText('Connect your official social media pages displayed in the website footer, customer portal, and contact sections.', 'កំណត់តំណភ្ជាប់បណ្ដាញសង្គមផ្លូវការរបស់អ្នកដែលត្រូវបង្ហាញនៅខាងក្រោមគេហទំព័រ (Footer) និងផតថលអតិថិជន។') }}</p>
                        </div>
                        <a href="{{ route('loan-management.public.home') }}" target="_blank" class="btn btn-sm btn-default" style="font-weight:700;">
                            <i class="fa fa-external-link"></i> {{ $lmText('Preview Public Website', 'មើលគេហទំព័រជាក់ស្តែង') }}
                        </a>
                    </div>

                    @php
                        $cms = $settings['home_cms'] ?? [];
                        $socialShow = (bool) old('home_cms.footer_show_social', $cms['footer_show_social'] ?? true);
                        $socialTitle = old('home_cms.footer_social_title', $cms['footer_social_title'] ?? 'Follow Us');

                        $socialChannels = [
                            [
                                'key' => 'footer_facebook',
                                'label' => 'Facebook',
                                'km_label' => 'ទំព័រ Facebook',
                                'desc' => 'Official Facebook page or profile',
                                'icon' => 'fa fa-facebook',
                                'bg' => '#1877F2',
                                'placeholder' => 'https://facebook.com/your-page-name',
                                'example' => 'https://facebook.com/...',
                            ],
                            [
                                'key' => 'footer_telegram',
                                'label' => 'Telegram',
                                'km_label' => 'ឆានែល Telegram',
                                'desc' => 'Public channel, group, or support bot',
                                'icon' => 'fa fa-paper-plane',
                                'bg' => '#229ED9',
                                'placeholder' => 'https://t.me/your-channel-or-username',
                                'example' => 'https://t.me/...',
                            ],
                            [
                                'key' => 'footer_tiktok',
                                'label' => 'TikTok',
                                'km_label' => 'គណនី TikTok',
                                'desc' => 'Official video showcase account',
                                'icon' => 'fa fa-music',
                                'bg' => '#000000',
                                'placeholder' => 'https://tiktok.com/@your-username',
                                'example' => 'https://tiktok.com/@...',
                            ],
                            [
                                'key' => 'footer_instagram',
                                'label' => 'Instagram',
                                'km_label' => 'គណនី Instagram',
                                'desc' => 'Showroom photos and stories',
                                'icon' => 'fa fa-instagram',
                                'bg' => 'linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)',
                                'placeholder' => 'https://instagram.com/your-handle',
                                'example' => 'https://instagram.com/...',
                            ],
                            [
                                'key' => 'footer_youtube',
                                'label' => 'YouTube',
                                'km_label' => 'ឆានែល YouTube',
                                'desc' => 'Tutorials, promotions, and reviews',
                                'icon' => 'fa fa-youtube-play',
                                'bg' => '#FF0000',
                                'placeholder' => 'https://youtube.com/@your-channel',
                                'example' => 'https://youtube.com/@...',
                            ],
                            [
                                'key' => 'footer_whatsapp',
                                'label' => 'WhatsApp',
                                'km_label' => 'គណនី WhatsApp',
                                'desc' => 'WhatsApp direct chat link or business number',
                                'icon' => 'fa fa-whatsapp',
                                'bg' => '#25D366',
                                'placeholder' => 'https://wa.me/85512345678',
                                'example' => 'https://wa.me/...',
                            ],
                            [
                                'key' => 'footer_linkedin',
                                'label' => 'LinkedIn',
                                'km_label' => 'ទំព័រ LinkedIn',
                                'desc' => 'Corporate and career profile',
                                'icon' => 'fa fa-linkedin',
                                'bg' => '#0A66C2',
                                'placeholder' => 'https://linkedin.com/company/your-company',
                                'example' => 'https://linkedin.com/...',
                            ],
                            [
                                'key' => 'footer_twitter',
                                'label' => 'X (Twitter)',
                                'km_label' => 'គណនី X (Twitter)',
                                'desc' => 'Official news and quick announcements',
                                'icon' => 'fa fa-twitter',
                                'bg' => '#0f172a',
                                'placeholder' => 'https://x.com/your-handle',
                                'example' => 'https://x.com/...',
                            ],
                            [
                                'key' => 'footer_website',
                                'label' => 'Official Website',
                                'km_label' => 'គេហទំព័រចម្បង',
                                'desc' => 'Main corporate or external partner portal',
                                'icon' => 'fa fa-globe',
                                'bg' => '#6366f1',
                                'placeholder' => 'https://your-main-domain.com',
                                'example' => 'https://...',
                            ],
                        ];
                    @endphp

                    <!-- Master Follow Us Toggle & Title Controls -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(15,23,42,0.04);">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap;">
                            <div style="flex:1; min-width:280px;" data-search-target>
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; margin-bottom:4px;">
                                    <input type="hidden" name="home_cms[footer_show_social]" value="0">
                                    <input type="checkbox" id="socialShowToggle" name="home_cms[footer_show_social]" value="1" {{ $socialShow ? 'checked' : '' }} style="width:18px; height:18px; accent-color:var(--lm-primary, #6366f1); cursor:pointer;">
                                    <span style="font-size:15px; font-weight:800; color:#0f172a;">{{ $lmText('Enable "Follow Us" Section on Public Site', 'បើកដំណើរការផ្នែក "តាមដានពួកយើង" (Follow Us) លើគេហទំព័រ') }}</span>
                                </label>
                                <p style="margin:0 0 0 28px; font-size:12.5px; color:#64748b; line-height:1.4;">
                                    {{ $lmText('When enabled, visitors will see the social media icons in the website footer. Any social channel with an empty link will be hidden automatically.', 'នៅពេលបើក អតិថិជននឹងឃើញនិមិត្តសញ្ញាបណ្ដាញសង្គមនៅក្បែរ Footer។ បណ្ដាញណាដែលគ្មានតំណភ្ជាប់ នឹងត្រូវលាក់ដោយស្វ័យប្រវត្តិ។') }}
                                </p>
                            </div>

                            <div style="min-width:260px; max-width:340px; flex:1;" data-search-target>
                                <label class="lm-field-label" for="socialTitleInput">
                                    <span>{{ $lmText('Section Title (Heading)', 'ចំណងជើងផ្នែក') }}</span>
                                </label>
                                <input type="text" class="lm-input" id="socialTitleInput" name="home_cms[footer_social_title]" value="{{ $socialTitle }}" maxlength="80" placeholder="Follow Us (e.g. តាមដានពួកយើង)">
                                <div class="lm-field-hint">{{ $lmText('Text displayed above the social icons.', 'អក្សរដែលបង្ហាញពីលើរូបតំណាងបណ្ដាញសង្គម។') }}</div>
                            </div>
                        </div>

                        <!-- Real-time Live Footer Mockup -->
                        <div class="lm-social-preview-container" id="socialPreviewWrapper">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #27272a; padding-bottom:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <i class="fa fa-eye" style="color:#a1a1aa; font-size:13px;"></i>
                                    <span style="font-size:12px; font-weight:800; color:#e4e4e7; text-transform:uppercase; letter-spacing:0.5px;">{{ $lmText('Live Website Footer Preview', 'គំរូជាក់ស្តែងនៅក្បាល Footer គេហទំព័រ') }}</span>
                                </div>
                                <span style="font-size:11px; color:#71717a; background:#27272a; padding:2px 8px; border-radius:4px;">{{ $lmText('Interactive Preview', 'មើលទិន្នន័យជាក់ស្តែង') }}</span>
                            </div>

                            <div id="socialPreviewBody" style="transition:opacity 0.2s ease;">
                                <div class="lm-social-preview-title" id="socialPreviewTitle">{{ $socialTitle ?: 'FOLLOW US' }}</div>
                                <div class="lm-social-preview-icons" id="socialPreviewIcons">
                                    @foreach($socialChannels as $channel)
                                        @php
                                            $val = trim((string) old('home_cms.'.$channel['key'], $cms[$channel['key']] ?? ''));
                                            $hasVal = filled($val);
                                            $url = $hasVal ? (preg_match('#^(https?:)?//#i', $val) ? $val : 'https://'.$val) : '#';
                                        @endphp
                                        <a href="{{ $url }}" id="preview_icon_{{ $channel['key'] }}" class="lm-social-preview-link" target="_blank" rel="noopener noreferrer" title="{{ $channel['label'] }}" style="{{ $hasVal ? 'display:inline-grid;' : 'display:none;' }}">
                                            <i class="{{ $channel['icon'] }}"></i>
                                        </a>
                                    @endforeach
                                </div>
                                <div id="socialPreviewEmptyNotice" style="display:none; color:#71717a; font-size:12px; font-style:italic; margin-top:8px;">
                                    {{ $lmText('No social links filled yet. Fill at least one link below to see it live.', 'មិនទាន់មានតំណភ្ជាប់ណាមួយត្រូវបានបញ្ចូលនៅឡើយទេ។ សូមបំពេញតំណភ្ជាប់ខាងក្រោមយ៉ាងហោចមួយ។') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Social Media Platform Cards -->
                    <div class="lm-social-grid">
                        @foreach($socialChannels as $channel)
                            @php
                                $val = trim((string) old('home_cms.'.$channel['key'], $cms[$channel['key']] ?? ''));
                                $hasVal = filled($val);
                                $testUrl = $hasVal ? (preg_match('#^(https?:)?//#i', $val) ? $val : 'https://'.$val) : 'javascript:void(0)';
                            @endphp
                            <div class="lm-social-card" data-search-target>
                                <div>
                                    <div class="lm-social-card-head">
                                        <div class="lm-social-meta">
                                            <div class="lm-social-icon-box" style="background:{{ $channel['bg'] }};">
                                                <i class="{{ $channel['icon'] }}"></i>
                                            </div>
                                            <div class="lm-social-info">
                                                <h4>{{ $channel['label'] }} <span style="font-size:12px; font-weight:600; color:#64748b;">({{ $channel['km_label'] }})</span></h4>
                                                <p>{{ $channel['desc'] }}</p>
                                            </div>
                                        </div>
                                        <span id="badge_{{ $channel['key'] }}" class="lm-social-badge {{ $hasVal ? 'active' : 'inactive' }}">
                                            @if($hasVal)
                                                <i class="fa fa-check-circle"></i> {{ $lmText('Active', 'បានភ្ជាប់') }}
                                            @else
                                                {{ $lmText('Not Set', 'មិនទាន់កំណត់') }}
                                            @endif
                                        </span>
                                    </div>

                                    <div class="lm-field" style="margin-bottom:8px;">
                                        <div class="lm-social-input-row">
                                            <input type="text"
                                                   class="lm-input social-link-input"
                                                   id="cms_{{ $channel['key'] }}"
                                                   name="home_cms[{{ $channel['key'] }}]"
                                                   value="{{ $val }}"
                                                   maxlength="220"
                                                   placeholder="{{ $channel['placeholder'] }}"
                                                   autocomplete="off">

                                            <a href="{{ $testUrl }}"
                                               id="test_btn_{{ $channel['key'] }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="lm-social-test-btn {{ $hasVal ? '' : 'disabled' }}"
                                               title="{{ $lmText('Open and test link in new tab', 'បើកមើលតំណភ្ជាប់ក្នុងផ្ទាំងថ្មី') }}">
                                                <i class="fa fa-external-link"></i> {{ $lmText('Test', 'តេស្ត') }}
                                            </a>
                                        </div>
                                        <div class="lm-field-hint" style="margin-top:4px;">
                                            {{ $lmText('Example:', 'ឧទាហរណ៍៖') }} <code>{{ $channel['example'] }}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- TAB 8: CMS & PUBLIC HOMEPAGE (EMBEDDED NATIVELY) -->
                <div class="lm-tab-pane" id="tab-cms">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Public Homepage CMS & Catalog', 'គ្រប់គ្រងមាតិកាទំព័រដើម CMS') }}</h2>
                            <p>{{ $lmText('Customize customer-facing hero banner, headlines, product brand highlights, and branch information.', 'កែសម្រួលផ្ទាំងរូបភាពទំព័រដើម ចំណងជើង ស្លាកយីហោទំនិញ និងព័ត៌មានសាខា។') }}</p>
                        </div>
                        <a href="{{ route('loan-management.public.home') }}" target="_blank" class="btn btn-sm btn-default" style="font-weight:700;">
                            <i class="fa fa-external-link"></i> {{ $lmText('View Public Site', 'មើលគេហទំព័រ') }}
                        </a>
                    </div>

                    <div class="lm-cms-workspace">
                        <div>
                            <div class="lm-field" data-search-target>
                                <label class="lm-field-label" for="homeHeadlineInput">{{ $lmText('Main Headline', 'ចំណងជើងធំ') }}</label>
                                <input type="text" class="lm-input" id="homeHeadlineInput" name="home_headline" maxlength="140" value="{{ old('home_headline', $settings['home_headline']) }}" placeholder="e.g. Simple loan service for customers">
                            </div>

                            <div class="lm-field" data-search-target>
                                <label class="lm-field-label" for="homeSubtitleInput">{{ $lmText('Subtitle Description', 'អត្ថបទរង') }}</label>
                                <input type="text" class="lm-input" id="homeSubtitleInput" name="home_subtitle" maxlength="220" value="{{ old('home_subtitle', $settings['home_subtitle']) }}">
                            </div>

                            <div class="lm-field" data-search-target>
                                <label class="lm-field-label" for="homeBodyInput">{{ $lmText('About / Service Body Text', 'អត្ថបទពណ៌នាសេវាកម្មលម្អិត') }}</label>
                                <textarea class="lm-textarea" id="homeBodyInput" name="home_body" maxlength="1200">{{ old('home_body', $settings['home_body']) }}</textarea>
                            </div>

                            <div class="lm-field" style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;" data-search-target>
                                <label class="lm-field-label" for="homeHeroInput">{{ $lmText('Homepage Hero Banner Image', 'រូបភាពបិទផ្ទាំងទំព័រដើម') }}</label>
                                <div style="margin-bottom:10px; border-radius:8px; overflow:hidden; border:1px solid #cbd5e1; max-height:140px;">
                                    <img id="cmsHeroThumbnail" src="{{ route('loan-management.public.home-image') }}" alt="Current hero" style="width:100%; height:140px; object-fit:cover; display:block;">
                                </div>
                                <input type="file" id="homeHeroInput" name="home_hero" accept="image/jpeg,image/png,image/webp" class="lm-input" style="padding:6px 10px;">
                                <div class="lm-field-hint" style="margin-top:6px;">{{ $lmText('Landscape JPG, PNG, or WEBP. Max 50 MB.', 'រូបភាពផ្តេក JPG, PNG ឬ WEBP។ ទំហំអតិបរមា 50 MB។') }}</div>
                                <label style="font-size:12px; font-weight:700; color:#475569; margin-top:8px; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" name="remove_home_hero" value="1"> {{ $lmText('Restore default template image', 'ប្រើរូបភាពលំនាំដើមឡើងវិញ') }}
                                </label>
                            </div>
                        </div>

                        <!-- Right Column: Live Mockup -->
                        <div>
                            <div class="lm-preview-box">
                                <div class="lm-preview-browser-bar">
                                    <span class="lm-browser-dot"></span>
                                    <span class="lm-browser-dot"></span>
                                    <span class="lm-browser-dot"></span>
                                    <span class="lm-browser-address">https://your-domain.com/</span>
                                </div>

                                <div class="lm-preview-hero" id="cmsPreviewHero" style="--lm-hero-preview: url('{{ route('loan-management.public.home-image') }}');">
                                    <div class="lm-preview-brand">
                                        <span class="lm-preview-logo">
                                            @if($businessLogoUrl)
                                                <img src="{{ $businessLogoUrl }}" alt="">
                                            @else
                                                {{ strtoupper(mb_substr($settings['business_name'], 0, 1)) }}
                                            @endif
                                        </span>
                                        <span>{{ $settings['business_name'] }}</span>
                                    </div>
                                    <div>
                                        <h3 id="cmsPreviewHeadline">{{ old('home_headline', $settings['home_headline']) }}</h3>
                                        <p id="cmsPreviewSubtitle" style="margin-bottom:6px;">{{ old('home_subtitle', $settings['home_subtitle']) }}</p>
                                        <p id="cmsPreviewBody" style="opacity:0.8; font-size:11px;">{{ old('home_body', $settings['home_body']) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 9: PORTAL & SECURITY -->
                <div class="lm-tab-pane" id="tab-portal">
                    <div class="lm-section-head">
                        <div>
                            <h2>{{ $lmText('Customer Portal & Access Control', 'ច្រកចូលអតិថិជន និងការគ្រប់គ្រងសុវត្ថិភាព') }}</h2>
                            <p>{{ $lmText('Configure customer self-service access, homepage CMS availability, and demo accounts visibility.', 'កំណត់ការចូលប្រើប្រាស់របស់អតិថិជន ម៉ូឌុល CMS និងការបង្ហាញគណនីសាកល្បង (Demo)។') }}</p>
                        </div>
                    </div>

                    <div class="lm-form-grid">
                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Public Homepage & CMS Module', 'ម៉ូឌុលទំព័រដើមសាធារណៈ & CMS') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('When enabled, visitors see your catalog and loan request forms. When disabled, visitors redirect directly to employee login.', 'ពេលបើក អ្នកចូលទស្សនានឹងឃើញទំព័រដើម និងទម្រង់ស្នើសុំកម្ចី។ ពេលបិទ នឹងបញ្ជូនទៅទំព័រចូលប្រើបុគ្គលិក។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="cms_enabled" value="0">
                                    <input type="checkbox" id="cmsEnabledInput" name="cms_enabled" value="1" {{ old('cms_enabled', $settings['cms_enabled'] ?? true) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Customer Self-Service Portal Login', 'អនុញ្ញាតឱ្យអតិថិជនចូលប្រើប្រាស់គណនី') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('Allows approved borrowers to sign in, review repayment schedules, download receipts, and chat with loan officers.', 'អនុញ្ញាតឱ្យអតិថិជនចូលពិនិត្យតារាងបង់ប្រាក់ ទាញយកបង្កាន់ដៃ និងជជែកជាមួយមន្ត្រីឥណទាន។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="customer_login_enabled" value="0">
                                    <input type="checkbox" id="customerLoginEnabledInput" name="customer_login_enabled" value="1" {{ old('customer_login_enabled', $settings['customer_login_enabled'] ?? true) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="lm-col-full" style="padding-top:14px; margin-top:10px; border-top:1px solid #f1f5f9;">
                            <h3 style="margin:0 0 4px; font-size:16px; font-weight:800; color:#0f172a;">{{ $lmText('Demo Credentials & Production Security', 'គណនីសាកល្បង និងសុវត្ថិភាពប្រព័ន្ធផ្លូវការ') }}</h3>
                            <p style="margin:0 0 14px; font-size:12px; color:#64748b;">{{ $lmText('Turn these OFF in real live production environments to prevent unauthorized demonstration access.', 'ត្រូវបិទជម្រើសទាំងនេះនៅពេលដំណើរការផ្លូវការ (Live Production) ដើម្បីសុវត្ថិភាព។') }}</p>
                        </div>

                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Show 1-Click Demo Customer Login Widget', 'បង្ហាញប្រអប់ 1-Click Demo លើទំព័រចូលអតិថិជន') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('Convenient for staff training and user demonstration.', 'ងាយស្រួលសម្រាប់ការបណ្តុះបណ្តាលបុគ្គលិក ឬការធ្វើ Demo។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="demo_customer_login_enabled" value="0">
                                    <input type="checkbox" id="demoCustomerLoginInput" name="demo_customer_login_enabled" value="1" {{ old('demo_customer_login_enabled', $settings['demo_customer_login_enabled'] ?? true) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="lm-field lm-col-full">
                            <div class="lm-toggle-card" data-search-target>
                                <div class="lm-toggle-info">
                                    <div class="lm-toggle-title">{{ $lmText('Show Demo Admin Credentials on Admin Sign-In', 'បង្ហាញព័ត៌មាន Demo លើទំព័រចូល Admin') }}</div>
                                    <p class="lm-toggle-desc">{{ $lmText('Displays demo admin email and password on the staff login screen.', 'បង្ហាញអ៊ីមែល និងពាក្យសម្ងាត់ Demo លើផ្ទាំងចូលប្រើរបស់បុគ្គលិក។') }}</p>
                                </div>
                                <label class="lm-switch">
                                    <input type="hidden" name="demo_admin_login_enabled" value="0">
                                    <input type="checkbox" id="demoAdminLoginInput" name="demo_admin_login_enabled" value="1" {{ old('demo_admin_login_enabled', $settings['demo_admin_login_enabled'] ?? false) ? 'checked' : '' }}>
                                    <span class="lm-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                </fieldset>
                <!-- Sticky Bottom Action Bar -->
                <div class="lm-sticky-actions">
                    <div class="lm-save-status">
                        <span class="dot"></span>
                        <span id="saveStatusText" role="status" aria-live="polite">{{ $lmText('Ready to save changes', 'រួចរាល់សម្រាប់ការរក្សាទុក') }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <a href="{{ route('loan-management.dashboard') }}" class="btn btn-default" style="border-radius:8px; font-weight:700; padding:8px 16px;">
                            {{ $lmText('Cancel', 'បោះបង់') }}
                        </a>
                        @if($canSaveSettings)
                        <button type="button" class="btn btn-default" id="resetSettingsBtn"><i class="fa fa-undo"></i> {{ $lmText('Reset changes', 'កំណត់ឡើងវិញ') }}</button>
                        <button type="submit" class="btn btn-primary" id="saveSettingsBtn" style="border-radius:8px; font-weight:800; padding:8px 22px;">
                            <i class="fa fa-save" style="margin-right:6px;"></i> {{ $lmText('Save All Settings', 'រក្សាទុកការកំណត់ទាំងអស់') }}
                        </button>
                        @endif
                    </div>
                </div>

            </main>
        </div>
    </form>
</div>
@endsection

@section('loan_js')
<script src="{{ asset('js/loan-settings.js') }}?v=20261029"></script>
@endsection
