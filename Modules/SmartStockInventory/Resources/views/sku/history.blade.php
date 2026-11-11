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
        <thead><tr><th>Date</th><th>Location</th><th>Transaction Type</th><th>Reference</th><th>Contact</th><th>Qty In</th><th>Qty Out</th><th>Location Balance</th></tr></thead>
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
                    @foreach([max(0, $row['quantity_change']), max(0, -$row['quantity_change']), $row['stock']] as $qty)
                        <td><span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $qty }}</span></td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">No stock transactions found.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    {{ $rows->links() }}
</div></div>
@endsection
