@extends('loanmanagement::layouts.app')
@section('title', 'Edit Loan')

@section('loan_css')
<style>
    .lm-edit-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 18px;
    }
    .lm-edit-title h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    }
    .lm-edit-title p {
        margin: 6px 0 0;
        color: #6b7280;
    }
    .lm-edit-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .lm-edit-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .lm-edit-metric {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        padding: 14px 16px;
        min-height: 96px;
    }
    .lm-edit-metric__label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #6b7280;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .lm-edit-metric__value {
        margin-top: 10px;
        font-size: 24px;
        line-height: 1.1;
        font-weight: 700;
        color: #111827;
        overflow-wrap: anywhere;
    }
    .lm-edit-metric__sub {
        margin-top: 8px;
        color: #6b7280;
        font-size: 12px;
    }
    .lm-edit-box {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: none;
    }
    .lm-edit-box > .box-header {
        border-bottom-color: #eef2f7;
        padding: 14px 16px;
    }
    .lm-edit-box > .box-header .box-title {
        font-weight: 700;
        color: #1f2937;
    }
    .lm-edit-box > .box-header,
    .lm-collapsible > .box-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .lm-edit-box > .box-header .box-tools,
    .lm-collapsible > .box-header .box-tools {
        margin-left: auto;
    }
    .lm-collapse-toggle {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        border-radius: 6px;
        width: 30px;
        height: 28px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }
    .lm-collapse-toggle:hover {
        background: #f8fafc;
        color: #111827;
    }
    .lm-collapsible.is-collapsed > .box-body,
    .lm-collapsible.is-collapsed > .box-footer {
        display: none;
    }
    .lm-collapsible.is-collapsed .lm-collapse-toggle .fa:before {
        content: "\f067";
    }
    .lm-edit-box > .box-body {
        padding: 16px;
    }
    .lm-edit-box .form-group label {
        color: #374151;
        font-size: 12px;
        font-weight: 700;
    }
    .lm-edit-box .form-control {
        border-radius: 6px;
        border-color: #d1d5db;
        min-height: 34px;
    }
    .lm-field-error {
        display: block;
        margin-top: 5px;
        color: #b91c1c;
        font-size: 12px;
        font-weight: 600;
    }
    .lm-edit-box .has-error .form-control {
        border-color: #b91c1c;
    }
    .lm-edit-snapshot {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }
    .lm-edit-snapshot__item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 12px;
        background: #f9fafb;
        min-height: 68px;
    }
    .lm-edit-snapshot__item small {
        display: block;
        color: #6b7280;
        margin-bottom: 5px;
    }
    .lm-edit-snapshot__item strong {
        display: block;
        color: #111827;
        overflow-wrap: anywhere;
    }
    .lm-standard-sections {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .lm-standard-card {
        display: block;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        padding: 14px 16px;
        color: #111827;
        min-height: 118px;
        text-decoration: none;
    }
    .lm-standard-card:hover,
    .lm-standard-card:focus {
        border-color: #93c5fd;
        color: #111827;
        text-decoration: none;
        background: #f8fafc;
    }
    .lm-standard-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }
    .lm-standard-card__title {
        font-size: 13px;
        font-weight: 800;
        color: #1f2937;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .lm-standard-card__icon {
        color: #2563eb;
        font-size: 15px;
    }
    .lm-standard-card__value {
        font-size: 16px;
        font-weight: 800;
        color: #111827;
        overflow-wrap: anywhere;
        line-height: 1.25;
    }
    .lm-standard-card__meta {
        margin-top: 8px;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .lm-edit-footer {
        position: sticky;
        bottom: 0;
        z-index: 4;
        background: #fff;
        border-top: 1px solid #e5e7eb;
    }
    @media (max-width: 991px) {
        .lm-edit-header {
            display: block;
        }
        .lm-edit-actions {
            justify-content: flex-start;
            margin-top: 12px;
        }
        .lm-edit-summary,
        .lm-edit-snapshot,
        .lm-standard-sections {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575px) {
        .lm-edit-summary,
        .lm-edit-snapshot,
        .lm-standard-sections {
            grid-template-columns: 1fr;
        }
        .lm-edit-title h1 {
            font-size: 18px;
        }
        .lm-edit-metric__value {
            font-size: 18px;
        }
        .lm-edit-actions {
            flex-direction: column;
        }
        .lm-edit-actions .btn {
            width: 100%;
            text-align: center;
        }
    }

    .lm-edit-sections-mobile {
        display: none;
    }
    .lm-edit-sections-table {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .lm-edit-sections-table table {
        min-width: 600px;
    }

    @media (max-width: 768px) {
        .lm-edit-sections-mobile {
            display: block;
        }
        .lm-edit-sections-table {
            display: none;
        }

        .lm-edit-section-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .lm-edit-section-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .lm-edit-section-card-title {
            font-weight: 700;
            font-size: 13px;
            color: #1e3a8a;
        }
        .lm-edit-section-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        .lm-edit-section-card-item small {
            display: block;
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
        }
        .lm-edit-section-card-item span {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
            margin-top: 2px;
            word-break: break-word;
        }
        .lm-edit-section-card-actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
        }
        .lm-edit-section-card-actions .btn {
            flex: 1;
            min-height: 34px;
            font-size: 11px;
        }

        .box.box-primary.lm-edit-box,
        .box.box-info.lm-edit-box,
        .box.box-solid.lm-edit-box,
        .box.box-default.lm-edit-box {
            border-radius: 10px;
        }
        .box.box-primary.lm-edit-box > .box-header,
        .box.box-info.lm-edit-box > .box-header,
        .box.box-solid.lm-edit-box > .box-header,
        .box.box-default.lm-edit-box > .box-header {
            padding: 10px 12px;
        }
        .box.box-primary.lm-edit-box > .box-body,
        .box.box-info.lm-edit-box > .box-body,
        .box.box-solid.lm-edit-box > .box-body,
        .box.box-default.lm-edit-box > .box-body {
            padding: 10px 12px;
        }
    }
    @media (max-width: 575px) {
        .lm-edit-section-card-body {
            grid-template-columns: 1fr;
        }
    }
    .lm-edit-clean .box {
        border-top: 0;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
        margin-bottom: 16px;
    }
    .lm-edit-clean .box-header {
        padding: 12px 16px;
    }
    .lm-edit-clean .box-body {
        padding: 16px;
    }
    .lm-edit-clean .form-group {
        margin-bottom: 10px;
    }
    .lm-edit-clean .row > [class*='col-'] {
        padding-left: 8px;
        padding-right: 8px;
    }
    .lm-edit-clean .lm-edit-summary {
        display: none;
    }
    .lm-edit-clean .lm-standard-sections {
        margin-bottom: 16px;
    }
    .lm-edit-clean .lm-standard-card {
        min-height: 92px;
        border-radius: 6px;
        padding: 12px 14px;
    }
    .lm-edit-clean .lm-standard-card__head {
        margin-bottom: 6px;
    }
    .lm-edit-clean .lm-standard-card__value {
        font-size: 15px;
    }
    .lm-edit-clean .lm-standard-card__meta {
        margin-top: 5px;
    }
    .lm-edit-clean .lm-edit-box {
        border-radius: 6px;
    }
    .lm-edit-clean .lm-edit-box > .box-header .box-title {
        font-size: 16px;
    }
    .lm-edit-clean .lm-clean-secondary {
        border-style: dashed;
        box-shadow: none;
    }
    .lm-edit-clean .lm-clean-secondary > .box-header .box-title {
        color: #64748b;
    }
</style>
@if(request()->boolean('_lm_modal'))
<style>
    html,
    body,
    #scrollable-container {
        background: #f8fafc !important;
    }

    body.loan-management-embedded-modal .thetop,
    body.loan-management-embedded-modal #scrollable-container,
    body.loan-management-embedded-modal .lm-app,
    body.loan-management-embedded-modal #loanManagementMain {
        min-height: 0 !important;
        height: auto !important;
        overflow: visible !important;
    }

    body.loan-management-embedded-modal #loanManagementMain {
        margin-left: 0 !important;
        width: 100% !important;
    }

    body.loan-management-embedded-modal .lm-content {
        padding: 0 !important;
        background: #f8fafc !important;
    }

    body.loan-management-embedded-modal .lm-workspace {
        padding: 18px 22px 28px !important;
        max-width: none !important;
    }

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
        padding: 12px 18px 24px !important;
    }

    .content-header {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .content {
        min-height: auto !important;
        padding: 0 !important;
    }

    .lm-edit-header {
        position: sticky;
        top: 0;
        z-index: 20;
        background: rgba(248, 250, 252, .96);
        border-bottom: 1px solid #e5e7eb;
        padding: 0 0 14px;
        backdrop-filter: blur(8px);
    }

    .lm-edit-summary {
        grid-template-columns: repeat(4, minmax(160px, 1fr));
    }
</style>
@endif
@endsection

@section('content_body')
@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $backCustomerId = request('customer_id') ?: ($loanRow->customer_id ?? null);
    $editRouteParams = ['loan' => $loanRow->id];
    $viewRouteParams = ['loan' => $loanRow->id];
    if ($isEmbeddedModal) {
        $editRouteParams['_lm_modal'] = 1;
        $viewRouteParams['_lm_modal'] = 1;
    }
    if (!empty($backCustomerId)) {
        $editRouteParams['customer_id'] = $backCustomerId;
    }
    $loanStatuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted', 'closed'];
    $paymentFrequencies = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
    $interestTypes = [
        'flat' => 'បង់ថេរ',
        'reducing_balance' => 'បង់ថយ',
    ];
    $collectionStatuses = ['new', 'active', 'follow_up', 'ptp', 'overdue', 'escalated', 'recovery', 'closed'];
    $riskLevels = ['low', 'medium', 'high', 'critical'];
    $ptpStatuses = ['open', 'kept', 'broken', 'cancelled'];
    $skipLevels = ['none', 'soft', 'medium', 'hard'];
    $cleanEditValue = function ($value) {
        $value = trim((string) $value);
        return $value === '-' ? '' : $value;
    };
    $editCustomerName = $cleanEditValue($customerName);
    $editCustomerPhone = $cleanEditValue($customerPhone);
    $editCustomerAddress = $cleanEditValue($customerAddress);
    $editLocationName = $cleanEditValue($locationName);
    $principalAfterDepositValue = (float) old('principal_amount', $loanRow->principal_amount ?? $loanRow->financed_amount ?? 0);
    $downPaymentValue = (float) old('down_payment', $loanRow->down_payment ?? 0);
    $sourceTotalBeforeDeposit = (float) ($loanRow->sell_final_total_snapshot ?? 0);
    $productTotalBeforeDeposit = $sourceTotalBeforeDeposit > 0
        ? $sourceTotalBeforeDeposit
        : ($principalAfterDepositValue + $downPaymentValue);
    $reviewTotalAmount = (float) old('total_amount', $loanRow->total_amount ?? (($loanRow->principal_amount ?? 0) + ($loanRow->interest_amount ?? 0)));
    $reviewBalanceAmount = (float) old('balance_amount', $loanRow->balance_amount ?? 0);
@endphp

<section class="content lm-edit-clean">
    <div class="lm-edit-header">
        <div class="lm-edit-title">
            <h1><i class="fa fa-pencil-square-o"></i> Edit Loan</h1>
            <p>Loan #{{ $loanRow->loan_number ?? $loanRow->id }} · {{ ucfirst($loanRow->status ?? 'draft') }}</p>
        </div>
        <div class="lm-edit-actions">
            @if(!empty($backCustomerId))
                <a href="{{ route('loan-management.customers.edit', $backCustomerId) }}" class="btn btn-default btn-sm" @if($isEmbeddedModal) target="_top" @endif>
                    <i class="fa fa-arrow-left"></i> Back to Customer
                </a>
            @endif
            <a href="{{ route('loan-management.loans.view', $viewRouteParams) }}" class="btn btn-default btn-sm">
                <i class="fa fa-eye"></i> View Loan
            </a>
            <button type="submit" form="loan_edit_form" class="btn btn-primary btn-sm">
                <i class="fa fa-save"></i> Save Loan
            </button>
        </div>
    </div>

    @if ($errors->any())
        @php
            $fullSaveError = $errors->first('save_error');
        @endphp
        <div class="alert alert-danger">
            <strong>Unable to save this loan.</strong>
            <div>Please check the highlighted fields below.</div>
            @if ($fullSaveError)
                <div style="margin-top: 8px;">
                    <a href="#" id="loanViewErrorLink">View error details</a>
                </div>
                <div id="loanErrorDetailsBox" style="display:none; margin-top:10px;">
                    <pre style="white-space:pre-wrap; word-break:break-word; margin:0; padding:10px; background:#fff; border:1px solid #f1b0b7; color:#a94442;">{{ $fullSaveError }}</pre>
                </div>
            @endif
            <ul style="margin:8px 0 0 18px; padding:0;">
                @foreach ($errors->all() as $error)
                    @if ($error === $fullSaveError)
                        @continue
                    @endif
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success">
            {{ is_array(session('status')) ? (session('status.msg') ?? 'Saved successfully.') : session('status') }}
        </div>
    @endif

    <div class="lm-edit-summary">
        <div class="lm-edit-metric">
            <div class="lm-edit-metric__label"><span>Principal</span><i class="fa fa-money"></i></div>
            <div class="lm-edit-metric__value">{{ number_format((float) ($loanRow->principal_amount ?? 0), 2) }}</div>
            <div class="lm-edit-metric__sub">Balance {{ number_format((float) ($loanRow->balance_amount ?? 0), 2) }}</div>
        </div>
        <div class="lm-edit-metric">
            <div class="lm-edit-metric__label"><span>Payments</span><i class="fa fa-credit-card"></i></div>
            <div class="lm-edit-metric__value">{{ $paymentsCount ?? 0 }}</div>
            <div class="lm-edit-metric__sub">Paid {{ number_format((float) ($loanRow->paid_amount ?? 0), 2) }}</div>
        </div>
        <div class="lm-edit-metric">
            <div class="lm-edit-metric__label"><span>Schedules</span><i class="fa fa-calendar"></i></div>
            <div class="lm-edit-metric__value">{{ $schedulesCount ?? 0 }}</div>
            <div class="lm-edit-metric__sub">Frequency {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</div>
        </div>
        <div class="lm-edit-metric">
            <div class="lm-edit-metric__label"><span>Loan Items</span><i class="fa fa-cubes"></i></div>
            <div class="lm-edit-metric__value">{{ $loanItemsCount ?? 0 }}</div>
            <div class="lm-edit-metric__sub">Status {{ ucfirst($loanRow->status ?? 'draft') }}</div>
        </div>
    </div>

    <div class="lm-standard-sections">
        <a href="#lm-section-invoice" class="lm-standard-card">
            <div class="lm-standard-card__head">
                <span class="lm-standard-card__title">Invoice</span>
                <i class="fa fa-file-text-o lm-standard-card__icon"></i>
            </div>
            <div class="lm-standard-card__value">{{ $loanRow->loan_number ?? $loanRow->id }}</div>
            <div class="lm-standard-card__meta">
                Source {{ $sourceInvoice ?: ($loanRow->source_invoice_no ?? '-') }}<br>
                {{ !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('d-m-Y') : 'No loan date' }}
            </div>
        </a>
        <a href="#lm-section-customer" class="lm-standard-card">
            <div class="lm-standard-card__head">
                <span class="lm-standard-card__title">Customer</span>
                <i class="fa fa-user-o lm-standard-card__icon"></i>
            </div>
            <div class="lm-standard-card__value">{{ $editCustomerName ?: ($customerName ?? '-') }}</div>
            <div class="lm-standard-card__meta">
                {{ $editCustomerPhone ?: ($customerPhone ?? '-') }}<br>
                {{ $editLocationName ?: ($locationName ?? '-') }}
            </div>
        </a>
        <a href="#lm-section-products" class="lm-standard-card" id="loanProductsReferenceLink">
            <div class="lm-standard-card__head">
                <span class="lm-standard-card__title">Products</span>
                <i class="fa fa-cubes lm-standard-card__icon"></i>
            </div>
            <div class="lm-standard-card__value">{{ $loanRow->product_name_snapshot ?? 'Loan Items' }}</div>
            <div class="lm-standard-card__meta">
                Items {{ $loanItemsCount ?? 0 }}<br>
                Reference in Loan Items
            </div>
        </a>
        <a href="#lm-section-review" class="lm-standard-card">
            <div class="lm-standard-card__head">
                <span class="lm-standard-card__title">Review</span>
                <i class="fa fa-check-square-o lm-standard-card__icon"></i>
            </div>
            <div class="lm-standard-card__value">{{ number_format($reviewBalanceAmount, 2) }}</div>
            <div class="lm-standard-card__meta">
                Total {{ number_format($reviewTotalAmount, 2) }}<br>
                Paid {{ number_format((float) ($loanRow->paid_amount ?? 0), 2) }}
            </div>
        </a>
    </div>

    <form method="POST" action="{{ route('loan-management.loans.update', $editRouteParams) }}" id="loan_edit_form">
        @csrf
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary lm-edit-box lm-collapsible" data-collapse-key="customer" id="lm-section-customer">
                <div class="box-header with-border">
                    <h3 class="box-title">Customer</h3>
                    <div class="box-tools pull-right">
                        <span class="label label-default">Customer & location</span>
                        <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                            <i class="fa fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loan #</label>
                            <input type="text" class="form-control" value="{{ $loanRow->loan_number ?? $loanRow->id }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer</label>
                            <input type="text" name="customer_name_snapshot" class="form-control" value="{{ old('customer_name_snapshot', $editCustomerName) }}">
                            @error('customer_name_snapshot')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="customer_phone_snapshot" class="form-control" value="{{ old('customer_phone_snapshot', $editCustomerPhone) }}">
                            @error('customer_phone_snapshot')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer ID</label>
                            <input type="number" min="0" name="customer_id" class="form-control" value="{{ old('customer_id', $loanRow->customer_id ?? '') }}">
                            @error('customer_id')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Main Contact ID</label>
                            <input type="number" min="0" name="main_contact_id" class="form-control" value="{{ old('main_contact_id', $mainContactId ?? '') }}">
                            @error('main_contact_id')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Location</label>
                            <select name="business_location_id" id="loanBusinessLocationSelect" class="form-control">
                                <option value="">Select location</option>
                                @foreach($locationOptions as $locationOption)
                                    <option value="{{ $locationOption->id }}"
                                        data-name="{{ $locationOption->name }}"
                                        data-main-location-id="{{ $locationOption->main_location_id ?? '' }}"
                                        data-address="{{ $locationOption->address ?? '' }}"
                                        {{ (string) old('business_location_id', $selectedBusinessLocationId ?? '') === (string) $locationOption->id ? 'selected' : '' }}>
                                        {{ $locationOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="business_location_name_snapshot" id="loanBusinessLocationName" value="{{ old('business_location_name_snapshot', $editLocationName) }}">
                            @error('business_location_id')<span class="lm-field-error">{{ $message }}</span>@enderror
                            @error('business_location_name_snapshot')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Main Location ID</label>
                            <input type="number" min="0" name="main_location_id" id="loanMainLocationIdInput" class="form-control" value="{{ old('main_location_id', $locationId ?? $loanRow->main_location_id ?? '') }}">
                            @error('main_location_id')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Business Location ID</label>
                            <input type="number" min="0" id="loanBusinessLocationIdDisplay" class="form-control" value="{{ old('business_location_id', $selectedBusinessLocationId ?? $loanRow->business_location_id ?? '') }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="customer_address_snapshot" class="form-control" rows="2">{{ old('customer_address_snapshot', $editCustomerAddress) }}</textarea>
                            @error('customer_address_snapshot')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-12" style="margin-top: -8px;">
                        <p><strong>Location Address:</strong> <span id="loanLocationAddressText">{{ $locationAddress }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <div class="box box-primary lm-edit-box lm-collapsible" data-collapse-key="invoice" id="lm-section-invoice">
            <div class="box-header with-border">
                <h3 class="box-title">Invoice</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                @foreach($loanStatuses as $status)
                                    <option value="{{ $status }}" {{ old('status', $loanRow->status ?? 'draft') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            @error('status')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Payment Frequency</label>
                            <select name="payment_frequency" class="form-control">
                                @foreach($paymentFrequencies as $frequency)
                                    <option value="{{ $frequency }}" {{ old('payment_frequency', $loanRow->payment_frequency ?? 'monthly') === $frequency ? 'selected' : '' }}>{{ ucfirst($frequency) }}</option>
                                @endforeach
                            </select>
                            @error('payment_frequency')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Currency</label>
                            <input type="text" name="currency" class="form-control" value="{{ old('currency', $loanRow->currency ?? 'USD') }}">
                            @error('currency')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Invoice No</label>
                            <input type="text" name="source_invoice_no" class="form-control" value="{{ old('source_invoice_no', $loanRow->source_invoice_no ?? '') }}">
                            @error('source_invoice_no')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Installment Count</label>
                            <input type="number" name="installment_count" class="form-control" min="0" value="{{ old('installment_count', $loanRow->installment_count ?? 0) }}">
                            <input type="hidden" name="duration_months" id="loanDurationMonthsInput" value="{{ old('duration_months', $loanRow->duration_months ?? $loanRow->installment_count ?? 0) }}">
                            @error('installment_count')<span class="lm-field-error">{{ $message }}</span>@enderror
                            @error('duration_months')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Interest Rate (%)</label>
                            <input type="number" step="0.01" min="0" name="interest_rate" class="form-control" value="{{ old('interest_rate', $displayInterestRate ?? 0) }}">
                            @error('interest_rate')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Interest Type</label>
                            <select name="interest_type" class="form-control">
                                @foreach($interestTypes as $interestType => $interestTypeLabel)
                                    <option value="{{ $interestType }}" {{ old('interest_type', $displayInterestType ?? 'flat') === $interestType ? 'selected' : '' }}>{{ $interestTypeLabel }}</option>
                                @endforeach
                            </select>
                            @error('interest_type')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loan Date</label>
                            <input type="date" name="loan_date" class="form-control" value="{{ old('loan_date', !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('Y-m-d') : '') }}">
                            @error('loan_date')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>First Due Date</label>
                            <input type="date" name="first_due_date" class="form-control" value="{{ old('first_due_date', !empty($loanRow->first_due_date) ? \Carbon\Carbon::parse($loanRow->first_due_date)->format('Y-m-d') : '') }}">
                            @error('first_due_date')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Maturity Date</label>
                            <input type="date" name="maturity_date" class="form-control" value="{{ old('maturity_date', !empty($loanRow->maturity_date) ? \Carbon\Carbon::parse($loanRow->maturity_date)->format('Y-m-d') : '') }}">
                            @error('maturity_date')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Approved At</label>
                            <input type="date" name="approved_at" class="form-control" value="{{ old('approved_at', !empty($loanRow->approved_at) ? \Carbon\Carbon::parse($loanRow->approved_at)->format('Y-m-d') : '') }}">
                            @error('approved_at')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="button" class="btn btn-info" id="btnGenerateLoanPreview">
                            <i class="fa fa-refresh"></i> Generate Preview
                        </button>
                        <button type="button"
                                class="btn btn-success"
                                id="btnUpdatePaymentSchedules"
                                data-url="{{ route('loan-management.loans.schedules.update-from-edit', ['loan' => $loanRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}">
                            <i class="fa fa-calendar-check-o"></i> Update Payment Schedules
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-primary lm-edit-box lm-collapsible" data-collapse-key="products" id="lm-section-products">
            <div class="box-header with-border">
                <h3 class="box-title">Products</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="lm-edit-snapshot">
                    <div class="lm-edit-snapshot__item"><small>Product</small><strong>{{ $loanRow->product_name_snapshot ?? 'Loan Items' }}</strong></div>
                    <div class="lm-edit-snapshot__item"><small>Items</small><strong>{{ $loanItemsCount ?? 0 }}</strong></div>
                    <div class="lm-edit-snapshot__item"><small>IMEI</small><strong>{{ $loanRow->imei_snapshot ?? '-' }}</strong></div>
                    <div class="lm-edit-snapshot__item"><small>Invoice</small><strong>{{ $loanRow->invoice_number_snapshot ?? $loanRow->source_invoice_no ?? '-' }}</strong></div>
                </div>
                <div class="text-right" style="margin-top: 10px;">
                    <button type="button" class="btn btn-xs btn-default" id="loanProductsSectionReferenceButton">
                        <i class="fa fa-eye"></i> View Reference
                    </button>
                </div>
            </div>
        </div>

        <div class="box box-info lm-edit-box lm-collapsible is-collapsed lm-clean-secondary" data-collapse-key="schedule-preview">
            <div class="box-header with-border">
                <h3 class="box-title">Schedule Preview</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="loanSchedulePreviewTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Due Date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Total</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Click Generate Preview to recalculate the schedule.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-right">Totals</th>
                            <th>0.00</th>
                            <th>0.00</th>
                            <th>0.00</th>
                            <th>0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="box box-primary lm-edit-box lm-collapsible" data-collapse-key="review" id="lm-section-review">
            <div class="box-header with-border">
                <h3 class="box-title">Review</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <input type="hidden" id="loanProductTotalBeforeDeposit" value="{{ number_format($productTotalBeforeDeposit, 2, '.', '') }}">
                    <input type="hidden" name="financed_amount" value="{{ old('financed_amount', $loanRow->financed_amount ?? $loanRow->principal_amount ?? 0) }}">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Principal After Deposit <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="principal_amount" class="form-control" value="{{ old('principal_amount', $loanRow->principal_amount ?? $loanRow->financed_amount ?? 0) }}">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="btnRegeneratePrincipalAfterDeposit" title="Regenerate from deposit">
                                        <i class="fa fa-refresh"></i>
                                    </button>
                                </span>
                            </div>
                            @error('principal_amount')<span class="lm-field-error">{{ $message }}</span>@enderror
                            @error('financed_amount')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-2"><div class="form-group"><label>Interest</label><input type="number" step="0.01" min="0" name="interest_amount" class="form-control" value="{{ old('interest_amount', $loanRow->interest_amount ?? 0) }}">@error('interest_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-2"><div class="form-group"><label>Total</label><input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="{{ old('total_amount', $loanRow->total_amount ?? 0) }}">@error('total_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-2"><div class="form-group"><label>Paid</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="{{ old('paid_amount', $loanRow->paid_amount ?? 0) }}">@error('paid_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-2"><div class="form-group"><label>Balance</label><input type="number" step="0.01" min="0" name="balance_amount" class="form-control" value="{{ old('balance_amount', $loanRow->balance_amount ?? 0) }}">@error('balance_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-2"><div class="form-group"><label>Down Payment</label><input type="number" step="0.01" min="0" name="down_payment" class="form-control" value="{{ old('down_payment', $loanRow->down_payment ?? 0) }}">@error('down_payment')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-2"><div class="form-group"><label>Penalty</label><input type="number" step="0.01" min="0" name="penalty_amount" class="form-control" value="{{ old('penalty_amount', $loanRow->penalty_amount ?? 0) }}">@error('penalty_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-2"><div class="form-group"><label>Discount</label><input type="number" step="0.01" min="0" name="discount_amount" class="form-control" value="{{ old('discount_amount', $loanRow->discount_amount ?? 0) }}">@error('discount_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                </div>
            </div>
        </div>

        <div class="box box-primary lm-edit-box lm-collapsible is-collapsed lm-clean-secondary" data-collapse-key="collection-workflow">
            <div class="box-header with-border">
                <h3 class="box-title">Source & Collection Workflow</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Type</label>
                            <input type="text" name="source_type" class="form-control" value="{{ old('source_type', $loanRow->source_type ?? '') }}">
                            @error('source_type')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Created At</label>
                            <input type="date" name="source_created_at" class="form-control" value="{{ old('source_created_at', !empty($loanRow->source_created_at) ? \Carbon\Carbon::parse($loanRow->source_created_at)->format('Y-m-d') : '') }}">
                            @error('source_created_at')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Collection Status</label>
                            <select name="collection_status" class="form-control">
                                <option value="">Select</option>
                                @foreach($collectionStatuses as $status)
                                    <option value="{{ $status }}" {{ old('collection_status', $loanRow->collection_status ?? '') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                            @error('collection_status')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Risk Level</label>
                            <select name="risk_level" class="form-control">
                                <option value="">Select</option>
                                @foreach($riskLevels as $risk)
                                    <option value="{{ $risk }}" {{ old('risk_level', $loanRow->risk_level ?? '') === $risk ? 'selected' : '' }}>{{ ucfirst($risk) }}</option>
                                @endforeach
                            </select>
                            @error('risk_level')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Collection Priority</label><input type="number" min="0" name="collection_priority" class="form-control" value="{{ old('collection_priority', $loanRow->collection_priority ?? 0) }}">@error('collection_priority')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Date</label><input type="date" name="ptp_date" class="form-control" value="{{ old('ptp_date', !empty($loanRow->ptp_date) ? \Carbon\Carbon::parse($loanRow->ptp_date)->format('Y-m-d') : '') }}">@error('ptp_date')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Amount</label><input type="number" step="0.01" min="0" name="ptp_amount" class="form-control" value="{{ old('ptp_amount', $loanRow->ptp_amount ?? 0) }}">@error('ptp_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>PTP Status</label>
                            <select name="ptp_status" class="form-control">
                                <option value="">Select</option>
                                @foreach($ptpStatuses as $status)
                                    <option value="{{ $status }}" {{ old('ptp_status', $loanRow->ptp_status ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            @error('ptp_status')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Broken PTP Count</label><input type="number" min="0" name="broken_ptp_count" class="form-control" value="{{ old('broken_ptp_count', $loanRow->broken_ptp_count ?? 0) }}">@error('broken_ptp_count')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Contact At</label><input type="date" name="last_contact_at" class="form-control" value="{{ old('last_contact_at', !empty($loanRow->last_contact_at) ? \Carbon\Carbon::parse($loanRow->last_contact_at)->format('Y-m-d') : '') }}">@error('last_contact_at')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Next Followup At</label><input type="date" name="next_followup_at" class="form-control" value="{{ old('next_followup_at', !empty($loanRow->next_followup_at) ? \Carbon\Carbon::parse($loanRow->next_followup_at)->format('Y-m-d') : '') }}">@error('next_followup_at')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3">
                        <div class="checkbox" style="margin-top: 32px;">
                            <label><input type="checkbox" name="stock_already_deducted" value="1" {{ old('stock_already_deducted', $loanRow->stock_already_deducted ?? 0) ? 'checked' : '' }}> Stock already deducted</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="field_visit_required" value="1" {{ old('field_visit_required', $loanRow->field_visit_required ?? 0) ? 'checked' : '' }}> Field visit required</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Skip Level</label>
                            <select name="skip_level" class="form-control">
                                <option value="">Select</option>
                                @foreach($skipLevels as $skip)
                                    <option value="{{ $skip }}" {{ old('skip_level', $loanRow->skip_level ?? '') === $skip ? 'selected' : '' }}>{{ ucfirst($skip) }}</option>
                                @endforeach
                            </select>
                            @error('skip_level')<span class="lm-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Legal Stage</label><input type="text" name="legal_stage" class="form-control" value="{{ old('legal_stage', $loanRow->legal_stage ?? '') }}">@error('legal_stage')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Stage</label><input type="text" name="recovery_stage" class="form-control" value="{{ old('recovery_stage', $loanRow->recovery_stage ?? '') }}">@error('recovery_stage')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Repossession Status</label><input type="text" name="repossession_status" class="form-control" value="{{ old('repossession_status', $loanRow->repossession_status ?? '') }}">@error('repossession_status')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Assigned Collection Team</label><input type="text" name="assigned_collection_team" class="form-control" value="{{ old('assigned_collection_team', $loanRow->assigned_collection_team ?? '') }}">@error('assigned_collection_team')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Days Past Due</label><input type="number" min="0" name="days_past_due" class="form-control" value="{{ old('days_past_due', $loanRow->days_past_due ?? 0) }}">@error('days_past_due')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Overdue Bucket</label><input type="text" name="overdue_bucket" class="form-control" value="{{ old('overdue_bucket', $loanRow->overdue_bucket ?? '') }}">@error('overdue_bucket')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Contact Attempt Count</label><input type="number" min="0" name="contact_attempt_count" class="form-control" value="{{ old('contact_attempt_count', $loanRow->contact_attempt_count ?? 0) }}">@error('contact_attempt_count')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Score</label><input type="number" min="0" name="recovery_score" class="form-control" value="{{ old('recovery_score', $loanRow->recovery_score ?? 0) }}">@error('recovery_score')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Date</label><input type="date" name="last_payment_date" class="form-control" value="{{ old('last_payment_date', !empty($loanRow->last_payment_date) ? \Carbon\Carbon::parse($loanRow->last_payment_date)->format('Y-m-d') : '') }}">@error('last_payment_date')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Amount</label><input type="number" step="0.01" min="0" name="last_payment_amount" class="form-control" value="{{ old('last_payment_amount', $loanRow->last_payment_amount ?? 0) }}">@error('last_payment_amount')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Blacklisted At</label><input type="date" name="blacklisted_at" class="form-control" value="{{ old('blacklisted_at', !empty($loanRow->blacklisted_at) ? \Carbon\Carbon::parse($loanRow->blacklisted_at)->format('Y-m-d') : '') }}">@error('blacklisted_at')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-3"><div class="form-group"><label>Written Off At</label><input type="date" name="written_off_at" class="form-control" value="{{ old('written_off_at', !empty($loanRow->written_off_at) ? \Carbon\Carbon::parse($loanRow->written_off_at)->format('Y-m-d') : '') }}">@error('written_off_at')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-6"><div class="form-group"><label>Last Contact Result</label><input type="text" name="last_contact_result" class="form-control" value="{{ old('last_contact_result', $loanRow->last_contact_result ?? '') }}">@error('last_contact_result')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                    <div class="col-md-6"><div class="form-group"><label>PTP Note</label><textarea name="ptp_note" class="form-control" rows="2">{{ old('ptp_note', $loanRow->ptp_note ?? '') }}</textarea>@error('ptp_note')<span class="lm-field-error">{{ $message }}</span>@enderror</div></div>
                </div>
            </div>
        </div>

        <div class="box box-primary lm-edit-box lm-collapsible" data-collapse-key="note">
            <div class="box-header with-border">
                <h3 class="box-title">Note</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="note" class="form-control" rows="4">{{ old('note', $loanRow->note ?? '') }}</textarea>
                    @error('note')<span class="lm-field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="box-footer text-right lm-edit-footer">
                @if(!empty($backCustomerId))
                    <a href="{{ route('loan-management.customers.edit', $backCustomerId) }}" class="btn btn-default">Back to Customer</a>
                @else
                    <a href="{{ route('loan-management.loans.view', $loanRow->id) }}" class="btn btn-default">Cancel</a>
                @endif
                <button class="btn btn-primary"><i class="fa fa-save"></i> Save Loan</button>
            </div>
        </div>
    </form>

    <div class="box box-default lm-edit-box lm-collapsible is-collapsed lm-clean-secondary" data-collapse-key="related-loan-data">
        <div class="box-header with-border">
            <h3 class="box-title">Related Loan Data</h3>
            <div class="box-tools pull-right">
                <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                    <i class="fa fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="box-body">
            <p class="text-muted">Large loan tables are loaded after the page opens so the edit form can appear faster.</p>
            <div id="loanEditSections" data-url="{{ route('loan-management.loans.sections.edit', ['loan' => $loanRow->id] + (!empty($backCustomerId) ? ['customer_id' => $backCustomerId] : []) + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}">
                <div class="text-center text-muted" style="padding: 24px 0;">
                    <i class="fa fa-spinner fa-spin"></i> Loading related sections...
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('loan_js')
<script>
    (function () {
        var select = document.getElementById('loanBusinessLocationSelect');
        var nameInput = document.getElementById('loanBusinessLocationName');
        var idDisplay = document.getElementById('loanBusinessLocationIdDisplay');
        var mainLocationInput = document.getElementById('loanMainLocationIdInput');
        var locationAddressText = document.getElementById('loanLocationAddressText');
        var sectionsContainer = document.getElementById('loanEditSections');
        var installmentCountInput = document.querySelector('input[name="installment_count"]');
        var durationMonthsInput = document.getElementById('loanDurationMonthsInput');
        var previewButton = document.getElementById('btnGenerateLoanPreview');
        var updateSchedulesButton = document.getElementById('btnUpdatePaymentSchedules');
        var previewTable = document.getElementById('loanSchedulePreviewTable');
        var viewErrorLink = document.getElementById('loanViewErrorLink');
        var errorDetailsBox = document.getElementById('loanErrorDetailsBox');
        var productTotalBeforeDepositInput = document.getElementById('loanProductTotalBeforeDeposit');
        var regeneratePrincipalButton = document.getElementById('btnRegeneratePrincipalAfterDeposit');
        var productsReferenceLink = document.getElementById('loanProductsReferenceLink');
        var productsSectionReferenceButton = document.getElementById('loanProductsSectionReferenceButton');
        var pendingProductReferenceOpen = false;

        function formatMoney(value) {
            var amount = Number(value || 0);

            return amount.toFixed(2);
        }

        function orderPrimaryEditSections() {
            var form = document.getElementById('loan_edit_form');
            var invoiceSection = document.getElementById('lm-section-invoice');
            var customerSection = document.getElementById('lm-section-customer');
            var customerBlock = customerSection && customerSection.closest ? customerSection.closest('.row') : customerSection;

            if (form && invoiceSection && customerBlock && invoiceSection.nextElementSibling !== customerBlock) {
                form.insertBefore(invoiceSection, customerBlock);
            }
        }

        orderPrimaryEditSections();

        function openProductReference() {
            var section = document.getElementById('lm-section-loan-items');
            var reference = document.querySelector('[id^="loanProductReference"]');
            if (!section || !reference) {
                return false;
            }

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.collapse) {
                window.jQuery(reference).collapse('show');
            } else {
                reference.classList.add('in');
                reference.style.display = 'block';
            }

            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            pendingProductReferenceOpen = false;

            return true;
        }

        if (productsReferenceLink) {
            productsReferenceLink.addEventListener('click', function () {
                pendingProductReferenceOpen = false;
            });
        }

        if (productsSectionReferenceButton) {
            productsSectionReferenceButton.addEventListener('click', function (event) {
                event.preventDefault();
                pendingProductReferenceOpen = true;
                openProductReference();
            });
        }

        function loanAmountInput(name) {
            return document.querySelector('[name="' + name + '"]');
        }

        function loanAmountValue(name) {
            var input = loanAmountInput(name);
            var value = input ? Number(input.value || 0) : 0;

            return Number.isFinite(value) ? value : 0;
        }

        function recalculateLoanAmounts() {
            var principal = Math.max(0, loanAmountValue('principal_amount'));
            var interest = Math.max(0, loanAmountValue('interest_amount'));
            var penalty = Math.max(0, loanAmountValue('penalty_amount'));
            var discount = Math.max(0, loanAmountValue('discount_amount'));
            var paid = Math.max(0, loanAmountValue('paid_amount'));
            var total = Math.max(0, principal + interest + penalty - discount);
            var balance = Math.max(0, total - paid);
            var totalInput = loanAmountInput('total_amount');
            var balanceInput = loanAmountInput('balance_amount');

            if (totalInput) {
                totalInput.value = formatMoney(total);
            }
            if (balanceInput) {
                balanceInput.value = formatMoney(balance);
            }
            var financedInput = loanAmountInput('financed_amount');
            if (financedInput) {
                financedInput.value = formatMoney(principal);
            }

            var metricValues = document.querySelectorAll('.lm-edit-metric__value');
            var metricSubs = document.querySelectorAll('.lm-edit-metric__sub');
            if (metricValues[0]) {
                metricValues[0].textContent = formatMoney(principal);
            }
            if (metricSubs[0]) {
                metricSubs[0].textContent = 'Balance ' + formatMoney(balance);
            }
            if (metricSubs[1]) {
                metricSubs[1].textContent = 'Paid ' + formatMoney(paid);
            }
        }

        function currentProductTotalBeforeDeposit() {
            var stored = productTotalBeforeDepositInput ? Number(productTotalBeforeDepositInput.value || 0) : 0;
            if (Number.isFinite(stored) && stored > 0) {
                return stored;
            }

            return Math.max(0, loanAmountValue('principal_amount') + loanAmountValue('down_payment'));
        }

        function refreshProductTotalBeforeDeposit() {
            if (!productTotalBeforeDepositInput) {
                return;
            }

            productTotalBeforeDepositInput.value = formatMoney(
                Math.max(0, loanAmountValue('principal_amount') + loanAmountValue('down_payment'))
            );
        }

        function regeneratePrincipalAfterDeposit() {
            var productTotal = currentProductTotalBeforeDeposit();
            var downPayment = Math.max(0, loanAmountValue('down_payment'));
            var principal = Math.max(0, productTotal - downPayment);
            var principalInput = loanAmountInput('principal_amount');

            if (principalInput) {
                principalInput.value = formatMoney(principal);
            }

            recalculateLoanAmounts();
        }

        function updateLoanDisplay(loan) {
            if (!loan) {
                return;
            }

            [
                'principal_amount',
                'interest_amount',
                'total_amount',
                'paid_amount',
                'balance_amount',
                'down_payment'
            ].forEach(function (field) {
                var input = document.querySelector('[name="' + field + '"]');
                if (input && Object.prototype.hasOwnProperty.call(loan, field)) {
                    input.value = formatMoney(loan[field]);
                }
            });

            var installmentInput = document.querySelector('[name="installment_count"]');
            if (installmentInput && Object.prototype.hasOwnProperty.call(loan, 'installment_count')) {
                installmentInput.value = loan.installment_count || '';
            }

            if (durationMonthsInput && Object.prototype.hasOwnProperty.call(loan, 'duration_months')) {
                durationMonthsInput.value = loan.duration_months || '';
            }

            recalculateLoanAmounts();

            var metricValues = document.querySelectorAll('.lm-edit-metric__value');
            var metricSubs = document.querySelectorAll('.lm-edit-metric__sub');
            if (metricValues[0]) {
                metricValues[0].textContent = formatMoney(loan.principal_amount);
            }
            if (metricValues[2]) {
                metricValues[2].textContent = loan.installment_count || 0;
            }
            if (metricSubs[0]) {
                metricSubs[0].textContent = 'Balance ' + formatMoney(loan.balance_amount);
            }
            if (metricSubs[1]) {
                metricSubs[1].textContent = 'Paid ' + formatMoney(loan.paid_amount);
            }
            if (metricSubs[2]) {
                metricSubs[2].textContent = 'Frequency ' + String(loan.payment_frequency || 'monthly').replace(/^./, function (letter) {
                    return letter.toUpperCase();
                });
            }
        }

        function syncDurationMonths() {
            if (durationMonthsInput && installmentCountInput) {
                durationMonthsInput.value = installmentCountInput.value || '';
            }
        }

        if (viewErrorLink && errorDetailsBox) {
            viewErrorLink.addEventListener('click', function (event) {
                event.preventDefault();

                var isHidden = errorDetailsBox.style.display === 'none';
                errorDetailsBox.style.display = isHidden ? 'block' : 'none';
                viewErrorLink.textContent = isHidden ? 'Hide error details' : 'View error details';
            });
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('.lm-collapse-toggle');
            if (!button) {
                return;
            }

            event.preventDefault();
            var box = button.closest('.lm-collapsible');
            if (!box) {
                return;
            }

            box.classList.toggle('is-collapsed');
        });

        function syncLocationFields(clearWhenEmpty) {
            if (!select) {
                return;
            }

            var option = select.options[select.selectedIndex];
            var hasValue = option && option.value !== '';

            if (!hasValue && !clearWhenEmpty) {
                return;
            }

            if (nameInput) {
                nameInput.value = hasValue ? (option.getAttribute('data-name') || '') : '';
            }

            if (idDisplay) {
                idDisplay.value = hasValue ? option.value : '';
            }

            if (mainLocationInput) {
                mainLocationInput.value = hasValue
                    ? (option.getAttribute('data-main-location-id') || '')
                    : '';
            }

            if (locationAddressText) {
                locationAddressText.textContent = hasValue
                    ? (option.getAttribute('data-address') || '-')
                    : '-';
            }
        }

        if (select) {
            select.addEventListener('change', function () {
                syncLocationFields(true);
            });
            syncLocationFields(false);
        }

        if (installmentCountInput) {
            installmentCountInput.addEventListener('input', syncDurationMonths);
            installmentCountInput.addEventListener('change', syncDurationMonths);
            syncDurationMonths();
        }

        [
            'principal_amount',
            'interest_amount',
            'paid_amount',
            'penalty_amount',
            'discount_amount',
            'down_payment'
        ].forEach(function (field) {
            var input = loanAmountInput(field);
            if (!input) {
                return;
            }
            input.addEventListener('input', function () {
                if (field === 'down_payment') {
                    regeneratePrincipalAfterDeposit();
                    return;
                }
                if (field === 'principal_amount') {
                    refreshProductTotalBeforeDeposit();
                }
                recalculateLoanAmounts();
            });
            input.addEventListener('change', function () {
                if (field === 'down_payment') {
                    regeneratePrincipalAfterDeposit();
                    return;
                }
                if (field === 'principal_amount') {
                    refreshProductTotalBeforeDeposit();
                }
                recalculateLoanAmounts();
            });
        });

        if (regeneratePrincipalButton) {
            regeneratePrincipalButton.addEventListener('click', function (event) {
                event.preventDefault();
                regeneratePrincipalAfterDeposit();
            });
        }

        if (previewButton && previewTable && window.jQuery) {
            window.jQuery(previewButton).on('click', function () {
                syncDurationMonths();

                var form = window.jQuery(previewButton).closest('form');
                var tbody = window.jQuery(previewTable).find('tbody').first();
                var footerCells = window.jQuery(previewTable).find('tfoot tr th');

                window.jQuery(previewButton)
                    .prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin"></i> Generating...');

                window.jQuery.post("{{ route('loan-management.loans.preview-standalone-schedule') }}", form.serialize(), function (res) {
                    var rows = res.data || [];
                    var totalPrincipal = 0;
                    var totalInterest = 0;
                    var totalAmount = 0;
                    var totalBalance = 0;

                    tbody.empty();

                    if (!rows.length) {
                        tbody.append('<tr><td colspan="6" class="text-center text-muted">No preview rows generated.</td></tr>');
                    }

                    rows.forEach(function (row) {
                        totalPrincipal += Number(row.principal || 0);
                        totalInterest += Number(row.interest || 0);
                        totalAmount += Number(row.total || 0);
                        totalBalance += Number(row.balance || 0);

                        tbody.append(
                            '<tr>' +
                                '<td>' + (row.schedule_no || '') + '</td>' +
                                '<td>' + (row.due_date || '') + '</td>' +
                                '<td>' + formatMoney(row.principal) + '</td>' +
                                '<td>' + formatMoney(row.interest) + '</td>' +
                                '<td>' + formatMoney(row.total) + '</td>' +
                                '<td>' + formatMoney(row.balance) + '</td>' +
                            '</tr>'
                        );
                    });

                    footerCells.eq(1).text(formatMoney(totalPrincipal));
                    footerCells.eq(2).text(formatMoney(totalInterest));
                    footerCells.eq(3).text(formatMoney(totalAmount));
                    footerCells.eq(4).text(formatMoney(totalBalance));
                }).fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Failed to preview schedule';

                    window.alert(message);
                }).always(function () {
                    window.jQuery(previewButton)
                        .prop('disabled', false)
                        .html('<i class="fa fa-refresh"></i> Generate Preview');
                });
            });
        }

        if (updateSchedulesButton && window.jQuery) {
            window.jQuery(updateSchedulesButton).on('click', function () {
                syncDurationMonths();

                var button = window.jQuery(updateSchedulesButton);
                var form = button.closest('form');
                var url = button.data('url');

                if (!url) {
                    return;
                }

                button
                    .prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin"></i> Updating...');

                window.jQuery.ajax({
                    url: url,
                    method: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    success: function (res) {
                        if (res.data && res.data.sections_html && sectionsContainer) {
                            sectionsContainer.innerHTML = res.data.sections_html;
                        }
                        if (res.data && res.data.loan) {
                            updateLoanDisplay(res.data.loan);
                        }

                        if (window.toastr) {
                            toastr.success(res.message || 'Payment schedules updated successfully.');
                        } else {
                            window.alert(res.message || 'Payment schedules updated successfully.');
                        }
                    },
                    error: function (xhr) {
                        var message = 'Unable to update payment schedules.';

                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                            message = xhr.responseJSON.errors[firstKey][0] || message;
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                            if (xhr.responseJSON.data && xhr.responseJSON.data.detail) {
                                message += '\n' + xhr.responseJSON.data.detail;
                            }
                        }

                        if (window.toastr) {
                            toastr.error(message);
                        } else {
                            window.alert(message);
                        }
                    },
                    complete: function () {
                        button
                            .prop('disabled', false)
                            .html('<i class="fa fa-calendar-check-o"></i> Update Payment Schedules');
                    }
                });
            });
        }

        if (sectionsContainer && sectionsContainer.getAttribute('data-url') && window.jQuery) {
            window.jQuery.ajax({
                url: sectionsContainer.getAttribute('data-url'),
                dataType: 'html',
                success: function (result) {
                    sectionsContainer.innerHTML = result;
                    if (pendingProductReferenceOpen || window.location.hash === '#lm-section-loan-items') {
                        openProductReference();
                    }
                },
                error: function () {
                    sectionsContainer.innerHTML = '<div class="alert alert-warning" style="margin-bottom:0;">Unable to load related sections right now.</div>';
                }
            });
        }
    })();
</script>
@endsection
