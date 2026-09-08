@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? ('Payment #'.$payment->id);
    $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
    $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
    $method = $payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? '-';
    $loanNumber = $loan->loan_number ?? $payment->loan_number_snapshot ?? ($payment->loan_id ? 'LN-'.$payment->loan_id : '-');
    $customerName = $loan->customer_name_snapshot ?? $payment->customer_name_snapshot ?? ($customerRow->name ?? '-');
    $customerPhone = $loan->customer_phone_snapshot ?? ($customerRow->phone ?? ($customerRow->mobile ?? '-'));
    $loanBalance = (float) ($loan->balance_amount ?? 0);
    $loanCurrency = $loan->currency ?? ($payment->currency ?? 'USD');
    $paymentStatus = strtolower((string)($payment->status ?? 'confirmed'));

    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp
@extends('loanmanagement::layouts.app')
@section('title', $lmText('Payment Receipt', 'បង្កាន់ដៃទូទាត់').' - '.$receipt)

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

    .lm-pay-show-wrap {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f1f5f9;
        color: #1e293b;
        margin: -15px -15px 0 -15px;
        min-height: calc(100vh - 60px);
        display: flex;
        flex-direction: column;
    }

    /* Enterprise Dark Header */
    .lm-pay-show-header {
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
    .lm-pay-show-header-left { display: flex; align-items: center; gap: 10px; }
    .lm-pay-show-icon {
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
    .lm-pay-show-title { font-size: 14px; font-weight: 700; margin: 0; color: #f8fafc; display: flex; align-items: center; gap: 8px; }
    .lm-pay-show-sub { font-size: 11px; color: #94a3b8; margin: 1px 0 0; }

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

    /* Professional Payment Type Badges */
    .lm-type-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        line-height: 1.2;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    .lm-type-monthly { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .lm-type-payoff { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .lm-type-deposit, .lm-type-loan { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .lm-type-advance { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
    .lm-type-penalty { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }
    .lm-type-default { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

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

    /* Workspace Content */
    .lm-pay-show-body {
        flex: 1;
        padding: 10px 14px;
        background: #f1f5f9;
    }

    /* KPI Summary Row */
    .lm-kpi-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-bottom: 10px;
    }
    @media (max-width: 768px) {
        .lm-kpi-strip { grid-template-columns: 1fr 1fr; }
    }
    .lm-kpi-card {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .lm-kpi-lbl { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
    .lm-kpi-val { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .lm-kpi-val.text-success { color: #16a34a; }
    .lm-kpi-val.text-danger { color: #dc2626; }

    /* 2-Column Dense Grid */
    .lm-grid-2col {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
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

    /* Dense Info Table / Rows */
    .lm-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 6px 14px;
    }
    .lm-info-item {
        display: flex;
        flex-direction: column;
        border-bottom: 1px dashed #f1f5f9;
        padding-bottom: 3px;
    }
    .lm-info-item.full-width { grid-column: 1 / -1; }
    .lm-info-lbl { font-size: 10px; font-weight: 600; color: #64748b; margin-bottom: 1px; text-transform: uppercase; }
    .lm-info-val { font-size: 12px; font-weight: 600; color: #0f172a; word-break: break-word; }

    /* Dense Table */
    .lm-table-dense {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }
    .lm-table-dense th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 10px;
        text-transform: uppercase;
        padding: 6px 8px;
        border-bottom: 1px solid #cbd5e1;
    }
    .lm-table-dense td {
        padding: 6px 8px;
        border-bottom: 1px solid #f1f5f9;
    }
</style>
@endsection

@section('content_body')
<div class="lm-pay-show-wrap">
    <!-- Enterprise Dark Header Strip -->
    <header class="lm-pay-show-header">
        <div class="lm-pay-show-header-left">
            @if($isEmbeddedModal)
            <button type="button"
                    class="lm-btn-nav"
                    onclick="window.jQuery && window.jQuery('.view_modal').modal('hide');"
                    title="{{ $lmText('Close', 'បិទ') }}">
                <i class="fa fa-times"></i>
            </button>
            @endif
            <div class="lm-pay-show-icon">
                <i class="fa fa-file-text-o"></i>
            </div>
            <div>
                <h1 class="lm-pay-show-title">
                    {{ $lmText('Payment Receipt', 'បង្កាន់ដៃទូទាត់') }} #{{ $receipt }}
                    <span class="lm-status-pill {{ $paymentStatus }}">
                        <i class="fa fa-circle" style="font-size: 6px;"></i>
                        {{ ucfirst($payment->status ?? 'confirmed') }}
                    </span>
                </h1>
                <p class="lm-pay-show-sub">
                    <i class="fa fa-file-text-o"></i> {{ $loanNumber }} &bull;
                    <i class="fa fa-user"></i> {{ $customerName }} &bull;
                    <i class="fa fa-calendar"></i> {{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}
                </p>
            </div>
        </div>

        <div class="lm-pay-show-header-right">
            @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.payment|loan_management.payments.create|loan_management.edit'))
                <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}" class="lm-btn-nav lm-btn-nav-primary">
                    <i class="fa fa-edit"></i> {{ $lmText('Edit Payment', 'កែប្រែការបង់') }}
                </a>
            @endif

            @unless($isEmbeddedModal)
                <a href="{{ route('loan-management.payments.index') }}" class="lm-btn-nav">
                    <i class="fa fa-arrow-left"></i> {{ $lmText('Back to Payments', 'ត្រឡប់ក្រោយ') }}
                </a>
            @endunless
        </div>
    </header>

    <!-- Main Workspace Body -->
    <div class="lm-pay-show-body">
        <!-- Top KPI Metric Strip -->
        <div class="lm-kpi-strip">
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Total Amount Paid', 'ទឹកប្រាក់បានបង់') }}</div>
                <div class="lm-kpi-val text-success">{{ number_format($amount, 2) }} <small>{{ $loanCurrency }}</small></div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Payment Method', 'វិធីទូទាត់') }}</div>
                <div class="lm-kpi-val" style="font-size: 13px;">{{ ucfirst($method) }}</div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Payment Type', 'ប្រភេទ') }}</div>
                <div class="lm-kpi-val" style="font-size: 13px; margin-top: 4px;">
                    @php
                        $showRawType = strtolower(trim((string)($payment->payment_type ?? 'monthly')));
                        $showTypeClass = match($showRawType) {
                            'payoff', 'pay_off' => 'lm-type-payoff',
                            'loan', 'down_payment', 'downpayment', 'deposit', 'initial' => 'lm-type-deposit',
                            'advance', 'prepayment' => 'lm-type-advance',
                            'penalty', 'late_fee' => 'lm-type-penalty',
                            default => 'lm-type-monthly',
                        };
                        $showTypeIcon = match($showRawType) {
                            'payoff', 'pay_off' => 'fa fa-check-circle',
                            'loan', 'down_payment', 'downpayment', 'deposit', 'initial' => 'fa fa-bookmark',
                            'advance', 'prepayment' => 'fa fa-forward',
                            'penalty', 'late_fee' => 'fa fa-exclamation-circle',
                            default => 'fa fa-calendar-check-o',
                        };
                    @endphp
                    <span class="lm-type-badge {{ $showTypeClass }}">
                        <i class="{{ $showTypeIcon }}"></i>
                        {{ \Modules\LoanManagement\Http\Controllers\LoanPaymentController::paymentTypeLabel($payment->payment_type ?? 'monthly') }}
                    </span>
                </div>
            </div>
            <div class="lm-kpi-card">
                <div class="lm-kpi-lbl">{{ $lmText('Remaining Loan Balance', 'សមតុល្យកិច្ចសន្យា') }}</div>
                <div class="lm-kpi-val text-danger">{{ number_format($loanBalance, 2) }} <small>{{ $loanCurrency }}</small></div>
            </div>
        </div>

        <div class="lm-grid-2col">
            <!-- Left Column: Payment Details & Split Lines -->
            <div class="lm-col">
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-check-circle"></i> {{ $lmText('Payment Information', 'ព័ត៌មានបង្កាន់ដៃទូទាត់') }}
                        </h2>
                    </div>
                    <div class="lm-card-body">
                        <div class="lm-info-grid">
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Receipt Number', 'លេខបង្កាន់ដៃ') }}</span>
                                <span class="lm-info-val"><strong>{{ $receipt }}</strong></span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Paid Date', 'កាលបរិច្ឆេទ') }}</span>
                                <span class="lm-info-val">{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Reference Code', 'លេខយោង') }}</span>
                                <span class="lm-info-val">{{ $payment->reference_number ?? '-' }}</span>
                            </div>
                            <div class="lm-info-item">
                                <span class="lm-info-lbl">{{ $lmText('Received / Collected By', 'អ្នកទទួលប្រាក់') }}</span>
                                <span class="lm-info-val">{{ $payment->received_by_name_snapshot ?? ($payment->collected_by_name_snapshot ?? '-') }}</span>
                            </div>
                            @if(!empty($payment->note))
                            <div class="lm-info-item full-width">
                                <span class="lm-info-lbl">{{ $lmText('Note / Remarks', 'កំណត់ចំណាំ') }}</span>
                                <span class="lm-info-val" style="font-weight: normal; color: #475569;">{{ $payment->note }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Payment Breakdown Lines -->
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-list-alt"></i> {{ $lmText('Payment Breakdown Lines', 'លម្អិតការបង់ប្រាក់') }}
                        </h2>
                    </div>
                    <div class="lm-card-body table-responsive" style="padding: 0;">
                        <table class="lm-table-dense">
                            <thead>
                                <tr>
                                    <th>{{ $lmText('Method', 'វិធីទូទាត់') }}</th>
                                    <th class="text-right">{{ $lmText('Amount', 'ចំនួនទឹកប្រាក់') }}</th>
                                    <th>{{ $lmText('Currency', 'រូបិយប័ណ្ណ') }}</th>
                                    <th>{{ $lmText('Reference', 'លេខយោង') }}</th>
                                    <th>{{ $lmText('Note', 'កំណត់ចំណាំ') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($details as $detail)
                                <tr>
                                    <td><strong>{{ $detail->payment_method_snapshot ?? ($detail->method ?? '-') }}</strong></td>
                                    <td class="text-right" style="color: #16a34a; font-weight: 700;">{{ number_format((float) ($detail->amount_base ?? ($detail->amount ?? 0)), 2) }}</td>
                                    <td>{{ $detail->currency ?? $loanCurrency }}</td>
                                    <td>{{ $detail->reference_number ?? ($detail->transaction_no ?? '-') }}</td>
                                    <td>{{ $detail->note ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td><strong>{{ $method }}</strong></td>
                                    <td class="text-right" style="color: #16a34a; font-weight: 700;">{{ number_format($amount, 2) }}</td>
                                    <td>{{ $payment->currency ?? $loanCurrency }}</td>
                                    <td>{{ $payment->reference_number ?? '-' }}</td>
                                    <td>{{ $payment->note ?? '-' }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Loan & Schedule Snapshot -->
            <div class="lm-col">
                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-file-text-o"></i> {{ $lmText('Associated Installment', 'កិច្ចសន្យារំលស់ពាក់ព័ន្ធ') }}
                        </h2>
                    </div>
                    <div class="lm-card-body">
                        @if($loan)
                            <div class="lm-info-grid">
                                <div class="lm-info-item full-width">
                                    <span class="lm-info-lbl">{{ $lmText('Installment #', 'លេខកិច្ចសន្យា') }}</span>
                                    <span class="lm-info-val">
                                        <a href="{{ route('loan-management.loans.view', $loan->id) }}" target="_blank" style="color: #2563eb; font-weight: 700;">
                                            {{ $loan->loan_number ?? ('Installment #'.$loan->id) }}
                                        </a>
                                    </span>
                                </div>
                                <div class="lm-info-item">
                                    <span class="lm-info-lbl">{{ $lmText('Customer', 'អតិថិជន') }}</span>
                                    <span class="lm-info-val">{{ $loan->customer_name_snapshot ?? '-' }}</span>
                                </div>
                                <div class="lm-info-item">
                                    <span class="lm-info-lbl">{{ $lmText('Phone', 'ទូរស័ព្ទ') }}</span>
                                    <span class="lm-info-val">{{ $loan->customer_phone_snapshot ?? '-' }}</span>
                                </div>
                                <div class="lm-info-item full-width">
                                    <span class="lm-info-lbl">{{ $lmText('Remaining Balance', 'សមតុល្យនៅសល់') }}</span>
                                    <span class="lm-info-val" style="color: #dc2626; font-size: 13px;">
                                        {{ number_format((float) ($loan->balance_amount ?? 0), 2) }} {{ $loanCurrency }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <p class="text-muted" style="margin: 0; font-size: 11px;">{{ $lmText('No loan record found.', 'រកមិនឃើញកិច្ចសន្យារំលស់ទេ។') }}</p>
                        @endif
                    </div>
                </div>

                <div class="lm-card">
                    <div class="lm-card-head">
                        <h2 class="lm-card-title">
                            <i class="fa fa-calendar-check-o"></i> {{ $lmText('Linked Schedule Installment', 'ដំណាក់កាលបង់') }}
                        </h2>
                    </div>
                    <div class="lm-card-body">
                        @if($schedule)
                            <div class="lm-info-grid">
                                <div class="lm-info-item">
                                    <span class="lm-info-lbl">{{ $lmText('Installment #', 'វគ្គទី') }}</span>
                                    <span class="lm-info-val"><strong>#{{ $schedule->installment_no ?? '-' }}</strong></span>
                                </div>
                                <div class="lm-info-item">
                                    <span class="lm-info-lbl">{{ $lmText('Due Date', 'ថ្ងៃត្រូវបង់') }}</span>
                                    <span class="lm-info-val">{{ $schedule->due_date ?? '-' }}</span>
                                </div>
                                <div class="lm-info-item">
                                    <span class="lm-info-lbl">{{ $lmText('Schedule Due', 'ទឹកប្រាក់ត្រូវបង់') }}</span>
                                    <span class="lm-info-val">{{ number_format((float) ($schedule->schedule_amount ?? ($schedule->amount_due ?? 0)), 2) }} {{ $loanCurrency }}</span>
                                </div>
                                <div class="lm-info-item">
                                    <span class="lm-info-lbl">{{ $lmText('Schedule Status', 'ស្ថានភាព') }}</span>
                                    <span class="lm-info-val"><span class="lm-status-pill confirmed">{{ ucfirst($schedule->status ?? '-') }}</span></span>
                                </div>
                            </div>
                        @else
                            <p class="text-muted" style="margin: 0; font-size: 11px;">
                                <i class="fa fa-info-circle"></i> {{ $lmText('This payment is not linked to a specific monthly schedule (e.g. Down payment / Bulk payment).', 'ការទូទាត់នេះមិនបានភ្ជាប់ជាមួយវគ្គបង់ប្រចាំខែជាក់លាក់ទេ (ដូចជាប្រាក់កក់ ឬការទូទាត់សរុប)។') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
