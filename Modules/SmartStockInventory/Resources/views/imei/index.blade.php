@extends('smartstockinventory::layouts.master')
@section('page_title', 'IMEI Management')
@section('module_content')
<form class="row" method="get"><div class="col-md-4"><input class="form-control" name="q" value="{{ $q }}" placeholder="Search IMEI"></div><div class="col-md-2"><button class="btn btn-primary">Search</button></div></form>
<div class="text-right" style="margin-bottom:8px;"><a class="btn btn-success btn-sm" href="{{ route('ssi.imei.export', request()->all()) }}">Export</a> <button onclick="window.print()" class="btn btn-default btn-sm">Print</button></div>
<div class="box box-default"><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>IMEI</th><th>Status</th><th>Ref Type</th><th>Ref ID</th><th>Date</th><th>Action</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->imei }}</td><td>{{ $row->status }}</td><td>{{ $row->reference_type }}</td><td>{{ $row->reference_id }}</td><td>{{ $row->movement_date }}</td><td><a class="btn btn-xs btn-info" href="{{ route('ssi.imei.history', $row->imei) }}">View History</a></td></tr>@empty<tr><td colspan="6" class="text-center">No IMEI data found</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div></div>
<div class="box box-danger"><div class="box-header">Duplicate IMEI</div><div class="box-body"><ul>@foreach($duplicates as $d)<li>{{ $d->imei }} ({{ $d->total }})</li>@endforeach</ul></div></div>
@endsection
