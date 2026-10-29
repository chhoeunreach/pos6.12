@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'របាយការណ៍ហានិភ័យផលប័ត្រ (PAR)' : 'Portfolio-at-Risk (PAR) Report')
@section('hide_breadcrumb', '1')

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
<style>
    .par-container {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .par-hero {
        background: #ffffff;
        border-radius: 14px;
        padding: 22px 26px;
        border: 1px solid #e2e8f0;
        border-left: 5px solid var(--lm-primary, #2563eb);
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 15px -3px rgba(0,0,0,0.04);
    }
    .par-hero h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .par-hero p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 13px;
    }
    .par-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }
    @media (max-width: 991px) {
        .par-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 575px) {
        .par-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
    .par-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 18px 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .par-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    }
    .par-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; bottom: 0;
        width: 4px;
        background: var(--card-color, #2563eb);
    }
    .par-card-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .par-card-val {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        margin: 6px 0 2px;
    }
    .par-card-sub {
        font-size: 12px;
        font-weight: 600;
        color: var(--card-color, #64748b);
    }
    .par-dist-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 22px 26px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }
    .par-dist-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .par-dist-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
    }
    .par-progress-stacked {
        display: flex;
        height: 24px;
        border-radius: 8px;
        overflow: hidden;
        background: #f1f5f9;
        margin-bottom: 16px;
    }
    .par-seg {
        height: 100%;
        transition: width 0.4s ease;
    }
    .par-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .par-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        padding: 10px 14px;
        border-bottom: 2px solid #e2e8f0;
    }
    .par-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        color: #334155;
    }
    .par-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        color: #ffffff;
    }
    .par-filter-bar {
        background: #ffffff;
        border-radius: 14px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
    }
    .par-filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .par-filter-group select, .par-filter-group input {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        padding: 7px 12px;
        font-size: 13px;
        color: #1e293b;
    }
</style>
@endsection

@section('content_body')
<div class="par-container">
    <!-- Top Hero Banner -->
    <div class="par-hero">
        <div>
            <h1>
                <i class="fa fa-shield" style="color: var(--lm-primary);"></i>
                {{ $isKhmer ? 'របាយការណ៍ហានិភ័យផលប័ត្រ (PAR)' : 'Portfolio-at-Risk (PAR) Analytics' }}
            </h1>
            <p>{{ $isKhmer ? 'តាមដានគុណភាពផលប័ត្រឥណទាន និងអត្រាកម្ចីមិនដំណើរការ (NPL) តាមស្តង់ដារធនាគារជាតិនៃកម្ពុជា' : 'Monitor loan portfolio delinquency, aging buckets, and Non-Performing Loans (NPL) ratio' }}</p>
        </div>
        <div>
            <button type="button" class="btn btn-default" onclick="window.print();">
                <i class="fa fa-print"></i> {{ $isKhmer ? 'បោះពុម្ព' : 'Print' }}
            </button>
        </div>
    </div>

    <!-- Top KPI Summary Cards -->
    <div class="par-kpi-grid">
        <!-- 1. Gross Loan Portfolio -->
        <div class="par-card" style="--card-color: #2563eb;">
            <div class="par-card-label">{{ $isKhmer ? 'ផលប័ត្រឥណទានសរុប (GLP)' : 'Gross Portfolio (GLP)' }}</div>
            <div class="par-card-val">$ {{ number_format($summary['total_glp'], 2) }}</div>
            <div class="par-card-sub">
                <i class="fa fa-cubes"></i> {{ number_format($summary['total_active_loans']) }} {{ $isKhmer ? 'កម្ចីសកម្ម' : 'Active Loans' }}
            </div>
        </div>

        <!-- 2. PAR > 30 Days -->
        <div class="par-card" style="--card-color: #f59e0b;">
            <div class="par-card-label">{{ $isKhmer ? 'ហានិភ័យលើសពី ៣០ ថ្ងៃ (PAR 30)' : 'PAR > 30 Days' }}</div>
            <div class="par-card-val" style="color: #d97706;">{{ $summary['par_30_rate'] }}%</div>
            <div class="par-card-sub" style="--card-color: #d97706;">
                $ {{ number_format($summary['par_30_amount'], 2) }} {{ $isKhmer ? 'ទឹកប្រាក់ហានិភ័យ' : 'At Risk' }}
            </div>
        </div>

        <!-- 3. Non-Performing Loans (NPL / PAR 90) -->
        <div class="par-card" style="--card-color: #ef4444;">
            <div class="par-card-label">{{ $isKhmer ? 'អត្រាកម្ចីមិនដំណើរការ (NPL / PAR 90+)' : 'NPL Ratio (PAR 90+)' }}</div>
            <div class="par-card-val" style="color: #dc2626;">{{ $summary['npl_rate'] }}%</div>
            <div class="par-card-sub" style="--card-color: #dc2626;">
                $ {{ number_format($summary['npl_amount'], 2) }} {{ $isKhmer ? 'កម្ចីខូចខាត' : 'Default/NPL' }}
            </div>
        </div>

        <!-- 4. Accrued Penalties -->
        <div class="par-card" style="--card-color: #8b5cf6;">
            <div class="par-card-label">{{ $isKhmer ? 'ប្រាក់ពិន័យបង្គរ' : 'Accrued Penalties' }}</div>
            <div class="par-card-val" style="color: #7c3aed;">$ {{ number_format($summary['total_penalties'], 2) }}</div>
            <div class="par-card-sub" style="--card-color: #7c3aed;">
                <i class="fa fa-clock-o"></i> {{ $isKhmer ? 'គណនាដោយស្វ័យប្រវត្តិ' : 'Automated DPD' }}
            </div>
        </div>
    </div>

    <!-- Visual Portfolio Quality Breakdown -->
    <div class="par-dist-card">
        <div class="par-dist-header">
            <h3>{{ $isKhmer ? 'ការបែងចែកហានិភ័យតាមកម្រិត (Risk Bucket Distribution)' : 'Portfolio Aging & Risk Classification Breakdown' }}</h3>
        </div>

        <!-- Visual Stacked Bar -->
        @php
            $tot = max(1, $summary['total_glp']);
        @endphp
        <div class="par-progress-stacked">
            @foreach($summary['buckets'] as $key => $bucket)
                @php $pct = round(($bucket['amount'] / $tot) * 100, 1); @endphp
                @if($pct > 0)
                    <div class="par-seg" style="width: {{ $pct }}%; background: {{ $bucket['color'] }};" title="{{ $bucket['label'] }}: {{ $pct }}%"></div>
                @endif
            @endforeach
        </div>

        <!-- Breakdown Table -->
        <div class="table-responsive">
            <table class="par-table">
                <thead>
                    <tr>
                        <th>{{ $isKhmer ? 'កម្រិតហានិភ័យ / ចំណាត់ថ្នាក់' : 'Classification / Risk Bucket' }}</th>
                        <th>{{ $isKhmer ? 'កម្រិតថ្ងៃយឺត (DPD)' : 'DPD Range' }}</th>
                        <th class="text-right">{{ $isKhmer ? 'ចំនួនកម្ចី' : 'Loan Count' }}</th>
                        <th class="text-right">{{ $isKhmer ? 'សមតុល្យសរុប ($)' : 'Outstanding Balance ($)' }}</th>
                        <th class="text-right">{{ $isKhmer ? 'សមាមាត្រ %' : '% of Portfolio' }}</th>
                        <th>{{ $isKhmer ? 'វិធានការដោះស្រាយ' : 'Action Required' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $dpdRanges = [
                            'current' => '0 Days',
                            'par_1_30' => '1 - 30 Days',
                            'par_31_60' => '31 - 60 Days',
                            'par_61_90' => '61 - 90 Days',
                            'par_90_plus' => '> 90 Days',
                        ];
                        $actions = [
                            'current' => $isKhmer ? 'ដំណើរការល្អ (អនុវត្តធម្មតា)' : 'Normal Monitoring',
                            'par_1_30' => $isKhmer ? 'ផ្ញើសេចក្តីជូនដំណឹងតាម Telegram / Call' : 'Soft Reminder & Telegram Alert',
                            'par_31_60' => $isKhmer ? 'ចុះជួបអតិថិជនផ្ទាល់ & ផាកពិន័យ' : 'Field Visit & Intensive Follow-up',
                            'par_61_90' => $isKhmer ? 'លិខិតព្រមានផ្លូវច្បាប់ & ទាក់ទងអ្នកធានា' : 'Formal Warning & Guarantor Call',
                            'par_90_plus' => $isKhmer ? 'រឹបអូសទ្រព្យ / ចាត់វិធានការច្បាប់' : 'Legal Recovery / Write-off Process',
                        ];
                    @endphp
                    @foreach($summary['buckets'] as $key => $bucket)
                        @php $pct = round(($bucket['amount'] / $tot) * 100, 2); @endphp
                        <tr>
                            <td>
                                <span class="par-badge" style="background: {{ $bucket['color'] }};">
                                    {{ $bucket['label'] }}
                                </span>
                            </td>
                            <td><strong>{{ $dpdRanges[$key] ?? '-' }}</strong></td>
                            <td class="text-right font-bold">{{ number_format($bucket['count']) }}</td>
                            <td class="text-right font-bold">$ {{ number_format($bucket['amount'], 2) }}</td>
                            <td class="text-right">
                                <span class="font-bold" style="color: {{ $bucket['color'] }};">{{ $pct }}%</span>
                            </td>
                            <td><small class="text-muted">{{ $actions[$key] ?? '-' }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Filter & Drill-down Loan List -->
    <div class="par-filter-bar">
        <form method="GET" action="{{ route('loan-management.reports.portfolio-at-risk') }}" class="par-filter-group" id="parFilterForm">
            <label><strong>{{ $isKhmer ? 'សាខា:' : 'Branch:' }}</strong></label>
            <select name="location_id" onchange="document.getElementById('parFilterForm').submit();">
                <option value="">{{ $isKhmer ? '-- គ្រប់សាខាទាំងអស់ --' : '-- All Branches --' }}</option>
                @foreach($locations as $locId => $locName)
                    <option value="{{ $locId }}" {{ (int) $selectedLocation === (int) $locId ? 'selected' : '' }}>
                        {{ $locName }}
                    </option>
                @endforeach
            </select>

            <label style="margin-left: 10px;"><strong>{{ $isKhmer ? 'កម្រិតហានិភ័យ:' : 'Risk Bucket:' }}</strong></label>
            <select name="risk_bucket" onchange="document.getElementById('parFilterForm').submit();">
                <option value="">{{ $isKhmer ? '-- គ្រប់កម្រិតទាំងអស់ --' : '-- All Buckets --' }}</option>
                <option value="par_90_plus" {{ $selectedRiskBucket === 'par_90_plus' ? 'selected' : '' }}>Loss / NPL (>90 DPD)</option>
                <option value="par_61_90" {{ $selectedRiskBucket === 'par_61_90' ? 'selected' : '' }}>Doubtful (61-90 DPD)</option>
                <option value="par_31_60" {{ $selectedRiskBucket === 'par_31_60' ? 'selected' : '' }}>Substandard (31-60 DPD)</option>
                <option value="par_1_30" {{ $selectedRiskBucket === 'par_1_30' ? 'selected' : '' }}>Special Mention (1-30 DPD)</option>
                <option value="current" {{ $selectedRiskBucket === 'current' ? 'selected' : '' }}>Standard / Current (0 DPD)</option>
            </select>
        </form>
    </div>

    <!-- Active Loan Drilldown Table -->
    <div class="par-dist-card">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="parLoansTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>{{ $isKhmer ? 'លេខកូដកម្ចី' : 'Loan #' }}</th>
                        <th>{{ $isKhmer ? 'អតិថិជន' : 'Customer' }}</th>
                        <th>{{ $isKhmer ? 'សមតុល្យជំពាក់' : 'Balance' }}</th>
                        <th>{{ $isKhmer ? 'កាលបរិច្ឆេទយឺត (DPD)' : 'DPD' }}</th>
                        <th>{{ $isKhmer ? 'ចំណាត់ថ្នាក់ហានិភ័យ' : 'Risk Classification' }}</th>
                        <th>{{ $isKhmer ? 'ប្រាក់ពិន័យ' : 'Penalty Due' }}</th>
                        <th>{{ $isKhmer ? 'សកម្មភាព' : 'Action' }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('loan_js')
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#parLoansTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('loan-management.reports.portfolio-at-risk') }}",
            data: function(d) {
                d.location_id = $('select[name="location_id"]').val();
                d.risk_bucket = $('select[name="risk_bucket"]').val();
            }
        },
        columns: [
            { data: 'loan_number', name: 'loan_number' },
            { data: 'customer_name_snapshot', name: 'customer_name_snapshot' },
            { data: 'balance_amount', name: 'balance_amount' },
            { data: 'max_dpd', name: 'max_dpd', className: 'text-center' },
            { data: 'risk_bucket', name: 'risk_bucket', className: 'text-center' },
            { data: 'penalty_due', name: 'penalty_due', className: 'text-right' },
            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
        ],
        order: [[3, 'desc']],
        pageLength: 25
    });
});
</script>
@endsection
