// Run with Node; uses the existing jsdom and jquery dependencies under storage/app/stock-ui-tests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function fixture() {
    const dom = new JSDOM(`<meta name="csrf-token" content="test-token">
        <input type="checkbox" id="select_all_expense_categories">
        <button id="delete_selected_expense_categories" data-url="/expense-categories/bulk-delete" data-confirm="Delete selected?"></button>
        <span id="expense_categories_selected_count">0</span>
        <table id="expense_category_table"><tbody>
        <tr><td><input class="select_expense_category" type="checkbox" value="1"></td></tr>
        <tr><td><input class="select_expense_category" type="checkbox" value="2"></td></tr>
        </tbody></table>`, {runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    const requests = [];
    const state = {search: '', confirmed: false, reloads: 0};
    dom.window.$ = $;
    dom.window.LANG = {sure: 'Confirm', something_went_wrong: 'Error'};
    dom.window.toastr = {success() {}, error() {}};
    dom.window.swal = () => Promise.resolve(state.confirmed);
    dom.window.expense_cat_table = {
        page: {info: () => ({recordsDisplay: 3})},
        search: () => state.search,
        ajax: {
            params: () => ({start: 0, length: 2, search: {value: state.search}}),
            url: () => '/expense-categories',
            reload: () => state.reloads++,
        },
    };
    $.ajax = options => requests.push(options);
    const source = fs.readFileSync(path.join(root, 'public/js/app.js'), 'utf8');
    new Function(source); // Parse the complete production file as well as exercising the selection handlers.
    const start = source.indexOf('    var selectedExpenseCategories = new Set();');
    const end = source.indexOf("    $(document).on('submit', 'form#expense_category_add_form'", start);
    assert.ok(start > 0 && end > start);
    dom.window.eval(source.slice(start, end));
    $('#expense_category_table').trigger('draw.dt');
    return {dom, $, requests, state};
}

test('individual selections survive pagination and clear only when the search changes', () => {
    const {$, state} = fixture();
    $('.select_expense_category[value="1"]').prop('checked', true).trigger('change');
    $('#expense_category_table').trigger('search.dt').trigger('draw.dt');
    assert.equal($('#expense_categories_selected_count').text(), '1');
    assert.equal($('.select_expense_category[value="1"]').prop('checked'), true);
    $('#expense_category_table tbody').html('<tr><td><input class="select_expense_category" type="checkbox" value="3"></td></tr>');
    $('#expense_category_table').trigger('search.dt').trigger('draw.dt');
    $('.select_expense_category').prop('checked', true).trigger('change');
    assert.equal($('#expense_categories_selected_count').text(), '2');
    state.search = 'different';
    $('#expense_category_table').trigger('search.dt');
    assert.equal($('#expense_categories_selected_count').text(), '0');
    assert.equal($('#delete_selected_expense_categories').prop('disabled'), true);
});

test('select all fetches matching rows across pages and excludes manually unchecked rows from deletion', async () => {
    const {$, requests, state} = fixture();
    state.search = 'office';
    $('#expense_category_table').trigger('search.dt');
    $('#select_all_expense_categories').prop('checked', true).trigger('change');
    assert.equal(requests[0].data.length, -1);
    assert.equal(requests[0].data.search.value, 'office');
    requests[0].success({data: [{id: 1}, {id: 2}, {id: 3}]});
    requests[0].complete();
    assert.equal($('#expense_categories_selected_count').text(), '3');
    assert.equal($('#select_all_expense_categories').prop('checked'), true);
    $('#expense_category_table tbody').html('<tr><td><input class="select_expense_category" type="checkbox" value="3"></td></tr>');
    $('#expense_category_table').trigger('draw.dt');
    assert.equal($('.select_expense_category').prop('checked'), true);
    $('.select_expense_category').prop('checked', false).trigger('change');
    assert.equal($('#select_all_expense_categories').prop('indeterminate'), true);
    $('#delete_selected_expense_categories').trigger('click');
    await new Promise(setImmediate);
    assert.equal(requests.length, 1, 'cancelled confirmation must not delete anything');
    state.confirmed = true;
    $('#delete_selected_expense_categories').trigger('click');
    await new Promise(setImmediate);
    assert.equal(requests[1].method, 'POST');
    assert.deepEqual(Array.from(requests[1].data.ids), ['1', '2']);
    assert.equal(requests[1].headers['X-CSRF-TOKEN'], 'test-token');
    requests[1].success({success: true, msg: 'Deleted'});
    requests[1].complete();
    assert.equal($('#expense_categories_selected_count').text(), '0');
    assert.equal(state.reloads, 1);
});

test('an outdated select-all response cannot select rows from a previous search', () => {
    const {$, requests, state} = fixture();
    $('#select_all_expense_categories').prop('checked', true).trigger('change');
    state.search = 'new search';
    $('#expense_category_table').trigger('search.dt');
    requests[0].success({data: [{id: 1}, {id: 2}, {id: 3}]});
    requests[0].complete();
    assert.equal($('#expense_categories_selected_count').text(), '0');
});
