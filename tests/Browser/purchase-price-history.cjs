const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function fixture() {
    const dom = new JSDOM('<div class="modal in" id="parent-report"></div><a class="purchase-price-history-link" href="/reports/financial-dashboard/purchase-price-history/accessory/42?report_lang=en" data-sku="SKU-42">SKU-42</a><div class="modal" id="purchase-price-history-modal" data-title="Purchase price history"><h4 id="purchase-price-history-title"></h4><p id="purchase-price-history-loading" hidden></p><iframe id="purchase-price-history-frame" src="about:blank"></iframe></div>', {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window), state = {focusRestored: 0};
    dom.window.jQuery = $;
    $.fn.modal = function(action) { this.toggleClass('in', action === 'show').trigger(action === 'show' ? 'shown.bs.modal' : 'hidden.bs.modal'); return this; };
    $('#parent-report').data('bs.modal', {enforceFocus: () => state.focusRestored++});
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/purchase-price-history.js'), 'utf8'));
    return {$, state};
}

test('SKU opens its source-specific purchase history in a read-only modal and restores the report on close', () => {
    const f = fixture(), click = f.$.Event('click');
    f.$('.purchase-price-history-link').trigger(click);
    assert.equal(click.isDefaultPrevented(), true);
    assert.equal(f.$('#purchase-price-history-modal').hasClass('in'), true);
    assert.match(f.$('#purchase-price-history-frame').attr('src'), /purchase-price-history\/accessory\/42\?report_lang=en$/);
    assert.equal(f.$('#purchase-price-history-title').text(), 'Purchase price history · SKU-42');
    assert.equal(f.$('#purchase-price-history-loading').prop('hidden'), false);
    f.$('#purchase-price-history-frame').trigger('load');
    assert.equal(f.$('#purchase-price-history-loading').prop('hidden'), true);
    f.$('#purchase-price-history-modal').modal('hide');
    assert.equal(f.$('#purchase-price-history-frame').attr('src'), 'about:blank');
    assert.equal(f.$('#parent-report').hasClass('in'), true);
    assert.equal(f.$('body').hasClass('modal-open'), true);
    assert.equal(f.state.focusRestored, 1);
});

test('history links cannot embed another origin', () => {
    const f = fixture();
    f.$('.purchase-price-history-link').attr('href', 'https://elsewhere.example/history').trigger('click');
    assert.equal(f.$('#purchase-price-history-modal').hasClass('in'), false);
    assert.equal(f.$('#purchase-price-history-frame').attr('src'), 'about:blank');
});
