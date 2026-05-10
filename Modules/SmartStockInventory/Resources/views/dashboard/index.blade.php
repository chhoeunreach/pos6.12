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
<div class="col-md-4 text-right"><a class="btn btn-success" href="{{ route('ssi.dashboard.export', request()->all()) }}">Export Dashboard</a> <a class="btn btn-default" href="{{ route('ssi.dashboard.print', request()->all()) }}">Print Summary</a></div>
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
            <a href="{{ route('ssi.dashboard.detail', ['metric' => $card['metric'], 'location_id' => request('location_id')]) }}" class="small-box-footer">View Detail <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    @endforeach
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
