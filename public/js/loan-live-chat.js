(function (window, document) {
    'use strict';

    function LiveChat(root, options) {
        this.root = root;
        this.options = options || {};
        this.api = root.dataset.api.replace(/\/$/, '');
        this.contacts = [];
        this.messages = new Map();
        this.cursor = 0;
        this.drafts = new Map();
        this.active = null;
        this.generation = 0;
        this.timer = null;
        this.searchTimer = null;
        this.lastListAt = 0;
        this.listGeneration = 0;
        this.failures = 0;
        this.busy = false;
        this.sending = false;
        this.expired = false;
        this.stopped = false;
        this.unreadOnly = false;
        this.threadController = null;
        this.lastTypingAt = 0;
        this.initialThread = Number(root.dataset.initialThread) || null;
        this.initialCustomer = Number(root.dataset.initialCustomer) || null;
        this.el = {};
        var self = this;
        ['Connection', 'Error', 'Refresh', 'Search', 'Contacts', 'FilterAll', 'FilterUnread', 'UnreadCount',
            'Empty', 'Thread', 'Avatar', 'Name', 'Presence', 'Customer', 'ToggleClosed', 'Older', 'Messages',
            'Typing', 'Composer', 'Draft', 'File', 'Attachment', 'AttachmentName', 'RemoveAttachment', 'Send',
            'SendStatus', 'Back', 'NewMessages'].forEach(function (name) { self.el[name] = root.querySelector('#lc' + name); });
        this.bind();
    }

    LiveChat.prototype.connection = function (state, text) {
        if (this.el.Connection.dataset.state !== state) this.el.Connection.dataset.state = state;
        if (this.el.Connection.textContent !== text) this.el.Connection.textContent = text;
    };
    LiveChat.prototype.error = function (text) {
        this.el.Error.textContent = text || '';
        this.el.Error.hidden = !text;
    };
    LiveChat.prototype.request = async function (url, options) {
        options = options || {};
        var controller = options.controller || new window.AbortController();
        var timeout = window.setTimeout(function () { controller.abort(); }, 20000);
        var config = { credentials: 'same-origin', signal: controller.signal, method: options.body ? 'POST' : 'GET',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.root.dataset.csrf } };
        if (options.body instanceof window.FormData) config.body = options.body;
        else if (options.body) { config.headers['Content-Type'] = 'application/json'; config.body = JSON.stringify(options.body); }
        try {
            var response = await (this.options.fetch || window.fetch.bind(window))(url, config);
            if ([401, 403, 419].indexOf(response.status) !== -1) {
                this.expired = true;
                this.connection('error', response.status === 403 ? 'Access denied' : 'Session expired');
                this.el.Send.disabled = true;
                throw new Error(response.status === 403 ? 'You no longer have access to this action. Reload the page to check your permissions.' : 'Your session has expired. Reload the page to sign in again.');
            }
            var payload;
            try { payload = await response.json(); } catch (e) { throw new Error('The server returned an unexpected response. Please try again.'); }
            if (!response.ok || payload.success === false) {
                var first = payload.errors && Object.values(payload.errors)[0];
                throw new Error((first && first[0]) || payload.message || 'Unable to complete this request.');
            }
            return payload.data;
        } finally { window.clearTimeout(timeout); }
    };
    LiveChat.prototype.bind = function () {
        var self = this;
        this.el.Refresh.addEventListener('click', function () {
            if (self.expired) { window.location.reload(); return; }
            self.lastListAt = 0; self.refresh();
        });
        this.el.Search.addEventListener('input', function () {
            window.clearTimeout(self.searchTimer);
            self.listGeneration++;
            self.searchTimer = window.setTimeout(function () { self.loadList(false).catch(function (e) { self.error(e.message); }); }, 300);
        });
        [this.el.FilterAll, this.el.FilterUnread].forEach(function (button, index) {
            button.addEventListener('click', function () {
                self.unreadOnly = index === 1;
                self.el.FilterAll.classList.toggle('active', !self.unreadOnly);
                self.el.FilterUnread.classList.toggle('active', self.unreadOnly);
                self.el.FilterAll.setAttribute('aria-pressed', String(!self.unreadOnly));
                self.el.FilterUnread.setAttribute('aria-pressed', String(self.unreadOnly));
                self.renderContacts();
            });
        });
        this.el.Contacts.addEventListener('click', function (event) {
            var button = event.target.closest('[data-contact-index]');
            if (button) self.open(self.contacts[Number(button.dataset.contactIndex)]);
        });
        this.el.Back.addEventListener('click', function () { self.root.classList.remove('thread-open'); });
        this.el.Composer.addEventListener('submit', function (event) { event.preventDefault(); self.send(); });
        this.el.Draft.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); self.send(); }
        });
        this.el.Draft.addEventListener('input', function () { self.saveDraft(); self.typing(); });
        if (this.el.File) this.el.File.addEventListener('change', function () {
            var file = self.el.File.files[0]; self.el.File.value = '';
            if (!file || !self.active) return;
            if (file.size > 50 * 1024 * 1024) { self.error('Attachments must be 50 MB or smaller.'); return; }
            var draft = self.draft(); draft.file = file; draft.pending = null; self.renderComposer();
        });
        this.el.RemoveAttachment.addEventListener('click', function () {
            if (!self.active) return;
            var draft = self.draft(); draft.file = null; draft.pending = null; self.renderComposer();
        });
        this.el.Older.addEventListener('click', function () { self.loadMessages('older').catch(function (e) { self.error(e.message); }); });
        this.el.NewMessages.addEventListener('click', function () {
            self.scrollBottom(); self.markRead();
        });
        if (this.el.ToggleClosed) this.el.ToggleClosed.addEventListener('click', async function () {
            if (!self.active) return;
            var id = self.active.id; var closed = self.closed();
            self.el.ToggleClosed.disabled = true;
            try {
                await self.request(self.api + '/' + id + (closed ? '/reopen' : '/close'), { body: {} });
                if (self.active && self.active.id === id) {
                    self.active.status = closed ? 'open' : 'closed'; self.active.is_closed = !closed;
                    self.renderHeader(); self.renderComposer();
                }
                self.lastListAt = 0;
            } catch (e) { self.error(e.message); }
            finally { self.el.ToggleClosed.disabled = false; }
        });
        this.visibilityHandler = function () {
            if (!document.hidden) { self.lastListAt = 0; self.refresh(); }
            else self.connection('paused', 'Updates paused');
        };
        this.onlineHandler = function () { self.refresh(); };
        this.offlineHandler = function () { self.connection('error', 'Offline · reconnecting'); };
        this.unloadHandler = function () { self.destroy(); };
        document.addEventListener('visibilitychange', this.visibilityHandler);
        window.addEventListener('online', this.onlineHandler);
        window.addEventListener('offline', this.offlineHandler);
        window.addEventListener('pagehide', this.unloadHandler);
    };
    LiveChat.prototype.closed = function () { return this.active && (this.active.is_closed || this.active.status === 'closed'); };
    LiveChat.prototype.draft = function (id) {
        id = id || (this.active && this.active.id);
        if (!this.drafts.has(id)) this.drafts.set(id, { text: '', file: null, pending: null });
        return this.drafts.get(id);
    };
    LiveChat.prototype.saveDraft = function () {
        if (!this.active) return;
        var draft = this.draft();
        if (draft.text !== this.el.Draft.value) draft.pending = null;
        draft.text = this.el.Draft.value;
    };
    LiveChat.prototype.renderComposer = function () {
        if (!this.active) return;
        var draft = this.draft();
        this.el.Draft.value = draft.text;
        this.el.Draft.disabled = this.closed() || this.root.dataset.canSend !== '1';
        this.el.Attachment.hidden = !draft.file;
        this.el.AttachmentName.textContent = draft.file ? draft.file.name : '';
        this.el.Send.disabled = this.sending || this.closed() || this.expired || this.root.dataset.canSend !== '1';
        this.el.Send.querySelector('span').textContent = this.sending ? 'Sending…' : draft.pending ? 'Retry' : 'Send';
        this.el.SendStatus.textContent = this.sending ? 'Sending…' : this.closed() ? 'This conversation is closed.' : draft.pending ? 'Delivery unconfirmed. Retry safely using the same message ID.' : '';
    };
    LiveChat.prototype.loadList = async function (threadsOnly) {
        var generation = ++this.listGeneration;
        var search = this.el.Search.value.trim();
        var incremental = threadsOnly && !search && this.contacts.length > 0;
        var params = new window.URLSearchParams({ view: 'all', search: search });
        if (incremental) params.set('threads_only', '1');
        var rows = await this.request(this.api + '?' + params);
        if (generation !== this.listGeneration || this.stopped) return;
        rows = Array.isArray(rows) ? rows : [];
        if (incremental) {
            var customerIds = new Set(rows.map(function (row) { return Number(row.customer_id); }));
            rows = rows.concat(this.contacts.filter(function (row) { return !row.id && !customerIds.has(Number(row.customer_id)); }));
        }
        this.contacts = rows;
        this.lastListAt = Date.now();
        this.renderContacts();
        if (this.initialCustomer) {
            var contact = rows.find(function (row) { return Number(row.customer_id) === this.initialCustomer; }, this);
            this.initialCustomer = null;
            if (contact) this.open(contact);
        }
    };
    LiveChat.prototype.renderContacts = function () {
        var self = this; var fragment = document.createDocumentFragment(); var count = 0;
        this.contacts.forEach(function (contact, index) {
            var unread = Number(contact.unread_count || 0); count += unread;
            if (self.unreadOnly && !unread) return;
            var button = document.createElement('button'); button.type = 'button';
            button.className = 'lc-contact'; button.dataset.contactIndex = index;
            var selected = self.active && String(contact.id) === String(self.active.id);
            button.classList.toggle('active', !!selected); button.setAttribute('aria-current', selected ? 'true' : 'false');
            var name = contact.display_name || contact.customer_name || 'Customer';
            var avatar = document.createElement('span'); avatar.className = 'lc-avatar'; avatar.textContent = name.slice(0, 1).toUpperCase();
            var info = document.createElement('span'); info.className = 'lc-contact-info';
            var title = document.createElement('strong'); title.textContent = name;
            var preview = document.createElement('small'); preview.textContent = contact.typing ? 'Typing…' : contact.last_message || contact.display_subtitle || (contact.id ? 'No messages yet' : 'Start a new conversation');
            info.append(title, preview);
            var meta = document.createElement('span'); meta.className = 'lc-contact-meta';
            var time = document.createElement('span'); time.textContent = self.time(contact.last_message_at_iso || contact.last_message_at); meta.appendChild(time);
            if (unread) { var badge = document.createElement('span'); badge.className = 'lc-unread'; badge.textContent = unread > 99 ? '99+' : unread; meta.appendChild(badge); }
            button.append(avatar, info, meta); fragment.appendChild(button);
        });
        this.el.UnreadCount.textContent = count;
        this.el.Contacts.replaceChildren(fragment);
        if (!this.el.Contacts.children.length) {
            var empty = document.createElement('p'); empty.className = 'lc-placeholder';
            empty.textContent = this.unreadOnly ? 'You’re all caught up.' : 'No matching customers or conversations.'; this.el.Contacts.appendChild(empty);
        }
    };
    LiveChat.prototype.open = async function (contact) {
        if (!contact || this.stopped) return;
        this.saveDraft();
        var generation = ++this.generation;
        if (this.threadController) this.threadController.abort();
        this.threadController = null;
        this.active = null;
        this.messages.clear();
        this.cursor = 0;
        this.el.Messages.replaceChildren(); this.el.Typing.textContent = '';
        this.el.Empty.hidden = true; this.el.Thread.hidden = false;
        this.el.Older.hidden = true; this.el.NewMessages.hidden = true;
        this.root.classList.add('thread-open');
        this.el.Name.textContent = contact.display_name || contact.customer_name || 'Opening conversation…';
        this.el.Presence.textContent = 'Loading…'; this.el.Draft.disabled = true; this.el.Send.disabled = true;
        try {
            if (!contact.id) contact = await this.request(this.api, { body: { customer_id: contact.customer_id, subject: 'Customer support' } });
            if (generation !== this.generation || this.stopped) return;
            this.active = contact;
            this.renderHeader(); this.renderComposer(); this.renderContacts();
            await this.loadMessages('initial');
            if (generation !== this.generation) return;
            var url = new window.URL(this.root.dataset.liveBase || window.location.href); url.searchParams.set('thread', contact.id); url.searchParams.delete('customer_id');
            window.history.replaceState(null, '', url);
            this.error('');
        } catch (e) {
            if (generation === this.generation && e.name !== 'AbortError') { this.error(e.message); this.el.Presence.textContent = 'Unable to load conversation. Select it again to retry.'; }
        }
    };
    LiveChat.prototype.renderHeader = function () {
        if (!this.active) return;
        var contact = this.active; var name = contact.display_name || contact.customer_name || 'Customer';
        this.el.Name.textContent = name; this.el.Avatar.textContent = name.slice(0, 1).toUpperCase();
        this.el.Presence.textContent = this.closed() ? 'Closed conversation' : contact.is_online ? 'Online now' : contact.display_subtitle || contact.customer_phone || 'Customer app conversation';
        var typing = contact.typing && !this.closed() ? name + ' is typing…' : '';
        if (this.el.Typing.textContent !== typing) this.el.Typing.textContent = typing;
        this.el.Customer.hidden = !contact.customer_id;
        if (contact.customer_id) this.el.Customer.href = this.root.dataset.customerBase + '/' + Number(contact.customer_id);
        if (this.el.ToggleClosed) this.el.ToggleClosed.textContent = this.closed() ? 'Reopen chat' : 'Close chat';
    };
    LiveChat.prototype.nearBottom = function () {
        var box = this.el.Messages; return box.scrollHeight - box.scrollTop - box.clientHeight < 100;
    };
    LiveChat.prototype.scrollBottom = function () {
        this.el.Messages.scrollTop = this.el.Messages.scrollHeight; this.el.NewMessages.hidden = true;
    };
    LiveChat.prototype.loadMessages = async function (mode) {
        if (!this.active) return;
        // A previous conversation must not hold the new conversation's request lock.
        if (this.threadController) return;
        var id = this.active.id; var generation = this.generation;
        var params = new window.URLSearchParams({ message_limit: '40', lightweight: '1', skip_read: document.hidden || !this.nearBottom() ? '1' : '0' });
        var ids = Array.from(this.messages.keys()).sort(function (a, b) { return a - b; });
        if (mode === 'older' && ids.length) params.set('before_message_id', ids[0]);
        else if (mode === 'updates' && this.cursor) params.set('after_message_id', this.cursor);
        ids.filter(function (key) { return this.messages.get(key).is_own; }, this).slice(-40).forEach(function (key) { params.append('receipt_ids[]', key); });
        var controller = new window.AbortController(); this.threadController = controller;
        this.el.Older.disabled = true;
        var box = this.el.Messages; var previousHeight = box.scrollHeight; var previousTop = box.scrollTop;
        var stick = mode === 'initial' || this.nearBottom();
        try {
            var data = await this.request(this.api + '/' + id + '?' + params, { controller: controller });
            if (generation !== this.generation || this.stopped) return;
            this.active = Object.assign({}, this.active, data);
            this.renderHeader();
            var incoming = data.messages || []; var receipts = data.message_receipts || [];
            var previousNewest = ids.length ? ids[ids.length - 1] : 0;
            if (mode !== 'older' && incoming.length) {
                this.cursor = Math.max(this.cursor, Math.max.apply(null, incoming.map(function (message) { return Number(message.id); })));
            }
            receipts.forEach(function (receipt) {
                var message = this.messages.get(Number(receipt.id));
                if (message) { Object.assign(message, receipt); this.updateReceipt(message); }
            }, this);
            var fresh = incoming.filter(function (message) { return !this.messages.has(Number(message.id)); }, this);
            incoming.forEach(function (message) { this.messages.set(Number(message.id), message); }, this);
            if (mode === 'initial' || mode === 'older') {
                this.renderMessages();
                this.el.Older.hidden = !(data.message_pagination && data.message_pagination.has_more_older);
                if (mode === 'older') box.scrollTop = previousTop + box.scrollHeight - previousHeight;
            } else {
                if (fresh.some(function (message) { return Number(message.id) < previousNewest; })) {
                    this.renderMessages();
                } else {
                    if (fresh.length) box.querySelectorAll('.lc-placeholder').forEach(function (el) { el.remove(); });
                    fresh.sort(function (a, b) { return Number(a.id) - Number(b.id); }).forEach(function (message) { box.appendChild(this.messageElement(message)); }, this);
                }
                if (!params.has('after_message_id')) this.el.Older.hidden = !(data.message_pagination && data.message_pagination.has_more_older);
                if (fresh.length && !stick) this.el.NewMessages.hidden = false;
            }
            if (stick && mode !== 'older') this.scrollBottom();
            if (!document.hidden && stick && mode !== 'older') {
                this.contacts.forEach(function (row) { if (String(row.id) === String(id)) row.unread_count = 0; });
                this.renderContacts();
            }
            this.renderComposer();
        } finally {
            if (this.threadController === controller) { this.threadController = null; this.el.Older.disabled = false; }
        }
    };
    LiveChat.prototype.time = function (value) {
        if (!value) return '';
        var date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };
    LiveChat.prototype.safeUrl = function (value) {
        if (!value) return '';
        try { var url = new window.URL(value, window.location.href); return /^https?:$/.test(url.protocol) ? url.href : ''; } catch (e) { return ''; }
    };
    LiveChat.prototype.receiptText = function (message) { return message.read_at ? 'Read' : 'Sent'; };
    LiveChat.prototype.updateReceipt = function (message) {
        var row = this.el.Messages.querySelector('[data-message-id="' + Number(message.id) + '"] .lc-receipt');
        if (row) { row.textContent = this.receiptText(message); row.classList.toggle('read', !!message.read_at); }
    };
    LiveChat.prototype.messageElement = function (message) {
        var row = document.createElement('article'); row.className = 'lc-message'; row.classList.toggle('own', !!message.is_own); row.dataset.messageId = Number(message.id);
        var bubble = document.createElement('div'); bubble.className = 'lc-bubble';
        if (message.file) {
            var href = this.safeUrl(message.file.url || '');
            if (href) {
                var link = document.createElement('a'); link.href = href; link.target = '_blank'; link.rel = 'noopener noreferrer';
                if (message.message_type === 'image') { var img = document.createElement('img'); img.src = href; img.alt = message.file.name || 'Image attachment'; img.loading = 'lazy'; link.appendChild(img); }
                link.appendChild(document.createTextNode(message.file.name || 'Open attachment')); bubble.appendChild(link);
            }
        }
        if (message.message_type === 'location' && message.location) {
            var lat = Number(message.location.latitude); var lng = Number(message.location.longitude);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                var map = document.createElement('a'); map.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng); map.target = '_blank'; map.rel = 'noopener noreferrer'; map.textContent = message.location.address || 'View shared location'; bubble.appendChild(map);
            }
        }
        if (message.message) { var text = document.createElement('div'); text.textContent = message.message; bubble.appendChild(text); }
        var meta = document.createElement('div'); meta.className = 'lc-message-meta';
        var time = document.createElement('time'); time.textContent = this.time(message.created_at_iso || message.created_at); time.title = message.created_at_display || message.created_at || '';
        meta.appendChild(time);
        if (message.is_own) { var receipt = document.createElement('span'); receipt.className = 'lc-receipt'; receipt.classList.toggle('read', !!message.read_at); receipt.textContent = this.receiptText(message); meta.appendChild(receipt); }
        row.append(bubble, meta); return row;
    };
    LiveChat.prototype.renderMessages = function () {
        var fragment = document.createDocumentFragment();
        Array.from(this.messages.values()).sort(function (a, b) { return Number(a.id) - Number(b.id); }).forEach(function (message) { fragment.appendChild(this.messageElement(message)); }, this);
        this.el.Messages.replaceChildren(fragment);
        if (!this.messages.size) { var empty = document.createElement('p'); empty.className = 'lc-placeholder'; empty.textContent = 'No messages yet. Say hello to start the conversation.'; this.el.Messages.appendChild(empty); }
    };
    LiveChat.prototype.markRead = function () {
        if (!this.active || document.hidden) return;
        this.request(this.api + '/' + this.active.id + '/read', { body: {} }).catch(function () {});
    };
    LiveChat.prototype.typing = function () {
        if (!this.active || this.closed() || this.expired || this.root.dataset.canType !== '1' || Date.now() - this.lastTypingAt < 4000) return;
        this.lastTypingAt = Date.now();
        this.request(this.api + '/' + this.active.id + '/typing', { body: {} }).catch(function () {});
    };
    LiveChat.prototype.send = async function () {
        if (!this.active || this.closed() || this.sending || this.expired || this.root.dataset.canSend !== '1') return;
        this.saveDraft();
        var id = this.active.id; var draft = this.draft(id); var text = draft.text.trim(); var file = draft.file;
        if (!text && !file) return;
        var pending = draft.pending || { uuid: window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Date.now() + '-' + Math.random().toString(36).slice(2), text: text, file: file };
        draft.pending = pending;
        var body = new window.FormData(); body.append('message_type', file ? /^image\//.test(file.type) ? 'image' : 'file' : 'text'); body.append('message', text); body.append('local_uuid', pending.uuid);
        if (file) body.append('file', file, file.name);
        this.sending = true; this.renderComposer(); this.el.SendStatus.textContent = 'Sending…';
        try {
            var message = await this.request(this.api + '/' + id + '/messages', { body: body });
            if (draft.pending === pending) { draft.text = ''; draft.file = null; draft.pending = null; }
            if (this.active && this.active.id === id) {
                var fresh = !this.messages.has(Number(message.id)); this.messages.set(Number(message.id), message);
                this.el.Messages.querySelectorAll('.lc-placeholder').forEach(function (el) { el.remove(); });
                if (fresh) this.el.Messages.appendChild(this.messageElement(message));
                this.scrollBottom(); this.error('');
            }
            this.lastListAt = 0;
        } catch (e) { this.error(e.name === 'AbortError' ? 'Delivery could not be confirmed. Retry to check the same message.' : e.message); }
        finally { this.sending = false; this.renderComposer(); }
    };
    LiveChat.prototype.refresh = async function () {
        if (this.stopped || this.expired) return;
        window.clearTimeout(this.timer);
        if (this.busy) return;
        if (document.hidden || window.navigator.onLine === false) {
            this.connection(document.hidden ? 'paused' : 'error', document.hidden ? 'Updates paused' : 'Offline · reconnecting');
            this.schedule(10000); return;
        }
        this.busy = true;
        try {
            var requests = [];
            if (!this.lastListAt || Date.now() - this.lastListAt > 6000) requests.push(this.loadList(true));
            if (this.active) requests.push(this.loadMessages('updates'));
            var results = await Promise.allSettled(requests);
            var failure = results.find(function (result) { return result.status === 'rejected' && result.reason.name !== 'AbortError'; });
            if (failure) throw failure.reason;
            this.failures = 0; this.connection('live', 'Live updates'); this.error('');
            this.el.Connection.title = 'Updated ' + new Date().toLocaleTimeString();
        } catch (e) {
            if (!this.expired && e.name !== 'AbortError') {
                this.failures++; this.connection('error', 'Reconnecting…'); this.error(e.message);
            }
        } finally {
            this.busy = false;
            if (!this.stopped && !this.expired) this.schedule(Math.min(30000, 2000 * Math.pow(2, this.failures)));
        }
    };
    LiveChat.prototype.schedule = function (delay) {
        var self = this; window.clearTimeout(this.timer); this.timer = window.setTimeout(function () { self.refresh(); }, delay);
    };
    LiveChat.prototype.start = function () {
        if (this.initialThread) this.open({ id: this.initialThread });
        this.refresh(); return this;
    };
    LiveChat.prototype.destroy = function () {
        this.stopped = true; window.clearTimeout(this.timer); window.clearTimeout(this.searchTimer);
        if (this.threadController) this.threadController.abort();
        document.removeEventListener('visibilitychange', this.visibilityHandler);
        window.removeEventListener('online', this.onlineHandler); window.removeEventListener('offline', this.offlineHandler); window.removeEventListener('pagehide', this.unloadHandler);
    };
    window.LoanLiveChat = LiveChat;
    function boot() { var root = document.getElementById('loanLiveChat'); if (root && !root.liveChat) root.liveChat = new LiveChat(root).start(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})(window, document);
