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

test('saving a purchase price refreshes the Stock Sell Report block without resetting its page', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../Modules/SmartStockInventory/Resources/views/report/stock_sell.blade.php'), 'utf8');
    const start = source.indexOf("        window.initPurchaseLotPriceModal({modal: '#ssi-purchase-price-modal'");
    const end = source.indexOf("        $(document).on('change'", start);
    const state = {messages: [], reloads: []};
    const host = {location: {origin: 'http://localhost'}, parent: {postMessage: (...args) => state.messages.push(args)}, initPurchaseLotPriceModal: options => state.options = options};
    const table = {ajax: {reload: (...args) => state.reloads.push(args)}};
    new Function('window', 'stock_sell_report_table', source.slice(start, end))(host, table);
    assert.equal(state.options.modal, '#ssi-purchase-price-modal');
    state.options.saved();
    assert.deepEqual(state.reloads, [[null, false]]);
    assert.equal(state.messages[0][0].type, 'financial-purchase-cost-updated');
    assert.equal(state.messages[0][1], 'http://localhost');
});
