@extends('loanmanagement::layouts.app')
@section('title', 'Customer Detail')

@section('content')
<section class="content-header"><h1>Customer Detail</h1></section>
<section class="content">
    <div class="box box-primary"><div class="box-body">
        <div class="row">
            <div class="col-md-4"><strong>Code:</strong> {{ $customerRow->customer_code ?? '-' }}</div>
            <div class="col-md-4"><strong>Name:</strong> {{ $customerRow->name ?? '-' }}</div>
            <div class="col-md-4"><strong>Phone:</strong> {{ $customerRow->phone ?? '-' }}</div>
            <div class="col-md-4"><strong>Status:</strong> {{ $customerRow->status ?? '-' }}</div>
            <div class="col-md-4"><strong>Can Login:</strong> {{ !empty($customerRow->can_login) ? 'Yes' : 'No' }}</div>
            <div class="col-md-4"><strong>GPS Tracking:</strong> {{ !empty($customerRow->allow_gps_tracking) ? 'Enabled' : 'Disabled' }}</div>
        </div>
    </div></div>

    <div class="box box-default"><div class="box-header"><h3 class="box-title">Loans</h3></div><div class="box-body">
        <table class="table table-bordered"><thead><tr><th>ID</th><th>Loan Number</th><th>Status</th><th>Balance</th></tr></thead><tbody>
            @forelse($loans as $l)<tr><td>{{ $l->id }}</td><td>{{ $l->loan_number ?? '-' }}</td><td>{{ $l->status ?? '-' }}</td><td>{{ $l->balance_amount ?? 0 }}</td></tr>@empty<tr><td colspan="4" class="text-center">No loans</td></tr>@endforelse
        </tbody></table>
    </div></div>

    <div class="box box-default"><div class="box-header"><h3 class="box-title">Payments</h3></div><div class="box-body">
        <table class="table table-bordered"><thead><tr><th>ID</th><th>Receipt</th><th>Amount</th><th>Date</th></tr></thead><tbody>
            @forelse($payments as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->receipt_number ?? '-' }}</td><td>{{ $p->total_paid ?? 0 }}</td><td>{{ $p->paid_date ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center">No payments</td></tr>@endforelse
        </tbody></table>
    </div></div>
</section>
@endsection

