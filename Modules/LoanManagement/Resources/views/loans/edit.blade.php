@extends('loanmanagement::layouts.app')
@section('title', 'Edit Loan')

@section('loan_css')
@php $isEmbeddedModal = request()->boolean('_lm_modal'); @endphp
@if($isEmbeddedModal)
<style>
    html,
    body.loan-management-embedded-modal {
        min-height: 100% !important;
        overflow: auto !important;
        background: #f5f5f5 !important;
    }
    body.loan-management-embedded-modal .thetop,
    body.loan-management-embedded-modal #scrollable-container,
    body.loan-management-embedded-modal #loanManagementApp,
    body.loan-management-embedded-modal #loanManagementMain,
    body.loan-management-embedded-modal .lm-content,
    body.loan-management-embedded-modal .lm-workspace {
        width: 100% !important;
        min-height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }
    body.loan-management-embedded-modal #main_app_header,
    body.loan-management-embedded-modal #app,
    #loanManagementSidebar,
    #loanManagementHeader,
    .lm-breadcrumb-wrap,
    .lm-footer {
        display: none !important;
    }
    #loanManagementMain {
        margin-left: 0 !important;
        width: 100% !important;
    }
    #loanManagementMain .lm-content {
        padding-top: 0 !important;
    }
    #loanManagementMain .lm-workspace {
        padding: 0 !important;
    }
    .content-header {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .content {
        min-height: 100% !important;
    }
    .lm-edit-wizard {
        min-height: 100% !important;
    }
    .lm-edit-wizard #wizEditForm {
        min-height: 100% !important;
    }
    .lm-edit-wizard .lm-wiz-steps-wrap {
        min-height: 0 !important;
        overflow: visible !important;
    }
</style>
@endif
<style>
*, *::before, *::after { box-sizing: border-box; }

.lm-edit-wizard { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }

.lm-edit-wizard .content-header { margin-bottom: 0 !important; }
.lm-edit-wizard .lm-wiz-topbar {
    display: flex; align-items: center; padding: 10px 16px; background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; position: relative; z-index: 5; min-height: 52px;
}
.lm-edit-wizard .lm-wiz-topbar .lm-wiz-title { flex: 1; text-align: center; font-size: 16px; font-weight: 700; }

.lm-edit-wizard .lm-wiz-progress {
    display: flex; padding: 12px 20px; background: #fff; border-bottom: 1px solid #f0f0f0;
}
.lm-edit-wizard .lm-wiz-progress .lm-wiz-step {
    flex: 1; height: 4px; border-radius: 2px; background: #e5e7eb; margin: 0 2px;
    position: relative; transition: background .3s;
}
.lm-edit-wizard .lm-wiz-progress .lm-wiz-step.active { background: #2563eb; }
.lm-edit-wizard .lm-wiz-progress .lm-wiz-step.done { background: #22c55e; }
.lm-edit-wizard .lm-wiz-step-dot {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 2px solid #d1d5db;
    display: none; font-size: 8px; color: #94a3b8; align-items: center; justify-content: center;
}
.lm-edit-wizard .lm-wiz-step.active .lm-wiz-step-dot { display: flex; border-color: #2563eb; color: #2563eb; }
.lm-edit-wizard .lm-wiz-step.done .lm-wiz-step-dot { display: flex; border-color: #22c55e; background: #22c55e; color: #fff; }

.lm-edit-wizard .lm-wiz-step-labels {
    display: flex; padding: 0 20px 10px; background: #fff;
}
.lm-edit-wizard .lm-wiz-progress .lm-wiz-step { cursor: pointer; }
.lm-edit-wizard .lm-wiz-step-labels span { cursor: pointer; }
.lm-edit-wizard .lm-wiz-step-labels span {
    flex: 1; text-align: center; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .2px;
}
.lm-edit-wizard .lm-wiz-step-labels span.active { color: #2563eb; }
.lm-edit-wizard .lm-wiz-step-labels span.done { color: #22c55e; }

.lm-edit-wizard .lm-wiz-steps-wrap { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; position: relative; min-height: 400px; }
.lm-edit-wizard .lm-wiz-panel {
    display: none; padding: 16px; animation: wizSlideIn .25s ease-out;
}
.lm-edit-wizard .lm-wiz-panel.active { display: block; }
@keyframes wizSlideIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.lm-edit-wizard .lm-wiz-section-title {
    font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.lm-edit-wizard .lm-wiz-section-title i { font-size: 12px; }

.lm-edit-wizard .lm-wiz-card {
    background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 12px;
    border: 1px solid #e8edf5; box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
}
.lm-edit-wizard .lm-wiz-panel[data-panel="1"].active {
    display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(340px, .85fr); gap: 16px; align-items: start;
}
.lm-edit-wizard .lm-wiz-panel[data-panel="1"] .lm-wiz-card { margin-bottom: 0; }
.lm-edit-wizard .lm-wiz-field { margin-bottom: 14px; }
.lm-edit-wizard .lm-wiz-field:last-child { margin-bottom: 0; }
.lm-edit-wizard .lm-wiz-field label {
    display: block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;
    text-transform: uppercase; letter-spacing: .2px;
}
.lm-edit-wizard .lm-wiz-field .lm-wiz-required { color: #ef4444; }
.lm-edit-wizard .lm-wiz-input {
    width: 100%; height: 40px; padding: 0 12px; border: 1px solid #e1e7ef; border-radius: 9px;
    font-size: 14px; background: #fbfcfe; color: #1f2937; transition: all .15s;
    -webkit-appearance: none; appearance: none;
}
.lm-edit-wizard .lm-wiz-input:focus { outline: none; border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
.lm-edit-wizard .lm-wiz-input::placeholder { color: #c4c4c4; }
.lm-edit-wizard select.lm-wiz-input { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%239ca3af' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
.lm-edit-wizard .lm-wiz-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.lm-edit-wizard .lm-wiz-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.lm-edit-wizard .lm-wiz-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.lm-edit-wizard .lm-wiz-muted { color: #64748b; font-size: 12px; line-height: 1.45; }
.lm-edit-wizard .lm-wiz-field textarea.lm-wiz-input { height: auto; padding: 10px 12px; resize: vertical; }
.lm-edit-wizard .lm-wiz-readonly {
    min-height: 40px; display: flex; align-items: center; width: 100%; padding: 8px 12px;
    border: 1px solid #e1e7ef; border-radius: 8px; background: #f8fafc; color: #1f2937; font-size: 14px; font-weight: 600;
}
.lm-edit-wizard .lm-wiz-stat-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
.lm-edit-wizard .lm-wiz-stat {
    padding: 10px 12px; background: #f8fafc; border: 1px solid #e5eaf2; border-radius: 8px;
}
.lm-edit-wizard .lm-wiz-stat small { display: block; color: #64748b; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.lm-edit-wizard .lm-wiz-stat strong { display: block; margin-top: 3px; color: #0f172a; font-size: 15px; }
.lm-edit-wizard .lm-wiz-subtitle {
    margin: -5px 0 12px; color: #64748b; font-size: 12px; line-height: 1.45;
}

.lm-edit-wizard .lm-wiz-bottombar {
    display: flex; gap: 8px; padding: 10px 16px; background: #fff; border-top: 1px solid #e5e7eb;
    position: relative; z-index: 5;
}
.lm-edit-wizard .lm-wiz-bottombar button {
    flex: 1; height: 48px; border-radius: 12px; font-size: 14px; font-weight: 700;
    border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: opacity .15s; -webkit-tap-highlight-color: transparent;
}
.lm-edit-wizard .lm-wiz-bottombar button:active { opacity: .85; }
.lm-edit-wizard .lm-wiz-btn-primary { background: #2563eb; color: #fff; }
.lm-edit-wizard .lm-wiz-btn-success { background: #22c55e; color: #fff; }
.lm-edit-wizard .lm-wiz-btn-ghost { background: #f1f5f9; color: #475569; }
.lm-edit-wizard .lm-wiz-btn-back { background: #f1f5f9; color: #475569; flex: 0 0 auto; width: 48px; }
.lm-edit-wizard .lm-wiz-btn-next { flex: 2; }
.lm-edit-wizard .lm-wiz-btn-submit { background: #22c55e; color: #fff; flex: 2; }

.lm-edit-wizard .lm-wiz-summary {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
    padding: 12px 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-top: 12px;
}
.lm-edit-wizard .lm-wiz-summary-item { text-align: center; }
.lm-edit-wizard .lm-wiz-summary-item .lm-wiz-s-label { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .3px; }
.lm-edit-wizard .lm-wiz-summary-item .lm-wiz-s-value { font-size: 15px; font-weight: 800; color: #1f2937; margin-top: 2px; }
.lm-edit-wizard .lm-wiz-summary-item .lm-wiz-s-value.green { color: #16a34a; }
.lm-edit-wizard .lm-wiz-summary-item .lm-wiz-s-value.blue { color: #2563eb; }

.lm-edit-wizard .lm-wiz-review-row {
    display: flex; justify-content: space-between; align-items: center; padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}
.lm-edit-wizard .lm-wiz-review-row:last-child { border-bottom: 0; }
.lm-edit-wizard .lm-wiz-review-label { font-size: 13px; color: #6b7280; }
.lm-edit-wizard .lm-wiz-review-value { font-size: 14px; font-weight: 600; color: #1f2937; text-align: right; max-width: 60%; }
.lm-edit-wizard .lm-wiz-review-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0 18px; }
.lm-edit-wizard .lm-wiz-payment-list { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.lm-edit-wizard .lm-wiz-payment-row {
    display: grid; grid-template-columns: 1.2fr .8fr .8fr .7fr; gap: 10px; align-items: center;
    padding: 10px 12px; border-bottom: 1px solid #eef2f7; font-size: 13px;
}
.lm-edit-wizard .lm-wiz-payment-row:last-child { border-bottom: 0; }
.lm-edit-wizard .lm-wiz-payment-row small { display: block; color: #94a3b8; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.lm-edit-wizard .lm-wiz-payment-row strong { color: #0f172a; }

.lm-edit-wizard .lm-wiz-schedule-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 10px; border: 1px solid #e5e7eb; }
.lm-edit-wizard .lm-wiz-schedule-tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 380px; }
.lm-edit-wizard .lm-wiz-schedule-tbl th { background: #f9fafb; padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
.lm-edit-wizard .lm-wiz-schedule-tbl td { padding: 8px 10px; border-bottom: 1px solid #f5f5f5; }
.lm-edit-wizard .lm-wiz-schedule-tbl tfoot th { background: #eff6ff; border-top: 2px solid #2563eb; font-size: 12px; }

.lm-edit-wizard .lm-wiz-items-table th { background: #f8fafc; font-weight: 600; font-size: 13px; }
.lm-edit-wizard .lm-wiz-items-table td { vertical-align: middle; }
.lm-edit-wizard .lm-wiz-items-table input { font-size: 13px; }

.lm-edit-wizard .lm-wiz-alert { border-radius: 10px; margin-bottom: 12px; }

.lm-edit-wizard .lm-wiz-loading {
    display: flex; align-items: center; justify-content: center; padding: 60px 20px;
    flex-direction: column; gap: 16px; color: #6b7280;
}
.lm-edit-wizard .lm-wiz-loading i { font-size: 32px; color: #2563eb; }

.lm-wiz-field-error {
    display: block; margin-top: 4px; font-size: 11px; font-weight: 600; color: #ef4444;
}
.lm-wiz-input.has-error { border-color: #ef4444; background: #fef2f2; }

.wiz-item-row { border-bottom: 1px solid #f3f4f6; }
.wiz-item-row:last-child { border-bottom: none; }
.wiz-item-header {
    display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; gap: 10px; align-items: center; padding: 8px 6px;
    font-size: 13px; cursor: pointer; border-radius: 6px; transition: background .1s;
}
.wiz-item-header:hover { background: #f9fafb; }
.wiz-item-row.open .wiz-item-header { background: #eff6ff; }
.wiz-item-header-main { min-width: 0; }
.wiz-item-header-main strong,
.wiz-item-header-main small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wiz-item-header-thumb {
    width: 28px; height: 28px; border: 1px solid #dbe4ef; border-radius: 6px; background: #f8fafc;
    display: flex; align-items: center; justify-content: center; color: #94a3b8; overflow: hidden; flex: 0 0 auto;
}
.wiz-item-header-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.wiz-item-body { padding: 8px 6px 12px; background: #fafcff; border-radius: 8px; margin-bottom: 4px; }
.wiz-item-form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
.wiz-item-field label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 3px; text-transform: uppercase; letter-spacing: .2px; }
.wiz-item-field .lm-wiz-input { height: 36px; font-size: 13px; padding: 0 10px; }
.wiz-item-photo-control { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.wiz-item-photo-action { margin: 0; border-radius: 8px; font-size: 12px; font-weight: 700; }
.wiz-item-photo-thumb {
    width: 34px; height: 34px; border: 1px solid #dbe4ef; border-radius: 6px; background: #f8fafc;
    display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; overflow: hidden;
}
.wiz-item-photo-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.wiz-item-photo-status { display: block; margin-top: 4px; color: #64748b; font-size: 11px; line-height: 1.25; }
.wiz-item-form-actions { display: flex; gap: 6px; margin-top: 10px; justify-content: flex-end; }
.wiz-item-form-actions .btn { font-size: 12px; padding: 5px 14px; border-radius: 8px; }
.wiz-product-crop-overlay {
    position: fixed; inset: 0; z-index: 1065; display: none; align-items: center; justify-content: center;
    background: rgba(15, 23, 42, .65); padding: 16px;
}
.wiz-product-crop-box {
    width: min(820px, 100%); max-height: 92vh; overflow: auto; background: #fff; border-radius: 10px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .35); padding: 14px;
}
.wiz-product-crop-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.wiz-product-crop-title { font-size: 15px; font-weight: 800; color: #1f2937; }
.wiz-product-crop-canvas {
    display: block; width: 100%; max-height: 68vh; border: 1px solid #dbe3ef; border-radius: 8px;
    touch-action: none; background: #f8fafc;
}
.wiz-product-crop-status { min-height: 18px; margin-top: 8px; color: #64748b; font-size: 12px; }
.wiz-product-crop-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.wiz-customer-photo-strip { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.wiz-customer-photo-panel {
    border: 1px solid #e5eaf2; border-radius: 8px; padding: 8px; background: #fbfcfe;
    display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 8px; align-items: center;
}
.wiz-customer-photo-panel .lm-wiz-section-title,
.wiz-customer-photo-panel .wiz-customer-photo-actions,
.wiz-customer-photo-panel .wiz-customer-ocr-status { grid-column: 1; }
.wiz-customer-photo-panel .wiz-customer-preview { grid-column: 2; grid-row: 1 / span 3; }
.wiz-customer-photo-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.wiz-customer-preview {
    width: 42px; height: 42px; border: 1px dashed #cbd5e1; border-radius: 7px; background: #fff; color: #94a3b8;
    display: flex; align-items: center; justify-content: center; margin-top: 0; overflow: hidden;
}
.wiz-customer-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.wiz-customer-ocr-status { margin-top: 6px; color: #64748b; font-size: 11px; line-height: 1.25; min-height: 15px; }
.wiz-doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(54px, 54px)); gap: 8px; margin-bottom: 12px; }
.wiz-doc-tile {
    width: 54px; height: 54px; min-height: 54px; aspect-ratio: 1; border: 1px dashed #cbd5e1; border-radius: 7px; background: #fbfcfe; color: #64748b;
    display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 8px; text-align: center;
    font-weight: 700; cursor: pointer; position: relative; overflow: hidden; font-size: 10px; padding: 5px;
}
.wiz-doc-tile span:not(.wiz-doc-size) {
    max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.wiz-doc-tile i { font-size: 16px; }
.wiz-doc-tile img { width: 100%; height: 100%; object-fit: cover; display: block; position: absolute; inset: 0; }
.wiz-doc-tile .wiz-doc-size { position: absolute; left: 4px; bottom: 4px; background: rgba(15,23,42,.75); color: #fff; border-radius: 999px; padding: 1px 4px; font-size: 9px; }
.wiz-doc-remove {
    position: absolute; right: 3px; top: 3px; width: 18px; height: 18px; border-radius: 999px; border: 0;
    background: rgba(239,68,68,.92); color: #fff; display: flex; align-items: center; justify-content: center;
}
.wiz-doc-link-row { display: flex; gap: 8px; margin-top: 10px; }
.wiz-doc-link-row .lm-wiz-input { flex: 1; }
.wiz-doc-link-row .btn { width: 46px; border-radius: 8px; }
.wiz-photo-choice-overlay {
    position: fixed; inset: 0; z-index: 1070; display: none; align-items: center; justify-content: center;
    background: rgba(15, 23, 42, .45); padding: 16px;
}
.wiz-photo-choice-box {
    width: min(320px, 100%); background: #fff; border-radius: 10px; box-shadow: 0 24px 70px rgba(15,23,42,.28);
    padding: 12px; border: 1px solid #e5eaf2;
}
.wiz-photo-choice-title { font-size: 13px; font-weight: 800; color: #1f2937; margin-bottom: 10px; }
.wiz-photo-choice-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.wiz-photo-choice-actions .btn { border-radius: 8px; font-weight: 700; }

.wiz-deposit-row { border-bottom: 1px solid #f3f4f6; }
.wiz-deposit-row:last-child { border-bottom: none; }
.wiz-deposit-header {
    display: flex; justify-content: space-between; align-items: center; padding: 8px 6px;
    font-size: 13px; cursor: pointer; border-radius: 6px; transition: background .1s;
}
.wiz-deposit-header:hover { background: #f9fafb; }
.wiz-deposit-row.open .wiz-deposit-header { background: #eff6ff; }
.wiz-deposit-body { padding: 8px 6px 12px; background: #fafcff; border-radius: 8px; margin-bottom: 4px; }
.wiz-deposit-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.wiz-deposit-field label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 3px; text-transform: uppercase; letter-spacing: .2px; }
.wiz-deposit-field .lm-wiz-input { height: 36px; font-size: 13px; padding: 0 10px; }
.wiz-deposit-field textarea.lm-wiz-input { height: auto; padding: 8px 10px; }
.wiz-deposit-form-actions { display: flex; gap: 6px; margin-top: 10px; justify-content: flex-end; }
.wiz-deposit-form-actions .btn { font-size: 12px; padding: 5px 14px; border-radius: 8px; }

@media (max-width: 767px) {
    .lm-edit-wizard .lm-wiz-bottombar { padding-bottom: max(10px, env(safe-area-inset-bottom)); }
    .lm-edit-wizard .lm-wiz-grid-2,
    .lm-edit-wizard .lm-wiz-grid-3,
    .lm-edit-wizard .lm-wiz-grid-4,
    .lm-edit-wizard .lm-wiz-stat-strip,
    .wiz-customer-photo-strip,
    .lm-edit-wizard .lm-wiz-panel[data-panel="1"].active,
    .wiz-doc-grid,
    .lm-edit-wizard .lm-wiz-review-grid { grid-template-columns: 1fr; }
    .lm-edit-wizard .lm-wiz-payment-row { grid-template-columns: 1fr 1fr; }
    .wiz-customer-photo-panel { grid-template-columns: 1fr; }
    .wiz-customer-photo-panel .wiz-customer-preview { grid-column: 1; grid-row: auto; }
}
</style>
@endsection

@section('content_body')
@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $backCustomerId = request('customer_id') ?: ($loanRow->customer_id ?? null);
    $editRouteParams = ['loan' => $loanRow->id];
    if ($isEmbeddedModal) { $editRouteParams['_lm_modal'] = 1; }
    if (!empty($backCustomerId)) { $editRouteParams['customer_id'] = $backCustomerId; }

    $loanStatuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted', 'closed'];
    $paymentFrequencies = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
    $interestTypes = ['flat' => 'បង់ថេរ', 'reducing_balance' => 'បង់ថយ'];

    $cleanVal = function ($v) { $v = trim((string) $v); return $v === '-' ? '' : $v; };
    $editCustomerName = $cleanVal($customerName);
    $editCustomerPhone = $cleanVal($customerPhone);
    $editCustomerAddress = $cleanVal($customerAddress);
    $editLocationName = $cleanVal($locationName);

    $principalAfterDepositValue = (float) old('principal_amount', $loanRow->principal_amount ?? $loanRow->financed_amount ?? 0);
    $reviewTotalAmount = (float) old('total_amount', $loanRow->total_amount ?? (($loanRow->principal_amount ?? 0) + ($loanRow->interest_amount ?? 0)));
    $reviewBalanceAmount = (float) old('balance_amount', $loanRow->balance_amount ?? 0);
    $collectedLoanItems = $loanItems ?? collect();
    $collectedCollectors = $collectors ?? collect();
    $collectedLocations = $locations ?? collect();
    $reviewPaidAmount = (float) old('paid_amount', $loanRow->paid_amount ?? 0);
    $reviewDownPayment = (float) old('down_payment', $loanRow->down_payment ?? 0);
    $reviewInterestAmount = (float) old('interest_amount', $loanRow->interest_amount ?? 0);
    $reviewPenaltyAmount = (float) old('penalty_amount', $loanRow->penalty_amount ?? 0);
    $reviewDiscountAmount = (float) old('discount_amount', $loanRow->discount_amount ?? 0);
    $depositPayments = $depositPayments ?? collect();
    $payments = $payments ?? collect();
    $reviewPaymentRows = $payments->take(6);
    $reviewDepositRows = $depositPayments->take(6);
    $sourceDisplayType = trim((string) ($sourceType ?? $loanRow->source_type ?? 'manual'));
    $sourceDisplayInvoice = trim((string) ($sourceInvoice ?? $loanRow->source_invoice_no ?? $loanRow->invoice_number_snapshot ?? ''));
    $quotationDisplayNo = trim((string) ($loanRow->quotation_no ?? $loanRow->quote_no ?? $loanRow->quotation_number_snapshot ?? $loanRow->quote_number_snapshot ?? ''));
    $productPhotoUrl = function ($path) {
        $path = trim((string) $path);
        if ($path === '') { return ''; }
        if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:image/') || str_starts_with($path, '/')) {
            return $path;
        }
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    };
@endphp

<section class="content lm-edit-wizard">
    @if ($errors->any())
        @php $fullSaveError = $errors->first('save_error'); @endphp
        <div class="alert alert-danger lm-wiz-alert">
            <strong>Unable to save this loan.</strong>
            <div>Please check the highlighted fields below.</div>
            @if ($fullSaveError)
                <div style="margin-top: 6px;"><a href="#" id="wizErrorLink">View error details</a></div>
                <pre id="wizErrorDetails" style="display:none; margin-top:6px; white-space:pre-wrap; font-size:12px; background:#fff; border:1px solid #f1b0b7; color:#a94442; padding:8px; border-radius:6px;">{{ $fullSaveError }}</pre>
            @endif
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success lm-wiz-alert">{{ is_array(session('status')) ? (session('status.msg') ?? 'Saved.') : session('status') }}</div>
    @endif

    <div class="lm-wiz-topbar" style="display:flex; align-items:center; gap:8px;">
        <div class="lm-wiz-title" style="flex:1;">
            <i class="fa fa-pencil-square-o"></i> Edit Loan #{{ $loanRow->loan_number ?? $loanRow->id }}
        </div>
    </div>

    <div class="lm-wiz-progress" id="wizProgress">
        <div class="lm-wiz-step active" data-step="0"><div class="lm-wiz-step-dot">1</div></div>
        <div class="lm-wiz-step" data-step="1"><div class="lm-wiz-step-dot">2</div></div>
        <div class="lm-wiz-step" data-step="2"><div class="lm-wiz-step-dot">3</div></div>
        <div class="lm-wiz-step" data-step="3"><div class="lm-wiz-step-dot">4</div></div>
        <div class="lm-wiz-step" data-step="4"><div class="lm-wiz-step-dot">5</div></div>
    </div>
    <div class="lm-wiz-step-labels" id="wizStepLabels">
        <span class="active">Invoice</span>
        <span>Customer</span>
        <span>Products</span>
        <span>Related Data</span>
        <span>Review</span>
    </div>

    <form id="wizEditForm" method="POST" action="{{ route('loan-management.loans.update', $editRouteParams) }}" style="display:flex; flex-direction:column;">
        @csrf

        <div class="lm-wiz-steps-wrap" id="wizStepsWrap">

            {{-- ========== STEP 0: INVOICE ========== --}}
            <div class="lm-wiz-panel active" data-panel="0">
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-file-invoice"></i> Invoice Details</div>
                    <div class="lm-wiz-subtitle">Core invoice, quotation/source, location, and ownership details for this loan.</div>
                    <div class="lm-wiz-stat-strip">
                        <div class="lm-wiz-stat"><small>Products</small><strong>{{ $loanItemsCount ?? $collectedLoanItems->count() }}</strong></div>
                        <div class="lm-wiz-stat"><small>Schedules</small><strong>{{ $schedulesCount ?? 0 }}</strong></div>
                        <div class="lm-wiz-stat"><small>Payments</small><strong>{{ $paymentsCount ?? $payments->count() }}</strong></div>
                        <div class="lm-wiz-stat"><small>Currency</small><strong>{{ $loanRow->currency ?? 'USD' }}</strong></div>
                    </div>
                    <div class="lm-wiz-grid-3">
                        <div class="lm-wiz-field">
                            <label>Loan No</label>
                            <div class="lm-wiz-readonly">{{ $loanRow->loan_number ?? '#' . $loanRow->id }}</div>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Date <span class="lm-wiz-required">*</span></label>
                            <input type="date" name="loan_date" class="lm-wiz-input" value="{{ old('loan_date', !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('Y-m-d') : '') }}" required>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Source Type</label>
                            <select name="source_type" class="lm-wiz-input">
                                @foreach(['manual' => 'Manual', 'sell' => 'Sale Invoice', 'quotation' => 'Quotation', 'pos' => 'POS', 'import' => 'Import'] as $srcKey => $srcLabel)
                                    <option value="{{ $srcKey }}" {{ old('source_type', $sourceDisplayType ?: 'manual') === $srcKey ? 'selected' : '' }}>{{ $srcLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lm-wiz-grid-3">
                        <div class="lm-wiz-field">
                            <label>Invoice No</label>
                            <input type="text" name="source_invoice_no" class="lm-wiz-input" value="{{ old('source_invoice_no', $sourceDisplayInvoice) }}" placeholder="Invoice number">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Quotation No</label>
                            <input type="text" class="lm-wiz-input" value="{{ $quotationDisplayNo ?: '-' }}" readonly>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Source Date</label>
                            <input type="date" name="source_created_at" class="lm-wiz-input" value="{{ old('source_created_at', !empty($loanRow->source_created_at) ? \Carbon\Carbon::parse($loanRow->source_created_at)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="lm-wiz-field">
                        <label>Business Location</label>
                        <select name="business_location_id" class="lm-wiz-input">
                            <option value="">-- Select --</option>
                            @foreach($collectedLocations as $locId => $locName)
                                <option value="{{ $locId }}" {{ (string) $locId === (string) ($selectedBusinessLocationId ?? '') ? 'selected' : '' }}>{{ $locName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="main_location_id" value="{{ old('main_location_id', $locationId ?? $loanRow->main_location_id ?? '') }}">
                    <div class="lm-wiz-field">
                        <label>Collector</label>
                        <select name="assigned_collector_id" class="lm-wiz-input">
                            <option value="">-- None --</option>
                            @foreach($collectedCollectors as $c)
                                <option value="{{ $c->id }}" {{ (string) $c->id === (string) ($loanRow->assigned_collector_id ?? $loanRow->collector_id ?? '') ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lm-wiz-grid-3">
                        <div class="lm-wiz-field">
                            <label>Status</label>
                            <select name="status" class="lm-wiz-input">
                                @foreach($loanStatuses as $st)
                                    <option value="{{ $st }}" {{ old('status', $loanRow->status ?? 'draft') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Approved Date</label>
                            <input type="date" name="approved_at" class="lm-wiz-input" value="{{ old('approved_at', !empty($loanRow->approved_at) ? \Carbon\Carbon::parse($loanRow->approved_at)->format('Y-m-d') : '') }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Maturity Date</label>
                            <input type="date" name="maturity_date" class="lm-wiz-input" value="{{ old('maturity_date', !empty($loanRow->maturity_date) ? \Carbon\Carbon::parse($loanRow->maturity_date)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <input type="hidden" name="currency" value="{{ $loanRow->currency ?? 'USD' }}">
                    <input type="hidden" name="exchange_rate" value="{{ $loanRow->exchange_rate ?? 1 }}">
                    <div class="lm-wiz-field">
                        <label>Loan Note</label>
                        <textarea name="note" class="lm-wiz-input" rows="3" placeholder="Internal note for this loan">{{ old('note', $loanRow->note ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ========== STEP 1: CUSTOMER ========== --}}
            <div class="lm-wiz-panel" data-panel="1">
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-user"></i> Customer Information</div>
                    <div class="lm-wiz-subtitle">Identity, contact, address, guarantor, and customer reference details.</div>
                    <input type="hidden" name="customer_id" value="{{ old('customer_id', $loanRow->customer_id ?? '') }}">
                    <input type="hidden" name="main_contact_id" value="{{ old('main_contact_id', $mainContactId ?? '') }}">
                    <div class="lm-wiz-stat-strip">
                        <div class="lm-wiz-stat"><small>Customer ID</small><strong>{{ $loanRow->customer_id ?? '-' }}</strong></div>
                        <div class="lm-wiz-stat"><small>Main Contact</small><strong>{{ $mainContactId ?: '-' }}</strong></div>
                        <div class="lm-wiz-stat"><small>ID Card</small><strong>{{ $loanRow->id_card_number ?? '-' }}</strong></div>
                        <div class="lm-wiz-stat"><small>Phone</small><strong>{{ $editCustomerPhone ?: '-' }}</strong></div>
                    </div>
                    <div class="wiz-customer-photo-strip">
                        <div class="wiz-customer-photo-panel">
                            <div class="lm-wiz-section-title"><i class="fa fa-user-circle"></i> Profile Photo</div>
                            <div class="wiz-customer-photo-actions">
                                <button type="button" class="btn btn-default btn-sm wiz-photo-choice-btn" data-camera="#wizCustomerProfileCamera" data-upload="#wizCustomerProfileUpload">
                                    <i class="fa fa-camera"></i> Photo
                                </button>
                                <input type="file" id="wizCustomerProfileCamera" accept="image/*" capture="user" style="display:none;">
                                <input type="file" id="wizCustomerProfileUpload" accept="image/*" style="display:none;">
                            </div>
                            <input type="hidden" name="customer_profile_image" id="wizCustomerProfileImage" value="">
                            <div class="wiz-customer-preview" id="wizCustomerProfilePreview">
                                @if(!empty($customerProfilePhotoUrl) || !empty($loanRow->customer_photo_snapshot))
                                    <img src="{{ $customerProfilePhotoUrl ?: $loanRow->customer_photo_snapshot }}" alt="Customer profile">
                                @else
                                    <i class="fa fa-user fa-2x"></i>
                                @endif
                            </div>
                            <div class="wiz-customer-ocr-status" id="wizCustomerProfileStatus"></div>
                        </div>
                        <div class="wiz-customer-photo-panel">
                            <div class="lm-wiz-section-title"><i class="fa fa-id-card"></i> ID Card Photo</div>
                            <div class="wiz-customer-photo-actions">
                                <button type="button" class="btn btn-default btn-sm wiz-photo-choice-btn" data-camera="#wizIdCardCamera" data-upload="#wizIdCardUpload">
                                    <i class="fa fa-camera"></i> Photo
                                </button>
                                <button type="button" class="btn btn-info btn-sm" id="wizIdCardReExtractBtn" data-image-url="{{ $idCardPhotoUrl ?? '' }}">
                                    <i class="fa fa-magic"></i> Re-extract Text
                                </button>
                                <input type="file" id="wizIdCardCamera" accept="image/*" capture="environment" style="display:none;">
                                <input type="file" id="wizIdCardUpload" accept="image/*" style="display:none;">
                            </div>
                            <input type="hidden" name="id_card_image" id="wizIdCardImage" value="">
                            <input type="hidden" name="id_card_ocr_fields[id_card_number]" id="wizIdCardOcrNumber" value="">
                            <input type="hidden" name="id_card_ocr_fields[khmer_name]" id="wizIdCardOcrKhmerName" value="">
                            <input type="hidden" name="id_card_ocr_fields[english_name]" id="wizIdCardOcrEnglishName" value="">
                            <input type="hidden" name="id_card_ocr_fields[address]" id="wizIdCardOcrAddress" value="">
                            <div class="wiz-customer-preview" id="wizIdCardPreview">
                                @if(!empty($idCardPhotoUrl))
                                    <img src="{{ $idCardPhotoUrl }}" alt="ID card">
                                @else
                                    <i class="fa fa-id-card fa-2x"></i>
                                @endif
                            </div>
                            <div class="wiz-customer-ocr-status" id="wizIdCardOcrStatus"></div>
                        </div>
                    </div>
                    <input type="hidden" name="customer_name_snapshot" value="{{ old('customer_khmer_name', trim((string) ($loanRow->customer_khmer_name ?? ''))) ?: old('customer_name_snapshot', $editCustomerName) }}">
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Khmer Name</label>
                            <input type="text" name="customer_khmer_name" class="lm-wiz-input" value="{{ old('customer_khmer_name', $loanRow->customer_khmer_name ?? '') }}" placeholder="Khmer name">
                        </div>
                        <div class="lm-wiz-field">
                            <label>English Name</label>
                            <input type="text" name="customer_english_name" class="lm-wiz-input" value="{{ old('customer_english_name', $loanRow->customer_english_name ?? '') }}" placeholder="English name">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Phone <span class="lm-wiz-required">*</span></label>
                            <input type="text" name="customer_phone_snapshot" class="lm-wiz-input" value="{{ old('customer_phone_snapshot', $editCustomerPhone) }}" required placeholder="Phone">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Alternate Phone</label>
                            <input type="text" name="alternate_phone" class="lm-wiz-input" value="{{ old('alternate_phone', $loanRow->alternate_phone ?? '') }}" placeholder="Alternate phone">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Customer Group</label>
                            <input type="text" name="customer_group_name" class="lm-wiz-input" value="{{ old('customer_group_name', $loanRow->customer_group_name_snapshot ?? 'រំលស់') }}" placeholder="Customer group">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>National ID</label>
                            <input type="text" name="id_card_number" class="lm-wiz-input" value="{{ old('id_card_number', $loanRow->id_card_number ?? '') }}" placeholder="ID Card">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Occupation</label>
                            <input type="text" name="occupation" class="lm-wiz-input" value="{{ old('occupation', $loanRow->occupation ?? '') }}" placeholder="Occupation">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-4">
                        <div class="lm-wiz-field">
                            <label>Province</label>
                            <select name="province_code" id="wizProvinceSelect" class="lm-wiz-input" data-current-code="{{ old('province_code', $loanRow->customer_province_code_snapshot ?? $loanRow->province_code ?? '') }}" data-current-name="{{ old('province_name', $loanRow->customer_province_snapshot ?? $loanRow->province ?? '') }}">
                                <option value="">-- Select --</option>
                            </select>
                            <input type="hidden" name="province_name" id="wizProvinceName" value="{{ old('province_name', $loanRow->customer_province_snapshot ?? $loanRow->province ?? '') }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>District</label>
                            <select name="district_code" id="wizDistrictSelect" class="lm-wiz-input" data-current-code="{{ old('district_code', $loanRow->customer_district_code_snapshot ?? $loanRow->district_code ?? '') }}" data-current-name="{{ old('district_name', $loanRow->customer_district_snapshot ?? $loanRow->district ?? '') }}" disabled>
                                <option value="">-- Select --</option>
                            </select>
                            <input type="hidden" name="district_name" id="wizDistrictName" value="{{ old('district_name', $loanRow->customer_district_snapshot ?? $loanRow->district ?? '') }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Commune</label>
                            <select name="commune_code" id="wizCommuneSelect" class="lm-wiz-input" data-current-code="{{ old('commune_code', $loanRow->customer_commune_code_snapshot ?? $loanRow->commune_code ?? '') }}" data-current-name="{{ old('commune_name', $loanRow->customer_commune_snapshot ?? $loanRow->commune ?? '') }}" disabled>
                                <option value="">-- Select --</option>
                            </select>
                            <input type="hidden" name="commune_name" id="wizCommuneName" value="{{ old('commune_name', $loanRow->customer_commune_snapshot ?? $loanRow->commune ?? '') }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Village</label>
                            <select name="village_code" id="wizVillageSelect" class="lm-wiz-input" data-current-code="{{ old('village_code', $loanRow->customer_village_code_snapshot ?? $loanRow->village_code ?? '') }}" data-current-name="{{ old('village_name', $loanRow->customer_village_snapshot ?? $loanRow->village ?? '') }}" disabled>
                                <option value="">-- Select --</option>
                            </select>
                            <input type="hidden" name="village_name" id="wizVillageName" value="{{ old('village_name', $loanRow->customer_village_snapshot ?? $loanRow->village ?? '') }}">
                        </div>
                    </div>
                    <div class="lm-wiz-muted" id="wizAddressLoadStatus"></div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Address</label>
                            <textarea name="customer_address_snapshot" class="lm-wiz-input" rows="3" placeholder="Address">{{ old('customer_address_snapshot', $editCustomerAddress) }}</textarea>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Location Address</label>
                            <textarea class="lm-wiz-input" rows="3" readonly>{{ $locationAddress ?: '-' }}</textarea>
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Guarantor Name</label>
                            <input type="text" name="guarantor_name" class="lm-wiz-input" value="{{ old('guarantor_name', $loanRow->guarantor_name ?? '') }}" placeholder="Guarantor name">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Guarantor Phone</label>
                            <input type="text" name="guarantor_phone" class="lm-wiz-input" value="{{ old('guarantor_phone', $loanRow->guarantor_phone ?? '') }}" placeholder="Guarantor phone">
                        </div>
                    </div>
                    <div class="lm-wiz-field">
                        <label>ID Card OCR Text</label>
                        <textarea name="id_card_ocr_raw_text" id="wizIdCardOcrRawText" class="lm-wiz-input" rows="3" placeholder="Extracted ID card text">{{ old('id_card_ocr_raw_text', $loanRow->id_card_ocr_raw_text ?? '') }}</textarea>
                    </div>
                </div>
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-paperclip"></i> Documents</div>
                    <div class="wiz-doc-grid" id="wizDocGrid">
                        @foreach(($loanDocumentFiles ?? collect()) as $doc)
                            <div class="wiz-doc-tile">
                                @if(!empty($doc->url) && str_starts_with((string) ($doc->mime_type ?? ''), 'image/'))
                                    <img src="{{ $doc->url }}" alt="Document">
                                @else
                                    <i class="fa fa-file-o"></i>
                                    <span>{{ $doc->original_name ?? 'Document' }}</span>
                                @endif
                                @if(!empty($doc->size_bytes))
                                    <span class="wiz-doc-size">{{ round($doc->size_bytes / 1024) }}KB</span>
                                @endif
                            </div>
                        @endforeach
                        <label class="wiz-doc-tile" for="wizDocInput" id="wizDocAddTile">
                            <i class="fa fa-plus-circle"></i>
                            <span>Add File</span>
                        </label>
                    </div>
                    <input type="file" id="wizDocInput" accept="image/*,.pdf,.txt,.csv,.doc,.docx" multiple style="display:none;">
                    <div id="wizDocHiddenFields"></div>
                    <div class="lm-wiz-field">
                        <textarea name="document_text" class="lm-wiz-input" rows="3" placeholder="Write document note or extra information to send with Telegram">{{ old('document_text') }}</textarea>
                    </div>
                    <div id="wizDocumentLinks">
                        <div class="wiz-doc-link-row">
                            <input type="url" name="document_links[]" class="lm-wiz-input" placeholder="Paste document link">
                            <button type="button" class="btn btn-default" id="wizBtnAddDocumentLink" title="Add another link"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="lm-wiz-muted" style="margin-top:10px;"><i class="fa fa-clipboard"></i> Paste images with Ctrl+V. Photos compressed, files kept as-is.</div>
                </div>
            </div>

            {{-- ========== STEP 2: PRODUCTS ========== --}}
            <div class="lm-wiz-panel" data-panel="2">
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-shopping-cart"></i> Product Items</div>
                    <div class="lm-wiz-subtitle">Editable product, SKU, IMEI/serial, quantity, price, and discount information.</div>
                    <div class="lm-wiz-stat-strip">
                        <div class="lm-wiz-stat"><small>Items</small><strong id="wizStatItemCount">{{ $collectedLoanItems->count() }}</strong></div>
                        <div class="lm-wiz-stat"><small>Product Total</small><strong id="wizStatProductTotal">${{ number_format((float) ($loanItemsUnitPriceTotal ?? 0), 2) }}</strong></div>
                        <div class="lm-wiz-stat"><small>Deposit</small><strong>${{ number_format($customerDepositPaymentsAmount, 2) }}</strong></div>
                        <div class="lm-wiz-stat"><small>Financed</small><strong id="wizStatFinanced">${{ number_format($principalAfterDepositValue, 2) }}</strong></div>
                    </div>
                    <div id="wizItemsList">
                        @forelse($collectedLoanItems as $item)
                            @php
                                $itemTotal = (float) ($item->line_total ?? (($item->qty ?? 1) * ($item->unit_price ?? 0) - ($item->discount ?? 0)));
                                $itemQty = (float) ($item->qty ?? $item->quantity ?? 1);
                                $itemUnitPrice = (float) ($item->unit_price ?? 0);
                                $itemDiscount = (float) ($item->discount ?? 0);
                            @endphp
                            @php
                                $itemUpdateUrl = route('loan-management.loans.items.update', ['loan' => $loanRow->id, 'item' => $item->id]);
                                $itemDeleteUrl = route('loan-management.loans.items.destroy', ['loan' => $loanRow->id, 'item' => $item->id]);
                            @endphp
                            <div class="wiz-item-row" data-id="{{ $item->id }}" data-loan-id="{{ $loanRow->id }}">
                                @php $itemPhotoPreview = $productPhotoUrl($item->product_photo_path ?? ''); @endphp
                                <div class="wiz-item-header" onclick="$(this).closest('.wiz-item-row').toggleClass('open').find('.wiz-item-body').slideToggle(200);">
                                    <span class="wiz-item-header-thumb">
                                        @if($itemPhotoPreview !== '')
                                            <img src="{{ $itemPhotoPreview }}" alt="">
                                        @else
                                            <i class="fa fa-image"></i>
                                        @endif
                                    </span>
                                    <span class="wiz-item-header-main">
                                        <strong>{{ $item->product_name_snapshot ?? $item->product_name ?? 'Unnamed' }}</strong>
                                        <small style="display:block;color:#94a3b8;">SKU: {{ $item->sku_snapshot ?? $item->sku ?? '-' }} · IMEI: {{ $item->imei_snapshot ?? $item->imei ?? '-' }} · Serial: {{ $item->serial_number_snapshot ?? $item->serial_number ?? '-' }} · Qty: {{ number_format($itemQty, 2) }}</small>
                                    </span>
                                    <span style="font-weight:600;" class="wiz-item-line-total">${{ number_format($itemTotal, 2) }}</span>
                                    <span style="color:#94a3b8; font-size:11px;"><i class="fa fa-chevron-down"></i></span>
                                </div>
                                <div class="wiz-item-body" style="display:none;" data-update-url="{{ $itemUpdateUrl }}">
                                    <div class="wiz-item-form-grid">
                                        <div class="wiz-item-field" style="grid-column:1/-1;">
                                            <label>Product Name</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][product_name_snapshot]" data-item-field="product_name_snapshot" class="lm-wiz-input" value="{{ $item->product_name_snapshot ?? $item->product_name ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>SKU</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][sku_snapshot]" data-item-field="sku_snapshot" class="lm-wiz-input" value="{{ $item->sku_snapshot ?? $item->sku ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Brand</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][brand]" data-item-field="brand" class="lm-wiz-input" value="{{ $item->brand ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Category</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][category]" data-item-field="category" class="lm-wiz-input" value="{{ $item->category ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>IMEI</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][imei_snapshot]" data-item-field="imei_snapshot" class="lm-wiz-input wiz-item-imei" value="{{ $item->imei_snapshot ?? $item->imei ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Serial Number</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][serial_number_snapshot]" data-item-field="serial_number_snapshot" class="lm-wiz-input" value="{{ $item->serial_number_snapshot ?? $item->serial_number ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Lot Number</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][lot_number_snapshot]" data-item-field="lot_number_snapshot" class="lm-wiz-input" value="{{ $item->lot_number_snapshot ?? $item->lot_number ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Qty</label>
                                            <input type="number" name="edit_items[{{ $item->id }}][qty]" data-item-field="qty" class="lm-wiz-input" value="{{ $item->qty ?? 1 }}" min="1">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Price</label>
                                            <input type="number" step="0.01" name="edit_items[{{ $item->id }}][unit_price]" data-item-field="unit_price" class="lm-wiz-input" value="{{ $item->unit_price ?? 0 }}" min="0">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Discount</label>
                                            <input type="number" step="0.01" name="edit_items[{{ $item->id }}][discount]" data-item-field="discount" class="lm-wiz-input" value="{{ $itemDiscount }}" min="0">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Color</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][color]" data-item-field="color" class="lm-wiz-input" value="{{ $item->color ?? '' }}">
                                        </div>
                                        <div class="wiz-item-field">
                                            <label>Storage</label>
                                            <input type="text" name="edit_items[{{ $item->id }}][storage]" data-item-field="storage" class="lm-wiz-input" value="{{ $item->storage ?? '' }}" placeholder="128GB, 256GB">
                                        </div>
                                        <div class="wiz-item-field" style="grid-column:1/-1;">
                                            <label>Product Photo / Reference</label>
                                            <div class="wiz-item-photo-control">
                                                <button type="button" class="btn btn-default btn-sm wiz-item-photo-action wiz-product-photo-choice-btn">
                                                    <i class="fa fa-camera"></i> Photo
                                                </button>
                                                <input type="file" accept="image/*" capture="environment" class="wiz-item-photo-input wiz-item-photo-camera" style="display:none;">
                                                <input type="file" accept="image/*" class="wiz-item-photo-input wiz-item-photo-upload" style="display:none;">
                                                <span class="wiz-item-photo-thumb">
                                                    @if($itemPhotoPreview !== '')
                                                        <img src="{{ $itemPhotoPreview }}" alt="">
                                                    @else
                                                        <i class="fa fa-image"></i>
                                                    @endif
                                                </span>
                                                <span class="wiz-item-photo-status">{{ !empty($item->product_photo_path) ? 'Photo reference ready' : '' }}</span>
                                            </div>
                                            <input type="hidden" name="edit_items[{{ $item->id }}][product_photo]" data-item-field="product_photo" class="wiz-item-photo-data" value="">
                                            <input type="text" name="edit_items[{{ $item->id }}][product_photo_path]" data-item-field="product_photo_path" class="lm-wiz-input wiz-item-photo-path" value="{{ $item->product_photo_path ?? '' }}" placeholder="Photo path, URL, or uploaded file name" style="margin-top:6px;">
                                        </div>
                                        <div class="wiz-item-field" style="grid-column:1/-1;">
                                            <label>Product OCR / Note</label>
                                            <textarea name="edit_items[{{ $item->id }}][product_ocr_raw_text]" data-item-field="product_ocr_raw_text" class="lm-wiz-input" rows="2" placeholder="OCR text or product note">{{ $item->product_ocr_raw_text ?? '' }}</textarea>
                                        </div>
                                    </div>
                                    <div class="wiz-item-form-actions">
                                        <button type="button" class="btn btn-sm btn-primary wiz-item-update-btn">
                                            <i class="fa fa-refresh"></i> Update
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger wiz-item-remove-btn" data-url="{{ $itemDeleteUrl }}">
                                            <i class="fa fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div style="text-align:center; padding:20px; color:#94a3b8;" id="wizItemsEmpty">No products yet. Click "Add Item" to add products.</div>
                        @endforelse
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="wizBtnAddItem" style="margin-top:8px;">
                        <i class="fa fa-plus"></i> Add Item
                    </button>
                </div>

                <div class="lm-wiz-card" style="margin-top:12px;">
                    <div class="lm-wiz-section-title"><i class="fa fa-money"></i> Customer Deposit Payment</div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Total Deposit Amount</label>
                            <div class="lm-wiz-input" style="background:#f9fafb; font-weight:700; padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px;">
                                ${{ number_format($customerDepositPaymentsAmount, 2) }}
                            </div>
                        </div>
                        <div class="lm-wiz-field" style="display:flex; align-items:flex-end; padding-bottom:4px;">
                            <button type="button"
                                    class="btn btn-sm btn-success lm-btn-modal"
                                    data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loanRow->id, 'deposit_payment' => 1, 'return_to' => route('loan-management.loans.edit', $editRouteParams)]) }}"
                                    data-container=".view_modal">
                                <i class="fa fa-plus"></i> Add Deposit
                            </button>
                        </div>
                    </div>
                    @php
                        $depositPayments = $depositPayments ?? collect();
                        $dpEditReturnUrl = route('loan-management.loans.edit', $editRouteParams);
                    @endphp
                    @if($depositPayments->isNotEmpty())
                        <div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:10px;">
                            <div style="font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Deposit Payment History</div>
                            <div id="wizDepositList">
                            @foreach($depositPayments as $dp)
                                @php
                                    $dpAmount = (float)($dp->total_paid_base ?? $dp->total_paid ?? $dp->amount ?? 0);
                                    $dpRef = $dp->receipt_number ?? $dp->payment_ref_no ?? $dp->reference_number ?? ('Payment #'.$dp->id);
                                    $dpMethod = $dp->payment_method_snapshot ?? $dp->method ?? $dp->channel ?? 'cash';
                                    $dpDate = !empty($dp->paid_date) ? \Carbon\Carbon::parse($dp->paid_date)->format('Y-m-d') : \Carbon\Carbon::parse($dp->paid_at ?? now())->format('Y-m-d');
                                    $dpStatus = strtolower($dp->status ?? 'confirmed');
                                    $dpNote = $dp->note ?? '';
                                @endphp
                                <div class="wiz-deposit-row" data-id="{{ $dp->id }}">
                                    <div class="wiz-deposit-header" onclick="$(this).closest('.wiz-deposit-row').toggleClass('open').find('.wiz-deposit-body').slideToggle(200);">
                                        <span>{{ $dpRef }}</span>
                                        <span style="font-weight:600;">${{ number_format($dpAmount, 2) }}</span>
                                        <span style="color:#94a3b8; font-size:11px;"><i class="fa fa-chevron-down"></i></span>
                                    </div>
                                    <div class="wiz-deposit-body" style="display:none;">
                                        <div class="wiz-deposit-edit-form">
                                            <input type="hidden" name="return_to" value="{{ $dpEditReturnUrl }}">
                                            <div class="wiz-deposit-form-grid">
                                                <div class="wiz-deposit-field">
                                                    <label>Amount</label>
                                                    <input type="number" step="0.01" min="0.01" name="amount" class="lm-wiz-input" value="{{ $dpAmount }}" required>
                                                </div>
                                                <div class="wiz-deposit-field">
                                                    <label>Paid Date</label>
                                                    <input type="date" name="paid_date" class="lm-wiz-input" value="{{ $dpDate }}" required>
                                                </div>
                                                <div class="wiz-deposit-field">
                                                    <label>Method</label>
                                                    <select name="method" class="lm-wiz-input">
                                                        @foreach($paymentTypes as $pKey => $pLabel)
                                                            <option value="{{ $pKey }}" {{ $dpMethod === $pKey || $dpMethod === $pLabel ? 'selected' : '' }}>{{ $pLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="wiz-deposit-field">
                                                    <label>Status</label>
                                                    <select name="status" class="lm-wiz-input">
                                                        @foreach(['confirmed'=>'Confirmed','paid'=>'Paid','pending'=>'Pending','failed'=>'Failed','cancelled'=>'Cancelled'] as $sk => $sl)
                                                            <option value="{{ $sk }}" {{ $dpStatus === $sk ? 'selected' : '' }}>{{ $sl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="wiz-deposit-field" style="grid-column:1/-1;">
                                                    <label>Reference</label>
                                                    <input type="text" name="reference_number" class="lm-wiz-input" value="{{ $dp->reference_number ?? '' }}">
                                                </div>
                                                <div class="wiz-deposit-field" style="grid-column:1/-1;">
                                                    <label>Note</label>
                                                    <textarea name="note" class="lm-wiz-input" rows="2">{{ $dpNote }}</textarea>
                                                </div>
                                            </div>
                                            <div class="wiz-deposit-form-actions">
                                                <button type="button" class="btn btn-sm btn-primary wiz-deposit-update-btn">
                                                    <i class="fa fa-refresh"></i> Update
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger wiz-deposit-remove" data-payment-id="{{ $dp->id }}" data-return-to="{{ $dpEditReturnUrl }}">
                                                    <i class="fa fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-sliders-h"></i> Loan Conditions</div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Principal After Deposit <span class="lm-wiz-required">*</span></label>
                            <input type="number" step="0.01" name="principal_amount" class="lm-wiz-input" value="{{ old('principal_amount', $loanRow->principal_amount ?? $loanRow->financed_amount ?? 0) }}" min="0.01" required>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Down Payment</label>
                            <input type="number" step="0.01" name="down_payment" class="lm-wiz-input" value="{{ old('down_payment', $loanRow->down_payment ?? 0) }}" min="0">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Interest %</label>
                            <input type="number" step="0.01" name="interest_rate" class="lm-wiz-input" value="{{ old('interest_rate', $displayInterestRate ?? 0) }}" min="0">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Interest Type <span class="lm-wiz-required">*</span></label>
                            <select name="interest_type" class="lm-wiz-input">
                                @foreach($interestTypes as $itKey => $itLabel)
                                    <option value="{{ $itKey }}" {{ old('interest_type', $displayInterestType ?? 'flat') === $itKey ? 'selected' : '' }}>{{ $itLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Duration (Months) <span class="lm-wiz-required">*</span></label>
                            <input type="number" name="installment_count" class="lm-wiz-input" min="1" max="360" value="{{ old('installment_count', $loanRow->installment_count ?? $loanRow->duration_months ?? 12) }}" required>
                            <input type="hidden" name="duration_months" id="wizDurationMonths">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Payment Frequency <span class="lm-wiz-required">*</span></label>
                            <select name="payment_frequency" class="lm-wiz-input">
                                @foreach($paymentFrequencies as $freq)
                                    <option value="{{ $freq }}" {{ old('payment_frequency', $loanRow->payment_frequency ?? 'monthly') === $freq ? 'selected' : '' }}>{{ ucfirst($freq) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>First Due Date <span class="lm-wiz-required">*</span></label>
                            <input type="date" name="first_due_date" class="lm-wiz-input" value="{{ old('first_due_date', !empty($loanRow->first_due_date) ? \Carbon\Carbon::parse($loanRow->first_due_date)->format('Y-m-d') : '') }}" required>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Penalty</label>
                            <div class="lm-wiz-grid-2" style="gap:6px;">
                                <select name="penalty_type" class="lm-wiz-input">
                                    <option value="fixed" {{ ($loanRow->penalty_type ?? 'fixed') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                                    <option value="percentage" {{ ($loanRow->penalty_type ?? 'fixed') === 'percentage' ? 'selected' : '' }}>Percent</option>
                                </select>
                                <input type="number" step="0.01" name="penalty_amount" class="lm-wiz-input" value="{{ old('penalty_amount', $loanRow->penalty_amount ?? 0) }}" min="0">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-info btn-sm" id="wizBtnPreviewSchedule">
                        <i class="fa fa-table"></i> Preview Schedule
                    </button>
                </div>

                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-tasks"></i> Collection & Follow-up</div>
                    <div class="lm-wiz-grid-3">
                        <div class="lm-wiz-field">
                            <label>Collection Status</label>
                            <select name="collection_status" class="lm-wiz-input">
                                <option value="">-- Select --</option>
                                @foreach(['current' => 'Current', 'watch' => 'Watch', 'overdue' => 'Overdue', 'field_visit' => 'Field Visit', 'legal' => 'Legal', 'recovered' => 'Recovered'] as $csKey => $csLabel)
                                    <option value="{{ $csKey }}" {{ old('collection_status', $loanRow->collection_status ?? '') === $csKey ? 'selected' : '' }}>{{ $csLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Risk Level</label>
                            <select name="risk_level" class="lm-wiz-input">
                                <option value="">-- Select --</option>
                                @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $riskKey => $riskLabel)
                                    <option value="{{ $riskKey }}" {{ old('risk_level', $loanRow->risk_level ?? '') === $riskKey ? 'selected' : '' }}>{{ $riskLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lm-wiz-field">
                            <label>Priority</label>
                            <input type="number" name="collection_priority" class="lm-wiz-input" min="0" max="255" value="{{ old('collection_priority', $loanRow->collection_priority ?? 0) }}">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-3">
                        <div class="lm-wiz-field">
                            <label>PTP Date</label>
                            <input type="date" name="ptp_date" class="lm-wiz-input" value="{{ old('ptp_date', !empty($loanRow->ptp_date) ? \Carbon\Carbon::parse($loanRow->ptp_date)->format('Y-m-d') : '') }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>PTP Amount</label>
                            <input type="number" step="0.01" name="ptp_amount" class="lm-wiz-input" min="0" value="{{ old('ptp_amount', $loanRow->ptp_amount ?? 0) }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>Next Follow-up</label>
                            <input type="date" name="next_followup_at" class="lm-wiz-input" value="{{ old('next_followup_at', !empty($loanRow->next_followup_at) ? \Carbon\Carbon::parse($loanRow->next_followup_at)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field">
                            <label>Last Contact Result</label>
                            <input type="text" name="last_contact_result" class="lm-wiz-input" value="{{ old('last_contact_result', $loanRow->last_contact_result ?? '') }}">
                        </div>
                        <div class="lm-wiz-field">
                            <label>PTP Note</label>
                            <input type="text" name="ptp_note" class="lm-wiz-input" value="{{ old('ptp_note', $loanRow->ptp_note ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== STEP 3: RELATED DATA ========== --}}
            <div class="lm-wiz-panel" data-panel="3">
                <div id="loanEditSections">
                    @include('loanmanagement::loans.partials.edit_sections', [
                        'loanRow' => $loanRow,
                        'backCustomerId' => request('customer_id') ?: ($loanRow->customer_id ?? null),
                        'loanItems' => $loanItems ?? collect(),
                        'schedules' => $schedules ?? collect(),
                        'payments' => $payments ?? collect(),
                        'depositPayments' => $depositPayments ?? collect(),
                    ])
                </div>
            </div>

            {{-- ========== STEP 4: REVIEW ========== --}}
            <div class="lm-wiz-panel" data-panel="4">
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-receipt"></i> Summary</div>
                    <div class="lm-wiz-review-grid">
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Loan Number</span><span class="lm-wiz-review-value">{{ $loanRow->loan_number ?? '#' . $loanRow->id }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Loan Date</span><span class="lm-wiz-review-value" id="wizRevDate">{{ !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('d-m-Y') : '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Status</span><span class="lm-wiz-review-value" id="wizRevStatus">{{ ucfirst($loanRow->status ?? 'draft') }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Collector</span><span class="lm-wiz-review-value" id="wizRevCollector">{{ $loanRow->collector_name_snapshot ?? optional($collectedCollectors->firstWhere('id', $loanRow->assigned_collector_id ?? $loanRow->collector_id ?? ''))->name ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Location</span><span class="lm-wiz-review-value" id="wizRevLocation">{{ $editLocationName ?: '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Maturity</span><span class="lm-wiz-review-value" id="wizRevMaturity">{{ !empty($loanRow->maturity_date) ? \Carbon\Carbon::parse($loanRow->maturity_date)->format('d-m-Y') : '-' }}</span></div>
                    </div>
                </div>
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-file-text-o"></i> Invoice / Quotation</div>
                    <div class="lm-wiz-review-grid">
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Source Type</span><span class="lm-wiz-review-value" id="wizRevSourceType">{{ ucfirst($sourceDisplayType ?: 'manual') }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Invoice No</span><span class="lm-wiz-review-value" id="wizRevInvoice">{{ $sourceDisplayInvoice ?: '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Quotation No</span><span class="lm-wiz-review-value">{{ $quotationDisplayNo ?: '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Sale Total</span><span class="lm-wiz-review-value">${{ number_format((float) ($sourceFinalTotal ?? 0), 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Sale Paid</span><span class="lm-wiz-review-value">${{ number_format((float) ($sourcePaid ?? 0), 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Sale Due</span><span class="lm-wiz-review-value">${{ number_format((float) ($sourceDue ?? 0), 2) }}</span></div>
                    </div>
                </div>
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-user"></i> Customer</div>
                    <div class="lm-wiz-review-grid">
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Name</span><span class="lm-wiz-review-value" id="wizRevCustName">{{ $editCustomerName ?: '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Phone</span><span class="lm-wiz-review-value" id="wizRevCustPhone">{{ $editCustomerPhone ?: '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Khmer Name</span><span class="lm-wiz-review-value" id="wizRevCustKhmer">{{ $loanRow->customer_khmer_name ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">English Name</span><span class="lm-wiz-review-value" id="wizRevCustEnglish">{{ $loanRow->customer_english_name ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">National ID</span><span class="lm-wiz-review-value" id="wizRevCustIdCard">{{ $loanRow->id_card_number ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Occupation</span><span class="lm-wiz-review-value" id="wizRevOccupation">{{ $loanRow->occupation ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Guarantor</span><span class="lm-wiz-review-value" id="wizRevGuarantor">{{ $loanRow->guarantor_name ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Address</span><span class="lm-wiz-review-value" id="wizRevAddress">{{ $editCustomerAddress ?: '-' }}</span></div>
                    </div>
                </div>
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-shopping-bag"></i> Products</div>
                    <div id="wizRevProducts" style="font-size:13px; color:#6b7280;">
                        @forelse($collectedLoanItems as $item)
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f5f5f5;">
                                <span>#{{ $loop->iteration }} {{ $item->product_name_snapshot ?? $item->product_name ?? 'Unnamed' }}</span>
                                <span style="font-weight:600;">${{ number_format((float) (($item->qty ?? 1) * ($item->unit_price ?? 0)), 2) }}</span>
                            </div>
                        @empty
                            <div style="color:#94a3b8; font-style:italic;">No products added</div>
                        @endforelse
                    </div>
                </div>
                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-calculator"></i> Loan Terms</div>
                    <div class="lm-wiz-review-grid">
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Principal</span><span class="lm-wiz-review-value" id="wizRevPrincipal">${{ number_format($principalAfterDepositValue, 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Interest Rate</span><span class="lm-wiz-review-value" id="wizRevInterest">{{ number_format($displayInterestRate ?? 0, 2) }}%</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Interest Amount</span><span class="lm-wiz-review-value" id="wizRevInterestAmount">${{ number_format($reviewInterestAmount, 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Duration</span><span class="lm-wiz-review-value" id="wizRevDuration">{{ ($loanRow->installment_count ?? $loanRow->duration_months ?? 0) }} months / {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Down Payment</span><span class="lm-wiz-review-value" id="wizRevDownPayment">${{ number_format($reviewDownPayment, 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Penalty / Discount</span><span class="lm-wiz-review-value" id="wizRevPenaltyDiscount">${{ number_format($reviewPenaltyAmount, 2) }} / ${{ number_format($reviewDiscountAmount, 2) }}</span></div>
                    </div>
                </div>

                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-tasks"></i> Collection</div>
                    <div class="lm-wiz-review-grid">
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Collection Status</span><span class="lm-wiz-review-value" id="wizRevCollectionStatus">{{ ucfirst(str_replace('_', ' ', $loanRow->collection_status ?? '-')) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Risk Level</span><span class="lm-wiz-review-value" id="wizRevRiskLevel">{{ ucfirst($loanRow->risk_level ?? '-') }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Priority</span><span class="lm-wiz-review-value" id="wizRevCollectionPriority">{{ $loanRow->collection_priority ?? '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Next Follow-up</span><span class="lm-wiz-review-value" id="wizRevNextFollowup">{{ !empty($loanRow->next_followup_at) ? \Carbon\Carbon::parse($loanRow->next_followup_at)->format('d-m-Y') : '-' }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">PTP Date / Amount</span><span class="lm-wiz-review-value" id="wizRevPtp">{{ !empty($loanRow->ptp_date) ? \Carbon\Carbon::parse($loanRow->ptp_date)->format('d-m-Y') : '-' }} / ${{ number_format((float) ($loanRow->ptp_amount ?? 0), 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Last Result</span><span class="lm-wiz-review-value" id="wizRevLastContact">{{ $loanRow->last_contact_result ?? '-' }}</span></div>
                    </div>
                </div>

                <div class="lm-wiz-summary" id="wizSummaryBox" style="margin-top: 12px;">
                    <div class="lm-wiz-summary-item">
                        <div class="lm-wiz-s-label">Total</div>
                        <div class="lm-wiz-s-value" id="wizSummaryTotal">${{ number_format($reviewTotalAmount, 2) }}</div>
                    </div>
                    <div class="lm-wiz-summary-item">
                        <div class="lm-wiz-s-label">Down Payment</div>
                        <div class="lm-wiz-s-value" id="wizSummaryDp">${{ number_format((float) ($loanRow->down_payment ?? 0), 2) }}</div>
                    </div>
                    <div class="lm-wiz-summary-item">
                        <div class="lm-wiz-s-label">Balance</div>
                        <div class="lm-wiz-s-value green" id="wizSummaryBalance">${{ number_format($reviewBalanceAmount, 2) }}</div>
                    </div>
                    <div class="lm-wiz-summary-item">
                        <div class="lm-wiz-s-label">Monthly</div>
                        <div class="lm-wiz-s-value blue" id="wizSummaryMonthly">${{ $reviewBalanceAmount > 0 && ($loanRow->installment_count ?? 0) > 0 ? number_format($reviewBalanceAmount / ($loanRow->installment_count ?? 1), 2) : '0.00' }}</div>
                    </div>
                </div>

                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-credit-card"></i> Payment Data</div>
                    <div class="lm-wiz-review-grid">
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Paid Amount</span><span class="lm-wiz-review-value" id="wizRevPaidAmount">${{ number_format($reviewPaidAmount, 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Balance Amount</span><span class="lm-wiz-review-value" id="wizRevBalanceAmount">${{ number_format($reviewBalanceAmount, 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Deposit Total</span><span class="lm-wiz-review-value">${{ number_format($customerDepositPaymentsAmount, 2) }}</span></div>
                        <div class="lm-wiz-review-row"><span class="lm-wiz-review-label">Payment Records</span><span class="lm-wiz-review-value">{{ $payments->count() }}</span></div>
                    </div>
                    <div class="lm-wiz-payment-list" style="margin-top:12px;">
                        @forelse($reviewPaymentRows as $payment)
                            @php
                                $payReceipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #' . $payment->id);
                                $payAmount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount_base ?? $payment->amount ?? 0);
                                $payMethod = $payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '-';
                                $payDate = $payment->paid_date ?? $payment->paid_at ?? null;
                            @endphp
                            <div class="lm-wiz-payment-row">
                                <div><small>Receipt</small><strong>{{ $payReceipt }}</strong></div>
                                <div><small>Date</small>{{ !empty($payDate) ? \Carbon\Carbon::parse($payDate)->format('d-m-Y') : '-' }}</div>
                                <div><small>Method</small>{{ $payMethod }}</div>
                                <div><small>Amount</small><strong>${{ number_format($payAmount, 2) }}</strong></div>
                            </div>
                        @empty
                            <div class="lm-wiz-payment-row"><div style="grid-column:1/-1;color:#94a3b8;text-align:center;">No payment records found.</div></div>
                        @endforelse
                    </div>
                </div>

                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title"><i class="fa fa-money"></i> Deposit / Down Payment</div>
                    <div class="lm-wiz-payment-list">
                        @forelse($reviewDepositRows as $dp)
                            @php
                                $dpReceipt = $dp->receipt_number ?? $dp->payment_ref_no ?? $dp->reference_number ?? ('Payment #' . $dp->id);
                                $dpAmount = (float) ($dp->total_paid_base ?? $dp->total_paid ?? $dp->amount_base ?? $dp->amount ?? 0);
                                $dpMethod = $dp->payment_method_snapshot ?? $dp->method ?? $dp->channel ?? '-';
                                $dpDate = $dp->paid_date ?? $dp->paid_at ?? null;
                            @endphp
                            <div class="lm-wiz-payment-row">
                                <div><small>Reference</small><strong>{{ $dpReceipt }}</strong></div>
                                <div><small>Date</small>{{ !empty($dpDate) ? \Carbon\Carbon::parse($dpDate)->format('d-m-Y') : '-' }}</div>
                                <div><small>Method</small>{{ $dpMethod }}</div>
                                <div><small>Amount</small><strong>${{ number_format($dpAmount, 2) }}</strong></div>
                            </div>
                        @empty
                            <div class="lm-wiz-payment-row"><div style="grid-column:1/-1;color:#94a3b8;text-align:center;">No deposit payment records found.</div></div>
                        @endforelse
                    </div>
                </div>

                <div class="lm-wiz-card" id="wizScheduleSection" style="display:{{ $schedulesCount > 0 ? 'block' : 'none' }};">
                    <div class="lm-wiz-section-title"><i class="fa fa-calendar"></i> Installment Schedule</div>
                    <div class="lm-wiz-schedule-wrap">
                        <table class="lm-wiz-schedule-tbl" id="wizScheduleTable">
                            <thead><tr><th>#</th><th>Date</th><th class="text-right">Principal</th><th class="text-right">Interest</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
                            <tbody>
                                <tr><td colspan="6" class="text-center text-muted">Click Preview Schedule to see updated schedule.</td></tr>
                            </tbody>
                            <tfoot><tr><th colspan="2" class="text-right">Total</th><th class="text-right">0.00</th><th class="text-right">0.00</th><th class="text-right">0.00</th><th class="text-right">0.00</th></tr></tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-info btn-sm" id="wizBtnRefreshSchedule" style="margin-top:8px;">
                        <i class="fa fa-refresh"></i> Refresh Schedule
                    </button>
                </div>

                <div class="lm-wiz-card">
                    <div class="lm-wiz-section-title">Amount Breakdown</div>
                    <div class="lm-wiz-grid-2">
                        <div class="lm-wiz-field"><label>Interest Amount</label><input type="number" step="0.01" name="interest_amount" class="lm-wiz-input" value="{{ old('interest_amount', $loanRow->interest_amount ?? 0) }}" min="0"></div>
                        <div class="lm-wiz-field"><label>Total Amount</label><input type="number" step="0.01" name="total_amount" class="lm-wiz-input" value="{{ old('total_amount', $loanRow->total_amount ?? 0) }}" min="0"></div>
                        <div class="lm-wiz-field"><label>Paid Amount</label><input type="number" step="0.01" name="paid_amount" class="lm-wiz-input" value="{{ old('paid_amount', $loanRow->paid_amount ?? 0) }}" min="0"></div>
                        <div class="lm-wiz-field"><label>Balance</label><input type="number" step="0.01" name="balance_amount" class="lm-wiz-input" value="{{ old('balance_amount', $loanRow->balance_amount ?? 0) }}" min="0"></div>
                        <div class="lm-wiz-field"><label>Penalty</label><input type="number" step="0.01" name="penalty_amount" class="lm-wiz-input" value="{{ old('penalty_amount', $loanRow->penalty_amount ?? 0) }}" min="0"></div>
                        <div class="lm-wiz-field"><label>Discount</label><input type="number" step="0.01" name="discount_amount" class="lm-wiz-input" value="{{ old('discount_amount', $loanRow->discount_amount ?? 0) }}" min="0"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lm-wiz-bottombar" id="wizBottombar">
            <button type="button" class="lm-wiz-btn-back" id="wizBtnBack" style="display:none;">
                <i class="fa fa-arrow-left"></i>
            </button>
            <button type="button" class="lm-wiz-btn-ghost" id="wizBtnCancel">
                <i class="fa fa-times"></i> Cancel
            </button>
            <button type="button" class="lm-wiz-btn-primary lm-wiz-btn-next" id="wizBtnNext">
                Next <i class="fa fa-arrow-right"></i>
            </button>
            <button type="button" class="lm-wiz-btn-submit" id="wizBtnSubmit" style="display:none;">
                <i class="fa fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</section>

<div class="wiz-photo-choice-overlay" id="wizPhotoChoiceOverlay" aria-hidden="true">
    <div class="wiz-photo-choice-box">
        <div class="wiz-photo-choice-title"><i class="fa fa-camera"></i> Choose Photo Source</div>
        <div class="wiz-photo-choice-actions">
            <button type="button" class="btn btn-primary btn-sm" id="wizPhotoChoiceCamera"><i class="fa fa-camera"></i> Take</button>
            <button type="button" class="btn btn-default btn-sm" id="wizPhotoChoiceUpload"><i class="fa fa-image"></i> Upload</button>
        </div>
        <button type="button" class="btn btn-link btn-block btn-sm" id="wizPhotoChoiceCancel">Cancel</button>
    </div>
</div>

<div class="wiz-product-crop-overlay" id="wizProductCropOverlay" aria-hidden="true">
    <div class="wiz-product-crop-box">
        <div class="wiz-product-crop-head">
            <div class="wiz-product-crop-title"><i class="fa fa-crop"></i> Crop Product Photo</div>
            <button type="button" class="btn btn-default btn-sm" id="wizProductCropClose"><i class="fa fa-times"></i></button>
        </div>
        <canvas class="wiz-product-crop-canvas" id="wizProductCropCanvas"></canvas>
        <div class="wiz-product-crop-status" id="wizProductCropStatus">Drag the box or corners to keep only the product label.</div>
        <div class="wiz-product-crop-actions">
            <button type="button" class="btn btn-default btn-sm" id="wizProductCropReset"><i class="fa fa-refresh"></i> Reset</button>
            <button type="button" class="btn btn-default btn-sm" id="wizProductCropOriginal"><i class="fa fa-image"></i> Use Original</button>
            <button type="button" class="btn btn-primary btn-sm" id="wizProductCropUse"><i class="fa fa-check"></i> Use Crop & OCR</button>
        </div>
    </div>
</div>
<div class="wiz-product-crop-overlay" id="wizCustomerCropOverlay" aria-hidden="true">
    <div class="wiz-product-crop-box">
        <div class="wiz-product-crop-head">
            <div class="wiz-product-crop-title" id="wizCustomerCropTitle"><i class="fa fa-crop"></i> Crop Customer Photo</div>
            <button type="button" class="btn btn-default btn-sm" id="wizCustomerCropClose"><i class="fa fa-times"></i></button>
        </div>
        <canvas class="wiz-product-crop-canvas" id="wizCustomerCropCanvas"></canvas>
        <div class="wiz-product-crop-status" id="wizCustomerCropStatus">Drag the box or corners to keep the important area.</div>
        <div class="wiz-product-crop-actions">
            <button type="button" class="btn btn-default btn-sm" id="wizCustomerCropReset"><i class="fa fa-refresh"></i> Reset</button>
            <button type="button" class="btn btn-default btn-sm" id="wizCustomerCropOriginal"><i class="fa fa-image"></i> Use Original</button>
            <button type="button" class="btn btn-primary btn-sm" id="wizCustomerCropUse"><i class="fa fa-check"></i> Use Crop</button>
        </div>
    </div>
</div>
@endsection

@section('loan_js')
<script>
(function ($) {
    var wizCurrentStep = 0;
    var wizTotalSteps = 5;
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
        if (productTotal <= 0) {
            return;
        }

        var enteredDownPayment = wizParseNum($('[name="down_payment"]').val());
        var effectiveDeposit = wizEffectiveDepositAmount();
        if (enteredDownPayment <= 0 && effectiveDeposit > 0) {
            $('[name="down_payment"]').val(wizFormatMoney(effectiveDeposit));
        }

        var principalAfterDeposit = Math.max(0, productTotal - effectiveDeposit);
        $('[name="principal_amount"]').val(wizFormatMoney(principalAfterDeposit)).trigger('change');
    }

    function wizShowPhotoChoice(cameraSelector, uploadSelector) {
        wizPhotoChoice = {
            camera: cameraSelector,
            upload: uploadSelector
        };
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
        $('#wizSummaryDp').text('$' + wizFormatMoney(downPayment));
        $('#wizSummaryBalance').text('$' + wizFormatMoney(balance));
        $('#wizRevInterestAmount').text('$' + wizFormatMoney(interest));
        $('#wizRevPaidAmount').text('$' + wizFormatMoney(paid));
        $('#wizRevBalanceAmount').text('$' + wizFormatMoney(balance));
        $('#wizRevPenaltyDiscount').text('$' + wizFormatMoney(penalty) + ' / $' + wizFormatMoney(discountAmount));
    }

    function wizRemoveItemFromScreen($row) {
        $row.remove();
        if ($('#wizItemsList .wiz-item-row').length === 0) {
            $('#wizItemsList').html('<div style="text-align:center; padding:20px; color:#94a3b8;" id="wizItemsEmpty">No products yet. Click "Add Item" to add products.</div>');
        }
        wizEditRecalcTotals();
        wizPopulateReview();
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
                'delete_items[]': itemId
            },
            dataType: 'json',
            success: function (res) {
                wizRemoveItemFromScreen($row);
                if (window.toastr) toastr.success(res.message || 'Item removed.');
                setTimeout(function () {
                    window.location.href = (res.data && res.data.redirect_url) || window.location.href;
                }, 500);
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
        var $thumb = $row.find('.wiz-item-photo-thumb');
        var $headerThumb = $row.find('.wiz-item-header-thumb');
        var $status = $row.find('.wiz-item-photo-status');
        var $path = $row.find('.wiz-item-photo-path');

        if (reference !== undefined) {
            $path.val(reference || '').trigger('input');
        }

        if (previewSrc) {
            $thumb.html('<img src="' + previewSrc + '" alt="">');
            $headerThumb.html('<img src="' + previewSrc + '" alt="">');
            $status.text('Photo selected');
        } else {
            $thumb.html('<i class="fa fa-image"></i>');
            $headerThumb.html('<i class="fa fa-image"></i>');
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
        wizSetItemOcrStatus($row, 'Reading product photo...');
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
                    wizSetItemOcrStatus($row, found ? 'Product fields filled automatically.' : 'OCR finished, but no matching fields were found.', false);
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

    function wizCompressItemPhoto(file, maxW, maxH, quality) {
        return new Promise(function(resolve, reject) {
            if (!window.FileReader) {
                reject(new Error('File preview is not supported in this browser.'));
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                var img = new Image();
                img.onload = function() {
                    var w = img.width;
                    var h = img.height;
                    if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                    if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }

                    var canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, w);
                    canvas.height = Math.max(1, h);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.onerror = function() { reject(new Error('Could not read selected image.')); };
                img.src = e.target.result;
            };
            reader.onerror = function() { reject(new Error('Could not read selected file.')); };
            reader.readAsDataURL(file);
        });
    }

    function wizReadFileAsDataUri(file) {
        return new Promise(function(resolve, reject) {
            var reader = new FileReader();
            reader.onload = function(e) { resolve(e.target.result); };
            reader.onerror = function() { reject(new Error('Could not read selected file.')); };
            reader.readAsDataURL(file);
        });
    }

    function wizDocIcon(fileName) {
        var name = String(fileName || '').toLowerCase();
        if (name.endsWith('.pdf')) return 'fa-file-pdf-o';
        if (name.endsWith('.doc') || name.endsWith('.docx')) return 'fa-file-word-o';
        if (name.endsWith('.csv') || name.endsWith('.xls') || name.endsWith('.xlsx')) return 'fa-file-excel-o';
        if (name.endsWith('.txt')) return 'fa-file-text-o';
        return 'fa-file-o';
    }

    function wizAddDocumentTile(dataUri, fileName, sizeBytes, isImage) {
        var index = wizDocCounter++;
        var safeName = $('<div>').text(fileName || 'Document').html();
        var sizeKb = Math.max(1, Math.round((sizeBytes || dataUri.length) / 1024));
        var $tile = $('<div class="wiz-doc-tile" data-doc-index="' + index + '"></div>');

        if (isImage) {
            $tile.append($('<img>', { src: dataUri, alt: safeName }));
        } else {
            $tile.append('<i class="fa ' + wizDocIcon(fileName) + '"></i>');
            $tile.append('<span>' + safeName + '</span>');
        }

        $tile.append('<button type="button" class="wiz-doc-remove" title="Remove document"><i class="fa fa-times"></i></button>');
        $tile.append('<span class="wiz-doc-size">' + sizeKb + 'KB</span>');
        $('#wizDocAddTile').before($tile);
        $('#wizDocHiddenFields').append($('<input>', {
            type: 'hidden',
            name: 'documents[]',
            'data-doc-index': index
        }).val(dataUri));
    }

    function wizQueueDocumentFile(file) {
        if (!file) return;
        var isImage = file.type && file.type.indexOf('image/') === 0;
        var reader = isImage
            ? wizCompressItemPhoto(file, 1400, 1400, 0.72)
            : wizReadFileAsDataUri(file);

        reader.then(function(dataUri) {
            wizAddDocumentTile(dataUri, file.name, file.size, isImage);
        }).catch(function(error) {
            if (window.toastr) {
                toastr.error((error && error.message) || 'Could not add document.');
            } else {
                alert((error && error.message) || 'Could not add document.');
            }
        });
    }

    function wizShowProductCropOverlay() {
        $('#wizProductCropOverlay').css('display', 'flex').attr('aria-hidden', 'false');
    }

    function wizHideProductCropOverlay() {
        $('#wizProductCropOverlay').hide().attr('aria-hidden', 'true');
    }

    function wizSetProductCropStatus(message, isError) {
        $('#wizProductCropStatus').text(message || '').css('color', isError ? '#dc2626' : '#64748b');
    }

    function wizStartProductCrop($row, file) {
        wizProductCropper = null;
        wizProductCropRow = $row;
        wizProductCropFile = file;
        wizShowProductCropOverlay();
        wizSetProductCropStatus('Preparing photo for crop...');

        if (!window.FileReader) {
            wizUseOriginalProductPhoto();
            return;
        }

        var reader = new FileReader();
        var image = new Image();
        reader.onload = function(event) {
            image.onload = function() {
                wizProductCropper = wizCreateProductCropper(document.getElementById('wizProductCropCanvas'), image);
                wizSetProductCropStatus('Drag the box or corners to keep only the product label.');
            };
            image.onerror = function() {
                wizSetProductCropStatus('This browser cannot preview this image. Using original photo.', true);
                wizUseOriginalProductPhoto();
            };
            image.src = event.target.result;
        };
        reader.onerror = function() {
            wizSetProductCropStatus('This browser cannot preview this image. Using original photo.', true);
            wizUseOriginalProductPhoto();
        };
        reader.readAsDataURL(file);
    }

    function wizCancelProductCrop() {
        wizProductCropper = null;
        wizProductCropRow = null;
        wizProductCropFile = null;
        wizHideProductCropOverlay();
    }

    function wizUseOriginalProductPhoto() {
        if (!wizProductCropRow || !wizProductCropFile) {
            wizCancelProductCrop();
            return;
        }
        var $row = wizProductCropRow;
        var file = wizProductCropFile;
        wizCancelProductCrop();
        wizSetItemOcrStatus($row, 'Preparing product photo...');
        wizCompressItemPhoto(file, 1400, 1400, 0.72).then(function(dataUri) {
            wizApplyProductPhotoData($row, dataUri);
        }).catch(function(error) {
            wizSetItemOcrStatus($row, error.message || 'Failed to prepare photo.', true);
        });
    }

    function wizUseCroppedProductPhoto() {
        if (!wizProductCropper || !wizProductCropRow) {
            wizUseOriginalProductPhoto();
            return;
        }
        var $row = wizProductCropRow;
        wizSetProductCropStatus('Cropping photo...');
        wizProductCropper.getDataUrl(function(dataUri) {
            wizCancelProductCrop();
            wizSetItemOcrStatus($row, 'Preparing cropped product photo...');
            wizApplyProductPhotoData($row, dataUri);
        });
    }

    function wizCreateProductCropper(canvas, image) {
        var context = canvas.getContext('2d');
        var maxWidth = Math.min(760, image.width);
        var scale = maxWidth / image.width;
        var canvasWidth = Math.round(image.width * scale);
        var canvasHeight = Math.round(image.height * scale);
        var dragMode = null;
        var lastPoint = null;
        var handleSize = 16;
        var crop = {};

        canvas.width = canvasWidth;
        canvas.height = canvasHeight;

        function reset() {
            crop = {
                x: Math.round(canvasWidth * 0.08),
                y: Math.round(canvasHeight * 0.12),
                width: Math.round(canvasWidth * 0.84),
                height: Math.round(canvasHeight * 0.72)
            };
            constrain();
            draw();
        }

        function drawHandle(x, y) {
            context.fillStyle = '#2563eb';
            context.fillRect(x - handleSize / 2, y - handleSize / 2, handleSize, handleSize);
        }

        function draw() {
            context.clearRect(0, 0, canvasWidth, canvasHeight);
            context.drawImage(image, 0, 0, canvasWidth, canvasHeight);
            context.fillStyle = 'rgba(15, 23, 42, 0.45)';
            context.fillRect(0, 0, canvasWidth, canvasHeight);
            context.drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, crop.x, crop.y, crop.width, crop.height);
            context.strokeStyle = '#2563eb';
            context.lineWidth = 3;
            context.strokeRect(crop.x, crop.y, crop.width, crop.height);
            drawHandle(crop.x, crop.y);
            drawHandle(crop.x + crop.width, crop.y);
            drawHandle(crop.x, crop.y + crop.height);
            drawHandle(crop.x + crop.width, crop.y + crop.height);
        }

        function pointFor(event) {
            var source = event.touches && event.touches.length ? event.touches[0] : event;
            var rect = canvas.getBoundingClientRect();
            return {
                x: (source.clientX - rect.left) * (canvas.width / rect.width),
                y: (source.clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        function getMode(point) {
            var handles = {
                nw: {x: crop.x, y: crop.y},
                ne: {x: crop.x + crop.width, y: crop.y},
                sw: {x: crop.x, y: crop.y + crop.height},
                se: {x: crop.x + crop.width, y: crop.y + crop.height}
            };
            for (var mode in handles) {
                if (Math.abs(point.x - handles[mode].x) <= handleSize && Math.abs(point.y - handles[mode].y) <= handleSize) {
                    return mode;
                }
            }
            return point.x >= crop.x && point.x <= crop.x + crop.width && point.y >= crop.y && point.y <= crop.y + crop.height ? 'move' : null;
        }

        function constrain() {
            var minSize = 40;
            crop.width = Math.max(minSize, crop.width);
            crop.height = Math.max(minSize, crop.height);
            crop.x = Math.max(0, Math.min(crop.x, canvasWidth - crop.width));
            crop.y = Math.max(0, Math.min(crop.y, canvasHeight - crop.height));
            if (crop.x + crop.width > canvasWidth) crop.width = canvasWidth - crop.x;
            if (crop.y + crop.height > canvasHeight) crop.height = canvasHeight - crop.y;
        }

        function resize(mode, dx, dy) {
            if (mode.indexOf('n') !== -1) { crop.y += dy; crop.height -= dy; }
            if (mode.indexOf('s') !== -1) crop.height += dy;
            if (mode.indexOf('w') !== -1) { crop.x += dx; crop.width -= dx; }
            if (mode.indexOf('e') !== -1) crop.width += dx;
        }

        function start(event) {
            var point = pointFor(event);
            dragMode = getMode(point);
            lastPoint = point;
            if (dragMode) event.preventDefault();
        }

        function move(event) {
            if (!dragMode) return;
            var point = pointFor(event);
            var dx = point.x - lastPoint.x;
            var dy = point.y - lastPoint.y;
            if (dragMode === 'move') {
                crop.x += dx;
                crop.y += dy;
            } else {
                resize(dragMode, dx, dy);
            }
            constrain();
            lastPoint = point;
            draw();
            event.preventDefault();
        }

        function end() {
            dragMode = null;
            lastPoint = null;
        }

        canvas.onmousedown = start;
        canvas.onmousemove = move;
        canvas.onmouseup = end;
        canvas.onmouseleave = end;
        canvas.ontouchstart = start;
        canvas.ontouchmove = move;
        canvas.ontouchend = end;
        reset();

        return {
            reset: reset,
            getDataUrl: function(callback) {
                var cropWidth = Math.round(crop.width / scale);
                var cropHeight = Math.round(crop.height / scale);
                var outputScale = Math.min(1, 1600 / Math.max(cropWidth, cropHeight));
                var output = document.createElement('canvas');
                output.width = Math.max(1, Math.round(cropWidth * outputScale));
                output.height = Math.max(1, Math.round(cropHeight * outputScale));
                output.getContext('2d').drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, 0, 0, output.width, output.height);
                callback(output.toDataURL('image/jpeg', 0.88));
            }
        };
    }

    function wizSetCustomerCropStatus(message, isError) {
        $('#wizCustomerCropStatus').text(message || '').css('color', isError ? '#dc2626' : '#64748b');
    }

    function wizShowCustomerCropOverlay(target) {
        var title = target === 'profile' ? 'Crop Profile Photo' : 'Crop ID Card Photo';
        var hint = target === 'profile'
            ? 'Drag the box or corners to keep the customer face centered.'
            : 'Drag the box or corners to keep only the ID card.';
        $('#wizCustomerCropTitle').html('<i class="fa fa-crop"></i> ' + title);
        $('#wizCustomerCropUse').html(target === 'id_card' ? '<i class="fa fa-check"></i> Use Crop & OCR' : '<i class="fa fa-check"></i> Use Crop');
        $('#wizCustomerCropOverlay').css('display', 'flex').attr('aria-hidden', 'false');
        wizSetCustomerCropStatus(hint, false);
    }

    function wizHideCustomerCropOverlay() {
        $('#wizCustomerCropOverlay').hide().attr('aria-hidden', 'true');
    }

    function wizStartCustomerCrop(target, file) {
        wizCustomerCropper = null;
        wizCustomerCropFile = file;
        wizCustomerCropTarget = target;
        wizShowCustomerCropOverlay(target);
        wizSetCustomerCropStatus('Preparing photo for crop...');

        if (!window.FileReader) {
            wizUseOriginalCustomerPhoto();
            return;
        }

        var reader = new FileReader();
        var image = new Image();
        reader.onload = function(event) {
            image.onload = function() {
                wizCustomerCropper = wizCreateProductCropper(document.getElementById('wizCustomerCropCanvas'), image);
                wizSetCustomerCropStatus(target === 'profile' ? 'Drag the box or corners to keep the customer face centered.' : 'Drag the box or corners to keep only the ID card.');
            };
            image.onerror = function() {
                wizSetCustomerCropStatus('This browser cannot preview this image. Using original photo.', true);
                wizUseOriginalCustomerPhoto();
            };
            image.src = event.target.result;
        };
        reader.onerror = function() {
            wizSetCustomerCropStatus('This browser cannot preview this image. Using original photo.', true);
            wizUseOriginalCustomerPhoto();
        };
        reader.readAsDataURL(file);
    }

    function wizCancelCustomerCrop() {
        wizCustomerCropper = null;
        wizCustomerCropFile = null;
        wizCustomerCropTarget = null;
        wizHideCustomerCropOverlay();
    }

    function wizApplyCustomerImage(target, dataUri) {
        if (target === 'profile') {
            $('#wizCustomerProfileImage').val(dataUri);
            $('#wizCustomerProfilePreview').html('<img src="' + dataUri + '" alt="Customer profile">');
            $('#wizCustomerProfileStatus').text('Profile photo ready.').css('color', '#64748b');
            return;
        }

        $('#wizIdCardImage').val(dataUri);
        $('#wizIdCardPreview').html('<img src="' + dataUri + '" alt="ID card">');
        $('#wizIdCardReExtractBtn').data('image-url', '');
        wizScanIdCard(dataUri);
    }

    function wizUseOriginalCustomerPhoto() {
        if (!wizCustomerCropFile || !wizCustomerCropTarget) {
            wizCancelCustomerCrop();
            return;
        }
        var target = wizCustomerCropTarget;
        var file = wizCustomerCropFile;
        wizCancelCustomerCrop();
        if (target === 'id_card') {
            $('#wizIdCardOcrStatus').text('Preparing ID card photo...').css('color', '#64748b');
        } else {
            $('#wizCustomerProfileStatus').text('Preparing profile photo...').css('color', '#64748b');
        }
        wizCompressItemPhoto(file, target === 'id_card' ? 1600 : 1000, target === 'id_card' ? 1000 : 1000, 0.76).then(function(dataUri) {
            wizApplyCustomerImage(target, dataUri);
        });
    }

    function wizUseCroppedCustomerPhoto() {
        if (!wizCustomerCropper || !wizCustomerCropTarget) {
            wizUseOriginalCustomerPhoto();
            return;
        }
        var target = wizCustomerCropTarget;
        wizSetCustomerCropStatus('Cropping photo...');
        wizCustomerCropper.getDataUrl(function(dataUri) {
            wizCancelCustomerCrop();
            wizApplyCustomerImage(target, dataUri);
        });
    }

    function wizFillCustomerIfEmpty(selector, value) {
        if (!value) return;
        var $field = $(selector);
        if (!$field.length) return;
        if (!String($field.val() || '').trim()) {
            $field.val(value).trigger('input').trigger('change');
        }
    }

    function wizApplyIdCardFields(fields, rawText) {
        fields = fields || {};
        $('#wizIdCardOcrRawText').val(rawText || '');
        $('#wizIdCardOcrNumber').val(fields.id_card_number || '');
        $('#wizIdCardOcrKhmerName').val(fields.khmer_name || '');
        $('#wizIdCardOcrEnglishName').val(fields.english_name || '');
        $('#wizIdCardOcrAddress').val(fields.address || '');
        wizFillCustomerIfEmpty('[name="id_card_number"]', fields.id_card_number);
        if (fields.khmer_name) {
            $('[name="customer_khmer_name"]').val(fields.khmer_name).trigger('input').trigger('change');
        }
        if (fields.english_name) {
            $('[name="customer_english_name"]').val(fields.english_name).trigger('input').trigger('change');
        }
        if (fields.khmer_name || fields.english_name) {
            wizSyncCustomerNameFromKhmer();
        }
        wizFillCustomerIfEmpty('[name="customer_address_snapshot"]', fields.address);
    }

    function wizScanIdCard(dataUri) {
        $('#wizIdCardOcrStatus').text('Reading ID card...').css('color', '#64748b');
        $.ajax({
            url: wizUrls.scanIdCard,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id_card_image: dataUri
            },
            dataType: 'json',
            success: function(res) {
                if (res && res.success) {
                    var data = res.data || {};
                    wizApplyIdCardFields(data.fields || {}, data.raw_text || '');
                    $('#wizIdCardOcrStatus').text(Object.keys(data.fields || {}).length ? 'ID card text filled automatically.' : 'OCR finished, but no matching fields were found.').css('color', '#64748b');
                } else {
                    $('#wizIdCardOcrStatus').text((res && res.message) || 'OCR unavailable.').css('color', '#dc2626');
                }
            },
            error: function(xhr) {
                $('#wizIdCardOcrStatus').text((xhr.responseJSON && xhr.responseJSON.message) || 'OCR failed.').css('color', '#dc2626');
            }
        });
    }

    function wizImageUrlToDataUri(url) {
        return new Promise(function(resolve, reject) {
            if (!url) {
                reject(new Error('No ID card photo found.'));
                return;
            }

            fetch(url, { credentials: 'same-origin' })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Unable to read ID card photo.');
                    }

                    return response.blob();
                })
                .then(function(blob) {
                    var reader = new FileReader();
                    reader.onload = function(event) { resolve(event.target.result); };
                    reader.onerror = function() { reject(new Error('Unable to prepare ID card photo.')); };
                    reader.readAsDataURL(blob);
                })
                .catch(reject);
        });
    }

    $('#wizIdCardReExtractBtn').on('click', function() {
        var $button = $(this);
        var dataUri = $('#wizIdCardImage').val();
        var imageUrl = $button.data('image-url') || '';

        $button.prop('disabled', true);
        $('#wizIdCardOcrStatus').text('Re-extracting ID card text...').css('color', '#64748b');

        var request = dataUri ? Promise.resolve(dataUri) : wizImageUrlToDataUri(imageUrl);

        request
            .then(function(imageDataUri) {
                $('#wizIdCardImage').val(imageDataUri);
                wizScanIdCard(imageDataUri);
            })
            .catch(function(error) {
                $('#wizIdCardOcrStatus').text(error.message || 'Unable to re-extract ID card text.').css('color', '#dc2626');
            })
            .finally(function() {
                $button.prop('disabled', false);
            });
    });

    function wizAddressItemCode(item, fallback) {
        return item.code || item.value || item.id || item.province_code || item.district_code || item.commune_code || item.village_code || fallback || '';
    }

    function wizAddressItemName(item) {
        return item.name || item.text || item.label || item.name_kh || item.khmer_name || item.province_kh || item.district_kh || item.commune_kh || item.village_kh || item.province_en || item.district_en || item.commune_en || item.village_en || '';
    }

    function wizSetAddressStatus(message, isError) {
        $('#wizAddressLoadStatus').text(message || '').css('color', isError ? '#dc2626' : '#64748b');
    }

    function wizSetAddressName($select, hiddenSelector) {
        var text = $select.find('option:selected').text();
        if (!text || text === '-- Select --') text = '';
        $(hiddenSelector).val(text);
    }

    function wizFillAddressSelect($select, items, selectedCode, selectedName) {
        $select.empty().append('<option value="">-- Select --</option>');
        if (items && !Array.isArray(items)) {
            items = Object.keys(items).map(function(key) {
                return typeof items[key] === 'object' ? items[key] : { code: key, name: items[key] };
            });
        }
        (items || []).forEach(function(item, index) {
            var code = wizAddressItemCode(item, index);
            var name = wizAddressItemName(item) || code;
            var selected = (selectedCode && String(code) === String(selectedCode)) || (!selectedCode && selectedName && String(name) === String(selectedName));
            $select.append('<option value="' + $('<div>').text(code).html() + '"' + (selected ? ' selected' : '') + '>' + $('<div>').text(name).html() + '</option>');
        });
        $select.prop('disabled', false);
    }

    function wizLoadAddressSelect(url, params, $select, selectedCode, selectedName, done) {
        wizSetAddressStatus('Loading address list...');
        $.get(url, params || {}, function(res) {
            if (res && res.success) {
                wizFillAddressSelect($select, res.items || [], selectedCode, selectedName);
                wizSetAddressStatus('');
                if (done) done();
            } else {
                wizSetAddressStatus((res && res.msg) || 'Unable to load address list.', true);
            }
        }).fail(function(xhr) {
            wizSetAddressStatus((xhr.responseJSON && xhr.responseJSON.msg) || 'Unable to load address list.', true);
        });
    }

    function wizResetAddressAfter(level) {
        if (level <= 1) {
            $('#wizDistrictSelect').prop('disabled', true).html('<option value="">-- Select --</option>');
            $('#wizDistrictName').val('');
        }
        if (level <= 2) {
            $('#wizCommuneSelect').prop('disabled', true).html('<option value="">-- Select --</option>');
            $('#wizCommuneName').val('');
        }
        if (level <= 3) {
            $('#wizVillageSelect').prop('disabled', true).html('<option value="">-- Select --</option>');
            $('#wizVillageName').val('');
        }
    }

    function wizInitAddressSelects() {
        var $province = $('#wizProvinceSelect');
        var provinceCode = $province.data('current-code') || '';
        var provinceName = $province.data('current-name') || '';
        var districtCode = $('#wizDistrictSelect').data('current-code') || '';
        var districtName = $('#wizDistrictSelect').data('current-name') || '';
        var communeCode = $('#wizCommuneSelect').data('current-code') || '';
        var communeName = $('#wizCommuneSelect').data('current-name') || '';
        var villageCode = $('#wizVillageSelect').data('current-code') || '';
        var villageName = $('#wizVillageSelect').data('current-name') || '';

        wizLoadAddressSelect(wizUrls.provinces, {}, $province, provinceCode, provinceName, function() {
            wizSetAddressName($province, '#wizProvinceName');
            if ($province.val()) {
                wizLoadAddressSelect(wizUrls.districts, { province_code: $province.val() }, $('#wizDistrictSelect'), districtCode, districtName, function() {
                    wizSetAddressName($('#wizDistrictSelect'), '#wizDistrictName');
                    if ($('#wizDistrictSelect').val()) {
                        wizLoadAddressSelect(wizUrls.communes, { district_code: $('#wizDistrictSelect').val() }, $('#wizCommuneSelect'), communeCode, communeName, function() {
                            wizSetAddressName($('#wizCommuneSelect'), '#wizCommuneName');
                            if ($('#wizCommuneSelect').val()) {
                                wizLoadAddressSelect(wizUrls.villages, { commune_code: $('#wizCommuneSelect').val() }, $('#wizVillageSelect'), villageCode, villageName, function() {
                                    wizSetAddressName($('#wizVillageSelect'), '#wizVillageName');
                                });
                            }
                        });
                    }
                });
            }
        });
    }

    function wizGoStep(step) {
        if (step < 0 || step >= wizTotalSteps) return;
        if (step > wizCurrentStep) {
            if (!wizValidateStep(wizCurrentStep)) return;
        }
        wizCurrentStep = step;

        $('.lm-wiz-panel').removeClass('active');
        $('.lm-wiz-panel[data-panel="' + step + '"]').addClass('active');

        $('.lm-wiz-progress .lm-wiz-step').each(function (i) {
            $(this).removeClass('active done');
            if (i < step) $(this).addClass('done');
            if (i === step) $(this).addClass('active');
        });
        $('.lm-wiz-step-labels span').each(function (i) {
            $(this).removeClass('active done');
            if (i < step) $(this).addClass('done');
            if (i === step) $(this).addClass('active');
        });

        $('#wizBtnBack').toggle(step > 0);
        $('#wizBtnNext').toggle(step < wizTotalSteps - 1);
        $('#wizBtnSubmit').toggle(step === wizTotalSteps - 1);
        $('#wizBtnCancel').toggle(step < wizTotalSteps - 1);

        $('#wizStepsWrap').scrollTop(0);

        if (step === wizTotalSteps - 1) wizPopulateReview();
    }

    function wizPopulateReview() {
        wizSyncCustomerNameFromKhmer();
        var getVal = function (sel) { var el = document.querySelector(sel); return el ? el.value : ''; };
        var getText = function (sel) {
            var el = document.querySelector(sel);
            if (!el) return '';
            if (el.tagName === 'SELECT') {
                return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '';
            }
            return el.value;
        };

        $('#wizRevDate').text(getVal('[name="loan_date"]') || '-');
        $('#wizRevLocation').text(getText('[name="business_location_id"]') || '-');
        $('#wizRevCollector').text(getText('[name="assigned_collector_id"]') || '-');
        $('#wizRevStatus').text(getText('[name="status"]') || '-');
        $('#wizRevMaturity').text(getVal('[name="maturity_date"]') || '-');
        $('#wizRevSourceType').text(getText('[name="source_type"]') || '-');
        $('#wizRevInvoice').text(getVal('[name="source_invoice_no"]') || '-');
        $('#wizRevCustName').text(getVal('[name="customer_name_snapshot"]') || '-');
        $('#wizRevCustPhone').text(getVal('[name="customer_phone_snapshot"]') || '-');
        $('#wizRevCustKhmer').text(getVal('[name="customer_khmer_name"]') || '-');
        $('#wizRevCustEnglish').text(getVal('[name="customer_english_name"]') || '-');
        $('#wizRevCustIdCard').text(getVal('[name="id_card_number"]') || '-');
        $('#wizRevOccupation').text(getVal('[name="occupation"]') || '-');
        $('#wizRevGuarantor').text(getVal('[name="guarantor_name"]') || '-');
        $('#wizRevAddress').text(getVal('[name="customer_address_snapshot"]') || '-');

        var principal = wizParseNum(getVal('[name="principal_amount"]'));
        var rate = wizParseNum(getVal('[name="interest_rate"]'));
        var dur = parseInt(getVal('[name="installment_count"]')) || 0;
        var freq = getText('[name="payment_frequency"]');
        var dp = wizParseNum(getVal('[name="down_payment"]'));

        $('#wizRevPrincipal').text('$' + wizFormatMoney(principal));
        $('#wizRevInterest').text(wizFormatMoney(rate) + '%');
        $('#wizRevDuration').text(dur + ' months / ' + freq);
        $('#wizRevDownPayment').text('$' + wizFormatMoney(dp));
        $('#wizRevCollectionStatus').text(getText('[name="collection_status"]') || '-');
        $('#wizRevRiskLevel').text(getText('[name="risk_level"]') || '-');
        $('#wizRevCollectionPriority').text(getVal('[name="collection_priority"]') || '-');
        $('#wizRevNextFollowup').text(getVal('[name="next_followup_at"]') || '-');
        $('#wizRevPtp').text((getVal('[name="ptp_date"]') || '-') + ' / $' + wizFormatMoney(getVal('[name="ptp_amount"]')));
        $('#wizRevLastContact').text(getVal('[name="last_contact_result"]') || '-');
        wizEditRecalcTotals();

        var itemsHtml = '';
        $('#wizItemsList .wiz-item-row').each(function (i) {
            var name = $(this).find('[name$="[product_name_snapshot]"], [name="product_name_snapshot"]').val();
            if (!name) name = $(this).find('.wiz-item-header strong:first').text().trim() || 'Unnamed';
            var total = wizParseNum($(this).find('.wiz-item-line-total').text().replace('$', ''));
            itemsHtml += '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f5f5f5;">' +
                '<span>#' + (i + 1) + ' ' + name + '</span>' +
                '<span style="font-weight:600;">$' + wizFormatMoney(total) + '</span></div>';
        });
        $('#wizRevProducts').html(itemsHtml || '<div style="color:#94a3b8; font-style:italic;">No products added</div>');
    }

    function wizValidateStep(step) {
        var valid = true;
        var firstInvalid = null;

        if (step === 0) {
            var dateInput = $('[name="loan_date"]');
            if (!dateInput.val()) {
                dateInput.addClass('has-error');
                valid = false;
                if (!firstInvalid) firstInvalid = dateInput;
            } else {
                dateInput.removeClass('has-error');
            }
        }

        if (step === 1) {
            var khmerFallback = String($('#wizIdCardOcrKhmerName').val() || $('[name="customer_name_snapshot"]').val() || $('[name="customer_english_name"]').val() || '').trim();
            var englishFallback = String($('#wizIdCardOcrEnglishName').val() || $('[name="customer_khmer_name"]').val() || $('[name="customer_name_snapshot"]').val() || '').trim();
            if (!$('[name="customer_khmer_name"]').val().trim() && khmerFallback) {
                $('[name="customer_khmer_name"]').val(khmerFallback).trigger('input').trigger('change');
            }
            if (!$('[name="customer_english_name"]').val().trim() && englishFallback) {
                $('[name="customer_english_name"]').val(englishFallback).trigger('input').trigger('change');
            }
            wizSyncCustomerNameFromKhmer();
            var khmerNameInput = $('[name="customer_khmer_name"]');
            khmerNameInput.removeClass('has-error');
            var englishNameInput = $('[name="customer_english_name"]');
            englishNameInput.removeClass('has-error');
            var phoneInput = $('[name="customer_phone_snapshot"]');
            if (!phoneInput.val().trim()) {
                phoneInput.addClass('has-error');
                valid = false;
                if (!firstInvalid) firstInvalid = phoneInput;
            } else {
                phoneInput.removeClass('has-error');
            }
        }

        $('.lm-wiz-field .has-error').each(function () {
            var parent = $(this).closest('.lm-wiz-field');
            if (parent.length) {
                if (parent.find('.lm-wiz-field-error').length === 0) {
                    parent.append('<span class="lm-wiz-field-error">This field is required</span>');
                }
            }
        });

        if (!valid && firstInvalid) {
            firstInvalid.focus();
        }

        return valid;
    }

    $('.lm-wiz-progress .lm-wiz-step, .lm-wiz-step-labels span').on('click', function () {
        var step = parseInt($(this).data('step')) || $(this).index();
        if (step >= 0 && step < wizTotalSteps) wizGoStep(step);
    });

    $('#wizBtnBack').on('click', function () { wizGoStep(wizCurrentStep - 1); });
    $('#wizBtnNext').on('click', function () { wizGoStep(wizCurrentStep + 1); });

    $('#wizBtnCancel').on('click', function () {
        @if($isEmbeddedModal)
        var cancelUrl = "{{ route('loan-management.loans.view', $loanRow->id) }}?_lm_modal=1";
        window.jQuery.ajax({
            url: cancelUrl, dataType: 'html',
            success: function(html) { window.jQuery('.view_modal').html(html); },
            error: function() { window.location.href = cancelUrl; }
        });
        @else
        window.location.href = "{{ route('loan-management.loans.view', $loanRow->id) }}";
        @endif
    });

    $(document).on('click', '.wiz-photo-choice-btn', function () {
        wizShowPhotoChoice($(this).data('camera'), $(this).data('upload'));
    });

    $(document).on('click', '.wiz-product-photo-choice-btn', function () {
        var $row = $(this).closest('.wiz-item-row');
        wizShowPhotoChoice($row.find('.wiz-item-photo-camera'), $row.find('.wiz-item-photo-upload'));
    });

    $('#wizPhotoChoiceCamera').on('click', function () { wizChoosePhotoSource('camera'); });
    $('#wizPhotoChoiceUpload').on('click', function () { wizChoosePhotoSource('upload'); });
    $('#wizPhotoChoiceCancel, #wizPhotoChoiceOverlay').on('click', function (event) {
        if (event.target !== this) return;
        wizHidePhotoChoice();
        wizPhotoChoice = null;
    });

    $(document).on('input change', '.lm-wiz-input', function () {
        $(this).removeClass('has-error');
        $(this).closest('.lm-wiz-field').find('.lm-wiz-field-error').remove();
        var $row = $(this).closest('.wiz-item-row');
        if ($row.length) {
            var name = $row.find('[name$="[product_name_snapshot]"], [name="product_name_snapshot"]').val() || 'New product';
            var sku = $row.find('[name$="[sku_snapshot]"], [name="sku_snapshot"]').val() || '-';
            var imei = $row.find('[name$="[imei_snapshot]"], [name="imei_snapshot"]').val() || '-';
            var serial = $row.find('[name$="[serial_number_snapshot]"], [name="serial_number_snapshot"]').val() || '-';
            var qty = $row.find('[name$="[qty]"], [name="qty"]').val() || '0';
            $row.find('.wiz-item-header strong:first').text(name);
            $row.find('.wiz-item-header small:first').text('SKU: ' + sku + ' · IMEI: ' + imei + ' · Serial: ' + serial + ' · Qty: ' + qty);
        }
        wizEditRecalcTotals();
    });

    $(document).on('change', '.wiz-item-photo-input', function () {
        var input = this;
        var file = input.files && input.files[0];
        var $row = $(input).closest('.wiz-item-row');
        if (!file) return;
        wizSetItemOcrStatus($row, 'Choose crop area before OCR...');
        wizStartProductCrop($row, file);
        input.value = '';
    });

    $(document).on('change', '.wiz-item-photo-path', function () {
        var value = ($(this).val() || '').trim();
        var canPreview = /^https?:\/\//i.test(value) || value.indexOf('data:image/') === 0 || value.indexOf('/') === 0;
        if (canPreview) {
            wizSetItemPhoto($(this).closest('.wiz-item-row'), value);
        }
    });

    $('#wizProductCropClose').on('click', wizCancelProductCrop);
    $('#wizProductCropOriginal').on('click', wizUseOriginalProductPhoto);
    $('#wizProductCropUse').on('click', wizUseCroppedProductPhoto);
    $('#wizProductCropReset').on('click', function () {
        if (wizProductCropper) {
            wizProductCropper.reset();
            wizSetProductCropStatus('Crop reset. Drag the box or corners to adjust.');
        }
    });

    $('#wizCustomerProfileCamera, #wizCustomerProfileUpload').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        wizStartCustomerCrop('profile', file);
        this.value = '';
    });

    $('#wizIdCardCamera, #wizIdCardUpload').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        $('#wizIdCardOcrStatus').text('Choose crop area before OCR...').css('color', '#64748b');
        wizStartCustomerCrop('id_card', file);
        this.value = '';
    });

    $('#wizCustomerCropClose').on('click', wizCancelCustomerCrop);
    $('#wizCustomerCropOriginal').on('click', wizUseOriginalCustomerPhoto);
    $('#wizCustomerCropUse').on('click', wizUseCroppedCustomerPhoto);
    $('#wizCustomerCropReset').on('click', function () {
        if (wizCustomerCropper) {
            wizCustomerCropper.reset();
            wizSetCustomerCropStatus('Crop reset. Drag the box or corners to adjust.');
        }
    });

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
            '<div class="wiz-doc-link-row">' +
                '<input type="url" name="document_links[]" class="lm-wiz-input" placeholder="Paste document link">' +
                '<button type="button" class="btn btn-default wiz-doc-link-remove" title="Remove link"><i class="fa fa-times"></i></button>' +
            '</div>'
        );
    });

    $(document).on('click', '.wiz-doc-link-remove', function () {
        $(this).closest('.wiz-doc-link-row').remove();
    });

    document.addEventListener('paste', function (event) {
        if (!$(event.target).closest('.lm-edit-wizard').length) return;
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
        if (this.value) {
            wizLoadAddressSelect(wizUrls.districts, { province_code: this.value }, $('#wizDistrictSelect'));
        }
    });

    $('#wizDistrictSelect').on('change', function () {
        wizSetAddressName($(this), '#wizDistrictName');
        wizResetAddressAfter(2);
        if (this.value) {
            wizLoadAddressSelect(wizUrls.communes, { district_code: this.value }, $('#wizCommuneSelect'));
        }
    });

    $('#wizCommuneSelect').on('change', function () {
        wizSetAddressName($(this), '#wizCommuneName');
        wizResetAddressAfter(3);
        if (this.value) {
            wizLoadAddressSelect(wizUrls.villages, { commune_code: this.value }, $('#wizVillageSelect'));
        }
    });

    $('#wizVillageSelect').on('change', function () {
        wizSetAddressName($(this), '#wizVillageName');
    });

    wizInitAddressSelects();

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
                    '<span class="wiz-item-header-thumb"><i class="fa fa-image"></i></span>' +
                    '<span class="wiz-item-header-main"><strong>New product</strong><small style="display:block;color:#94a3b8;">SKU: - · IMEI: - · Serial: - · Qty: 1</small></span>' +
                    '<span style="font-weight:600;" class="wiz-item-line-total">$0.00</span>' +
                    '<span style="color:#94a3b8; font-size:11px;"><i class="fa fa-chevron-down"></i></span>' +
                '</div>' +
                '<div class="wiz-item-body">' +
                    '<div class="wiz-item-form-grid">' +
                        '<div class="wiz-item-field" style="grid-column:1/-1;"><label>Product Name</label><input type="text" name="edit_items[' + key + '][product_name_snapshot]" data-item-field="product_name_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>SKU</label><input type="text" name="edit_items[' + key + '][sku_snapshot]" data-item-field="sku_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>Brand</label><input type="text" name="edit_items[' + key + '][brand]" data-item-field="brand" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>Category</label><input type="text" name="edit_items[' + key + '][category]" data-item-field="category" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>IMEI</label><input type="text" name="edit_items[' + key + '][imei_snapshot]" data-item-field="imei_snapshot" class="lm-wiz-input wiz-item-imei" value=""></div>' +
                        '<div class="wiz-item-field"><label>Serial Number</label><input type="text" name="edit_items[' + key + '][serial_number_snapshot]" data-item-field="serial_number_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>Lot Number</label><input type="text" name="edit_items[' + key + '][lot_number_snapshot]" data-item-field="lot_number_snapshot" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>Qty</label><input type="number" name="edit_items[' + key + '][qty]" data-item-field="qty" class="lm-wiz-input" value="1" min="1"></div>' +
                        '<div class="wiz-item-field"><label>Price</label><input type="number" step="0.01" name="edit_items[' + key + '][unit_price]" data-item-field="unit_price" class="lm-wiz-input" value="0" min="0"></div>' +
                        '<div class="wiz-item-field"><label>Discount</label><input type="number" step="0.01" name="edit_items[' + key + '][discount]" data-item-field="discount" class="lm-wiz-input" value="0" min="0"></div>' +
                        '<div class="wiz-item-field"><label>Color</label><input type="text" name="edit_items[' + key + '][color]" data-item-field="color" class="lm-wiz-input" value=""></div>' +
                        '<div class="wiz-item-field"><label>Storage</label><input type="text" name="edit_items[' + key + '][storage]" data-item-field="storage" class="lm-wiz-input" value="" placeholder="128GB, 256GB"></div>' +
                        '<div class="wiz-item-field" style="grid-column:1/-1;">' +
                            '<label>Product Photo / Reference</label>' +
                            '<div class="wiz-item-photo-control">' +
                                '<button type="button" class="btn btn-default btn-sm wiz-item-photo-action wiz-product-photo-choice-btn"><i class="fa fa-camera"></i> Photo</button>' +
                                '<input type="file" accept="image/*" capture="environment" class="wiz-item-photo-input wiz-item-photo-camera" style="display:none;">' +
                                '<input type="file" accept="image/*" class="wiz-item-photo-input wiz-item-photo-upload" style="display:none;">' +
                                '<span class="wiz-item-photo-thumb"><i class="fa fa-image"></i></span>' +
                                '<span class="wiz-item-photo-status"></span>' +
                            '</div>' +
                            '<input type="hidden" name="edit_items[' + key + '][product_photo]" data-item-field="product_photo" class="wiz-item-photo-data" value="">' +
                            '<input type="text" name="edit_items[' + key + '][product_photo_path]" data-item-field="product_photo_path" class="lm-wiz-input wiz-item-photo-path" value="" placeholder="Photo path, URL, or uploaded file name" style="margin-top:6px;">' +
                        '</div>' +
                        '<div class="wiz-item-field" style="grid-column:1/-1;"><label>Product OCR / Note</label><textarea name="edit_items[' + key + '][product_ocr_raw_text]" data-item-field="product_ocr_raw_text" class="lm-wiz-input" rows="2" placeholder="OCR text or product note"></textarea></div>' +
                    '</div>' +
                    '<div class="wiz-item-form-actions">' +
                        '<button type="button" class="btn btn-sm btn-danger wiz-new-item-remove"><i class="fa fa-trash"></i> Remove</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        var $newItem = $(newItemHtml);
        $('#wizItemsList').append($newItem);
        wizEditRecalcTotals();
        $newItem.find('.wiz-product-photo-choice-btn').trigger('click');
    });

    $(document).on('click', '.wiz-new-item-remove', function () {
        $(this).closest('.wiz-item-row').remove();
        if ($('#wizItemsList .wiz-item-row').length === 0) {
            $('#wizItemsList').html('<div style="text-align:center; padding:20px; color:#94a3b8;" id="wizItemsEmpty">No products yet. Click "Add Item" to add products.</div>');
        }
        wizEditRecalcTotals();
    });

    $(document).on('click', '.wiz-item-update-btn', function () {
        var $btn = $(this);
        var $body = $btn.closest('.wiz-item-body');
        var updateUrl = $body.data('update-url');
        if (!updateUrl) {
            if (window.toastr) toastr.error('Missing item update URL.'); else alert('Missing item update URL.');
            return;
        }
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        $.ajax({
            url: updateUrl,
            method: 'POST',
            data: wizSerializeItemUpdate($body),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) toastr.success(res.message || 'Item updated.');
                setTimeout(function () {
                    window.location.href = (res.data && res.data.redirect_url) || window.location.href;
                }, 800);
            },
            error: function (xhr) {
                var msg = 'Failed to update item.';
                if (xhr.status === 422 && xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        var keys = Object.keys(xhr.responseJSON.errors);
                        if (keys.length) msg = xhr.responseJSON.errors[keys[0]][0] || msg;
                    }
                    msg = xhr.responseJSON.message || msg;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (window.toastr) toastr.error(msg); else alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Update');
            }
        });
    });

    $(document).on('click', '.wiz-item-remove-btn', function () {
        if (!confirm('Delete this item? This will update loan totals.')) return;
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
                if (window.toastr) toastr.success(res.message || 'Item removed.');
                setTimeout(function () {
                    window.location.href = (res.data && res.data.redirect_url) || window.location.href;
                }, 500);
            },
            error: function (xhr) {
                wizDeleteItemViaLoanSave($row, $btn);
            }
        });
    });

    $(document).on('input', '.wiz-item-field .wiz-item-imei', function () {
        var $input = $(this);
        var serial = $input.val().trim();
        var $row = $input.closest('.wiz-item-row');
        var rowId = $row.index();
        if (wizSerialLookupTimers[rowId]) clearTimeout(wizSerialLookupTimers[rowId]);
        if (serial.length < 3) return;
        var existingName = ($row.find('[name$="[product_name_snapshot]"], [name="product_name_snapshot"]').val() || '').trim();
        if (existingName) return;
        wizSerialLookupTimers[rowId] = setTimeout(function () {
            $.get(wizUrls.productBySerial, { serial: serial }, function (res) {
                if (res.success && res.data && res.data.product_name) {
                    var $nf = $row.find('[name$="[product_name_snapshot]"], [name="product_name_snapshot"]');
                    if (!($nf.val() || '').trim()) $nf.val(res.data.product_name).trigger('input');
                }
            });
        }, 600);
    });

    $('#wizBtnPreviewSchedule, #wizBtnRefreshSchedule').on('click', function () {
        wizAutoGeneratePrincipalAfterDeposit();
        wizSyncDuration();
        wizEditRecalcTotals();
        var $btn = $(this);
        var defaultButtonHtml = $btn.attr('id') === 'wizBtnRefreshSchedule'
            ? '<i class="fa fa-refresh"></i> Refresh Schedule'
            : '<i class="fa fa-table"></i> Preview Schedule';
        var form = $('#wizEditForm');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
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
                $tb.append('<tr><td>' + (r.schedule_no || '') + '</td><td>' + (r.due_date || '') + '</td><td class="text-right">' + wizFormatMoney(r.principal) + '</td><td class="text-right">' + wizFormatMoney(r.interest) + '</td><td class="text-right">' + wizFormatMoney(r.total) + '</td><td class="text-right">' + wizFormatMoney(r.balance) + '</td></tr>');
            });
            if (!rows.length) {
                $tb.append('<tr><td colspan="6" class="text-center text-muted">No preview rows generated.</td></tr>');
            }
            $('#wizScheduleTable tfoot th').eq(1).text(wizFormatMoney(tP));
            $('#wizScheduleTable tfoot th').eq(2).text(wizFormatMoney(tI));
            $('#wizScheduleTable tfoot th').eq(3).text(wizFormatMoney(tA));
            $('#wizScheduleTable tfoot th').eq(4).text(wizFormatMoney(tB));
            $('#wizScheduleSection').show();

            var ic = parseInt($('[name="installment_count"]').val()) || 1;
            $('#wizSummaryMonthly').text('$' + wizFormatMoney(tA / ic));

            if (tI > 0) {
                $('[name="interest_amount"]').val(wizFormatMoney(tI));
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to preview schedule';
            if (window.toastr) toastr.error(msg); else alert(msg);
        }).always(function () {
            $btn.prop('disabled', false).html(defaultButtonHtml);
        });
    });

    $('#wizBtnSubmit').on('click', function () {
        if (!wizValidateStep(0) || !wizValidateStep(1)) {
            wizGoStep(0);
            return;
        }
        wizSyncCustomerNameFromKhmer();
        wizSyncDuration();
        var $btn = $(this);
        var form = $('#wizEditForm');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: wizUrls.updateAction,
            method: 'POST',
            data: wizSerializeLoanForm(),
            dataType: 'json',
            headers: {
                Accept: 'application/json'
            },
            success: function (res) {
                if (window.toastr) {
                    toastr.success(res.message || 'Loan updated successfully.');
                } else {
                    alert(res.message || 'Loan updated successfully.');
                }
                if (res.data && res.data.sections_html) {
                    $('#loanEditSections').html(res.data.sections_html);
                }
                wizEditRecalcTotals();
                wizPopulateReview();
            },
            error: function (xhr) {
                var msg = 'Failed to save loan.';
                if (xhr.status === 422 && xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        var keys = Object.keys(xhr.responseJSON.errors);
                        if (keys.length) msg = xhr.responseJSON.errors[keys[0]][0] || msg;
                    }
                    msg = xhr.responseJSON.message || msg;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (window.toastr) toastr.error(msg); else alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
            }
        });
    });

    $(document).on('input change', '[name="customer_khmer_name"], [name="customer_english_name"]', wizSyncCustomerNameFromKhmer);
    wizSyncCustomerNameFromKhmer();

    $(document).on('keydown', function (e) {
        if (e.key === 'Enter') {
            var $active = $('.lm-wiz-panel.active');
            if ($active.length) {
                var step = parseInt($active.data('panel'));
                if (step < wizTotalSteps - 1) {
                    e.preventDefault();
                    wizGoStep(step + 1);
                }
            }
        }
    });

    var wizErrorLink = document.getElementById('wizErrorLink');
    var wizErrorDetails = document.getElementById('wizErrorDetails');
    if (wizErrorLink && wizErrorDetails) {
        wizErrorLink.addEventListener('click', function (e) {
            e.preventDefault();
            wizErrorDetails.style.display = wizErrorDetails.style.display === 'none' ? 'block' : 'none';
            wizErrorLink.textContent = wizErrorDetails.style.display === 'block' ? 'Hide error details' : 'View error details';
        });
    }

    $(document).on('click', '.wiz-deposit-remove', function () {
        if (!confirm('Delete this deposit payment? This will update loan totals.')) return;
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
                if (window.toastr) toastr.success(res.message || 'Deposit removed.');
                setTimeout(function () { window.location.reload(); }, 800);
            },
            error: function (xhr) {
                var msg = 'Failed to remove deposit.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                if (window.toastr) toastr.error(msg); else alert(msg);
                $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Remove');
            }
        });
    });

    $(document).on('click', '.wiz-deposit-update-btn', function () {
        var $btn = $(this);
        var $form = $btn.closest('.wiz-deposit-edit-form');
        var $row = $form.closest('.wiz-deposit-row');
        var paymentId = $row.data('id');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        var payload = $form.find(':input').serializeArray();
        payload.push({ name: '_token', value: '{{ csrf_token() }}' });
        payload.push({ name: '_method', value: 'PUT' });
        $.ajax({
            url: wizUrls.paymentUpdateBase + '/' + paymentId,
            method: 'POST',
            data: $.param(payload),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) toastr.success(res.message || 'Deposit updated.');
                setTimeout(function () { window.location.reload(); }, 800);
            },
            error: function (xhr) {
                var msg = 'Failed to update deposit.';
                if (xhr.status === 422 && xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        var keys = Object.keys(xhr.responseJSON.errors);
                        if (keys.length) msg = xhr.responseJSON.errors[keys[0]][0] || msg;
                    }
                    msg = xhr.responseJSON.message || msg;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (window.toastr) toastr.error(msg); else alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Update');
            }
        });
    });

    $(function () {
        wizEditRecalcTotals();
    });
})(jQuery);
</script>
@endsection
