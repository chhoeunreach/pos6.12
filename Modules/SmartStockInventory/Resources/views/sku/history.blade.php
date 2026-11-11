@extends('smartstockinventory::layouts.master')
@section('page_title', 'SKU Transaction History')
@section('module_content')
@php
    $types = ['purchase' => 'Purchase', 'sell' => 'Sale / Production Use', 'opening_stock' => 'Opening Stock', 'purchase_transfer' => 'Transfer In', 'sell_transfer' => 'Transfer Out', 'sell_return' => 'Sale Return', 'purchase_return' => 'Purchase Return', 'stock_adjustment' => 'Stock Adjustment', 'production_purchase' => 'Production In'];
@endphp
<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">{{ $product->sku ?: $product->product_sku }} — {{ $product->product_name }}@if($product->variation_name && $product->variation_name !== 'DUMMY') ({{ $product->variation_name }})@endif</h3></div>
    <div class="box-body">
        <form method="get" action="{{ ssi_route('ssi.sku.history', ['variation' => $product->variation_id]) }}" class="row">
            <div class="col-md-3 form-group">
                <label for="history_location">Location</label>
                <select id="history_location" name="location_id" class="form-control select2">
                    <option value="">All permitted locations</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 form-group"><label for="sku_start">From</label><input id="sku_start" class="form-control" type="date" name="start_date" value="{{ request('start_date') }}"></div>
            <div class="col-md-3 form-group"><label for="sku_end">To</label><input id="sku_end" class="form-control" type="date" name="end_date" value="{{ request('end_date') }}"></div>
            <div class="col-md-3 form-group">
                <label for="history_type">Transaction Type</label>
                <select id="history_type" class="form-control" name="type">
                    <option value="">All</option>
                    @foreach($types as $type => $label)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-12 form-group">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-default" href="{{ ssi_route('ssi.sku.history', ['variation' => $product->variation_id]) }}">Reset</a>
                <a class="btn btn-default" href="{{ ssi_route('ssi.sku.index', array_filter(['search' => $product->sku ?: $product->product_sku, 'location_id' => request('location_id')])) }}">Back to SKU Management</a>
                @can('stock_inventory.export')
                    <a class="btn btn-success" href="{{ ssi_route('ssi.sku.export', array_merge(request()->only('location_id', 'start_date', 'end_date', 'type'), ['variation' => $product->variation_id])) }}"><i class="fa fa-download"></i> Export History</a>
                @endcan
            </div>
        </form>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <p>Current stock: <strong><span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $product->current_stock }}</span> {{ $product->unit }}</strong></p>
        <p class="text-muted">Each balance is the stock at that location after the movement. Date filters preserve balances from earlier transactions.</p>
    </div>
</div>
<div class="box box-primary"><div class="box-body">
    <div class="table-responsive"><table class="table table-bordered table-striped">
        <thead><tr><th>Date</th><th>Location</th><th>Transaction Type</th><th>Reference</th><th>Contact</th><th>Lots</th><th>Qty In</th><th>Qty Out</th><th>Location Balance</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td><td>{{ $row['location_name'] }}</td><td>{{ $row['type_label'] }}</td>
                    <td>
                        @php
                            $transactionPath = match ($row['type']) {
                                'sell' => '/sells/',
                                'sell_return' => '/sell-return/',
                                'purchase', 'opening_stock', 'production_purchase' => '/purchases/',
                                'purchase_return' => '/purchase-return/',
                                'stock_adjustment' => '/stock-adjustments/',
                                'sell_transfer', 'purchase_transfer' => '/stock-transfers/',
                                default => null,
                            };
                        @endphp
                        @if($transactionPath)
                            <a href="#" class="btn-modal" data-href="{{ ssi_url($transactionPath . $row['transaction_id']) }}" data-container=".view_modal">{{ $row['ref_no'] ?: '#' . $row['transaction_id'] }}</a>
                        @else
                            {{ $row['ref_no'] ?: '#' . $row['transaction_id'] }}
                        @endif
                    </td>
                    <td>{{ $row['contact_name'] ?? $row['supplier_business_name'] ?? '' }}</td>
                    <td>
                        @forelse($row['lots'] as $lot)
                            <div style="white-space:nowrap;margin-bottom:4px">
                                @if((string) $lot['lot_number'] !== '')
                                    <a href="{{ ssi_route('ssi.lot.history', ['lot' => $lot['lot_number']]) }}">{{ $lot['lot_number'] }}</a>
                                @else
                                    <span class="text-muted">No lot number</span>
                                @endif
                                @can('stock_inventory.update')
                                    @if($lot['editable'])
                                        <button type="button" class="btn btn-xs btn-default sku-quick-lot" data-url="{{ ssi_route('ssi.sku.lot.update', ['variation' => $product->variation_id, 'line' => $lot['id']]) }}" data-lot-number="{{ $lot['lot_number'] }}" data-line-id="{{ $lot['id'] }}"><i class="fa fa-pencil"></i> Quick Update</button>
                                    @endif
                                @endcan
                            </div>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    @foreach([max(0, $row['quantity_change']), max(0, -$row['quantity_change']), $row['stock']] as $qty)
                        <td><span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $qty }}</span></td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">No stock transactions found.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    {{ $rows->links() }}
</div></div>
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
@endsection

@section('module_js')
<script>
$(function () {
    $('.sku-quick-lot').on('click', function () {
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
        $.ajax({url: form.attr('action'), type: 'POST', data: form.serialize(), dataType: 'json'})
            .done(function () { window.location.reload(); })
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
