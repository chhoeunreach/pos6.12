@extends('loanmanagement::layouts.app')
@section('title', 'Create Loan')

@section('content_body')
<style>
    .loan-create-workspace .dataTables_scrollBody {
        overflow: visible !important;
    }
    .loan-create-action-dropdown {
        position: relative;
    }
    .loan-create-action-toggle {
        background: #fff;
        border: 1px solid #00a9ff;
        border-radius: 12px;
        color: #00a9ff;
        font-weight: 600;
        padding: 6px 14px;
    }
    .loan-create-action-toggle:hover,
    .loan-create-action-toggle:focus {
        background: #f2fbff;
        color: #008fd8;
    }
    .loan-create-action-menu {
        min-width: 230px;
        padding: 8px 0;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
        z-index: 3000;
    }
    .loan-create-action-menu > li > a,
    .loan-create-action-menu > li > button {
        display: block;
        width: 100%;
        padding: 9px 18px;
        border: 0;
        background: transparent;
        color: #777;
        font-size: 14px;
        line-height: 1.4;
        text-align: left;
    }
    .loan-create-action-menu > li > a:hover,
    .loan-create-action-menu > li > button:hover {
        background: #f5f5f5;
        color: #333;
        text-decoration: none;
    }
    .loan-create-action-menu i {
        width: 24px;
        margin-right: 8px;
        color: #777;
        text-align: center;
    }
    .loan-create-action-menu .divider {
        margin: 7px 0;
    }
    .loan-create-action-menu .disabled > a {
        color: #999;
        cursor: not-allowed;
    }
    .loan-create-action-menu .text-danger,
    .loan-create-action-menu .text-danger i {
        color: #d9534f;
    }
</style>
<section class="content-header no-print">
    <h1>Create Loan</h1>
</section>

<section class="content loan-create-workspace no-print">
    @php
        $posAddSellUrl = Route::has('pos.create') ? route('pos.create') : url('/pos/create');
    @endphp

    @if(session('duplicate_installment_warning'))
        <div class="alert alert-warning">
            <strong>{{ session('duplicate_installment_warning') }}</strong>
            @if(session('duplicate_loan_url'))
                <a href="{{ session('duplicate_loan_url') }}" class="btn btn-xs btn-primary m-l-10">View Loan</a>
            @endif
            <button type="button" class="btn btn-xs btn-default m-l-5" data-dismiss="alert">Cancel</button>
        </div>
    @endif

    @component('components.filters', ['title' => __('report.filters')])
        <form id="sellSearchForm" class="row">
            <input type="hidden" name="start_date" id="sell_filter_start_date">
            <input type="hidden" name="end_date" id="sell_filter_end_date">
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Business Location</label>
                    <select name="location_id" id="sell_list_filter_location_id" class="form-control select2" style="width:100%">
                        <option value="">All</option>
                        @foreach($locations as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Date Range</label>
                    <input name="date_range" id="sell_list_filter_date_range" class="form-control" placeholder="Select a date range" readonly>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status" id="sell_list_filter_payment_status" class="form-control select2" style="width:100%">
                        <option value="">All</option>
                        @foreach($paymentStatuses as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Sale Status</label>
                    <select name="sale_status" id="sell_list_filter_sale_status" class="form-control select2" style="width:100%">
                        <option value="">Final</option>
                        <option value="draft">Draft</option>
                        <option value="final">Final</option>
                        <option value="quotation">Quotation</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-3"><div class="form-group"><label>Invoice Number</label><input name="invoice_no" class="form-control"></div></div>
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Customer Name</label>
                    <select name="customer_name" class="form-control select2" style="width:100%">
                        <option value="">All</option>
                        @foreach($customerNames as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Customer Phone</label>
                    <select name="customer_phone" class="form-control select2" style="width:100%">
                        <option value="">All</option>
                        @foreach($customerPhones as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="form-group">
                    <label>Customer Group Name</label>
                    <select name="customer_group_name" id="defaultCustomerGroupName" class="form-control select2" style="width:100%">
                        @foreach($customerGroups as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'រំលស់')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-3"><div class="form-group"><label>Product Name / SKU</label><input name="product_name_sku" class="form-control"></div></div>
            <div class="col-sm-6 col-md-3"><div class="form-group"><label>IMEI / Lot Number</label><input name="imei_or_lot" class="form-control"></div></div>
        </form>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => 'All sales'])
        @slot('tool')
            <div class="box-tools">
                <button type="button" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right" id="btnOpenAddSellModal">
                    <i class="fa fa-plus"></i> Add Sell
                </button>
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="sellSearchTable" width="100%">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Customer name</th>
                        <th>Contact Number</th>
                        <th>Location</th>
                        <th>Payment Status</th>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Lots</th>
                        <th>Total amount</th>
                        <th>Total paid</th>
                        <th>Sell Due</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th colspan="10" class="text-right">Total:</th>
                        <th id="footer_total_amount">0.00</th>
                        <th id="footer_total_paid">0.00</th>
                        <th id="footer_sell_due">0.00</th>
                    </tr>
                </tfoot>
                <tbody></tbody>
            </table>
        </div>
    @endcomponent

    <div id="duplicateLoanWarning" class="alert alert-warning" style="display:none;">
        <strong>This sale already has installment loan.</strong>
        <a href="#" class="btn btn-xs btn-primary m-l-10" id="duplicateLoanViewLink">View Loan</a>
        <button type="button" class="btn btn-xs btn-default m-l-5" id="duplicateLoanCancel">Cancel</button>
    </div>

    @include('loanmanagement::loans.create_from_sell.partials.add_sell_modal')

    <div class="modal fade" id="addInstallmentModal" tabindex="-1" role="dialog" aria-labelledby="addInstallmentModalLabel">
        <div class="modal-dialog" role="document" style="width: 96%; max-width: 1400px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="addInstallmentModalLabel">Add to Installment</h4>
                </div>
                <div class="modal-body" id="addInstallmentModalBody">
                    <div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('loan_js')
<script>
(function($){
    var urls = {
        searchSales: "{{ route('loan-management.loans.search-sales') }}",
        cloneBase: "{{ url('/loan-management/loans/sales') }}",
        previewSchedule: "{{ route('loan-management.loans.preview-schedule') }}",
        loanViewBase: "{{ url('/loan-management/loans') }}",
        sellViewBase: "{{ url('/sells') }}",
        sellEditBase: "{{ url('/sells') }}",
        sellDeleteBase: "{{ url('/pos') }}",
        sellPrintBase: "{{ url('/sells') }}",
        posCreate: "{{ $posAddSellUrl }}"
    };
    var searchRequest = null;
    var filterTimer = null;
    var salesTable = null;

    function money(value) {
        var number = parseFloat(value || 0);
        return Number.isFinite(number) ? number.toFixed(2) : '0.00';
    }

    function numberValue(value) {
        if (typeof value === 'number') {
            return value;
        }

        var number = parseFloat(String(value || '').replace(/<[^>]*>/g, '').replace(/,/g, ''));
        return Number.isFinite(number) ? number : 0;
    }

    function esc(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function renderJoinedValue(value) {
        var parts = String(value || '')
            .split('|')
            .map(function(item) { return item.trim(); })
            .filter(function(item) { return item !== ''; });

        if (!parts.length) {
            return '';
        }

        return parts.map(function(item) {
            return '<div>'+esc(item)+'</div>';
        }).join('');
    }

    function buildActionDropdown(row) {
        var rowId = esc(row.id);
        var viewUrl = row.is_converted && row.loan_id
            ? urls.loanViewBase + '/' + encodeURIComponent(row.loan_id) + '/view'
            : urls.sellViewBase + '/' + encodeURIComponent(row.id);
        var viewAction = row.is_converted && row.loan_id
            ? '<li><a href="'+esc(viewUrl)+'"><i class="fa fa-eye"></i> View Loan</a></li>'
            : '<li><a href="#" class="btn-modal" data-container=".view_modal" data-href="'+esc(viewUrl)+'"><i class="fa fa-eye"></i> View Sale</a></li>';
        var addAction = row.is_converted
            ? '<li class="disabled"><a href="#" tabindex="-1"><i class="fa fa-check"></i> Already Added</a></li>'
            : '<li><a href="#" class="btn-select-sale" data-id="'+rowId+'"><i class="fa fa-credit-card"></i> Add to Installment</a></li>';
        var editAction = '<li><a target="_blank" href="'+urls.sellEditBase+'/'+encodeURIComponent(row.id)+'/edit"><i class="fa fa-edit"></i> Edit Sale</a></li>';
        var printAction = '<li><a href="#" class="print-invoice" data-href="'+urls.sellPrintBase+'/'+encodeURIComponent(row.id)+'/print"><i class="fa fa-print"></i> Print Invoice</a></li>';
        var deleteAction = '<li><a href="#" class="btn-delete-create-loan-sale text-danger" data-href="'+urls.sellDeleteBase+'/'+encodeURIComponent(row.id)+'"><i class="fa fa-trash"></i> Delete Sale</a></li>';

        return '<div class="btn-group loan-create-action-dropdown">' +
            '<button type="button" class="btn btn-sm dropdown-toggle loan-create-action-toggle" data-toggle="dropdown" aria-expanded="false">' +
                'Actions <span class="caret m-l-5"></span>' +
            '</button>' +
            '<ul class="dropdown-menu loan-create-action-menu">' +
                addAction +
                '<li class="divider"></li>' +
                viewAction +
                editAction +
                printAction +
                deleteAction +
            '</ul>' +
        '</div>';
    }

    function loadSells(){
        initSalesTable();
        if (searchRequest) {
            searchRequest.abort();
        }
        $('#sellSearchTable').closest('.dataTables_wrapper').find('.dataTables_processing').show();
        searchRequest = $.get(urls.searchSales, $('#sellSearchForm').serialize(), function(res){
            var rows = res.data || [];
            salesTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            if (xhr.statusText === 'abort') {
                return;
            }
            salesTable.clear().draw();
            alert(xhr.responseJSON?.message || 'Failed to search sales');
        }).always(function(){
            searchRequest = null;
            $('#sellSearchTable').closest('.dataTables_wrapper').find('.dataTables_processing').hide();
        });
    }

    function initSalesTable() {
        if (salesTable || !$.fn.DataTable) {
            return;
        }

        var tableButtons = [];
        if ($.fn.dataTable.Buttons) {
            tableButtons = [
                {
                    extend: 'csv',
                    text: '<i class="fa fa-file-csv" aria-hidden="true"></i> Export CSV',
                    className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                    exportOptions: {columns: ':visible'}
                },
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel" aria-hidden="true"></i> Export Excel',
                    className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                    exportOptions: {columns: ':visible'}
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print" aria-hidden="true"></i> Print',
                    className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                    exportOptions: {columns: ':visible', stripHtml: true}
                },
                {
                    extend: 'colvis',
                    text: '<i class="fa fa-columns" aria-hidden="true"></i> Column visibility',
                    className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf" aria-hidden="true"></i> Export PDF',
                    className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2',
                    exportOptions: {columns: ':visible'}
                }
            ];
        }

        salesTable = $('#sellSearchTable').DataTable({
            data: [],
            processing: true,
            serverSide: false,
            dom: '<"row margin-bottom-20 text-center"<"col-sm-1"l><"col-sm-8"B><"col-sm-3"f> r>tip',
            buttons: tableButtons,
            scrollX: true,
            scrollY: '55vh',
            scrollCollapse: true,
            aaSorting: [[1, 'desc']],
            footerCallback: function() {
                var api = this.api();
                var sumColumn = function(index) {
                    return api.column(index, {search: 'applied'}).data().reduce(function(total, value) {
                        return total + numberValue(value);
                    }, 0);
                };

                $(api.column(10).footer()).html(money(sumColumn(10)));
                $(api.column(11).footer()).html(money(sumColumn(11)));
                $(api.column(12).footer()).html(money(sumColumn(12)));
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return buildActionDropdown(row);
                    }
                },
                {data: 'transaction_date', defaultContent: ''},
                {data: 'invoice_no', defaultContent: ''},
                {data: 'customer_name', defaultContent: ''},
                {data: 'customer_phone', defaultContent: ''},
                {data: 'location_name', defaultContent: ''},
                {
                    data: 'payment_status',
                    defaultContent: '',
                    render: function(data) {
                        return data ? '<span class="label label-info">'+esc(data)+'</span>' : '';
                    }
                },
                {
                    data: 'skus',
                    defaultContent: '',
                    render: function(data) { return renderJoinedValue(data); }
                },
                {
                    data: 'product_names',
                    defaultContent: '',
                    render: function(data) { return renderJoinedValue(data); }
                },
                {
                    data: 'lots',
                    defaultContent: '',
                    render: function(data) { return renderJoinedValue(data); }
                },
                {
                    data: 'final_total',
                    render: function(data) { return money(data); }
                },
                {
                    data: 'paid_amount',
                    render: function(data) { return money(data); }
                },
                {
                    data: 'due_amount',
                    render: function(data) { return money(data); }
                }
            ],
            createdRow: function(row) {
                $(row).find('td:eq(0)').addClass('no-print');
                $(row).find('td:eq(0)').css({'white-space': 'nowrap', 'min-width': '130px'});
            }
        });
    }

    function scheduleFilterReload(delay) {
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(loadSells, delay || 250);
    }

    function bindLoanFormActions(){
        var form = $('#createLoanFromSellForm');
        function parseNum(v) {
            var n = parseFloat(String(v || '').replace(/,/g, '').trim());
            return Number.isFinite(n) ? n : 0;
        }
        function updatePaymentSummary(){
            var totalAmount = parseNum(form.find('#loan_total_amount_value').val() || form.find('#loan_total_amount_display').val());
            var paid = parseNum(form.find('#payment_amount_input').val());
            var due = Math.max(0, totalAmount - paid);
            form.find('#down_payment_hidden').val(paid.toFixed(2));
            form.find('#loan_total_paid_display').val(paid.toFixed(2));
            form.find('#loan_total_due_display').val(due.toFixed(2));
        }

        form.find('#principal_amount_input, #payment_amount_input, input[name="interest_rate"], input[name="duration_months"]').off('input change').on('input change', updatePaymentSummary);
        updatePaymentSummary();

        $('#btnPreviewSchedule').off('click').on('click', function(){
            $.post(urls.previewSchedule, form.serialize(), function(res){
                var rows = res.data || [];
                var tb = form.find('#schedulePreviewTable tbody').first();
                var table = tb.closest('table');
                var totalPrincipal = 0, totalInterest = 0, totalAmount = 0, totalBalance = 0;
                tb.empty();
                rows.forEach(function(r){
                    totalPrincipal += Number(r.principal || 0);
                    totalInterest += Number(r.interest || 0);
                    totalAmount += Number(r.total || 0);
                    totalBalance += Number(r.balance || 0);
                    tb.append('<tr><td>'+r.schedule_no+'</td><td>'+r.due_date+'</td><td>'+money(r.principal)+'</td><td>'+money(r.interest)+'</td><td>'+money(r.total)+'</td><td>'+money(r.balance)+'</td></tr>');
                });
                table.find('tfoot tr th').eq(1).text(totalPrincipal.toFixed(2));
                table.find('tfoot tr th').eq(2).text(totalInterest.toFixed(2));
                table.find('tfoot tr th').eq(3).text(totalAmount.toFixed(2));
                table.find('tfoot tr th').eq(4).text(totalBalance.toFixed(2));
            }).fail(function(xhr){
                alert(xhr.responseJSON?.message || 'Failed to preview schedule');
            });
        });

        $('#btnSaveDraft, #btnCreateLoan, #btnCreateApproveLoan').off('click').on('click', function(){
            form.find('input[name="action_type"]').val($(this).data('action'));
            form.trigger('submit');
        });

        form.off('submit').on('submit', function(e){
            e.preventDefault();
            var buttons = $('#btnSaveDraft, #btnCreateLoan, #btnCreateApproveLoan');
            buttons.prop('disabled', true);
            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                success: function(res){
                    alert(res.message || 'Installment loan created successfully');
                    if(res?.data?.loan_id){
                        window.location = urls.loanViewBase + '/' + res.data.loan_id + '/view';
                    }
                },
                error: function(xhr){
                    if(xhr.status === 422 && xhr.responseJSON?.errors){
                        var errors = xhr.responseJSON.errors;
                        var firstKey = Object.keys(errors)[0];
                        alert(errors[firstKey][0] || xhr.responseJSON?.message || 'Validation failed');
                    } else {
                        if(xhr.responseJSON?.data?.loan_url){
                            $('#duplicateLoanViewLink').attr('href', xhr.responseJSON.data.loan_url).show();
                            $('#duplicateLoanWarning').show();
                        }
                        alert(xhr.responseJSON?.message || 'Failed to create loan');
                    }
                },
                complete: function(){ buttons.prop('disabled', false); }
            });
        });
    }

    function selectSale(id) {
        $('#duplicateLoanWarning').hide();
        $('#addInstallmentModalBody').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading selected sale...</div>');
        $('#addInstallmentModal').modal('show');
        $.get(urls.cloneBase + '/' + id + '/clone-data', function(res){
            if(!res.success){
                if(res.data && res.data.loan_url){
                    $('#duplicateLoanViewLink').attr('href', res.data.loan_url).show();
                    $('#duplicateLoanWarning').show();
                }
                alert(res.message || 'Unable to select sale');
                $('#addInstallmentModal').modal('hide');
                return;
            }
            $('#addInstallmentModalBody').html(res.data.form_html);
            $('#addInstallmentModalBody').find('input[name="customer_group_name"]').val($('#defaultCustomerGroupName').val() || 'រំលស់');
            bindLoanFormActions();
        }).fail(function(xhr){
            var data = xhr.responseJSON?.data || {};
            if(data.loan_url){
                $('#duplicateLoanViewLink').attr('href', data.loan_url).show();
                $('#duplicateLoanWarning').show();
            }
            $('#addInstallmentModalBody').html('<div class="alert alert-danger">'+esc(xhr.responseJSON?.message || 'Failed to load sale data')+'</div>');
        });
    }

    function openAddSellModal(){
        var frame = $('#ultimatePosSellFrame');
        if (frame.attr('src') !== urls.posCreate) {
            frame.attr('src', urls.posCreate);
        }
        $('#addSellModal').modal('show');
    }

    if ($.fn.select2) {
        $('#sellSearchForm .select2').select2();
    }

    if ($.fn.daterangepicker && typeof dateRangeSettings !== 'undefined' && typeof moment !== 'undefined') {
        var startLast30 = moment().subtract(29, 'days');
        var endLast = moment();
        $('#sell_filter_start_date').val(startLast30.format('YYYY-MM-DD'));
        $('#sell_filter_end_date').val(endLast.format('YYYY-MM-DD'));
        $('#sell_list_filter_date_range').val(startLast30.format(moment_date_format) + ' ~ ' + endLast.format(moment_date_format));
        $('#sell_list_filter_date_range').daterangepicker(
            $.extend(true, {}, dateRangeSettings, { startDate: startLast30, endDate: endLast }),
            function(start, end) {
            $('#sell_filter_start_date').val(start.format('YYYY-MM-DD'));
            $('#sell_filter_end_date').val(end.format('YYYY-MM-DD'));
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            loadSells();
        });
        $('#sell_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#sell_list_filter_date_range, #sell_filter_start_date, #sell_filter_end_date').val('');
            loadSells();
        });
    }

    $('#sellSearchForm select').on('change', function(){ scheduleFilterReload(100); });
    $('#sellSearchForm input:not(#sell_list_filter_date_range):not([type="hidden"])').on('input', function(){ scheduleFilterReload(450); });
    $('#defaultCustomerGroupName').on('change', function(){
        $('#addInstallmentModalBody').find('input[name="customer_group_name"]').val($(this).val() || 'រំលស់');
    });
    $('#sellSearchForm input').on('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            loadSells();
        }
    });
    $(document).on('click', '.btn-select-sale', function(){ selectSale($(this).data('id')); });
    $(document).on('click', '.btn-duplicate-sale', function(){ alert('This sale already has installment loan.'); });
    $(document).on('click', '.btn-delete-create-loan-sale', function(e){
        e.preventDefault();
        var deleteUrl = $(this).data('href');
        var runDelete = function(){
            $.ajax({
                method: 'DELETE',
                url: deleteUrl,
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        if (window.toastr) {
                            toastr.success(result.msg || 'Sale deleted successfully');
                        }
                        loadSells();
                    } else if (window.toastr) {
                        toastr.error(result.msg || 'Unable to delete sale');
                    } else {
                        alert(result.msg || 'Unable to delete sale');
                    }
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.msg || xhr.responseJSON?.message || 'Unable to delete sale');
                }
            });
        };

        if (window.swal) {
            swal({
                title: (window.LANG && LANG.sure) ? LANG.sure : 'Are you sure?',
                icon: 'warning',
                buttons: true,
                dangerMode: true
            }).then(function(willDelete){
                if (willDelete) {
                    runDelete();
                }
            });
        } else if (confirm('Are you sure?')) {
            runDelete();
        }
    });
    $('#duplicateLoanCancel').on('click', function(){ $('#duplicateLoanWarning').hide(); });
    $('#btnOpenAddSellModal').on('click', openAddSellModal);
    $('#btnRefreshSalesAfterPosSell').on('click', function(){
        $('#addSellModal').modal('hide');
        loadSells();
    });
    loadSells();
})(jQuery);
</script>
@endsection
