@extends('smartstockinventory::layouts.master')

@section('page_title', __('lang_v1.lot_history') . ' - ' . $lot)

@section('module_content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('lang_v1.lot_history') }}: {{ $lot }}</h1>
</section>

<section class="content">

    @if($lotInfo)
    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-2">
                            <strong>SKU:</strong> {{ $lotInfo->sku ?? '--' }}
                        </div>
                        <div class="col-md-3">
                            <strong>{{ __('business.product') }}:</strong> {{ $lotInfo->product_name }}{{ $lotInfo->variation_name && $lotInfo->variation_name !== 'DUMMY' ? ' (' . $lotInfo->variation_name . ')' : '' }}
                        </div>
                        <div class="col-md-2">
                            <strong>{{ __('lang_v1.lot_number') }}:</strong> {{ $lotInfo->lot_number }}
                        </div>
                        <div class="col-md-2">
                            <strong>{{ __('product.exp_date') }}:</strong> {{ $lotInfo->exp_date ? $lotInfo->exp_date : '--' }}
                        </div>
                        <div class="col-md-1">
                            <strong>{{ __('sale.stock') }}:</strong> <span class="
                                @if($currentStock > 0) text-green @else text-red @endif
                            ">{{ number_format($currentStock) }} {{ $lotInfo->unit ?? '' }}</span>
                        </div>
                        <div class="col-md-2">
                            <strong>{{ __('purchase.business_location') }}:</strong> {{ $lotInfo->location_name ?? '--' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default" id="lot_history_filter_box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('report.filters') }}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                </div>
                <div class="box-body">
                    <form id="lot_history_filter_form" class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ __('purchase.business_location') }}:</label>
                                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.all')]) !!}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ __('lang_v1.type') }}:</label>
                                <select class="form-control select2" name="movement_type" id="movement_type" style="width:100%;">
                                    <option value="all">{{ __('messages.all') }}</option>
                                    <option value="purchase">{{ __('purchase.purchase') }}</option>
                                    <option value="sell">{{ __('sale.sale') }}</option>
                                    <option value="transfer">{{ __('lang_v1.stock_transfers') }}</option>
                                    <option value="adjustment">{{ __('stock_adjustment.stock_adjustment') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ __('report.date_range') }}:</label>
                                {!! Form::text('lot_history_date_filter', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'lot_history_date_filter']); !!}
                                {!! Form::hidden('start_date', null, ['id' => 'lot_history_start_date']) !!}
                                {!! Form::hidden('end_date', null, ['id' => 'lot_history_end_date']) !!}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-body">
                    <div class="alert alert-warning">
                        @lang('lang_v1.lot_history_note')
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="lot_history_report">
                            <thead>
                                <tr>
                                    <th>{{ __('messages.date') }}</th>
                                    <th>{{ __('purchase.business_location') }}</th>
                                    <th>SKU</th>
                                    <th>{{ __('business.product') }}</th>
                                    <th>{{ __('lang_v1.lot_number') }}</th>
                                    <th>{{ __('product.exp_date') }}</th>
                                    <th>{{ __('lang_v1.type') }}</th>
                                    <th>{{ __('purchase.ref_no') }}</th>
                                    <th>{{ __('contact.contact') }}</th>
                                    <th>{{ __('lang_v1.qty_in') }}</th>
                                    <th>{{ __('lang_v1.qty_out') }}</th>
                                    <th>{{ __('sale.notes') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

@endsection

@section('module_js')
<script>
    $(document).ready(function() {
        $('#lot_history_date_filter').daterangepicker(dateRangeSettings, function(start, end) {
            $('#lot_history_date_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            $('#lot_history_start_date').val(start.format('YYYY-MM-DD'));
            $('#lot_history_end_date').val(end.format('YYYY-MM-DD'));
            lotHistoryTable.ajax.reload();
        });

        var lotHistoryTable = $('#lot_history_report').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ ssi_route("ssi.lot.history", $lot) }}',
                data: function(d) {
                    d.location_id = $('#location_id').val();
                    d.movement_type = $('#movement_type').val();
                    d.start_date = $('#lot_history_start_date').val();
                    d.end_date = $('#lot_history_end_date').val();
                }
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'movement_date', name: 'movement_date' },
                { data: 'location_name', name: 'location_name' },
                { data: 'sku', name: 'sku' },
                { data: 'product', name: 'product' },
                { data: 'lot_number', name: 'lot_number' },
                { data: 'exp_date', name: 'exp_date' },
                { data: 'movement_type', name: 'movement_type' },
                { data: 'ref_no', name: 'ref_no' },
                { data: 'contact', name: 'contact' },
                { data: 'qty_in', name: 'qty_in', searchable: false },
                { data: 'qty_out', name: 'qty_out', searchable: false },
                { data: 'notes', name: 'notes', searchable: false },
                { data: 'transaction_id', name: 'transaction_id', visible: false, searchable: false },
                { data: 'transaction_type', name: 'transaction_type', visible: false, searchable: false },
            ],
        });

        $('#location_id, #movement_type').on('change', function() {
            lotHistoryTable.ajax.reload();
        });
    });
</script>
@endsection
