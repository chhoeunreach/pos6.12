<div class="box box-primary">
    <div class="box-body table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Loan #</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Risk</th>
                    <th>Bucket</th>
                    <th>DPD</th>
                    <th>PTP</th>
                    <th class="text-right">Balance</th>
                    <th>Next Follow-up</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    @php
                        $status = $loan->collection_status ?? $loan->status ?? 'active';
                        $risk = $loan->risk_level ?? 'normal';
                    @endphp
                    <tr>
                        <td>{{ $loan->loan_number ?? $loan->id }}</td>
                        <td>{{ $loan->customer_name_snapshot ?? '-' }}</td>
                        <td>{{ $loan->customer_phone_snapshot ?? '-' }}</td>
                        <td><span class="{{ $badges::badgeClass($status, $risk) }}">{{ $options['statuses'][$status] ?? ucfirst(str_replace('_', ' ', $status)) }}</span></td>
                        <td><span class="{{ $badges::badgeClass($status, $risk) }}">{{ $options['riskLevels'][$risk] ?? ucfirst(str_replace('_', ' ', $risk)) }}</span></td>
                        <td>{{ $options['buckets'][$loan->overdue_bucket ?? 'current'] ?? '-' }}</td>
                        <td>{{ (int) ($loan->days_past_due ?? 0) }}</td>
                        <td>
                            @if(!empty($loan->ptp_date))
                                {{ $loan->ptp_date }} / {{ number_format((float)($loan->ptp_amount ?? 0), 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right">{{ number_format((float)($loan->balance_amount ?? 0), 2) }}</td>
                        <td>{{ $loan->next_followup_at ?? '-' }}</td>
                        <td>
                            @if(Route::has('loan-management.loans.view'))
                                <a class="btn btn-xs btn-default" href="{{ route('loan-management.loans.view', $loan->id) }}"><i class="fa fa-eye"></i> View</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if(method_exists($loans, 'links'))
            {{ $loans->links() }}
        @endif
    </div>
</div>
