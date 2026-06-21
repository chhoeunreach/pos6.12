@extends('smartstockinventory::layouts.master')
@section('page_title', 'Lot Management')
@section('module_content')

<div class="box box-default" id="lot_filter_box">
    <div class="box-header with-border">
        <h3 class="box-title">{{ __('report.filters') }}</h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
        </div>
    </div>
    <div class="box-body">
        <form id="lot_filter_form" class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('purchase.business_location') }}:</label>
                    {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'location_id', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('category.category') }}:</label>
                    {!! Form::select('category_id', $categories, null, ['placeholder' => __('messages.all'), 'class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'category_id']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('product.sub_category') }}:</label>
                    {!! Form::select('sub_category_id', [], null, ['placeholder' => __('messages.all'), 'class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sub_category_id']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('product.brand') }}:</label>
                    {!! Form::select('brand_id', $brands, null, ['placeholder' => __('messages.all'), 'class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'brand_id']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Stock Status:</label>
                    <select class="form-control select2" name="stock_status" id="stock_status" style="width:100%;">
                        <option value="">All</option>
                        <option value="positive">In Stock</option>
                        <option value="negative">Out of Stock</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="lot_table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>@lang('lang_v1.lot_number')</th>
                        <th>Expiry Date</th>
                        <th>Current Stock</th>
                        <th>Total Sold</th>
                        <th>Total Adjusted</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr class="bg-gray font-17 text-center footer-total">
                        <td colspan="4"><strong>Total:</strong></td>
                        <td id="footer_total_stock"></td>
                        <td id="footer_total_sold"></td>
                        <td id="footer_total_adjusted"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endcomponent
    </div>
</div>
@endsection

@section('module_js')
<script>
    $(function() {
        $('.select2').select2();

        $('#category_id').change(function() {
            get_sub_categories();
        });

        var lot_table = $('#lot_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            ajax: {
                url: '{{ ssi_route("ssi.lot.index") }}',
                data: function(d) {
                    d.location_id = $('#location_id').val();
                    d.category_id = $('#category_id').val();
                    d.sub_category_id = $('#sub_category_id').val();
                    d.brand_id = $('#brand_id').val();
                    d.stock_status = $('#stock_status').val();
                }
            },
            columns: [
                { data: 'sku', name: 'v.sub_sku' },
                { data: 'product_display', name: 'product_display', orderable: false },
                { data: 'lot_number', name: 'pl.lot_number' },
                { data: 'exp_date', name: 'pl.exp_date' },
                { data: 'stock', name: 'stock', searchable: false },
                { data: 'total_sold', name: 'total_sold', searchable: false },
                { data: 'total_adjusted', name: 'total_adjusted', searchable: false },
                { data: 'action', name: 'action', searchable: false, orderable: false },
            ],
            order: [[2, 'asc']],
            footerCallback: function(row, data, start, end, display) {
                var totalStock = 0, totalSold = 0, totalAdjusted = 0;
                for (var r in data) {
                    totalStock += $(data[r].stock).data('orig-value') ? parseFloat($(data[r].stock).data('orig-value')) : 0;
                    totalSold += $(data[r].total_sold).data('orig-value') ? parseFloat($(data[r].total_sold).data('orig-value')) : 0;
                    totalAdjusted += $(data[r].total_adjusted).data('orig-value') ? parseFloat($(data[r].total_adjusted).data('orig-value')) : 0;
                }
                $('#footer_total_stock').html(__currency_trans_from_en(totalStock, false));
                $('#footer_total_sold').html(__currency_trans_from_en(totalSold, false));
                $('#footer_total_adjusted').html(__currency_trans_from_en(totalAdjusted, false));
                __currency_convert_recursively($('#lot_table'));
            }
        });

        $('#apply_filter').click(function() {
            lot_table.ajax.reload();
        });

        $('#location_id, #category_id, #sub_category_id, #brand_id, #stock_status').change(function() {
            lot_table.ajax.reload();
        });


    });
</script>
@endsection
