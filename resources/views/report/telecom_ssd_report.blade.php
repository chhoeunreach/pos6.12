@extends('layouts.app')
@section('title', __('report.telecom_ssd_report'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('report.telecom_ssd_report')</h1>
    <p class="text-muted">@lang('report.telecom_ssd_basis')</p>
</section>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        {!! Form::open(['url' => route('reports.telecom-ssd'), 'method' => 'get', 'id' => 'telecom_ssd_filters']) !!}
        {!! Form::hidden('start_date', request('start_date'), ['id' => 'telecom_ssd_start_date']) !!}
        {!! Form::hidden('end_date', request('end_date'), ['id' => 'telecom_ssd_end_date']) !!}
        @foreach([
            'location_id' => [__('purchase.business_location'), $business_locations],
            'supplier_id' => [__('purchase.supplier'), $suppliers],
            'brand_id' => [__('product.brand'), $brands],
        ] as $field => $options)
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label($field, $options[0] . ':') !!}
                    {!! Form::select($field, $options[1], request($field), ['class' => 'form-control select2', 'placeholder' => __('lang_v1.all'), 'style' => 'width:100%']) !!}
                </div>
            </div>
        @endforeach
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('telecom_ssd_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('date_range', null, ['id' => 'telecom_ssd_date_range', 'placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('category_id', __('product.category') . ':') !!}
                {!! Form::select('category_id', $categories, request('category_id'), ['class' => 'form-control select2', 'placeholder' => __('lang_v1.all'), 'style' => 'width:100%']) !!}
            </div>
        </div>
        {!! Form::close() !!}
    @endcomponent
    @component('components.widget', ['class' => 'box-primary'])
        <div class="clearfix" style="margin-bottom: 12px;">
            <a href="{{ route('reports.telecom-ssd', array_merge(request()->only(['start_date', 'end_date', 'supplier_id', 'location_id', 'brand_id', 'category_id', 'search_text']), ['export' => 'csv'])) }}"
                id="telecom_ssd_export" class="btn btn-default pull-right">
                <i class="fa fa-download" aria-hidden="true"></i> {{ __('Export All (CSV)') }}
            </a>
        </div>
        <div id="telecom_ssd_error" class="alert alert-danger" role="alert" hidden></div>
        <div class="table-responsive">
            <table id="telecom_ssd_report_table" class="table table-bordered table-striped ajax_view" style="width:100%" data-initial-search="{{ request('search_text', '') }}" data-url="{{ route('reports.telecom-ssd') }}" data-index-column="{{ config('datatables.index_column', 'DT_RowIndex') }}">
                <thead><tr>
                    @foreach($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
    @endcomponent
</section>
@endsection

@section('javascript')
    <script src="{{ asset('js/telecom-ssd-report.js?v=' . $asset_v) }}"></script>
@endsection
