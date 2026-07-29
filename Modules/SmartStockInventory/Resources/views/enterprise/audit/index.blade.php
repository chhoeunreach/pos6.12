@extends('smartstockinventory::layouts.master')
@section('page_title', 'Enterprise Stock Audit')
@section('module_content')
<style>
.ssi-kpi{border:1px solid #d2d6de;background:#fff;padding:14px;min-height:94px;margin-bottom:12px}.ssi-kpi span{display:block;color:#667085}.ssi-kpi strong{display:block;font-size:26px;line-height:1.2}.ssi-actions{display:flex;gap:6px;flex-wrap:wrap}
@media(max-width:767px){.ssi-actions .btn,.ssi-actions form{width:100%}.ssi-actions form .btn{width:100%}}
</style>
<div class="row">
<div class="col-md-3"><div class="ssi-kpi"><span>Current Inventory</span><strong>{{ number_format($metrics['current_inventory'], 2) }}</strong></div></div>
<div class="col-md-3"><div class="ssi-kpi"><span>Inventory Value</span><strong>{{ number_format($metrics['inventory_value'], 2) }}</strong></div></div>
<div class="col-md-2"><div class="ssi-kpi"><span>Accuracy</span><strong>{{ $metrics['accuracy_percent'] }}%</strong></div></div>
<div class="col-md-2"><div class="ssi-kpi"><span>Pending Audits</span><strong>{{ $metrics['pending_audits'] }}</strong></div></div>
<div class="col-md-2"><div class="ssi-kpi"><span>Investigations</span><strong>{{ $metrics['pending_investigations'] }}</strong></div></div>
</div>
<div class="row">
<div class="col-md-4"><div class="ssi-kpi"><span>Low Stock</span><strong>{{ $metrics['low_stock'] }}</strong></div></div>
<div class="col-md-4"><div class="ssi-kpi"><span>Negative Stock</span><strong>{{ $metrics['negative_stock'] }}</strong></div></div>
<div class="col-md-4"><div class="ssi-kpi"><span>Dead Stock</span><strong>{{ $metrics['dead_stock'] }}</strong></div></div>
</div>

<div class="box box-primary">
<div class="box-header with-border"><h4 class="box-title">Create Audit Session</h4></div>
<form method="post" action="{{ ssi_route('ssi.enterprise.audit.store') }}">@csrf
<div class="box-body">
<div class="row">
<div class="col-md-3"><label>Name</label><input name="name" class="form-control" required></div>
<div class="col-md-2"><label>Location</label><select name="location_id" class="form-control"><option value="">All</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
<div class="col-md-2"><label>Audit Type</label><select name="audit_type" class="form-control"><option value="cycle">Cycle Count</option><option value="blind">Blind Count</option><option value="spot">Spot Count</option><option value="full">Full Count</option><option value="recount">Recount</option></select></div>
<div class="col-md-2"><label>Count Mode</label><select name="count_mode" class="form-control"><option value="normal">Normal</option><option value="blind">Blind</option></select></div>
<div class="col-md-3"><label>Scheduled At</label><input type="datetime-local" name="scheduled_at" class="form-control"></div>
</div>
<div class="row" style="margin-top:10px;">
<div class="col-md-9"><label>Notes</label><input name="notes" class="form-control"></div>
<div class="col-md-3" style="padding-top:24px;"><button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Create Audit</button></div>
</div>
</div>
</form>
</div>

<div class="box box-default">
<div class="box-header with-border"><h4 class="box-title">Audit Sessions</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="ssi_audits_table">
<thead><tr><th>No</th><th>Name</th><th>Type</th><th>Mode</th><th>Status</th><th>Scheduled</th><th>Actions</th></tr></thead>
<tbody>
@foreach($audits as $audit)
<tr>
<td>{{ $audit->audit_no }}</td>
<td>{{ $audit->name }}</td>
<td>{{ $audit->audit_type }}</td>
<td>{{ $audit->count_mode }}</td>
<td><span class="label label-default">{{ $audit->status }}</span></td>
<td>{{ $audit->scheduled_at }}</td>
<td><div class="ssi-actions">
<a class="btn btn-xs btn-primary" href="{{ ssi_route('ssi.enterprise.audit.show', $audit->id) }}">Open</a>
<a class="btn btn-xs btn-info" href="{{ ssi_route('ssi.enterprise.scanner.mobile', $audit->id) }}">Scanner</a>
<form method="post" action="{{ ssi_route('ssi.enterprise.audit.start', $audit->id) }}">@csrf<button class="btn btn-xs btn-success">Start</button></form>
</div></td>
</tr>
@endforeach
</tbody>
</table>
{{ $audits->links() }}
</div>
</div>
@endsection
@section('module_js')
<script>$(function(){ $('#ssi_audits_table').DataTable({pageLength:25,order:[]}); });</script>
@endsection
