@extends('smartstockinventory::layouts.master')
@section('page_title', 'SKU Management')
@section('module_content')
@can('stock_inventory.export')<input type="hidden" id="view_export_buttons">@endcan
@component('components.filters', ['title' => __('report.filters')])
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form id="sku-filter-form" method="get" action="{{ ssi_route('ssi.sku.index') }}" class="row">
            <div class="col-md-4 form-group">
                <label for="sku_search">SKU / Product</label>
                <input id="sku_search" name="sku_search" class="form-control" value="{{ request('sku_search', is_string(request('search')) ? request('search') : '') }}" placeholder="Enter or scan SKU, or search product name">
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
            <div class="col-md-3 form-group">
                <label for="sku_subcategory">Subcategory</label>
                <select id="sku_subcategory" name="sub_category_id" class="form-control select2"><option value="">All</option></select>
            </div>
            <div class="col-md-3 form-group">
                <label for="sku_unit">Unit</label>
                {!! Form::select('unit_id', $units, request('unit_id'), ['id' => 'sku_unit', 'class' => 'form-control select2', 'placeholder' => 'All']) !!}
            </div>
            <div class="col-md-3 form-group">
                <label for="sku_status">Product Status</label>
                <select id="sku_status" name="product_status" class="form-control">
                    @foreach(['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) request('product_status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 form-group" style="padding-top:25px">
                <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> Search</button>
                <a class="btn btn-default" href="{{ ssi_route('ssi.sku.index') }}">Reset</a>
            </div>
        </form>
@endcomponent
@component('components.widget', ['title' => 'All Product SKUs'])
    @slot('tool')
        <div class="box-tools pull-right">
            @can('stock_inventory.export')<button type="button" id="sku-export-all" class="btn btn-sm btn-default"><i class="fa fa-download"></i> Export all filtered SKUs</button>@endcan
            <button type="button" id="sku-refresh" class="btn btn-sm btn-default"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
    @endslot
        <p class="text-muted">Current stock is for the selected locations. Open History to see purchases, sales, transfers, returns, adjustments, opening stock, and production movements, including products without lots.</p>
        <div class="table-responsive">
            <table id="sku-products-table" class="table table-bordered table-striped" style="width:100%">
                <thead><tr><th>SKU</th><th>Product SKU</th><th>Product</th><th>Current Stock</th><th>Unit</th><th class="sku-action">Action</th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><th colspan="3" class="text-right">Page stock</th><th id="sku-page-stock"></th><th></th><th></th></tr></tfoot>
            </table>
        </div>
@endcomponent
@endsection

@section('module_js')
<script>
$(function () {
    var form = $('#sku-filter-form');
    var subcategories = @json($subcategories);
    function setSubcategories(selected) {
        var category = $('#sku_category').val();
        var select = $('#sku_subcategory').empty().append(new Option('All', ''));
        subcategories.forEach(function (item) {
            if (!category || String(item.parent_id) === String(category)) {
                select.append(new Option(item.name, item.id, false, String(item.id) === String(selected)));
            }
        });
        select.trigger('change.select2');
    }
    setSubcategories(@json(request('sub_category_id', '')));
    $('#sku_category').on('change', function () { setSubcategories(''); });
    function filters() {
        var values = {};
        form.serializeArray().forEach(function (field) { if (field.value !== '') values[field.name] = field.value; });
        return values;
    }
    function prepareButtons(items) {
        return $.map(items || [], function (item) {
            var button = $.extend(true, {}, item);
            if (button.buttons) button.buttons = prepareButtons(button.buttons);
            if (button.extend === 'colvis') button.columns = ':not(.sku-action)';
            else if (button.extend !== 'collection') button.exportOptions = $.extend(true, {}, button.exportOptions || {}, {columns: ':visible:not(.sku-action)'});
            return button;
        });
    }
    var buttons = prepareButtons($.fn.dataTable.defaults.buttons);
    if (!@json(\Modules\SmartStockInventory\Support\SkuPermissions::allows(auth()->user(), 'export'))) {
        buttons = [{extend: 'colvis', text: LANG.col_vis, columns: ':not(.sku-action)', className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2'}];
    }
    $('#sku-export-all').on('click', function () {
        var values = filters();
        var globalSearch = table.search();
        if (globalSearch) values.table_search = globalSearch;
        window.location.href = @json(ssi_route('ssi.sku.products.export')) + '?' + $.param(values);
    });
    $('#sku-refresh').on('click', function () { table.ajax.reload(null, false); });
    var text = $.fn.dataTable.render.text();
    var table = $('#sku-products-table').DataTable({
        processing: true, serverSide: true, searching: true, fixedHeader: false,
        pageLength: 10, lengthMenu: [[10, 25, 50, 100, 200], [10, 25, 50, 100, 200]],
        dom: '<"row margin-bottom-20 text-center no-print"<"col-sm-2"l><"col-sm-7"B><"col-sm-3"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>',
        language: {search: '', searchPlaceholder: 'Search ...'},
        searchDelay: 350,
        buttons: buttons,
        ajax: {url: @json(ssi_route('ssi.sku.index')), data: function (data) {
            var current = filters();
            data.sku_search = current.sku_search || '';
            ['location_id', 'category_id', 'sub_category_id', 'brand_id', 'stock_status', 'unit_id', 'product_status'].forEach(function (name) { data[name] = current[name] || ''; });
        }},
        columns: [
            {data: 'sku', name: 'v.sub_sku', render: text},
            {data: 'product_sku', name: 'p.sku', render: text},
            {data: 'product_name', name: 'product_display', render: function (value, type, row) {
                var label = value + (row.variation_name && row.variation_name !== 'DUMMY' ? ' (' + row.variation_name + ')' : '');
                return type === 'display' ? $('<span>').text(label).html() : label;
            }},
            {data: 'current_stock', name: 'current_stock', searchable: false, render: function (value, type) {
                return type === 'display' ? __currency_trans_from_en(Number(value || 0), false, false, __currency_precision, true) : Number(value || 0);
            }},
            {data: 'unit', name: 'u.short_name', render: text, defaultContent: '', searchable: false},
            {data: 'action', className: 'sku-action', orderable: false, searchable: false}
        ],
        order: [[2, 'asc']],
        footerCallback: function (row, data) {
            var total = data.reduce(function (sum, item) { return sum + Number(item.current_stock || 0); }, 0);
            $('#sku-page-stock').text(__currency_trans_from_en(total, false, false, __currency_precision, true));
        }
    });
    table.on('xhr.dt error.dt', function () { form.find('button[type=submit]').prop('disabled', false); });
    form.on('submit', function (event) {
        event.preventDefault();
        var query = $.param(filters());
        window.history.replaceState(null, '', form.attr('action') + (query ? '?' + query : ''));
        table.ajax.reload();
    });
});
</script>
@endsection
