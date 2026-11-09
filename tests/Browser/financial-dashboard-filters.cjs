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
        <button type="button" class="finance-selection-trigger" id="finance-choose-locations" data-select="finance-locations" data-title="Locations"></button>
        <select id="finance-locations" multiple><option value="1" selected>Branch A</option><option value="2">Branch B</option></select>
        <button type="button" class="finance-selection-trigger" id="finance-choose-expenses" data-select="finance-expense-categories" data-title="Expense categories"></button>
        <select id="finance-expense-categories" multiple><option value="Office">Office</option><option value="Travel" selected>Travel</option></select>
        <button type="button" id="finance-expenses-all">All</button><button type="button" id="finance-expenses-none">None</button>
        </form></details><button id="finance-open-category-filter">Filter categories</button>
        <div id="finance-selection-modal"><h4 id="finance-selection-title"></h4><input id="finance-selection-search"><span id="finance-selection-count"></span><div id="finance-selection-options"></div><button id="finance-selection-all"></button><button id="finance-selection-none"></button><button id="finance-selection-apply"></button></div>
        <select id="finance-print-orientation"><option value="landscape">Landscape</option><option value="portrait">Portrait</option></select>
        <a id="finance-print" href="/reports/financial-dashboard?print=1&orientation=landscape&start_date=2026-11-01&location_ids%5B0%5D=-2&excluded_expense_categories%5B0%5D=Office">Print</a><div id="finance-print-modal"><p id="finance-print-loading" hidden></p><iframe id="finance-print-frame" src="about:blank"></iframe></div>`,
        {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    const state = {picker: null, callback: null, scrolled: 0, printed: 0};
    dom.window.$ = dom.window.jQuery = $;
    $.fn.modal = function(action) {
        this.toggleClass('in', action === 'show');
        this.trigger(action === 'show' ? 'shown.bs.modal' : 'hidden.bs.modal');
        return this;
    };
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
    assert.equal($('#finance-selection-modal').hasClass('in'), true);
    assert.equal(dom.window.document.activeElement.id, 'finance-selection-search');
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

test('location popup keeps draft selections until Apply and supports a single or multiple choices', () => {
    const {$} = fixture();
    $('#finance-choose-locations').trigger('click');
    assert.equal($('#finance-selection-title').text(), 'Locations');
    $('#finance-selection-options input').prop('checked', true).trigger('change');
    assert.deepEqual(Array.from($('#finance-locations').val()), ['1']);
    $('#finance-selection-modal').modal('hide');
    $('#finance-choose-locations').trigger('click');
    assert.equal($('#finance-selection-options input:checked').length, 1);
    $('#finance-selection-all').trigger('click');
    $('#finance-selection-apply').trigger('click');
    assert.deepEqual(Array.from($('#finance-locations').val()), ['1', '2']);
    assert.equal($('#finance-choose-locations').text(), 'Branch A, Branch B');
    assert.equal($('#finance-expense-filter-applied').val(), '0');
});
test('category popup searches safely and Apply clears exclusions and makes category scope explicit', () => {
    const {$} = fixture();
    $('#finance-expense-categories').append($('<option>').val('<img>').text('<img src=x>'));
    $('#finance-choose-expenses').trigger('click');
    assert.equal($('#finance-selection-options img').length, 0);
    $('#finance-selection-search').val('office').trigger('input');
    assert.equal($('#finance-selection-options label:not([hidden])').length, 1);
    $('#finance-selection-none').trigger('click');
    $('#finance-selection-options input[value="Office"]').prop('checked', true).trigger('change');
    $('#finance-selection-apply').trigger('click');
    assert.deepEqual(Array.from($('#finance-expense-categories').val()), ['Office']);
    assert.equal($('#finance-expense-filter-applied').val(), '1');
    assert.equal($('.finance-excluded-category').length, 0);
    $('#finance-choose-expenses').trigger('click');
    $('#finance-selection-none').trigger('click');
    $('#finance-selection-apply').trigger('click');
    assert.deepEqual(Array.from($('#finance-expense-categories').val()), []);
    assert.equal($('#finance-choose-expenses').text(), 'None');
});

test('Print opens a filtered modal preview and closes without printing or navigating the dashboard', () => {
    const {dom, $, state} = fixture();
    $('#finance-print-orientation').val('portrait').trigger('change');
    const click = $.Event('click');
    $('#finance-print').trigger(click);
    assert.equal(click.isDefaultPrevented(), true);
    assert.equal($('#finance-print-modal').hasClass('in'), true);
    const preview = new URL($('#finance-print-frame').attr('src'));
    assert.equal(preview.searchParams.get('print'), '1');
    assert.equal(preview.searchParams.get('print_embed'), '1');
    assert.equal(preview.searchParams.get('orientation'), 'portrait');
    assert.equal(preview.searchParams.get('start_date'), '2026-11-01');
    assert.equal(preview.searchParams.get('location_ids[0]'), '-2');
    assert.equal(preview.searchParams.get('excluded_expense_categories[0]'), 'Office');
    assert.equal(dom.window.location.href, 'http://localhost/');
    assert.equal(state.printed, 0);
    assert.equal($('#finance-print-loading').prop('hidden'), false);
    $('#finance-print-frame').trigger('load');
    assert.equal($('#finance-print-loading').prop('hidden'), true);
    $('#finance-print-modal').modal('hide');
    assert.equal($('#finance-print-frame').attr('src'), 'about:blank');
    assert.equal(dom.window.document.activeElement.id, 'finance-print');
    $('#finance-print-orientation').val('landscape').trigger('change');
    $('#finance-print').trigger('click');
    assert.equal(new URL($('#finance-print-frame').attr('src')).searchParams.get('orientation'), 'landscape');
    dom.window.close();
});
