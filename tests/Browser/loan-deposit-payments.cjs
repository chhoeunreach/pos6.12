const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../../storage/app/stock-ui-tests/node_modules/jsdom');

function row(index, amount, id = '') {
    return `<div class="lm-method-row"><input name="payment_details[${index}][id]" value="${id}"><select name="payment_details[${index}][method]"><option value="cash">Cash</option><option value="aba">ABA</option></select><input type="number" class="lm-method-amount" name="payment_details[${index}][amount]" value="${amount}" min="0.01" step="0.01" required><button type="button" class="lm-remove-method">Remove</button></div>`;
}
function form(id, amount) {
    return `<div class="wiz-deposit-edit-form" id="${id}"><input name="amount" value="${amount}"><div class="wiz-deposit-methods" data-next-index="1">${row(0, amount, id)}</div><template class="wiz-deposit-method-template">${row('__INDEX__', '')}</template><button class="wiz-deposit-add-method">Add</button><p class="wiz-deposit-method-total" data-label="Total" data-currency="USD" data-mismatch="Amounts must match"></p></div>`;
}
const dom = new JSDOM(form('first', 100) + form('second', 80), { runScripts: 'outside-only' });
const { window } = dom;
const document = window.document;
window.HTMLElement.prototype.scrollIntoView = function () {};
window.eval(fs.readFileSync(path.join(__dirname, '../../public/js/loan-deposit-payments.js'), 'utf8'));
window.LoanDepositPayments.init(document);
const first = document.getElementById('first');
const second = document.getElementById('second');
function set(input, value) { input.value = value; input.dispatchEvent(new window.Event('input', { bubbles: true })); }
assert.equal(first.querySelector('.lm-remove-method').disabled, true);
set(first.querySelector('.lm-method-amount'), 30);
assert.equal(window.LoanDepositPayments.validate(first), false);
first.querySelector('.wiz-deposit-add-method').click();
const inputs = first.querySelectorAll('.lm-method-amount');
assert.equal(inputs[1].value, '70.00');
first.querySelectorAll('select')[1].value = 'aba';
assert.equal(window.LoanDepositPayments.validate(first), true, JSON.stringify(Array.from(first.querySelectorAll('input, select')).map(i => [i.name, i.value, i.validationMessage])));
assert.equal(second.querySelectorAll('.lm-method-row').length, 1);
assert.equal(second.querySelector('.lm-method-amount').value, '80');
first.querySelector('.lm-remove-method').click();
assert.equal(first.querySelector('.lm-method-amount').name, 'payment_details[1][amount]');
set(first.querySelector('[name="amount"]'), 90);
assert.equal(first.querySelector('.lm-method-amount').value, '90');
first.querySelector('.wiz-deposit-add-method').click();
assert.equal(first.querySelectorAll('.lm-method-amount')[1].name, 'payment_details[2][amount]');
assert.equal(window.LoanDepositPayments.validate(first), false);
assert.equal(window.LoanDepositPayments.validate(second), true);
console.log('Deposit payment UI checks passed.');
