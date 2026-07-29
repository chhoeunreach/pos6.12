@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell Dashboard')
@section('module_content')
<div class="row">
@foreach([
    ['Total Sales', $metrics['total_sales'], 'bg-aqua', 'fa-money'],
    ['Today Sales', $metrics['today_sales'], 'bg-green', 'fa-calendar'],
    ['Pending Approval', $metrics['pending_approval'], 'bg-yellow', 'fa-clock-o'],
    ['Follow-ups Due', $metrics['followups_due'], 'bg-red', 'fa-phone'],
    ['Approved Commission', $metrics['commission_total'], 'bg-purple', 'fa-percent'],
    ['Due Amount', $metrics['due_total'], 'bg-maroon', 'fa-warning'],
] as $card)
<div class="col-md-2 col-sm-4"><div class="small-box {{ $card[2] }}"><div class="inner"><h3>{{ is_numeric($card[1]) ? number_format((float) $card[1], 2) : $card[1] }}</h3><p>{{ $card[0] }}</p></div><div class="icon"><i class="fa {{ $card[3] }}"></i></div></div></div>
@endforeach
</div>
<div class="row">
<div class="col-md-6"><div class="box box-primary"><div class="box-header"><h4>Top HR Staff</h4></div><div class="box-body table-responsive">
<table class="table table-bordered table-striped"><thead><tr><th>Staff</th><th>Sales</th><th>Total</th><th>Commission</th></tr></thead><tbody>
@foreach($topHr as $row)<tr><td>{{ trim($row->user_name) ?: $row->hr_user_id }}</td><td>{{ $row->sale_count }}</td><td>{{ number_format((float) $row->sale_total, 2) }}</td><td>{{ number_format((float) $row->commission_total, 2) }}</td></tr>@endforeach
</tbody></table>
</div></div></div>
<div class="col-md-6"><div class="box box-default"><div class="box-header"><h4>Recent HR Sales</h4></div><div class="box-body table-responsive">
<table class="table table-bordered table-striped"><thead><tr><th>Invoice</th><th>Customer</th><th>HR</th><th>Total</th><th>Approval</th><th>Follow-up</th></tr></thead><tbody>
@foreach($recent as $row)<tr><td><a href="{{ route('hr-sell.sales.show', $row->id) }}">{{ $row->invoice_no }}</a></td><td>{{ $row->customer }}</td><td>{{ trim($row->hr_name) ?: '-' }}</td><td>{{ number_format((float) $row->sale_total, 2) }}</td><td>{{ $row->approval_status }}</td><td>{{ $row->follow_up_date }}</td></tr>@endforeach
</tbody></table>
</div></div></div>
</div>
@endsection
