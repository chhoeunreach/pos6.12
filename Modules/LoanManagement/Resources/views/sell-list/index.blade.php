@extends('loanmanagement::layouts.app')
@section('title', 'Loan Sell List')
@section('content_body')
<section class="content-header"><h1>Installment / Loan - Sell List</h1></section>
<section class="content">
@component('components.filters', ['title' => __('report.filters')])
{!! Form::open(['url' => route('loan-management.sell-list'), 'method' => 'get', 'id' => 'loan_sell_filter_form']) !!}
<div class="row">
    @include('sell.partials.sell_list_filters', ['only' => ['sell_list_filter_location_id', 'sell_list_filter_customer_id', 'sell_list_filter_payment_status', 'sell_list_filter_date_range', 'created_by', 'only_subscriptions']])
    <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
    <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
    @if(!empty($shipping_statuses))
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('shipping_status', __('lang_v1.shipping_status') . ':') !!}
            {!! Form::select('shipping_status', $shipping_statuses, request('shipping_status'), ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
        </div>
    </div>
    @endif
    @if(!empty($payment_types))
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('payment_method', __('lang_v1.payment_method') . ':') !!}
            {!! Form::select('payment_method', $payment_types, request('payment_method'), ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
        </div>
    </div>
    @endif
</div>
{!! Form::close() !!}
@endcomponent

<div class="box box-primary">
<div class="box-body table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Invoice</th><th>Customer</th><th>Phone</th><th>Location</th><th>Total</th><th>Paid</th><th>Due</th><th>Sale Date</th><th>Created By</th><th>Payment Status</th><th>Installment Status</th><th>Action</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
<td>{{ $row->invoice_no }}</td><td>{{ $row->customer_name }}</td><td>{{ $row->customer_phone }}</td><td>{{ $row->location_name }}</td>
<td>{{ number_format($row->final_total,2) }}</td><td>{{ number_format($row->paid_amount,2) }}</td><td>{{ number_format($row->due_amount,2) }}</td>
<td>{{ $row->sale_date }}</td><td>{{ $row->created_by_name }}</td><td><span class="label label-info">{{ $row->payment_status }}</span></td>
<td>{!! $row->installment_status === 'Already Added' ? '<span class="label label-success">Already Added</span>' : '<span class="label label-warning">Pending</span>' !!}</td>
<td>
<a href="{{ route('loan-management.sell-list.view', $row->id) }}" class="btn btn-xs btn-default">View</a>
@if($row->installment_status === 'Already Added')
<button class="btn btn-xs btn-success" disabled>Already Added</button>
@else
<a href="{{ route('loan-management.sell-list.add', $row->id) }}" class="btn btn-xs btn-primary">Add to Installment</a>
@endif
</td>
</tr>
@empty
<tr><td colspan="12" class="text-center">No eligible sell transactions found.</td></tr>
@endforelse
</tbody>
</table>
</div></div></section>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function(){
    $('.select2').select2();
    var filterForm = $('#loan_sell_filter_form');
    var isReloading = false;
    var startLast30 = moment().subtract(29, 'days');
    var endLast = moment();
    var defaultStart = '{{ request('start_date') }}' ? moment('{{ request('start_date') }}') : startLast30;
    var defaultEnd = '{{ request('end_date') }}' ? moment('{{ request('end_date') }}') : endLast;
    function reloadWithFilters() {
        if (isReloading) return;
        var drp = $('#sell_list_filter_date_range').data('daterangepicker');
        if ($('#sell_list_filter_date_range').val() && drp) {
            $('#start_date').val(drp.startDate.format('YYYY-MM-DD'));
            $('#end_date').val(drp.endDate.format('YYYY-MM-DD'));
        } else {
            $('#start_date').val('');
            $('#end_date').val('');
        }
        isReloading = true;
        var qs = filterForm.serialize();
        window.location = filterForm.attr('action') + (qs ? ('?' + qs) : '');
    }

    $('#sell_list_filter_date_range').daterangepicker($.extend(true, {}, dateRangeSettings, {
        startDate: defaultStart,
        endDate: defaultEnd
    }));

    $('#sell_list_filter_date_range').data('daterangepicker').setStartDate(defaultStart);
    $('#sell_list_filter_date_range').data('daterangepicker').setEndDate(defaultEnd);
    $('#sell_list_filter_date_range').val(defaultStart.format(moment_date_format) + ' ~ ' + defaultEnd.format(moment_date_format));
    $('#start_date').val(defaultStart.format('YYYY-MM-DD'));
    $('#end_date').val(defaultEnd.format('YYYY-MM-DD'));

    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val(picker.startDate.format(moment_date_format) + ' ~ ' + picker.endDate.format(moment_date_format));
        $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
        $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
        reloadWithFilters();
    });

    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function() {
        $(this).val('');
        $('#start_date').val('');
        $('#end_date').val('');
        reloadWithFilters();
    });

    $(document).on('change select2:select select2:clear', '#loan_sell_filter_form select, #loan_sell_filter_form input[type=\"checkbox\"]', function() {
        reloadWithFilters();
    });

    $('#only_subscriptions').on('ifChanged', function() {
        reloadWithFilters();
    });
});
</script>
@endsection
