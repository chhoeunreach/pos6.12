@extends('smartstockinventory::layouts.master')
@section('page_title', 'Lot Management')
@section('module_content')
<form class="row" method="get"><div class="col-md-4"><input class="form-control" name="q" value="{{ $q }}" placeholder="Search Lot"></div><div class="col-md-2"><button class="btn btn-primary">Search</button></div></form>
<div class="text-right" style="margin-bottom:8px;"><a class="btn btn-success btn-sm" href="{{ route('ssi.lot.export', request()->all()) }}">Export</a> <button onclick="window.print()" class="btn btn-default btn-sm">Print</button></div>
<div class="box box-default"><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Lot</th><th>Expiry</th><th>Qty In</th><th>Qty Out</th><th>Balance</th><th>Date</th><th>Action</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->lot_number }}</td><td>{{ $row->expiry_date }}</td><td>{{ $row->qty_in }}</td><td>{{ $row->qty_out }}</td><td>{{ $row->balance_qty }}</td><td>{{ $row->movement_date }}</td><td><a class="btn btn-xs btn-info" href="{{ route('ssi.lot.history', $row->lot_number) }}">View Movement</a></td></tr>@empty<tr><td colspan="7" class="text-center">No lot data found</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div></div>
<div class="box box-danger"><div class="box-header">Duplicate Lots</div><div class="box-body"><ul>@foreach($duplicates as $d)<li>{{ $d->lot_number }} ({{ $d->total }})</li>@endforeach</ul></div></div>
@endsection
