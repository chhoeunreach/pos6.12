const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));

test('preview keeps orientation controls visible until Print is clicked and waits for fonts', async () => {
    const template = fs.readFileSync(path.join(root, 'Modules/LocalCashierReport/Resources/views/financial_print_layout.blade.php'), 'utf8');
    const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
    const dom = new JSDOM('<button id="finance-print-preview">Print / PDF</button>', {runScripts: 'outside-only'});
    let printed = 0;
    let fontsReady;
    const ready = new Promise(resolve => { fontsReady = resolve; });
    Object.defineProperty(dom.window.document, 'fonts', {value: {ready}});
    dom.window.print = () => printed++;
    dom.window.eval(script);
    dom.window.dispatchEvent(new dom.window.Event('load'));
    assert.equal(printed, 0, 'opening the preview must not obscure its orientation controls');
    dom.window.document.getElementById('finance-print-preview').click();
    assert.equal(printed, 0, 'Khmer fonts must finish loading before print');
    fontsReady();
    await new Promise(setImmediate);
    assert.equal(printed, 1);
    dom.window.document.getElementById('finance-print-preview').click();
    await new Promise(setImmediate);
    assert.equal(printed, 2);
    dom.window.close();
});
