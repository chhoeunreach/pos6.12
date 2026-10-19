// Run with Node after installing jsdom and jquery under storage/app/stock-ui-tests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function editor(lineId = null, withDatePicker = false, linkedLine = null, changeLines = null) {
    const manual = fs.readFileSync(path.join(root, 'resources/views/stock_transfer/partials/manual_correction_modal.blade.php'), 'utf8');
    const plan = fs.readFileSync(path.join(root, 'resources/views/stock_transfer/partials/correction_plan_modal.blade.php'), 'utf8');
    const dom = new JSDOM(`<meta name="csrf-token" content="test-token"><button id="generate_stock_correction_plan"
        data-url="/plan" data-apply-url="/apply-plan" data-options-url="/options" data-preview-url="/preview" data-manual-apply-url="/apply"></button>
        <button id="edit_verified_stock_correction">Correct Serials</button>
        <button id="auto_correct_stock" data-url="/auto-correct">Auto Correct</button>
        <div id="auto_stock_correction_result" hidden></div>
        <button class="correct-stock-line" data-line-id="1">Correct This Line</button>
        <button class="correct-stock-line" data-line-id="2">Correct This Line</button>${manual}${plan}`, {url: 'http://localhost' + (linkedLine ? '?correct_line=' + linkedLine : ''), runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    dom.window.jQuery = $;
    dom.window.toastr = {error: () => {}};
    const datePicker = {shows: 0, selected: null, options: null, minimum: null,
        show() { this.shows++; }, hide() {}, clear() { this.selected = null; }, date() { return this.selected; }, minDate(value) { this.minimum = value; }};
    if (withDatePicker) {
        dom.window.moment_date_format = 'DD/MM/YYYY';
        dom.window.moment_time_format = 'HH:mm';
        $.fn.datetimepicker = function(options) {
            datePicker.options = options;
            return this.data('DateTimePicker', datePicker);
        };
    }
    $.fn.modal = function(action) {
        return this.each(function() {
            $(this).toggleClass('in', action === 'show').trigger(action === 'show' ? 'shown.bs.modal' : 'hidden.bs.modal');
        });
    };
    const calls = [];
    $.ajax = options => {
        const deferred = $.Deferred();
        calls.push({options, deferred});
        return deferred.promise();
    };
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_reconciliation.js'), 'utf8'));
    const candidate = {source_id: 2, source_serial: 'VERIFIED-PHONE', destination_id: 9,
        support: ['Recorded allocation'], issues: ['Delivery date requires verification.'], selectable: true};
    const lines = [
        {sell_line_id: 1, selected_serial: 'WRONG-PHONE', quantity: 1,
            unchanged_lines_date_floor: '2026-10-01 10:00:00',
            sources: [{id: 2, lot_number: 'VERIFIED-PHONE', receipt_date: '2026-10-01', available: 1}],
            destinations: [{id: 9, lot_number: 'WRONG-PHONE', quantity: 1}],
            recommendation: {prefill: false, message: 'Verify conflicting serials.', candidates: [candidate]}},
        {sell_line_id: 2, selected_serial: 'MATCHING-PHONE', quantity: 1,
            sources: [{id: 3, lot_number: 'MATCHING-PHONE', receipt_date: '2026-10-01', available: 1}],
            destinations: [{id: 10, lot_number: 'MATCHING-PHONE', quantity: 1}],
            recommendation: {prefill: true, source_id: 3, destination_id: 10, message: 'Records agree.',
                candidates: [{...candidate, source_id: 3, source_serial: 'MATCHING-PHONE', destination_id: 10, issues: []}]}},
        {sell_line_id: 3, selected_serial: 'USED-PHONE', quantity: 1,
            sources: [{id: 4, lot_number: 'USED-PHONE', receipt_date: '2026-10-01', available: 1}],
            destinations: [{id: 11, lot_number: 'USED-PHONE', quantity: 1}],
            recommendation: {prefill: false, message: 'Receiving batch is protected.', candidates: [{...candidate, source_id: 4,
                destination_id: 11, selectable: false, unavailable_reason: 'Receiving batch has later usage or references.'}]}}
    ];
    if (!linkedLine) {
        (lineId === null ? $('#edit_verified_stock_correction') : $(`.correct-stock-line[data-line-id="${lineId}"]`)).trigger('click');
    }
    if (changeLines) { changeLines(lines); }
    calls[0].deferred.resolve({reference: 'ST-TEST', transaction_date: '2026-10-01 12:00:00', lines});
    const row = id => $(`.manual-correction-row[data-line-id="${id}"]`);
    return {dom, $, calls, row, datePicker};
}

test('auto complete fills a supported missing selection and verifies a preview without saving stock', () => {
    const {dom, $, calls, row} = editor(1, false, null, lines => {
        lines[0].selected_serial = null;
        lines[0].recommendation.automatic_match = {source_id: 2, destination_id: 9};
    });
    assert.equal(row(1).find('.manual-source').val(), '2');
    assert.equal(row(1).find('.manual-destination').val(), '9');
    $('#auto_complete_manual_stock_correction').trigger('click');
    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.url, '/preview');
    assert.equal(calls[1].options.data.verified, 1);
    assert.match(calls[1].options.data.evidence, /source batch #2.*receiving batch #9/);
    assert.equal(calls[1].options.data.transaction_date, undefined);
    calls[1].deferred.resolve({plan: {transfer_id: 1, token: 'a'.repeat(64), changes: [], counter_repairs: []}});
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), false);
    assert.equal(calls.length, 2);
    dom.window.close();
});

test('a transfer without lot numbers verifies receipt IDs and quantity without requiring a serial', () => {
    const {dom, $, calls, row} = editor(1, false, null, lines => {
        lines[0].selected_serial = null;
        lines[0].sources[0].lot_number = null;
        lines[0].destinations[0].lot_number = null;
        lines[0].recommendation.candidates = [];
    });
    assert.equal(row(1).find('.manual-source').val(), '2');
    assert.equal(row(1).find('.manual-destination').val(), '9');
    assert.match(row(1).text(), /No serial/);
    row(1).find('.manual-source').trigger('change');
    row(1).find('.manual-destination').trigger('change');
    $('#auto_complete_manual_stock_correction').trigger('click');
    assert.equal(calls[1].options.url, '/preview');
    assert.equal(calls[1].options.data.corrections[0].source_id, '2');
    assert.equal(calls[1].options.data.corrections[0].destination_id, '9');
    dom.window.close();
});

test('applying a verified correction refreshes only changed rows and preserves other unsaved edits', () => {
    const {dom, $, calls} = editor(1, false, null, lines => {
        lines[0].selected_serial = null;
        lines[0].recommendation.automatic_match = {source_id: 2, destination_id: 9};
    });
    $('#generate_stock_correction_plan').attr('data-refresh-url', '/stock-transfers/1/edit');
    $('body').append('<input id="additional_notes" value="Unsaved notes"><table><tbody>' +
        '<tr data-stock-transfer-line-id="1"><td><input name="products[8][lot_no_line_id]" value="old"></td></tr>' +
        '<tr data-stock-transfer-line-id="2"><td><input name="products[9][quantity]" value="7"></td></tr></tbody></table>');
    const unchanged = $('[data-stock-transfer-line-id="2"]')[0];
    $('#auto_complete_manual_stock_correction').trigger('click');
    calls[1].deferred.resolve({plan: {transfer_id: 1, token: 'a'.repeat(64), changes: [], counter_repairs: []}});
    $('#apply_manual_stock_correction').trigger('click');
    assert.equal(calls[2].options.url, '/apply');
    assert.deepEqual(JSON.parse(JSON.stringify(calls[2].options.data.corrections)).map(row => row.sell_line_id), ['1']);
    calls[2].deferred.resolve({success: 1, msg: 'Correction saved.', plan: {changes: [{sell_line_id: 1}]}});
    assert.equal(calls[3].options.method, 'GET');
    assert.equal(calls[3].options.url, '/stock-transfers/1/edit');
    assert.deepEqual(Array.from(calls[3].options.data.refresh_line_ids), ['1']);
    calls[3].deferred.resolve('<table><tr data-stock-transfer-line-id="1"><td><input name="products[0][lot_no_line_id]" value="new"></td></tr>' +
        '<tr data-stock-transfer-line-id="2"><td>Replace me incorrectly</td></tr></table><script>window.unwantedScript = true;</script>');
    assert.equal($('[data-stock-transfer-line-id="1"] input').val(), 'new');
    assert.equal($('[data-stock-transfer-line-id="1"] input').attr('name'), 'products[8][lot_no_line_id]');
    assert.equal($('[data-stock-transfer-line-id="2"]')[0], unchanged);
    assert.equal($('[data-stock-transfer-line-id="2"] input').val(), '7');
    assert.equal($('#additional_notes').val(), 'Unsaved notes');
    assert.equal(dom.window.unwantedScript, undefined);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('a failed row refresh reports that saving succeeded and keeps the current document', () => {
    const {dom, $, calls} = editor(1);
    $('#generate_stock_correction_plan').attr('data-refresh-url', '/stock-transfers/1/edit');
    $('body').append('<table><tr data-stock-transfer-line-id="1"><td>Old serial</td></tr></table>');
    $('#auto_correct_stock').trigger('click');
    calls[1].deferred.resolve({complete: true, applied: true, changes: [{sell_line_id: 1}]});
    calls[2].deferred.reject({status: 500});
    assert.match($('#auto_stock_correction_result').text(), /Correction saved, but the updated rows could not be loaded/);
    assert.match($('[data-stock-transfer-line-id="1"]').text(), /Old serial/);
    dom.window.close();
});

test('auto complete leaves unresolved selections blocked and never guesses a source', () => {
    const {dom, $, calls, row} = editor(1, false, null, lines => {
        lines[0].sources = [];
        lines[0].recommendation.candidates = [];
    });
    $('#auto_complete_manual_stock_correction').trigger('click');
    assert.equal(row(1).find('.manual-source').val(), '');
    assert.equal(calls.length, 1);
    assert.match($('#manual_stock_correction_error').text(), /select both/i);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('stock mismatch shows the system balance calculation without treating history as available stock', () => {
    const {dom, $} = editor();
    $('body').append(fs.readFileSync(path.join(root, 'resources/views/stock_transfer/partials/stock_error_modal.blade.php'), 'utf8'));
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_error.js'), 'utf8'));
    dom.window.POSStockError.show({stock_error: {serial: 'PHONE', recorded_available: 0, history_available: 1,
        system_available: 0, calculation: {received: 1, purchase_returns: 0, adjustments: 0, manufacturing_used: 0,
            recorded_consumption: 1, history_consumption: 0, difference: 1}}});
    const value = label => $('#stock_error_details tr').filter(function() { return $(this).find('th').text() === label; }).find('td').text();
    assert.equal(value('System Available Quantity'), '0');
    assert.equal(value('History Available Quantity'), '1');
    assert.equal(value('Recorded Consumption'), '1');
    assert.match(value('Balance Calculation'), /net consumption/);
    assert.match(value('System Availability Rule'), /Lower of recorded and history/);
    dom.window.close();
});

test('POS reconciliation error identifies the effective overselling policy and business', () => {
    const {dom, $} = editor();
    $('body').append(fs.readFileSync(path.join(root, 'resources/views/stock_transfer/partials/stock_error_modal.blade.php'), 'utf8'));
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_error.js'), 'utf8'));
    dom.window.POSStockError.show({stock_error: {business_id: 1, overselling_enabled: false,
        stock_policy_version: 'pos-overselling-20261015-2'}});
    const value = label => $('#stock_error_details tr').filter(function() { return $(this).find('th').text() === label; }).find('td').text();
    assert.equal(value('Allow overselling'), 'Disabled');
    assert.equal(value('Business ID'), '1');
    assert.equal(value('Stock validation version'), 'pos-overselling-20261015-2');
    dom.window.close();
});

test('POS reconciliation modal titles the review according to the overselling policy', () => {
    const {dom, $} = editor();
    $('body').append(fs.readFileSync(path.join(root, 'resources/views/stock_transfer/partials/stock_error_modal.blade.php'), 'utf8'));
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_error.js'), 'utf8'));
    dom.window.POSStockError.show({stock_error: {overselling_enabled: true, business_id: 1}});
    assert.equal($('#stock_error_title').text(), 'Stock Records Review');
    dom.window.POSStockError.show({stock_error: {overselling_enabled: false, business_id: 1}});
    assert.equal($('#stock_error_title').text(), 'Stock Reconciliation Required');
    dom.window.close();
});

test('authorized correction link opens only the requested line without approving stock changes', () => {
    const {dom, $, calls, row} = editor(null, false, '1');
    assert.equal(calls.length, 1);
    assert.equal($('#manual_stock_correction_modal').hasClass('in'), true);
    assert.equal($('.manual-correction-row').length, 1);
    assert.equal(row(1).find('.manual-line-enabled').prop('checked'), true);
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('auto correction shows conflicts inline without opening a correction plan', () => {
    const {dom, $, calls} = editor();
    $('#manual_stock_correction_modal').modal('hide');
    $('#auto_correct_stock').trigger('click').trigger('click');
    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.url, '/auto-correct');
    assert.deepEqual({...calls[1].options.data}, {_token: 'test-token'});
    assert.equal($('#auto_correct_stock').prop('disabled'), true);
    calls[1].deferred.resolve({success: 1, applied: false, plan: {
        reference: 'ST-TEST', status: 'verification_required', blockers: ['Conflicting serials'],
        counter_repairs: [], next_steps: ['Verify the delivered serial.'], review_items: [
            {reference: 'ST-OTHER', line_id: 61351, date: '2026-10-01', status: 'final', reasons: ['Conflicting serials'], url: '/stock-transfers/5/edit?correct_line=61351'},
            {reference: '<script>bad</script>', line_id: null, status: 'unknown', reasons: ['Unverified'], url: 'javascript:alert(1)'}
        ]
    }});
    assert.equal($('#stock_correction_plan_modal').hasClass('in'), false);
    assert.match($('#auto_stock_correction_result').text(), /Conflicting serials/);
    assert.equal($('#auto_stock_correction_result').prop('hidden'), false);
    assert.equal($('#auto_stock_correction_result tbody tr').length, 2);
    assert.equal($('#auto_stock_correction_result a').length, 1);
    assert.match($('#auto_stock_correction_result a').attr('href'), /correct_line=61351/);
    assert.equal($('#auto_stock_correction_result script').length, 0);
    assert.equal($('#apply_stock_correction_plan').prop('disabled'), true);
    assert.equal($('#auto_correct_stock').prop('disabled'), false);
    dom.window.close();
});

test('auto correction network failure restores controls without approving corrections', () => {
    const {dom, $, calls} = editor();
    $('#auto_correct_stock').trigger('click');
    calls[1].deferred.reject({status: 0});
    assert.equal($('#auto_correct_stock').prop('disabled'), false);
    assert.equal($('#generate_stock_correction_plan').prop('disabled'), false);
    assert.equal($('#auto_correct_stock').text(), 'Auto Correct');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.match($('#auto_stock_correction_result').text(), /Connection interrupted/);
    dom.window.close();
});

test('auto correction reports verified consistency without opening a plan', () => {
    const {dom, $, calls} = editor();
    $('#auto_correct_stock').trigger('click');
    calls[1].deferred.resolve({success: 1, applied: false, complete: true});
    assert.equal($('#stock_correction_plan_modal').hasClass('in'), false);
    assert.match($('#auto_stock_correction_result').text(), /consistent/);
    assert.equal($('#auto_stock_correction_result').hasClass('text-success'), true);
    dom.window.close();
});

test('direct entry opens editor with editable selectors and problem rows first', () => {
    const {dom, $, row} = editor();
    assert.equal($('#manual_stock_correction_modal').hasClass('in'), true);
    assert.equal($('.manual-correction-row').first().attr('data-line-id'), '1');
    assert.equal(row(1).find('.manual-source').prop('disabled'), false);
    assert.equal(row(2).css('display'), 'none');
    assert.match($('#manual_stock_correction_summary').text(), /2 lines need review/);
    assert.match($('#manual_stock_correction_context').text(), /ST-TEST/);
    dom.window.close();
});

test('selecting a receipt activates its line and pairs the unique receiving batch without verifying anything', () => {
    const {dom, $, row} = editor();
    row(1).find('.manual-source').val('2').trigger('change');
    assert.equal(row(1).find('.manual-line-enabled').prop('checked'), true);
    assert.equal(row(1).find('.manual-destination').val(), '9');
    assert.equal(row(1).find('.manual-source').prop('required'), false);
    assert.equal(row(1).find('.manual-source').attr('aria-required'), 'true');
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    assert.equal($('#manual_stock_date_verified').prop('checked'), false);
    assert.equal($('#manual_stock_corrected_date').prop('disabled'), true);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('unavailable recommendations show a reason instead of a dead button', () => {
    const {dom, $, row} = editor();
    row(3).find('.manual-details-toggle').trigger('click');
    const detail = row(3).next('.manual-evidence-row');
    assert.notEqual(detail.css('display'), 'none');
    assert.match(detail.text(), /Unavailable: Receiving batch has later usage/);
    assert.equal(detail.find('.manual-use-candidate').length, 0);
    dom.window.close();
});

test('using a candidate fills the correct owner line and leaves physical verification unchecked', () => {
    const {dom, $, row} = editor();
    row(1).find('.manual-details-toggle').trigger('click');
    row(1).next('.manual-evidence-row').find('.manual-use-candidate').trigger('click');
    assert.equal(row(1).find('.manual-source').val(), '2');
    assert.equal(row(1).find('.manual-destination').val(), '9');
    assert.equal(row(2).find('.manual-line-enabled').prop('checked'), false);
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    dom.window.close();
});

test('missing receipt choices display an accessible error without an ajax request', () => {
    const {dom, $, calls, row} = editor();
    row(1).find('.manual-source').val('').trigger('change');
    row(1).find('.manual-destination').val('').trigger('change');
    row(1).find('.manual-line-enabled').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    assert.equal(calls.length, 1);
    assert.match($('#manual_stock_correction_error').text(), /Line #1: select both/);
    assert.equal(dom.window.document.activeElement, row(1).find('.manual-source')[0]);
    dom.window.close();
});

test('opening a correction fills records, factual notes and the recorded date without verifying them', () => {
    const {dom, $, row} = editor(1);
    assert.equal(row(1).find('.manual-source').val(), '2');
    assert.equal(row(1).find('.manual-destination').val(), '9');
    assert.match($('#manual_stock_correction_evidence').val(), /ST-TEST.*source batch #2/);
    assert.match($('#manual_stock_corrected_date').val(), /^2026-10-01T12:00/);
    assert.equal($('#manual_stock_date_verified').prop('checked'), false);
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    assert.equal($('#confirm_manual_stock_correction').prop('checked'), false);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.equal($('#manual_stock_correction_form .manual-section').length, 2);
    assert.equal($('#manual_stock_correction_modal .modal-footer').length, 1);
    dom.window.close();
});

test('default source uses the recorded batch among multiple receipts for the same serial', () => {
    const {dom, $, row} = editor(1, false, null, lines => {
        lines[0].selected_purchase_line_id = 22;
        lines[0].selected_serial = 'VERIFIED-PHONE';
        lines[0].sources.push({...lines[0].sources[0], id: 22});
        lines[0].destinations[0].lot_number = 'VERIFIED-PHONE';
        lines[0].destinations.push({id: 19, lot_number: 'OTHER-PHONE', quantity: 1});
    });
    assert.equal(row(1).find('.manual-source').val(), '22');
    assert.equal(row(1).find('.manual-destination').val(), '9');
    assert.match($('#manual_stock_correction_evidence').val(), /source batch #22/);
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    dom.window.close();
});

test('record defaults preserve custom correction notes when the selected line changes', () => {
    const {dom, $, row} = editor();
    $('#manual_stock_correction_evidence').val('Checked delivery document D-42 and the physical serial.').trigger('input');
    row(2).find('.manual-line-enabled').prop('checked', true).trigger('change');
    assert.equal($('#manual_stock_correction_evidence').val(), 'Checked delivery document D-42 and the physical serial.');
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    dom.window.close();
});

test('unverified related allocation shows the original document and keeps apply disabled', () => {
    const {dom, $, calls} = editor(1);
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    calls[1].deferred.reject({status: 422, responseJSON: {msg: 'Related line #42966 has an unverified allocation.',
        stock_error: {kind: 'related_allocation', sell_line_id: 42966, reference: 'ST-FIFO', status: 'draft', date: '2026-09-02',
            url: '/stock-transfers/4/edit?correct_line=42966'}}});
    assert.match($('#manual_stock_correction_error').text(), /Original document: ST-FIFO.*42966/);
    assert.equal($('#manual_stock_correction_error a').attr('href'), 'http://localhost/stock-transfers/4/edit?correct_line=42966');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('manual preview lists related allocations before approval without saving them', () => {
    const {dom, $, calls, row} = editor();
    row(1).find('.manual-source').val('2').trigger('change');
    $('#manual_stock_correction_evidence').val('Selected and receiving serials verified against delivery records.');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    calls[1].deferred.resolve({plan: {transfer_id: 1, token: 'a'.repeat(64), changes: [], counter_repairs: [],
        related_allocation_repairs: [{sell_line_id: 66356, transaction_id: 34959, selected_batch_id: 78835,
            serial: '<script>unsafe</script>', quantity: 1, allocations_before: [{purchase_line_id: 83760}]}]}});
    assert.equal($('#manual_stock_related_changes li').length, 1);
    assert.match($('#manual_stock_related_changes').text(), /Related line #66356/);
    assert.match($('#manual_stock_related_changes').text(), /83760 -> batch #78835/);
    assert.equal($('#manual_stock_related_changes script').length, 0);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.equal(calls.length, 2);
    dom.window.close();
});

test('preview explains an unchanged shortage and lists source movements before approval', () => {
    const {dom, $, calls, row} = editor();
    row(1).find('.manual-source').val('2').trigger('change');
    $('#manual_stock_correction_evidence').val('Serial verified against the selected and receiving records.');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    calls[1].deferred.resolve({plan: {transfer_id: 1, token: 'a'.repeat(64), changes: [], counter_repairs: [],
        remaining_stock_shortages: [{purchase_line_id: 37519, serial: 'LT6XWFQDXD', available_before: -1, available_after: -1,
            message: 'The existing source shortage remains unchanged.', movements: [
                {sell_line_id: 63104, reference: 'ST2026/4991', date: '2026-09-22', quantity: 1, url: '/stock-transfers/32961/edit'},
                {sell_line_id: 75525, reference: 'ST2026/6219', date: '2026-10-09', quantity: 1, url: 'javascript:alert(1)'}]}]}});
    assert.match($('#manual_stock_shortage_changes').text(), /history availability -1 -> -1/);
    assert.match($('#manual_stock_shortage_changes').text(), /ST2026\/4991/);
    assert.equal($('#manual_stock_shortage_changes a').length, 1);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('preview sends only selected lines and shows counter changes before approval', () => {
    const {dom, $, calls, row} = editor();
    row(1).find('.manual-source').val('2').trigger('change');
    $('#manual_stock_correction_evidence').val('Physical serial and delivery document verified.');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.data.corrections.length, 1);
    assert.equal(calls[1].options.data.corrections[0].sell_line_id, '1');
    assert.equal($('#manual_stock_correction_stage').text(), 'Checking Correction');
    calls[1].deferred.resolve({plan: {transfer_id: 1, token: 'a'.repeat(64), date_change: null,
        changes: [{sell_line_id: 1, selected_batch_before: 5, selected_batch_after: 2, receiving_serial_before: 'WRONG-PHONE', receiving_serial_after: 'VERIFIED-PHONE'}],
        counter_repairs: [{purchase_line_id: 5, quantity_sold_before: 1, quantity_sold_after: 0}]}});
    assert.equal($('#manual_stock_counter_changes tr').length, 1);
    assert.equal($('#manual_stock_correction_stage').text(), 'Preview Ready');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.equal($('#download_manual_stock_correction').prop('disabled'), false);
    $('#confirm_manual_stock_correction').prop('checked', true).trigger('change');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), false);
    $('#manual_stock_correction_evidence').val('Changed evidence after preview.').trigger('input');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.equal($('#download_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('date dependency errors show protected documents, safe review links and steps without enabling apply', () => {
    const {dom, $, calls, row} = editor(1);
    row(1).find('.manual-source').val('2').trigger('change');
    $('#manual_stock_correction_evidence').val('Delivery record and physical serial verified.');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    calls[1].deferred.reject({status: 422, responseJSON: {msg: 'Transfer dates cannot change while receiving batches are used.', stock_error: {
        kind: 'transfer_date_dependencies', recorded_date: '2026-10-09 14:53:00',
        steps: ['Keep the recorded date if accurate.', 'Review dependencies newest first.'],
        dependent_movements: [{reference: '<script>bad</script>', date: '2026-10-10 10:00:00', serials: ['PHONE'], status: 'draft', quantity: 1}],
        receiving_batches: [{id: 9, serial: 'PHONE', sold: 1, adjusted: 0, returned: 0, manufacturing_used: 0}],
        links: [{label: 'Review transfer', url: '/stock-transfers/4/edit'}, {label: 'Unsafe', url: 'javascript:alert(1)'},
            {label: 'External', url: 'https://example.com/private'}]
    }}});
    const $error = $('#manual_stock_correction_error');
    assert.equal($error.find('tbody tr').length, 1);
    assert.equal($error.find('script').length, 0);
    assert.match($error.text(), /newest first/);
    assert.match($error.text(), /sold 1/);
    assert.equal($error.find('a').length, 1);
    assert.equal($error.find('a').attr('href'), 'http://localhost/stock-transfers/4/edit');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.equal($('#download_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('selected matching rows stay visible when other matching records are hidden', () => {
    const {dom, $, row} = editor();
    $('#show_matching_stock_lines').prop('checked', true).trigger('change');
    row(2).find('.manual-line-enabled').prop('checked', true).trigger('change');
    $('#show_matching_stock_lines').prop('checked', false).trigger('change');
    assert.notEqual(row(2).css('display'), 'none');
    assert.equal(row(2).find('.manual-source').prop('disabled'), false);
    dom.window.close();
});

test('choosing a new line deselects the previous line and requires fresh verification', () => {
    const {dom, $, row} = editor();
    row(1).find('.manual-source').val('2').trigger('change');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#show_matching_stock_lines').prop('checked', true).trigger('change');
    row(2).find('.manual-line-enabled').prop('checked', true).trigger('change');
    assert.equal(row(1).find('.manual-line-enabled').prop('checked'), false);
    assert.equal(row(2).find('.manual-line-enabled').prop('checked'), true);
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    assert.match($('#manual_stock_correction_summary').text(), /1 selected/);
    dom.window.close();
});

test('error-row action opens the requested line with recorded defaults and details collapsed', () => {
    const {dom, $, row} = editor(1);
    assert.equal($('.manual-correction-row').length, 1);
    assert.equal(row(1).find('.manual-line-enabled').prop('checked'), true);
    assert.equal(row(1).next('.manual-evidence-row').css('display'), 'none');
    row(1).find('.manual-details-toggle').trigger('click');
    assert.notEqual(row(1).next('.manual-evidence-row').css('display'), 'none');
    assert.equal(row(1).find('.manual-source').prop('disabled'), false);
    assert.equal(row(1).find('.manual-source').val(), '2');
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    assert.match($('#manual_stock_correction_context').text(), /Line #1/);
    dom.window.close();
});

test('error-row action can focus an otherwise matching line without including other rows', () => {
    const {dom, $, row} = editor(2);
    assert.equal($('.manual-correction-row').length, 1);
    assert.equal(row(2).find('.manual-line-enabled').prop('checked'), true);
    assert.notEqual(row(2).css('display'), 'none');
    assert.equal(row(2).find('.manual-source').val(), '3');
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    dom.window.close();
});

test('verified delivery date uses the existing picker and calendar button', () => {
    const {dom, $, datePicker} = editor(1, true);
    assert.equal(datePicker.options.format, 'DD/MM/YYYY HH:mm');
    assert.equal(datePicker.options.ignoreReadonly, true);
    assert.equal(datePicker.options.useCurrent, false);
    assert.equal($('#choose_manual_stock_date').prop('disabled'), true);
    $('#manual_stock_date_verified').prop('checked', true).trigger('change');
    assert.equal($('#choose_manual_stock_date').prop('disabled'), false);
    assert.equal(datePicker.shows, 1);
    $('#choose_manual_stock_date').trigger('click');
    assert.equal(datePicker.shows, 2);
    $('#manual_stock_date_verified').prop('checked', false).trigger('change');
    assert.equal($('#choose_manual_stock_date').prop('disabled'), true);
    dom.window.close();
});

test('localized picker choice submits canonical date and changing it invalidates approval', () => {
    const {dom, $, datePicker, row, calls} = editor(1, true);
    row(1).find('.manual-source').val('2').trigger('change');
    $('#manual_stock_date_verified').prop('checked', true).trigger('change');
    datePicker.selected = {isValid: () => true, format: value => {
        assert.equal(value, 'YYYY-MM-DD HH:mm:ss');
        return '2026-10-13 15:30:00';
    }};
    $('#manual_stock_corrected_date').val('13/10/2026 15:30').trigger('dp.change');
    $('#manual_stock_correction_evidence').val('Physical serial and delivery date verified.');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    assert.equal(calls[1].options.data.transaction_date, '2026-10-13 15:30:00');
    assert.equal(calls[1].options.data.date_verified, 1);
    calls[1].deferred.resolve({plan: {transfer_id: 1, token: 'a'.repeat(64), changes: [], counter_repairs: [], date_change: null}});
    $('#confirm_manual_stock_correction').prop('checked', true).trigger('change');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), false);
    $('#manual_stock_corrected_date').trigger('dp.change');
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('picker shows the earliest valid date and blocks an earlier preview locally', () => {
    const {dom, $, datePicker, row, calls} = editor(1, true);
    row(1).find('.manual-source').val('2').trigger('change');
    assert.equal(datePicker.minimum, '2026-10-01 10:00:00');
    assert.match($('#manual_stock_date_bounds').text(), /Earliest permitted delivery: 2026-10-01 10:00:00/);
    $('#manual_stock_date_verified').prop('checked', true).trigger('change');
    datePicker.selected = {isValid: () => true, format: () => '2026-10-01 09:00:00'};
    $('#manual_stock_correction_evidence').val('Physical serial and delivery date verified.');
    $('#manual_stock_correction_verified').prop('checked', true).trigger('change');
    $('#preview_manual_stock_correction').trigger('click');
    assert.equal(calls.length, 1);
    assert.match($('#manual_stock_correction_error').text(), /on or after 2026-10-01 10:00:00/);
    assert.equal($('#apply_manual_stock_correction').prop('disabled'), true);
    dom.window.close();
});

test('choosing a later source moves the picker limit and clears an incompatible old date', () => {
    const {dom, $, datePicker, row} = editor(1, true);
    datePicker.selected = {isValid: () => true, format: () => '2026-10-02 12:00:00'};
    row(1).find('.manual-source option[value="2"]').attr('data-receipt-date', '2026-10-14 10:16:00');
    row(1).find('.manual-source').val('2').trigger('change');
    assert.equal(datePicker.minimum, '2026-10-14 10:16:00');
    assert.equal(datePicker.selected, null);
    assert.equal($('#manual_stock_correction_verified').prop('checked'), false);
    dom.window.close();
});
