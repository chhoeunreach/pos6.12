@extends('loanmanagement::layouts.app')
@section('title', 'CBC Export')
@section('hide_breadcrumb', '1')

@section('loan_css')
<style>
    .cbc-heading { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
    .cbc-heading h1 { margin:0; font-size:20px; font-weight:700; }
    .cbc-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .cbc-filters { display:flex; flex-wrap:wrap; gap:12px; align-items:end; padding:16px 0; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; }
    .cbc-filters label { display:block; font-size:12px; margin-bottom:4px; }
    .cbc-filters .form-control { min-width:140px; }
    .cbc-filters .cbc-search { flex:1; min-width:180px; }
    .cbc-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin:16px 0; }
    .cbc-stat { background:#fff; padding:14px; border:1px solid #e2e8f0; border-radius:8px; min-width:0; }
    .cbc-stat span { display:block; color:#64748b; font-size:12px; }
    .cbc-stat strong { display:block; font-size:18px; overflow-wrap:anywhere; }
    .cbc-table { background:#fff; }
    .cbc-table th { white-space:nowrap; font-size:12px; background:#f8fafc; }
    .cbc-table td { vertical-align:middle !important; font-size:13px; }
    .cbc-amount { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .cbc-quality { color:#9a6700; font-size:12px; min-width:120px; }
    .cbc-pagination { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
    @media (max-width:767px) { .cbc-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .cbc-filters > div { flex:1; } }
</style>
@endsection

@section('content_body')
@php
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
    $exportFilters = array_merge($filters, ['format' => 'csv']);
    unset($exportFilters['snapshot_date'], $exportFilters['cutoff_date']);
@endphp
<div class="cbc-heading">
    <h1><i class="fa fa-university" aria-hidden="true"></i> {{ $text('CBC Export', 'នាំចេញទិន្នន័យ CBC') }}</h1>
    <div class="cbc-actions">
        @if($canExport)
            <a href="{{ route('loan-management.reports.cbc-export', $exportFilters) }}" class="btn btn-success"><i class="fa fa-download"></i> {{ $text('Download CSV', 'ទាញយក CSV') }}</a>
        @endif
        <a href="{{ route('loan-management.reports.portfolio-at-risk') }}" class="btn btn-default"><i class="fa fa-pie-chart"></i> {{ $text('Portfolio at Risk', 'ហានិភ័យផលប័ត្រ') }}</a>
    </div>
</div>
<div class="alert alert-warning" role="status">
    {{ $text('Working export. Official CBC template mapping pending.', 'ឯកសារនាំចេញសម្រាប់រៀបចំ។ ការផ្គូផ្គងគំរូ CBC ផ្លូវការមិនទាន់បញ្ចប់។') }}
</div>
<p class="text-muted">
    {{ $text('Current balances, account status and DPD:', 'សមតុល្យ ស្ថានភាពគណនី និង DPD បច្ចុប្បន្ន៖') }} <strong>{{ $filters['snapshot_date'] }}</strong>.
    {{ $text('Payments through:', 'ការបង់ប្រាក់គិតត្រឹម៖') }} <strong>{{ $filters['cutoff_date'] }}</strong>.
    @if($filters['month'] !== now()->format('Y-m'))
        {{ $text('Historical month-end balance snapshots are not available.', 'មិនមានទិន្នន័យសមតុល្យប្រវត្តិនៅចុងខែ។') }}
    @endif
</p>
@if($errors->any())
    <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
@endif
<form method="GET" action="{{ route('loan-management.reports.cbc-export') }}" class="cbc-filters">
    <div><label for="cbcMonth">{{ $text('Reporting Month', 'ខែរបាយការណ៍') }}</label><input id="cbcMonth" type="month" name="month" class="form-control" value="{{ $filters['month'] }}" max="{{ now()->format('Y-m') }}" required></div>
    <div><label for="cbcLocation">{{ $text('Branch', 'សាខា') }}</label>
        <select id="cbcLocation" name="location_id" class="form-control">
            <option value="0">{{ $text('All Branches', 'គ្រប់សាខា') }}</option>
            @foreach($locations as $id => $name)<option value="{{ $id }}" @selected((int) $filters['location_id'] === (int) $id)>{{ $name }}</option>@endforeach
        </select>
    </div>
    <div class="cbc-search"><label for="cbcSearch">{{ $text('Search', 'ស្វែងរក') }}</label><input id="cbcSearch" name="search" class="form-control" value="{{ $filters['search'] }}" maxlength="100"></div>
    <div><label for="cbcPerPage">{{ $text('Rows', 'ចំនួនជួរ') }}</label><select id="cbcPerPage" name="per_page" class="form-control">@foreach([25,50,100] as $size)<option value="{{ $size }}" @selected($loans->perPage() === $size)>{{ $size }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> {{ $text('Apply', 'អនុវត្ត') }}</button>
    <a href="{{ route('loan-management.reports.cbc-export') }}" class="btn btn-default" title="{{ $text('Reset', 'កំណត់ឡើងវិញ') }}" aria-label="{{ $text('Reset', 'កំណត់ឡើងវិញ') }}"><i class="fa fa-undo"></i></a>
</form>
<div class="cbc-stats">
    <div class="cbc-stat"><span>{{ $text('Loan Accounts', 'គណនីកម្ចី') }}</span><strong>{{ number_format($summary['records']) }}</strong></div>
    <div class="cbc-stat"><span>{{ $text('Current Outstanding', 'សមតុល្យបច្ចុប្បន្ន') }}</span>@forelse($summary['currencies'] as $currency => $amounts)<strong>{{ $currency }} {{ number_format($amounts['balance'], 2) }}</strong>@empty<strong>0</strong>@endforelse</div>
    <div class="cbc-stat"><span>{{ $text('Current Overdue Accounts', 'គណនីហួសកំណត់បច្ចុប្បន្ន') }}</span><strong>{{ number_format($summary['overdue']) }}</strong></div>
    <div class="cbc-stat"><span>{{ $text('Missing Borrower Details', 'ព័ត៌មានអតិថិជនមិនគ្រប់') }}</span><strong>{{ number_format($summary['incomplete']) }}</strong></div>
</div>
<div class="table-responsive cbc-table">
    <table class="table table-striped table-hover" id="cbc_export_table">
        <thead><tr><th>{{ $text('Account', 'គណនី') }}</th><th>{{ $text('Borrower', 'អតិថិជន') }}</th><th>{{ $text('ID / Passport', 'អត្តសញ្ញាណប័ណ្ណ') }}</th><th>{{ $text('DOB / Gender', 'ថ្ងៃកំណើត / ភេទ') }}</th><th>{{ $text('Branch', 'សាខា') }}</th><th class="text-right">{{ $text('Principal', 'ប្រាក់ដើម') }}</th><th class="text-right">{{ $text('Current Balance', 'សមតុល្យបច្ចុប្បន្ន') }}</th><th class="text-right">{{ $text('Current Past Due', 'ប្រាក់ហួសកំណត់') }}</th><th>DPD</th><th>{{ $text('Status', 'ស្ថានភាព') }}</th><th>{{ $text('Last Repayment', 'បង់ប្រាក់ចុងក្រោយ') }}</th><th>{{ $text('Missing Details', 'ព័ត៌មានមិនគ្រប់') }}</th></tr></thead>
        <tbody>
        @forelse($loans as $row)
            <tr>
                <td><a href="{{ route('loan-management.loans.view', $row->id) }}" target="_blank" rel="noopener">{{ $row->loan_number ?: '#'.$row->id }}</a></td>
                <td><strong>{{ $row->customer_name_snapshot ?: ($row->customer_name_khmer ?: $row->customer_name_english) }}</strong><br><small class="text-muted">{{ $row->customer_phone_snapshot ?: '-' }}</small></td>
                <td>{{ $row->customer_national_id ?: '-' }}</td>
                <td>{{ $row->customer_dob ?: '-' }}<br>{{ $row->customer_gender ?: '-' }}</td>
                <td>{{ $row->location_name ?: '-' }}</td>
                <td class="text-right cbc-amount">{{ $row->currency }} {{ number_format($row->principal_amount, 2) }}</td>
                <td class="text-right cbc-amount">{{ $row->currency }} {{ number_format($row->balance_amount, 2) }}</td>
                <td class="text-right cbc-amount">{{ $row->currency }} {{ number_format($row->overdue_amount, 2) }}</td>
                <td><strong class="{{ $row->max_dpd > 0 ? 'text-danger' : 'text-success' }}">{{ $row->max_dpd }}</strong><br><small>{{ $row->dpd_bucket }}</small></td>
                <td>{{ ucfirst($row->status ?? '') }}</td>
                <td>{{ $row->last_payment_date ? substr($row->last_payment_date, 0, 10) : '-' }}<br><small class="cbc-amount">{{ $row->currency }} {{ number_format($row->total_repaid, 2) }}</small></td>
                <td class="cbc-quality">{{ implode(', ', $row->missing_details) ?: $text('Complete', 'គ្រប់គ្រាន់') }}</td>
            </tr>
        @empty
            <tr><td colspan="12" class="text-center text-muted">{{ $text('No records match the selected filters.', 'មិនមានទិន្នន័យត្រូវនឹងការចម្រោះ។') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="cbc-pagination">
    <small class="text-muted">{{ $loans->firstItem() ?? 0 }}–{{ $loans->lastItem() ?? 0 }} / {{ number_format($loans->total()) }}</small>
    {{ $loans->links() }}
</div>
@endsection
