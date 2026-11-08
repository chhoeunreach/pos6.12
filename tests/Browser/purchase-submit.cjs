const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function fixture() {
    const dom = new JSDOM(`<form id="add_purchase_form" action="/purchases/12" method="post">
        <input id="purchase_id" value="12"><input name="_method" value="PUT">
        <input name="_token" value="csrf"><input id="ref_no" name="ref_no" value="PUR-12" required>
        <input id="supplier_id" name="contact_id" value="3" required>
        <table id="purchase_entry_table"><tbody><tr class="purchase_entry_row">
        <td><input name="purchases[0][purchase_line_id]" value="7"><input name="purchases[0][purchase_price]" type="number" min="0" value="65" required></td>
        </tr><tr class="purchase-product-details-row"><td><input name="purchases[0][model_number]" value="Model A"></td></tr></tbody></table>
        <button type="button" id="submit_purchase_form">Update</button></form>`, {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window), requests = [], submissions = [];
    dom.window.$ = dom.window.jQuery = $;
    dom.window.LANG = {ref_no_already_exists: 'Reference already exists'};
    dom.window.toastr = {warning() {}, error() {}};
    dom.window.validate_all_purchase_lot_numbers = () => true;
    dom.window.purchase_row_group = row => row.add(row.next('.purchase-product-details-row'));
    dom.window.eval(fs.readFileSync(path.join(root, 'resources/plugins/jquery-validation-1.16.0/dist/jquery.validate.js'), 'utf8'));
    $.validator.setDefaults({ignore: []});
    $.ajax = options => { requests.push(options); return {abort() {}}; };
    dom.window.HTMLFormElement.prototype.submit = function() { submissions.push($(this).serializeArray()); };
    const source = fs.readFileSync(path.join(root, 'public/js/purchase.js'), 'utf8');
    dom.window.eval(source.slice(source.indexOf('function show_purchase_submit_progress('), source.indexOf('function toggle_search()')));
    return {dom, $, requests, submissions};
}

test('Update waits for remote validation, then submits the compact payload exactly once', () => {
    const f = fixture();
    f.$('#submit_purchase_form').trigger('click');
    assert.equal(f.requests.length, 1);
    assert.equal(f.requests[0].data.purchase_id(), '12');
    assert.equal(f.submissions.length, 0);
    assert.equal(f.$('#purchase_submit_progress_overlay').length, 0);
    assert.equal(f.$('[name="purchases[0][purchase_price]"]').length, 1);
    assert.match(f.$('#purchase_validation_status').text(), /Checking/);
    f.requests[0].success(true);
    assert.equal(f.submissions.length, 1);
    const payload = Object.fromEntries(f.submissions[0].map(field => [field.name, field.value]));
    assert.equal(payload._method, 'PUT');
    assert.equal(payload._token, 'csrf');
    assert.deepEqual(JSON.parse(payload.purchases_json), [{purchase_line_id: '7', purchase_price: '65', model_number: 'Model A'}]);
    f.$('#submit_purchase_form').trigger('click');
    assert.equal(f.submissions.length, 1);
});

test('duplicate references leave the form editable without a stuck saving overlay', () => {
    const f = fixture();
    f.$('#submit_purchase_form').trigger('click');
    f.requests[0].success(false);
    assert.equal(f.submissions.length, 0);
    assert.equal(f.$('#purchase_submit_progress_overlay').length, 0);
    assert.equal(f.$('#submit_purchase_form').prop('disabled'), false);
    assert.equal(f.$('[name="purchases[0][purchase_price]"]').val(), '65');
    assert.match(f.$('#ref_no-error').text(), /Reference already exists/);
});

test('reference check timeouts show an error and allow retrying Update', () => {
    const f = fixture();
    f.$('#submit_purchase_form').trigger('click');
    assert.equal(f.requests[0].timeout, 15000);
    f.requests[0].error({status: 0}, 'timeout');
    assert.equal(f.$('#submit_purchase_form').prop('disabled'), false);
    assert.equal(f.$('#purchase_submit_progress_overlay').length, 0);
    assert.match(f.$('#purchase_validation_status').text(), /Unable to check/);
    f.$('#submit_purchase_form').trigger('click');
    assert.equal(f.requests.length, 2);
    f.requests[1].success(true);
    assert.equal(f.submissions.length, 1);
});

test('invalid purchase prices do not compact rows or disable Update permanently', () => {
    const f = fixture();
    f.$('[name="purchases[0][purchase_price]"]').val('-1');
    f.$('#submit_purchase_form').trigger('click');
    assert.equal(f.$('#submit_purchase_form').prop('disabled'), false);
    assert.equal(f.$('#purchase_submit_progress_overlay').length, 0);
    assert.equal(f.submissions.length, 0);
    assert.equal(f.$('[name="purchases[0][purchase_price]"]').length, 1);
});
