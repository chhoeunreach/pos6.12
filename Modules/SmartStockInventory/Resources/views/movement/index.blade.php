@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Movement History')
@section('module_content')
@component('components.filters', ['title' => __('report.filters')])
    <form method="get" id="ssi_movement_filter_form">
        <div class="col-md-3">
            <div class="form-group">
                <label>Product:</label>
                <input class="form-control" name="product" value="{{ request('product') }}" placeholder="Product">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Location:</label>
                <select class="form-control select2" name="location_id" style="width:100%">
                    <option value="">{{ __('lang_v1.all') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string)request('location_id') === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Transaction Type:</label>
                <select class="form-control select2" name="type" style="width:100%">
                    <option value="">{{ __('lang_v1.all') }}</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>{{ __('report.date_range') }}:</label>
                <input type="text" class="form-control" id="ssi_movement_date_range" name="date_range" readonly value="{{ request('date_range') }}" placeholder="{{ __('lang_v1.select_a_date_range') }}">
            </div>
        </div>
        <div class="col-md-12 text-right">
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('ssi.movement.index') }}" class="btn btn-default">Clear</a>
            <a href="{{ route('ssi.movement.export', request()->all()) }}" class="btn btn-success">Export</a>
            <a href="{{ route('ssi.movement.print', request()->all()) }}" class="btn btn-default">Print</a>
        </div>
    </form>
@endcomponent

<div class="box box-primary"><div class="box-body table-responsive">
<table class="table table-bordered table-striped datatable" id="ssi_movement_table">
<thead><tr><th>Date</th><th>Reference No</th><th>Transaction Type</th><th>Location</th><th>Product</th><th>SKU</th><th>IMEI</th><th>Lot</th><th>Qty In</th><th>Qty Out</th><th>Balance</th><th>Created By</th></tr></thead>
<tbody>@forelse($rows as $row)<tr><td>{{ $row->movement_date }}</td><td>{{ $row->reference_no }}</td><td>{{ $row->transaction_type }}</td><td>{{ $row->location_name }}</td><td>{{ $row->product_name }}</td><td>{{ $row->sku }}</td><td>{{ $row->imei }}</td><td>{{ $row->lot_number }}</td><td>{{ $row->qty_in }}</td><td>{{ $row->qty_out }}</td><td>{{ $row->balance_qty }}</td><td>{{ $row->created_by_name }}</td></tr>@empty<tr><td colspan="12" class="text-center">No movement found</td></tr>@endforelse</tbody>
</table>{{ $rows->links() }}</div></div>
@endsection

@section('module_js')
<script>
$(function(){
    $('#ssi_movement_date_range').daterangepicker(dateRangeSettings, function(start, end){
        $('#ssi_movement_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
    });
    $('#ssi_movement_date_range').on('cancel.daterangepicker', function(){ $(this).val(''); });
});
</script>
@endsection

