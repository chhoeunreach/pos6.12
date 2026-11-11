@extends('smartstockinventory::layouts.master')
@section('page_title', 'SKU Management')
@section('module_content')
<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Find products by SKU</h3></div>
    <div class="box-body">
        <form method="get" action="{{ ssi_route('ssi.sku.index') }}" class="row">
            <div class="col-md-4 form-group">
                <label for="sku_search">SKU / Product</label>
                <input id="sku_search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Enter or scan SKU, or search product name">
            </div>
            <div class="col-md-3 form-group">
                <label for="sku_location">Location</label>
                <select id="sku_location" name="location_id" class="form-control select2">
                    <option value="">All permitted locations</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 form-group">
                <label for="sku_category">Category</label>
                {!! Form::select('category_id', $categories, request('category_id'), ['id' => 'sku_category', 'class' => 'form-control select2', 'placeholder' => 'All']) !!}
            </div>
            <div class="col-md-3 form-group">
                <label for="sku_brand">Brand</label>
                {!! Form::select('brand_id', $brands, request('brand_id'), ['id' => 'sku_brand', 'class' => 'form-control select2', 'placeholder' => 'All']) !!}
            </div>
            <div class="col-md-3 form-group">
                <label for="sku_stock">Stock Status</label>
                <select id="sku_stock" name="stock_status" class="form-control">
                    @foreach(['' => 'All', 'positive' => 'In stock', 'zero' => 'Zero stock', 'negative' => 'Negative stock'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) request('stock_status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 form-group" style="padding-top:25px">
                <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> Search</button>
                <a class="btn btn-default" href="{{ ssi_route('ssi.sku.index') }}">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">All Product SKUs</h3></div>
    <div class="box-body">
        <p class="text-muted">Current stock is for the selected locations. Open History to see purchases, sales, transfers, returns, adjustments, opening stock, and production movements, including products without lots.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>SKU</th><th>Product SKU</th><th>Product</th><th>Current Stock</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($products as $product)
                        @php($historyUrl = ssi_route('ssi.sku.history', array_filter(['variation' => $product->variation_id, 'location_id' => request('location_id')])))
                        <tr>
                            <td><a href="{{ $historyUrl }}">{{ $product->sku ?: $product->product_sku }}</a></td>
                            <td>{{ $product->product_sku }}</td>
                            <td>{{ $product->product_name }}@if($product->variation_name && $product->variation_name !== 'DUMMY') ({{ $product->variation_name }})@endif</td>
                            <td><span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $product->current_stock }}</span> {{ $product->unit }}</td>
                            <td><a href="{{ $historyUrl }}" class="btn btn-xs btn-info"><i class="fa fa-history"></i> History</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </div>
</div>
@endsection
