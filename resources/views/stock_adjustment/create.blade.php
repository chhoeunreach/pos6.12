@extends('layouts.app')
@section('title', __('stock_adjustment.add'))

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <br>
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('stock_adjustment.add')</h1>
        <!-- <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
            <li class="active">Here</li>
        </ol> -->
    </section>

    <!-- Main content -->
    <section class="content no-print">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\StockAdjustmentController::class, 'store']),
            'method' => 'post',
            'id' => 'stock_adjustment_form',
        ]) !!}

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':*') !!}
                        {!! Form::select('location_id', $business_locations, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('ref_no', __('purchase.ref_no') . ':') !!}
                        {!! Form::text('ref_no', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('transaction_date', __('messages.date') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-calendar"></i>
                            </span>
                            {!! Form::text('transaction_date', @format_datetime('now'), ['class' => 'form-control', 'readonly', 'required']) !!}
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('adjustment_type', __('stock_adjustment.adjustment_type') . ':*') !!} @show_tooltip(__('tooltip.adjustment_type'))
                        {!! Form::select(
                            'adjustment_type',
                            ['normal' => __('stock_adjustment.normal'), 'abnormal' => __('stock_adjustment.abnormal')],
                            null,
                            ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required'],
                        ) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid'])
	            <div class="row">
	                <div class="col-sm-8 col-sm-offset-2">
	                    <div class="form-group">
	                        <div class="input-group">
	                            <span class="input-group-addon">
	                                <i class="fa fa-search"></i>
	                            </span>
	                            {!! Form::text('search_product', null, [
	                                'class' => 'form-control',
	                                'id' => 'search_product_for_srock_adjustment',
	                                'placeholder' => __('stock_adjustment.search_product'),
	                                'disabled',
	                            ]) !!}
	                            <span class="input-group-btn">
	                                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#import_stock_adjustment_products_modal" title="Import">
	                                    <i class="fa fa-upload"></i>
	                                </button>
	                            </span>
	                        </div>
	                        <p class="help-block text-muted" style="margin-bottom:0;">
	                            Import columns: sku (optional), lot_number (optional), quantity (required), adjustment_type (optional), note (optional)
	                        </p>
	                    </div>
	                </div>
	            </div>
            <div class="row">
                <div class="col-sm-10 col-sm-offset-1">
                    <input type="hidden" id="product_row_index" value="0">
                    <input type="hidden" id="total_amount" name="final_total" value="0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed" id="stock_adjustment_product_table">
                            <thead>
                                <tr>
                                    <th class="col-sm-4 text-center">
                                        @lang('sale.product')
                                    </th>
                                    <th class="col-sm-2 text-center">
                                        @lang('sale.qty')
                                    </th>
                                    <th class="col-sm-2 text-center show_price_with_permission">
                                        @lang('sale.unit_price')
                                    </th>
                                    <th class="col-sm-2 text-center show_price_with_permission">
                                        @lang('sale.subtotal')
                                    </th>
                                    <th class="col-sm-2 text-center"><i class="fa fa-trash" aria-hidden="true"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                            <tfoot>
                                <tr class="text-center show_price_with_permission">
                                    <td colspan="3"></td>
                                    <td>
                                        <div class="pull-right"><b>@lang('stock_adjustment.total_amount'):</b> <span
                                                id="total_adjustment">0.00</span></div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('total_amount_recovered', __('stock_adjustment.total_amount_recovered') . ':') !!} @show_tooltip(__('tooltip.total_amount_recovered'))
                        {!! Form::text('total_amount_recovered', 0, [
                            'class' => 'form-control input_number',
                            'placeholder' => __('stock_adjustment.total_amount_recovered'),
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('additional_notes', __('stock_adjustment.reason_for_stock_adjustment') . ':') !!}
                        {!! Form::textarea('additional_notes', null, [
                            'class' => 'form-control',
                            'placeholder' => __('stock_adjustment.reason_for_stock_adjustment'),
                            'rows' => 3,
                        ]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12 text-center">
                    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white">@lang('messages.save')</button>
                </div>
            </div>
        @endcomponent
	        {!! Form::close() !!}
	    </section>

	    <div class="modal fade" id="import_stock_adjustment_products_modal" tabindex="-1" role="dialog" aria-labelledby="importStockAdjustmentProductsLabel">
	        <div class="modal-dialog modal-lg" role="document">
	            <div class="modal-content">
	                <div class="modal-header">
	                    <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')"><span aria-hidden="true">&times;</span></button>
	                    <h4 class="modal-title" id="importStockAdjustmentProductsLabel">Import Products</h4>
	                </div>
	                <div class="modal-body">
	                    <div class="alert alert-info" style="margin-bottom: 10px;">
	                        <strong>Tip:</strong> Select <strong>Business Location</strong> first. Lot Number matching is prioritized over SKU.
	                    </div>

	                    <div class="clearfix" style="margin-bottom:10px;">
	                        <a href="{{ action([\App\Http\Controllers\StockAdjustmentController::class, 'downloadImportTemplate']) }}"
	                            class="btn btn-default pull-right">
	                            <i class="fa fa-download"></i> Download Template
	                        </a>
	                    </div>

	                    <div class="form-group">
	                        <label>File (CSV/XLSX)</label>
	                        <input type="file" class="form-control" id="stock_adjustment_import_file" accept=".csv,.xlsx,.xls">
	                    </div>

	                    <div id="stock_adjustment_import_summary" class="well well-sm" style="display:none; margin-bottom: 10px;"></div>

	                    <div id="stock_adjustment_import_errors_wrap" style="display:none;">
	                        <h5 style="margin-top:0;">Failed rows</h5>
	                        <div class="table-responsive">
	                            <table class="table table-bordered table-condensed" id="stock_adjustment_import_errors_table">
	                                <thead>
	                                    <tr>
	                                        <th>Row</th>
	                                        <th>Matched By</th>
	                                        <th>SKU</th>
	                                        <th>Lot Number</th>
	                                        <th>Qty</th>
	                                        <th>Adj Type</th>
	                                        <th>Note</th>
	                                        <th>Error</th>
	                                    </tr>
	                                </thead>
	                                <tbody></tbody>
	                            </table>
	                        </div>
	                    </div>
	                </div>
	                <div class="modal-footer">
	                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
	                    <button type="button" class="btn btn-primary" id="btn_import_stock_adjustment_products">Import & Load</button>
	                </div>
	            </div>
	        </div>
	    </div>
@stop
@section('javascript')
    <script src="{{ asset('js/stock_adjustment.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        __page_leave_confirmation('#stock_adjustment_form');
    </script>
@endsection


@cannot('view_purchase_price')
    <style>
        .show_price_with_permission {
            display: none !important;
        }
    </style>
@endcannot
