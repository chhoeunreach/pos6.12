@extends('loanmanagement::layouts.app')
@section('title', 'Edit Loan')
@section('content_body')
<section class="content-header"><h1>Edit Loan #{{ $loanRow->id }}</h1></section>
<section class="content">
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header"><h3 class="box-title">Loan Information</h3></div>
            <div class="box-body row">
                <div class="col-md-3"><strong>Loan #:</strong> {{ $loanRow->loan_number ?? $loanRow->id }}</div>
                <div class="col-md-3"><strong>Status:</strong> <span class="label label-info">{{ ucfirst($loanRow->status ?? 'pending') }}</span></div>
                <div class="col-md-3"><strong>Currency:</strong> {{ $loanRow->currency ?? 'USD' }}</div>
                <div class="col-md-3"><strong>Frequency:</strong> {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }}</div>
                <div class="col-md-3"><strong>Customer:</strong> {{ $customerName }}</div>
                <div class="col-md-3"><strong>Phone:</strong> {{ $customerPhone }}</div>
                <div class="col-md-3"><strong>Main Contact ID:</strong> {{ $mainContactId ?? '-' }}</div>
                <div class="col-md-3"><strong>Location ID:</strong> {{ $locationId ?? '-' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-solid">
            <div class="box-header"><h3 class="box-title">Source Sell Snapshot</h3></div>
            <div class="box-body">
                <p><strong>Source Type:</strong> {{ $sourceType ?? '-' }}</p>
                <p><strong>Source Transaction ID:</strong> {{ $sourceTransactionId ?? '-' }}</p>
                <p><strong>Source Invoice:</strong> {{ $sourceInvoice ?? '-' }}</p>
                <p><strong>Sell Final Total:</strong> {{ number_format((float)($sourceFinalTotal ?? 0),2) }}</p>
                <p><strong>Sell Paid:</strong> {{ number_format((float)($sourcePaid ?? 0),2) }}</p>
                <p><strong>Sell Due:</strong> {{ number_format((float)($sourceDue ?? 0),2) }}</p>
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
                <p><strong>Location Name:</strong> {{ $locationName }}</p>
                <p><strong>Location Address:</strong> {{ $locationAddress }}</p>
            </div>
        </div>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header"><h3 class="box-title">Edit Fields</h3></div>
    <div class="box-body">
        <form method="POST" action="{{ route('loan-management.loans.update', $loanRow->id) }}">@csrf
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label>Loan Date</label><input type="date" name="loan_date" class="form-control" value="{{ !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('Y-m-d') : '' }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Principal</label><input type="number" step="0.01" name="principal_amount" class="form-control" value="{{ $loanRow->principal_amount ?? 0 }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Down Payment</label><input type="number" step="0.01" name="down_payment" class="form-control" value="{{ $loanRow->down_payment ?? 0 }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Interest Rate</label><input type="number" step="0.01" name="interest_rate" class="form-control" value="{{ $loanRow->interest_rate ?? 0 }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Duration Months</label><input type="number" name="duration_months" class="form-control" value="{{ $loanRow->duration_months ?? 1 }}"></div></div>
                <div class="col-md-9"><div class="form-group"><label>Note</label><input name="note" class="form-control" value="{{ $loanRow->note ?? '' }}"></div></div>
            </div>
            <button class="btn btn-primary">Save</button>
            <a href="{{ route('loan-management.loans.view', $loanRow->id) }}" class="btn btn-default">Cancel</a>
        </form>
    </div>
</div>
</section>
@endsection
