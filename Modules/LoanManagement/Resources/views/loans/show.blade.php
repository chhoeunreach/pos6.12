@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $loanEditRouteParams = ['loan' => $loanRow->id];
    if ($isEmbeddedModal) {
        $loanEditRouteParams['_lm_modal'] = 1;
    }
    if (request()->filled('customer_id')) {
        $loanEditRouteParams['customer_id'] = request('customer_id');
    }
    $loanMeta = [];
    if (! empty($loanRow->meta_json)) {
        $loanMeta = json_decode((string) $loanRow->meta_json, true) ?: [];
    }
    $customerName = $customerDisplayName ?? ($loanRow->customer_name_snapshot ?? ($customerRow->name ?? ($customerRow->full_name ?? '-')));
    $customerPhone = $customerPhoneDisplay ?? ($loanRow->customer_phone_snapshot ?? ($customerRow->phone ?? ($customerRow->mobile ?? '-')));
    $customerAddress = $customerAddressDisplay ?? ($loanRow->customer_address_snapshot ?? ($customerRow->address ?? '-'));
    $locationName = $locationDisplayName ?? ($loanRow->location_name_snapshot ?? ($locationRow->name ?? '-'));
    $locationAddress = $locationAddressDisplay ?? ($locationRow->address ?? '-');
    $sourceInvoice = $sourceInvoiceDisplay ?? ($loanRow->source_invoice_no ?? '-');
    $displayInterestRate = (float) ($loanRow->interest_rate ?? ($loanMeta['interest_rate'] ?? ($loanMeta['raw_import_row']['interest_rate'] ?? 0)));
    $displayInterestAmount = (float) ($loanRow->interest_amount ?? 0);
    if ($displayInterestAmount <= 0) {
        $displayInterestAmount = (float) ($scheduleSummary['interest_total'] ?? 0);
    }
    $displayDuration = max(
        (int) ($loanRow->duration_months ?? 0),
        (int) ($loanMeta['duration_months'] ?? 0),
        (int) ($loanRow->installment_count ?? 0),
        (int) ($scheduleCount ?? 0),
        1
    );
    $principalAmount = (float) ($loanRow->principal_amount ?? 0);
    $downPayment = (float) ($loanRow->down_payment ?? 0);
    $paidAmount = (float) ($loanRow->paid_amount ?? 0);
    $balanceAmount = (float) ($loanRow->balance_amount ?? 0);
    $totalContractAmount = $principalAmount + $displayInterestAmount;
    $progressPercent = $totalContractAmount > 0 ? min(100, round(($paidAmount / $totalContractAmount) * 100, 1)) : 0;
    $idCardNumber = $loanRow->id_card_number ?? ($loanRow->customer_id_number ?? ($customerRow->id_proof_number ?? ($customerRow->id_card_number ?? ($loanMeta['id_card_number'] ?? '-'))));
    $occupation = $loanRow->occupation ?? ($customerRow->occupation ?? ($loanMeta['occupation'] ?? '-'));
    $guarantorName = $loanRow->guarantor_name ?? ($loanMeta['guarantor_name'] ?? '-');
    $guarantorPhone = $loanRow->guarantor_phone ?? ($loanMeta['guarantor_phone'] ?? '-');
    $loanStatus = strtolower((string) ($loanRow->status ?? 'pending'));

    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp
@extends('loanmanagement::layouts.app')
@section('title', $lmText('Installment Detail', 'សេចក្តីលម្អិតការបង់រំលស់').' #'.($loanRow->loan_number ?? $loanRow->id))

@section('loan_css')
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

    .lm-detail-wrapper {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f1f5f9;
        color: #1e293b;
        margin: -15px -15px 0 -15px;
        min-height: calc(100vh - 60px);
        display: flex;
        flex-direction: column;
    }

    /* Enterprise Dark Header */
    .lm-detail-header {
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
    .lm-detail-header-left { display: flex; align-items: center; gap: 10px; }
    .lm-detail-icon {
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
    .lm-detail-title { font-size: 15px; font-weight: 700; margin: 0; color: #f8fafc; display: flex; align-items: center; gap: 8px; }
    .lm-detail-sub { font-size: 11px; color: #94a3b8; margin: 1px 0 0; }
    .lm-status-pill {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .lm-status-pill.active, .lm-status-pill.approved { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .lm-status-pill.draft, .lm-status-pill.pending { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
    .lm-status-pill.completed, .lm-status-pill.paid, .lm-status-pill.paid_off { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); }
    .lm-status-pill.rejected, .lm-status-pill.cancelled, .lm-status-pill.defaulted { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

    .lm-detail-header-right { display: flex; align-items: center; gap: 6px; }
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
        cursor: pointer;
        transition: all 0.15s;
    }
    .lm-btn-nav:hover { background: rgba(255, 255, 255, 0.2); color: #fff; text-decoration: none; }
    .lm-btn-nav-primary { background: #2563eb; border-color: #3b82f6; color: #fff; }
    .lm-btn-nav-primary:hover { background: #1d4ed8; color: #fff; }
    .lm-btn-nav-success { background: #16a34a; border-color: #22c55e; color: #fff; }
    .lm-btn-nav-success:hover { background: #15803d; color: #fff; }

    /* Main Body & Padding */
    .lm-detail-body {
        flex: 1;
        padding: 10px 14px;
        background: #f1f5f9;
    }

    /* KPI Financial Metric Strip */
    .lm-kpi-strip {
        display: grid;
        grid-template-columns: repeat(6, 1fr) 1.2fr;
        gap: 8px;
        margin-bottom: 10px;
    }
    @media (max-width: 1200px) {
        .lm-kpi-strip { grid-template-columns: repeat(3, 1fr) 1fr; }
    }
    @media (max-width: 768px) {
        .lm-kpi-strip { grid-template-columns: 1fr 1fr; }
    }
    .lm-kpi-card {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 8px 10px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .lm-kpi-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px; margin-bottom: 2px; }
    .lm-kpi-value { font-size: 14px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .lm-kpi-sub { font-size: 10px; color: #94a3b8; margin-top: 1px; }
    .lm-kpi-card.kpi-paid .lm-kpi-value { color: #16a34a; }
    .lm-kpi-card.kpi-balance .lm-kpi-value { color: #dc2626; }
    .lm-kpi-card.kpi-progress { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border-color: #334155; }
    .lm-kpi-card.kpi-progress .lm-kpi-label { color: #94a3b8; }
    .lm-kpi-card.kpi-progress .lm-kpi-value { color: #38bdf8; }

    .lm-progress-bar-wrap {
        width: 100%;
        height: 6px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 999px;
        overflow: hidden;
        margin-top: 4px;
    }
    .lm-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #38bdf8, #22c55e);
        border-radius: 999px;
        transition: width 0.4s ease;
    }

    /* 2-Column Responsive Workspace */
    .lm-grid-workspace {
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        gap: 10px;
        align-items: start;
    }
    @media (max-width: 992px) {
        .lm-grid-workspace { grid-template-columns: 1fr; }
    }
    .lm-col { display: flex; flex-direction: column; gap: 10px; }

    /* Compact Professional Cards */
    .lm-card {
        background: #fff;
        border-radius: 8px;
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
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .lm-card-title i { color: #2563eb; font-size: 12px; }
    .lm-card-body { padding: 8px 12px; }

    /* Dense Info Table / Rows */
    .lm-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 6px 14px;
    }
    .lm-info-grid.grid-3 {
        grid-template-columns: repeat(3, 1fr);
    }
    @media (max-width: 600px) {
        .lm-info-grid, .lm-info-grid.grid-3 { grid-template-columns: 1fr; }
    }
    .lm-info-item {
        display: flex;
        flex-direction: column;
        border-bottom: 1px dashed #f1f5f9;
        padding-bottom: 3px;
    }
    .lm-info-item.full-width { grid-column: 1 / -1; }
    .lm-info-lbl { font-size: 10px; font-weight: 600; color: #64748b; margin-bottom: 1px; text-transform: uppercase; letter-spacing: 0.2px; }
    .lm-info-val { font-size: 12px; font-weight: 600; color: #0f172a; word-break: break-word; }
    .lm-info-val a { color: #2563eb; text-decoration: none; }
    .lm-info-val a:hover { text-decoration: underline; }

    /* Customer Profile Box */
    .lm-cust-badge-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 1px solid #f1f5f9;
    }
    .lm-cust-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
        border: 2px solid #cbd5e1;
    }
    .lm-cust-meta { flex: 1; min-width: 0; }
    .lm-cust-name { font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0; }
    .lm-cust-phone { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
    .lm-cust-phone a { color: #2563eb; font-weight: 600; text-decoration: none; }

    /* Mobile Sticky Action Bar */
    .lm-mobile-action-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #0f172a;
        color: #fff;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 999;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.15);
    }
    .lm-mab-info { display: flex; flex-direction: column; }
    .lm-mab-label { font-size: 9px; color: #94a3b8; text-transform: uppercase; }
    .lm-mab-amount { font-size: 14px; font-weight: 700; color: #f87171; }
    .lm-mab-actions { display: flex; align-items: center; gap: 6px; }
    .lm-mab-btn {
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        border: none;
        background: #16a34a;
        color: #fff;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .lm-mab-btn-outline { background: rgba(255, 255, 255, 0.15); color: #fff; }
</style>
@endsection

@section('content_body')
<div class="lm-detail-wrapper">
    <!-- Enterprise Compact Header Strip -->
    <header class="lm-detail-header">
        <div class="lm-detail-header-left">
            @if($isEmbeddedModal)
            <button type="button"
                    class="lm-btn-nav"
                    onclick="window.jQuery && window.jQuery('.view_modal').modal('hide');"
                    title="{{ $lmText('Close', 'បិទ') }}">
                <i class="fa fa-times"></i>
            </button>
            @endif
            <div class="lm-detail-icon">
                <i class="fa fa-file-text-o"></i>
            </div>
            <div>
                <h1 class="lm-detail-title">
                    {{ $lmText('Installment #', 'ការបង់រំលស់ #') }}{{ $loanRow->loan_number ?? $loanRow->id }}
                    <span class="lm-status-pill {{ $loanStatus }}">
                        <i class="fa fa-circle" style="font-size: 6px;"></i>
                        {{ ucfirst($loanRow->status ?? 'pending') }}
                    </span>
                </h1>
                <p class="lm-detail-sub">
                    <i class="fa fa-calendar-o"></i> {{ $loanRow->loan_date ?? $loanRow->created_at }} &bull;
                    <i class="fa fa-map-marker"></i> {{ $locationName }} &bull;
                    <i class="fa fa-user"></i> {{ $customerName }}
                </p>
            </div>
        </div>

        <div class="lm-detail-header-right">
            <button type="button"
                    class="lm-btn-nav lm-btn-nav-success btn-modal"
                    data-href="{{ route('loan-management.loans.payment.create', $loanRow->id) }}"
                    data-container=".view_modal">
                <i class="fa fa-money"></i> {{ $lmText('Add Payment', 'ទទួលការទូទាត់') }}
            </button>

            @can('loan_management.edit')
            <button type="button"
                    class="lm-btn-nav lm-btn-nav-primary btn-modal"
                    data-href="{{ route('loan-management.loans.edit', $loanEditRouteParams) }}"
                    data-container=".view_modal">
                <i class="fa fa-pencil"></i> {{ $lmText('Edit Installment', 'កែប្រែការរំលស់') }}
            </button>
            @endcan

            <button type="button"
                    class="lm-btn-nav btn-modal"
                    data-href="{{ route('loan-management.loans.print-modal', $loanRow->id) }}"
                    data-container=".view_modal">
                <i class="fa fa-print"></i> {{ $lmText('Print', 'បោះពុម្ព') }}
            </button>

            @can('loan_management.edit')
            <button type="button"
                    class="lm-btn-nav lm-refresh-schedule-btn"
                    data-url="{{ route('loan-management.loans.schedules.refresh', ['loan' => $loanRow->id, 'sections_context' => 'show'] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}"
                    title="{{ $lmText('Refresh Schedule', 'ធ្វើបច្ចុប្បន្នភាពតារាងបង់') }}">
                <i class="fa fa-refresh"></i> {{ $lmText('Refresh Schedule', 'រៀបចំតារាងឡើងវិញ') }}
            </button>
            @endcan

            @if(!$isEmbeddedModal)
            <a href="{{ route('loan-management.loans.index') }}" class="lm-btn-nav">
                <i class="fa fa-arrow-left"></i> {{ $lmText('Back to List', 'ត្រឡប់ក្រោយ') }}
            </a>
            @endif
        </div>
    </header>

    <!-- Main Workspace Body -->
    <div class="lm-detail-body">
        <!-- Top Financial KPI Strip -->
        <div class="lm-kpi-strip">
            <div class="lm-kpi-card">
                <span class="lm-kpi-label">{{ $lmText('Principal', 'ប្រាក់ដើម') }}</span>
                <span class="lm-kpi-value">{{ number_format($principalAmount, 2) }} <small>{{ $loanRow->currency ?? 'USD' }}</small></span>
                <span class="lm-kpi-sub">{{ $displayDuration }} {{ $lmText('Months', 'ខែ') }} &bull; {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</span>
            </div>

            <div class="lm-kpi-card">
                <span class="lm-kpi-label">{{ $lmText('Down Payment', 'ប្រាក់កក់') }}</span>
                <span class="lm-kpi-value">{{ number_format($downPayment, 2) }} <small>{{ $loanRow->currency ?? 'USD' }}</small></span>
                <span class="lm-kpi-sub">{{ $downPayment > 0 ? round(($downPayment / max($principalAmount + $downPayment, 1)) * 100, 1) : 0 }}% {{ $lmText('of total', 'នៃសរុប') }}</span>
            </div>

            <div class="lm-kpi-card">
                <span class="lm-kpi-label">{{ $lmText('Interest Total', 'ការប្រាក់សរុប') }}</span>
                <span class="lm-kpi-value">{{ number_format($displayInterestAmount, 2) }} <small>{{ $loanRow->currency ?? 'USD' }}</small></span>
                <span class="lm-kpi-sub">{{ rtrim(rtrim(number_format($displayInterestRate, 2, '.', ''), '0'), '.') }}% &bull; {{ ucfirst($loanRow->interest_type ?? 'flat') }}</span>
            </div>

            <div class="lm-kpi-card">
                <span class="lm-kpi-label">{{ $lmText('Total Amount', 'សរុបទឹកប្រាក់') }}</span>
                <span class="lm-kpi-value">{{ number_format($totalContractAmount, 2) }} <small>{{ $loanRow->currency ?? 'USD' }}</small></span>
                <span class="lm-kpi-sub">{{ $lmText('Principal + Interest', 'ប្រាក់ដើម + ការប្រាក់') }}</span>
            </div>

            <div class="lm-kpi-card kpi-paid">
                <span class="lm-kpi-label">{{ $lmText('Paid Amount', 'ប្រាក់បានបង់') }}</span>
                <span class="lm-kpi-value">{{ number_format($paidAmount, 2) }} <small>{{ $loanRow->currency ?? 'USD' }}</small></span>
                <span class="lm-kpi-sub"><i class="fa fa-check-circle"></i> {{ $paymentsCount ?? 0 }} {{ $lmText('Receipts', 'វិក្កយបត្រ') }}</span>
            </div>

            <div class="lm-kpi-card kpi-balance">
                <span class="lm-kpi-label">{{ $lmText('Remaining Balance', 'សមតុល្យនៅសល់') }}</span>
                <span class="lm-kpi-value">{{ number_format($balanceAmount, 2) }} <small>{{ $loanRow->currency ?? 'USD' }}</small></span>
                <span class="lm-kpi-sub"><i class="fa fa-clock-o"></i> {{ $scheduleCount ?? 0 }} {{ $lmText('Installments', 'ដំណាក់កាល') }}</span>
            </div>

            <div class="lm-kpi-card kpi-progress">
                <span class="lm-kpi-label">{{ $lmText('Repayment Progress', 'វឌ្ឍនភាពនៃការបង់') }}</span>
                <span class="lm-kpi-value">{{ $progressPercent }}%</span>
                <div class="lm-progress-bar-wrap">
                    <div class="lm-progress-bar-fill" style="width: {{ $progressPercent }}%;"></div>
                </div>
            </div>
        </div>

        <!-- 2-Column High-Density Grid -->
        <div class="lm-grid-workspace">
            <!-- Left Column: Contract Terms & Dynamic Sections (Items, Schedules, Payments, Logs) -->
            <div class="lm-col">
                <!-- Contract & Financial Parameters Card -->
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-info-circle"></i> {{ $lmText('Contract & Financial Parameters', 'លក្ខខណ្ឌកិច្ចសន្យា និងហិរញ្ញវត្ថុ') }}
                        </h2>
                        <span class="text-muted" style="font-size: 11px;">
                            {{ $lmText('Created by', 'បង្កើតដោយ') }}: <strong>{{ $createdByName ?? '-' }}</strong>
                        </span>
                    </div>
                    <div class="lm-card-body">
                        <div class="lm-info-grid grid-3">
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Loan Number', 'លេខកិច្ចសន្យា') }}</span>
                                <span class="lm-info-val"><strong>{{ $loanRow->loan_number ?? ('LN-'.$loanRow->id) }}</strong></span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Installment Date', 'កាលបរិច្ឆេទចាប់ផ្តើម') }}</span>
                                <span class="lm-info-val">{{ $loanRow->loan_date ?? $loanRow->created_at }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Status', 'ស្ថានភាព') }}</span>
                                <span class="lm-info-val">
                                    <span class="lm-status-pill {{ $loanStatus }}">{{ ucfirst($loanRow->status ?? 'pending') }}</span>
                                </span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Interest Calculation', 'ប្រភេទគណនាការប្រាក់') }}</span>
                                <span class="lm-info-val">{{ ucfirst($loanRow->interest_type ?? 'flat') }} ({{ rtrim(rtrim(number_format($displayInterestRate, 2, '.', ''), '0'), '.') }}%)</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Duration & Frequency', 'រយៈពេល និងប្រេកង់') }}</span>
                                <span class="lm-info-val">{{ $displayDuration }} {{ $lmText('Months', 'ខែ') }} &bull; {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Branch / Location', 'សាខា / ទីតាំង') }}</span>
                                <span class="lm-info-val">{{ $locationName }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Assigned Collector', 'អ្នកប្រមូលប្រាក់') }}</span>
                                <span class="lm-info-val">
                                    @if(!empty($collectorDisplayName) && $collectorDisplayName !== '-')
                                        <i class="fa fa-user-circle text-primary"></i> {{ $collectorDisplayName }}
                                    @else
                                        <span class="text-muted">{{ $lmText('Unassigned', 'មិនទាន់ចាត់តាំង') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('POS / Invoice Ref', 'យោងវិក្កយបត្រ POS') }}</span>
                                <span class="lm-info-val">
                                    @if(!empty($loanRow->source_invoice_no))
                                        <span class="label label-default" style="font-size: 11px;">{{ $loanRow->source_invoice_no }}</span>
                                    @else
                                        -
                                    @endif
                                </span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Currency', 'រូបិយប័ណ្ណ') }}</span>
                                <span class="lm-info-val"><strong>{{ $loanRow->currency ?? 'USD' }}</strong></span>
                            </div>
                            @if(!empty($loanRow->note))
                            <div class="lm-info-item full-width">
                                <span class="lm-info-lbl">{{ $lmText('Note / Remarks', 'កំណត់ចំណាំ') }}</span>
                                <span class="lm-info-val" style="color: #475569; font-weight: normal;">{{ $loanRow->note }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Related Dynamic Sections Placeholder (Items, Schedules, Payments, Status Logs) -->
                <div id="loanShowSections"
                     data-url="{{ route('loan-management.loans.sections.show', ['loan' => $loanRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}">
                    <div class="lm-card" style="padding: 24px; text-align: center; color: #64748b;">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color: #2563eb; margin-bottom: 8px;"></i>
                        <p style="margin: 0; font-size: 12px; font-weight: 600;">{{ $lmText('Loading related items, schedules and ledger...', 'កំពុងផ្ទុកទិន្នន័យទំនិញ តារាងបង់ និងប្រវត្តិទូទាត់...') }}</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Customer KYC & Guarantor Snapshot -->
            <div class="lm-col">
                <!-- Customer Profile & KYC Card -->
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-user"></i> {{ $lmText('Customer Profile & KYC', 'ប្រវត្តិរូបអតិថិជន និង KYC') }}
                        </h2>
                        @if(!empty($loanRow->customer_id) || !empty($loanRow->main_contact_id))
                        <span class="label label-info" style="font-size: 10px;">ID: #{{ $loanRow->customer_id ?? $loanRow->main_contact_id }}</span>
                        @endif
                    </div>
                    <div class="lm-card-body">
                        <div class="lm-cust-badge-row">
                            <div class="lm-cust-avatar">
                                {{ strtoupper(mb_substr($customerName, 0, 1, 'UTF-8')) }}
                            </div>
                            <div class="lm-cust-meta">
                                <h3 class="lm-cust-name">{{ $customerName }}</h3>
                                <div class="lm-cust-phone">
                                    <i class="fa fa-phone text-success"></i>
                                    @if($customerPhone && $customerPhone !== '-')
                                        <a href="tel:{{ $customerPhone }}">{{ $customerPhone }}</a>
                                        <a href="https://t.me/+855{{ ltrim(preg_replace('/[^0-9]/', '', $customerPhone), '0') }}" target="_blank" class="text-info" title="Telegram" style="margin-left: 6px;"><i class="fa fa-telegram"></i></a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="lm-info-grid">
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('National ID / Passport #', 'លេខអត្តសញ្ញាណប័ណ្ណ / លិខិតឆ្លងដែន') }}</span>
                                <span class="lm-info-val">{{ $idCardNumber }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Occupation / Job', 'មុខរបរ / អាជីព') }}</span>
                                <span class="lm-info-val">{{ $occupation }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Customer Group', 'ក្រុមអតិថិជន') }}</span>
                                <span class="lm-info-val">{{ $loanRow->customer_group_name_snapshot ?? 'រំលស់' }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Main Contact ID', 'លេខសម្គាល់ទំនាក់ទំនង CRM') }}</span>
                                <span class="lm-info-val">{{ $mainContactIdDisplay ?? ($loanRow->main_contact_id ?? '-') }}</span>
                            </div>
                            <div class="lm-info-item full-width">
                                <span class="lm-info-lbl">{{ $lmText('Administrative Address', 'អាសយដ្ឋានរដ្ឋបាល') }}</span>
                                <span class="lm-info-val" style="color: #334155; line-height: 1.3;">
                                    <i class="fa fa-map-marker text-danger" style="margin-right: 3px;"></i>
                                    {{ $customerAddress ?: '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guarantor / Co-Signer Card -->
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-shield"></i> {{ $lmText('Guarantor / Co-Signer Information', 'ព័ត៌មានអ្នកធានា') }}
                        </h2>
                    </div>
                    <div class="lm-card-body">
                        <div class="lm-info-grid">
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Guarantor Name', 'ឈ្មោះអ្នកធានា') }}</span>
                                <span class="lm-info-val"><strong>{{ $guarantorName }}</strong></span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Guarantor Phone', 'លេខទូរស័ព្ទអ្នកធានា') }}</span>
                                <span class="lm-info-val">
                                    @if($guarantorPhone && $guarantorPhone !== '-')
                                        <a href="tel:{{ $guarantorPhone }}"><i class="fa fa-phone text-success"></i> {{ $guarantorPhone }}</a>
                                    @else
                                        -
                                    @endif
                                </span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Relationship', 'ទំនាក់ទំនង') }}</span>
                                <span class="lm-info-val">{{ $loanRow->guarantor_relationship ?? ($loanMeta['guarantor_relationship'] ?? '-') }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Guarantor ID #', 'លេខអត្តសញ្ញាណប័ណ្ណអ្នកធានា') }}</span>
                                <span class="lm-info-val">{{ $loanRow->guarantor_id_number ?? ($loanMeta['guarantor_id_number'] ?? '-') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branch & Location Card -->
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-building-o"></i> {{ $lmText('Location & Store Snapshot', 'ព័ត៌មានសាខា និងទីតាំងលក់') }}
                        </h2>
                    </div>
                    <div class="lm-card-body">
                        <div class="lm-info-grid">
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Branch Name', 'ឈ្មោះសាខា') }}</span>
                                <span class="lm-info-val"><strong>{{ $locationName }}</strong></span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Location ID', 'លេខសម្គាល់ទីតាំង') }}</span>
                                <span class="lm-info-val">{{ $loanRow->main_location_id ?? ($loanRow->business_location_id ?? '-') }}</span>
                            </div>
                            <div class="lm-info-item full-width">
                                <span class="lm-info-lbl">{{ $lmText('Store Address', 'អាសយដ្ឋានសាខា') }}</span>
                                <span class="lm-info-val" style="color: #64748b; font-size: 11px;">{{ $locationAddress ?: '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sticky Action Bar -->
<div class="lm-mobile-action-bar d-lg-none" id="lmLoanActionBar">
    <div class="lm-mab-info">
        <span class="lm-mab-label">{{ $lmText('Remaining Balance', 'សមតុល្យនៅសល់') }}</span>
        <span class="lm-mab-amount">{{ number_format($balanceAmount, 2) }} {{ $loanRow->currency ?? 'USD' }}</span>
    </div>
    <div class="lm-mab-actions">
        <button type="button"
                class="lm-mab-btn lm-quick-pay-trigger"
                data-url="{{ route('loan-management.loans.payment.quick-pay', $loanRow->id) }}"
                data-loan-id="{{ $loanRow->id }}">
            <i class="fa fa-money"></i> {{ $lmText('Pay', 'បង់ប្រាក់') }}
        </button>
        <button type="button"
                class="lm-mab-btn lm-mab-btn-outline btn-modal"
                data-href="{{ route('loan-management.loans.print-modal', $loanRow->id) }}"
                data-container=".view_modal">
            <i class="fa fa-print"></i>
        </button>
        @can('loan_management.edit')
        <button type="button"
                class="lm-mab-btn lm-mab-btn-outline btn-modal"
                data-href="{{ route('loan-management.loans.edit', $loanEditRouteParams) }}"
                data-container=".view_modal">
            <i class="fa fa-pencil"></i>
        </button>
        @endcan
    </div>
</div>
@endsection

@section('loan_js')
<script>
    (function () {
        var sectionsContainer = document.getElementById('loanShowSections');
        if (sectionsContainer && sectionsContainer.getAttribute('data-url') && window.jQuery) {
            window.jQuery.ajax({
                url: sectionsContainer.getAttribute('data-url'),
                dataType: 'html',
                success: function (result) {
                    sectionsContainer.innerHTML = result;
                },
                error: function () {
                    sectionsContainer.innerHTML = '<div class="alert alert-warning" style="margin-bottom:0; font-size:12px;">{{ $lmText("Unable to load related sections right now.", "មិនអាចផ្ទុកទិន្នន័យផ្នែកពាក់ព័ន្ធបានទេនៅពេលនេះ។") }}</div>';
                }
            });
        }

        function replaceLoanSections(html) {
            var target = document.getElementById('loanShowSections');
            if (target && html) {
                target.innerHTML = html;
            }
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.lm-refresh-schedule-btn');
            if (!trigger || !window.jQuery) {
                return;
            }

            event.preventDefault();
            if (!window.confirm('{{ $lmText("Refresh this installment payment schedule from original terms?", "តើអ្នកពិតជាចង់រៀបចំតារាងបង់ប្រាក់ឡើងវិញមែនទេ?") }}')) {
                return;
            }

            var originalHtml = trigger.innerHTML;
            trigger.disabled = true;
            trigger.innerHTML = '<i class="fa fa-spinner fa-spin"></i> {{ $lmText("Refreshing...", "កំពុងរៀបចំ...") }}';

            window.jQuery.ajax({
                url: trigger.getAttribute('data-url'),
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: '{{ csrf_token() }}',
                    sections_context: 'show'
                },
                success: function (res) {
                    if (res && res.success) {
                        replaceLoanSections(res.data && res.data.sections_html);
                        if (window.toastr) {
                            window.toastr.success(res.message || '{{ $lmText("Payment schedule refreshed successfully.", "បានរៀបចំតារាងបង់ប្រាក់ឡើងវិញដោយជោគជ័យ។") }}');
                        }
                    } else if (window.toastr) {
                        window.toastr.error((res && res.message) || '{{ $lmText("Unable to refresh payment schedule.", "មិនអាចរៀបចំតារាងបង់ប្រាក់បានទេ។") }}');
                    }
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || '{{ $lmText("Unable to refresh payment schedule.", "មិនអាចរៀបចំតារាងបង់ប្រាក់បានទេ។") }}';
                    if (window.toastr) {
                        window.toastr.error(message);
                    } else {
                        alert(message);
                    }
                },
                complete: function () {
                    trigger.disabled = false;
                    trigger.innerHTML = originalHtml;
                }
            });
        });

        // Copy text helper
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.lm-copy-btn');
            if (!btn) return;
            var text = btn.getAttribute('data-copy');
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).then(function () {
                    if (window.toastr) window.toastr.info('Copied: ' + text);
                });
            }
        });

        @if($isEmbeddedModal)
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.btn-modal[data-container=".view_modal"]');
            if (!trigger) {
                return;
            }

            var parentWindow = window.parent;
            if (!parentWindow || !parentWindow.jQuery || !trigger.getAttribute('data-href')) {
                return;
            }

            event.preventDefault();
            var parentModal = parentWindow.jQuery('.view_modal');
            if (!parentModal.length) {
                return;
            }

            parentWindow.jQuery.ajax({
                url: trigger.getAttribute('data-href'),
                dataType: 'html',
                success: function (result) {
                    parentModal.html(result).modal('show');
                }
            });
        });
        @endif
    })();
</script>
@endsection
