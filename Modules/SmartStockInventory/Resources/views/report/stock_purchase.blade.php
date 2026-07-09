@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Purchase Report')
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Stock Purchase Report</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('purchase_list_filter_location_id[]', ['all' => __('lang_v1.all')] + $business_locations->toArray(), ['all'], ['class' => 'form-control select2', 'id' => 'purchase_list_filter_location_id', 'style' => 'width:100%', 'multiple' => 'multiple']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_supplier_id',  __('purchase.supplier') . ':') !!}
                {!! Form::select('purchase_list_filter_supplier_id', $suppliers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_payment_status',  __('purchase.payment_status') . ':') !!}
                {!! Form::select('purchase_list_filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('purchase_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'purchase_list_filter_date_range', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="stock_purchase_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Phone</th>
                        <th>Lot</th>
                        <th>SKU</th>
                        <th>Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Purchase Price</th>
                        <th class="text-right">Subtotal</th>
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
        $('#purchase_list_filter_location_id').val(['all']).trigger('change.select2');

        $('#purchase_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: moment(),
                endDate: moment()
            }),
            function (start, end) {
                $('#purchase_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                stock_purchase_report_table.ajax.reload();
            }
        );
        $('#purchase_list_filter_date_range').val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));

        $('#purchase_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#purchase_list_filter_date_range').val('');
            stock_purchase_report_table.ajax.reload();
        });

        stock_purchase_report_table = $('#stock_purchase_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ ssi_route('ssi.report.stock_purchase') }}",
                data: function(d) {
                    if ($('#purchase_list_filter_date_range').val()) {
                        d.start_date = $('#purchase_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#purchase_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.location_id = $('#purchase_list_filter_location_id').val();
                    d.supplier_id = $('#purchase_list_filter_supplier_id').val();
                    d.payment_status = $('#purchase_list_filter_payment_status').val();
                    d = __datatable_ajax_callback(d);
                }
            },
            lengthMenu: [[10, 25, 50, 500, 1000, 2000, -1], [10, 25, 50, 500, 1000, 2000, "All"]],
            columns: [
                { data: 'transaction_date', name: 't.transaction_date', type: 'date' },
                { data: 'ref_no', name: 't.ref_no', type: 'text' },
                { data: 'supplier', name: 'c.name' },
                { data: 'phone', name: 'c.mobile' },
                { data: 'lot_number', name: 'purchase_lines.lot_number' },
                { data: 'sku', name: 'v.sub_sku' },
                { data: 'product', name: 'p.name' },
                { data: 'quantity', name: 'purchase_lines.quantity', className: 'text-right' },
                { data: 'purchase_price', name: 'purchase_lines.purchase_price_inc_tax', className: 'text-right' },
                { data: 'subtotal', name: 'subtotal', searchable: false, className: 'text-right' },
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
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#stock_purchase_report_table'));
            }
        });

        $(document).on('change', '#purchase_list_filter_location_id, #purchase_list_filter_supplier_id, #purchase_list_filter_payment_status', function() {
            if ($(this).attr('id') === 'purchase_list_filter_location_id') {
                var selectedLocations = $('#purchase_list_filter_location_id').val() || [];
                var selectedWithoutAll = selectedLocations.filter(function(value) {
                    return value !== 'all';
                });

                if (selectedLocations.indexOf('all') !== -1 && selectedLocations.length > 1) {
                    $('#purchase_list_filter_location_id').val(selectedWithoutAll).trigger('change.select2');
                }

                if (selectedLocations.length === 0) {
                    $('#purchase_list_filter_location_id').val(['all']).trigger('change.select2');
                }
            }
            stock_purchase_report_table.ajax.reload();
        });
    });
</script>
@endsection
