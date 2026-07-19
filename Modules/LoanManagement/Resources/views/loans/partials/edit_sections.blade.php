@php
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $editLoanReturnParams = ['loan' => $loanRow->id] + (!empty($backCustomerId) ? ['customer_id' => $backCustomerId] : []) + ($isEmbeddedModal ? ['_lm_modal' => 1] : []);
    $addDepositPaymentUrl = route('loan-management.loans.payment.create', [
        'loan' => $loanRow->id,
        'deposit_payment' => 1,
        'return_to' => route('loan-management.loans.edit', $editLoanReturnParams),
    ] + ($isEmbeddedModal ? ['_lm_modal' => 1] : []));
@endphp

<div class="box box-default lm-collapsible is-collapsed" data-collapse-key="loan-items" id="lm-section-loan-items">
    <div class="box-header with-border">
        <h3 class="box-title">Loan Items (Read-only Reference)</h3>
        <div class="box-tools pull-right">
            <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                <i class="fa fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="box-body">
        <div class="collapse" id="loanProductReference{{ $loanRow->id }}" style="margin-bottom:12px;">
            <div class="lm-edit-snapshot">
                <div class="lm-edit-snapshot__item"><small>Customer</small><strong>{{ $loanRow->customer_name_snapshot ?? '-' }}</strong></div>
                <div class="lm-edit-snapshot__item"><small>Phone</small><strong>{{ $loanRow->customer_phone_snapshot ?? '-' }}</strong></div>
                <div class="lm-edit-snapshot__item"><small>Product</small><strong>{{ $loanRow->product_name_snapshot ?? '-' }}</strong></div>
                <div class="lm-edit-snapshot__item"><small>IMEI</small><strong>{{ $loanRow->imei_snapshot ?? '-' }}</strong></div>
                <div class="lm-edit-snapshot__item"><small>Invoice</small><strong>{{ $loanRow->invoice_number_snapshot ?? $loanRow->source_invoice_no ?? '-' }}</strong></div>
            </div>
        </div>
        <p class="text-muted" style="margin:0;">Items are now editable directly in the form above. This section shows the snapshot reference.</p>
    </div>
</div>

<div class="box box-default lm-collapsible" data-collapse-key="payment-schedules">
    <div class="box-header with-border">
        <h3 class="box-title">Payment Schedules</h3>
        <div class="box-tools pull-right">
            <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                <i class="fa fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="box-body">
        <div class="lm-edit-sections-table">
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
                            $principal = (float) ($schedule->principal_due ?? $schedule->principal_amount ?? $schedule->principal ?? $schedule->installment_value ?? 0);
                            $interest = (float) ($schedule->interest_due ?? $schedule->interest_amount ?? $schedule->interest ?? 0);
                            $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? $schedule->total ?? 0);
                            $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
                            $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? 0);
                            $paidPaymentSummary = trim((string) ($schedule->paid_payment_summary ?? ''));
                            $scheduleStatus = strtolower((string) ($schedule->status ?? 'pending'));
                            if ($scheduleStatus === 'pay off') {
                                $scheduleStatus = 'pay off';
                            } elseif ($paid > 0 && ($balance <= 0 || ($due > 0 && $paid >= $due))) {
                                $scheduleStatus = 'paid';
                            } elseif ($paid > 0 && !in_array($scheduleStatus, ['paid', 'completed'], true)) {
                                $scheduleStatus = 'partial';
                            }
                            $statusClass = match($scheduleStatus) {
                                'paid', 'completed', 'pay off' => 'color:#16a34a;background:#dcfce7;',
                                'partial' => 'color:#d97706;background:#fef3c7;',
                                'late', 'overdue' => 'color:#dc2626;background:#fee2e2;',
                                default => 'color:#64748b;background:#f1f5f9;',
                            };
                        @endphp
                        <tr>
                            <td>{{ $schedule->installment_no ?? $schedule->id }}</td>
                            <td>{{ !empty($schedule->due_date) ? \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') : '-' }}</td>
                            <td>{{ number_format($principal, 2) }}</td>
                            <td>{{ number_format($interest, 2) }}</td>
                            <td>{{ number_format($due, 2) }}</td>
                            <td>
                                <strong style="color:#16a34a;">{{ number_format($paid, 2) }}</strong>
                                @if($paidPaymentSummary !== '')
                                    <div style="margin-top:4px;font-size:11px;line-height:1.35;color:#64748b;white-space:pre-line;">{{ $paidPaymentSummary }}</div>
                                @endif
                            </td>
                            <td>{{ number_format($balance, 2) }}</td>
                            <td>
                                <span class="label" style="{{ $statusClass }}">{{ ucfirst($scheduleStatus) }}</span>
                            </td>
                            <td>
                                <button type="button"
                                        class="btn btn-xs btn-primary lm-btn-modal"
                                        data-href="{{ route('loan-management.loans.schedules.edit', ['loan' => $loanRow->id, 'schedule' => $schedule->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}"
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

        <div class="lm-edit-sections-mobile">
            @forelse($schedules as $schedule)
                @php
                    $principal = (float) ($schedule->principal_due ?? $schedule->principal_amount ?? $schedule->principal ?? $schedule->installment_value ?? 0);
                    $interest = (float) ($schedule->interest_due ?? $schedule->interest_amount ?? $schedule->interest ?? 0);
                    $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? $schedule->total ?? 0);
                    $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
                    $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? 0);
                    $paidPaymentSummary = trim((string) ($schedule->paid_payment_summary ?? ''));
                    $scheduleStatus = strtolower((string) ($schedule->status ?? 'pending'));
                    if ($scheduleStatus === 'pay off') {
                        $scheduleStatus = 'pay off';
                    } elseif ($paid > 0 && ($balance <= 0 || ($due > 0 && $paid >= $due))) {
                        $scheduleStatus = 'paid';
                    } elseif ($paid > 0 && !in_array($scheduleStatus, ['paid', 'completed'], true)) {
                        $scheduleStatus = 'partial';
                    }
                    $statusClass = match($scheduleStatus) {
                        'paid', 'completed', 'pay off' => 'color:#16a34a;background:#dcfce7;',
                        'partial' => 'color:#d97706;background:#fef3c7;',
                        'late', 'overdue' => 'color:#dc2626;background:#fee2e2;',
                        default => 'color:#64748b;background:#f1f5f9;',
                    };
                @endphp
                <div class="lm-edit-section-card">
                    <div class="lm-edit-section-card-header">
                        <span class="lm-edit-section-card-title">
                            #{{ $schedule->installment_no ?? $schedule->id }}
                            @if(!empty($schedule->due_date))
                                <span style="color:#94a3b8;font-weight:400;"> &middot; {{ \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') }}</span>
                            @endif
                        </span>
                        <span class="label" style="{{ $statusClass }}">{{ ucfirst($scheduleStatus) }}</span>
                    </div>
                    <div class="lm-edit-section-card-body">
                        <div class="lm-edit-section-card-item"><small>Principal</small><span>{{ number_format($principal, 2) }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Interest</small><span>{{ number_format($interest, 2) }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Due</small><span style="font-weight:700;">{{ number_format($due, 2) }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Paid</small><span style="color:#16a34a;">{{ number_format($paid, 2) }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Balance</small><span style="color:{{ $balance > 0 ? '#dc2626' : '#16a34a' }};">{{ number_format($balance, 2) }}</span></div>
                    </div>
                    @if($paidPaymentSummary !== '')
                        <div style="margin-top:8px;font-size:12px;line-height:1.45;color:#64748b;white-space:pre-line;">{{ $paidPaymentSummary }}</div>
                    @endif
                    <div class="lm-edit-section-card-actions">
                        <button type="button"
                                class="btn btn-xs btn-primary lm-btn-modal"
                                data-href="{{ route('loan-management.loans.schedules.edit', ['loan' => $loanRow->id, 'schedule' => $schedule->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}"
                                data-container=".view_modal">
                            <i class="fa fa-pencil"></i> Edit Schedule
                        </button>
                    </div>
                </div>
            @empty
                <div class="lm-edit-section-card">
                    <div style="text-align:center;color:#94a3b8;padding:12px 0;">No schedules found.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="box box-default lm-collapsible" data-collapse-key="customer-deposit-payments">
    <div class="box-header with-border">
        <h3 class="box-title">Customer Deposit Payments</h3>
        <div class="box-tools pull-right">
            <button type="button"
                    class="btn btn-xs btn-success lm-btn-modal"
                    data-href="{{ $addDepositPaymentUrl }}"
                    data-container=".view_modal">
                <i class="fa fa-plus"></i> Add Deposit
            </button>
            <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                <i class="fa fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="box-body">
        <div class="lm-edit-sections-table">
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
                    @forelse($depositPayments as $payment)
                        @php
                            $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #' . $payment->id);
                            $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount_base ?? $payment->amount ?? 0);
                            $method = $payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '-';
                            $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                            $paymentStatus = strtolower((string) ($payment->status ?? 'confirmed'));
                            $pStatusClass = match($paymentStatus) {
                                'confirmed', 'completed', 'paid' => 'color:#16a34a;background:#dcfce7;',
                                'pending' => 'color:#d97706;background:#fef3c7;',
                                'cancelled', 'failed' => 'color:#dc2626;background:#fee2e2;',
                                default => 'color:#64748b;background:#f1f5f9;',
                            };
                        @endphp
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>{{ $receipt }}</td>
                            <td>{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $method }}</td>
                            <td>{{ number_format($amount, 2) }}</td>
                            <td>
                                <span class="label" style="{{ $pStatusClass }}">{{ ucfirst($paymentStatus) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id, 'customer_id' => $backCustomerId]) }}" class="btn btn-xs btn-primary">
                                    <i class="fa fa-pencil"></i> Edit Payment
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No customer deposit payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lm-edit-sections-mobile">
            @forelse($depositPayments as $payment)
                @php
                    $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #' . $payment->id);
                    $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount_base ?? $payment->amount ?? 0);
                    $method = $payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '-';
                    $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                    $paymentStatus = strtolower((string) ($payment->status ?? 'confirmed'));
                    $pStatusClass = match($paymentStatus) {
                        'confirmed', 'completed', 'paid' => 'color:#16a34a;background:#dcfce7;',
                        'pending' => 'color:#d97706;background:#fef3c7;',
                        'cancelled', 'failed' => 'color:#dc2626;background:#fee2e2;',
                        default => 'color:#64748b;background:#f1f5f9;',
                    };
                @endphp
                <div class="lm-edit-section-card">
                    <div class="lm-edit-section-card-header">
                        <span class="lm-edit-section-card-title">{{ $receipt }}</span>
                        <span class="label" style="{{ $pStatusClass }}">{{ ucfirst($paymentStatus) }}</span>
                    </div>
                    <div class="lm-edit-section-card-body">
                        <div class="lm-edit-section-card-item"><small>Amount</small><span style="font-weight:700;color:#0f172a;">{{ number_format($amount, 2) }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Method</small><span>{{ $method }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Paid Date</small><span>{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Payment ID</small><span>{{ $payment->id }}</span></div>
                    </div>
                    <div class="lm-edit-section-card-actions">
                        <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id, 'customer_id' => $backCustomerId]) }}" class="btn btn-xs btn-primary">
                            <i class="fa fa-pencil"></i> Edit Payment
                        </a>
                    </div>
                </div>
            @empty
                <div class="lm-edit-section-card">
                    <div style="text-align:center;color:#94a3b8;padding:12px 0;">No customer deposit payments found.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="box box-default lm-collapsible is-collapsed" data-collapse-key="recent-payments">
    <div class="box-header with-border">
        <h3 class="box-title">Recent Payments</h3>
        <div class="box-tools pull-right">
            <button type="button" class="lm-collapse-toggle" title="Collapse or expand section">
                <i class="fa fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="box-body">
        <div class="lm-edit-sections-table">
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
                            $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount_base ?? $payment->amount ?? 0);
                            $method = $payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '-';
                            $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                            $paymentStatus = strtolower((string) ($payment->status ?? 'confirmed'));
                            $pStatusClass = match($paymentStatus) {
                                'confirmed', 'completed' => 'color:#16a34a;background:#dcfce7;',
                                'pending' => 'color:#d97706;background:#fef3c7;',
                                'cancelled', 'failed' => 'color:#dc2626;background:#fee2e2;',
                                default => 'color:#64748b;background:#f1f5f9;',
                            };
                        @endphp
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>{{ $receipt }}</td>
                            <td>{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $method }}</td>
                            <td>{{ number_format($amount, 2) }}</td>
                            <td>
                                <span class="label" style="{{ $pStatusClass }}">{{ ucfirst($paymentStatus) }}</span>
                            </td>
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

        <div class="lm-edit-sections-mobile">
            @forelse($payments as $payment)
                @php
                    $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #' . $payment->id);
                    $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount_base ?? $payment->amount ?? 0);
                    $method = $payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '-';
                    $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                    $paymentStatus = strtolower((string) ($payment->status ?? 'confirmed'));
                    $pStatusClass = match($paymentStatus) {
                        'confirmed', 'completed' => 'color:#16a34a;background:#dcfce7;',
                        'pending' => 'color:#d97706;background:#fef3c7;',
                        'cancelled', 'failed' => 'color:#dc2626;background:#fee2e2;',
                        default => 'color:#64748b;background:#f1f5f9;',
                    };
                @endphp
                <div class="lm-edit-section-card">
                    <div class="lm-edit-section-card-header">
                        <span class="lm-edit-section-card-title">{{ $receipt }}</span>
                        <span class="label" style="{{ $pStatusClass }}">{{ ucfirst($paymentStatus) }}</span>
                    </div>
                    <div class="lm-edit-section-card-body">
                        <div class="lm-edit-section-card-item"><small>Amount</small><span style="font-weight:700;color:#0f172a;">{{ number_format($amount, 2) }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Method</small><span>{{ $method }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Paid Date</small><span>{{ !empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-' }}</span></div>
                        <div class="lm-edit-section-card-item"><small>Payment ID</small><span>{{ $payment->id }}</span></div>
                    </div>
                    <div class="lm-edit-section-card-actions">
                        <a href="{{ route('loan-management.payments.edit', ['payment' => $payment->id, 'customer_id' => $backCustomerId]) }}" class="btn btn-xs btn-primary">
                            <i class="fa fa-pencil"></i> Edit Payment
                        </a>
                    </div>
                </div>
            @empty
                <div class="lm-edit-section-card">
                    <div style="text-align:center;color:#94a3b8;padding:12px 0;">No payments found.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
