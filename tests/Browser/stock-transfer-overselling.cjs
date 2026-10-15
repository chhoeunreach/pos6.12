const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function productRow(index, lot) {
    return `<tr class="product_row"><td>
        <input name="products[${index}][variation_id]" value="2701">
        <select class="lot_number" name="products[${index}][lot_no_line_id]">
            <option value="${lot}" data-qty_available="5">Lot ${lot}</option>
        </select><input class="product_quantity" name="products[${index}][quantity]" value="1">
        <input class="product_line_total" value="1"></td></tr>`;
}

test('rapid lot scans reserve distinct form indexes even when responses arrive in reverse order', () => {
    const {dom, $, requests} = transfer(false);
    dom.window.stock_transfer_product_row(2701);
    dom.window.stock_transfer_product_row(2701);
    assert.deepEqual(requests.map(request => request.data.row_index), [0, 1]);
    requests[1].success(productRow(1, 2));
    requests[0].success(productRow(0, 1));
    const fields = $('#stock_transfer_form').serializeArray();
    assert.equal(fields.find(field => field.name === 'products[0][lot_no_line_id]').value, '1');
    assert.equal(fields.find(field => field.name === 'products[1][lot_no_line_id]').value, '2');
    assert.equal($('#product_row_index').val(), '2');
    dom.window.close();
});

test('a row response from an old source location is discarded after switching away and back', () => {
    const {dom, $, requests} = transfer(false);
    let callbacks = 0;
    dom.window.stock_transfer_product_row(2701, () => callbacks++);
    $('#location_id').append('<option value="13">Other</option>').val('13').trigger('change');
    $('#location_id').val('12').trigger('change');
    requests[0].success(productRow(0, 1));
    assert.equal($('#stock_adjustment_product_table tbody tr').length, 0);
    assert.equal(callbacks, 0);
    dom.window.close();
});

test('a scanned lot missing from the returned options cannot silently become a FIFO transfer', () => {
    const {dom, $, requests, autocomplete} = transfer(false);
    const errors = [];
    dom.window.toastr = {error(message) { errors.push(message); }};
    autocomplete.options.select.call($('#search_product_for_srock_adjustment')[0], {}, {
        item: {qty_available: 1, variation_id: 2701, purchase_line_id: 99, is_lot_row: true}
    });
    requests[0].success(productRow(0, 1));
    assert.equal($('#stock_adjustment_product_table select[name]').length, 0);
    assert.equal(errors.length, 1);
    dom.window.close();
});

test('ordinary product search ignores an incidental purchase-line ID and keeps the row', () => {
    const {dom, $, requests, autocomplete} = transfer(false);
    const errors = [];
    dom.window.toastr = {error(message) { errors.push(message); }};
    const item = {qty_available: 3, variation_id: 2701, purchase_line_id: 99, lot_number: 'EXHAUSTED-LOT'};
    assert.equal(autocomplete._renderItem($('<ul>'), item).text().includes('EXHAUSTED-LOT'), false);
    autocomplete.options.select.call($('#search_product_for_srock_adjustment')[0], {}, {item});
    assert.equal(requests[0].data.purchase_line_id, null);
    requests[0].success(productRow(0, 1));
    assert.equal($('#stock_adjustment_product_table select[name]').length, 1);
    assert.equal(errors.length, 0);
    dom.window.close();
});

test('ordinary product search works when the product row has no lot selector', () => {
    const {dom, $, requests, autocomplete} = transfer(false);
    autocomplete.options.select.call($('#search_product_for_srock_adjustment')[0], {}, {
        item: {qty_available: 3, variation_id: 2701, purchase_line_id: 99}
    });
    requests[0].success('<tr class="product_row added-product"><td><input class="product_line_total" value="1"></td></tr>');
    assert.equal($('.added-product').length, 1);
    dom.window.close();
});

test('an explicit lot search sends and preserves the chosen receipt', () => {
    const {dom, $, requests, autocomplete} = transfer(false);
    autocomplete.options.select.call($('#search_product_for_srock_adjustment')[0], {}, {
        item: {qty_available: 3, variation_id: 2701, purchase_line_id: 99, is_lot_row: true}
    });
    assert.equal(requests[0].data.purchase_line_id, 99);
    requests[0].success(productRow(0, 99));
    assert.equal($('#stock_adjustment_product_table select[name]').val(), '99');
    dom.window.close();
});

function transfer(enabled) {
    const dom = new JSDOM(`<input id="stock_transfer_allow_overselling" value="${enabled ? 1 : 0}">
        <input id="search_product_for_srock_adjustment"><input id="product_row_index" value="0">
        <select id="location_id"><option value="12" selected>Source</option></select>
        <form id="stock_transfer_form"><table id="stock_adjustment_product_table"><tbody><tr class="product_row">
        <td><select class="lot_number"><option value="1" data-qty_available="0" selected>Serial</option></select>
        <select class="sub_unit"><option data-multiplier="1" data-allow_decimal="0" data-unit_name="piece">Piece</option></select>
        <input class="product_quantity" value="2" data-qty_available="0" data-allow-overselling="${enabled}"
            data-rule-max-value="0"><input class="product_unit_price"><input class="hidden_base_unit_price" value="1">
        <input class="base_unit_multiplier"><input class="product_line_total"></td></tr></tbody></table></form>`,
        {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    dom.window.$ = dom.window.jQuery = $;
    dom.window.moment_date_format = 'YYYY-MM-DD';
    dom.window.moment_time_format = 'HH:mm:ss';
    dom.window.LANG = {out_of_stock: 'Out of stock'};
    dom.window.__read_number = element => Number(element.val() || 0);
    dom.window.__write_number = (element, value) => element.val(value);
    dom.window.__number_f = value => String(value);
    dom.window.__currency_trans_from_en = value => String(value);
    dom.window.__translate = key => key;
    dom.window.alert = () => {};
    const ready = [];
    $.fn.ready = function(callback) { ready.push(callback); return this; };
    $.fn.datetimepicker = $.fn.validate = function() { return this; };
    $.validator = {addMethod() {}};
    const ruleCalls = [];
    $.fn.rules = function(action, value) { ruleCalls.push({action, value}); return this; };
    $.fn.valid = () => true;
    $.fn.DataTable = () => ({on() {}, ajax: {reload() {}}});
    const autocomplete = {options: null, selections: 0, _trigger() { this.selections++; }};
    $.fn.autocomplete = function(argument) {
        if (typeof argument === 'object') { autocomplete.options = argument; this.data('ui-autocomplete', autocomplete); }
        return argument === 'instance' ? autocomplete : this;
    };
    const requests = [];
    $.ajax = options => { requests.push(options); return $.Deferred().promise(); };
    $.getJSON = (url, data) => { requests.push({url, data}); };
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_transfer.js'), 'utf8'));
    ready.forEach(callback => callback.call(dom.window.document));
    const common = fs.readFileSync(path.join(root, 'public/js/common.js'), 'utf8');
    const start = common.indexOf("$('table#stock_adjustment_product_table tbody').on('change', 'select.lot_number'");
    const end = common.indexOf("$('button#btnCalculator", start);
    assert.ok(start >= 0 && end > start);
    dom.window.eval(common.slice(start, end));
    return {dom, $, autocomplete, requests, ruleCalls};
}

for (const enabled of [true, false]) {
    test(`serial and unit changes ${enabled ? 'remove' : 'enforce'} zero-stock quantity limits`, () => {
        const {dom, $, ruleCalls} = transfer(enabled);
        $('.lot_number').trigger('change');
        $('.sub_unit').trigger('change');
        const quantity = $('.product_quantity');
        if (enabled) {
            assert.equal(quantity.attr('data-rule-max-value'), undefined);
            assert.equal(ruleCalls.filter(call => call.action === 'remove' && call.value === 'max-value').length, 2);
            assert.equal(ruleCalls.some(call => call.action === 'add' && 'max-value' in call.value), false);
        } else {
            assert.equal(quantity.attr('data-rule-max-value'), '0');
            assert.equal(ruleCalls.filter(call => call.action === 'add' && call.value['max-value'] === 0).length, 2);
        }
        dom.window.close();
    });
}

test('enabled overselling allows an empty serial to be searched and added to a transfer', () => {
    const {dom, $, autocomplete, requests} = transfer(true);
    const item = {qty_available: 0, enable_stock: 1, variation_id: 2701, purchase_line_id: 1, name: 'Phone', sub_sku: 'Phone-1'};
    const input = $('#search_product_for_srock_adjustment')[0];
    autocomplete.options.source({term: 'serial'}, () => {});
    assert.equal(requests[0].data.stock_transfer, 1);
    autocomplete.options.response.call(input, {}, {content: [item]});
    assert.equal(autocomplete.selections, 1);
    assert.notEqual(autocomplete.options.focus.call(input, {}, {item}), false);
    autocomplete.options.select.call(input, {}, {item});
    assert.equal(requests[1].url, '/stock-adjustments/get_product_row');
    assert.equal(requests[1].data.type, 'stock_transfer');
    const result = autocomplete._renderItem($('<ul>'), item);
    assert.equal(result.hasClass('ui-state-disabled'), false);
    dom.window.close();
});

test('disabled overselling keeps empty search results unavailable', () => {
    const {dom, $, autocomplete, requests} = transfer(false);
    const item = {qty_available: 0, enable_stock: 1, variation_id: 2701, name: 'Phone', sub_sku: 'Phone-1'};
    const input = $('#search_product_for_srock_adjustment')[0];
    autocomplete.options.response.call(input, {}, {content: [item]});
    autocomplete.options.select.call(input, {}, {item});
    assert.equal(autocomplete.selections, 0);
    assert.equal(requests.length, 0);
    assert.equal(autocomplete._renderItem($('<ul>'), item).hasClass('ui-state-disabled'), true);
    dom.window.close();
});

test('transfer errors identify the blocking lot without pointing to the quantity-only detector', () => {
    const {dom} = transfer(false);
    const result = {msg: 'Long allocation error', stock_error: {
        sku: 'PHONE-1', serial: 'SERIAL-1', reason: 'The transfer is dated before this lot arrived at the source location.'
    }};
    const message = dom.window.stock_transfer_error_message(result);
    assert.ok(message.includes('PHONE-1 / SERIAL-1'));
    assert.ok(message.includes(result.stock_error.reason));
    assert.equal(message.includes('Mismatch Detector'), false);
    assert.equal(dom.window.stock_transfer_error_message({msg: 'Transfer has existing allocations'}), 'Transfer has existing allocations');
    dom.window.close();
});

test('transfer errors include a safe direct detector link and reject external links', () => {
    const {dom, $} = transfer(false);
    dom.window.toastr = {error() {}};
    const $target = $('<div>').appendTo('body');
    const result = {msg: '<script>unsafe</script>', mismatch_url: '/smart-stock-inventory/mismatch?view=stock_errors&transfer_id=21'};
    dom.window.show_stock_transfer_error(result, $target);
    assert.equal($target.find('a').text(), 'Open Mismatch Detector & Fix');
    assert.equal($target.find('a').attr('href'), 'http://localhost/smart-stock-inventory/mismatch?view=stock_errors&transfer_id=21');
    assert.equal($target.find('script').length, 0);
    dom.window.show_stock_transfer_error(result, $target);
    assert.equal($target.find('.transfer-stock-error-link').length, 1);
    $target.empty();
    dom.window.show_stock_transfer_error({...result, mismatch_url: 'https://example.com/unsafe'}, $target);
    assert.equal($target.find('a').length, 0);
    dom.window.close();
});
