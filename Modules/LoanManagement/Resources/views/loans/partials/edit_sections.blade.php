<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Loan Items</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>IMEI</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loanItems as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->product_name_snapshot ?? '-' }}</td>
                        <td>{{ $item->sku_snapshot ?? '-' }}</td>
                        <td>{{ $item->imei_snapshot ?? '-' }}</td>
                        <td>{{ $item->qty ?? 0 }}</td>
                        <td>{{ number_format((float) ($item->unit_price ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($item->line_total ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No loan items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Payment Schedules</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Due</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $schedule)
                    @php
                        $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? 0);
                        $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
                        $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $schedule->installment_no ?? $schedule->id }}</td>
                        <td>{{ !empty($schedule->due_date) ? \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') : '-' }}</td>
                        <td>{{ number_format((float) ($schedule->principal_due ?? $schedule->principal_amount ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($schedule->interest_due ?? $schedule->interest_amount ?? 0), 2) }}</td>
                        <td>{{ number_format($due, 2) }}</td>
                        <td>{{ number_format($paid, 2) }}</td>
                        <td>{{ number_format($balance, 2) }}</td>
                        <td>{{ ucfirst($schedule->status ?? 'pending') }}</td>
                        <td>
                            <button type="button"
                                    class="btn btn-xs btn-primary btn-modal"
                                    data-href="{{ route('loan-management.loans.schedules.edit', ['loan' => $loanRow->id, 'schedule' => $schedule->id]) }}"
                                    data-container=".view_modal">
                                <i class="fa fa-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center">No schedules found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Recent Payments</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Receipt</th>
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
                        $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
                        $method = $payment->payment_method_snapshot ?? $payment->channel ?? '-';
                        $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                    @endphp
                    <tr>
                        <td>{{ $payment->id }}</td>
                        <td>{{ $receipt }}</td>
                        <td>{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</td>
                        <td>{{ $method }}</td>
                        <td>{{ number_format($amount, 2) }}</td>
                        <td>{{ ucfirst($payment->status ?? 'confirmed') }}</td>
                        <td>
                            <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id, 'customer_id' => $backCustomerId]) }}" class="btn btn-xs btn-primary">
                                <i class="fa fa-pencil"></i> Edit Payment
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
