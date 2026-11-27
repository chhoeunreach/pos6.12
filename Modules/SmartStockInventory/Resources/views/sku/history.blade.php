@extends('smartstockinventory::layouts.master')
@section('page_title', 'SKU Transaction History')
@section('module_content')
@php
    $skuCanViewCosts = \Modules\SmartStockInventory\Support\SkuPermissions::allows(auth()->user(), 'view_cost');
    $skuCanEditCosts = \Modules\SmartStockInventory\Support\SkuPermissions::allows(auth()->user(), 'update_cost');
    $skuCanExport = \Modules\SmartStockInventory\Support\SkuPermissions::allows(auth()->user(), 'export');
    $types = [
        'purchase' => 'Purchase',
        'sell' => 'Sale / Production Use',
        'opening_stock' => 'Opening Stock',
        'purchase_transfer' => 'Transfer In',
        'sell_transfer' => 'Transfer Out',
        'sell_return' => 'Sale Return',
        'purchase_return' => 'Purchase Return',
        'stock_adjustment' => 'Stock Adjustment',
        'production_purchase' => 'Production In',
    ];
    $selectedLocationIds = ! empty($filters['location_ids']) ? array_map('strval', $filters['location_ids']) : ['all'];
    $selectedTypes = ! empty($filters['types']) ? array_map('strval', $filters['types']) : [];
    $selectedDirection = (string) ($filters['direction'] ?? '');
    $initialStartDate = (string) ($filters['start_date'] ?? '');
    $initialEndDate = (string) ($filters['end_date'] ?? '');
    $backUrl = ssi_route('ssi.sku.index', array_filter([
        'search' => $product->sku ?: $product->product_sku,
        'location_id' => count($filters['location_ids'] ?? []) === 1 ? $filters['location_ids'][0] : null,
    ]));
@endphp
@if($skuCanExport)
    <input type="hidden" id="view_export_buttons">
@endif

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        SKU Transaction History
        <small style="display:inline-block;margin-left:8px;">
            {{ $product->sku ?: $product->product_sku }} — {{ $product->product_name }}@if($product->variation_name && $product->variation_name !== 'DUMMY') ({{ $product->variation_name }})@endif
        </small>
    </h1>
</section>

<section class="content no-print">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @component('components.filters', ['title' => __('report.filters')])
        <form id="sku_history_filter_form" method="get" action="{{ ssi_route('ssi.sku.history', ['variation' => $product->variation_id]) }}">
            <div class="col-md-3">
                <div class="form-group">
                    <label for="sku_history_filter_location_id">{{ __('purchase.business_location') }}:</label>
                    <select id="sku_history_filter_location_id" name="location_id[]" class="form-control select2" style="width:100%;" multiple="multiple">
                        <option value="all" @selected(in_array('all', $selectedLocationIds, true))>{{ __('lang_v1.all') }}</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(in_array((string) $location->id, $selectedLocationIds, true))>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="sku_history_filter_type">Transaction Type:</label>
                    <select id="sku_history_filter_type" name="type[]" class="form-control select2" style="width:100%;" multiple="multiple" data-placeholder="{{ __('lang_v1.all') }}">
                        @foreach($types as $type => $label)
                            <option value="{{ $type }}" @selected(in_array($type, $selectedTypes, true))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="sku_history_filter_direction">Movement:</label>
                    <select id="sku_history_filter_direction" name="direction" class="form-control select2" style="width:100%;" data-placeholder="{{ __('lang_v1.all') }}">
                        <option value="" @selected($selectedDirection === '')>{{ __('lang_v1.all') }}</option>
                        <option value="in" @selected($selectedDirection === 'in')>Stock In (+)</option>
                        <option value="out" @selected($selectedDirection === 'out')>Stock Out (-)</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="sku_history_filter_date_range">{{ __('report.date_range') }}:</label>
                    <input type="text"
                           id="sku_history_filter_date_range"
                           class="form-control"
                           placeholder="{{ __('lang_v1.select_a_date_range') }}"
                           readonly>
                    <input type="hidden" id="sku_history_start_date" name="start_date" value="{{ $initialStartDate }}">
                    <input type="hidden" id="sku_history_end_date" name="end_date" value="{{ $initialEndDate }}">
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group" style="margin-bottom:0;">
                    <button type="button" id="sku_history_reset_btn" class="btn btn-default btn-sm">
                        <i class="fa fa-undo" aria-hidden="true"></i> Reset
                    </button>
                    <a class="btn btn-default btn-sm" href="{{ $backUrl }}">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i> Back to SKU Management
                    </a>
                    @can('stock_inventory.export')
                        <a id="sku_history_export_xlsx_btn"
                           class="btn btn-success btn-sm"
                           href="{{ ssi_route('ssi.sku.export', array_merge(request()->only('location_id', 'start_date', 'end_date', 'type', 'direction'), ['variation' => $product->variation_id])) }}">
                            <i class="fa fa-download" aria-hidden="true"></i> Export History
                        </a>
                    @endcan
                </div>
            </div>
        </form>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => ($product->sku ?: $product->product_sku) . ' — ' . $product->product_name])
        @slot('tool')
            <div class="box-tools pull-right">
                <button type="button" id="sku_history_refresh_btn" class="btn btn-sm btn-default">
                    <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
                </button>
            </div>
        @endslot

        <div class="row" style="margin-bottom: 10px;">
            <div class="col-sm-6">
                <p style="margin-bottom:4px;">
                    Current stock:
                    <strong>
                        <span id="sku_history_current_stock" class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $product->current_stock }}</span>
                        <span id="sku_history_unit">{{ $product->unit }}</span>
                    </strong>
                </p>
            </div>
            <div class="col-sm-6 text-right">
                <p class="text-muted" style="margin-bottom:4px;">Each balance is the stock at that location after the movement. Date filters preserve balances from earlier transactions.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="sku_history_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Transaction Type</th>
                        <th>Reference</th>
                        <th>Contact</th>
                        <th>Lots</th>
                        <th class="text-right" title="Selling price before line discount and item tax">Unit Price</th>
                        @if($skuCanViewCosts)
                            <th class="text-right">Unit Cost (Before Discount)</th>
                            <th class="text-right">Unit Cost (incl. tax)</th>
                        @endif
                        <th class="text-right">Qty In</th>
                        <th class="text-right">Qty Out</th>
                        <th class="text-right">Location Balance</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="bg-gray font-17 footer-total text-center">
                        <td colspan="{{ $skuCanViewCosts ? 9 : 7 }}" class="text-right"><strong>Total:</strong></td>
                        <td class="text-right" id="sku_history_footer_in"><strong>0</strong></td>
                        <td class="text-right" id="sku_history_footer_out"><strong>0</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent
</section>

@can('stock_inventory.update')
<div class="modal fade" id="sku-lot-modal" tabindex="-1" role="dialog" aria-labelledby="sku-lot-title">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form id="sku-lot-form" method="post">
            @csrf
            @method('PUT')
            <input type="hidden" name="original_lot_number" id="sku-lot-original">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="sku-lot-title">Quick Update Lot</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Update the lot number on receipt line <strong id="sku-lot-line"></strong>. Transactions linked to this receipt will show the updated number.</p>
                <div id="sku-lot-error" class="alert alert-danger" role="alert" style="display:none"></div>
                <div class="form-group"><label for="sku-lot-number">Lot Number</label><input id="sku-lot-number" name="lot_number" class="form-control" maxlength="191" required></div>
                <div class="form-group"><label for="sku-lot-reason">Reason</label><input id="sku-lot-reason" name="reason" class="form-control" maxlength="500" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="sku-lot-save">Save Lot</button></div>
        </form>
    </div></div>
</div>
@endcan
@if($skuCanEditCosts)
    @include('purchase.partials.lot_price_modal', ['costEditorId' => 'sku-purchase-cost-modal', 'costEditorLabels' => ['shared' => 'Updates the linked receipt cost for all related transactions. Prices include item tax.']])
    @include('purchase.partials.lot_price_modal', ['costEditorId' => 'sku-before-cost-modal', 'costEditorLabels' => ['title' => 'Unit Cost (Before Discount)', 'shared' => 'Updates the linked receipt cost for all related transactions, before item discount and item tax. Existing discount and tax rates are retained.']])
@endif
@can('sell.update')
    @include('purchase.partials.lot_price_modal', ['costEditorId' => 'sku-sale-price-modal', 'costEditorLabels' => ['title' => 'Update Unit Price', 'shared' => 'Selling price per base unit, before line discount and item tax. Existing discounts and tax rates are retained.']])
@endcan
@endsection

@section('module_js')
<script src="{{ asset('js/purchase-lot-price-modal.js') }}?v={{ filemtime(public_path('js/purchase-lot-price-modal.js')) }}"></script>
<script>
$(function () {
    var historyUrl = @json(ssi_route('ssi.sku.history', ['variation' => $product->variation_id]));
    var exportBaseUrl = @json(ssi_route('ssi.sku.export', ['variation' => $product->variation_id]));
    var canViewCosts = @json($skuCanViewCosts);
    var canExport = @json($skuCanExport);

    function syncExportUrl() {
        var $btn = $('#sku_history_export_xlsx_btn');
        if (!$btn.length) return;
        var params = buildFilterParams();
        $btn.attr('href', exportBaseUrl + ($.isEmptyObject(params) ? '' : '?' + $.param(params)));
    }

    function buildFilterParams() {
        var params = {};
        var locations = $('#sku_history_filter_location_id').val() || [];
        var cleanLocations = locations.filter(function (v) { return v !== 'all' && v !== ''; });
        if (cleanLocations.length) {
            params.location_id = cleanLocations;
        }
        var types = $('#sku_history_filter_type').val() || [];
        var cleanTypes = types.filter(function (v) { return v !== 'all' && v !== ''; });
        if (cleanTypes.length) {
            params.type = cleanTypes;
        }
        var direction = $('#sku_history_filter_direction').val();
        if (direction) {
            params.direction = direction;
        }
        var startDate = $('#sku_history_start_date').val();
        var endDate = $('#sku_history_end_date').val();
        if (startDate && endDate) {
            params.start_date = startDate;
            params.end_date = endDate;
        }
        return params;
    }

    var $dateRange = $('#sku_history_filter_date_range');
    var initStart = $('#sku_history_start_date').val();
    var initEnd = $('#sku_history_end_date').val();
    $dateRange.daterangepicker(
        $.extend({}, dateRangeSettings, {
            autoUpdateInput: false,
            startDate: initStart ? moment(initStart, 'YYYY-MM-DD') : moment(),
            endDate: initEnd ? moment(initEnd, 'YYYY-MM-DD') : moment()
        }),
        function (start, end) {
            $dateRange.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            $('#sku_history_start_date').val(start.format('YYYY-MM-DD'));
            $('#sku_history_end_date').val(end.format('YYYY-MM-DD'));
            syncExportUrl();
            skuHistoryTable.ajax.reload();
        }
    );
    if (initStart && initEnd) {
        $dateRange.val(moment(initStart, 'YYYY-MM-DD').format(moment_date_format) + ' ~ ' + moment(initEnd, 'YYYY-MM-DD').format(moment_date_format));
    }
    $dateRange.on('cancel.daterangepicker', function () {
        $dateRange.val('');
        $('#sku_history_start_date').val('');
        $('#sku_history_end_date').val('');
        syncExportUrl();
        skuHistoryTable.ajax.reload();
    });

    function prepareButtons(items) {
        return $.map(items || [], function (item) {
            var button = $.extend(true, {}, item);
            if (button.buttons) button.buttons = prepareButtons(button.buttons);
            return button;
        });
    }
    var buttons = canExport
        ? prepareButtons($.fn.dataTable.defaults.buttons)
        : [{ extend: 'colvis', text: LANG.col_vis, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2' }];

    var columns = [
        {
            data: 'date',
            name: 'date',
            render: function (data, type, row) {
                return type === 'sort' || type === 'type' ? (row.date_raw || '') : data;
            }
        },
        { data: 'location_name', name: 'location_name' },
        { data: 'type_label', name: 'type_label' },
        { data: 'ref_html', name: 'ref_html' },
        { data: 'contact_name', name: 'contact_name' },
        { data: 'lots_html', name: 'lots_html' },
        { data: 'sale_price_html', name: 'sale_price_html', className: 'text-right' }
    ];
    if (canViewCosts) {
        columns.push({ data: 'cost_before_html', name: 'cost_before_html', className: 'text-right' });
        columns.push({ data: 'cost_inc_html', name: 'cost_inc_html', className: 'text-right' });
    }
    columns.push(
        {
            data: 'qty_in_html',
            name: 'qty_in',
            className: 'text-right',
            render: function (data, type, row) {
                return type === 'sort' || type === 'type' ? Number(row.qty_in || 0) : data;
            }
        },
        {
            data: 'qty_out_html',
            name: 'qty_out',
            className: 'text-right',
            render: function (data, type, row) {
                return type === 'sort' || type === 'type' ? Number(row.qty_out || 0) : data;
            }
        },
        {
            data: 'stock_html',
            name: 'stock',
            className: 'text-right',
            render: function (data, type, row) {
                return type === 'sort' || type === 'type' ? Number(row.stock || 0) : data;
            }
        }
    );

    var skuHistoryTable = $('#sku_history_table').DataTable({
        processing: true,
        serverSide: false,
        fixedHeader: false,
        aaSorting: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 500, 1000, 2000, -1], [10, 25, 50, 500, 1000, 2000, "All"]],
        dom: '<"row margin-bottom-20 text-center no-print"<"col-sm-2"l><"col-sm-7"B><"col-sm-3"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>',
        buttons: buttons,
        ajax: {
            url: historyUrl,
            data: function (d) {
                $.extend(d, buildFilterParams());
            },
            dataSrc: function (json) {
                if (json && json.summary) {
                    var precision = typeof __quantity_precision !== 'undefined' ? __quantity_precision : 2;
                    $('#sku_history_current_stock').text(__currency_trans_from_en(Number(json.summary.current_stock || 0), false, false, precision, true));
                    $('#sku_history_unit').text(json.summary.unit || '');
                }
                return (json && json.data) ? json.data : [];
            }
        },
        columns: columns,
        fnDrawCallback: function () {
            __currency_convert_recursively($('#sku_history_table'));
        },
        footerCallback: function () {
            var api = this.api();
            var rowsData = api.rows({ search: 'applied' }).data();
            var totalIn = 0;
            var totalOut = 0;
            for (var i = 0; i < rowsData.length; i++) {
                totalIn += Number(rowsData[i].qty_in || 0);
                totalOut += Number(rowsData[i].qty_out || 0);
            }
            var precision = typeof __quantity_precision !== 'undefined' ? __quantity_precision : 2;
            $('#sku_history_footer_in').html('<strong>' + __currency_trans_from_en(totalIn, false, false, precision, true) + '</strong>');
            $('#sku_history_footer_out').html('<strong>' + __currency_trans_from_en(totalOut, false, false, precision, true) + '</strong>');
        }
    });

    $(document).on('change', '#sku_history_filter_location_id, #sku_history_filter_type, #sku_history_filter_direction', function () {
        if ($(this).attr('id') === 'sku_history_filter_location_id') {
            var selectedLocations = $('#sku_history_filter_location_id').val() || [];
            var selectedWithoutAll = selectedLocations.filter(function (value) {
                return value !== 'all';
            });
            if (selectedLocations.indexOf('all') !== -1 && selectedLocations.length > 1) {
                $('#sku_history_filter_location_id').val(selectedWithoutAll).trigger('change.select2');
            }
            if (selectedLocations.length === 0) {
                $('#sku_history_filter_location_id').val(['all']).trigger('change.select2');
            }
        }
        syncExportUrl();
        skuHistoryTable.ajax.reload();
    });

    $('#sku_history_refresh_btn').on('click', function () {
        skuHistoryTable.ajax.reload(null, false);
    });

    $('#sku_history_reset_btn').on('click', function () {
        $('#sku_history_filter_location_id').val(['all']).trigger('change.select2');
        $('#sku_history_filter_type').val(null).trigger('change.select2');
        $('#sku_history_filter_direction').val('').trigger('change.select2');
        $dateRange.val('');
        $('#sku_history_start_date').val('');
        $('#sku_history_end_date').val('');
        syncExportUrl();
        skuHistoryTable.search('').ajax.reload();
    });

    syncExportUrl();

    window.initPurchaseLotPriceModal({
        modal: '#sku-sale-price-modal',
        trigger: '.sku-quick-sale-price',
        priceField: 'unit_price',
        labels: { error: 'Unable to update unit price.' },
        saved: function () { skuHistoryTable.ajax.reload(null, false); }
    });
    window.initPurchaseLotPriceModal({
        modal: '#sku-purchase-cost-modal',
        trigger: '.sku-quick-cost',
        saved: function () { skuHistoryTable.ajax.reload(null, false); }
    });
    window.initPurchaseLotPriceModal({
        modal: '#sku-before-cost-modal',
        trigger: '.sku-before-cost',
        saved: function () { skuHistoryTable.ajax.reload(null, false); }
    });
    $(document).on('click', '.sku-quick-lot', function () {
        var button = $(this);
        $('#sku-lot-form').attr('action', button.attr('data-url'));
        $('#sku-lot-number, #sku-lot-original').val(button.attr('data-lot-number'));
        $('#sku-lot-line').text(button.attr('data-line-id'));
        $('#sku-lot-reason').val('');
        $('#sku-lot-error').hide().text('');
        $('#sku-lot-save').prop('disabled', false);
        $('#sku-lot-modal').modal('show');
    });
    $('#sku-lot-modal').on('shown.bs.modal', function () { $('#sku-lot-number').focus(); });
    $('#sku-lot-form').on('submit', function (event) {
        event.preventDefault();
        var form = $(this);
        $('#sku-lot-save').prop('disabled', true);
        $('#sku-lot-error').hide();
        $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), dataType: 'json' })
            .done(function () {
                $('#sku-lot-modal').modal('hide');
                skuHistoryTable.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                var response = xhr.responseJSON || {};
                var messages = response.errors ? [].concat.apply([], Object.values(response.errors)) : [];
                $('#sku-lot-error').text(messages.join(' ') || response.message || 'Unable to update the lot. Please try again.').show();
                $('#sku-lot-save').prop('disabled', false);
            });
    });
});
</script>
@endsection
