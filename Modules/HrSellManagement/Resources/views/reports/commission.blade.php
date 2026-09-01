@extends('hrsellmanagement::layouts.master')
@section('page_title', 'Commission Report')
@section('module_content')
@php
    $hasActiveFilters = request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('branch_name') || request()->filled('department_id') || request()->filled('sell_type') || request()->filled('seller_key') || request()->filled('commission_condition_mode') || request()->filled('commission_period');
    $selectedBranchNames = collect((array) request('branch_name', []))->map(fn ($branchName) => (string) $branchName)->all();
    $selectedDepartmentIds = collect((array) request('department_id', []))->map(fn ($departmentId) => (string) $departmentId)->all();
    $selectedSellTypes = collect((array) request('sell_type', []))->map(fn ($sellType) => (string) $sellType)->all();
    $printDate = request('start_date') && request('end_date')
        ? (request('start_date') === request('end_date') ? request('start_date') : request('start_date') . ' - ' . request('end_date'))
        : now()->toDateString();
    $printBranch = count($selectedBranchNames) ? implode(', ', $selectedBranchNames) : 'ទាំងអស់';
    $printTitle = 'របាយការណ៍ ប្រាក់លើកទឹកចិត្ត​សម្រាប់ ' . $printDate . ' សាខា ' . $printBranch;
    $commissionPerPageOptions = ['25' => '25', '50' => '50', '100' => '100', '200' => '200', '500' => '500', 'all' => 'All'];
    $commissionPerPage = array_key_exists((string) request('commission_per_page', '50'), $commissionPerPageOptions) ? (string) request('commission_per_page', '50') : '50';
    $commissionConditionModes = ['with_condition', 'without_sell_qty_condition', 'no_condition'];
    $commissionConditionMode = in_array(request('commission_condition_mode', 'with_condition'), $commissionConditionModes, true) ? request('commission_condition_mode', 'with_condition') : 'with_condition';
    $applyCommissionConditions = $commissionConditionMode !== 'no_condition';
    $commissionPeriod = request('commission_period') === 'monthly' ? 'monthly' : 'daily';
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

    .hr-commission-alert-badge {
        cursor: help;
        display: inline-block;
        margin: 1px 2px;
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
                        <label>Report By:</label>
                        <select name="commission_period" class="form-control">
                            <option value="daily" {{ $commissionPeriod === 'daily' ? 'selected' : '' }}>Daily</option>
                            <option value="monthly" {{ $commissionPeriod === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Location / Branch:</label>
                        <select name="branch_name[]" class="form-control select2 js-hr-multi-select js-branch-filter" multiple data-placeholder="All">
                            @foreach($hrBranches as $branch => $name)
                                <option value="{{ $branch }}" @selected(in_array((string) $branch, $selectedBranchNames, true))>{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="btn-group btn-group-xs" style="margin-top:5px;">
                            <button type="button" class="btn btn-default js-select-all-options">Select all</button>
                            <button type="button" class="btn btn-default js-clear-all-options">Clear all</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Department:</label>
                        <select name="department_id[]" class="form-control select2 js-hr-multi-select" multiple data-placeholder="All">
                            @foreach($hrDepartments as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" @selected(in_array((string) $departmentId, $selectedDepartmentIds, true))>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                        <div class="btn-group btn-group-xs" style="margin-top:5px;">
                            <button type="button" class="btn btn-default js-select-all-options">Select all</button>
                            <button type="button" class="btn btn-default js-clear-all-options">Clear all</button>
                        </div>
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
                        <select name="sell_type[]" class="form-control select2 js-hr-multi-select" multiple data-placeholder="All">
                            @foreach($hrSellTypes as $type => $name)
                                <option value="{{ $type }}" @selected(in_array((string) $type, $selectedSellTypes, true))>{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="btn-group btn-group-xs" style="margin-top:5px;">
                            <button type="button" class="btn btn-default js-select-all-options">Select all</button>
                            <button type="button" class="btn btn-default js-clear-all-options">Clear all</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Search:</label>
                        <input name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice, staff, product, IMEI">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Commission:</label>
                        <select name="commission_condition_mode" class="form-control">
                            <option value="with_condition" {{ $commissionConditionMode === 'with_condition' ? 'selected' : '' }}>With Sell qty condition</option>
                            <option value="without_sell_qty_condition" {{ $commissionConditionMode === 'without_sell_qty_condition' ? 'selected' : '' }}>No Sell qty condition</option>
                            <option value="no_condition" {{ $commissionConditionMode === 'no_condition' ? 'selected' : '' }}>No condition</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
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

<div class="box box-info">
    <div class="box-header"><h4>Commission Conditions</h4></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-condensed" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Condition</th>
                    <th class="text-right">Rate</th>
                    <th>Expression</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Iron</td>
                    <td>{{ $applyCommissionConditions ? 'Phone number required' : 'No condition' }}</td>
                    <td class="text-right">0.20</td>
                    <td>Invoice count * 0.20</td>
                </tr>
                <tr>
                    <td>Mat.</td>
                    <td>{{ $applyCommissionConditions ? 'Phone number required, invoice total >= 10' : 'No condition' }}</td>
                    <td class="text-right">0.25</td>
                    <td>Invoice count * 0.25</td>
                </tr>
                <tr>
                    <td>Repair</td>
                    <td>{{ $applyCommissionConditions ? 'Phone number required' : 'No condition' }}</td>
                    <td class="text-right">0.20</td>
                    <td>Invoice count * 0.20</td>
                </tr>
                <tr>
                    <td>Sell</td>
                    <td>
                        @if($commissionConditionMode === 'with_condition')
                            Phone number required, part-time qty >= 50, full-time qty >= 100
                        @elseif($commissionConditionMode === 'without_sell_qty_condition')
                            Phone number required
                        @else
                            No condition
                        @endif
                    </td>
                    <td class="text-right">0.25</td>
                    <td>Qualified product qty * 0.25</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="box box-success">
    <div class="box-header"><h4>Commission Report</h4></div>
    <div class="box-body table-responsive">
        <div class="clearfix" style="margin-bottom: 10px;">
            <form method="get" action="{{ route('hr-sell.reports.commission') }}" class="form-inline pull-left">
                @foreach(request()->except(['commission_per_page', 'commission_page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="text-muted" for="commission_per_page" style="margin-right: 5px;">Show</label>
                <select name="commission_per_page" id="commission_per_page" class="form-control input-sm" onchange="this.form.submit()">
                    @foreach($commissionPerPageOptions as $value => $label)
                        <option value="{{ $value }}" {{ $commissionPerPage === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="text-muted" style="margin-left: 5px;">rows</span>
            </form>
        </div>
        <table class="table table-bordered table-striped hr-commission-table" id="hr_commission_table">
            <thead>
                <tr>
                    <th>{{ $commissionPeriod === 'monthly' ? 'Month' : 'Date' }}</th>
                    <th>User</th>
                    <th>Staff</th>
                    <th>Branch</th>
                    <th>Office Time</th>
                    <th>Total Hour / Day</th>
                    <th>Time Work</th>
                    <th>Alert</th>
                    @foreach($commissionColumns as $column)
                        <th class="text-right">{{ $column['short_label'] ?? $column['label'] }}</th>
                        @if($column['has_commission'])
                            <th class="text-right">Com.</th>
                        @endif
                    @endforeach
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($commissionRows as $row)
                    <tr>
                        <td>{{ $row->sale_period ?? '-' }}</td>
                        <td>{{ $row->staff_code ?: '-' }}</td>
                        <td>{{ $row->staff_name }}</td>
                        <td>{{ $row->branch_name }}</td>
                        <td>{{ $row->office_time ?? '-' }}</td>
                        <td>{{ $row->total_hour_day ?? '-' }}</td>
                        <td>{{ $row->time_work ?? '-' }}</td>
                        <td>
                            @php
                                $alerts = [];
                                foreach ($commissionColumns as $alertColumn) {
                                    $qualifiedValue = (float) ($row->{$alertColumn['key'] . '_total'} ?? 0);
                                    $rawValue = (float) ($row->{$alertColumn['key'] . '_raw_total'} ?? $qualifiedValue);
                                    $excludedValue = max(0, $rawValue - $qualifiedValue);

                                    if ($excludedValue <= 0) {
                                        continue;
                                    }

                                    $alertDecimals = ($alertColumn['commission_basis'] ?? '') === 'invoice' ? 0 : 2;
                                    $alertLabel = $alertColumn['short_label'] ?? $alertColumn['label'];
                                    if ($alertColumn['key'] === 'material') {
                                        $failedCondition = 'invoice total is lower than 10.';
                                    } elseif ($alertColumn['key'] === 'sell') {
                                        $failedCondition = 'part-time qty is lower than 50 or full-time qty is lower than 100.';
                                    } else {
                                        $failedCondition = 'commission condition not completed.';
                                    }

                                    $alerts[] = [
                                        'label' => $alertLabel,
                                        'value' => number_format($excludedValue, $alertDecimals),
                                        'title' => $alertLabel . ': ' . number_format($excludedValue, $alertDecimals) . ' did not receive commission. Failed condition: ' . $failedCondition,
                                    ];
                                }
                            @endphp
                            @forelse($alerts as $alert)
                                <span class="label label-warning hr-commission-alert-badge"
                                    data-toggle="tooltip"
                                    data-placement="top"
                                    title="{{ $alert['title'] }}">
                                    {{ $alert['label'] }} {{ $alert['value'] }}
                                </span>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </td>
                        @foreach($commissionColumns as $column)
                            @php
                                $columnValue = (float) ($row->{$column['key'] . '_total'} ?? 0);
                                $columnDecimals = ($column['commission_basis'] ?? '') === 'invoice' ? 0 : 2;
                                $columnUrl = $row->detail_urls[$column['key']] ?? null;
                            @endphp
                            <td class="text-right">
                                @if($columnValue > 0 && $columnUrl)
                                    <a href="{{ $columnUrl }}">{{ number_format($columnValue, $columnDecimals) }}</a>
                                @else
                                    {{ number_format($columnValue, $columnDecimals) }}
                                @endif
                            </td>
                            @if($column['has_commission'])
                                <td class="text-right">{{ number_format((float) ($row->{$column['key'] . '_commission_total'} ?? 0), 2) }}</td>
                            @endif
                        @endforeach
                        <td class="text-right">{{ number_format((float) ($row->commission_total ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                {!! count($commissionRows) === 0 ? '<tr><td colspan="' . (count($commissionColumns) + collect($commissionColumns)->where('has_commission', true)->count() + 9) . '" class="text-center text-muted">No commission rows found for selected filters.</td></tr>' : '' !!}
            </tbody>
            <tfoot>
                <tr>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th class="text-right">Total</th>
                    @foreach($commissionColumns as $column)
                        @php
                            $footerValue = (float) ($commissionTotals[$column['key'] . '_total'] ?? 0);
                            $footerDecimals = ($column['commission_basis'] ?? '') === 'invoice' ? 0 : 2;
                        @endphp
                        <th class="text-right">
                            {{ number_format($footerValue, $footerDecimals) }}
                        </th>
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
    $('[data-toggle="tooltip"]').tooltip();
    var commissionPrintTitle = @json($printTitle);

    $(document).on('click', '.js-select-all-options', function() {
        var select = $(this).closest('.form-group').find('select.js-hr-multi-select');
        select.find('option').prop('selected', true);
        select.trigger('change');
    });

    $(document).on('click', '.js-clear-all-options', function() {
        var select = $(this).closest('.form-group').find('select.js-hr-multi-select');
        select.val(null).trigger('change');
    });

    $('.js-branch-filter').on('change', function() {
        var form = $(this).closest('form');
        form.find('select[name="department_id[]"]').val(null);
        form.submit();
    });

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
            paging: false,
            searching: false,
            info: false,
            ordering: true,
            responsive: false,
            autoWidth: false,
            dom: '<"row"<"col-sm-12 text-center"B>>rt',
            buttons: [
                { extend: 'copy', text: 'Copy', footer: true, exportOptions: { columns: ':visible' } },
                { extend: 'csv', text: 'Export CSV', footer: true, exportOptions: { columns: ':visible' } },
                { extend: 'excel', text: 'Export Excel', footer: true, exportOptions: { columns: ':visible' } },
                {
                    extend: 'print',
                    text: 'Print',
                    title: commissionPrintTitle,
                    footer: true,
                    exportOptions: { columns: ':visible' },
                    customize: function(win) {
                        var css =
                            '@page{size:portrait;margin:6mm;}' +
                            'body{font-family:Arial,"Khmer OS Battambang","Khmer OS",sans-serif;color:#111827;font-size:9px;line-height:1.2;margin:0;padding:0;}' +
                            'h1{font-size:14px;text-align:center;margin:0 0 6px;font-weight:700;color:#0f5132;}' +
                            'table{width:100%!important;border-collapse:collapse!important;table-layout:auto!important;}' +
                            'table th,table td{border:1px solid #9ca3af!important;padding:3px 4px!important;vertical-align:middle!important;word-break:break-word!important;}' +
                            'table thead th{background:#d9ead3!important;color:#111827!important;font-weight:700!important;text-align:center!important;}' +
                            'table tbody tr:nth-child(even) td{background:#f8fafc!important;}' +
                            'table tfoot th{background:#fce5cd!important;color:#111827!important;font-weight:700!important;}' +
                            '.text-right{text-align:right!important;}' +
                            'a{color:#111827!important;text-decoration:none!important;}' +
                            '.label{border:1px solid #b45309!important;background:#fef3c7!important;color:#92400e!important;padding:2px 4px!important;border-radius:2px!important;}' +
                            '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}tr{break-inside:avoid;}}';

                        $(win.document.head).append('<style>' + css + '</style>');
                    }
                },
                { extend: 'colvis', text: 'Column visibility' },
                {
                    extend: 'pdf',
                    text: 'Export PDF',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    title: commissionPrintTitle,
                    footer: true,
                    exportOptions: { columns: ':visible' }
                }
            ],
            order: []
        });
    }
});
</script>
@endsection
