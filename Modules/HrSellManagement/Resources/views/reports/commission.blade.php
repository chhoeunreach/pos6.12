@extends('hrsellmanagement::layouts.master')
@section('page_title', 'Commission Report')
@section('module_content')
@php
    $hasActiveFilters = request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('branch_name') || request()->filled('department_id') || request()->filled('sell_type') || request()->filled('seller_key');
@endphp
<style>
    #hr_commission_filter_box {
        border: 1px solid #e7edf3;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        margin-bottom: 18px;
        overflow: hidden;
    }

    #hr_commission_filter_box .box-header {
        background: #fff;
        border-bottom: 1px solid #eef2f6;
        cursor: pointer;
        padding: 14px 16px;
    }

    #hr_commission_filter_box .box-title {
        color: #2f80aa;
        font-size: 18px;
        font-weight: 600;
    }

    #hr_commission_filter_box .box-body {
        padding: 20px 28px 22px;
    }

    #hr_commission_filter_box label {
        color: #111827;
        font-weight: 700;
        margin-bottom: 7px;
    }

    #hr_commission_filter_box .select2-container {
        width: 100% !important;
    }

    #hr_commission_filter_box .form-control,
    #hr_commission_filter_box .select2-selection {
        border-color: #d9e0e8;
        border-radius: 0;
        box-shadow: none;
        min-height: 42px;
    }

    .hr-commission-actions {
        align-items: flex-end;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-height: 67px;
    }

    .hr-commission-summary .small-box {
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.08);
    }

    .hr-commission-table th,
    .hr-commission-table td {
        vertical-align: middle !important;
    }
</style>

<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_commission_filter_box">
    <div class="box-header with-border hr-commission-filter-toggle" role="button" tabindex="0" aria-controls="hr_commission_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
        <h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool hr-commission-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
        </div>
    </div>
    <div class="box-body" id="hr_commission_filter_body" @unless($hasActiveFilters) style="display:none;" @endunless>
        <form method="get" action="{{ route('hr-sell.reports.commission') }}">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range:</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                            <input type="text" id="hr_commission_date_range" class="form-control" readonly placeholder="{{ __('lang_v1.select_a_date_range') }}" value="{{ request('start_date') && request('end_date') ? request('start_date') . ' ~ ' . request('end_date') : '' }}">
                        </div>
                        <input type="hidden" name="start_date" id="hr_commission_start_date" value="{{ request('start_date') }}">
                        <input type="hidden" name="end_date" id="hr_commission_end_date" value="{{ request('end_date') }}">
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
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Department:</label>
                        <select name="department_id" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrDepartments as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" @selected((string) request('department_id') === (string) $departmentId)>{{ $departmentName }}</option>
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
                <div class="col-md-6">
                    <div class="hr-commission-actions">
                        <button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                        <a class="btn btn-default" href="{{ route('hr-sell.reports.commission') }}">Reset</a>
                        <a class="btn btn-success" href="{{ route('hr-sell.reports.commission.export', request()->all()) }}"><i class="fa fa-download"></i> Export</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row hr-commission-summary">
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-aqua">
            <div class="inner"><h3>{{ number_format((float) ($commissionTotals['sale_count'] ?? 0), 0) }}</h3><p>Sales</p></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-blue">
            <div class="inner"><h3>{{ number_format((float) ($commissionTotals['line_count'] ?? 0), 0) }}</h3><p>Lines</p></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-yellow">
            <div class="inner"><h3>{{ number_format((float) ($commissionTotals['total_qty'] ?? 0), 2) }}</h3><p>Total Qty</p></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-green">
            <div class="inner"><h3>{{ number_format((float) ($commissionTotals['total_amount'] ?? 0), 2) }}</h3><p>Total Amount</p></div>
        </div>
    </div>
</div>

<div class="box box-success">
    <div class="box-header"><h4>Commission Report</h4></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped hr-commission-table" id="hr_commission_table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Staff</th>
                    <th>Branch</th>
                    @foreach($commissionColumns as $column)
                        <th class="text-right">{{ $column['label'] }}</th>
                        @if($column['has_commission'])
                            <th class="text-right">Commission</th>
                        @endif
                    @endforeach
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($commissionRows as $row)
                    <tr>
                        <td>{{ $row->staff_code ?: '-' }}</td>
                        <td>{{ $row->staff_name }}</td>
                        <td>{{ $row->branch_name }}</td>
                        @foreach($commissionColumns as $column)
                            <td class="text-right">{{ number_format((float) ($row->{$column['key'] . '_total'} ?? 0), ($column['commission_basis'] ?? '') === 'invoice' ? 0 : 2) }}</td>
                            @if($column['has_commission'])
                                <td class="text-right">{{ number_format((float) ($row->{$column['key'] . '_commission_total'} ?? 0), 2) }}</td>
                            @endif
                        @endforeach
                        <td class="text-right">{{ number_format((float) ($row->commission_total ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                {!! count($commissionRows) === 0 ? '<tr><td colspan="' . (count($commissionColumns) + collect($commissionColumns)->where('has_commission', true)->count() + 4) . '" class="text-center text-muted">No commission rows found for selected filters.</td></tr>' : '' !!}
            </tbody>
            <tfoot>
                <tr>
                    <th></th>
                    <th></th>
                    <th class="text-right">Total</th>
                    @foreach($commissionColumns as $column)
                        <th class="text-right">{{ number_format((float) ($commissionTotals[$column['key'] . '_total'] ?? 0), ($column['commission_basis'] ?? '') === 'invoice' ? 0 : 2) }}</th>
                        @if($column['has_commission'])
                            <th class="text-right">{{ number_format((float) ($commissionTotals[$column['key'] . '_commission_total'] ?? 0), 2) }}</th>
                        @endif
                    @endforeach
                    <th class="text-right">{{ number_format((float) ($commissionTotals['commission_total'] ?? 0), 2) }}</th>
                </tr>
            </tfoot>
        </table>

        <div class="clearfix">
            <div class="pull-left text-muted">Showing {{ $commissionRows->firstItem() ?? 0 }} to {{ $commissionRows->lastItem() ?? 0 }} of {{ $commissionRows->total() }} rows</div>
            <div class="pull-right">{{ $commissionRows->appends(request()->query())->links() }}</div>
        </div>
    </div>
</div>
@endsection

@section('module_js')
<script>
$(function() {
    $('.select2').select2();

    var dateRange = $('#hr_commission_date_range');
    var startDate = $('#hr_commission_start_date');
    var endDate = $('#hr_commission_end_date');

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

    var filterBox = $('#hr_commission_filter_box');
    var filterBody = $('#hr_commission_filter_body');
    var filterHeader = filterBox.find('.hr-commission-filter-toggle');
    var filterButton = filterBox.find('.hr-commission-filter-button');

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

    if ($.fn.DataTable && ! $.fn.DataTable.isDataTable('#hr_commission_table')) {
        $('#hr_commission_table').DataTable({
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
                    title: 'HR Commission Report'
                }
            ],
            order: []
        });
    }
});
</script>
@endsection
