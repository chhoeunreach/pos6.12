@extends('loanmanagement::layouts.app')
@section('title', 'Installment List')

@section('content_body')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Installment List</h1>
    <div class="pull-right lm-header-actions-mobile">
        @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
            <button type="button" class="btn btn-primary btn-sm lm-standalone-loan-trigger"
                    data-url="{{ route('loan-management.loans.create-standalone-modal') }}"
                    data-target="#standaloneLoanModal">
                <i class="fa fa-plus"></i> <span class="hidden-xs">Create Loan</span> <span class="visible-xs-inline">New</span>
            </button>
        @endif
    </div>
</section>

<section class="content no-print">
    <div class="lm-mobile-section-tabs">
        <a href="{{ route('loan-management.loans') }}" class="active">
            <i class="fa fa-credit-card"></i> Loans
        </a>
        <a href="{{ route('loan-management.monthly-payments.index') }}">
            <i class="fa fa-money"></i> Collection
        </a>
    </div>

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
        <div class="lm-mobile-loan-list" id="loan_mobile_list">
            <div class="text-center text-muted" style="padding: 16px;">Loading loans...</div>
        </div>
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

@section('loan_js')
<script>
$(document).ready(function(){
    $('.select2').select2();
    var loanBaseUrl = "{{ url('loan-management/loans') }}";

    function plainText(value) {
        return $('<div>').html(value || '').text().trim() || '-';
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function mobileLoanCard(row) {
        var id = row.id || '';
        var loanNumber = plainText(row.loan_number);
        var customer = plainText(row.customer_name_snapshot);
        var phone = plainText(row.customer_phone_snapshot);
        var date = plainText(row.loan_date);
        var statusText = plainText(row.status).toLowerCase();
        var statusClass = statusText.replace(/[^a-z0-9_-]+/g, '-');
        var location = plainText(row.location_name_snapshot);
        var collector = plainText(row.collector_name_snapshot);
        var principal = plainText(row.principal_amount);
        var paid = plainText(row.paid_amount);
        var balance = plainText(row.balance_amount);
        var viewUrl = loanBaseUrl + '/' + id + '/view';
        var quickPayUrl = loanBaseUrl + '/' + id + '/payment/quick-pay';

        return ''
            + '<article class="lm-mobile-loan-card">'
            + '  <div class="lm-mobile-loan-card-header">'
            + '    <div><div class="lm-mobile-loan-card-title">' + escapeHtml(loanNumber) + '</div><div class="lm-mobile-loan-card-date">' + escapeHtml(date) + '</div></div>'
            + '    <span class="lm-mobile-loan-card-status status-' + escapeHtml(statusClass) + '">' + escapeHtml(statusText || 'status') + '</span>'
            + '  </div>'
            + '  <div class="lm-mobile-loan-card-body">'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">Customer</span><span class="value">' + escapeHtml(customer) + '</span></div>'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">Phone</span><span class="value">' + escapeHtml(phone) + '</span></div>'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">Location</span><span class="value">' + escapeHtml(location) + '</span></div>'
            + '    <div class="lm-mobile-loan-card-row"><span class="label">Collector</span><span class="value">' + escapeHtml(collector) + '</span></div>'
            + '  </div>'
            + '  <div class="lm-mobile-loan-card-balance">'
            + '    <div class="lm-mobile-loan-card-balance-item"><small>Principal</small><strong>' + escapeHtml(principal) + '</strong></div>'
            + '    <div class="lm-mobile-loan-card-balance-item paid"><small>Paid</small><strong>' + escapeHtml(paid) + '</strong></div>'
            + '    <div class="lm-mobile-loan-card-balance-item due"><small>Balance</small><strong>' + escapeHtml(balance) + '</strong></div>'
            + '  </div>'
            + '  <div class="lm-mobile-loan-card-actions">'
            + '    <a href="' + viewUrl + '" class="btn btn-default btn-sm"><i class="fa fa-eye"></i> View</a>'
            + '    <a href="#" class="btn btn-success btn-sm btn-modal" data-href="' + quickPayUrl + '" data-container=".view_modal"><i class="fa fa-money"></i> Pay</a>'
            + '  </div>'
            + '</article>';
    }

    function renderMobileLoanList(rows) {
        var $list = $('#loan_mobile_list');
        if (!$list.length) return;
        if (!rows || !rows.length) {
            $list.html('<div class="lm-mobile-loan-empty">No loans found.</div>');
            return;
        }

        $list.html(rows.map(mobileLoanCard).join(''));
    }

    function setRange(s, e){
        $('#start_date').val(s.format('YYYY-MM-DD'));
        $('#end_date').val(e.format('YYYY-MM-DD'));
        $('#sell_list_filter_date_range').val(s.format(moment_date_format) + ' ~ ' + e.format(moment_date_format));
    }

    $('#sell_list_filter_date_range').daterangepicker($.extend(true, {}, dateRangeSettings, {autoUpdateInput: false}), function(s, e){
        setRange(s, e);
        loanTable.ajax.reload();
    });

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
            {data:'loan_number', name:'l.loan_number'},
            {data:'loan_date', name:'l.loan_date'},
            {data:'source_invoice_no', name:'l.source_invoice_no'},
            {data:'customer_name_snapshot', name:'l.customer_name_snapshot'},
            {data:'customer_phone_snapshot', name:'l.customer_phone_snapshot'},
            {data:'location_name_snapshot', name:'location_name_snapshot', searchable:false},
            {data:'collector_name_snapshot', name:'l.collector_name_snapshot'},
            {data:'principal_amount', name:'l.principal_amount'},
            {data:'paid_amount', name:'l.paid_amount'},
            {data:'balance_amount', name:'l.balance_amount'},
            {data:'status', name:'l.status'},
            {data:'currency', name:'l.currency'},
            {data:'action', name:'action', orderable:false, searchable:false}
        ],
        fnDrawCallback: function(){
            __currency_convert_recursively($('#loan_list_table'));
            renderMobileLoanList(this.api().rows({page: 'current'}).data().toArray());
        }
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
