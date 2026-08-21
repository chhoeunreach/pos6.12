@extends('hrsellmanagement::layouts.master')
@section('page_title', 'Staff Sell Report')
@section('module_content')
@php
    $hasActiveFilters = request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('branch_name') || request()->filled('department_id') || request()->filled('sell_type') || request()->filled('seller_key') || request()->filled('period');
    $showLines = request()->boolean('show_lines');
    $filterBodyStyle = $hasActiveFilters ? '' : 'display:none;';
    $printDate = request('start_date') && request('end_date')
        ? (request('start_date') === request('end_date') ? request('start_date') : request('start_date') . ' - ' . request('end_date'))
        : now()->toDateString();
    $saleLinesPrintTitle = 'របាយការណ៍ប្រចាំថ្ងៃ ' . $printDate . ' សាខា ' . (request('branch_name') ?: 'ទាំងអស់');
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

    .hr-staff-clickable-row {
        cursor: pointer;
    }

    .hr-staff-clickable-row:hover > td {
        background: #eef7fb !important;
    }

    #hr_staff_sell_lines td[rowspan] {
        vertical-align: middle !important;
    }

    .hr-staff-line-group-qty {
        display: block;
        margin-top: 4px;
        color: #6b7280;
        font-size: 12px;
        font-weight: 600;
    }

    .hr-staff-lines-tools {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
        margin-bottom: 12px;
    }

    .hr-staff-lines-colvis-menu {
        max-height: 280px;
        overflow-y: auto;
        padding: 8px 12px;
    }

    .hr-staff-lines-colvis-menu label {
        display: block;
        font-weight: 400;
        margin: 5px 0;
        white-space: nowrap;
    }

</style>

<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_staff_sell_filter_box">
    <div class="box-header with-border hr-staff-sell-filter-toggle" role="button" tabindex="0" aria-controls="hr_staff_sell_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
        <h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool hr-staff-sell-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
        </div>
    </div>
    <div class="box-body" id="hr_staff_sell_filter_body" style="{{ $filterBodyStyle }}">
        <form method="get" action="{{ route('hr-sell.reports.staff') }}">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Period:</label>
                        <select name="period" class="form-control">
                            <option value="daily" {{ $period === 'daily' ? 'selected' : '' }}>Daily</option>
                            <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>Monthly</option>
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
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Location / Branch:</label>
                        <select name="branch_name" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrBranches as $branch => $name)
                                <option value="{{ $branch }}" {{ (string) request('branch_name') === (string) $branch ? 'selected' : '' }}>{{ $name }}</option>
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
                                <option value="{{ $departmentId }}" {{ (string) request('department_id') === (string) $departmentId ? 'selected' : '' }}>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Seller:</label>
                        <select name="seller_key" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrSellers as $key => $name)
                                <option value="{{ $key }}" {{ (string) request('seller_key') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
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
                                <option value="{{ $type }}" {{ (string) request('sell_type') === (string) $type ? 'selected' : '' }}>{{ $name }}</option>
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
                            <label><input type="checkbox" name="show_lines" value="1" {{ $showLines ? 'checked' : '' }}> Show sale lines</label>
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
                @foreach(collect([$topSeller])->filter() as $topSeller)
                    <h3 style="margin-top:0;">{{ $topSeller->staff_name }}</h3>
                    {!! ! empty($topSeller->staff_code) ? '<p class="text-muted" style="margin-top:-8px;">' . e($topSeller->staff_code) . '</p>' : '' !!}
                    <table class="table table-condensed">
                        <tr><th>Rank</th><td class="text-right">#{{ $topSeller->rank }}</td></tr>
                        <tr><th>Sales</th><td class="text-right">{{ number_format((float) $topSeller->sale_count, 0) }}</td></tr>
                        <tr><th>Qty</th><td class="text-right">{{ number_format((float) $topSeller->total_qty, 2) }}</td></tr>
                        <tr><th>Total</th><td class="text-right">{{ number_format((float) $topSeller->sale_total, 2) }}</td></tr>
                    </table>
                @endforeach
                {!! empty($topSeller) ? '<p class="text-muted">No top seller for selected range.</p>' : '' !!}
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
                        @foreach($topSellers as $seller)
                            <tr>
                                <td><span class="hr-staff-rank-badge">{{ $seller->rank }}</span></td>
                                <td>
                                    {{ $seller->staff_name }}
                                    {!! ! empty($seller->staff_code) ? '<small class="text-muted">(' . e($seller->staff_code) . ')</small>' : '' !!}
                                </td>
                                <td class="text-right">{{ number_format((float) $seller->sale_count, 0) }}</td>
                                <td class="text-right">{{ number_format((float) $seller->sale_total, 2) }}</td>
                            </tr>
                        @endforeach
                        {!! $topSellers->isEmpty() ? '<tr><td colspan="4" class="text-center text-muted">No seller ranking for selected range.</td></tr>' : '' !!}
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
                        @foreach($trafficRows as $traffic)
                            @php($trafficPercent = min(100, round(((float) $traffic->sale_total / $maxTrafficTotal) * 100)))
                            <tr>
                                <td>{{ $traffic->period_label ?? '-' }}</td>
                                <td class="text-right">{{ number_format((float) $traffic->sale_count, 0) }}</td>
                                <td class="text-right">{{ number_format((float) $traffic->sale_total, 2) }}</td>
                                <td class="hr-staff-traffic-bar">
                                    <div class="hr-staff-traffic-track">
                                        <div class="hr-staff-traffic-fill" style="width: {{ $trafficPercent }}%;"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        {!! $trafficRows->isEmpty() ? '<tr><td colspan="4" class="text-center text-muted">No sales traffic for selected range.</td></tr>' : '' !!}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="box box-success">
    <div class="box-header"><h4>Staff Summary - {{ ucfirst($period) }}</h4></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped" id="hr_staff_summary_table">
            <thead>
                <tr>
                    <th>{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                    <th>Staff</th>
                    <th>Branch</th>
                    <th class="text-right">Invoice Qty</th>
                    <th class="text-right">Products Qty</th>
                    <th class="text-right">Avg Price</th>
                    <th class="text-right">Total</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summaryRows as $row)
                    <tr class="hr-staff-clickable-row" data-href="{{ $row->detail_url }}">
                        <td>{{ $row->period_label ?? '-' }}</td>
                        <td>
                            {{ $row->staff_name }}
                            {!! ! empty($row->staff_code) ? '<small class="text-muted">(' . e($row->staff_code) . ')</small>' : '' !!}
                        </td>
                        <td>{{ $row->branch_name }}</td>
                        <td class="text-right">{{ number_format((float) $row->sale_count, 0) }}</td>
                        <td class="text-right">{{ number_format((float) $row->total_qty, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $row->average_price, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $row->sale_total, 2) }}</td>
                        <td><a class="btn btn-xs btn-primary" href="{{ $row->detail_url }}"><i class="fa fa-eye"></i> View</a></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th></th>
                    <th></th>
                    <th class="text-right">Total</th>
                    <th class="text-right">{{ number_format((float) ($totals['sale_count'] ?? 0), 0) }}</th>
                    <th class="text-right">{{ number_format((float) ($totals['total_qty'] ?? 0), 2) }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

    <div class="box box-info" id="hr_staff_sell_lines" style="{{ $showLines ? '' : 'display:none;' }}">
        <div class="box-header"><h4>Sale Lines</h4></div>
        <div class="box-body">
            <div class="hr-staff-lines-tools">
                <button type="button" class="btn btn-default btn-sm" id="hr_staff_lines_print">
                    <i class="fa fa-print"></i> Print
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa fa-columns"></i> Column visibility <span class="caret"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right hr-staff-lines-colvis-menu" id="hr_staff_lines_colvis_menu"></div>
                </div>
            </div>

            <ul class="nav nav-tabs" role="tablist">
                <li class="active"><a href="#hr_staff_sell_lines_current" role="tab" data-toggle="tab">Sale Lines</a></li>
                <li><a href="#hr_staff_sell_lines_v1" role="tab" data-toggle="tab">Sale Lines v1</a></li>
            </ul>

            <div class="tab-content" style="padding-top: 15px;">
                <div class="tab-pane active table-responsive" id="hr_staff_sell_lines_current">
                    <table class="table table-bordered table-striped" id="hr_staff_sell_lines_current_table">
                        <thead>
                            <tr>
                                <th data-line-column="0">{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                                <th data-line-column="1">Invoice</th>
                                <th data-line-column="2">Sale Date</th>
                                <th data-line-column="3">Staff</th>
                                <th data-line-column="4">Branch</th>
                                <th data-line-column="5">Type</th>
                                <th data-line-column="6">Product</th>
                                <th data-line-column="7">SKU</th>
                                <th data-line-column="8">Serial / IMEI</th>
                                <th class="text-right" data-line-column="9">Qty</th>
                                <th class="text-right" data-line-column="10">Price</th>
                                <th class="text-right" data-line-column="11">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lineRows as $row)
                                <tr class="hr-staff-clickable-row hr-staff-line-detail-row" data-href="{{ $row->detail_url ?? '' }}">
                                    <td data-line-column="0">{{ $row->period_label ?? '-' }}</td>
                                    <td data-line-column="1"><button type="button" class="btn btn-link btn-xs btn-modal" data-href="{{ $row->detail_url ?? '' }}" data-container=".view_modal">{{ $row->invoice_no ?? '-' }}</button></td>
                                    <td data-line-column="2">{{ $row->created_at }}</td>
                                    <td class="hr-staff-line-group-cell" data-line-column="3">
                                        {{ $row->staff_name }}
                                        {!! ! empty($row->staff_code) ? '<small class="text-muted">(' . e($row->staff_code) . ')</small>' : '' !!}
                                    </td>
                                    <td class="hr-staff-line-group-cell" data-line-column="4">{{ $row->branch_name }}</td>
                                    <td class="hr-staff-line-group-cell" data-line-column="5">{{ $row->service_type_label ?? ($row->service_type ?: '-') }}</td>
                                    <td data-line-column="6">{{ $row->product_name ?: '-' }}</td>
                                    <td data-line-column="7">{{ $row->sku ?: '-' }}</td>
                                    <td data-line-column="8">{{ $row->serial_identifier ?: '-' }}</td>
                                    <td class="text-right" data-line-column="9">{{ number_format((float) $row->qty, 2) }}</td>
                                    <td class="text-right" data-line-column="10">{{ number_format((float) $row->unit_price, 2) }}</td>
                                    <td class="text-right" data-line-column="11">{{ number_format((float) $row->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                            {!! count($lineRows) === 0 ? '<tr><td colspan="12" class="text-center text-muted">No sale lines found for selected filters.</td></tr>' : '' !!}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th data-line-column="0"></th>
                                <th data-line-column="1"></th>
                                <th data-line-column="2"></th>
                                <th data-line-column="3"></th>
                                <th data-line-column="4"></th>
                                <th data-line-column="5"></th>
                                <th data-line-column="6"></th>
                                <th data-line-column="7"></th>
                                <th class="text-right" data-line-column="8">Total</th>
                                <th class="text-right hr-staff-lines-qty-total" data-line-column="9">0.00</th>
                                <th class="text-right hr-staff-lines-price-total" data-line-column="10">0.00</th>
                                <th class="text-right hr-staff-lines-total-total" data-line-column="11">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="tab-pane table-responsive" id="hr_staff_sell_lines_v1">
                    <table class="table table-bordered table-striped" id="hr_staff_sell_lines_v1_table">
                        <thead>
                            <tr>
                                <th data-line-column="0">{{ $period === 'monthly' ? 'Month' : 'Date' }}</th>
                                <th data-line-column="1">Invoice</th>
                                <th data-line-column="2">Sale Date</th>
                                <th data-line-column="3">Staff</th>
                                <th data-line-column="4">Branch</th>
                                <th data-line-column="5">Type</th>
                                <th data-line-column="6">Product</th>
                                <th data-line-column="7">SKU</th>
                                <th data-line-column="8">Serial / IMEI</th>
                                <th class="text-right" data-line-column="9">Qty</th>
                                <th class="text-right" data-line-column="10">Price</th>
                                <th class="text-right" data-line-column="11">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lineRows as $row)
                                <tr class="hr-staff-clickable-row hr-staff-line-detail-row" data-href="{{ $row->detail_url ?? '' }}">
                                    <td data-line-column="0">{{ $row->period_label ?? '-' }}</td>
                                    <td data-line-column="1"><button type="button" class="btn btn-link btn-xs btn-modal" data-href="{{ $row->detail_url ?? '' }}" data-container=".view_modal">{{ $row->invoice_no ?? '-' }}</button></td>
                                    <td data-line-column="2">{{ $row->created_at }}</td>
                                    <td data-line-column="3">
                                        {{ $row->staff_name }}
                                        {!! ! empty($row->staff_code) ? '<small class="text-muted">(' . e($row->staff_code) . ')</small>' : '' !!}
                                    </td>
                                    <td data-line-column="4">{{ $row->branch_name }}</td>
                                    <td data-line-column="5">{{ $row->service_type_label ?? ($row->service_type ?: '-') }}</td>
                                    <td data-line-column="6">{{ $row->product_name ?: '-' }}</td>
                                    <td data-line-column="7">{{ $row->sku ?: '-' }}</td>
                                    <td data-line-column="8">{{ $row->serial_identifier ?: '-' }}</td>
                                    <td class="text-right" data-line-column="9">{{ number_format((float) $row->qty, 2) }}</td>
                                    <td class="text-right" data-line-column="10">{{ number_format((float) $row->unit_price, 2) }}</td>
                                    <td class="text-right" data-line-column="11">{{ number_format((float) $row->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                            {!! count($lineRows) === 0 ? '<tr><td colspan="12" class="text-center text-muted">No sale lines found for selected filters.</td></tr>' : '' !!}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th data-line-column="0"></th>
                                <th data-line-column="1"></th>
                                <th data-line-column="2"></th>
                                <th data-line-column="3"></th>
                                <th data-line-column="4"></th>
                                <th data-line-column="5"></th>
                                <th data-line-column="6"></th>
                                <th data-line-column="7"></th>
                                <th class="text-right" data-line-column="8">Total</th>
                                <th class="text-right hr-staff-lines-v1-qty-total" data-line-column="9">0.00</th>
                                <th class="text-right hr-staff-lines-v1-price-total" data-line-column="10">0.00</th>
                                <th class="text-right hr-staff-lines-v1-total-total" data-line-column="11">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="clearfix">
                <div class="pull-left text-muted">Showing {{ $lineRows->firstItem() ?? 0 }} to {{ $lineRows->lastItem() ?? 0 }} of {{ $lineRows->total() }} sale lines</div>
                <div class="pull-right">{{ $lineRows->appends(request()->query())->links() }}</div>
            </div>
        </div>
    </div>
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

    if ($.fn.DataTable && ! $.fn.DataTable.isDataTable('#hr_staff_summary_table')) {
        $('#hr_staff_summary_table').DataTable({
            paging: true,
            pageLength: parseInt(window.__default_datatable_page_entries || 25, 10),
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            searching: true,
            ordering: true,
            responsive: false,
            autoWidth: false,
            dom: '<"row"<"col-sm-3"l><"col-sm-6 text-center"B><"col-sm-3"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>',
            buttons: [
                { extend: 'copy', text: 'Copy' },
                { extend: 'csv', text: 'Export CSV' },
                { extend: 'excel', text: 'Export Excel' },
                { extend: 'print', text: 'Print' },
                { extend: 'colvis', text: 'Column visibility' },
                {
                    extend: 'pdf',
                    text: 'Export PDF',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    title: 'HR Staff Sell Summary'
                }
            ],
            order: [],
            footerCallback: function() {
                var api = this.api();
                var parseNumber = function(value) {
                    if (typeof value === 'number') {
                        return value;
                    }

                    return parseFloat(String(value || '').replace(/,/g, '')) || 0;
                };
                var formatNumber = function(value, decimals) {
                    return value.toLocaleString(undefined, {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals
                    });
                };
                var totalColumn = function(index) {
                    return api
                        .column(index, { search: 'applied' })
                        .data()
                        .reduce(function(total, value) {
                            return total + parseNumber(value);
                        }, 0);
                };

                $(api.column(3).footer()).html(formatNumber(totalColumn(3), 0));
                $(api.column(4).footer()).html(formatNumber(totalColumn(4), 2));
            }
        });
    }

    var mergeSaleLineGroupCells = function() {
        var previousKey = null;
        var $groupCells = null;
        var $groupQty = null;
        var groupQtyTotal = 0;
        var rowspan = 1;
        var parseNumber = function(value) {
            return parseFloat(String(value || '').replace(/,/g, '')) || 0;
        };
        var formatNumber = function(value) {
            return value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        var updateGroupQty = function() {
            if ($groupQty) {
                $groupQty.text('Qty: ' + formatNumber(groupQtyTotal));
            }
        };

        $('#hr_staff_sell_lines_current tbody tr').each(function() {
            var $row = $(this);
            var $cells = $row.find('td.hr-staff-line-group-cell');

            if ($cells.length !== 3) {
                previousKey = null;
                $groupCells = null;
                $groupQty = null;
                groupQtyTotal = 0;
                rowspan = 1;
                return;
            }

            var rowQty = parseNumber($row.children('[data-line-column="9"]').clone().children().remove().end().text());
            var groupKey = $.trim($cells.eq(0).text()) + '|' + $.trim($cells.eq(1).text()) + '|' + $.trim($cells.eq(2).text());

            if (groupKey === previousKey && $groupCells) {
                rowspan++;
                groupQtyTotal += rowQty;
                $groupCells.attr('rowspan', rowspan);
                updateGroupQty();
                $cells.remove();
                return;
            }

            previousKey = groupKey;
            $groupCells = $cells;
            groupQtyTotal = rowQty;
            $groupQty = $('<span class="hr-staff-line-group-qty"></span>');
            $cells.eq(0).append($groupQty);
            updateGroupQty();
            rowspan = 1;
        });
    };

    var updateSaleLinesFooter = function(tableSelector, containerSelector, totalClassPrefix) {
        var totals = {
            qty: 0,
            price: 0,
            total: 0
        };
        var parseNumber = function(value) {
            return parseFloat(String(value || '').replace(/,/g, '')) || 0;
        };
        var formatNumber = function(value) {
            return value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        $(tableSelector + ' tbody tr').each(function() {
            var $row = $(this);

            if (! $row.children('[data-line-column]').length) {
                return;
            }

            totals.qty += parseNumber($row.children('[data-line-column="9"]').clone().children().remove().end().text());
            totals.price += parseNumber($row.children('[data-line-column="10"]').clone().children().remove().end().text());
            totals.total += parseNumber($row.children('[data-line-column="11"]').clone().children().remove().end().text());
        });

        $(containerSelector + ' .' + totalClassPrefix + '-qty-total').text(formatNumber(totals.qty));
        $(containerSelector + ' .' + totalClassPrefix + '-price-total').text(formatNumber(totals.price));
        $(containerSelector + ' .' + totalClassPrefix + '-total-total').text(formatNumber(totals.total));
    };

    var updateSaleLinesFooters = function() {
        updateSaleLinesFooter('#hr_staff_sell_lines_current_table', '#hr_staff_sell_lines_current', 'hr-staff-lines');
        updateSaleLinesFooter('#hr_staff_sell_lines_v1_table', '#hr_staff_sell_lines_v1', 'hr-staff-lines-v1');
    };

    updateSaleLinesFooters();
    mergeSaleLineGroupCells();

    if (window.location.hash === '#hr_staff_sell_lines_v1') {
        $('#hr_staff_sell_lines a[href="#hr_staff_sell_lines_v1"]').tab('show');
    } else if (window.location.hash === '#hr_staff_sell_lines_current') {
        $('#hr_staff_sell_lines a[href="#hr_staff_sell_lines_current"]').tab('show');
    }

    var saleLineColumnVisibility = [];
    var saleLineHeaders = [];

    $('#hr_staff_sell_lines_v1_table thead th').each(function(index) {
        saleLineHeaders[index] = $.trim($(this).text());
        saleLineColumnVisibility[index] = true;
        $('#hr_staff_lines_colvis_menu').append(
            $('<label></label>').append(
                $('<input type="checkbox" checked>').attr('data-column-index', index),
                ' ' + saleLineHeaders[index]
            )
        );
    });

    var setSaleLineColumnVisibility = function(index, visible) {
        saleLineColumnVisibility[index] = visible;

        $('#hr_staff_sell_lines_current_table, #hr_staff_sell_lines_v1_table')
            .find('[data-line-column="' + index + '"]')
            .toggle(visible);

        updateSaleLinesFooters();
    };

    $('#hr_staff_lines_colvis_menu').on('click', function(e) {
        e.stopPropagation();
    });

    $('#hr_staff_lines_colvis_menu').on('change', 'input[type="checkbox"]', function() {
        setSaleLineColumnVisibility(parseInt($(this).data('column-index'), 10), $(this).is(':checked'));
    });

    $('#hr_staff_lines_print').on('click', function() {
        var activeSelector = $('#hr_staff_sell_lines .tab-pane.active').is('#hr_staff_sell_lines_v1')
            ? '#hr_staff_sell_lines_v1_table'
            : '#hr_staff_sell_lines_current_table';
        var $sourceTable = $(activeSelector);
        var $printTable = $sourceTable.clone();
        $printTable.find('[data-line-column]').each(function() {
            var columnIndex = parseInt($(this).attr('data-line-column'), 10);

            if (! saleLineColumnVisibility[columnIndex]) {
                $(this).remove();
                return;
            }

            $(this).removeAttr('style');
        });

        var printTitle = @json($saleLinesPrintTitle);
        var printWindow = window.open('', '_blank');
        if (! printWindow) {
            window.print();
            return;
        }

        printWindow.document.open();
        printWindow.document.write(
            '<!doctype html><html><head><meta charset="utf-8"><title>' + printTitle + '</title>' +
            '<style>' +
                '@page{margin:14mm 10mm;}' +
                'body{background:#fff;color:#1f2937;font-family:Arial,"Khmer OS Battambang","Khmer OS",sans-serif;font-size:12px;line-height:1.35;margin:0;}' +
                '.report-header{border-bottom:3px solid #2563eb;margin-bottom:14px;padding-bottom:10px;}' +
                '.report-title{color:#111827;font-size:20px;font-weight:700;margin:0;text-align:center;}' +
                '.report-subtitle{color:#64748b;font-size:11px;margin-top:4px;text-align:center;}' +
                'table{border-collapse:collapse;width:100%;}' +
                'th,td{border:1px solid #cbd5e1;padding:6px 7px;vertical-align:top;}' +
                'thead th{background:#e8f0fe;color:#1e3a8a;font-weight:700;text-align:left;}' +
                'tbody tr:nth-child(even) td{background:#f8fafc;}' +
                'tfoot th,tfoot td{background:#dbeafe;color:#0f172a;font-weight:700;}' +
                '.text-right{text-align:right;}' +
                '.btn{background:none;border:0;color:#1f2937;padding:0;}' +
                'a{color:#1f2937;text-decoration:none;}' +
                'small{color:#64748b;}' +
                '.hr-staff-line-group-qty{color:#2563eb;display:block;font-size:11px;font-weight:700;margin-top:4px;}' +
                '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}.report-header{break-after:avoid;}tr{break-inside:avoid;}}' +
            '</style>' +
            '</head><body><div class="report-header"><h3 class="report-title">' + printTitle + '</h3><div class="report-subtitle">HR Sell Report</div></div>' + $printTable.prop('outerHTML') + '</body></html>'
        );
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    });

    $(document).on('click', '.hr-staff-clickable-row', function(e) {
        if ($(e.target).closest('a, button, input, select, textarea, label').length) {
            return;
        }

        var href = $(this).data('href');
        if (!href) {
            return;
        }

        if ($(this).hasClass('hr-staff-line-detail-row')) {
            $('.view_modal').load(href, function() {
                $(this).modal('show');
            });
            return;
        }

        window.location.href = href;
    });
});
</script>
@endsection
