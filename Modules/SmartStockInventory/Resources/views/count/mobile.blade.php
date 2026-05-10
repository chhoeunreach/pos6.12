@extends('smartstockinventory::layouts.master')
@section('page_title', 'Mobile Count Mode')
@section('module_content')
<style>
.mobile-wrap{max-width:620px;margin:auto}.mobile-wrap .btn,.mobile-wrap input{font-size:20px;height:52px}.scan-box{display:flex;gap:8px}
</style>
<div class="mobile-wrap">
<div class="box box-primary"><div class="box-body">
<h4>Session: {{ $session->name }}</h4>
<div class="scan-box">
<input id="scan_input" class="form-control" placeholder="Scan SKU/IMEI/QR" autofocus>
<button id="scan_btn" class="btn btn-primary">Scan</button>
</div>
<div style="margin-top:8px;"><input id="qty_input" type="number" step="0.0001" class="form-control" placeholder="Qty"></div>
<div style="margin-top:8px;"><button id="save_btn" class="btn btn-success btn-block">Save Count</button></div>
<div style="margin-top:8px;"><button id="camera_btn" class="btn btn-default btn-block">Camera Scan</button></div>
<small>Offline draft auto-saved to local storage.</small>
</div></div>
</div>
@endsection
@section('module_js')
<script>
(function(){
const key='ssi_mobile_draft_{{ $session->id }}';
const scanEl=document.getElementById('scan_input');const qtyEl=document.getElementById('qty_input');
const draft=JSON.parse(localStorage.getItem(key)||'{}');if(draft.scan){scanEl.value=draft.scan;}if(draft.qty){qtyEl.value=draft.qty;}
function saveDraft(){localStorage.setItem(key,JSON.stringify({scan:scanEl.value,qty:qtyEl.value,t:Date.now()}));}
scanEl.addEventListener('input',saveDraft);qtyEl.addEventListener('input',saveDraft);
document.getElementById('save_btn').addEventListener('click',function(){
  $.post('{{ route('ssi.count.enterprise.line',$session->id) }}',{_token:'{{ csrf_token() }}',sku:scanEl.value,imei:scanEl.value,actual_qty:qtyEl.value||1,system_qty:0,product_name:scanEl.value,variation_name:'',remark:'mobile_count'},function(resp){
    if(resp.success){ if(window.Audio){new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEA').play().catch(()=>{});} scanEl.value='';qtyEl.value='';saveDraft();scanEl.focus(); }
  });
});
// USB scanner support (keyboard wedge)
let buffer='';let timer=null;document.addEventListener('keydown',function(e){if(e.key==='Enter'){if(buffer.length>2){scanEl.value=buffer;buffer='';saveDraft();}return;} if(e.key.length===1){buffer+=e.key;clearTimeout(timer);timer=setTimeout(()=>buffer='',120);}});
// Camera scan via BarcodeDetector API
const cameraBtn=document.getElementById('camera_btn');cameraBtn.addEventListener('click',async function(){
 if(!('BarcodeDetector' in window)){alert('Camera scanner not supported on this browser');return;}
 const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
 const video=document.createElement('video');video.srcObject=stream;await video.play();
 const detector=new BarcodeDetector({formats:['qr_code','ean_13','code_128']});
 const loop=async()=>{const codes=await detector.detect(video);if(codes[0]){scanEl.value=codes[0].rawValue;saveDraft();stream.getTracks().forEach(t=>t.stop());return;} requestAnimationFrame(loop);};loop();
});
})();
</script>
@endsection