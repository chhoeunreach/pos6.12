@extends('smartstockinventory::layouts.master')
@section('page_title', 'Mismatch Detector')
@section('module_content')
<div class="box box-primary">
<div class="box-body">
<form method="get" action="{{ route('ssi.mismatch.index') }}">
<div class="row">
<div class="col-md-4"><label>Purchase Ref No</label><input class="form-control" name="purchase_ref" value="{{ $filters['purchase_ref'] ?? '' }}" placeholder="PUR-0001"></div>
<div class="col-md-3"><label>Purchase Date From</label><input type="date" class="form-control" name="purchase_date_from" value="{{ $filters['purchase_date_from'] ?? '' }}"></div>
<div class="col-md-3"><label>Purchase Date To</label><input type="date" class="form-control" name="purchase_date_to" value="{{ $filters['purchase_date_to'] ?? '' }}"></div>
<div class="col-md-2" style="margin-top:24px;">
<button class="btn btn-primary">Filter</button>
<a class="btn btn-default" href="{{ route('ssi.mismatch.index') }}">Clear</a>
</div>
</div>
</form>
</div>
</div>
<div class="box box-danger"><div class="box-body table-responsive">
<table class="table table-bordered table-striped datatable" id="ssi_mismatch_table">
<thead><tr><th>Product</th><th>SKU</th><th>Location</th><th>Available Qty</th><th>Problem</th><th>Severity</th><th>Action</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $row['product'] ?? '-' }}</td><td>{{ $row['sku'] ?? '-' }}</td><td>{{ $row['location'] ?? '-' }}</td><td>{{ $row['available_qty'] ?? '-' }}</td>
<td><span class="badge bg-red">{{ $row['problem'] ?? '-' }}</span></td><td>{{ strtoupper($row['severity'] ?? 'low') }}</td>
<td>
<button class="btn btn-xs btn-warning preview-fix-btn" data-product_id="{{ $row['product_id'] }}" data-variation_id="{{ $row['variation_id'] }}" data-location_id="{{ $row['location_id'] }}" data-problem_type="negative_stock">Confirm & Fix</button>
</td>
</tr>
@endforeach
</tbody></table></div></div>

<div class="modal fade" id="fixPreviewModal"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4>Fix Confirmation</h4></div>
<form method="post" action="{{ route('ssi.mismatch.fix_auto') }}">@csrf
<div class="modal-body">
<input type="hidden" name="product_id" id="fx_product_id"><input type="hidden" name="variation_id" id="fx_variation_id"><input type="hidden" name="location_id" id="fx_location_id"><input type="hidden" name="problem_type" id="fx_problem_type">
<p><b>Problem found:</b> <span id="fx_problem"></span></p><p><b>Current system qty:</b> <span id="fx_current"></span></p><p><b>Correct calculated qty:</b> <span id="fx_correct"></span></p><p><b>Affected transaction IDs:</b> <span id="fx_trx"></span></p><p><b>Risk level:</b> <span id="fx_risk"></span></p>
<div class="form-group"><label>Reason</label><input class="form-control" name="reason" required></div>
</div>
<div class="modal-footer"><button type="submit" class="btn btn-warning">Confirm Fix</button></div>
</form></div></div></div>
@endsection
@section('module_js')
<script>
$(function(){ $('#ssi_mismatch_table').DataTable({pageLength:25});
$(document).on('click','.preview-fix-btn',function(){
 const d=$(this).data();
 $.post("{{ route('ssi.mismatch.preview_fix') }}",{_token:"{{ csrf_token() }}",product_id:d.product_id,variation_id:d.variation_id,location_id:d.location_id,problem_type:d.problem_type},function(resp){
   if(!resp.success){return;}
   $('#fx_product_id').val(d.product_id);$('#fx_variation_id').val(d.variation_id);$('#fx_location_id').val(d.location_id);$('#fx_problem_type').val(d.problem_type);
   $('#fx_problem').text(resp.data.problem);$('#fx_current').text(resp.data.current_system_qty);$('#fx_correct').text(resp.data.correct_calculated_qty);$('#fx_trx').text((resp.data.affected_transaction_ids||[]).join(', '));$('#fx_risk').text(resp.data.risk_level);$('#fixPreviewModal').modal('show');
 });
});
});
</script>
@endsection
