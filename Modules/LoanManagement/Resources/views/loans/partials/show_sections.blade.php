@php
    $scheduleTotals = $scheduleTotals ?? [
        'principal_total' => 0,
        'interest_total' => 0,
        'amount_total' => 0,
        'paid_total' => 0,
        'balance_total' => 0,
    ];
@endphp

<div class="box box-solid">
    <div class="box-header"><h3 class="box-title">Loan Items Snapshot</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>Product</th><th>SKU</th><th>Brand</th><th>Color</th><th>Storage</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>IMEI</th><th>Serial</th><th>Lot</th></tr></thead>
            <tbody>
            @forelse($items as $i)
                <tr>
                    <td>{{ $i->product_name_snapshot ?? '-' }}</td>
                    <td>{{ $i->sku_snapshot ?? '-' }}</td>
                    <td>{{ $i->brand ?? '-' }}</td>
                    <td>{{ $i->color_snapshot ?? $i->color ?? '-' }}</td>
                    <td>{{ $i->storage_snapshot ?? $i->storage ?? '-' }}</td>
                    <td>{{ $i->qty ?? 0 }}</td>
                    <td>{{ number_format((float)($i->unit_price ?? 0),2) }}</td>
                    <td>{{ number_format((float)($i->line_total ?? $i->total_price ?? (($i->qty ?? 0) * ($i->unit_price ?? 0))),2) }}</td>
                    <td>{{ $i->imei_snapshot ?? '-' }}</td>
                    <td>{{ $i->serial_number_snapshot ?? '-' }}</td>
                    <td>{{ $i->lot_number_snapshot ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center">No loan items</td></tr>
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
    <div class="box-header">
        <h3 class="box-title">Payment Schedule</h3>
        @can('loan_management.edit')
            <div class="box-tools pull-right">
                <button type="button"
                        class="btn btn-xs btn-info lm-refresh-schedule-btn"
                        data-url="{{ route('loan-management.loans.schedules.refresh', ['loan' => $loanRow->id, 'sections_context' => 'show'] + (request()->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])) }}"
                        title="Refresh Schedule">
                    <i class="fa fa-refresh"></i> <span class="hidden-xs">Refresh Schedule</span>
                </button>
            </div>
        @endcan
    </div>
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
                        <button type="button"
                                class="btn btn-xs btn-primary btn-modal"
                                data-href="{{ route('loan-management.loans.schedules.edit', ['loan' => $loanRow->id, 'schedule' => $s->id, 'sections_context' => 'show'] + (request()->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])) }}"
                                data-container=".view_modal">
                            <i class="fa fa-pencil"></i> <span class="hidden-xs">Edit</span>
                        </button>
                        @if(! in_array(strtolower((string) ($s->status ?? '')), ['paid', 'completed', 'pay off', 'pay_off', 'payoff'], true))
                            <button type="button"
                                    class="btn btn-xs btn-success btn-modal d-none d-lg-inline-block"
                                    data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loanRow->id, 'schedule_id' => $s->id]) }}"
                                    data-container=".view_modal">
                                <i class="fa fa-money"></i> Pay
                            </button>
                            <button type="button"
                                    class="btn btn-xs btn-success lm-quick-pay-trigger d-lg-none"
                                    data-url="{{ route('loan-management.loans.payment.quick-pay', ['loan' => $loanRow->id, 'schedule_id' => $s->id]) }}"
                                    data-loan-id="{{ $loanRow->id }}">
                                <i class="fa fa-money"></i> Pay
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">No schedules</td></tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr class="bg-gray">
                <th colspan="2" class="text-right">Total</th>
                <th>{{ number_format((float) $scheduleTotals['principal_total'], 2) }}</th>
                <th>{{ number_format((float) $scheduleTotals['interest_total'], 2) }}</th>
                <th>{{ number_format((float) $scheduleTotals['amount_total'], 2) }}</th>
                <th>{{ number_format((float) $scheduleTotals['paid_total'], 2) }}</th>
                <th>{{ number_format((float) $scheduleTotals['balance_total'], 2) }}</th>
                <th colspan="2"></th>
            </tr>
            </tfoot>
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
                    <td>
                        @if(! empty($p->id))
                            <a href="{{ route('loan-management.payments.show', $p->id) }}">{{ $p->receipt_number ?? ('Payment #'.$p->id) }}</a>
                        @else
                            {{ $p->receipt_number ?? '-' }}
                        @endif
                    </td>
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
