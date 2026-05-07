@extends('layouts.app')
@section('title', 'Stock Mismatch Detail')

@section('content')
<section class="content-header">
    <h1>Stock Mismatch Detail
        <small>{{ $location->name }}</small>
    </h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $details['variation'] ?? '' }}</h3>
            <div class="box-tools pull-right">
                <a href="{{ url('stock-mismatch') }}" class="btn btn-default btn-sm">
                    <i class="fa fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-4">
                    <strong>Current Stock:</strong> {{ @num_format($mismatch->stock ?? 0) }}
                </div>
                <div class="col-sm-4">
                    <strong>Calculated Stock:</strong> {{ @num_format($mismatch->total_stock_calculated ?? 0) }}
                </div>
                <div class="col-sm-4">
                    <strong>Difference:</strong> {{ @num_format(($mismatch->stock ?? 0) - ($mismatch->total_stock_calculated ?? 0)) }}
                </div>
            </div>

            <hr>

            <h4>Calculated breakdown</h4>
            <table class="table table-bordered table-condensed">
                <tr><th>Total Opening Stock</th><td>{{ @num_format($mismatch->total_opening_stock ?? 0) }}</td></tr>
                <tr><th>Total Purchased</th><td>{{ @num_format($mismatch->total_purchased ?? 0) }}</td></tr>
                <tr><th>Total Purchase Transfer In</th><td>{{ @num_format($mismatch->total_purchase_transfered ?? 0) }}</td></tr>
                <tr><th>Total Sell Return</th><td>{{ @num_format($mismatch->total_sell_return ?? 0) }}</td></tr>
                <tr><th>Total Manufactured</th><td>{{ @num_format($mismatch->total_manufactured ?? 0) }}</td></tr>
                <tr><th>Total Sold</th><td>{{ @num_format($mismatch->total_sold ?? 0) }}</td></tr>
                <tr><th>Total Sell Transfer Out</th><td>{{ @num_format($mismatch->total_sell_transfered ?? 0) }}</td></tr>
                <tr><th>Total Adjusted</th><td>{{ @num_format($mismatch->total_adjusted ?? 0) }}</td></tr>
                <tr><th>Total Purchase Return</th><td>{{ @num_format($mismatch->total_purchase_return ?? 0) }}</td></tr>
                <tr><th>Total Combined Purchase Return</th><td>{{ @num_format($mismatch->total_combined_purchase_return ?? 0) }}</td></tr>
                <tr><th>Total Ingredients Used</th><td>{{ @num_format($mismatch->total_ingredients_used ?? 0) }}</td></tr>
            </table>

            @if(auth()->user()->can('stock_mismatch.fix'))
                <button class="btn btn-primary fix_mismatch_btn" data-variation_id="{{ $variation_id }}" data-location_id="{{ $location_id }}">
                    <i class="fa fa-wrench"></i> Fix
                </button>
            @endif

            <hr>

            <h4>Stock history</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Ref</th>
                            <th>Contact</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $h)
                            <tr>
                                <td>{{ @format_datetime($h['date'] ?? null) }}</td>
                                <td>{{ $h['type_label'] ?? $h['type'] ?? '' }}</td>
                                <td>{{ $h['ref_no'] ?? '' }}</td>
                                <td>{{ $h['supplier_business_name'] ?? $h['contact_name'] ?? '' }}</td>
                                <td>{{ @num_format($h['quantity_change'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
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
                        window.location.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                }
            });
        });
    });
</script>
@endsection
