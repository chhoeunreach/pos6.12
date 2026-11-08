(function($) {
    'use strict';
    $(function() {
        window.initPurchaseLotPriceModal({modal: '#purchase-row-price-modal', trigger: '.purchase-row-price-edit',
            saved: function(result) {
                (result.lots || []).forEach(function(lot) {
                    $('tr[data-purchase-row="' + Number(lot.id) + '"] .purchase-row-price').text(lot.purchase_price);
                });
            }
        });
    });
})(window.jQuery);
