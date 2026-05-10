@extends('smartstockinventory::layouts.master')
@section('page_title', 'Inventory Reports')
@section('module_content')
<div class="row">
<div class="col-md-4"><div class="info-box"><span class="info-box-icon bg-blue"><i class="fa fa-cubes"></i></span><div class="info-box-content"><span class="info-box-text">Full Inventory</span><span class="info-box-number">{{ $full->count() }}</span></div></div></div>
<div class="col-md-4"><div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-exclamation"></i></span><div class="info-box-content"><span class="info-box-text">Mismatch</span><span class="info-box-number">{{ $mismatch->count() }}</span></div></div></div>
<div class="col-md-4"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-check"></i></span><div class="info-box-content"><span class="info-box-text">Over Stock</span><span class="info-box-number">{{ $over->count() }}</span></div></div></div>
</div>
<div class="box box-default"><div class="box-header"><h4>Mismatch Report</h4></div><div class="box-body table-responsive"><table class="table table-bordered"><thead><tr><th>Session</th><th>SKU</th><th>Product</th><th>System</th><th>Actual</th><th>Diff</th><th>User</th></tr></thead><tbody>@foreach($mismatch as $r)<tr><td>{{ $r->session }}</td><td>{{ $r->sku }}</td><td>{{ $r->product_name }}</td><td>{{ $r->system_qty }}</td><td>{{ $r->actual_qty }}</td><td>{{ $r->difference_qty }}</td><td>{{ $r->counted_by_user_id }}</td></tr>@endforeach</tbody></table></div></div>
@endsection