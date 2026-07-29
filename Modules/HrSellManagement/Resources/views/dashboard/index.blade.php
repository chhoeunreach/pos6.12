@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell Dashboard')
@section('module_content')
@unless($hrConnectionOk)
<div class="alert alert-warning">
    HR POS sell data is not available: {{ $hrConnectionMessage }}
</div>
@endunless

<div class="row">
@foreach([
    ['POS Total Sales', $metrics['pos_total_sales'], 'bg-aqua', 'fa-money'],
    ['POS Sale Count', $metrics['pos_total_count'], 'bg-blue', 'fa-list'],
    ['Today Sales', $metrics['pos_today_sales'], 'bg-green', 'fa-calendar'],
    ['Today Count', $metrics['pos_today_count'], 'bg-teal', 'fa-shopping-cart'],
    ['Average Sale', $metrics['pos_average_sale'], 'bg-purple', 'fa-line-chart'],
    ['Branches', $metrics['pos_branch_count'], 'bg-navy', 'fa-map-marker'],
] as $card)
<div class="col-md-2 col-sm-4">
<div class="small-box {{ $card[2] }}">
<div class="inner"><h3>{{ number_format((float) $card[1], in_array($card[0], ['POS Sale Count', 'Today Count', 'Branches']) ? 0 : 2) }}</h3><p>{{ $card[0] }}</p></div>
<div class="icon"><i class="fa {{ $card[3] }}"></i></div>
</div>
</div>
@endforeach
</div>

<div class="row">
@foreach([
    ['Managed Sales', $metrics['managed_count'], 'bg-aqua', 'fa-check-square-o'],
    ['Pending Approval', $metrics['pending_approval'], 'bg-yellow', 'fa-clock-o'],
    ['Follow-ups Due', $metrics['followups_due'], 'bg-red', 'fa-phone'],
    ['Approved Commission', $metrics['commission_total'], 'bg-purple', 'fa-percent'],
    ['Managed Due', $metrics['due_total'], 'bg-maroon', 'fa-warning'],
    ['Sellers', $metrics['pos_seller_count'], 'bg-olive', 'fa-users'],
] as $card)
<div class="col-md-2 col-sm-4">
<div class="small-box {{ $card[2] }}">
<div class="inner"><h3>{{ number_format((float) $card[1], in_array($card[0], ['Managed Sales', 'Pending Approval', 'Follow-ups Due', 'Sellers']) ? 0 : 2) }}</h3><p>{{ $card[0] }}</p></div>
<div class="icon"><i class="fa {{ $card[3] }}"></i></div>
</div>
</div>
@endforeach
</div>

<div class="row">
<div class="col-md-6">
<div class="box box-primary">
<div class="box-header"><h4>Top HR Staff</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Staff</th><th>Sales</th><th>Total</th></tr></thead>
<tbody>
@forelse($topHr as $row)
<tr><td>{{ $row->user_name }}</td><td>{{ number_format((float) $row->sale_count, 0) }}</td><td>{{ number_format((float) $row->sale_total, 2) }}</td></tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No HR staff sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>

<div class="col-md-6">
<div class="box box-info">
<div class="box-header"><h4>Top Branches</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Branch</th><th>Sales</th><th>Total</th></tr></thead>
<tbody>
@forelse($topBranches as $row)
<tr><td>{{ $row->branch_name }}</td><td>{{ number_format((float) $row->sale_count, 0) }}</td><td>{{ number_format((float) $row->sale_total, 2) }}</td></tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No branch sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="box box-default">
<div class="box-header"><h4>Recent POS HR Sales</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="hr_sell_dashboard_recent_table">
<thead><tr><th>Invoice</th><th>Date</th><th>Branch</th><th>Customer</th><th>Phone</th><th>Seller</th><th>Type</th><th>Total</th></tr></thead>
<tbody>
@forelse($recent as $row)
<tr>
<td>{{ $row->invoice_no }}</td>
<td>{{ $row->created_at }}</td>
<td>{{ $row->branch_name }}</td>
<td>{{ $row->customer_name }}</td>
<td>{{ $row->customer_phone }}</td>
<td>{{ $row->staff_name ?: '-' }}</td>
<td>{{ in_array($row->service_type, ['sell', 'លក់']) ? 'Sell / លក់' : $row->service_type }}</td>
<td>{{ number_format((float) $row->total_amount, 2) }}</td>
</tr>
@empty
<tr><td colspan="8" class="text-center text-muted">No recent POS HR sales found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
@endsection
@section('module_js')
<script>
$(function(){
    if ($.fn.DataTable && ! $.fn.DataTable.isDataTable('#hr_sell_dashboard_recent_table')) {
        $('#hr_sell_dashboard_recent_table').DataTable({
            pageLength: 25,
            order: [],
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
        });
    }
});
</script>
@endsection
