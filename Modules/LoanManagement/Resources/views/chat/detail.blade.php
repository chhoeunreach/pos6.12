@extends('loanmanagement::layouts.app')
@section('title', 'Live Chat Detail')

@section('content_body')
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
<style>
    .lm-chat-attachment{margin-top:6px}
    .lm-chat-thumb{display:block;max-width:220px;max-height:260px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover;cursor:pointer}
    .lm-chat-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
    .lm-chat-actions a,.lm-chat-actions button{border:1px solid #dbe4ef;background:#f8fafc;color:#334155;border-radius:12px;padding:3px 9px;font-size:11px;font-weight:700;text-decoration:none}
    .lm-chat-actions a:hover,.lm-chat-actions button:hover{background:#eff6ff;color:#1d4ed8}
    .lm-chat-image-viewer{display:none;position:fixed;inset:0;background:rgba(15,23,42,.86);z-index:1100;align-items:center;justify-content:center;padding:18px}
    .lm-chat-image-viewer.open{display:flex}
    .lm-chat-image-viewer img{max-width:96vw;max-height:84vh;object-fit:contain;border-radius:8px;background:#fff}
</style>
<div class="lm-chat-image-viewer" id="lmChatImageViewer">
    <div>
        <button type="button" class="btn btn-default btn-sm pull-right" id="lmChatImageViewerClose" style="margin-bottom:8px;">Close</button>
        <img src="" alt="" id="lmChatImageViewerImage">
        <div class="lm-chat-actions">
            <a href="#" target="_blank" rel="noopener" id="lmChatImageViewerOpen"><i class="fa fa-external-link"></i> Open full</a>
            <a href="#" download id="lmChatImageViewerDownload"><i class="fa fa-download"></i> Download</a>
        </div>
    </div>
</div>
<script>
(function($){
    var threadId = {{ (int) $threadId }};
    var notificationSoundUrl = '{{ asset("audio/success.mp3") }}';
    var notificationAudio = null;
    var notificationAudioUnlocked = false;
    var seenMessagesInitialized = false;
    var seenMessages = {};
    function esc(v){ return $('<div>').text(v == null ? '' : String(v)).html(); }
    function ensureNotificationAudio(){
        if (!notificationAudio) {
            notificationAudio = new Audio(notificationSoundUrl);
            notificationAudio.preload = 'auto';
        }
        return notificationAudio;
    }
    function unlockNotificationAudio(){
        if (notificationAudioUnlocked) return;
        var audio = ensureNotificationAudio();
        audio.muted = true;
        var playPromise = audio.play();
        if (playPromise && playPromise.then) {
            playPromise.then(function(){
                audio.pause();
                audio.currentTime = 0;
                audio.muted = false;
                notificationAudioUnlocked = true;
            }).catch(function(){
                audio.muted = false;
            });
        } else {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            notificationAudioUnlocked = true;
        }
    }
    function playChatNotificationSound(){
        var audio = ensureNotificationAudio();
        audio.muted = false;
        audio.currentTime = 0;
        var playPromise = audio.play();
        if (playPromise && playPromise.catch) {
            playPromise.catch(function(){});
        }
    }
    function isIncomingMessage(message){
        return !(message && (message.is_own || message.sender_type === 'staff' || message.sender_type === 'admin'));
    }
    function messageSoundKey(message){
        if (!message) return '';
        return String(message.id || [message.sender_type, message.sender_id, message.created_at, message.message_type, message.message].join('|'));
    }
    function shouldPlayForNewIncomingMessages(messages){
        if (!seenMessagesInitialized) {
            seenMessages = {};
            (messages || []).forEach(function(m){
                var key = messageSoundKey(m);
                if (key) seenMessages[key] = true;
            });
            seenMessagesInitialized = true;
            return false;
        }

        var shouldPlay = false;
        (messages || []).forEach(function(m){
            var key = messageSoundKey(m);
            if (!key) return;
            if (!seenMessages[key] && isIncomingMessage(m)) {
                shouldPlay = true;
            }
            seenMessages[key] = true;
        });

        return shouldPlay;
    }
    function fileName(file, fallback){ return (file && file.name) || fallback || 'chat-file'; }
    function downloadUrl(url){ return url ? url + (String(url).indexOf('?') === -1 ? '?' : '&') + 'download=1' : '#'; }
    function imageHtml(file){
        var name = fileName(file, 'chat-image');
        return '<div class="lm-chat-attachment">' +
            '<img src="'+esc(file.url)+'" alt="'+esc(name)+'" class="lm-chat-thumb js-lm-chat-view-image" data-url="'+esc(file.url)+'" data-name="'+esc(name)+'">' +
            '<div class="lm-chat-actions">' +
                '<button type="button" class="js-lm-chat-view-image" data-url="'+esc(file.url)+'" data-name="'+esc(name)+'"><i class="fa fa-search-plus"></i> View full</button>' +
                '<a href="'+esc(file.url)+'" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Open</a>' +
                '<a href="'+esc(downloadUrl(file.url))+'" download="'+esc(name)+'"><i class="fa fa-download"></i> Download</a>' +
            '</div>' +
        '</div>';
    }
    function fileHtml(file){
        var name = fileName(file, 'Download file');
        return '<div class="lm-chat-attachment">' +
            '<a href="'+esc(file.url)+'" target="_blank" rel="noopener"><i class="fa fa-paperclip"></i> '+esc(name)+'</a>' +
            '<div class="lm-chat-actions">' +
                '<a href="'+esc(file.url)+'" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Open</a>' +
                '<a href="'+esc(downloadUrl(file.url))+'" download="'+esc(name)+'"><i class="fa fa-download"></i> Download</a>' +
            '</div>' +
        '</div>';
    }
    function loadDetail(){
        $.get('/api/loan-management/chats/'+threadId, function(resp){
            var d = resp.data || {};
            var msgs = d.messages || [];
            var box = $('#chat-box'); box.html('');
            if (shouldPlayForNewIncomingMessages(msgs)) {
                playChatNotificationSound();
            }
            msgs.forEach(function(m){
                var file = m.file || {};
                var attachment = '';
                if (m.message_type === 'image' && file.url) attachment = imageHtml(file);
                if (m.message_type === 'file' && file.url) attachment = fileHtml(file);
                if (m.message_type === 'audio' && file.url) attachment = '<div class="lm-chat-attachment"><audio controls src="'+esc(file.url)+'" style="max-width:220px"></audio></div>';
                box.append('<div style="margin-bottom:10px;"><strong>'+esc(m.sender_type)+'#'+esc(m.sender_id)+':</strong> '+esc(m.message||'')+' <small class="text-muted">'+esc(m.created_at)+'</small>'+attachment+'</div>');
            });
            box.scrollTop(box[0].scrollHeight);
        });
    }
    function openImageViewer(url, name){
        $('#lmChatImageViewerImage').attr('src', url).attr('alt', name || '');
        $('#lmChatImageViewerOpen').attr('href', url);
        $('#lmChatImageViewerDownload').attr('href', downloadUrl(url)).attr('download', name || 'chat-image');
        $('#lmChatImageViewer').addClass('open');
    }
    function closeImageViewer(){
        $('#lmChatImageViewer').removeClass('open');
        $('#lmChatImageViewerImage').attr('src', '');
    }
    $(document).on('click', '.js-lm-chat-view-image', function(){ openImageViewer($(this).data('url'), $(this).data('name')); });
    $('#lmChatImageViewerClose').on('click', closeImageViewer);
    $('#lmChatImageViewer').on('click', function(e){ if (e.target === this) closeImageViewer(); });
    $('#btnSend').on('click', function(){
        $.post('/api/loan-management/chats/'+threadId+'/messages', {_token: '{{ csrf_token() }}', message_type:'text', message:$('#msg').val()}, function(){
            $('#msg').val(''); loadDetail();
        });
    });
    $('#btnClose').on('click', function(){ $.post('/api/loan-management/chats/'+threadId+'/close', {_token:'{{ csrf_token() }}'}, loadDetail); });
    $('#btnReopen').on('click', function(){ $.post('/api/loan-management/chats/'+threadId+'/reopen', {_token:'{{ csrf_token() }}'}, loadDetail); });
    $(document).one('pointerdown keydown', unlockNotificationAudio);
    loadDetail();
    setInterval(loadDetail, {{ (int) config('loanmanagement.chat_polling_seconds', 5) * 1000 }});
})(jQuery);
</script>
@endsection
