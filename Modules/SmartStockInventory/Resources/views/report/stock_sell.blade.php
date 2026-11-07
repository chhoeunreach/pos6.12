@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Sell Report')
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Stock Sell Report</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id[]', ['all' => __('lang_v1.all')] + $business_locations->toArray(), ['all'], ['class' => 'form-control select2', 'id' => 'sell_list_filter_location_id', 'style' => 'width:100%', 'multiple' => 'multiple']); !!}
            </div>
        </div>
        @include('sell.partials.sell_list_filters', ['only' => ['sell_list_filter_customer_id', 'sell_list_filter_payment_status', 'sell_list_filter_date_range']])
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="stock_sell_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>I-T</th>
                        <th>Cust.</th>
                        <th>Phone</th>
                        <th>SKU</th>
                        <th>Lots</th>
                        <th>Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Purchase Price</th>
                        <th class="text-right">Selling Price</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Profit/Loss</th>
                        <th class="text-right">Cash</th>
                        <th class="text-right">Wing</th>
                        <th class="text-right">ABA</th>
                        <th class="text-right">Acleda</th>
                        <th class="text-right">TRUE</th>
                        <th class="text-right">Card</th>
                        <th class="text-right">Other</th>
                        <th class="text-right">វ៉ៃដូ</th>
                        <th class="text-right">បង់ប្រចាំខែ</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Due</th>
                        <th>Location</th>
                        <th>Customer Group Name</th>
                        <th>Sell Note</th>
                        <th>User Name</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr class="bg-gray">
                        <th colspan="8" class="text-right">Total</th>
                        <th class="text-right"><span id="stock_sell_footer_quantity">0</span></th>
                        <th></th>
                        <th></th>
                        <th class="text-right"><span id="stock_sell_footer_total">0</span></th>
                        <th></th>
                        <th class="text-right"><span id="stock_sell_footer_cash">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_wing">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_aba">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_acleda">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_true_money">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_card">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_other">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_voido">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_monthly">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_paid">0</span></th>
                        <th class="text-right"><span id="stock_sell_footer_due">0</span></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent
</section>

<section id="receipt_section" class="print_section"></section>

<div class="modal fade no-print" id="ssi-purchase-reference-modal" tabindex="-1" role="dialog" aria-labelledby="ssi-purchase-reference-title" style="z-index:1070">
    <div class="modal-dialog" style="width:96%;max-width:1450px"><div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}">×</button>
            <h4 class="modal-title" id="ssi-purchase-reference-title">{{ __('purchase.edit_purchase') }}</h4>
        </div>
        <div class="modal-body" style="padding:0">
            <p id="ssi-purchase-reference-loading" role="status" style="padding:16px">{{ __('localcashierreport::report.loading') }}</p>
            <iframe id="ssi-purchase-reference-frame" title="{{ __('purchase.edit_purchase') }}" src="about:blank" style="display:block;width:100%;height:75vh;border:0"></iframe>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">{{ __('messages.close') }}</button></div>
    </div></div>
</div>
@stop
@section('module_js')
<style>.ssi-purchase-reference-edit{background:transparent;border:0;border-bottom:1px dashed #8ba5c2;color:#194f8c;font:inherit;cursor:pointer;padding:3px}.ssi-purchase-reference-edit:focus-visible{outline:2px solid #93c5fd}</style>
<script type="text/javascript">
    $(document).ready(function() {
        @php $initialStockFilters = (request()->boolean('finance_embed') || request()->hasAny(['start_date', 'end_date', 'location_id'])) ? ['locations' => request('location_id', []), 'start' => request('start_date'), 'end' => request('end_date')] : null; @endphp
        var initialFilters = @json($initialStockFilters);
        var initialStart = initialFilters && initialFilters.start ? moment(initialFilters.start, 'YYYY-MM-DD', true) : moment();
        var initialEnd = initialFilters && initialFilters.end ? moment(initialFilters.end, 'YYYY-MM-DD', true) : moment();
        if (!initialStart.isValid()) initialStart = moment();
        if (!initialEnd.isValid()) initialEnd = moment();
        $('#sell_list_filter_location_id').val(initialFilters && initialFilters.locations.length ? initialFilters.locations.map(String) : ['all']).trigger('change.select2');

        $('#sell_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: initialStart,
                endDate: initialEnd
            }),
            function (start, end) {
                $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                stock_sell_report_table.ajax.reload();
            }
        );
        $('#sell_list_filter_date_range').val(initialStart.format(moment_date_format) + ' ~ ' + initialEnd.format(moment_date_format));

        $('#sell_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#sell_list_filter_date_range').val('');
            stock_sell_report_table.ajax.reload();
        });

        function ssiFormatFooterMoney(value) {
            if (value === null) {
                return 'Unavailable';
            }

            return __currency_trans_from_en(parseFloat(value || 0), true);
        }

        function ssiFormatFooterQty(value) {
            return __currency_trans_from_en(parseFloat(value || 0), false);
        }

        $('#stock_sell_report_table').on('xhr.dt', function(e, settings, json) {
            var totals = json && json.footer_totals ? json.footer_totals : {};

            $('#stock_sell_footer_quantity').html(ssiFormatFooterQty(totals.quantity));
            $('#stock_sell_footer_total').html(ssiFormatFooterMoney(totals.total));
            $('#stock_sell_footer_cash').html(ssiFormatFooterMoney(totals.cash));
            $('#stock_sell_footer_wing').html(ssiFormatFooterMoney(totals.wing));
            $('#stock_sell_footer_aba').html(ssiFormatFooterMoney(totals.aba));
            $('#stock_sell_footer_acleda').html(ssiFormatFooterMoney(totals.acleda));
            $('#stock_sell_footer_true_money').html(ssiFormatFooterMoney(totals.true_money));
            $('#stock_sell_footer_card').html(ssiFormatFooterMoney(totals.card));
            $('#stock_sell_footer_other').html(ssiFormatFooterMoney(totals.other));
            $('#stock_sell_footer_voido').html(ssiFormatFooterMoney(totals.voido));
            $('#stock_sell_footer_monthly').html(ssiFormatFooterMoney(totals.monthly));
            $('#stock_sell_footer_paid').html(ssiFormatFooterMoney(totals.paid));
            $('#stock_sell_footer_due').html(ssiFormatFooterMoney(totals.due));
        });

        var stockSellButtons = $.map($.fn.dataTable.defaults.buttons || [], function(button) {
            var clonedButton = $.extend(true, {}, button);

            if (clonedButton.extend === 'copy') {
                clonedButton.title = '';
                clonedButton.messageTop = null;
                clonedButton.messageBottom = null;
                clonedButton.header = false;
                clonedButton.footer = false;
                clonedButton.action = function(e, dt, buttonNode, config, callback) {
                    config.title = '';
                    config.messageTop = null;
                    config.messageBottom = null;
                    config.header = false;
                    config.footer = false;

                    $.fn.dataTable.ext.buttons.copyHtml5.action.call(
                        this,
                        e,
                        dt,
                        buttonNode,
                        config,
                        callback
                    );
                };
            }

            return clonedButton;
        });

        stock_sell_report_table = $('#stock_sell_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            buttons: stockSellButtons,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ ssi_route('ssi.report.stock_sell') }}",
                data: function(d) {
                    if ($('#sell_list_filter_date_range').val()) {
                        d.start_date = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.location_id = $('#sell_list_filter_location_id').val();
                    d.customer_id = $('#sell_list_filter_customer_id').val();
                    d.payment_status = $('#sell_list_filter_payment_status').val();
                    d = __datatable_ajax_callback(d);
                }
            },
            lengthMenu: [[10, 25, 50, 500, 1000, 2000, -1], [10, 25, 50, 500, 1000, 2000, "All"]],
            columns: [
                { data: 'transaction_date', name: 't.transaction_date', type: 'text' },
                { data: 'invoice_no', name: 't.invoice_no', type: 'text' },
                { data: 'i_t', name: 't.additional_notes', orderable: false },
                { data: 'customer', name: 'c.name' },
                { data: 'phone', name: 'c.mobile' },
                { data: 'sku', name: 'v.sub_sku' },
                { data: 'lots', name: 'lots', searchable: false, orderable: false },
                { data: 'product', name: 'p.name' },
                { data: 'quantity', name: 'transaction_sell_lines.quantity', className: 'text-right' },
                { data: 'purchase_price', name: 'purchase_price', searchable: false, className: 'text-right' },
                { data: 'price', name: 'transaction_sell_lines.unit_price_inc_tax', className: 'text-right' },
                { data: 'total', name: 'total', searchable: false, className: 'text-right' },
                { data: 'purchase_profit_loss', name: 'purchase_profit_loss', searchable: false, className: 'text-right' },
                { data: 'cash', name: 'cash', searchable: false, className: 'text-right' },
                { data: 'wing', name: 'wing', searchable: false, className: 'text-right' },
                { data: 'aba', name: 'aba', searchable: false, className: 'text-right' },
                { data: 'acleda', name: 'acleda', searchable: false, className: 'text-right' },
                { data: 'true_money', name: 'true_money', searchable: false, className: 'text-right' },
                { data: 'card', name: 'card', searchable: false, className: 'text-right' },
                { data: 'other', name: 'other', searchable: false, className: 'text-right' },
                { data: 'voido', name: 'voido', searchable: false, className: 'text-right' },
                { data: 'monthly', name: 'monthly', searchable: false, className: 'text-right' },
                { data: 'paid', name: 'paid', searchable: false, className: 'text-right' },
                { data: 'due', name: 'due', searchable: false, className: 'text-right' },
                { data: 'location', name: 'bl.name' },
                { data: 'customer_group', name: 'tcg.name' },
                { data: 'sell_note', name: 't.additional_notes' },
                { data: 'user_name', name: 'u.surname' },
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#stock_sell_report_table'));
            }
        });

        var purchaseModal = $('#ssi-purchase-reference-modal'), purchaseFrame = $('#ssi-purchase-reference-frame');
        $(document).on('click', '.ssi-purchase-reference-edit', function(event) {
            event.preventDefault();
            $('#ssi-purchase-reference-title').text($(this).attr('title'));
            $('#ssi-purchase-reference-loading').prop('hidden', false);
            purchaseFrame.attr('src', $(this).attr('href'));
            purchaseModal.modal('show');
        });
        purchaseFrame.on('load', function() {
            if ($(this).attr('src') === 'about:blank') return;
            $('#ssi-purchase-reference-loading').prop('hidden', true);
            try {
                var doc = this.contentDocument;
                if (doc && !doc.getElementById('ssi-purchase-embed-style')) {
                    var style = doc.createElement('style');
                    style.id = 'ssi-purchase-embed-style';
                    style.textContent = '.main-header,.main-sidebar,.main-footer,.control-sidebar,#main_app_header,.side-bar{display:none!important}.content-wrapper{margin-left:0!important}.thetop>main{width:100%!important}';
                    doc.head.appendChild(style);
                }
            } catch (error) { /* The browser keeps cross-origin documents isolated. */ }
        });
        purchaseModal.on('shown.bs.modal', function() { $('.modal-backdrop').last().css('z-index', 1060); });
        purchaseModal.on('hidden.bs.modal', function() {
            purchaseFrame.attr('src', 'about:blank');
            stock_sell_report_table.ajax.reload(null, false);
            if (window.parent !== window) window.parent.postMessage({type: 'financial-purchase-cost-updated'}, window.location.origin);
        });

        $(document).on('change', '#sell_list_filter_location_id, #sell_list_filter_customer_id, #sell_list_filter_payment_status, #created_by, #sales_cmsn_agnt, #service_staffs', function() {
            if ($(this).attr('id') === 'sell_list_filter_location_id') {
                var selectedLocations = $('#sell_list_filter_location_id').val() || [];
                var selectedWithoutAll = selectedLocations.filter(function(value) {
                    return value !== 'all';
                });

                if (selectedLocations.indexOf('all') !== -1 && selectedLocations.length > 1) {
                    $('#sell_list_filter_location_id').val(selectedWithoutAll).trigger('change.select2');
                }

                if (selectedLocations.length === 0) {
                    $('#sell_list_filter_location_id').val(['all']).trigger('change.select2');
                }
            }
            stock_sell_report_table.ajax.reload();
        });
    });
</script>
@endsection
