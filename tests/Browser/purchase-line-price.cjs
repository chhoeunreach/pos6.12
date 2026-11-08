const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function fixture() {
    const dom = new JSDOM(`<form id="purchase-line-price-form" action="/reports/financial-dashboard/purchase-price/main/250" data-saving="Saving…" data-saved="Price saved" data-error="Save failed">
        <input name="_token" value="csrf"><input name="cost_version" value="version-1"><input name="prices[0][id]" value="250">
        <div id="purchase-line-price-status" hidden></div><input type="number" id="purchase-line-price" name="prices[0][purchase_price]" value="85" min="0" required>
        <button type="submit">Save price</button></form>`, {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window), requests = [];
    dom.window.$ = dom.window.jQuery = $;
    $.ajax = options => requests.push(options);
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/purchase-line-price.js'), 'utf8'));
    return {$, requests};
}

test('quick price save submits only the selected row with CSRF and prevents duplicate saves', () => {
    const f = fixture();
    f.$('form').trigger('submit');
    f.$('form').trigger('submit');
    assert.equal(f.requests.length, 1);
    const request = f.requests[0], data = new URLSearchParams(request.data);
    assert.equal(request.method, 'PUT');
    assert.equal(request.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(data.get('prices[0][id]'), '250');
    assert.equal(data.get('prices[0][purchase_price]'), '85');
    assert.equal([...data.keys()].length, 4);
    assert.equal(f.$('button').prop('disabled'), true);
    request.success({success: true, cost_version: 'version-2', lots: [{id: 250, purchase_price: 85}]});
    request.complete();
    assert.equal(f.$('#purchase-line-price-status').text(), 'Price saved');
    assert.equal(f.$('button').prop('disabled'), false);
    f.$('#purchase-line-price').val('90');
    f.$('form').trigger('submit');
    assert.equal(new URLSearchParams(f.requests[1].data).get('cost_version'), 'version-2');
});

test('validation and stale-price errors keep the selected price editable', () => {
    const f = fixture();
    f.$('form').trigger('submit');
    f.requests[0].error({responseJSON: {errors: {'prices.0.purchase_price': ['Invalid price']}}});
    f.requests[0].complete();
    assert.equal(f.$('#purchase-line-price-status').text(), 'Invalid price');
    assert.equal(f.$('#purchase-line-price').val(), '85');
    assert.equal(f.$('button').prop('disabled'), false);
    f.$('form').trigger('submit');
    f.requests[1].error({status: 409, responseJSON: {message: 'Price changed. Refresh before saving.'}});
    f.requests[1].complete();
    assert.match(f.$('#purchase-line-price-status').text(), /Refresh/);
    assert.equal(f.$('#purchase-line-price').val(), '85');
});

test('saving in the purchase list modal updates only the selected row block', async () => {
    const dom = new JSDOM('<table><tbody><tr data-purchase-row="250"><td class="purchase-row-price">60</td></tr><tr data-purchase-row="251"><td class="purchase-row-price">60</td></tr></tbody></table>', {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    let editor;
    dom.window.jQuery = $;
    dom.window.initPurchaseLotPriceModal = options => { editor = options; };
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/purchase-row-list.js'), 'utf8'));
    await new Promise(resolve => $(resolve));
    assert.equal(editor.modal, '#purchase-row-price-modal');
    editor.saved({lots: [{id: 250, purchase_price: 85}]});
    assert.equal($('[data-purchase-row="250"] .purchase-row-price').text(), '85');
    assert.equal($('[data-purchase-row="251"] .purchase-row-price').text(), '60');
});
