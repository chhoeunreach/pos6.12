<script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
<script>
$(document).ready(function() {
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
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'sku', name: 'sku' },
            { data: 'product_name', name: 'product_name' },
            { data: 'qty', name: 'qty' },
            { data: 'location_from', name: 'location_from' },
            { data: 'location_to', name: 'location_to' },
            { data: 'invoice', name: 'invoice' },
            { data: 'sender_by', name: 'sender_by' },
            { data: 'note', name: 'note', className: 'all' },
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

    if ($('#st_report_date_range').length == 1) {
        $('#st_report_date_range').daterangepicker(dateRangeSettings, function(start, end) {
            $('#st_report_date_range').val(
                start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
            );
            stock_transfer_report_table.ajax.reload();
        });
        $('#st_report_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#st_report_date_range').val('');
            stock_transfer_report_table.ajax.reload();
        });
    }

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
