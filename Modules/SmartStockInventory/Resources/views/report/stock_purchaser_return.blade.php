@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Purchaser Return Report')
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Stock Purchaser Return Report</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('pur_return_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('pur_return_list_filter_location_id[]', ['all' => __('lang_v1.all')] + $business_locations->toArray(), ['all'], ['class' => 'form-control select2', 'id' => 'pur_return_list_filter_location_id', 'style' => 'width:100%', 'multiple' => 'multiple']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('pur_return_list_filter_supplier_id', __('purchase.supplier') . ':') !!}
                {!! Form::select('pur_return_list_filter_supplier_id', $suppliers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('pur_return_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('pur_return_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'pur_return_list_filter_date_range', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="stock_purchaser_return_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Phone</th>
                        <th>Lot Number</th>
                        <th>SKU</th>
                        <th>Product</th>
                        <th class="text-right">Qty Returned</th>
                        <th class="text-right">Purchase Price</th>
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
        $('#pur_return_list_filter_location_id').val(['all']).trigger('change.select2');

        $('#pur_return_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: moment(),
                endDate: moment()
            }),
            function (start, end) {
                $('#pur_return_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                stock_purchaser_return_report_table.ajax.reload();
            }
        );
        $('#pur_return_list_filter_date_range').val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));

        $('#pur_return_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#pur_return_list_filter_date_range').val('');
            stock_purchaser_return_report_table.ajax.reload();
        });

        stock_purchaser_return_report_table = $('#stock_purchaser_return_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ ssi_route('ssi.report.stock_purchaser_return') }}",
                data: function(d) {
                    if ($('#pur_return_list_filter_date_range').val()) {
                        d.start_date = $('#pur_return_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#pur_return_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.location_id = $('#pur_return_list_filter_location_id').val();
                    d.supplier_id = $('#pur_return_list_filter_supplier_id').val();
                    d = __datatable_ajax_callback(d);
                }
            },
            lengthMenu: [[10, 25, 50, 500, 1000, 2000, -1], [10, 25, 50, 500, 1000, 2000, "All"]],
            columns: [
                { data: 'date', name: 'transactions.transaction_date', type: 'date' },
                { data: 'ref_no', name: 'transactions.ref_no', type: 'text' },
                { data: 'supplier', name: 'c.name', type: 'text' },
                { data: 'phone', name: 'c.mobile', type: 'text' },
                { data: 'lot_number', name: 'purchase_lines.lot_number', type: 'text' },
                { data: 'sku', name: 'v.sub_sku', type: 'text' },
                { data: 'product', name: 'v.name', type: 'text' },
                { data: 'quantity', name: 'purchase_lines.quantity_returned', className: 'text-right', searchable: false },
                { data: 'purchase_price', name: 'purchase_lines.purchase_price_inc_tax', className: 'text-right' },
                { data: 'total', name: 'total', searchable: false, className: 'text-right' },
                { data: 'reason', name: 'transactions.additional_notes', type: 'text' },
                { data: 'location', name: 'bl.name', type: 'text' }
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#stock_purchaser_return_report_table'));
            }
        });

        $(document).on('change', '#pur_return_list_filter_location_id, #pur_return_list_filter_supplier_id, #pur_return_list_filter_date_range', function() {
            if ($(this).attr('id') === 'pur_return_list_filter_location_id') {
                var selectedLocations = $('#pur_return_list_filter_location_id').val() || [];
                var selectedWithoutAll = selectedLocations.filter(function(value) {
                    return value !== 'all';
                });

                if (selectedLocations.indexOf('all') !== -1 && selectedLocations.length > 1) {
                    $('#pur_return_list_filter_location_id').val(selectedWithoutAll).trigger('change.select2');
                }

                if (selectedLocations.length === 0) {
                    $('#pur_return_list_filter_location_id').val(['all']).trigger('change.select2');
                }
            }
            stock_purchaser_return_report_table.ajax.reload();
        });
    });
</script>
@endsection
