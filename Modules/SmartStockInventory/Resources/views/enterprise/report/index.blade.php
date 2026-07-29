@extends('smartstockinventory::layouts.master')
@section('page_title', 'Enterprise Inventory Reports')
@section('module_content')
<div class="row">
<div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-percent"></i></span><div class="info-box-content"><span class="info-box-text">Accuracy</span><span class="info-box-number">{{ $metrics['accuracy_percent'] }}%</span></div></div></div>
<div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-warning"></i></span><div class="info-box-content"><span class="info-box-text">Mismatch Items</span><span class="info-box-number">{{ $metrics['mismatch_items'] }}</span></div></div></div>
<div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-level-down"></i></span><div class="info-box-content"><span class="info-box-text">Negative Stock</span><span class="info-box-number">{{ $metrics['negative_stock'] }}</span></div></div></div>
<div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-money"></i></span><div class="info-box-content"><span class="info-box-text">Inventory Value</span><span class="info-box-number">{{ number_format($metrics['inventory_value'], 2) }}</span></div></div></div>
</div>
<div class="box box-warning">
<div class="box-header with-border"><h4 class="box-title">Audit Difference Report</h4></div>
<div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="ssi_report_table">
<thead><tr><th>Audit</th><th>SKU</th><th>Product</th><th>IMEI/Lot</th><th>Expected</th><th>Counted</th><th>Difference</th><th>Mismatch</th></tr></thead>
<tbody>
@foreach($differenceRows as $row)
<tr><td>{{ $row->audit_no }}<br><small>{{ $row->audit_name }}</small></td><td>{{ $row->sku }}</td><td>{{ $row->product_name }}</td><td>{{ $row->imei ?: $row->lot_number }}</td><td>{{ number_format((float) $row->expected_qty, 4) }}</td><td>{{ number_format((float) $row->counted_qty, 4) }}</td><td>{{ number_format((float) $row->difference_qty, 4) }}</td><td>{{ $row->mismatch_type }}</td></tr>
@endforeach
</tbody>
</table>
</div>
</div>
@endsection
@section('module_js')
<script>$(function(){ $('#ssi_report_table').DataTable({pageLength:25,order:[]}); });</script>
@endsection
