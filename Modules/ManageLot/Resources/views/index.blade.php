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

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('lot_id',  __('lang_v1.lot_number') . ':') !!}
                            {!! Form::select('lot_id', [], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'ml_lot_id']); !!}
                            <p class="help-block">@lang('messages.search') @lang('lang_v1.lot_number') / SKU / @lang('product.product')</p>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a class="btn btn-primary btn-block" id="ml_view_history_btn" href="#" style="pointer-events:none; opacity:0.6;">
                                <i class="fa fa-eye"></i> @lang('messages.view')
                            </a>
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
                <p class="text-muted">@lang('lang_v1.note'): @lang('messages.search') @lang('lang_v1.lot_number') @lang('lang_v1.to') @lang('messages.view') @lang('lang_v1.lot_history').</p>
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

        if ($('#ml_lot_id').length) {
            $('#ml_lot_id').select2({
                ajax: {
                    url: '{{ action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'lotSearch']) }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            term: params.term,
                            location_id: $('#ml_location_id').val(),
                            product_id: $('#ml_product_id').val(),
                        };
                    },
                    processResults: function (data) { return { results: data }; },
                },
                minimumInputLength: 1,
                escapeMarkup: function (m) { return m; },
            });
        }

        function updateHistoryLink() {
            var lotId = $('#ml_lot_id').val();
            var $btn = $('#ml_view_history_btn');
            if (lotId) {
                $btn.attr('href', '/manage-lot/' + lotId + '/history');
                $btn.css({ 'pointer-events': 'auto', 'opacity': 1 });
            } else {
                $btn.attr('href', '#');
                $btn.css({ 'pointer-events': 'none', 'opacity': 0.6 });
            }
        }

        $('#ml_lot_id').on('change', updateHistoryLink);
        $('#ml_location_id, #ml_product_id').on('change', function () {
            $('#ml_lot_id').val(null).trigger('change');
            updateHistoryLink();
        });
        updateHistoryLink();
    });
</script>
@endsection
