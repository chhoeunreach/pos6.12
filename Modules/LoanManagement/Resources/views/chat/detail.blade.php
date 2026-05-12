@extends('layouts.app')
@section('title', 'Live Chat Detail')

@section('content')
<section class="content-header"><h1>Live Chat #{{ $threadId }}</h1></section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <div id="chat-box" style="height:420px;overflow:auto;border:1px solid #ddd;padding:8px;margin-bottom:10px;"></div>
            <div class="row">
                <div class="col-md-10"><input type="text" class="form-control" id="msg" placeholder="Type message"></div>
                <div class="col-md-2"><button class="btn btn-primary btn-block" id="btnSend">Send</button></div>
            </div>
            <hr>
            <button class="btn btn-warning" id="btnClose">Close Thread</button>
            <button class="btn btn-success" id="btnReopen">Reopen Thread</button>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
(function($){
    var threadId = {{ (int) $threadId }};
    function loadDetail(){
        $.get('/api/loan-management/chats/'+threadId, function(resp){
            var d = resp.data || {};
            var msgs = d.messages || [];
            var box = $('#chat-box'); box.html('');
            msgs.forEach(function(m){
                box.append('<div style="margin-bottom:6px;"><strong>'+m.sender_type+'#'+m.sender_id+':</strong> '+(m.message||'')+' <small class="text-muted">'+m.created_at+'</small></div>');
            });
            box.scrollTop(box[0].scrollHeight);
        });
    }
    $('#btnSend').on('click', function(){
        $.post('/api/loan-management/chats/'+threadId+'/messages', {_token: '{{ csrf_token() }}', message_type:'text', message:$('#msg').val()}, function(){
            $('#msg').val(''); loadDetail();
        });
    });
    $('#btnClose').on('click', function(){ $.post('/api/loan-management/chats/'+threadId+'/close', {_token:'{{ csrf_token() }}'}, loadDetail); });
    $('#btnReopen').on('click', function(){ $.post('/api/loan-management/chats/'+threadId+'/reopen', {_token:'{{ csrf_token() }}'}, loadDetail); });
    loadDetail();
    setInterval(loadDetail, {{ (int) config('loanmanagement.chat_polling_seconds', 5) * 1000 }});
})(jQuery);
</script>
@endsection

