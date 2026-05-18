@extends('loanmanagement::layouts.app')
@section('title', 'Add To Installment')
@section('content_body')
<section class="content-header"><h1>Add to Installment - {{ $sell['header']->invoice_no }}</h1></section>
<section class="content">
<div class="box box-primary"><div class="box-body">
<form method="POST" action="{{ route('loan-management.sell-list.store', $sell['header']->id) }}">@csrf
<div class="row">
<div class="col-md-4"><div class="form-group"><label>Loan Date</label><input type="date" name="loan_date" value="{{ date('Y-m-d') }}" class="form-control"></div></div>
<div class="col-md-4"><div class="form-group"><label>Term Months</label><input type="number" min="1" max="120" name="term_months" value="6" class="form-control"></div></div>
<div class="col-md-4"><div class="form-group"><label>Interest Rate (%)</label><input type="number" step="0.01" min="0" name="interest_rate" value="0" class="form-control"></div></div>
</div>
<div class="row">
<div class="col-md-3"><p><strong>Principal:</strong> {{ number_format($preview['principal_amount'],2) }}</p></div>
<div class="col-md-3"><p><strong>Down Payment:</strong> {{ number_format($preview['down_payment'],2) }}</p></div>
<div class="col-md-3"><p><strong>Balance:</strong> {{ number_format($preview['balance_amount'],2) }}</p></div>
<div class="col-md-3"><p><strong>Customer:</strong> {{ $preview['customer_name'] }}</p></div>
</div>
<div class="form-group"><label>Note</label><textarea name="note" class="form-control" rows="3"></textarea></div>
<button type="submit" class="btn btn-primary">Save Installment</button>
<a href="{{ route('loan-management.sell-list') }}" class="btn btn-default">Cancel</a>
</form>
</div></div>
</section>
@endsection
