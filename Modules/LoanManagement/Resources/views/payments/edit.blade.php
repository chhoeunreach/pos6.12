@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $backCustomerId = request('customer_id') ?: ($loan->customer_id ?? $payment->customer_id ?? null);
    $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? ('Payment #'.$payment->id);
    $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
    $paidDate = ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('Y-m-d') : (! empty($payment->paid_at) ? \Carbon\Carbon::parse($payment->paid_at)->format('Y-m-d') : date('Y-m-d'));
    $loanNumber = $loan->loan_number ?? $payment->loan_number_snapshot ?? ($payment->loan_id ? 'LN-'.$payment->loan_id : '-');
    $customerName = $loan->customer_name_snapshot ?? $payment->customer_name_snapshot ?? ($customerRow->name ?? '-');
    $customerPhone = $loan->customer_phone_snapshot ?? $payment->customer_phone_snapshot ?? ($customerRow->phone ?? ($customerRow->mobile ?? '-'));
    $customerAddress = $loan->customer_address_snapshot ?? $payment->customer_address_snapshot ?? ($customerRow->address ?? '-');
    $locationName = $loan->location_name_snapshot ?? $payment->location_name_snapshot ?? '-';
    $loanPrincipal = (float) ($loan->principal_amount ?? 0);
    $loanInterest = (float) ($loan->interest_amount ?? 0);
    $loanPaidTotal = (float) ($loan->paid_amount ?? 0);
    $loanBalance = (float) ($loan->balance_amount ?? 0);
    $loanCurrency = $loan->currency ?? ($payment->currency ?? 'USD');
    $collectorName = $payment->received_by ?? ($payment->received_by_name_snapshot ?? ($payment->collected_by_name_snapshot ?? ($loan->collector_name_snapshot ?? '-')));
    $paymentStatus = strtolower((string)($payment->status ?? 'confirmed'));
    $paymentType = strtolower((string)($payment->payment_type ?? 'monthly'));

    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp
@extends('loanmanagement::layouts.app')
@section('title', $lmText('Edit Payment', 'កែប្រែការទូទាត់').' - '.$receipt)

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

    .lm-pay-edit-wrap {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f1f5f9;
        color: #1e293b;
        margin: -15px -15px 0 -15px;
        min-height: calc(100vh - 60px);
        display: flex;
        flex-direction: column;
    }

    /* Enterprise Dark Header Strip */
    .lm-pay-edit-header {
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
    .lm-pay-edit-header-left { display: flex; align-items: center; gap: 10px; }
    .lm-pay-edit-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(16, 185, 129, 0.25);
        border: 1px solid rgba(52, 211, 153, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: #34d399;
    }
    .lm-pay-edit-title { font-size: 14px; font-weight: 700; margin: 0; color: #f8fafc; display: flex; align-items: center; gap: 8px; }
    .lm-pay-edit-sub { font-size: 11px; color: #94a3b8; margin: 1px 0 0; }

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
    .lm-status-pill.confirmed, .lm-status-pill.paid { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .lm-status-pill.pending { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
    .lm-status-pill.failed, .lm-status-pill.cancelled { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

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

    /* Workspace Content */
    .lm-pay-edit-body {
        flex: 1;
        padding: 10px 14px;
        background: #f1f5f9;
    }

    /* KPI Summary Strip */
    .lm-kpi-strip {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
        margin-bottom: 10px;
    }
    @media (max-width: 1000px) {
        .lm-kpi-strip { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-kpi-strip { grid-template-columns: 1fr 1fr; }
    }
    .lm-kpi-card {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .lm-kpi-lbl { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
    .lm-kpi-val { font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .lm-kpi-val.text-success { color: #16a34a; }
    .lm-kpi-val.text-danger { color: #dc2626; }
    .lm-kpi-sub { font-size: 9.5px; color: #94a3b8; margin-top: 1px; }

    /* 2-Column Dense Grid */
    .lm-grid-2col {
        display: grid;
        grid-template-columns: 1.25fr 1fr;
        gap: 10px;
        align-items: start;
    }
    @media (max-width: 900px) {
        .lm-grid-2col { grid-template-columns: 1fr; }
    }

    /* Card Component */
    .lm-card {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        overflow: hidden;
        margin-bottom: 10px;
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
    .lm-card-title i { color: #16a34a; font-size: 12px; }
    .lm-card-body { padding: 10px 12px; }

    /* High Density Form Elements */
    .lm-form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-bottom: 8px;
    }
    .lm-field { display: flex; flex-direction: column; gap: 2px; }
    .lm-field.full-width { grid-column: 1 / -1; }
    .lm-label {
        font-size: 10px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .lm-control {
        height: 31px;
        padding: 4px 8px;
        font-size: 12px;
        color: #0f172a;
        background-color: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        outline: none;
        width: 100%;
        transition: all 0.15s;
    }
    .lm-control:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.15);
    }
    .lm-control[readonly], .lm-control:disabled {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }
    textarea.lm-control {
        height: auto;
        min-height: 55px;
        padding: 6px 8px;
        resize: vertical;
    }

    /* Shortcut Badges for Amount */
    .lm-amt-chips {
        display: flex;
        gap: 4px;
        margin-top: 3px;
        flex-wrap: wrap;
    }
    .lm-amt-chip {
        font-size: 9px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 4px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #334155;
        cursor: pointer;
        transition: all 0.1s;
    }
    .lm-amt-chip:hover { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }

    /* Action Footer */
    .lm-card-footer {
        padding: 8px 12px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }
    .lm-btn-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.15s;
        text-decoration: none;
    }
    .lm-btn-cancel { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    .lm-btn-cancel:hover { background: #e2e8f0; color: #0f172a; text-decoration: none; }
    .lm-btn-save { background: #16a34a; color: #fff; border-color: #15803d; }
    .lm-btn-save:hover { background: #15803d; color: #fff; text-decoration: none; }

    /* Quick Info List in Right Column */
    .lm-quick-list { display: flex; flex-direction: column; gap: 6px; }
    .lm-quick-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 5px;
        border-bottom: 1px dashed #e2e8f0;
        font-size: 11px;
    }
    .lm-quick-item span { color: #64748b; }
    .lm-quick-item strong { color: #0f172a; }

    /* Live Balance Forecast Box */
    .lm-balance-forecast {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 6px;
        padding: 8px 10px;
        margin-top: 8px;
    }
    .lm-balance-forecast-lbl { font-size: 10px; font-weight: 700; color: #166534; text-transform: uppercase; }
    .lm-balance-forecast-val { font-size: 13px; font-weight: 800; color: #15803d; margin-top: 2px; }
</style>
@endsection

@section('content_body')
<div class="lm-pay-edit-wrap">
    <!-- Enterprise Dark Header Strip -->
    <header class="lm-pay-edit-header">
        <div class="lm-pay-edit-header-left">
            @if($isEmbeddedModal)
            <button type="button"
                    class="lm-btn-nav"
                    onclick="window.jQuery && window.jQuery('.view_modal').modal('hide');"
                    title="{{ $lmText('Close', 'បិទ') }}">
                <i class="fa fa-times"></i>
            </button>
            @endif
            <div class="lm-pay-edit-icon">
                <i class="fa fa-pencil-square-o"></i>
            </div>
            <div>
                <h1 class="lm-pay-edit-title">
                    {{ $lmText('Edit Payment', 'កែប្រែការទូទាត់') }} #{{ $receipt }}
                    <span class="lm-status-pill {{ $paymentStatus }}">
                        <i class="fa fa-circle" style="font-size: 6px;"></i>
                        {{ ucfirst($payment->status ?? 'confirmed') }}
                    </span>
                </h1>
                <p class="lm-pay-edit-sub">
                    <i class="fa fa-file-text-o"></i> {{ $loanNumber }} &bull;
                    <i class="fa fa-user"></i> {{ $customerName }} &bull;
                    <i class="fa fa-calendar"></i> {{ $paidDate }} &bull;
                    <i class="fa fa-user-circle-o"></i> {{ $collectorName }}
                </p>
            </div>
        </div>

        <div class="lm-pay-edit-header-right">
            @if($isEmbeddedModal)
                <a href="{{ route('loan-management.payments.show', ['payment' => $payment->id, '_lm_modal' => 1]) }}" class="lm-btn-nav">
                    <i class="fa fa-eye"></i> {{ $lmText('View Receipt', 'មើលបង្កាន់ដៃ') }}
                </a>
            @elseif(!empty($backCustomerId))
                <a href="{{ route('loan-management.customers.edit', $backCustomerId) }}" class="lm-btn-nav">
                    <i class="fa fa-arrow-left"></i> {{ $lmText('Back to Customer', 'ត្រឡប់ទៅអតិថិជន') }}
                </a>
            @else
                <a href="{{ route('loan-management.payments.index') }}" class="lm-btn-nav">
                    <i class="fa fa-arrow-left"></i> {{ $lmText('Back to Payments', 'ត្រឡប់ក្រោយ') }}
                </a>
            @endif
        </div>
    </header>

    <!-- Main Workspace Body -->
    <div class="lm-pay-edit-body">
        <!-- Top KPI Metric Strip with All Financial Key Indicators -->
        <div class="lm-kpi-strip">
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Payment Amount', 'ចំនួនទឹកប្រាក់បង់') }}</div>
                <div class="lm-kpi-val text-success">{{ number_format($amount, 2) }} <small>{{ $loanCurrency }}</small></div>
                <div class="lm-kpi-sub">{{ $lmText('Current receipt', 'បង្កាន់ដៃបច្ចុប្បន្ន') }}</div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Loan Financed Total', 'កិច្ចសន្យាសរុប') }}</div>
                <div class="lm-kpi-val">{{ number_format($loanPrincipal + $loanInterest, 2) }} <small>{{ $loanCurrency }}</small></div>
                <div class="lm-kpi-sub">{{ $lmText('Principal + Interest', 'ដើម + ការប្រាក់') }}</div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Total Paid to Date', 'ប្រាក់បានបង់សរុប') }}</div>
                <div class="lm-kpi-val text-success">{{ number_format($loanPaidTotal, 2) }} <small>{{ $loanCurrency }}</small></div>
                <div class="lm-kpi-sub">{{ $loanPrincipal > 0 ? round(($loanPaidTotal / max($loanPrincipal + $loanInterest, 1)) * 100, 1) : 0 }}% {{ $lmText('completed', 'សម្រេច') }}</div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Installment Balance', 'សមតុល្យនៅសល់') }}</div>
                <div class="lm-kpi-val text-danger" id="currentBalanceDisplay">{{ number_format($loanBalance, 2) }} <small>{{ $loanCurrency }}</small></div>
                <div class="lm-kpi-sub">{{ $lmText('Before changes', 'មុនកែប្រែ') }}</div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Payment Type', 'ប្រភេទបង់ប្រាក់') }}</div>
                <div class="lm-kpi-val" style="font-size: 13px; color: #2563eb;">{{ ucfirst($paymentType) }}</div>
                <div class="lm-kpi-sub">{{ $receipt }}</div>
            </div>
        </div>

        <!-- Form Form Area -->
        <form method="POST" action="{{ route('loan-management.payments.update', ['payment' => $payment->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}" id="lmEditPaymentForm">
            @csrf
            @method('PUT')
            @if($isEmbeddedModal)
                <input type="hidden" name="return_to" value="{{ route('loan-management.payments.show', ['payment' => $payment->id, '_lm_modal' => 1]) }}">
            @elseif(!empty($backCustomerId))
                <input type="hidden" name="return_to" value="{{ route('loan-management.customers.edit', $backCustomerId) }}">
            @endif

            <div class="lm-grid-2col">
                <!-- Left Column: Payment Details Input -->
                <div class="lm-col">
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h2 class="lm-card-title">
                                <i class="fa fa-credit-card"></i> {{ $lmText('Payment Transaction Fields', 'ព័ត៌មានលម្អិតប្រតិបត្តិការទូទាត់') }}
                            </h2>
                            <span class="text-muted" style="font-size: 10px;">ID: #{{ $payment->id }}</span>
                        </div>
                        <div class="lm-card-body">
                            <!-- Loan & Customer Reference -->
                            <div class="lm-form-row">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Installment #', 'លេខកិច្ចសន្យា') }}</label>
                                    <input type="text" class="lm-control" value="{{ $loanNumber }}" readonly>
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Customer Name', 'ឈ្មោះអតិថិជន') }}</label>
                                    <input type="text" class="lm-control" value="{{ $customerName }}" readonly>
                                </div>
                            </div>

                            <!-- Date & Amount -->
                            <div class="lm-form-row">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Paid Date', 'កាលបរិច្ឆេទបង់') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="paid_date" class="lm-control" value="{{ old('paid_date', $paidDate) }}" required>
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">
                                        <span>{{ $lmText('Amount Paid', 'ចំនួនទឹកប្រាក់បង់') }} ({{ $loanCurrency }}) <span class="text-danger">*</span></span>
                                    </label>
                                    <input type="number" name="amount" id="lmPaymentAmountInput" class="lm-control" step="0.01" min="0.01" value="{{ old('amount', $amount) }}" required style="font-weight: 800; color: #16a34a; font-size: 14px;">
                                    <div class="lm-amt-chips">
                                        <span class="lm-amt-chip" onclick="setPaymentAmount({{ $amount }})">{{ $lmText('Original', 'ដើម') }}: {{ number_format($amount, 2) }}</span>
                                        @if($loanBalance > 0)
                                            <span class="lm-amt-chip" onclick="setPaymentAmount({{ $loanBalance + $amount }})">{{ $lmText('Pay-Off', 'ផ្តាច់') }}: {{ number_format($loanBalance + $amount, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Type & Payment Method -->
                            <div class="lm-form-row">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Payment Type', 'ប្រភេទបង់ប្រាក់') }} <span class="text-danger">*</span></label>
                                    <select name="payment_type" class="lm-control" required>
                                        @php $currentType = old('payment_type', $paymentType); @endphp
                                        <option value="monthly" {{ $currentType === 'monthly' ? 'selected' : '' }}>
                                            {{ $lmText('Monthly Installment Schedule', 'ការបង់រំលស់ប្រចាំខែ / តាមវគ្គ') }}
                                        </option>
                                        <option value="loan" {{ in_array($currentType, ['loan', 'deposit', 'down_payment', 'initial']) ? 'selected' : '' }}>
                                            {{ $lmText('Customer Deposit / Down Payment', 'ប្រាក់កក់ / បង់ដំបូង') }}
                                        </option>
                                        <option value="payoff" {{ in_array($currentType, ['payoff', 'pay_off']) ? 'selected' : '' }}>
                                            {{ $lmText('Full Pay-Off / Contract Closure', 'ការបង់ផ្តាច់កិច្ចសន្យា') }}
                                        </option>
                                        <option value="penalty" {{ in_array($currentType, ['penalty', 'fee', 'service_fee']) ? 'selected' : '' }}>
                                            {{ $lmText('Late Penalty / Service Fee', 'ប្រាក់ពិន័យយឺត / ថ្លៃសេវា') }}
                                        </option>
                                    </select>
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Payment Method', 'វិធីទូទាត់') }} <span class="text-danger">*</span></label>
                                    <select name="method" class="lm-control" required>
                                        @php $currentMethod = old('method', $payment->method ?? $payment->channel ?? $payment->payment_method_snapshot ?? ''); @endphp
                                        @foreach($methods as $key => $label)
                                            @php
                                                $displayMethodLabel = (string) $label;
                                                if (str_starts_with($displayMethodLabel, 'lang_v1.') || str_starts_with($displayMethodLabel, 'messages.')) {
                                                    $rawKey = str_replace(['lang_v1.', 'messages.'], '', $displayMethodLabel);
                                                    $displayMethodLabel = $rawKey === 'advance' ? $lmText('Advance Payment', 'ប្រាក់បង់មុន / បុរេប្រទាន (Advance)') : ucfirst(str_replace('_', ' ', $rawKey));
                                                }
                                            @endphp
                                            <option value="{{ $key }}" {{ $currentMethod == $key || $currentMethod == $label ? 'selected' : '' }}>{{ $displayMethodLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Schedule & Status -->
                            <div class="lm-form-row">
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Linked Schedule Installment', 'ភ្ជាប់ជាមួយដំណាក់កាលបង់') }}</label>
                                    <select name="schedule_id" id="lmScheduleSelect" class="lm-control">
                                        <option value="" data-due="0">{{ $lmText('-- No schedule / Down payment / Advance --', '-- មិនភ្ជាប់វគ្គ / ប្រាក់កក់ --') }}</option>
                                        @foreach($schedules as $schedule)
                                            @php
                                                $schedDue = (float)($schedule->schedule_amount ?? $schedule->amount_due ?? 0);
                                                $schedBal = (float)($schedule->balance_amount ?? $schedule->amount_balance ?? $schedDue);
                                                $schedDate = ! empty($schedule->due_date) ? \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') : '-';
                                            @endphp
                                            <option value="{{ $schedule->id }}"
                                                    data-due="{{ $schedDue }}"
                                                    data-balance="{{ $schedBal }}"
                                                    data-date="{{ $schedDate }}"
                                                    {{ (string) old('schedule_id', $payment->schedule_id ?? '') === (string) $schedule->id ? 'selected' : '' }}>
                                                #{{ $schedule->installment_no ?? $schedule->id }}
                                                ({{ $schedDate }}) - Due: {{ number_format($schedDue, 2) }} - Bal: {{ number_format($schedBal, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="lm-field">
                                    <label class="lm-label">{{ $lmText('Payment Status', 'ស្ថានភាពទូទាត់') }} <span class="text-danger">*</span></label>
                                    <select name="status" class="lm-control" required>
                                        @foreach(['confirmed' => $lmText('Confirmed', 'បានបញ្ជាក់'), 'paid' => $lmText('Paid', 'បានបង់រួច'), 'pending' => $lmText('Pending', 'រង់ចាំ'), 'failed' => $lmText('Failed', 'បរាជ័យ'), 'cancelled' => $lmText('Cancelled', 'បានលុបចោល')] as $key => $label)
                                            <option value="{{ $key }}" {{ old('status', $payment->status ?? 'confirmed') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Reference Code -->
                            <div class="lm-form-row">
                                <div class="lm-field full-width">
                                    <label class="lm-label">{{ $lmText('Reference / Transaction Code', 'លេខយោងប្រតិបត្តិការ') }}</label>
                                    <input type="text" name="reference_number" class="lm-control" value="{{ old('reference_number', $payment->reference_number ?? '') }}" placeholder="{{ $lmText('Bank slip #, ABA ref, check number...', 'លេខស្លីបធនាគារ, លេខយោង ABA, មូលប្បទានប័ត្រ...') }}">
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="lm-field full-width" style="margin-top: 4px;">
                                <label class="lm-label">{{ $lmText('Payment Note / Remarks', 'កំណត់ចំណាំការទូទាត់') }}</label>
                                <textarea name="note" class="lm-control" rows="2" placeholder="{{ $lmText('Internal memo, collection remarks, customer notes...', 'កំណត់ចំណាំផ្ទៃក្នុង ការប្រមូលប្រាក់...') }}">{{ old('note', $payment->note ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Context & Summary Snapshot -->
                <div class="lm-col">
                    <!-- Customer & Loan Profile Snapshot -->
                    <div class="lm-card">
                        <div class="lm-card-head">
                            <h2 class="lm-card-title">
                                <i class="fa fa-user"></i> {{ $lmText('Customer & Loan Context', 'ព័ត៌មានអតិថិជន និងកិច្ចសន្យា') }}
                            </h2>
                        </div>
                        <div class="lm-card-body">
                            <div class="lm-quick-list">
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Customer Name', 'ឈ្មោះអតិថិជន') }}:</span>
                                    <strong>{{ $customerName }}</strong>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Phone Number', 'លេខទូរស័ព្ទ') }}:</span>
                                    <strong>
                                        @if($customerPhone && $customerPhone !== '-')
                                            <a href="tel:{{ $customerPhone }}" style="color: #2563eb;">{{ $customerPhone }}</a>
                                        @else
                                            -
                                        @endif
                                    </strong>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Branch Location', 'សាខា / ទីតាំង') }}:</span>
                                    <span>{{ $locationName }}</span>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Installment Contract #', 'លេខកិច្ចសន្យា') }}:</span>
                                    <strong>
                                        @if($loan)
                                            <a href="{{ route('loan-management.loans.view', $loan->id) }}" target="_blank" style="color: #2563eb;">{{ $loanNumber }}</a>
                                        @else
                                            {{ $loanNumber }}
                                        @endif
                                    </strong>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Collected By', 'អ្នកទទួលប្រាក់') }}:</span>
                                    <strong>{{ $collectorName }}</strong>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Created / Recorded', 'កាលបរិច្ឆេទបង្កើត') }}:</span>
                                    <span class="text-muted">{{ $payment->created_at ?? '-' }}</span>
                                </div>
                            </div>

                            <!-- Live Balance Forecast Preview -->
                            <div class="lm-balance-forecast">
                                <div class="lm-balance-forecast-lbl">
                                    <i class="fa fa-calculator"></i> {{ $lmText('Estimated Balance Forecast', 'ការព្យាករសមតុល្យបន្ទាប់ពីកែប្រែ') }}
                                </div>
                                <div class="lm-balance-forecast-val" id="lmForecastBalance">
                                    {{ number_format($loanBalance, 2) }} {{ $loanCurrency }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Schedule Details (Dynamic) -->
                    <div class="lm-card" id="lmSchedDetailCard" style="display: none;">
                        <div class="lm-card-head">
                            <h2 class="lm-card-title">
                                <i class="fa fa-calendar-check-o"></i> {{ $lmText('Selected Schedule Info', 'ព័ត៌មានវគ្គដែលបានជ្រើស') }}
                            </h2>
                        </div>
                        <div class="lm-card-body">
                            <div class="lm-quick-list">
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Schedule Due Date', 'ថ្ងៃត្រូវបង់') }}:</span>
                                    <strong id="lmSchedDueDate">-</strong>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Scheduled Amount', 'ទឹកប្រាក់ត្រូវបង់') }}:</span>
                                    <strong id="lmSchedDueAmt">0.00</strong>
                                </div>
                                <div class="lm-quick-item">
                                    <span>{{ $lmText('Schedule Remaining', 'សមតុល្យវគ្គ') }}:</span>
                                    <strong id="lmSchedBalAmt" style="color: #dc2626;">0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky / Footer Actions Bar -->
            <div class="lm-card" style="margin-top: 10px;">
                <div class="lm-card-footer">
                    @if($isEmbeddedModal)
                        <a href="{{ route('loan-management.payments.show', ['payment' => $payment->id, '_lm_modal' => 1]) }}" class="lm-btn-action lm-btn-cancel">
                            <i class="fa fa-times"></i> {{ $lmText('Cancel', 'បោះបង់') }}
                        </a>
                    @elseif(!empty($backCustomerId))
                        <a href="{{ route('loan-management.customers.edit', $backCustomerId) }}" class="lm-btn-action lm-btn-cancel">
                            <i class="fa fa-times"></i> {{ $lmText('Cancel', 'បោះបង់') }}
                        </a>
                    @else
                        <a href="{{ route('loan-management.payments.index') }}" class="lm-btn-action lm-btn-cancel">
                            <i class="fa fa-times"></i> {{ $lmText('Cancel', 'បោះបង់') }}
                        </a>
                    @endif
                    <button type="submit" class="lm-btn-action lm-btn-save">
                        <i class="fa fa-save"></i> {{ $lmText('Update Payment', 'រក្សាទុកការកែប្រែ') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('loan_js')
<script>
    (function () {
        var originalPaymentAmt = {{ (float) $amount }};
        var currentLoanBalance = {{ (float) $loanBalance }};
        var currency = '{{ $loanCurrency }}';
        var amtInput = document.getElementById('lmPaymentAmountInput');
        var forecastEl = document.getElementById('lmForecastBalance');
        var schedSelect = document.getElementById('lmScheduleSelect');
        var schedCard = document.getElementById('lmSchedDetailCard');
        var schedDueDate = document.getElementById('lmSchedDueDate');
        var schedDueAmt = document.getElementById('lmSchedDueAmt');
        var schedBalAmt = document.getElementById('lmSchedBalAmt');

        window.setPaymentAmount = function (val) {
            if (amtInput) {
                amtInput.value = parseFloat(val).toFixed(2);
                updateForecast();
            }
        };

        function updateForecast() {
            if (!amtInput || !forecastEl) return;
            var newAmt = parseFloat(amtInput.value) || 0;
            var diff = newAmt - originalPaymentAmt;
            var newLoanBal = Math.max(0, currentLoanBalance - diff);
            forecastEl.textContent = newLoanBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;
            if (diff > 0) {
                forecastEl.style.color = '#15803d';
            } else if (diff < 0) {
                forecastEl.style.color = '#dc2626';
            } else {
                forecastEl.style.color = '#0f172a';
            }
        }

        function updateSchedCard() {
            if (!schedSelect || !schedCard) return;
            var opt = schedSelect.options[schedSelect.selectedIndex];
            if (opt && opt.value) {
                schedCard.style.display = 'block';
                if (schedDueDate) schedDueDate.textContent = opt.getAttribute('data-date') || '-';
                if (schedDueAmt) schedDueAmt.textContent = parseFloat(opt.getAttribute('data-due') || 0).toFixed(2) + ' ' + currency;
                if (schedBalAmt) schedBalAmt.textContent = parseFloat(opt.getAttribute('data-balance') || 0).toFixed(2) + ' ' + currency;
            } else {
                schedCard.style.display = 'none';
            }
        }

        if (amtInput) {
            amtInput.addEventListener('input', updateForecast);
        }
        if (schedSelect) {
            schedSelect.addEventListener('change', updateSchedCard);
        }

        updateForecast();
        updateSchedCard();
    })();
</script>
@endsection
