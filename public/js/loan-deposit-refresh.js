(function(window, $) {
    'use strict';
    if (!$) return;
    window.addEventListener('message', function(event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'loan-deposit-payments-changed') return;
        var owner = null;
        document.querySelectorAll('.modal iframe').forEach(function(frame) {
            if (frame.contentWindow === event.source) owner = $(frame).closest('.modal');
        });
        if (!owner || !owner.length) return;
        owner.data('loanDepositChanged', true);
        if (window.parent !== window) window.parent.postMessage(event.data, window.location.origin);
    });
    $(document).on('hidden.bs.modal.loanDepositRefresh', '.modal', function(event) {
        if (event.target !== this || !$(this).data('loanDepositChanged')) return;
        $(this).removeData('loanDepositChanged');
        window.location.reload();
    });
})(window, window.jQuery);
