@extends('smartstockinventory::layouts.master')
@section('page_title', 'Stock Movement History')
@section('module_content')
<div class="row">
    <div class="col-md-12">
        @component('components.widget', ['title' => 'Filters'])
        <form method="get" class="row">
            @if(request()->filled('variation_id'))<input type="hidden" name="variation_id" value="{{ request('variation_id') }}">@endif
            @if(request()->filled('product'))<input type="hidden" name="product" value="{{ request('product') }}">@endif
            @if(request()->filled('product_id'))<input type="hidden" name="product_id" value="{{ request('product_id') }}">@endif
            @if(request()->filled('mode'))<input type="hidden" name="mode" value="{{ request('mode') }}">@endif
            <div class="col-md-5">
                <div class="form-group">
                    {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                    <select name="location_id" class="form-control select2" style="width:100%;">
                        <option value="">All Locations</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ (int)($selectedLocationId ?? 0) === (int)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-5">
                <div class="form-group">
                    {!! Form::label('sku', 'SKU:') !!}
                    <select name="sku" class="form-control select2" style="width:100%;" data-placeholder="Type to search SKU">
                        <option value=""></option>
                        @if(!empty($selectedSku))
                            <option value="{{ $selectedSku }}" selected>{{ $selectedSku }}</option>
                        @endif
                    </select>
                </div>
            </div>
        </form>
        @endcomponent
    </div>
</div>
@if(!empty($stockSummary))
<div class="box box-default">
    <div class="box-body">
        <h3 style="margin-top:0; margin-bottom:14px;">{{ $stockSummary['variation'] ?? 'Stock Summary' }}</h3>
        <div class="row">
            <div class="col-md-4">
                <h4><strong>Quantities In</strong></h4>
                <table class="table table-condensed">
                    <tr><th>Total Purchase</th><td class="text-right">{{ number_format((float) ($stockSummary['total_purchase'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                    <tr><th>Opening Stock</th><td class="text-right">{{ number_format((float) ($stockSummary['total_opening_stock'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                    <tr><th>Total Sell Return</th><td class="text-right">{{ number_format((float) ($stockSummary['total_sell_return'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                    <tr><th>Stock Transfers (In)</th><td class="text-right">{{ number_format((float) ($stockSummary['total_purchase_transfer'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h4><strong>Quantities Out</strong></h4>
                <table class="table table-condensed">
                    <tr><th>Total Sold</th><td class="text-right">{{ number_format((float) ($stockSummary['total_sold'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                    <tr><th>Total Stock Adjustment</th><td class="text-right">{{ number_format((float) ($stockSummary['total_adjusted'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                    <tr><th>Total Purchase Return</th><td class="text-right">{{ number_format((float) ($stockSummary['total_purchase_return'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                    <tr><th>Stock Transfers (Out)</th><td class="text-right">{{ number_format((float) ($stockSummary['total_sell_transfer'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h4><strong>Totals</strong></h4>
                <table class="table table-condensed">
                    <tr><th>Current stock</th><td class="text-right">{{ number_format((float) ($stockSummary['current_stock'] ?? 0), 2) }} {{ $stockSummary['unit'] ?? '' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
<div class="box box-primary"><div class="box-body table-responsive">
<table class="table table-bordered table-striped datatable" id="ssi_movement_table">
<thead><tr><th>Date</th><th>Reference No</th><th>Action</th><th>Transaction Type</th><th>Location</th><th>IMEI</th><th>Lot</th><th>Qty In</th><th>Qty Out</th><th>Balance</th><th>Created By</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $row->movement_date }}</td>
    <td>{{ $row->reference_no }}</td>
    <td>
        @php($viewUrl = null)
        @php($editUrl = null)
        @php($voidUrl = null)
        @php($restoreUrl = null)
        @php($txId = (int)($row->action_transaction_id ?? $row->transaction_id ?? 0))
        @php($txType = (string)($row->transaction_type ?? ''))
        @php($txStatus = (string)($row->transaction_status ?? ''))
        @php($isPendingTransfer = $txType === 'sell_transfer_pending')
        @if($txId > 0)
            @php($voidUrl = route('ssi.movement.void', ['transaction' => $txId]))
            @php($restoreUrl = route('ssi.movement.restore', ['transaction' => $txId]))
            @php($editUrl = route('ssi.movement.edit_modal', ['transaction' => $txId]))
            @if(in_array($txType, ['sell','production_sell'], true))
                @php($viewUrl = url('/sells/' . $txId))
            @elseif($txType === 'sell_return')
                @php($viewUrl = url('/sell-return/' . $txId))
            @elseif(in_array($txType, ['purchase','opening_stock','production_purchase'], true))
                @php($viewUrl = url('/purchases/' . $txId))
            @elseif($txType === 'purchase_return')
                @php($viewUrl = url('/purchase-return/' . $txId))
            @elseif($txType === 'stock_adjustment')
                @php($viewUrl = url('/stock-adjustments/' . $txId))
            @elseif(in_array($txType, ['sell_transfer','purchase_transfer','sell_transfer_pending'], true))
                @php($viewUrl = url('/stock-transfers/' . $txId))
            @endif
        @endif

        @if($viewUrl || $editUrl || $voidUrl || $restoreUrl)
            <div class="btn-group">
                <button type="button" class="btn btn-xs btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Actions <span class="caret"></span></button>
                <ul class="dropdown-menu dropdown-menu-right" role="menu">
                    @if($viewUrl)
                        <li><a href="#" class="btn-modal" data-href="{{ $viewUrl }}" data-container=".view_modal"><i class="fa fa-eye"></i> View</a></li>
                    @endif
                    @if($editUrl)
                        <li><a href="#" class="ssi-open-edit-modal" data-url="{{ $editUrl }}"><i class="fa fa-pencil"></i> Edit</a></li>
                    @endif

                    @if($isPendingTransfer)
                        <li class="divider"></li>
                        <li><a href="#" class="ssi-post-action" data-form-id="status_completed_{{ $loop->index }}" data-confirm-msg="Mark transfer as completed?"><i class="fa fa-check text-success"></i> Set Completed</a></li>
                        <li><a href="#" class="ssi-post-action" data-form-id="status_in_transit_{{ $loop->index }}" data-confirm-msg="Mark transfer as in transit?"><i class="fa fa-truck text-warning"></i> Set In Transit</a></li>
                        <li><a href="#" class="ssi-post-action" data-form-id="status_pending_{{ $loop->index }}" data-confirm-msg="Mark transfer as pending?"><i class="fa fa-clock-o text-info"></i> Set Pending</a></li>
                        <li class="divider"></li>
                    @else
                        @if($voidUrl && $txStatus !== 'cancelled')
                            <li><a href="#" class="ssi-post-action" data-form-id="void_tx_{{ $loop->index }}" data-confirm-msg="Are you sure you want to void this transaction?"><i class="fa fa-ban"></i> Void</a></li>
                        @endif
                        @if($restoreUrl && $txStatus === 'cancelled')
                            <li><a href="#" class="ssi-post-action" data-form-id="restore_tx_{{ $loop->index }}" data-confirm-msg="Are you sure you want to restore this transaction?"><i class="fa fa-undo"></i> Restore</a></li>
                        @endif
                    @endif
                </ul>
            </div>

            @if($voidUrl)
                <form id="void_tx_{{ $loop->index }}" method="POST" action="{{ $voidUrl }}" style="display:none;">@csrf</form>
            @endif
            @if($restoreUrl)
                <form id="restore_tx_{{ $loop->index }}" method="POST" action="{{ $restoreUrl }}" style="display:none;">@csrf</form>
            @endif

            @if($isPendingTransfer)
                <form id="status_completed_{{ $loop->index }}" method="POST" action="{{ url('/stock-transfers/update-status/' . $txId) }}" style="display:none;">@csrf<input type="hidden" name="status" value="completed"></form>
                <form id="status_in_transit_{{ $loop->index }}" method="POST" action="{{ url('/stock-transfers/update-status/' . $txId) }}" style="display:none;">@csrf<input type="hidden" name="status" value="in_transit"></form>
                <form id="status_pending_{{ $loop->index }}" method="POST" action="{{ url('/stock-transfers/update-status/' . $txId) }}" style="display:none;">@csrf<input type="hidden" name="status" value="pending"></form>
            @endif
        @else
            <span class="text-muted">-</span>
        @endif
    </td>
    <td>{{ $row->transaction_type }}</td>
    <td>{{ $row->location_name }}</td>
    <td>{{ $row->imei }}</td>
    <td>{{ $row->lot_number }}</td>
    <td>{{ $row->qty_in }}</td>
    <td>{{ $row->qty_out }}</td>
    <td>{{ $row->balance_qty }}</td>
    <td>{{ $row->created_by_name }}</td>
</tr>
@empty
<tr><td colspan="11" class="text-center">No movement found</td></tr>
@endforelse
</tbody>
</table>{{ $rows->links() }}</div></div>
<div class="modal fade view_modal" tabindex="-1" role="dialog" aria-hidden="true"></div>
<div class="modal fade" id="ssiEditModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="width:95%; max-width:1400px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Edit Transaction</h4>
            </div>
            <div class="modal-body" style="padding:0;">
                <iframe id="ssiEditFrame" src="about:blank" style="width:100%; height:75vh; border:0;"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@section('module_js')
<script>
$(function(){
    $('select[name=\"location_id\"].select2').select2({
        width: '100%',
        placeholder: 'Search location'
    });
    var filterForm = $('select[name="location_id"]').closest('form');
    var autoSubmitTimer = null;
    function clearLockedParamsForFlexibleFilter() {
        filterForm.find('input[name="variation_id"], input[name="product"], input[name="product_id"], input[name="mode"]').remove();
    }
    function autoApplyFilters(clearLockedParams) {
        if (clearLockedParams) {
            clearLockedParamsForFlexibleFilter();
        }
        clearTimeout(autoSubmitTimer);
        autoSubmitTimer = setTimeout(function() {
            if (filterForm.length) {
                filterForm.trigger('submit');
            }
        }, 120);
    }

    var skuSelect = $('select[name=\"sku\"].select2');
    skuSelect.select2({
        width: '100%',
        allowClear: true,
        placeholder: skuSelect.data('placeholder') || 'Type to search SKU',
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("ssi.movement.search_sku") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term || '',
                    location_id: $('select[name=\"location_id\"]').val() || ''
                };
            },
            processResults: function(data) {
                return {
                    results: (data && data.results) ? data.results : []
                };
            }
        },
        templateSelection: function(data) {
            if (!data || !data.id) {
                return data.text || '';
            }
            return data.id;
        }
    });
    $('select[name="location_id"]').on('change', function() {
        autoApplyFilters(true);
    });
    skuSelect.on('select2:select select2:clear', function() {
        autoApplyFilters(true);
    });

    $(document).on('click', '.ssi-post-action', function(e){
        e.preventDefault();
        var formId = $(this).data('form-id');
        var confirmMsg = $(this).data('confirm-msg') || 'Are you sure?';
        if (!formId) return;
        if (confirm(confirmMsg)) {
            $('#' + formId).submit();
        }
    });
    $(document).on('click', '.ssi-open-edit-modal', function(e){
        e.preventDefault();
        var url = $(this).data('url');
        if (!url) return;
        $('#ssiEditFrame').attr('src', url);
        $('#ssiEditModal').modal('show');
    });
    $('#ssiEditModal').on('hidden.bs.modal', function(){
        $('#ssiEditFrame').attr('src', 'about:blank');
    });
});
</script>
@endsection
