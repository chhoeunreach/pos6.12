@extends('loanmanagement::layouts.app')
@section('title', 'Live Chat')

@section('loan_css')
<style>
    .lm-chat-shell{height:calc(100dvh - 190px);min-height:620px;display:grid;grid-template-columns:320px minmax(420px,1fr) 300px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-family:"Khmer OS Battambang","Noto Sans Khmer","Khmer UI","Segoe UI",Arial,sans-serif}
    .lm-chat-inbox{border-right:1px solid #e5e7eb;background:#f8fafc;display:flex;flex-direction:column;min-width:0;min-height:0}
    .lm-chat-toolbar{padding:14px;border-bottom:1px solid #e5e7eb;background:#fff}
    .lm-chat-toolbar h3{margin:0 0 10px;font-size:18px;font-weight:700;color:#0f172a}
    .lm-chat-search{height:36px;border:1px solid #d1d5db;border-radius:18px;padding:0 14px;width:100%;outline:none}
    .lm-chat-tabs{display:flex;gap:6px;overflow-x:auto;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#fff}
    .lm-chat-tab{white-space:nowrap;border:1px solid #d1d5db;background:#fff;border-radius:16px;padding:6px 10px;font-size:12px;color:#475569;cursor:pointer}
    .lm-chat-tab.active{background:#0ea5e9;border-color:#0ea5e9;color:#fff}
    .lm-chat-list{overflow:auto;flex:1}
    .lm-chat-item{display:grid;grid-template-columns:44px 1fr auto;gap:10px;padding:12px 14px;border-bottom:1px solid #e5e7eb;cursor:pointer;background:#fff}
    .lm-chat-item:hover,.lm-chat-item.active{background:#eef6ff}
    .lm-chat-avatar{width:44px;height:44px;border-radius:50%;background:#dbeafe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-weight:700;position:relative}
    .lm-chat-avatar.online:after{content:"";position:absolute;right:1px;bottom:1px;width:10px;height:10px;background:#22c55e;border:2px solid #fff;border-radius:50%}
    .lm-chat-title{font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-chat-subtitle,.lm-chat-preview{font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-chat-badge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:10px;background:#ef4444;color:#fff;font-size:11px;padding:0 6px}
    .lm-chat-main{display:flex;flex-direction:column;min-width:0;min-height:0;background:#f1f5f9}
    .lm-chat-header{height:72px;flex:0 0 72px;background:#fff;border-bottom:1px solid #e5e7eb;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .lm-chat-header-title{font-size:16px;font-weight:700;color:#0f172a}
    .lm-chat-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
    .lm-chat-actions .btn{border-radius:16px}
    .lm-chat-messages{flex:1 1 auto;min-height:0;overflow:auto;padding:18px}
    .lm-chat-empty{height:100%;display:flex;align-items:center;justify-content:center;color:#64748b;text-align:center}
    .lm-msg-row{display:flex;margin-bottom:12px}
    .lm-msg-row.own{justify-content:flex-end}
    .lm-msg{max-width:72%;border-radius:18px;padding:10px 13px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 1px 1px rgba(15,23,42,.04);overflow-wrap:anywhere;line-height:1.45}
    .lm-msg-row.own .lm-msg{background:#0ea5e9;color:#fff;border-color:#0ea5e9}
    .lm-msg-name{font-size:11px;font-weight:700;margin-bottom:4px;color:#64748b}
    .lm-msg-row.own .lm-msg-name{color:#e0f2fe}
    .lm-msg-meta{font-size:10px;margin-top:5px;color:#94a3b8}
    .lm-msg-row.own .lm-msg-meta{color:#dbeafe}
    .lm-chat-composer{flex:0 0 auto;background:#fff;border-top:1px solid #e5e7eb;padding:12px;display:flex;gap:8px;align-items:center}
    .lm-chat-composer input[type=text]{flex:1;height:40px;border:1px solid #d1d5db;border-radius:20px;padding:0 14px;outline:none}
    .lm-chat-side{min-height:0;border-left:1px solid #e5e7eb;background:#fff;overflow:auto}
    .lm-chat-side-section{padding:16px;border-bottom:1px solid #e5e7eb}
    .lm-chat-side-section h4{margin:0 0 12px;font-size:14px;font-weight:700;color:#0f172a}
    .lm-info-row{display:flex;justify-content:space-between;gap:12px;font-size:12px;padding:6px 0;border-bottom:1px dashed #e5e7eb}
    .lm-info-row span:first-child{color:#64748b}
    .lm-info-row span:last-child{font-weight:700;color:#111827;text-align:right}
    .lm-priority{font-size:11px;border-radius:10px;padding:2px 7px;background:#e2e8f0;color:#334155;text-transform:capitalize}
    .lm-priority.new{background:#dcfce7;color:#166534}
    .lm-priority.high,.lm-priority.urgent{background:#fee2e2;color:#991b1b}
    @media(max-width:1100px){.lm-chat-shell{grid-template-columns:280px 1fr}.lm-chat-side{display:none}}
    @media(max-width:760px){.lm-chat-shell{height:auto;min-height:700px;grid-template-columns:1fr}.lm-chat-inbox{height:260px}.lm-chat-main{min-height:520px}.lm-chat-composer{position:sticky;bottom:0}}
</style>
@endsection

@section('content_body')
<section class="content-header">
    <h1>Live Chat <small>Support inbox</small></h1>
</section>
<section class="content">
    <div class="row" style="margin-bottom:12px">
        <div class="col-sm-2"><div class="small-box bg-aqua"><div class="inner"><h3 id="card_active">0</h3><p>Active Chats</p></div></div></div>
        <div class="col-sm-2"><div class="small-box bg-yellow"><div class="inner"><h3 id="card_unread">0</h3><p>Unread Chats</p></div></div></div>
        <div class="col-sm-2"><div class="small-box bg-orange"><div class="inner"><h3 id="card_overdue">0</h3><p>Overdue Chats</p></div></div></div>
        <div class="col-sm-2"><div class="small-box bg-purple"><div class="inner"><h3 id="card_recovery">0</h3><p>Recovery Chats</p></div></div></div>
        <div class="col-sm-2"><div class="small-box bg-red"><div class="inner"><h3 id="card_legal">0</h3><p>Legal Chats</p></div></div></div>
        <div class="col-sm-2"><div class="small-box bg-green"><div class="inner"><h3 id="card_closed">0</h3><p>Closed Today</p></div></div></div>
    </div>

    <div class="lm-chat-shell" id="lmChatApp">
        <aside class="lm-chat-inbox">
            <div class="lm-chat-toolbar">
                <h3>Staff Support Inbox</h3>
                <input type="text" class="lm-chat-search" id="chatSearch" placeholder="Search customer, phone, loan">
            </div>
            <div class="lm-chat-tabs" id="chatTabs">
                <button class="lm-chat-tab active" data-view="all">All</button>
                <button class="lm-chat-tab" data-view="unread">Unread</button>
                <button class="lm-chat-tab" data-view="assigned_to_me">Assigned To Me</button>
                <button class="lm-chat-tab" data-view="active_customers">Active Customers</button>
                <button class="lm-chat-tab" data-view="overdue_customers">Overdue</button>
                <button class="lm-chat-tab" data-view="skip_customers">Skip</button>
                <button class="lm-chat-tab" data-view="recovery">Recovery</button>
                <button class="lm-chat-tab" data-view="legal">Legal</button>
                <button class="lm-chat-tab" data-view="closed">Closed</button>
            </div>
            <div class="lm-chat-list" id="chatList"></div>
        </aside>

        <main class="lm-chat-main">
            <div class="lm-chat-header">
                <div>
                    <div class="lm-chat-header-title" id="activeTitle">Select a chat</div>
                    <div class="lm-chat-subtitle" id="activeSubtitle">Customer support, collection, recovery and legal conversations</div>
                </div>
                <div class="lm-chat-actions">
                    <button class="btn btn-default btn-sm" id="btnPin"><i class="fa fa-thumb-tack"></i></button>
                    <button class="btn btn-default btn-sm" id="btnMute"><i class="fa fa-bell-slash"></i></button>
                    <button class="btn btn-default btn-sm" id="btnAssign"><i class="fa fa-user-plus"></i> Assign</button>
                    <button class="btn btn-default btn-sm" id="btnTransfer"><i class="fa fa-exchange"></i> Transfer</button>
                    <button class="btn btn-warning btn-sm" id="btnReopen">Reopen</button>
                    <button class="btn btn-danger btn-sm" id="btnClose">Close</button>
                </div>
            </div>
            <div class="lm-chat-messages" id="messageList">
                <div class="lm-chat-empty">Choose a conversation from the inbox.</div>
            </div>
            <form class="lm-chat-composer" id="messageForm">
                <button type="button" class="btn btn-default btn-sm" id="btnImage"><i class="fa fa-image"></i></button>
                <button type="button" class="btn btn-default btn-sm" id="btnFile"><i class="fa fa-paperclip"></i></button>
                <button type="button" class="btn btn-default btn-sm" id="btnLocation"><i class="fa fa-map-marker"></i></button>
                <input type="text" id="messageText" placeholder="Write a message">
                <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i></button>
                <input type="file" id="chatFile" style="display:none">
            </form>
        </main>

        <aside class="lm-chat-side">
            <div class="lm-chat-side-section">
                <h4>Customer Info</h4>
                <div id="customerInfo"></div>
            </div>
            <div class="lm-chat-side-section">
                <h4>Assignment</h4>
                <div class="form-group">
                    <label>Staff ID</label>
                    <input type="number" class="form-control" id="assignStaffId" placeholder="Collector/Admin ID">
                </div>
                <div class="form-group">
                    <label>Team</label>
                    <select class="form-control" id="assignTeam">
                        <option value="">Support Team</option>
                        <option value="collection">Collection</option>
                        <option value="recovery">Recovery</option>
                        <option value="legal">Legal</option>
                        <option value="skip">Skip Customers</option>
                    </select>
                </div>
            </div>
        </aside>
    </div>
</section>
@endsection

@section('loan_js')
<script>
(function($){
    var activeThread = @json($initialThreadId ?? null);
    var activeView = 'all';
    var threads = [];
    var csrf = '{{ csrf_token() }}';
    var chatBaseUrl = '{{ url('loan-management/chat-api/chats') }}';
    var pollMs = {{ (int) config('loanmanagement.chat_polling_seconds', 5) * 1000 }};

    function apiData(resp){ return resp && resp.data && resp.data.data ? resp.data.data : (resp && resp.data ? resp.data : []); }
    function esc(v){ return $('<div>').text(v == null ? '' : String(v)).html(); }
    function initials(name){ name = (name || 'Support Team').trim(); return esc(name.charAt(0).toUpperCase() || 'S'); }
    function money(v){ var n = parseFloat(v || 0); return '$ ' + n.toFixed(2); }
    function pad2(v){ return String(v).padStart(2, '0'); }
    function formatChatTime(value, fallback){
        if (!value) return fallback || '';
        var raw = String(value).trim();
        var date = new Date(raw);
        if (isNaN(date.getTime()) && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)) {
            date = new Date(raw.replace(' ', 'T'));
        }
        if (isNaN(date.getTime())) return fallback || raw;
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate()) + ' ' +
            pad2(date.getHours()) + ':' + pad2(date.getMinutes()) + ':' + pad2(date.getSeconds());
    }

    function loadInbox(keepActive){
        $.get(chatBaseUrl, {view: activeView, search: $('#chatSearch').val() || ''}, function(resp){
            threads = apiData(resp) || [];
            renderCards(threads);
            renderThreads();
            if (activeThread) {
                var exists = threads.some(function(t){ return String(t.id) === String(activeThread); });
                if (exists) loadThread(activeThread, false);
            }
        });
    }

    function renderCards(rows){
        $('#card_active').text(rows.filter(function(r){ return ['open','active','pending'].indexOf(r.status) >= 0; }).length);
        $('#card_unread').text(rows.filter(function(r){ return Number(r.unread_count || 0) > 0; }).length);
        $('#card_overdue').text(rows.filter(function(r){ return ['high','urgent'].indexOf(r.priority) >= 0 || r.type === 'overdue'; }).length);
        $('#card_recovery').text(rows.filter(function(r){ return r.type === 'recovery' || r.assigned_team === 'recovery'; }).length);
        $('#card_legal').text(rows.filter(function(r){ return r.type === 'legal' || r.assigned_team === 'legal'; }).length);
        $('#card_closed').text(rows.filter(function(r){ return r.status === 'closed'; }).length);
    }

    function renderThreads(){
        var q = ($('#chatSearch').val() || '').toLowerCase();
        var list = $('#chatList').empty();
        var filtered = threads.filter(function(r){
            var hay = [r.display_name, r.display_subtitle, r.customer_name, r.customer_phone, r.last_message].join(' ').toLowerCase();
            return !q || hay.indexOf(q) >= 0;
        });
        if (!filtered.length) {
            list.html('<div class="lm-chat-empty" style="height:160px">No chats found.</div>');
            return;
        }
        filtered.forEach(function(r){
            var badge = Number(r.unread_count || 0) > 0 ? '<span class="lm-chat-badge">'+Number(r.unread_count)+'</span>' : '';
            var item = $('<div class="lm-chat-item" data-id="'+(r.id || '')+'" data-customer-id="'+(r.customer_id || '')+'" data-new-chat="'+(r.is_customer_only ? '1' : '0')+'">'+
                '<div class="lm-chat-avatar '+(r.is_online ? 'online' : '')+'">'+initials(r.display_name)+'</div>'+
                '<div style="min-width:0"><div class="lm-chat-title">'+esc(r.display_name)+'</div>'+
                '<div class="lm-chat-subtitle">'+esc(r.display_subtitle || '')+'</div>'+
                '<div class="lm-chat-preview">'+esc(r.typing ? 'Typing...' : (r.last_sender_name ? r.last_sender_name + ': ' : '') + (r.last_message || 'No messages yet'))+'</div></div>'+
                '<div style="text-align:right"><span class="lm-priority '+esc(r.status === 'new' ? 'new' : (r.priority || ''))+'">'+esc(r.status === 'new' ? 'new' : (r.priority || 'normal'))+'</span><div style="margin-top:6px">'+badge+'</div></div>'+
            '</div>');
            if (String(r.id) === String(activeThread)) item.addClass('active');
            list.append(item);
        });
    }

    function loadThread(id, markRead){
        activeThread = id;
        $('.lm-chat-item').removeClass('active');
        $('.lm-chat-item[data-id="'+id+'"]').addClass('active');
        $.get(chatBaseUrl + '/' + id, function(resp){
            var row = apiData(resp);
            $('#activeTitle').text(row.display_name || 'Customer');
            $('#activeSubtitle').text((row.display_subtitle || '') + (row.status ? ' - ' + row.status : ''));
            $('#btnReopen').toggle(row.status === 'closed');
            $('#btnClose').toggle(row.status !== 'closed');
            renderMessages(row.messages || []);
            renderSidebar(row.sidebar || {});
            if (markRead !== false) {
                $.post(chatBaseUrl + '/' + id + '/read', {_token: csrf});
            }
        });
    }

    function renderMessages(messages){
        var box = $('#messageList').empty();
        if (!messages.length) {
            box.html('<div class="lm-chat-empty">No messages yet.</div>');
            return;
        }
        messages.forEach(function(m){
            var body = esc(m.message || '');
            if (m.message_type === 'image' && m.file && m.file.url) body += '<div><img src="'+esc(m.file.url)+'" style="max-width:220px;border-radius:8px;margin-top:6px"></div>';
            if (m.message_type === 'file' && m.file && m.file.url) body += '<div><a href="'+esc(m.file.url)+'" target="_blank">'+esc(m.file.name || 'Download file')+'</a></div>';
            if (m.message_type === 'audio' && m.file && m.file.url) body += '<div><audio controls src="'+esc(m.file.url)+'" style="max-width:220px;margin-top:6px"></audio></div>';
            if (m.message_type === 'location' && m.location && m.location.latitude) body += '<div><a target="_blank" href="https://maps.google.com/?q='+esc(m.location.latitude)+','+esc(m.location.longitude)+'">Open location</a></div>';
            box.append('<div class="lm-msg-row '+(m.is_own ? 'own' : '')+'"><div class="lm-msg"><div class="lm-msg-name">'+esc(m.sender_name || '')+'</div><div>'+body+'</div><div class="lm-msg-meta">'+esc(formatChatTime(m.created_at_iso || m.created_at, m.created_at_display || m.created_at || ''))+'</div></div></div>');
        });
        box.scrollTop(box[0].scrollHeight);
    }

    function renderSidebar(info){
        var rows = [
            ['Customer', info.customer_name],
            ['Phone', info.phone],
            ['Loan #', info.loan_number],
            ['Overdue Days', info.overdue_days],
            ['Balance', money(info.balance)],
            ['Next Due', info.next_due_date],
            ['Risk', info.risk_level],
            ['Guarantor', info.guarantor],
            ['GPS', info.gps_location && info.gps_location.latitude ? info.gps_location.latitude + ', ' + info.gps_location.longitude : '-'],
            ['Notes', info.collection_notes]
        ];
        $('#customerInfo').html(rows.map(function(r){ return '<div class="lm-info-row"><span>'+esc(r[0])+'</span><span>'+esc(r[1] || '-')+'</span></div>'; }).join(''));
    }

    function sendFile(type, file){
        if (!activeThread || !file) return;
        var data = new FormData();
        data.append('_token', csrf);
        data.append('message_type', type);
        data.append('file', file);
        data.append('message', $('#messageText').val() || '');
        $.ajax({url:chatBaseUrl + '/' + activeThread + '/messages', method:'POST', data:data, processData:false, contentType:false})
            .done(function(){ $('#messageText').val(''); $('#chatFile').val(''); loadThread(activeThread); loadInbox(true); });
    }

    $('#chatTabs').on('click', '.lm-chat-tab', function(){
        activeView = $(this).data('view');
        $('.lm-chat-tab').removeClass('active');
        $(this).addClass('active');
        loadInbox(false);
    });
    function openCustomerTarget(customerId){
        $('#activeTitle').text('Opening customer chat...');
        $('#activeSubtitle').text('Preparing conversation');
        $.post(chatBaseUrl, {_token: csrf, customer_id: customerId, type: 'customer_staff', priority: 'normal'}, function(resp){
            var row = apiData(resp);
            if (row && row.id) {
                activeThread = row.id;
                loadInbox(true);
                loadThread(row.id);
            }
        }).fail(function(xhr){
            var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Cannot open chat for this customer.';
            $('#messageList').html('<div class="lm-chat-empty">'+esc(message)+'</div>');
            $('#activeTitle').text('Chat not opened');
            $('#activeSubtitle').text('Please check Live Chat permission');
        });
    }

    $('#chatList').on('click', '.lm-chat-item', function(){
        var id = $(this).data('id');
        if (id) {
            loadThread(id);
            return;
        }
        var customerId = $(this).data('customer-id');
        if (customerId) {
            openCustomerTarget(customerId);
        }
    });
    $('#chatSearch').on('input', function(){ renderThreads(); loadInbox(false); });
    $('#messageText').on('input', function(){ if(activeThread) $.post(chatBaseUrl + '/' + activeThread + '/typing', {_token:csrf}); });
    $('#messageForm').on('submit', function(e){
        e.preventDefault();
        if (!activeThread || !$('#messageText').val().trim()) return;
        $.post(chatBaseUrl + '/' + activeThread + '/messages', {_token:csrf, message_type:'text', message:$('#messageText').val()}, function(){
            $('#messageText').val('');
            loadThread(activeThread);
            loadInbox(true);
        }).fail(function(xhr){
            var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Cannot send reply.';
            $('#messageList').append('<div class="lm-chat-empty" style="height:auto;padding:10px">'+esc(message)+'</div>');
        });
    });
    $('#btnImage').on('click', function(){ $('#chatFile').attr('accept','image/*').data('type','image').click(); });
    $('#btnFile').on('click', function(){ $('#chatFile').removeAttr('accept').data('type','file').click(); });
    $('#chatFile').on('change', function(){ sendFile($(this).data('type') || 'file', this.files[0]); });
    $('#btnLocation').on('click', function(){
        if (!activeThread || !navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function(pos){
            $.post(chatBaseUrl + '/' + activeThread + '/messages', {_token:csrf,message_type:'location',latitude:pos.coords.latitude,longitude:pos.coords.longitude}, function(){ loadThread(activeThread); });
        });
    });
    $('#btnAssign').on('click', function(){
        if (!activeThread || !$('#assignStaffId').val()) return;
        $.post(chatBaseUrl + '/' + activeThread + '/assign', {_token:csrf, staff_id:$('#assignStaffId').val(), assigned_team:$('#assignTeam').val()}, function(){ loadThread(activeThread); loadInbox(true); });
    });
    $('#btnTransfer').on('click', function(){
        if (!activeThread || !$('#assignStaffId').val()) return;
        $.post(chatBaseUrl + '/' + activeThread + '/transfer', {_token:csrf, staff_id:$('#assignStaffId').val(), assigned_team:$('#assignTeam').val()}, function(){ loadThread(activeThread); loadInbox(true); });
    });
    $('#btnClose').on('click', function(){ if(activeThread) $.post(chatBaseUrl + '/' + activeThread + '/close', {_token:csrf}, function(){ loadThread(activeThread); loadInbox(true); }); });
    $('#btnReopen').on('click', function(){ if(activeThread) $.post(chatBaseUrl + '/' + activeThread + '/reopen', {_token:csrf}, function(){ loadThread(activeThread); loadInbox(true); }); });
    $('#btnPin').on('click', function(){ if(activeThread) $.post(chatBaseUrl + '/' + activeThread + '/pin', {_token:csrf, is_pinned:1}, function(){ loadInbox(true); }); });
    $('#btnMute').on('click', function(){ if(activeThread) $.post(chatBaseUrl + '/' + activeThread + '/mute', {_token:csrf, is_muted:1}, function(){ loadInbox(true); }); });

    loadInbox(false);
    setInterval(function(){ loadInbox(true); }, pollMs);
})(jQuery);
</script>
@endsection
