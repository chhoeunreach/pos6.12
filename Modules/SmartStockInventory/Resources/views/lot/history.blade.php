@extends('smartstockinventory::layouts.master')

@section('page_title', __('lang_v1.lot_history') . ' - ' . $lot)

@section('css')
<style>
    #lot_history_report {
        color: #111827;
    }

    #lot_history_report thead th {
        color: #163b82;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }

    #lot_history_report tbody td {
        vertical-align: middle;
        white-space: nowrap;
    }

    #lot_history_report tbody td.lot-timeline-date {
        padding-left: 26px;
        position: relative;
    }

    #lot_history_report tbody td.lot-timeline-date:before {
        background: #1d4ed8;
        border: 2px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 0 1px rgba(29, 78, 216, .35);
        content: "";
        height: 8px;
        left: 7px;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        z-index: 2;
    }

    #lot_history_report tbody td.lot-timeline-date:after {
        background: #d1d5db;
        content: "";
        left: 10px;
        position: absolute;
        top: -12px;
        bottom: -12px;
        width: 1px;
        z-index: 1;
    }

    #lot_history_report tbody tr:first-child td.lot-timeline-date:after {
        top: 50%;
    }

    #lot_history_report tbody tr:last-child td.lot-timeline-date:after {
        bottom: 50%;
    }

    #lot_history_report tbody tr.lot-row-purchase td.lot-timeline-date:before,
    #lot_history_report tbody tr.lot-row-transfer_in td.lot-timeline-date:before,
    #lot_history_report tbody tr.lot-row-sell_return td.lot-timeline-date:before {
        background: #15803d;
    }

    #lot_history_report tbody tr.lot-row-sell td.lot-timeline-date:before,
    #lot_history_report tbody tr.lot-row-adjustment td.lot-timeline-date:before {
        background: #dc2626;
    }

    #lot_history_report tbody tr.lot-row-transfer_out td.lot-timeline-date:before {
        background: #7c3aed;
    }

    .lot-status {
        font-weight: 700;
    }

    .lot-status-stock {
        color: #15803d;
    }

    .lot-status-sold {
        color: #dc2626;
    }

    .lot-status-transferred {
        color: #7c3aed;
    }

    .lot-status-adjusted {
        color: #1d4ed8;
    }
</style>
@endsection

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
                                    <th>{{ __('lang_v1.lot_number') }}</th>
                                    <th>{{ __('product.sku') }}</th>
                                    <th>{{ __('business.product') }}</th>
                                    <th>Type</th>
                                    <th>Ref No.</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>{{ __('lang_v1.qty_in') }}</th>
                                    <th>{{ __('lang_v1.qty_out') }}</th>
                                    <th>Balance</th>
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
                data: function(d) {
                    d.location_id = $('#location_id').val();
                    d.movement_type = $('#movement_type').val();
                    d.start_date = $('#lot_history_start_date').val();
                    d.end_date = $('#lot_history_end_date').val();
                }
            },
            order: [[0, 'desc']],
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
                { data: 'movement_type', name: 'movement_type' },
                { data: 'ref_no', name: 'ref_no' },
                { data: 'from_location', name: 'from_location' },
                { data: 'to_location', name: 'to_location' },
                { data: 'qty_in', name: 'qty_in', searchable: false },
                { data: 'qty_out', name: 'qty_out', searchable: false },
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
    });
</script>
@endsection
