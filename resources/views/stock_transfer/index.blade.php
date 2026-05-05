@extends('layouts.app')
@section('title', __('lang_v1.stock_transfers'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.stock_transfers')
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('stock_transfer_list_filter_product_id', __('sale.product') . ':') !!}
                {!! Form::select('stock_transfer_list_filter_product_id', [], null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_transfer_list_filter_product_id',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('stock_transfer_list_filter_location_from', __('lang_v1.location_from') . ':') !!}
                {!! Form::select('stock_transfer_list_filter_location_from', $business_locations, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_transfer_list_filter_location_from',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('stock_transfer_list_filter_location_to', __('lang_v1.location_to') . ':') !!}
                {!! Form::select('stock_transfer_list_filter_location_to', $business_locations, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_transfer_list_filter_location_to',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_transfer_list_filter_status', __('sale.status') . ':') !!}
                {!! Form::select('stock_transfer_list_filter_status', $statuses, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_transfer_list_filter_status',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('stock_transfer_list_filter_created_by', __('report.user') . ':') !!}
                {!! Form::select('stock_transfer_list_filter_created_by', $users, null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id' => 'stock_transfer_list_filter_created_by',
                    'placeholder' => __('lang_v1.all'),
                ]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('stock_transfer_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('stock_transfer_list_filter_date_range', null, [
                    'class' => 'form-control',
                    'id' => 'stock_transfer_list_filter_date_range',
                    'readonly',
                    'placeholder' => __('report.select_a_date_range'),
                ]) !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_stock_transfers')])
        @slot('tool')
            <div class="box-tools">
                @if(auth()->user()->can('stock_transfer.create'))
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                        href="{{action([\App\Http\Controllers\StockTransferController::class, 'create'])}}">
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
            <table class="table table-bordered table-striped ajax_view" id="stock_transfer_table">
                <thead>
                    <tr>
                        <th>@lang('messages.date')</th>
                        <th>@lang('purchase.ref_no')</th>
                        <th>@lang('lang_v1.location_from')</th>
                        <th>@lang('lang_v1.location_to')</th>
                        <th>@lang('sale.status')</th>
                        <th>Total Qty</th>
                        <th>@lang('lang_v1.shipping_charges')</th>
                        <th>@lang('stock_adjustment.total_amount')</th>
                        <th>@lang('purchase.additional_notes')</th>
                        <th class="tw-w-full">@lang('messages.action')</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>

@include('stock_transfer.partials.update_status_modal')

<section id="receipt_section" class="print_section"></section>

<!-- /.content -->
@stop
@section('javascript')
	<script src="{{ asset('js/stock_transfer.js?v=' . $asset_v) }}"></script>
@endsection

@cannot('view_purchase_price')
    <style>
        .show_price_with_permission {
            display: none !important;
        }
    </style>
@endcannot
