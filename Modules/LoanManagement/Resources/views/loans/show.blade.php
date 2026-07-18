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
@endphp
@extends('loanmanagement::layouts.app')
@section('title', 'Loan Detail')
@section('loan_css')
@if($isEmbeddedModal)
<style>
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
    }
</style>
@endif
@endsection
@section('content_body')
<section class="content-header" style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
    <div style="display:flex; align-items:center; gap:8px;">
        @if($isEmbeddedModal)
        <button type="button"
                class="btn btn-default btn-sm"
                onclick="window.jQuery('.view_modal').modal('hide');"
                title="Close">
            <i class="fa fa-times"></i>
        </button>
        @endif
        <h1 style="margin:0;">Loan Detail #{{ $loanRow->id }}</h1>
    </div>
    <div class="pull-right d-none d-lg-block" style="white-space:nowrap;">
        @can('loan_management.edit')
        <button type="button"
                class="btn btn-primary btn-modal"
                data-href="{{ route('loan-management.loans.edit', $loanEditRouteParams) }}"
                data-container=".view_modal">
            <i class="fa fa-pencil"></i> Edit Loan
        </button>
        @endcan
        <button type="button"
                class="btn btn-success btn-modal"
                data-href="{{ route('loan-management.loans.payment.create', $loanRow->id) }}"
                data-container=".view_modal">
            <i class="fa fa-money"></i> Add Payment
        </button>
        <button type="button"
                class="btn btn-default btn-modal"
                data-href="{{ route('loan-management.loans.print-modal', $loanRow->id) }}"
                data-container=".view_modal">
            <i class="fa fa-print"></i> Print Loan
        </button>
        <button type="button"
           data-href="{{ route('loan-management.loans.convert-to-pos', ['loan' => $loanRow->id, 'modal' => 1]) }}"
           data-container=".view_modal"
           class="btn btn-warning btn-modal">
            <i class="fa fa-exchange"></i> Convert to POS
        </button>
    </div>
</section>
<section class="content">

{{-- Mobile Loan Info Card --}}
<div class="lm-mobile-info-card lm-animate-slideUp">
    <div class="lm-mobile-info-card-title"><i class="fa fa-file-text-o" style="color: #3b82f6;"></i> Loan Information</div>
    <div class="lm-mobile-info-grid">
        <div class="lm-mobile-info-item">
            <small>Loan #</small>
            <strong>{{ $loanRow->loan_number ?? $loanRow->id }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Status</small>
            <strong><span class="label label-info" style="font-size: 11px;">{{ ucfirst($loanRow->status ?? 'pending') }}</span></strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Principal</small>
            <strong>{{ number_format((float)($loanRow->principal_amount ?? 0),2) }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Paid</small>
            <strong style="color: #16a34a;">{{ number_format((float)($loanRow->paid_amount ?? 0),2) }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Balance</small>
            <strong style="color: #dc2626;">{{ number_format((float)($loanRow->balance_amount ?? 0),2) }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Customer</small>
            <strong>{{ $customerName }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Phone</small>
            <strong>{{ $customerPhone }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Duration</small>
            <strong>{{ $displayDuration }} mo</strong>
        </div>
    </div>
</div>

{{-- Mobile Customer Card --}}
<div class="lm-mobile-info-card lm-animate-slideUp" style="animation-delay: .1s;">
    <div class="lm-mobile-info-card-title"><i class="fa fa-user" style="color: #8b5cf6;"></i> Customer</div>
    <div class="lm-mobile-info-grid">
        <div class="lm-mobile-info-item">
            <small>Name</small>
            <strong>{{ $customerName }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Phone</small>
            <strong>{{ $customerPhone }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Address</small>
            <strong>{{ $customerAddress ?: '-' }}</strong>
        </div>
        <div class="lm-mobile-info-item">
            <small>Collector</small>
            <strong>{{ $collectorDisplayName ?? '-' }}</strong>
        </div>
    </div>
</div>

<div class="row">
<div class="col-md-12">
<div class="box box-primary">
<div class="box-header"><h3 class="box-title">Loan Information</h3></div>
<div class="box-body row">
<div class="col-md-3"><strong>Loan #:</strong> {{ $loanRow->loan_number ?? $loanRow->id }}</div>
<div class="col-md-3"><strong>Loan Date:</strong> {{ $loanRow->loan_date ?? $loanRow->created_at }}</div>
<div class="col-md-3"><strong>Status:</strong> <span class="label label-info">{{ ucfirst($loanRow->status ?? 'pending') }}</span></div>
<div class="col-md-3"><strong>Currency:</strong> {{ $loanRow->currency ?? 'USD' }}</div>
<div class="col-md-3"><strong>Principal:</strong> {{ number_format((float)($loanRow->principal_amount ?? 0),2) }}</div>
<div class="col-md-3"><strong>Down Payment:</strong> {{ number_format((float)($loanRow->down_payment ?? 0),2) }}</div>
<div class="col-md-3"><strong>Paid Amount:</strong> {{ number_format((float)($loanRow->paid_amount ?? 0),2) }}</div>
<div class="col-md-3"><strong>Balance:</strong> {{ number_format((float)($loanRow->balance_amount ?? 0),2) }}</div>
<div class="col-md-3"><strong>Interest Rate:</strong> {{ rtrim(rtrim(number_format($displayInterestRate, 2, '.', ''), '0'), '.') }}%</div>
<div class="col-md-3"><strong>Interest Amount:</strong> {{ number_format($displayInterestAmount,2) }}</div>
<div class="col-md-3"><strong>Interest Type:</strong> {{ ucfirst($loanRow->interest_type ?? 'flat') }}</div>
<div class="col-md-3"><strong>Duration:</strong> {{ $displayDuration }} months</div>
<div class="col-md-3"><strong>Frequency:</strong> {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</div>
<div class="col-md-3"><strong>Assigned Collector:</strong> {{ $collectorDisplayName ?? '-' }}</div>
<div class="col-md-3"><strong>Created By:</strong> {{ $createdByName ?? '-' }}</div>
<div class="col-md-3"><strong>Customer:</strong> {{ $customerName }}</div>
<div class="col-md-3"><strong>Phone:</strong> {{ $customerPhone }}</div>
<div class="col-md-6"><strong>Note:</strong> {{ $loanRow->note ?? '-' }}</div>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="box box-solid">
<div class="box-header"><h3 class="box-title">Customer / Location Snapshot</h3></div>
<div class="box-body">
<p><strong>Customer Name:</strong> {{ $customerName }}</p>
<p><strong>Customer Phone:</strong> {{ $customerPhone }}</p>
<p><strong>Customer Address:</strong> {{ $customerAddress }}</p>
<p><strong>Main Contact ID:</strong> {{ $mainContactIdDisplay ?? ($loanRow->main_contact_id ?? '-') }}</p>
<p><strong>Location Name:</strong> {{ $locationName }}</p>
<p><strong>Location Address:</strong> {{ $locationAddress }}</p>
<p><strong>Location ID:</strong> {{ $loanRow->main_location_id ?? ($loanRow->business_location_id ?? '-') }}</p>
</div>
</div>
</div>
</div>

<div class="box box-default">
<div class="box-header"><h3 class="box-title">Related Loan Data</h3></div>
<div class="box-body">
    <div class="row" style="margin-bottom: 12px;">
        <div class="col-md-3"><strong>Loan Items:</strong> {{ $loanItemsCount ?? 0 }}</div>
        <div class="col-md-3"><strong>Product Items:</strong> {{ $productItemsCount ?? 0 }}</div>
        <div class="col-md-3"><strong>Schedules:</strong> {{ $scheduleCount ?? 0 }}</div>
        <div class="col-md-3"><strong>Payments:</strong> {{ $paymentsCount ?? 0 }}</div>
    </div>
    <div class="row" style="margin-bottom: 12px;">
        <div class="col-md-3"><strong>Status Logs:</strong> {{ $statusLogsCount ?? 0 }}</div>
        <div class="col-md-3"><strong>Schedule Principal:</strong> {{ number_format((float) ($scheduleSummary['principal_total'] ?? 0), 2) }}</div>
        <div class="col-md-3"><strong>Schedule Interest:</strong> {{ number_format((float) ($scheduleSummary['interest_total'] ?? 0), 2) }}</div>
        <div class="col-md-3"><strong>Schedule Balance:</strong> {{ number_format((float) ($scheduleSummary['balance_total'] ?? 0), 2) }}</div>
    </div>
    <div id="loanShowSections"
         data-url="{{ route('loan-management.loans.sections.show', ['loan' => $loanRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}">
        <div class="text-center text-muted" style="padding: 24px 0;">
            <i class="fa fa-spinner fa-spin"></i> Loading related sections...
        </div>
    </div>
</div>
</div>

</section>

{{-- Mobile Sticky Action Bar --}}
<div class="lm-mobile-action-bar d-lg-none" id="lmLoanActionBar">
    <div class="lm-mab-info">
        <div class="lm-mab-balance">
            <span class="lm-mab-label">Balance</span>
            <span class="lm-mab-amount">{{ number_format((float)($loanRow->balance_amount ?? 0), 2) }}</span>
            <span class="lm-mab-currency">{{ $loanRow->currency ?? 'USD' }}</span>
        </div>
    </div>
    <div class="lm-mab-actions">
        <button type="button"
                class="lm-mab-btn lm-quick-pay-trigger"
                data-url="{{ route('loan-management.loans.payment.quick-pay', $loanRow->id) }}"
                data-loan-id="{{ $loanRow->id }}">
            <i class="fa fa-money"></i> Pay
        </button>
        <button type="button"
                class="lm-mab-btn lm-mab-btn-outline btn-modal"
                data-href="{{ route('loan-management.loans.print-modal', $loanRow->id) }}"
                data-container=".view_modal">
            <i class="fa fa-print"></i> Print
        </button>
        <button type="button"
           data-href="{{ route('loan-management.loans.convert-to-pos', ['loan' => $loanRow->id, 'modal' => 1]) }}"
           data-container=".view_modal"
           class="lm-mab-btn lm-mab-btn-outline btn-modal">
            <i class="fa fa-exchange"></i> POS
        </button>
        @can('loan_management.edit')
        <button type="button"
                class="lm-mab-btn lm-mab-btn-outline btn-modal"
                data-href="{{ route('loan-management.loans.edit', $loanEditRouteParams) }}"
                data-container=".view_modal">
            <i class="fa fa-pencil"></i> Edit
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
                    sectionsContainer.innerHTML = '<div class="alert alert-warning" style="margin-bottom:0;">Unable to load related sections right now.</div>';
                }
            });
        }

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
