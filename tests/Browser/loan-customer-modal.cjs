const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../../storage/app/stock-ui-tests/node_modules/jsdom');
const jquery = require('../../storage/app/stock-ui-tests/node_modules/jquery');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/loan-customer-modal.js'), 'utf8');
const detail = '<div data-customer-modal-view="detail"><a class="js-customer-modal" data-title="Edit Customer" href="/customers/1/edit">Edit</a><p>Customer detail</p></div>';
const edit = `<div data-customer-modal-view="edit"><form id="loanCustomerModalForm" action="/customers/1" method="POST" enctype="multipart/form-data"><input name="_token" value="csrf"><input name="_method" value="PUT"><input name="expected_customer_id" value="1"><div class="lcm-errors" hidden></div><ul class="nav-tabs"><li class="active"><a href="#basic">Basic</a></li><li><a href="#contact">Contact</a></li></ul><div class="tab-pane active" id="basic"><input name="name" value="Original"><input type="file" name="customer_photo"></div><div class="tab-pane" id="contact"><input name="phone" value="012345678"></div><button type="submit" class="lcm-save">Save Changes</button></form></div>`;
function boot() {
    const dom = new JSDOM('<body><a id="open" class="js-customer-modal" href="/customers/1" data-title="Customer Detail">View</a><a id="other" class="js-customer-modal" href="/customers/2">Other</a><div id="loanCustomerModal"><h4 id="loanCustomerModalTitle"></h4><div id="loanCustomerModalBody"></div></div></body>', { url: 'https://example.test/customers', runScripts: 'outside-only' });
    const w = dom.window, $ = jquery(w), requests = [];
    w.jQuery = $;
    $.fn.modal = function (action) {
        const event = $.Event(action === 'hide' ? 'hide.bs.modal' : 'show.bs.modal'); this.trigger(event);
        if (!event.isDefaultPrevented()) { this.toggle(action !== 'hide'); this.trigger(action === 'hide' ? 'hidden.bs.modal' : 'shown.bs.modal'); }
        return this;
    };
    $.fn.tab = function () { const id = this.attr('href'); $('#loanCustomerModalForm .tab-pane').removeClass('active'); $(id).addClass('active'); return this; };
    $.ajax = options => { const d = $.Deferred(); const req = { options, d, aborted: false }; req.abort = () => { req.aborted = true; d.reject({}, 'abort'); }; requests.push(req); return d.promise({ abort: req.abort }); };
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.confirm = () => true;
    w.eval(script);
    return { dom, w, $, requests };
}
{
    const { dom, $, requests } = boot();
    $('#open').trigger('click');
    assert.equal(requests[0].options.url, 'https://example.test/customers/1');
    assert.equal(requests[0].options.data._customer_modal, 1);
    requests[0].d.resolve(detail);
    assert.equal($('#loanCustomerModalTitle').text(), 'Customer Detail');
    $('#loanCustomerModalBody .js-customer-modal').trigger('click');
    requests[1].d.resolve(edit);
    assert.equal($('#loanCustomerModalForm [name=expected_customer_id]').val(), '1');
    assert.equal($('#loanCustomerModalTitle').text(), 'Edit Customer');
    $('#loanCustomerModal').modal('hide');
    assert.equal($('#loanCustomerModalBody').text(), '');
    console.log('PASS detail/edit loading and close cleanup'); dom.window.close();
}
{
    const { dom, w, $, requests } = boot();
    $('#open').trigger('click'); requests[0].d.resolve(edit);
    $('[name=name]').val('Unsaved').trigger('input');
    w.confirm = () => false;
    $('#other').trigger('click'); assert.equal(requests.length, 1);
    $('#loanCustomerModal').modal('hide'); assert.equal($('[name=name]').val(), 'Unsaved');
    $('#loanCustomerModalForm').trigger('submit');
    assert.equal(requests[1].options.data.get('name'), 'Unsaved');
    assert.equal(requests[1].options.data.get('_method'), 'PUT');
    assert.equal(requests[1].options.data.get('_token'), 'csrf');
    $('#loanCustomerModalForm').trigger('submit'); assert.equal(requests.length, 2, 'Duplicate saves blocked');
    requests[1].d.reject({ status: 422, responseJSON: { message: 'Check fields', errors: { phone: ['Invalid phone <b>text</b>'] } } });
    assert.equal($('#contact').hasClass('active'), true);
    assert.equal($('[name=phone]').attr('aria-invalid'), 'true');
    assert.equal($('.lcm-field-error').text(), 'Invalid phone <b>text</b>');
    assert.equal($('.lcm-field-error b').length, 0);
    assert.equal($('[name=name]').val(), 'Unsaved');
    assert.equal($('.lcm-save').prop('disabled'), false);
    $('#loanCustomerModalForm').trigger('submit');
    requests[2].d.resolve({ success: true, detail_url: '/customers/1', message: 'Saved successfully' });
    assert.equal(requests[3].options.url, '/customers/1'); requests[3].d.resolve(detail);
    assert.equal($('#loanCustomerModalBody .alert-success').text(), 'Saved successfully');
    assert.equal($('#loanCustomerModalBody [data-customer-modal-view]').attr('data-customer-modal-view'), 'detail');
    console.log('PASS multipart save, identity/CSRF, duplicate protection, retained validation and success'); dom.window.close();
}
{
    const { dom, $, requests } = boot();
    $('#open').trigger('click'); $('#other').trigger('click');
    assert.equal(requests[0].aborted, true);
    requests[0].d.resolve(detail);
    requests[1].d.resolve('<html><body>Login</body></html>');
    assert.match($('#loanCustomerModalBody').text(), /session may have expired/);
    assert.equal($('#loanCustomerModalBody html').length, 0);
    $('#open').trigger('click'); $('#loanCustomerModal').modal('hide');
    assert.equal(requests[2].aborted, true);
    requests[2].d.resolve(detail);
    assert.equal($('#loanCustomerModalBody').text(), '');
    console.log('PASS stale requests, expired sessions and close cancellation'); dom.window.close();
}
console.log('3 customer modal UI checks passed');
