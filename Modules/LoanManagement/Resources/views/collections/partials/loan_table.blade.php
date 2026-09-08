@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

@php
    $pageTotalBalance = collect($loans->items() ?? [])->sum('balance_amount');
@endphp

@component('components.widget', ['class' => 'box-primary', 'title' => $definition['title'] ?? $lmText('Due Today Installments', 'កម្ចីដល់ថ្ងៃត្រូវបង់ថ្ងៃនេះ')])
    <div class="table-responsive">
        <table class="lm-table-dense table table-striped table-bordered table-hover" id="loanCollectionTable">
            <thead>
                <tr style="background: #f8fafc; color: #475569;">
                    <th style="width: 100px; text-align: center;" class="no-export">{{ $lmText('Action', 'សកម្មភាព') }}</th>
                    <th>{{ $lmText('Installment #', 'លេខកិច្ចសន្យា') }}</th>
                    <th>{{ $lmText('Customer', 'អតិថិជន') }}</th>
                    <th>{{ $lmText('Phone', 'ទូរស័ព្ទ') }}</th>
                    <th style="text-align: center;">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                    <th style="text-align: center;">{{ $lmText('Risk', 'ហានិភ័យ') }}</th>
                    <th>{{ $lmText('Bucket', 'កម្រិត') }}</th>
                    <th style="text-align: center;">{{ $lmText('DPD', 'ថ្ងៃហួស') }}</th>
                    <th>{{ $lmText('PTP Promise', 'សន្យាបង់') }}</th>
                    <th class="text-right">{{ $lmText('Balance', 'សមតុល្យនៅសល់') }}</th>
                    <th>{{ $lmText('Next Follow-up', 'តាមដានបន្ទាប់') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    @php
                        $status = $loan->collection_status ?? $loan->status ?? 'active';
                        $risk = $loan->risk_level ?? 'normal';
                        $loanId = $loan->id ?? null;
                    @endphp
                    <tr>
                        <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                            <div class="btn-group btn-group-xs" role="group">
                                @if(Route::has('loan-management.loans.view') && $loanId)
                                    <a class="btn btn-default btn-xs" href="{{ route('loan-management.loans.view', $loanId) }}" title="{{ $lmText('View Details', 'មើលព័ត៌មានលម្អិត') }}" style="border-radius: 4px; margin-right: 2px;">
                                        <i class="fa fa-eye text-primary"></i>
                                    </a>
                                @endif
                                @if(!empty($loan->customer_phone_snapshot))
                                    <a class="btn btn-default btn-xs" href="tel:{{ $loan->customer_phone_snapshot }}" title="{{ $lmText('Call Customer', 'ខលទៅអតិថិជន') }}" style="border-radius: 4px; margin-right: 2px;">
                                        <i class="fa fa-phone text-success"></i>
                                    </a>
                                @endif
                                @if(Route::has('loan-management.loans.payment.create') && $loanId)
                                    <a class="btn btn-default btn-xs" href="{{ route('loan-management.loans.payment.create', $loanId) }}" title="{{ $lmText('Record Payment', 'កត់ត្រាការបង់ប្រាក់') }}" style="border-radius: 4px;">
                                        <i class="fa fa-dollar text-warning"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if(Route::has('loan-management.loans.view') && $loanId)
                                <a href="{{ route('loan-management.loans.view', $loanId) }}" style="font-weight: 700; color: #0284c7; text-decoration: none;">
                                    {{ $loan->loan_number ?? $loanId }}
                                </a>
                            @else
                                <strong>{{ $loan->loan_number ?? $loanId }}</strong>
                            @endif
                        </td>
                        <td>
                            <strong style="color: #0f172a;">{{ $loan->customer_name_snapshot ?? '-' }}</strong>
                        </td>
                        <td>
                            @if(!empty($loan->customer_phone_snapshot))
                                <span style="font-size: 12px; color: #475569;">{{ $loan->customer_phone_snapshot }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <span class="{{ $badges::badgeClass($status, $risk) }}" style="font-size: 10.5px; border-radius: 4px; padding: 3px 7px;">
                                {{ $options['statuses'][$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </td>
                        <td style="text-align: center;">
                            @php
                                $riskTone = match($risk) {
                                    'critical', 'hard_skip', 'fraud_risk' => 'bg-red',
                                    'high_risk', 'soft_skip' => 'bg-yellow',
                                    'low_risk' => 'bg-teal',
                                    default => 'bg-gray'
                                };
                            @endphp
                            <span class="badge {{ $riskTone }}" style="font-size: 10.5px; font-weight: 600;">
                                {{ $options['riskLevels'][$risk] ?? ucfirst(str_replace('_', ' ', $risk)) }}
                            </span>
                        </td>
                        <td>
                            <span style="font-size: 11.5px; color: #475569;">{{ $options['buckets'][$loan->overdue_bucket ?? 'current'] ?? '-' }}</span>
                        </td>
                        <td style="text-align: center;">
                            @php $dpd = (int) ($loan->days_past_due ?? 0); @endphp
                            <span class="badge {{ $dpd > 30 ? 'bg-red' : ($dpd > 0 ? 'bg-yellow' : 'bg-gray') }}" style="font-size: 11px; font-weight: 700;">
                                {{ $dpd }}
                            </span>
                        </td>
                        <td>
                            @if(!empty($loan->ptp_date))
                                <div style="font-weight: 700; color: #0284c7; font-size: 12px;">{{ $loan->ptp_date }}</div>
                                <div style="font-size: 11px; color: #d97706; font-weight: 600;">${{ number_format((float)($loan->ptp_amount ?? 0), 2) }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-right" style="font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums;">
                            ${{ number_format((float)($loan->balance_amount ?? 0), 2) }}
                        </td>
                        <td style="font-size: 11.5px; color: #64748b;">
                            {{ $loan->next_followup_at ?? '-' }}
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 700; border-top: 2px solid #cbd5e1;">
                    <td colspan="9" class="text-right" style="padding: 10px 12px; color: #334155;">
                        {{ $lmText('Page Subtotal Balance:', 'សមតុល្យសរុបទំព័រនេះ:') }}
                    </td>
                    <td class="text-right" style="padding: 10px 12px; color: #0f172a; font-size: 13.5px; font-weight: 800; border-bottom: 3px double #94a3b8;">
                        ${{ number_format($pageTotalBalance, 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if(method_exists($loans, 'links') && $loans->hasPages())
        <div style="margin-top: 14px; display: flex; justify-content: flex-end;">
            {{ $loans->links() }}
        </div>
    @endif
@endcomponent
