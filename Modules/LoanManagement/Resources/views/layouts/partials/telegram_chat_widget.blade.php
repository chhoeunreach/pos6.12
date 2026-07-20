{{-- Global Telegram-style chat widget: sticky floating button + a two-pane panel (contact
     sidebar + conversation), available on every Loan Management module page. Backed entirely by
     its own tables/service/controller (TelegramChatService, LoanTelegramChatController,
     loan_telegram_chat_threads/messages) - fully independent from the staff's own internal Live
     Chat tool (chat/inbox.blade.php, LoanChatService, loan_chat_threads/messages), which this
     widget never reads from or writes to. Contacts shown are scoped to the staff member's
     permitted business location(s) by the backend. --}}
@php
    $tgBound = isset($customerRow) && $customerRow;
    $tgBoundId = $tgBound ? (int) $customerRow->id : null;
    $tgBoundName = $tgBound ? (trim((string) ($customerRow->khmer_name ?? '')) ?: trim((string) ($customerRow->name ?? ''))) : '';
    $tgBoundLinked = $tgBound ? !empty($customerRow->telegram_chat_id) : false;
    $tgPollMs = (int) config('loanmanagement.chat_polling_seconds', 5) * 1000;
@endphp
<style>
    #lmTgFab{position:fixed;right:26px;bottom:26px;width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,#6dc9f7,#2894e0);color:#fff;border:0;box-shadow:0 6px 20px rgba(41,148,224,.5);font-size:25px;cursor:pointer;z-index:4998;display:flex;align-items:center;justify-content:center;transition:transform .15s ease}
    #lmTgFab:hover{transform:scale(1.07)}
    #lmTgFab:active{transform:scale(.96)}
    #lmTgFab .lm-tg-fab-dot{position:absolute;top:2px;right:2px;width:13px;height:13px;background:#94a3b8;border:2px solid #fff;border-radius:50%}
    #lmTgFab.linked .lm-tg-fab-dot{background:#22c55e}
    #lmTgFab.open{display:none}

    #lmTgDrawerOverlay{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:4999;opacity:0;pointer-events:none;transition:opacity .18s ease}
    #lmTgDrawerOverlay.open{opacity:1;pointer-events:auto}

    #lmTgDrawer{position:fixed;top:50%;left:50%;width:min(940px,94vw);height:min(660px,86vh);background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.3);z-index:5000;border-radius:14px;overflow:hidden;display:flex;flex-direction:row;opacity:0;pointer-events:none;transform:translate(-50%,-50%) scale(.96);transition:opacity .18s ease,transform .18s ease;font-family:"Khmer OS Battambang","Noto Sans Khmer","Segoe UI",Arial,sans-serif}
    #lmTgDrawer.open{opacity:1;pointer-events:auto;transform:translate(-50%,-50%) scale(1)}

    .lm-tg-sidebar{width:300px;flex:0 0 300px;border-right:1px solid #e5e7eb;background:#f7f9fb;display:flex;flex-direction:column;min-height:0}
    .lm-tg-sidebar-head{padding:14px 14px 10px;flex:0 0 auto}
    .lm-tg-sidebar-head h4{margin:0 0 10px;font-size:16px;font-weight:700;color:#0f172a}
    .lm-tg-sidebar-head input{width:100%;border:1px solid #d1d5db;border-radius:18px;padding:8px 14px;font-size:12.5px;outline:none;box-sizing:border-box;background:#fff}
    .lm-tg-sidebar-head input:focus{border-color:#54a9eb}
    .lm-tg-contact-list{flex:1 1 auto;overflow-y:auto;min-height:0;padding:4px 8px 10px}
    .lm-tg-contact{display:flex;align-items:center;gap:10px;padding:9px 8px;cursor:pointer;border-radius:9px;margin-bottom:2px}
    .lm-tg-contact:hover{background:#eef4fb}
    .lm-tg-contact.active{background:#dbeafe}
    .lm-tg-contact-avatar{width:38px;height:38px;border-radius:50%;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 auto;font-size:14px;position:relative}
    .lm-tg-contact-avatar img,.lm-tg-header .lm-tg-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block}
    .lm-tg-contact-avatar .dot{position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;border-radius:50%;background:#cbd5e1;border:2px solid #f7f9fb}
    .lm-tg-contact-avatar .dot.linked{background:#22c55e}
    .lm-tg-contact-info{min-width:0;flex:1}
    .lm-tg-contact-name{font-weight:600;font-size:13px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-contact-sub{font-size:11.5px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-contact-badge{min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:10.5px;display:flex;align-items:center;justify-content:center;padding:0 5px;flex:0 0 auto}
    .lm-tg-empty-side{text-align:center;color:#94a3b8;font-size:11.5px;margin-top:24px;padding:0 12px}

    .lm-tg-chat{flex:1 1 auto;display:flex;flex-direction:column;min-width:0;min-height:0}
    .lm-tg-header{background:linear-gradient(135deg,#54a9eb,#4592d6);color:#fff;padding:13px 16px;display:flex;align-items:center;gap:10px;flex:0 0 auto;box-shadow:0 1px 4px rgba(0,0,0,.12)}
    .lm-tg-header .lm-tg-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex:0 0 auto}
    .lm-tg-header-info{flex:1;min-width:0}
    .lm-tg-header-info .name{font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-header-info .status{font-size:11.5px;opacity:.9;display:flex;align-items:center;gap:5px}
    .lm-tg-header-info .status .dot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.6)}
    .lm-tg-header-info .status.online .dot{background:#7CFC98}
    .lm-tg-close{background:rgba(255,255,255,.15);border:0;color:#fff;font-size:16px;line-height:1;cursor:pointer;padding:6px 9px;border-radius:50%;flex:0 0 auto}

    .lm-tg-body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:14px;background:#e7ecf1;background-image:radial-gradient(rgba(84,169,235,.06) 1px,transparent 1px);background-size:14px 14px}
    .lm-tg-date-sep{text-align:center;margin:12px 0}
    .lm-tg-date-sep span{background:rgba(255,255,255,.75);color:#64748b;font-size:11px;padding:3px 12px;border-radius:12px;box-shadow:0 1px 1px rgba(0,0,0,.05)}
    .lm-tg-row{display:flex;margin-bottom:6px}
    .lm-tg-row.own{justify-content:flex-end}
    .lm-tg-bubble{max-width:74%;padding:7px 10px;border-radius:15px;background:#fff;box-shadow:0 1px 1px rgba(0,0,0,.07);font-size:13.5px;line-height:1.42;overflow-wrap:anywhere;position:relative}
    .lm-tg-row.own .lm-tg-bubble{background:linear-gradient(135deg,#e3fbd4,#d5f7c4);border-bottom-right-radius:4px}
    .lm-tg-row:not(.own) .lm-tg-bubble{border-bottom-left-radius:4px}
    .lm-tg-meta{display:flex;align-items:center;gap:4px;margin-top:3px;font-size:10px;color:#94a3b8;justify-content:flex-end}
    .lm-tg-meta .fa-telegram{color:#54a9eb}
    .lm-tg-ticks{color:#9ca3af}
    .lm-tg-ticks.read{color:#54a9eb}
    .lm-tg-actions{display:flex;gap:4px;justify-content:flex-end;margin-top:4px;opacity:.85}
    .lm-tg-action{border:0;background:rgba(15,23,42,.08);color:#334155;border-radius:10px;padding:2px 7px;font-size:10px;line-height:1.4;cursor:pointer}
    .lm-tg-action:hover{background:rgba(15,23,42,.14)}
    .lm-tg-action.delete{color:#b91c1c;background:rgba(239,68,68,.12)}
    .lm-tg-edited{font-style:italic;color:#94a3b8}
    .lm-tg-empty{text-align:center;color:#94a3b8;font-size:12px;margin-top:30px}
    .lm-tg-composer{flex:0 0 auto;background:#fff;padding:9px 10px;display:flex;gap:8px;align-items:center;border-top:1px solid #e2e8f0}
    .lm-tg-composer input[type=text]{flex:1;border:1px solid #d1d5db;background:#f4f6f8;border-radius:20px;padding:9px 15px;outline:none;font-size:13px}
    .lm-tg-composer input[type=text]:focus{border-color:#54a9eb;background:#fff}
    .lm-tg-composer button{border:0;background:linear-gradient(135deg,#6dc9f7,#2894e0);color:#fff;width:38px;height:38px;border-radius:50%;flex:0 0 auto;cursor:pointer;font-size:14px}
    .lm-tg-composer button:disabled{opacity:.5;cursor:default}
    .lm-tg-not-linked{padding:9px 14px;background:#fff7ed;border-bottom:1px solid #fde68a;font-size:11.5px;color:#92400e;flex:0 0 auto}
    .lm-tg-tools{flex:0 0 auto;display:none;gap:6px;align-items:center;flex-wrap:wrap;padding:7px 10px;background:#fff;border-top:1px solid #e2e8f0}
    .lm-tg-tools button{border:1px solid #dbe4ef;background:#f8fafc;color:#334155;border-radius:14px;padding:5px 9px;font-size:11px;font-weight:700;cursor:pointer}
    .lm-tg-tools button:hover{background:#eff6ff;color:#1d4ed8}
    .lm-tg-tools button.recording{background:#fee2e2;color:#b91c1c;border-color:#fecaca}

    @media (max-width:760px){
        #lmTgDrawer{width:96vw;height:92vh}
        .lm-tg-sidebar{width:120px;flex-basis:120px}
        .lm-tg-contact-info{display:none}
    }
</style>

<button type="button" id="lmTgFab" title="Open Telegram Chat" class="{{ $tgBoundLinked ? 'linked' : '' }}">
    <i class="fa fa-telegram"></i>
    <span class="lm-tg-fab-dot"></span>
</button>

<div id="lmTgDrawerOverlay"></div>
<div id="lmTgDrawer">
    <aside class="lm-tg-sidebar">
        <div class="lm-tg-sidebar-head">
            <h4><i class="fa fa-telegram"></i> Chats</h4>
            <input type="text" id="lmTgSearchInput" placeholder="Search name, phone or code" autocomplete="off">
        </div>
        <div class="lm-tg-contact-list" id="lmTgContactList">
            <div class="lm-tg-empty-side">Loading contacts...</div>
        </div>
    </aside>
    <main class="lm-tg-chat">
        <div class="lm-tg-header">
            <div class="lm-tg-avatar" id="lmTgHeaderAvatar"><i class="fa fa-telegram"></i></div>
            <div class="lm-tg-header-info">
                <div class="name" id="lmTgHeaderName">Select a conversation</div>
                <div class="status" id="lmTgHeaderStatus"><span class="dot"></span><span id="lmTgHeaderStatusText">Choose a customer from the list</span></div>
            </div>
            <button type="button" class="lm-tg-close" id="lmTgDrawerCloseX" aria-label="Close">&times;</button>
        </div>
        <div class="lm-tg-not-linked" id="lmTgNotLinkedBanner" style="display:none">This customer hasn't connected Telegram yet. Messages sent here are still saved, but won't reach them until they connect.</div>
        <div class="lm-tg-body" id="lmTgMessages">
            <div class="lm-tg-empty">Select a conversation from the left to start chatting.</div>
        </div>
        <div id="lmTgComposerError" style="display:none;padding:4px 14px 0;font-size:11px;color:#dc2626;background:#fff"></div>
        <div class="lm-tg-tools" id="lmTgTools">
            <button type="button" id="lmTgSendInvoice"><i class="fa fa-file-text-o"></i> Send Invoice</button>
            <button type="button" id="lmTgSendPay"><i class="fa fa-money"></i> Pay</button>
            <button type="button" id="lmTgPickImages"><i class="fa fa-image"></i> Images</button>
            <button type="button" id="lmTgPickDocs"><i class="fa fa-paperclip"></i> Documents</button>
            <button type="button" id="lmTgSendLocation"><i class="fa fa-map-marker"></i> Location</button>
            <button type="button" id="lmTgVoiceBtn"><i class="fa fa-microphone"></i> Voice</button>
            <input type="file" id="lmTgImageInput" accept="image/*" multiple style="display:none">
            <input type="file" id="lmTgDocInput" multiple style="display:none">
        </div>
        <form class="lm-tg-composer" id="lmTgComposerForm" style="display:none">
            <input type="text" id="lmTgMessageInput" placeholder="Write a message" autocomplete="off">
            <button type="submit" aria-label="Send"><i class="fa fa-paper-plane"></i></button>
        </form>
    </main>
</div>

<script>
(function($){
    var csrf = '{{ csrf_token() }}';
    var boundCustomerId = @json($tgBoundId);
    var boundCustomerName = @json($tgBoundName);
    var boundTelegramLinked = @json($tgBoundLinked);
    var chatBaseUrl = '{{ url("loan-management/telegram-chat-api/chats") }}';
    var pollMs = parseInt('{{ $tgPollMs }}', 10);
    var activeThreadId = null;
    var activeCustomerId = null;
    var pollTimer = null;
    var loadingThread = false;
    var searchTimer = null;
    var contacts = [];
    var activeCustomerName = '';
    var activeLoanContext = {};
    var mediaRecorder = null;
    var voiceChunks = [];
    var voiceStartedAt = null;

    function esc(v){ return $('<div>').text(v == null ? '' : String(v)).html(); }
    function pad2(v){ return String(v).padStart(2, '0'); }
    function formatTime(value){
        if (!value) return '';
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }
    function dateKey(value){
        if (!value) return '';
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }
    function dateLabel(value){
        var key = dateKey(value);
        if (!key) return '';
        var today = dateKey(new Date().toISOString());
        var yestDate = new Date(); yestDate.setDate(yestDate.getDate() - 1);
        var yesterday = dateKey(yestDate.toISOString());
        if (key === today) return 'Today';
        if (key === yesterday) return 'Yesterday';
        var d = new Date(String(value).replace(' ', 'T'));
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function apiGet(url){
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        }).then(function(r){ return r.json(); });
    }
    function apiPostJson(url, payload){
        return apiJson(url, 'POST', payload);
    }
    function apiPostForm(url, formData){
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: formData
        }).then(function(r){ return r.json(); });
    }
    function apiJson(url, method, payload){
        return fetch(url, {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(payload || {})
        }).then(function(r){ return r.json(); });
    }
    function singleData(resp){ return resp && resp.data ? resp.data : null; }
    function listData(resp){ return resp && Array.isArray(resp.data) ? resp.data : []; }

    function profileName(profile, fallback){
        return (profile && (profile.display_name || profile.customer_name || profile.name)) || fallback || 'Customer';
    }
    function profileInitial(name){
        return (name || 'C').charAt(0).toUpperCase();
    }
    function setHeader(profile, linked, statusText){
        if (typeof profile === 'string') {
            profile = {display_name: profile};
        }
        profile = profile || {};
        var name = profileName(profile);
        var isLinked = typeof profile.telegram_linked === 'boolean' ? profile.telegram_linked : !!linked;
        var details = profile.subtitle || profile.phone || profile.customer_code || '';

        if (profile.avatar_url) {
            $('#lmTgHeaderAvatar').html('<img src="' + esc(profile.avatar_url) + '" alt="">');
        } else {
            $('#lmTgHeaderAvatar').html(esc(profileInitial(name)));
        }

        $('#lmTgHeaderName').text(name);
        $('#lmTgHeaderStatus').toggleClass('online', isLinked);
        $('#lmTgHeaderStatusText').text(statusText || (details ? details + ' · ' : '') + (isLinked ? 'connected via Telegram' : 'not connected'));
        $('#lmTgNotLinkedBanner').toggle(!isLinked);
    }

    function renderMessages(messages){
        var box = $('#lmTgMessages').empty();
        if (!messages || !messages.length) {
            box.html('<div class="lm-tg-empty">No messages yet. Say hello!</div>');
            return;
        }
        var lastDateKey = null;
        messages.forEach(function(m){
            var thisDateKey = dateKey(m.created_at);
            if (thisDateKey !== lastDateKey) {
                box.append('<div class="lm-tg-date-sep"><span>' + esc(dateLabel(m.created_at)) + '</span></div>');
                lastDateKey = thisDateKey;
            }

            var body = esc(m.message || '');
            if (m.message_type === 'image' && m.file && m.file.url) body += '<div><img src="'+esc(m.file.url)+'" style="max-width:200px;border-radius:8px;margin-top:6px"></div>';
            if (m.message_type === 'file' && m.file && m.file.url) body += '<div><a href="'+esc(m.file.url)+'" target="_blank">'+esc(m.file.name || 'Download file')+'</a></div>';
            if (m.message_type === 'audio' && m.file && m.file.url) body += '<div><audio controls src="'+esc(m.file.url)+'" style="max-width:200px;margin-top:6px"></audio></div>';
            if (m.message_type === 'location' && m.latitude && m.longitude) body += '<div><a href="https://maps.google.com/?q='+esc(m.latitude)+','+esc(m.longitude)+'" target="_blank"><i class="fa fa-map-marker"></i> Open location</a></div>';
            var ticks = '';
            if (m.is_own) {
                var isRead = !!m.read_at;
                ticks = '<span class="lm-tg-ticks' + (isRead ? ' read' : '') + '">' + (isRead ? '&#10003;&#10003;' : '&#10003;') + '</span>';
            }
            var edited = m.edited ? '<span class="lm-tg-edited">edited</span>' : '';
            var actions = '';
            if (m.can_update || m.can_delete) {
                actions += '<div class="lm-tg-actions">';
                if (m.can_update) {
                    actions += '<button type="button" class="lm-tg-action edit" data-message-id="' + esc(m.id) + '" data-message-text="' + esc(m.message || '') + '"><i class="fa fa-pencil"></i> Edit</button>';
                }
                if (m.can_delete) {
                    actions += '<button type="button" class="lm-tg-action delete" data-message-id="' + esc(m.id) + '"><i class="fa fa-trash"></i> Delete</button>';
                }
                actions += '</div>';
            }
            box.append(
                '<div class="lm-tg-row '+(m.is_own ? 'own' : '')+'">' +
                    '<div class="lm-tg-bubble">' + body +
                        '<div class="lm-tg-meta"><span>' + esc(formatTime(m.created_at)) + '</span>' + edited + ticks + '</div>' +
                        actions +
                    '</div>' +
                '</div>'
            );
        });
        box.scrollTop(box[0].scrollHeight);
    }

    function renderContacts(rows){
        contacts = rows || [];
        var list = $('#lmTgContactList').empty();
        if (!contacts.length) {
            list.html('<div class="lm-tg-empty-side">No customers found in your branch.</div>');
            return;
        }
        contacts.forEach(function(r){
            if (!r.customer_id) return;
            var name = r.display_name || r.customer_name || 'Customer';
            var sub = r.last_message ? ((r.last_sender_name ? r.last_sender_name + ': ' : '') + r.last_message) : (r.display_subtitle || r.customer_phone || 'New chat');
            var badge = Number(r.unread_count || 0) > 0 ? '<span class="lm-tg-contact-badge">' + Number(r.unread_count) + '</span>' : '';
            var avatar = r.avatar_url
                ? '<img src="' + esc(r.avatar_url) + '" alt="">'
                : esc(profileInitial(name));
            var item = $('<div class="lm-tg-contact" data-customer-id="'+r.customer_id+'" data-thread-id="'+(r.id || '')+'"></div>')
                .append('<div class="lm-tg-contact-avatar">' + avatar + '<span class="dot'+(r.telegram_linked ? ' linked' : '')+'"></span></div>')
                .append('<div class="lm-tg-contact-info"><div class="lm-tg-contact-name">' + esc(name) + '</div><div class="lm-tg-contact-sub">' + esc(sub) + '</div></div>')
                .append(badge);
            if (String(r.customer_id) === String(activeCustomerId)) item.addClass('active');
            item.on('click', function(){ openContact(r.customer_id, name, !!r.telegram_linked, {profile: r}); });
            list.append(item);
        });
    }

    function loadContacts(search){
        return apiGet(chatBaseUrl + '?search=' + encodeURIComponent(search || '')).then(function(resp){
            renderContacts(listData(resp));
        }).catch(function(){});
    }

    function loadThread(showLoading){
        if (!activeThreadId || loadingThread) return;
        loadingThread = true;
        if (showLoading) $('#lmTgMessages').html('<div class="lm-tg-empty">Loading conversation...</div>');
        apiGet(chatBaseUrl + '/' + activeThreadId).then(function(resp){
            var thread = singleData(resp);
            if (thread && String(thread.customer_id) === String(activeCustomerId)) {
                setHeader(thread.customer_profile || thread, !!thread.telegram_linked);
                activeCustomerName = profileName(thread.customer_profile || thread, activeCustomerName);
            }
            renderMessages(thread ? thread.messages : []);
            apiPostJson(chatBaseUrl + '/' + activeThreadId + '/read', {});
        }).catch(function(){}).finally(function(){ loadingThread = false; });
    }

    function startPolling(){
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = window.setInterval(function(){
            loadThread(false);
            loadContacts($('#lmTgSearchInput').val());
        }, pollMs);
    }
    function stopPolling(){
        if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
    }

    function openContact(customerId, name, linked, context){
        activeLoanContext = context || {};
        var initialProfile = activeLoanContext.profile || {display_name: name, telegram_linked: !!linked};
        activeCustomerName = profileName(initialProfile, name);
        activeCustomerId = customerId;
        $('.lm-tg-contact').removeClass('active');
        $('.lm-tg-contact[data-customer-id="'+customerId+'"]').addClass('active');
        $('#lmTgComposerForm').show();
        $('#lmTgTools').css('display', 'flex');
        setHeader(initialProfile, linked);
        $('#lmTgMessages').html('<div class="lm-tg-empty">Loading conversation...</div>');
        apiPostJson(chatBaseUrl, {customer_id: customerId}).then(function(resp){
            var thread = singleData(resp);
            if (thread && thread.id) {
                activeThreadId = thread.id;
                setHeader(thread.customer_profile || thread, !!thread.telegram_linked);
                activeCustomerName = profileName(thread.customer_profile || thread, activeCustomerName);
                loadThread(true);
                startPolling();
                if (activeLoanContext.auto_action === 'invoice') {
                    activeLoanContext.auto_action = '';
                    window.setTimeout(function(){ $('#lmTgSendInvoice').trigger('click'); }, 250);
                } else if (activeLoanContext.auto_action === 'pay') {
                    activeLoanContext.auto_action = '';
                    window.setTimeout(function(){ $('#lmTgSendPay').trigger('click'); }, 250);
                }
            } else {
                $('#lmTgMessages').html('<div class="lm-tg-empty">Unable to open this chat.</div>');
            }
        }).catch(function(){
            $('#lmTgMessages').html('<div class="lm-tg-empty">Unable to open this chat.</div>');
        });
    }

    function openDrawer(){
        $('#lmTgDrawer').addClass('open');
        $('#lmTgDrawerOverlay').addClass('open');
        $('#lmTgFab').addClass('open');

        loadContacts('');

        if (boundCustomerId && !activeThreadId) {
            openContact(boundCustomerId, boundCustomerName, boundTelegramLinked, {});
        }
    }

    function closeDrawer(){
        $('#lmTgDrawer').removeClass('open');
        $('#lmTgDrawerOverlay').removeClass('open');
        $('#lmTgFab').removeClass('open');
        stopPolling();
    }

    $('#lmTgFab').on('click', openDrawer);
    $('#lmTgDrawerCloseX, #lmTgDrawerOverlay').on('click', closeDrawer);

    $('#lmTgSearchInput').on('input', function(){
        var q = $(this).val();
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function(){ loadContacts(q); }, 300);
    });

    var sendingMessage = false;
    function showComposerError(message){
        var $err = $('#lmTgComposerError').text(message).show();
        window.clearTimeout(showComposerError._t);
        showComposerError._t = window.setTimeout(function(){ $err.fadeOut(200); }, 4000);
    }

    $('#lmTgComposerForm').on('submit', function(e){
        e.preventDefault();
        var text = $('#lmTgMessageInput').val().trim();
        if (!text || !activeThreadId || sendingMessage) return;

        sendingMessage = true;
        var $btn = $('#lmTgComposerForm button[type=submit]').prop('disabled', true);

        apiPostJson(chatBaseUrl + '/' + activeThreadId + '/messages', {message_type: 'text', message: text})
            .then(function(resp){
                if (resp && resp.success) {
                    $('#lmTgMessageInput').val('');
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to send message.');
                }
            })
            .catch(function(){
                showComposerError('Failed to send message. Check your connection.');
            })
            .finally(function(){
                sendingMessage = false;
                $btn.prop('disabled', false);
                $('#lmTgMessageInput').trigger('focus');
            });
    });

    function sendTelegramText(text){
        if (!text || !activeThreadId) return;
        return apiPostJson(chatBaseUrl + '/' + activeThreadId + '/messages', {message_type: 'text', message: text})
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to send message.');
                }
            })
            .catch(function(){ showComposerError('Failed to send message.'); });
    }

    function sendTelegramFile(file, type, caption, durationSeconds){
        if (!file || !activeThreadId) return $.Deferred().reject().promise();
        var fd = new FormData();
        fd.append('message_type', type);
        fd.append('file', file);
        fd.append('message', caption || '');
        if (durationSeconds) fd.append('duration_seconds', durationSeconds);

        return apiPostForm(chatBaseUrl + '/' + activeThreadId + '/messages', fd)
            .then(function(resp){
                if (!(resp && resp.success)) {
                    showComposerError((resp && resp.message) || 'Failed to send file.');
                }
                return resp;
            })
            .catch(function(){ showComposerError('Failed to send file.'); });
    }

    function sendSelectedFiles(files, type){
        files = Array.prototype.slice.call(files || []);
        if (!files.length) return;
        var caption = $('#lmTgMessageInput').val().trim();
        var chain = Promise.resolve();
        files.forEach(function(file){
            chain = chain.then(function(){ return sendTelegramFile(file, type, caption); });
        });
        chain.then(function(){
            $('#lmTgMessageInput').val('');
            loadThread(false);
            loadContacts($('#lmTgSearchInput').val());
        });
    }

    $('#lmTgSendInvoice').on('click', function(){
        var loanNo = activeLoanContext.loan_number ? ('Loan #: ' + activeLoanContext.loan_number + '\n') : '';
        var balance = activeLoanContext.balance_amount ? ('Balance: ' + activeLoanContext.balance_amount + '\n') : '';
        var text = window.prompt('Invoice message:', 'Dear ' + activeCustomerName + ',\n' + loanNo + balance + 'Please review your invoice and contact us if you have questions.');
        if (text) sendTelegramText(text);
    });

    $('#lmTgSendPay').on('click', function(){
        var balance = activeLoanContext.balance_amount ? (' Current balance: ' + activeLoanContext.balance_amount + '.') : '';
        var text = window.prompt('Payment request message:', 'Dear ' + activeCustomerName + ', please make your payment when convenient.' + balance);
        if (text) sendTelegramText(text);
    });

    $('#lmTgPickImages').on('click', function(){ $('#lmTgImageInput').trigger('click'); });
    $('#lmTgPickDocs').on('click', function(){ $('#lmTgDocInput').trigger('click'); });
    $('#lmTgImageInput').on('change', function(){ sendSelectedFiles(this.files, 'image'); this.value = ''; });
    $('#lmTgDocInput').on('change', function(){ sendSelectedFiles(this.files, 'file'); this.value = ''; });

    $('#lmTgSendLocation').on('click', function(){
        if (!navigator.geolocation) {
            showComposerError('Location is not available in this browser.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function(pos){
            apiPostJson(chatBaseUrl + '/' + activeThreadId + '/messages', {
                message_type: 'location',
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude
            }).then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                } else {
                    showComposerError((resp && resp.message) || 'Failed to send location.');
                }
            });
        }, function(){ showComposerError('Unable to get location permission.'); });
    });

    $('#lmTgVoiceBtn').on('click', function(){
        var $btn = $(this);
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            $btn.removeClass('recording').html('<i class="fa fa-microphone"></i> Voice');
            return;
        }
        if (!navigator.mediaDevices || !window.MediaRecorder) {
            showComposerError('Voice recording is not available in this browser.');
            return;
        }
        navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream){
            voiceChunks = [];
            voiceStartedAt = Date.now();
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = function(event){
                if (event.data && event.data.size) voiceChunks.push(event.data);
            };
            mediaRecorder.onstop = function(){
                stream.getTracks().forEach(function(track){ track.stop(); });
                var blob = new Blob(voiceChunks, {type: mediaRecorder.mimeType || 'audio/webm'});
                var file = new File([blob], 'voice-message.webm', {type: blob.type});
                var duration = Math.max(1, Math.round((Date.now() - voiceStartedAt) / 1000));
                sendTelegramFile(file, 'audio', '', duration).then(function(){
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                });
            };
            mediaRecorder.start();
            $btn.addClass('recording').html('<i class="fa fa-stop"></i> Stop');
        }).catch(function(){ showComposerError('Unable to access microphone.'); });
    });

    $('#lmTgMessages').on('click', '.lm-tg-action.edit', function(){
        if (!activeThreadId) return;
        var messageId = $(this).data('message-id');
        var currentText = $(this).data('message-text') || '';
        var nextText = window.prompt('Update Telegram message:', currentText);
        if (nextText === null) return;
        nextText = nextText.trim();
        if (!nextText) {
            showComposerError('Message cannot be empty.');
            return;
        }

        apiJson(chatBaseUrl + '/' + activeThreadId + '/messages/' + messageId, 'PUT', {message: nextText})
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to update message.');
                }
            })
            .catch(function(){
                showComposerError('Failed to update message.');
            });
    });

    $('#lmTgMessages').on('click', '.lm-tg-action.delete', function(){
        if (!activeThreadId) return;
        var messageId = $(this).data('message-id');
        if (!window.confirm('Delete this Telegram chat message?')) {
            return;
        }

        apiJson(chatBaseUrl + '/' + activeThreadId + '/messages/' + messageId, 'DELETE', {})
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to delete message.');
                }
            })
            .catch(function(){
                showComposerError('Failed to delete message.');
            });
    });

    window.loanManagementOpenTelegramCustomer = function(customerId, name, linked, context){
        $('#lmTgDrawer').addClass('open');
        $('#lmTgDrawerOverlay').addClass('open');
        $('#lmTgFab').addClass('open');
        loadContacts('');
        openContact(customerId, name || 'Customer', !!linked, context || {});
    };
})(jQuery);
</script>
