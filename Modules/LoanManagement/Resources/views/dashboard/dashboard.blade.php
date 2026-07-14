@php
    $cards = [
        ['key' => 'due_today', 'label' => 'Due Today', 'icon' => 'fa fa-calendar-check-o', 'tone' => 'blue'],
        ['key' => 'overdue_accounts', 'label' => 'Overdue Accounts', 'icon' => 'fa fa-exclamation-triangle', 'tone' => 'red'],
        ['key' => 'skip_customers', 'label' => 'Skip Customers', 'icon' => 'fa fa-phone-square', 'tone' => 'slate'],
        ['key' => 'broken_ptp', 'label' => 'Broken PTP', 'icon' => 'fa fa-chain-broken', 'tone' => 'amber'],
        ['key' => 'field_visits_today', 'label' => 'Field Visits Today', 'icon' => 'fa fa-street-view', 'tone' => 'teal'],
        ['key' => 'collection_amount_today', 'label' => 'Collection Amount Today', 'icon' => 'fa fa-dollar', 'tone' => 'green'],
        ['key' => 'recovery_cases', 'label' => 'Recovery Cases', 'icon' => 'fa fa-refresh', 'tone' => 'purple'],
        ['key' => 'legal_cases', 'label' => 'Legal Cases', 'icon' => 'fa fa-gavel', 'tone' => 'rose'],
        ['key' => 'high_risk_customers', 'label' => 'High Risk Customers', 'icon' => 'fa fa-user-times', 'tone' => 'orange'],
        ['key' => 'repossessions', 'label' => 'Repossessions', 'icon' => 'fa fa-truck', 'tone' => 'gray'],
    ];
    $heroMetrics = [
        ['label' => 'Active Loans', 'value' => (int) ($quickCards['active_loans'] ?? 0), 'format' => 'int'],
        ['label' => 'Late Customers', 'value' => (int) ($quickCards['late_customers'] ?? 0), 'format' => 'int'],
        ['label' => 'Today Collection', 'value' => (float) ($quickCards['today_collection'] ?? 0), 'format' => 'money'],
        ['label' => 'Monthly Income', 'value' => (float) ($quickCards['monthly_income'] ?? 0), 'format' => 'money'],
    ];
    $dashboardBadgeCounts = \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts();
    $dashboardUnreadChats = (int) ($dashboardBadgeCounts['unread_chat'] ?? 0);
    $dashboardPendingVisits = (int) ($dashboardBadgeCounts['pending_visits'] ?? 0);
@endphp

@section('loan_css')
@parent
<style>
    .lm-dashboard {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .lm-dashboard-tabs {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 6px;
        border: 1px solid #dbe5f0;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        align-self: flex-start;
    }
    .lm-dashboard-tab {
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        padding: 10px 18px;
        transition: background .18s ease, color .18s ease, transform .18s ease;
    }
    .lm-dashboard-tab:hover {
        color: #0f172a;
        transform: translateY(-1px);
    }
    .lm-dashboard-tab.is-active {
        background: linear-gradient(135deg, #15314b 0%, #1c5d77 52%, #20a083 100%);
        color: #fff;
        box-shadow: 0 10px 22px rgba(21, 49, 75, 0.2);
    }
    .lm-dashboard-pane {
        display: none;
        flex-direction: column;
        gap: 18px;
    }
    .lm-dashboard-pane.is-active {
        display: flex;
    }
    .lm-dashboard-hero {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        padding: 22px 24px;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,0.22), transparent 26%),
            linear-gradient(135deg, #15314b 0%, #1c5d77 52%, #20a083 100%);
        color: #fff;
        box-shadow: 0 18px 40px rgba(20, 42, 74, 0.18);
    }
    .lm-dashboard-hero::after {
        content: '';
        position: absolute;
        right: -40px;
        bottom: -40px;
        width: 180px;
        height: 180px;
        border-radius: 50%;
        background: rgba(255,255,255,0.09);
    }
    .lm-dashboard-hero-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 18px;
        align-items: end;
    }
    .lm-dashboard-title {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .lm-dashboard-subtitle {
        margin: 0;
        max-width: 720px;
        color: rgba(255,255,255,0.85);
        font-size: 14px;
        line-height: 1.6;
    }
    .lm-hero-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .lm-hero-metric {
        padding: 14px 16px;
        border-radius: 14px;
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(6px);
    }
    .lm-hero-metric-label {
        display: block;
        margin-bottom: 4px;
        color: rgba(255,255,255,0.78);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .lm-hero-metric-value {
        display: block;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.1;
    }
    .lm-dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 14px;
    }
    .lm-stat-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-height: 114px;
        padding: 16px 18px;
        border: 1px solid #e5ecf3;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }
    .lm-stat-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 14px;
        color: #fff;
        font-size: 18px;
        flex: 0 0 auto;
    }
    .lm-tone-blue { background: linear-gradient(135deg, #2d6cdf, #53a0fd); }
    .lm-tone-red { background: linear-gradient(135deg, #d94b66, #f97373); }
    .lm-tone-slate { background: linear-gradient(135deg, #475569, #64748b); }
    .lm-tone-amber { background: linear-gradient(135deg, #d97706, #f59e0b); }
    .lm-tone-teal { background: linear-gradient(135deg, #0f766e, #14b8a6); }
    .lm-tone-green { background: linear-gradient(135deg, #15803d, #22c55e); }
    .lm-tone-purple { background: linear-gradient(135deg, #7c3aed, #a855f7); }
    .lm-tone-rose { background: linear-gradient(135deg, #be185d, #f43f5e); }
    .lm-tone-orange { background: linear-gradient(135deg, #c2410c, #fb923c); }
    .lm-tone-gray { background: linear-gradient(135deg, #334155, #94a3b8); }

    /* Payment Collection quick search styles */
    .lm-pay-row td { vertical-align: middle !important; }
    .lm-pay-due {
        white-space: nowrap;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }
    .lm-pay-balance {
        font-weight: 700;
        color: #0f172a;
    }
    .lm-pay-action {
        white-space: nowrap;
    }
    .lm-pay-btn {
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 700;
        border-radius: 8px;
        background: linear-gradient(135deg, #16a34a, #15803d);
        border: 0;
        color: #fff;
        box-shadow: 0 2px 6px rgba(22, 163, 74, .25);
        transition: transform .12s ease, box-shadow .12s ease;
    }
    .lm-pay-btn:hover,
    .lm-pay-btn:focus {
        color: #fff;
        box-shadow: 0 4px 12px rgba(22, 163, 74, .35);
        transform: translateY(-1px);
    }
    .lm-pay-btn:active {
        transform: scale(.96);
    }
    .lm-pay-more {
        display: inline-block;
        margin-left: 4px;
    }
    .lm-pay-more .btn {
        padding: 4px 6px;
        min-height: auto;
        border-radius: 6px;
    }
    .lm-pay-status {
        display: inline-block;
        margin-top: 3px;
        padding: 1px 6px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        background: #f1f5f9;
        color: #64748b;
    }
    .lm-pay-status--overdue {
        background: #fef2f2;
        color: #dc2626;
    }

    /* Payment Modal Form Styles */
    .view_modal .modal-content {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 24px 64px rgba(15, 23, 42, 0.18);
        overflow: hidden;
    }
    .view_modal .modal-header {
        background: linear-gradient(135deg, #15314b 0%, #1c5d77 52%, #20a083 100%);
        color: #fff;
        border: 0;
        padding: 18px 22px;
    }
    .view_modal .modal-header .modal-title {
        color: #fff;
        font-size: 18px;
        font-weight: 700;
    }
    .view_modal .modal-header .modal-title .fa {
        margin-right: 8px;
        opacity: .85;
    }
    .view_modal .modal-header .close {
        color: #fff;
        opacity: .8;
        text-shadow: none;
    }
    .view_modal .modal-header .close:hover {
        opacity: 1;
    }
    .view_modal .modal-body {
        padding: 20px 22px;
        background: #f8fafc;
    }
    .view_modal .modal-body .well {
        background: #fff;
        border: 1px solid #e5ecf3;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
        margin-bottom: 12px;
    }
    .view_modal .modal-body .well strong {
        color: #0f172a;
        font-weight: 700;
    }
    .view_modal .modal-body .form-group label {
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
    }
    .view_modal .modal-body .form-control,
    .view_modal .modal-body select.form-control {
        border: 1px solid #d7e2ee;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 13px;
        height: auto;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .view_modal .modal-body .form-control:focus,
    .view_modal .modal-body select.form-control:focus {
        border-color: #7dd3fc;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
    }
    .view_modal .modal-body .input-group-addon {
        border: 1px solid #d7e2ee;
        border-right: 0;
        border-radius: 10px 0 0 10px;
        background: #f1f5f9;
        color: #64748b;
        font-size: 13px;
        padding: 8px 10px;
    }
    .view_modal .modal-body .input-group .form-control {
        border-left: 0;
        border-radius: 0 10px 10px 0;
    }
    .view_modal .modal-body .box.box-solid.bg-lightgray {
        background: #fff !important;
        border: 1px solid #e5ecf3;
        border-radius: 14px;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }
    .view_modal .modal-body .box-header {
        background: #f8fafc;
        border-bottom: 1px solid #edf2f7;
        padding: 12px 16px;
    }
    .view_modal .modal-body .box-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }
    .view_modal .modal-body .box-body {
        padding: 16px;
    }
    .view_modal .modal-body .loan-payment-line {
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .view_modal .modal-body .loan-payment-line:last-child {
        border-bottom: 0;
    }
    .view_modal .modal-body .checkbox label {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
    }
    .view_modal .modal-body .checkbox input[type="checkbox"] {
        margin-right: 6px;
    }
    .view_modal .modal-body .well-sm {
        background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 12px 16px;
    }
    .view_modal .modal-body .well-sm strong {
        color: #15803d;
    }
    .view_modal .modal-footer {
        background: #f8fafc;
        border-top: 1px solid #edf2f7;
        padding: 14px 22px;
    }
    .view_modal .modal-footer .btn {
        border-radius: 10px;
        font-weight: 700;
        padding: 8px 20px;
        font-size: 13px;
    }
    .lm-pay-action .btn-modal {
        cursor: pointer;
    }
    .loan-schedule-display {
        display: block;
        margin-top: 4px;
        color: #475569;
        font-size: 12px;
    }

    .lm-stat-card__label {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .lm-stat-card__value {
        display: block;
        margin-top: 8px;
        color: #0f172a;
        font-size: 28px;
        font-weight: 700;
        line-height: 1.05;
    }
    .lm-stat-card__meta {
        display: block;
        margin-top: 10px;
        color: #94a3b8;
        font-size: 12px;
    }
    .lm-dashboard-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 18px;
    }
    .lm-dashboard-panel {
        border: 1px solid #e5ecf3;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }
    .lm-dashboard-panel--feature {
        position: relative;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.07), transparent 26%),
            linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }
    .lm-dashboard-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px 12px;
        border-bottom: 1px solid #edf2f7;
    }
    .lm-dashboard-panel__title {
        margin: 0;
        color: #0f172a;
        font-size: 18px;
        font-weight: 700;
    }
    .lm-dashboard-panel__hint {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 12px;
    }
    .lm-dashboard-panel__badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border: 1px solid #dbe8ff;
        border-radius: 999px;
        background: linear-gradient(135deg, #eff6ff, #f8fbff);
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.08);
    }
    .lm-dashboard-panel__body {
        padding: 16px 18px 18px;
    }
    .lm-quick-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .lm-quick-box {
        position: relative;
        padding: 16px;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .lm-quick-box::before {
        content: '';
        position: absolute;
        top: -44px;
        right: -30px;
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: rgba(56, 189, 248, 0.12);
        pointer-events: none;
    }
    .lm-quick-box:hover {
        transform: translateY(-2px);
        border-color: #cfe0f7;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.09);
    }
    .lm-quick-box--sell::before {
        background: rgba(34, 197, 94, 0.12);
    }
    .lm-quick-box__title {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 8px;
        color: #0f172a;
        font-size: 15px;
        font-weight: 700;
    }
    .lm-quick-box__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 12px;
        background: #e0f2fe;
        color: #0369a1;
        font-size: 15px;
    }
    .lm-quick-box__subtitle {
        margin: 0 0 12px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }
    .lm-quick-box__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 12px;
    }
    .lm-quick-box__chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .lm-quick-box--sell .lm-quick-box__chip {
        background: #ecfdf5;
        color: #15803d;
    }
    .lm-quick-box__footer {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed #dbe5ef;
        color: #64748b;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .lm-quick-input .input-group-addon {
        border-color: #d7e2ee;
        background: #f8fafc;
        color: #64748b;
    }
    .lm-quick-trigger {
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }
    .lm-quick-trigger:hover,
    .lm-quick-trigger:focus {
        background: #e0f2fe !important;
        color: #0369a1 !important;
    }
    .lm-quick-box__icon--pay {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #15803d;
    }
    .lm-quick-box__chip--pay {
        background: #f0fdf4;
        color: #15803d;
    }
    .lm-quick-box--loan {
        border-color: #bbf7d0;
    }
    .lm-quick-box--loan::before {
        background: rgba(34, 197, 94, 0.12);
    }
    .lm-quick-input .form-control {
        height: 40px;
        border-color: #d7e2ee;
        box-shadow: none;
    }
    .lm-quick-input .form-control:focus {
        border-color: #7dd3fc;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
    }
    .lm-dashboard-table {
        margin-bottom: 0;
    }
    .lm-dashboard-table > thead > tr > th {
        border-bottom: 1px solid #dbe5ef;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        background: #f8fafc;
    }
    .lm-dashboard-table > tbody > tr > td {
        vertical-align: middle;
        border-top: 1px solid #edf2f7;
    }
    .lm-row-title {
        color: #0f172a;
        font-weight: 700;
    }
    .lm-row-subtitle {
        display: block;
        margin-top: 2px;
        color: #64748b;
        font-size: 12px;
    }
    .lm-action-buttons {
        display: inline-flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .lm-action-menu {
        position: relative;
        display: inline-block;
    }
    .lm-action-menu__toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border: 1px solid #d7e2ee;
        border-radius: 999px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        color: #0f172a;
        font-size: 12px;
        font-weight: 700;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }
    .lm-action-menu__toggle:hover,
    .lm-action-menu__toggle:focus {
        background: #f8fafc;
        color: #020617;
    }
    .lm-action-menu__list {
        min-width: 190px;
        padding: 6px;
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
    }
    .lm-action-menu__list > li > a,
    .lm-action-menu__list > li > button {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 9px 10px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #0f172a;
        font-size: 12px;
        font-weight: 600;
        text-align: left;
        white-space: nowrap;
    }
    .lm-action-menu__list > li > a:hover,
    .lm-action-menu__list > li > a:focus,
    .lm-action-menu__list > li > button:hover,
    .lm-action-menu__list > li > button:focus {
        background: #eff6ff;
        color: #1d4ed8;
        text-decoration: none;
    }
    .lm-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid #d7e2ee;
        background: #fff;
        color: #0f172a;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none !important;
    }
    .lm-action-btn:hover,
    .lm-action-btn:focus {
        background: #f8fafc;
        color: #020617;
    }
    .lm-action-btn--primary {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }
    .lm-action-btn--success {
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #15803d;
    }
    .lm-side-stack {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .lm-mini-table td,
    .lm-mini-table th {
        padding-top: 9px !important;
        padding-bottom: 9px !important;
    }
    .lm-chart-shell {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 250px;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        background:
            radial-gradient(circle at top left, rgba(56, 189, 248, 0.08), transparent 24%),
            linear-gradient(180deg, #f8fbff 0%, #f8fafc 100%);
    }
    .lm-chart-copy {
        text-align: center;
        color: #64748b;
    }
    .lm-chart-copy strong {
        display: block;
        margin-bottom: 6px;
        color: #0f172a;
        font-size: 16px;
    }
    .lm-live-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .lm-live-panel {
        min-height: 360px;
    }
    .lm-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #ecfeff;
        color: #0f766e;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #bae6fd;
    }
    .lm-live-badge__dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #14b8a6;
        box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.55);
        animation: lm-live-pulse 1.8s infinite;
    }
    .lm-live-chart {
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-height: 250px;
    }
    .lm-live-chart__canvas {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        min-height: 220px;
        padding: 18px 18px 14px;
        border-radius: 18px;
        background:
            linear-gradient(180deg, rgba(255,255,255,0.98), rgba(241,245,249,0.92)),
            repeating-linear-gradient(to top, rgba(148, 163, 184, 0.12) 0, rgba(148, 163, 184, 0.12) 1px, transparent 1px, transparent 44px);
        border: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .lm-live-chart__bar-group {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        min-width: 56px;
        flex: 1 1 0;
    }
    .lm-live-chart__bar-stack {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 6px;
        width: 100%;
        min-height: 170px;
    }
    .lm-live-chart__bar {
        width: 18px;
        min-height: 8px;
        border-radius: 10px 10px 4px 4px;
        background: linear-gradient(180deg, #38bdf8 0%, #2563eb 100%);
        box-shadow: 0 10px 18px rgba(37, 99, 235, 0.18);
    }
    .lm-live-chart__bar--accent {
        background: linear-gradient(180deg, #34d399 0%, #059669 100%);
        box-shadow: 0 10px 18px rgba(5, 150, 105, 0.18);
    }
    .lm-live-chart__bar--warn {
        background: linear-gradient(180deg, #fb7185 0%, #e11d48 100%);
        box-shadow: 0 10px 18px rgba(225, 29, 72, 0.18);
    }
    .lm-live-chart__label {
        max-width: 100%;
        color: #475569;
        font-size: 11px;
        font-weight: 700;
        text-align: center;
        line-height: 1.25;
        word-break: break-word;
    }
    .lm-live-chart__value {
        color: #0f172a;
        font-size: 11px;
        font-weight: 700;
        text-align: center;
    }
    .lm-live-chart__legend {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        color: #64748b;
        font-size: 12px;
    }
    .lm-live-chart__legend-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-live-chart__legend-swatch {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #2563eb;
    }
    .lm-live-chart__legend-swatch--accent {
        background: #059669;
    }
    .lm-live-chart__legend-swatch--warn {
        background: #e11d48;
    }
    .lm-live-chart__empty {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 220px;
        border: 1px dashed #cbd5e1;
        border-radius: 18px;
        color: #64748b;
        text-align: center;
        padding: 20px;
        background: #f8fafc;
    }
    .lm-live-chat-shell {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr) 300px;
        min-height: 76vh;
        border: 1px solid #dbe5f0;
        border-radius: 22px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
    }
    .lm-live-chat-inbox {
        display: flex;
        flex-direction: column;
        min-height: 0;
        border-right: 1px solid #e5edf5;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .lm-live-chat-toolbar {
        padding: 18px 18px 14px;
        border-bottom: 1px solid #e5edf5;
    }
    .lm-live-chat-toolbar h4 {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .lm-live-chat-search {
        width: 100%;
        height: 42px;
        border: 1px solid #dbe5f0;
        border-radius: 999px;
        padding: 0 16px;
        outline: none;
        background: #f8fafc;
    }
    .lm-live-chat-list {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 8px;
    }
    .lm-live-chat-item {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        width: 100%;
        padding: 12px;
        border: 0;
        border-radius: 20px;
        background: transparent;
        text-align: left;
        transition: background .18s ease, transform .18s ease;
    }
    .lm-live-chat-item:hover,
    .lm-live-chat-item.is-active {
        background: #eef5ff;
        transform: translateX(2px);
    }
    .lm-live-chat-avatar {
        position: relative;
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e0f2fe, #dbeafe);
        color: #1e3a8a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 800;
    }
    .lm-live-chat-avatar::after {
        content: '';
        position: absolute;
        right: 3px;
        bottom: 3px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #22c55e;
        border: 2px solid #fff;
    }
    .lm-live-chat-name {
        display: block;
        color: #111827;
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-live-chat-preview {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-live-chat-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        min-width: 48px;
    }
    .lm-live-chat-time {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
    }
    .lm-live-chat-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 999px;
        background: #2563eb;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }
    .lm-live-chat-main {
        display: flex;
        flex-direction: column;
        min-height: 0;
        background: #f8fafc;
        border-right: 1px solid #e5edf5;
    }
    .lm-live-chat-mainbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e5edf5;
        background: #fff;
    }
    .lm-live-chat-main-title {
        margin: 0;
        color: #111827;
        font-size: 18px;
        font-weight: 800;
    }
    .lm-live-chat-main-subtitle {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 12px;
    }
    .lm-live-chat-main-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .lm-live-chat-frame {
        flex: 1 1 auto;
        min-height: 0;
        width: 100%;
        border: 0;
        background: #fff;
    }
    .lm-live-chat-side {
        padding: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        overflow-y: auto;
    }
    .lm-live-chat-profile {
        text-align: center;
        padding-bottom: 18px;
        border-bottom: 1px solid #e5edf5;
    }
    .lm-live-chat-profile-avatar {
        width: 88px;
        height: 88px;
        border-radius: 50%;
        margin: 0 auto 14px;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1d4ed8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 800;
    }
    .lm-live-chat-profile-name {
        margin: 0;
        color: #111827;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .lm-live-chat-profile-subtitle,
    .lm-live-chat-profile-time {
        margin: 6px 0 0;
        color: #64748b;
        font-size: 13px;
    }
    .lm-live-chat-side-section {
        padding: 18px 0;
        border-bottom: 1px solid #e5edf5;
    }
    .lm-live-chat-side-title {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 15px;
        font-weight: 800;
    }
    .lm-live-chat-side-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        font-size: 13px;
        border-bottom: 1px dashed #eef2f7;
    }
    .lm-live-chat-side-row:last-child {
        border-bottom: 0;
    }
    .lm-live-chat-side-row span:first-child {
        color: #64748b;
    }
    .lm-live-chat-side-row span:last-child {
        color: #111827;
        font-weight: 700;
        text-align: right;
    }
    .lm-live-chat-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 220px;
        color: #64748b;
        text-align: center;
        padding: 20px;
    }
    @keyframes lm-live-pulse {
        0% { box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.55); }
        70% { box-shadow: 0 0 0 9px rgba(20, 184, 166, 0); }
        100% { box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
    }
    .lm-dashboard-frame-link {
        cursor: pointer;
        text-decoration: none !important;
    }
    .lm-dashboard-iframe-modal .modal-dialog {
        width: 96%;
        max-width: 1280px;
    }
    .lm-dashboard-iframe-modal .modal-body {
        padding: 0;
        height: 80vh;
        background: #f8fafc;
    }
    .lm-dashboard-iframe-modal iframe {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
        background: #fff;
    }
    .lm-chat-card {
        position: relative;
        padding: 10px;
        border: 1px solid #e8eef5;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 28%),
            linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
    }
    .lm-chat-card::before {
        content: '';
        position: absolute;
        top: 14px;
        right: 18px;
        width: 66px;
        height: 66px;
        border-radius: 50%;
        background: rgba(37, 99, 235, 0.07);
        pointer-events: none;
    }
    .lm-chat-card__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 10px 6px;
    }
    .lm-chat-card__title {
        margin: 0;
        color: #111827;
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .lm-chat-card__actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .lm-chat-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #6b7280;
        text-decoration: none !important;
        border: 0;
    }
    .lm-chat-card__search {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 8px 10px 14px;
        padding: 14px 18px;
        border-radius: 999px;
        background: #eef2f7;
        color: #7c8698;
        font-size: 14px;
    }
    .lm-chat-card__search i {
        font-size: 22px;
    }
    .lm-chat-card__tabs {
        display: flex;
        align-items: center;
        gap: 10px;
        overflow-x: auto;
        padding: 0 10px 12px;
    }
    .lm-chat-card__summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 0 10px 14px;
    }
    .lm-chat-card__summary-box {
        padding: 12px 14px;
        border-radius: 18px;
        background: linear-gradient(135deg, #f8fbff, #eef4ff);
        border: 1px solid #dce8fb;
    }
    .lm-chat-card__summary-label {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .lm-chat-card__summary-value {
        display: block;
        margin-top: 6px;
        color: #0f172a;
        font-size: 22px;
        font-weight: 800;
        line-height: 1;
    }
    .lm-chat-card__summary-note {
        display: block;
        margin-top: 5px;
        color: #64748b;
        font-size: 11px;
    }
    .lm-chat-card__tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 10px 18px;
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #111827;
        font-size: 15px;
        font-weight: 700;
        white-space: nowrap;
    }
    .lm-chat-card__tab.is-active {
        background: #dbeafe;
        color: #2563eb;
    }
    .lm-chat-card__list {
        max-height: 690px;
        overflow-y: auto;
        padding: 0 6px 8px;
    }
    .lm-chat-card__request {
        display: grid;
        grid-template-columns: 66px minmax(0, 1fr) 24px;
        gap: 14px;
        align-items: center;
        padding: 8px 12px 16px;
    }
    .lm-chat-card__request-avatar {
        width: 66px;
        height: 66px;
        border-radius: 50%;
        background: #e5e7eb;
        color: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    .lm-chat-card__request-title {
        margin: 0 0 2px;
        color: #111827;
        font-size: 15px;
        font-weight: 700;
    }
    .lm-chat-card__request-subtitle {
        margin: 0;
        color: #111827;
        font-size: 13px;
    }
    .lm-chat-card__request-arrow {
        color: #6b7280;
        font-size: 26px;
        text-align: right;
    }
    .lm-chat-card__item {
        display: grid;
        grid-template-columns: 82px minmax(0, 1fr) 16px;
        gap: 14px;
        align-items: center;
        padding: 12px;
        border-radius: 20px;
        text-decoration: none !important;
        transition: background .18s ease, transform .18s ease;
    }
    .lm-chat-card__item:hover {
        background: #f8fafc;
        transform: translateX(2px);
    }
    .lm-chat-card__avatar-wrap {
        position: relative;
        width: 82px;
        height: 82px;
    }
    .lm-chat-card__avatar {
        width: 82px;
        height: 82px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e0f2fe, #dbeafe);
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        font-weight: 700;
        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.22);
    }
    .lm-chat-card__presence {
        position: absolute;
        right: 4px;
        bottom: 4px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #24a148;
        border: 3px solid #fff;
    }
    .lm-chat-card__name {
        display: block;
        margin: 0 0 4px;
        color: #111827;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.3;
    }
    .lm-chat-card__preview {
        display: block;
        margin: 0;
        color: #111827;
        font-size: 13px;
        line-height: 1.5;
    }
    .lm-chat-card__time {
        color: #6b7280;
    }
    .lm-chat-card__dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #0a66d6;
        justify-self: end;
    }
    .lm-chat-card__empty {
        margin: 8px 10px 10px;
        padding: 26px 18px;
        border-radius: 20px;
        background: #f8fafc;
        color: #64748b;
        text-align: center;
        font-size: 14px;
    }
    @media (max-width: 1199px) {
        .lm-dashboard-grid,
        .lm-dashboard-hero-grid,
        .lm-quick-grid,
        .lm-live-grid {
            grid-template-columns: 1fr;
        }
        .lm-live-chat-shell {
            grid-template-columns: 280px minmax(0, 1fr);
        }
        .lm-live-chat-side {
            display: none;
        }
    }
    @media (max-width: 767px) {
        .lm-dashboard-hero {
            padding: 18px;
        }
        .lm-dashboard-title {
            font-size: 24px;
        }
        .lm-hero-metrics {
            grid-template-columns: 1fr;
        }
        .lm-dashboard-panel__header,
        .lm-dashboard-panel__body {
            padding-left: 14px;
            padding-right: 14px;
        }
        .lm-dashboard-table {
            min-width: 560px;
        }
        .lm-chat-card__title {
            font-size: 21px;
        }
        .lm-chat-card__summary {
            grid-template-columns: 1fr;
        }
        .lm-chat-card__item {
            grid-template-columns: 64px minmax(0, 1fr) 16px;
        }
        .lm-chat-card__avatar-wrap,
        .lm-chat-card__avatar {
            width: 64px;
            height: 64px;
        }
        .lm-live-chat-shell {
            grid-template-columns: 1fr;
            min-height: 860px;
        }
        .lm-live-chat-inbox {
            min-height: 320px;
            border-right: 0;
            border-bottom: 1px solid #e5edf5;
        }
        .lm-live-chat-main {
            border-right: 0;
        }
    }

    /* ============================================================
       DASHBOARD MOBILE: Tablet (max-width: 992px)
       ============================================================ */
    @media (max-width: 992px) {
        .lm-dashboard-hero-grid {
            grid-template-columns: 1fr;
        }
        .lm-dashboard-title {
            font-size: 24px;
        }
        .lm-dashboard-cards {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 8px;
        }
        .lm-quick-grid {
            grid-template-columns: 1fr;
        }
        .lm-side-stack {
            flex-direction: row;
            gap: 14px;
        }
        .lm-side-stack > .lm-dashboard-panel {
            flex: 1;
            min-width: 0;
        }
    }

    /* ============================================================
       DASHBOARD MOBILE: Phone (max-width: 767px)
       ============================================================ */
    @media (max-width: 767px) {
        .lm-dashboard {
            gap: 10px;
            overflow: hidden;
            max-width: 100%;
        }
        /* Tabs: full-width scrollable */
        .lm-dashboard-tabs {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            justify-content: center;
        }
        .lm-dashboard-tab {
            padding: 8px 14px;
            font-size: 12px;
            white-space: nowrap;
        }

        /* Hero */
        .lm-dashboard-hero {
            padding: 10px;
            border-radius: 12px;
            overflow: hidden;
        }
        .lm-dashboard-hero::after {
            display: none;
        }
        .lm-dashboard-hero-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .lm-dashboard-title {
            font-size: 20px;
            margin-bottom: 2px;
        }
        .lm-dashboard-subtitle {
            font-size: 12px;
            max-width: 100%;
            display: none;
        }

        /* Hero metrics: horizontal scroll strip */
        .lm-hero-metrics {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2px;
            scroll-snap-type: x mandatory;
        }
        .lm-hero-metric {
            flex: 0 0 110px;
            min-width: 110px;
            padding: 6px 8px;
            border-radius: 8px;
            scroll-snap-align: start;
        }
        .lm-hero-metric-label {
            font-size: 9px;
            margin-bottom: 1px;
        }
        .lm-hero-metric-value {
            font-size: 16px;
        }

        /* Stat cards: compact full-width inline cards */
        .lm-dashboard-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
        }
        .lm-stat-card {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            min-height: auto;
            border-radius: 10px;
        }
        .lm-stat-card__icon {
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            font-size: 13px;
            border-radius: 8px;
        }
        .lm-stat-card__label {
            font-size: 10px;
            line-height: 1.2;
            margin-bottom: 0;
        }
        .lm-stat-card__value {
            font-size: 16px;
            margin-top: 0;
            line-height: 1.1;
        }
        .lm-stat-card__meta {
            display: none;
        }

        /* Panels */
        .lm-dashboard-panel {
            border-radius: 14px;
            overflow: hidden;
            max-width: 100%;
        }
        .lm-dashboard-panel__header {
            padding: 12px 14px 10px;
            overflow: hidden;
        }
        .lm-dashboard-panel__header > div {
            min-width: 0;
            flex: 1;
        }
        .lm-dashboard-panel__title {
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lm-dashboard-panel__hint {
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lm-dashboard-panel__body {
            padding: 12px 14px 14px;
            overflow: hidden;
            min-width: 0;
        }
        .lm-dashboard-panel__badge {
            font-size: 10px;
            padding: 5px 8px;
            flex-shrink: 0;
            white-space: nowrap;
        }

        /* Quick action grid: full width boxes */
        .lm-quick-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .lm-quick-box {
            padding: 14px;
            border-radius: 12px;
            overflow: hidden;
            max-width: 100%;
        }
        .lm-quick-box::before {
            display: none;
        }
        .lm-quick-box:hover {
            transform: none;
        }
        .lm-quick-box__title {
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lm-quick-box__subtitle {
            font-size: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .lm-quick-box__meta {
            flex-wrap: wrap;
            gap: 6px;
        }
        .lm-quick-box__chip {
            font-size: 10px;
            padding: 4px 8px;
        }
        .lm-quick-box__footer {
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Tables: horizontal scroll */
        .lm-dashboard-table {
            min-width: 0;
            width: 100%;
        }
        .lm-pay-btn {
            padding: 6px 12px;
            font-size: 13px;
        }
        .lm-pay-more .btn {
            padding: 6px 8px;
        }
        .lm-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }

        /* Side stack: vertical on phone */
        .lm-side-stack {
            flex-direction: column;
            gap: 12px;
        }

        /* Live charts grid */
        .lm-live-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .lm-live-chart__canvas {
            min-height: 180px;
            padding: 12px;
        }
        .lm-live-chart__bar-group {
            min-width: 44px;
        }
        .lm-live-chart__bar {
            width: 14px;
        }
        .lm-live-chart__label {
            font-size: 10px;
        }
        .lm-live-chart__value {
            font-size: 10px;
        }

        /* Chat card mobile */
        .lm-chat-card {
            border-radius: 18px;
            padding: 8px;
            overflow: hidden;
            max-width: 100%;
        }
        .lm-chat-card::before {
            display: none;
        }
        .lm-chat-card__toolbar {
            padding: 8px;
        }
        .lm-chat-card__title {
            font-size: 20px;
        }
        .lm-chat-card__summary {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .lm-chat-card__summary-value {
            font-size: 18px;
        }
        .lm-chat-card__tabs {
            padding: 0 8px 10px;
            gap: 6px;
        }
        .lm-chat-card__tab {
            min-height: 34px;
            padding: 6px 14px;
            font-size: 13px;
        }
        .lm-chat-card__list {
            max-height: 400px;
        }
        .lm-chat-card__item {
            grid-template-columns: 50px minmax(0, 1fr) 14px;
            gap: 10px;
            padding: 8px;
        }
        .lm-chat-card__avatar-wrap,
        .lm-chat-card__avatar {
            width: 50px;
            height: 50px;
            font-size: 18px;
        }
        .lm-chat-card__name {
            font-size: 14px;
        }
        .lm-chat-card__preview {
            font-size: 12px;
        }
        .lm-chat-card__request {
            grid-template-columns: 48px minmax(0, 1fr) 20px;
            gap: 10px;
            padding: 8px;
        }
        .lm-chat-card__request-avatar {
            width: 48px;
            height: 48px;
            font-size: 18px;
        }
        .lm-chat-card__request-title {
            font-size: 13px;
        }
        .lm-chat-card__request-subtitle {
            font-size: 11px;
        }

        /* Dashboard grid panels */
        .lm-dashboard-grid {
            grid-template-columns: 1fr;
            gap: 12px;
            min-width: 0;
            max-width: 100%;
        }

        /* Live chat shell */
        .lm-live-chat-shell {
            grid-template-columns: 1fr;
            min-height: auto;
            border-radius: 16px;
            overflow: hidden;
            max-width: 100%;
        }
        .lm-live-chat-inbox {
            min-height: 200px;
            max-height: 320px;
            border-right: 0;
            border-bottom: 1px solid #e5edf5;
        }
        .lm-live-chat-toolbar {
            padding: 12px;
        }
        .lm-live-chat-toolbar h4 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .lm-live-chat-search {
            height: 38px;
            font-size: 13px;
        }
        .lm-live-chat-item {
            padding: 10px;
            border-radius: 14px;
        }
        .lm-live-chat-avatar {
            width: 42px;
            height: 42px;
            font-size: 15px;
        }
        .lm-live-chat-name {
            font-size: 14px;
        }
        .lm-live-chat-preview {
            font-size: 11px;
        }
        .lm-live-chat-main {
            border-right: 0;
            min-height: 400px;
        }
        .lm-live-chat-mainbar {
            padding: 12px;
            flex-wrap: wrap;
        }
        .lm-live-chat-main-title {
            font-size: 16px;
        }
        .lm-live-chat-main-actions {
            width: 100%;
            margin-top: 8px;
        }
        .lm-live-chat-frame {
            min-height: 350px;
        }
        .lm-live-chat-side {
            padding: 14px;
        }
        .lm-live-chat-profile-avatar {
            width: 64px;
            height: 64px;
            font-size: 22px;
        }
        .lm-live-chat-profile-name {
            font-size: 20px;
        }

        /* Status chart shell */
        .lm-chart-shell {
            min-height: 160px;
        }

        /* Iframe modal */
        .lm-dashboard-iframe-modal .modal-dialog {
            width: 100%;
            margin: 0;
        }
        .lm-dashboard-iframe-modal .modal-body {
            height: 70vh;
        }

        /* Action buttons: stack on mobile */
        .lm-action-buttons {
            flex-direction: column;
        }
        .lm-action-btn {
            width: 100%;
            justify-content: center;
            min-height: 36px;
        }
    }

    /* ============================================================
       DASHBOARD MOBILE: Small Phone (max-width: 400px)
       ============================================================ */
    @media (max-width: 400px) {
        .lm-dashboard-hero {
            padding: 12px;
        }
        .lm-dashboard-title {
            font-size: 19px;
        }
        .lm-dashboard-subtitle {
            font-size: 12px;
        }
        .lm-hero-metric {
            flex: 0 0 100px;
            min-width: 100px;
            padding: 5px 6px;
        }
        .lm-hero-metric-value {
            font-size: 14px;
        }
        .lm-dashboard-cards {
            grid-template-columns: repeat(2, 1fr);
            gap: 4px;
        }
        .lm-stat-card {
            padding: 6px 8px;
            gap: 6px;
        }
        .lm-stat-card__icon {
            flex: 0 0 24px;
            width: 24px;
            height: 24px;
            font-size: 11px;
            border-radius: 6px;
        }
        .lm-stat-card__label {
            font-size: 9px;
        }
        .lm-stat-card__value {
            font-size: 14px;
        }
        .lm-quick-box {
            padding: 12px;
        }
        .lm-quick-box__title {
            font-size: 13px;
        }
        .lm-chat-card__title {
            font-size: 17px;
        }
    }

    /* ============================================================
       DASHBOARD MOBILE: Touch Enhancements
       ============================================================ */
    @media (pointer: coarse) {
        .lm-dashboard-tab {
            min-height: 44px;
        }
        .lm-quick-box:hover {
            transform: none;
        }
        .lm-chat-card__item:hover {
            transform: none;
            background: #f0f5ff;
        }
        .lm-live-chat-item:hover {
            transform: none;
        }
    }

    /* ============================================================
       PAYMENT MODAL: Mobile Responsive
       ============================================================ */
    @media (max-width: 767px) {
        .view_modal .modal-content {
            border-radius: 14px;
            margin: 8px;
        }
        .view_modal .modal-header {
            padding: 14px 16px;
        }
        .view_modal .modal-header .modal-title {
            font-size: 15px;
        }
        .view_modal .modal-body {
            padding: 14px 16px;
        }
        .view_modal .modal-body .well {
            padding: 10px 12px;
            border-radius: 10px;
        }
        .view_modal .modal-body .loan-payment-line .col-md-3,
        .view_modal .modal-body .loan-payment-line .col-md-2,
        .view_modal .modal-body .loan-payment-line .col-md-1 {
            width: 100%;
            flex: 0 0 100%;
            max-width: 100%;
            margin-bottom: 8px;
        }
        .view_modal .modal-body .box-body {
            padding: 12px;
        }
        .view_modal .modal-footer {
            padding: 12px 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .view_modal .modal-footer .btn {
            flex: 1;
            min-width: 100px;
        }
    }

    /* ============================================================
       DASHBOARD MOBILE: Safe areas & bottom nav offset
       ============================================================ */
    @media (max-width: 767px) {
        .lm-dashboard-pane {
            padding-bottom: 8px;
            overflow: hidden;
            max-width: 100%;
            min-width: 0;
        }
        .lm-chart-shell {
            min-height: auto;
            overflow: hidden;
        }
    }
</style>
@endsection

<div class="lm-dashboard">
    <div class="lm-dashboard-tabs" role="tablist" aria-label="Loan dashboard tabs">
        <button type="button" class="lm-dashboard-tab is-active" data-dashboard-tab="overview" aria-pressed="true">Overview</button>
        <button type="button" class="lm-dashboard-tab" data-dashboard-tab="live" aria-pressed="false">Live Chat</button>
    </div>

    <div class="lm-dashboard-pane is-active" data-dashboard-pane="overview">
    <section class="lm-dashboard-hero">
        <div class="lm-dashboard-hero-grid">
            <div>
                <h1 class="lm-dashboard-title">Loan Control Center</h1>
                <p class="lm-dashboard-subtitle">Manage loans, collect payments, track overdue customers.</p>
            </div>
            <div class="lm-hero-metrics">
                @foreach($heroMetrics as $metric)
                    <div class="lm-hero-metric">
                        <span class="lm-hero-metric-label">{{ $metric['label'] }}</span>
                        <span class="lm-hero-metric-value">{{ $metric['format'] === 'money' ? number_format((float) $metric['value'], 2) : (int) $metric['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lm-dashboard-cards">
        @foreach($cards as $card)
            @php $val = $quickCards[$card['key']] ?? 0; @endphp
            <article class="lm-stat-card">
                <span class="lm-stat-card__icon lm-tone-{{ $card['tone'] }}"><i class="{{ $card['icon'] }}"></i></span>
                <div>
                    <span class="lm-stat-card__label">{{ $card['label'] }}</span>
                    <span class="lm-stat-card__value" data-loan-card="{{ $card['key'] }}" data-format="{{ in_array($card['key'], ['collection_amount_today']) ? 'money' : 'int' }}">{{ in_array($card['key'], ['collection_amount_today']) ? number_format((float) $val, 2) : (int) $val }}</span>
                    <span class="lm-stat-card__meta">Updated live from the loan dashboard feed</span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="lm-dashboard-grid">
        <div class="lm-dashboard-panel lm-dashboard-panel--feature">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">Quick Actions</h3>
                    <p class="lm-dashboard-panel__hint">Search loans, collect payment, create new loans.</p>
                </div>
                <span class="lm-dashboard-panel__badge"><i class="fa fa-bolt"></i> 3 smart tools</span>
            </div>
            <div class="lm-dashboard-panel__body">
                <div class="lm-quick-grid">
                    <div class="lm-quick-box lm-quick-box--loan">
                        <h4 class="lm-quick-box__title"><span class="lm-quick-box__icon lm-quick-box__icon--pay"><i class="fa fa-money"></i></span> Collect Payment</h4>
                        <p class="lm-quick-box__subtitle">Search by name, phone, or loan # to collect payment.</p>
                        <div class="lm-quick-box__meta">
                            <span class="lm-quick-box__chip lm-quick-box__chip--pay"><i class="fa fa-calendar"></i> Due Date</span>
                            <span class="lm-quick-box__chip lm-quick-box__chip--pay"><i class="fa fa-money"></i> Balance</span>
                            <span class="lm-quick-box__chip lm-quick-box__chip--pay"><i class="fa fa-check-circle"></i> Quick Pay</span>
                        </div>
                        <div class="form-group lm-quick-input" style="margin-bottom:12px;">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                <input type="text" class="form-control" id="loanDashboardQuickSearchInput" placeholder="Search loan #, customer name, phone...">
                            </div>
                        </div>
                        <div class="table-responsive lm-table-wrap">
                            <table class="table table-condensed table-bordered lm-dashboard-table lm-mini-table" id="loanDashboardQuickSearchTable">
                                <thead><tr><th>Customer</th><th>Due</th><th class="text-right">Balance</th><th class="text-center">Pay</th></tr></thead>
                                <tbody data-loan-table="dashboard_quick_search">
                                    <tr><td colspan="4" class="text-center text-muted">Type to search for payment collection.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="lm-quick-box__footer"><i class="fa fa-bolt"></i> Search customer name or phone for fast payment.</div>
                    </div>

                    <div class="lm-quick-box lm-quick-box--sell">
                        <h4 class="lm-quick-box__title"><span class="lm-quick-box__icon"><i class="fa fa-plus-square"></i></span> <a href="{{ route('loan-management.loans.create') }}" style="color:inherit;text-decoration:none;">New Loan</a></h4>
                        <p class="lm-quick-box__subtitle">Create a loan without POS sell.</p>
                        <div class="lm-quick-box__meta">
                            <span class="lm-quick-box__chip"><i class="fa fa-user"></i> Select customer</span>
                            <span class="lm-quick-box__chip"><i class="fa fa-shopping-cart"></i> Add items</span>
                            <span class="lm-quick-box__chip"><i class="fa fa-credit-card"></i> Set terms</span>
                        </div>
                        <div class="lm-quick-box__footer">
                            <a href="{{ route('loan-management.loans.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Create New Loan</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="lm-dashboard-panel lm-dashboard-panel--feature">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">Customer Chat</h3>
                    <p class="lm-dashboard-panel__hint">Recent conversations before field follow-up.</p>
                </div>
                <span class="lm-dashboard-panel__badge"><i class="fa fa-comments"></i> {{ $dashboardUnreadChats }} unread</span>
            </div>
            <div class="lm-dashboard-panel__body">
                <div class="lm-chat-card">
                    <div class="lm-chat-card__toolbar">
                        <h4 class="lm-chat-card__title">ការជជែក</h4>
                        <div class="lm-chat-card__actions">
                            <span class="lm-chat-card__icon" aria-hidden="true"><i class="fa fa-ellipsis-h"></i></span>
                            <span class="lm-chat-card__icon" aria-hidden="true"><i class="fa fa-expand"></i></span>
                            @if(Route::has('loan-management.chat.index'))
                                <a href="{{ route('loan-management.chat.index') }}" class="lm-chat-card__icon" title="Open Messenger style inbox">
                                    <i class="fa fa-pencil-square-o"></i>
                                </a>
                            @else
                                <span class="lm-chat-card__icon" aria-hidden="true"><i class="fa fa-pencil-square-o"></i></span>
                            @endif
                        </div>
                    </div>

                    <div class="lm-chat-card__search">
                        <i class="fa fa-search"></i>
                        <span>ស្វែងរក Messenger</span>
                    </div>

                    <div class="lm-chat-card__tabs">
                        <span class="lm-chat-card__tab is-active">ទាំងអស់</span>
                        <span class="lm-chat-card__tab">មិនទាន់អាន</span>
                        <span class="lm-chat-card__tab">ក្រុម</span>
                        <span class="lm-chat-card__tab"><i class="fa fa-ellipsis-h"></i></span>
                    </div>

                    <div class="lm-chat-card__summary">
                        <div class="lm-chat-card__summary-box">
                            <span class="lm-chat-card__summary-label">Unread queue</span>
                            <span class="lm-chat-card__summary-value">{{ $dashboardUnreadChats }}</span>
                            <span class="lm-chat-card__summary-note">Messages waiting for staff reply</span>
                        </div>
                        <div class="lm-chat-card__summary-box">
                            <span class="lm-chat-card__summary-label">Pending visits</span>
                            <span class="lm-chat-card__summary-value">{{ $dashboardPendingVisits }}</span>
                            <span class="lm-chat-card__summary-note">Field follow-up cases linked to chat</span>
                        </div>
                    </div>

                    <div class="lm-chat-card__list">
                        <div class="lm-chat-card__request">
                            <span class="lm-chat-card__request-avatar"><i class="fa fa-comments"></i></span>
                            <div>
                                <p class="lm-chat-card__request-title">New message request</p>
                                <p class="lm-chat-card__request-subtitle">
                                    {{ $dashboardUnreadChats > 0 ? $dashboardUnreadChats.' unread customer chat(s) waiting for reply.' : 'No unread customer chats right now.' }}
                                </p>
                            </div>
                            <span class="lm-chat-card__request-arrow"><i class="fa fa-angle-right"></i></span>
                        </div>

                        @if(!empty($recentChats))
                            @foreach($recentChats as $chat)
                                @php
                                    $chatUrl = Route::has('loan-management.chat.detail')
                                        ? route('loan-management.chat.detail', $chat['id'])
                                        : (Route::has('loan-management.chat.index') ? route('loan-management.chat.index') : '#');
                                    $chatName = trim((string) ($chat['display_name'] ?? 'Customer Chat'));
                                    $avatarSeed = mb_substr($chatName !== '' ? $chatName : 'C', 0, 1);
                                    $previewText = trim((string) ($chat['last_message'] ?: ($chat['display_subtitle'] ?: 'Open the conversation to continue the follow-up.')));
                                    $timeText = !empty($chat['last_message_at']) ? \Carbon\Carbon::parse($chat['last_message_at'])->diffForHumans() : ucfirst((string) ($chat['status'] ?: 'open'));
                                @endphp
                                <a href="{{ $chatUrl }}" class="lm-chat-card__item">
                                    <span class="lm-chat-card__avatar-wrap">
                                        <span class="lm-chat-card__avatar">{{ $avatarSeed }}</span>
                                        <span class="lm-chat-card__presence"></span>
                                    </span>
                                    <span>
                                        <span class="lm-chat-card__name">{{ \Illuminate\Support\Str::limit($chatName, 34) }}</span>
                                        <span class="lm-chat-card__preview">
                                            {{ \Illuminate\Support\Str::limit($previewText, 60) }}
                                            <span class="lm-chat-card__time">&middot; {{ $timeText }}</span>
                                        </span>
                                    </span>
                                    @if(($chat['unread_count'] ?? 0) > 0)
                                        <span class="lm-chat-card__dot" title="{{ (int) $chat['unread_count'] }} unread"></span>
                                    @else
                                        <span></span>
                                    @endif
                                </a>
                            @endforeach
                        @else
                            <div class="lm-chat-card__empty">
                                <p style="margin:0 0 12px;">
                                    {{ $dashboardPendingVisits > 0 ? $dashboardPendingVisits.' pending collection visit(s) still need follow-up.' : 'No recent customer chats yet. Open the inbox to start a conversation.' }}
                                </p>
                                @if(Route::has('loan-management.chat.index'))
                                    <a href="{{ route('loan-management.chat.index') }}" class="btn btn-primary btn-sm">
                                        <i class="fa fa-comments"></i> Open Live Chat
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="lm-side-stack">
            <div class="lm-dashboard-panel">
                <div class="lm-dashboard-panel__header">
                    <div>
                        <h3 class="lm-dashboard-panel__title">Overdue Customers</h3>
                        <p class="lm-dashboard-panel__hint">Need immediate follow-up today.</p>
                    </div>
                </div>
                <div class="lm-dashboard-panel__body lm-table-wrap">
                    <table class="table table-condensed lm-dashboard-table lm-mini-table" id="loanOverdueCustomersTable">
                        <thead><tr><th>Customer</th><th>Days</th><th class="text-right">Amount</th></tr></thead>
                        <tbody data-loan-table="overdue_customers">
                        @forelse(($overdueCustomers ?? []) as $row)
                            <tr>
                                <td><span class="lm-row-title">{{ $row['customer'] ?? '-' }}</span></td>
                                <td>{{ (int)($row['overdue_days'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format((float)($row['overdue_amount'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">No overdue customers.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lm-dashboard-panel">
                <div class="lm-dashboard-panel__header">
                    <div>
                        <h3 class="lm-dashboard-panel__title">Loan Status Overview</h3>
                        <p class="lm-dashboard-panel__hint">Loan status distribution.</p>
                    </div>
                </div>
                <div class="lm-dashboard-panel__body">
                    <div class="lm-chart-shell">
                        <div class="lm-chart-copy">
                            <strong>Status Snapshot</strong>
                            <small id="loanStatusChartText" data-loan-chart="loan_status">Status labels: {{ implode(', ', $loanStatusChart['labels'] ?? []) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lm-dashboard-grid">
        <div class="lm-dashboard-panel">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">Visit Schedule</h3>
                    <p class="lm-dashboard-panel__hint">Pending fieldwork assignments.</p>
                </div>
            </div>
            <div class="lm-dashboard-panel__body lm-table-wrap">
                <table class="table table-bordered table-condensed lm-dashboard-table" id="loanVisitScheduleTable">
                    <thead><tr><th>Customer</th><th>Date</th><th>Status</th><th>Staff</th></tr></thead>
                    <tbody data-loan-table="follow_up_customers">
                    @forelse(($visitSchedule ?? []) as $row)
                        <tr>
                            <td><span class="lm-row-title">{{ $row['customer'] ?? '-' }}</span></td>
                            <td>{{ $row['follow_up_date'] ?? '-' }}</td>
                            <td>{{ $row['status'] ?? '-' }}</td>
                            <td>{{ $row['assigned_staff'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No pending visits.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lm-dashboard-panel">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">Collector Performance</h3>
                    <p class="lm-dashboard-panel__hint">Output, loans, and visits by collector.</p>
                </div>
            </div>
            <div class="lm-dashboard-panel__body lm-table-wrap">
                <table class="table table-striped table-bordered lm-dashboard-table" id="loanCollectorPerformanceTable">
                    <thead><tr><th>Collector</th><th>Assigned Loans</th><th class="text-right">Collected</th><th>Visits</th></tr></thead>
                    <tbody data-loan-table="collector_performance">
                    @forelse(($collectorPerformance ?? []) as $row)
                        <tr>
                            <td><span class="lm-row-title">{{ $row['collector'] ?? '-' }}</span></td>
                            <td>{{ (int)($row['assigned_loans'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format((float)($row['collected_amount'] ?? 0), 2) }}</td>
                            <td>{{ (int)($row['visit_count'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No collector performance data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

    <div class="lm-dashboard-pane" data-dashboard-pane="live">
        @php
            $initialLiveChat = collect($recentChats ?? [])->first();
        @endphp
        <section class="lm-dashboard-panel lm-dashboard-panel--feature">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">Live Chat</h3>
                    <p class="lm-dashboard-panel__hint">Conversations, unread queues, and support activity.</p>
                </div>
                <span class="lm-live-badge"><span class="lm-live-badge__dot"></span> Auto refresh 30s</span>
            </div>
            <div class="lm-dashboard-panel__body">
                <div class="lm-live-chat-shell">
                    <aside class="lm-live-chat-inbox">
                        <div class="lm-live-chat-toolbar">
                            <h4>Chats</h4>
                            <input type="text" class="lm-live-chat-search" id="loanDashboardLiveChatSearch" placeholder="Search Messenger style inbox">
                        </div>
                        <div class="lm-live-chat-list" id="loanDashboardLiveChatList">
                            <div class="lm-live-chat-empty">Loading live chats...</div>
                        </div>
                    </aside>

                    <main class="lm-live-chat-main">
                        <div class="lm-live-chat-mainbar">
                            <div>
                                <h4 class="lm-live-chat-main-title" id="loanDashboardLiveChatTitle">{{ $initialLiveChat['display_name'] ?? 'Select a chat' }}</h4>
                                <p class="lm-live-chat-main-subtitle" id="loanDashboardLiveChatSubtitle">{{ $initialLiveChat['display_subtitle'] ?? 'Open a customer conversation from the inbox list.' }}</p>
                            </div>
                            <div class="lm-live-chat-main-actions">
                            <a href="{{ route('loan-management.live-chat') }}" class="btn btn-default btn-sm">
                                <i class="fa fa-external-link"></i> Open Full Inbox
                            </a>
                            @if(!empty($initialLiveChat['id']))
                                <a href="{{ route('loan-management.live-chat.detail', $initialLiveChat['id']) }}" class="btn btn-primary btn-sm" id="loanDashboardLiveChatOpenBtn">
                                    <i class="fa fa-comments"></i> Open Conversation
                                    </a>
                                @else
                                    <a href="{{ route('loan-management.live-chat') }}" class="btn btn-primary btn-sm" id="loanDashboardLiveChatOpenBtn">
                                        <i class="fa fa-comments"></i> Open Conversation
                                    </a>
                                @endif
                            </div>
                        </div>
                        <iframe
                            id="loanDashboardLiveChatFrame"
                            class="lm-live-chat-frame"
                            src="{{ !empty($initialLiveChat['id']) ? route('loan-management.live-chat.detail', ['thread' => $initialLiveChat['id'], '_lm_embed' => 1]) : route('loan-management.live-chat', ['_lm_embed' => 1]) }}"
                            title="Loan live chat dashboard"></iframe>
                    </main>

                    <aside class="lm-live-chat-side">
                        <div class="lm-live-chat-profile">
                            <div class="lm-live-chat-profile-avatar" id="loanDashboardLiveChatProfileAvatar">
                                {{ strtoupper(substr((string) ($initialLiveChat['display_name'] ?? 'C'), 0, 1)) }}
                            </div>
                            <h4 class="lm-live-chat-profile-name" id="loanDashboardLiveChatProfileName">{{ $initialLiveChat['display_name'] ?? 'Customer Chat' }}</h4>
                            <p class="lm-live-chat-profile-subtitle" id="loanDashboardLiveChatProfileSubtitle">{{ $initialLiveChat['display_subtitle'] ?? 'Loan support inbox' }}</p>
                            <p class="lm-live-chat-profile-time" id="loanDashboardLiveChatProfileTime">
                                {{ !empty($initialLiveChat['last_message_at']) ? \Carbon\Carbon::parse($initialLiveChat['last_message_at'])->diffForHumans() : 'Waiting for live activity' }}
                            </p>
                        </div>

                        <div class="lm-live-chat-side-section">
                            <h5 class="lm-live-chat-side-title">Conversation Summary</h5>
                            <div class="lm-live-chat-side-row"><span>Status</span><span id="loanDashboardLiveChatStatus">{{ ucfirst((string) ($initialLiveChat['status'] ?? 'open')) }}</span></div>
                            <div class="lm-live-chat-side-row"><span>Priority</span><span id="loanDashboardLiveChatPriority">{{ ucfirst((string) ($initialLiveChat['priority'] ?? 'normal')) }}</span></div>
                            <div class="lm-live-chat-side-row"><span>Assigned Team</span><span id="loanDashboardLiveChatTeam">{{ $initialLiveChat['assigned_team'] ?? 'Support' }}</span></div>
                            <div class="lm-live-chat-side-row"><span>Unread</span><span id="loanDashboardLiveChatUnread">{{ (int) ($initialLiveChat['unread_count'] ?? 0) }}</span></div>
                        </div>

                        <div class="lm-live-chat-side-section">
                            <h5 class="lm-live-chat-side-title">Last Message</h5>
                            <div id="loanDashboardLiveChatLastMessage" style="color:#334155; font-size:13px; line-height:1.6;">
                                {{ $initialLiveChat['last_message'] ?? 'No recent message yet.' }}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</div>

@section('loan_js')
@parent
<script>
    (function ($) {
        if (!window.jQuery) {
            return;
        }

        var liveUrl = "{{ route('loan-management.dashboard.data', [], true) }}";
        var quickSearchUrl = "{{ route('loan-management.dashboard.quick-search', [], true) }}";
        var refreshMs = 30000;
        var loading = false;
        var timer = null;
        var quickSearchTimer = null;
        var liveTabLoaded = false;
        var liveChatSearchTimer = null;
        var liveChatThreads = [];
        var activeLiveChatId = {{ (int) ($initialLiveChat['id'] ?? 0) }};
        var liveChatApiUrl = "{{ route('loan-management.chat-api.index') }}";
        var liveChatFrameBaseUrl = "{{ url('loan-management/live-chat') }}";

        function money(value) {
            var number = parseFloat(value || 0);
            return Number.isFinite(number) ? number.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00';
        }

        function intValue(value) {
            var number = parseInt(value || 0, 10);
            return Number.isFinite(number) ? String(number) : '0';
        }

        function esc(value) {
            return $('<div>').text(value == null ? '-' : value).html();
        }

        function openDashboardIframeModal(title, url) {
            if (!url || !$('.view_modal').length) {
                return;
            }

            var html = '' +
                '<div class="modal-dialog modal-xl lm-dashboard-iframe-modal" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<h4 class="modal-title">' + esc(title || 'Detail') + '</h4>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<iframe src="' + esc(url) + '" title="' + esc(title || 'Detail') + '"></iframe>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $('.view_modal')
                .html(html)
                .modal('show');
        }

        function updateCards(cards) {
            $('[data-loan-card]').each(function () {
                var key = $(this).data('loan-card');
                var value = cards && Object.prototype.hasOwnProperty.call(cards, key) ? cards[key] : 0;
                $(this).text($(this).data('format') === 'money' ? money(value) : intValue(value));
            });
        }

        function renderRecentPayments(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td>'+esc(row.paid_date)+'</td><td>'+esc(row.customer_name_snapshot)+'</td><td>'+esc(row.loan_number)+'</td><td>'+esc(row.payment_method)+'</td><td class="text-right">'+money(row.paid_amount)+'</td></tr>';
            });
            $('[data-loan-table="recent_payments"]').html(html || '<tr><td colspan="5" class="text-center">No recent payments found.</td></tr>');
        }

        function renderOverdueCustomers(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td><span class="lm-row-title">'+esc(row.customer)+'</span></td><td>'+intValue(row.overdue_days)+'</td><td class="text-right">'+money(row.overdue_amount)+'</td></tr>';
            });
            $('[data-loan-table="overdue_customers"]').html(html || '<tr><td colspan="3" class="text-center">No overdue customers.</td></tr>');
        }

        function renderQuickSearch(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                var detailUrl = "{{ url('loan-management/loans') }}/" + row.id + "/view?_lm_modal=1";
                var editUrl = "{{ url('loan-management/loans') }}/" + row.id + "/edit?_lm_modal=1";
                var printModalUrl = "{{ url('loan-management/loans') }}/" + row.id + "/print-modal";
                var payUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payment/create?return_to={{ rawurlencode(route('loan-management.dashboard')) }}";
                var quickPayUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payment/quick-pay";
                var addToPosUrl = "{{ url('loan-management/loans') }}/" + row.id + "/convert-to-pos?modal=1";
                var dueLabel = row.next_due_date ? esc(row.next_due_date) : '<span class="text-muted">-</span>';
                var isOverdue = row.status && (String(row.status).toLowerCase() === 'overdue' || String(row.status).toLowerCase() === 'late');
                var statusBadge = isOverdue
                    ? '<span class="lm-pay-status lm-pay-status--overdue">OVERDUE</span>'
                    : (row.status && String(row.status).toLowerCase() !== 'active' ? '<span class="lm-pay-status">' + esc(row.status) + '</span>' : '');
                html += '<tr class="lm-pay-row">'
                    + '<td>'
                    + '<a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="Loan Detail" data-url="' + detailUrl + '">' + esc(row.customer_name) + '</a>'
                    + '<span class="lm-row-subtitle">' + esc(row.loan_number) + (row.customer_phone && row.customer_phone !== '-' ? ' &middot; ' + esc(row.customer_phone) : '') + '</span>'
                    + statusBadge
                    + '</td>'
                    + '<td class="lm-pay-due">' + dueLabel + '</td>'
                    + '<td class="text-right lm-pay-balance">' + money(row.balance_amount) + '</td>'
                    + '<td class="text-center lm-pay-action">'
                    + '<button type="button" class="btn btn-success btn-xs lm-pay-btn btn-modal" data-href="' + payUrl + '" data-container=".view_modal" title="Collect payment for ' + esc(row.customer_name) + '"><i class="fa fa-money"></i> Pay</button>'
                    + '<div class="lm-pay-more dropdown">'
                    + '<button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" title="More actions"><i class="fa fa-ellipsis-h"></i></button>'
                    + '<ul class="dropdown-menu dropdown-menu-right lm-action-menu__list">'
                    + '<li><button type="button" class="js-loan-detail-modal" data-title="Loan Detail" data-url="' + detailUrl + '"><i class="fa fa-eye"></i> View Loan</button></li>'
                    + '<li><button type="button" class="js-loan-detail-modal" data-title="Edit Loan" data-url="' + editUrl + '"><i class="fa fa-pencil"></i> Edit</button></li>'
                    + '<li><button type="button" class="btn-modal" data-href="' + printModalUrl + '" data-container=".view_modal"><i class="fa fa-print"></i> Print</button></li>'
                    + '<li><button type="button" class="btn-modal" data-href="' + addToPosUrl + '" data-container=".view_modal"><i class="fa fa-exchange"></i> Add to POS</button></li>'
                    + '</ul>'
                    + '</div>'
                    + '</td>'
                    + '</tr>';
            });
            $('[data-loan-table="dashboard_quick_search"]').html(html || '<tr><td colspan="4" class="text-center">No loans found for this search.</td></tr>');
        }

        function runQuickSearch() {
            var term = $.trim($('#loanDashboardQuickSearchInput').val() || '');

            fetch(quickSearchUrl + '?q=' + encodeURIComponent(term), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (res) {
                    renderQuickSearch(res && res.data ? res.data : []);
                })
                .catch(function () {
                    $('[data-loan-table="dashboard_quick_search"]').html('<tr><td colspan="4" class="text-center text-danger">Search failed.</td></tr>');
                });
        }

        function renderFollowUps(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td><span class="lm-row-title">'+esc(row.customer)+'</span></td><td>'+esc(row.follow_up_date)+'</td><td>'+esc(row.status)+'</td><td>'+esc(row.assigned_staff)+'</td></tr>';
            });
            $('[data-loan-table="follow_up_customers"]').html(html || '<tr><td colspan="4" class="text-center">No pending visits.</td></tr>');
        }

        function renderCollectorPerformance(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td><span class="lm-row-title">'+esc(row.collector)+'</span></td><td>'+intValue(row.assigned_loans)+'</td><td class="text-right">'+money(row.collected_amount)+'</td><td>'+intValue(row.visit_count)+'</td></tr>';
            });
            $('[data-loan-table="collector_performance"]').html(html || '<tr><td colspan="4" class="text-center">No collector performance data.</td></tr>');
        }

        function updateChartText(chart) {
            if (!chart || !chart.labels) {
                return;
            }
            $('#loanStatusChartText').text('Status labels: ' + chart.labels.join(', '));
        }

        function compactLabel(value) {
            var raw = String(value == null ? '' : value);
            return raw.length > 10 ? raw.slice(2) : raw;
        }

        function renderLiveBarChart(containerSelector, config) {
            var container = $(containerSelector);
            if (!container.length) {
                return;
            }

            var labels = (config && config.labels) ? config.labels : [];
            var series = (config && config.series) ? config.series : [];
            var legends = (config && config.legends) ? config.legends : [];
            var maxValue = 0;

            series.forEach(function (row) {
                (row && row.values ? row.values : []).forEach(function (value) {
                    maxValue = Math.max(maxValue, Number(value || 0));
                });
            });

            if (!labels.length || !series.length || maxValue <= 0) {
                container.html('<div class="lm-live-chart__empty">No live chart data for this filter range.</div>');
                return;
            }

            var canvas = '<div class="lm-live-chart__canvas">';
            labels.forEach(function (label, index) {
                canvas += '<div class="lm-live-chart__bar-group">';
                canvas += '<div class="lm-live-chart__value">';
                series.forEach(function (row, rowIndex) {
                    var rawValue = Number((row.values || [])[index] || 0);
                    canvas += (rowIndex > 0 ? '<br>' : '') + esc(row.format === 'money' ? money(rawValue) : intValue(rawValue));
                });
                canvas += '</div>';
                canvas += '<div class="lm-live-chart__bar-stack">';
                series.forEach(function (row) {
                    var rawValue = Number((row.values || [])[index] || 0);
                    var percent = maxValue > 0 ? Math.max(4, (rawValue / maxValue) * 100) : 4;
                    canvas += '<span class="lm-live-chart__bar ' + esc(row.className || '') + '" style="height:' + percent + '%"></span>';
                });
                canvas += '</div>';
                canvas += '<div class="lm-live-chart__label">' + esc(compactLabel(label)) + '</div>';
                canvas += '</div>';
            });
            canvas += '</div>';

            var legendHtml = '';
            if (legends.length) {
                legendHtml += '<div class="lm-live-chart__legend">';
                legends.forEach(function (legend) {
                    legendHtml += '<span class="lm-live-chart__legend-item"><span class="lm-live-chart__legend-swatch ' + esc(legend.className || '') + '"></span>' + esc(legend.label || '') + '</span>';
                });
                legendHtml += '</div>';
            }

            container.html(canvas + legendHtml);
        }

        function renderLiveCharts(charts) {
            charts = charts || {};

            renderLiveBarChart('#loanLiveMonthlyLoanChart', {
                labels: charts.monthly_loan ? charts.monthly_loan.labels : [],
                series: [
                    { values: charts.monthly_loan ? charts.monthly_loan.count : [], format: 'int', className: '' },
                    { values: charts.monthly_loan ? charts.monthly_loan.principal : [], format: 'money', className: 'lm-live-chart__bar--accent' }
                ],
                legends: [
                    { label: 'Loan Count', className: '' },
                    { label: 'Principal', className: 'lm-live-chart__legend-swatch--accent' }
                ]
            });

            renderLiveBarChart('#loanLiveDailyCollectionChart', {
                labels: charts.daily_collection ? charts.daily_collection.labels : [],
                series: [
                    { values: charts.daily_collection ? charts.daily_collection.amount : [], format: 'money', className: 'lm-live-chart__bar--accent' }
                ],
                legends: [
                    { label: 'Collected Amount', className: 'lm-live-chart__legend-swatch--accent' }
                ]
            });

            renderLiveBarChart('#loanLiveStatusChart', {
                labels: charts.loan_status ? charts.loan_status.labels : [],
                series: [
                    { values: charts.loan_status ? charts.loan_status.series : [], format: 'int', className: 'lm-live-chart__bar--warn' }
                ],
                legends: [
                    { label: 'Loans', className: 'lm-live-chart__legend-swatch--warn' }
                ]
            });

            renderLiveBarChart('#loanLivePaymentMethodChart', {
                labels: charts.payment_method ? charts.payment_method.labels : [],
                series: [
                    { values: charts.payment_method ? charts.payment_method.amount : [], format: 'money', className: '' }
                ],
                legends: [
                    { label: 'Payment Total', className: '' }
                ]
            });
        }

        function activateDashboardTab(tabKey) {
            $('[data-dashboard-tab]').removeClass('is-active').attr('aria-pressed', 'false');
            $('[data-dashboard-pane]').removeClass('is-active');
            $('[data-dashboard-tab="' + tabKey + '"]').addClass('is-active').attr('aria-pressed', 'true');
            $('[data-dashboard-pane="' + tabKey + '"]').addClass('is-active');

            if (tabKey === 'live' && !liveTabLoaded) {
                liveTabLoaded = true;
                loadLiveChatThreads();
                refreshLoanDashboard();
            }
        }

        function formatLiveChatTime(value) {
            if (!value) {
                return '';
            }

            var date = new Date(value);
            if (isNaN(date.getTime())) {
                return String(value);
            }

            return date.toLocaleString();
        }

        function setLiveChatProfile(thread) {
            thread = thread || {};
            var name = thread.display_name || 'Customer Chat';
            $('#loanDashboardLiveChatTitle, #loanDashboardLiveChatProfileName').text(name);
            $('#loanDashboardLiveChatSubtitle, #loanDashboardLiveChatProfileSubtitle').text(thread.display_subtitle || 'Loan support inbox');
            $('#loanDashboardLiveChatProfileAvatar').text((name.charAt(0) || 'C').toUpperCase());
            $('#loanDashboardLiveChatProfileTime').text(thread.last_message_at ? formatLiveChatTime(thread.last_message_at) : 'Waiting for live activity');
            $('#loanDashboardLiveChatStatus').text(thread.status ? String(thread.status).replace(/_/g, ' ') : 'open');
            $('#loanDashboardLiveChatPriority').text(thread.priority ? String(thread.priority).replace(/_/g, ' ') : 'normal');
            $('#loanDashboardLiveChatTeam').text(thread.assigned_team || 'Support');
            $('#loanDashboardLiveChatUnread').text(intValue(thread.unread_count || 0));
            $('#loanDashboardLiveChatLastMessage').text(thread.last_message || 'No recent message yet.');

            var openUrl = thread.id ? (liveChatFrameBaseUrl + '/' + encodeURIComponent(thread.id)) : liveChatFrameBaseUrl;
            var embedUrl = openUrl + (openUrl.indexOf('?') === -1 ? '?' : '&') + '_lm_embed=1';
            $('#loanDashboardLiveChatOpenBtn').attr('href', openUrl);
            $('#loanDashboardLiveChatFrame').attr('src', embedUrl);
        }

        function renderLiveChatThreads() {
            var list = $('#loanDashboardLiveChatList');
            if (!list.length) {
                return;
            }

            var term = $.trim($('#loanDashboardLiveChatSearch').val() || '').toLowerCase();
            var rows = liveChatThreads.filter(function (thread) {
                if (!term) {
                    return true;
                }

                var hay = [
                    thread.display_name,
                    thread.display_subtitle,
                    thread.last_message,
                    thread.assigned_team,
                    thread.priority
                ].join(' ').toLowerCase();

                return hay.indexOf(term) !== -1;
            });

            if (!rows.length) {
                list.html('<div class="lm-live-chat-empty">No live chats found.</div>');
                return;
            }

            var html = '';
            rows.forEach(function (thread) {
                var activeClass = String(activeLiveChatId || '') === String(thread.id || '') ? ' is-active' : '';
                var unread = Number(thread.unread_count || 0);
                html += '<button type="button" class="lm-live-chat-item' + activeClass + '" data-live-chat-id="' + esc(thread.id || '') + '">'
                    + '<span class="lm-live-chat-avatar">' + esc((thread.display_name || 'C').charAt(0).toUpperCase()) + '</span>'
                    + '<span>'
                    + '<span class="lm-live-chat-name">' + esc(thread.display_name || 'Customer Chat') + '</span>'
                    + '<span class="lm-live-chat-preview">' + esc(thread.last_message || thread.display_subtitle || 'Open conversation') + '</span>'
                    + '</span>'
                    + '<span class="lm-live-chat-meta">'
                    + '<span class="lm-live-chat-time">' + esc(thread.last_message_time || '') + '</span>'
                    + (unread > 0 ? '<span class="lm-live-chat-badge">' + esc(intValue(unread)) + '</span>' : '')
                    + '</span>'
                    + '</button>';
            });

            list.html(html);
        }

        function loadLiveChatThreads() {
            fetch(liveChatApiUrl + '?view=all', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (res) {
                    liveChatThreads = res && res.data ? res.data : [];
                    renderLiveChatThreads();

                    if (!liveChatThreads.length) {
                        setLiveChatProfile({});
                        return;
                    }

                    var selected = liveChatThreads.find(function (thread) {
                        return String(thread.id || '') === String(activeLiveChatId || '');
                    }) || liveChatThreads[0];

                    activeLiveChatId = selected.id || 0;
                    setLiveChatProfile(selected);
                    renderLiveChatThreads();
                })
                .catch(function () {
                    $('#loanDashboardLiveChatList').html('<div class="lm-live-chat-empty">Unable to load live chats right now.</div>');
                });
        }

        function refreshLoanDashboard() {
            if (loading || document.hidden) {
                return;
            }

            loading = true;
            fetch(liveUrl + window.location.search + (window.location.search ? '&' : '?') + 'realtime=1', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    var contentType = response.headers.get('content-type') || '';
                    if (!response.ok || contentType.indexOf('application/json') === -1) {
                        if (timer) {
                            window.clearInterval(timer);
                            timer = null;
                        }
                        return null;
                    }

                    return response.json();
                })
                .then(function (res) {
                    if (!res) {
                        return;
                    }

                    var data = res && res.data ? res.data : {};
                    updateCards(data.quick_cards || data.cards || {});
                    renderOverdueCustomers(data.tables ? data.tables.overdue_customers : []);
                    renderFollowUps(data.tables ? data.tables.follow_up_customers : []);
                    renderCollectorPerformance(data.charts ? data.charts.collector_performance : []);
                    updateChartText(data.charts ? data.charts.loan_status : null);
                    renderLiveCharts(data.charts || {});
                    if ($('[data-dashboard-pane="live"]').hasClass('is-active')) {
                        loadLiveChatThreads();
                    }
                })
                .catch(function () {})
                .finally(function () {
                    loading = false;
                });
        }

        $(function () {
            if (!$('#loanManagementApp').length) {
                return;
            }

            if (window.loanDashboardRealtimeTimer) {
                window.clearInterval(window.loanDashboardRealtimeTimer);
            }

            $('#loanDashboardQuickSearchInput').on('input', function () {
                window.clearTimeout(quickSearchTimer);
                quickSearchTimer = window.setTimeout(runQuickSearch, 250);
            });
            $('#loanDashboardLiveChatSearch').on('input', function () {
                window.clearTimeout(liveChatSearchTimer);
                liveChatSearchTimer = window.setTimeout(renderLiveChatThreads, 160);
            });
            $(document).on('click', '[data-dashboard-tab]', function () {
                activateDashboardTab($(this).data('dashboard-tab'));
            });
            $(document).on('click', '[data-live-chat-id]', function () {
                var threadId = $(this).data('live-chat-id');
                var selected = liveChatThreads.find(function (thread) {
                    return String(thread.id || '') === String(threadId || '');
                });
                if (!selected) {
                    return;
                }

                activeLiveChatId = selected.id || 0;
                setLiveChatProfile(selected);
                renderLiveChatThreads();
            });
            runQuickSearch();
            renderLiveCharts({});
            $(document).on('click', '.js-loan-detail-modal, .js-sell-detail-modal', function (event) {
                event.preventDefault();
                openDashboardIframeModal($(this).data('title') || 'Detail', $(this).data('url'));
            });

            timer = window.setInterval(refreshLoanDashboard, refreshMs);
            window.loanDashboardRealtimeTimer = timer;
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    refreshLoanDashboard();
                }
            });
        });
    })(jQuery);
</script>
@endsection
