@extends('smartstockinventory::layouts.master')

@section('page_title', __('lang_v1.lot_history') . ' - ' . $lot)
@section('hide_page_header', '1')
@section('hide_action_bar', '1')

@section('css')
<link rel="stylesheet" href="{{ asset('css/lot-history.css') }}?v={{ filemtime(public_path('css/lot-history.css')) }}">
@endsection

@section('module_content')

<section class="content-header lot-history-heading">
    <h1>{{ __('lang_v1.lot_history') }} <span>{{ $lot }}</span></h1>
</section>

<section class="content lot-history-content">

    @if($lotInfo)
    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid lot-history-summary">
                <div class="box-body">
                    <div class="row lot-history-meta-grid">
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
                        <div class="col-md-3">
                            <strong id="lot_history_stock_label" data-normal-label="{{ __('sale.stock') }}">{{ $stockNeedsReview ? 'History Quantity' : __('sale.stock') }}:</strong> <span id="lot_history_current_stock" class="
                                @if($stockNeedsReview) text-warning @elseif($currentStock > 0) text-green @else text-red @endif
                            "><span class="display_currency" data-is_quantity="true" data-currency_symbol="false" data-orig-value="{{ $currentStock }}">{{ $currentStock }}</span> {{ $lotInfo->unit ?? '' }}</span>
                            <span class="text-muted" id="lot_history_stock_locations">({{ $lotInfo->location_name ?: '--' }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div id="lot_stock_review">
        @include('smartstockinventory::lot.partials.stock_review', ['stockReview' => $stockReview])
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default" id="lot_history_filter_box">
                <div class="box-header with-border" id="toggle_lot_history_filters" style="cursor:pointer;">
                    <h3 class="box-title">{{ __('report.filters') }}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" title="{{ __('report.filters') }}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body" id="lot_history_filters_body" style="display:none;">
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
            <div class="box box-primary lot-history-table-card">
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="lot_history_report">
                            <thead>
                                <tr>
                                    <th>{{ __('messages.date') }}</th>
                                    <th>{{ __('lang_v1.lot_number') }}</th>
                                    <th>{{ __('product.sku') }}</th>
                                    <th>{{ __('business.product') }}</th>
                                    @foreach(\App\Services\LotHistoryPrices::COLUMNS as $column => $types)
                                        <th class="text-right">{{ __('localcashierreport::report.finance_lot_'.$column) }}</th>
                                    @endforeach
                                    <th>Type</th>
                                    <th>Ref No.</th>
                                    <th>Location</th>
                                    <th>{{ __('lang_v1.quantity_change') }}</th>
                                    <th>{{ __('lang_v1.new_quantity') }}</th>
                                    <th>{{ __('sale.status') }}</th>
                                    <th>{{ __('report.user') }}</th>
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
                dataSrc: function(json) {
                    $('#lot_stock_review').html(json.stock_review_html || '');
                    var $stockLabel = $('#lot_history_stock_label');
                    $stockLabel.text((json.stock_reconciliation_required ? 'History Quantity' : $stockLabel.attr('data-normal-label')) + ':');
                    var $stock = $('#lot_history_current_stock');
                    $stock.toggleClass('text-green', !json.stock_reconciliation_required && Number(json.current_stock) > 0)
                        .toggleClass('text-red', !json.stock_reconciliation_required && Number(json.current_stock) <= 0)
                        .toggleClass('text-warning', !!json.stock_reconciliation_required);
                    $stock.find('.display_currency').attr('data-orig-value', json.current_stock)
                        .data('orig-value', json.current_stock).text(json.current_stock);
                    $('#lot_history_stock_locations').text('(' + (json.stock_location_display || '--') + ')');
                    __currency_convert_recursively($stock);

                    return json.data;
                },
                data: function(d) {
                    d.location_id = $('#location_id').val();
                    d.movement_type = $('#movement_type').val();
                    d.start_date = $('#lot_history_start_date').val();
                    d.end_date = $('#lot_history_end_date').val();
                }
            },
            ordering: false,
            drawCallback: function() {
                __currency_convert_recursively($('#lot_history_report'));
            },
            createdRow: function(row, data) {
                if (data.movement_type_key) {
                    $(row).addClass('lot-row-' + data.movement_type_key);
                }
            },
            columns: [
                { data: 'movement_date', name: 'movement_date', className: 'lot-timeline-date' },
                { data: 'lot_number', name: 'lot_number' },
                { data: 'sku', name: 'sku' },
                { data: 'product', name: 'product' },
                { data: 'purchase_price', name: 'purchase_price', className: 'text-right', searchable: false },
                { data: 'sell_price', name: 'sell_price', className: 'text-right', searchable: false },
                { data: 'transfer_price', name: 'transfer_price', className: 'text-right', searchable: false },
                { data: 'return_price', name: 'return_price', className: 'text-right', searchable: false },
                { data: 'adjustment_price', name: 'adjustment_price', className: 'text-right', searchable: false },
                { data: 'movement_type', name: 'movement_type' },
                { data: 'ref_no', name: 'ref_no' },
                { data: 'location_display', name: 'location_display' },
                { data: 'quantity_change', name: 'quantity_change', searchable: false },
                { data: 'balance_qty', name: 'balance_qty', searchable: false },
                { data: 'status', name: 'status', searchable: false, orderable: false },
                { data: 'user_name', name: 'user_name' },
                { data: 'movement_type_key', name: 'movement_type_key', visible: false, searchable: false },
                { data: 'transaction_id', name: 'transaction_id', visible: false, searchable: false },
                { data: 'transaction_type', name: 'transaction_type', visible: false, searchable: false },
            ],
        });

        $('#location_id, #movement_type').on('change', function() {
            lotHistoryTable.ajax.reload();
        });

        $('#toggle_lot_history_filters').on('click', function() {
            $('#lot_history_filters_body').slideToggle(150);
            $(this).find('i').toggleClass('fa-minus fa-plus');
        });
    });
</script>
@endsection
