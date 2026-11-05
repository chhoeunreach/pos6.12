const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));
function fixture() {
    const dom = new JSDOM(`<details id="finance-filter-panel"></details>
        <a class="finance-sales-details" href="/reports/financial-dashboard/sales?location_ids%5B0%5D=1&start_date=2026-11-01">5 units</a>
        <a class="finance-sales-details" data-cost-details="1" href="/reports/financial-dashboard/cost-sales?location_ids%5B0%5D=1">Cost</a><div id="finance-sales-modal"><h4 id="finance-sales-title"></h4><span id="finance-sales-basis"></span><span id="finance-sales-amount-label"></span><div id="finance-sales-error" hidden></div><p id="finance-sales-summary"></p><select id="finance-sales-page-size"><option selected>25</option><option>10</option><option value="-1">All</option></select><input id="finance-sales-page-input"><button id="finance-sales-refresh"></button>
        <table id="finance-sales-table"><thead><tr><th><button class="finance-sales-sort" data-sort="date">Date<span class="finance-sort-indicator">▼</span></button></th><th><button class="finance-sales-sort" data-sort="amount">Amount<span class="finance-sort-indicator">↕</span></button></th></tr></thead><tbody></tbody></table><button id="finance-sales-prev"></button><span id="finance-sales-page"></span><button id="finance-sales-next"></button></div>`,
        {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window), requests = [], state = {refreshes: 0};
    $('body').append('<div id="finance-purchase-price-modal" class="modal"><form class="purchase-lot-price-form"><div class="purchase-lot-price-error" hidden></div><p class="purchase-lot-price-loading"></p><div class="purchase-lot-price-fields"></div><button type="submit" class="purchase-lot-price-save">Save</button><button type="button" class="purchase-lot-price-cancel" data-dismiss="modal">Cancel</button></form></div>');
    $('body').append('<a class="finance-pos-report-details" href="/smart-stock-inventory/stock-reports/sell?finance_embed=1&start_date=2026-11-01&end_date=2026-11-05&location_id%5B0%5D=1">POS total</a><div id="finance-pos-report-modal"><span id="finance-pos-report-loading"></span><iframe id="finance-pos-report-frame" src="about:blank"></iframe><a id="finance-pos-report-tab"></a></div>');
    $('#finance-sales-modal').append('<div class="finance-cost-tools" hidden><input id="finance-cost-search"><button id="finance-cost-clear-search"></button></div><div class="finance-cost-stats" hidden><strong id="finance-cost-stat-qty"></strong><strong id="finance-cost-stat-total"></strong><strong id="finance-cost-stat-profit"></strong></div><div id="finance-cost-quality" hidden></div><span id="finance-sales-range"></span><button id="finance-sales-first"></button><button id="finance-sales-last"></button>');
    dom.window.$ = dom.window.jQuery = $;
    $.fn.modal = function(action) {
        if (action === 'show') { this.addClass('in').trigger('shown.bs.modal'); }
        else { const event = $.Event('hide.bs.modal'); this.trigger(event); if (!event.isDefaultPrevented()) this.removeClass('in').trigger('hidden.bs.modal'); }
        return this;
    };
    $(dom.window.document).on('click', '[data-dismiss="modal"]', function() { $(this).closest('.modal').modal('hide'); });
    $.ajax = options => requests.push(options);
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/purchase-lot-price-modal.js'), 'utf8'));
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/financial-dashboard.js'), 'utf8'));
    dom.window.initFinancialDashboard({currencySymbol: '$', refreshDashboard: () => state.refreshes++, labels: {units: 'units', salesError: 'Load failed'}});
    return {dom, $, requests, state};
}
test('unit counts open the filtered sales modal with safe invoice text and pagination', () => {
    const f = fixture();
    f.$('.finance-sales-details').first().trigger('click');
    assert.equal(f.$('#finance-sales-modal').hasClass('in'), true);
    assert.match(f.requests[0].url, /location_ids%5B0%5D=1/);
    f.requests[0].success({page: 1, last_page: 2, total: 26, units: 5, rows: [{invoice_no: '<img src=x>', customer_name: '<script>bad()</script>', units: -1, amount: -110}]});
    assert.equal(f.$('#finance-sales-table img, #finance-sales-table script').length, 0);
    assert.match(f.$('#finance-sales-table').text(), /<img src=x>/);
    assert.match(f.$('#finance-sales-summary').text(), /5 units/);
    assert.match(f.$('#finance-sales-table').text(), /-110.00/);
    f.$('#finance-sales-next').trigger('click');
    assert.equal(f.requests[1].data.page, 2);
});
test('closing or reopening the modal ignores stale responses and displays loading errors', () => {
    const f = fixture();
    f.$('.finance-sales-details').first().trigger('click');
    f.$('#finance-sales-modal').trigger('hidden.bs.modal');
    f.requests[0].success({page: 1, last_page: 1, total: 999, units: 999, rows: []});
    assert.equal(f.$('#finance-sales-summary').text(), '');
    f.$('.finance-sales-details').first().trigger('click');
    f.requests[1].error({responseJSON: {message: 'Forbidden'}});
    assert.equal(f.$('#finance-sales-error').text(), 'Forbidden');
    assert.equal(f.$('#finance-sales-error').prop('hidden'), false);
    assert.equal(f.$('#finance-sales-table tbody').text(), '');
});

test('page size, direct page navigation and refresh preserve the sales filters', () => {
    const f = fixture();
    f.$('.finance-sales-details').first().trigger('click');
    f.requests[0].success({page: 1, last_page: 3, total: 26, units: 26, rows: []});
    f.$('#finance-sales-page-size').val('10').trigger('change');
    assert.equal(f.requests[1].data.per_page, 10);
    assert.equal(f.requests[1].data.page, 1);
    f.requests[1].success({page: 1, last_page: 3, total: 26, units: 26, rows: []});
    f.$('#finance-sales-page-input').val('3').trigger('change');
    assert.equal(f.requests[2].data.page, 3);
    f.requests[2].success({page: 3, last_page: 3, total: 26, units: 26, rows: []});
    f.$('#finance-sales-refresh').trigger('click');
    assert.equal(f.requests[3].data.page, 3);
    assert.equal(f.requests[3].url, f.requests[0].url);
});
test('authorized edit links open the existing form and refresh the list and dashboard after editing', () => {
    const f = fixture();
    f.$('.finance-sales-details').first().trigger('click');
    f.requests[0].success({page: 1, last_page: 1, total: 1, units: 1, rows: [{units: 1, amount: 20, edit_url: '/pos/1/edit'}]});
    const edit = f.$('.finance-sales-edit');
    assert.equal(edit.attr('href'), '/pos/1/edit');
    assert.equal(edit.attr('target'), '_blank');
    edit.on('click', event => event.preventDefault()).trigger('click');
    f.$(f.dom.window).triggerHandler('focus');
    assert.equal(f.requests.length, 2);
    f.$('#finance-sales-modal').trigger('hidden.bs.modal');
    assert.equal(f.state.refreshes, 1);
});

test('All rows sends the unpaginated option and disables page navigation', () => {
    const f = fixture();
    f.$('.finance-sales-details').first().trigger('click');
    f.$('#finance-sales-page-size').val('-1').trigger('change');
    assert.equal(f.requests[1].data.per_page, -1);
    assert.equal(f.requests[1].data.page, 1);
    f.requests[1].success({page: 1, last_page: 1, total: 200, units: 200, rows: []});
    assert.equal(f.$('#finance-sales-prev').prop('disabled'), true);
    assert.equal(f.$('#finance-sales-next').prop('disabled'), true);
});

test('column headings toggle sorting, reset to page one and preserve sort when paging', () => {
    const f = fixture();
    f.$('.finance-sales-details').first().trigger('click');
    assert.equal(f.requests[0].data.sort, 'date');
    assert.equal(f.requests[0].data.direction, 'desc');
    f.$('[data-sort="amount"]').trigger('click');
    assert.equal(f.requests[1].data.sort, 'amount');
    assert.equal(f.requests[1].data.direction, 'asc');
    assert.equal(f.requests[1].data.page, 1);
    assert.equal(f.$('[data-sort="amount"]').closest('th').attr('aria-sort'), 'ascending');
    f.requests[1].success({page: 1, last_page: 2, total: 26, units: 26, rows: []});
    f.$('#finance-sales-next').trigger('click');
    assert.equal(f.requests[2].data.sort, 'amount');
    assert.equal(f.requests[2].data.direction, 'asc');
    assert.equal(f.requests[2].data.page, 2);
    f.$('[data-sort="amount"]').trigger('click');
    assert.equal(f.requests[3].data.direction, 'desc');
    assert.equal(f.$('[data-sort="amount"]').closest('th').attr('aria-sort'), 'descending');
});

test('cost amounts open the cost sales list with purchase values and source labels', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    assert.match(f.requests[0].url, /cost-sales/);
    assert.match(f.$('#finance-sales-title').text(), /Cost/);
    assert.equal(f.$('#finance-sales-amount-label').text(), 'Cost');
    f.requests[0].success({page: 1, last_page: 1, total: 2, units: 2, amount: 60, rows: [
        {source: 'main', units: 1, amount: 60, purchase_price: 60, selling_price: 100, profit_loss: 40, sku: 'SKU-001', lots: 'LOT-01', product: '<img src=x>', estimated: 1},
        {source: 'accessory', units: 1, amount: null}
    ]});
    assert.match(f.$('#finance-sales-table').text(), /Estimated cost/);
    assert.match(f.$('#finance-sales-table').text(), /accessory/);
    assert.match(f.$('#finance-sales-table').text(), /SKU-001/);
    assert.match(f.$('#finance-sales-table').text(), /LOT-01/);
    assert.equal(f.$('#finance-sales-table img').length, 0);
    assert.equal(f.$('#finance-sales-table tbody tr').first().children('td').length, 8);
    assert.match(f.$('#finance-sales-table').text(), /40.00/);
    assert.match(f.$('#finance-sales-table').text(), /—/);
    assert.match(f.$('#finance-sales-summary').text(), /60.00/);
    f.$('.finance-sales-details').first().trigger('click');
    assert.equal(f.$('#finance-sales-amount-label').text(), 'Amount');
    assert.equal(f.$('#finance-sales-title').text(), 'POS sales list');
});

test('professional cost table searches all rows and updates filtered totals and navigation', async () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    assert.equal(f.$('#finance-sales-modal').hasClass('finance-cost-mode'), true);
    assert.equal(f.$('.finance-cost-tools').prop('hidden'), false);
    f.$('#finance-cost-search').val('LOT-01').trigger('input');
    await new Promise(resolve => setTimeout(resolve, 350));
    assert.equal(f.requests[1].data.q, 'LOT-01');
    assert.equal(f.requests[1].data.page, 1);
    f.requests[1].success({page: 1, last_page: 2, total: 26, units: 26, amount: 260, profit_loss: -20, estimated_count: 2, missing_count: 0, rows: [{units: -1, amount: -10, profit_loss: -2, lots: '<img>, LOT-01', sku: 'SKU'}]});
    assert.equal(f.$('#finance-cost-stat-total').text(), '$260.00');
    assert.equal(f.$('#finance-cost-stat-profit').hasClass('finance-profit-negative'), true);
    assert.match(f.$('#finance-cost-quality').text(), /2 estimated/);
    assert.equal(f.$('#finance-sales-table img').length, 0);
    assert.equal(f.$('#finance-sales-table tbody tr').hasClass('finance-cost-return'), true);
    assert.equal(f.$('#finance-sales-table').attr('aria-busy'), 'false');
    f.$('#finance-sales-last').trigger('click');
    assert.equal(f.requests[2].data.page, 2);
    assert.equal(f.requests[2].data.q, 'LOT-01');
    f.$('#finance-cost-clear-search').trigger('click');
    assert.equal(f.requests[3].data.q, '');
    assert.equal(f.requests[3].data.page, 1);
});

function openQuickCost(f, requestIndex) {
    f.$('.finance-cost-price-edit').trigger('click');
    f.requests[requestIndex].success({lots: [{id: 1, lot: 'LOT-01', reference: 'PUR-1', purchase_price: 50}], cost_version: 'a'.repeat(64), update_url: '/cost/main/1'});
    assert.equal(f.$('#finance-purchase-price-modal').hasClass('in'), true);
}
test('quick cost editing loads the original lot, saves with CSRF and refreshes filtered totals', () => {
    const f = fixture();
    f.$('head').append('<meta name="csrf-token" content="csrf-token">');
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, total: 1, units: 2, amount: 100, profit_loss: 20, rows: [{units: 2, purchase_price: 50, amount: 100, profit_loss: 20, cost_edit_url: '/cost/main/1'}]});
    openQuickCost(f, 1);
    assert.equal(f.$('#finance-purchase-price-modal form input[type="number"]').val(), '50');
    assert.equal(f.$('#finance-purchase-price-modal form input[type=number]').length, 1);
    f.$('#finance-purchase-price-modal form input[type="number"]').val('60');
    f.$('#finance-purchase-price-modal form').trigger('submit');
    assert.equal(f.requests[2].method, 'PUT');
    assert.equal(f.requests[2].url, '/cost/main/1');
    assert.match(f.requests[2].data, /prices%5B0%5D%5Bpurchase_price%5D=60/);
    assert.match(f.requests[2].data, /cost_version=aaaa/);
    assert.equal(f.requests[2].headers['X-CSRF-TOKEN'], 'csrf-token');
    f.requests[2].success({success: true});
    assert.equal(f.requests[3].url, f.requests[0].url);
    assert.equal(f.requests[3].data.page, 1);
    f.$('#finance-sales-modal').trigger('hidden.bs.modal');
    assert.equal(f.state.refreshes, 1);
});
test('canceling or rejecting an original-lot price edit keeps the recorded cost unchanged', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, total: 1, units: 1, amount: 50, rows: [{units: 1, purchase_price: 50, amount: 50, cost_edit_url: '/cost/main/1'}]});
    openQuickCost(f, 1);
    f.$('.purchase-lot-price-cancel').trigger('click');
    assert.equal(f.$('#finance-purchase-price-modal').hasClass('in'), false);
    assert.equal(f.requests.length, 2);
    openQuickCost(f, 2);
    f.$('#finance-purchase-price-modal form').trigger('submit');
    f.requests[3].error({responseJSON: {errors: {purchase_price: ['Invalid cost']}}});
    f.requests[3].complete();
    assert.equal(f.$('#finance-purchase-price-modal .purchase-lot-price-error').text(), 'Invalid cost');
    assert.equal(f.$('#finance-purchase-price-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-purchase-price-modal form button').first().prop('disabled'), false);
});

test('POS total opens the actual Stock Sell Report in a filtered modal and stops it on close', () => {
    const f = fixture();
    f.$('.finance-pos-report-details').trigger('click');
    assert.equal(f.$('#finance-pos-report-modal').hasClass('in'), true);
    const url = new URL(f.$('#finance-pos-report-frame').attr('src'));
    assert.equal(url.pathname, '/smart-stock-inventory/stock-reports/sell');
    assert.equal(url.searchParams.get('finance_embed'), '1');
    assert.equal(url.searchParams.get('start_date'), '2026-11-01');
    assert.equal(url.searchParams.get('location_id[0]'), '1');
    const tab = new URL(f.$('#finance-pos-report-tab').attr('href'));
    assert.equal(tab.searchParams.has('finance_embed'), false);
    assert.equal(tab.searchParams.get('end_date'), '2026-11-05');
    f.$('#finance-pos-report-frame').trigger('load');
    assert.equal(f.$('#finance-pos-report-loading').prop('hidden'), true);
    f.$('#finance-pos-report-modal').trigger('hidden.bs.modal');
    assert.equal(f.$('#finance-pos-report-frame').attr('src'), 'about:blank');
});
