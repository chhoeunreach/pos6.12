@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $scheduleTotals = $scheduleTotals ?? [
        'principal_total' => 0,
        'interest_total' => 0,
        'amount_total' => 0,
        'paid_total' => 0,
        'balance_total' => 0,
    ];

    $todayDate = date('Y-m-d');
@endphp

<style>
    /* High-Density Section Styles */
    .lm-sec-card {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        margin-bottom: 10px;
        overflow: hidden;
    }
    .lm-sec-head {
        padding: 6px 12px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .lm-sec-title {
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .lm-sec-title i { color: #2563eb; font-size: 12px; }
    .lm-sec-body { padding: 0; }

    /* Ultra-Compact Table */
    .lm-table-dense {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
        font-size: 11px;
    }
    .lm-table-dense th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.2px;
        padding: 6px 8px;
        border-bottom: 1px solid #cbd5e1;
        border-top: none;
        white-space: nowrap;
    }
    .lm-table-dense td {
        padding: 5px 8px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle;
    }
    .lm-table-dense tr:hover td {
        background-color: #f8fafc;
    }
    .lm-table-dense tfoot th {
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 700;
        font-size: 11px;
        padding: 6px 8px;
        border-top: 2px solid #cbd5e1;
    }

    /* Small Badges & Action Buttons */
    .lm-badge {
        font-size: 9px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        text-transform: uppercase;
    }
    .lm-badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .lm-badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .lm-badge-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .lm-badge-info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .lm-badge-gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .lm-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 6px;
        font-size: 10px;
        font-weight: 600;
        border-radius: 4px;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.1s;
    }
    .lm-action-btn-pay { background: #16a34a; color: #fff; border-color: #15803d; }
    .lm-action-btn-pay:hover { background: #15803d; color: #fff; }
    .lm-action-btn-edit { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    .lm-action-btn-edit:hover { background: #e2e8f0; color: #0f172a; }

    .lm-copy-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 1px 6px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 10px;
        color: #334155;
        cursor: pointer;
    }
    .lm-copy-pill:hover { background: #e2e8f0; }
</style>

    <!-- Installment Items Snapshot -->
    <div class="lm-sec-card">
    <div class="lm-sec-head">
        <h3 class="lm-sec-title">
            <i class="fa fa-cubes"></i> {{ $lmText('Purchased Products & Collateral Items', 'ទំនិញទិញរំលស់ និងទ្រព្យបញ្ចាំ') }} ({{ $items->count() }})
        </h3>
    </div>
    <div class="lm-sec-body table-responsive">
        <table class="lm-table-dense">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $lmText('Product / Model', 'ឈ្មោះទំនិញ / ម៉ូដែល') }}</th>
                    <th>{{ $lmText('SKU / Brand', 'កូដ / ម៉ាក') }}</th>
                    <th>{{ $lmText('Specs (Color/Storage)', 'លក្ខណៈ (ពណ៌/ទំហំ)') }}</th>
                    <th>{{ $lmText('Serial / IMEI', 'លេខស៊េរី / IMEI') }}</th>
                    <th class="text-center">{{ $lmText('Qty', 'ចំនួន') }}</th>
                    <th class="text-right">{{ $lmText('Unit Price', 'តម្លៃឯកតា') }}</th>
                    <th class="text-right">{{ $lmText('Total', 'សរុប') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $i)
                @php
                    $lineTotal = (float)($i->line_total ?? $i->total_price ?? (($i->qty ?? 1) * ($i->unit_price ?? 0)));
                    $posRef = trim(implode(' / ', array_filter([
                        !empty($i->pos_product_id) ? '#'.$i->pos_product_id : null,
                        !empty($i->pos_variation_id) ? '#'.$i->pos_variation_id : null,
                    ])));
                    $posLocation = trim((string) ($i->pos_location_name_snapshot ?? ''));
                    $posStatus = trim((string) ($i->pos_stock_status ?? ''));
                @endphp
                <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td><strong>{{ $i->product_name_snapshot ?? ($i->item_name ?? '-') }}</strong></td>
                    <td>
                        <span class="text-muted">{{ $i->brand ?? '-' }}</span>
                        @if(!empty($i->sku_snapshot))
                            <small class="text-muted" style="display:block;">SKU: {{ $i->sku_snapshot }}</small>
                        @endif
                        @if($posRef !== '')
                            <small class="text-muted" style="display:block;">{{ $lmText('POS Ref', 'លេខយោង POS') }}: {{ $posRef }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $i->color_snapshot ?? ($i->color ?? '-') }}
                        @if(!empty($i->storage_snapshot) || !empty($i->storage))
                            <span class="text-muted">/ {{ $i->storage_snapshot ?? $i->storage }}</span>
                        @endif
                        @if($posLocation !== '')
                            <small class="text-muted" style="display:block;">{{ $lmText('Location', 'សាខា') }}: {{ $posLocation }}</small>
                        @endif
                        @if($posStatus !== '')
                            <small class="text-muted" style="display:block;">{{ $lmText('Stock Status', 'ស្ថានភាពស្តុក') }}: {{ ucfirst($posStatus) }}</small>
                        @endif
                    </td>
                    <td>
                        @if(!empty($i->imei_snapshot) || !empty($i->imei))
                            <span class="lm-copy-pill lm-copy-btn" data-copy="{{ $i->imei_snapshot ?? $i->imei }}" title="{{ $lmText('Click to copy', 'ចុចដើម្បីចម្លង') }}">
                                <i class="fa fa-barcode"></i> {{ $i->imei_snapshot ?? $i->imei }}
                            </span>
                        @elseif(!empty($i->serial_number_snapshot) || !empty($i->serial_number))
                            <span class="lm-copy-pill lm-copy-btn" data-copy="{{ $i->serial_number_snapshot ?? $i->serial_number }}" title="{{ $lmText('Click to copy', 'ចុចដើម្បីចម្លង') }}">
                                <i class="fa fa-tag"></i> {{ $i->serial_number_snapshot ?? $i->serial_number }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-center"><strong>{{ $i->qty ?? 1 }}</strong></td>
                    <td class="text-right">{{ number_format((float)($i->unit_price ?? 0), 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($lineTotal, 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 14px;">
                        <i class="fa fa-info-circle"></i> {{ $lmText('No installment items recorded.', 'មិនមានទិន្នន័យទំនិញទេ។') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <!-- Payment Schedule -->
<div class="lm-sec-card">
    <div class="lm-sec-head">
        <h3 class="lm-sec-title">
            <i class="fa fa-calendar-check-o"></i> {{ $lmText('Repayment Schedule', 'តារាងបង់ប្រាក់ប្រចាំវគ្គ') }} ({{ $schedules->count() }})
        </h3>
        @can('loan_management.edit')
            <div>
                <button type="button"
                        class="lm-action-btn lm-action-btn-edit lm-refresh-schedule-btn"
                        data-url="{{ route('loan-management.loans.schedules.refresh', ['loan' => $loanRow->id, 'sections_context' => 'show'] + (request()->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])) }}"
                        title="{{ $lmText('Refresh Schedule', 'រៀបចំតារាងឡើងវិញ') }}">
                    <i class="fa fa-refresh"></i> <span>{{ $lmText('Refresh Schedule', 'រៀបចំឡើងវិញ') }}</span>
                </button>
            </div>
        @endcan
    </div>
    <div class="lm-sec-body table-responsive">
        <table class="lm-table-dense">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $lmText('Due Date', 'ថ្ងៃត្រូវបង់') }}</th>
                    <th class="text-right">{{ $lmText('Principal', 'ប្រាក់ដើម') }}</th>
                    <th class="text-right">{{ $lmText('Interest', 'ការប្រាក់') }}</th>
                    <th class="text-right">{{ $lmText('Total Due', 'ត្រូវបង់សរុប') }}</th>
                    <th class="text-right">{{ $lmText('Paid', 'បានបង់') }}</th>
                    <th class="text-right">{{ $lmText('Balance', 'នៅសល់') }}</th>
                    <th class="text-center">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                    <th class="text-center">{{ $lmText('Action', 'សកម្មភាព') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($schedules as $s)
                @php
                    $principal = (float)($s->principal_amount ?? $s->principal_due ?? 0);
                    $interest = (float)($s->interest_amount ?? $s->interest_due ?? 0);
                    $due = (float)($s->schedule_amount ?? $s->amount_due ?? 0);
                    $paid = (float)($s->paid_amount ?? $s->amount_paid ?? 0);
                    $balance = (float)($s->balance_amount ?? $s->amount_balance ?? ($due - $paid));
                    $rawStatus = strtolower((string) ($s->status ?? 'pending'));
                    $isPaid = in_array($rawStatus, ['paid', 'completed', 'pay off', 'pay_off', 'payoff'], true) || ($balance <= 0 && $paid > 0);
                    $isOverdue = !$isPaid && !empty($s->due_date) && $s->due_date < $todayDate;
                @endphp
                <tr style="{{ $isOverdue ? 'background-color: #fff1f2;' : '' }}">
                    <td><strong>{{ $loop->iteration }}</strong></td>
                    <td>
                        <span style="font-weight: 600;">{{ $s->due_date ?? '-' }}</span>
                        @if($isOverdue)
                            <span class="lm-badge lm-badge-danger" style="margin-left: 4px;">
                                <i class="fa fa-exclamation-triangle"></i> {{ $lmText('Overdue', 'ហួសកំណត់') }}
                            </span>
                        @elseif($isPaid)
                            <span class="lm-badge lm-badge-success" style="margin-left: 4px;">
                                <i class="fa fa-check"></i> {{ $lmText('Paid', 'បង់រួច') }}
                            </span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($principal, 2) }}</td>
                    <td class="text-right">{{ number_format($interest, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($due, 2) }}</strong></td>
                    <td class="text-right" style="color: #16a34a;">{{ number_format($paid, 2) }}</td>
                    <td class="text-right" style="color: {{ $balance > 0 ? '#dc2626' : '#64748b' }};">
                        <strong>{{ number_format($balance, 2) }}</strong>
                    </td>
                    <td class="text-center">
                        @if($isPaid)
                            <span class="lm-badge lm-badge-success">{{ $lmText('Paid', 'បង់រួច') }}</span>
                        @elseif($paid > 0)
                            <span class="lm-badge lm-badge-warning">{{ $lmText('Partial', 'បង់ខ្លះ') }}</span>
                        @elseif($isOverdue)
                            <span class="lm-badge lm-badge-danger">{{ $lmText('Overdue', 'ហួសកំណត់') }}</span>
                        @else
                            <span class="lm-badge lm-badge-gray">{{ $lmText('Pending', 'រង់ចាំ') }}</span>
                        @endif
                    </td>
                    <td class="text-center" style="white-space: nowrap;">
                        <button type="button"
                                class="lm-action-btn lm-action-btn-edit btn-modal"
                                data-href="{{ route('loan-management.loans.schedules.edit', ['loan' => $loanRow->id, 'schedule' => $s->id, 'sections_context' => 'show'] + (request()->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])) }}"
                                data-container=".view_modal"
                                title="{{ $lmText('Edit Schedule', 'កែប្រែ') }}">
                            <i class="fa fa-pencil"></i>
                        </button>
                        @if(! $isPaid)
                            <button type="button"
                                    class="lm-action-btn lm-action-btn-pay btn-modal d-none d-lg-inline-flex"
                                    data-href="{{ route('loan-management.loans.payment.create', ['loan' => $loanRow->id, 'schedule_id' => $s->id]) }}"
                                    data-container=".view_modal">
                                <i class="fa fa-money"></i> {{ $lmText('Pay', 'បង់') }}
                            </button>
                            <button type="button"
                                    class="lm-action-btn lm-action-btn-pay lm-quick-pay-trigger d-lg-none"
                                    data-url="{{ route('loan-management.loans.payment.quick-pay', ['loan' => $loanRow->id, 'schedule_id' => $s->id]) }}"
                                    data-loan-id="{{ $loanRow->id }}">
                                <i class="fa fa-money"></i> {{ $lmText('Pay', 'បង់') }}
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted" style="padding: 14px;">
                        <i class="fa fa-info-circle"></i> {{ $lmText('No schedule records found.', 'មិនទាន់មានតារាងបង់ប្រាក់នៅឡើយទេ។') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
            @if($schedules->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">{{ $lmText('Grand Total', 'សរុបរួម') }}:</th>
                    <th class="text-right">{{ number_format((float) ($scheduleTotals['principal_total'] ?? 0), 2) }}</th>
                    <th class="text-right">{{ number_format((float) ($scheduleTotals['interest_total'] ?? 0), 2) }}</th>
                    <th class="text-right">{{ number_format((float) ($scheduleTotals['amount_total'] ?? 0), 2) }}</th>
                    <th class="text-right" style="color: #16a34a;">{{ number_format((float) ($scheduleTotals['paid_total'] ?? 0), 2) }}</th>
                    <th class="text-right" style="color: #dc2626;">{{ number_format((float) ($scheduleTotals['balance_total'] ?? 0), 2) }}</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<!-- 4. Payments Transactions History -->
<div class="lm-sec-card">
    <div class="lm-sec-head">
        <h3 class="lm-sec-title">
            <i class="fa fa-money"></i> {{ $lmText('Payment History & Receipts', 'ប្រវត្តិទូទាត់ និងបង្កាន់ដៃទទួលប្រាក់') }} ({{ $payments->count() }})
        </h3>
        <div>
            <button type="button"
                    class="lm-action-btn lm-action-btn-pay btn-modal"
                    data-href="{{ route('loan-management.loans.payment.create', $loanRow->id) }}"
                    data-container=".view_modal">
                <i class="fa fa-plus"></i> {{ $lmText('Add Payment', 'បន្ថែមការទូទាត់') }}
            </button>
        </div>
    </div>
    <div class="lm-sec-body table-responsive">
        <table class="lm-table-dense">
            <thead>
                <tr>
                    <th>{{ $lmText('Receipt #', 'លេខបង្កាន់ដៃ') }}</th>
                    <th>{{ $lmText('Paid Date', 'កាលបរិច្ឆេទ') }}</th>
                    <th class="text-right">{{ $lmText('Amount', 'ចំនួនទឹកប្រាក់') }}</th>
                    <th>{{ $lmText('Method', 'វិធីទូទាត់') }}</th>
                    <th>{{ $lmText('Collected By', 'អ្នកទទួលប្រាក់') }}</th>
                    <th class="text-center">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td>
                        @if(! empty($p->id))
                            <a href="{{ route('loan-management.payments.show', $p->id) }}" style="font-weight: 700; color: #2563eb;">
                                {{ $p->receipt_number ?? ('#'.$p->id) }}
                            </a>
                        @else
                            {{ $p->receipt_number ?? '-' }}
                        @endif
                    </td>
                    <td>{{ $p->paid_date ?? ($p->paid_at ?? '-') }}</td>
                    <td class="text-right" style="font-weight: 700; color: #16a34a;">
                        {{ number_format((float)($p->total_paid_base ?? ($p->amount ?? 0)), 2) }}
                    </td>
                    <td>
                        <span class="lm-badge lm-badge-info">
                            {{ ucfirst($p->payment_method_snapshot ?? ($p->method ?? 'Cash')) }}
                        </span>
                    </td>
                    <td>{{ $p->received_by_name_snapshot ?? ($p->created_by_name ?? '-') }}</td>
                    <td class="text-center">
                        <span class="lm-badge lm-badge-success">{{ ucfirst($p->status ?? 'paid') }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding: 14px;">
                        <i class="fa fa-info-circle"></i> {{ $lmText('No payments have been recorded yet.', 'មិនទាន់មានការទូទាត់ណាមួយនៅឡើយទេ។') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- 5. Status Audit Logs -->
@if($statusLogs->isNotEmpty())
<div class="lm-sec-card">
    <div class="lm-sec-head">
        <h3 class="lm-sec-title">
            <i class="fa fa-history"></i> {{ $lmText('Status & Activity Audit Logs', 'កំណត់ហេតុសកម្មភាព និងការផ្លាស់ប្តូរ') }} ({{ $statusLogs->count() }})
        </h3>
    </div>
    <div class="lm-sec-body table-responsive">
        <table class="lm-table-dense">
            <thead>
                <tr>
                    <th>{{ $lmText('Date & Time', 'កាលបរិច្ឆេទ & ម៉ោង') }}</th>
                    <th>{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                    <th>{{ $lmText('Changed By', 'ធ្វើដោយ') }}</th>
                    <th>{{ $lmText('Note / Reason', 'កំណត់ចំណាំ') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($statusLogs as $l)
                <tr>
                    <td style="color: #64748b;">{{ $l->created_at ?? '-' }}</td>
                    <td><span class="lm-badge lm-badge-gray">{{ ucfirst($l->status ?? '-') }}</span></td>
                    <td><strong>{{ $l->changed_by ?? '-' }}</strong></td>
                    <td>{{ $l->note ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
