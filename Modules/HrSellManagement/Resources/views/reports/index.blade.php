@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell Reports')
@section('module_content')
<div class="box box-primary"><div class="box-body">
<form method="get" action="{{ route('hr-sell.reports.index') }}"><div class="row">
<div class="col-md-3"><label>Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice, customer, note"></div>
<div class="col-md-2"><label>Start</label><input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
<div class="col-md-2"><label>End</label><input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
<div class="col-md-3"><label>HR Staff</label><select name="hr_user_id" class="form-control select2"><option value="">All</option>@foreach($users as $id => $name)<option value="{{ $id }}" @selected((string) request('hr_user_id') === (string) $id)>{{ $name }}</option>@endforeach</select></div>
<div class="col-md-2"><label>Status</label><select name="status" class="form-control"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
</div>
<div class="row" style="margin-top:8px;">
<div class="col-md-3"><label>Approval</label><select name="approval_status" class="form-control"><option value="">All</option>@foreach($approvalStatuses as $status)<option value="{{ $status }}" @selected(request('approval_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
<div class="col-md-3"><label>Follow-up Status</label><select name="follow_up_status" class="form-control"><option value="">All</option>@foreach($followUpStatuses as $status)<option value="{{ $status }}" @selected(request('follow_up_status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<div class="col-md-6" style="padding-top:24px;"><button class="btn btn-primary">Filter</button> <a class="btn btn-default" href="{{ route('hr-sell.reports.index') }}">Reset</a> <a class="btn btn-success" href="{{ route('hr-sell.reports.export', request()->all()) }}">Export All Filtered</a></div>
</div></form>
</div></div>
<div class="row">
@foreach(['sale_count'=>'Sales','sale_total'=>'Total','paid_total'=>'Paid','due_total'=>'Due','commission_amount'=>'Commission'] as $key => $label)
<div class="col-md-2"><div class="small-box bg-aqua"><div class="inner"><h3>{{ $key === 'sale_count' ? number_format((float) $summary[$key], 0) : number_format((float) $summary[$key], 2) }}</h3><p>{{ $label }}</p></div></div></div>
@endforeach
</div>
<div class="box box-default"><div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="hr_sell_report_table"><thead><tr><th>ID</th><th>Invoice</th><th>Date</th><th>Location</th><th>Customer</th><th>HR</th><th>Supervisor</th><th>Status</th><th>Approval</th><th>Follow-up</th><th>Follow-up Status</th><th>Total</th><th>Paid</th><th>Due</th><th>Commission Type</th><th>Commission Value</th><th>Commission</th><th>Created By</th><th>Created At</th><th>Action</th></tr></thead><tbody>
@foreach($rows as $row)<tr>
<td>{{ $row->id }}</td>
<td>{{ $row->invoice_no }}</td>
<td>{{ $row->transaction_date }}</td>
<td>{{ $row->location_name }}</td>
<td>{{ $row->customer }}</td>
<td>{{ trim($row->hr_name) ?: '-' }}</td>
<td>{{ trim($row->supervisor_name) ?: '-' }}</td>
<td>{{ ucfirst(str_replace('_', ' ', $row->status)) }}</td>
<td>{{ ucfirst($row->approval_status) }}</td>
<td>{{ $row->follow_up_date }}</td>
<td>{{ ucfirst(str_replace('_', ' ', $row->follow_up_status)) }}</td>
<td>{{ number_format((float) $row->sale_total, 2) }}</td>
<td>{{ number_format((float) $row->paid_total, 2) }}</td>
<td>{{ number_format((float) $row->due_total, 2) }}</td>
<td>{{ ucfirst($row->commission_type) }}</td>
<td>{{ number_format((float) $row->commission_value, 4) }}</td>
<td>{{ number_format((float) $row->commission_amount, 2) }}</td>
<td>{{ trim($row->created_by_name) ?: '-' }}</td>
<td>{{ $row->created_at }}</td>
<td><a class="btn btn-xs btn-primary" href="{{ route('hr-sell.sales.show', $row->id) }}">Open</a></td>
</tr>@endforeach
</tbody></table>
{{ $rows->links() }}
</div></div>
@endsection
@section('module_js')
<script>$(function(){ $('.select2').select2(); });</script>
@endsection
