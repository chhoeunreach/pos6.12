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
        .lm-quick-grid {
            grid-template-columns: 1fr;
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
    }
</style>
@endsection

<div class="lm-dashboard">
    <section class="lm-dashboard-hero">
        <div class="lm-dashboard-hero-grid">
            <div>
                <h1 class="lm-dashboard-title">Loan Control Center</h1>
                <p class="lm-dashboard-subtitle">Search installment loans, convert sells into installment plans, monitor overdue customers, and track collection performance from one workspace.</p>
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
                    <h3 class="lm-dashboard-panel__title">Installment Quick Actions</h3>
                    <p class="lm-dashboard-panel__hint">Fast search for loan operations and sell conversion in one place.</p>
                </div>
                <span class="lm-dashboard-panel__badge"><i class="fa fa-bolt"></i> 2 smart tools</span>
            </div>
            <div class="lm-dashboard-panel__body">
                <div class="lm-quick-grid">
                    <div class="lm-quick-box lm-quick-box--loan">
                        <h4 class="lm-quick-box__title"><span class="lm-quick-box__icon"><i class="fa fa-search"></i></span> Loan Search</h4>
                        <p class="lm-quick-box__subtitle">Find installment loans by loan number, customer, or phone. Then print invoice or add monthly payment.</p>
                        <div class="lm-quick-box__meta">
                            <span class="lm-quick-box__chip"><i class="fa fa-print"></i> Print</span>
                            <span class="lm-quick-box__chip"><i class="fa fa-money"></i> Payment</span>
                            <span class="lm-quick-box__chip"><i class="fa fa-user"></i> Customer lookup</span>
                        </div>
                        <div class="form-group lm-quick-input" style="margin-bottom:12px;">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                <input type="text" class="form-control" id="loanDashboardQuickSearchInput" placeholder="Search loan #, customer, phone">
                            </div>
                        </div>
                        <div class="table-responsive lm-table-wrap">
                            <table class="table table-condensed table-bordered lm-dashboard-table lm-mini-table" id="loanDashboardQuickSearchTable">
                                <thead><tr><th>Loan</th><th>Customer</th><th class="text-right">Balance</th><th>Action</th></tr></thead>
                                <tbody data-loan-table="dashboard_quick_search">
                                    <tr><td colspan="4" class="text-center text-muted">Type to search installment loans.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="lm-quick-box__footer">Use this for fast payment collection and loan document access.</div>
                    </div>

                    <div class="lm-quick-box lm-quick-box--sell">
                        <h4 class="lm-quick-box__title"><span class="lm-quick-box__icon"><i class="fa fa-plus-square"></i></span> Create Loan From Sell</h4>
                        <p class="lm-quick-box__subtitle">Search POS sell invoices and jump straight into the add-installment flow from the dashboard.</p>
                        <div class="lm-quick-box__meta">
                            <span class="lm-quick-box__chip"><i class="fa fa-shopping-cart"></i> Open POS</span>
                            <span class="lm-quick-box__chip"><i class="fa fa-credit-card"></i> Convert sell</span>
                            <span class="lm-quick-box__chip"><i class="fa fa-print"></i> Auto print</span>
                        </div>
                        <div class="form-group lm-quick-input" style="margin-bottom:12px;">
                            <div class="input-group">
                                <span class="input-group-addon lm-quick-trigger" id="loanDashboardOpenSellPos" title="Add POS Sell" role="button" tabindex="0"><i class="fa fa-plus-square"></i></span>
                                <input type="text" class="form-control" id="loanDashboardSellSearchInput" placeholder="Search sell invoice, customer, phone">
                            </div>
                        </div>
                        <div class="table-responsive lm-table-wrap">
                            <table class="table table-condensed table-bordered lm-dashboard-table lm-mini-table" id="loanDashboardSellSearchTable">
                                <thead><tr><th>Invoice</th><th>Customer</th><th class="text-right">Due</th><th>Action</th></tr></thead>
                                <tbody data-loan-table="dashboard_sell_search">
                                    <tr><td colspan="4" class="text-center text-muted">Type to search sells for installment.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="lm-quick-box__footer">Best for creating installment loans directly from completed POS sales.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lm-dashboard-panel lm-dashboard-panel--feature">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">Customer Chat</h3>
                    <p class="lm-dashboard-panel__hint">Recent customer conversations before field collection follow-up.</p>
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
                        <p class="lm-dashboard-panel__hint">Customers who need immediate follow-up today.</p>
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
                        <p class="lm-dashboard-panel__hint">Quick glance chart placeholder for status mix.</p>
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
                    <h3 class="lm-dashboard-panel__title">Collection Visit Schedule</h3>
                    <p class="lm-dashboard-panel__hint">Pending fieldwork and customer follow-up assignments.</p>
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
                    <p class="lm-dashboard-panel__hint">Collection output, assigned loans, and visit activity by collector.</p>
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
        var sellSearchTimer = null;

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
                var printModalUrl = "{{ url('loan-management/loans') }}/" + row.id + "/print-modal";
                var payUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payment/create?return_to={{ rawurlencode(route('loan-management.dashboard')) }}";
                html += '<tr>'
                    + '<td><a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="Loan Detail" data-url="' + detailUrl + '">' + esc(row.loan_number) + '</a><span class="lm-row-subtitle">' + esc(row.status) + (row.next_due_date ? ' / Due ' + esc(row.next_due_date) : '') + '</span></td>'
                    + '<td><a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="Loan Detail" data-url="' + detailUrl + '">' + esc(row.customer_name) + '</a><span class="lm-row-subtitle">' + esc(row.customer_phone) + '</span></td>'
                    + '<td class="text-right">' + money(row.balance_amount) + '</td>'
                    + '<td class="text-nowrap">'
                    + '<div class="btn-group lm-action-menu">'
                    + '<button type="button" class="btn dropdown-toggle lm-action-menu__toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-bars"></i> Actions <span class="caret"></span></button>'
                    + '<ul class="dropdown-menu dropdown-menu-right lm-action-menu__list">'
                    + '<li><button type="button" class="js-loan-detail-modal" data-title="Loan Detail" data-url="' + detailUrl + '"><i class="fa fa-eye"></i> Detail</button></li>'
                    + '<li><button type="button" class="btn-modal" data-href="' + printModalUrl + '" data-container=".view_modal"><i class="fa fa-print"></i> Print Loan</button></li>'
                    + '<li><button type="button" class="btn-modal" data-href="' + payUrl + '" data-container=".view_modal"><i class="fa fa-money"></i> Add Payment</button></li>'
                    + '</ul>'
                    + '</div>'
                    + '</td>'
                    + '</tr>';
            });
            $('[data-loan-table="dashboard_quick_search"]').html(html || '<tr><td colspan="4" class="text-center">No installment loans found.</td></tr>');
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

        function renderSellSearch(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                var addUrl = "{{ url('loan-management/loans/sell') }}/" + row.id + "/clone";
                var detailUrl = "{{ action([\App\Http\Controllers\SellController::class, 'show'], ['__ROW_ID__']) }}".replace('__ROW_ID__', encodeURIComponent(row.id));
                var viewLoanUrl = row.linked_loan_id ? "{{ url('loan-management/loans') }}/" + row.linked_loan_id + "/view?_lm_modal=1" : '';
                html += '<tr>'
                    + '<td><a href="#" class="lm-row-title btn-modal" data-container=".view_modal" data-href="' + detailUrl + '">' + esc(row.invoice_no) + '</a><span class="lm-row-subtitle">Total ' + money(row.final_total) + '</span></td>'
                    + '<td><a href="#" class="lm-row-title btn-modal" data-container=".view_modal" data-href="' + detailUrl + '">' + esc(row.customer_name) + '</a><span class="lm-row-subtitle">' + esc(row.customer_phone) + '</span></td>'
                    + '<td class="text-right">' + money(row.due_amount) + '</td>'
                    + '<td class="text-nowrap">'
                    + '<div class="btn-group lm-action-menu">'
                    + '<button type="button" class="btn dropdown-toggle lm-action-menu__toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-bars"></i> Actions <span class="caret"></span></button>'
                    + '<ul class="dropdown-menu dropdown-menu-right lm-action-menu__list">';

                if (row.is_converted && viewLoanUrl) {
                    html += '<li><button type="button" class="btn-modal" data-container=".view_modal" data-href="' + detailUrl + '"><i class="fa fa-file-text-o"></i> Sell Detail</button></li>';
                    html += '<li><button type="button" class="js-loan-detail-modal" data-title="Loan Detail" data-url="' + viewLoanUrl + '"><i class="fa fa-eye"></i> View Loan</button></li>';
                } else {
                    html += '<li><button type="button" class="btn-modal" data-container=".view_modal" data-href="' + detailUrl + '"><i class="fa fa-file-text-o"></i> Detail</button></li>';
                    html += '<li><button type="button" class="btn-select-sale" data-id="' + row.id + '"><i class="fa fa-plus"></i> Add Installment</button></li>';
                }

                html += '</ul></div></td></tr>';
            });
            $('[data-loan-table="dashboard_sell_search"]').html(html || '<tr><td colspan="4" class="text-center">No sells found.</td></tr>');
        }

        function runSellSearch() {
            var term = $.trim($('#loanDashboardSellSearchInput').val() || '');

            fetch(quickSearchUrl + '?scope=sell&q=' + encodeURIComponent(term), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (res) {
                    renderSellSearch(res && res.data ? res.data : []);
                })
                .catch(function () {
                    $('[data-loan-table="dashboard_sell_search"]').html('<tr><td colspan="4" class="text-center text-danger">Search failed.</td></tr>');
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
            $('#loanDashboardSellSearchInput').on('input', function () {
                window.clearTimeout(sellSearchTimer);
                sellSearchTimer = window.setTimeout(runSellSearch, 250);
            });
            runQuickSearch();
            runSellSearch();
            $('#loanDashboardOpenSellPos').on('click keydown', function (event) {
                if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                if (typeof window.loanManagementOpenSellPos === 'function' && window.loanManagementOpenSellPos()) {
                    return;
                }
                var sharedPosTrigger = $('#loanHeaderOpenSellPos');
                if (sharedPosTrigger.length) {
                    sharedPosTrigger.trigger('click');
                }
            });
            $(document).on('click', '.js-loan-detail-modal, .js-sell-detail-modal', function (event) {
                event.preventDefault();
                openDashboardIframeModal($(this).data('title') || 'Detail', $(this).data('url'));
            });
            $(document).on('click', '[data-loan-table="dashboard_sell_search"] .btn-select-sale', function (event) {
                event.preventDefault();
                var saleId = $(this).data('id');
                if (saleId && typeof window.loanManagementOpenAutoInstallment === 'function') {
                    window.loanManagementOpenAutoInstallment(saleId);
                }
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
