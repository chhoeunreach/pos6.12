@extends('smartstockinventory::layouts.master')
@section('page_title', 'Audit ' . $audit->audit_no)
@section('module_content')
<div class="box box-primary">
<div class="box-header with-border"><h4 class="box-title">{{ $audit->audit_no }} - {{ $audit->name }} <span class="label label-default">{{ $audit->status }}</span></h4>
<div class="pull-right"><a class="btn btn-info btn-sm" href="{{ ssi_route('ssi.enterprise.scanner.mobile', $audit->id) }}"><i class="fa fa-barcode"></i> Scanner</a></div></div>
<div class="box-body">
<div class="row">
<div class="col-md-3"><strong>Type</strong><br>{{ $audit->audit_type }}</div>
<div class="col-md-3"><strong>Mode</strong><br>{{ $audit->count_mode }}</div>
<div class="col-md-3"><strong>Scheduled</strong><br>{{ $audit->scheduled_at }}</div>
<div class="col-md-3"><strong>Started</strong><br>{{ $audit->started_at }}</div>
</div>
</div>
</div>

<div class="box box-warning">
<div class="box-header with-border"><h4 class="box-title">Difference Report</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="ssi_items_table">
<thead><tr><th>SKU</th><th>Product</th><th>IMEI</th><th>Warehouse</th><th>Expected</th><th>Counted</th><th>Difference</th><th>Status</th><th>Verify</th></tr></thead>
<tbody>
@foreach($audit->items as $item)
<tr>
<td>{{ $item->sku }}</td>
<td>{{ $item->product_name }}</td>
<td>{{ $item->imei ?: $item->serial }}</td>
<td>{{ collect([$item->warehouse,$item->zone,$item->rack,$item->shelf,$item->bin])->filter()->implode(' / ') }}</td>
<td>{{ number_format((float) $item->expected_qty, 4) }}</td>
<td>{{ number_format((float) $item->counted_qty, 4) }}</td>
<td>{{ number_format((float) $item->difference_qty, 4) }}</td>
<td>{{ $item->verification_status }} @if($item->mismatch_type)<small>({{ $item->mismatch_type }})</small>@endif</td>
<td><form method="post" action="{{ ssi_route('ssi.enterprise.audit.items.verify', ['audit' => $audit->id, 'item' => $item->id]) }}" class="form-inline">@csrf<input type="number" step="0.0001" name="verified_qty" value="{{ $item->counted_qty }}" class="form-control input-sm" style="width:110px;"><button class="btn btn-xs btn-default">Verify</button></form></td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>

<div class="row">
<div class="col-md-6"><div class="box box-default"><div class="box-header with-border"><h4 class="box-title">Approval Workflow</h4></div><div class="box-body table-responsive">
<table class="table table-bordered"><thead><tr><th>Step</th><th>Status</th><th>By</th><th>Action</th></tr></thead><tbody>
@foreach($audit->approvals as $approval)
<tr><td>{{ $approval->sequence }}. {{ $approval->approval_level }}</td><td>{{ $approval->status }}</td><td>{{ $approval->approved_by }}</td><td><form method="post" action="{{ ssi_route('ssi.enterprise.audit.approve', $audit->id) }}">@csrf<input type="hidden" name="approval_level" value="{{ $approval->approval_level }}"><button class="btn btn-xs btn-success" @if($approval->status === 'approved') disabled @endif>Approve</button></form></td></tr>
@endforeach
</tbody></table>
</div></div></div>
<div class="col-md-6"><div class="box box-default"><div class="box-header with-border"><h4 class="box-title">Investigations</h4></div><div class="box-body table-responsive">
<table class="table table-bordered"><thead><tr><th>Case</th><th>Type</th><th>Status</th><th>Opened</th></tr></thead><tbody>
@forelse($audit->investigations as $case)<tr><td>{{ $case->case_no }}</td><td>{{ $case->case_type }}</td><td>{{ $case->status }}</td><td>{{ $case->opened_at }}</td></tr>@empty<tr><td colspan="4">No investigations.</td></tr>@endforelse
</tbody></table>
</div></div></div>
</div>

<div class="box box-default"><div class="box-header with-border"><h4 class="box-title">Audit Logs</h4></div><div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="ssi_logs_table"><thead><tr><th>When</th><th>User</th><th>Type</th><th>Action</th><th>IP</th></tr></thead><tbody>
@foreach($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->user_name ?: $log->user_id }}</td><td>{{ $log->log_type }}</td><td>{{ $log->action }}</td><td>{{ $log->ip_address }}</td></tr>@endforeach
</tbody></table>
</div></div>
@endsection
@section('module_js')
<script>$(function(){ $('#ssi_items_table,#ssi_logs_table').DataTable({pageLength:25,order:[]}); });</script>
@endsection
