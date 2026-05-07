@extends('layouts.app')
@section('title', __('stock_adjustment.stock_adjustments'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('stock_adjustment.stock_adjustments')
        <small></small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_adjustment_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('stock_adjustment_list_filter_location_id', $business_locations, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_adjustment_list_filter_location_id',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_adjustment_list_filter_adjustment_type', __('stock_adjustment.adjustment_type') . ':') !!}
                {!! Form::select('stock_adjustment_list_filter_adjustment_type', $adjustment_types, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_adjustment_list_filter_adjustment_type',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_adjustment_list_filter_product_id', __('sale.product') . ':') !!}
                {!! Form::select('stock_adjustment_list_filter_product_id', [], null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_adjustment_list_filter_product_id',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_adjustment_list_filter_created_by', __('report.user') . ':') !!}
                {!! Form::select('stock_adjustment_list_filter_created_by', $users, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_adjustment_list_filter_created_by',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_adjustment_list_filter_ref_no', __('purchase.ref_no') . ':') !!}
                {!! Form::text('stock_adjustment_list_filter_ref_no', null, [
                    'class' => 'form-control',
                    'id' => 'stock_adjustment_list_filter_ref_no',
                    'placeholder' => __('purchase.ref_no'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('stock_adjustment_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('stock_adjustment_list_filter_date_range', null, [
                    'class' => 'form-control',
                    'id' => 'stock_adjustment_list_filter_date_range',
                    'readonly',
                    'placeholder' => __('report.select_a_date_range'),
                ]) !!}
            </div>
        </div>
    @endcomponent
    @component('components.widget', ['class' => 'box-primary', 'title' => __('stock_adjustment.all_stock_adjustments')])
        @slot('tool')
            <div class="box-tools">
                @if(auth()->user()->can('stock_adjustment.create'))
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                        href="{{action([\App\Http\Controllers\StockAdjustmentController::class, 'create'])}}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg> @lang('messages.add')
                    </a>
                @endif
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="stock_adjustment_table">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('messages.date')</th>
                        <th>@lang('purchase.ref_no')</th>
                        <th>@lang('business.location')</th>
                        <th>@lang('stock_adjustment.adjustment_type')</th>
                        <th>@lang('stock_adjustment.total_amount')</th>
                        <th>@lang('stock_adjustment.total_amount_recovered')</th>
                        <th>@lang('stock_adjustment.reason_for_stock_adjustment')</th>
                        <th>@lang('lang_v1.added_by')</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent

</section>
<!-- /.content -->
@stop
@section('javascript')
	<script src="{{ asset('js/stock_adjustment.js?v=' . $asset_v) }}"></script>
@endsection

@cannot('view_purchase_price')
    <style>
        .show_price_with_permission {
            display: none !important;
        }
    </style>
@endcannot
