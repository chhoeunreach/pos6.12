@extends('loanmanagement::layouts.app')
@section('title', 'Loan Detail')
@section('content_body')
@php
    $customerName = $customerDisplayName ?? ($loanRow->customer_name_snapshot ?? ($customerRow->name ?? ($customerRow->full_name ?? '-')));
    $customerPhone = $customerPhoneDisplay ?? ($loanRow->customer_phone_snapshot ?? ($customerRow->phone ?? ($customerRow->mobile ?? '-')));
    $customerAddress = $customerAddressDisplay ?? ($loanRow->customer_address_snapshot ?? ($customerRow->address ?? '-'));
    $locationName = $locationDisplayName ?? ($loanRow->location_name_snapshot ?? ($locationRow->name ?? '-'));
    $locationAddress = $locationAddressDisplay ?? ($locationRow->address ?? '-');
    $sourceInvoice = $sourceInvoiceDisplay ?? ($loanRow->source_invoice_no ?? '-');
@endphp
<section class="content-header">
    <h1>Loan Detail #{{ $loanRow->id }}</h1>
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
</section>
<section class="content">

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
<div class="col-md-3"><strong>Interest Rate:</strong> {{ $loanRow->interest_rate ?? 0 }}%</div>
<div class="col-md-3"><strong>Interest Type:</strong> {{ ucfirst($loanRow->interest_type ?? 'flat') }}</div>
<div class="col-md-3"><strong>Duration:</strong> {{ $loanRow->duration_months ?? 0 }} months</div>
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
<div class="box-header"><h3 class="box-title">Source Sell Snapshot</h3></div>
<div class="box-body">
<p><strong>Source Type:</strong> {{ $sourceTypeDisplay ?? '-' }}</p>
<p><strong>Source Transaction ID:</strong> {{ $sourceTransactionIdDisplay ?? '-' }}</p>
<p><strong>Source Invoice:</strong> {{ $sourceInvoice }}</p>
<p><strong>Sell Final Total:</strong> {{ number_format((float)($sourceFinalTotalDisplay ?? 0),2) }}</p>
<p><strong>Sell Paid:</strong> {{ number_format((float)($sourcePaidDisplay ?? 0),2) }}</p>
<p><strong>Sell Due:</strong> {{ number_format((float)($sourceDueDisplay ?? 0),2) }}</p>
<p><strong>Stock Already Deducted:</strong> {{ (isset($loanRow->stock_already_deducted) && (int)$loanRow->stock_already_deducted === 1) ? 'Yes' : 'No' }}</p>
</div>
</div>
</div>
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

<div class="box box-solid">
<div class="box-header"><h3 class="box-title">Loan Items Snapshot</h3></div>
<div class="box-body table-responsive">
<table class="table table-bordered">
<thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>IMEI</th><th>Serial</th><th>Lot</th></tr></thead>
<tbody>
@forelse($items as $i)
<tr>
<td>{{ $i->product_name_snapshot ?? '-' }}</td>
<td>{{ $i->sku_snapshot ?? '-' }}</td>
<td>{{ $i->qty ?? 0 }}</td>
<td>{{ number_format((float)($i->unit_price ?? 0),2) }}</td>
<td>{{ number_format((float)($i->total_price ?? 0),2) }}</td>
<td>{{ $i->imei_snapshot ?? '-' }}</td>
<td>{{ $i->serial_number_snapshot ?? '-' }}</td>
<td>{{ $i->lot_number_snapshot ?? '-' }}</td>
</tr>
@empty
<tr><td colspan="8" class="text-center">No loan items</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

<div class="box box-solid">
<div class="box-header"><h3 class="box-title">Loan Product Items Snapshot</h3></div>
<div class="box-body table-responsive">
<table class="table table-bordered">
<thead><tr><th>Product ID</th><th>Variation ID</th><th>IMEI</th><th>Serial</th><th>Location Snapshot</th><th>Unit Price</th><th>Total Price</th></tr></thead>
<tbody>
@forelse($productItems as $pi)
<tr>
<td>{{ $pi->main_product_id ?? '-' }}</td>
<td>{{ $pi->main_variation_id ?? '-' }}</td>
<td>{{ $pi->imei_no ?? '-' }}</td>
<td>{{ $pi->serial_no ?? '-' }}</td>
<td>{{ $pi->location_name_snapshot ?? '-' }}</td>
<td>{{ number_format((float)($pi->unit_price ?? 0),2) }}</td>
<td>{{ number_format((float)($pi->total_price ?? 0),2) }}</td>
</tr>
@empty
<tr><td colspan="7" class="text-center">No product item snapshots</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

<div class="box box-info">
<div class="box-header"><h3 class="box-title">Payment Schedule</h3></div>
<div class="box-body table-responsive">
<table class="table table-bordered">
<thead><tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Schedule Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
@forelse($schedules as $s)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $s->due_date ?? '-' }}</td>
<td>{{ number_format((float)($s->principal_amount ?? $s->principal_due ?? 0),2) }}</td>
<td>{{ number_format((float)($s->interest_amount ?? $s->interest_due ?? 0),2) }}</td>
<td>{{ number_format((float)($s->schedule_amount ?? $s->amount_due ?? 0),2) }}</td>
<td>{{ number_format((float)($s->paid_amount ?? $s->amount_paid ?? 0),2) }}</td>
<td>{{ number_format((float)($s->balance_amount ?? $s->amount_balance ?? 0),2) }}</td>
<td>{{ $s->status ?? '-' }}</td>
<td>
    @if(! in_array($s->status ?? '', ['paid', 'completed'], true))
        <button type="button"
                class="btn btn-xs btn-success btn-modal"
                data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loanRow->id, 'schedule_id' => $s->id]) }}"
                data-container=".view_modal">
            <i class="fa fa-money"></i> Pay
        </button>
    @endif
</td>
</tr>
@empty
<tr><td colspan="9" class="text-center">No schedules</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

<div class="box box-success">
<div class="box-header"><h3 class="box-title">Payments</h3></div>
<div class="box-body table-responsive">
<table class="table table-bordered">
<thead><tr><th>Receipt #</th><th>Paid Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Received By</th></tr></thead>
<tbody>
@forelse($payments as $p)
<tr>
<td>{{ $p->receipt_number ?? '-' }}</td>
<td>{{ $p->paid_date ?? '-' }}</td>
<td>{{ number_format((float)($p->total_paid_base ?? 0),2) }}</td>
<td>{{ $p->payment_method_snapshot ?? '-' }}</td>
<td>{{ $p->status ?? '-' }}</td>
<td>{{ $p->received_by_name_snapshot ?? '-' }}</td>
</tr>
@empty
<tr><td colspan="6" class="text-center">No payments</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

<div class="box box-warning">
<div class="box-header"><h3 class="box-title">Status Logs</h3></div>
<div class="box-body table-responsive">
<table class="table table-bordered">
<thead><tr><th>Date</th><th>Status</th><th>Changed By</th><th>Note</th></tr></thead>
<tbody>
@forelse($statusLogs as $l)
<tr>
<td>{{ $l->created_at ?? '-' }}</td>
<td>{{ $l->status ?? '-' }}</td>
<td>{{ $l->changed_by ?? '-' }}</td>
<td>{{ $l->note ?? '-' }}</td>
</tr>
@empty
<tr><td colspan="4" class="text-center">No status logs</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

</section>
@endsection
