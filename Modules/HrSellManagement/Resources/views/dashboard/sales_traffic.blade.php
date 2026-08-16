@extends('hrsellmanagement::layouts.master')
@section('page_title', 'Sales Traffic Dashboard')
@section('module_content')
@php
    $hasActiveFilters = ! empty($filters['branch_name']) || ! empty($filters['department_id']) || ! empty($filters['sell_type']) || ! empty($filters['start_date']) || ! empty($filters['end_date']) || $period !== 'daily';
    $maxTrafficTotal = max((float) $trafficRows->max('sale_total'), 1);
    $maxLocationTotal = max((float) $locationCards->max('sale_total'), 1);
@endphp
<style>
    #hr_sales_traffic_filter_box {
        border: 1px solid #e7edf3;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        margin-bottom: 18px;
        overflow: hidden;
    }

    #hr_sales_traffic_filter_box .box-header {
        cursor: pointer;
        padding: 14px 16px;
        background: #fff;
        border-bottom: 1px solid #eef2f6;
    }

    #hr_sales_traffic_filter_box .box-title {
        color: #2f80aa;
        font-size: 18px;
        font-weight: 600;
    }

    #hr_sales_traffic_filter_box .box-body {
        padding: 20px 28px 22px;
    }

    #hr_sales_traffic_filter_box .select2-container {
        width: 100% !important;
    }

    .hr-sales-traffic-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        min-height: 67px;
    }

    .hr-sales-traffic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
        gap: 14px;
    }

    .hr-sales-traffic-card {
        border: 1px solid #e3ebf2;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        overflow: hidden;
    }

    .hr-sales-traffic-card-header {
        padding: 12px 14px;
        border-bottom: 1px solid #eef2f6;
        background: #f8fbfd;
    }

    .hr-sales-traffic-title {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
    }

    .hr-sales-traffic-track {
        height: 8px;
        background: #edf2f7;
        border-radius: 999px;
        overflow: hidden;
    }

    .hr-sales-traffic-fill {
        height: 100%;
        background: #2f80aa;
    }

    .hr-sales-traffic-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-top: 10px;
    }

    .hr-sales-traffic-stat {
        border: 1px solid #edf2f7;
        border-radius: 6px;
        padding: 8px;
        background: #fff;
    }

    .hr-sales-traffic-stat strong,
    .hr-sales-traffic-stat span {
        display: block;
    }

    .hr-sales-traffic-stat strong {
        color: #111827;
        font-size: 15px;
    }

    .hr-sales-traffic-stat span {
        color: #6b7280;
        font-size: 12px;
    }

    .hr-sales-rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #eef7fb;
        color: #24789f;
        font-weight: 700;
    }
</style>

@if(! $hrConnectionOk)
    <div class="alert alert-warning">Unable to load HR sales traffic data: {{ $hrConnectionMessage }}</div>
@endif

<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_sales_traffic_filter_box">
    <div class="box-header with-border hr-sales-traffic-filter-toggle" role="button" tabindex="0" aria-controls="hr_sales_traffic_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
        <h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool hr-sales-traffic-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
        </div>
    </div>
    <div class="box-body" id="hr_sales_traffic_filter_body" @unless($hasActiveFilters) style="display:none;" @endunless>
        <form method="get" action="{{ route('hr-sell.dashboard.sales_traffic') }}">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Period:</label>
                        <select name="period" class="form-control">
                            <option value="daily" @selected($period === 'daily')>Daily</option>
                            <option value="monthly" @selected($period === 'monthly')>Monthly</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range:</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                            <input type="text" id="hr_sales_traffic_date_range" class="form-control" readonly placeholder="{{ __('lang_v1.select_a_date_range') }}">
                        </div>
                        <input type="hidden" name="start_date" id="hr_sales_traffic_start_date" value="{{ $filters['start_date'] }}">
                        <input type="hidden" name="end_date" id="hr_sales_traffic_end_date" value="{{ $filters['end_date'] }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Location / Branch:</label>
                        <select name="branch_name" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrBranches as $branch => $name)
                                <option value="{{ $branch }}" @selected((string) ($filters['branch_name'] ?? '') === (string) $branch)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Department:</label>
                        <select name="department_id" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrDepartments as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" @selected((string) ($filters['department_id'] ?? '') === (string) $departmentId)>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Sell Type:</label>
                        <select name="sell_type" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrSellTypes as $type => $name)
                                <option value="{{ $type }}" @selected((string) ($filters['sell_type'] ?? '') === (string) $type)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="hr-sales-traffic-actions">
                        <button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                        <a class="btn btn-default" href="{{ route('hr-sell.dashboard.sales_traffic') }}">Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    @foreach([
        'sale_count' => ['Sales', 'bg-aqua', 0],
        'sale_total' => ['Total Amount', 'bg-green', 2],
        'average_sale' => ['Average Sale', 'bg-blue', 2],
        'location_count' => ['Locations', 'bg-purple', 0],
    ] as $key => $meta)
        <div class="col-md-3 col-sm-6">
            <div class="small-box {{ $meta[1] }}">
                <div class="inner">
                    <h3>{{ number_format((float) ($metrics[$key] ?? 0), $meta[2]) }}</h3>
                    <p>{{ $meta[0] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="box box-info">
    <div class="box-header"><h4>Sales Traffic - {{ ucfirst($period) }}</h4></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                    <th class="text-right">Sales</th>
                    <th class="text-right">Total</th>
                    <th>Traffic</th>
                </tr>
            </thead>
            <tbody>
                @forelse($trafficRows as $traffic)
                    @php($trafficPercent = min(100, round(((float) $traffic->sale_total / $maxTrafficTotal) * 100)))
                    <tr>
                        <td>{{ $traffic->period_label }}</td>
                        <td class="text-right">{{ number_format((float) $traffic->sale_count, 0) }}</td>
                        <td class="text-right">{{ number_format((float) $traffic->sale_total, 2) }}</td>
                        <td style="min-width:160px;">
                            <div class="hr-sales-traffic-track">
                                <div class="hr-sales-traffic-fill" style="width: {{ $trafficPercent }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No sales traffic for selected range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header"><h4>Location Traffic Cards</h4></div>
    <div class="box-body">
        @if($locationCards->isNotEmpty())
            <div class="hr-sales-traffic-grid">
                @foreach($locationCards as $locationCard)
                    @php($locationPercent = min(100, round(((float) $locationCard->sale_total / $maxLocationTotal) * 100)))
                    <div class="hr-sales-traffic-card">
                        <div class="hr-sales-traffic-card-header">
                            <h5 class="hr-sales-traffic-title">{{ $locationCard->branch_name }}</h5>
                            <div class="hr-sales-traffic-track" style="margin-top:10px;">
                                <div class="hr-sales-traffic-fill" style="width: {{ $locationPercent }}%;"></div>
                            </div>
                            <div class="hr-sales-traffic-stats">
                                <div class="hr-sales-traffic-stat">
                                    <strong>{{ number_format((float) $locationCard->sale_count, 0) }}</strong>
                                    <span>Sales</span>
                                </div>
                                <div class="hr-sales-traffic-stat">
                                    <strong>{{ number_format((float) $locationCard->sale_total, 2) }}</strong>
                                    <span>Total</span>
                                </div>
                                <div class="hr-sales-traffic-stat">
                                    <strong>{{ number_format((float) $locationCard->average_sale, 2) }}</strong>
                                    <span>Average</span>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" style="margin-bottom:0;">
                                <thead>
                                    <tr>
                                        <th>{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                                        <th>Rank</th>
                                        <th class="text-right">Sales</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($locationCard->rows as $row)
                                        <tr>
                                            <td>{{ $row->period_label }}</td>
                                            <td><span class="hr-sales-rank-badge">{{ $row->rank }}</span></td>
                                            <td class="text-right">{{ number_format((float) $row->sale_count, 0) }}</td>
                                            <td class="text-right">{{ number_format((float) $row->sale_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-center text-muted">No location traffic for selected range.</p>
        @endif
    </div>
</div>
@endsection

@section('module_js')
<script>
$(function() {
    $('.select2').select2();

    var dateRange = $('#hr_sales_traffic_date_range');
    var startDate = $('#hr_sales_traffic_start_date');
    var endDate = $('#hr_sales_traffic_end_date');

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
    }

    var filterBox = $('#hr_sales_traffic_filter_box');
    var filterBody = $('#hr_sales_traffic_filter_body');
    var filterHeader = filterBox.find('.hr-sales-traffic-filter-toggle');
    var filterButton = filterBox.find('.hr-sales-traffic-filter-button');

    var setFilterExpanded = function(expanded) {
        filterBox.toggleClass('collapsed-box', !expanded);
        filterBody.stop(true, true)[expanded ? 'slideDown' : 'slideUp'](150);
        filterHeader.attr('aria-expanded', expanded ? 'true' : 'false');
        filterButton.attr('title', expanded ? 'Collapse filters' : 'Expand filters');
        filterButton.find('i').toggleClass('fa-minus', expanded).toggleClass('fa-plus', !expanded);
    };

    filterHeader.on('click keydown', function(e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        e.preventDefault();
        setFilterExpanded(filterBox.hasClass('collapsed-box'));
    });
});
</script>
@endsection
