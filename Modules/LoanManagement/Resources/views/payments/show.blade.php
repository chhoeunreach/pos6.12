@extends('loanmanagement::layouts.app')
@section('title', 'Payment Detail')

@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? ('Payment #'.$payment->id);
    $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
    $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
    $method = $payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? '-';
@endphp

@if($isEmbeddedModal)
    @section('hide_breadcrumb', '1')
    @section('loan_css')
        @parent
        <style>
            body.loan-management-embedded-modal {
                background: #fff !important;
            }
            body.loan-management-embedded-modal .lm-content,
            body.loan-management-embedded-modal .lm-workspace {
                padding: 0 !important;
                margin: 0 !important;
                background: #fff !important;
            }
            body.loan-management-embedded-modal .content {
                padding: 10px 12px !important;
            }
            body.loan-management-embedded-modal .box {
                margin-bottom: 10px;
                border-radius: 0;
                box-shadow: none;
            }
            .lm-payment-method-full {
                white-space: normal;
                overflow-wrap: anywhere;
                word-break: normal;
            }
        </style>
    @endsection
@endif

@section('content_body')
@unless($isEmbeddedModal)
    <section class="content-header">
        <h1>Payment Detail</h1>
    </section>
@endunless

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $receipt }}</h3>
            <div class="box-tools">
                @unless($isEmbeddedModal)
                    <a href="{{ route('loan-management.payments.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                @endunless
                @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.payment|loan_management.payments.create|loan_management.edit'))
                    <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> Edit Payment</a>
                @endif
            </div>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-3"><strong>Receipt #:</strong><br>{{ $receipt }}</div>
                <div class="col-md-3"><strong>Paid Date:</strong><br>{{ ! empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</div>
                <div class="col-md-3"><strong>Amount:</strong><br>$ {{ number_format($amount, 2) }}</div>
                <div class="col-md-3"><strong>Status:</strong><br>{{ ucfirst($payment->status ?? '-') }}</div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4 lm-payment-method-full"><strong>Method:</strong><br>{{ $method }}</div>
                <div class="col-md-2"><strong>Type:</strong><br>{{ \Modules\LoanManagement\Http\Controllers\LoanPaymentController::paymentTypeLabel($payment->payment_type ?? 'monthly') }}</div>
                <div class="col-md-3"><strong>Reference:</strong><br>{{ $payment->reference_number ?? '-' }}</div>
                <div class="col-md-3"><strong>Received By:</strong><br>{{ $payment->received_by_name_snapshot ?? $payment->collected_by_name_snapshot ?? '-' }}</div>
            </div>
            @if(! empty($payment->note))
                <hr>
                <strong>Note:</strong>
                <div>{{ $payment->note }}</div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Loan</h3></div>
                <div class="box-body">
                    @if($loan)
                        <p><strong>Loan #:</strong>
                            <a href="{{ route('loan-management.loans.view', $loan->id) }}">{{ $loan->loan_number ?? ('Loan #'.$loan->id) }}</a>
                        </p>
                        <p><strong>Customer:</strong> {{ $loan->customer_name_snapshot ?? '-' }}</p>
                        <p><strong>Phone:</strong> {{ $loan->customer_phone_snapshot ?? '-' }}</p>
                        <p><strong>Balance:</strong> $ {{ number_format((float) ($loan->balance_amount ?? 0), 2) }}</p>
                    @else
                        <p class="text-muted">No loan found.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border"><h3 class="box-title">Schedule</h3></div>
                <div class="box-body">
                    @if($schedule)
                        <p><strong>Installment #:</strong> {{ $schedule->installment_no ?? '-' }}</p>
                        <p><strong>Due Date:</strong> {{ $schedule->due_date ?? '-' }}</p>
                        <p><strong>Schedule Amount:</strong> $ {{ number_format((float) ($schedule->schedule_amount ?? $schedule->amount_due ?? 0), 2) }}</p>
                        <p><strong>Schedule Status:</strong> {{ ucfirst($schedule->status ?? '-') }}</p>
                    @else
                        <p class="text-muted">This payment is not linked to a monthly schedule.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Payment Lines</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th class="text-right">Amount</th>
                        <th>Currency</th>
                        <th>Reference</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($details as $detail)
                        <tr>
                            <td class="lm-payment-method-full">{{ $detail->payment_method_snapshot ?? $detail->method ?? '-' }}</td>
                            <td class="text-right">$ {{ number_format((float) ($detail->amount_base ?? $detail->amount ?? 0), 2) }}</td>
                            <td>{{ $detail->currency ?? '-' }}</td>
                            <td>{{ $detail->reference_number ?? $detail->transaction_no ?? '-' }}</td>
                            <td>{{ $detail->note ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="lm-payment-method-full">{{ $method }}</td>
                            <td class="text-right">$ {{ number_format($amount, 2) }}</td>
                            <td>{{ $payment->currency ?? $payment->base_currency ?? '-' }}</td>
                            <td>{{ $payment->reference_number ?? '-' }}</td>
                            <td>{{ $payment->note ?? '-' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
