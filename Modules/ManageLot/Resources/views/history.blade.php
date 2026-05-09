@extends('layouts.app')
@section('title', __('lang_v1.lot_history'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ __('lang_v1.lot_history') }} - {{ $lot->lot_number }}
    </h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="row">
                    <div class="col-md-6">
                        <strong>@lang('business.product'):</strong> {{ $lot->product }}<br>
                        <strong>SKU:</strong> {{ $lot->sku ?? '--' }}
                    </div>
                    <div class="col-md-6">
                        <strong>@lang('lang_v1.lot_number'):</strong> {{ $lot->lot_number }}<br>
                        <strong>@lang('product.exp_date'):</strong> {{ !empty($lot->exp_date) ? @format_date($lot->exp_date) : '--' }}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                {!! Form::open(['url' => action([\Modules\ManageLot\Http\Controllers\ManageLotController::class, 'history'], [$lot->lot_id]), 'method' => 'get', 'id' => 'manage_lot_history_filter_form' ]) !!}
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('mlh_date_filter', __('report.date_range') . ':') !!}
                            {!! Form::text('mlh_date_filter', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'mlh_date_filter']); !!}
                            {!! Form::hidden('start_date', null, ['id' => 'mlh_start_date']) !!}
                            {!! Form::hidden('end_date', null, ['id' => 'mlh_end_date']) !!}
                        </div>
                    </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="manage_lot_history_table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>@lang('messages.date')</th>
                                <th>@lang('lang_v1.type')</th>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('lang_v1.from') @lang('purchase.business_location')</th>
                                <th>@lang('lang_v1.to') @lang('purchase.business_location')</th>
                                <th>@lang('lang_v1.qty_in')</th>
                                <th>@lang('lang_v1.qty_out')</th>
                                <th>@lang('lang_v1.balance')</th>
                                <th>@lang('business.created_by')</th>
                                <th>@lang('sale.status')</th>
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
        if ($('#mlh_date_filter').length) {
            $('#mlh_date_filter').daterangepicker(dateRangeSettings, function(start, end) {
                $('#mlh_date_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                $('#mlh_start_date').val(start.format('YYYY-MM-DD'));
                $('#mlh_end_date').val(end.format('YYYY-MM-DD'));
                manage_lot_history_table.ajax.reload();
            });
            $('#mlh_date_filter').on('cancel.daterangepicker', function() {
                $('#mlh_date_filter').val('');
                $('#mlh_start_date').val('');
                $('#mlh_end_date').val('');
                manage_lot_history_table.ajax.reload();
            });
        }

            var manage_lot_history_table = $('#manage_lot_history_table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                dom: 'Blfrtip',
                buttons: ['copy', 'csv', 'excel', 'print'],
                ajax: {
                    url: '{{ route('manage-lot.history-list', [$lot->lot_id]) }}',
                    data: function(d) {
                        d.start_date = $('#mlh_start_date').val();
                        d.end_date = $('#mlh_end_date').val();
                    }
                },
                columns: [
                    { data: 'movement_date', name: 'movement_date' },
                    { data: 'movement_type', name: 'movement_type' },
                    { data: 'ref_no', name: 'ref_no' },
                    { data: 'from_location', name: 'from_location' },
                    { data: 'to_location', name: 'to_location' },
                    { data: 'qty_in', name: 'qty_in', searchable: false, orderable: false },
                    { data: 'qty_out', name: 'qty_out', searchable: false, orderable: false },
                    { data: 'balance_qty', name: 'balance_qty', searchable: false, orderable: false },
                    { data: 'created_by', name: 'created_by' },
                    { data: 'status', name: 'status', orderable: false, searchable: false },
                ],
                fnDrawCallback: function() {
                    __currency_convert_recursively($('#manage_lot_history_table'));
                }
            });
        });
</script>
@endsection
