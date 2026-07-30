@extends('hrsellmanagement::layouts.master')
@section('page_title', 'Staff Sell Report')
@section('module_content')
@php
    $hasActiveFilters = request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('branch_name') || request()->filled('sell_type') || request()->filled('seller_key') || request()->filled('period');
    $showLines = request()->boolean('show_lines');
@endphp
<style>
    #hr_staff_sell_filter_box {
        border: 1px solid #e7edf3;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        margin-bottom: 18px;
        overflow: hidden;
    }

    #hr_staff_sell_filter_box .box-header {
        cursor: pointer;
        padding: 14px 16px;
        background: #fff;
        border-bottom: 1px solid #eef2f6;
    }

    #hr_staff_sell_filter_box .box-title {
        color: #2f80aa;
        font-size: 18px;
        font-weight: 600;
    }

    #hr_staff_sell_filter_box .box-body {
        padding: 20px 28px 22px;
    }

    #hr_staff_sell_filter_box label {
        color: #111827;
        font-weight: 700;
        margin-bottom: 7px;
    }

    #hr_staff_sell_filter_box .select2-container {
        width: 100% !important;
    }

    #hr_staff_sell_filter_box .form-control,
    #hr_staff_sell_filter_box .select2-selection {
        min-height: 42px;
        border-color: #d9e0e8;
        border-radius: 0;
        box-shadow: none;
    }

    .hr-staff-sell-actions {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 8px;
        min-height: 67px;
    }

    .hr-staff-sell-summary .small-box {
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.08);
    }

    .hr-staff-rank-badge {
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

    .hr-staff-traffic-bar {
        min-width: 140px;
    }

    .hr-staff-traffic-track {
        height: 8px;
        background: #edf2f7;
        border-radius: 999px;
        overflow: hidden;
    }

    .hr-staff-traffic-fill {
        height: 100%;
        background: #2f80aa;
    }
</style>

<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_staff_sell_filter_box">
    <div class="box-header with-border hr-staff-sell-filter-toggle" role="button" tabindex="0" aria-controls="hr_staff_sell_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
        <h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool hr-staff-sell-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
        </div>
    </div>
    <div class="box-body" id="hr_staff_sell_filter_body" @unless($hasActiveFilters) style="display:none;" @endunless>
        <form method="get" action="{{ route('hr-sell.reports.staff') }}">
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
                            <input type="text" id="hr_staff_sell_date_range" class="form-control" readonly placeholder="{{ __('lang_v1.select_a_date_range') }}" value="{{ request('start_date') && request('end_date') ? request('start_date') . ' ~ ' . request('end_date') : '' }}">
                        </div>
                        <input type="hidden" name="start_date" id="hr_staff_sell_start_date" value="{{ request('start_date') }}">
                        <input type="hidden" name="end_date" id="hr_staff_sell_end_date" value="{{ request('end_date') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Location / Branch:</label>
                        <select name="branch_name" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrBranches as $branch => $name)
                                <option value="{{ $branch }}" @selected((string) request('branch_name') === (string) $branch)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Seller:</label>
                        <select name="seller_key" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrSellers as $key => $name)
                                <option value="{{ $key }}" @selected((string) request('seller_key') === (string) $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Sell Type:</label>
                        <select name="sell_type" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrSellTypes as $type => $name)
                                <option value="{{ $type }}" @selected((string) request('sell_type') === (string) $type)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Search:</label>
                        <input name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice, staff, product, IMEI">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox" style="margin-top: 7px;">
                            <label><input type="checkbox" name="show_lines" value="1" @checked($showLines)> Show sale lines</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hr-staff-sell-actions">
                        <button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                        <a class="btn btn-default" href="{{ route('hr-sell.reports.staff') }}">Reset</a>
                        <a class="btn btn-success" href="{{ route('hr-sell.reports.staff.export', array_merge(request()->all(), ['export_type' => 'summary'])) }}"><i class="fa fa-download"></i> Export Summary</a>
                        <a class="btn btn-info" href="{{ route('hr-sell.reports.staff.export', array_merge(request()->all(), ['export_type' => 'lines'])) }}"><i class="fa fa-download"></i> Export Lines</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row hr-staff-sell-summary">
    @foreach([
        'sale_count' => ['Sales', 'bg-aqua', 0],
        'line_count' => ['Lines', 'bg-blue', 0],
        'total_qty' => ['Total Qty', 'bg-yellow', 2],
        'average_price' => ['Avg Price', 'bg-purple', 2],
        'sale_total' => ['Total Amount', 'bg-green', 2],
    ] as $key => $meta)
        <div class="col-md-2 col-sm-4">
            <div class="small-box {{ $meta[1] }}">
                <div class="inner">
                    <h3>{{ number_format((float) ($totals[$key] ?? 0), $meta[2]) }}</h3>
                    <p>{{ $meta[0] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

@php
    $topSeller = $topSellers->first();
    $maxTrafficTotal = max((float) $trafficRows->max('sale_total'), 1);
@endphp

<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header"><h4>Top Seller Range</h4></div>
            <div class="box-body">
                @if($topSeller)
                    <h3 style="margin-top:0;">{{ $topSeller->staff_name }}</h3>
                    @if(! empty($topSeller->staff_code))
                        <p class="text-muted" style="margin-top:-8px;">{{ $topSeller->staff_code }}</p>
                    @endif
                    <table class="table table-condensed">
                        <tr><th>Rank</th><td class="text-right">#{{ $topSeller->rank }}</td></tr>
                        <tr><th>Sales</th><td class="text-right">{{ number_format((float) $topSeller->sale_count, 0) }}</td></tr>
                        <tr><th>Qty</th><td class="text-right">{{ number_format((float) $topSeller->total_qty, 2) }}</td></tr>
                        <tr><th>Total</th><td class="text-right">{{ number_format((float) $topSeller->sale_total, 2) }}</td></tr>
                    </table>
                @else
                    <p class="text-muted">No top seller for selected range.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="box box-success">
            <div class="box-header"><h4>Top Sellers Order</h4></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Staff</th>
                            <th class="text-right">Sales</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topSellers as $seller)
                            <tr>
                                <td><span class="hr-staff-rank-badge">{{ $seller->rank }}</span></td>
                                <td>{{ $seller->staff_name }} @if(! empty($seller->staff_code))<small class="text-muted">({{ $seller->staff_code }})</small>@endif</td>
                                <td class="text-right">{{ number_format((float) $seller->sale_count, 0) }}</td>
                                <td class="text-right">{{ number_format((float) $seller->sale_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No seller ranking for selected range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
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
                                <td class="hr-staff-traffic-bar">
                                    <div class="hr-staff-traffic-track">
                                        <div class="hr-staff-traffic-fill" style="width: {{ $trafficPercent }}%;"></div>
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
    </div>
</div>

<div class="box box-success">
    <div class="box-header"><h4>Staff Summary - {{ ucfirst($period) }}</h4></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                    <th>Staff</th>
                    <th>Branch</th>
                    <th class="text-right">Sales</th>
                    <th class="text-right">Lines</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Avg Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryRows as $row)
                    <tr>
                        <td>{{ $row->period_label }}</td>
                        <td>{{ $row->staff_name }} @if(! empty($row->staff_code))<small class="text-muted">({{ $row->staff_code }})</small>@endif</td>
                        <td>{{ $row->branch_name }}</td>
                        <td class="text-right">{{ number_format((float) $row->sale_count, 0) }}</td>
                        <td class="text-right">{{ number_format((float) $row->line_count, 0) }}</td>
                        <td class="text-right">{{ number_format((float) $row->total_qty, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $row->average_price, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $row->sale_total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">No staff sell data found for selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="clearfix">
            <div class="pull-left text-muted">Showing {{ $summaryRows->firstItem() ?? 0 }} to {{ $summaryRows->lastItem() ?? 0 }} of {{ $summaryRows->total() }} staff rows</div>
            <div class="pull-right">{{ $summaryRows->appends(request()->query())->links() }}</div>
        </div>
    </div>
</div>

@if($showLines)
    <div class="box box-info">
        <div class="box-header"><h4>Sale Lines</h4></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                        <th>Invoice</th>
                        <th>Sale Date</th>
                        <th>Staff</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Serial / IMEI</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lineRows as $row)
                        <tr>
                            <td>{{ $row->period_label }}</td>
                            <td><button type="button" class="btn btn-link btn-xs btn-modal" data-href="{{ route('hr-sell.sales.pos_detail', [$row->report_id]) }}" data-container=".view_modal">{{ $row->invoice_no }}</button></td>
                            <td>{{ $row->created_at }}</td>
                            <td>{{ $row->staff_name }} @if(! empty($row->staff_code))<small class="text-muted">({{ $row->staff_code }})</small>@endif</td>
                            <td>{{ $row->branch_name }}</td>
                            <td>{{ $row->service_type_label ?? ($row->service_type ?: '-') }}</td>
                            <td>{{ $row->product_name ?: '-' }}</td>
                            <td>{{ $row->sku ?: '-' }}</td>
                            <td>{{ $row->serial_identifier ?: '-' }}</td>
                            <td class="text-right">{{ number_format((float) $row->qty, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->unit_price, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted">No sale lines found for selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="clearfix">
                <div class="pull-left text-muted">Showing {{ $lineRows->firstItem() ?? 0 }} to {{ $lineRows->lastItem() ?? 0 }} of {{ $lineRows->total() }} sale lines</div>
                <div class="pull-right">{{ $lineRows->appends(request()->query())->links() }}</div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('module_js')
<script>
$(function() {
    $('.select2').select2();

    var dateRange = $('#hr_staff_sell_date_range');
    var startDate = $('#hr_staff_sell_start_date');
    var endDate = $('#hr_staff_sell_end_date');

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
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'This Year': [moment().startOf('year'), moment().endOf('year')]
            }
        });

        dateRange.on('apply.daterangepicker', function(ev, picker) {
            startDate.val(picker.startDate.format('YYYY-MM-DD'));
            endDate.val(picker.endDate.format('YYYY-MM-DD'));
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
        });

        dateRange.on('cancel.daterangepicker', function() {
            $(this).val('');
            startDate.val('');
            endDate.val('');
        });
    }

    var filterBox = $('#hr_staff_sell_filter_box');
    var filterBody = $('#hr_staff_sell_filter_body');
    var filterHeader = filterBox.find('.hr-staff-sell-filter-toggle');
    var filterButton = filterBox.find('.hr-staff-sell-filter-button');

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
