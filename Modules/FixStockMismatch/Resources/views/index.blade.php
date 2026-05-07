@extends('layouts.app')
@section('title', 'Fix Stock Mismatch')

@section('content')
<section class="content-header">
    <h1>Fix Stock Mismatch</h1>
</section>

<section class="content">
    <div class="alert alert-warning">
        <i class="fa fa-warning"></i>
        <strong>Please backup database before fixing stock mismatch.</strong>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Stock Mismatch Scanner</h3>
            @if(!empty($is_admin) && auth()->user()->can('stock_mismatch.fix'))
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-danger btn-sm" id="fix_all_btn">
                        <i class="fa fa-wrench"></i> Fix All
                    </button>
                </div>
            @endif
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('location_id', __('business.location') . ':') !!}
                        {!! Form::select('location_id', $locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'id' => 'location_id']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('product_id', __('product.product') . ':') !!}
                        {!! Form::select('product_id', $products, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'id' => 'product_id']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('category_id', __('category.category') . ':') !!}
                        {!! Form::select('category_id', $categories, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'id' => 'category_id']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('brand_id', __('brand.brand') . ':') !!}
                        {!! Form::select('brand_id', $brands, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'id' => 'brand_id']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group" style="margin-top: 25px;">
                        <label>
                            <input type="checkbox" id="only_mismatch" checked> Show only mismatch
                        </label>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="stock_mismatch_table">
                    <thead>
                        <tr>
                            <th>Business Location</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Variation</th>
                            <th>Current Stock</th>
                            <th>Calculated Stock</th>
                            <th>Difference</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('.select2').select2();

        var table = $('#stock_mismatch_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ url('stock-mismatch/data') }}",
                data: function(d) {
                    d.location_id = $('#location_id').val();
                    d.product_id = $('#product_id').val();
                    d.category_id = $('#category_id').val();
                    d.brand_id = $('#brand_id').val();
                    d.only_mismatch = $('#only_mismatch').is(':checked') ? 1 : 0;
                }
            },
            columns: [
                {data: 'business_location', name: 'business_location'},
                {data: 'product_name', name: 'product_name'},
                {data: 'sku', name: 'sku'},
                {data: 'variation', name: 'variation'},
                {data: 'current_stock', name: 'stock', searchable: false},
                {data: 'calculated_stock', name: 'total_stock_calculated', searchable: false},
                {data: 'difference', name: 'difference', searchable: false},
                {data: 'status', name: 'status', orderable: false, searchable: false},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        $('#location_id, #product_id, #category_id, #brand_id, #only_mismatch').change(function() {
            table.ajax.reload();
        });

        $(document).on('click', '.fix_mismatch_btn', function() {
            var variation_id = $(this).data('variation_id');
            var location_id = $(this).data('location_id');

            swal({
                title: "Fix stock mismatch?",
                text: "This will update qty_available to the calculated stock. Please ensure you have a backup.",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then(function(willFix) {
                if (!willFix) return;

                $.ajax({
                    method: 'POST',
                    url: "{{ url('stock-mismatch/fix') }}",
                    dataType: 'json',
                    data: {
                        _token: "{{ csrf_token() }}",
                        variation_id: variation_id,
                        location_id: location_id
                    },
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });
        });

        $('#fix_all_btn').click(function() {
            swal({
                title: "Fix all mismatches?",
                text: "This will update qty_available for all mismatched rows (based on filters). Please backup first.",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then(function(willFix) {
                if (!willFix) return;

                $.ajax({
                    method: 'POST',
                    url: "{{ url('stock-mismatch/fix-all') }}",
                    dataType: 'json',
                    data: {
                        _token: "{{ csrf_token() }}",
                        location_id: $('#location_id').val(),
                        product_id: $('#product_id').val(),
                        category_id: $('#category_id').val(),
                        brand_id: $('#brand_id').val()
                    },
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });
        });
    });
</script>
@endsection

