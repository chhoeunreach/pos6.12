@extends('loanmanagement::layouts.app')
@section('title', 'Installment List')

@section('content_body')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Installment List</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']) !!}
                <input type="hidden" id="start_date">
                <input type="hidden" id="end_date">
            </div>
        </div>
        <div class="col-md-3"><div class="form-group"><label>Status:</label><select id="status" class="form-control select2" style="width:100%"><option value="">All</option><option>draft</option><option>pending</option><option>approved</option><option>active</option><option>completed</option><option>rejected</option><option>cancelled</option><option>defaulted</option></select></div></div>
        <div class="col-md-3"><div class="form-group"><label>Location:</label>{!! Form::select('location_name', $locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'location_name']) !!}</div></div>
        <div class="col-md-3"><div class="form-group"><label>Collector:</label>{!! Form::select('collector_name', $collectors, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'collector_name']) !!}</div></div>
        <div class="col-md-3"><div class="form-group"><label>Customer:</label><input id="customer" class="form-control" placeholder="Customer name"></div></div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => 'Installment List'])
        <table class="table table-bordered table-striped" id="loan_list_table" width="100%">
            <thead>
                <tr>
                    <th>Loan #</th><th>Date</th><th>Source Invoice</th><th>Customer</th><th>Phone</th><th>Location</th><th>Collector</th><th>Principal</th><th>Paid</th><th>Balance</th><th>Status</th><th>Currency</th><th>Action</th>
                </tr>
            </thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function(){
    $('.select2').select2();
    var start = moment().subtract(29, 'days');
    var end = moment();

    function setRange(s, e){
        $('#start_date').val(s.format('YYYY-MM-DD'));
        $('#end_date').val(e.format('YYYY-MM-DD'));
        $('#sell_list_filter_date_range').val(s.format(moment_date_format) + ' ~ ' + e.format(moment_date_format));
    }

    $('#sell_list_filter_date_range').daterangepicker($.extend(true, {}, dateRangeSettings, {startDate: start, endDate: end}), function(s, e){
        setRange(s, e);
        loanTable.ajax.reload();
    });

    setRange(start, end);

    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(){
        $(this).val('');
        $('#start_date').val('');
        $('#end_date').val('');
        loanTable.ajax.reload();
    });

    var loanTable = $('#loan_list_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('loan-management.loans.list-data') }}",
            data: function(d){
                d.start_date = $('#start_date').val();
                d.end_date = $('#end_date').val();
                d.status = $('#status').val();
                d.location_name = $('#location_name').val();
                d.collector_name = $('#collector_name').val();
                d.customer = $('#customer').val();
            }
        },
        columns: [
            {data:'loan_number', name:'loan_number'},
            {data:'loan_date', name:'loan_date'},
            {data:'source_invoice_no', name:'source_invoice_no'},
            {data:'customer_name_snapshot', name:'customer_name_snapshot'},
            {data:'customer_phone_snapshot', name:'customer_phone_snapshot'},
            {data:'location_name_snapshot', name:'location_name_snapshot'},
            {data:'collector_name_snapshot', name:'collector_name_snapshot'},
            {data:'principal_amount', name:'principal_amount'},
            {data:'paid_amount', name:'paid_amount'},
            {data:'balance_amount', name:'balance_amount'},
            {data:'status', name:'status'},
            {data:'currency', name:'currency'},
            {data:'action', name:'action', orderable:false, searchable:false}
        ],
        fnDrawCallback: function(){ __currency_convert_recursively($('#loan_list_table')); }
    });

    $(document).on('change keyup', '#status,#location_name,#collector_name,#customer', function(){
        loanTable.ajax.reload();
    });

    $(document).on('click', '.btn-delete-loan', function(){
        if(!confirm('Delete this loan?')) return;
        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: {_token: $('meta[name=\"csrf-token\"]').attr('content')},
            success: function(){ loanTable.ajax.reload(); },
            error: function(){ alert('Failed to delete loan.'); }
        });
    });

    $(document).on('click', '.btn-change-status', function(e){
        e.preventDefault();
        $.post($(this).data('url'), {
            _token: $('meta[name=\"csrf-token\"]').attr('content'),
            status: $(this).data('status')
        }, function(){ loanTable.ajax.reload(); }).fail(function(){ alert('Failed to update status.'); });
    });
});
</script>
@endsection
