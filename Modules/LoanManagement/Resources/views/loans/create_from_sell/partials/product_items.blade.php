<div class="box box-solid">
    <div class="box-header"><h3 class="box-title">Product Items</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>Product</th><th>SKU</th><th>IMEI/Serial/Lot</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($sell['products'] as $p)
                <tr>
                    <td>{{ $p->product_name_snapshot }}</td>
                    <td>{{ $p->sku_snapshot }}</td>
                    <td>{{ $p->imei_snapshot }}</td>
                    <td>{{ $p->quantity }}</td>
                    <td>{{ number_format($p->unit_price_inc_tax,2) }}</td>
                    <td>{{ number_format($p->line_total,2) }}</td>
                    <td><span class="label label-success">Ready</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
