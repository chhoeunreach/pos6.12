@extends('layouts.app')
@section('title', __('lang_v1.manage_lot'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('lang_v1.manage_lot') }}</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="row">
                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_lot_id',  __('lang_v1.lot_number') . ' / SN / IMEI:') !!}
                            {!! Form::select('ml_lot_id', [], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'ml_lot_id']); !!}
                            <p class="help-block">Search by lot number, SKU/sub_sku, product name.</p>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_product_id',  __('product.product') . ' / ' . __('product.sku') . ':') !!}
                            {!! Form::select('ml_product_id', [], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'ml_product_id']); !!}
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_lot_number', __('lang_v1.lot_number') . ':') !!}
                            {!! Form::text('ml_lot_number', null, ['class' => 'form-control', 'id' => 'ml_lot_number', 'placeholder' => 'Lot/SN/IMEI contains...']) !!}
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_location_id', __('business.location') . ':') !!}
                            {!! Form::select('ml_location_id', $locations ?? [], null, ['class' => 'form-control select2', 'id' => 'ml_location_id']) !!}
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_supplier_id', __('purchase.supplier') . ':') !!}
                            {!! Form::select('ml_supplier_id', $suppliers ?? [], null, ['class' => 'form-control select2', 'id' => 'ml_supplier_id', 'placeholder' => __('messages.all')]) !!}
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_date_filter', __('report.date_range') . ':') !!}
                            {!! Form::text('ml_date_filter', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'ml_date_filter']); !!}
                            {!! Form::hidden('ml_start_date', null, ['id' => 'ml_start_date']) !!}
                            {!! Form::hidden('ml_end_date', null, ['id' => 'ml_end_date']) !!}
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_transaction_type', __('lang_v1.type') . ':') !!}
                            {!! Form::select('ml_transaction_type', ['all' => __('lang_v1.all'), 'purchase' => __('purchase.purchase'), 'sell' => __('sale.sale'), 'transfer' => __('lang_v1.stock_transfer'), 'adjustment' => __('stock_adjustment.stock_adjustment')], 'all', ['class' => 'form-control select2', 'id' => 'ml_transaction_type']) !!}
                        </div>
                    </div>

                    <div class="col-sm-6 col-xs-12">
                        <div class="form-group">
                            {!! Form::label('ml_status', __('sale.status') . ':') !!}
                            {!! Form::select('ml_status', ['all' => __('lang_v1.all'), 'in_stock' => 'In Stock', 'sold' => 'Sold', 'transferred' => 'Transferred', 'adjusted' => 'Adjusted'], 'all', ['class' => 'form-control select2', 'id' => 'ml_status']) !!}
                        </div>
                    </div>

                    <div class="col-sm-3 col-xs-6">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block" id="ml_search_btn">
                                <i class="fa fa-search"></i> @lang('messages.search')
                            </button>
                        </div>
                    </div>

                    <div class="col-sm-3 col-xs-6">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-default btn-block" id="ml_reset_btn">
                                <i class="fa fa-refresh"></i> @lang('messages.reset')
                            </button>
                        </div>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="manage_lot_table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>@lang('product.product')</th>
                                <th>SKU</th>
                                <th>@lang('product.variation')</th>
                                <th>SN / IMEI / @lang('lang_v1.lot_number')</th>
                                <th>@lang('product.exp_date')</th>
                                <th>Purchase Qty</th>
                                <th>Sold Qty</th>
                                <th>Transfer Qty</th>
                                <th>Adjustment Qty</th>
                                <th>Current Qty</th>
                                <th>Current Location</th>
                                <th>@lang('purchase.supplier')</th>
                                <th>@lang('messages.date')</th>
                                <th>@lang('sale.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $('.select2').select2({ width: '100%' });

        var should_load = false;

        $('#ml_lot_id').select2({
            ajax: {
                url: '{{ action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'lotSearch']) }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { term: params.term };
                },
                processResults: function (data) { return { results: data }; },
                cache: true
            },
            minimumInputLength: 2,
            allowClear: true,
            placeholder: 'Search lot/SN/IMEI',
        });

        $('#ml_product_id').select2({
            ajax: {
                url: '{{ action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'productSearch']) }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { term: params.term };
                },
                processResults: function (data) { return { results: data }; },
                cache: true
            },
            minimumInputLength: 2,
            allowClear: true,
            placeholder: 'Search product/SKU',
        });

        if ($('#ml_date_filter').length && typeof $.fn.daterangepicker !== 'undefined') {
            var ml_date_settings = (typeof dateRangeSettings !== 'undefined') ? dateRangeSettings : {};
            var ml_moment_format = (typeof moment_date_format !== 'undefined') ? moment_date_format : 'MM/DD/YYYY';

            $('#ml_date_filter').daterangepicker(ml_date_settings, function(start, end) {
                $('#ml_date_filter').val(start.format(ml_moment_format) + ' ~ ' + end.format(ml_moment_format));
                $('#ml_start_date').val(start.format('YYYY-MM-DD'));
                $('#ml_end_date').val(end.format('YYYY-MM-DD'));
            });
            $('#ml_date_filter').on('cancel.daterangepicker', function() {
                $('#ml_date_filter').val('');
                $('#ml_start_date').val('');
                $('#ml_end_date').val('');
            });
        }

        var manage_lot_table = $('#manage_lot_table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            dom: 'Blfrtip',
            buttons: ['copy', 'csv', 'excel', 'print'],
            ajax: {
                url: '{{ route('manage-lot.list') }}',
                data: function(d) {
                    d.should_load = should_load ? 1 : 0;
                    d.lot_id = $('#ml_lot_id').val();
                    d.product_id = $('#ml_product_id').val();
                    d.lot_number = $('#ml_lot_number').val();
                    d.location_id = $('#ml_location_id').val();
                    d.supplier_id = $('#ml_supplier_id').val();
                    d.start_date = $('#ml_start_date').val();
                    d.end_date = $('#ml_end_date').val();
                    d.transaction_type = $('#ml_transaction_type').val();
                    d.status = $('#ml_status').val();
                }
            },
            columns: [
                { data: 'product', name: 'product' },
                { data: 'sku', name: 'sku' },
                { data: 'variation', name: 'variation' },
                { data: 'lot_number', name: 'lot_number' },
                { data: 'exp_date', name: 'exp_date', searchable: false },
                { data: 'purchase_qty', name: 'purchase_qty', searchable: false },
                { data: 'sold_qty', name: 'sold_qty', searchable: false },
                { data: 'transfer_out_qty', name: 'transfer_qty', searchable: false },
                { data: 'adjustment_qty', name: 'adjustment_qty', searchable: false },
                { data: 'current_qty', name: 'current_qty', searchable: false },
                { data: 'current_location', name: 'current_location', orderable: false },
                { data: 'supplier', name: 'supplier' },
                { data: 'purchase_date', name: 'purchase_date', searchable: false },
                { data: 'status', name: 'status', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#manage_lot_table'));
            }
        });

        $('#ml_search_btn').click(function() {
            var hasFilter = $('#ml_lot_id').val()
                || $('#ml_product_id').val()
                || $('#ml_lot_number').val()
                || $('#ml_location_id').val()
                || $('#ml_supplier_id').val()
                || ($('#ml_start_date').val() && $('#ml_end_date').val());

            if (!hasFilter) {
                toastr.warning('Please select at least one filter first.');
                return;
            }
            should_load = true;
            manage_lot_table.ajax.reload();
        });

        $('#ml_reset_btn').click(function() {
            should_load = false;
            $('#ml_lot_id').val(null).trigger('change');
            $('#ml_product_id').val(null).trigger('change');
            $('#ml_lot_number').val('');
            $('#ml_location_id').val('').trigger('change');
            $('#ml_supplier_id').val(null).trigger('change');
            $('#ml_date_filter').val('');
            $('#ml_start_date').val('');
            $('#ml_end_date').val('');
            $('#ml_transaction_type').val('all').trigger('change');
            $('#ml_status').val('all').trigger('change');
            manage_lot_table.ajax.reload();
        });
    });
</script>
@endsection
