@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Inventory Dashboard')

@section('module_content')
<div class="box box-primary"><div class="box-body">
<form method="get" class="row" id="ssi_dashboard_filter_form">
<input type="hidden" name="start_date" id="ssi_start_date" value="{{ $filters['start_date'] ?? '' }}">
<input type="hidden" name="end_date" id="ssi_end_date" value="{{ $filters['end_date'] ?? '' }}">
<div class="col-md-3">
    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#ssiLocationFilterModal">
        <i class="fa fa-map-marker"></i> Select Locations
    </button>
    <small class="help-block">Selected: <span id="ssi_selected_locations_count">{{ count((array)($locationIds ?? [])) }}</span></small>
</div>
<div class="col-md-3">
    <button type="button" id="dashboard_date_filter" class="btn btn-default">
        <i class="fa fa-calendar"></i> {{ __('messages.filter_by_date') }}
    </button>
</div>
<div class="col-md-2"><button class="btn btn-primary">Refresh Data</button></div>
<div class="col-md-4 text-right">
    @if(\Nwidart\Modules\Facades\Module::has('ManageLot') && \Nwidart\Modules\Facades\Module::isEnabled('ManageLot') && (auth()->user()->can('stock_report.view') || auth()->user()->can('product.view')))
        <a class="btn btn-primary" href="{{ action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'index']) }}">
            <i class="fa fa-tags"></i> Manage Lot
        </a>
    @endif
    <a class="btn btn-success" href="{{ route('ssi.dashboard.export', request()->all()) }}">Export Dashboard</a>
    <a class="btn btn-default" href="{{ route('ssi.dashboard.print', request()->all()) }}">Print Summary</a>
</div>
</form>
</div></div>

<div class="modal fade" id="ssiLocationFilterModal" tabindex="-1" role="dialog" aria-labelledby="ssiLocationFilterModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ssiLocationFilterModalLabel">Select Business Locations</h4>
            </div>
            <div class="modal-body">
                <select class="form-control select2" id="dashboard_location_modal" multiple style="width:100%;">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ in_array((int)$loc->id, (array)($locationIds ?? []), true) ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="ssi_clear_location_filter">Clear Selection</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="ssi_apply_location_filter">Apply</button>
            </div>
        </div>
    </div>
</div>
<div class="row">
    @foreach([
        ['label' => 'Total Products', 'value' => $totalProducts, 'metric' => 'total_products'],
        ['label' => 'Total Stock Qty', 'value' => number_format($totalStockQty,2), 'metric' => 'total_stock_qty'],
        ['label' => 'Low Stock Products', 'value' => $lowStockProducts, 'metric' => 'low_stock'],
        ['label' => 'Negative Stock Products', 'value' => $negativeStockProducts, 'metric' => 'negative_stock'],
        ['label' => 'Mismatch Products', 'value' => $mismatchProducts, 'metric' => 'mismatch'],
        ['label' => 'Duplicate IMEI', 'value' => $duplicateImei, 'metric' => 'duplicate_imei'],
        ['label' => 'Duplicate Lot', 'value' => $duplicateLot, 'metric' => 'duplicate_lot'],
        ['label' => 'Pending Transfers', 'value' => $pendingTransfers, 'metric' => 'pending_transfers'],
        ['label' => 'Inventory Sessions Today', 'value' => $inventorySessionsToday, 'metric' => 'sessions_today'],
        ['label' => 'Total Stock Value', 'value' => '$ '.number_format($totalStockValue,2), 'metric' => 'total_stock_value'],
    ] as $card)
    <div class="col-md-3">
        <div class="small-box bg-aqua">
            <div class="inner"><h3>{{ $card['value'] }}</h3><p>{{ $card['label'] }}</p></div>
            <div class="icon"><i class="fa fa-cubes"></i></div>
            <a href="{{ route('ssi.dashboard.detail', ['metric' => $card['metric'], 'location_ids' => request('location_ids', (array)($locationIds ?? []))]) }}" class="small-box-footer">View Detail <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    @endforeach

    @if(\Nwidart\Modules\Facades\Module::has('ManageLot') && \Nwidart\Modules\Facades\Module::isEnabled('ManageLot') && (auth()->user()->can('stock_report.view') || auth()->user()->can('product.view')))
    <div class="col-md-3">
        <div class="small-box bg-green">
            <div class="inner"><h3>{{ number_format((int) ($totalLots ?? 0)) }}</h3><p>Total Lots</p></div>
            <div class="icon"><i class="fa fa-cubes"></i></div>
            <a href="{{ action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'index']) }}" class="small-box-footer">Open Manage Lot <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    @endif
</div>

<div class="box box-info">
    <div class="box-header with-border">
        <h3 class="box-title">Summary</h3>
        <small class="text-muted">Qty and Value by Location, Category, and Brand</small>
    </div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-3">
                <h4>By Location</h4>
                <div class="form-group">
                    <button type="button" class="btn btn-xs btn-info summary-open-modal" data-title="Summary by Location" data-source-table="#summary_location_table">View Report</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped summary-data-table" id="summary_location_table">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($summaryByLocation ?? collect()) as $row)
                                <tr data-name="{{ $row->location_name }}" data-qty="{{ (float) ($row->total_qty ?? 0) }}" data-value="{{ (float) ($row->total_value ?? 0) }}">
                                    <td>
                                        <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'location_id' => (int) $row->location_id]) }}">
                                            {{ $row->location_name }}
                                        </a>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'location_id' => (int) $row->location_id]) }}">
                                            {{ number_format((float) ($row->total_qty ?? 0), 2) }}
                                        </a>
                                    </td>
                                    <td class="text-right">$ {{ number_format((float) ($row->total_value ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No location summary.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right summary-total-qty">{{ number_format((float) collect($summaryByLocation ?? [])->sum('total_qty'), 2) }}</th>
                                <th class="text-right summary-total-value">$ {{ number_format((float) collect($summaryByLocation ?? [])->sum('total_value'), 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-3">
                <h4>By Category</h4>
                <div class="form-group">
                    <button type="button" class="btn btn-xs btn-info summary-open-modal" data-title="Summary by Category" data-source-table="#summary_category_table">View Report</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped summary-data-table" id="summary_category_table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($summaryByCategory ?? collect()) as $row)
                                <tr data-name="{{ $row->category_name }}" data-qty="{{ (float) ($row->total_qty ?? 0) }}" data-value="{{ (float) ($row->total_value ?? 0) }}">
                                    <td>
                                        @if(!empty($row->category_id))
                                            <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'category_id' => (int) $row->category_id, 'location_ids' => request('location_ids', (array)($locationIds ?? []))]) }}">
                                                {{ $row->category_name }}
                                            </a>
                                        @else
                                            {{ $row->category_name }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if(!empty($row->category_id))
                                            <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'category_id' => (int) $row->category_id, 'location_ids' => request('location_ids', (array)($locationIds ?? []))]) }}">
                                                {{ number_format((float) ($row->total_qty ?? 0), 2) }}
                                            </a>
                                        @else
                                            {{ number_format((float) ($row->total_qty ?? 0), 2) }}
                                        @endif
                                    </td>
                                    <td class="text-right">$ {{ number_format((float) ($row->total_value ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No category summary.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right summary-total-qty">{{ number_format((float) collect($summaryByCategory ?? [])->sum('total_qty'), 2) }}</th>
                                <th class="text-right summary-total-value">$ {{ number_format((float) collect($summaryByCategory ?? [])->sum('total_value'), 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-3">
                <h4>By Brand</h4>
                <div class="form-group">
                    <button type="button" class="btn btn-xs btn-info summary-open-modal" data-title="Summary by Brand" data-source-table="#summary_brand_table">View Report</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped summary-data-table" id="summary_brand_table">
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($summaryByBrand ?? collect()) as $row)
                                <tr data-name="{{ $row->brand_name }}" data-qty="{{ (float) ($row->total_qty ?? 0) }}" data-value="{{ (float) ($row->total_value ?? 0) }}">
                                    <td>
                                        @if(!empty($row->brand_id))
                                            <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'brand_id' => (int) $row->brand_id, 'location_ids' => request('location_ids', (array)($locationIds ?? []))]) }}">
                                                {{ $row->brand_name }}
                                            </a>
                                        @else
                                            {{ $row->brand_name }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if(!empty($row->brand_id))
                                            <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'brand_id' => (int) $row->brand_id, 'location_ids' => request('location_ids', (array)($locationIds ?? []))]) }}">
                                                {{ number_format((float) ($row->total_qty ?? 0), 2) }}
                                            </a>
                                        @else
                                            {{ number_format((float) ($row->total_qty ?? 0), 2) }}
                                        @endif
                                    </td>
                                    <td class="text-right">$ {{ number_format((float) ($row->total_value ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No brand summary.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right summary-total-qty">{{ number_format((float) collect($summaryByBrand ?? [])->sum('total_qty'), 2) }}</th>
                                <th class="text-right summary-total-value">$ {{ number_format((float) collect($summaryByBrand ?? [])->sum('total_value'), 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-3">
                <h4>By Product</h4>
                <div class="form-group">
                    <button type="button" class="btn btn-xs btn-info summary-open-modal" data-title="Summary by Product" data-source-table="#summary_product_table">View Report</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped summary-data-table" id="summary_product_table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($summaryByProduct ?? collect()) as $row)
                                <tr data-name="{{ $row->product_name }}" data-qty="{{ (float) ($row->total_qty ?? 0) }}" data-value="{{ (float) ($row->total_value ?? 0) }}">
                                    <td>{{ $row->product_name }}</td>
                                    <td class="text-right">{{ number_format((float) ($row->total_qty ?? 0), 2) }}</td>
                                    <td class="text-right">$ {{ number_format((float) ($row->total_value ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No product summary.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right summary-total-qty">{{ number_format((float) collect($summaryByProduct ?? [])->sum('total_qty'), 2) }}</th>
                                <th class="text-right summary-total-value">$ {{ number_format((float) collect($summaryByProduct ?? [])->sum('total_value'), 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Location Section</h3>
        <small class="text-muted">Quick cards by location</small>
    </div>
    <div class="box-body">
        <div class="row">
            @forelse(($summaryByLocation ?? collect()) as $row)
                <div class="col-md-3 col-sm-6">
                    <div class="small-box bg-blue">
                        <div class="inner">
                            <h4 style="margin:0 0 8px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $row->location_name }}</h4>
                            <p style="margin:0;">Qty: <strong>{{ number_format((float) ($row->total_qty ?? 0), 2) }}</strong></p>
                            <p style="margin:0;">Value: <strong>$ {{ number_format((float) ($row->total_value ?? 0), 2) }}</strong></p>
                        </div>
                        <div class="icon"><i class="fa fa-map-marker"></i></div>
                        <a href="{{ route('ssi.dashboard.detail', ['metric' => 'total_stock_qty', 'location_id' => (int) $row->location_id]) }}" class="small-box-footer">
                            View Detail <i class="fa fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-md-12">
                    <p class="text-muted">No location data available.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="modal fade" id="summaryReportModal" tabindex="-1" role="dialog" aria-labelledby="summaryReportModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="summaryReportModalLabel">Summary Report</h4>
            </div>
            <div class="modal-body">
                <div id="summaryReportModalBody"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('module_js')
<script>
$(function(){
    var start = moment("{{ $filters['start_date'] ?? now()->toDateString() }}");
    var end = moment("{{ $filters['end_date'] ?? now()->toDateString() }}");
    function setDates(s,e){
        $('#ssi_start_date').val(s.format('YYYY-MM-DD'));
        $('#ssi_end_date').val(e.format('YYYY-MM-DD'));
        $('#dashboard_date_filter span').remove();
        $('#dashboard_date_filter').append(' <span>'+s.format(moment_date_format)+' ~ '+e.format(moment_date_format)+'</span>');
    }
    $('#dashboard_date_filter').daterangepicker(dateRangeSettings, function(s,e){ setDates(s,e); });
    setDates(start,end);
    $('#dashboard_location_modal').select2({ width:'100%', dropdownParent: $('#ssiLocationFilterModal') });
    var summaryTables = {};
    function formatMoney(value){
        return '$ ' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function updateSummaryFooter(tableSelector){
        var api = summaryTables[tableSelector];
        if (!api) return;
        var qty = 0;
        var value = 0;
        api.rows({search:'applied'}).every(function(){
            var node = this.node();
            qty += parseFloat($(node).data('qty') || 0);
            value += parseFloat($(node).data('value') || 0);
        });
        var $table = $(tableSelector);
        $table.find('.summary-total-qty').text(qty.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $table.find('.summary-total-value').text(formatMoney(value));
    }

    function openSummaryModalFromTable(tableSelector, title){
        var $table = $(tableSelector);
        if (!$table.length) return;
        var api = summaryTables[tableSelector];
        var $thead = $table.find('thead').clone();
        var $tfoot = $table.find('tfoot').clone();
        var $tbody = $('<tbody></tbody>');
        if (api) {
            $(api.rows({search:'applied'}).nodes()).each(function(){
                $tbody.append($(this).clone());
            });
        } else {
            $tbody = $table.find('tbody').clone();
        }

        var modalTableHtml = $('<table class="table table-bordered table-striped"></table>')
            .append($thead)
            .append($tbody)
            .append($tfoot)
            .prop('outerHTML');

        var tableHtml = '<div class="table-responsive">' + modalTableHtml + '</div>';
        $('#summaryReportModalLabel').text(title || 'Summary Report');
        $('#summaryReportModalBody').html(tableHtml);
        $('#summaryReportModal').modal('show');
    }

    if ($.fn.DataTable) {
        $('.summary-data-table').each(function(){
            var id = '#' + this.id;
            summaryTables[id] = $(this).DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: false,
                pageLength: 10,
                lengthChange: false,
                dom: 't<"text-right"p>'
            });
            summaryTables[id].on('draw', function(){ updateSummaryFooter(id); });
            updateSummaryFooter(id);
        });
    }

    $(document).on('click', '.summary-open-modal', function(){
        var title = $(this).data('title') || 'Summary Report';
        var source = $(this).data('source-table');
        openSummaryModalFromTable(source, title);
    });

    $('#ssi_clear_location_filter').on('click', function(){
        $('#dashboard_location_modal').val(null).trigger('change');
        $('#ssi_selected_locations_count').text(0);
    });
    $('#ssi_apply_location_filter').on('click', function(){
        $('#ssi_dashboard_filter_form input[name="location_ids[]"]').remove();
        var vals = $('#dashboard_location_modal').val() || [];
        vals.forEach(function(v){
            $('#ssi_dashboard_filter_form').append('<input type="hidden" name="location_ids[]" value="'+v+'">');
        });
        $('#ssi_selected_locations_count').text(vals.length);
        $('#ssiLocationFilterModal').modal('hide');
        $('#ssi_dashboard_filter_form').submit();
    });
});
</script>
@endsection
