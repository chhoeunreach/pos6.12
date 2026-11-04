const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function fixture({savedOpen = null, datePicker = true} = {}) {
    const dom = new JSDOM(`<details id="finance-filter-panel" open><summary>Filters</summary>
        <form id="finance-filter-form"><input id="finance-start" type="hidden" value="2026-11-01">
        <input id="finance-end" type="hidden" value="2026-11-04">
        <div class="finance-field"><input id="finance-date-range" readonly></div>
        <input type="hidden" id="finance-expense-filter-applied" value="0">
        <input class="finance-excluded-category" value="Office">
        <select id="finance-expense-categories" multiple><option value="Office">Office</option><option value="Travel" selected>Travel</option></select>
        <button type="button" id="finance-expenses-all">All</button><button type="button" id="finance-expenses-none">None</button>
        </form></details><button id="finance-open-category-filter">Filter categories</button>
        <select id="finance-print-orientation"><option value="landscape">Landscape</option><option value="portrait">Portrait</option></select>
        <a id="finance-print" href="/reports/financial-dashboard?print=1&orientation=landscape&start_date=2026-11-01&location_ids%5B0%5D=-2&excluded_expense_categories%5B0%5D=Office">Print</a>`,
        {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    const state = {picker: null, callback: null, scrolled: 0, printed: 0};
    dom.window.$ = dom.window.jQuery = $;
    dom.window.print = () => state.printed++;
    dom.window.document.getElementById('finance-filter-panel').scrollIntoView = () => state.scrolled++;
    if (savedOpen !== null) dom.window.localStorage.setItem('financial-dashboard.filters-open', savedOpen);
    if (datePicker) {
        dom.window.moment = value => ({format: format => format === 'YYYY-MM-DD' ? value : 'display:' + value});
        dom.window.moment_date_format = 'DD/MM/YYYY';
        dom.window.dateRangeSettings = {locale: {daysOfWeek: ['Su', 'Mo']}};
        $.fn.daterangepicker = function(options, callback) {
            state.picker = options;
            state.callback = callback;
            return this;
        };
    }
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/financial-dashboard.js'), 'utf8'));
    dom.window.initFinancialDashboard({dateSettings: {locale: {applyLabel: 'អនុវត្ត'}}, ranges: {Month: []}});
    return {dom, $, state};
}

test('date-range selection updates canonical submitted dates without changing expense scope', () => {
    const {dom, $, state} = fixture();
    assert.equal(state.picker.showDropdowns, true);
    assert.equal(state.picker.locale.applyLabel, 'អនុវត្ត');
    assert.equal(state.picker.locale.format, 'DD/MM/YYYY');
    assert.deepEqual(Array.from(state.picker.locale.daysOfWeek), ['Su', 'Mo']);
    state.callback(dom.window.moment('2026-10-01'), dom.window.moment('2026-10-31'));
    assert.equal($('#finance-start').val(), '2026-10-01');
    assert.equal($('#finance-end').val(), '2026-10-31');
    assert.equal($('#finance-date-range').val(), 'display:2026-10-01 ~ display:2026-10-31');
    assert.equal($('#finance-expense-filter-applied').val(), '0');
});

test('collapse preference survives initialization and expanding from the chart opens the controls', () => {
    const {dom, $, state} = fixture({savedOpen: 'false'});
    const panel = dom.window.document.getElementById('finance-filter-panel');
    assert.equal(panel.open, false);
    $('#finance-open-category-filter').trigger('click');
    assert.equal(panel.open, true);
    assert.equal(state.scrolled, 1);
    panel.dispatchEvent(new dom.window.Event('toggle'));
    assert.equal(dom.window.localStorage.getItem('financial-dashboard.filters-open'), 'true');
    assert.equal(dom.window.document.activeElement.id, 'finance-expense-categories');
});

test('select all and none make category scope explicit and clear previous exclusions', () => {
    const {$} = fixture();
    $('#finance-expenses-all').trigger('click');
    assert.deepEqual(Array.from($('#finance-expense-categories').val()), ['Office', 'Travel']);
    assert.equal($('.finance-excluded-category').length, 0);
    assert.equal($('#finance-expense-filter-applied').val(), '1');
    $('#finance-expenses-none').trigger('click');
    assert.equal($('#finance-expense-categories').val().length, 0);
    assert.equal($('#finance-expense-filter-applied').val(), '1');
});

test('native date controls remain available when date-picker assets are unavailable', () => {
    const {$} = fixture({datePicker: false});
    assert.equal($('#finance-start').attr('type'), 'date');
    assert.equal($('#finance-end').attr('type'), 'date');
    $('#finance-start').val('2026-10-01').trigger('change');
    assert.equal($('#finance-end').attr('min'), '2026-10-01');
});

test('print orientation can switch both ways while preserving dashboard filters', () => {
    const {dom, $, state} = fixture();
    $('#finance-print-orientation').val('portrait').trigger('change');
    let url = new URL(dom.window.document.getElementById('finance-print').href);
    assert.equal(url.searchParams.get('orientation'), 'portrait');
    assert.equal(url.searchParams.get('print'), '1');
    assert.equal(url.searchParams.get('start_date'), '2026-11-01');
    assert.equal(url.searchParams.get('location_ids[0]'), '-2');
    assert.equal(url.searchParams.get('excluded_expense_categories[0]'), 'Office');
    $('#finance-print-orientation').val('landscape').trigger('change');
    url = new URL(dom.window.document.getElementById('finance-print').href);
    assert.equal(url.searchParams.get('orientation'), 'landscape');
    assert.equal(state.printed, 0);
});
