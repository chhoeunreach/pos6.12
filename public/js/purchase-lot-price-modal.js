(function(window, $) {
    'use strict';
    window.initPurchaseLotPriceModal = function(options) {
        var modal = $(options.modal), form = modal.find('form'), fields = modal.find('.purchase-lot-price-fields');
        if (!modal.length) return;
        var sequence = 0, saving = false, labels = options.labels || {};
        function error(xhr) {
            var response = xhr && xhr.responseJSON || {}, errors = response.errors && Object.values(response.errors);
            modal.find('.purchase-lot-price-error').text(errors && errors.length ? errors[0][0] : (response.message || labels.error || 'Unable to update purchase price.')).prop('hidden', false);
        }
        function disable(value) { modal.find('button, input').prop('disabled', value); }
        $(document).on('click', options.trigger, function(event) {
            event.preventDefault();
            if (saving) return;
            var current = ++sequence;
            fields.empty(); form.removeAttr('action');
            modal.find('.purchase-lot-price-error').prop('hidden', true);
            modal.find('.purchase-lot-price-loading').prop('hidden', false);
            disable(false); modal.find('.purchase-lot-price-save').prop('disabled', true);
            modal.modal('show');
            $.ajax({url: $(this).attr('data-url'), dataType: 'json',
                success: function(result) {
                    if (current !== sequence) return;
                    form.attr('action', result.update_url);
                    fields.append($('<input type="hidden" name="cost_version">').val(result.cost_version));
                    (result.lots || []).forEach(function(lot, index) {
                        var group = $('<div class="form-group">').css({background: '#f6f8fc', padding: '12px', borderRadius: '7px'});
                        var id = modal.attr('id') + '-price-' + index;
                        group.append($('<label>').attr('for', id).text((lot.lot || '#' + lot.id) + (lot.reference ? ' · ' + lot.reference : '')));
                        group.append($('<input class="form-control" type="number" min="0" max="999999999999" step="0.0001" required>')
                            .attr({id: id, name: 'prices[' + index + '][purchase_price]'}).val(lot.purchase_price == null ? '' : lot.purchase_price)
                            .css({background: '#fff', color: '#173b67', fontSize: '18px', height: '44px'}));
                        group.append($('<input type="hidden">').attr('name', 'prices[' + index + '][id]').val(lot.id));
                        fields.append(group);
                    });
                    modal.find('.purchase-lot-price-loading').prop('hidden', true);
                    modal.find('.purchase-lot-price-save').prop('disabled', !(result.lots || []).length);
                    fields.find('input[type=number]').first().trigger('focus').trigger('select');
                },
                error: function(xhr) { if (current === sequence) { modal.find('.purchase-lot-price-loading').prop('hidden', true); error(xhr); } }
            });
        });
        form.on('submit', function(event) {
            event.preventDefault();
            if (saving || !form.attr('action') || !form[0].checkValidity()) return;
            var data = form.serialize(); saving = true; disable(true);
            modal.find('.purchase-lot-price-error').prop('hidden', true);
            $.ajax({url: form.attr('action'), method: 'PUT', data: data, dataType: 'json',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                success: function(result) {
                    if (!result.success) { error({responseJSON: result}); return; }
                    saving = false;
                    modal.modal('hide');
                    if (typeof options.saved === 'function') options.saved(result);
                },
                error: error,
                complete: function() { saving = false; disable(false); }
            });
        });
        modal.on('shown.bs.modal', function() { $('.modal-backdrop').last().css('z-index', 1060); });
        modal.on('hide.bs.modal', function(event) { if (saving) event.preventDefault(); });
        modal.on('hidden.bs.modal', function() {
            ++sequence; fields.empty(); form.removeAttr('action');
            var parent = $('.modal.in').last();
            if (parent.length) {
                $('body').addClass('modal-open');
                var instance = parent.data('bs.modal');
                if (instance && typeof instance.enforceFocus === 'function') instance.enforceFocus();
            }
        });
    };
})(window, window.jQuery);
