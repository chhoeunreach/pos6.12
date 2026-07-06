@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Sell Return Report')
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Stock Sell Return Report</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_return_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_return_list_filter_location_id[]', ['all' => __('lang_v1.all')] + $business_locations->toArray(), ['all'], ['class' => 'form-control select2', 'id' => 'sell_return_list_filter_location_id', 'style' => 'width:100%', 'multiple' => 'multiple']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_return_list_filter_customer_id', __('sale.customer') . ':') !!}
                {!! Form::select('sell_return_list_filter_customer_id', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_return_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_return_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'sell_return_list_filter_date_range', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="stock_sell_return_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Lot Number</th>
                        <th>SKU</th>
                        <th>Product</th>
                        <th class="text-right">Qty Returned</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total Return Value</th>
                        <th>Reason</th>
                        <th>Location</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>

<section id="receipt_section" class="print_section"></section>

@stop
@section('module_js')
<script type="text/javascript">
    $(document).ready(function() {
        $('#sell_return_list_filter_location_id').val(['all']).trigger('change.select2');

        $('#sell_return_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: moment(),
                endDate: moment()
            }),
            function (start, end) {
                $('#sell_return_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                stock_sell_return_report_table.ajax.reload();
            }
        );
        $('#sell_return_list_filter_date_range').val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));

        $('#sell_return_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#sell_return_list_filter_date_range').val('');
            stock_sell_return_report_table.ajax.reload();
        });

        stock_sell_return_report_table = $('#stock_sell_return_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ ssi_route('ssi.report.stock_sell_return') }}",
                data: function(d) {
                    if ($('#sell_return_list_filter_date_range').val()) {
                        d.start_date = $('#sell_return_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#sell_return_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.location_id = $('#sell_return_list_filter_location_id').val();
                    d.customer_id = $('#sell_return_list_filter_customer_id').val();
                    d = __datatable_ajax_callback(d);
                }
            },
            columns: [
                { data: 'date', name: 't.transaction_date', type: 'date' },
                { data: 'invoice_no', name: 't.ref_no', type: 'text' },
                { data: 'customer', name: 'c.name', type: 'text' },
                { data: 'phone', name: 'c.mobile', type: 'text' },
                { data: 'lot_number', name: 'purchase_lines.lot_number', type: 'text' },
                { data: 'sku', name: 'v.sub_sku', type: 'text' },
                { data: 'product', name: 'p.name', type: 'text' },
                { data: 'quantity', name: 'transaction_sell_lines.quantity', className: 'text-right', searchable: false },
                { data: 'unit_price', name: 'transaction_sell_lines.unit_price_before_discount', className: 'text-right' },
                { data: 'total', name: 'total', searchable: false, className: 'text-right' },
                { data: 'reason', name: 't.additional_notes', type: 'text' },
                { data: 'location', name: 'bl.name', type: 'text' }
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#stock_sell_return_report_table'));
            }
        });

        $(document).on('change', '#sell_return_list_filter_location_id, #sell_return_list_filter_customer_id, #sell_return_list_filter_date_range', function() {
            if ($(this).attr('id') === 'sell_return_list_filter_location_id') {
                var selectedLocations = $('#sell_return_list_filter_location_id').val() || [];
                var selectedWithoutAll = selectedLocations.filter(function(value) {
                    return value !== 'all';
                });

                if (selectedLocations.indexOf('all') !== -1 && selectedLocations.length > 1) {
                    $('#sell_return_list_filter_location_id').val(selectedWithoutAll).trigger('change.select2');
                }

                if (selectedLocations.length === 0) {
                    $('#sell_return_list_filter_location_id').val(['all']).trigger('change.select2');
                }
            }
            stock_sell_return_report_table.ajax.reload();
        });
    });
</script>
@endsection