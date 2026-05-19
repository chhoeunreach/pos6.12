@extends('loanmanagement::layouts.app')
@section('title', 'Edit Payment')

@section('content_body')
<section class="content-header">
    <h1>Edit Payment</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $payment->receipt_number ?? $payment->payment_ref_no ?? ('Payment #'.$payment->id) }}</h3>
            <div class="box-tools pull-right">
                <a href="{{ route('loan-management.payments.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <form method="POST" action="{{ route('loan-management.payments.update', $payment->id) }}">
            @csrf
            @method('PUT')
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loan #</label>
                            <input type="text" class="form-control" value="{{ $loan->loan_number ?? $payment->loan_number_snapshot ?? $payment->loan_id }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer</label>
                            <input type="text" class="form-control" value="{{ $loan->customer_name_snapshot ?? $payment->customer_name_snapshot ?? '-' }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Paid Date</label>
                            <input type="date" name="paid_date" class="form-control" value="{{ old('paid_date', ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('Y-m-d') : (! empty($payment->paid_at) ? \Carbon\Carbon::parse($payment->paid_at)->format('Y-m-d') : date('Y-m-d'))) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount', (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0)) }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="method" class="form-control">
                                @php $currentMethod = old('method', $payment->method ?? $payment->channel ?? $payment->payment_method_snapshot ?? ''); @endphp
                                @foreach($methods as $key => $label)
                                    <option value="{{ $key }}" {{ $currentMethod == $key || $currentMethod == $label ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Schedule</label>
                            <select name="schedule_id" class="form-control">
                                <option value="">No schedule / down payment</option>
                                @foreach($schedules as $schedule)
                                    <option value="{{ $schedule->id }}" {{ (string) old('schedule_id', $payment->schedule_id ?? '') === (string) $schedule->id ? 'selected' : '' }}>
                                        #{{ $schedule->installment_no ?? $schedule->id }}
                                        @if(! empty($schedule->due_date))
                                            - {{ \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                @foreach(['confirmed' => 'Confirmed', 'paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $key => $label)
                                    <option value="{{ $key }}" {{ old('status', $payment->status ?? 'confirmed') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Reference</label>
                            <input type="text" name="reference_number" class="form-control" value="{{ old('reference_number', $payment->reference_number ?? '') }}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Note</label>
                    <textarea name="note" class="form-control" rows="3">{{ old('note', $payment->note ?? '') }}</textarea>
                </div>
            </div>
            <div class="box-footer text-right">
                <a href="{{ route('loan-management.payments.index') }}" class="btn btn-default">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update Payment</button>
            </div>
        </form>
    </div>
</section>
@endsection
