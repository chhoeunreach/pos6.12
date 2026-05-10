(function ($) {
    'use strict';
    window.SmartStockInventory = {
        notify: function (msg, type) {
            if (window.swal) {
                swal({ title: type === 'error' ? 'Error' : 'Success', text: msg, icon: type || 'success' });
            }
        },
        showLoading: function () { $('#ssi_loading').show(); },
        hideLoading: function () { $('#ssi_loading').hide(); },
        beep: function () {
            try { new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEA').play(); } catch (e) {}
        }
    };

    $(function () {
        if (!$('#ssi_loading').length) {
            $('body').append('<div id="ssi_loading" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,.6);z-index:9999;text-align:center;padding-top:20%"><h4>Loading...</h4></div>');
        }
        $('.datatable').DataTable({ pageLength: 25, order: [] });
        $('.select2').select2({ width: '100%' });
        $(document).ajaxStart(function(){ SmartStockInventory.showLoading(); });
        $(document).ajaxStop(function(){ SmartStockInventory.hideLoading(); });
    });
})(jQuery);
