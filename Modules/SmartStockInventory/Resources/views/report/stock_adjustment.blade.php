@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Adjustment Report')
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Stock Adjustment Report</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('adj_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('adj_list_filter_location_id[]', ['all' => __('lang_v1.all')] + $business_locations->toArray(), ['all'], ['class' => 'form-control select2', 'id' => 'adj_list_filter_location_id', 'style' => 'width:100%', 'multiple' => 'multiple']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('adj_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('adj_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'adj_list_filter_date_range', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="stock_adjustment_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Invoice No</th>
                        <th>Lot Number</th>
                        <th>SKU</th>
                        <th>Product</th>
                        <th class="text-right">Previous Qty</th>
                        <th class="text-right">Adjusted Qty</th>
                        <th class="text-right">Difference</th>
                        <th>Reason</th>
                        <th>Adjusted By</th>
                        <th>Notes</th>
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
        $('#adj_list_filter_location_id').val(['all']).trigger('change.select2');

        $('#adj_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: moment(),
                endDate: moment()
            }),
            function (start, end) {
                $('#adj_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                stock_adjustment_report_table.ajax.reload();
            }
        );
        $('#adj_list_filter_date_range').val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));

        $('#adj_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#adj_list_filter_date_range').val('');
            stock_adjustment_report_table.ajax.reload();
        });

        stock_adjustment_report_table = $('#stock_adjustment_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ ssi_route('ssi.report.stock_adjustment') }}",
                data: function(d) {
                    if ($('#adj_list_filter_date_range').val()) {
                        d.start_date = $('#adj_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#adj_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.location_id = $('#adj_list_filter_location_id').val();
                    d = __datatable_ajax_callback(d);
                }
            },
            lengthMenu: [[10, 25, 50, 500, 1000, 2000, -1], [10, 25, 50, 500, 1000, 2000, "All"]],
            columns: [
                { data: 'date', name: 't.transaction_date', type: 'date' },
                { data: 'location', name: 'bl.name', type: 'text' },
                { data: 'invoice_no', name: 't.ref_no', type: 'text' },
                { data: 'lot_number', name: 'purchase_lines.lot_number', type: 'text' },
                { data: 'sku', name: 'v.sub_sku', type: 'text' },
                { data: 'product', name: 'v.name', type: 'text' },
                { data: 'previous_qty', name: 'previous_qty', searchable: false, className: 'text-right' },
                { data: 'adjusted_qty', name: 'adjusted_qty', searchable: false, className: 'text-right' },
                { data: 'difference', name: 'difference', searchable: false, className: 'text-right' },
                { data: 'reason', name: 't.additional_notes', type: 'text' },
                { data: 'adjusted_by', name: 'adjusted_by', type: 'text' },
                { data: 'note', name: 't.additional_notes', type: 'text' }
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#stock_adjustment_report_table'));
            }
        });

        $(document).on('change', '#adj_list_filter_location_id, #adj_list_filter_date_range', function() {
            if ($(this).attr('id') === 'adj_list_filter_location_id') {
                var selectedLocations = $('#adj_list_filter_location_id').val() || [];
                var selectedWithoutAll = selectedLocations.filter(function(value) {
                    return value !== 'all';
                });

                if (selectedLocations.indexOf('all') !== -1 && selectedLocations.length > 1) {
                    $('#adj_list_filter_location_id').val(selectedWithoutAll).trigger('change.select2');
                }

                if (selectedLocations.length === 0) {
                    $('#adj_list_filter_location_id').val(['all']).trigger('change.select2');
                }
            }
            stock_adjustment_report_table.ajax.reload();
        });
    });
</script>
@endsection
