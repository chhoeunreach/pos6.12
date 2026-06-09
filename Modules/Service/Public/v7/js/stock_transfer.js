$(document).ready(function() {
    //Add products
    if ($('#search_product_for_srock_adjustment').length > 0) {
        // Prevent barcode scanners (Enter key) from submitting the form / stopping next scans.
        $(document).on('keydown', '#search_product_for_srock_adjustment', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                return false;
            }
        });

        //Add Product
        $('#search_product_for_srock_adjustment')
            .autocomplete({
                source: function(request, response) {
                    $.getJSON(
                        '/products/list',
                        {
                            location_id: $('#location_id').val(),
                            term: request.term,
                            search_fields: ['name', 'sku', 'lot'],
                            group_by_purchase_line: 1
                        },
                        response
                    );
                },
                minLength: 2,
                response: function(event, ui) {
                    if (ui.content.length == 1) {
                        ui.item = ui.content[0];
                        if (ui.item.qty_available > 0 && ui.item.enable_stock == 1) {
                            $(this)
                                .data('ui-autocomplete')
                                ._trigger('select', 'autocompleteselect', ui);
                            $(this).autocomplete('close');
                        }
                    } else if (ui.content.length == 0) {
                        swal(LANG.no_products_found);
                    }
                },
                focus: function(event, ui) {
                    if (ui.item.qty_available <= 0) {
                        return false;
                    }
                },
                select: function(event, ui) {
                    if (ui.item.qty_available > 0) {
                        $(this).val(null);
                        stock_transfer_product_row(ui.item.variation_id, function($row) {
                            if (ui.item.purchase_line_id && $row.find('select.lot_number').length) {
                                $row.find('select.lot_number').val(ui.item.purchase_line_id).trigger('change');
                            }
                        });
                        // Keep focus for continuous scanning
                        setTimeout(function() {
                            $('#search_product_for_srock_adjustment').focus();
                        }, 50);
                    } else {
                        alert(LANG.out_of_stock);
                    }
                },
            })
            .autocomplete('instance')._renderItem = function(ul, item) {
            if (item.qty_available <= 0) {
                var string = '<li class="ui-state-disabled">' + item.name;
                if (item.type == 'variable') {
                    string += '-' + item.variation;
                }
                string += ' (' + item.sub_sku + ') (Out of stock) </li>';
                return $(string).appendTo(ul);
            } else if (item.enable_stock != 1) {
                return ul;
            } else {
                var string = '<div>' + item.name;
                if (item.type == 'variable') {
                    string += '-' + item.variation;
                }
                string += ' (' + item.sub_sku + ') </div>';
                if (item.lot_number) {
                    string += '<small class="text-muted">Lot: ' + item.lot_number + '</small>';
                }
                return $('<li>')
                    .append(string)
                    .appendTo(ul);
            }
        };
    }

    $('select#location_id').change(function() {
        if ($(this).val()) {
            $('#search_product_for_srock_adjustment').removeAttr('disabled');
        } else {
            $('#search_product_for_srock_adjustment').attr('disabled', 'disabled');
        }

        // Retransfer mode: reload original lines for the selected source location
        if ($('#retransfer_lines').length) {
            var location_id = $('select#location_id').val();
            $('table#stock_adjustment_product_table tbody').html('');
            $('#product_row_index').val(0);

            var lines = [];
            try {
                lines = JSON.parse($('#retransfer_lines').val() || '[]');
            } catch (e) {
                lines = [];
            }

            if (location_id && lines.length) {
                (function add_next_line(i) {
                    if (i >= lines.length) {
                        update_table_total();
                        return;
                    }

                    var line = lines[i] || {};
                    stock_transfer_product_row(line.variation_id, function($row) {
                        if (!$row || !$row.length) {
                            add_next_line(i + 1);
                            return;
                        }

                        if (line.sub_unit_id && $row.find('select.sub_unit').length) {
                            $row.find('select.sub_unit').val(line.sub_unit_id).trigger('change');
                        }

                        var multiplier = 1;
                        if ($row.find('select.sub_unit').length) {
                            var selected_option = $row.find('select.sub_unit').find(':selected');
                            multiplier = parseFloat(selected_option.data('multiplier')) || 1;
                        }

                        var display_qty = (parseFloat(line.quantity) || 0) / multiplier;
                        __write_number($row.find('input.product_quantity'), display_qty);
                        $row.find('input.product_quantity').trigger('change');

                        if (line.lot_no_line_id && $row.find('select.lot_number').length) {
                            $row.find('select.lot_number').val(line.lot_no_line_id).trigger('change');
                        }

                        add_next_line(i + 1);
                    });
                })(0);
            } else {
                update_table_total();
            }

            return;
        }

        $('table#stock_adjustment_product_table tbody').html('');
        $('#product_row_index').val(0);
        update_table_total();
    });

    $(document).on('change', 'input.product_quantity', function() {
        update_table_row($(this).closest('tr'));
    });
    $(document).on('change', 'input.product_unit_price', function() {
        update_table_row($(this).closest('tr'));
    });

    $(document).on('click', '.remove_product_row', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                $(this)
                    .closest('tr')
                    .remove();
                update_table_total();
            }
        });
    });

    //Date picker
    $('#transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });

    // If location is preselected (ex: retransfer), enable search without clearing rows
    if ($('select#location_id').length && $('select#location_id').val()) {
        $('#search_product_for_srock_adjustment').removeAttr('disabled');
    }

    // Ensure totals are in sync on initial load (edit/retransfer)
    if ($('#stock_adjustment_product_table').length) {
        update_table_total();
    }

    jQuery.validator.addMethod(
        'notEqual',
        function(value, element, param) {
            return this.optional(element) || value != param;
        },
        'Please select different location'
    );

    $('form#stock_transfer_form').validate({
        rules: {
            transfer_location_id: {
                notEqual: function() {
                    return $('select#location_id').val();
                },
            },
        },
    });
    $('#save_stock_transfer').click(function(e) {
        e.preventDefault();

        if ($('table#stock_adjustment_product_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }
        if ($('form#stock_transfer_form').valid()) {
            $('form#stock_transfer_form').submit();
        } else {
            return false;
        }
    });

    // Stock Transfer list filters (only on index page)
    if ($('#stock_transfer_list_filter_product_id').length) {
        $('#stock_transfer_list_filter_product_id').select2({
            ajax: {
                url: '/products/list-no-variation',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term,
                    };
                },
                processResults: function(data) {
                    return {
                        results: data,
                    };
                },
            },
            minimumInputLength: 1,
            escapeMarkup: function(m) {
                return m;
            },
        });
    }

    if ($('#stock_transfer_list_filter_date_range').length) {
        var start = moment().subtract(29, 'days');
        var end = moment();
        $('#stock_transfer_list_filter_date_range').daterangepicker(
            $.extend(true, {}, dateRangeSettings, { startDate: start, endDate: end }),
            function(start, end) {
                if (typeof stock_transfer_table !== 'undefined') {
                    stock_transfer_table.ajax.reload();
                }
            }
        );
        $('#stock_transfer_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#stock_transfer_list_filter_date_range').val('');
            if (typeof stock_transfer_table !== 'undefined') {
                stock_transfer_table.ajax.reload();
            }
        });
    }

    stock_transfer_table = $('#stock_transfer_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader:false,
        aaSorting: [[0, 'desc']],
        ajax: {
            url: '/stock-transfers',
            data: function(d) {
                d.product_id = $('#stock_transfer_list_filter_product_id').val();
                d.location_from_id = $('#stock_transfer_list_filter_location_from').val();
                d.location_to_id = $('#stock_transfer_list_filter_location_to').val();
                d.status = $('#stock_transfer_list_filter_status').val();
                d.created_by = $('#stock_transfer_list_filter_created_by').val();

                if ($('#stock_transfer_list_filter_date_range').val()) {
                    d.start_date = $('#stock_transfer_list_filter_date_range')
                        .data('daterangepicker')
                        .startDate.format('YYYY-MM-DD');
                    d.end_date = $('#stock_transfer_list_filter_date_range')
                        .data('daterangepicker')
                        .endDate.format('YYYY-MM-DD');
                }

                d = __datatable_ajax_callback(d);
            },
        },
        columnDefs: [
            {
                targets: 9,
                orderable: false,
                searchable: false,
            },
        ],
        columns: [
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'ref_no', name: 'ref_no' },
            { data: 'location_from', name: 'l1.name' },
            { data: 'location_to', name: 'l2.name' },
            { data: 'status', name: 'status' },
            { data: 'total_qty', name: 'total_qty', searchable: false },
            { data: 'shipping_charges', name: 'shipping_charges' },
            { data: 'final_total', name: 'final_total' },
            { data: 'additional_notes', name: 'additional_notes' },
            { data: 'action', name: 'action' },
        ],
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#stock_transfer_table'));
        },
    });

    $(document).on(
        'change',
        '#stock_transfer_list_filter_product_id, #stock_transfer_list_filter_location_from, #stock_transfer_list_filter_location_to, #stock_transfer_list_filter_status, #stock_transfer_list_filter_created_by',
        function() {
            if (typeof stock_transfer_table !== 'undefined') {
                stock_transfer_table.ajax.reload();
            }
        }
    );
    var detailRows = [];

    $('#stock_transfer_table tbody').on('click', '.view_stock_transfer', function() {
        var tr = $(this).closest('tr');
        var row = stock_transfer_table.row(tr);
        var idx = $.inArray(tr.attr('id'), detailRows);

        if (row.child.isShown()) {
            $(this)
                .find('i')
                .removeClass('fa-eye')
                .addClass('fa-eye-slash');
            row.child.hide();

            // Remove from the 'open' array
            detailRows.splice(idx, 1);
        } else {
            $(this)
                .find('i')
                .removeClass('fa-eye-slash')
                .addClass('fa-eye');

            row.child(get_stock_transfer_details(row.data())).show();

            // Add to the 'open' array
            if (idx === -1) {
                detailRows.push(tr.attr('id'));
            }
        }
    });

    // On each draw, loop over the `detailRows` array and show any child rows
    stock_transfer_table.on('draw', function() {
        $.each(detailRows, function(i, id) {
            $('#' + id + ' .view_stock_transfer').trigger('click');
        });
    });

    //Delete Stock Transfer
    $(document).on('click', 'button.delete_stock_transfer', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                var href = $(this).data('href');
                $.ajax({
                    method: 'DELETE',
                    url: href,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            stock_transfer_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });

    // Import stock transfer lines (create page)
    $(document).on('click', '#btn_import_stock_transfer_lines', function(e) {
        e.preventDefault();

        if (!$('#stock_transfer_import_file').length) {
            return;
        }

        var fileEl = $('#stock_transfer_import_file')[0];
        if (!fileEl.files || !fileEl.files.length) {
            toastr.warning('Please choose a file to import.');
            return;
        }

        var locationId = $('select#location_id').val();
        if (!locationId) {
            toastr.warning('Please select Location From first.');
            return;
        }

        var formData = new FormData();
        formData.append('file', fileEl.files[0]);
        formData.append('location_id', locationId);

        $('#stock_transfer_import_summary').hide().empty();
        $('#stock_transfer_import_errors_wrap').hide();
        $('#stock_transfer_import_errors_table tbody').empty();

        $.ajax({
            method: 'POST',
            url: '/stock-transfers/import-lines',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(result) {
                if (!result || result.success !== true) {
                    toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                    return;
                }

                var summary = result.summary || {};
                var summaryHtml =
                    '<strong>Import Summary</strong><br>' +
                    'Total Rows: ' + (summary.total_rows || 0) + ' | ' +
                    'Loaded: ' + (summary.success || 0) + ' | ' +
                    'Failed: ' + (summary.failed || 0);
                $('#stock_transfer_import_summary').html(summaryHtml).show();

                var errors = result.errors || [];
                if (errors.length) {
                    errors.forEach(function(err) {
                        var row = err.row !== null && typeof err.row !== 'undefined' ? err.row : '--';
                        var matchedBy = err.match_by || '--';
                        var tr =
                            '<tr>' +
                                '<td>' + row + '</td>' +
                                '<td>' + matchedBy + '</td>' +
                                '<td>' + (err.sku || '') + '</td>' +
                                '<td>' + (err.lot_number || '') + '</td>' +
                                '<td>' + (err.quantity || '') + '</td>' +
                                '<td>' + (err.note || '') + '</td>' +
                                '<td>' + (err.error || '') + '</td>' +
                            '</tr>';
                        $('#stock_transfer_import_errors_table tbody').append(tr);
                    });
                    $('#stock_transfer_import_errors_wrap').show();
                }

                var lines = result.lines || [];
                if (!lines.length) {
                    if (!errors.length) {
                        toastr.warning('No valid rows found to load.');
                    }
                    return;
                }

                function find_existing_row(variationId, lotNoLineId) {
                    var found = null;
                    $('#stock_adjustment_product_table tbody tr.product_row').each(function() {
                        var $tr = $(this);
                        var v = $tr.find('input[name$="[variation_id]"]').val();
                        if (parseInt(v) !== parseInt(variationId)) {
                            return;
                        }
                        var lotVal = $tr.find('select.lot_number').length ? ($tr.find('select.lot_number').val() || '') : '';
                        var targetLot = lotNoLineId ? String(lotNoLineId) : '';
                        if (String(lotVal) === targetLot) {
                            found = $tr;
                            return false;
                        }
                    });
                    return found;
                }

                // Add/merge lines sequentially (to avoid row_index races)
                (function add_next(i) {
                    if (i >= lines.length) {
                        toastr.success('Imported lines loaded.');
                        return;
                    }

                    var line = lines[i] || {};
                    var existing = find_existing_row(line.variation_id, line.lot_no_line_id);
                    if (existing) {
                        // Merge quantities into existing row
                        var multiplier = 1;
                        if (existing.find('select.sub_unit').length) {
                            var selected = existing.find('select.sub_unit').find(':selected');
                            multiplier = parseFloat(selected.data('multiplier')) || 1;
                        }
                        var currentQty = parseFloat(__read_number(existing.find('input.product_quantity'))) || 0;
                        var addQty = (parseFloat(line.quantity) || 0) / multiplier;
                        __write_number(existing.find('input.product_quantity'), currentQty + addQty);
                        existing.find('input.product_quantity').trigger('change');

                        if (line.note) {
                            var $note = existing.find('input.sell_line_note');
                            var existingNote = $note.val() || '';
                            $note.val((existingNote ? (existingNote + "\n") : '') + line.note);
                        }

                        add_next(i + 1);
                        return;
                    }

                    stock_transfer_product_row(line.variation_id, function($row) {
                        if (!$row || !$row.length) {
                            add_next(i + 1);
                            return;
                        }

                        if (line.lot_no_line_id && $row.find('select.lot_number').length) {
                            $row.find('select.lot_number').val(line.lot_no_line_id).trigger('change');
                        }

                        var multiplier = 1;
                        if ($row.find('select.sub_unit').length) {
                            var selected_option = $row.find('select.sub_unit').find(':selected');
                            multiplier = parseFloat(selected_option.data('multiplier')) || 1;
                        }
                        var display_qty = (parseFloat(line.quantity) || 0) / multiplier;
                        __write_number($row.find('input.product_quantity'), display_qty);
                        $row.find('input.product_quantity').trigger('change');

                        if (line.note) {
                            $row.find('input.sell_line_note').val(line.note);
                        }

                        // Highlight import source (SKU vs LOT)
                        if (line.match_by === 'lot') {
                            $row.addClass('imported-by-lot');
                            $row.find('td:first').prepend('<span class="label label-info" style="margin-right:6px;">LOT</span>');
                        } else if (line.match_by === 'sku') {
                            $row.addClass('imported-by-sku');
                            $row.find('td:first').prepend('<span class="label label-default" style="margin-right:6px;">SKU</span>');
                        }

                        add_next(i + 1);
                    });
                })(0);
            },
            error: function(xhr) {
                toastr.error(LANG.something_went_wrong);
            },
        });
    });
});

function stock_transfer_product_row(variation_id, on_success) {
    var row_index = parseInt($('#product_row_index').val());
    var location_id = $('select#location_id').val();
    $.ajax({
        method: 'POST',
        url: '/stock-adjustments/get_product_row',
        data: { row_index: row_index, variation_id: variation_id, location_id: location_id, type: 'stock_transfer' },
        dataType: 'html',
        success: function(result) {
            var $row = $(result);
            $('table#stock_adjustment_product_table tbody').append($row);
            update_table_total();
            $('#product_row_index').val(row_index + 1);

            if (typeof on_success === 'function') {
                on_success($row);
            }
        },
        error: function() {
            if (typeof toastr !== 'undefined') {
                toastr.warning(LANG.no_products_found);
            }
            if (typeof on_success === 'function') {
                on_success($());
            }
        }
    });
}

function update_table_total() {
    var table_total = 0;
    $('table#stock_adjustment_product_table tbody tr').each(function() {
        var this_total = parseFloat(__read_number($(this).find('input.product_line_total')));
        if (this_total) {
            table_total += this_total;
        }
    });

    $('span#total_adjustment').text(__number_f(table_total));

    if ($('input#shipping_charges').length) {
        var shipping_charges = __read_number($('input#shipping_charges'));
        table_total += shipping_charges;
    }

    $('span#final_total_text').text(__number_f(table_total));
    $('input#total_amount').val(table_total);
}

$(document).on('change', '#shipping_charges', function() {
    update_table_total();
});

$(document).on('change', 'select.sub_unit', function() {
    var tr = $(this).closest('tr');
    var selected_option = $(this).find(':selected');
    var multiplier = parseFloat(selected_option.data('multiplier'));
    var allow_decimal = parseInt(selected_option.data('allow_decimal'));
    tr.find('input.base_unit_multiplier').val(multiplier);

    var base_unit_price = tr.find('input.hidden_base_unit_price').val();

    var unit_price = base_unit_price * multiplier;
    var unit_price_element = tr.find('input.product_unit_price');
    __write_number(unit_price_element, unit_price);
    
    var qty_element = tr.find('input.product_quantity');
    var base_max_avlbl = qty_element.data('qty_available');
    var error_msg_line = 'pos_max_qty_error';

    if (tr.find('select.lot_number').length > 0) {
        var lot_select = tr.find('select.lot_number');
        if (lot_select.val()) {
            base_max_avlbl = lot_select.find(':selected').data('qty_available');
            error_msg_line = 'lot_max_qty_error';
        }
    }
    qty_element.attr('data-decimal', allow_decimal);
    var abs_digit = true;
    if (allow_decimal) {
        abs_digit = false;
    }
    qty_element.rules('add', {
        abs_digit: abs_digit,
    });

    if (base_max_avlbl) {
        var max_avlbl = parseFloat(base_max_avlbl) / multiplier;
        var formated_max_avlbl = __number_f(max_avlbl);
        var unit_name = selected_option.data('unit_name');
        var max_err_msg = __translate(error_msg_line, {
            max_val: formated_max_avlbl,
            unit_name: unit_name,
        });
        qty_element.attr('data-rule-max-value', max_avlbl);
        qty_element.attr('data-msg-max-value', max_err_msg);
        qty_element.rules('add', {
            'max-value': max_avlbl,
            messages: {
                'max-value': max_err_msg,
            },
        });
        qty_element.trigger('change');
    }
    qty_element.valid();
    update_table_row($(this).closest('tr'));
});

function update_table_row(tr) {
    var quantity = parseFloat(__read_number(tr.find('input.product_quantity')));
    var multiplier = 1;

    if (tr.find('select.sub_unit').length) {
        multiplier = parseFloat(
            tr.find('select.sub_unit')
                .find(':selected')
                .data('multiplier')
        );
    }
    quantity = quantity * multiplier;
    
    var unit_price = parseFloat(tr.find('input.hidden_base_unit_price').val());
    var row_total = 0;
    if (quantity && unit_price) {
        row_total = quantity * unit_price;
    }
    tr.find('input.product_line_total').val(__number_f(row_total));
    update_table_total();
}

function get_stock_transfer_details(rowData) {
    var div = $('<div/>')
        .addClass('loading')
        .text('Loading...');
    $.ajax({
        url: '/stock-transfers/' + rowData.DT_RowId,
        dataType: 'html',
        success: function(data) {
            div.html(data).removeClass('loading');
        },
    });

    return div;
}

$(document).on('click', 'a.stock_transfer_status', function(e) {
    e.preventDefault();
    var href = $(this).data('href');
    var status = $(this).data('status');
    $('#update_stock_transfer_status_modal').modal('show');
    $('#update_stock_transfer_status_form').attr('action', href);
    $('#update_stock_transfer_status_form #update_status').val(status);
    $('#update_stock_transfer_status_form #update_status').trigger('change');
});

$(document).on('submit', '#update_stock_transfer_status_form', function(e) {
    e.preventDefault();
    var form = $(this);
    var data = form.serialize();

    $.ajax({
        method: 'post',
        url: $(this).attr('action'),
        dataType: 'json',
        data: data,
        beforeSend: function(xhr) {
            __disable_submit_button(form.find('button[type="submit"]'));
        },
        success: function(result) {
            if (result.success == true) {
                $('div#update_stock_transfer_status_modal').modal('hide');
                toastr.success(result.msg);
                stock_transfer_table.ajax.reload();
            } else {
                toastr.error(result.msg);
            }
            $('#update_stock_transfer_status_form')
            .find('button[type="submit"]')
            .attr('disabled', false);
        },
    });
});
$(document).on('shown.bs.modal', '.view_modal', function() {
    __currency_convert_recursively($('.view_modal'));
});
