@extends('layouts.app')
@section('title', 'Stock Sell Report')

@section('content')

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
                        <th class="text-right">Price</th>
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
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>

<section id="receipt_section" class="print_section"></section>

@stop
@section('javascript')

<script type="text/javascript">
    $(document).ready(function() {
        $('#sell_list_filter_location_id').val(['all']).trigger('change.select2');

        $('#sell_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: moment(),
                endDate: moment()
            }),
            function (start, end) {
                $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                stock_sell_report_table.ajax.reload();
            }
        );
        $('#sell_list_filter_date_range').val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));

        $('#sell_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#sell_list_filter_date_range').val('');
            stock_sell_report_table.ajax.reload();
        });

        stock_sell_report_table = $('#stock_sell_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ action([\App\Http\Controllers\ReportController::class, 'stockSellReport']) }}",
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
            columns: [
                { data: 'transaction_date', name: 't.transaction_date', type: 'date' },
                { data: 'invoice_no', name: 't.invoice_no', type: 'text' },
                { data: 'i_t', name: 't.additional_notes', orderable: false },
                { data: 'customer', name: 'c.name' },
                { data: 'phone', name: 'c.mobile' },
                { data: 'sku', name: 'v.sub_sku' },
                { data: 'lots', name: 'lots', searchable: false, orderable: false },
                { data: 'product', name: 'p.name' },
                { data: 'quantity', name: 'transaction_sell_lines.quantity', className: 'text-right' },
                { data: 'purchase_price', name: 'purchase_price', searchable: false, className: 'text-right' },
                { data: 'price', name: 'transaction_sell_lines.unit_price_before_discount', className: 'text-right' },
                { data: 'total', name: 'total', searchable: false, className: 'text-right' },
                { data: 'profit_loss', name: 'profit_loss', searchable: false, className: 'text-right' },
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
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#stock_sell_report_table'));
            }
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
