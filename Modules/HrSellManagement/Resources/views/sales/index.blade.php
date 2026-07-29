@extends('hrsellmanagement::layouts.master')
@php($isLinkMode = request()->query('mode') === 'link')
@section('page_title', $isLinkMode ? 'Sell From HR' : 'HR Sell List')
@section('module_content')
<div class="box box-primary" id="link-sale"><div class="box-header"><h4>Link Existing POS Sale to HR</h4></div><div class="box-body">
<form method="post" action="{{ route('hr-sell.sales.link') }}">@csrf
<div class="row">
<div class="col-md-4"><label>POS Sale</label><select name="transaction_id" class="form-control select2" required><option value="">Select sale</option>@foreach($unlinkedSales as $sale)<option value="{{ $sale->id }}">{{ $sale->invoice_no }} - {{ @format_datetime($sale->transaction_date) }} - {{ @num_format($sale->final_total) }}</option>@endforeach</select></div>
<div class="col-md-3"><label>HR Staff</label><select name="hr_user_id" class="form-control select2" required><option value="">Select staff</option>@foreach($users as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
<div class="col-md-2"><label>Commission Type</label><select name="commission_type" class="form-control"><option value="percent">Percent</option><option value="fixed">Fixed</option></select></div>
<div class="col-md-2"><label>Commission</label><input name="commission_value" type="number" step="0.0001" class="form-control" value="0"></div>
<div class="col-md-1" style="padding-top:24px;"><button class="btn btn-primary">Link</button></div>
</div>
<div class="row" style="margin-top:8px;"><div class="col-md-3"><label>Follow-up Date</label><input type="date" name="follow_up_date" class="form-control"></div><div class="col-md-9"><label>Internal Note</label><input name="internal_note" class="form-control"></div></div>
</form>
</div></div>

<div class="box box-info"><div class="box-header"><h4>Filter HR Sell Database</h4></div><div class="box-body">
<form method="get" action="{{ route('hr-sell.sales.index') }}">
<input type="hidden" name="mode" value="{{ request('mode') }}">
<div class="row">
<div class="col-md-2"><label>Location / Branch</label><select name="location_id" class="form-control select2"><option value="">All locations</option>@foreach($businessLocations as $id => $name)<option value="{{ $id }}" @selected((string) request('location_id') === (string) $id)>{{ $name }}</option>@endforeach</select></div>
<div class="col-md-3"><label>Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice, customer, note"></div>
<div class="col-md-2"><label>Start</label><input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
<div class="col-md-2"><label>End</label><input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
<div class="col-md-3"><label>HR Staff</label><select name="hr_user_id" class="form-control select2"><option value="">All</option>@foreach($users as $id => $name)<option value="{{ $id }}" @selected((string) request('hr_user_id') === (string) $id)>{{ $name }}</option>@endforeach</select></div>
</div>
<div class="row" style="margin-top:8px;">
<div class="col-md-3"><label>Status</label><select name="status" class="form-control"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<div class="col-md-3"><label>Approval</label><select name="approval_status" class="form-control"><option value="">All</option>@foreach($approvalStatuses as $status)<option value="{{ $status }}" @selected(request('approval_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
<div class="col-md-3"><label>Follow-up Status</label><select name="follow_up_status" class="form-control"><option value="">All</option>@foreach($followUpStatuses as $status)<option value="{{ $status }}" @selected(request('follow_up_status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<div class="col-md-3" style="padding-top:24px;"><button class="btn btn-primary">Filter</button> <a class="btn btn-default" href="{{ route('hr-sell.sales.index') }}">Reset</a> <a class="btn btn-success" href="{{ route('hr-sell.reports.index', request()->except('mode')) }}">Full Report</a></div>
</div>
</form>
</div></div>

<div class="box box-default" id="hr-sell-list"><div class="box-header"><h4>Managed HR Sales</h4></div><div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="hr_sell_table"><thead><tr><th>Invoice</th><th>Date</th><th>Location / Branch</th><th>Customer</th><th>HR Staff</th><th>Status</th><th>Total</th><th>Paid</th><th>Due</th><th>Commission</th><th>Approval</th><th>Follow-up</th><th>Action</th></tr></thead><tbody>
@foreach($records as $record)
<tr>
<td>{{ optional($record->transaction)->invoice_no }}</td>
<td>{{ optional($record->transaction)->transaction_date }}</td>
<td>{{ $businessLocations[$record->location_id] ?? optional(optional($record->transaction)->location)->name ?? '-' }}</td>
<td>{{ optional(optional($record->transaction)->contact)->name }}</td>
<td>{{ optional($record->hrUser)->first_name }} {{ optional($record->hrUser)->last_name }}</td>
<td>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</td>
<td>{{ number_format((float) $record->sale_total, 2) }}</td>
<td>{{ number_format((float) $record->paid_total, 2) }}</td>
<td>{{ number_format((float) $record->due_total, 2) }}</td>
<td>{{ number_format((float) $record->commission_amount, 2) }}</td>
<td>{{ $record->approval_status }}</td>
<td>{{ $record->follow_up_date }}</td>
<td><a class="btn btn-xs btn-primary" href="{{ route('hr-sell.sales.show', $record->id) }}">Open</a></td>
</tr>
@endforeach
</tbody></table>
{{ $records->links() }}
</div></div>
@endsection
@section('module_js')
<script>
$(function(){
    $('.select2').select2();
    @if($isLinkMode)
        var linkBox = $('#link-sale');
        if (linkBox.length) {
            $('html, body').animate({ scrollTop: linkBox.offset().top - 70 }, 200);
        }
    @endif
});
</script>
@endsection
