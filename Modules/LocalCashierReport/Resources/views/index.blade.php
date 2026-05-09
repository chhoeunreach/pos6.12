@extends('layouts.app')
@section('title', 'Local Cashier Report')

@section('content')
<section class="content-header no-print">
    @can('direct_sell.access')
        <div class="box-tools pull-right">
            <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full"
               href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M12 5l0 14"/>
                    <path d="M5 12l14 0"/>
                </svg> @lang('messages.add')
            </a>
        </div>
    @endcan
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Local Cashier Report
        <span id="local_cashier_selected_range" class="tw-text-gray-600 tw-font-normal tw-text-base">
            {{ request('start_date') ? @format_date(request('start_date')) : @format_date(\Carbon\Carbon::now()->subDays(29)) }}
            ~
            {{ request('end_date') ? @format_date(request('end_date')) : @format_date(\Carbon\Carbon::now()) }}
        </span>
    </h1>
</section>

<section class="content" id="local_cashier_report_app" style="font-family: {{ $khmerFontFamily }};">
    @component('components.filters', ['title' => __('report.filters')])
        <form id="local_cashier_report_filter" class="row" method="get">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range</label>
                        <input type="text" id="local_cashier_report_date_range" class="form-control" placeholder="Select a date range" readonly>
                        <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                        <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Cashier/User</label>
                        <select name="user_ids[]" class="form-control select2" multiple style="width:100%">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @if(in_array($u->id, (array) request('user_ids', []))) selected @endif>{{ trim($u->first_name . ' ' . $u->last_name) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Business Location</label>
                        <select name="location_ids[]" class="form-control select2" multiple style="width:100%">
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}" @if(in_array($l->id, (array) request('location_ids', []))) selected @endif>{{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" class="form-control select2" style="width:100%">
                            <option value="">All</option>
                            @foreach($paymentStatuses as $status)
                                <option value="{{ $status }}" @if(request('payment_status') === $status) selected @endif>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <button type="button" id="local_cashier_report_reset" class="btn btn-default">Reset</button>
                    <a href="{{ route('local-cashier-report.export.excel') }}" id="btn_export_excel" class="btn btn-success">Export Excel</a>
                    <a href="{{ route('local-cashier-report.export.pdf') }}" id="btn_export_pdf" class="btn btn-danger" target="_blank">Export PDF</a>
                    <button type="button" id="btn_print" class="btn btn-info">Print</button>
                </div>
            </form>
    @endcomponent

    <div id="summary_wrapper" class="box box-solid">
        <div class="box-body">
            <div id="summary_cards" class="row"></div>
            <hr>
            <div class="row">
                <div class="col-md-4"><h4>Summary by User/Cashier</h4><div id="group_user"></div></div>
                <div class="col-md-4"><h4>Summary by Location</h4><div id="group_location"></div></div>
                <div class="col-md-4"><h4>Summary by Payment Method</h4><div id="group_payment"></div></div>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => 'All cashier sales'])
        @include('localcashierreport::partials.report_table', ['rows' => collect(), 'summary' => [], 'currencySymbol' => $currencySymbol, 'khmerFontFamily' => $khmerFontFamily, 'isPdf' => false, 'paymentLabels' => $paymentLabels])
    @endcomponent

    <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
</section>
@endsection

@section('javascript')
<script>
(function($){
    let currencySymbol = @json($currencySymbol);
    function formatMoney(v){ return currencySymbol + parseFloat(v || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function queryString(){ return $('#local_cashier_report_filter').serialize(); }
    const tableButtons = [
        { extend: 'csv', text: '<i class="fa fa-file-csv" aria-hidden="true"></i> ' + LANG.export_to_csv, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2', exportOptions: { columns: ':visible' } },
        { extend: 'excel', text: '<i class="fa fa-file-excel" aria-hidden="true"></i> ' + LANG.export_to_excel, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2', exportOptions: { columns: ':visible' } },
        { extend: 'print', text: '<i class="fa fa-print" aria-hidden="true"></i> ' + LANG.print, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2', exportOptions: { columns: ':visible' } },
        { extend: 'colvis', text: '<i class="fa fa-columns" aria-hidden="true"></i> ' + LANG.col_vis, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2' },
        { extend: 'pdf', text: '<i class="fa fa-file-pdf" aria-hidden="true"></i> ' + LANG.export_to_pdf, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2', exportOptions: { columns: ':visible' } }
    ];

    let table = $('#local_cashier_report_table').DataTable({
        processing: true,
        serverSide: true,
        deferLoading: 0,
        dom: '<"row margin-bottom-20 text-center"<"col-sm-1"l><"col-sm-8"B><"col-sm-3"f> r>tip',
        buttons: tableButtons,
        aLengthMenu: [[25, 50, 100, 200, 500, 1000, -1], [25, 50, 100, 200, 500, 1000, LANG.all]],
        iDisplayLength: __default_datatable_page_entries,
        pagingType: 'simple_numbers',
        ajax: {
            url: '{{ route('local-cashier-report.datatable') }}',
            data: function(d){
                $('#local_cashier_report_filter').serializeArray().forEach(function(i){ d[i.name]=i.value; });
            },
            dataSrc: function(json){
                renderSummary(json.summary || {});
                if (json.warning) {
                    toastr.warning(json.warning);
                }
                return json.data;
            }
        },
        columns: [
            {data:'action',name:'action',orderable:false,searchable:false},
            {data:'transaction_date',name:'transaction_date'},
            {data:'invoice_no',name:'invoice_no'},
            {data:'cashier_name',name:'cashier_name'},
            {data:'location_name',name:'location_name'},
            {data:'sku',name:'sku'},
            {data:'product_name',name:'product_name'},
            {data:'quantity',name:'quantity'},
            {data:'unit_price',name:'unit_price',render:function(v){return formatMoney(v);}},
            {data:'line_total',name:'line_total',render:function(v){return formatMoney(v);}},
            {data:'discount',name:'discount',render:function(v){return formatMoney(v);}},
            {data:'total_paid',name:'total_paid',render:function(v){return formatMoney(v);}},
            {data:'cash',name:'cash',render:function(v){return formatMoney(v);}},
            {data:'aba',name:'aba',render:function(v){return formatMoney(v);}},
            {data:'acleda',name:'acleda',render:function(v){return formatMoney(v);}},
            {data:'wing',name:'wing',render:function(v){return formatMoney(v);}},
            {data:'e_and_t',name:'e_and_t',render:function(v){return formatMoney(v);}},
            {data:'card',name:'card',render:function(v){return formatMoney(v);}},
            {data:'other',name:'other',render:function(v){return formatMoney(v);}},
            {data:'due',name:'due',render:function(v){return '<span class="text-danger">'+formatMoney(v)+'</span>';}}
        ]
    });

    function renderSummary(summary){
        const cards = summary.cards || {};
        const cardOrder = [
            ['Total Sale','total_sale'],['Total Paid','total_paid'],['Total Cash','total_cash'],['Total ABA','total_aba'],['Total ACLEDA','total_acleda'],['Total WING','total_wing'],['Total E&T','total_e_and_t'],['Total Card','total_card'],['Total Other','total_other'],['Total Due','total_due'],['Total Discount','total_discount'],['Total Qty Sold','total_qty']
        ];
        let html='';
        cardOrder.forEach(function(c){ html += '<div class="col-md-2"><div class="well well-sm"><strong>'+c[0]+'</strong><br>'+((c[1]==='total_qty')?parseFloat(cards[c[1]]||0).toLocaleString():formatMoney(cards[c[1]]||0))+'</div></div>';});
        $('#summary_cards').html(html);

        const makeList = (arr, mapMethod=false) => '<table class="table table-bordered table-condensed"><thead><tr><th>Name</th><th>Amount</th></tr></thead><tbody>' + (arr||[]).map(i => '<tr><td>' + (mapMethod ? humanMethod(i.label) : (i.label || 'N/A')) + '</td><td>' + formatMoney(i.amount || 0) + '</td></tr>').join('') + '</tbody></table>';
        $('#group_user').html(makeList(summary.group_by_user));
        $('#group_location').html(makeList(summary.group_by_location));
        $('#group_payment').html(makeList(summary.group_by_payment_method, true));
    }

    function humanMethod(method){
        let map = @json($paymentMap);
        let labels = @json($paymentLabels);
        let invert = {};
        Object.keys(map).forEach(function(k){ invert[map[k]] = k; });
        let k = invert[method] || method;
        return labels[k] || method;
    }

    $('#local_cashier_report_filter').on('submit', function(e){
        e.preventDefault();
        if (!$('input[name=\"start_date\"]').val() || !$('input[name=\"end_date\"]').val()) {
            toastr.warning('Please select Date Range first.');
            return;
        }
        table.ajax.reload();
        syncExportLinks();
    });
    $('#local_cashier_report_reset').on('click', function(){
        this.form?.reset();
        $('.select2').val(null).trigger('change');
        table.clear().draw();
        renderSummary({});
        syncExportLinks();
    });
    $('#btn_print').on('click', function(){ window.print(); });

    function syncExportLinks(){
        $('#btn_export_excel').attr('href', '{{ route('local-cashier-report.export.excel') }}' + '?' + queryString());
        $('#btn_export_pdf').attr('href', '{{ route('local-cashier-report.export.pdf') }}' + '?' + queryString());
    }
    syncExportLinks();
})(jQuery);
</script>
<script>
(function($){
    const $dateRange = $('#local_cashier_report_date_range');
    const $startDate = $('#local_cashier_report_filter input[name="start_date"]');
    const $endDate = $('#local_cashier_report_filter input[name="end_date"]');

    $('#local_cashier_report_filter .select2').select2();

    $dateRange.daterangepicker(dateRangeSettings, function(start, end) {
        $dateRange.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        $startDate.val(start.format('YYYY-MM-DD'));
        $endDate.val(end.format('YYYY-MM-DD'));
        $('#local_cashier_selected_range').text(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
    });

    if ($startDate.val() && $endDate.val()) {
        const drp = $dateRange.data('daterangepicker');
        const start = moment($startDate.val(), 'YYYY-MM-DD');
        const end = moment($endDate.val(), 'YYYY-MM-DD');
        drp.setStartDate(start);
        drp.setEndDate(end);
        $dateRange.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        $('#local_cashier_selected_range').text(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
    }

    $dateRange.on('cancel.daterangepicker', function() {
        $dateRange.val('');
        $startDate.val('');
        $endDate.val('');
        const defaultStart = moment().subtract(29, 'days').format(moment_date_format);
        const defaultEnd = moment().format(moment_date_format);
        $('#local_cashier_selected_range').text(defaultStart + ' ~ ' + defaultEnd);
    });
})(jQuery);
</script>
<style>
#local_cashier_report_app .box-tools { margin-bottom: 8px; }
@media print { .content-header, #local_cashier_report_filter, .dataTables_length, .dataTables_filter, .dataTables_paginate, .dataTables_info { display:none !important; } }
</style>
@endsection
