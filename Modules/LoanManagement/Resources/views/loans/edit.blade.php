@extends('loanmanagement::layouts.app')
@section('title', 'Edit Installment')

@section('loan_css')
@php $isEmbeddedModal = request()->boolean('_lm_modal'); @endphp
@if($isEmbeddedModal)
<style>
    html, body.loan-management-embedded-modal { min-height: 100% !important; overflow: auto !important; background: #f8fafc !important; }
    body.loan-management-embedded-modal .thetop,
    body.loan-management-embedded-modal #scrollable-container,
    body.loan-management-embedded-modal #loanManagementApp,
    body.loan-management-embedded-modal #loanManagementMain,
    body.loan-management-embedded-modal .lm-content,
    body.loan-management-embedded-modal .lm-workspace {
        width: 100% !important; min-height: 100% !important; margin: 0 !important; padding: 0 !important; overflow: visible !important;
    }
    body.loan-management-embedded-modal #main_app_header,
    body.loan-management-embedded-modal #app,
    #loanManagementSidebar,
    #loanManagementHeader,
    .lm-breadcrumb-wrap,
    .lm-footer { display: none !important; }
    #loanManagementMain { margin-left: 0 !important; width: 100% !important; }
    #loanManagementMain .lm-content { padding-top: 0 !important; }
    #loanManagementMain .lm-workspace { padding: 0 !important; }
    .content-header { margin-top: 0 !important; padding-top: 0 !important; }
    .content { min-height: 100% !important; }
</style>
@endif

<style>
    *, *::before, *::after { box-sizing: border-box; }

    .lm-pro-edit-wrap {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f1f5f9;
        color: #1e293b;
        margin: -15px -15px 0 -15px;
        min-height: calc(100vh - 60px);
        display: flex;
        flex-direction: column;
    }

    /* Enterprise Compact Header */
    .lm-pro-edit-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #fff;
        padding: 8px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .lm-pro-edit-header-left { display: flex; align-items: center; gap: 10px; }
    .lm-pro-edit-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(37, 99, 235, 0.25);
        border: 1px solid rgba(96, 165, 250, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: #60a5fa;
    }
    .lm-pro-edit-title { font-size: 15px; font-weight: 700; margin: 0; color: #f8fafc; display: flex; align-items: center; gap: 8px; }
    .lm-pro-edit-sub { font-size: 11px; color: #94a3b8; margin: 1px 0 0; }
    .lm-status-pill {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .lm-status-pill.active, .lm-status-pill.approved { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .lm-status-pill.draft, .lm-status-pill.pending { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
    .lm-status-pill.rejected, .lm-status-pill.cancelled, .lm-status-pill.defaulted { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

    .lm-pro-edit-header-right { display: flex; align-items: center; gap: 8px; }
    .lm-btn-nav {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.15);
        text-decoration: none;
        transition: all 0.15s;
    }
    .lm-btn-nav:hover { background: rgba(255, 255, 255, 0.2); color: #fff; text-decoration: none; }

    /* Workspace Content */
    .lm-pro-edit-body {
        flex: 1;
        padding: 10px 14px;
        background: #f1f5f9;
    }

    /* Top Strip (Compact Single Row) */
    .lm-top-strip {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        margin-bottom: 10px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        display: grid;
        grid-template-columns: 140px 150px 1fr 1fr 130px 1.4fr;
        gap: 8px;
        align-items: center;
    }

    /* 2-Column Dense Grid */
    .lm-grid-workspace {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 10px;
        align-items: start;
    }
    .lm-col { display: flex; flex-direction: column; gap: 10px; }

    /* Compact Professional Cards */
    .lm-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        overflow: hidden;
    }
    .lm-card-head {
        padding: 6px 12px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .lm-card-title {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .lm-card-title i { color: #2563eb; font-size: 13px; }
    .lm-card-body { padding: 10px 12px; }

    /* Form Controls */
    .lm-field { margin-bottom: 7px; }
    .lm-field:last-child { margin-bottom: 0; }
    .lm-label {
        display: block;
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        line-height: 1.2;
    }
    .lm-req { color: #ef4444; margin-left: 2px; }
    .lm-control {
        width: 100%;
        height: 31px;
        padding: 0 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 12px;
        color: #1e293b;
        background: #fff;
        transition: all 0.15s;
    }
    .lm-control:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
    }
    select.lm-control {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        padding-right: 24px;
        -webkit-appearance: none;
        appearance: none;
    }
    .lm-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .lm-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .lm-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }

    /* Smart KYC Strip (Slim) */
    .lm-kyc-strip {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px;
    }
    .lm-kyc-box {
        background: #fff;
        border-radius: 8px;
        border: 1px dashed #cbd5e1;
        padding: 6px 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }
    .lm-kyc-thumb {
        width: 44px;
        height: 44px;
        border-radius: 6px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 18px;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
    }
    .lm-kyc-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .lm-kyc-details { flex: 1; min-width: 0; }
    .lm-kyc-title { font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 2px; }
    .lm-kyc-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    .lm-kyc-btn {
        padding: 3px 8px;
        border-radius: 5px;
        font-size: 10px;
        font-weight: 600;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #2563eb;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s;
    }
    .lm-kyc-btn:hover { background: #eff6ff; border-color: #2563eb; }

    /* Product Item Rows (Compact) */
    .wiz-item-row {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 6px;
        overflow: hidden;
    }
    .wiz-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 10px;
        background: #f8fafc;
        cursor: pointer;
        user-select: none;
    }
    .wiz-item-header:hover { background: #f1f5f9; }
    .wiz-item-row.open .wiz-item-header { background: #eff6ff; border-bottom: 1px solid #dbeafe; }
    .wiz-item-header-left { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .wiz-item-header-thumb {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        overflow: hidden;
        flex-shrink: 0;
        font-size: 12px;
    }
    .wiz-item-header-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .wiz-item-header-main { min-width: 0; }
    .wiz-item-header-main strong { font-size: 12px; color: #1e293b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wiz-item-header-main small { font-size: 10px; color: #64748b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wiz-item-line-total { font-weight: 800; color: #059669; font-size: 12px; margin-left: 8px; }
    .wiz-item-body { padding: 8px 10px; background: #fff; }
    .wiz-item-form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }
    .wiz-item-field label { display: block; font-size: 9px; font-weight: 700; color: #64748b; margin-bottom: 2px; text-transform: uppercase; }
    .wiz-item-field .lm-wiz-input { width: 100%; height: 28px; border: 1px solid #cbd5e1; border-radius: 5px; padding: 0 8px; font-size: 12px; }
    .wiz-item-form-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 8px; }

    /* Deposit / Payment Rows (Compact) */
    .wiz-deposit-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 6px;
        overflow: hidden;
    }
    .wiz-deposit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 8px;
        cursor: pointer;
    }
    .wiz-deposit-header:hover { background: #f1f5f9; }
    .wiz-deposit-row.open .wiz-deposit-header { background: #eff6ff; }
    .wiz-deposit-body { padding: 8px; background: #fff; border-top: 1px solid #e2e8f0; }
    .wiz-deposit-form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }

    /* Financial Metrics Overview (Compact Grid) */
    .lm-metrics-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 6px;
        margin-top: 8px;
    }
    .lm-metric-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 8px;
        text-align: center;
    }
    .lm-metric-card.highlight { background: #eff6ff; border-color: #bfdbfe; }
    .lm-metric-card.success { background: #ecfdf5; border-color: #a7f3d0; }
    .lm-metric-label { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
    .lm-metric-val { font-size: 13px; font-weight: 800; color: #0f172a; }
    .lm-metric-card.highlight .lm-metric-val { color: #2563eb; }
    .lm-metric-card.success .lm-metric-val { color: #059669; }

    /* Documents Grid (Compact) */
    .wiz-doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(68px, 1fr));
        gap: 6px;
        margin-top: 6px;
    }
    .wiz-doc-tile {
        aspect-ratio: 1;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        text-align: center;
        cursor: pointer;
    }
    .wiz-doc-tile img { width: 100%; height: 100%; object-fit: cover; }
    .wiz-doc-tile i { font-size: 16px; color: #64748b; margin-bottom: 2px; }
    .wiz-doc-remove {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.9);
        color: #fff;
        border: none;
        font-size: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    /* Schedule Table (Compact) */
    .lm-wiz-schedule-wrap {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-top: 8px;
    }
    .lm-wiz-schedule-tbl {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        background: #fff;
    }
    .lm-wiz-schedule-tbl th {
        background: #f8fafc;
        padding: 5px 8px;
        font-weight: 700;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .lm-wiz-schedule-tbl td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
    .lm-wiz-schedule-tbl tfoot th { background: #f8fafc; font-weight: 800; border-top: 1px solid #cbd5e1; }

    /* Slim Sticky Footer */
    .lm-pro-footer {
        background: #fff;
        border-top: 1px solid #e2e8f0;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.03);
        position: sticky;
        bottom: 0;
        z-index: 20;
    }
    .lm-pro-footer-info { display: flex; align-items: center; gap: 10px; font-size: 12px; }
    .lm-pro-footer-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 11px;
    }
    .lm-pro-footer-pill strong { color: #0f172a; }
    .lm-pro-footer-actions { display: flex; align-items: center; gap: 8px; }

    .lm-btn {
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.15s;
        text-decoration: none;
    }
    .lm-btn-outline { background: #fff; border-color: #cbd5e1; color: #475569; }
    .lm-btn-outline:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; text-decoration: none; }
    .lm-btn-secondary { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
    .lm-btn-secondary:hover { background: #dbeafe; text-decoration: none; }
    .lm-btn-primary {
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: #fff;
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
    }
    .lm-btn-primary:hover { background: linear-gradient(135deg, #15803d, #166534); transform: translateY(-1px); color: #fff; text-decoration: none; }

    /* Photo Choice & Crop Overlays (Hidden by default, fixed full-screen modal) */
    .wiz-photo-choice-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .wiz-photo-choice-box {
        background: #ffffff;
        border-radius: 16px;
        width: 100%;
        max-width: 380px;
        padding: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        text-align: center;
        animation: wizPopIn 0.2s ease-out;
    }
    .wiz-photo-choice-title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .wiz-photo-choice-title i { color: #2563eb; }
    .wiz-photo-choice-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }
    .wiz-photo-choice-actions .btn {
        padding: 12px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.15s;
    }
    .wiz-photo-choice-actions .btn-primary {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    .wiz-photo-choice-actions .btn-primary:hover { background: #1d4ed8; }
    .wiz-photo-choice-actions .btn-default {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #334155;
    }
    .wiz-photo-choice-actions .btn-default:hover { background: #e2e8f0; }
    #wizPhotoChoiceCancel {
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        padding: 6px;
    }
    #wizPhotoChoiceCancel:hover { color: #ef4444; }

    .wiz-product-crop-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .wiz-product-crop-box {
        background: #ffffff;
        border-radius: 16px;
        width: 100%;
        max-width: 760px;
        max-height: 92vh;
        overflow-y: auto;
        padding: 20px 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        animation: wizPopIn 0.2s ease-out;
    }
    .wiz-product-crop-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .wiz-product-crop-title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .wiz-product-crop-title i { color: #2563eb; }
    #wizProductCropClose, #wizCustomerCropClose {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }
    #wizProductCropClose:hover, #wizCustomerCropClose:hover { background: #fee2e2; color: #ef4444; border-color: #fca5a5; }
    .wiz-product-crop-canvas {
        display: block;
        width: 100%;
        max-height: 52vh;
        border-radius: 10px;
        background: #0f172a;
        touch-action: none;
        border: 1px solid #334155;
        margin-bottom: 10px;
    }
    .wiz-product-crop-status {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-align: center;
        margin-bottom: 14px;
    }
    .wiz-product-crop-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }
    .wiz-product-crop-actions .btn {
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .wiz-product-crop-actions .btn-default {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #334155;
    }
    .wiz-product-crop-actions .btn-default:hover { background: #e2e8f0; color: #0f172a; }
    .wiz-product-crop-actions .btn-primary {
        background: #2563eb;
        border: 1px solid #2563eb;
        color: #fff;
    }
    .wiz-product-crop-actions .btn-primary:hover { background: #1d4ed8; }

    @keyframes wizPopIn {
        from { opacity: 0; transform: scale(0.96); }
        to { opacity: 1; transform: scale(1); }
    }

    @media (max-width: 991px) {
        .lm-top-strip { grid-template-columns: 1fr 1fr; }
        .lm-grid-workspace { grid-template-columns: 1fr; }
        .lm-metrics-grid { grid-template-columns: repeat(3, 1fr); }
        .lm-pro-footer { flex-direction: column; gap: 12px; align-items: stretch; }
        .lm-pro-footer-actions { justify-content: flex-end; flex-wrap: wrap; }
    }
</style>
@endsection

@section('content_body')
@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $isEmbeddedModal = request()->boolean('_lm_modal');
    $backCustomerId = request('customer_id') ?: ($loanRow->customer_id ?? null);
    $editRouteParams = ['loan' => $loanRow->id];
    if ($isEmbeddedModal) { $editRouteParams['_lm_modal'] = 1; }
    if (!empty($backCustomerId)) { $editRouteParams['customer_id'] = $backCustomerId; }

    $loanStatuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted', 'closed'];
    $interestTypes = [
        'flat' => $lmText('Flat Rate (ថេរ)', 'អត្រាថេរ (Flat Rate)'),
        'reducing_balance' => $lmText('Reducing Balance (ថយចុះ)', 'អត្រាថយចុះ (Reducing Balance)')
    ];
    $status = strtolower($loanRow->status ?? 'draft');
    $statusKhmerMap = [
        'active' => 'កំពុងដំណើរការ',
        'approved' => 'អនុម័តរួច',
        'pending' => 'រង់ចាំអនុម័ត',
        'draft' => 'ព្រាង',
        'completed' => 'បានបញ្ចប់',
        'rejected' => 'បដិសេធ',
        'cancelled' => 'បានលុបចោល',
        'defaulted' => 'ខកខានបង់',
        'closed' => 'បានបិទ'
    ];
    $statusLabel = $lmText(ucfirst($status), $statusKhmerMap[$status] ?? ucfirst($status));

    $cleanVal = function ($v) { $v = trim((string) $v); return $v === '-' ? '' : $v; };
    $editCustomerName = $cleanVal($customerName);
    $editCustomerPhone = $cleanVal($customerPhone);
    $editCustomerAddress = $cleanVal($customerAddress);

    $principalAfterDepositValue = (float) old('principal_amount', $loanRow->principal_amount ?? $loanRow->financed_amount ?? 0);
    $collectedLoanItems = $loanItems ?? collect();
    $collectedCollectors = $collectors ?? collect();
    $collectedLocations = $locations ?? collect();
    $depositPayments = $depositPayments ?? collect();

    $safeImageUrl = function ($path) {
        $path = trim((string) $path);
        if ($path === '') { return ''; }
        $path = str_replace('\\', '/', $path);
        if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:image/')) {
            return $path;
        }
        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $storagePath = substr($cleanPath, 8);
            return \Illuminate\Support\Facades\Storage::disk('public')->exists($storagePath) ? asset($cleanPath) : '';
        }
        if (is_file(public_path($cleanPath))) {
            return asset($cleanPath);
        }
        return \Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($cleanPath)
            : '';
    };
    $productPhotoUrl = $safeImageUrl;
    $customerSnapshotPhotoUrl = $safeImageUrl($loanRow->customer_photo_snapshot ?? '');
@endphp

<div class="lm-pro-edit-wrap">
    <!-- Hero Enterprise Header -->
    <div class="lm-pro-edit-header">
        <div class="lm-pro-edit-header-left">
            <div class="lm-pro-edit-icon">
                <i class="fa fa-pencil-square-o"></i>
            </div>
            <div>
                <h2 class="lm-pro-edit-title">
                    <span>{{ $lmText('Edit Installment', 'កែប្រែកម្ចី') }} #{{ $loanRow->loan_number ?? $loanRow->id }}</span>
                    <span class="lm-status-pill {{ $status }}">{{ $statusLabel }}</span>
                </h2>
                <p class="lm-pro-edit-sub">{{ $lmText('Customer:', 'អតិថិជន:') }} <strong>{{ $loanRow->customer_khmer_name ?: ($loanRow->customer_english_name ?: $editCustomerName) }}</strong> &middot; {{ $lmText('Created on', 'បង្កើតនៅថ្ងៃ') }} {{ !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('d-m-Y') : '-' }}</p>
            </div>
        </div>
        <div class="lm-pro-edit-header-right">
            @if(Route::has('loan-management.loans.view'))
                <a href="{{ route('loan-management.loans.view', $loanRow->id) }}" class="lm-btn-nav" title="View agreement">
                    <i class="fa fa-eye"></i> {{ $lmText('View Agreement', 'មើលកិច្ចសន្យា') }}
                </a>
            @endif
            @if(Route::has('loan-management.loans.print'))
                <a href="{{ route('loan-management.loans.print', $loanRow->id) }}" target="_blank" class="lm-btn-nav" title="Print agreement">
                    <i class="fa fa-print"></i> {{ $lmText('Print', 'បោះពុម្ព') }}
                </a>
            @endif
            <a href="{{ route('loan-management.loans') }}" class="lm-btn-nav" title="Back to installments list">
                <i class="fa fa-arrow-left"></i> {{ $lmText('Back', 'ត្រឡប់ក្រោយ') }}
            </a>
        </div>
    </div>

    <!-- Main Edit Form -->
    <form id="wizEditForm" method="POST" action="{{ route('loan-management.loans.update', $editRouteParams) }}" style="display: flex; flex-direction: column; flex: 1;">
        @csrf
        @method('PUT')
        <input type="hidden" name="expected_loan_id" value="{{ $loanRow->id }}">
        <input type="hidden" name="expected_loan_number" value="{{ $loanRow->loan_number ?? '' }}">
        <input type="hidden" name="expected_customer_id" value="{{ $loanRow->customer_id ?? '' }}">

        <div class="lm-pro-edit-body">
            @if ($errors->any())
                <div class="alert alert-danger" style="border-radius: 10px; margin-bottom: 16px;">
                    <strong>{{ $lmText('Unable to save changes.', 'មិនអាចរក្សាទុកការផ្លាស់ប្តូរបានទេ។') }}</strong> {{ $lmText('Please check the highlighted fields below.', 'សូមពិនិត្យមើលប្រអប់ដែលបានរំលេចខាងក្រោម។') }}
                </div>
            @endif

            <!-- Top Agreement Configuration Strip -->
            <div class="lm-top-strip">
                <div class="lm-field">
                    <label class="lm-label">{{ $lmText('Agreement #', 'លេខកិច្ចសន្យា') }}</label>
                    <input type="text" class="lm-control" value="{{ $loanRow->loan_number ?? '#' . $loanRow->id }}" readonly style="font-weight: 700; color: #2563eb; background: #f8fafc;">
                </div>
                <div class="lm-field">
                    <label class="lm-label">{{ $lmText('Agreement Date', 'ថ្ងៃចុះកិច្ចសន្យា') }} <span class="lm-req">*</span></label>
                    <input type="date" name="loan_date" class="lm-control" value="{{ old('loan_date', !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('Y-m-d') : '') }}" required>
                </div>
                <div class="lm-field">
                    <label class="lm-label">{{ $lmText('Business Location', 'ទីតាំងសាខា') }}</label>
                    <select name="business_location_id" class="lm-control">
                        <option value="">{{ $lmText('-- Select Location --', '-- ជ្រើសរើសទីតាំង --') }}</option>
                        @foreach($collectedLocations as $locId => $locName)
                            <option value="{{ $locId }}" {{ (string) $locId === (string) ($selectedBusinessLocationId ?? '') ? 'selected' : '' }}>{{ $locName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lm-field">
                    <label class="lm-label">{{ $lmText('Assigned Collector', 'បុគ្គលិកប្រមូលប្រាក់') }}</label>
                    <select name="assigned_collector_id" class="lm-control">
                        <option value="">{{ $lmText('-- None --', '-- គ្មាន --') }}</option>
                        @foreach($collectedCollectors as $c)
                            <option value="{{ $c->id }}" {{ (string) $c->id === (string) ($loanRow->assigned_collector_id ?? $loanRow->collector_id ?? '') ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lm-field">
                    <label class="lm-label">{{ $lmText('Agreement Status', 'ស្ថានភាពកម្ចី') }}</label>
                    <select name="status" class="lm-control" style="font-weight: 700;">
                        @foreach($loanStatuses as $st)
                            <option value="{{ $st }}" {{ old('status', $loanRow->status ?? 'draft') === $st ? 'selected' : '' }}>
                                {{ $lmText(ucfirst($st), $statusKhmerMap[$st] ?? ucfirst($st)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="lm-field">
                    <label class="lm-label">{{ $lmText('Agreement Note', 'កំណត់ចំណាំកិច្ចសន្យា') }}</label>
                    <input type="text" name="note" class="lm-control" value="{{ old('note', $loanRow->note ?? '') }}" placeholder="{{ $lmText('Agreement remarks...', 'កំណត់ចំណាំបន្ថែម...') }}">
                </div>
                <input type="hidden" name="currency" value="{{ $loanRow->currency ?? 'USD' }}">
                <input type="hidden" name="exchange_rate" value="{{ $loanRow->exchange_rate ?? 1 }}">
                <input type="hidden" name="main_location_id" value="{{ old('main_location_id', $locationId ?? $loanRow->main_location_id ?? '') }}">
            </div>

            <!-- Two-Column Responsive Workspace -->
            <div class="lm-grid-workspace">
                <!-- LEFT COLUMN: Customer KYC, Identification & Documents -->
                <div class="lm-col">
                    <!-- Customer Information Card -->
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h3 class="lm-card-title"><i class="fa fa-user-circle"></i> {{ $lmText('Customer KYC & Identity', 'ព័ត៌មានអតិថិជន & KYC') }}</h3>
                            <span style="font-size: 11px; font-weight: 700; color: #64748b;">{{ $lmText('ID #', 'លេខសម្គាល់ #') }}{{ $loanRow->customer_id ?? '-' }}</span>
                        </div>
                        <div class="lm-card-body">
                            <input type="hidden" name="main_contact_id" value="{{ old('main_contact_id', $mainContactId ?? '') }}">
                            <input type="hidden" name="customer_id" value="{{ old('customer_id', $loanRow->customer_id ?? '') }}">

                            <!-- Smart KYC Strip (Profile & ID Photo) -->
                            <div class="lm-kyc-strip">
                                <!-- Profile Photo Box -->
                                <div class="lm-kyc-box">
                                    <div class="lm-kyc-thumb" id="wizCustomerProfilePreview">
                                        @if(!empty($customerProfilePhotoUrl) || !empty($customerSnapshotPhotoUrl))
                                            <img src="{{ $customerProfilePhotoUrl ?: $customerSnapshotPhotoUrl }}" alt="Customer profile">
                                        @else
                                            <i class="fa fa-user"></i>
                                        @endif
                                    </div>
                                    <div class="lm-kyc-details">
                                        <div class="lm-kyc-title">{{ $lmText('Profile Photo', 'រូបថតផ្ទាល់ខ្លួន') }}</div>
                                        <div class="lm-kyc-actions">
                                            <button type="button" class="lm-kyc-btn wiz-photo-choice-btn" data-camera="#wizCustomerProfileCamera" data-upload="#wizCustomerProfileUpload">
                                                <i class="fa fa-camera"></i> {{ $lmText('Change Photo', 'ប្តូររូបថត') }}
                                            </button>
                                        </div>
                                        <input type="file" id="wizCustomerProfileCamera" accept="image/*" capture="user" style="display:none;">
                                        <input type="file" id="wizCustomerProfileUpload" accept="image/*" style="display:none;">
                                        <input type="hidden" name="customer_profile_image" id="wizCustomerProfileImage" value="">
                                        <div class="wiz-customer-ocr-status" id="wizCustomerProfileStatus" style="font-size: 10px; color: #64748b; margin-top: 3px;"></div>
                                    </div>
                                </div>

                                <!-- National ID OCR Box -->
                                <div class="lm-kyc-box">
                                    <div class="lm-kyc-thumb" id="wizIdCardPreview">
                                        @if(!empty($idCardPhotoUrl))
                                            <img src="{{ $idCardPhotoUrl }}" alt="ID card">
                                        @else
                                            <i class="fa fa-id-card"></i>
                                        @endif
                                    </div>
                                    <div class="lm-kyc-details">
                                        <div class="lm-kyc-title">{{ $lmText('National ID Card', 'អត្តសញ្ញាណប័ណ្ណ') }}</div>
                                        <div class="lm-kyc-actions">
                                            <button type="button" class="lm-kyc-btn wiz-photo-choice-btn" data-camera="#wizIdCardCamera" data-upload="#wizIdCardUpload">
                                                <i class="fa fa-camera"></i> {{ $lmText('Scan Card', 'ស្កេនកាត') }}
                                            </button>
                                            <button type="button" class="lm-kyc-btn" id="wizIdCardReExtractBtn" data-image-url="{{ $idCardPhotoUrl ?? '' }}" style="color: #0284c7;">
                                                <i class="fa fa-magic"></i> {{ $lmText('Re-extract', 'ទាញយកឡើងវិញ') }}
                                            </button>
                                        </div>
                                        <input type="file" id="wizIdCardCamera" accept="image/*" capture="environment" style="display:none;">
                                        <input type="file" id="wizIdCardUpload" accept="image/*" style="display:none;">
                                        <input type="hidden" name="id_card_image" id="wizIdCardImage" value="">
                                        <input type="hidden" name="id_card_ocr_fields[id_card_number]" id="wizIdCardOcrNumber" value="">
                                        <input type="hidden" name="id_card_ocr_fields[khmer_name]" id="wizIdCardOcrKhmerName" value="">
                                        <input type="hidden" name="id_card_ocr_fields[english_name]" id="wizIdCardOcrEnglishName" value="">
                                        <input type="hidden" name="id_card_ocr_fields[address]" id="wizIdCardOcrAddress" value="">
                                        <div class="wiz-customer-ocr-status" id="wizIdCardOcrStatus" style="font-size: 10px; color: #2563eb; font-weight: 600; margin-top: 3px;"></div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="customer_name_snapshot" value="{{ old('customer_khmer_name', trim((string) ($loanRow->customer_khmer_name ?? ''))) ?: old('customer_name_snapshot', $editCustomerName) }}">

                            <!-- Customer Information Fields -->
                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Name in Khmer', 'ឈ្មោះជាភាសាខ្មែរ') }} <span class="lm-req">*</span></label>
                                    <input type="text" name="customer_khmer_name" class="lm-control" value="{{ old('customer_khmer_name', $loanRow->customer_khmer_name ?? '') }}" placeholder="{{ $lmText('Khmer name', 'ឈ្មោះខ្មែរ') }}" required>
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Name in English', 'ឈ្មោះជាអក្សរឡាតាំង') }} <span class="lm-req">*</span></label>
                                    <input type="text" name="customer_english_name" class="lm-control" value="{{ old('customer_english_name', $loanRow->customer_english_name ?? '') }}" placeholder="{{ $lmText('English name', 'ឈ្មោះឡាតាំង') }}" required>
                                </div>
                            </div>

                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Primary Phone', 'លេខទូរស័ព្ទចម្បង') }} <span class="lm-req">*</span></label>
                                    <input type="text" name="customer_phone_snapshot" class="lm-control" value="{{ old('customer_phone_snapshot', $editCustomerPhone) }}" required placeholder="{{ $lmText('Phone number', 'លេខទូរស័ព្ទ') }}">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Alternate Phone', 'លេខទូរស័ព្ទបន្ទាប់បន្សំ') }}</label>
                                    <input type="text" name="alternate_phone" class="lm-control" value="{{ old('alternate_phone', $loanRow->alternate_phone ?? '') }}" placeholder="{{ $lmText('Alternate phone', 'លេខទូរស័ព្ទទីពីរ') }}">
                                </div>
                            </div>

                            <div class="lm-grid-3">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('National ID #', 'លេខអត្តសញ្ញាណប័ណ្ណ') }}</label>
                                    <input type="text" name="id_card_number" class="lm-control" value="{{ old('id_card_number', $loanRow->id_card_number ?? '') }}" placeholder="{{ $lmText('ID card number', 'លេខកាត') }}">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Occupation', 'មុខរបរ / អាជីព') }}</label>
                                    <input type="text" name="occupation" class="lm-control" value="{{ old('occupation', $loanRow->occupation ?? '') }}" placeholder="{{ $lmText('Job / Business', 'ការងារ / អាជីវកម្ម') }}">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Customer Group', 'ក្រុមអតិថិជន') }}</label>
                                    <input type="text" name="customer_group_name" class="lm-control" value="{{ old('customer_group_name', $loanRow->customer_group_name_snapshot ?? 'រំលស់') }}" placeholder="{{ $lmText('Group', 'ក្រុម') }}">
                                </div>
                            </div>

                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Guarantor Name', 'ឈ្មោះអ្នកធានា') }}</label>
                                    <input type="text" name="guarantor_name" class="lm-control" value="{{ old('guarantor_name', $loanRow->guarantor_name ?? '') }}" placeholder="{{ $lmText('Guarantor full name', 'ឈ្មោះអ្នកធានាពេញលេញ') }}">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Guarantor Phone', 'លេខទូរស័ព្ទអ្នកធានា') }}</label>
                                    <input type="text" name="guarantor_phone" class="lm-control" value="{{ old('guarantor_phone', $loanRow->guarantor_phone ?? '') }}" placeholder="{{ $lmText('Guarantor phone', 'លេខទូរស័ព្ទ') }}">
                                </div>
                            </div>

                            <!-- Cambodia Address Dropdowns -->
                            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                                <label class="lm-label" style="color: #2563eb; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa fa-map-marker"></i> {{ $lmText('Cambodia Administrative Address', 'អាសយដ្ឋានរដ្ឋបាលកម្ពុជា') }}
                                </label>
                                <div class="lm-grid-2" style="margin-top: 8px;">
                                    <div class="lm-field">
                                        <label class="lm-label">{{ $lmText('Province / City', 'រាជធានី / ខេត្ត') }}</label>
                                        <select name="province_code" id="wizProvinceSelect" class="lm-control" data-current-code="{{ old('province_code', $loanRow->customer_province_code_snapshot ?? $loanRow->province_code ?? '') }}" data-current-name="{{ old('province_name', $loanRow->customer_province_snapshot ?? $loanRow->province ?? '') }}">
                                            <option value="">{{ $lmText('-- Select Province --', '-- ជ្រើសរើសខេត្ត --') }}</option>
                                        </select>
                                        <input type="hidden" name="province_name" id="wizProvinceName" value="{{ old('province_name', $loanRow->customer_province_snapshot ?? $loanRow->province ?? '') }}">
                                    </div>
                                    <div class="lm-field">
                                        <label class="lm-label">{{ $lmText('District / Khan', 'ក្រុង / ស្រុក / ខណ្ឌ') }}</label>
                                        <select name="district_code" id="wizDistrictSelect" class="lm-control" data-current-code="{{ old('district_code', $loanRow->customer_district_code_snapshot ?? $loanRow->district_code ?? '') }}" data-current-name="{{ old('district_name', $loanRow->customer_district_snapshot ?? $loanRow->district ?? '') }}" disabled>
                                            <option value="">{{ $lmText('-- Select District --', '-- ជ្រើសរើសស្រុក --') }}</option>
                                        </select>
                                        <input type="hidden" name="district_name" id="wizDistrictName" value="{{ old('district_name', $loanRow->customer_district_snapshot ?? $loanRow->district ?? '') }}">
                                    </div>
                                    <div class="lm-field">
                                        <label class="lm-label">{{ $lmText('Commune / Sangkat', 'ឃុំ / សង្កាត់') }}</label>
                                        <select name="commune_code" id="wizCommuneSelect" class="lm-control" data-current-code="{{ old('commune_code', $loanRow->customer_commune_code_snapshot ?? $loanRow->commune_code ?? '') }}" data-current-name="{{ old('commune_name', $loanRow->customer_commune_snapshot ?? $loanRow->commune ?? '') }}" disabled>
                                            <option value="">{{ $lmText('-- Select Commune --', '-- ជ្រើសរើសឃុំ --') }}</option>
                                        </select>
                                        <input type="hidden" name="commune_name" id="wizCommuneName" value="{{ old('commune_name', $loanRow->customer_commune_snapshot ?? $loanRow->commune ?? '') }}">
                                    </div>
                                    <div class="lm-field">
                                        <label class="lm-label">{{ $lmText('Village', 'ភូមិ') }}</label>
                                        <select name="village_code" id="wizVillageSelect" class="lm-control" data-current-code="{{ old('village_code', $loanRow->customer_village_code_snapshot ?? $loanRow->village_code ?? '') }}" data-current-name="{{ old('village_name', $loanRow->customer_village_snapshot ?? $loanRow->village ?? '') }}" disabled>
                                            <option value="">{{ $lmText('-- Select Village --', '-- ជ្រើសរើសភូមិ --') }}</option>
                                        </select>
                                        <input type="hidden" name="village_name" id="wizVillageName" value="{{ old('village_name', $loanRow->customer_village_snapshot ?? $loanRow->village ?? '') }}">
                                    </div>
                                </div>
                                <div id="wizAddressLoadStatus" style="font-size: 11px; color: #64748b; margin-top: 4px;"></div>
                            </div>

                            <div class="lm-field" style="margin-top: 10px;">
                                <label class="lm-label">{{ $lmText('Detailed Street Address / Landmark', 'អាសយដ្ឋានលម្អិត / ទីតាំងសម្គាល់') }}</label>
                                <textarea name="customer_address_snapshot" class="lm-control" rows="2" style="height: auto; padding: 8px 12px;" placeholder="{{ $lmText('House number, street, landmark...', 'លេខផ្ទះ ផ្លូវ ទីតាំងសម្គាល់...') }}">{{ old('customer_address_snapshot', $editCustomerAddress) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Supporting Documents Card -->
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h3 class="lm-card-title"><i class="fa fa-paperclip"></i> {{ $lmText('Supporting Documents & Telegram Notes', 'ឯកសារភ្ជាប់ & Telegram') }}</h3>
                        </div>
                        <div class="lm-card-body">
                            <div class="wiz-doc-grid" id="wizDocGrid">
                                @foreach(($loanDocumentFiles ?? collect()) as $doc)
                                    <div class="wiz-doc-tile">
                                        @if(!empty($doc->url) && str_starts_with((string) ($doc->mime_type ?? ''), 'image/'))
                                            <img src="{{ $doc->url }}" alt="Document">
                                        @else
                                            <i class="fa fa-file-o"></i>
                                            <span style="font-size: 9px; padding: 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $doc->original_name ?? 'Document' }}</span>
                                        @endif
                                        @if(!empty($doc->size_bytes))
                                            <span style="position: absolute; bottom: 2px; left: 2px; background: rgba(0,0,0,0.6); color: #fff; font-size: 8px; padding: 1px 4px; border-radius: 4px;">{{ round($doc->size_bytes / 1024) }}KB</span>
                                        @endif
                                    </div>
                                @endforeach
                                <label class="wiz-doc-tile" for="wizDocInput" id="wizDocAddTile" title="{{ $lmText('Click or paste files here', 'ចុច ឬបិទភ្ជាប់ឯកសារ') }}">
                                    <i class="fa fa-cloud-upload" style="font-size: 22px; color: #2563eb;"></i>
                                    <span style="font-weight: 700; color: #2563eb;">{{ $lmText('Add Files', 'បញ្ចូលឯកសារ') }}</span>
                                </label>
                            </div>
                            <input type="file" id="wizDocInput" accept="image/*,.pdf,.txt,.csv,.doc,.docx" multiple style="display:none;">
                            <div id="wizDocHiddenFields"></div>

                            <div style="margin-top: 14px;">
                                <label class="lm-label">{{ $lmText('Telegram Summary Note', 'កំណត់ចំណាំផ្ញើ Telegram') }}</label>
                                <textarea name="document_text" class="lm-control" rows="2" style="height: auto; padding: 8px 12px;" placeholder="{{ $lmText('Document remark or extra details for telegram notification...', 'កំណត់ចំណាំឯកសារ ឬព័ត៌មានលម្អិតបន្ថែមសម្រាប់ Telegram...') }}">{{ old('document_text') }}</textarea>
                            </div>

                            <div id="wizDocumentLinks" style="margin-top: 10px;">
                                <label class="lm-label">{{ $lmText('External Document Links', 'តំណភ្ជាប់ឯកសារក្រៅ') }}</label>
                                <div class="wiz-doc-link-row" style="display: flex; gap: 6px; margin-bottom: 6px;">
                                    <input type="url" name="document_links[]" class="lm-control" placeholder="https://drive.google.com/...">
                                    <button type="button" class="btn btn-default btn-sm" id="wizBtnAddDocumentLink" title="Add another link" style="border-radius: 8px;"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">
                                <i class="fa fa-clipboard"></i> {{ $lmText('Tip: You can paste screenshots with Ctrl+V directly anywhere.', 'ជំនួយ៖ លោកអ្នកអាចចុច Ctrl+V ដើម្បីបិទភ្ជាប់រូបថតភ្លាមៗ។') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Collateral Items, Down Payment, Financing Terms & Schedules -->
                <div class="lm-col">
                    <!-- Collateral Items Card -->
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h3 class="lm-card-title"><i class="fa fa-cubes"></i> {{ $lmText('Collateral / Products for Installment', 'ទំនិញ / ទ្រព្យបញ្ចាំបង់រំលស់') }}</h3>
                            <button type="button" class="btn btn-primary btn-xs" id="wizBtnAddItem" style="border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa fa-plus-circle"></i> {{ $lmText('Add Item', 'បន្ថែមទំនិញ') }}
                            </button>
                        </div>
                        <div class="lm-card-body">
                            <div id="wizItemsList">
                                @forelse($collectedLoanItems as $item)
                                    @php
                                        $itemTotal = (float) ($item->line_total ?? (($item->qty ?? 1) * ($item->unit_price ?? 0) - ($item->discount ?? 0)));
                                        $itemQty = (float) ($item->qty ?? $item->quantity ?? 1);
                                        $itemUnitPrice = (float) ($item->unit_price ?? 0);
                                        $itemDiscount = (float) ($item->discount ?? 0);
                                        $itemUpdateUrl = route('loan-management.loans.items.update', ['loan' => $loanRow->id, 'item' => $item->id]);
                                        $itemDeleteUrl = route('loan-management.loans.items.destroy', ['loan' => $loanRow->id, 'item' => $item->id]);
                                        $itemPhotoPreview = $productPhotoUrl($item->product_photo_path ?? '');
                                    @endphp
                                    <div class="wiz-item-row" data-id="{{ $item->id }}" data-loan-id="{{ $loanRow->id }}">
                                        <div class="wiz-item-header" onclick="$(this).closest('.wiz-item-row').toggleClass('open').find('.wiz-item-body').slideToggle(200);">
                                            <div class="wiz-item-header-left">
                                                <span class="wiz-item-header-thumb">
                                                    @if($itemPhotoPreview !== '')
                                                        <img src="{{ $itemPhotoPreview }}" alt="">
                                                    @else
                                                        <i class="fa fa-image"></i>
                                                    @endif
                                                </span>
                                                <div class="wiz-item-header-main">
                                                    <strong>{{ $item->product_name_snapshot ?? $item->product_name ?? 'Unnamed' }}</strong>
                                                    <small>SKU: {{ $item->sku_snapshot ?? $item->sku ?? '-' }} &middot; IMEI: {{ $item->imei_snapshot ?? $item->imei ?? '-' }} &middot; {{ $lmText('Qty:', 'ចំនួន:') }} {{ number_format($itemQty, 2) }}</small>
                                                </div>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span class="wiz-item-line-total">${{ number_format($itemTotal, 2) }}</span>
                                                <span style="color:#94a3b8; font-size:11px;"><i class="fa fa-chevron-down"></i></span>
                                            </div>
                                        </div>
                                        <div class="wiz-item-body" style="display:none;" data-update-url="{{ $itemUpdateUrl }}">
                                            <div class="wiz-item-form-grid">
                                                <div class="wiz-item-field" style="grid-column:1/-1;">
                                                    <label>{{ $lmText('Product Name', 'ឈ្មោះទំនិញ') }}</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][product_name_snapshot]" data-item-field="product_name_snapshot" class="lm-wiz-input" value="{{ $item->product_name_snapshot ?? $item->product_name ?? '' }}">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>SKU</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][sku_snapshot]" data-item-field="sku_snapshot" class="lm-wiz-input" value="{{ $item->sku_snapshot ?? $item->sku ?? '' }}">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>IMEI</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][imei_snapshot]" data-item-field="imei_snapshot" class="lm-wiz-input wiz-item-imei" value="{{ $item->imei_snapshot ?? $item->imei ?? '' }}">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Serial #', 'លេខស៊េរី') }}</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][serial_number_snapshot]" data-item-field="serial_number_snapshot" class="lm-wiz-input" value="{{ $item->serial_number_snapshot ?? $item->serial_number ?? '' }}">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Qty', 'ចំនួន') }}</label>
                                                    <input type="number" name="edit_items[{{ $item->id }}][qty]" data-item-field="qty" class="lm-wiz-input" value="{{ $item->qty ?? 1 }}" min="1">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Price ($)', 'តម្លៃ ($)') }}</label>
                                                    <input type="number" step="0.01" name="edit_items[{{ $item->id }}][unit_price]" data-item-field="unit_price" class="lm-wiz-input" value="{{ $item->unit_price ?? 0 }}" min="0">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Discount ($)', 'បញ្ចុះតម្លៃ ($)') }}</label>
                                                    <input type="number" step="0.01" name="edit_items[{{ $item->id }}][discount]" data-item-field="discount" class="lm-wiz-input" value="{{ $itemDiscount }}" min="0">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Color', 'ពណ៌') }}</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][color]" data-item-field="color" class="lm-wiz-input" value="{{ $item->color ?? '' }}">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Storage', 'ទំហំផ្ទុក') }}</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][storage]" data-item-field="storage" class="lm-wiz-input" value="{{ $item->storage ?? '' }}" placeholder="128GB">
                                                </div>
                                                <div class="wiz-item-field">
                                                    <label>{{ $lmText('Brand', 'ម៉ាក') }}</label>
                                                    <input type="text" name="edit_items[{{ $item->id }}][brand]" data-item-field="brand" class="lm-wiz-input" value="{{ $item->brand ?? '' }}">
                                                </div>
                                                <div class="wiz-item-field" style="grid-column:1/-1;">
                                                    <label>{{ $lmText('Product Photo OCR / Scan', 'រូបថតទំនិញ & OCR') }}</label>
                                                    <div style="display: flex; gap: 8px; align-items: center;">
                                                        <button type="button" class="btn btn-default btn-sm wiz-item-photo-action wiz-product-photo-choice-btn" style="border-radius: 6px;">
                                                            <i class="fa fa-camera"></i> {{ $lmText('Photo / OCR', 'រូបថត / OCR') }}
                                                        </button>
                                                        <input type="file" accept="image/*" capture="environment" class="wiz-item-photo-input wiz-item-photo-camera" style="display:none;">
                                                        <input type="file" accept="image/*" class="wiz-item-photo-input wiz-item-photo-upload" style="display:none;">
                                                        <span class="wiz-item-photo-status" style="font-size: 11px; color: #64748b;">{{ !empty($item->product_photo_path) ? $lmText('Photo attached', 'មានរូបថតភ្ជាប់') : '' }}</span>
                                                    </div>
                                                    <input type="hidden" name="edit_items[{{ $item->id }}][product_photo]" data-item-field="product_photo" class="wiz-item-photo-data" value="">
                                                    <input type="text" name="edit_items[{{ $item->id }}][product_photo_path]" data-item-field="product_photo_path" class="lm-wiz-input wiz-item-photo-path" value="{{ $item->product_photo_path ?? '' }}" placeholder="{{ $lmText('Photo URL or path', 'តំណភ្ជាប់រូបថត') }}" style="margin-top:6px;">
                                                </div>
                                            </div>
                                            <div class="wiz-item-form-actions">
                                                <button type="button" class="btn btn-sm btn-primary wiz-item-update-btn" style="border-radius: 6px;">
                                                    <i class="fa fa-refresh"></i> {{ $lmText('Update Item', 'កែប្រែទំនិញ') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger wiz-item-remove-btn" data-url="{{ $itemDeleteUrl }}" style="border-radius: 6px;">
                                                    <i class="fa fa-trash"></i> {{ $lmText('Remove', 'លុបចេញ') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div style="text-align:center; padding:20px; color:#94a3b8;" id="wizItemsEmpty">{{ $lmText('No collateral products attached. Click "Add Item" to add products.', 'មិនទាន់មានទំនិញនៅឡើយទេ។ ចុច "បន្ថែមទំនិញ" ដើម្បីបញ្ចូល។') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Customer Deposit / Down Payment Card -->
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h3 class="lm-card-title"><i class="fa fa-money"></i> {{ $lmText('Customer Deposit Payments', 'ប្រាក់កក់របស់អតិថិជន') }}</h3>
                            <button type="button"
                                    class="btn btn-xs btn-success lm-btn-modal"
                                    data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loanRow->id, 'deposit_payment' => 1, 'return_to' => route('loan-management.loans.edit', $editRouteParams)]) }}"
                                    data-container=".view_modal"
                                    style="border-radius: 6px; font-weight: 700;">
                                <i class="fa fa-plus"></i> {{ $lmText('Add Deposit', 'បន្ថែមប្រាក់កក់') }}
                            </button>
                        </div>
                        <div class="lm-card-body">
                            <div class="lm-field">
                                <label class="lm-label">{{ $lmText('Total Customer Deposit Paid', 'ប្រាក់កក់បានបង់សរុប') }}</label>
                                <div class="lm-control" style="background: #f8fafc; font-weight: 800; color: #059669; display: flex; align-items: center;">
                                    ${{ number_format($customerDepositPaymentsAmount, 2) }}
                                </div>
                            </div>

                            @if($depositPayments->isNotEmpty())
                                <div id="wizDepositList" style="margin-top: 10px;">
                                    @foreach($depositPayments as $dp)
                                        @php
                                            $dpAmount = (float)($dp->total_paid_base ?? $dp->total_paid ?? $dp->amount ?? 0);
                                            $dpRef = $dp->receipt_number ?? $dp->payment_ref_no ?? $dp->reference_number ?? ('Payment #'.$dp->id);
                                            $dpMethod = $dp->payment_method_snapshot ?? $dp->method ?? $dp->channel ?? 'cash';
                                            $dpDate = !empty($dp->paid_date) ? \Carbon\Carbon::parse($dp->paid_date)->format('Y-m-d') : \Carbon\Carbon::parse($dp->paid_at ?? now())->format('Y-m-d');
                                            $dpStatus = strtolower($dp->status ?? 'confirmed');
                                            $dpNote = $dp->note ?? '';
                                            $dpEditReturnUrl = route('loan-management.loans.edit', $editRouteParams);
                                        @endphp
                                        <div class="wiz-deposit-row" data-id="{{ $dp->id }}">
                                            <div class="wiz-deposit-header" onclick="$(this).closest('.wiz-deposit-row').toggleClass('open').find('.wiz-deposit-body').slideToggle(200);">
                                                <div>
                                                    <strong style="font-size: 12px; color: #1e293b;">{{ $dpRef }}</strong>
                                                    <small style="color: #64748b; margin-left: 6px;">{{ $dpDate }} &middot; {{ ucfirst($dpMethod) }}</small>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-weight: 800; color: #059669;">${{ number_format($dpAmount, 2) }}</span>
                                                    <i class="fa fa-chevron-down" style="font-size: 11px; color: #94a3b8;"></i>
                                                </div>
                                            </div>
                                            <div class="wiz-deposit-body" style="display:none;">
                                                <div class="wiz-deposit-edit-form">
                                                    <input type="hidden" name="return_to" value="{{ $dpEditReturnUrl }}">
                                                    <div class="wiz-deposit-form-grid">
                                                        <div class="wiz-deposit-field">
                                                            <label>{{ $lmText('Amount ($)', 'ចំនួនទឹកប្រាក់ ($)') }}</label>
                                                            <input type="number" step="0.01" min="0.01" name="amount" class="lm-wiz-input" value="{{ $dpAmount }}" required>
                                                        </div>
                                                        <div class="wiz-deposit-field">
                                                            <label>{{ $lmText('Date', 'កាលបរិច្ឆេទ') }}</label>
                                                            <input type="date" name="paid_date" class="lm-wiz-input" value="{{ $dpDate }}" required>
                                                        </div>
                                                        <div class="wiz-deposit-field">
                                                            <label>{{ $lmText('Method', 'វិធីសាស្ត្រ') }}</label>
                                                            <select name="method" class="lm-wiz-input">
                                                                @foreach($paymentTypes as $pKey => $pLabel)
                                                                    <option value="{{ $pKey }}" {{ $dpMethod === $pKey || $dpMethod === $pLabel ? 'selected' : '' }}>{{ $pLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="wiz-deposit-field">
                                                            <label>{{ $lmText('Status', 'ស្ថានភាព') }}</label>
                                                            <select name="status" class="lm-wiz-input">
                                                                @foreach(['confirmed'=>$lmText('Confirmed','បានបញ្ជាក់'),'paid'=>$lmText('Paid','បានបង់'),'pending'=>$lmText('Pending','រង់ចាំ'),'failed'=>$lmText('Failed','បរាជ័យ'),'cancelled'=>$lmText('Cancelled','បានលុបចោល')] as $sk => $sl)
                                                                    <option value="{{ $sk }}" {{ $dpStatus === $sk ? 'selected' : '' }}>{{ $sl }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="wiz-deposit-form-actions">
                                                        <button type="button" class="btn btn-sm btn-primary wiz-deposit-update-btn">{{ $lmText('Update Deposit', 'កែប្រែប្រាក់កក់') }}</button>
                                                        <button type="button" class="btn btn-sm btn-danger wiz-deposit-remove" data-payment-id="{{ $dp->id }}" data-return-to="{{ $dpEditReturnUrl }}">{{ $lmText('Remove', 'លុបចេញ') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Financing Terms & Loan Conditions Card -->
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h3 class="lm-card-title"><i class="fa fa-sliders"></i> {{ $lmText('Installment Terms & Conditions', 'លក្ខខណ្ឌ & ការប្រាក់កម្ចី') }}</h3>
                        </div>
                        <div class="lm-card-body">
                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Net Principal Financed', 'ប្រាក់ដើមស្នើសុំសុទ្ធ') }} <span class="lm-req">*</span></label>
                                    <input type="number" step="0.01" name="principal_amount" class="lm-control" value="{{ old('principal_amount', $loanRow->principal_amount ?? $loanRow->financed_amount ?? 0) }}" min="0.01" required style="font-size: 15px; font-weight: 800; color: #2563eb;">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Recorded Deposit / Down Payment', 'ប្រាក់កក់បានកត់ត្រា') }}</label>
                                    <input type="number" step="0.01" name="down_payment" class="lm-control" value="{{ old('down_payment', $loanRow->down_payment ?? 0) }}" min="0">
                                </div>
                            </div>

                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Interest Rate (%)', 'អត្រាការប្រាក់ (%)') }}</label>
                                    <input type="number" step="0.01" name="interest_rate" class="lm-control" value="{{ old('interest_rate', $displayInterestRate ?? 0) }}" min="0" style="font-weight: 700;">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Interest Type', 'ប្រភេទការប្រាក់') }} <span class="lm-req">*</span></label>
                                    <select name="interest_type" class="lm-control">
                                        @foreach($interestTypes as $itKey => $itLabel)
                                            <option value="{{ $itKey }}" {{ old('interest_type', $displayInterestType ?? 'flat') === $itKey ? 'selected' : '' }}>{{ $itLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Duration (Months)', 'រយៈពេល (ខែ)') }} <span class="lm-req">*</span></label>
                                    <input type="number" name="installment_count" class="lm-control" min="1" max="360" value="{{ old('installment_count', $loanRow->installment_count ?? $loanRow->duration_months ?? 12) }}" required style="font-weight: 700;">
                                    <input type="hidden" name="duration_months" id="wizDurationMonths">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Payment Frequency', 'ភាពញឹកញាប់នៃការបង់') }} <span class="lm-req">*</span></label>
                                    <select name="payment_frequency" class="lm-control">
                                        @foreach(['monthly' => $lmText('Monthly (ប្រចាំខែ)', 'ប្រចាំខែ (Monthly)'), 'weekly' => $lmText('Weekly (ប្រចាំសប្ដាហ៍)', 'ប្រចាំសប្ដាហ៍ (Weekly)'), 'daily' => $lmText('Daily (ប្រចាំថ្ងៃ)', 'ប្រចាំថ្ងៃ (Daily)')] as $pfKey => $pfLabel)
                                            <option value="{{ $pfKey }}" {{ old('payment_frequency', $loanRow->payment_frequency ?? 'monthly') === $pfKey ? 'selected' : '' }}>{{ $pfLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('First Due Date', 'ថ្ងៃត្រូវបង់ដំបូង') }} <span class="lm-req">*</span></label>
                                    <input type="date" name="first_due_date" class="lm-control" value="{{ old('first_due_date', !empty($loanRow->first_due_date) ? \Carbon\Carbon::parse($loanRow->first_due_date)->format('Y-m-d') : '') }}">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Maturity Date', 'ថ្ងៃបញ្ចប់កិច្ចសន្យា') }}</label>
                                    <input type="date" name="maturity_date" class="lm-control" value="{{ old('maturity_date', !empty($loanRow->maturity_date) ? \Carbon\Carbon::parse($loanRow->maturity_date)->format('Y-m-d') : '') }}">
                                </div>
                            </div>

                            <div class="lm-grid-2">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Penalty Amount ($)', 'ប្រាក់ពិន័យ ($)') }}</label>
                                    <input type="number" step="0.01" name="penalty_amount" class="lm-control" value="{{ old('penalty_amount', $loanRow->penalty_amount ?? 0) }}" min="0">
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Discount Amount ($)', 'ប្រាក់បញ្ចុះតម្លៃ ($)') }}</label>
                                    <input type="number" step="0.01" name="discount_amount" class="lm-control" value="{{ old('discount_amount', $loanRow->discount_amount ?? 0) }}" min="0">
                                </div>
                            </div>

                            <!-- Financial Overview Metrics -->
                            <div class="lm-metrics-grid">
                                <div class="lm-metric-card">
                                    <div class="lm-metric-label">{{ $lmText('Products', 'ទំនិញសរុប') }}</div>
                                    <div class="lm-metric-val" id="wizStatProductTotal">${{ number_format((float) ($loanItemsUnitPriceTotal ?? 0), 2) }}</div>
                                </div>
                                <div class="lm-metric-card">
                                    <div class="lm-metric-label">{{ $lmText('Deposit', 'ប្រាក់កក់') }}</div>
                                    <div class="lm-metric-val">${{ number_format($customerDepositPaymentsAmount, 2) }}</div>
                                </div>
                                <div class="lm-metric-card highlight">
                                    <div class="lm-metric-label">{{ $lmText('Principal', 'ប្រាក់ដើម') }}</div>
                                    <div class="lm-metric-val" id="wizStatFinanced">${{ number_format($principalAfterDepositValue, 2) }}</div>
                                </div>
                                <div class="lm-metric-card">
                                    <div class="lm-metric-label">{{ $lmText('Total Due', 'ត្រូវបង់សរុប') }}</div>
                                    <div class="lm-metric-val" id="wizSummaryTotal">${{ number_format((float) ($loanRow->total_amount ?? 0), 2) }}</div>
                                </div>
                                <div class="lm-metric-card success">
                                    <div class="lm-metric-label">{{ $lmText('Paid', 'បានបង់') }}</div>
                                    <div class="lm-metric-val">${{ number_format((float) ($loanRow->paid_amount ?? 0), 2) }}</div>
                                </div>
                                <div class="lm-metric-card highlight">
                                    <div class="lm-metric-label">{{ $lmText('Balance', 'សមតុល្យ') }}</div>
                                    <div class="lm-metric-val" id="wizSummaryBalance">${{ number_format((float) ($loanRow->balance_amount ?? 0), 2) }}</div>
                                </div>
                            </div>

                            <!-- Payment Schedules Section -->
                            <div id="wizScheduleSection" style="margin-top: 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <label class="lm-label" style="color: #2563eb; margin: 0;"><i class="fa fa-calendar"></i> {{ $lmText('Amortization Schedule Preview', 'កាលវិភាគបង់ប្រាក់សាកល្បង') }}</label>
                                    <button type="button" class="btn btn-default btn-xs" id="wizBtnRefreshSchedule" style="border-radius: 6px;">
                                        <i class="fa fa-refresh"></i> {{ $lmText('Refresh Schedule', 'គណនាឡើងវិញ') }}
                                    </button>
                                </div>
                                <div class="lm-wiz-schedule-wrap">
                                    <table class="lm-wiz-schedule-tbl" id="wizScheduleTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>{{ $lmText('Due Date', 'ថ្ងៃផុតកំណត់') }}</th>
                                                <th class="text-right">{{ $lmText('Principal', 'ប្រាក់ដើម') }}</th>
                                                <th class="text-right">{{ $lmText('Interest', 'ការប្រាក់') }}</th>
                                                <th class="text-right">{{ $lmText('Total', 'សរុប') }}</th>
                                                <th class="text-right">{{ $lmText('Balance', 'សមតុល្យ') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="6" class="text-center text-muted">{{ $lmText('Click Preview Schedule to recalculate schedule table.', 'ចុច "គណនាកាលវិភាគ" ដើម្បីបង្ហាញតារាងបង់ប្រាក់ឡើងវិញ។') }}</td></tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="2" class="text-right">{{ $lmText('Total', 'សរុប') }}</th>
                                                <th class="text-right">$0.00</th>
                                                <th class="text-right">$0.00</th>
                                                <th class="text-right">$0.00</th>
                                                <th class="text-right">$0.00</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Professional Action Footer -->
        <div class="lm-pro-footer">
            <div class="lm-pro-footer-info">
                <span class="lm-pro-footer-pill">
                    <i class="fa fa-money" style="color: #2563eb;"></i>
                    <span>{{ $lmText('Principal:', 'ប្រាក់ដើម:') }} <strong id="wizFooterPrincipal">${{ number_format($principalAfterDepositValue, 2) }}</strong></span>
                </span>
                <span class="lm-pro-footer-pill">
                    <i class="fa fa-pie-chart" style="color: #10b981;"></i>
                    <span>{{ $lmText('Balance:', 'សមតុល្យ:') }} <strong id="wizFooterBalance">${{ number_format((float) ($loanRow->balance_amount ?? 0), 2) }}</strong></span>
                </span>
                <span class="lm-pro-footer-pill">
                    <i class="fa fa-clock-o" style="color: #6366f1;"></i>
                    <span>{{ $lmText('Monthly Est:', 'បង់ប្រចាំខែ:') }} <strong id="wizSummaryMonthly">$0.00</strong></span>
                </span>
            </div>
            <div class="lm-pro-footer-actions">
                <button type="button" class="lm-btn lm-btn-secondary" id="wizBtnPreviewSchedule">
                    <i class="fa fa-table"></i> {{ $lmText('Preview Schedule', 'គណនាកាលវិភាគ') }}
                </button>
                <button type="button" class="lm-btn lm-btn-primary" id="wizBtnSubmit">
                    <i class="fa fa-save"></i> {{ $lmText('Save Changes & Update', 'រក្សាទុក & កែប្រែកម្ចី') }}
                </button>
                <a href="{{ route('loan-management.loans') }}" class="lm-btn lm-btn-outline">
                    {{ $lmText('Cancel', 'បោះបង់') }}
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Modal Dialogs & Crop Overlays -->
<div class="wiz-photo-choice-overlay" id="wizPhotoChoiceOverlay" aria-hidden="true">
    <div class="wiz-photo-choice-box">
        <div class="wiz-photo-choice-title"><i class="fa fa-camera"></i> {{ $lmText('Choose Photo Source', 'ជ្រើសរើសប្រភពរូបថត') }}</div>
        <div class="wiz-photo-choice-actions">
            <button type="button" class="btn btn-primary btn-sm" id="wizPhotoChoiceCamera"><i class="fa fa-camera"></i> {{ $lmText('Take', 'ថតរូប') }}</button>
            <button type="button" class="btn btn-default btn-sm" id="wizPhotoChoiceUpload"><i class="fa fa-image"></i> {{ $lmText('Upload', 'បញ្ចូលរូប') }}</button>
        </div>
        <button type="button" class="btn btn-link btn-block btn-sm" id="wizPhotoChoiceCancel">{{ $lmText('Cancel', 'បោះបង់') }}</button>
    </div>
</div>

<div class="wiz-product-crop-overlay" id="wizProductCropOverlay" aria-hidden="true">
    <div class="wiz-product-crop-box">
        <div class="wiz-product-crop-head">
            <div class="wiz-product-crop-title"><i class="fa fa-crop"></i> {{ $lmText('Crop Product Photo', 'កាត់រូបថតទំនិញ') }}</div>
            <button type="button" class="btn btn-default btn-sm" id="wizProductCropClose"><i class="fa fa-times"></i></button>
        </div>
        <canvas class="wiz-product-crop-canvas" id="wizProductCropCanvas"></canvas>
        <div class="wiz-product-crop-status" id="wizProductCropStatus">{{ $lmText('Drag the box or corners to keep only the product label.', 'ទាញប្រអប់ ឬជ្រុងដើម្បីតម្រឹមរូបថតទំនិញ។') }}</div>
        <div class="wiz-product-crop-actions">
            <button type="button" class="btn btn-default btn-sm" id="wizProductCropReset"><i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}</button>
            <button type="button" class="btn btn-default btn-sm" id="wizProductCropOriginal"><i class="fa fa-image"></i> {{ $lmText('Use Original', 'យករូបដើម') }}</button>
            <button type="button" class="btn btn-primary btn-sm" id="wizProductCropUse"><i class="fa fa-check"></i> {{ $lmText('Use Crop & OCR', 'កាត់រូប & ស្កេន OCR') }}</button>
        </div>
    </div>
</div>

<div class="wiz-product-crop-overlay" id="wizCustomerCropOverlay" aria-hidden="true">
    <div class="wiz-product-crop-box">
        <div class="wiz-product-crop-head">
            <div class="wiz-product-crop-title" id="wizCustomerCropTitle"><i class="fa fa-crop"></i> {{ $lmText('Crop Customer Photo', 'កាត់រូបថតអតិថិជន') }}</div>
            <button type="button" class="btn btn-default btn-sm" id="wizCustomerCropClose"><i class="fa fa-times"></i></button>
        </div>
        <canvas class="wiz-product-crop-canvas" id="wizCustomerCropCanvas"></canvas>
        <div class="wiz-product-crop-status" id="wizCustomerCropStatus">{{ $lmText('Drag the box or corners to keep the important area.', 'ទាញប្រអប់ ឬជ្រុងដើម្បីតម្រឹមរូបថតអតិថិជន។') }}</div>
        <div class="wiz-product-crop-actions">
            <button type="button" class="btn btn-default btn-sm" id="wizCustomerCropReset"><i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}</button>
            <button type="button" class="btn btn-default btn-sm" id="wizCustomerCropOriginal"><i class="fa fa-image"></i> {{ $lmText('Use Original', 'យករូបដើម') }}</button>
            <button type="button" class="btn btn-primary btn-sm" id="wizCustomerCropUse"><i class="fa fa-check"></i> {{ $lmText('Use Crop', 'យករូបកាត់') }}</button>
        </div>
    </div>
</div>
@endsection

@section('loan_js')
<script>
(function ($) {
    var wizUrls = {
        productBySerial: "{{ route('loan-management.loans.ajax.product-by-serial') }}",
        scanIdCard: "{{ route('loan-management.loans.edit.scan-id-card', ['loan' => $loanRow->id]) }}",
        scanProductPhoto: "{{ route('loan-management.loans.edit.scan-product-photo', ['loan' => $loanRow->id]) }}",
        previewSchedule: "{{ route('loan-management.loans.preview-standalone-schedule') }}",
        updateAction: "{{ route('loan-management.loans.update', $editRouteParams) }}",
        paymentUpdateBase: "{{ url('loan-management/payments') }}",
        provinces: "{{ route('loan-management.loans.edit.cambodia-address.provinces', ['loan' => $loanRow->id]) }}",
        districts: "{{ route('loan-management.loans.edit.cambodia-address.districts', ['loan' => $loanRow->id]) }}",
        communes: "{{ route('loan-management.loans.edit.cambodia-address.communes', ['loan' => $loanRow->id]) }}",
        villages: "{{ route('loan-management.loans.edit.cambodia-address.villages', ['loan' => $loanRow->id]) }}"
    };
    var wizSerialLookupTimers = {};
    var wizProductCropper = null;
    var wizProductCropRow = null;
    var wizProductCropFile = null;
    var wizCustomerCropper = null;
    var wizCustomerCropFile = null;
    var wizCustomerCropTarget = null;
    var wizDocCounter = 0;
    var wizPhotoChoice = null;
    var wizDepositPaymentsAmount = {{ json_encode((float) ($customerDepositPaymentsAmount ?? 0)) }};

    function wizFormatMoney(v) {
        var n = parseFloat(v || 0);
        return Number.isFinite(n) ? n.toFixed(2) : '0.00';
    }

    function wizParseNum(v) {
        var n = parseFloat(String(v || '').replace(/,/g, ''));
        return Number.isFinite(n) ? n : 0;
    }

    function wizProductItemsTotal() {
        var itemTotal = 0;
        $('#wizItemsList .wiz-item-row').each(function () {
            var qty = wizParseNum($(this).find('[name$="[qty]"], [name="qty"]').val()) || 0;
            var price = wizParseNum($(this).find('[name$="[unit_price]"], [name="unit_price"]').val()) || 0;
            var discount = wizParseNum($(this).find('[name$="[discount]"], [name="discount"]').val()) || 0;
            itemTotal += Math.max(0, qty * price - discount);
        });
        return itemTotal;
    }

    function wizEffectiveDepositAmount() {
        var enteredDownPayment = wizParseNum($('[name="down_payment"]').val());
        return enteredDownPayment > 0 ? enteredDownPayment : wizDepositPaymentsAmount;
    }

    function wizAutoGeneratePrincipalAfterDeposit() {
        var productTotal = wizProductItemsTotal();
        if (productTotal <= 0) return;
        var enteredDownPayment = wizParseNum($('[name="down_payment"]').val());
        var effectiveDeposit = wizEffectiveDepositAmount();
        if (enteredDownPayment <= 0 && effectiveDeposit > 0) {
            $('[name="down_payment"]').val(wizFormatMoney(effectiveDeposit));
        }
        var principalAfterDeposit = Math.max(0, productTotal - effectiveDeposit);
        $('[name="principal_amount"]').val(wizFormatMoney(principalAfterDeposit)).trigger('change');
    }

    function wizShowPhotoChoice(cameraSelector, uploadSelector) {
        wizPhotoChoice = { camera: cameraSelector, upload: uploadSelector };
        $('#wizPhotoChoiceOverlay').css('display', 'flex').attr('aria-hidden', 'false');
    }

    function wizHidePhotoChoice() {
        $('#wizPhotoChoiceOverlay').hide().attr('aria-hidden', 'true');
    }

    function wizChoosePhotoSource(type) {
        if (!wizPhotoChoice) return;
        var selector = type === 'camera' ? wizPhotoChoice.camera : wizPhotoChoice.upload;
        wizHidePhotoChoice();
        $(selector).trigger('click');
        wizPhotoChoice = null;
    }

    function wizSyncCustomerNameFromKhmer() {
        var khmerName = String($('[name="customer_khmer_name"]').val() || '').trim();
        var englishName = String($('[name="customer_english_name"]').val() || '').trim();
        $('[name="customer_name_snapshot"]').val(khmerName || englishName);
    }

    function wizSerializeLoanForm() {
        wizSyncCustomerNameFromKhmer();
        var fields = $('#wizEditForm')
            .find(':input')
            .not('.wiz-deposit-edit-form :input')
            .serializeArray()
            .filter(function (field) {
                return field.name !== '_method';
            });
        return $.param(fields);
    }

    function wizEditRecalcTotals() {
        var itemTotal = 0;
        var itemCount = 0;
        $('#wizItemsList .wiz-item-row').each(function () {
            itemCount++;
            var qty = wizParseNum($(this).find('[name$="[qty]"], [name="qty"]').val()) || 0;
            var price = wizParseNum($(this).find('[name$="[unit_price]"], [name="unit_price"]').val()) || 0;
            var discount = wizParseNum($(this).find('[name$="[discount]"], [name="discount"]').val()) || 0;
            var lineTotal = Math.max(0, qty * price - discount);
            itemTotal += lineTotal;
            $(this).find('.wiz-item-line-total').text('$' + wizFormatMoney(lineTotal));
        });

        var principal = wizParseNum($('[name="principal_amount"]').val());
        var interest = wizParseNum($('[name="interest_amount"]').val());
        var penalty = wizParseNum($('[name="penalty_amount"]').val());
        var discountAmount = wizParseNum($('[name="discount_amount"]').val());
        var paid = wizParseNum($('[name="paid_amount"]').val());
        var downPayment = wizParseNum($('[name="down_payment"]').val());
        var total = Math.max(0, principal + interest + penalty - discountAmount);
        var balance = Math.max(0, total - paid);

        $('#wizStatItemCount').text(itemCount);
        $('#wizStatProductTotal').text('$' + wizFormatMoney(itemTotal));
        $('#wizStatFinanced').text('$' + wizFormatMoney(principal));
        $('#wizSummaryTotal').text('$' + wizFormatMoney(total));
        $('#wizSummaryBalance').text('$' + wizFormatMoney(balance));
        $('#wizFooterPrincipal').text('$' + wizFormatMoney(principal));
        $('#wizFooterBalance').text('$' + wizFormatMoney(balance));

        var dur = parseInt($('[name="installment_count"]').val()) || 12;
        var rate = parseFloat($('[name="interest_rate"]').val()) || 0;
        var estMonthly = dur > 0 ? (principal + (principal * rate / 100 * dur)) / dur : 0;
        $('#wizSummaryMonthly').text('$' + wizFormatMoney(estMonthly));
    }

    function wizRemoveItemFromScreen($row) {
        $row.remove();
        if ($('#wizItemsList .wiz-item-row').length === 0) {
            $('#wizItemsList').html('<div style="text-align:center; padding:20px; color:#94a3b8;" id="wizItemsEmpty">No products yet. Click "Add Item" to add products.</div>');
        }
        wizEditRecalcTotals();
    }

    function wizDeleteItemViaLoanSave($row, $btn) {
        var itemId = parseInt($row.data('id'), 10);
        if (!itemId) {
            wizRemoveItemFromScreen($row);
            return;
        }

        $.ajax({
            url: wizUrls.updateAction,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'PUT',
                'delete_items[]': itemId
            },
            dataType: 'json',
            success: function (res) {
                wizRemoveItemFromScreen($row);
                if (window.toastr) toastr.success(res.message || 'Item removed.');
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to remove item.';
                if (window.toastr) toastr.error(msg); else alert(msg);
                $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Remove');
            }
        });
    }

    function wizSerializeItemUpdate($body) {
        var fields = [
            { name: '_token', value: '{{ csrf_token() }}' }
        ];
        $body.find(':input[data-item-field]').each(function () {
            fields.push({
                name: $(this).data('item-field'),
                value: $(this).val()
            });
        });
        return $.param(fields);
    }

    function wizSetItemPhoto($row, previewSrc, reference) {
        var $thumb = $row.find('.wiz-item-header-thumb');
        var $status = $row.find('.wiz-item-photo-status');
        var $path = $row.find('.wiz-item-photo-path');
        if (reference !== undefined) {
            $path.val(reference || '').trigger('input');
        }
        if (previewSrc) {
            $thumb.html('<img src="' + previewSrc + '" alt="">');
            $status.text('Photo selected');
        } else {
            $thumb.html('<i class="fa fa-image"></i>');
            $status.text('');
        }
    }

    function wizSetItemOcrStatus($row, message, isError) {
        $row.find('.wiz-item-photo-status').text(message || '').css('color', isError ? '#dc2626' : '#64748b');
    }

    function wizSetItemFieldIfPresent($row, field, value) {
        if (!value) return;
        var $field = $row.find('[data-item-field="' + field + '"]');
        if (!$field.length) return;
        $field.val(value).trigger('input').trigger('change');
    }

    function wizApplyProductOcrFields($row, fields, rawText) {
        fields = fields || {};
        wizSetItemFieldIfPresent($row, 'product_name_snapshot', fields.product_name);
        wizSetItemFieldIfPresent($row, 'color', fields.color);
        wizSetItemFieldIfPresent($row, 'storage', fields.storage);
        wizSetItemFieldIfPresent($row, 'serial_number_snapshot', fields.serial_number);
        wizSetItemFieldIfPresent($row, 'imei_snapshot', fields.imei);
        if (rawText) {
            wizSetItemFieldIfPresent($row, 'product_ocr_raw_text', rawText);
        }
    }

    function wizScanProductPhoto($row, dataUri) {
        wizSetItemOcrStatus($row, 'Reading product photo with AI Vision...');
        $.ajax({
            url: wizUrls.scanProductPhoto,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                product_image: dataUri
            },
            dataType: 'json',
            success: function(res) {
                if (res && res.success) {
                    var data = res.data || {};
                    var fields = data.fields || {};
                    wizApplyProductOcrFields($row, fields, data.raw_text || '');
                    var found = Object.keys(fields).filter(function(key) { return fields[key]; }).length;
                    wizSetItemOcrStatus($row, found ? 'Product fields filled automatically.' : 'OCR finished.', false);
                } else {
                    wizSetItemOcrStatus($row, (res && res.message) || 'Product OCR unavailable.', true);
                }
            },
            error: function(xhr) {
                wizSetItemOcrStatus($row, (xhr.responseJSON && xhr.responseJSON.message) || 'Product OCR failed.', true);
            }
        });
    }

    function wizApplyProductPhotoData($row, dataUri) {
        $row.find('.wiz-item-photo-data').val(dataUri).trigger('input');
        wizSetItemPhoto($row, dataUri);
        wizScanProductPhoto($row, dataUri);
    }

    function wizSetProductCropStatus(m, e) { var el = document.getElementById('wizProductCropStatus'); if (el) { el.style.color = e ? '#dc2626' : '#64748b'; el.textContent = m || ''; } }
    function wizStartProductCrop($row, file) {
        wizProductCropper = null; wizProductCropRow = $row; wizProductCropFile = file;
        $('#wizProductCropOverlay').css('display', 'flex').attr('aria-hidden', 'false');
        wizSetProductCropStatus('Preparing photo...');
        var reader = new FileReader();
        var img = new Image();
        reader.onload = function(e) {
            img.onload = function() {
                wizProductCropper = wizCreateCropper(document.getElementById('wizProductCropCanvas'), img);
                wizSetProductCropStatus('Drag to fit product label.');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    function wizCancelProductCrop() { wizProductCropper = null; wizProductCropRow = null; wizProductCropFile = null; $('#wizProductCropOverlay').hide().attr('aria-hidden', 'true'); }
    function wizUseOriginalProductPhoto() {
        if (!wizProductCropRow || !wizProductCropFile) { wizCancelProductCrop(); return; }
        var $row = wizProductCropRow; var file = wizProductCropFile;
        wizCancelProductCrop();
        wizCompressImage(file, 1400, 1400, 0.72).then(function(dataUri) { wizApplyProductPhotoData($row, dataUri); });
    }
    function wizUseCroppedProductPhoto() {
        if (!wizProductCropper || !wizProductCropRow) { wizUseOriginalProductPhoto(); return; }
        var $row = wizProductCropRow;
        wizSetProductCropStatus('Cropping...');
        wizProductCropper.getDataUrl(function(dataUri) {
            wizCancelProductCrop();
            wizApplyProductPhotoData($row, dataUri);
        });
    }

    // Customer photo crop & OCR
    function wizSetCustomerCropStatus(m, e) { var el = document.getElementById('wizCustomerCropStatus'); if (el) { el.style.color = e ? '#dc2626' : '#64748b'; el.textContent = m || ''; } }
    function wizStartCustomerCrop(target, file) {
        wizCustomerCropper = null; wizCustomerCropTarget = target; wizCustomerCropFile = file;
        $('#wizCustomerCropOverlay').css('display', 'flex').attr('aria-hidden', 'false');
        $('#wizCustomerCropTitle').html('<i class="fa fa-crop"></i> ' + (target === 'profile' ? 'Crop Profile Photo' : 'Crop National ID Card Photo'));
        wizSetCustomerCropStatus('Preparing photo...');
        var reader = new FileReader();
        var img = new Image();
        reader.onload = function(e) {
            img.onload = function() {
                wizCustomerCropper = wizCreateCropper(document.getElementById('wizCustomerCropCanvas'), img, target === 'profile' ? {x:0.18, y:0.08, width:0.64, height:0.84} : null);
                wizSetCustomerCropStatus('Drag to center image area.');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    function wizCancelCustomerCrop() { wizCustomerCropper = null; wizCustomerCropTarget = null; wizCustomerCropFile = null; $('#wizCustomerCropOverlay').hide().attr('aria-hidden', 'true'); }
    function wizUseOriginalCustomerPhoto() {
        if (!wizCustomerCropFile || !wizCustomerCropTarget) { wizCancelCustomerCrop(); return; }
        var target = wizCustomerCropTarget; var file = wizCustomerCropFile;
        wizCancelCustomerCrop();
        wizCompressImage(file, target === 'profile' ? 900 : 1600, target === 'profile' ? 900 : 1000, 0.78).then(function(dataUri) {
            wizApplyCustomerPhotoData(target, dataUri);
        });
    }
    function wizUseCroppedCustomerPhoto() {
        if (!wizCustomerCropper || !wizCustomerCropTarget) { wizUseOriginalCustomerPhoto(); return; }
        var target = wizCustomerCropTarget;
        wizSetCustomerCropStatus('Cropping...');
        wizCustomerCropper.getDataUrl(function(dataUri) {
            wizCancelCustomerCrop();
            wizApplyCustomerPhotoData(target, dataUri);
        });
    }
    function wizApplyCustomerPhotoData(target, dataUri) {
        if (target === 'profile') {
            $('#wizCustomerProfileImage').val(dataUri);
            $('#wizCustomerProfilePreview').html('<img src="' + dataUri + '" alt="Profile">');
        } else {
            $('#wizIdCardImage').val(dataUri);
            $('#wizIdCardPreview').html('<img src="' + dataUri + '" alt="ID Card">');
            wizScanIdCard(dataUri);
        }
    }

    function wizScanIdCard(dataUri) {
        $('#wizIdCardOcrStatus').text('Reading ID card with AI Vision...').css('color', '#2563eb');
        $.ajax({
            url: wizUrls.scanIdCard,
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', id_card_image: dataUri },
            dataType: 'json',
            success: function(res) {
                if (res && res.success) {
                    var fields = res.data?.fields || {};
                    if (fields.khmer_name) $('[name="customer_khmer_name"]').val(fields.khmer_name);
                    if (fields.english_name) $('[name="customer_english_name"]').val(fields.english_name);
                    if (fields.id_card_number) $('[name="id_card_number"]').val(fields.id_card_number);
                    wizSyncCustomerNameFromKhmer();
                    $('#wizIdCardOcrStatus').text('ID Card text extracted automatically.').css('color', '#10b981');
                } else {
                    $('#wizIdCardOcrStatus').text((res && res.message) || 'OCR unavailable.').css('color', '#dc2626');
                }
            },
            error: function(xhr) {
                $('#wizIdCardOcrStatus').text((xhr.responseJSON && xhr.responseJSON.message) || 'OCR failed.').css('color', '#dc2626');
            }
        });
    }

    function wizCompressImage(file, maxW, maxH, quality) {
        return new Promise(function(resolve) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = new Image();
                img.onload = function() {
                    var w = img.width, h = img.height;
                    if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                    if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }
                    var canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function wizCreateCropper(canvas, image, initialCrop) {
        var context = canvas.getContext('2d');
        var maxWidth = Math.min(760, image.width);
        var scale = maxWidth / image.width;
        var canvasWidth = Math.round(image.width * scale);
        var canvasHeight = Math.round(image.height * scale);
        var dragMode = null; var lastPoint = null; var handleSize = 16; var crop = {};
        canvas.width = canvasWidth; canvas.height = canvasHeight;

        function reset() {
            var preset = initialCrop || {x: 0.08, y: 0.12, width: 0.84, height: 0.72};
            crop = {
                x: Math.round(canvasWidth * preset.x),
                y: Math.round(canvasHeight * preset.y),
                width: Math.round(canvasWidth * preset.width),
                height: Math.round(canvasHeight * preset.height)
            };
            constrainCrop(); draw();
        }
        function drawHandle(x, y) { context.fillStyle = '#2563eb'; context.fillRect(x - handleSize / 2, y - handleSize / 2, handleSize, handleSize); }
        function draw() {
            context.clearRect(0, 0, canvasWidth, canvasHeight);
            context.drawImage(image, 0, 0, canvasWidth, canvasHeight);
            context.fillStyle = 'rgba(15, 23, 42, 0.45)';
            context.fillRect(0, 0, canvasWidth, canvasHeight);
            context.drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, crop.x, crop.y, crop.width, crop.height);
            context.strokeStyle = '#2563eb'; context.lineWidth = 3;
            context.strokeRect(crop.x, crop.y, crop.width, crop.height);
            drawHandle(crop.x, crop.y); drawHandle(crop.x + crop.width, crop.y);
            drawHandle(crop.x, crop.y + crop.height); drawHandle(crop.x + crop.width, crop.y + crop.height);
        }
        function getPoint(e) {
            var s = e.touches && e.touches.length ? e.touches[0] : e;
            var r = canvas.getBoundingClientRect();
            return { x: (s.clientX - r.left) * (canvas.width / r.width), y: (s.clientY - r.top) * (canvas.height / r.height) };
        }
        function getDragMode(p) {
            var h = { nw: {x: crop.x, y: crop.y}, ne: {x: crop.x + crop.width, y: crop.y}, sw: {x: crop.x, y: crop.y + crop.height}, se: {x: crop.x + crop.width, y: crop.y + crop.height} };
            for (var m in h) { if (Math.abs(p.x - h[m].x) <= handleSize && Math.abs(p.y - h[m].y) <= handleSize) return m; }
            if (p.x >= crop.x && p.x <= crop.x + crop.width && p.y >= crop.y && p.y <= crop.y + crop.height) return 'move';
            return null;
        }
        function constrainCrop() {
            var minSize = 40;
            crop.width = Math.max(minSize, crop.width); crop.height = Math.max(minSize, crop.height);
            crop.x = Math.max(0, Math.min(crop.x, canvasWidth - crop.width)); crop.y = Math.max(0, Math.min(crop.y, canvasHeight - crop.height));
            if (crop.x + crop.width > canvasWidth) crop.width = canvasWidth - crop.x;
            if (crop.y + crop.height > canvasHeight) crop.height = canvasHeight - crop.y;
        }
        function resizeCrop(mode, dx, dy) {
            if (mode.indexOf('n') !== -1) { crop.y += dy; crop.height -= dy; }
            if (mode.indexOf('s') !== -1) { crop.height += dy; }
            if (mode.indexOf('w') !== -1) { crop.x += dx; crop.width -= dx; }
            if (mode.indexOf('e') !== -1) { crop.width += dx; }
        }
        function startDrag(e) { var p = getPoint(e); dragMode = getDragMode(p); lastPoint = p; if (dragMode) e.preventDefault(); }
        function moveDrag(e) {
            if (!dragMode) return;
            var p = getPoint(e);
            var dx = p.x - lastPoint.x, dy = p.y - lastPoint.y;
            if (dragMode === 'move') { crop.x += dx; crop.y += dy; } else { resizeCrop(dragMode, dx, dy); }
            constrainCrop(); lastPoint = p; draw(); e.preventDefault();
        }
        function endDrag() { dragMode = null; lastPoint = null; }
        canvas.onmousedown = startDrag; canvas.onmousemove = moveDrag; canvas.onmouseup = endDrag; canvas.onmouseleave = endDrag;
        canvas.ontouchstart = startDrag; canvas.ontouchmove = moveDrag; canvas.ontouchend = endDrag;
        reset();

        return {
            reset: reset,
            getDataUrl: function(callback) {
                var cw = Math.round(crop.width / scale), ch = Math.round(crop.height / scale);
                var maxOut = 1600; var outScale = Math.min(1, maxOut / Math.max(cw, ch));
                var out = document.createElement('canvas');
                out.width = Math.max(1, Math.round(cw * outScale)); out.height = Math.max(1, Math.round(ch * outScale));
                out.getContext('2d').drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, 0, 0, out.width, out.height);
                callback(out.toDataURL('image/jpeg', 0.88));
            }
        };
    }

    // Documents
    function wizQueueDocumentFile(file) {
        if (!file) return;
        wizDocCounter++;
        var index = wizDocCounter;
        var $grid = $('#wizDocGrid');
        var $addTile = $('#wizDocAddTile');
        var $tile = $('<div class="wiz-doc-tile" data-doc-index="' + index + '"><div style="color:#2563eb;"><i class="fa fa-spinner fa-spin"></i></div></div>');
        $tile.insertBefore($addTile);

        if (file.type && file.type.indexOf('image/') === 0) {
            wizCompressImage(file, 1200, 800, 0.65).then(function(dataUri) {
                $('#wizDocHiddenFields').append('<input type="hidden" name="documents[]" data-doc-index="' + index + '" value="' + dataUri + '">');
                $tile.html('<img src="' + dataUri + '"><button type="button" class="wiz-doc-remove"><i class="fa fa-times"></i></button>');
            });
        } else {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#wizDocHiddenFields').append('<input type="hidden" name="documents[]" data-doc-index="' + index + '" value="' + e.target.result + '">');
                $tile.html('<i class="fa fa-file-o"></i><span style="font-size:9px;padding:0 2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + file.name + '</span><button type="button" class="wiz-doc-remove"><i class="fa fa-times"></i></button>');
            };
            reader.readAsDataURL(file);
        }
    }

    // Address Cascading
    function wizSetAddressName($select, targetSelector) {
        var text = $select.find('option:selected').data('kh') || $select.find('option:selected').text();
        if ($select.val()) $(targetSelector).val(text); else $(targetSelector).val('');
    }
    function wizResetAddressAfter(level) {
        if (level < 2) { $('#wizDistrictSelect').empty().append('<option value="">-- Select --</option>').prop('disabled', true); $('#wizDistrictName').val(''); }
        if (level < 3) { $('#wizCommuneSelect').empty().append('<option value="">-- Select --</option>').prop('disabled', true); $('#wizCommuneName').val(''); }
        if (level < 4) { $('#wizVillageSelect').empty().append('<option value="">-- Select --</option>').prop('disabled', true); $('#wizVillageName').val(''); }
    }
    function wizLoadAddressSelect(url, data, $select, selectedCode, callback) {
        $select.prop('disabled', true);
        $.get(url, data, function(res) {
            $select.empty().append('<option value="">-- Select --</option>');
            (res.items || []).forEach(function(item) {
                $select.append($('<option>', { value: item.code, text: item.label || item.kh || item.en }).attr({'data-kh': item.kh||'', 'data-en': item.en||''}));
            });
            $select.prop('disabled', (res.items || []).length === 0);
            if (selectedCode) $select.val(selectedCode);
            if (typeof callback === 'function') callback();
        });
    }
    function wizInitAddressSelects() {
        var prov = $('#wizProvinceSelect').data('current-code');
        var dist = $('#wizDistrictSelect').data('current-code');
        var comm = $('#wizCommuneSelect').data('current-code');
        var vill = $('#wizVillageSelect').data('current-code');

        wizLoadAddressSelect(wizUrls.provinces, {}, $('#wizProvinceSelect'), prov, function() {
            if (prov) {
                wizLoadAddressSelect(wizUrls.districts, { province_code: prov }, $('#wizDistrictSelect'), dist, function() {
                    if (dist) {
                        wizLoadAddressSelect(wizUrls.communes, { district_code: dist }, $('#wizCommuneSelect'), comm, function() {
                            if (comm) {
                                wizLoadAddressSelect(wizUrls.villages, { commune_code: comm }, $('#wizVillageSelect'), vill);
                            }
                        });
                    }
                });
            }
        });
    }

    // Event listeners
    $(document).on('click', '.wiz-photo-choice-btn', function () {
        wizShowPhotoChoice($(this).data('camera'), $(this).data('upload'));
    });
    $('#wizPhotoChoiceCamera').on('click', function () { wizChoosePhotoSource('camera'); });
    $('#wizPhotoChoiceUpload').on('click', function () { wizChoosePhotoSource('upload'); });
    $('#wizPhotoChoiceCancel').on('click', wizHidePhotoChoice);

    $(document).on('click', '.wiz-product-photo-choice-btn', function () {
        var $row = $(this).closest('.wiz-item-row');
        wizShowPhotoChoice($row.find('.wiz-item-photo-camera'), $row.find('.wiz-item-photo-upload'));
    });

    $(document).on('input change', '#wizItemsList input, [name="principal_amount"], [name="down_payment"], [name="interest_rate"], [name="installment_count"]', function () {
        wizEditRecalcTotals();
    });

    $(document).on('change', '.wiz-item-photo-input', function () {
        var file = this.files && this.files[0];
        var $row = $(this).closest('.wiz-item-row');
        if (!file) return;
        wizStartProductCrop($row, file);
        this.value = '';
    });

    $('#wizProductCropClose').on('click', wizCancelProductCrop);
    $('#wizProductCropOriginal').on('click', wizUseOriginalProductPhoto);
    $('#wizProductCropUse').on('click', wizUseCroppedProductPhoto);
    $('#wizProductCropReset').on('click', function () { if (wizProductCropper) wizProductCropper.reset(); });

    $('#wizCustomerProfileCamera, #wizCustomerProfileUpload').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        wizStartCustomerCrop('profile', file);
        this.value = '';
    });

    $('#wizIdCardCamera, #wizIdCardUpload').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        wizStartCustomerCrop('id_card', file);
        this.value = '';
    });

    $('#wizCustomerCropClose').on('click', wizCancelCustomerCrop);
    $('#wizCustomerCropOriginal').on('click', wizUseOriginalCustomerPhoto);
    $('#wizCustomerCropUse').on('click', wizUseCroppedCustomerPhoto);
    $('#wizCustomerCropReset').on('click', function () { if (wizCustomerCropper) wizCustomerCropper.reset(); });

    $('#wizDocInput').on('change', function () {
        var files = Array.prototype.slice.call(this.files || []);
        files.forEach(wizQueueDocumentFile);
        this.value = '';
    });

    $(document).on('click', '.wiz-doc-remove', function () {
        var $tile = $(this).closest('.wiz-doc-tile');
        var index = $tile.data('doc-index');
        $('#wizDocHiddenFields').find('[data-doc-index="' + index + '"]').remove();
        $tile.remove();
    });

    $('#wizBtnAddDocumentLink').on('click', function () {
        $('#wizDocumentLinks').append(
            '<div class="wiz-doc-link-row" style="display:flex;gap:6px;margin-bottom:6px;">' +
                '<input type="url" name="document_links[]" class="lm-control" placeholder="Paste document link">' +
                '<button type="button" class="btn btn-default btn-sm wiz-doc-link-remove" title="Remove link" style="border-radius:8px;"><i class="fa fa-times"></i></button>' +
            '</div>'
        );
    });
    $(document).on('click', '.wiz-doc-link-remove', function () { $(this).closest('.wiz-doc-link-row').remove(); });

    document.addEventListener('paste', function (event) {
        if (!$(event.target).closest('.lm-pro-edit-wrap').length) return;
        var items = event.clipboardData && event.clipboardData.items ? Array.prototype.slice.call(event.clipboardData.items) : [];
        items.forEach(function (item) {
            if (item.kind === 'file' && item.type && item.type.indexOf('image/') === 0) {
                wizQueueDocumentFile(item.getAsFile());
            }
        });
    });

    $('#wizProvinceSelect').on('change', function () {
        wizSetAddressName($(this), '#wizProvinceName');
        wizResetAddressAfter(1);
        if (this.value) wizLoadAddressSelect(wizUrls.districts, { province_code: this.value }, $('#wizDistrictSelect'));
    });
    $('#wizDistrictSelect').on('change', function () {
        wizSetAddressName($(this), '#wizDistrictName');
        wizResetAddressAfter(2);
        if (this.value) wizLoadAddressSelect(wizUrls.communes, { district_code: this.value }, $('#wizCommuneSelect'));
    });
    $('#wizCommuneSelect').on('change', function () {
        wizSetAddressName($(this), '#wizCommuneName');
        wizResetAddressAfter(3);
        if (this.value) wizLoadAddressSelect(wizUrls.villages, { commune_code: this.value }, $('#wizVillageSelect'));
    });
    $('#wizVillageSelect').on('change', function () { wizSetAddressName($(this), '#wizVillageName'); });

    function wizSyncDuration() {
        var ic = parseInt($('[name="installment_count"]').val()) || 0;
        $('#wizDurationMonths').val(ic);
    }
    $('[name="installment_count"]').on('input change', wizSyncDuration);
    wizSyncDuration();

    $('#wizBtnAddItem').on('click', function () {
        var key = 'new_' + Date.now();
        $('#wizItemsEmpty').remove();
        var newItemHtml =
            '<div class="wiz-item-row open" data-id="' + key + '" data-loan-id="{{ $loanRow->id }}">' +
                '<div class="wiz-item-header" onclick="$(this).closest(\'.wiz-item-row\').toggleClass(\'open\').find(\'.wiz-item-body\').slideToggle(200);">' +
                    '<div class="wiz-item-header-left">' +
                        '<span class="wiz-item-header-thumb"><i class="fa fa-image"></i></span>' +
                        '<div class="wiz-item-header-main"><strong>{{ $lmText('New Product', 'ទំនិញថ្មី') }}</strong><small>SKU: - &middot; IMEI: - &middot; {{ $lmText('Qty: 1', 'ចំនួន: 1') }}</small></div>' +
                    '</div>' +
                    '<div style="display:flex;align-items:center;gap:8px;"><span class="wiz-item-line-total">$0.00</span><i class="fa fa-chevron-down" style="font-size:11px;color:#94a3b8;"></i></div>' +
                '</div>' +
                '<div class="wiz-item-body">' +
                    '<div class="wiz-item-form-grid">' +
                        '<div class="wiz-item-field" style="grid-column:1/-1;"><label>{{ $lmText('Product Name', 'ឈ្មោះទំនិញ') }}</label><input type="text" name="edit_items[' + key + '][product_name_snapshot]" data-item-field="product_name_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>SKU</label><input type="text" name="edit_items[' + key + '][sku_snapshot]" data-item-field="sku_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>IMEI</label><input type="text" name="edit_items[' + key + '][imei_snapshot]" data-item-field="imei_snapshot" class="lm-wiz-input wiz-item-imei" value=""></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Serial #', 'លេខស៊េរី') }}</label><input type="text" name="edit_items[' + key + '][serial_number_snapshot]" data-item-field="serial_number_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Qty', 'ចំនួន') }}</label><input type="number" name="edit_items[' + key + '][qty]" data-item-field="qty" class="lm-wiz-input" value="1" min="1"></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Price ($)', 'តម្លៃ ($)') }}</label><input type="number" step="0.01" name="edit_items[' + key + '][unit_price]" data-item-field="unit_price" class="lm-wiz-input" value="0" min="0"></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Discount ($)', 'បញ្ចុះតម្លៃ ($)') }}</label><input type="number" step="0.01" name="edit_items[' + key + '][discount]" data-item-field="discount" class="lm-wiz-input" value="0" min="0"></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Color', 'ពណ៌') }}</label><input type="text" name="edit_items[' + key + '][color]" data-item-field="color" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Storage', 'ទំហំផ្ទុក') }}</label><input type="text" name="edit_items[' + key + '][storage]" data-item-field="storage" class="lm-wiz-input" value="" placeholder="128GB"></div>' +
                        '<div class="wiz-item-field"><label>{{ $lmText('Brand', 'ម៉ាក') }}</label><input type="text" name="edit_items[' + key + '][brand]" data-item-field="brand" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field" style="grid-column:1/-1;">' +
                            '<label>{{ $lmText('Product Photo OCR / Scan', 'រូបថតទំនិញ & OCR') }}</label>' +
                            '<div style="display:flex;gap:8px;align-items:center;">' +
                                '<button type="button" class="btn btn-default btn-sm wiz-item-photo-action wiz-product-photo-choice-btn" style="border-radius:6px;"><i class="fa fa-camera"></i> {{ $lmText('Photo / OCR', 'រូបថត / OCR') }}</button>' +
                                '<input type="file" accept="image/*" capture="environment" class="wiz-item-photo-input wiz-item-photo-camera" style="display:none;">' +
                                '<input type="file" accept="image/*" class="wiz-item-photo-input wiz-item-photo-upload" style="display:none;">' +
                                '<span class="wiz-item-photo-status" style="font-size:11px;color:#64748b;"></span>' +
                            '</div>' +
                            '<input type="hidden" name="edit_items[' + key + '][product_photo]" data-item-field="product_photo" class="wiz-item-photo-data" value="">' +
                            '<input type="text" name="edit_items[' + key + '][product_photo_path]" data-item-field="product_photo_path" class="lm-wiz-input wiz-item-photo-path" value="" placeholder="{{ $lmText('Photo URL or path', 'តំណភ្ជាប់រូបថត') }}" style="margin-top:6px;">' +
                        '</div>' +
                    '</div>' +
                    '<div class="wiz-item-form-actions">' +
                        '<button type="button" class="btn btn-sm btn-danger wiz-new-item-remove" style="border-radius:6px;"><i class="fa fa-trash"></i> {{ $lmText('Remove', 'លុបចេញ') }}</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        var $newItem = $(newItemHtml);
        $('#wizItemsList').append($newItem);
        wizEditRecalcTotals();
    });

    $(document).on('click', '.wiz-new-item-remove', function () {
        $(this).closest('.wiz-item-row').remove();
        if ($('#wizItemsList .wiz-item-row').length === 0) {
            $('#wizItemsList').html('<div style="text-align:center; padding:20px; color:#94a3b8;" id="wizItemsEmpty">{{ $lmText('No collateral products attached. Click "Add Item" to add products.', 'មិនទាន់មានទំនិញនៅឡើយទេ។ ចុច "បន្ថែមទំនិញ" ដើម្បីបញ្ចូល។') }}</div>');
        }
        wizEditRecalcTotals();
    });

    $(document).on('click', '.wiz-item-update-btn', function () {
        var $btn = $(this);
        var $body = $btn.closest('.wiz-item-body');
        var updateUrl = $body.data('update-url');
        if (!updateUrl) return;
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ $lmText('Updating...', 'កំពុងកែប្រែ...') }}');
        $.ajax({
            url: updateUrl,
            method: 'POST',
            data: wizSerializeItemUpdate($body),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) toastr.success(res.message || '{{ $lmText('Item updated.', 'ទំនិញត្រូវបានកែប្រែ។') }}');
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || '{{ $lmText('Failed to update item.', 'មិនអាចកែប្រែទំនិញបានទេ។') }}';
                if (window.toastr) toastr.error(msg); else alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> {{ $lmText('Update Item', 'កែប្រែទំនិញ') }}');
            }
        });
    });

    $(document).on('click', '.wiz-item-remove-btn', function () {
        if (!confirm('{{ $lmText('Delete this item? This will update loan totals.', 'តើអ្នកពិតជាចង់លុបទំនិញនេះមែនទេ? ការលុបនេះនឹងគណនាតម្លៃសរុបឡើងវិញ។') }}')) return;
        var $btn = $(this);
        var url = $btn.data('url');
        var $row = $btn.closest('.wiz-item-row');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: url,
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', return_to: window.location.href },
            dataType: 'json',
            success: function (res) {
                wizRemoveItemFromScreen($row);
                if (window.toastr) toastr.success(res.message || '{{ $lmText('Item removed.', 'ទំនិញត្រូវបានលុប។') }}');
            },
            error: function (xhr) {
                wizDeleteItemViaLoanSave($row, $btn);
            }
        });
    });

    $('#wizBtnPreviewSchedule, #wizBtnRefreshSchedule').on('click', function () {
        wizAutoGeneratePrincipalAfterDeposit();
        wizSyncDuration();
        wizEditRecalcTotals();
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ $lmText('Calculating...', 'កំពុងគណនា...') }}');
        $.post(wizUrls.previewSchedule, wizSerializeLoanForm(), function (res) {
            var rows = res.data || [];
            var $tb = $('#wizScheduleTable tbody');
            var tP = 0, tI = 0, tA = 0, tB = 0;
            $tb.empty();
            rows.forEach(function (r) {
                tP += Number(r.principal || 0);
                tI += Number(r.interest || 0);
                tA += Number(r.total || 0);
                tB += Number(r.balance || 0);
                $tb.append('<tr><td>' + (r.schedule_no || '') + '</td><td>' + (r.due_date || '') + '</td><td class="text-right">$' + wizFormatMoney(r.principal) + '</td><td class="text-right">$' + wizFormatMoney(r.interest) + '</td><td class="text-right">$' + wizFormatMoney(r.total) + '</td><td class="text-right">$' + wizFormatMoney(r.balance) + '</td></tr>');
            });
            if (!rows.length) {
                $tb.append('<tr><td colspan="6" class="text-center text-muted">{{ $lmText('No schedule rows generated.', 'គ្មានទិន្នន័យកាលវិភាគត្រូវបានបង្កើត។') }}</td></tr>');
            }
            $('#wizScheduleTable tfoot th').eq(1).text('$' + wizFormatMoney(tP));
            $('#wizScheduleTable tfoot th').eq(2).text('$' + wizFormatMoney(tI));
            $('#wizScheduleTable tfoot th').eq(3).text('$' + wizFormatMoney(tA));
            $('#wizScheduleTable tfoot th').eq(4).text('$' + wizFormatMoney(tB));
            $('#wizScheduleSection').show();

            var ic = parseInt($('[name="installment_count"]').val()) || 1;
            $('#wizSummaryMonthly').text('$' + wizFormatMoney(tA / ic));
            if (tI > 0) $('[name="interest_amount"]').val(wizFormatMoney(tI));
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || '{{ $lmText('Failed to preview schedule', 'មិនអាចគណនាកាលវិភាគបានទេ។') }}';
            if (window.toastr) toastr.error(msg); else alert(msg);
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-table"></i> {{ $lmText('Preview Schedule', 'គណនាកាលវិភាគ') }}');
        });
    });

    $('#wizBtnSubmit').on('click', function () {
        wizSyncCustomerNameFromKhmer();
        wizSyncDuration();
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ $lmText('Saving...', 'កំពុងរក្សាទុក...') }}');
        $.ajax({
            url: wizUrls.updateAction,
            method: 'POST',
            data: wizSerializeLoanForm() + '&_method=PUT',
            dataType: 'json',
            headers: { Accept: 'application/json' },
            success: function (res) {
                if (window.toastr) toastr.success(res.message || '{{ $lmText('Installment updated successfully.', 'កម្ចីត្រូវបានកែប្រែដោយជោគជ័យ។') }}');
                wizEditRecalcTotals();
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            },
            error: function (xhr) {
                var msg = '{{ $lmText('Failed to save installment.', 'មិនអាចរក្សាទុកកម្ចីបានទេ។') }}';
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = errors[Object.keys(errors)[0]][0] || msg;
                } else if (xhr.responseJSON?.message) {
                    msg = xhr.responseJSON.message;
                }
                if (window.toastr) toastr.error(msg); else alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> {{ $lmText('Save Changes & Update', 'រក្សាទុក & កែប្រែកម្ចី') }}');
            }
        });
    });

    $(document).on('click', '.wiz-deposit-remove', function () {
        if (!confirm('{{ $lmText('Delete this deposit payment? This will update loan totals.', 'តើអ្នកពិតជាចង់លុបប្រាក់កក់នេះមែនទេ? ការលុបនេះនឹងគណនាតម្លៃសរុបឡើងវិញ។') }}')) return;
        var $btn = $(this);
        var paymentId = $btn.data('payment-id');
        var returnTo = $btn.data('return-to');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: wizUrls.paymentUpdateBase + '/' + paymentId,
            method: 'POST',
            data: { _method: 'DELETE', _token: '{{ csrf_token() }}', return_to: returnTo },
            dataType: 'json',
            success: function (res) {
                if (window.toastr) toastr.success(res.message || '{{ $lmText('Deposit removed.', 'ប្រាក់កក់ត្រូវបានលុប។') }}');
                setTimeout(function () { window.location.reload(); }, 800);
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || '{{ $lmText('Failed to remove deposit.', 'មិនអាចលុបប្រាក់កក់បានទេ។') }}';
                if (window.toastr) toastr.error(msg); else alert(msg);
                $btn.prop('disabled', false).html('{{ $lmText('Remove', 'លុបចេញ') }}');
            }
        });
    });

    $(document).on('click', '.wiz-deposit-update-btn', function () {
        var $btn = $(this);
        var $form = $btn.closest('.wiz-deposit-edit-form');
        var $row = $form.closest('.wiz-deposit-row');
        var paymentId = $row.data('id');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        var payload = $form.find(':input').serializeArray();
        payload.push({ name: '_token', value: '{{ csrf_token() }}' });
        payload.push({ name: '_method', value: 'PUT' });
        $.ajax({
            url: wizUrls.paymentUpdateBase + '/' + paymentId,
            method: 'POST',
            data: $.param(payload),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) toastr.success(res.message || '{{ $lmText('Deposit updated.', 'ប្រាក់កក់ត្រូវបានកែប្រែ។') }}');
                setTimeout(function () { window.location.reload(); }, 800);
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || '{{ $lmText('Failed to update deposit.', 'មិនអាចកែប្រែប្រាក់កក់បានទេ។') }}';
                if (window.toastr) toastr.error(msg); else alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('{{ $lmText('Update Deposit', 'កែប្រែប្រាក់កក់') }}');
            }
        });
    });

    $(function () {
        wizEditRecalcTotals();
        wizInitAddressSelects();
    });
})(jQuery);
</script>
@endsection
