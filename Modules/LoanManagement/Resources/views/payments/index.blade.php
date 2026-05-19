@extends('loanmanagement::layouts.app')
@section('title', 'Payments')

@section('content_body')
<section class="content-header">
    <h1>Payments</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Filtered Amount</span>
                    <span class="info-box-number">$ {{ number_format($summary['amount'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-list"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Payments</span>
                    <span class="info-box-number">{{ number_format($summary['count'] ?? 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Filters</h3>
        </div>
        <div class="box-body">
            <form method="GET" action="{{ route('loan-management.payments.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Receipt, reference, loan, customer">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Loan #</label>
                            <input type="text" name="loan_number" class="form-control" value="{{ $filters['loan_number'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Customer</label>
                            <input type="text" name="customer" class="form-control" value="{{ $filters['customer'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Method</label>
                            <select name="method" class="form-control">
                                <option value="">All</option>
                                @foreach($methods as $key => $label)
                                    <option value="{{ $label }}" {{ ($filters['method'] ?? '') == $label || ($filters['method'] ?? '') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Location</label>
                            <select name="location_id" class="form-control">
                                <option value="">All</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}" {{ (string)($filters['location_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                @foreach($statuses as $status => $label)
                                    <option value="{{ $status }}" {{ ($filters['status'] ?? '') == $status ? 'selected' : '' }}>{{ ucfirst($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-6 text-right" style="padding-top:25px;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                        <a href="{{ route('loan-management.payments.index') }}" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">Payment List</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Paid Date</th>
                        <th>Loan #</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Received By</th>
                        <th style="width:145px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->receipt_number ?? ('#'.$payment->id) }}</td>
                            <td>{{ ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('d-m-Y') : '-' }}</td>
                            <td>
                                @if(Route::has('loan-management.loans.view') && ! empty($payment->loan_id))
                                    <a href="{{ route('loan-management.loans.view', $payment->loan_id) }}">{{ $payment->loan_number ?? ('Loan #'.$payment->loan_id) }}</a>
                                @else
                                    {{ $payment->loan_number ?? '-' }}
                                @endif
                            </td>
                            <td>
                                <strong>{{ $payment->customer_name ?? '-' }}</strong><br>
                                <small class="text-muted">{{ $payment->customer_phone ?? '' }}</small>
                            </td>
                            <td>{{ $payment->payment_method ?? '-' }}</td>
                            <td class="text-right">$ {{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                            <td><span class="label label-{{ in_array($payment->status, ['paid', 'confirmed', 'completed']) ? 'success' : 'default' }}">{{ ucfirst($payment->status ?? '-') }}</span></td>
                            <td>{{ $payment->reference_number ?? '-' }}</td>
                            <td>{{ $payment->received_by ?? '-' }}</td>
                            <td>
                                @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.payment|loan_management.payments.create|loan_management.edit'))
                                    <a href="{{ route('loan-management.payments.edit', $payment->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i> Edit</a>
                                    <form method="POST" action="{{ route('loan-management.payments.destroy', $payment->id) }}" style="display:inline;" onsubmit="return confirm('Delete this payment? This will update loan totals.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                @else
                                    <span class="text-muted">View only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted">No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="text-center">
                {{ $payments->links() }}
            </div>
        </div>
    </div>
</section>
@endsection
