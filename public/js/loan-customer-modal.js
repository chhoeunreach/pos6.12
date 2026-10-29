(function ($) {
    'use strict';
    var $modal = $('#loanCustomerModal').appendTo(document.body);
    if (!$modal.length) return;
    var $body = $('#loanCustomerModalBody');
    var $title = $('#loanCustomerModalTitle');
    var request = null, sequence = 0, saving = false, dirty = false, refresh = false, opener = null, initial = '';
    var khmer = document.body.classList.contains('lm-language-km');
    function text(en, km) { return khmer ? km : en; }
    function snapshot(form) {
        return JSON.stringify(Array.from(new FormData(form)).filter(function (pair) { return pair[0] !== '_token'; }).map(function (pair) {
            var value = pair[1];
            return [pair[0], value instanceof File ? (value.name ? [value.name, value.size, value.lastModified] : null) : value];
        }));
    }
    function discard() { return !dirty || window.confirm(text('Discard your unsaved customer changes?', 'បោះបង់ការផ្លាស់ប្តូរអតិថិជនមិនទាន់រក្សាទុក?')); }
    function reveal(field) {
        var $pane = $(field).closest('.tab-pane');
        if ($pane.length) $('#loanCustomerModalForm .nav-tabs a').filter(function () { return this.getAttribute('href') === '#' + $pane.attr('id'); }).tab('show');
    }
    function showError(message) { $body.empty().append($('<div class="alert alert-danger" role="alert">').text(message)); }
    function load(url, title, message) {
        if (request) request.abort();
        var id = ++sequence;
        dirty = false;
        $title.text(title);
        $body.html('<div class="lcm-loading" role="status"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + text('Loading customer…', 'កំពុងផ្ទុកព័ត៌មានអតិថិជន…') + '</div>');
        $modal.modal('show');
        request = $.ajax({ url: url, data: { _customer_modal: 1 }, dataType: 'html' }).done(function (html) {
            if (id !== sequence) return;
            if (!/data-customer-modal-view\s*=/.test(html)) {
                showError(text('Your session may have expired. Close this window and reload the page.', 'សម័យចូលប្រើអាចផុតកំណត់។ សូមបិទ និងផ្ទុកទំព័រឡើងវិញ។'));
                return;
            }
            $body.html(html);
            if (message) $body.prepend($('<div class="alert alert-success" role="status">').text(message));
            var form = document.getElementById('loanCustomerModalForm');
            if (form) {
                initial = snapshot(form);
                form.addEventListener('invalid', function (event) { reveal(event.target); }, true);
            }
        }).fail(function (xhr, status) {
            if (status === 'abort' || id !== sequence) return;
            showError(text('Unable to load this customer. Close the window and try again.', 'មិនអាចផ្ទុកព័ត៌មានអតិថិជន។ សូមបិទ និងព្យាយាមម្តងទៀត។'));
        }).always(function () { if (id === sequence) request = null; });
    }
    $(document).on('click', '.js-customer-modal', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (saving || !discard()) return;
        if (!$.contains($modal[0], this)) opener = this;
        load(this.href, $(this).data('title') || text('Customer Detail', 'ព័ត៌មានអតិថិជន'));
    });
    $modal.on('input change', '#loanCustomerModalForm', function () { dirty = snapshot(this) !== initial; });
    $modal.on('submit', '#loanCustomerModalForm', function (event) {
        event.preventDefault();
        if (saving) return;
        var form = this, $form = $(form), $button = $form.find('.lcm-save'), label = $button.html();
        var $errors = $form.find('.lcm-errors').empty().prop('hidden', true);
        $form.find('.lcm-field-error').remove();
        $form.find('[aria-invalid]').removeAttr('aria-invalid');
        saving = true;
        $button.prop('disabled', true).text(text('Saving…', 'កំពុងរក្សាទុក…'));
        $.ajax({ url: form.action, type: 'POST', data: new FormData(form), processData: false, contentType: false, dataType: 'json', headers: { Accept: 'application/json' } }).done(function (data) {
            dirty = false; refresh = true;
            load(data.detail_url, text('Customer Detail', 'ព័ត៌មានអតិថិជន'), data.message);
        }).fail(function (xhr) {
            var data = xhr.responseJSON || {};
            var message = xhr.status === 419 || xhr.status === 401 ? text('Your session expired. Reload the page before saving again.', 'សម័យចូលប្រើផុតកំណត់។ សូមផ្ទុកទំព័រឡើងវិញ។') : data.message || text('Unable to save. Your changes are still in this form.', 'មិនអាចរក្សាទុក។ ការផ្លាស់ប្តូរនៅតែមានក្នុងទម្រង់នេះ។');
            $errors.text(message).prop('hidden', false);
            var first = null;
            Object.keys(data.errors || {}).forEach(function (name) {
                var field = form.elements.namedItem(name);
                var error = data.errors[name][0];
                if (field && field.tagName) {
                    $(field).attr('aria-invalid', 'true').after($('<div class="lcm-field-error">').text(error));
                    first = first || field;
                } else $errors.append($('<div>').text(error));
            });
            if (first) { reveal(first); first.focus(); }
            else $errors[0].scrollIntoView({ block: 'nearest' });
        }).always(function () { saving = false; $button.prop('disabled', false).html(label); });
    });
    $modal.on('hide.bs.modal', function (event) { if (saving || !discard()) event.preventDefault(); });
    $modal.on('hidden.bs.modal', function () {
        sequence++;
        if (request) { request.abort(); request = null; }
        dirty = false;
        $body.empty();
        if (refresh) window.location.reload();
        else if (opener && opener.isConnected) opener.focus();
    });
    window.addEventListener('beforeunload', function (event) { if (dirty || saving) { event.preventDefault(); event.returnValue = ''; } });
})(jQuery);
