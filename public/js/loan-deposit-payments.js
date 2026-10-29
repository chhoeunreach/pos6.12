(function () {
    'use strict';

    function cents(value) {
        return Math.round((parseFloat(value) || 0) * 100);
    }

    function update(form) {
        var rows = form.querySelector('.wiz-deposit-methods');
        var summary = form.querySelector('.wiz-deposit-method-total');
        var total = 0;
        rows.querySelectorAll('.lm-method-amount').forEach(function (input) {
            total += cents(input.value);
        });
        var matches = total === cents(form.querySelector('[name="amount"]').value);
        summary.textContent = summary.dataset.label + ': ' + (total / 100).toFixed(2) + ' ' + summary.dataset.currency;
        if (!matches) summary.textContent += ' — ' + summary.dataset.mismatch;
        summary.style.color = matches ? '#15803d' : '#dc2626';
        var buttons = rows.querySelectorAll('.lm-remove-method');
        buttons.forEach(function (button) { button.disabled = buttons.length === 1; });
        form.querySelector('.wiz-deposit-add-method').disabled = buttons.length >= 50;
        return matches;
    }

    document.addEventListener('click', function (event) {
        var form = event.target.closest('.wiz-deposit-edit-form');
        if (!form) return;
        var rows = form.querySelector('.wiz-deposit-methods');
        if (event.target.closest('.wiz-deposit-add-method')) {
            if (rows.children.length >= 50) return;
            var index = Number(rows.dataset.nextIndex);
            rows.dataset.nextIndex = index + 1;
            var template = form.querySelector('.wiz-deposit-method-template');
            var assigned = 0;
            rows.querySelectorAll('.lm-method-amount').forEach(function (input) { assigned += cents(input.value); });
            rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, index));
            var remaining = cents(form.querySelector('[name="amount"]').value) - assigned;
            var input = rows.lastElementChild.querySelector('.lm-method-amount');
            input.value = remaining > 0 ? (remaining / 100).toFixed(2) : '';
            input.focus();
            update(form);
        } else if (event.target.closest('.lm-remove-method') && rows.children.length > 1) {
            event.target.closest('.lm-method-row').remove();
            update(form);
        }
    });

    document.addEventListener('input', function (event) {
        var form = event.target.closest('.wiz-deposit-edit-form');
        if (!form) return;
        var inputs = form.querySelectorAll('.wiz-deposit-methods .lm-method-amount');
        if (event.target.name === 'amount' && inputs.length === 1) inputs[0].value = event.target.value;
        update(form);
    });

    window.LoanDepositPayments = {
        init: function (root) {
            root.querySelectorAll('.wiz-deposit-edit-form').forEach(update);
        },
        validate: function (form) {
            var matches = update(form);
            var inputs = form.querySelectorAll('input, select');
            for (var i = 0; i < inputs.length; i++) {
                if (!inputs[i].reportValidity()) return false;
            }
            if (!matches) form.querySelector('.wiz-deposit-method-total').scrollIntoView({ block: 'nearest' });
            return matches;
        }
    };
})();
