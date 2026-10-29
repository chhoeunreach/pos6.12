const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../../storage/app/stock-ui-tests/node_modules/jsdom');
const code = fs.readFileSync(path.join(__dirname, '../../public/js/loan-settings.js'), 'utf8');
function boot(hash = '') {
    const dom = new JSDOM(`<body class="loan-management-page"><div class="lm-settings-wrapper" data-can-save="1">
        <input id="businessSettingsSearch"><button id="clearSearchBtn"></button><div id="settingsSearchSummary" hidden></div>
        <form id="businessSettingsForm"><input name="_token" value="csrf"><input name="active_tab" id="activeTabInput" value="tab-profile">
        <aside><div><a class="lm-nav-item" href="#tab-profile" data-tab="tab-profile">Profile</a><a class="lm-nav-item" href="#tab-notifications" data-tab="tab-notifications">Alerts</a></div></aside>
        <section class="lm-tab-pane active" id="tab-profile"><div data-search-target>Company name<input id="businessNameInput" name="business_name" value="Original" required></div><input name="logo" id="logoInput" type="file"></section>
        <section class="lm-tab-pane" id="tab-notifications"><div data-search-target>Telegram Bot<input id="telegramBotTokenInput" name="telegram_bot_token" type="password" value="123:token" required><button id="toggleTelegramToken" type="button"></button><button id="testTelegramBot" data-url="/test-bot" type="button"></button><div id="telegramTestResult"></div></div></section>
        <span id="saveStatusText"></span><button id="resetSettingsBtn" type="button"></button><button id="saveSettingsBtn" type="submit"></button>
        </form></div></body>`, { url: 'https://example.test/settings' + hash, runScripts: 'outside-only' });
    dom.window.eval(code);
    return dom;
}
(async () => {
    let dom = boot('#tab-notifications'), w = dom.window, d = w.document;
    assert(d.querySelector('#tab-notifications').classList.contains('active'));
    assert.equal(d.querySelector('[data-tab="tab-notifications"]').getAttribute('aria-selected'), 'true');
    d.querySelector('[data-tab="tab-notifications"]').dispatchEvent(new w.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    assert(d.querySelector('#tab-profile').classList.contains('active'));
    console.log('PASS deep links and keyboard section navigation');
    const input = d.querySelector('#businessNameInput');
    input.value = 'Changed'; input.dispatchEvent(new w.Event('input', { bubbles: true }));
    assert(d.querySelector('.lm-settings-wrapper').classList.contains('has-unsaved-changes'));
    assert.equal(d.querySelector('#resetSettingsBtn').disabled, false);
    const unload = new w.Event('beforeunload', { cancelable: true }); w.dispatchEvent(unload); assert.equal(unload.defaultPrevented, true);
    await new Promise(resolve => setTimeout(resolve, 20));
    input.value = 'Original'; input.dispatchEvent(new w.Event('input', { bubbles: true }));
    assert.equal(d.querySelector('.lm-settings-wrapper').classList.contains('has-unsaved-changes'), false, 'Reverted values and empty files must not remain dirty');
    console.log('PASS dirty state, revert detection and unsaved-change guard');
    const search = d.querySelector('#businessSettingsSearch'); search.value = 'telegram'; search.dispatchEvent(new w.Event('input'));
    assert(d.querySelector('#tab-notifications').classList.contains('active'));
    assert.equal(d.querySelector('#tab-profile [data-search-target]').style.display, 'none');
    assert.equal(d.querySelector('#settingsSearchSummary').hidden, false);
    search.value = 'unfindable'; search.dispatchEvent(new w.Event('input'));
    assert.match(d.querySelector('#settingsSearchSummary').textContent, /No settings/);
    input.dispatchEvent(new w.Event('invalid'));
    assert(d.querySelector('#tab-profile').classList.contains('active'));
    assert.equal(search.value, '');
    assert.equal(d.querySelector('#tab-profile [data-search-target]').style.display, '');
    console.log('PASS cross-section search and hidden invalid field recovery');
    const token = d.querySelector('#telegramBotTokenInput');
    d.querySelector('#toggleTelegramToken').click(); assert.equal(token.type, 'text');
    d.querySelector('#toggleTelegramToken').click(); assert.equal(token.type, 'password');
    let request;
    w.fetch = async (url, options) => { request = { url, options }; return { ok: true, json: async () => ({ message: 'Connected to @sample_bot' }) }; };
    d.querySelector('#testTelegramBot').click(); await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(request.url, '/test-bot'); assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(JSON.parse(request.options.body).telegram_bot_token, '123:token');
    assert.equal(d.querySelector('#telegramTestResult').textContent, 'Connected to @sample_bot');
    assert.equal(d.querySelector('#testTelegramBot').disabled, false);
    w.fetch = async () => { throw new Error('offline'); };
    d.querySelector('#testTelegramBot').click(); await new Promise(resolve => setTimeout(resolve, 0));
    assert.match(d.querySelector('#telegramTestResult').textContent, /Connection unavailable/);
    assert.equal(d.querySelector('#testTelegramBot').disabled, false);
    console.log('PASS masked credential control, CSRF and connection feedback');
    dom.window.close();
    console.log('4 settings UI tests passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
