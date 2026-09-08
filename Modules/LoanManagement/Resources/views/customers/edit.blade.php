@extends('loanmanagement::layouts.app')
@section('title', 'Edit Installment Customer')

@section('content_body')
<section class="content-header">
    <h1>Edit Installment Customer</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>{{ $loanSummary['count'] ?? 0 }}</h3>
                    <p>Related Installments</p>
                </div>
                <div class="icon"><i class="fa fa-file-text-o"></i></div>
                <span class="small-box-footer">Balance {{ number_format((float) ($loanSummary['balance'] ?? 0), 2) }}</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>{{ $paymentSummary['count'] ?? 0 }}</h3>
                    <p>Related Payments</p>
                </div>
                <div class="icon"><i class="fa fa-money"></i></div>
                <span class="small-box-footer">Collected {{ number_format((float) ($paymentSummary['amount'] ?? 0), 2) }}</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>{{ $customerRow->customer_code ?? '-' }}</h3>
                    <p>{{ $customerRow->phone ?? '-' }}</p>
                </div>
                <div class="icon"><i class="fa fa-user"></i></div>
                <span class="small-box-footer">Status {{ ucfirst($customerRow->status ?? 'active') }}</span>
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Customer Information</h3>
            <div class="box-tools pull-right">
                <a href="{{ route('loan-management.customers.show', $customerRow->id) }}" class="btn btn-default btn-sm">
                    <i class="fa fa-eye"></i> View Detail
                </a>
            </div>
        </div>
        <div class="box-body">
            <form method="POST" action="{{ route('loan-management.customers.update', $customerRow->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="create_mode" id="create_mode" value="new">
                <input type="hidden" name="expected_customer_id" value="{{ $customerRow->id }}">
                <input type="hidden" name="expected_customer_code" value="{{ $customerRow->customer_code ?? '' }}">
                @include('loanmanagement::customers.partials.form')
                <button type="submit" class="btn btn-primary">Update Customer</button>
                <a href="{{ route('loan-management.customers') }}" class="btn btn-default">Back to List</a>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">Security & App Access</h3>
                </div>
                <div class="box-body">
                    <form method="POST" action="{{ route('loan-management.customers.reset-password', $customerRow->id) }}" class="form-inline">
                        @csrf
                        <div class="form-group">
                            <label style="margin-right:8px;">Reset App Password</label>
                            <input type="password" class="form-control" name="new_password" placeholder="New password (min 8)" required>
                        </div>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">GPS Tracking</h3>
                </div>
                <div class="box-body">
                    <form method="POST" action="{{ route('loan-management.customer-tracking.toggle', $customerRow->id) }}" class="form-inline">
                        @csrf
                        <div class="form-group">
                            <label style="margin-right:8px;">GPS Tracking</label>
                            <select class="form-control" name="allow_gps_tracking">
                                <option value="0" {{ !empty($customerRow->allow_gps_tracking) ? '' : 'selected' }}>Disable</option>
                                <option value="1" {{ !empty($customerRow->allow_gps_tracking) ? 'selected' : '' }}>Enable</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-left:8px;">
                            <input type="text" class="form-control" name="note" placeholder="Note">
                        </div>
                        <button type="submit" class="btn btn-info">Save GPS Setting</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Related Installments</h3>
        </div>
        <div class="box-body table-responsive">
            @php $customerEditReturnUrl = route('loan-management.customers.edit', $customerRow->id); @endphp
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Installment #</th>
                        <th>Status</th>
                        <th>Installment Date</th>
                        <th>Principal</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td>{{ $loan->id }}</td>
                            <td>{{ $loan->loan_number ?? '-' }}</td>
                            <td><span class="label label-info">{{ ucfirst($loan->status ?? 'pending') }}</span></td>
                            <td>{{ !empty($loan->loan_date) ? \Carbon\Carbon::parse($loan->loan_date)->format('d-m-Y') : '-' }}</td>
                            <td>{{ number_format((float) ($loan->principal_amount ?? 0), 2) }}</td>
                            <td>{{ number_format((float) ($loan->balance_amount ?? 0), 2) }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('loan-management.loans.view', $loan->id) }}" class="btn btn-xs btn-default">
                                    <i class="fa fa-eye"></i> View
                                </a>
                                <a href="{{ route('loan-management.loans.edit', ['loan' => $loan->id, 'customer_id' => $customerRow->id]) }}" class="btn btn-xs btn-primary">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                <a href="#"
                                   class="btn btn-xs btn-success btn-modal"
                                   data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loan->id, 'return_to' => $customerEditReturnUrl]) }}"
                                   data-container=".view_modal">
                                    <i class="fa fa-money"></i> Add Payment
                                </a>
                                <a href="#"
                                   class="btn btn-xs btn-warning btn-modal"
                                   data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loan->id, 'deposit_payment' => 1, 'return_to' => $customerEditReturnUrl]) }}"
                                   data-container=".view_modal">
                                    <i class="fa fa-plus-circle"></i> Add Deposit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No related loans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Related Payments</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Receipt</th>
                        <th>Installment #</th>
                        <th>Paid Date</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php
                            $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #' . $payment->id);
                            $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                            $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
                            $method = $payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? '-';
                        @endphp
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>{{ $receipt }}</td>
                            <td>{{ $payment->loan_number ?? '-' }}</td>
                            <td>{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $method }}</td>
                            <td>{{ number_format($amount, 2) }}</td>
                            <td>{{ ucfirst($payment->status ?? 'confirmed') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('loan-management.payments.show', $payment->id) }}" class="btn btn-xs btn-default">
                                    <i class="fa fa-eye"></i> View
                                </a>
                                <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id, 'customer_id' => $customerRow->id]) }}" class="btn btn-xs btn-primary">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                <form method="POST"
                                      action="{{ route('loan-management.payments.destroy', ['payment' => $payment->id, 'return_to' => $customerEditReturnUrl]) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Delete this payment? This will update loan balances and schedules.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-danger">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No related payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
