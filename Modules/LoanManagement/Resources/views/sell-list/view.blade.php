@extends('loanmanagement::layouts.app')
@section('title', 'View Sell Transaction')
@section('content')
<section class="content-header"><h1>Sell Detail - {{ $sell['header']->invoice_no }}</h1></section>
<section class="content">
<div class="box box-primary"><div class="box-body">
<p><strong>Customer:</strong> {{ $sell['header']->customer_name }} ({{ $sell['header']->customer_phone }})</p>
<p><strong>Location:</strong> {{ $sell['header']->location_name }}</p>
<p><strong>Total:</strong> {{ number_format($sell['header']->final_total,2) }} | <strong>Paid:</strong> {{ number_format($sell['paid_amount'],2) }} | <strong>Due:</strong> {{ number_format($sell['due_amount'],2) }}</p>
<table class="table table-bordered"><thead><tr><th>Product</th><th>SKU/IMEI</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>
@foreach($sell['lines'] as $line)
<tr><td>{{ $line->product_name }}</td><td>{{ $line->variation_sku ?: $line->product_sku }}</td><td>{{ $line->quantity }}</td><td>{{ number_format($line->unit_price_inc_tax,2) }}</td><td>{{ number_format($line->quantity * $line->unit_price_inc_tax,2) }}</td></tr>
@endforeach
</tbody></table>
</div></div>
</section>
@endsection
