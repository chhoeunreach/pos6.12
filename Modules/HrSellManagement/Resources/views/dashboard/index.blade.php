@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell Dashboard')
@section('module_content')
<style>
    #hr_sell_dashboard_filter_box {
        border: 1px solid #e7edf3;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        overflow: hidden;
        margin-bottom: 18px;
    }

    #hr_sell_dashboard_filter_box .hr-sell-dashboard-filter-toggle {
        background: #fff;
        cursor: pointer;
        user-select: none;
        padding: 14px 16px;
        border-bottom: 1px solid #eef2f6;
    }

    #hr_sell_dashboard_filter_box .hr-sell-dashboard-filter-toggle:focus {
        outline: 2px solid #78b7dc;
        outline-offset: -2px;
    }

    #hr_sell_dashboard_filter_box .box-title {
        color: #6caed6;
        font-size: 18px;
        font-weight: 500;
    }

    #hr_sell_dashboard_filter_box .box-body {
        padding: 22px 30px 24px;
    }

    #hr_sell_dashboard_filter_box label {
        color: #111827;
        font-weight: 700;
        margin-bottom: 7px;
    }

    #hr_sell_dashboard_filter_box .select2-container {
        width: 100% !important;
    }

    #hr_sell_dashboard_filter_box .form-control,
    #hr_sell_dashboard_filter_box .select2-selection {
        min-height: 42px;
        border-color: #d9e0e8;
        border-radius: 0;
        box-shadow: none;
    }

    #hr_sell_dashboard_filter_box .input-group-addon {
        background: #f8fafc;
        border-color: #d9e0e8;
        color: #6caed6;
    }

    .hr-sell-dashboard-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        min-height: 67px;
    }

    .hr-sell-dashboard-card-link {
        color: inherit;
        display: block;
    }

    .hr-sell-dashboard-card-link:hover,
    .hr-sell-dashboard-card-link:focus {
        color: inherit;
        text-decoration: none;
    }

    .hr-sell-dashboard-card-link .small-box {
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }

    .hr-sell-dashboard-card-link:hover .small-box,
    .hr-sell-dashboard-card-link:focus .small-box {
        box-shadow: 0 6px 16px rgba(31, 45, 61, 0.16);
        transform: translateY(-1px);
    }

    .hr-sell-dashboard-row-link {
        color: inherit;
        display: block;
    }

    .hr-sell-dashboard-row-link:hover,
    .hr-sell-dashboard-row-link:focus {
        color: inherit;
        text-decoration: none;
    }

    .hr-sell-clickable-row:hover td {
        background: #f5fbff;
    }

    .hr-sell-report-links-disabled .hr-sell-dashboard-card-link,
    .hr-sell-report-links-disabled .hr-sell-dashboard-row-link {
        pointer-events: none;
        cursor: default;
    }
</style>

@unless($hrConnectionOk)
<div class="alert alert-warning">
    HR POS sell data is not available: {{ $hrConnectionMessage }}
</div>
@endunless

@php($canOpenReports = auth()->user()->can('hr_sell.report') || auth()->user()->can('superadmin') || auth()->user()->can('business_settings.access'))
@php($hasActiveFilters = ! empty($filters['branch_name']) || ! empty($filters['sell_type']) || (($filters['start_date'] ?? now()->toDateString()) !== now()->toDateString()) || (($filters['end_date'] ?? now()->toDateString()) !== now()->toDateString()))
<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_sell_dashboard_filter_box">
<div class="box-header with-border hr-sell-dashboard-filter-toggle" role="button" tabindex="0" aria-controls="hr_sell_dashboard_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
<h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
<div class="box-tools pull-right">
<button type="button" class="btn btn-box-tool hr-sell-dashboard-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
</div>
</div>
<div class="box-body" id="hr_sell_dashboard_filter_body" @unless($hasActiveFilters) style="display:none;" @endunless>
<form method="get" action="{{ route('hr-sell.dashboard') }}">
<div class="row {{ $canOpenReports ? '' : 'hr-sell-report-links-disabled' }}">
<div class="col-md-3"><div class="form-group"><label>Location / Branch:</label><select name="branch_name" class="form-control select2"><option value="">All</option>@foreach($hrBranches as $branch => $name)<option value="{{ $branch }}" @selected((string) ($filters['branch_name'] ?? '') === (string) $branch)>{{ $name }}</option>@endforeach</select></div></div>
<div class="col-md-3"><div class="form-group"><label>Sell Type:</label><select name="sell_type" class="form-control select2"><option value="">All</option>@foreach($hrSellTypes as $type => $name)<option value="{{ $type }}" @selected((string) ($filters['sell_type'] ?? '') === (string) $type)>{{ $name }}</option>@endforeach</select></div></div>
<div class="col-md-3"><div class="form-group"><label>Date Range:</label><div class="input-group"><span class="input-group-addon"><i class="fa fa-calendar"></i></span><input type="text" id="hr_sell_dashboard_date_range" class="form-control" readonly placeholder="{{ __('lang_v1.select_a_date_range') }}"></div><input type="hidden" name="start_date" id="hr_sell_dashboard_start_date" value="{{ $filters['start_date'] ?? now()->toDateString() }}"><input type="hidden" name="end_date" id="hr_sell_dashboard_end_date" value="{{ $filters['end_date'] ?? now()->toDateString() }}"></div></div>
<div class="col-md-3"><div class="hr-sell-dashboard-actions"><button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button> <a class="btn btn-default" href="{{ route('hr-sell.dashboard') }}">Today</a> @if($canOpenReports)<a class="btn btn-success" href="{{ route('hr-sell.reports.index', array_filter(['branch_name' => $filters['branch_name'] ?? null, 'sell_type' => $filters['sell_type'] ?? null, 'start_date' => $filters['start_date'] ?? null, 'end_date' => $filters['end_date'] ?? null])) }}"><i class="fa fa-bar-chart"></i> Full Report</a>@endif</div></div>
</div>
</form>
</div>
</div>

@php($dashboardReportFilters = array_filter(['branch_name' => $filters['branch_name'] ?? null, 'sell_type' => $filters['sell_type'] ?? null, 'start_date' => $filters['start_date'] ?? null, 'end_date' => $filters['end_date'] ?? null]))
<div class="row {{ $canOpenReports ? '' : 'hr-sell-report-links-disabled' }}">
@foreach([
    ['Sales Amount', $metrics['pos_filtered_sales'], 'bg-aqua', 'fa-money'],
    ['Sale Count', $metrics['pos_filtered_count'], 'bg-blue', 'fa-list'],
    ['Average Sale', $metrics['pos_average_sale'], 'bg-purple', 'fa-line-chart'],
    ['Branches', $metrics['pos_branch_count'], 'bg-navy', 'fa-map-marker'],
    ['Sellers', $metrics['pos_seller_count'], 'bg-olive', 'fa-users'],
] as $card)
<div class="col-md-2 col-sm-4">
<a class="hr-sell-dashboard-card-link" href="{{ route('hr-sell.reports.index', $dashboardReportFilters) }}">
<div class="small-box {{ $card[2] }}">
<div class="inner"><h3>{{ number_format((float) $card[1], in_array($card[0], ['Sale Count', 'Branches', 'Sellers']) ? 0 : 2) }}</h3><p>{{ $card[0] }}</p></div>
<div class="icon"><i class="fa {{ $card[3] }}"></i></div>
</div>
</a>
</div>
@endforeach
@php($topSellType = $topSellTypes->first())
<div class="col-md-2 col-sm-4">
<a class="hr-sell-dashboard-card-link" href="{{ route('hr-sell.reports.index', array_filter(array_merge($dashboardReportFilters, ['sell_type' => $topSellType->sell_type_key ?? ($filters['sell_type'] ?? null)]))) }}">
<div class="small-box bg-teal">
<div class="inner"><h3 style="font-size:24px;white-space:normal;">{{ $topSellType->sell_type_name ?? '-' }}</h3><p>Top Sell Type @if($topSellType) / {{ number_format((float) $topSellType->sale_count, 0) }} sales @endif</p></div>
<div class="icon"><i class="fa fa-tags"></i></div>
</div>
</a>
</div>
</div>

<div class="row {{ $canOpenReports ? '' : 'hr-sell-report-links-disabled' }}">
@foreach([
    ['Managed Sales', $metrics['managed_count'], 'bg-aqua', 'fa-check-square-o'],
    ['Pending Approval', $metrics['pending_approval'], 'bg-yellow', 'fa-clock-o'],
    ['Follow-ups Due', $metrics['followups_due'], 'bg-red', 'fa-phone'],
    ['Approved Commission', $metrics['commission_total'], 'bg-purple', 'fa-percent'],
    ['Managed Due', $metrics['due_total'], 'bg-maroon', 'fa-warning'],
] as $card)
<div class="col-md-2 col-sm-4">
<a class="hr-sell-dashboard-card-link" href="{{ route('hr-sell.reports.index', $dashboardReportFilters) }}">
<div class="small-box {{ $card[2] }}">
<div class="inner"><h3>{{ number_format((float) $card[1], in_array($card[0], ['Managed Sales', 'Pending Approval', 'Follow-ups Due', 'Sellers']) ? 0 : 2) }}</h3><p>{{ $card[0] }}</p></div>
<div class="icon"><i class="fa {{ $card[3] }}"></i></div>
</div>
</a>
</div>
@endforeach
</div>

<div class="row">
<div class="col-md-4">
<div class="box box-primary">
<div class="box-header"><h4>Top HR Staff</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Staff</th><th>Sales</th><th>Total</th></tr></thead>
<tbody>
@forelse($topHr as $row)
@php($staffReportUrl = route('hr-sell.reports.index', array_filter(array_merge($dashboardReportFilters, ['seller_key' => $row->seller_key ?? null]))))
<tr class="hr-sell-clickable-row">
<td><a class="hr-sell-dashboard-row-link" href="{{ $staffReportUrl }}">{{ $row->user_name }} @if(! empty($row->username))<small class="text-muted">({{ $row->username }})</small>@endif</a></td>
<td><a class="hr-sell-dashboard-row-link" href="{{ $staffReportUrl }}">{{ number_format((float) $row->sale_count, 0) }}</a></td>
<td><a class="hr-sell-dashboard-row-link" href="{{ $staffReportUrl }}">{{ number_format((float) $row->sale_total, 2) }}</a></td>
</tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No HR staff sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>

<div class="col-md-4">
<div class="box box-info">
<div class="box-header"><h4>Top Branches</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Branch</th><th>Sales</th><th>Total</th></tr></thead>
<tbody>
@forelse($topBranches as $row)
@php($branchReportUrl = route('hr-sell.reports.index', array_filter(array_merge($dashboardReportFilters, ['branch_name' => $row->branch_name === 'Unknown' ? null : $row->branch_name]))))
<tr class="hr-sell-clickable-row">
<td><a class="hr-sell-dashboard-row-link" href="{{ $branchReportUrl }}">{{ $row->branch_name }}</a></td>
<td><a class="hr-sell-dashboard-row-link" href="{{ $branchReportUrl }}">{{ number_format((float) $row->sale_count, 0) }}</a></td>
<td><a class="hr-sell-dashboard-row-link" href="{{ $branchReportUrl }}">{{ number_format((float) $row->sale_total, 2) }}</a></td>
</tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No branch sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>

<div class="col-md-4">
<div class="box box-success">
<div class="box-header"><h4>Top Sell Type</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Sell Type</th><th>Sales</th><th>Total</th></tr></thead>
<tbody>
@forelse($topSellTypes as $row)
@php($typeReportUrl = route('hr-sell.reports.index', array_filter(array_merge($dashboardReportFilters, ['sell_type' => $row->sell_type_key ?? null]))))
<tr class="hr-sell-clickable-row">
<td><a class="hr-sell-dashboard-row-link" href="{{ $typeReportUrl }}">{{ $row->sell_type_name }}</a></td>
<td><a class="hr-sell-dashboard-row-link" href="{{ $typeReportUrl }}">{{ number_format((float) $row->sale_count, 0) }}</a></td>
<td><a class="hr-sell-dashboard-row-link" href="{{ $typeReportUrl }}">{{ number_format((float) $row->sale_total, 2) }}</a></td>
</tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No sell type sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="box box-default">
<div class="box-header"><h4>Recent POS HR Sales</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="hr_sell_dashboard_recent_table">
<thead><tr><th>Invoice</th><th>Date</th><th>Branch</th><th>Customer</th><th>Phone</th><th>Seller</th><th>Type</th><th>Total</th></tr></thead>
<tbody>
@forelse($recent as $row)
<tr>
<td>{{ $row->invoice_no }}</td>
<td>{{ $row->created_at }}</td>
<td>{{ $row->branch_name }}</td>
<td>{{ $row->customer_name }}</td>
<td>{{ $row->customer_phone }}</td>
<td>{{ $row->staff_name ?: '-' }} @if(! empty($row->staff_code))<small class="text-muted">({{ $row->staff_code }})</small>@endif</td>
<td>{{ $row->service_type_label ?? $row->service_type }}</td>
<td>{{ number_format((float) $row->total_amount, 2) }}</td>
</tr>
@empty
<tr><td colspan="8" class="text-center text-muted">No recent POS HR sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
@endsection
@section('module_js')
<script>
$(function(){
    $('.select2').select2();

    var dateRange = $('#hr_sell_dashboard_date_range');
    var startDate = $('#hr_sell_dashboard_start_date');
    var endDate = $('#hr_sell_dashboard_end_date');

    if (dateRange.length && $.fn.daterangepicker) {
        var initialStart = startDate.val() ? moment(startDate.val(), 'YYYY-MM-DD') : moment();
        var initialEnd = endDate.val() ? moment(endDate.val(), 'YYYY-MM-DD') : moment();

        dateRange.daterangepicker({
            autoUpdateInput: false,
            startDate: initialStart,
            endDate: initialEnd,
            locale: {
                format: moment_date_format,
                cancelLabel: 'Clear'
            },
            ranges: {
                Today: [moment(), moment()],
                Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end) {
            dateRange.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            startDate.val(start.format('YYYY-MM-DD'));
            endDate.val(end.format('YYYY-MM-DD'));
        });

        dateRange.val(initialStart.format(moment_date_format) + ' ~ ' + initialEnd.format(moment_date_format));

        dateRange.on('cancel.daterangepicker', function() {
            dateRange.val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));
            startDate.val(moment().format('YYYY-MM-DD'));
            endDate.val(moment().format('YYYY-MM-DD'));
        });
    }

    var hasActiveFilters = {{ $hasActiveFilters ? 'true' : 'false' }};
    var filterBox = $('#hr_sell_dashboard_filter_box');
    var filterBody = $('#hr_sell_dashboard_filter_body');
    var filterHeader = filterBox.find('.hr-sell-dashboard-filter-toggle');
    var filterButton = filterBox.find('.hr-sell-dashboard-filter-button');
    var filterIcon = filterButton.find('i.fa');
    var storedFilterState = localStorage.getItem('hr_sell_dashboard_filters_expanded');

    function setFilterState(isExpanded) {
        filterBox.toggleClass('collapsed-box', !isExpanded);
        filterHeader.attr('aria-expanded', isExpanded ? 'true' : 'false');
        filterIcon.toggleClass('fa-minus', isExpanded).toggleClass('fa-plus', !isExpanded);
        filterButton.attr('title', isExpanded ? 'Collapse filters' : 'Expand filters');
        localStorage.setItem('hr_sell_dashboard_filters_expanded', isExpanded ? '1' : '0');
    }

    function toggleFilter(forceExpanded) {
        var isExpanded = typeof forceExpanded === 'boolean'
            ? forceExpanded
            : filterBox.hasClass('collapsed-box');

        if (isExpanded) {
            filterBody.stop(true, true).slideDown(180);
        } else {
            filterBody.stop(true, true).slideUp(180);
        }

        setFilterState(isExpanded);
    }

    if (! hasActiveFilters && storedFilterState === '1') {
        toggleFilter(true);
    }

    filterHeader.on('click keydown', function(e) {
        if ($(e.target).closest('button, a, input, select, textarea, .select2').length) {
            return;
        }

        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }

        e.preventDefault();
        toggleFilter();
    });

    filterButton.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleFilter();
    });

    if ($.fn.DataTable && ! $.fn.DataTable.isDataTable('#hr_sell_dashboard_recent_table')) {
        $('#hr_sell_dashboard_recent_table').DataTable({
            pageLength: 25,
            order: [],
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
        });
    }
});
</script>
@endsection
