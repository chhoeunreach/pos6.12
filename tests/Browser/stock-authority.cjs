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

test('different unused receiving serial can be explicitly selected for correction', () => {
    const {dom, row} = form([{id: 10, serial: 'B'}]);
    row().find('.authority-source').val('1').trigger('change');
    assert.equal(row().find('.authority-destination').val(), '');
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
