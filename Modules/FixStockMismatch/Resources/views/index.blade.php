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
                <div class="col-sm-12">
                    <div class="alert alert-info" style="margin-bottom: 15px;">
                        <i class="fa fa-info-circle"></i>
                        Select <strong>Business Location</strong> or <strong>SKU/Product</strong> first, then click <strong>Check Mismatch</strong>.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('location_id', __('business.location') . ':') !!}
                        {!! Form::select('location_id', $locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'id' => 'location_id']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('variation_id', __('product.product') . ' / ' . __('product.sku') . ':') !!}
                        {!! Form::select('variation_id', [], null, ['class' => 'form-control', 'id' => 'variation_id', 'style' => 'width: 100%;']) !!}
                        <input type="hidden" id="product_id" value="">
                        <input type="hidden" id="sku" value="">
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
            </div>

            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('mismatch_type', 'Mismatch Type:') !!}
                        {!! Form::select('mismatch_type', ['mismatch' => 'Mismatch Only', 'matched' => 'Matched Only', 'all' => 'All'], 'mismatch', ['class' => 'form-control select2', 'id' => 'mismatch_type']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('limit', 'Limit:') !!}
                        {!! Form::select('limit', [50 => 50, 100 => 100, 200 => 200], 50, ['class' => 'form-control select2', 'id' => 'limit']) !!}
                    </div>
                </div>
                <div class="col-sm-6" style="padding-top: 25px;">
                    <button type="button" class="btn btn-primary" id="check_mismatch_btn">
                        <i class="fa fa-search"></i> Check Mismatch
                    </button>
                    <button type="button" class="btn btn-default" id="reset_filters_btn">
                        <i class="fa fa-refresh"></i> Reset
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="stock_mismatch_table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Location</th>
                            <th>System Qty</th>
                            <th>Calculated Qty</th>
                            <th>Difference</th>
                            <th>Mismatch Type</th>
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

        var should_load = false;

        $('#variation_id').select2({
            ajax: {
                url: "{{ url('stock-mismatch/products/search') }}",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { term: params.term };
                },
                processResults: function(data) {
                    return data;
                },
                cache: true
            },
            placeholder: "Search by SKU / Product",
            allowClear: true,
            minimumInputLength: 2,
            width: 'resolve'
        }).on('select2:select', function(e) {
            var data = e.params.data || {};
            $('#product_id').val(data.product_id || '');
            $('#sku').val(data.sku || '');
        }).on('select2:clear', function() {
            $('#product_id').val('');
            $('#sku').val('');
        });

        var table = $('#stock_mismatch_table').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            pageLength: parseInt($('#limit').val() || 50),
            ajax: {
                url: "{{ url('stock-mismatch/data') }}",
                dataSrc: function(json) {
                    if (json && json.warning) {
                        toastr.warning(json.warning);
                    }
                    return json.data || [];
                },
                data: function(d) {
                    d.should_load = should_load ? 1 : 0;
                    d.location_id = $('#location_id').val();
                    d.product_id = $('#product_id').val();
                    d.variation_id = $('#variation_id').val();
                    d.sku = $('#sku').val();
                    d.category_id = $('#category_id').val();
                    d.brand_id = $('#brand_id').val();
                    d.mismatch_type = $('#mismatch_type').val();
                    d.limit = $('#limit').val();
                }
            },
            columns: [
                {data: 'product', name: 'product_name', orderable: false},
                {data: 'sku_display', name: 'sub_sku', orderable: false},
                {data: 'business_location', name: 'business_location'},
                {data: 'current_stock', name: 'stock', searchable: false},
                {data: 'calculated_stock', name: 'total_stock_calculated', searchable: false},
                {data: 'difference', name: 'difference', searchable: false},
                {data: 'mismatch_type', name: 'mismatch_type', orderable: false, searchable: false},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        $('#check_mismatch_btn').click(function() {
            var location_id = $('#location_id').val();
            var variation_id = $('#variation_id').val();

            if (!location_id && !variation_id) {
                toastr.warning('Please select Location or SKU/Product first.');
                return;
            }

            should_load = true;
            table.page.len(parseInt($('#limit').val() || 50));
            table.ajax.reload();
        });

        $('#reset_filters_btn').click(function() {
            should_load = false;
            $('#location_id').val(null).trigger('change');
            $('#variation_id').val(null).trigger('change');
            $('#category_id').val(null).trigger('change');
            $('#brand_id').val(null).trigger('change');
            $('#mismatch_type').val('mismatch').trigger('change');
            $('#limit').val(50).trigger('change');
            $('#product_id').val('');
            $('#sku').val('');
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
                            table.ajax.reload(null, false);
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });
        });

        $('#fix_all_btn').click(function() {
            var location_id = $('#location_id').val();
            var variation_id = $('#variation_id').val();
            if (!location_id && !variation_id) {
                toastr.warning('Please select Location or SKU/Product first.');
                return;
            }

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
                        variation_id: $('#variation_id').val(),
                        category_id: $('#category_id').val(),
                        brand_id: $('#brand_id').val()
                    },
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            table.ajax.reload(null, false);
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
