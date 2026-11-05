const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function fixture() {
    const dom = new JSDOM(`<meta name="csrf-token" content="test-token"><details id="finance-filter-panel" open></details>
        <input id="finance-start" value="2026-11-01"><input id="finance-end" value="2026-11-04"><input id="finance-date-range">
        <select id="finance-expense-categories" multiple></select><input id="finance-expense-filter-applied">
        <a class="finance-expense-details" href="/reports/financial-dashboard/expenses?source=main&expense_categories%5B0%5D=Office">Details</a>
        <div id="finance-expense-details-modal"><div id="finance-details-error" hidden></div><div id="finance-details-success" hidden></div>
        <form id="finance-details-edit-form" hidden><input id="finance-edit-ref" name="ref_no"><input id="finance-edit-date" name="transaction_date">
        <input id="finance-edit-amount" name="final_total"><input id="finance-edit-version" name="updated_at">
        <textarea id="finance-edit-note" name="additional_notes"></textarea><select id="finance-edit-category" name="expense_category_id"></select>
        <select id="finance-edit-subcategory" name="expense_sub_category_id"></select><button type="submit">Save</button><button type="button" id="finance-edit-cancel">Cancel</button></form>
        <span id="finance-details-count"></span><span id="finance-details-total"></span><span id="finance-details-page"></span>
        <select id="finance-details-page-size"><option selected>25</option><option>10</option><option value="-1">All</option></select><input id="finance-details-page-input"><button id="finance-details-refresh"></button>
        <table id="finance-details-table"><thead><tr><th><button class="finance-expense-sort" data-sort="amount">Amount<span>↕</span></button></th></tr></thead><tbody></tbody></table><button id="finance-details-prev">Previous</button><button id="finance-details-next">Next</button></div>`,
        {url: 'http://localhost', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    $('body').append('<div id="finance-expense-edit-modal"><div id="finance-edit-error" hidden></div></div>');
    $('#finance-details-edit-form').appendTo('#finance-expense-edit-modal');
    const requests = [];
    const state = {refreshes: 0};
    dom.window.$ = dom.window.jQuery = $;
    dom.window.document.getElementById('finance-details-edit-form').scrollIntoView = () => {};
    $.fn.modal = function(action) {
        this.toggleClass('in', action === 'show');
        if (action === 'hide') this.trigger('hidden.bs.modal');
        return this;
    };
    $.ajax = options => requests.push(options);
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/financial-dashboard.js'), 'utf8'));
    dom.window.initFinancialDashboard({currencySymbol: '$', refreshDashboard: () => state.refreshes++, labels: {edit: 'Edit', error: 'Error', none: 'None'}});
    return {dom, $, requests, state};
}

function listResponse(editable = true) {
    return {page: 1, last_page: 1, total: 1, amount: 100, rows: [{
        id: 1, source: 'main', transaction_date: '2026-11-03 12:00:00', ref_no: '<img src=x>',
        category_name: 'Office', location_name: 'Branch A', amount: 100, additional_notes: '<script>bad()</script>',
        edit_url: editable ? '/reports/financial-dashboard/expenses/main/1' : null,
    }]};
}

function openEdit(f) {
    f.$('.finance-expense-details').trigger('click');
    f.requests[0].success(listResponse());
    f.requests[0].complete();
    f.$('.finance-edit-expense').trigger('click');
    f.requests[1].success({
        expense: {ref_no: 'EXP-1', transaction_date: '2026-11-03 12:00:00', final_total: 100, additional_notes: 'Existing note',
            expense_category_id: 1, expense_sub_category_id: 2, updated_at: '2026-11-01 12:00:00'},
        categories: [{id: 1, parent_id: null, name: 'Office'}, {id: 2, parent_id: 1, name: 'Supplies'}],
        update_url: '/reports/financial-dashboard/expenses/main/1',
    });
}

test('expense details open in a modal using the selected filters and escape stored text', () => {
    const f = fixture();
    f.$('.finance-expense-details').trigger('click');
    assert.match(f.requests[0].url, /source=main/);
    f.requests[0].success(listResponse());
    f.requests[0].complete();
    assert.equal(f.$('#finance-expense-details-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-details-table img, #finance-details-table script').length, 0);
    assert.match(f.$('#finance-details-table tbody').text(), /<img src=x>/);
    assert.equal(f.$('#finance-details-total').text(), '$100.00');
    assert.equal(f.$('.finance-edit-expense').length, 1);
});

test('editing saves the versioned form with CSRF protection and refreshes the dashboard after closing', () => {
    const f = fixture();
    openEdit(f);
    assert.equal(f.$('#finance-details-edit-form').prop('hidden'), false);
    assert.equal(f.$('#finance-expense-edit-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-expense-details-modal').hasClass('in'), false);
    assert.equal(f.$('#finance-edit-version').val(), '2026-11-01 12:00:00');
    assert.equal(f.$('#finance-edit-subcategory').val(), '2');
    f.$('#finance-edit-amount').val('110');
    f.$('#finance-details-edit-form').trigger('submit');
    assert.equal(f.requests[2].method, 'PUT');
    assert.match(f.requests[2].data, /final_total=110/);
    assert.match(f.requests[2].data, /updated_at=2026-11-01/);
    assert.equal(f.requests[2].headers['X-CSRF-TOKEN'], 'test-token');
    f.requests[2].success({success: true, msg: 'Saved'});
    f.requests[2].complete();
    assert.equal(f.$('#finance-details-edit-form').prop('hidden'), true);
    assert.equal(f.$('#finance-expense-edit-modal').hasClass('in'), false);
    assert.equal(f.$('#finance-expense-details-modal').hasClass('in'), true);
    assert.equal(f.requests.length, 4, 'the expense list reloads after saving');
    assert.equal(f.state.refreshes, 0);
    f.$('#finance-expense-details-modal').modal('hide');
    assert.equal(f.state.refreshes, 1);
});

test('validation failures retain the editable form and do not refresh the dashboard', () => {
    const f = fixture();
    openEdit(f);
    f.$('#finance-details-edit-form').trigger('submit');
    f.requests[2].error({responseJSON: {errors: {final_total: ['Amount is invalid.']}}});
    f.requests[2].complete();
    assert.equal(f.$('#finance-edit-error').text(), 'Amount is invalid.');
    assert.equal(f.$('#finance-expense-edit-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-details-edit-form').prop('hidden'), false);
    f.$('#finance-expense-details-modal').modal('hide');
    assert.equal(f.state.refreshes, 0);
});

test('read-only expense lists do not offer an edit action', () => {
    const f = fixture();
    f.$('.finance-expense-details').trigger('click');
    f.requests[0].success(listResponse(false));
    assert.equal(f.$('.finance-edit-expense').length, 0);
});

test('canceling the edit popup returns to the list without saving or refreshing the dashboard', () => {
    const f = fixture();
    openEdit(f);
    f.$('#finance-edit-cancel').trigger('click');
    assert.equal(f.$('#finance-expense-edit-modal').hasClass('in'), false);
    assert.equal(f.$('#finance-expense-details-modal').hasClass('in'), true);
    assert.equal(f.$('#finance-details-edit-form').prop('hidden'), true);
    assert.equal(f.requests.length, 2);
    assert.equal(f.state.refreshes, 0);
});

test('expense sorting and pagination retain category filters and support All rows', () => {
    const f = fixture();
    f.$('.finance-expense-details').trigger('click');
    f.$('.finance-expense-sort').trigger('click');
    assert.equal(f.requests[1].data.sort, 'amount');
    assert.equal(f.requests[1].data.direction, 'asc');
    assert.equal(f.requests[1].data.page, 1);
    assert.match(f.requests[1].url, /expense_categories/);
    assert.equal(f.$('.finance-expense-sort').closest('th').attr('aria-sort'), 'ascending');
    f.requests[1].success({...listResponse(), last_page: 3});
    f.requests[1].complete();
    f.$('#finance-details-next').trigger('click');
    assert.equal(f.requests[2].data.page, 2);
    assert.equal(f.requests[2].data.sort, 'amount');
    f.$('#finance-details-page-size').val('-1').trigger('change');
    assert.equal(f.requests[3].data.per_page, -1);
    assert.equal(f.requests[3].data.page, 1);
    f.requests[3].success(listResponse());
    f.requests[3].complete();
    assert.equal(f.$('#finance-details-next').prop('disabled'), true);
    f.$('.finance-expense-sort').trigger('click');
    assert.equal(f.requests[4].data.direction, 'desc');
});
