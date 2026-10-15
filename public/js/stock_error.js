(function($) {
    'use strict';

    window.POSStockError = {
        show: function(result) {
            var detail = result && result.stock_error;
            var $modal = $('#stock_error_modal');
            if (!detail || !$modal.length) {
                return false;
            }
            $('#stock_error_reason').text(detail.reason || result.msg || '');
            var $rows = $('#stock_error_details').empty();
            var fields = [
                ['Product', detail.product], ['SKU', detail.sku], ['Lot / Serial', detail.serial],
                ['Batch ID', detail.purchase_line_id], ['Source Location', detail.location],
                ['Requested Quantity', detail.requested_quantity],
                ['Recorded Available Quantity', detail.recorded_available],
                ['History Available Quantity', detail.history_available],
                ['System Available Quantity', detail.system_available]
            ];
            if (detail.overselling_enabled != null) {
                fields.push(['Allow overselling', detail.overselling_enabled ? 'Enabled' : 'Disabled']);
            }
            if (detail.business_id != null) {
                fields.push(['Business ID', detail.business_id]);
            }
            if (detail.stock_policy_version) {
                fields.push(['Stock validation version', detail.stock_policy_version]);
            }
            fields.forEach(function(field) {
                $('<tr>').append($('<th>').text(field[0]), $('<td>').text(field[1] == null ? '--' : field[1])).appendTo($rows);
            });
            var calculation = detail.calculation;
            if (calculation) {
                [
                    ['Received Quantity', calculation.received], ['Purchase Returns', calculation.purchase_returns],
                    ['Stock Adjustments', calculation.adjustments], ['Manufacturing Usage', calculation.manufacturing_used],
                    ['Recorded Consumption', calculation.recorded_consumption], ['History Consumption', calculation.history_consumption],
                    ['History Minus Recorded Balance', calculation.difference]
                ].forEach(function(field) {
                    $('<tr>').append($('<th>').text(field[0]), $('<td>').text(field[1] == null ? '--' : field[1])).appendTo($rows);
                });
                $('<tr>').append($('<th>').text('Balance Calculation'), $('<td>').text(
                    'Received - purchase returns - adjustments - manufacturing usage - net consumption')).appendTo($rows);
                $('<tr>').append($('<th>').text('System Availability Rule'), $('<td>').text(
                    'Lower of recorded and history balances, never below 0. Receipt, location and transfer dates must also be valid.')).appendTo($rows);
            }
            var $allocations = $('#stock_error_allocations').empty();
            (detail.related_allocations || []).forEach(function(text) {
                $('<li>').text(text).appendTo($allocations);
            });
            if (!$allocations.children().length) {
                $('<li>').text('No outstanding sale or transfer allocations found.').appendTo($allocations);
            }
            var $links = $('#stock_error_links').empty();
            (detail.links || []).forEach(function(link) {
                var target;
                try {
                    target = new URL(link.url, window.location.href);
                } catch (error) {
                    return;
                }
                if (target.origin !== window.location.origin || !/^https?:$/.test(target.protocol)) {
                    return;
                }
                $('<a>').addClass('btn btn-default').attr({href: target.href, target: '_blank', rel: 'noopener noreferrer'})
                    .css({marginRight: '8px', marginBottom: '8px', whiteSpace: 'normal'})
                    .append($('<i>').addClass('fa fa-external-link').attr('aria-hidden', 'true'), document.createTextNode(' ' + link.label))
                    .appendTo($links);
            });
            var $statusModal = $('#update_stock_transfer_status_modal');
            if ($statusModal.hasClass('in')) {
                $statusModal.one('hidden.bs.modal', function() { $modal.modal('show'); }).modal('hide');
            } else {
                $modal.modal('show');
            }
            return true;
        }
    };

    $(function() {
        var raw = $('#status_span').attr('data-stock-error');
        if (raw) {
            try {
                window.POSStockError.show({stock_error: JSON.parse(raw)});
            } catch (error) {
                // Keep the standard status message when a flash payload is unavailable.
            }
        }
    });
})(jQuery);
