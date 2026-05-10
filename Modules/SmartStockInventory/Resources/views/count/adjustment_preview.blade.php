@extends('smartstockinventory::layouts.master')
@section('page_title', 'Adjustment Preview')
@section('module_content')
<div class="box box-warning"><div class="box-header"><h4>Session: {{ $session->name }}</h4><div class="pull-right"><a class="btn btn-success btn-sm" href="{{ route('ssi.count.enterprise.adjustment_preview', ['session'=>$session->id,'export'=>1]) }}">Export Preview</a></div></div>
<div class="box-body table-responsive"><table class="table table-bordered"><thead><tr><th>Product</th><th>SKU</th><th>Old Qty</th><th>New Qty</th><th>Difference</th><th>Stock Value Difference</th></tr></thead><tbody>
@foreach($rows as $r)<tr><td>{{ $r['product'] }}</td><td>{{ $r['sku'] }}</td><td>{{ $r['old_qty'] }}</td><td>{{ $r['new_qty'] }}</td><td>{{ $r['difference'] }}</td><td>{{ $r['stock_value_difference'] }}</td></tr>@endforeach
</tbody></table></div>
<div class="box-footer"><button class="btn btn-success">Approve Adjustment</button> <a class="btn btn-default" href="{{ route('ssi.count.enterprise') }}">Cancel</a></div>
</div>
@endsection