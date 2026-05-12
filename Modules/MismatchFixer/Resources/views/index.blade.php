@extends('layouts.app')
@section('title', 'Mismatch Detector')
@section('content')
<section class="content-header"><h1>Mismatch Detector</h1></section>
<section class="content">
<div class="box box-solid"><div class="box-body">
<form id="scan_form" class="row">
@csrf
<div class="col-md-2"><label>Location</label><select name="location_id" class="form-control"><option value="">All</option>@foreach($locations as $id=>$name)<option value="{{$id}}">{{$name}}</option>@endforeach</select></div>
<div class="col-md-2"><label>Product</label><select name="product_id" class="form-control"><option value="">All</option>@foreach($products as $id=>$name)<option value="{{$id}}">{{$name}}</option>@endforeach</select></div>
<div class="col-md-2"><label>Variation</label><input type="number" name="variation_id" class="form-control"></div>
<div class="col-md-2"><label>SKU</label><input type="text" name="sku" class="form-control"></div>
<div class="col-md-2"><label>Date From</label><input type="date" name="date_from" class="form-control"></div>
<div class="col-md-2"><label>Date To</label><input type="date" name="date_to" class="form-control"></div>
<div class="col-md-2"><label>Transaction Type</label><select name="transaction_type" class="form-control"><option value="">All</option><option>purchase_transfer</option><option>sell_transfer</option><option>purchase</option><option>opening_stock</option></select></div>
<div class="col-md-2"><label>Mismatch Type</label><select name="mismatch_type" class="form-control"><option value="">All</option><option value="mismatch">Mismatch</option><option value="fake_sold">Fake Sold</option><option value="broken_transfer">Broken Transfer</option></select></div>
<div class="col-md-2" style="margin-top:24px"><button class="btn btn-primary" type="submit">Scan</button> <button id="bulk_fix" type="button" class="btn btn-danger">Bulk Fix Selected</button></div>
</form>
</div></div>
<div class="box"><div class="box-body">
<table id="mismatch_table" class="table table-bordered table-striped" style="width:100%">
<thead><tr>
<th><input type="checkbox" id="check_all"></th><th>Product name</th><th>SKU</th><th>Variation ID</th><th>Location</th><th>Purchase Line ID</th><th>Transaction ID</th><th>Type</th><th>Status</th><th>Quantity</th><th>Quantity Sold</th><th>Quantity Adjusted</th><th>Quantity Returned</th><th>Available Quantity</th><th>Calculated Available</th><th>Difference</th><th>Problem Type</th><th>Action</th>
</tr></thead>
</table>
</div></div>
</section>
@endsection
@section('javascript')
<script>
(function(){
var table=$('#mismatch_table').DataTable({processing:true,serverSide:true,searching:false,dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print'],ajax:{url:'{{ route("mismatch-fixer.scan") }}',type:'POST',data:function(d){return $.extend(d,$('#scan_form').serializeObject ? $('#scan_form').serializeObject() : $('#scan_form').serializeArray().reduce((a,x)=>{a[x.name]=x.value;return a;},{}));}},columns:[{data:'purchase_line_id',render:(d)=>'<input type="checkbox" class="row_check" value="'+d+'">',orderable:false,searchable:false},{data:'product_name'},{data:'sku'},{data:'variation_id'},{data:'location'},{data:'purchase_line_id'},{data:'transaction_id'},{data:'type'},{data:'status'},{data:'quantity'},{data:'quantity_sold'},{data:'quantity_adjusted'},{data:'quantity_returned'},{data:'available_quantity'},{data:'calculated_available'},{data:'difference'},{data:'problem_type'},{data:'action',orderable:false,searchable:false}]});
$('#scan_form').on('submit',function(e){e.preventDefault();table.ajax.reload();});
$('#check_all').on('change',function(){$('.row_check').prop('checked',this.checked);});
$(document).on('click','.js-fix-row',function(){ if(!confirm('Fix this selected row?')) return; $.post('/mismatch-fixer/fix/'+$(this).data('id'), {_token:'{{ csrf_token() }}', reason:'manual single fix'}, function(r){alert(r.msg); table.ajax.reload();});});
$('#bulk_fix').on('click',function(){ var ids=$('.row_check:checked').map(function(){return this.value}).get(); if(ids.length===0){alert('Select rows first');return;} if(ids.length>100){alert('Max 100 rows');return;} if(!confirm('Confirm bulk fix for selected rows?')) return; $.post('{{ route("mismatch-fixer.bulk-fix") }}',{_token:'{{ csrf_token() }}',purchase_line_ids:ids,reason:'manual bulk fix'},function(r){alert(r.msg);table.ajax.reload();});});
})();
</script>
@endsection
