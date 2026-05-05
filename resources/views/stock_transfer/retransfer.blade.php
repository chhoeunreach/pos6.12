@extends('layouts.app')
@section('title', 'Retransfer')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Retransfer</h1>
    </section>

    <!-- Main content -->
    <section class="content no-print">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\StockTransferController::class, 'store']),
            'method' => 'post',
            'id' => 'stock_transfer_form',
        ]) !!}

        <input type="hidden" name="retransfer_of_transfer_id" value="{{ $original_transfer->id }}">
        <input type="hidden" id="retransfer_lines" value="{{ e(json_encode($retransfer_lines ?? [])) }}">

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
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
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('ref_no', __('purchase.ref_no') . ':') !!}
                        {!! Form::text('ref_no', $suggested_ref_no ?? null, ['class' => 'form-control']) !!}
                        <p class="help-block text-muted" style="margin-bottom: 0;">
                            Retransfer from: <strong>{{ $original_transfer->ref_no }}</strong>
                        </p>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('status', __('sale.status') . ':*') !!} @show_tooltip(__('lang_v1.completed_status_help'))
                        {!! Form::select('status', $statuses, 'completed', [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'status',
                        ]) !!}
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('location_id', __('lang_v1.location_from') . ':*') !!}
                        {!! Form::select('location_id', $business_locations, $default_location_from_id ?? null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'location_id',
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('transfer_location_id', __('lang_v1.location_to') . ':*') !!}
                        {!! Form::select('transfer_location_id', $business_locations, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'transfer_location_id',
                        ]) !!}
                    </div>
                </div>

            </div>
        @endcomponent

        <!-- end-->
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
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-10 col-sm-offset-1">
                    @php
                        $product_row_index = 0;
                        $subtotal = 0;
                    @endphp
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
                                @foreach ($products as $product)
                                    @include('stock_transfer.partials.product_table_row', [
                                        'product' => $product,
                                        'row_index' => $loop->index,
                                        'sub_units' => !empty($product->unit_details) ? $product->unit_details : [],
                                    ])
                                    @php
                                        $product_row_index = $loop->index + 1;
                                        $subtotal += $product->quantity_ordered * $product->default_purchase_price;
                                    @endphp
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="text-center show_price_with_permission">
                                    <td colspan="3"></td>
                                    <td>
                                        <div class="pull-right"><b>@lang('sale.total'): </b> <span id="total_adjustment">{{ @num_format($subtotal) }}</span></div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        <input type="hidden" id="product_row_index" value="{{ $product_row_index }}">
                    </div>
                </div>
            </div>
        @endcomponent


        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('shipping_charges', __('lang_v1.shipping_charges') . ':') !!}
                        {!! Form::text('shipping_charges', 0, [
                            'class' => 'form-control input_number',
                            'placeholder' => __('lang_v1.shipping_charges'),
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('additional_notes', __('purchase.additional_notes')) !!}
                        {!! Form::textarea('additional_notes', $suggested_additional_notes ?? null, ['class' => 'form-control', 'rows' => 3]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 text-right show_price_with_permission">
                    <b>@lang('stock_adjustment.total_amount'):</b> <span id="final_total_text">{{ @num_format($subtotal) }}</span>
                </div>
                <br>
                <br>
                <div class="col-sm-12 text-center">
                    <button type="submit" id="save_stock_transfer" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white">@lang('messages.save')</button>
                </div>
            </div>
        @endcomponent

        {!! Form::close() !!}
    </section>
@stop
@section('javascript')
    <script src="{{ asset('js/stock_transfer.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        __page_leave_confirmation('#stock_transfer_form');
    </script>
@endsection


@cannot('view_purchase_price')
    <style>
        .show_price_with_permission {
            display: none !important;
        }
    </style>
@endcannot
