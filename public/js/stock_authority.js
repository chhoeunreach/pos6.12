function authority_update_receiving($row, suggestMatch) {
    var $source = $row.find('.authority-source');
    var isNewSource = $source.val() === 'new';
    $row.find('.authority-new-source').toggle(isNewSource).find('input').prop('disabled', !isNewSource);
    var fillMissingSerial = Boolean($source.val()) && !isNewSource && !($source.find(':selected').attr('data-serial') || '').trim();
    $row.find('.authority-existing-serial').toggle(fillMissingSerial).find('input').prop('disabled', !fillMissingSerial);
    var serial = isNewSource ? ($row.find('.authority-new-serial').val() || '').trim()
        : (fillMissingSerial ? ($row.find('.authority-verified-serial').val() || '').trim() : ($source.find(':selected').attr('data-serial') || ''));
    var $destination = $row.find('.authority-destination');
    var changeSerial = $row.find('.authority-change-receiving-serial').prop('checked');
    var quantity = Number($row.attr('data-quantity'));
    var occupied = [];
    var reservedMissing = 0;
    $('#authority_correction_form .authority-line').not($row).each(function () {
        var $other = $(this);
        if ($other.attr('data-receiving-group') === $row.attr('data-receiving-group') && $other.find('.authority-destination').val() === 'new') {
            reservedMissing += Number($other.attr('data-quantity'));
        }
    });
    $('#authority_correction_form .authority-line').not($row).find('.authority-destination').each(function () {
        if (this.value && this.value !== 'new') { occupied.push(this.value); }
    });
    var matches = [];
    var occupiedMatch = false;
    $destination.find('option[value!=""]').each(function () {
        var $option = $(this);
        if (this.value === 'new') {
            $option.prop('disabled', !(Number($row.attr('data-missing-receiving')) - reservedMissing + 0.0001 >= quantity));
            return;
        }
        var optionSerial = $option.attr('data-serial') || '';
        var sameSerial = optionSerial === serial;
        var sameQuantity = Math.abs(Number($option.attr('data-quantity')) - quantity) < 0.0001;
        var isOccupied = occupied.indexOf(this.value) !== -1;
        var disabled = !sameQuantity || isOccupied ||
            (Boolean($source.val()) && ((!serial && Boolean(optionSerial)) || (!changeSerial && $option.attr('data-used') === '1' && Boolean(optionSerial) && !sameSerial)));
        $option.prop('disabled', disabled);
        if (!disabled && $source.val() && sameSerial) { matches.push(this.value); }
        if ($source.val() && sameSerial && sameQuantity && isOccupied) { occupiedMatch = true; }
    });

    var $selected = $destination.find(':selected');
    if ($selected.prop('disabled') || (suggestMatch && $selected.val() && $selected.val() !== 'new' && $selected.attr('data-serial') !== serial)) {
        $destination.val('');
    }
    if (suggestMatch && !$destination.val() && matches.length === 1) {
        $destination.val(matches[0]);
    }
    var hasAvailableBatch = $destination.find('option').filter(function () {
        return this.value && this.value !== 'new' && !this.disabled;
    }).length > 0;
    if (suggestMatch && $source.val() && !$destination.val() && (occupiedMatch || !hasAvailableBatch)) {
        var $missingReceiving = $destination.find('option[value="new"]');
        if ($missingReceiving.length && !$missingReceiving.prop('disabled')) { $destination.val('new'); }
    }
    $destination.trigger('change.select2');
    $row.find('.authority-missing-receiving-help').toggle(!$destination.val() && Boolean($source.val()) &&
        $destination.find('option[value="new"]').length > 0 && !$destination.find('option[value="new"]').prop('disabled'));

    var message;
    if (!$source.val()) {
        message = 'Choose the correct source receipt first.';
    } else if ($destination.val() === 'new') {
        message = 'Preview will create the missing receiving batch for ' + (serial ? 'serial ' + serial : 'this receipt without a serial') +
            ', quantity ' + quantity + '. No second transfer quantity movement will be posted.';
    } else if ($destination.val()) {
        var receivingSerial = $destination.find(':selected').attr('data-serial') || '';
        message = !serial && !receivingSerial
            ? 'No serial: deduction will use the selected receipt ID and receiving batch ID. Quantities must match.'
            : receivingSerial === serial
            ? 'Serials match. Preview will validate source stock and dependent movements before this deduction can be corrected.'
            : 'The receiving serial will change to ' + serial + '. Preview will validate later usage before applying this serial / lot correction.';
    } else {
        message = matches.length > 1
            ? 'Several receiving batches match. Select the batch confirmed by your delivery record.'
            : (changeSerial
                ? 'Select the receiving batch to update to the source serial / lot. Quantities must match; preview will check later usage.'
                : 'Select the receiving batch to correct. Enable Change receiving serial / lot to correct a different serial on a used batch.');
    }
    $row.find('.authority-selection-help').text(message);
}

function authority_date_value(value) {
    value = (value || '').trim().replace('T', ' ');
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(value)) { value += ':00'; }
    return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value) ? value : '';
}

function authority_date_change_enabled($form) {
    var $choice = $form.find('#authority_change_date');
    return !$choice.length || $choice.prop('checked');
}

function authority_update_dates($form) {
    var recorded = authority_date_value($form.attr('data-recorded-date'));
    var effective = (authority_date_change_enabled($form) ? authority_date_value($form.find('#authority_date').val()) : '') || recorded;
    var earliest = '';
    var conflicts = [];
    $form.find('.authority-line').each(function () {
        var $row = $(this);
        var $source = $row.find('.authority-source');
        var date = authority_date_value(!$source.val() ? $row.attr('data-recorded-source-date')
            : ($source.val() === 'new' ? $row.find('.authority-new-source-date').val() : $source.find(':selected').attr('data-receipt-date')));
        if (date > earliest) { earliest = date; }
        if (date && effective && date > effective) {
            conflicts.push(($source.val() ? 'Source receipt ' + ($source.val() === 'new' ? '(new)' : '#' + $source.val()) : 'An unchanged source receipt') + ' arrived ' + date);
        }
    });
    var message = earliest ? 'Earliest permitted delivery: ' + earliest + '.' : '';
    if (conflicts.length) {
        message = conflicts.join('; ') + ', after delivery ' + effective + '. Select an earlier source receipt or enter the actual verified delivery date on or after ' + earliest + '.';
    }
    $form.find('.authority-date-guidance').toggleClass('text-danger', conflicts.length > 0).text(message);
    $form.find('.authority-use-earliest-date').toggle(Boolean(authority_date_change_enabled($form) && earliest && effective && earliest > effective)).attr('data-date', earliest);
    return conflicts.length === 0;
}

function authority_complete_fields($form) {
    var unresolved = $form.find('.authority-line').length ? 0 : 1;
    var summary = ['Authority record-based correction for ' + ($form.attr('data-reference') || 'this transfer') + '.'];
    $form.find('.authority-line').each(function () {
        var $row = $(this);
        var completion;
        try { completion = JSON.parse($row.attr('data-completion') || '{}'); } catch (ignore) { completion = {}; }
        var $source = $row.find('.authority-source');
        var $destination = $row.find('.authority-destination');
        if (!$source.val() && completion.source_id && $source.find('option').filter(function () { return this.value === String(completion.source_id); }).length) {
            $source.val(String(completion.source_id)).trigger('change.select2');
        }
        if (String($source.val()) === String(completion.source_id) && completion.verified_serial && !$row.find('.authority-verified-serial').val()) {
            $row.find('.authority-verified-serial').val(completion.verified_serial);
        }
        authority_update_receiving($row, false);
        if (!$destination.val() && String($source.val()) === String(completion.source_id) && completion.destination_id) {
            var $option = $destination.find('option').filter(function () { return this.value === String(completion.destination_id); });
            if ($option.length && !$option.prop('disabled')) { $destination.val(String(completion.destination_id)); }
        }
        if (!$destination.val() && $source.val()) { authority_update_receiving($row, true); }
        authority_update_receiving($row, false);
        if (!$source.val() || !$destination.val()) { unresolved++; }
        if ($source.val() && $destination.val()) {
            summary.push('Line #' + ($row.attr('data-line-id') || '?') + ': source receipt ' + $source.val() +
                ', receiving batch ' + $destination.val() + ', quantity ' + $row.attr('data-quantity') + '.');
            var filledSerial = $row.find('.authority-verified-serial:not(:disabled)').val();
            if (filledSerial) { summary.push('Proposed missing serial ' + filledSerial + ' from the receiving record; review before applying.'); }
        }
    });
    // Claim existing batches first, then fill only gaps with no available existing batch.
    // A batch used by a different serial must not prevent completion of a missing receipt.
    $form.find('.authority-line').each(function () {
        var $row = $(this);
        var $destination = $row.find('.authority-destination');
        authority_update_receiving($row, false);
        if ($row.find('.authority-source').val() && !$destination.val() &&
            !$destination.find('option').filter(function () { return this.value && this.value !== 'new' && !this.disabled; }).length) {
            var $new = $destination.find('option[value="new"]');
            if ($new.length && !$new.prop('disabled')) {
                $destination.val('new');
                authority_update_receiving($row, false);
                unresolved--;
                summary.push('Line #' + ($row.attr('data-line-id') || '?') + ': source receipt ' + $row.find('.authority-source').val() +
                    ', receiving batch new, quantity ' + $row.attr('data-quantity') + '.');
            }
        }
    });
    authority_update_dates($form);
    // Completing receipt selections must never propose or submit a date change.
    var $date = $form.find('#authority_date');
    if (!authority_date_change_enabled($form)) {
        $date.val('').removeAttr('data-proposed-date').prop('disabled', true);
        $form.find('#authority_date_basis').val('');
    }
    authority_update_dates($form);
    if ($form.find('#authority_date_basis').val()) {
        summary.push('Proposed delivery ' + $date.val() + ' from existing receipt dates. Actual delivery must be reviewed against delivery records.');
    }
    var $evidence = $form.find('#authority_evidence');
    if (!$evidence.val() || $evidence.val() === $evidence.attr('data-generated-notes')) {
        var notes = summary.join('\n').slice(0, 2000);
        $evidence.val(notes).attr('data-generated-notes', notes);
    }
    $form.find('.authority-auto-status').text(unresolved
        ? 'Filled supported records. ' + unresolved + ' line(s) still need a source receipt or receiving batch. Select those records to complete verification.'
        : 'Fields completed from existing records. Review proposed serial and date changes, then verify the correction.');
    return unresolved === 0;
}

function authority_preview_error($form, xhr) {
    var response = xhr.responseJSON || {};
    var errors = response.errors ? Object.keys(response.errors).map(function (key) {
        return [].concat(response.errors[key]).join(' ');
    }).join(' ') : '';
    var message = response.msg || errors || (xhr.status === 422 ? response.message : '') ||
        (xhr.status === 0 ? 'Unable to reach the server.' : 'The correction preview could not be completed.');
    var kind = (response.stock_error || {}).kind;
    var guidance = 'Check the selected source receipt and receiving batch against the SKU, quantity and supporting records, then preview again.';
    if (kind === 'receipt_date_conflict') {
        guidance = 'Select a source receipt available on the recorded delivery date, or enable Change delivery date and enter the actual verified delivery date.';
    } else if (kind === 'related_allocation' || kind === 'transfer_date_dependencies') {
        guidance = 'Review and reconcile the dependent movements named in the error before retrying this correction.';
    } else if (xhr.status === 419 || xhr.status === 401) {
        guidance = 'Your session may have expired. Sign in again in another tab, then retry. If the session token has expired, copy your entries before refreshing this page.';
    } else if (xhr.status === 403) {
        guidance = 'Ask an administrator to check your stock authority permission and access to the transfer locations.';
    } else if (xhr.status === 0 || xhr.status >= 500) {
        guidance = 'Retry the preview. If it fails again, ask the administrator to check the server log' +
            (response.error_reference ? ' using reference ' + response.error_reference : ' for this transfer') + '.';
    } else if (errors) {
        guidance = 'Correct the fields listed above, then preview again.';
    }
    $form.find('.authority-auto-status').text(message);
    var $error = $form.find('.authority-preview-error');
    if (!$error.length) { $error = $('<div class="authority-preview-error alert alert-danger" role="alert" tabindex="-1"></div>').appendTo($form); }
    $error.empty().append($('<p>').text(message), $('<p>').text(guidance), $('<p>').text('No correction was applied. Your entries are still available here.')).show().focus();
}

function authority_request_preview($form) {
    if ($form.data('authority-preview-busy')) { return; }
    if ($form[0] && $form[0].reportValidity && !$form[0].reportValidity()) {
        $form.find('.authority-auto-status').text('Complete the required receipt details before verification.');
        return;
    }
    // Skip untouched rows without disabling their controls: failures must remain editable.
    var ignored = {};
    $form.find('.authority-line').each(function () {
        var $row = $(this);
        if (!$row.find('.authority-source').val()) {
            $row.find(':input[name]').each(function () { ignored[this.name] = true; });
        }
    });
    var payload = $.param($form.serializeArray().filter(function (field) { return !ignored[field.name]; }));
    var $buttons = $form.find(':submit, .authority-auto-complete').filter(':enabled');
    $form.data('authority-preview-busy', true);
    $buttons.prop('disabled', true);
    $form.find('.authority-preview-error').hide();
    $form.find('.authority-auto-status').text('Checking source quantities, receiving batches, delivery dates and dependent movements…');
    $.ajax({url: $form.attr('action'), type: 'POST', data: payload, dataType: 'json', headers: {Accept: 'application/json'}}).done(function (result) {
        if (!$.contains(document, $form[0])) { return; }
        if (!result.success || !result.html) {
            authority_preview_error($form, {status: 422, responseJSON: result});
            return;
        }
        var $content = $form.closest('.modal').find('.modal-content');
        if ($content.length) {
            $content.html('<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button><h4 class="modal-title">Verified Authority correction — review changes</h4></div><div class="modal-body"></div>');
            $content.find('.modal-body').html(result.html);
        } else {
            $form.replaceWith(result.html);
        }
    }).fail(function (xhr) {
        if ($.contains(document, $form[0])) { authority_preview_error($form, xhr); }
    }).always(function () {
        $form.removeData('authority-preview-busy');
        $buttons.prop('disabled', false);
    });
}

function authority_verify_completion($form) {
    if (authority_complete_fields($form) && authority_update_dates($form)) { authority_request_preview($form); }
}

function authority_initialize_form() {
    var $form = $('#authority_correction_form');
    if ($.fn.select2) {
        var options = {width: '100%'};
        var $modal = $form.closest('.modal');
        if ($modal.length) { options.dropdownParent = $modal; }
        $form.find('.authority-receipt').select2(options);
    }
    $form.find('#authority_date').prop('disabled', !authority_date_change_enabled($form)).prop('required', authority_date_change_enabled($form));
    $form.find('.authority-line').each(function () { authority_update_receiving($(this), false); });
    authority_complete_fields($form);
    $form.on('click', '.authority-auto-complete', function () { authority_verify_completion($form, $(this)); });
    $form.on('change', '.authority-source', function () {
        $(this).closest('.authority-line').find('.authority-verified-serial').val('');
        authority_update_receiving($(this).closest('.authority-line'), true);
        $form.find('.authority-line').not($(this).closest('.authority-line')).each(function () {
            authority_update_receiving($(this), false);
        });
        authority_update_dates($form);
    });
    $form.on('change', '.authority-destination', function () {
        $form.find('.authority-line').each(function () { authority_update_receiving($(this), false); });
    });
    $form.on('change', '.authority-change-receiving-serial', function () {
        authority_update_receiving($(this).closest('.authority-line'), false);
    });
    $form.on('input', '.authority-new-serial, .authority-verified-serial', function () {
        authority_update_receiving($(this).closest('.authority-line'), false);
    });
    $form.on('change', '#authority_change_date', function () {
        var enabled = authority_date_change_enabled($form);
        $form.find('#authority_date').prop('disabled', !enabled).prop('required', enabled);
        if (!enabled) {
            $form.find('#authority_date').val('').removeAttr('data-proposed-date');
            $form.find('#authority_date_basis').val('');
        }
        authority_update_dates($form);
    });
    $form.on('click', '.authority-keep-dates', function () {
        $form.find('#authority_change_date').prop('checked', false).trigger('change');
    });
    $form.on('input change', '#authority_date, .authority-new-source-date', function () {
        if ($(this).is('#authority_date') && $(this).val() !== $(this).attr('data-proposed-date')) { $form.find('#authority_date_basis').val(''); }
        authority_update_dates($form);
    });
    $form.on('click', '.authority-use-earliest-date', function () {
        $form.find('#authority_date').val($(this).attr('data-date')).trigger('change').focus();
    });
    $form.on('submit', function (event) {
        event.preventDefault();
        var valid = authority_update_dates($form);
        $form.find('.authority-line').each(function () {
            var $row = $(this);
            var source = Boolean($row.find('.authority-source').val());
            var destination = Boolean($row.find('.authority-destination').val());
            if (source !== destination) {
                valid = false;
                $row.find('.authority-selection-help').text('Select both the correct source receipt and the receiving batch.');
            }
        });
        if (!valid) { event.preventDefault(); $form.find('.authority-date-guidance.text-danger').length && $form.find('#authority_date').focus(); return; }
        authority_request_preview($form);
    });
}

$(function () {
    var $details = $('#authority_details_modal');
    var detailsVersion = 0;
    function loadAuthorityDetails(url, data) {
        var version = ++detailsVersion;
        $details.find('.modal-body').text('Loading issue details…');
        $details.modal('show');
        $.ajax({url: url, data: data || {}, dataType: 'html'}).done(function (html) {
            if (version === detailsVersion) { $details.find('.modal-body').html(html); }
        }).fail(function () {
            if (version === detailsVersion) { $details.find('.modal-body').text('Unable to load issue details. Close this window and try again.'); }
        });
    }
    $(document).on('click', '.authority-detail-link, #authority_details_modal .pagination a', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
        event.preventDefault();
        loadAuthorityDetails(this.href);
    });
    $details.on('submit', '.authority-details-filter', function (event) {
        event.preventDefault();
        loadAuthorityDetails(this.action, $(this).serialize());
    });
    var $modal = $('#authority_correction_modal');
    var loadVersion = 0;
    authority_initialize_form();
    if ($modal.find('#authority_correction_form').length) { $modal.modal('show'); }

    $(document).on('click', '.open-authority-correction', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
        event.preventDefault();
        var version = ++loadVersion;
        $modal.find('.modal-content').html('<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button><h4 class="modal-title">Authority correction</h4></div><div class="modal-body" role="status">Loading correction records…</div>');
        $modal.modal('show');
        $.ajax({url: this.href, data: {modal: 1}, dataType: 'html'}).done(function (html) {
            if (version !== loadVersion) { return; }
            $modal.find('.modal-content').html(html);
            authority_initialize_form();
        }).fail(function () {
            if (version !== loadVersion) { return; }
            $modal.find('.modal-body').text('Unable to load correction records. Close this window and try again.');
        });
    });
});
