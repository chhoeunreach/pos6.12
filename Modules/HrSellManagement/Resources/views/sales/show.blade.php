@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sale Detail')
@section('module_content')
@php
    $currentUser = auth()->user();
    $canUpdateHrSell = $currentUser->can('hr_sell.update') || $currentUser->can('superadmin') || $currentUser->can('business_settings.access');
    $canApproveHrSell = $currentUser->can('hr_sell.approve') || $currentUser->can('superadmin') || $currentUser->can('business_settings.access');
@endphp
<div class="box box-primary"><div class="box-header"><h4>Invoice: {{ optional($hrSell->transaction)->invoice_no }}</h4></div><div class="box-body">
<div class="row">
<div class="col-md-3"><strong>Customer</strong><br>{{ optional(optional($hrSell->transaction)->contact)->name }}</div>
<div class="col-md-2"><strong>Total</strong><br>{{ number_format((float) $hrSell->sale_total, 2) }}</div>
<div class="col-md-2"><strong>Paid</strong><br>{{ number_format((float) $hrSell->paid_total, 2) }}</div>
<div class="col-md-2"><strong>Due</strong><br>{{ number_format((float) $hrSell->due_total, 2) }}</div>
<div class="col-md-3"><strong>Approval</strong><br>{{ $hrSell->approval_status }}</div>
</div>
</div></div>

<div class="row">
<div class="col-md-6"><div class="box box-warning"><div class="box-header"><h4>Control</h4></div><div class="box-body">
@if($canUpdateHrSell)
<form method="post" action="{{ route('hr-sell.sales.update', $hrSell->id) }}">@csrf
<div class="row">
<div class="col-md-6"><label>HR Staff</label><select name="hr_user_id" class="form-control select2">@foreach($users as $id => $name)<option value="{{ $id }}" @selected($hrSell->hr_user_id == $id)>{{ $name }}</option>@endforeach</select></div>
<div class="col-md-6"><label>Status</label><select name="status" class="form-control"><option value="active" @selected($hrSell->status=='active')>Active</option><option value="on_hold" @selected($hrSell->status=='on_hold')>On Hold</option><option value="completed" @selected($hrSell->status=='completed')>Completed</option><option value="cancelled" @selected($hrSell->status=='cancelled')>Cancelled</option></select></div>
</div>
<div class="row" style="margin-top:8px;">
<div class="col-md-4"><label>Commission Type</label><select name="commission_type" class="form-control"><option value="percent" @selected($hrSell->commission_type=='percent')>Percent</option><option value="fixed" @selected($hrSell->commission_type=='fixed')>Fixed</option></select></div>
<div class="col-md-4"><label>Commission</label><input name="commission_value" type="number" step="0.0001" class="form-control" value="{{ $hrSell->commission_value }}"></div>
<div class="col-md-4"><label>Follow-up</label><input name="follow_up_date" type="date" class="form-control" value="{{ optional($hrSell->follow_up_date)->format('Y-m-d') }}"></div>
</div>
<div style="margin-top:8px;"><label>Internal Note</label><textarea name="internal_note" class="form-control" rows="3">{{ $hrSell->internal_note }}</textarea></div>
<button class="btn btn-primary" style="margin-top:10px;">Save Control</button>
</form>
@else
<p class="text-muted">You do not have permission to update this HR sale.</p>
@endif
</div></div></div>

<div class="col-md-6"><div class="box box-success"><div class="box-header"><h4>Approval Workflow</h4></div><div class="box-body table-responsive">
<table class="table table-bordered"><thead><tr><th>Level</th><th>Status</th><th>By</th><th>Action</th></tr></thead><tbody>
@foreach($hrSell->approvals as $approval)
<tr><td>{{ $approval->level }}</td><td>{{ $approval->status }}</td><td>{{ $approval->approved_by }}</td><td>
@if($canApproveHrSell)
<form method="post" action="{{ route('hr-sell.sales.approve', $hrSell->id) }}" style="display:inline-block;">@csrf<input type="hidden" name="level" value="{{ $approval->level }}"><input type="hidden" name="status" value="approved"><button class="btn btn-xs btn-success">Approve</button></form>
<form method="post" action="{{ route('hr-sell.sales.approve', $hrSell->id) }}" style="display:inline-block;">@csrf<input type="hidden" name="level" value="{{ $approval->level }}"><input type="hidden" name="status" value="rejected"><button class="btn btn-xs btn-danger">Reject</button></form>
@else
<span class="text-muted">No permission</span>
@endif
</td></tr>
@endforeach
</tbody></table>
</div></div></div>
</div>

<div class="row">
<div class="col-md-6"><div class="box box-default"><div class="box-header"><h4>Follow-up Notes</h4></div><div class="box-body">
@if($canUpdateHrSell)
<form method="post" action="{{ route('hr-sell.sales.notes.store', $hrSell->id) }}">@csrf
<div class="row"><div class="col-md-4"><select name="note_type" class="form-control"><option value="note">Note</option><option value="call">Call</option><option value="visit">Visit</option><option value="problem">Problem</option><option value="promise">Promise</option></select></div><div class="col-md-4"><input type="date" name="next_follow_up_date" class="form-control"></div><div class="col-md-4"><button class="btn btn-primary btn-block">Add Note</button></div></div>
<textarea name="note" class="form-control" rows="3" style="margin-top:8px;" required></textarea>
</form>
<hr>
@endif
@foreach($hrSell->notes as $note)<p><strong>{{ $note->note_type }}</strong> {{ $note->created_at }}<br>{{ $note->note }}</p>@endforeach
</div></div></div>
<div class="col-md-6"><div class="box box-default"><div class="box-header"><h4>Activity Logs</h4></div><div class="box-body table-responsive">
<table class="table table-bordered"><thead><tr><th>When</th><th>User</th><th>Action</th></tr></thead><tbody>@foreach($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->user_name }}</td><td>{{ $log->action }}</td></tr>@endforeach</tbody></table>
</div></div></div>
</div>
@endsection
@section('module_js')
<script>$(function(){ $('.select2').select2(); });</script>
@endsection
