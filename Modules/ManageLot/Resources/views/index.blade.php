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
                {!! Form::open(['url' => action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'index']), 'method' => 'get', 'id' => 'manage_lot_filter_form' ]) !!}
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('lot_number',  __('lang_v1.lot_number') . ':') !!}
                            {!! Form::text('lot_number', null, ['class' => 'form-control', 'id' => 'ml_lot_number', 'placeholder' => __('lang_v1.lot_number')]); !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('product_id',  __('product.product') . ':') !!}
                            {!! Form::select('product_id', [], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'ml_product_id']); !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                            {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'ml_location_id']); !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block" id="ml_search_btn">
                                <i class="fa fa-search"></i> @lang('messages.search')
                            </button>
                        </div>
                    </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="alert alert-warning">
                    @lang('lang_v1.lot_history_note')
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="manage_lot_table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>@lang('business.product')</th>
                                <th>SKU</th>
                                <th>@lang('product.variation')</th>
                                <th>@lang('lang_v1.lot_number')</th>
                                <th>@lang('product.exp_date')</th>
                                <th>@lang('purchase.purchase_quantity')</th>
                                <th>@lang('report.total_unit_sold')</th>
                                <th>@lang('lang_v1.stock_transfer')</th>
                                <th>@lang('lang_v1.total_unit_adjusted')</th>
                                <th>@lang('report.current_stock')</th>
                                <th>@lang('purchase.business_location')</th>
                                <th>@lang('purchase.supplier')</th>
                                <th>@lang('purchase.purchase_date')</th>
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
        if ($('#ml_product_id').length) {
            $('#ml_product_id').select2({
                ajax: {
                    url: '/products/list-no-variation',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { term: params.term }; },
                    processResults: function (data) { return { results: data }; },
                },
                minimumInputLength: 1,
                escapeMarkup: function (m) { return m; },
            });
        }

        var manage_lot_table = $('#manage_lot_table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            dom: 'Blfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'print'
            ],
            ajax: {
                url: '{{ action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'indexData']) }}',
                data: function(d) {
                    d.lot_number = $('#ml_lot_number').val();
                    d.product_id = $('#ml_product_id').val();
                    d.location_id = $('#ml_location_id').val();
                }
            },
            columns: [
                { data: 'product', name: 'p.name' },
                { data: 'sku', name: 'v.sub_sku' },
                { data: 'variation', name: 'v.name' },
                { data: 'lot_number', name: 'pl.lot_number' },
                { data: 'exp_date', name: 'pl.exp_date' },
                { data: 'purchase_qty', name: 'purchase_qty', searchable: false },
                { data: 'sold_qty', name: 'sold_qty', searchable: false },
                { data: 'transfer_out_qty', name: 'transfer_out_qty', searchable: false },
                { data: 'adjustment_qty', name: 'adjustment_qty', searchable: false },
                { data: 'current_qty', name: 'current_qty', searchable: false },
                { data: 'location_name', name: 'bl.name' },
                { data: 'supplier', name: 'supplier.name' },
                { data: 'purchase_date', name: 'tp.transaction_date' },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#manage_lot_table'));
            }
        });

        $('#ml_search_btn').click(function() {
            manage_lot_table.ajax.reload();
        });

        $('#ml_lot_number').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                manage_lot_table.ajax.reload();
            }
        });
    });
</script>
@endsection
