@php
    $cards = [
        ['key' => 'due_today', 'label' => 'Due Today', 'icon' => 'fa fa-calendar-check-o'],
        ['key' => 'overdue_accounts', 'label' => 'Overdue Accounts', 'icon' => 'fa fa-exclamation-triangle'],
        ['key' => 'skip_customers', 'label' => 'Skip Customers', 'icon' => 'fa fa-phone-square'],
        ['key' => 'broken_ptp', 'label' => 'Broken PTP', 'icon' => 'fa fa-chain-broken'],
        ['key' => 'field_visits_today', 'label' => 'Field Visits Today', 'icon' => 'fa fa-street-view'],
        ['key' => 'collection_amount_today', 'label' => 'Collection Amount Today', 'icon' => 'fa fa-dollar'],
        ['key' => 'recovery_cases', 'label' => 'Recovery Cases', 'icon' => 'fa fa-refresh'],
        ['key' => 'legal_cases', 'label' => 'Legal Cases', 'icon' => 'fa fa-gavel'],
        ['key' => 'high_risk_customers', 'label' => 'High Risk Customers', 'icon' => 'fa fa-user-times'],
        ['key' => 'repossessions', 'label' => 'Repossessions', 'icon' => 'fa fa-truck'],
    ];
@endphp

<div class="row">
    @foreach($cards as $card)
        @php $val = $quickCards[$card['key']] ?? 0; @endphp
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-blue"><i class="{{ $card['icon'] }}"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $card['label'] }}</span>
                    <span class="info-box-number">{{ in_array($card['key'], ['collection_amount_today']) ? number_format((float) $val, 2) : (int) $val }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Recent Payments</h3></div>
            <div class="box-body lm-table-wrap">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Date</th><th>Customer</th><th>Loan</th><th>Method</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @forelse(($recentPayments ?? []) as $row)
                        <tr>
                            <td>{{ $row['paid_date'] ?? '-' }}</td>
                            <td>{{ $row['customer_name_snapshot'] ?? '-' }}</td>
                            <td>{{ $row['loan_number'] ?? '-' }}</td>
                            <td>{{ $row['payment_method'] ?? '-' }}</td>
                            <td class="text-right">{{ number_format((float)($row['paid_amount'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No recent payments found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="box box-danger">
            <div class="box-header with-border"><h3 class="box-title">Overdue Customers</h3></div>
            <div class="box-body lm-table-wrap">
                <table class="table table-condensed">
                    <thead><tr><th>Customer</th><th>Days</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @forelse(($overdueCustomers ?? []) as $row)
                        <tr>
                            <td>{{ $row['customer'] ?? '-' }}</td>
                            <td>{{ (int)($row['overdue_days'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format((float)($row['overdue_amount'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">No overdue customers.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title">Collection Visit Schedule</h3></div>
            <div class="box-body lm-table-wrap">
                <table class="table table-bordered table-condensed">
                    <thead><tr><th>Customer</th><th>Date</th><th>Status</th><th>Staff</th></tr></thead>
                    <tbody>
                    @forelse(($visitSchedule ?? []) as $row)
                        <tr>
                            <td>{{ $row['customer'] ?? '-' }}</td>
                            <td>{{ $row['follow_up_date'] ?? '-' }}</td>
                            <td>{{ $row['status'] ?? '-' }}</td>
                            <td>{{ $row['assigned_staff'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No pending visits.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">Loan Status Chart</h3></div>
            <div class="box-body">
                <div style="height: 230px; display:flex; align-items:center; justify-content:center; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px;">
                    <div class="text-center text-muted">
                        <strong>Chart Placeholder</strong><br>
                        <small>Status labels: {{ implode(', ', $loanStatusChart['labels'] ?? []) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title">Collector Performance</h3></div>
            <div class="box-body lm-table-wrap">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Collector</th><th>Assigned Loans</th><th class="text-right">Collected</th><th>Visits</th></tr></thead>
                    <tbody>
                    @forelse(($collectorPerformance ?? []) as $row)
                        <tr>
                            <td>{{ $row['collector'] ?? '-' }}</td>
                            <td>{{ (int)($row['assigned_loans'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format((float)($row['collected_amount'] ?? 0), 2) }}</td>
                            <td>{{ (int)($row['visit_count'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No collector performance data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
