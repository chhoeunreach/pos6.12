<script>
$(document).ready(function() {
    if ($('#st_report_date_range').length == 1) {
        var drpSettings = $.extend(true, {}, dateRangeSettings, {
            startDate: moment(),
            endDate: moment()
        });
        $('#st_report_date_range').daterangepicker(drpSettings, function(start, end) {
            $('#st_report_date_range').val(
                start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
            );
            stock_transfer_report_table.ajax.reload();
        });
        $('#st_report_date_range').val(
            moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format)
        );
        $('#st_report_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#st_report_date_range').val('');
            stock_transfer_report_table.ajax.reload();
        });
    }

    var stock_transfer_report_table = $('#stock_transfer_report_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        ordering: true,
        order: [[0, 'desc']],
        responsive: {
            details: false
        },
        ajax: {
            url: '/reports/stock-transfer-report',
            data: function(d) {
                d.location_from_id = $('#st_report_location_from').val();
                d.location_to_id = $('#st_report_location_to').val();
                d.sender_id = $('#st_report_sender').val();
                if ($('#st_report_date_range').val()) {
                    var start = $('#st_report_date_range')
                        .data('daterangepicker')
                        .startDate.format('YYYY-MM-DD');
                    var end = $('#st_report_date_range')
                        .data('daterangepicker')
                        .endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                }
            }
        },
        columns: [
            { data: 'transaction_date', name: 'transactions.transaction_date', type: 'date' },
            { data: 'lot_number', name: 'purchase_lines.lot_number' },
            { data: 'sku', name: 'variations.sub_sku' },
            { data: 'product_name', name: 'product_name', searchable: false, orderable: false, width: '300px' },
            { data: 'qty', name: 'transaction_sell_lines.quantity', className: 'text-right' },
            { data: 'location_from', name: 'l1.name' },
            { data: 'location_to', name: 'l2.name' },
            { data: 'invoice', name: 'transactions.ref_no' },
            { data: 'sender_by', name: 'sender_by', searchable: false },
            { data: 'note', name: 'transactions.additional_notes' },
        ],
        footerCallback: function(row, data, start, end, display) {
            var api = this.api();
            var total = api.column(3, { page: 'current' }).data().reduce(function(a, b) {
                var val = parseFloat(b) || 0;
                return a + val;
            }, 0);
            $(api.column(3).footer()).html(total.toFixed(2));
        },
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#stock_transfer_report_table'));
        },
    });

    $(document).on('change', '#st_report_location_from, #st_report_location_to, #st_report_sender', function() {
        stock_transfer_report_table.ajax.reload();
    });

    $('#copy_table_btn').click(function() {
        var table = $('#stock_transfer_report_table');
        var data = '';

        table.find('thead tr th').each(function(i) {
            if (i > 0) data += '\t';
            data += $(this).text().trim();
        });
        data += '\n';

        table.find('tbody tr').each(function() {
            $(this).find('td').each(function(i) {
                if (i > 0) data += '\t';
                data += $(this).text().trim();
            });
            data += '\n';
        });

        var textarea = $('<textarea>').val(data).appendTo('body').select();
        document.execCommand('copy');
        textarea.remove();
        toastr.success('@lang("messages.success")');
    });

    $('#export_csv_btn').click(function() {
        var table = $('#stock_transfer_report_table');
        var csv = '';

        table.find('thead tr th').each(function(i) {
            if (i > 0) csv += ',';
            csv += '"' + $(this).text().trim() + '"';
        });
        csv += '\n';

        table.find('tbody tr').each(function() {
            $(this).find('td').each(function(i) {
                if (i > 0) csv += ',';
                var text = $(this).text().trim().replace(/"/g, '""');
                csv += '"' + text + '"';
            });
            csv += '\n';
        });

        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'stock_transfer_report_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
    });
});
</script>
