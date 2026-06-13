<!doctype html><html><head><meta charset="utf-8"><title>Inventory Count Sheet</title>
<style>body{font-family:Arial} table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px} th{background:#f5f5f5}</style></head>
<body onload="window.print()"><h3>Inventory Count Sheet - {{ $session->name }}</h3>
<table><thead><tr><th>SKU</th><th>Product</th><th>Variation</th><th>IMEI</th><th>Lot</th><th>System Qty</th><th>Actual Qty</th><th>Diff</th></tr></thead>
<tbody>@foreach($lines as $line)<tr><td>{{ $line->sku }}</td><td>{{ $line->product_name }}</td><td>{{ $line->variation_name }}</td><td>{{ $line->imei }}</td><td>{{ $line->lot_number }}</td><td>{{ $line->system_qty }}</td><td>{{ $line->actual_qty }}</td><td>{{ $line->difference_qty }}</td></tr>@endforeach</tbody>
</table></body></html>
