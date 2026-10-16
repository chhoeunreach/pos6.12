@extends('smartstockinventory::layouts.master')
@section('page_title', 'Mismatch Detector')
@section('module_content')
@php $stock_error_mode = in_array($filters['view'] ?? 'quantity', ['stock_errors', 'deducted_serial'], true); @endphp
<div class="box box-primary">
<div class="box-body">
<form method="get" action="{{ ssi_route('ssi.mismatch.index') }}">
@if(!empty($filters['line_id']))<input type="hidden" name="line_id" value="{{ $filters['line_id'] }}"><p>Showing stock issues for line #{{ $filters['line_id'] }}.</p>@endif
@if(!empty($filters['transfer_id']))<input type="hidden" name="transfer_id" value="{{ $filters['transfer_id'] }}"><p>Showing issues related to transfer #{{ $filters['transfer_id'] }}.</p>@endif
<div class="form-group"><label for="mismatch_view">Show</label><select id="mismatch_view" name="view" class="form-control"><option value="quantity" {{ !$stock_error_mode ? 'selected' : '' }}>Quantity mismatches (Product Stock Details)</option><option value="stock_errors" {{ ($filters['view'] ?? '') === 'stock_errors' ? 'selected' : '' }}>All stock errors (lots, serials and allocations)</option><option value="deducted_serial" {{ ($filters['view'] ?? '') === 'deducted_serial' ? 'selected' : '' }}>Deducted serial mismatches</option></select></div>
<div class="row">
<div class="col-md-4"><label>Purchase Ref No</label><input class="form-control" name="purchase_ref" value="{{ $filters['purchase_ref'] ?? '' }}" placeholder="PUR-0001"></div>
<div class="col-md-3"><label>Purchase Date From</label><input type="date" class="form-control" name="purchase_date_from" value="{{ $filters['purchase_date_from'] ?? '' }}"></div>
<div class="col-md-3"><label>Purchase Date To</label><input type="date" class="form-control" name="purchase_date_to" value="{{ $filters['purchase_date_to'] ?? '' }}"></div>
<div class="col-md-2" style="margin-top:24px;">
<button class="btn btn-primary">Filter</button>
<a class="btn btn-default" href="{{ ssi_route('ssi.mismatch.index') }}">Clear</a>
</div>
</div>
</form>
</div>
</div>
<div class="box box-danger">
<div class="box-header">
    <h3 class="box-title">Stock mismatches ({{ count($rows) }})</h3>
    <div class="box-tools pull-right">
        @if(auth()->user()->can('stock_inventory.authority') || auth()->user()->can('superadmin') || stripos((string)(auth()->user()->roles->pluck('name')->implode(' ')), 'admin') !== false)
            <a href="{{ ssi_route('ssi.authority.index') }}" class="btn btn-warning btn-sm">
                <i class="fa fa-shield"></i> Open Authority Mismatch Fix
            </a>
        @endif
    </div>
    <p class="help-block">{{ $stock_error_mode ? 'Review stock, lot, serial and allocation errors. Fix and Correct record both set the recorded stock to the calculated correct stock immediately; open Details to inspect the underlying receipt, lot or transfer.' : 'Shows the same quantity mismatches as Product Stock Details for your permitted locations. Click Fix to apply the calculated stock quantity immediately.' }}</p>
</div>
<div id="transfer_fix_result" role="status" aria-live="polite"></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped datatable" id="ssi_mismatch_table">
<thead><tr><th>Action</th><th>Product</th><th>SKU</th><th>Location</th>@if($stock_error_mode)<th>Lot / Serial</th><th>Deducted / Receiving Serial</th><th>Reference</th><th>Error</th>@endif<th>Recorded stock</th><th>Correct stock</th><th>Difference</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td style="min-width:135px">
@if(!empty($row['deducted_serial_fix_url']))
    @can('stock_transfer.update')
    <form method="post" action="{{ $row['deducted_serial_fix_url'] }}" class="direct-stock-fix" style="display:inline-block">
        @csrf
        <input type="hidden" name="sell_line_id" value="{{ $row['sell_line_id'] }}">
        <button type="submit" class="btn btn-sm btn-primary">Fix Deducted Serial</button>
    </form>
    @endcan
    <a class="btn btn-xs btn-default" href="{{ $row['url'] }}">Verified correction</a>
    @if(auth()->user()->can('stock_inventory.authority') || auth()->user()->can('superadmin') || stripos((string)(auth()->user()->roles->pluck('name')->implode(' ')), 'admin') !== false)
        <a class="btn btn-xs btn-default" href="{{ $row['authority_url'] ?? ssi_route('ssi.authority.index') }}">Authority</a>
    @endif
@elseif(!empty($row['completion_url']))
    @can('stock_transfer.update')
    <button type="button" class="btn btn-sm btn-primary retry-transfer-fix" data-url="{{ $row['completion_url'] }}">Complete transfer</button>
    @endcan
    <a class="btn btn-xs btn-default" href="{{ $row['url'] }}">Edit transfer</a>
@elseif(!empty($row['can_fix_quantity']) && (!$stock_error_mode || ($row['lot'] ?? null) === 'All lots at location'))
    @can('stock_inventory.fix')
    <form method="post" action="{{ ssi_route('ssi.mismatch.fix_auto') }}" class="direct-stock-fix" style="display:inline-block">
        @csrf
        <input type="hidden" name="product_id" value="{{ $row['product_id'] }}">
        <input type="hidden" name="variation_id" value="{{ $row['variation_id'] }}">
        <input type="hidden" name="location_id" value="{{ $row['location_id'] }}">
        <input type="hidden" name="problem_type" value="mismatch_stock">
        <input type="hidden" name="reason" value="Reconcile quantity using Product Stock Details">
        <button type="submit" class="btn btn-sm btn-primary" title="Apply the current calculated stock quantity"><i class="fa fa-wrench" aria-hidden="true"></i> Fix</button>
    </form>
    @endcan
    <a class="btn btn-xs btn-default" href="{{ $row['url'] }}">Details</a>
@else
    @can('stock_inventory.fix')
    <form method="post" action="{{ ssi_route('ssi.mismatch.fix_auto') }}" class="direct-stock-fix" style="display:inline-block">
        @csrf
        <input type="hidden" name="product_id" value="{{ $row['product_id'] }}">
        <input type="hidden" name="variation_id" value="{{ $row['variation_id'] }}">
        <input type="hidden" name="location_id" value="{{ $row['location_id'] }}">
        <input type="hidden" name="problem_type" value="mismatch_stock">
        <input type="hidden" name="reason" value="Correct record: reconcile stock quantity using Product Stock Details">
        <button type="submit" class="btn btn-sm btn-primary" title="Set recorded stock to the calculated correct stock"><i class="fa fa-wrench" aria-hidden="true"></i> Correct record</button>
    </form>
    @endcan
    <a class="btn btn-xs btn-default" href="{{ $row['url'] }}">Details</a>
@endif
</td>
<td>{{ $row['product'] }}</td><td>{{ $row['sku'] }}</td><td>{{ $row['location'] }}</td>
@if($stock_error_mode)<td>{{ $row['lot'] ?? '—' }}</td><td>{{ $row['allocated_lots'] ?? '—' }}</td><td>{{ $row['reference'] ?? '—' }}</td><td>{{ $row['problem'] }}</td>@endif
@foreach(['available_qty', 'expected_qty', 'difference'] as $field)
<td class="text-right">{{ isset($row[$field]) ? number_format((float) $row[$field], 4) : '—' }}</td>
@endforeach
</tr>
@endforeach
</tbody></table>
</div></div>

@endsection
@section('module_js')
<script>
$(function(){
$(document).on('click', '.retry-transfer-fix', function() {
 const $button = $(this);
 if (!window.confirm('Repair verified stale lot counters and attempt to complete this transfer? Conflicting records will remain unchanged.')) { return; }
 $button.prop('disabled', true);
 $.post($button.attr('data-url'), {_token: "{{ csrf_token() }}", status: 'completed'})
 .done(function(result) {
   if (result.success) { window.location.reload(); return; }
   $('#transfer_fix_result').addClass('alert alert-danger').text((result.stock_error && result.stock_error.reason) || result.msg || 'The transfer still needs its original records corrected.');
 })
 .fail(function() { $('#transfer_fix_result').addClass('alert alert-danger').text('Unable to complete the request. Refresh and try again.'); })
 .always(function() { $button.prop('disabled', false); });
});
$(document).on('submit', '.direct-stock-fix', function() {
    $(this).find('button[type="submit"]').prop('disabled', true).text('Fixing…');
});
});
</script>
@endsection
