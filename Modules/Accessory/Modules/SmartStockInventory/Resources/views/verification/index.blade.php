@extends('smartstockinventory::layouts.master')
@section('page_title', 'Verification Report')
@section('module_content')
<div class="box box-primary"><div class="box-body">
<form method="get" class="row">
<div class="col-md-2"><input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}"></div>
<div class="col-md-2"><input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}"></div>
<div class="col-md-2"><input class="form-control" name="session_id" value="{{ request('session_id') }}" placeholder="Session ID"></div>
<div class="col-md-2"><input class="form-control" name="location_id" value="{{ request('location_id') }}" placeholder="Location"></div>
<div class="col-md-4 text-right"><button class="btn btn-primary">Filter</button> <a class="btn btn-success" href="{{ route('ssi.verification.export', request()->all()) }}">Export</a> <a class="btn btn-default" href="{{ route('ssi.verification.print', request()->all()) }}">Print</a></div>
</form>
</div></div>
<div class="row">
<div class="col-md-4"><div class="alert alert-danger"><b>Total Missing Value:</b> {{ number_format($totalMissingValue,2) }}</div></div>
<div class="col-md-4"><div class="alert alert-success"><b>Total Over Value:</b> {{ number_format($totalOverValue,2) }}</div></div>
<div class="col-md-4"><div class="alert alert-info"><b>Net Difference:</b> {{ number_format($netDifference,2) }}</div></div>
</div>
<div class="box box-primary"><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Product</th><th>SKU</th><th>Location</th><th>System Qty</th><th>Count Qty</th><th>Difference</th><th>Stock Value Difference</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->product }}</td><td>{{ $row->sku }}</td><td>{{ $row->location_id }}</td><td>{{ $row->system_qty }}</td><td>{{ $row->count_qty }}</td><td>{{ $row->difference }}</td><td>{{ $row->stock_value_difference }}</td><td>{{ $row->status }}</td><td><form method="post" action="{{ route('ssi.verification.approve', $row->session_id) }}" style="display:inline-block;">@csrf<button class="btn btn-xs btn-success">Approve</button></form> <form method="post" action="{{ route('ssi.verification.reject', $row->session_id) }}" style="display:inline-block;">@csrf<button class="btn btn-xs btn-danger">Reject</button></form> <form method="post" action="{{ route('ssi.verification.recount', $row->session_id) }}" style="display:inline-block;">@csrf<button class="btn btn-xs btn-warning">Recount</button></form></td></tr>@empty<tr><td colspan="9" class="text-center">No verification data found</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div></div>
@endsection
