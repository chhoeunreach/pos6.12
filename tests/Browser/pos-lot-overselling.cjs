const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function changeLot(allowOverselling) {
    const dom = new JSDOM(`<table id="pos_table"><tbody><tr><td>
        <select class="lot_number"><option value="86748" data-qty_available="0" data-msg-max="No stock">Serial</option></select>
        <input class="pos_quantity" value="1" data-allow-overselling="${allowOverselling}"
            data-rule-max-value="0" data-msg-max-value="No stock">
        </td></tr></tbody></table>`, {runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    dom.window.$ = dom.window.jQuery = $;
    const rules = [];
    $.fn.rules = function (...args) { rules.push(args); return this; };
    const source = fs.readFileSync(path.join(root, 'public/js/pos.js'), 'utf8');
    dom.window.eval(source.slice(source.indexOf('//Change max quantity rule if lot number changes'),
        source.indexOf('//Change in row discount type or discount amount')));
    $('.lot_number').trigger('change');
    return {dom, $, rules};
}

test('overselling removes stale quantity limits when selecting an exhausted POS receipt', () => {
    const {dom, $, rules} = changeLot(true);
    assert.equal(rules.length, 1);
    assert.deepEqual(rules[0], ['remove', 'max-value']);
    assert.equal($('.pos_quantity').attr('data-rule-max-value'), undefined);
    assert.equal($('.lot_number').val(), '86748');
    dom.window.close();
});

test('disabled overselling keeps the selected receipt quantity limit', () => {
    const {dom, $, rules} = changeLot(false);
    assert.equal(rules.length, 1);
    assert.equal(rules[0][0], 'add');
    assert.equal(rules[0][1]['max-value'], 0);
    assert.equal($('.pos_quantity').attr('data-rule-max-value'), '0');
    dom.window.close();
});
