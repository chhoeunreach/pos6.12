@extends('layouts.app')
@section('title', 'Fix Logs')
@section('content')
<section class="content-header"><h1>Fix Logs</h1></section>
<section class="content"><div class="box"><div class="box-body">
<table class="table table-bordered table-striped"><thead><tr><th>ID</th><th>Purchase Line</th><th>Problem</th><th>Status</th><th>User</th><th>Reason</th><th>Date</th></tr></thead><tbody>
@foreach($logs as $log)
<tr><td>{{$log->id}}</td><td>{{$log->purchase_line_id}}</td><td>{{$log->problem_type}}</td><td><span class="label {{$log->status==='fixed'?'label-success':'label-danger'}}">{{$log->status}}</span></td><td>{{$log->user_id}}</td><td>{{$log->reason}}</td><td>{{$log->created_at}}</td></tr>
@endforeach
</tbody></table>
{{ $logs->links() }}
</div></div></section>
@endsection
