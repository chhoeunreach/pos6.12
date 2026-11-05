(function () {
    'use strict';

    var snapshots = new WeakMap();

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
        var row = form.closest('.wiz-deposit-row');
        if (row && row.hasAttribute('data-saved-amount')) {
            var dirty = cents(form.querySelector('[name="amount"]').value) !== cents(row.getAttribute('data-saved-amount'));
            row.classList.toggle('has-unsaved-amount', dirty);
        }
        return matches;
    }

    document.addEventListener('click', function (event) {
        var header = event.target.closest('.wiz-deposit-header');
        if (header) {
            var row = header.closest('.wiz-deposit-row');
            var expanded = header.getAttribute('aria-expanded') !== 'true';
            row.classList.toggle('open', expanded);
            header.setAttribute('aria-expanded', String(expanded));
            row.querySelector('.wiz-deposit-body').style.display = expanded ? 'block' : 'none';
            return;
        }
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
        else if (event.target.classList.contains('lm-method-amount') && inputs.length === 1) form.querySelector('[name="amount"]').value = event.target.value;
        update(form);
    });

    window.LoanDepositPayments = {
        init: function (root) {
            root.querySelectorAll('.wiz-deposit-edit-form').forEach(function(form) {
                if (!snapshots.has(form)) snapshots.set(form, form.innerHTML);
                update(form);
            });
        },
        initActions: function (options) {
            var $ = window.jQuery;
            var busy = false;
            var labels = options.labels || {};
            function feedback(message, error) {
                $('#wizDepositFeedback').text(message).removeClass('alert-danger alert-success').addClass(error ? 'alert-danger' : 'alert-success').prop('hidden', false);
            }
            function notifyParent() {
                if (window.parent !== window) window.parent.postMessage({type: 'loan-deposit-payments-changed', loanId: options.loanId}, window.location.origin);
            }
            function refresh(message, openId) {
                return $.ajax({url: options.refreshUrl, dataType: 'html', cache: false}).then(function (html) {
                    var page = $('<div>').append($.parseHTML(html, document, false));
                    var block = page.find('#wizCustomerDepositBlock');
                    if (block.length !== 1) throw new Error('Deposit block was not returned');
                    $('#wizCustomerDepositBlock').replaceWith(block);
                    window.LoanDepositPayments.init(block[0]);
                    if (openId != null) block.find('.wiz-deposit-row').filter(function() { return String($(this).attr('data-id')) === String(openId); }).addClass('open').find('.wiz-deposit-body').show().end().find('.wiz-deposit-header').attr('aria-expanded', 'true');
                    if (message) feedback(message, false);
                });
            }
            function lock(value) {
                busy = value;
                $('#wizCustomerDepositBlock .wiz-deposit-update-btn, #wizCustomerDepositBlock .wiz-deposit-remove, #wizCustomerDepositBlock .wiz-deposit-refresh, #wizCustomerDepositBlock .wiz-deposit-cancel, #wizDepositTotalTarget, #wizDepositAdjustPayment, #wizCustomerDepositBlock .wiz-deposit-total-review').prop('disabled', value);
            }
            $(document).off('.loanDepositActions');
            $(document).on('click.loanDepositActions', '.wiz-deposit-total-review', function() {
                if (busy) return;
                var target = document.getElementById('wizDepositTotalTarget');
                if (!target.reportValidity()) return;
                var id = $('#wizDepositAdjustPayment').val();
                var row = $('#wizCustomerDepositBlock .wiz-deposit-row').filter(function() { return String($(this).attr('data-id')) === String(id); });
                if (!row.length) { feedback(labels.noPayment || 'Add a deposit payment first.', true); return; }
                var current = cents($('#wizCustomerDepositBlock .wiz-deposit-summary').attr('data-current-total'));
                var adjusted = cents(target.value) - current + cents(row.attr('data-saved-amount'));
                if (adjusted <= 0) { feedback(labels.totalTooLow || 'Choose another payment or delete this payment to remove it.', true); return; }
                var form = row.find('.wiz-deposit-edit-form');
                form.find('[name="amount"]').val((adjusted / 100).toFixed(2));
                var methods = form.find('.lm-method-amount');
                if (methods.length === 1) methods.val((adjusted / 100).toFixed(2));
                update(form[0]);
                row.addClass('open').find('.wiz-deposit-header').attr('aria-expanded', 'true');
                row.find('.wiz-deposit-body').show();
                feedback(methods.length > 1 ? (labels.splitReview || 'Review payment methods before saving.') : (labels.review || 'Review the amount and methods, then save.'), false);
                form.find('[name="amount"]').trigger('focus');
            });
            $(document).on('click.loanDepositActions', '.wiz-deposit-cancel', function() {
                if (busy) return;
                var form = $(this).closest('.wiz-deposit-edit-form')[0];
                if (snapshots.has(form)) form.innerHTML = snapshots.get(form);
                update(form);
                var row = $(form).closest('.wiz-deposit-row');
                row.removeClass('open').find('.wiz-deposit-header').attr('aria-expanded', 'false');
                row.find('.wiz-deposit-body').hide();
                $('#wizDepositTotalTarget').val($('#wizCustomerDepositBlock .wiz-deposit-summary').attr('data-current-total'));
                $('#wizDepositFeedback').prop('hidden', true);
            });
            $(document).on('click.loanDepositActions', '.wiz-deposit-refresh', function() {
                if (busy) return;
                lock(true);
                refresh().fail(function() { feedback(labels.refreshError, true); }).always(function() { lock(false); });
            });
            $(document).on('click.loanDepositActions', '.wiz-deposit-update-btn, .wiz-deposit-remove', function() {
                if (busy) return;
                var button = $(this), deleting = button.hasClass('wiz-deposit-remove');
                var row = button.closest('.wiz-deposit-row'), form = button.closest('.wiz-deposit-edit-form');
                if (deleting && !window.confirm(labels.confirmDelete)) return;
                if (!deleting && !window.LoanDepositPayments.validate(form[0])) return;
                var payload = deleting ? [] : form.find(':input').serializeArray();
                payload.push({name: '_token', value: options.token}, {name: '_method', value: deleting ? 'DELETE' : 'PUT'});
                if (deleting) payload.push({name: 'return_to', value: button.attr('data-return-to') || options.refreshUrl});
                lock(true);
                $.ajax({url: options.updateBase + '/' + row.attr('data-id'), method: 'POST', data: $.param(payload), dataType: 'json'})
                    .then(function(result) {
                        if (!result.success) { feedback(result.message || labels.error, true); return; }
                        notifyParent();
                        return refresh(result.message || (deleting ? labels.deleted : labels.updated), deleting ? null : row.attr('data-id'))
                            .then(null, function() { feedback(labels.refreshError, true); });
                    }, function(xhr) {
                        var response = xhr.responseJSON || {}, errors = response.errors && Object.values(response.errors);
                        feedback(errors && errors.length ? errors[0][0] : (response.message || labels.error), true);
                    }).always(function() { lock(false); });
            });
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
