@extends('layouts.app')
@section('title', __('lang_v1.lot_history'))

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('lang_v1.lot_history') }}</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                {!! Form::open(['url' => action([\App\Http\Controllers\ReportController::class, 'getLotHistory']), 'method' => 'get', 'id' => 'lot_history_filter_form' ]) !!}
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                            {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('product_id',  __('product.product') . ':') !!}
                            {!! Form::select('product_id', [], null, ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('lot_number',  __('lang_v1.lot_number') . ':') !!}
                            {!! Form::text('lot_number', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.lot_number')]); !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('movement_type', __('lang_v1.type') . ':') !!}
                            {!! Form::select('movement_type', ['all' => __('messages.all'), 'purchase' => __('purchase.purchase'), 'sell' => __('sale.sale'), 'adjustment' => __('stock_adjustment.stock_adjustment')], 'all', ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('lot_history_date_filter', __('report.date_range') . ':') !!}
                            {!! Form::text('lot_history_date_filter', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
                            {!! Form::hidden('start_date', null, ['id' => 'lot_history_start_date']) !!}
                            {!! Form::hidden('end_date', null, ['id' => 'lot_history_end_date']) !!}
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
                    <table class="table table-bordered table-striped" id="lot_history_report">
                        <thead>
                            <tr>
                                <th>@lang('messages.date')</th>
                                <th>@lang('purchase.business_location')</th>
                                <th>SKU</th>
                                <th>@lang('business.product')</th>
                                <th>@lang('lang_v1.lot_number')</th>
                                <th>@lang('product.exp_date')</th>
                                <th>@lang('lang_v1.type')</th>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('contact.contact')</th>
                                <th>@lang('lang_v1.qty_in')</th>
                                <th>@lang('lang_v1.qty_out')</th>
                                <th>@lang('sale.notes')</th>
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
    <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
@endsection

