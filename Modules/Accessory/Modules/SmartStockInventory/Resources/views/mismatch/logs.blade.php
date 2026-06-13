@extends('smartstockinventory::layouts.master')
@section('page_title', 'Fix Logs')
@section('module_content')
<div class="box box-primary"><div class="box-body">
<form method="get" class="row">
<div class="col-md-2"><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div>
<div class="col-md-2"><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div>
<div class="col-md-2"><input class="form-control" name="user_id" value="{{ request('user_id') }}" placeholder="User"></div>
<div class="col-md-2"><input class="form-control" name="location_id" value="{{ request('location_id') }}" placeholder="Location"></div>
<div class="col-md-2"><input class="form-control" name="problem_type" value="{{ request('problem_type') }}" placeholder="Problem"></div>
<div class="col-md-2 text-right"><button class="btn btn-primary">Filter</button> <button type="button" onclick="window.print()" class="btn btn-default">Print</button></div>
</form>
</div></div>
<div class="box box-default"><div class="box-body table-responsive">
<table class="table table-bordered table-striped"><thead><tr><th>Date</th><th>User</th><th>Product</th><th>Location</th><th>Problem Type</th><th>Old Qty</th><th>New Qty</th><th>Action</th><th>Rollback</th><th>Delete</th></tr></thead>
<tbody>
@foreach($logs as $log)
<tr>
<td>{{ $log->created_at }}</td><td>{{ $log->created_by }}</td><td>{{ $log->product_name ?? '-' }} {{ $log->sub_sku ?? '' }}</td><td>{{ $log->location_name ?? '-' }}</td>
<td>{{ $log->problem_type ?? $log->fix_type }}</td><td>{{ $log->old_qty }}</td><td>{{ $log->new_qty }}</td><td>{{ $log->fix_type }}</td>
<td>
@if(!$log->is_rollback && $log->rollbackable)
<form method="post" action="{{ route('ssi.mismatch.rollback') }}">@csrf
<input type="hidden" name="fix_log_id" value="{{ $log->id }}"><input type="hidden" name="reason" value="manual_rollback">
<button class="btn btn-xs btn-danger">Rollback</button>
</form>
@else N/A @endif
</td>
<td>
<form method="post" action="{{ route('ssi.fix_logs.delete', $log->id) }}">@csrf @method('DELETE')
<input type="hidden" name="reason" value="incorrect_log_entry"><button class="btn btn-xs btn-danger">Delete</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>{{ $logs->links() }}</div></div>
@endsection
