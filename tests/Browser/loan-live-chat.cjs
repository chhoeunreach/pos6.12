const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const { JSDOM } = require('../../storage/app/stock-ui-tests/node_modules/jsdom');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/loan-live-chat.js'), 'utf8');
const markup = fs.readFileSync(path.join(__dirname, '../../Modules/LoanManagement/Resources/views/chat/live.blade.php'), 'utf8')
    .replace(/@php[\s\S]*?@endphp/g, '').replace(/\{\{[\s\S]*?\}\}/g, '').replace(/@if\([^)]*\)|@endif/g, '');
const tick = () => new Promise(resolve => setImmediate(resolve));
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => ({ success: status < 400, data, message: status < 400 ? 'OK' : 'Network problem' }) });
const message = (id, text, own = false) => ({ id, message: text, message_type: 'text', is_own: own, created_at_iso: '2026-10-29T12:00:00+07:00' });
async function setup(fetch) {
    const dom = new JSDOM(markup, { url: 'https://example.test/loan-management/chat?channel=live', runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    const document = window.document;
    if (document.readyState === 'loading') await new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    const root = document.getElementById('loanLiveChat');
    root.id = 'liveChatTest';
    Object.assign(root.dataset, { api: 'https://example.test/chat-api/chats', canSend: '1', canType: '1', canClose: '1', csrf: 'test-token', customerBase: 'https://example.test/customers' });
    window.eval(source);
    const app = new window.LoanLiveChat(root, { fetch });
    return { app, window, document, close() { app.destroy(); dom.window.close(); } };
}
function thread(id, messages = []) { return { id, display_name: id === 1 ? 'Alice' : 'Bob', status: 'open', messages, message_pagination: { has_more_older: false } }; }

test('switching during an in-flight load ignores stale results and immediately loads the new thread', async () => {
    let resolveOld;
    const state = await setup(url => {
        const id = Number(new URL(url).pathname.split('/').pop());
        if (id === 1) return new Promise(resolve => { resolveOld = resolve; });
        return Promise.resolve(response(thread(2, [message(20, 'Hello Bob')])));
    });
    try {
        const first = state.app.open({ id: 1, display_name: 'Alice' });
        await tick();
        await state.app.open({ id: 2, display_name: 'Bob' });
        assert.equal(state.app.el.Name.textContent, 'Bob');
        assert.match(state.app.el.Messages.textContent, /Hello Bob/);
        resolveOld(response(thread(1, [message(10, 'Old Alice response')])));
        await first;
        assert.doesNotMatch(state.app.el.Messages.textContent, /Old Alice response/);
        assert.equal(state.app.active.id, 2);
    } finally { state.close(); }
});

test('failed sends keep the draft and reuse their message UUID on retry without rendering unsafe HTML', async () => {
    const uuids = []; let sends = 0;
    const state = await setup(async (url, options) => {
        if (options.method === 'POST' && url.endsWith('/messages')) {
            uuids.push(options.body.get('local_uuid'));
            if (++sends === 1) return response(null, 500);
            return response(message(101, '<img src=x onerror=alert(1)>', true));
        }
        return response(thread(1));
    });
    try {
        await state.app.open({ id: 1 });
        state.app.el.Draft.value = '<img src=x onerror=alert(1)>';
        await state.app.send();
        assert.equal(state.app.el.Draft.value, '<img src=x onerror=alert(1)>');
        assert.equal(state.app.el.Send.querySelector('span').textContent, 'Retry');
        await state.app.send();
        assert.equal(uuids.length, 2);
        assert.equal(uuids[0], uuids[1]);
        assert.equal(state.app.el.Draft.value, '');
        assert.equal(state.app.el.Messages.querySelectorAll('[data-message-id="101"]').length, 1);
        assert.equal(state.app.el.Messages.querySelectorAll('img').length, 0);
    } finally { state.close(); }
});

test('incremental updates deduplicate messages, refresh receipts and preserve the current draft', async () => {
    let updates = false; const requests = [];
    const state = await setup(async url => {
        requests.push(url);
        return response(updates ? Object.assign(thread(1, [message(2, 'New customer reply')]), { message_receipts: [{ id: 1, read_at: '2026-10-29 12:01:00' }] }) : thread(1, [message(1, 'Original staff message', true)]));
    });
    try {
        await state.app.open({ id: 1 });
        state.app.el.Draft.value = 'Keep this unsent draft'; state.app.saveDraft();
        updates = true;
        await state.app.loadMessages('updates'); await state.app.loadMessages('updates');
        assert.equal(state.app.el.Messages.querySelectorAll('.lc-message').length, 2);
        assert.equal(state.app.el.Messages.querySelector('[data-message-id="1"] .lc-receipt').textContent, 'Read');
        assert.equal(state.app.el.Draft.value, 'Keep this unsent draft');
        assert.equal(new URL(requests[1]).searchParams.get('after_message_id'), '1');
        assert.equal(new URL(requests[1]).searchParams.get('receipt_ids[]'), '1');
    } finally { state.close(); }
});

test('drafts stay with their conversation when sending finishes after switching chats', async () => {
    let finishSend; const posts = [];
    const state = await setup((url, options) => {
        if (options.method === 'POST') { posts.push(url); return new Promise(resolve => { finishSend = resolve; }); }
        return Promise.resolve(response(thread(Number(new URL(url).pathname.split('/').pop()))));
    });
    try {
        await state.app.open({ id: 1 });
        state.app.el.Draft.value = 'For Alice'; const send = state.app.send(); await tick();
        await state.app.open({ id: 2 });
        state.app.el.Draft.value = 'Unsent for Bob'; state.app.saveDraft();
        finishSend(response(message(99, 'For Alice', true))); await send;
        assert.equal(posts[0], 'https://example.test/chat-api/chats/1/messages');
        assert.equal(state.app.el.Draft.value, 'Unsent for Bob');
        assert.doesNotMatch(state.app.el.Messages.textContent, /For Alice/);
        await state.app.open({ id: 1 });
        assert.equal(state.app.el.Draft.value, '');
    } finally { state.close(); }
});

test('hidden tabs pause polling and do not mark messages read', async () => {
    const urls = [];
    const state = await setup(async url => { urls.push(url); return response(thread(1)); });
    try {
        Object.defineProperty(state.document, 'hidden', { value: true, configurable: true });
        await state.app.refresh();
        assert.equal(urls.length, 0);
        assert.equal(state.app.el.Connection.dataset.state, 'paused');
        await state.app.open({ id: 1 });
        assert.equal(new URL(urls[0]).searchParams.get('skip_read'), '1');
        state.app.markRead(); await tick();
        assert.equal(urls.length, 1);
    } finally { state.close(); }
});

test('polls wait for outstanding requests and authentication errors stop retries', async () => {
    let finish; let calls = 0;
    const state = await setup(() => { calls++; return new Promise(resolve => { finish = resolve; }); });
    try {
        const first = state.app.refresh(); await tick(); await state.app.refresh();
        assert.equal(calls, 1);
        finish(response(null, 401)); await first;
        assert.equal(state.app.expired, true);
        assert.equal(state.app.el.Send.disabled, true);
        await state.app.refresh(); assert.equal(calls, 1);
    } finally { state.close(); }
});

test('an outbound acknowledgement cannot skip an incoming message that arrived immediately before it', async () => {
    let update = false; const urls = [];
    const state = await setup(async (url, options) => {
        urls.push(url);
        if (options.method === 'POST') return response(message(12, 'Staff reply', true));
        return response(thread(1, update ? [message(11, 'Customer raced the send'), message(12, 'Staff reply', true)] : [message(10, 'Earlier message')]));
    });
    try {
        await state.app.open({ id: 1 });
        state.app.el.Draft.value = 'Staff reply'; await state.app.send();
        update = true; await state.app.loadMessages('updates');
        assert.equal(new URL(urls[2]).searchParams.get('after_message_id'), '10');
        assert.deepEqual(Array.from(state.app.el.Messages.querySelectorAll('[data-message-id]'), el => Number(el.dataset.messageId)), [10, 11, 12]);
        assert.equal(state.app.cursor, 12);
    } finally { state.close(); }
});
