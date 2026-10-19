(function($) {
    'use strict';
    var plan = null;
    var busy = false;
    var manualPlan = null;
    var manualPayload = null;
    var autoCompletePreview = false;
    var $generate = $('#generate_stock_correction_plan');
    var $modal = $('#stock_correction_plan_modal');

    function refreshCorrectedRows(result) {
        var changes = (result.changes || []).concat(result.related_allocation_repairs || []);
        var ids = changes.map(function(change) { return String(change.sell_line_id); });
        var $rows = $('[data-stock-transfer-line-id]').filter(function() {
            return ids.indexOf($(this).attr('data-stock-transfer-line-id')) !== -1;
        });
        var $status = $('#auto_stock_correction_result').prop('hidden', false)
            .removeClass('text-danger').addClass('text-success').text(result.msg || 'Correction saved and verified.');
        if (!$rows.length) { return; }
        var url = $generate.attr('data-refresh-url');
        if (!url) {
            $status.removeClass('text-success').addClass('text-danger').text('Correction saved. Reopen the document to see the updated rows.');
            return;
        }
        $.ajax({method: 'GET', url: url, data: {refresh_line_ids: ids}, dataType: 'html', cache: false}).done(function(html) {
            // Keep scripts inert and preserve edits in every unaffected row and header field.
            var $fresh = $('<div>').append($.parseHTML(html, document, false));
            var missing = false;
            $rows.each(function() {
                var $old = $(this);
                var id = $old.attr('data-stock-transfer-line-id');
                var $replacement = $fresh.find('[data-stock-transfer-line-id]').filter(function() {
                    return $(this).attr('data-stock-transfer-line-id') === id;
                }).first();
                if (!$replacement.length) { missing = true; return; }
                var name = $old.find('[name]').first().attr('name') || '';
                var prefix = name.match(/^products\[\d+\]/);
                if (prefix) {
                    $replacement.find('[name]').each(function() {
                        $(this).attr('name', $(this).attr('name').replace(/^products\[\d+\]/, prefix[0]));
                    });
                }
                $old.replaceWith($replacement);
                if (typeof __currency_convert_recursively === 'function') { __currency_convert_recursively($replacement); }
            });
            if (missing) {
                $status.removeClass('text-success').addClass('text-danger').text('Correction saved. Some updated rows could not be refreshed; reopen the document to verify them.');
            }
        }).fail(function() {
            $status.removeClass('text-success').addClass('text-danger').text('Correction saved, but the updated rows could not be loaded. Reopen the document to verify them.');
        });
    }

    function errorMessage(xhr) {
        var result = xhr.responseJSON || {};
        if (xhr.status === 419) { return 'Your session expired. Reload the page and try again.'; }
        if (xhr.status === 0) { return 'Connection interrupted. Reload to verify the transfer before retrying.'; }
        if (result.errors) {
            return Object.keys(result.errors).map(function(key) { return result.errors[key].join(' '); }).join(' ');
        }
        return result.msg || result.message || 'Unable to process the correction plan. Try again.';
    }

    function renderManualError(xhr) {
        var $error = $('#manual_stock_correction_error').empty();
        $('<p>').text(errorMessage(xhr)).appendTo($error);
        var detail = (xhr.responseJSON || {}).stock_error;
        if (detail && detail.kind === 'related_allocation') {
            $('<p>').text('Original document: ' + detail.reference + ' / line #' + detail.sell_line_id
                + ' / ' + detail.date + ' / status ' + detail.status).appendTo($error);
            $('<p>').text('Review this document’s source allocations and return quantities, then generate a new preview.').appendTo($error);
            var target;
            try { target = detail.url ? new URL(detail.url, window.location.href) : null; } catch (error) { target = null; }
            if (target && target.origin === window.location.origin && /^https?:$/.test(target.protocol)) {
                $('<a>').addClass('btn btn-default btn-sm').text('Review original document')
                    .attr({href: target.href, target: '_blank', rel: 'noopener noreferrer'}).appendTo($error);
            }
            return;
        }
        if (!detail || detail.kind !== 'transfer_date_dependencies') { return; }
        $('<p>').text('Recorded delivery: ' + detail.recorded_date).appendTo($error);
        var $steps = $('<ol>').appendTo($error);
        (detail.steps || []).forEach(function(step) { $('<li>').text(step).appendTo($steps); });
        var $table = $('<table>').addClass('table table-bordered table-condensed');
        var $head = $('<tr>').appendTo($('<thead>').appendTo($table));
        ['Reference', 'Date', 'Serial', 'Status', 'Quantity'].forEach(function(label) { $('<th>').text(label).appendTo($head); });
        var $body = $('<tbody>').appendTo($table);
        (detail.dependent_movements || []).forEach(function(movement) {
            var $row = $('<tr>').appendTo($body);
            [movement.reference, movement.date, (movement.serials || []).join(', '), movement.status, movement.quantity]
                .forEach(function(value) { $('<td>').text(value).appendTo($row); });
        });
        if ($body.children().length) {
            $('<div>').addClass('table-responsive').append($table).appendTo($error);
        } else {
            $('<p>').text('Usage counters or unmatched references still protect this receipt. Review the receiving batches before changing its date.').appendTo($error);
        }
        var $batches = $('<ul>').appendTo($error);
        (detail.receiving_batches || []).forEach(function(batch) {
            if (batch.sold || batch.adjusted || batch.returned || batch.manufacturing_used) {
                $('<li>').text('Batch #' + batch.id + ' / ' + (batch.serial || '--') + ': sold ' + batch.sold
                    + ', adjusted ' + batch.adjusted + ', returned ' + batch.returned + ', manufacturing used ' + batch.manufacturing_used).appendTo($batches);
            }
        });
        (detail.links || []).forEach(function(link) {
            var target;
            try { target = new URL(link.url, window.location.href); } catch (error) { return; }
            if (target.origin !== window.location.origin || !/^https?:$/.test(target.protocol)) { return; }
            $('<a>').addClass('btn btn-default btn-sm').attr({href: target.href, target: '_blank', rel: 'noopener noreferrer'})
                .css({marginRight: '8px', marginBottom: '8px', whiteSpace: 'normal'})
                .append($('<i>').addClass('fa fa-external-link').attr('aria-hidden', 'true'), document.createTextNode(' ' + link.label)).appendTo($error);
        });
    }

    function renderPlan() {
        var ready = plan.status === 'ready';
        $('#stock_correction_plan_status').text(plan.reference + ': ' + (ready
            ? 'Verified counter repairs available. No stock records have been changed.'
            : plan.status === 'consistent' ? 'No correction is needed.'
            : 'Physical verification required. Automatic correction is blocked. No stock records have been changed.'));
        $('#stock_correction_plan_error').empty();
        var $blockers = $('#stock_correction_plan_blockers').empty();
        plan.blockers.forEach(function(reason) { $('<li>').text(reason).appendTo($blockers); });
        var $rows = $('#stock_correction_plan_repairs tbody').empty();
        plan.counter_repairs.forEach(function(repair) {
            var $row = $('<tr>').appendTo($rows);
            [repair.purchase_line_id, repair.serial, repair.quantity_sold_before, repair.quantity_sold_after].forEach(function(value) {
                $('<td>').text(value).appendTo($row);
            });
        });
        $('#stock_correction_plan_repairs').toggle(ready);
        var $steps = $('#stock_correction_plan_steps').empty();
        plan.next_steps.forEach(function(step) { $('<li>').text(step).appendTo($steps); });
        $('#stock_correction_confirmation').toggle(ready);
        $('#confirm_stock_correction').prop('checked', false);
        $('#apply_stock_correction_plan').prop('disabled', true).toggle(ready);
        $('#download_stock_correction_plan').prop('disabled', false);
        $('#open_manual_stock_correction').toggle(plan.status === 'verification_required');
        $modal.modal('show');
    }

    $generate.on('click', function() {
        if (busy) { return; }
        busy = true;
        $generate.prop('disabled', true);
        $.ajax({method: 'POST', url: $generate.attr('data-url'), dataType: 'json', data: {_token: $('meta[name="csrf-token"]').attr('content')}})
            .done(function(result) { plan = result.plan; renderPlan(); })
            .fail(function(xhr) { toastr.error(errorMessage(xhr)); })
            .always(function() { busy = false; $generate.prop('disabled', false); });
    });

    $('#auto_correct_stock').on('click', function() {
        if (busy) { return; }
        busy = true;
        var $button = $(this);
        var label = $button.html();
        var $result = $('#auto_stock_correction_result').empty().prop('hidden', false).text('Checking stock records...');
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Checking Stock');
        $generate.prop('disabled', true);
        $.ajax({method: 'POST', url: $button.attr('data-url'), dataType: 'json', data: {
            _token: $('meta[name="csrf-token"]').attr('content')
        }})
            .done(function(result) {
                resetManualPreview();
                $result.empty();
                if (result.complete) {
                    $result.removeClass('text-danger').addClass('text-success').text(result.msg || 'Stock records are consistent. No correction is needed.');
                    if (result.applied) { refreshCorrectedRows(result); }
                } else {
                    $result.removeClass('text-success').addClass('text-danger');
                    $('<strong>').text('Automatic correction needs verified records. No stock was changed.').appendTo($result);
                    var $reasons = $('<ul>').appendTo($result);
                    (result.plan.blockers || []).forEach(function(reason) { $('<li>').text(reason).appendTo($reasons); });
                    var reviewItems = result.plan.review_items || [];
                    if (reviewItems.length) {
                        var $table = $('<table>').addClass('table table-bordered table-condensed');
                        var $head = $('<tr>').appendTo($('<thead>').appendTo($table));
                        ['Document / Batch', 'Line', 'Date', 'Status', 'Issue', 'Action'].forEach(function(label) { $('<th>').text(label).appendTo($head); });
                        var $body = $('<tbody>').appendTo($table);
                        reviewItems.forEach(function(item) {
                            var $row = $('<tr>').appendTo($body);
                            [item.reference, item.line_id || '--', item.date || '--', item.status, (item.reasons || []).join(' ')]
                                .forEach(function(value) { $('<td>').text(value).appendTo($row); });
                            var $action = $('<td>').appendTo($row);
                            var target;
                            try { target = item.url ? new URL(item.url, window.location.href) : null; } catch (error) { target = null; }
                            if (target && target.origin === window.location.origin && /^https?:$/.test(target.protocol)) {
                                $('<a>').addClass('btn btn-default btn-sm').attr({href: target.href, target: '_blank', rel: 'noopener noreferrer'})
                                    .append($('<i>').addClass('fa fa-external-link').attr('aria-hidden', 'true'),
                                        document.createTextNode(item.line_id ? ' Review This Line' : ' Review Record')).appendTo($action);
                            } else { $action.text('Authorized review required'); }
                        });
                        $('<div>').addClass('table-responsive').append($table).appendTo($result);
                    }
                    $('<p>').text('Review the linked records against physical serials and delivery documents, then retry Auto Correct. Unrelated documents cannot be corrected from this transfer.').appendTo($result);
                }
            })
            .fail(function(xhr) { $result.removeClass('text-success').addClass('text-danger').text(errorMessage(xhr)); })
            .always(function() {
                busy = false;
                $button.prop('disabled', false).html(label);
                $generate.prop('disabled', false);
            });
    });

    $('#confirm_stock_correction').on('change', function() {
        $('#apply_stock_correction_plan').prop('disabled', busy || !plan || plan.status !== 'ready' || !this.checked);
    });

    $('#apply_stock_correction_plan').on('click', function() {
        if (busy || !plan || plan.status !== 'ready' || !$('#confirm_stock_correction').prop('checked')) { return; }
        busy = true;
        var $button = $(this).prop('disabled', true);
        $('#confirm_stock_correction, #download_stock_correction_plan').prop('disabled', true);
        $.ajax({method: 'POST', url: $generate.attr('data-apply-url'), dataType: 'json', data: {
            _token: $('meta[name="csrf-token"]').attr('content'), token: plan.token, confirmed: 1
        }})
            .done(function(result) { plan = null; $modal.modal('hide'); refreshCorrectedRows(result); })
            .fail(function(xhr) {
                $('#stock_correction_plan_error').text(errorMessage(xhr));
                plan.status = 'stale';
                $('#confirm_stock_correction').prop('checked', false);
                $button.prop('disabled', true);
            })
            .always(function() { busy = false; $('#confirm_stock_correction, #download_stock_correction_plan').prop('disabled', false); });
    });

    $('#download_stock_correction_plan').on('click', function() {
        if (!plan) { return; }
        var url = URL.createObjectURL(new Blob([JSON.stringify(plan, null, 2)], {type: 'application/json'}));
        var link = document.createElement('a');
        link.href = url;
        link.download = 'stock-correction-' + plan.transfer_id + '.json';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
    });

    var $manualModal = $('#manual_stock_correction_modal');
    var $manualForm = $('#manual_stock_correction_form');
    var $manualDate = $('#manual_stock_corrected_date');
    var lastDateBound = null;
    function fillRecordedDelivery(date) {
        if (!date) { return; }
        var picker = $manualDate.data('DateTimePicker');
        if (picker && typeof moment === 'function') {
            picker.minDate(false);
            picker.date(moment(date, 'YYYY-MM-DD HH:mm:ss', true));
        } else {
            $manualDate.val(picker ? date : date.replace(' ', 'T'));
        }
    }

    function fillCorrectionNotes() {
        var $notes = $('#manual_stock_correction_evidence');
        var previous = $notes.data('default-text');
        if ($notes.val().trim() && $notes.val() !== previous) { return; }
        var records = [];
        $('#manual_stock_correction_lines .manual-correction-row').each(function() {
            var $row = $(this);
            if (!$row.find('.manual-line-enabled').prop('checked')) { return; }
            var source = $row.find('.manual-source option:selected');
            var destination = $row.find('.manual-destination option:selected');
            if (!source.val() || !destination.val()) { return; }
            records.push('Line #' + $row.attr('data-line-id') + ': source batch #' + source.val()
                + ' (' + (source.attr('data-serial') || '--') + '), receiving batch #' + destination.val()
                + ' (' + (destination.attr('data-serial') || '--') + ').');
        });
        var draft = records.length ? ($manualForm.data('reference') || 'Transfer') + '. ' + records.join(' ')
            + ' Allocation correction based on these recorded source and receiving records.' : '';
        $notes.val(draft).data('default-text', draft);
    }

    if ($.fn.datetimepicker) {
        $manualDate.datetimepicker({
            format: (typeof moment_date_format === 'string' ? moment_date_format : 'YYYY-MM-DD') + ' '
                + (typeof moment_time_format === 'string' ? moment_time_format : 'HH:mm:ss'),
            ignoreReadonly: true, useCurrent: false, showClear: true, showClose: true,
            widgetParent: $manualDate.closest('.manual-date-control')
        });
        $manualDate.on('dp.change', function() { $('#manual_stock_correction_error').empty(); resetManualPreview(); updateManualRows(); });
    } else {
        $manualDate.attr('type', 'datetime-local').prop('readonly', false);
    }

    function showManualDatePicker() {
        if (busy || !$('#manual_stock_date_verified').prop('checked')) { return; }
        var picker = $manualDate.data('DateTimePicker');
        if (picker) {
            picker.show();
        } else if (typeof $manualDate[0].showPicker === 'function') {
            $manualDate[0].showPicker();
        } else {
            $manualDate.trigger('focus');
        }
    }
    $('#choose_manual_stock_date').on('click', showManualDatePicker);
    $manualDate.on('click', showManualDatePicker);

    function updateManualDateBound() {
        var bound = '';
        $('#manual_stock_correction_lines .manual-correction-row').each(function() {
            var $row = $(this);
            if (!$row.find('.manual-line-enabled').prop('checked')) { return; }
            var dates = [$row.data('date-floor'), $row.find('.manual-source option:selected').attr('data-receipt-date')];
            dates.forEach(function(date) {
                if (!date) { return; }
                date = String(date);
                if (date.length === 10) { date += ' 00:00:00'; }
                if (date > bound) { bound = date; }
            });
        });
        $('#manual_stock_date_bounds').text(bound ? 'Earliest permitted delivery: ' + bound + '. This is a limit, not proof of the actual delivery date.' : 'Choose the date supported by delivery records.');
        if (lastDateBound === bound) { return; }
        lastDateBound = bound;
        var picker = $manualDate.data('DateTimePicker');
        if (picker) {
            var selected = picker.date();
            if (bound && selected && selected.isValid() && selected.format('YYYY-MM-DD HH:mm:ss') < bound) { picker.clear(); }
            picker.minDate(bound ? (typeof moment === 'function' ? moment(bound, 'YYYY-MM-DD HH:mm:ss', true) : bound) : false);
        } else {
            $manualDate.attr('min', bound.replace(' ', 'T'));
        }
    }

    function updateManualRows() {
        var $rows = $('#manual_stock_correction_lines .manual-correction-row');
        var matching = $rows.filter('.manual-records-agree').length;
        var review = $rows.length - matching;
        var selected = $rows.find('.manual-line-enabled:checked').length;
        $('#manual_stock_correction_summary').text(review + ' lines need review. ' + selected + ' selected for correction.');
        $('#manual_matching_lines_toggle').toggle(matching > 0 && review > 0);
        $rows.filter('.manual-records-agree').each(function() {
            $(this).toggle(review === 0 || $('#show_matching_stock_lines').prop('checked') || $(this).find('.manual-line-enabled').prop('checked'));
        });
        $rows.each(function() {
            var $row = $(this);
            var selected = $row.find('.manual-line-enabled').prop('checked');
            // Select2 hides its native select, so validate it through the visible editor.
            $row.find('select').prop('required', false).attr('aria-required', selected ? 'true' : 'false');
            $row.next('.manual-evidence-row').toggle($row.css('display') !== 'none' && !!$row.data('evidence-open'));
        });
        updateManualDateBound();
        if (!busy) {
            $('#manual_stock_correction_stage').text(manualPlan ? 'Preview Ready' : (selected ? 'Awaiting Verification' : 'Awaiting Selection'));
        }
    }

    function resetManualPreview() {
        manualPlan = null;
        manualPayload = null;
        $('#manual_stock_correction_preview').hide();
        $('#confirm_manual_stock_correction').prop('checked', false);
        $('#apply_manual_stock_correction').prop('disabled', true);
        $('#download_manual_stock_correction').prop('disabled', true);
    }

    function manualInput() {
        var corrections = [];
        $('#manual_stock_correction_lines .manual-correction-row').each(function() {
            var $row = $(this);
            if ($row.find('.manual-line-enabled').prop('checked')) {
                corrections.push({sell_line_id: $row.attr('data-line-id'), source_id: $row.find('.manual-source').val(), destination_id: $row.find('.manual-destination').val()});
            }
        });
        var payload = {_token: $('meta[name="csrf-token"]').attr('content'), corrections: corrections,
            evidence: $('#manual_stock_correction_evidence').val(), verified: $('#manual_stock_correction_verified').prop('checked') ? 1 : 0};
        if ($('#manual_stock_date_verified').prop('checked')) {
            var picker = $manualDate.data('DateTimePicker');
            var selectedDate = picker && picker.date();
            var date = $manualDate.val().replace('T', ' ');
            payload.transaction_date = picker ? (selectedDate && selectedDate.isValid() ? selectedDate.format('YYYY-MM-DD HH:mm:ss') : '')
                : (date.length === 16 ? date + ':00' : date);
            payload.date_verified = 1;
        }
        return payload;
    }

    function setManualBusy(value, stage) {
        busy = value;
        $manualForm.find(':input').prop('disabled', value);
        if (!value) {
            $('#manual_stock_corrected_date').prop('disabled', !$('#manual_stock_date_verified').prop('checked'));
            updateManualRows();
        }
        $('#choose_manual_stock_date').prop('disabled', value || !$('#manual_stock_date_verified').prop('checked'));
        if (value && $manualDate.data('DateTimePicker')) { $manualDate.data('DateTimePicker').hide(); }
        $manualForm.find('.manual-use-candidate').each(function() {
            $(this).prop('disabled', value || $(this).attr('data-selectable') !== '1');
        });
        $('#auto_complete_manual_stock_correction, #preview_manual_stock_correction, #open_manual_stock_correction, #edit_verified_stock_correction, #show_matching_stock_lines, .correct-stock-line').prop('disabled', value);
        $('#confirm_manual_stock_correction').prop('disabled', value);
        $('#apply_manual_stock_correction').prop('disabled', value || !manualPlan || !$('#confirm_manual_stock_correction').prop('checked'));
        $('#download_manual_stock_correction').prop('disabled', value || !manualPlan);
        $('#open_manual_stock_correction, #edit_verified_stock_correction, .correct-stock-line').find('i').attr('class', value ? 'fa fa-spinner fa-spin' : 'fa fa-edit');
        if (value) { $('#manual_stock_correction_stage').text(stage || 'Loading Records'); }
    }

    $('#open_manual_stock_correction, #edit_verified_stock_correction, .correct-stock-line').on('click', function() {
        if (busy) { return; }
        var requestedLineId = $(this).attr('data-line-id');
        setManualBusy(true);
        $.ajax({method: 'POST', url: $generate.attr('data-options-url'), dataType: 'json', data: {_token: $('meta[name="csrf-token"]').attr('content')}})
            .done(function(result) {
                if (requestedLineId) {
                    result.lines = result.lines.filter(function(line) { return String(line.sell_line_id) === requestedLineId; });
                    if (!result.lines.length) {
                        toastr.error('This transfer line changed or was removed. Reload the page to review the current errors.');
                        return;
                    }
                }
                resetManualPreview();
                $manualForm[0].reset();
                $manualForm.data('reference', result.reference || 'Transfer');
                $('#manual_stock_correction_evidence').removeData('default-text');
                if ($manualDate.data('DateTimePicker')) { $manualDate.data('DateTimePicker').clear(); }
                $('#show_matching_stock_lines').prop('checked', false);
                $('#manual_stock_correction_error').empty();
                $('#manual_stock_overselling_badge').toggle(!!result.allow_overselling);
                $('#manual_stock_correction_context').text((result.reference || 'Transfer') + (requestedLineId ? ' | Line #' + requestedLineId : '')
                    + ' | Recorded delivery: ' + (result.transaction_date || '--'));
                var $rows = $('#manual_stock_correction_lines');
                if ($.fn.select2) {
                    $rows.find('select.select2-hidden-accessible').select2('destroy');
                }
                $rows.empty();
                result.lines.sort(function(a, b) { return Number(!!(a.recommendation && a.recommendation.prefill)) - Number(!!(b.recommendation && b.recommendation.prefill)); });
                result.lines.forEach(function(line) {
                    var $row = $('<tr>').addClass('manual-correction-row').attr('data-line-id', line.sell_line_id).appendTo($rows);
                    $row.data('date-floor', line.unchanged_lines_date_floor || '');
                    var $serial = $('<td>').attr('data-label', 'Serial').appendTo($row);
                    $('<label>').addClass('manual-line-label').append($('<input>').attr({type: 'checkbox', 'class': 'manual-line-enabled'}),
                        document.createTextNode('Correct line #' + line.sell_line_id)).appendTo($serial);
                    $('<strong>').addClass('manual-serial').text(line.selected_serial || 'Serial not recorded').appendTo($serial);
                    $('<span>').addClass('manual-line-meta').text('Quantity ' + line.quantity).appendTo($serial);
                    var $source = $('<select>').addClass('form-control manual-source').attr('aria-label', 'Verified source for line ' + line.sell_line_id);
                    $('<option>').val('').text('Select verified source receipt').appendTo($source);
                    line.sources.forEach(function(source) {
                        $('<option>').val(source.id).attr({'data-receipt-date': source.receipt_date, 'data-serial': source.lot_number || ''})
                            .text((source.lot_number || 'No serial') + ' · #' + source.id + ' · ' + source.receipt_date + ' · Available ' + Number(source.available)).appendTo($source);
                    });
                    $('<td>').attr('data-label', 'Source receipt').append($source).appendTo($row);
                    var $destination = $('<select>').addClass('form-control manual-destination').attr('aria-label', 'Receiving batch for line ' + line.sell_line_id);
                    $('<option>').val('').text('Select receiving batch').appendTo($destination);
                    line.destinations.forEach(function(destination) {
                        $('<option>').val(destination.id).attr('data-serial', destination.lot_number || '').text((destination.lot_number || 'No serial') + ' · #' + destination.id + ' · Quantity ' + Number(destination.quantity)).appendTo($destination);
                    });
                    $('<td>').attr('data-label', 'Receiving batch').append($destination).appendTo($row);
                    var recommendation = line.recommendation;
                    $row.data('automatic-match', recommendation ? recommendation.automatic_match : null);
                    $row.data('candidates', recommendation ? recommendation.candidates : []);
                    if (recommendation) {
                        var $review = $('<td>').attr('data-label', 'Review').appendTo($row);
                        $('<strong>').addClass(recommendation.prefill ? 'text-success' : 'text-warning')
                            .text(recommendation.prefill ? 'Records Match' : 'Review Required').appendTo($review);
                        $('<p>').addClass('text-muted').text(recommendation.candidates.length + ' record-supported candidates').appendTo($review);
                        $('<button>').attr({type: 'button', 'class': 'btn btn-default btn-sm manual-details-toggle',
                            'aria-expanded': 'false', title: 'Show supporting evidence'})
                            .append($('<i>').addClass('fa fa-chevron-down').attr('aria-hidden', 'true'), document.createTextNode(' Details')).appendTo($review);
                        var $detail = $('<tr>').addClass('manual-evidence-row').attr('data-owner-line', line.sell_line_id).hide().appendTo($rows);
                        var $detailBody = $('<td>').attr('colspan', 4).appendTo($detail);
                        $('<p>').text(recommendation.message).appendTo($detailBody);
                        var $candidateColumns = $('<div>').addClass('row').appendTo($detailBody);
                        recommendation.candidates.forEach(function(candidate) {
                            var $column = $('<div>').addClass(recommendation.candidates.length > 1 ? 'col-sm-6' : 'col-sm-12')
                                .css('marginBottom', '12px').appendTo($candidateColumns);
                            var $candidate = $('<div>').addClass('manual-candidate').appendTo($column);
                            $('<strong>').text('#' + candidate.source_id + ' ' + candidate.source_serial + ' -> receiving #' + candidate.destination_id).appendTo($candidate);
                            $('<p>').text(candidate.support.join('; ')).appendTo($candidate);
                            var $issues = $('<ul>').appendTo($candidate);
                            candidate.issues.forEach(function(issue) { $('<li>').text(issue).appendTo($issues); });
                            if (candidate.selectable) {
                                $('<button>').attr({type: 'button', 'class': 'btn btn-default btn-sm manual-use-candidate',
                                    'data-source-id': candidate.source_id, 'data-destination-id': candidate.destination_id, 'data-selectable': '1'})
                                    .append($('<i>').addClass('fa fa-arrow-right').attr('aria-hidden', 'true'), document.createTextNode(' Use ' + candidate.source_serial))
                                    .appendTo($candidate);
                            } else {
                                $('<p>').addClass('text-danger').text('Unavailable: ' + (candidate.unavailable_reason || 'Verify the related stock records.')).appendTo($candidate);
                            }
                        });
                        if (recommendation.prefill) {
                            $row.addClass('manual-records-agree');
                            $source.val(recommendation.source_id);
                            $destination.val(recommendation.destination_id);
                        }
                    } else {
                        $('<td>').attr('data-label', 'Review').text('No recommendation available.').appendTo($row);
                    }
                    if (!$source.val()) {
                        var recorded = line.sources.filter(function(source) { return String(source.id) === String(line.selected_purchase_line_id); });
                        var sameSerial = line.sources.filter(function(source) { return source.lot_number === line.selected_serial; });
                        var defaultSource = recorded[0] || (sameSerial.length === 1 ? sameSerial[0] : null)
                            || (line.sources.length === 1 ? line.sources[0] : null);
                        if (defaultSource) { $source.val(defaultSource.id); }
                    }
                    if (recommendation && recommendation.automatic_match) {
                        $source.val(recommendation.automatic_match.source_id);
                        $destination.val(recommendation.automatic_match.destination_id);
                    }
                    if (!$destination.val() && $source.val()) {
                        var serial = $source.find(':selected').attr('data-serial');
                        var matches = line.destinations.filter(function(destination) {
                            return destination.lot_number === serial && Number(destination.quantity) === Number(line.quantity);
                        });
                        var defaultDestination = matches.length === 1 ? matches[0] : (line.destinations.length === 1 ? line.destinations[0] : null);
                        if (defaultDestination) { $destination.val(defaultDestination.id); }
                    }
                });
                if ($.fn.select2) {
                    $rows.find('select').select2({width: '100%', dropdownParent: $manualModal});
                }
                if (requestedLineId) {
                    $rows.find('.manual-line-enabled').prop('checked', true).trigger('change');
                } else {
                    var $firstReview = $rows.find('.manual-correction-row').not('.manual-records-agree').first();
                    if (!$firstReview.length) { $firstReview = $rows.find('.manual-correction-row').first(); }
                    $firstReview.find('.manual-line-enabled').prop('checked', true).trigger('change');
                }
                fillCorrectionNotes();
                fillRecordedDelivery(result.transaction_date);
                if ($modal.hasClass('in')) {
                    $modal.one('hidden.bs.modal', function() { $manualModal.modal('show'); }).modal('hide');
                } else {
                    $manualModal.modal('show');
                }
            })
            .fail(function(xhr) { $('#stock_correction_plan_error').text(errorMessage(xhr)); })
            .always(function() { setManualBusy(false); });
    });

    $manualForm.on('submit', function(event) { event.preventDefault(); });
    $('#show_matching_stock_lines').on('change', updateManualRows);
    $manualForm.on('click', '.manual-details-toggle', function() {
        var $row = $(this).closest('.manual-correction-row');
        var open = !$row.data('evidence-open');
        $row.data('evidence-open', open);
        $(this).attr('aria-expanded', String(open)).find('i').attr('class', open ? 'fa fa-chevron-up' : 'fa fa-chevron-down');
        updateManualRows();
    });
    $manualForm.on('click', '.manual-use-candidate', function() {
        if (busy || $(this).attr('data-selectable') !== '1') { return; }
        var lineId = $(this).closest('.manual-evidence-row').attr('data-owner-line');
        var $row = $('#manual_stock_correction_lines .manual-correction-row').filter(function() { return $(this).attr('data-line-id') === lineId; });
        $row.find('.manual-line-enabled').prop('checked', true).trigger('change');
        $row.find('.manual-source').val($(this).attr('data-source-id')).trigger('change');
        $row.find('.manual-destination').val($(this).attr('data-destination-id')).trigger('change');
    });
    $manualForm.on('input change', ':input', function() {
        $('#manual_stock_correction_error').empty();
        if ($(this).hasClass('manual-line-enabled') && this.checked) {
            $('#manual_stock_correction_lines .manual-line-enabled').not(this).prop('checked', false);
            $('#manual_stock_correction_verified').prop('checked', false);
        }
        if ($(this).is('.manual-source, .manual-destination') && $(this).val()) {
            var $row = $(this).closest('tr');
            $row.data('user-selected-records', true);
            $row.find('.manual-line-enabled').prop('checked', true);
            $('#manual_stock_correction_lines .manual-line-enabled').not($row.find('.manual-line-enabled')).prop('checked', false);
            $('#manual_stock_correction_verified').prop('checked', false);
            var candidates = ($row.data('candidates') || []).filter(function(candidate) {
                return candidate.selectable && ($(this).hasClass('manual-source')
                    ? String(candidate.source_id) === String($(this).val())
                    : String(candidate.destination_id) === String($(this).val()));
            }, this);
            if (candidates.length === 1) {
                var $other = $row.find($(this).hasClass('manual-source') ? '.manual-destination' : '.manual-source');
                if (!$other.val()) {
                    $other.val($(this).hasClass('manual-source') ? candidates[0].destination_id : candidates[0].source_id).trigger('change.select2');
                }
            }
        }
        if (this.id === 'manual_stock_date_verified') {
            $('#manual_stock_corrected_date').prop('disabled', !this.checked).prop('required', this.checked);
            $('#choose_manual_stock_date').prop('disabled', !this.checked);
            if (this.checked) { showManualDatePicker(); }
            else if ($manualDate.data('DateTimePicker')) { $manualDate.data('DateTimePicker').hide(); }
        }
        if ($(this).is('.manual-source, .manual-destination, .manual-line-enabled')) { fillCorrectionNotes(); }
        resetManualPreview();
        updateManualRows();
    });

    $('#auto_complete_manual_stock_correction').on('click', function() {
        if (busy) { return; }
        var unresolved = false;
        $('#manual_stock_correction_lines .manual-correction-row').each(function() {
            var $row = $(this);
            if (!$row.find('.manual-line-enabled').prop('checked')) { return; }
            var match = $row.data('automatic-match');
            var source = $row.find('.manual-source').val();
            var destination = $row.find('.manual-destination').val();
            if (match && (!source || String(source) === String(match.source_id))
                && (!destination || String(destination) === String(match.destination_id))) {
                $row.find('.manual-source').val(match.source_id).trigger('change.select2');
                $row.find('.manual-destination').val(match.destination_id).trigger('change.select2');
            }
            source = $row.find('.manual-source').val();
            destination = $row.find('.manual-destination').val();
            var supported = match && String(source) === String(match.source_id) && String(destination) === String(match.destination_id);
            if (!source || !destination || (!supported && !$row.data('user-selected-records'))) { unresolved = true; }
        });
        fillCorrectionNotes();
        updateManualRows();
        if (unresolved) {
            resetManualPreview();
            $('#manual_stock_correction_verified').prop('checked', false);
            $('#manual_stock_correction_error').text('No unique supported match for a selected line. Select both the source receipt and receiving batch to review the correction.');
            return;
        }
        $('#manual_stock_correction_verified').prop('checked', true);
        autoCompletePreview = true;
        $('#preview_manual_stock_correction').trigger('click');
    });

    $('#preview_manual_stock_correction').on('click', function() {
        if (busy) { return; }
        var completeAutomatically = autoCompletePreview;
        autoCompletePreview = false;
        var payload = manualInput();
        if (!payload.corrections.length) {
            $('#manual_stock_correction_error').text('Select at least one line to correct.');
            return;
        }
        var missing = payload.corrections.find(function(row) { return !row.source_id || !row.destination_id; });
        if (missing) {
            $('#manual_stock_correction_error').text('Line #' + missing.sell_line_id + ': select both the verified source receipt and receiving batch.');
            var $field = $('#manual_stock_correction_lines .manual-correction-row').filter(function() {
                return $(this).attr('data-line-id') === missing.sell_line_id;
            }).find(!missing.source_id ? '.manual-source' : '.manual-destination');
            if ($.fn.select2) { $field.select2('open'); } else { $field.trigger('focus'); }
            return;
        }
        var destinations = payload.corrections.map(function(row) { return row.destination_id; });
        if (new Set(destinations).size !== destinations.length) {
            $('#manual_stock_correction_error').text('A receiving batch is selected for more than one line. Choose each line\'s matching receiving record.');
            return;
        }
        if (payload.date_verified && !payload.transaction_date) {
            $('#manual_stock_correction_error').text('Choose the verified delivery date and time.');
            showManualDatePicker();
            return;
        }
        if (payload.date_verified && lastDateBound && payload.transaction_date < lastDateBound) {
            $('#manual_stock_correction_error').text('Delivery must be on or after ' + lastDateBound + '. Keep the recorded date unless a different date is verified.');
            showManualDatePicker();
            return;
        }
        if (!$manualForm[0].reportValidity()) { return; }
        resetManualPreview();
        $('#manual_stock_correction_error').empty();
        setManualBusy(true, 'Checking Correction');
        $.ajax({method: 'POST', url: $generate.attr('data-preview-url'), dataType: 'json', data: payload})
            .done(function(result) {
                manualPlan = result.plan;
                manualPayload = payload;
                if (completeAutomatically) {
                    $('#confirm_manual_stock_correction').prop('checked', true);
                }
                $('#manual_stock_date_change').text(manualPlan.date_change
                    ? 'Verified transfer date: ' + manualPlan.date_change.transfer_before + ' -> ' + manualPlan.date_change.transfer_after
                        + '. Receiving date: ' + manualPlan.date_change.receipt_before + ' -> ' + manualPlan.date_change.receipt_after
                    : 'Transfer and receiving dates remain unchanged.');
                var $rows = $('#manual_stock_correction_changes').empty();
                manualPlan.changes.forEach(function(change) {
                    var $row = $('<tr>').appendTo($rows);
                    [change.sell_line_id, change.selected_batch_before, change.selected_batch_after, change.receiving_serial_before, change.receiving_serial_after]
                        .forEach(function(value) { $('<td>').text(value).appendTo($row); });
                });
                $('#manual_stock_shortage_title').text(manualPlan.allow_overselling ? 'Overselling enabled: negative source stock' : 'Existing source shortages remain');
                var $shortages = $('#manual_stock_shortage_changes').empty();
                (manualPlan.remaining_stock_shortages || []).forEach(function(shortage) {
                    $('<p>').text('Batch #' + shortage.purchase_line_id + ' / ' + shortage.serial + ': history availability '
                        + shortage.available_before + ' -> ' + shortage.available_after + '. ' + shortage.message).appendTo($shortages);
                    var $history = $('<ul>').appendTo($shortages);
                    (shortage.movements || []).forEach(function(movement) {
                        var $item = $('<li>').text(movement.reference + ' / line #' + movement.sell_line_id
                            + ' / ' + movement.date + ' / quantity ' + movement.quantity + ' ').appendTo($history);
                        var target;
                        try { target = movement.url ? new URL(movement.url, window.location.href) : null; } catch (error) { target = null; }
                        if (target && target.origin === window.location.origin && /^https?:$/.test(target.protocol)) {
                            $('<a>').text('Review record').attr({href: target.href, target: '_blank', rel: 'noopener noreferrer'}).appendTo($item);
                        }
                    });
                });
                $('#manual_stock_shortage_preview').toggle($shortages.children().length > 0);
                var $related = $('#manual_stock_related_changes').empty();
                (manualPlan.related_allocation_repairs || []).forEach(function(repair) {
                    var before = (repair.allocations_before || []).map(function(allocation) { return allocation.purchase_line_id || 'Unallocated'; }).join(', ');
                    $('<li>').text('Related line #' + repair.sell_line_id + ' (transaction #' + repair.transaction_id
                        + '): allocation ' + before + ' -> batch #' + repair.selected_batch_id + ' / ' + repair.serial
                        + '. Quantity ' + repair.quantity + '. Delivery date and receiving serial remain unchanged.').appendTo($related);
                });
                $('#manual_stock_related_preview').toggle($related.children().length > 0);
                $('#manual_stock_correction_preview').show();
                var remaining = (manualPlan.remaining_line_issues || []).length;
                var remainingReceipts = (manualPlan.remaining_transfer_issues || []).length;
                $('#manual_stock_remaining_issues').text(remaining || remainingReceipts
                    ? remaining + ' other line issues and ' + remainingReceipts + ' receiving record issues remain. The selected line and any listed related allocations will be corrected.'
                    : 'This line is ready for correction. Only the listed records and counters will change.');
                var $counters = $('#manual_stock_counter_changes').empty();
                (manualPlan.counter_repairs || []).forEach(function(repair) {
                    if (Number(repair.quantity_sold_before) === Number(repair.quantity_sold_after)) { return; }
                    var $row = $('<tr>').appendTo($counters);
                    [repair.purchase_line_id, repair.quantity_sold_before, repair.quantity_sold_after]
                        .forEach(function(value) { $('<td>').text(value).appendTo($row); });
                });
                $('#manual_stock_counter_preview').toggle($counters.children().length > 0);
            })
            .fail(function(xhr) {
                resetManualPreview();
                if (completeAutomatically) { $('#manual_stock_correction_verified').prop('checked', false); }
                renderManualError(xhr);
            })
            .always(function() { setManualBusy(false); });
    });

    $('#confirm_manual_stock_correction').on('change', function() {
        $('#apply_manual_stock_correction').prop('disabled', busy || !manualPlan || !this.checked);
    });

    $('#apply_manual_stock_correction').on('click', function() {
        if (busy || !manualPlan || !manualPayload || !$('#confirm_manual_stock_correction').prop('checked')) { return; }
        var payload = $.extend({}, manualPayload, {token: manualPlan.token, confirmed: 1});
        setManualBusy(true, 'Applying Correction');
        $.ajax({method: 'POST', url: $generate.attr('data-manual-apply-url'), dataType: 'json', data: payload})
            .done(function(result) {
                var saved = result.plan || {};
                refreshCorrectedRows($.extend({}, saved, {msg: result.msg}));
                resetManualPreview();
                $('#manual_stock_correction_modal').modal('hide');
            })
            .fail(function(xhr) { resetManualPreview(); renderManualError(xhr); })
            .always(function() { setManualBusy(false); });
    });

    $('#download_manual_stock_correction').on('click', function() {
        if (!manualPlan || busy) { return; }
        var url = URL.createObjectURL(new Blob([JSON.stringify(manualPlan, null, 2)], {type: 'application/json'}));
        var link = document.createElement('a');
        link.href = url;
        link.download = 'verified-stock-correction-' + manualPlan.transfer_id + '.json';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
    });
    var correctionLine = new URLSearchParams(window.location.search).get('correct_line');
    if (correctionLine && /^[1-9][0-9]*$/.test(correctionLine) && $generate.length) {
        $('#edit_verified_stock_correction').attr('data-line-id', correctionLine).trigger('click').removeAttr('data-line-id');
    }
})(jQuery);
