<script type="text/javascript">
    function initExpenseContactSelect(selector) {
        var $selects = $(selector || '.contact_id_ajax');

        if (!$selects.length) {
            return;
        }

        $selects.each(function() {
            var $select = $(this);
            var options = {
                ajax: {
                    url: "{{ action([\App\Http\Controllers\ContactController::class, 'getCustomers']) }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term,
                            all_contact: true
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    }
                },
                minimumInputLength: 1,
                allowClear: true,
                placeholder: "{{ __('messages.please_select') }}",
                width: '100%'
            };

            if ($select.closest('.modal').length) {
                options.dropdownParent = $select.closest('.modal');
            }

            $select.select2(options);
        });
    }
</script>
