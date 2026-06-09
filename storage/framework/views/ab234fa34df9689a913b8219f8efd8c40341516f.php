<script type="text/javascript">
    $(document).ready(function() {
        if ($('#dashboard_date_filter').length == 1) {
            dateRangeSettings.startDate = moment();
            dateRangeSettings.endDate = moment();
            $('#dashboard_date_filter').daterangepicker(dateRangeSettings, function(start, end) {
                $('#dashboard_date_filter span').html(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
                update_statistics(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
                if ($('#quotation_table').length && $('#dashboard_location').length) {
                    quotation_datatable.ajax.reload();
                }
            });

            update_statistics(moment().format('YYYY-MM-DD'), moment().format('YYYY-MM-DD'));
        }

        $('#dashboard_location').change(function(e) {
            var start = $('#dashboard_date_filter')
                .data('daterangepicker')
                .startDate.format('YYYY-MM-DD');

            var end = $('#dashboard_date_filter')
                .data('daterangepicker')
                .endDate.format('YYYY-MM-DD');

            update_statistics(start, end);
        });

        var stock_alert_table = $('#stock_alert_table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            scrollY: "75vh",
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            dom: 'Btirp',
            ajax: {
                url: "<?php echo e(url(config('service.route_prefix', 'service-pos') . '/home/product-stock-alert'), false); ?>",
                data: function(d) {
                    if ($('#stock_alert_location').length > 0) {
                        d.location_id = $('#stock_alert_location').val();
                    }
                }
            },
            fnDrawCallback: function(oSettings) {
                __currency_convert_recursively($('#stock_alert_table'));
            },
        });

        $('#stock_alert_location').change(function() {
            stock_alert_table.ajax.reload();
        });

        purchase_payment_dues_table = $('#purchase_payment_dues_table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            scrollY: "75vh",
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            dom: 'Btirp',
            ajax: {
                url: "<?php echo e(url(config('service.route_prefix', 'service-pos') . '/home/purchase-payment-dues'), false); ?>",
                data: function(d) {
                    if ($('#purchase_payment_dues_location').length > 0) {
                        d.location_id = $('#purchase_payment_dues_location').val();
                    }
                }
            },
            fnDrawCallback: function(oSettings) {
                __currency_convert_recursively($('#purchase_payment_dues_table'));
            },
        });

        $('#purchase_payment_dues_location').change(function() {
            purchase_payment_dues_table.ajax.reload();
        });

        sales_payment_dues_table = $('#sales_payment_dues_table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            scrollY: "75vh",
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            dom: 'Btirp',
            ajax: {
                url: "<?php echo e(url(config('service.route_prefix', 'service-pos') . '/home/sales-payment-dues'), false); ?>",
                data: function(d) {
                    if ($('#sales_payment_dues_location').length > 0) {
                        d.location_id = $('#sales_payment_dues_location').val();
                    }
                }
            },
            fnDrawCallback: function(oSettings) {
                __currency_convert_recursively($('#sales_payment_dues_table'));
            },
        });

        $('#sales_payment_dues_location').change(function() {
            sales_payment_dues_table.ajax.reload();
        });

        stock_expiry_alert_table = $('#stock_expiry_alert_table').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            scrollY: "75vh",
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            dom: 'Btirp',
            ajax: {
                url: "<?php echo e(url(config('service.route_prefix', 'service-pos') . '/reports/stock-expiry'), false); ?>",
                data: function(d) {
                    d.exp_date_filter = $('#stock_expiry_alert_days').val();
                },
            },
            order: [[3, 'asc']],
            columns: [
                { data: 'product', name: 'p.name' },
                { data: 'location', name: 'l.name' },
                { data: 'stock_left', name: 'stock_left' },
                { data: 'exp_date', name: 'exp_date' },
            ],
            fnDrawCallback: function(oSettings) {
                __show_date_diff_for_human($('#stock_expiry_alert_table'));
                __currency_convert_recursively($('#stock_expiry_alert_table'));
            },
        });

        if ($('#quotation_table').length) {
            quotation_datatable = $('#quotation_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader: false,
                aaSorting: [[0, 'desc']],
                ajax: {
                    url: "<?php echo e(url(config('service.route_prefix', 'service-pos') . '/sells/draft-dt?is_quotation=1'), false); ?>",
                    data: function(d) {
                        if ($('#dashboard_location').length > 0) {
                            d.location_id = $('#dashboard_location').val();
                        }
                    }
                },
                columnDefs: [{
                    targets: 4,
                    orderable: false,
                    searchable: false
                }],
                columns: [
                    { data: 'transaction_date', name: 'transaction_date' },
                    { data: 'invoice_no', name: 'invoice_no' },
                    { data: 'name', name: 'contacts.name' },
                    { data: 'business_location', name: 'bl.name' },
                    { data: 'action', name: 'action' }
                ]
            });
        }
    });

    function update_statistics(start, end) {
        var location_id = '';
        if ($('#dashboard_location').length > 0) {
            location_id = $('#dashboard_location').val();
        }
        var data = { start: start, end: end, location_id: location_id };
        var loader = '<i class="fas fa-sync fa-spin fa-fw margin-bottom"></i>';
        $('.total_purchase').html(loader);
        $('.purchase_due').html(loader);
        $('.total_sell').html(loader);
        $('.invoice_due').html(loader);
        $('.total_expense').html(loader);
        $('.total_purchase_return').html(loader);
        $('.total_sell_return').html(loader);
        $('.net').html(loader);
        $.ajax({
            method: 'get',
            url: "<?php echo e(url(config('service.route_prefix', 'service-pos') . '/home/get-totals'), false); ?>",
            dataType: 'json',
            data: data,
            success: function(data) {
                $('.total_purchase').html(__currency_trans_from_en(data.total_purchase, true));
                $('.purchase_due').html(__currency_trans_from_en(data.purchase_due, true));
                $('.total_sell').html(__currency_trans_from_en(data.total_sell, true));
                $('.invoice_due').html(__currency_trans_from_en(data.invoice_due, true));
                $('.total_expense').html(__currency_trans_from_en(data.total_expense, true));
                var total_purchase_return = data.total_purchase_return - data.total_purchase_return_paid;
                $('.total_purchase_return').html(__currency_trans_from_en(total_purchase_return, true));
                var total_sell_return_due = data.total_sell_return - data.total_sell_return_paid;
                $('.total_sell_return').html(__currency_trans_from_en(total_sell_return_due, true));
                $('.total_sr').html(__currency_trans_from_en(data.total_sell_return, true));
                $('.total_srp').html(__currency_trans_from_en(data.total_sell_return_paid, true));
                $('.total_pr').html(__currency_trans_from_en(data.total_purchase_return, true));
                $('.total_prp').html(__currency_trans_from_en(data.total_purchase_return_paid, true));
                $('.net').html(__currency_trans_from_en(data.net, true));

                var lang = $('#total_srp').data('value');
                var splitlang = lang.split('-');
                var newContent = "<p class='mb-0 text-muted fs-10 mt-5'>" + splitlang[0] + ": <span class=''>" + __currency_trans_from_en(data.total_sell_return, true) + "</span><br>" + splitlang[1] + ": <span class=''>" + __currency_trans_from_en(data.total_sell_return_paid, true) + "</span></p>";
                $('#total_srp').attr('data-content', newContent);

                lang = $('#total_prp').data('value');
                splitlang = lang.split('-');
                newContent = "<p class='mb-0 text-muted fs-10 mt-5'>" + splitlang[0] + ": <span class=''>" + __currency_trans_from_en(data.total_purchase_return, true) + "</span><br>" + splitlang[1] + ": <span class=''>" + __currency_trans_from_en(data.total_purchase_return_paid, true) + "</span></p>";
                $('#total_prp').attr('data-content', newContent);
            },
        });
    }
</script>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules/Service\Resources/views/home/partials/home_js.blade.php ENDPATH**/ ?>