@extends('smartstockinventory::layouts.master')
@section('page_title', 'Enterprise Mobile Scanner')
@section('module_content')
<style>
.ssi-scan-wrap{max-width:680px;margin:auto}.ssi-scan-wrap input,.ssi-scan-wrap .btn{height:52px;font-size:18px}.ssi-scan-grid{display:grid;grid-template-columns:1fr 120px;gap:8px}.ssi-location-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}.ssi-result{margin-top:12px;border:1px solid #d2d6de;background:#fff;padding:12px}.ssi-camera{display:none;margin-top:10px;background:#111}.ssi-camera video{width:100%;max-height:360px}@media(max-width:520px){.ssi-scan-grid,.ssi-location-grid{grid-template-columns:1fr}}
</style>
<div class="ssi-scan-wrap">
<div class="box box-primary">
<div class="box-header with-border"><h4 class="box-title">{{ $audit->audit_no }} - {{ $audit->name }}</h4></div>
<div class="box-body">
<div class="ssi-scan-grid"><input id="scan_value" class="form-control" placeholder="Scan IMEI / Serial / Barcode" autofocus><input id="quantity" class="form-control" type="number" step="0.0001" value="1"></div>
<div class="ssi-location-grid">
<input id="warehouse" class="form-control" placeholder="Warehouse">
<input id="zone" class="form-control" placeholder="Zone">
<input id="rack" class="form-control" placeholder="Rack">
<input id="shelf" class="form-control" placeholder="Shelf">
<input id="bin" class="form-control" placeholder="Bin">
<button id="save_btn" class="btn btn-success"><i class="fa fa-check"></i> Save Scan</button>
</div>
<button id="camera_btn" class="btn btn-default btn-block" style="margin-top:8px;"><i class="fa fa-camera"></i> Camera Scan</button>
<div id="camera_box" class="ssi-camera"></div>
<div id="result" class="ssi-result text-muted">Ready.</div>
</div>
</div>
</div>
@endsection
@section('module_js')
<script>
(function(){
const endpoint='{{ ssi_route('ssi.enterprise.scanner.scan', $audit->id) }}';
const token='{{ csrf_token() }}';
const scan=document.getElementById('scan_value');
const result=document.getElementById('result');
function val(id){return document.getElementById(id).value;}
function save(){
 const code=scan.value.trim();if(!code){scan.focus();return;}
 result.textContent='Saving scan...';
 $.post(endpoint,{_token:token,scan_value:code,quantity:val('quantity')||1,warehouse:val('warehouse'),zone:val('zone'),rack:val('rack'),shelf:val('shelf'),bin:val('bin')},function(resp){
   const item=resp.data.item;
   result.innerHTML='<strong>Saved:</strong> '+(item.product_name||item.sku||code)+'<br>Expected: '+Number(item.expected_qty||0).toFixed(4)+' | Counted: '+Number(item.counted_qty||0).toFixed(4)+' | Difference: '+Number(item.difference_qty||0).toFixed(4);
   scan.value='';scan.focus();
 }).fail(function(xhr){result.textContent=(xhr.responseJSON&&xhr.responseJSON.message)||'Scan failed';});
}
document.getElementById('save_btn').addEventListener('click',save);
scan.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();save();}});
document.getElementById('camera_btn').addEventListener('click',async function(){
 if(!('BarcodeDetector' in window)){alert('Camera scanner not supported on this browser');return;}
 const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
 const box=document.getElementById('camera_box');box.style.display='block';box.innerHTML='';
 const video=document.createElement('video');video.srcObject=stream;box.appendChild(video);await video.play();
 const detector=new BarcodeDetector({formats:['qr_code','ean_13','code_128']});
 const loop=async()=>{const codes=await detector.detect(video);if(codes[0]){scan.value=codes[0].rawValue;stream.getTracks().forEach(t=>t.stop());box.style.display='none';box.innerHTML='';save();return;} requestAnimationFrame(loop);};loop();
});
})();
</script>
@endsection
