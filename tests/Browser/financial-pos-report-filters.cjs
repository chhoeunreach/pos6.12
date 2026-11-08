const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {JSDOM} = require('../../storage/app/stock-ui-tests/node_modules/jsdom');
const jquery = require('../../storage/app/stock-ui-tests/node_modules/jquery');
function initialize(filters) {
    const source = fs.readFileSync(path.join(__dirname, '../../Modules/SmartStockInventory/Resources/views/report/stock_sell.blade.php'), 'utf8');
    const start = source.indexOf('        var initialFilters =');
    const end = source.indexOf('        function ssiFormatFooterMoney', start);
    const script = source.slice(start, end).replace(/var initialFilters = .*;/, 'var initialFilters = ' + JSON.stringify(filters) + ';');
    const dom = new JSDOM('<select id="sell_list_filter_location_id" multiple><option value="all">All</option><option value="1">Branch A</option><option value="2">Branch B</option></select><input id="sell_list_filter_date_range">', {runScripts:'outside-only'});
    const $ = jquery(dom.window), state = {};
    dom.window.$ = $;
    dom.window.dateRangeSettings = {};
    dom.window.moment_date_format = 'DD/MM/YYYY';
    dom.window.moment = value => ({isValid: () => !value || /^\d{4}-\d{2}-\d{2}$/.test(value), format: () => value || '2026-11-05'});
    $.fn.daterangepicker = function(options) { state.options = options; return this; };
    dom.window.eval(script);
    return {$, state};
}
test('embedded and linked Stock Sell Reports retain dashboard date range and locations', () => {
    const {$, state} = initialize({locations:[1,2], start:'2026-10-01', end:'2026-10-04'});
    assert.deepEqual(Array.from($('#sell_list_filter_location_id').val()), ['1','2']);
    assert.equal(state.options.startDate.format(), '2026-10-01');
    assert.equal(state.options.endDate.format(), '2026-10-04');
    assert.equal($('#sell_list_filter_date_range').val(), '2026-10-01 ~ 2026-10-04');
});
test('opening the Stock Sell Report normally keeps its existing today and all-locations defaults', () => {
    const {$, state} = initialize(null);
    assert.deepEqual(Array.from($('#sell_list_filter_location_id').val()), ['all']);
    assert.equal(state.options.startDate.format(), '2026-11-05');
    assert.equal(state.options.endDate.format(), '2026-11-05');
});

test('quick price modal refreshes the Stock Sell Report block only after a successful save', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../Modules/SmartStockInventory/Resources/views/report/stock_sell.blade.php'), 'utf8');
    const start = source.indexOf("        var purchaseModal = $('#ssi-purchase-reference-modal')");
    const end = source.indexOf("        $(document).on('change'", start);
    const state = {messages: [], reloads: []};
    const dom = new JSDOM('<a class="ssi-purchase-reference-edit" href="/purchases/12/edit" target="_blank" rel="noopener">Edit</a><div id="ssi-purchase-reference-modal"></div><iframe id="ssi-purchase-reference-frame" src="about:blank"></iframe>', {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    dom.window.$ = $;
    dom.window.stock_sell_report_table = {ajax: {reload: (...args) => state.reloads.push(args)}};
    Object.defineProperty(dom.window, 'parent', {value: {postMessage: (...args) => state.messages.push(args)}});
    dom.window.initPurchaseLotPriceModal = options => { state.editor = options; };
    dom.window.eval(source.slice(start, end));
    assert.equal(state.editor.modal, '#ssi-purchase-price-modal');
    assert.equal(state.editor.trigger, '.ssi-purchase-reference-edit');
    assert.equal(state.reloads.length, 0);
    assert.equal(state.messages.length, 0);
    state.editor.saved({success: true});
    assert.deepEqual(state.reloads, [[null, false]]);
    assert.equal(state.messages[0][0].type, 'financial-purchase-cost-updated');
    assert.equal(state.messages[0][1], 'http://localhost');
});
