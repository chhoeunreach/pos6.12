(function($) {
    'use strict';
    var modal = $('#purchase-price-history-modal'), frame = $('#purchase-price-history-frame');
    $(document).on('click', '.purchase-price-history-link', function(event) {
        event.preventDefault();
        var url = new URL($(this).attr('href'), window.location.href);
        if (url.origin !== window.location.origin) return;
        var title = modal.attr('data-title') + ' · ' + ($(this).attr('data-sku') || $(this).text());
        $('#purchase-price-history-title').text(title);
        $('#purchase-price-history-loading').prop('hidden', false);
        frame.attr('title', title).attr('src', url.toString());
        modal.modal('show');
    });
    frame.on('load', function() { if (frame.attr('src') !== 'about:blank') $('#purchase-price-history-loading').prop('hidden', true); });
    modal.on('shown.bs.modal', function() { $('.modal-backdrop').last().css('z-index', 1060); });
    modal.on('hidden.bs.modal', function() {
        frame.attr('src', 'about:blank');
        var parent = $('.modal.in').last();
        if (parent.length) {
            $('body').addClass('modal-open');
            var instance = parent.data('bs.modal');
            if (instance && typeof instance.enforceFocus === 'function') instance.enforceFocus();
        }
    });
})(window.jQuery);
