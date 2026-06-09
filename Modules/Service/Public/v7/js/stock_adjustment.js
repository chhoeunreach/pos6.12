$(document).ready(function() {
    //Add products
    if ($('#search_product_for_srock_adjustment').length > 0) {
        //Add Product
        $('#search_product_for_srock_adjustment')
            .autocomplete({
                source: function(request, response) {
                    $.getJSON(
                        '/products/list',
                        { location_id: $('#location_id').val(), term: request.term },
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
                        stock_adjustment_product_row(ui.item.variation_id);
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

    $('form#stock_adjustment_form').validate();

    // Import products (create page)
    $(document).on('click', '#btn_import_stock_adjustment_products', function(e) {
        e.preventDefault();

        if (!$('#stock_adjustment_import_file').length) {
            return;
        }

        var fileEl = $('#stock_adjustment_import_file')[0];
        if (!fileEl.files || !fileEl.files.length) {
            toastr.warning('Please choose a file to import.');
            return;
        }

        var locationId = $('select#location_id').val();
        if (!locationId) {
            toastr.warning('Please select Business Location first.');
            return;
        }

        var formData = new FormData();
        formData.append('file', fileEl.files[0]);
        formData.append('location_id', locationId);

        $('#stock_adjustment_import_summary').hide().empty();
        $('#stock_adjustment_import_errors_wrap').hide();
        $('#stock_adjustment_import_errors_table tbody').empty();

        $.ajax({
            method: 'POST',
            url: '/stock-adjustments/import-products',
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
                $('#stock_adjustment_import_summary').html(summaryHtml).show();

                var errors = result.error_rows || [];
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
                                '<td>' + (err.adjustment_type || '') + '</td>' +
                                '<td>' + (err.note || '') + '</td>' +
                                '<td>' + (err.error || '') + '</td>' +
                            '</tr>';
                        $('#stock_adjustment_import_errors_table tbody').append(tr);
                    });
                    $('#stock_adjustment_import_errors_wrap').show();
                }

                var lines = result.valid_rows || [];
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
                        toastr.success('Imported products loaded.');
                        return;
                    }

                    var line = lines[i] || {};
                    var existing = find_existing_row(line.variation_id, line.lot_no_line_id);
                    if (existing) {
                        var multiplier = 1;
                        if (existing.find('select.sub_unit').length) {
                            var selected = existing.find('select.sub_unit').find(':selected');
                            multiplier = parseFloat(selected.data('multiplier')) || 1;
                        }
                        var currentQty = parseFloat(__read_number(existing.find('input.product_quantity'))) || 0;
                        var addQty = (parseFloat(line.quantity) || 0) / multiplier;
                        __write_number(existing.find('input.product_quantity'), currentQty + addQty);
                        existing.find('input.product_quantity').trigger('change');

                        add_next(i + 1);
                        return;
                    }

                    stock_adjustment_product_row(line.variation_id, function($row) {
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

    // Stock Adjustment list filters (only on index page)
    if ($('#stock_adjustment_list_filter_product_id').length) {
        $('#stock_adjustment_list_filter_product_id').select2({
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

    if ($('#stock_adjustment_list_filter_date_range').length) {
        $('#stock_adjustment_list_filter_date_range').daterangepicker(dateRangeSettings, function(start, end) {
            if (typeof stock_adjustment_table !== 'undefined') {
                stock_adjustment_table.ajax.reload();
            }
        });
        $('#stock_adjustment_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#stock_adjustment_list_filter_date_range').val('');
            if (typeof stock_adjustment_table !== 'undefined') {
                stock_adjustment_table.ajax.reload();
            }
        });
    }

    stock_adjustment_table = $('#stock_adjustment_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader:false,
        ajax: {
            url: '/stock-adjustments',
            data: function(d) {
                d.location_id = $('#stock_adjustment_list_filter_location_id').val();
                d.adjustment_type = $('#stock_adjustment_list_filter_adjustment_type').val();
                d.product_id = $('#stock_adjustment_list_filter_product_id').val();
                d.created_by = $('#stock_adjustment_list_filter_created_by').val();
                d.ref_no = $('#stock_adjustment_list_filter_ref_no').val();

                var start = '';
                var end = '';
                if ($('#stock_adjustment_list_filter_date_range').val()) {
                    start = $('input#stock_adjustment_list_filter_date_range')
                        .data('daterangepicker')
                        .startDate.format('YYYY-MM-DD');
                    end = $('input#stock_adjustment_list_filter_date_range')
                        .data('daterangepicker')
                        .endDate.format('YYYY-MM-DD');
                }
                d.start_date = start;
                d.end_date = end;

                d = __datatable_ajax_callback(d);
            },
        },
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                searchable: false,
            },
        ],
        aaSorting: [[1, 'desc']],
        columns: [
            { data: 'action', name: 'action' },
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'ref_no', name: 'ref_no' },
            { data: 'location_name', name: 'BL.name' },
            { data: 'adjustment_type', name: 'adjustment_type' },
            { data: 'final_total', name: 'final_total' },
            { data: 'total_amount_recovered', name: 'total_amount_recovered' },
            { data: 'additional_notes', name: 'additional_notes' },
            { data: 'added_by', name: 'u.first_name' },
        ],
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#stock_adjustment_table'));
        },
    });
    var detailRows = [];

    $(document).on('click', 'button.delete_stock_adjustment', function() {
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
                            stock_adjustment_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });
});

$(document).on(
    'change',
    '#stock_adjustment_list_filter_location_id, #stock_adjustment_list_filter_adjustment_type, #stock_adjustment_list_filter_product_id, #stock_adjustment_list_filter_created_by',
    function() {
        if (typeof stock_adjustment_table !== 'undefined') {
            stock_adjustment_table.ajax.reload();
        }
    }
);

var stock_adjustment_ref_no_timer = null;
$(document).on('keyup', '#stock_adjustment_list_filter_ref_no', function() {
    if (stock_adjustment_ref_no_timer) {
        clearTimeout(stock_adjustment_ref_no_timer);
    }
    stock_adjustment_ref_no_timer = setTimeout(function() {
        if (typeof stock_adjustment_table !== 'undefined') {
            stock_adjustment_table.ajax.reload();
        }
    }, 400);
});

function stock_adjustment_product_row(variation_id, on_success) {
    var row_index = parseInt($('#product_row_index').val());
    var location_id = $('select#location_id').val();
    $.ajax({
        method: 'POST',
        url: '/stock-adjustments/get_product_row',
        data: { row_index: row_index, variation_id: variation_id, location_id: location_id },
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
    $('input#total_amount').val(table_total);
    $('span#total_adjustment').text(__number_f(table_total));
}

function update_table_row(tr) {
    var quantity = parseFloat(__read_number(tr.find('input.product_quantity')));
    var unit_price = parseFloat(__read_number(tr.find('input.product_unit_price')));
    var row_total = 0;
    if (quantity && unit_price) {
        row_total = quantity * unit_price;
    }
    tr.find('input.product_line_total').val(__number_f(row_total));
    update_table_total();
}

$(document).on('shown.bs.modal', '.view_modal', function() {
    __currency_convert_recursively($('.view_modal'));
});
