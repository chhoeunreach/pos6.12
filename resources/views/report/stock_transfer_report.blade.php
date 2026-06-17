@extends('layouts.app')
@section('title', __( 'report.stock_transfer_report' ))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'report.stock_transfer_report' )
    </h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_location_from', __('lang_v1.location_from') . ':') !!}
                    {!! Form::select('st_report_location_from', $business_locations, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'id' => 'st_report_location_from',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_location_to', __('lang_v1.location_to') . ':') !!}
                    {!! Form::select('st_report_location_to', $business_locations, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'id' => 'st_report_location_to',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_sender', __('lang_v1.added_by') . ':') !!}
                    {!! Form::select('st_report_sender[]', $users, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'id' => 'st_report_sender',
                        'multiple',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_date_range', __('report.date_range') . ':') !!}
                    {!! Form::text('st_report_date_range', null, [
                        'class' => 'form-control',
                        'id' => 'st_report_date_range',
                        'readonly',
                        'placeholder' => __('report.date_range'),
                    ]) !!}
                </div>
            </div>
        @endcomponent
    </div>

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('report.stock_transfer_report')])
                @slot('tool')
                    <div class="box-tools">
                        <button type="button" class="btn btn-primary btn-xs" id="copy_table_btn">
                            <i class="fa fa-copy"></i> @lang('lang_v1.copy')
                        </button>
                        <a class="btn btn-xs btn-success" id="export_csv_btn">
                            <i class="fa fa-download"></i> CSV
                        </a>
                    </div>
                @endslot
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="stock_transfer_report_table">
                        <thead>
                            <tr>
                                <th>@lang('messages.date')</th>
                                <th>@lang('product.sku')</th>
                                <th>@lang('sale.product')</th>
                                <th>@lang('lang_v1.quantity')</th>
                                <th>@lang('lang_v1.location_from')</th>
                                <th>@lang('lang_v1.location_to')</th>
                                <th>@lang('sale.invoice_no')</th>
                                <th>@lang('lang_v1.added_by')</th>
                                <th>@lang('purchase.additional_notes')</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th>@lang('lang_v1.total')</th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>

</section>
<!-- /.content -->
@stop
@section('javascript')
@include('report.partials.stock_transfer_report_script')
@endsection
