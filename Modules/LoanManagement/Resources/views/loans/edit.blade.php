@extends('loanmanagement::layouts.app')
@section('title', 'Edit Loan')

@section('loan_css')
@if(request()->boolean('_lm_modal'))
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
@php
    $backCustomerId = request('customer_id') ?: ($loanRow->customer_id ?? null);
    $loanStatuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted', 'closed'];
    $paymentFrequencies = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
    $interestTypes = ['flat', 'reducing'];
    $collectionStatuses = ['new', 'active', 'follow_up', 'ptp', 'overdue', 'escalated', 'recovery', 'closed'];
    $riskLevels = ['low', 'medium', 'high', 'critical'];
    $ptpStatuses = ['open', 'kept', 'broken', 'cancelled'];
    $skipLevels = ['none', 'soft', 'medium', 'hard'];
@endphp

<section class="content-header">
    <h1><i class="fa fa-pencil-square-o"></i> Edit Loan</h1>
    <p class="text-muted" style="margin: 6px 0 0 30px;">
        Loan #{{ $loanRow->loan_number ?? $loanRow->id }}
    </p>
</section>

<section class="content">
    @if ($errors->any())
        @php
            $fullSaveError = $errors->first('save_error');
        @endphp
        <div class="alert alert-danger">
            <strong>Unable to save this loan.</strong>
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

    <div class="row">
        <div class="col-md-3">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>{{ number_format((float) ($loanRow->principal_amount ?? 0), 2) }}</h3>
                    <p>Principal</p>
                </div>
                <div class="icon"><i class="fa fa-money"></i></div>
                <span class="small-box-footer">Balance {{ number_format((float) ($loanRow->balance_amount ?? 0), 2) }}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>{{ $paymentsCount ?? 0 }}</h3>
                    <p>Payments</p>
                </div>
                <div class="icon"><i class="fa fa-credit-card"></i></div>
                <span class="small-box-footer">Paid {{ number_format((float) ($loanRow->paid_amount ?? 0), 2) }}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>{{ $schedulesCount ?? 0 }}</h3>
                    <p>Schedules</p>
                </div>
                <div class="icon"><i class="fa fa-calendar"></i></div>
                <span class="small-box-footer">Frequency {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3>{{ $loanItemsCount ?? 0 }}</h3>
                    <p>Loan Items</p>
                </div>
                <div class="icon"><i class="fa fa-cubes"></i></div>
                <span class="small-box-footer">Status {{ ucfirst($loanRow->status ?? 'draft') }}</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('loan-management.loans.update', $loanRow->id) }}">
        @csrf
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Loan Overview</h3>
                    <div class="box-tools pull-right">
                        @if(!empty($backCustomerId))
                            <a href="{{ route('loan-management.customers.edit', $backCustomerId) }}" class="btn btn-default btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to Customer
                            </a>
                        @endif
                        <a href="{{ route('loan-management.loans.view', $loanRow->id) }}" class="btn btn-default btn-sm">
                            <i class="fa fa-eye"></i> View Loan
                        </a>
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
                            <input type="text" name="customer_name_snapshot" class="form-control" value="{{ old('customer_name_snapshot', $customerName) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="customer_phone_snapshot" class="form-control" value="{{ old('customer_phone_snapshot', $customerPhone) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer ID</label>
                            <input type="number" min="0" name="customer_id" class="form-control" value="{{ old('customer_id', $loanRow->customer_id ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Main Contact ID</label>
                            <input type="number" min="0" name="main_contact_id" class="form-control" value="{{ old('main_contact_id', $mainContactId ?? '') }}">
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
                            <input type="hidden" name="business_location_name_snapshot" id="loanBusinessLocationName" value="{{ old('business_location_name_snapshot', $locationName) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Main Location ID</label>
                            <input type="number" min="0" name="main_location_id" id="loanMainLocationIdInput" class="form-control" value="{{ old('main_location_id', $locationId ?? $loanRow->main_location_id ?? '') }}">
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
                            <textarea name="customer_address_snapshot" class="form-control" rows="2">{{ old('customer_address_snapshot', $customerAddress) }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-12" style="margin-top: -8px;">
                        <p><strong>Location Address:</strong> <span id="loanLocationAddressText">{{ $locationAddress }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Source Sell Snapshot</h3></div>
                <div class="box-body">
                    <p><strong>Source Type:</strong> {{ $sourceType ?? '-' }}</p>
                    <p><strong>Source Transaction ID:</strong> {{ $sourceTransactionId ?? '-' }}</p>
                    <p><strong>Source Invoice:</strong> {{ $sourceInvoice ?? '-' }}</p>
                    <p><strong>Sell Final Total:</strong> {{ number_format((float) ($sourceFinalTotal ?? 0), 2) }}</p>
                    <p><strong>Sell Paid:</strong> {{ number_format((float) ($sourcePaid ?? 0), 2) }}</p>
                    <p><strong>Sell Due:</strong> {{ number_format((float) ($sourceDue ?? 0), 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Stored Snapshot</h3></div>
                <div class="box-body">
                    <p><strong>Customer Snapshot:</strong> {{ $loanRow->customer_name_snapshot ?? '-' }}</p>
                    <p><strong>Phone Snapshot:</strong> {{ $loanRow->customer_phone_snapshot ?? '-' }}</p>
                    <p><strong>Product Snapshot:</strong> {{ $loanRow->product_name_snapshot ?? '-' }}</p>
                    <p><strong>IMEI Snapshot:</strong> {{ $loanRow->imei_snapshot ?? '-' }}</p>
                    <p><strong>Invoice Snapshot:</strong> {{ $loanRow->invoice_number_snapshot ?? '-' }}</p>
                </div>
            </div>
        </div>
    </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Core Loan Fields</h3></div>
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
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Currency</label>
                            <input type="text" name="currency" class="form-control" value="{{ old('currency', $loanRow->currency ?? 'USD') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Installment Count</label>
                            <input type="number" name="installment_count" class="form-control" min="0" value="{{ old('installment_count', $loanRow->installment_count ?? 0) }}">
                            <input type="hidden" name="duration_months" id="loanDurationMonthsInput" value="{{ old('duration_months', $loanRow->duration_months ?? $loanRow->installment_count ?? 0) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Interest Rate (%)</label>
                            <input type="number" step="0.01" min="0" name="interest_rate" class="form-control" value="{{ old('interest_rate', $displayInterestRate ?? 0) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Interest Type</label>
                            <select name="interest_type" class="form-control">
                                <option value="">Select</option>
                                @foreach($interestTypes as $interestType)
                                    <option value="{{ $interestType }}" {{ old('interest_type', $loanRow->interest_type ?? '') === $interestType ? 'selected' : '' }}>{{ ucfirst($interestType) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loan Date</label>
                            <input type="date" name="loan_date" class="form-control" value="{{ old('loan_date', !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>First Due Date</label>
                            <input type="date" name="first_due_date" class="form-control" value="{{ old('first_due_date', !empty($loanRow->first_due_date) ? \Carbon\Carbon::parse($loanRow->first_due_date)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Maturity Date</label>
                            <input type="date" name="maturity_date" class="form-control" value="{{ old('maturity_date', !empty($loanRow->maturity_date) ? \Carbon\Carbon::parse($loanRow->maturity_date)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Approved At</label>
                            <input type="date" name="approved_at" class="form-control" value="{{ old('approved_at', !empty($loanRow->approved_at) ? \Carbon\Carbon::parse($loanRow->approved_at)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="button" class="btn btn-info" id="btnGenerateLoanPreview">
                            <i class="fa fa-refresh"></i> Generate Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title">Schedule Preview</h3></div>
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

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Amounts</h3></div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-2"><div class="form-group"><label>Principal</label><input type="number" step="0.01" min="0" name="principal_amount" class="form-control" value="{{ old('principal_amount', $loanRow->principal_amount ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Interest</label><input type="number" step="0.01" min="0" name="interest_amount" class="form-control" value="{{ old('interest_amount', $loanRow->interest_amount ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Total</label><input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="{{ old('total_amount', $loanRow->total_amount ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Paid</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="{{ old('paid_amount', $loanRow->paid_amount ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Balance</label><input type="number" step="0.01" min="0" name="balance_amount" class="form-control" value="{{ old('balance_amount', $loanRow->balance_amount ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Down Payment</label><input type="number" step="0.01" min="0" name="down_payment" class="form-control" value="{{ old('down_payment', $loanRow->down_payment ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Penalty</label><input type="number" step="0.01" min="0" name="penalty_amount" class="form-control" value="{{ old('penalty_amount', $loanRow->penalty_amount ?? 0) }}"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Discount</label><input type="number" step="0.01" min="0" name="discount_amount" class="form-control" value="{{ old('discount_amount', $loanRow->discount_amount ?? 0) }}"></div></div>
                    <div class="col-md-8"><div class="form-group"><label>Source Invoice No</label><input type="text" name="source_invoice_no" class="form-control" value="{{ old('source_invoice_no', $loanRow->source_invoice_no ?? '') }}"></div></div>
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Source & Collection Workflow</h3></div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Type</label>
                            <input type="text" name="source_type" class="form-control" value="{{ old('source_type', $loanRow->source_type ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Created At</label>
                            <input type="date" name="source_created_at" class="form-control" value="{{ old('source_created_at', !empty($loanRow->source_created_at) ? \Carbon\Carbon::parse($loanRow->source_created_at)->format('Y-m-d') : '') }}">
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
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Collection Priority</label><input type="number" min="0" name="collection_priority" class="form-control" value="{{ old('collection_priority', $loanRow->collection_priority ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Date</label><input type="date" name="ptp_date" class="form-control" value="{{ old('ptp_date', !empty($loanRow->ptp_date) ? \Carbon\Carbon::parse($loanRow->ptp_date)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Amount</label><input type="number" step="0.01" min="0" name="ptp_amount" class="form-control" value="{{ old('ptp_amount', $loanRow->ptp_amount ?? 0) }}"></div></div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>PTP Status</label>
                            <select name="ptp_status" class="form-control">
                                <option value="">Select</option>
                                @foreach($ptpStatuses as $status)
                                    <option value="{{ $status }}" {{ old('ptp_status', $loanRow->ptp_status ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Broken PTP Count</label><input type="number" min="0" name="broken_ptp_count" class="form-control" value="{{ old('broken_ptp_count', $loanRow->broken_ptp_count ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Contact At</label><input type="date" name="last_contact_at" class="form-control" value="{{ old('last_contact_at', !empty($loanRow->last_contact_at) ? \Carbon\Carbon::parse($loanRow->last_contact_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Next Followup At</label><input type="date" name="next_followup_at" class="form-control" value="{{ old('next_followup_at', !empty($loanRow->next_followup_at) ? \Carbon\Carbon::parse($loanRow->next_followup_at)->format('Y-m-d') : '') }}"></div></div>
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
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Legal Stage</label><input type="text" name="legal_stage" class="form-control" value="{{ old('legal_stage', $loanRow->legal_stage ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Stage</label><input type="text" name="recovery_stage" class="form-control" value="{{ old('recovery_stage', $loanRow->recovery_stage ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Repossession Status</label><input type="text" name="repossession_status" class="form-control" value="{{ old('repossession_status', $loanRow->repossession_status ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Assigned Collection Team</label><input type="text" name="assigned_collection_team" class="form-control" value="{{ old('assigned_collection_team', $loanRow->assigned_collection_team ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Days Past Due</label><input type="number" min="0" name="days_past_due" class="form-control" value="{{ old('days_past_due', $loanRow->days_past_due ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Overdue Bucket</label><input type="text" name="overdue_bucket" class="form-control" value="{{ old('overdue_bucket', $loanRow->overdue_bucket ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Contact Attempt Count</label><input type="number" min="0" name="contact_attempt_count" class="form-control" value="{{ old('contact_attempt_count', $loanRow->contact_attempt_count ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Score</label><input type="number" min="0" name="recovery_score" class="form-control" value="{{ old('recovery_score', $loanRow->recovery_score ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Date</label><input type="date" name="last_payment_date" class="form-control" value="{{ old('last_payment_date', !empty($loanRow->last_payment_date) ? \Carbon\Carbon::parse($loanRow->last_payment_date)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Amount</label><input type="number" step="0.01" min="0" name="last_payment_amount" class="form-control" value="{{ old('last_payment_amount', $loanRow->last_payment_amount ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Blacklisted At</label><input type="date" name="blacklisted_at" class="form-control" value="{{ old('blacklisted_at', !empty($loanRow->blacklisted_at) ? \Carbon\Carbon::parse($loanRow->blacklisted_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Written Off At</label><input type="date" name="written_off_at" class="form-control" value="{{ old('written_off_at', !empty($loanRow->written_off_at) ? \Carbon\Carbon::parse($loanRow->written_off_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Last Contact Result</label><input type="text" name="last_contact_result" class="form-control" value="{{ old('last_contact_result', $loanRow->last_contact_result ?? '') }}"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>PTP Note</label><textarea name="ptp_note" class="form-control" rows="2">{{ old('ptp_note', $loanRow->ptp_note ?? '') }}</textarea></div></div>
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Note</h3></div>
            <div class="box-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="note" class="form-control" rows="4">{{ old('note', $loanRow->note ?? '') }}</textarea>
                </div>
            </div>
            <div class="box-footer text-right">
                @if(!empty($backCustomerId))
                    <a href="{{ route('loan-management.customers.edit', $backCustomerId) }}" class="btn btn-default">Back to Customer</a>
                @else
                    <a href="{{ route('loan-management.loans.view', $loanRow->id) }}" class="btn btn-default">Cancel</a>
                @endif
                <button class="btn btn-primary"><i class="fa fa-save"></i> Save Loan</button>
            </div>
        </div>
    </form>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Related Loan Data</h3></div>
        <div class="box-body">
            <p class="text-muted">Large loan tables are loaded after the page opens so the edit form can appear faster.</p>
            <div id="loanEditSections" data-url="{{ route('loan-management.loans.sections.edit', ['loan' => $loanRow->id] + (!empty($backCustomerId) ? ['customer_id' => $backCustomerId] : [])) }}">
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
        var previewTable = document.getElementById('loanSchedulePreviewTable');
        var viewErrorLink = document.getElementById('loanViewErrorLink');
        var errorDetailsBox = document.getElementById('loanErrorDetailsBox');

        function formatMoney(value) {
            var amount = Number(value || 0);

            return amount.toFixed(2);
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

        function syncLocationFields() {
            if (!select) {
                return;
            }

            var option = select.options[select.selectedIndex];
            var hasValue = option && option.value !== '';

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
            select.addEventListener('change', syncLocationFields);
            syncLocationFields();
        }

        if (installmentCountInput) {
            installmentCountInput.addEventListener('input', syncDurationMonths);
            installmentCountInput.addEventListener('change', syncDurationMonths);
            syncDurationMonths();
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

                window.jQuery.post("{{ route('loan-management.loans.preview-schedule') }}", form.serialize(), function (res) {
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
    })();
</script>
@endsection
