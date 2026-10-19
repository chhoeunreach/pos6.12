function authority_receiving_reserved_for_other_row($row, $option) {
    var reserved = false;
    $('#authority_correction_form .authority-line').not($row).each(function () {
        var $other = $(this);
        if ($other.attr('data-receiving-group') !== $row.attr('data-receiving-group') || $other.find('.authority-destination').val()
            || Math.abs(Number($other.attr('data-quantity')) - Number($option.attr('data-quantity'))) > 0.0001) { return; }
        var $source = $other.find('.authority-source');
        var sourceId = $source.val();
        if (!sourceId) {
            try { sourceId = JSON.parse($other.attr('data-completion') || '{}').source_id; } catch (ignore) { return; }
        }
        if (!sourceId || sourceId === 'new') { return; }
        var $sourceOption = $source.find('option').filter(function () { return this.value === String(sourceId); });
        if (!$sourceOption.length) { return; }
        var serial = ($sourceOption.attr('data-serial') || '').trim() || ($other.find('.authority-verified-serial').val() || '').trim();
        if (serial === ($option.attr('data-serial') || '').trim()) { reserved = true; }
    });
    return reserved;
}

function authority_combined_receiving_rows($form, $row, value) {
    return $form.find('.authority-line').filter(function () {
        var $candidate = $(this), destination = $candidate.find('.authority-destination').val();
        return $candidate.attr('data-receiving-group') === $row.attr('data-receiving-group')
            && $candidate.find('.authority-source').val() && (!destination || destination === value)
            && $candidate.find('.authority-destination option').filter(function () { return this.value === value; }).length;
    });
}

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
        if ($option.attr('data-split') === '1') {
            var total = 0;
            authority_combined_receiving_rows($row.closest('form'), $row, this.value).each(function () { total += Number($(this).attr('data-quantity')); });
            $option.prop('disabled', !total || Math.abs(total - Number($option.attr('data-quantity'))) > 0.0001);
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
    if ($selected.prop('disabled') || (suggestMatch && $selected.val() && $selected.val() !== 'new' && $selected.attr('data-split') !== '1' && $selected.attr('data-serial') !== serial)) {
        $destination.val('');
    }
    if (suggestMatch && !$destination.val() && matches.length === 1) {
        $destination.val(matches[0]);
    }
    var $availableBatches = $destination.find('option').filter(function () {
        return this.value && this.value !== 'new' && $(this).attr('data-split') !== '1' && !this.disabled && !authority_receiving_reserved_for_other_row($row, $(this));
    });
    // A unique eligible batch can be proposed even when its missing/wrong serial needs repair.
    // Exact serial matches take priority, including batches needed by other rows.
    if (suggestMatch && $source.val() && !$destination.val() && matches.length === 0 && $availableBatches.length === 1) {
        $destination.val($availableBatches.val());
    }
    var hasAvailableBatch = $availableBatches.length > 0;
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
    } else if ($destination.find(':selected').attr('data-split') === '1') {
        message = 'Preview will split this unused combined receiving batch across the selected lines. Total received stock will stay unchanged.';
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
    var bypass = $form.find('#authority_receipt_date_override').prop('checked') && !authority_date_change_enabled($form);
    var message = earliest ? 'Earliest permitted delivery: ' + earliest + '.' : '';
    if (conflicts.length) {
        message = conflicts.join('; ') + ', after delivery ' + effective + '. Select an earlier source receipt or enter the actual verified delivery date on or after ' + earliest + '.';
    }
    if (conflicts.length && bypass) { message = conflicts.join('; ') + '. Authority date bypass enabled: recorded delivery dates will be preserved and exceptions recorded in the preview and audit.'; }
    $form.find('.authority-date-guidance').toggleClass('text-danger', conflicts.length > 0 && !bypass).text(message);
    $form.find('.authority-use-earliest-date').toggle(Boolean(authority_date_change_enabled($form) && earliest && effective && earliest > effective)).attr('data-date', earliest);
    return conflicts.length === 0 || Boolean(bypass);
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
            if ($option.length && !$option.prop('disabled') && !authority_receiving_reserved_for_other_row($row, $option)) { $destination.val(String(completion.destination_id)); }
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
            !$destination.find('option').filter(function () { return this.value && this.value !== 'new' && $(this).attr('data-split') !== '1' && !this.disabled; }).length) {
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
    authority_refresh_generated_evidence($form, summary);
    authority_update_quick_fix_buttons($form);
    $form.find('.authority-auto-status').text(unresolved
        ? 'Filled supported records. ' + unresolved + ' line(s) still need a source receipt or receiving batch. Select those records to complete verification.'
        : 'Fields completed from existing records. Review proposed serial and date changes, then verify the correction.');
    return unresolved === 0;
}

function authority_refresh_generated_evidence($form, summary) {
    if (!summary) {
        summary = ['Authority record-based correction for ' + ($form.attr('data-reference') || 'this transfer') + '.'];
        $form.find('.authority-line').each(function () {
            var $row = $(this), source = $row.find('.authority-source').val(), destination = $row.find('.authority-destination').val();
            if (source && destination) {
                summary.push('Line #' + ($row.attr('data-line-id') || '?') + ': source receipt ' + source +
                    ', receiving batch ' + destination + ', quantity ' + $row.attr('data-quantity') + '.');
                var serial = $row.find('.authority-verified-serial:not(:disabled)').val();
                if (serial) { summary.push('Proposed missing serial ' + serial + ' from the receiving record; review before applying.'); }
            }
        });
    }
    var $evidence = $form.find('#authority_evidence');
    if (!$evidence.val() || $evidence.val() === $evidence.attr('data-generated-notes')) {
        // Browsers submit textarea newlines as CRLF, which counts as two characters
        // on the server. Keep whole generated lines and room for the summary notice.
        var limit = Number($evidence.attr('maxlength')) || 20000;
        var notice = 'Additional row details are included in the correction preview.';
        var notes = '';
        summary.forEach(function (line) {
            var candidate = notes ? notes + '\r\n' + line : line;
            if (candidate.length + notice.length + 2 <= limit) { notes = candidate; }
        });
        if (notes.split('\r\n').length < summary.length) { notes += '\r\n' + notice; }
        $evidence.val(notes).attr('data-generated-notes', $evidence.val());
    }
}

function authority_row_needs_fix($row) {
    return !$row.find('.authority-source').val() || !$row.find('.authority-destination').val() ||
        $row.hasClass('has-error') || $row.find(':input').filter(function () {
            return !this.disabled && this.checkValidity && !this.checkValidity();
        }).length > 0;
}

function authority_update_quick_fix_buttons($form) {
    var unresolved = false;
    $form.find('.authority-line').each(function () {
        var $row = $(this), needsFix = authority_row_needs_fix($row);
        var $button = $row.find('.authority-quick-fix');
        if (!$button.length) {
            $button = $('<button type="button" class="btn btn-warning btn-sm authority-quick-fix">Quick fix</button>')
                .insertAfter($row.find('.authority-selection-help'));
        }
        $button.toggle(needsFix);
        unresolved = unresolved || needsFix;
    });
    var $all = $form.find('.authority-quick-fix-next');
    if (!$all.length) {
        $all = $('<button type="button" class="btn btn-warning btn-sm authority-quick-fix authority-quick-fix-next">Quick fix next row</button>')
            .insertAfter($form.find('.authority-auto-status'));
    }
    $all.toggle(unresolved);
}

function authority_focus_fix_control($control) {
    $control.trigger('focus');
    if ($control.is('select') && $.fn.select2 && $control.data('select2')) {
        $control.select2('open');
    } else if ($control.is('select') && $control[0] && $control[0].showPicker) {
        try { $control[0].showPicker(); } catch (ignore) { /* Native focus still exposes the selector. */ }
    }
}

function authority_show_receiving_pair_choices($form, error) {
    var $error = $form.find('.authority-preview-error');
    $form.find('.authority-linked-pair-options').remove();
    var $panel = $('<div class="authority-linked-pair-options" role="group" tabindex="-1"></div>')
        .attr('aria-label', 'Verified receiving batch choices').appendTo($error);
    $('<p>').text('Choose the receiving batch in ' + error.linked_reference + ' for serial ' + error.source_serial +
        '. Confirm the batch from your receiving records. Other unknown serials will stay blank.').appendTo($panel);
    (error.candidates || []).forEach(function (candidate) {
        $('<button type="button" class="btn btn-primary btn-sm authority-linked-pair-choice" style="margin:4px"></button>')
            .text('Use batch #' + candidate.id + ' · ' + (candidate.serial || 'No serial') + ' · Quantity ' + candidate.quantity)
            .attr({'data-linked-line-id': error.linked_sell_line_id, 'data-batch-id': candidate.id,
                'data-root-batch-id': error.receiving_batch_id}).appendTo($panel);
    });
    $panel.trigger('focus');
}

function authority_clear_receiving_pair_choices($form, $row) {
    var root = $row.data('authority-linked-root');
    if (!root) { return; }
    $form.find('input.authority-linked-pair').filter(function () {
        return String($(this).attr('data-root-batch-id')) === String(root);
    }).remove();
    $row.removeData('authority-linked-root');
}

function authority_quick_fix($form, $button) {
    if ($form.data('authority-preview-busy')) { return; }
    var $row = $button.closest('.authority-line');
    var error = $form.data('authority-stock-error') || {};
    if (error.kind === 'receiving_pairing_required') { authority_show_receiving_pair_choices($form, error); return; }
    var lineId = $button.attr('data-line-id') || error.sell_line_id;
    if (!$row.length && lineId) {
        $row = $form.find('.authority-line').filter(function () { return String($(this).attr('data-line-id')) === String(lineId); }).first();
    }
    if (!$row.length) {
        $row = $form.find('.authority-line').filter(function () { return authority_row_needs_fix($(this)); }).first();
    }
    if (!$row.length) {
        var $invalid = $form.find(':input').filter(function () { return !this.disabled && this.checkValidity && !this.checkValidity(); }).first();
        if ($invalid.length) { authority_focus_fix_control($invalid); return; }
        if ($form.find('.authority-date-guidance.text-danger').length) {
            authority_focus_fix_control($form.find('#authority_date:enabled, #authority_receipt_date_override').first());
            return;
        }
        authority_check_on_open($form);
        return;
    }
    var $source = $row.find('.authority-source'), $destination = $row.find('.authority-destination');
    if (error.kind === 'receiving_balance_conflict' && String(error.sell_line_id) === String($row.attr('data-line-id'))) {
        var candidates = (error.candidate_receiving_batch_ids || []).map(String);
        authority_update_receiving($row, false);
        var $safe = $destination.find('option').filter(function () {
            return candidates.indexOf(this.value) !== -1 && !this.disabled && !authority_receiving_reserved_for_other_row($row, $(this));
        });
        authority_clear_receiving_pair_choices($form, $row);
        $destination.val($safe.length === 1 ? $safe.val() : '').trigger('change');
        authority_refresh_generated_evidence($form);
        if ($safe.length === 1) {
            authority_check_on_open($form);
        } else {
            var message = $safe.length > 1 ? 'Several receiving batches preserve the other rows. Choose the batch confirmed by your records.'
                : 'The previous batch belongs to another serial balance. Choose this line\'s receiving batch, or review the source receipt.';
            $row.addClass('has-error').find('.authority-selection-help').addClass('text-danger').text(message);
            $form.find('.authority-auto-status').text(message);
            authority_focus_fix_control($destination);
        }
        authority_update_quick_fix_buttons($form);
        return;
    }
    var completion;
    try { completion = JSON.parse($row.attr('data-completion') || '{}'); } catch (ignore) { completion = {}; }
    if (!$source.val()) {
        var $recorded = $source.find('option').filter(function () { return this.value === String(completion.source_id) && !this.disabled; });
        var $sources = $source.find('option').filter(function () { return this.value && this.value !== 'new' && $(this).attr('data-split') !== '1' && !this.disabled; });
        if ($recorded.length || $sources.length === 1) { $source.val(($recorded.length ? $recorded : $sources).val()).trigger('change'); }
        else if (!$sources.length && $source.find('option[value="new"]:enabled').length) { $source.val('new').trigger('change'); }
    }
    if (error.kind === 'receiving_serial_confirmation' && String(error.sell_line_id) === String($row.attr('data-line-id'))) {
        $row.find('.authority-change-receiving-serial').prop('checked', true).trigger('change');
    }
    if (String($source.val()) === String(completion.source_id) && completion.verified_serial && !$row.find('.authority-verified-serial').val()) {
        $row.find('.authority-verified-serial').val(completion.verified_serial);
    }
    authority_update_receiving($row, false);
    if ($source.val() && !$destination.val() && String($source.val()) === String(completion.source_id)) {
        var $proposed = $destination.find('option').filter(function () { return this.value === String(completion.destination_id) && !this.disabled; });
        if ($proposed.length && !authority_receiving_reserved_for_other_row($row, $proposed)) { $destination.val($proposed.val()); }
    }
    if ($source.val() && !$destination.val()) { authority_update_receiving($row, true); }
    if ($source.val() && !$destination.val()) {
        var $splits = $destination.find('option[data-split="1"]:enabled');
        if ($splits.length === 1) {
            var splitValue = $splits.val();
            authority_combined_receiving_rows($form, $row, splitValue).each(function () {
                $(this).find('.authority-destination').val(splitValue).trigger('change');
                $(this).removeClass('has-error').find('.authority-selection-help').removeClass('text-danger');
            });
        }
    }
    $row.removeClass('has-error').find('.authority-selection-help').removeClass('text-danger');
    authority_refresh_generated_evidence($form);
    authority_check_on_open($form);
    authority_update_quick_fix_buttons($form);
    // Incomplete or ambiguous records need a choice; never invent a receipt date or cost.
    var $control = !$source.val() ? $source : (!$destination.val() ? $destination : $row.find(':input').filter(function () {
        return !this.disabled && this.checkValidity && !this.checkValidity();
    }).first());
    if ($control.length) { authority_focus_fix_control($control); }
}

function authority_preview_error($form, xhr, applying) {
    var response = xhr.responseJSON || {};
    var errors = response.errors ? Object.keys(response.errors).map(function (key) {
        return [].concat(response.errors[key]).join(' ');
    }).join(' ') : '';
    var message = response.msg || errors || (xhr.status === 422 ? response.message : '') ||
        (xhr.status === 0 ? 'Unable to reach the server.' : 'The correction preview could not be completed.');
    $form.data('authority-stock-error', response.stock_error || {});
    var kind = (response.stock_error || {}).kind;
    var guidance = 'Check the selected source receipt and receiving batch against the SKU, quantity and supporting records, then preview again.';
    if (kind === 'receiving_pairing_required') {
        guidance = 'Use Quick fix to confirm the receiving batch for the known source serial, then recheck the linked transfer.';
    } else if (kind === 'receiving_balance_conflict') {
        guidance = 'Use Quick fix on the highlighted row to select a receiving batch that preserves the other rows. If several batches qualify, choose the one confirmed by your records.';
    } else if (kind === 'receipt_date_conflict') {
        guidance = 'Select a source receipt available on the recorded delivery date, or enable Change delivery date and enter the actual verified delivery date.';
    } else if (kind === 'receiving_serial_confirmation') {
        guidance = 'Enable Change receiving serial / lot on the highlighted row, confirm the selected source serial, then preview again. Authority forms enable this option by default.';
    } else if (kind === 'receiving_batch_dependencies') {
        guidance = 'This receiving serial is also used by connected stock movements. Keep its current serial by selecting the matching source receipt, or reconcile those linked records together before changing it.';
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
    var uncertainApply = applying && (xhr.status === 0 || xhr.status >= 500);
    if (applying) {
        guidance = uncertainApply ? 'The result of Apply could not be confirmed. Verify the current transfer records before trying again.'
            : 'Return to your selections, review the reported error and generate a fresh preview.';
    }
    var lineId = (response.stock_error || {}).sell_line_id;
    if (!lineId && (response.stock_error || {}).receiving_batch_id) {
        var batchId = response.stock_error.receiving_batch_id;
        var $affected = $form.find('.authority-line').filter(function () {
            return String($(this).find('.authority-destination').val()) === String(batchId);
        }).first();
        lineId = $affected.attr('data-line-id');
        if (lineId) { $form.data('authority-stock-error').sell_line_id = lineId; }
    }
    if (lineId) {
        $form.find('.authority-line').filter(function () { return String($(this).attr('data-line-id')) === String(lineId); })
            .addClass('has-error').find('.authority-selection-help').addClass('text-danger').text(message);
    }
    $form.find('.authority-auto-status').text(message);
    var $error = $form.find('.authority-preview-error');
    if (!$error.length) { $error = $('<div class="authority-preview-error alert alert-danger" role="alert" tabindex="-1"></div>').appendTo($form); }
    $error.empty().append($('<p>').text(message), $('<p>').text(guidance), $('<p>').text(uncertainApply
        ? 'Your preview entries are still available here.' : 'No correction was applied. Your entries are still available here.'),
        applying ? $('<button type="button" class="btn btn-default authority-return-to-records">Back to records</button>')
            : $('<button type="button" class="btn btn-warning authority-quick-fix">Quick fix</button>').attr('data-line-id', lineId || '')).show().focus();
    authority_update_quick_fix_buttons($form);
    if (kind === 'receiving_pairing_required') { authority_show_receiving_pair_choices($form, response.stock_error); }
}

function authority_request_preview($form, checkOnly) {
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
    var $buttons = $form.find(':submit, .authority-auto-complete, .authority-quick-fix').filter(':enabled');
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
        if (checkOnly) {
            $form.find('.authority-auto-status').text('Initial check passed for the selected records. Review your selections, then preview before applying.');
            return;
        }
        var $content = $form.closest('.modal').find('.modal-content');
        if ($content.length) {
            if ($form.closest('.modal').is('[data-stock-transfer-editor]')) {
                $form.closest('.modal').data('authority-editor-form', $form.detach());
            }
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

function authority_check_on_open($form) {
    var issues = [];
    $form.find('.authority-line').each(function () {
        var $row = $(this);
        var message = '';
        if (!$row.find('.authority-source').val()) { message = 'Choose the verified source receipt.'; }
        else if (!$row.find('.authority-destination').val()) {
            var candidates = $row.find('.authority-destination option').filter(function () { return this.value && this.value !== 'new' && $(this).attr('data-split') !== '1' && !this.disabled; }).length;
            message = $row.find('.authority-destination option[data-split="1"]:enabled').length
                ? 'The received quantity is stored in a combined batch. Use Quick fix to split its existing quantity across the serials.'
                : candidates > 1 ? 'Several receiving batches have this SKU and quantity. Select the batch confirmed by the receiving record.'
                : 'Choose the receiving batch for the selected source receipt, or confirm a missing receiving batch if its quantity is absent.';
        }
        else if ($row.find(':input').filter(function () { return !this.disabled && this.checkValidity && !this.checkValidity(); }).length) {
            message = 'Complete the required receipt details for this row.';
        }
        $row.toggleClass('has-error', Boolean(message));
        if (message) {
            $row.find('.authority-selection-help').addClass('text-danger').text(message);
            issues.push('Line #' + ($row.attr('data-line-id') || '?') + ': ' + message);
        }
    });
    if (!authority_update_dates($form)) { issues.push($form.find('.authority-date-guidance').text()); }
    if (issues.length) {
        authority_preview_error($form, {status: 422, responseJSON: {msg: issues.join(' ')}});
        return;
    }
    if ($form[0] && !$form[0].checkValidity()) {
        authority_preview_error($form, {status: 422, responseJSON: {msg: 'Complete the required reason and receipt details before verification.'}});
        return;
    }
    // The server checks a rollback-only preview. Keep the correction form open for editing.
    authority_request_preview($form, true);
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
    $form.on('click', '.authority-quick-fix', function () { authority_quick_fix($form, $(this)); });
    $form.on('click', '.authority-linked-pair-choice', function () {
        if ($form.data('authority-preview-busy')) { return; }
        var $button = $(this), lineId = $button.attr('data-linked-line-id'), batchId = $button.attr('data-batch-id');
        if (!/^[1-9][0-9]*$/.test(lineId) || !/^[1-9][0-9]*$/.test(batchId)) { return; }
        var name = 'linked_receiving_pairs[' + lineId + ']';
        var $input = $form.find('input.authority-linked-pair').filter(function () { return this.name === name; });
        if (!$input.length) { $input = $('<input type="hidden" class="authority-linked-pair">').attr('name', name).appendTo($form); }
        $input.val(batchId).attr('data-root-batch-id', $button.attr('data-root-batch-id'));
        $form.find('.authority-line').filter(function () {
            return String($(this).find('.authority-destination').val()) === String($button.attr('data-root-batch-id'));
        }).data('authority-linked-root', $button.attr('data-root-batch-id'));
        $form.find('.authority-linked-pair-options').remove();
        authority_check_on_open($form);
    });
    $form.on('change', '.authority-source', function () {
        authority_clear_receiving_pair_choices($form, $(this).closest('.authority-line'));
        $(this).closest('.authority-line').find('.authority-verified-serial').val('');
        authority_update_receiving($(this).closest('.authority-line'), true);
        $form.find('.authority-line').not($(this).closest('.authority-line')).each(function () {
            authority_update_receiving($(this), false);
        });
        authority_update_dates($form);
    });
    $form.on('change', '.authority-destination', function () {
        var $row = $(this).closest('.authority-line');
        if (String($row.data('authority-linked-root')) !== String($(this).val())) { authority_clear_receiving_pair_choices($form, $row); }
        $form.find('.authority-line').each(function () { authority_update_receiving($(this), false); });
    });
    $form.on('change', '.authority-change-receiving-serial', function () {
        authority_update_receiving($(this).closest('.authority-line'), false);
    });
    $form.on('input', '.authority-new-serial, .authority-verified-serial', function () {
        authority_clear_receiving_pair_choices($form, $(this).closest('.authority-line'));
        authority_update_receiving($(this).closest('.authority-line'), false);
    });
    $form.on('change', '#authority_change_date', function () {
        var enabled = authority_date_change_enabled($form);
        $form.find('#authority_date').prop('disabled', !enabled).prop('required', enabled);
        if (enabled) { $form.find('#authority_receipt_date_override').prop('checked', false); }
        if (!enabled) {
            $form.find('#authority_date').val('').removeAttr('data-proposed-date');
            $form.find('#authority_date_basis').val('');
        }
        authority_update_dates($form);
    });
    $form.on('change', '#authority_receipt_date_override', function () {
        if ($(this).prop('checked')) { $form.find('#authority_change_date').prop('checked', false).trigger('change'); }
        authority_update_dates($form);
        $form.find('.authority-preview-error').hide();
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
    $form.on('input change', ':input', function () {
        $form.removeData('authority-stock-error');
        $(this).closest('.authority-line').removeClass('has-error').find('.authority-selection-help').removeClass('text-danger');
        authority_update_quick_fix_buttons($form);
        var $error = $form.find('.authority-preview-error');
        if ($error.length && $error.css('display') !== 'none') {
            $error.hide();
            $form.find('.authority-auto-status').text('Selections changed. Preview again to check the updated records.');
        }
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
    if ($form.attr('data-check-on-open') === '1') { authority_check_on_open($form); }
}

$(function () {
    $(document).on('click', '#authority_correction_modal[data-stock-transfer-editor] .authority-return-to-records', function (event) {
        var $modal = $(this).closest('.modal'), $saved = $modal.data('authority-editor-form');
        if (!$saved || !$saved.length) { return; }
        event.preventDefault();
        if ($modal.find('.authority-apply-form').data('authority-apply-busy')) { return; }
        $modal.removeData('authority-editor-form');
        $modal.find('.modal-content').html('<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button><h4 class="modal-title">Full transfer repair — review records</h4></div><div class="modal-body"></div>');
        $modal.find('.modal-body').append($saved);
        $saved.find('.authority-preview-error').hide();
        $saved.find('.authority-auto-status').text('Your selections and notes were retained. Review the records, then preview again.');
    });
    $(document).on('submit', '#authority_correction_modal[data-stock-transfer-editor] .authority-apply-form', function (event) {
        event.preventDefault();
        var $form = $(this);
        if ($form.data('authority-apply-busy')) { return; }
        var payload = $form.serialize();
        var $buttons = $form.find(':submit:enabled');
        $form.data('authority-apply-busy', true);
        $buttons.prop('disabled', true);
        $form.find('.authority-preview-error').hide();
        $.ajax({url: $form.attr('action'), type: 'POST', data: payload, dataType: 'json', headers: {Accept: 'application/json'}})
            .done(function (result) {
                if (!result.success || !result.plan) {
                    authority_preview_error($form, {status: 422, responseJSON: result}, true);
                    return;
                }
                var $modal = $form.closest('.modal'), $saved = $modal.data('authority-editor-form');
                if ($saved) { $saved.remove(); $modal.removeData('authority-editor-form'); }
                $(document).trigger('stock-authority:applied', [result]);
            }).fail(function (xhr) { authority_preview_error($form, xhr, true); })
            .always(function () {
                $form.removeData('authority-apply-busy');
                $buttons.prop('disabled', false);
            });
    });
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
        var $saved = $modal.data('authority-editor-form');
        if ($saved) { $saved.remove(); $modal.removeData('authority-editor-form'); }
        $modal.find('.modal-content').html('<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button><h4 class="modal-title">Authority correction</h4></div><div class="modal-body" role="status">Loading correction records…</div>');
        $modal.modal('show');
        $.ajax({url: this.href, data: {modal: 1}, dataType: 'html'}).done(function (html) {
            if (version !== loadVersion) { return; }
            $modal.find('.modal-content').html(html);
            authority_initialize_form();
        }).fail(function (xhr) {
            if (version !== loadVersion) { return; }
            var response = xhr.responseJSON || {};
            var message = xhr.status === 422 ? (response.msg || response.message) : '';
            if (xhr.status === 403) { message = 'You do not have permission to open this authority correction. Ask an administrator to check your location access and authority permission.'; }
            $modal.find('.modal-body').attr('role', 'alert').text(message || 'Unable to load correction records. Retry opening this transfer. If it fails again, ask the administrator to check the server log.');
        });
    });
});
