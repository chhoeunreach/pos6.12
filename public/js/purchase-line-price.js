(function($) {
    'use strict';
    $(document).on('submit', '#purchase-line-price-form', function(event) {
        event.preventDefault();
        var form = $(this), status = $('#purchase-line-price-status');
        if (form.data('priceSubmitting') || !this.checkValidity()) return;
        var data = form.serialize();
        form.data('priceSubmitting', true).find('button, input').prop('disabled', true);
        status.attr('class', 'alert alert-info').text(form.attr('data-saving')).prop('hidden', false);
        function error(xhr) {
            var response = xhr.responseJSON || {}, errors = response.errors && Object.values(response.errors);
            status.attr('class', 'alert alert-danger').text(errors && errors.length ? errors[0][0] : (response.message || form.attr('data-error')));
        }
        $.ajax({url: form.attr('action'), method: 'PUT', data: data, dataType: 'json',
            headers: {'X-CSRF-TOKEN': form.find('[name="_token"]').val()},
            success: function(result) {
                if (!result.success) { error({responseJSON: result}); return; }
                form.find('[name="cost_version"]').val(result.cost_version);
                if (result.lots && result.lots[0]) $('#purchase-line-price').val(result.lots[0].purchase_price);
                status.attr('class', 'alert alert-success').text(form.attr('data-saved'));
            },
            error: error,
            complete: function() { form.data('priceSubmitting', false).find('button, input').prop('disabled', false); }
        });
    });
})(window.jQuery);
