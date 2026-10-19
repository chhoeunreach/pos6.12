const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const root = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jsdom'));
const jquery = require(path.join(root, 'storage/app/stock-ui-tests/node_modules/jquery'));

function form(batches, rows = 1) {
    const dom = new JSDOM('<form id="authority_correction_form"></form>', {runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    dom.window.$ = dom.window.jQuery = $;
    $.ajax = () => $.Deferred().promise();
    for (let index = 0; index < rows; index++) {
        const $row = $(`<div class="authority-line" data-quantity="1">
            <input type="hidden" name="corrections[${index}][sell_line_id]" value="${index + 1}">
            <select class="authority-source"><option value="">Source</option>
                <option value="1" data-serial="A">A</option><option value="2" data-serial="B">B</option></select>
            <select class="authority-destination"><option value="">Receiving</option></select>
            <p class="authority-selection-help"></p></div>`).appendTo('#authority_correction_form');
        for (const batch of batches) {
            $('<option>').val(batch.id).text(batch.serial).attr({
                'data-serial': batch.serial, 'data-quantity': batch.quantity ?? 1, 'data-used': batch.used ? '1' : '0'
            }).appendTo($row.find('.authority-destination'));
        }
    }
    const ready = [];
    $.fn.ready = function (callback) { ready.push(callback); return this; };
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_authority.js'), 'utf8'));
    ready.forEach(callback => callback.call(dom.window.document));
    return {dom, $, row: index => $('.authority-line').eq(index || 0)};
}

test('embedded full repair applies once and reports changed rows without navigating', () => {
    const {dom, $} = form([]);
    $('#authority_correction_form').remove();
    $('<div id="authority_correction_modal" data-stock-transfer-editor="1"><form class="authority-apply-form" action="/authority/1/apply"><input name="token" value="preview-token"><button type="submit">Apply</button></form></div>').appendTo('body');
    const deferred = $.Deferred(); let requests = 0, saved = null;
    $.ajax = options => { requests++; assert.match(options.data, /token=preview-token/); return deferred.promise(); };
    $(dom.window.document).on('stock-authority:applied', (event, result) => { saved = result; });
    $('.authority-apply-form').trigger('submit').trigger('submit');
    assert.equal(requests, 1);
    deferred.resolve({success: 1, msg: 'Saved', plan: {changes: [{sell_line_id: 77}]}});
    assert.equal(saved.plan.changes[0].sell_line_id, 77);
    assert.equal($('.authority-apply-form :submit').prop('disabled'), false);
    dom.window.close();
});

test('embedded full repair preserves its preview inputs when apply fails', () => {
    const {dom, $} = form([]);
    $('#authority_correction_form').remove();
    $('<div id="authority_correction_modal" data-stock-transfer-editor="1"><form class="authority-apply-form" action="/apply"><input name="token" value="retained-token"><button type="submit">Apply</button></form></div>').appendTo('body');
    $.ajax = () => $.Deferred().reject({status: 422, responseJSON: {msg: 'Records changed. Preview again.'}}).promise();
    $('.authority-apply-form').trigger('submit');
    assert.equal($('.authority-apply-form input[name="token"]').val(), 'retained-token');
    assert.match($('.authority-preview-error').text(), /Records changed/);
    assert.equal($('.authority-apply-form :submit').prop('disabled'), false);
    dom.window.close();
});

test('returning from an embedded preview restores selected records and custom notes', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr('action', '/preview');
    $form.wrap('<div id="authority_correction_modal" class="modal" data-stock-transfer-editor="1"><div class="modal-content"></div></div>');
    row().find('.authority-source').val('1').trigger('change');
    $form.append('<textarea id="authority_evidence" name="evidence">Verified original delivery record.</textarea>');
    $.ajax = () => $.Deferred().resolve({success: 1, html: '<form class="authority-apply-form"><a class="authority-return-to-records" href="/authority">Back to records</a></form>'}).promise();
    dom.window.authority_request_preview($form, false);
    assert.equal($('#authority_correction_form').length, 0);
    $('.authority-return-to-records').trigger('click');
    assert.equal($('#authority_correction_form').length, 1);
    assert.equal($('#authority_evidence').val(), 'Verified original delivery record.');
    assert.equal(row().find('.authority-source').val(), '1');
    assert.equal(row().find('.authority-destination').val(), '10');
    dom.window.close();
});

test('a disconnected Apply never claims that no correction was saved', () => {
    const {dom, $} = form([]);
    const $form = $('#authority_correction_form');
    dom.window.authority_preview_error($form, {status: 0}, true);
    assert.match($('.authority-preview-error').text(), /could not be confirmed/);
    assert.doesNotMatch($('.authority-preview-error').text(), /No correction was applied/);
    dom.window.close();
});

test('source selection suggests only one receiving batch with matching serial and quantity', () => {
    const {dom, row} = form([{id: 10, serial: 'A'}, {id: 11, serial: 'A', quantity: 2}]);
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.equal(row().find('option[value="11"]').prop('disabled'), true);
    dom.window.close();
});

test('ambiguous matching receipts require an explicit receiving-batch selection', () => {
    const {dom, row} = form([{id: 10, serial: 'A'}, {id: 11, serial: 'A'}]);
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '');
    assert.match(row().find('.authority-selection-help').text(), /Several receiving batches/);
    dom.window.close();
});

test('unique unused receiving batch is proposed with its serial change for review', () => {
    const {dom, row} = form([{id: 10, serial: 'B'}]);
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '10');
    row().find('.authority-destination').val('10').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.match(row().find('.authority-selection-help').text(), /receiving serial will change to A/);
    dom.window.close();
});

test('used batches keep their serial and remain available for deduction-only repair', () => {
    const {dom, row} = form([{id: 10, serial: 'A', used: true}, {id: 11, serial: 'B', used: true}]);
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.equal(row().find('option[value="11"]').prop('disabled'), true);
    row().find('.authority-source').val('2').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '11');
    assert.equal(row().find('option[value="10"]').prop('disabled'), true);
    dom.window.close();
});

test('explicit serial correction enables a used batch for a different verified source serial', () => {
    const {dom, row} = form([{id: 10, serial: 'B', used: true}]);
    row().append('<input type="checkbox" class="authority-change-receiving-serial" name="change_receiving_serial" value="1">');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination option[value="10"]').prop('disabled'), true);
    row().find('.authority-change-receiving-serial').prop('checked', true).trigger('change');
    assert.equal(row().find('.authority-destination option[value="10"]').prop('disabled'), false);
    row().find('.authority-destination').val('10').trigger('change');
    assert.match(row().find('.authority-selection-help').text(), /receiving serial will change to A/);
    row().find('.authority-change-receiving-serial').prop('checked', false).trigger('change');
    assert.equal(row().find('.authority-destination').val(), '');
    dom.window.close();
});

test('receiving batches already chosen on another line are not suggested again', () => {
    const {dom, row} = form([{id: 10, serial: 'A'}], 2);
    row(0).find('.authority-source').val('1').trigger('change');
    row(1).find('.authority-source').val('1').trigger('change');
    assert.equal(row(0).find('.authority-destination').val(), '10');
    assert.equal(row(1).find('.authority-destination').val(), '');
    assert.equal(row(1).find('option[value="10"]').prop('disabled'), true);
    dom.window.close();
});

test('clearing the source clears its previous matching receiving selection', () => {
    const {dom, row} = form([{id: 10, serial: 'A'}]);
    row().find('.authority-source').val('1').trigger('change').val('').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '');
    dom.window.close();
});

test('preview submission requires both selections and skips untouched lines', () => {
    const {dom, $, row} = form([{id: 10, serial: 'B'}], 2);
    row(0).find('.authority-source').val('1').trigger('change');
    let event = $.Event('submit');
    $('#authority_correction_form').triggerHandler(event);
    assert.equal(event.isDefaultPrevented(), true);
    row(0).find('.authority-destination').val('10').trigger('change');
    event = $.Event('submit');
    $('#authority_correction_form').triggerHandler(event);
    assert.equal(event.isDefaultPrevented(), true);
    assert.equal(row(0).find('input').prop('disabled'), false);
    assert.equal(row(1).find('input').prop('disabled'), false);
    dom.window.close();
});

test('opening a correction loads a modal and binds receipt selection on the loaded form', () => {
    const {dom, $} = form([{id: 10, serial: 'A'}]);
    const markup = $('#authority_correction_form').prop('outerHTML');
    $('#authority_correction_form').remove();
    $('body').append('<a class="open-authority-correction" href="/authority?transfer_id=7">Open correction form</a><div id="authority_correction_modal" class="modal"><div class="modal-content"></div></div>');
    let shown = false;
    $.fn.modal = function (action) { shown = action === 'show'; return this; };
    $.ajax = options => {
        assert.match(options.url, /transfer_id=7/);
        assert.equal(options.data.modal, 1);
        return $.Deferred().resolve('<div class="modal-body">' + markup + '</div>').promise();
    };
    // Re-run ready initialization now that the modal exists.
    const ready = [];
    $.fn.ready = function (callback) { ready.push(callback); return this; };
    $(dom.window.document).off('click', '.open-authority-correction');
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_authority.js'), 'utf8'));
    ready.forEach(callback => callback());
    $('.open-authority-correction').trigger('click');
    assert.equal(shown, true);
    assert.equal($('#authority_correction_modal #authority_correction_form').length, 1);
    $('.authority-source').val('1').trigger('change');
    assert.equal($('.authority-destination').val(), '10');
    dom.window.close();
});

test('new source fields are enabled only for a new receipt and its serial filters receiving matches', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}, {id: 11, serial: 'B', used: true}]);
    row().find('.authority-source').append('<option value="new">New source</option>');
    row().append('<div class="authority-new-source"><input class="authority-new-serial" value="A" required></div>');
    row().attr('data-missing-receiving', '1');
    row().find('.authority-destination').append('<option value="new">New receiving batch</option>');
    row().find('.authority-source').val('new').trigger('change');
    assert.equal(row().find('.authority-new-serial').prop('disabled'), false);
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.equal(row().find('option[value="11"]').prop('disabled'), true);
    row().find('.authority-destination').val('new').trigger('change');
    assert.match(row().find('.authority-selection-help').text(), /create the missing receiving batch for serial A/);
    row().find('.authority-source').val('1').trigger('change');
    $('#authority_correction_form').triggerHandler($.Event('submit'));
    assert.equal(row().find('.authority-new-serial').prop('disabled'), true);
    dom.window.close();
});

test('new receiving is disabled when the received quantity is already complete', () => {
    const {dom, row} = form([{id: 10, serial: 'A'}]);
    row().attr('data-missing-receiving', '0');
    row().find('.authority-destination').append('<option value="new">New receiving batch</option>');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination option[value="new"]').prop('disabled'), true);
    dom.window.close();
});

test('completion fills a missing receiving batch when the only existing batch belongs to another used serial', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A', used: true}], 2);
    for (let i = 0; i < 2; i++) {
        row(i).attr({'data-receiving-group': '5:8', 'data-missing-receiving': '1', 'data-line-id': String(i + 1),
            'data-completion': JSON.stringify({source_id: i + 1})});
        row(i).find('.authority-destination').append('<option value="new">New receiving batch</option>');
        row(i).append('<p class="authority-missing-receiving-help"></p>');
    }
    $('#authority_correction_form').append('<textarea id="authority_evidence"></textarea>');
    assert.equal(dom.window.authority_complete_fields($('#authority_correction_form')), true);
    assert.equal(row(0).find('.authority-destination').val(), '10');
    assert.equal(row(1).find('.authority-destination').val(), 'new');
    assert.equal(row(0).find('.authority-missing-receiving-help').css('display'), 'none');
    assert.match(row(0).find('.authority-selection-help').text(), /Preview will validate source stock and dependent movements/);
    assert.match($('#authority_evidence').val(), /Line #2: source receipt 2, receiving batch new/);
    const event = $.Event('submit');
    $('#authority_correction_form').triggerHandler(event);
    assert.equal(event.isDefaultPrevented(), true);
    dom.window.close();
});

test('completion never creates more receiving batches than the shared product quantity gap', () => {
    const {dom, $, row} = form([], 2);
    for (let i = 0; i < 2; i++) {
        row(i).attr({'data-receiving-group': '5:8', 'data-missing-receiving': '1',
            'data-completion': JSON.stringify({source_id: i + 1})});
        row(i).find('.authority-destination').append('<option value="new">New receiving batch</option>');
    }
    assert.equal(dom.window.authority_complete_fields($('#authority_correction_form')), false);
    assert.equal(row(0).find('.authority-destination').val(), 'new');
    assert.equal(row(1).find('.authority-destination').val(), '');
    assert.equal(row(1).find('.authority-destination option[value="new"]').prop('disabled'), true);
    dom.window.close();
});

test('changing the source selects a supported missing batch when existing used serials cannot be changed', () => {
    const {dom, row} = form([{id: 10, serial: 'A', used: true}]);
    row().attr('data-missing-receiving', '1');
    row().find('.authority-destination').append('<option value="new">New receiving batch</option>');
    row().find('.authority-source').val('2').trigger('change');
    assert.equal(row().find('.authority-destination').val(), 'new');
    assert.equal(row().find('.authority-destination option[value="10"]').prop('disabled'), true);
    assert.match(row().find('.authority-selection-help').text(), /create the missing receiving batch for serial B/);
    dom.window.close();
});

test('separate lines can request separate new receiving batches', () => {
    const {dom, row} = form([], 2);
    for (let i = 0; i < 2; i++) {
        row(i).attr('data-missing-receiving', '2');
        row(i).find('.authority-destination').append('<option value="new">New receiving batch</option>');
        row(i).find('.authority-source').val('1').trigger('change');
        row(i).find('.authority-destination').val('new').trigger('change');
    }
    assert.equal(row(0).find('.authority-destination').val(), 'new');
    assert.equal(row(1).find('.authority-destination').val(), 'new');
    dom.window.close();
});

test('blank serial receipts are selected by ID without matching unrelated blank batches automatically', () => {
    const {dom, row} = form([{id: 10, serial: ''}, {id: 11, serial: ''}, {id: 12, serial: 'B'}]);
    row().find('.authority-source option[value="1"]').attr('data-serial', '');
    row().append('<div class="authority-existing-serial"><input class="authority-verified-serial"></div>');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '');
    assert.equal(row().find('.authority-verified-serial').prop('disabled'), false);
    assert.equal(row().find('option[value="12"]').prop('disabled'), true);
    row().find('.authority-destination').val('10').trigger('change');
    assert.match(row().find('.authority-selection-help').text(), /deduction will use the selected receipt ID/);
    dom.window.close();
});

test('entering an existing receipt missing serial enables its matching receiving batch', () => {
    const {dom, row} = form([{id: 10, serial: 'A'}, {id: 11, serial: '', used: true}]);
    row().find('.authority-source option[value="1"]').attr('data-serial', '');
    row().append('<div class="authority-existing-serial"><input class="authority-verified-serial"></div>');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('option[value="10"]').prop('disabled'), true);
    row().find('.authority-verified-serial').val('A').trigger('input');
    assert.equal(row().find('option[value="10"]').prop('disabled'), false);
    assert.equal(row().find('option[value="11"]').prop('disabled'), false);
    row().find('.authority-source').val('2').trigger('change');
    assert.equal(row().find('.authority-verified-serial').prop('disabled'), true);
    assert.equal(row().find('.authority-verified-serial').val(), '');
    dom.window.close();
});

test('later source receipt explains the date conflict without silently changing delivery', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    $('#authority_correction_form').attr('data-recorded-date', '2026-09-01 12:00:00').append('<input id="authority_date"><div class="authority-date-guidance"></div><button type="button" class="authority-use-earliest-date"></button>');
    row().find('.authority-source option[value="1"]').attr('data-receipt-date', '2026-09-02 15:23:00');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal($('#authority_date').val(), '');
    assert.match($('.authority-date-guidance').text(), /Source receipt #1 arrived 2026-09-02 15:23:00/);
    let event = $.Event('submit');
    $('#authority_correction_form').triggerHandler(event);
    assert.equal(event.isDefaultPrevented(), true);
    $('.authority-use-earliest-date').trigger('click');
    assert.equal($('#authority_date').val(), '2026-09-02 15:23:00');
    event = $.Event('submit');
    $('#authority_correction_form').triggerHandler(event);
    assert.equal(event.isDefaultPrevented(), true);
    dom.window.close();
});

test('delivery date guidance includes unchanged lines and newly entered receipt dates', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}], 2);
    $('#authority_correction_form').attr('data-recorded-date', '2026-09-01 12:00:00').append('<input id="authority_date"><div class="authority-date-guidance"></div><button type="button" class="authority-use-earliest-date"></button>');
    row(0).find('.authority-source').append('<option value="new">New receipt</option>');
    row(0).append('<div class="authority-new-source"><input class="authority-new-source-date" value="2026-09-02 12:00:00"></div>');
    row(1).attr('data-recorded-source-date', '2026-09-03 12:00:00');
    row(0).find('.authority-source').val('new').trigger('change');
    assert.match($('.authority-date-guidance').text(), /unchanged source receipt.*2026-09-03/);
    assert.equal($('.authority-use-earliest-date').attr('data-date'), '2026-09-03 12:00:00');
    dom.window.close();
});

test('auto-complete keeps delivery dates unchanged and requires review of a later receipt', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr({'data-recorded-date': '2026-09-01 12:00:00'});
    $form.append('<textarea id="authority_evidence"></textarea><input type="checkbox" id="authority_change_date"><input id="authority_date"><div class="authority-date-guidance"></div><button type="button" class="authority-auto-complete"></button>');
    row().attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    row().find('.authority-source option[value="1"]').attr('data-receipt-date', '2026-09-02 15:23:00');
    $.ajax = () => { throw new Error('Unverified date must not be submitted'); };
    $('.authority-auto-complete').trigger('click');
    assert.equal($('#authority_date').val(), '');
    assert.equal($('#authority_change_date').prop('checked'), false);
    assert.match($('.authority-date-guidance').text(), /Source receipt #1/);
    dom.window.close();
});

test('auto-complete respects manual choices and notes and never verifies unresolved lines', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}, {id: 11, serial: 'B'}], 2);
    $('#authority_correction_form').append('<textarea id="authority_evidence">Verified document DOC-77, keep my notes.</textarea><button type="button" class="authority-auto-complete"></button><p class="authority-auto-status"></p>');
    row(0).attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    row(0).find('.authority-source').val('2').trigger('change');
    $.ajax = () => { throw new Error('Unresolved correction must not be sent.'); };
    $('.authority-auto-complete').trigger('click');
    assert.equal(row(0).find('.authority-source').val(), '2');
    assert.equal(row(0).find('.authority-destination').val(), '11');
    assert.equal($('#authority_evidence').val(), 'Verified document DOC-77, keep my notes.');
    assert.match($('.authority-auto-status').text(), /1 line\(s\) still need/);
    dom.window.close();
});

test('auto-complete reports server blockers inside the form', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    $('#authority_correction_form').append('<button type="button" class="authority-auto-complete"></button><p class="authority-auto-status"></p>');
    row().attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    $.ajax = () => $.Deferred().reject({responseJSON: {msg: 'Receiving batch has dependent movements.'}}).promise();
    $('.authority-auto-complete').trigger('click');
    assert.equal($('.authority-auto-status').text(), 'Receiving batch has dependent movements.');
    assert.equal($('.authority-auto-complete').prop('disabled'), false);
    dom.window.close();
});

test('opening a populated modal auto-fills fields and verification replaces it with the apply preview', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').off().attr({action: '/authority/1/preview', 'data-recorded-date': '2026-09-01 12:00:00'});
    $form.wrap('<div class="modal"><div class="modal-content"></div></div>');
    $form.append('<textarea id="authority_evidence"></textarea><input type="checkbox" id="authority_change_date"><input id="authority_date"><input id="authority_date_basis"><div class="authority-date-guidance"></div><button type="button" class="authority-use-earliest-date"></button><button type="button" class="authority-auto-complete"></button><p class="authority-auto-status"></p>');
    row().attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    row().find('.authority-source option[value="1"]').attr('data-receipt-date', '2026-08-31 12:00:00');
    dom.window.authority_initialize_form();
    assert.equal(row().find('.authority-source').val(), '1');
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.match($('#authority_evidence').val(), /Authority record-based correction/);
    let requests = 0;
    $.ajax = options => {
        requests++;
        assert.equal(options.url, '/authority/1/preview');
        return $.Deferred().resolve({success: 1, html: '<form action="/authority/1/apply"><button>Apply authority correction</button></form>'}).promise();
    };
    $('.authority-auto-complete').trigger('click');
    assert.equal($('.modal-content form[action="/authority/1/apply"]').length, 1);
    assert.equal($('.modal-content #authority_correction_form').length, 0);
    assert.equal(requests, 1);
    dom.window.close();
});

test('Authority cards load category details, pagination and reference search in one modal', () => {
    const {dom, $} = form([]);
    $('body').append('<a class="authority-detail-link" href="/authority/details?category=missing_selected">Missing Selected Lots</a><div class="modal" id="authority_details_modal"><div class="modal-body"></div></div>');
    const ready = [];
    $.fn.ready = function (callback) { ready.push(callback); return this; };
    $.fn.modal = function () { return this; };
    $(dom.window.document).off('click', '.authority-detail-link, #authority_details_modal .pagination a');
    dom.window.eval(fs.readFileSync(path.join(root, 'public/js/stock_authority.js'), 'utf8'));
    ready.forEach(callback => callback());
    const requests = [];
    $.ajax = options => {
        requests.push(options);
        return $.Deferred().resolve('<h3>Missing Selected Lots</h3><div class="pagination"><a href="/authority/details?category=missing_selected&page=2">2</a></div><form class="authority-details-filter" action="/authority/details"><input name="category" value="missing_selected"><input name="reference" value="ST-77"></form>').promise();
    };
    $('.authority-detail-link').trigger('click');
    assert.match($('#authority_details_modal').text(), /Missing Selected Lots/);
    assert.match(requests[0].url, /category=missing_selected/);
    $('#authority_details_modal .pagination a').trigger('click');
    assert.match(requests[1].url, /page=2/);
    $('#authority_details_modal form').trigger('submit');
    assert.match(requests[2].data, /category=missing_selected/);
    assert.match(requests[2].data, /reference=ST-77/);
    assert.equal(requests.length, 3);
    dom.window.close();
});

test('preview errors preserve editable rows, omit untouched rows and allow retry', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}], 2);
    const $form = $('#authority_correction_form').attr('action', '/authority/1/preview');
    $form.append('<textarea name="evidence">Verified document</textarea><button type="submit">Preview</button>');
    row(0).find('.authority-source').val('1').trigger('change');
    let requests = 0;
    $.ajax = options => {
        requests++;
        assert.equal(options.headers.Accept, 'application/json');
        assert.match(options.data, /corrections%5B0%5D/);
        assert.doesNotMatch(options.data, /corrections%5B1%5D/);
        return $.Deferred().reject({status: 500, responseJSON: {message: 'SQL SECRET', error_reference: 'incident-123'}}).promise();
    };
    $form.triggerHandler('submit');
    assert.match($('.authority-preview-error').text(), /incident-123/);
    assert.doesNotMatch($('.authority-preview-error').text(), /SQL SECRET/);
    assert.equal(row(0).find('.authority-source').val(), '1');
    assert.equal(row(1).find('input').prop('disabled'), false);
    assert.equal($form.find(':submit').prop('disabled'), false);
    $form.triggerHandler('submit');
    assert.equal(requests, 2);
    dom.window.close();
});

test('validation and receipt date errors show corrective guidance as text', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    row().find('.authority-source').val('1').trigger('change');
    $.ajax = () => $.Deferred().reject({status: 422, responseJSON: {
        msg: '<b>Receipt date conflict</b>', stock_error: {kind: 'receipt_date_conflict'}
    }}).promise();
    $('#authority_correction_form').triggerHandler('submit');
    assert.match($('.authority-preview-error').text(), /actual verified delivery date/);
    assert.equal($('.authority-preview-error b').length, 0);
    $.ajax = () => $.Deferred().reject({status: 422, responseJSON: {errors: {evidence: ['Provide supporting records.']}}}).promise();
    $('#authority_correction_form').triggerHandler('submit');
    assert.match($('.authority-preview-error').text(), /Provide supporting records/);
    dom.window.close();
});

test('successful standalone preview renders review without applying a correction', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    row().find('.authority-source').val('1').trigger('change');
    $.ajax = () => $.Deferred().resolve({success: 1, html: '<div id="review">Review changes before applying</div>'}).promise();
    $('#authority_correction_form').triggerHandler('submit');
    assert.equal($('#authority_correction_form').length, 0);
    assert.match($('#review').text(), /Review changes/);
    dom.window.close();
});

test('generated evidence supports long transfers and respects the server limit with CRLF newlines', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    $('#authority_correction_form').append('<textarea id="authority_evidence" maxlength="20000"></textarea>');
    for (let i = 1; i <= 80; i++) {
        const $copy = row().clone().attr({'data-line-id': String(i), 'data-completion': JSON.stringify({source_id: 1})});
        $copy.find('.authority-destination option[value="10"]').val(String(100 + i));
        $copy.appendTo('#authority_correction_form');
    }
    dom.window.authority_complete_fields($('#authority_correction_form'));
    const evidence = $('#authority_evidence').val();
    assert.ok(evidence.length > 2000);
    assert.match(evidence, /Line #80:/);
    assert.ok(evidence.replace(/\r?\n/g, '\r\n').length <= 20000);
    $('#authority_evidence').attr('maxlength', '2000');
    // Generated notes are refreshed, while manually entered evidence is preserved.
    $('#authority_evidence').attr('data-generated-notes', evidence);
    dom.window.authority_complete_fields($('#authority_correction_form'));
    const bounded = $('#authority_evidence').val().replace(/\r?\n/g, '\r\n');
    assert.ok(bounded.length <= 2000);
    assert.match(bounded, /Additional row details/);
    dom.window.close();
});

test('opening authority correction checks server errors and keeps the form editable', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    row().attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    const $form = $('#authority_correction_form').off().attr('data-check-on-open', '1');
    let checks = 0;
    $.ajax = () => {
        checks++;
        return $.Deferred().reject({status: 422, responseJSON: {msg: 'Source quantity is insufficient.'}}).promise();
    };
    dom.window.authority_initialize_form();
    assert.equal(checks, 1);
    assert.match($('.authority-preview-error').text(), /Source quantity is insufficient/);
    assert.equal(row().find('.authority-source').prop('disabled'), false);
    assert.equal($('#authority_correction_form').length, 1);
    dom.window.close();
});

test('opening authority correction highlights missing row selections immediately', () => {
    const {dom, $, row} = form([]);
    row().attr('data-line-id', '77');
    $('#authority_correction_form').off().attr('data-check-on-open', '1');
    $.ajax = () => { throw new Error('Incomplete records must not be previewed'); };
    dom.window.authority_initialize_form();
    assert.equal(row().hasClass('has-error'), true);
    assert.match($('.authority-preview-error').text(), /Line #77: Choose the verified source receipt/);
    dom.window.close();
});

test('successful opening check keeps selections and does not open the apply preview', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    row().attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    $('#authority_correction_form').off().attr('data-check-on-open', '1').append('<p class="authority-auto-status"></p>');
    $.ajax = () => $.Deferred().resolve({success: 1, html: '<button id="apply">Apply</button>'}).promise();
    dom.window.authority_initialize_form();
    assert.equal($('#authority_correction_form').length, 1);
    assert.equal($('#apply').length, 0);
    assert.match($('.authority-auto-status').text(), /Initial check passed/);
    dom.window.close();
});

test('authority date bypass allows preview while preserving recorded delivery', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr('data-recorded-date', '2026-10-14 14:56:00');
    $form.append('<input type="checkbox" id="authority_change_date"><input id="authority_date"><input type="checkbox" id="authority_receipt_date_override" name="allow_receipt_date_override" value="1" checked><div class="authority-date-guidance"></div>');
    row().find('.authority-source option[value="1"]').attr('data-receipt-date', '2026-10-14 16:27:00');
    row().find('.authority-source').val('1').trigger('change');
    let requests = 0;
    $.ajax = options => {
        requests++;
        assert.match(options.data, /allow_receipt_date_override=1/);
        return $.Deferred().promise();
    };
    $form.triggerHandler('submit');
    assert.equal(requests, 1);
    assert.equal($('#authority_date').val(), '');
    assert.match($('.authority-date-guidance').text(), /bypass enabled/);
    assert.equal($('.authority-date-guidance').hasClass('text-danger'), false);
    dom.window.close();
});

test('disabling authority date bypass restores the receipt chronology check', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr('data-recorded-date', '2026-10-14 14:56:00');
    $form.append('<input type="checkbox" id="authority_change_date"><input id="authority_date"><input type="checkbox" id="authority_receipt_date_override" checked><div class="authority-date-guidance"></div>');
    row().find('.authority-source option[value="1"]').attr('data-receipt-date', '2026-10-14 16:27:00');
    row().find('.authority-source').val('1').trigger('change');
    $('#authority_receipt_date_override').prop('checked', false).trigger('change');
    $.ajax = () => { throw new Error('Chronology conflict must block without override'); };
    $form.triggerHandler('submit');
    assert.equal($('.authority-date-guidance').hasClass('text-danger'), true);
    dom.window.close();
});

test('authority receiving serial correction works for every row with the enabled default', () => {
    const {dom, $, row} = form([{id: 10, serial: 'B', used: true}, {id: 11, serial: 'B', used: true}], 2);
    for (let i = 0; i < 2; i++) {
        row(i).append(`<input type="hidden" name="corrections[${i}][change_receiving_serial]" value="0"><input type="checkbox" class="authority-change-receiving-serial" name="corrections[${i}][change_receiving_serial]" value="1" checked>`);
        row(i).find('.authority-source').val('1').trigger('change');
        assert.equal(row(i).find('.authority-destination option[value="' + (10 + i) + '"]').prop('disabled'), false);
        row(i).find('.authority-destination').val(String(10 + i)).trigger('change');
        assert.match(row(i).find('.authority-selection-help').text(), /receiving serial will change to A/);
    }
    $.ajax = options => {
        const params = new URLSearchParams(options.data);
        assert.deepEqual(params.getAll('corrections[0][change_receiving_serial]'), ['0', '1']);
        assert.deepEqual(params.getAll('corrections[1][change_receiving_serial]'), ['0', '1']);
        return $.Deferred().promise();
    };
    $('#authority_correction_form').triggerHandler('submit');
    dom.window.close();
});

test('receiving dependency errors highlight the affected row and explain the linked-record remedy', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    row().attr('data-line-id', '77').find('.authority-source').val('1').trigger('change');
    $.ajax = () => $.Deferred().reject({status: 422, responseJSON: {msg: 'Line #77: receiving batch #10 supplies a connected transfer.',
        stock_error: {kind: 'receiving_batch_dependencies', sell_line_id: 77}}}).promise();
    $('#authority_correction_form').triggerHandler('submit');
    assert.equal(row().hasClass('has-error'), true);
    assert.match(row().find('.authority-selection-help').text(), /Line #77/);
    assert.match($('.authority-preview-error').text(), /reconcile those linked records together/);
    dom.window.close();
});

test('changing source retains the unique blank receiving batch proposed for serial fill', () => {
    const {dom, $, row} = form([{id: 10, serial: '', used: true}]);
    row().append('<input type="checkbox" class="authority-change-receiving-serial" checked>');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '10');
    row().find('.authority-source').val('2').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.match(row().find('.authority-selection-help').text(), /receiving serial will change to B/);
    dom.window.close();
});

test('automatic receiving proposals reserve exact serial batches for other rows', () => {
    const {dom, $, row} = form([{id: 10, serial: 'B', used: true}], 2);
    for (let i = 0; i < 2; i++) {
        row(i).attr({'data-receiving-group': '5:8', 'data-missing-receiving': '1',
            'data-completion': JSON.stringify({source_id: i + 1, destination_id: 10})});
        row(i).append('<input type="checkbox" class="authority-change-receiving-serial" checked>');
        row(i).find('.authority-destination').append('<option value="new">New receiving</option>');
    }
    assert.equal(dom.window.authority_complete_fields($('#authority_correction_form')), true);
    assert.equal(row(0).find('.authority-destination').val(), 'new');
    assert.equal(row(1).find('.authority-destination').val(), '10');
    dom.window.close();
});

test('multiple eligible receiving batches require a choice and edits clear stale errors', () => {
    const {dom, $, row} = form([{id: 10, serial: '', used: true}, {id: 11, serial: '', used: true}]);
    row().attr('data-line-id', '77').append('<input type="checkbox" class="authority-change-receiving-serial" checked>');
    $('#authority_correction_form').append('<p class="authority-auto-status"></p>');
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '');
    dom.window.authority_check_on_open($('#authority_correction_form'));
    assert.match($('.authority-preview-error').text(), /Several receiving batches have this SKU and quantity/);
    row().find('.authority-destination').val('11').trigger('change');
    assert.equal($('.authority-preview-error').css('display'), 'none');
    assert.equal(row().hasClass('has-error'), false);
    assert.match($('.authority-auto-status').text(), /Selections changed/);
    dom.window.close();
});

test('Quick fix fills supported row selections and checks without applying stock changes', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr('action', '/authority/1/preview');
    $form.append('<textarea id="authority_evidence" name="evidence">Keep my verified delivery notes.</textarea><p class="authority-auto-status"></p>');
    row().attr({'data-line-id': '76626', 'data-completion': JSON.stringify({source_id: 1, destination_id: 10})});
    let requests = 0;
    $.ajax = options => {
        requests++;
        assert.equal(options.url, '/authority/1/preview');
        assert.equal(options.type, 'POST');
        return $.Deferred().resolve({success: 1, html: '<button id="apply-stock">Apply</button>'}).promise();
    };
    row().find('.authority-quick-fix').trigger('click');
    assert.equal(row().find('.authority-source').val(), '1');
    assert.equal(row().find('.authority-destination').val(), '10');
    assert.equal(requests, 1);
    assert.equal($('#authority_correction_form').length, 1);
    assert.equal($('#apply-stock').length, 0);
    assert.equal($('#authority_evidence').val(), 'Keep my verified delivery notes.');
    assert.match($('.authority-auto-status').text(), /Initial check passed/);
    dom.window.close();
});

test('Quick fix replaces a conflicting receiving choice with the unique server-verified alternative', () => {
    const {dom, $, row} = form([{id: 10, serial: 'B'}, {id: 11, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr('action', '/authority/1/preview');
    row().attr('data-line-id', '77').find('.authority-source').val('1').trigger('change');
    row().find('.authority-destination').val('10').trigger('change');
    let requests = 0;
    $.ajax = () => { requests++; return $.Deferred().resolve({success: 1, html: 'Review correction'}).promise(); };
    dom.window.authority_preview_error($form, {status: 422, responseJSON: {
        msg: 'Serial B: sent 1; received 0.', stock_error: {kind: 'receiving_balance_conflict', sell_line_id: 77,
            receiving_batch_id: 10, candidate_receiving_batch_ids: [11]}
    }});
    $('.authority-preview-error .authority-quick-fix').trigger('click');
    assert.equal(row().find('.authority-destination').val(), '11');
    assert.equal(requests, 1);
    assert.equal($('#authority_correction_form').length, 1);
    dom.window.close();
});

test('Quick fix clears an unsafe receiving choice without guessing when alternatives are ambiguous', () => {
    const {dom, $, row} = form([{id: 10, serial: 'B'}, {id: 11, serial: 'A'}, {id: 12, serial: 'A'}]);
    const $form = $('#authority_correction_form');
    row().attr('data-line-id', '77').find('.authority-source').val('1').trigger('change');
    row().find('.authority-destination').val('10').trigger('change');
    $.ajax = () => { throw new Error('Ambiguous receiving choice must not be sent'); };
    dom.window.authority_preview_error($form, {status: 422, responseJSON: {
        msg: 'The selected batch would take another row\'s stock.', stock_error: {kind: 'receiving_balance_conflict',
            sell_line_id: 77, receiving_batch_id: 10, candidate_receiving_batch_ids: [11, 12]}
    }});
    $('.authority-preview-error .authority-quick-fix').trigger('click');
    assert.equal(row().find('.authority-destination').val(), '');
    assert.match(row().find('.authority-selection-help').text(), /Several receiving batches/);
    assert.equal(dom.window.document.activeElement, row().find('.authority-destination')[0]);
    dom.window.close();
});

test('Quick fix never claims a verified alternative already selected by another row', () => {
    const {dom, $, row} = form([{id: 10, serial: 'B'}, {id: 11, serial: 'A'}], 2);
    const $form = $('#authority_correction_form');
    row(0).attr('data-line-id', '77').find('.authority-source').val('1').trigger('change');
    row(1).find('.authority-source').val('1').trigger('change');
    row(1).find('.authority-destination').val('11').trigger('change');
    row(0).find('.authority-destination').val('10').trigger('change');
    $.ajax = () => { throw new Error('Occupied batch must not be selected'); };
    dom.window.authority_preview_error($form, {status: 422, responseJSON: {
        msg: 'Receiving balance conflict.', stock_error: {kind: 'receiving_balance_conflict', sell_line_id: 77,
            receiving_batch_id: 10, candidate_receiving_batch_ids: [11]}
    }});
    $('.authority-preview-error .authority-quick-fix').trigger('click');
    assert.equal(row(0).find('.authority-destination').val(), '');
    assert.equal(row(1).find('.authority-destination').val(), '11');
    assert.match(row(0).find('.authority-selection-help').text(), /review the source receipt/);
    dom.window.close();
});

test('Quick fix on an ambiguous receiving error focuses the selector without guessing', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}, {id: 11, serial: 'A'}]);
    row().attr('data-line-id', '76626').find('.authority-source').val('1').trigger('change');
    $.ajax = () => { throw new Error('Ambiguous selection must not be sent'); };
    dom.window.authority_check_on_open($('#authority_correction_form'));
    assert.equal($('.authority-preview-error .authority-quick-fix').length, 1);
    $('.authority-preview-error .authority-quick-fix').trigger('click');
    assert.equal(row().find('.authority-destination').val(), '');
    assert.equal(dom.window.document.activeElement, row().find('.authority-destination')[0]);
    assert.match($('.authority-preview-error').text(), /Several receiving batches/);
    dom.window.close();
});

test('Quick fix selects a missing receiving batch only within the recorded quantity gap', () => {
    const {dom, $, row} = form([]);
    const $form = $('#authority_correction_form').attr('action', '/authority/1/preview');
    row().attr('data-missing-receiving', '1').find('.authority-destination').append('<option value="new">Add missing receiving</option>');
    row().find('.authority-source').val('1').trigger('change');
    row().find('.authority-destination').val('').trigger('change');
    let requests = 0;
    $.ajax = options => {
        requests++;
        assert.equal(options.url, '/authority/1/preview');
        return $.Deferred().resolve({success: 1, html: 'Review missing batch'}).promise();
    };
    row().find('.authority-quick-fix').trigger('click');
    assert.equal(row().find('.authority-destination').val(), 'new');
    assert.equal(requests, 1);
    assert.equal($('#authority_correction_form').length, 1);
    dom.window.close();
});

test('Quick fix exposes missing source receipt fields and requires actual date and cost', () => {
    const {dom, $, row} = form([]);
    row().attr('data-missing-receiving', '1');
    row().find('.authority-source option[value="1"], .authority-source option[value="2"]').remove();
    row().find('.authority-source').append('<option value="new">Add missing source</option>');
    row().find('.authority-destination').append('<option value="new">Add missing receiving</option>');
    row().append('<div class="authority-new-source"><input id="actual-source-date" class="authority-new-source-date" required><input id="actual-source-cost" type="number" required></div>');
    $.ajax = () => { throw new Error('Missing actual receipt fields must not be submitted'); };
    row().find('.authority-quick-fix').trigger('click');
    assert.equal(row().find('.authority-source').val(), 'new');
    assert.equal($('#actual-source-date').val(), '');
    assert.equal($('#actual-source-cost').val(), '');
    assert.equal(dom.window.document.activeElement, $('#actual-source-date')[0]);
    assert.match($('.authority-preview-error').text(), /required receipt details/);
    dom.window.close();
});

test('Quick fix next row acts on one unresolved row and leaves unrelated choices alone', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}], 2);
    $('#authority_correction_form').append('<p class="authority-auto-status"></p>');
    row(0).attr('data-completion', JSON.stringify({source_id: 1, destination_id: 10}));
    dom.window.authority_update_quick_fix_buttons($('#authority_correction_form'));
    $.ajax = () => { throw new Error('Another unresolved row remains'); };
    $('.authority-quick-fix-next').trigger('click');
    assert.equal(row(0).find('.authority-source').val(), '1');
    assert.equal(row(0).find('.authority-destination').val(), '10');
    assert.equal(row(1).find('.authority-source').val(), '');
    $('.authority-quick-fix-next').trigger('click');
    assert.equal(dom.window.document.activeElement, row(1).find('.authority-source')[0]);
    dom.window.close();
});

test('Quick fix retries a server error as verification while keeping the form open', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form');
    row().find('.authority-source').val('1').trigger('change');
    dom.window.authority_preview_error($form, {status: 500, responseJSON: {error_reference: 'incident-1'}});
    let checks = 0;
    $.ajax = () => {
        checks++;
        return $.Deferred().resolve({success: 1, html: '<button id="apply-stock">Apply</button>'}).promise();
    };
    $('.authority-preview-error .authority-quick-fix').trigger('click');
    assert.equal(checks, 1);
    assert.equal($('#authority_correction_form').length, 1);
    assert.equal($('#apply-stock').length, 0);
    dom.window.close();
});

test('receiving dependency errors identify the row from the batch ID for Quick fix', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    row().attr('data-line-id', '76626').find('.authority-source').val('1').trigger('change');
    dom.window.authority_preview_error($('#authority_correction_form'), {status: 422, responseJSON: {
        msg: 'The connected receiving lot needs repair.', stock_error: {kind: 'receiving_batch_dependencies', receiving_batch_id: 10}
    }});
    assert.equal(row().hasClass('has-error'), true);
    assert.equal($('.authority-preview-error .authority-quick-fix').attr('data-line-id'), '76626');
    assert.equal($('#authority_correction_form').data('authority-stock-error').sell_line_id, '76626');
    dom.window.close();
});

test('Quick fix offers explicit linked receiving choices and rechecks the selected pairing without applying', () => {
    const {dom, $, row} = form([{id: 10, serial: 'A'}]);
    const $form = $('#authority_correction_form').attr('action', '/authority/1/preview');
    row().attr('data-line-id', '76626').find('.authority-source').val('1').trigger('change');
    const error = {kind: 'receiving_pairing_required', receiving_batch_id: 10, linked_sell_line_id: 79605,
        linked_reference: 'ST2026/6576', source_serial: 'A', candidates: [{id: 92565, serial: null, quantity: 1}, {id: 92577, serial: null, quantity: 1}]};
    let requests = 0;
    $.ajax = options => {
        requests++;
        assert.equal(new URLSearchParams(options.data).get('linked_receiving_pairs[79605]'), '92565');
        return $.Deferred().resolve({success: 1, html: '<button id="apply-stock">Apply</button>'}).promise();
    };
    dom.window.authority_preview_error($form, {status: 422, responseJSON: {msg: 'Confirm a linked receiving lot.', stock_error: error}});
    assert.equal($('.authority-linked-pair-choice').length, 2);
    assert.equal(requests, 0);
    assert.match($('.authority-linked-pair-options').text(), /ST2026\/6576/);
    $('.authority-preview-error .authority-quick-fix').trigger('click');
    assert.equal($('.authority-linked-pair-choice').length, 2);
    $('.authority-linked-pair-choice[data-batch-id="92565"]').trigger('click');
    assert.equal(requests, 1);
    assert.equal($('#authority_correction_form').length, 1);
    assert.equal($('#apply-stock').length, 0);
    assert.equal($form.find('input[name="linked_receiving_pairs[79605]"]').val(), '92565');
    row().find('.authority-source').val('2').trigger('change');
    assert.equal($form.find('input[name="linked_receiving_pairs[79605]"]').length, 0);
    dom.window.close();
});

test('Quick fix splits an existing combined receiving quantity across all matching serial rows', () => {
    const {dom, $, row} = form([{id: 10, serial: '', quantity: 2}], 2);
    const $form = $('#authority_correction_form').attr('action', '/authority/1/preview');
    for (let i = 0; i < 2; i++) {
        row(i).attr('data-receiving-group', '5:8');
        row(i).find('.authority-destination').attr('name', `corrections[${i}][destination_id]`)
            .append('<option value="split:10" data-split="1" data-quantity="2">Split combined batch</option>');
        row(i).find('.authority-source').val(String(i + 1)).trigger('change');
    }
    let checks = 0;
    $.ajax = options => {
        checks++;
        const params = new URLSearchParams(options.data);
        assert.equal(params.get('corrections[0][destination_id]'), 'split:10');
        assert.equal(params.get('corrections[1][destination_id]'), 'split:10');
        return $.Deferred().resolve({success: 1, html: 'Review existing receiving split'}).promise();
    };
    row(0).find('.authority-quick-fix').trigger('click');
    assert.equal(checks, 1);
    assert.equal(row(0).find('.authority-destination').val(), 'split:10');
    assert.equal(row(1).find('.authority-destination').val(), 'split:10');
    assert.match(row(1).find('.authority-selection-help').text(), /Total received stock will stay unchanged/);
    assert.equal($('#authority_correction_form').length, 1);
    dom.window.close();
});
