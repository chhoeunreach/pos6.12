@extends('smartstockinventory::layouts.master')
@section('page_title', 'Enterprise Inventory Count')
@section('module_content')
<div class="box box-primary"><div class="box-header"><h4>Create Advanced Session</h4></div><div class="box-body">
<form method="post" action="{{ route('ssi.count.enterprise.session') }}">@csrf
<div class="row">
<div class="col-md-3"><label>Session Name</label><input class="form-control" name="session_name" required></div>
<div class="col-md-3"><label>Location</label><select class="form-control select2" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
<div class="col-md-2"><label>Warehouse</label><input class="form-control" name="warehouse"></div>
<div class="col-md-2"><label>Count Type</label><select class="form-control" name="count_type"><option value="full_count">Full Count</option><option value="partial_count">Partial Count</option><option value="cycle_count">Cycle Count</option><option value="blind_count">Blind Count</option><option value="imei_count">IMEI Count</option><option value="lot_count">Lot Count</option></select></div>
<div class="col-md-2"><label>Count Method</label><select class="form-control" name="count_method"><option value="manual">Manual</option><option value="barcode_scan">Barcode Scan</option><option value="imei_scan">IMEI Scan</option><option value="import_excel">Import Excel</option></select></div>
</div>
<div class="row" style="margin-top:8px;">
<div class="col-md-2"><label>Count By</label><select class="form-control" name="count_by"><option value="product">Product</option><option value="category">Category</option><option value="brand">Brand</option><option value="supplier">Supplier</option><option value="location">Location</option><option value="rack">Rack</option><option value="sku_range">SKU Range</option></select></div>
<div class="col-md-2"><label>Start Date</label><input type="datetime-local" class="form-control" name="start_date"></div>
<div class="col-md-2"><label>End Date</label><input type="datetime-local" class="form-control" name="end_date"></div>
<div class="col-md-4"><label>Description</label><input class="form-control" name="description"></div>
<div class="col-md-1"><label>Blind</label><div><input type="checkbox" name="blind_count" value="1"></div></div>
<div class="col-md-1" style="margin-top:25px;"><button class="btn btn-primary">Create</button></div>
</div>
</form></div></div>

<div class="box box-default"><div class="box-header"><h4>Sessions</h4></div><div class="box-body table-responsive">
<table class="table table-bordered table-striped datatable" id="ssi_enterprise_session_table">
<thead><tr><th>Name</th><th>Location</th><th>Type</th><th>Method</th><th>Status</th><th>Start</th><th>Approved By</th><th>Actions</th></tr></thead>
<tbody>
@foreach($sessions as $s)
<tr>
<td>{{ $s->name }}</td><td>{{ $s->location_id }}</td><td>{{ $s->count_type }}</td><td>{{ $s->count_method }}</td><td>{{ $s->status }}</td><td>{{ $s->start_date }}</td><td>{{ $s->approved_by }}</td>
<td>
<a class="btn btn-xs btn-info" href="{{ route('ssi.count.enterprise.mobile', $s->id) }}">Mobile</a>
<a class="btn btn-xs btn-default" href="{{ route('ssi.count.enterprise.adjustment_preview', $s->id) }}">Adjustment Preview</a>
<a class="btn btn-xs btn-warning" href="{{ route('ssi.count.enterprise.dashboard', $s->id) }}" target="_blank">Live Dashboard JSON</a>
<form method="post" action="{{ route('ssi.count.enterprise.approve', $s->id) }}" style="display:inline-block;">@csrf<input type="hidden" name="approval_level" value="manager"><button class="btn btn-xs btn-success">Approve</button></form>
</td>
</tr>
@endforeach
</tbody></table>
{{ $sessions->links() }}</div></div>
@endsection
@section('module_js')
<script>$(function(){ $('#ssi_enterprise_session_table').DataTable({pageLength:25}); });</script>
@endsection