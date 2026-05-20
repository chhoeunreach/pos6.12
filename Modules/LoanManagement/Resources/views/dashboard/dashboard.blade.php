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
                    <span class="info-box-number" data-loan-card="{{ $card['key'] }}" data-format="{{ in_array($card['key'], ['collection_amount_today']) ? 'money' : 'int' }}">{{ in_array($card['key'], ['collection_amount_today']) ? number_format((float) $val, 2) : (int) $val }}</span>
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
                <table class="table table-striped table-bordered" id="loanRecentPaymentsTable">
                    <thead><tr><th>Date</th><th>Customer</th><th>Loan</th><th>Method</th><th class="text-right">Amount</th></tr></thead>
                    <tbody data-loan-table="recent_payments">
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
                <table class="table table-condensed" id="loanOverdueCustomersTable">
                    <thead><tr><th>Customer</th><th>Days</th><th class="text-right">Amount</th></tr></thead>
                    <tbody data-loan-table="overdue_customers">
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
                <table class="table table-bordered table-condensed" id="loanVisitScheduleTable">
                    <thead><tr><th>Customer</th><th>Date</th><th>Status</th><th>Staff</th></tr></thead>
                    <tbody data-loan-table="follow_up_customers">
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
                        <small id="loanStatusChartText" data-loan-chart="loan_status">Status labels: {{ implode(', ', $loanStatusChart['labels'] ?? []) }}</small>
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
                <table class="table table-striped table-bordered" id="loanCollectorPerformanceTable">
                    <thead><tr><th>Collector</th><th>Assigned Loans</th><th class="text-right">Collected</th><th>Visits</th></tr></thead>
                    <tbody data-loan-table="collector_performance">
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

@section('loan_js')
@parent
<script>
    (function ($) {
        if (!window.jQuery) {
            return;
        }

        var liveUrl = "{{ route('loan-management.dashboard.data', [], true) }}";
        var refreshMs = 30000;
        var loading = false;
        var timer = null;

        function money(value) {
            var number = parseFloat(value || 0);
            return Number.isFinite(number) ? number.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00';
        }

        function intValue(value) {
            var number = parseInt(value || 0, 10);
            return Number.isFinite(number) ? String(number) : '0';
        }

        function esc(value) {
            return $('<div>').text(value == null ? '-' : value).html();
        }

        function updateCards(cards) {
            $('[data-loan-card]').each(function () {
                var key = $(this).data('loan-card');
                var value = cards && Object.prototype.hasOwnProperty.call(cards, key) ? cards[key] : 0;
                $(this).text($(this).data('format') === 'money' ? money(value) : intValue(value));
            });
        }

        function renderRecentPayments(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td>'+esc(row.paid_date)+'</td><td>'+esc(row.customer_name_snapshot)+'</td><td>'+esc(row.loan_number)+'</td><td>'+esc(row.payment_method)+'</td><td class="text-right">'+money(row.paid_amount)+'</td></tr>';
            });
            $('[data-loan-table="recent_payments"]').html(html || '<tr><td colspan="5" class="text-center">No recent payments found.</td></tr>');
        }

        function renderOverdueCustomers(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td>'+esc(row.customer)+'</td><td>'+intValue(row.overdue_days)+'</td><td class="text-right">'+money(row.overdue_amount)+'</td></tr>';
            });
            $('[data-loan-table="overdue_customers"]').html(html || '<tr><td colspan="3" class="text-center">No overdue customers.</td></tr>');
        }

        function renderFollowUps(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td>'+esc(row.customer)+'</td><td>'+esc(row.follow_up_date)+'</td><td>'+esc(row.status)+'</td><td>'+esc(row.assigned_staff)+'</td></tr>';
            });
            $('[data-loan-table="follow_up_customers"]').html(html || '<tr><td colspan="4" class="text-center">No pending visits.</td></tr>');
        }

        function renderCollectorPerformance(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td>'+esc(row.collector)+'</td><td>'+intValue(row.assigned_loans)+'</td><td class="text-right">'+money(row.collected_amount)+'</td><td>'+intValue(row.visit_count)+'</td></tr>';
            });
            $('[data-loan-table="collector_performance"]').html(html || '<tr><td colspan="4" class="text-center">No collector performance data.</td></tr>');
        }

        function updateChartText(chart) {
            if (!chart || !chart.labels) {
                return;
            }
            $('#loanStatusChartText').text('Status labels: ' + chart.labels.join(', '));
        }

        function refreshLoanDashboard() {
            if (loading || document.hidden) {
                return;
            }

            loading = true;
            fetch(liveUrl + window.location.search + (window.location.search ? '&' : '?') + 'realtime=1', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    var contentType = response.headers.get('content-type') || '';
                    if (!response.ok || contentType.indexOf('application/json') === -1) {
                        if (timer) {
                            window.clearInterval(timer);
                            timer = null;
                        }
                        return null;
                    }

                    return response.json();
                })
                .then(function (res) {
                    if (!res) {
                        return;
                    }

                    var data = res && res.data ? res.data : {};
                    updateCards(data.quick_cards || data.cards || {});
                    renderRecentPayments(data.tables ? data.tables.recent_payments : []);
                    renderOverdueCustomers(data.tables ? data.tables.overdue_customers : []);
                    renderFollowUps(data.tables ? data.tables.follow_up_customers : []);
                    renderCollectorPerformance(data.charts ? data.charts.collector_performance : []);
                    updateChartText(data.charts ? data.charts.loan_status : null);
                })
                .catch(function () {})
                .finally(function () {
                    loading = false;
                });
        }

        $(function () {
            if (!$('#loanManagementApp').length) {
                return;
            }

            if (window.loanDashboardRealtimeTimer) {
                window.clearInterval(window.loanDashboardRealtimeTimer);
            }

            timer = window.setInterval(refreshLoanDashboard, refreshMs);
            window.loanDashboardRealtimeTimer = timer;
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    refreshLoanDashboard();
                }
            });
        });
    })(jQuery);
</script>
@endsection
