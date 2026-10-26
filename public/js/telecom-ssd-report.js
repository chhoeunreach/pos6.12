$(function() {
    var $form = $('#telecom_ssd_filters');
    var $table = $('#telecom_ssd_report_table');
    var $error = $('#telecom_ssd_error');
    var filters = $form.serializeArray().filter(function(filter) { return filter.name !== 'date_range'; });
    var $dateRange = $('#telecom_ssd_date_range');
    var $startDate = $('#telecom_ssd_start_date');
    var $endDate = $('#telecom_ssd_end_date');

    function updateExportLink() {
        var exportFilters = filters.concat([{name: 'search_text', value: table ? table.search() : ($table.data('initial-search') || '')}]);
        if (table) {
            table.order().forEach(function(order, index) {
                exportFilters.push({name: 'order[' + index + '][column]', value: order[0]});
                exportFilters.push({name: 'order[' + index + '][dir]', value: order[1]});
            });
        }
        $('#telecom_ssd_export').attr('href', $table.data('url') + '?' + $.param(exportFilters) + '&export=csv');
    }

    function numberCell(value, type) {
        return type === 'display' ? __currency_trans_from_en(parseFloat(value) || 0, false, false, __quantity_precision, true) : value;
    }

    function moneyCell(value, type) {
        return type === 'display' ? __currency_trans_from_en(parseFloat(value) || 0, false) : value;
    }

    var table = $table.DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        searching: true,
        searchDelay: 350,
        search: {search: $table.data('initial-search') || ''},
        fixedHeader: false,
        columnDefs: [{targets: '_all', searchable: false}],
        ordering: true,
        order: [[10, 'desc']],
        ajax: {
            url: $table.data('url'),
            data: function(data) {
                filters.forEach(function(filter) { data[filter.name] = filter.value; });
                data.search_text = data.search ? data.search.value : '';
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {};
                var message = response.message || 'Unable to load the report. Please try again.';
                if (response.errors) {
                    message = Object.keys(response.errors).map(function(key) { return response.errors[key].join(' '); }).join(' ');
                }
                $error.text(message).prop('hidden', false);
                $table.closest('.dataTables_wrapper').find('.dataTables_processing').hide();
            }
        },
        columns: [
            {data: $table.data('index-column'), searchable: false, orderable: false},
            {data: 'product_name'},
            {data: 'model_number', defaultContent: ''},
            {data: 'qa_number', defaultContent: ''},
            {data: 'trademark', defaultContent: ''},
            {data: 'country_of_manufacture', defaultContent: ''},
            {data: 'sim', defaultContent: ''},
            {data: 'quantity', className: 'text-right', render: numberCell},
            {data: 'unit_price', className: 'text-right', render: moneyCell},
            {data: 'total_price', className: 'text-right', render: moneyCell},
            {data: 'transaction_date', render: function(value) { return (value || '').slice(0, 10); }},
            {data: 'client_name', defaultContent: ''},
            {data: 'remark', defaultContent: ''}
        ]
    });

    $table.on('search.dt order.dt', updateExportLink);

    function reloadReport() {
        filters = $form.serializeArray().filter(function(filter) { return filter.name !== 'date_range'; });
        $error.prop('hidden', true);
        updateExportLink();
        table.ajax.reload();
    }

    $dateRange.daterangepicker($.extend({}, dateRangeSettings, {
        autoUpdateInput: false,
        startDate: ($startDate.val() || $endDate.val()) ? moment($startDate.val() || $endDate.val(), 'YYYY-MM-DD') : moment(),
        endDate: ($endDate.val() || $startDate.val()) ? moment($endDate.val() || $startDate.val(), 'YYYY-MM-DD') : moment()
    }));
    if ($startDate.val() || $endDate.val()) {
        $dateRange.val(($startDate.val() ? moment($startDate.val(), 'YYYY-MM-DD').format(moment_date_format) : '…') +
            ' ~ ' + ($endDate.val() ? moment($endDate.val(), 'YYYY-MM-DD').format(moment_date_format) : '…'));
    }
    $dateRange.on('apply.daterangepicker', function(event, picker) {
        $startDate.val(picker.startDate.format('YYYY-MM-DD'));
        $endDate.val(picker.endDate.format('YYYY-MM-DD'));
        $dateRange.val(picker.startDate.format(moment_date_format) + ' ~ ' + picker.endDate.format(moment_date_format));
        reloadReport();
    });
    $dateRange.on('cancel.daterangepicker', function() {
        $dateRange.val('');
        $startDate.val('');
        $endDate.val('');
        reloadReport();
    });
    $form.on('change', 'select', reloadReport);
    $form.on('submit', function(event) {
        event.preventDefault();
        reloadReport();
    });
    updateExportLink();
});
