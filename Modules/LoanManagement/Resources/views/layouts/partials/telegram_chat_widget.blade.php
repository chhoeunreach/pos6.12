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
    .lm-tg-empty{text-align:center;color:#94a3b8;font-size:12px;margin-top:30px}
    .lm-tg-composer{flex:0 0 auto;background:#fff;padding:9px 10px;display:flex;gap:8px;align-items:center;border-top:1px solid #e2e8f0}
    .lm-tg-composer input[type=text]{flex:1;border:1px solid #d1d5db;background:#f4f6f8;border-radius:20px;padding:9px 15px;outline:none;font-size:13px}
    .lm-tg-composer input[type=text]:focus{border-color:#54a9eb;background:#fff}
    .lm-tg-composer button{border:0;background:linear-gradient(135deg,#6dc9f7,#2894e0);color:#fff;width:38px;height:38px;border-radius:50%;flex:0 0 auto;cursor:pointer;font-size:14px}
    .lm-tg-composer button:disabled{opacity:.5;cursor:default}
    .lm-tg-not-linked{padding:9px 14px;background:#fff7ed;border-bottom:1px solid #fde68a;font-size:11.5px;color:#92400e;flex:0 0 auto}

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
        return fetch(url, {
            method: 'POST',
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

    function setHeader(name, linked, statusText){
        $('#lmTgHeaderAvatar').html(esc((name || 'C').charAt(0).toUpperCase()));
        $('#lmTgHeaderName').text(name || 'Customer');
        $('#lmTgHeaderStatus').toggleClass('online', !!linked);
        $('#lmTgHeaderStatusText').text(statusText || (linked ? 'connected via Telegram' : 'not connected'));
        $('#lmTgNotLinkedBanner').toggle(!linked);
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
            var ticks = '';
            if (m.is_own) {
                var isRead = !!m.read_at;
                ticks = '<span class="lm-tg-ticks' + (isRead ? ' read' : '') + '">' + (isRead ? '&#10003;&#10003;' : '&#10003;') + '</span>';
            }
            box.append(
                '<div class="lm-tg-row '+(m.is_own ? 'own' : '')+'">' +
                    '<div class="lm-tg-bubble">' + body +
                        '<div class="lm-tg-meta"><span>' + esc(formatTime(m.created_at)) + '</span>' + ticks + '</div>' +
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
            var item = $('<div class="lm-tg-contact" data-customer-id="'+r.customer_id+'" data-thread-id="'+(r.id || '')+'"></div>')
                .append('<div class="lm-tg-contact-avatar">' + esc(name.charAt(0).toUpperCase()) + '<span class="dot'+(r.telegram_linked ? ' linked' : '')+'"></span></div>')
                .append('<div class="lm-tg-contact-info"><div class="lm-tg-contact-name">' + esc(name) + '</div><div class="lm-tg-contact-sub">' + esc(sub) + '</div></div>')
                .append(badge);
            if (String(r.customer_id) === String(activeCustomerId)) item.addClass('active');
            item.on('click', function(){ openContact(r.customer_id, name, !!r.telegram_linked); });
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

    function openContact(customerId, name, linked){
        activeCustomerId = customerId;
        $('.lm-tg-contact').removeClass('active');
        $('.lm-tg-contact[data-customer-id="'+customerId+'"]').addClass('active');
        $('#lmTgComposerForm').show();
        setHeader(name, linked);
        $('#lmTgMessages').html('<div class="lm-tg-empty">Loading conversation...</div>');
        apiPostJson(chatBaseUrl, {customer_id: customerId}).then(function(resp){
            var thread = singleData(resp);
            if (thread && thread.id) {
                activeThreadId = thread.id;
                loadThread(true);
                startPolling();
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
            openContact(boundCustomerId, boundCustomerName, boundTelegramLinked);
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
})(jQuery);
</script>
