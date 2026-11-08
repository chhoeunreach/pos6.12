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
    $('body').append('<div id="finance-purchase-edit-modal" class="modal"><h4 id="finance-purchase-edit-title"></h4><p id="finance-purchase-edit-loading"></p><iframe id="finance-purchase-edit-frame" src="about:blank"></iframe></div>');
    $('body').append('<a class="finance-pos-report-details" href="/smart-stock-inventory/stock-reports/sell?finance_embed=1&start_date=2026-11-01&end_date=2026-11-05&location_id%5B0%5D=1">POS total</a><div id="finance-pos-report-modal"><span id="finance-pos-report-loading"></span><iframe id="finance-pos-report-frame" src="about:blank"></iframe><a id="finance-pos-report-tab"></a></div>');
    $('#finance-sales-modal').append('<div class="finance-cost-tools" hidden><input id="finance-cost-search"><button id="finance-cost-clear-search"></button></div><div class="finance-cost-stats" hidden><div><strong id="finance-cost-stat-qty"></strong></div><div><strong id="finance-cost-stat-total"></strong></div><div><strong id="finance-cost-stat-profit"></strong></div></div><div id="finance-cost-quality" hidden></div><span id="finance-sales-range"></span><button id="finance-sales-first"></button><button id="finance-sales-last"></button>');
    $('#finance-sales-modal').append('<div class="finance-cost-export-actions"><button class="finance-cost-export" data-format="csv"></button><button class="finance-cost-export" data-format="excel"></button><button class="finance-cost-export" data-format="print"></button></div>');
    $('#finance-sales-table').prepend('<thead id="finance-sales-cost-head"><tr><th>SKU</th><th>Lots</th><th>Product</th><th>Qty</th><th>Purchase</th><th>Selling</th><th>Cost</th><th>Profit</th></tr></thead>');
    $('<div id="finance-sales-controls">').insertBefore('#finance-sales-table').append($('#finance-sales-summary, #finance-sales-page-size, #finance-sales-refresh'));
    state.downloads = [];
    dom.window.URL.createObjectURL = blob => { state.downloads.push(blob); return 'blob:export'; };
    dom.window.URL.revokeObjectURL = () => {};
    dom.window.HTMLAnchorElement.prototype.click = function() { state.filename = this.download; };
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
test('quick row price links open a modal and refresh the current report block only after saving', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, rows: [{units: 1, purchase_price: 2540,
        purchase_edit_links: [{lot: 'SERIAL-1', reference: 'PUR-12', purchase_price: 2549, url: '/reports/financial-dashboard/purchase-price/main/12'}]}]});
    f.$('#finance-purchase-price-modal form').append('<a class="purchase-lot-price-full" target="_blank" rel="noopener" hidden>View full purchase</a>');
    const link = f.$('.finance-cost-price-edit-link');
    assert.equal(link.attr('target'), undefined);
    assert.equal(link.attr('aria-haspopup'), 'dialog');
    const click = f.$.Event('click');
    link.trigger(click);
    assert.equal(click.isDefaultPrevented(), true);
    assert.equal(f.$('#finance-purchase-price-modal').hasClass('in'), true);
    assert.equal(f.requests[1].url, link.attr('href'));
    f.requests[1].success({lots: [{id: 12, lot: 'SERIAL-1', reference: 'PUR-12', purchase_price: 2549}], cost_version: 'a'.repeat(64), update_url: link.attr('href'), full_purchase_url: '/purchases/12/edit'});
    const full = f.$('#finance-purchase-price-modal .purchase-lot-price-full');
    assert.equal(full.prop('hidden'), false);
    assert.equal(full.attr('href'), '/purchases/12/edit');
    assert.equal(full.attr('target'), '_blank');
    assert.equal(full.attr('rel'), 'noopener');
    f.$('#finance-purchase-price-modal input[type=number]').val('2600');
    f.$('#finance-purchase-price-modal form').trigger('submit');
    assert.equal(f.requests[2].method, 'PUT');
    assert.match(f.requests[2].data, /purchase_price%5D=2600/);
    assert.equal(f.requests.length, 3, 'the block waits for a successful save');
    f.requests[2].success({success: true});
    f.requests[2].complete();
    assert.equal(f.$('#finance-purchase-price-modal').hasClass('in'), false);
    assert.equal(f.$('#finance-sales-modal').hasClass('in'), true);
    assert.equal(full.prop('hidden'), true);
    assert.equal(full.attr('href'), undefined);
    assert.equal(f.requests.length, 4);
    assert.equal(f.requests[3].url, f.requests[0].url);
    assert.equal(f.requests[3].data.page, 1);
    assert.equal(f.state.refreshes, 0, 'saving refreshes the detail block without reloading the dashboard');
});

test('canceling a quick row price modal does not refresh the report', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, rows: [{units: 1, purchase_price: 50,
        purchase_edit_links: [{lot: 'LOT', reference: 'PUR-12', purchase_price: 50, url: '/quick-price/main/12'}]}]});
    f.$('.finance-cost-price-edit-link').trigger('click');
    f.$('#finance-purchase-price-modal').modal('hide');
    f.$('#finance-sales-modal').modal('hide');
    assert.equal(f.requests.length, 2);
    assert.equal(f.state.refreshes, 0);
});

test('accessory and service revenue links open source-scoped details with revenue labels', () => {
    const f = fixture();
    f.$('body').append('<a class="finance-sales-details revenue-link" data-cost-details="1" data-detail-kind="revenue" data-detail-title="Accessory net sales" href="/reports/financial-dashboard/revenue-sales?source=accessory&location_ids%5B0%5D=-2">Accessory</a>');
    f.$('.finance-cost-stats').append('<span id="finance-cost-stat-amount-label"></span>');
    f.$('.revenue-link').trigger('click');
    assert.match(f.requests[0].url, /revenue-sales\?source=accessory/);
    assert.equal(f.$('#finance-sales-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-sales-title').text(), 'Accessory net sales');
    assert.equal(f.$('#finance-cost-stat-amount-label').text(), 'Net sales');
    f.requests[0].success({page: 1, last_page: 1, total: 1, units: 2, amount: 100, profit_loss: 60,
        rows: [{source: 'accessory', product: 'Accessory', units: 2, purchase_price: 20, selling_price: 50, amount: 100, profit_loss: 60}]});
    assert.match(f.$('#finance-sales-summary').text(), /Net sales: \$100\.00/);
    assert.equal(f.$('#finance-cost-stat-total').text(), '$100.00');
    assert.equal(f.$('#finance-sales-table tbody tr').first().children().length, 7);
    assert.equal(f.$('#finance-sales-cost-head th').eq(7).prop('hidden'), true);
    assert.equal(f.$('#finance-sales-table tbody tr').first().children().last().text(), '$100.00');
    assert.equal(f.$('#finance-sales-table tbody .finance-profit-positive, #finance-sales-table tbody .finance-profit-negative').length, 0);
    f.$('[data-cost-details]').first().trigger('click');
    assert.equal(f.$('#finance-cost-stat-amount-label').text(), 'Cost');
    assert.equal(f.$('#finance-sales-cost-head th').eq(7).prop('hidden'), false);
});

test('service revenue exports selling totals without a profit column or purchase cost deduction', async () => {
    const f = fixture();
    f.$('body').append('<a class="finance-sales-details revenue-link" data-cost-details="1" data-detail-kind="revenue" href="/reports/financial-dashboard/revenue-sales?source=service">Service</a>');
    f.$('.revenue-link').trigger('click');
    const result = {page: 1, last_page: 1, total: 1, units: 2, amount: 100, profit_loss: -20, estimated_count: 1,
        rows: [{source: 'service', product: 'Service', units: 2, purchase_price: 60, selling_price: 50, amount: 100, profit_loss: -20}]};
    f.requests[0].success(result);
    assert.equal(f.$('#finance-cost-stat-total').text(), '$100.00');
    assert.equal(f.$('#finance-cost-stat-profit').parent().prop('hidden'), true);
    assert.equal(f.$('#finance-cost-quality').prop('hidden'), true);
    f.$('[data-format="csv"]').trigger('click');
    assert.match(f.requests[1].url, /source=service/);
    f.requests[1].success(result);
    const reader = new f.dom.window.FileReader();
    const csv = await new Promise(resolve => { reader.onload = () => resolve(reader.result); reader.readAsText(f.state.downloads[0]); });
    const lines = csv.trim().split('\r\n');
    assert.equal(lines[0].split(',').length, 7);
    assert.equal(lines[0].includes('Profit'), false);
    assert.match(lines[1], /"2","60","50","100"$/);
    assert.match(lines[2], /"100"$/);
    assert.equal(f.state.filename, 'net-sales-details.csv');
});

test('SKU cells link to purchase-price-only history and escape SKU text', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, rows: [{sku: '<img src=x>', units: 1,
        purchase_price_history_url: '/reports/financial-dashboard/purchase-price-history/service/7'}]});
    const link = f.$('#finance-sales-table .purchase-price-history-link');
    assert.equal(link.attr('href'), '/reports/financial-dashboard/purchase-price-history/service/7');
    assert.equal(link.text(), '<img src=x>');
    assert.equal(link.attr('aria-haspopup'), 'dialog');
    assert.equal(f.$('#finance-sales-table img').length, 0);
});

test('each lot opens its source history in a modal without marking purchase data as changed', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, rows: [{source: 'accessory', units: 1, lots: 'LOT-1, LOT-2',
        lot_history_links: [{lot: 'LOT-1', url: '/lot-history/accessory/1/LOT-1'}, {lot: 'LOT-2', url: '/lot-history/accessory/1/LOT-2'}]}]});
    const links = f.$('.finance-lot-history-link');
    assert.equal(links.length, 2);
    links.last().trigger('click');
    assert.equal(f.$('#finance-purchase-edit-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-purchase-edit-frame').attr('src'), '/lot-history/accessory/1/LOT-2');
    assert.match(f.$('#finance-purchase-edit-title').text(), /Lot history · LOT-2/);
    f.$('#finance-purchase-edit-modal').modal('hide');
    assert.equal(f.$('#finance-purchase-edit-frame').attr('src'), 'about:blank');
    assert.equal(f.requests.length, 1);
    assert.equal(f.$('body').hasClass('modal-open'), true);
});

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

test('quick price load failures show the HTTP status when the server has no JSON error', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.requests[0].success({page: 1, last_page: 1, rows: [{units: 1, purchase_price: 50, cost_edit_url: '/cost/main/1'}]});
    f.$('.finance-cost-price-edit').trigger('click');
    f.requests[1].error({status: 404});
    assert.match(f.$('.purchase-lot-price-error').text(), /HTTP 404/);
    assert.match(f.$('.purchase-lot-price-diagnostics').text(), /GET \/cost\/main\/1\nHTTP 404/);
    assert.equal(f.$('.purchase-lot-price-save').prop('disabled'), true);
    assert.equal(f.$('.purchase-lot-price-loading').prop('hidden'), true);
    f.$('.finance-cost-price-edit').trigger('click');
    f.requests[2].error({status: 200}, 'parsererror');
    assert.match(f.$('.purchase-lot-price-error').text(), /HTTP 200, invalid JSON/);
    f.$('.finance-cost-price-edit').trigger('click');
    assert.equal(f.$('.purchase-lot-price-diagnostics').prop('hidden'), true);
    f.requests[3].error({status: 0}, 'error');
    assert.match(f.$('.purchase-lot-price-error').text(), /HTTP 0/);
    assert.match(f.$('.purchase-lot-price-diagnostics').text(), /HTTP 0/);
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

function exportResult() {
    return {units: -2, amount: -40, profit_loss: -10, rows: [{sku: '001', lots: 'LOT,01', product: '=SUM(1,2)', units: -2, purchase_price: 20, selling_price: 25, amount: -40, profit_loss: -10}, {sku: '002', product: '<script>bad()</script>', units: 1, purchase_price: null, amount: null, profit_loss: null}]};
}
test('CSV toolbar exports all filtered rows, preserves sorting and protects spreadsheet text', async () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.$('#finance-cost-search').val('LOT');
    f.$('[data-format="csv"]').trigger('click');
    const request = f.requests[1];
    assert.equal(request.url, f.requests[0].url);
    assert.deepEqual(JSON.parse(JSON.stringify(request.data)), {page: 1, per_page: -1, sort: 'sku', direction: 'asc', q: 'LOT'});
    assert.equal(f.$('[data-format="csv"]').prop('disabled'), true);
    request.success(exportResult());
    request.complete();
    const reader = new f.dom.window.FileReader();
    const content = await new Promise(resolve => { reader.onload = () => resolve(reader.result); reader.readAsText(f.state.downloads[0]); });
    assert.ok(content.includes('"\'=SUM(1,2)"'));
    assert.ok(content.includes('"LOT,01"'));
    assert.ok(content.includes('"-40"'));
    assert.equal(f.state.filename, 'cost-of-goods-sold.csv');
    assert.equal(f.$('[data-format="csv"]').prop('disabled'), false);
});
test('Excel toolbar creates typed worksheet cells with literal product text and missing costs', async () => {
    const f = fixture(), files = {};
    f.dom.window.JSZip = function() {
        this.file = (name, text) => { files[name] = text; };
        this.generate = () => new f.dom.window.Blob(['xlsx']);
    };
    f.$('[data-cost-details]').trigger('click');
    f.$('[data-format="excel"]').trigger('click');
    f.requests[1].success(exportResult());
    await Promise.resolve();
    const sheet = new f.dom.window.DOMParser().parseFromString(files['xl/worksheets/sheet1.xml'], 'application/xml');
    assert.equal(sheet.querySelector('parsererror'), null);
    assert.equal(sheet.querySelector('c[r="A2"]').getAttribute('t'), 'inlineStr');
    assert.equal(sheet.querySelector('c[r="A2"] t').textContent, '001');
    assert.equal(sheet.querySelector('c[r="C2"] t').textContent, '=SUM(1,2)');
    assert.equal(sheet.querySelector('c[r="G2"] v').textContent, '-40');
    assert.equal(sheet.querySelector('c[r="G3"] t').textContent, '—');
    assert.equal(sheet.querySelectorAll('f').length, 0);
    assert.equal(Object.keys(files).length, 5);
    assert.equal(f.state.filename, 'cost-of-goods-sold.xlsx');
});
test('print toolbar uses all matching rows and inserts product names as safe text', () => {
    const f = fixture();
    const preview = new JSDOM('<html><head></head><body></body></html>').window;
    let printed = false;
    preview.focus = () => {};
    preview.print = () => { printed = true; };
    f.dom.window.open = () => preview;
    f.$('[data-cost-details]').trigger('click');
    f.$('[data-format="print"]').trigger('click');
    f.requests[1].success(exportResult());
    assert.equal(printed, true);
    assert.equal(preview.document.querySelectorAll('tbody tr').length, 2);
    assert.equal(preview.document.querySelectorAll('script').length, 0);
    assert.match(preview.document.body.textContent, /<script>bad/);
    assert.equal(preview.document.querySelectorAll('tfoot tr').length, 1);
});
test('toolbar reports export failures and discards responses after closing the list', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    f.$('[data-format="csv"]').trigger('click');
    f.requests[1].error({responseJSON: {message: 'Forbidden'}});
    f.requests[1].complete();
    assert.equal(f.$('#finance-sales-error').text(), 'Forbidden');
    assert.equal(f.$('[data-format="csv"]').prop('disabled'), false);
    f.$('[data-format="csv"]').trigger('click');
    f.$('#finance-sales-modal').trigger('hidden.bs.modal');
    f.requests[2].success(exportResult());
    assert.equal(f.state.downloads.length, 0);
});

test('cost toolbar groups row controls and restores them when reopening ordinary sales', () => {
    const f = fixture();
    f.$('[data-cost-details]').trigger('click');
    assert.equal(f.$('.finance-cost-tools #finance-sales-page-size').length, 1);
    assert.equal(f.$('.finance-cost-tools #finance-sales-refresh').length, 1);
    assert.equal(f.$('.finance-cost-export-actions').prop('hidden'), false);
    f.$('.finance-sales-details').first().trigger('click');
    assert.equal(f.$('.finance-cost-tools #finance-sales-controls').length, 0);
    assert.equal(f.$('#finance-sales-controls').length, 1);
    assert.equal(f.$('.finance-cost-tools').prop('hidden'), true);
    assert.equal(f.$('.finance-cost-export-actions').prop('hidden'), true);
});
