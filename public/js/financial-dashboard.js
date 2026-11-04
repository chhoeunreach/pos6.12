(function(window, $) {
    'use strict';

    function initFinancialExpenseDetails(options) {
        var modal = $('#finance-expense-details-modal');
        if (!modal.length) return;
        var labels = options.labels || {};
        var listUrl = '';
        var currentPage = 1;
        var lastPage = 1;
        var listSequence = 0;
        var editSequence = 0;
        var changed = false;
        var categories = [];
        var form = $('#finance-details-edit-form');
        var errorBox = $('#finance-details-error');
        var successBox = $('#finance-details-success');
        function refreshDashboard() {
            if (typeof options.refreshDashboard === 'function') options.refreshDashboard();
            else window.location.reload();
        }
        function money(value) {
            var number = Number(value || 0);
            return (number < 0 ? '(' : '') + (options.currencySymbol || '$') + Math.abs(number).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (number < 0 ? ')' : '');
        }
        function showError(xhr) {
            var response = xhr && xhr.responseJSON || {};
            var errors = response.errors ? Object.values(response.errors) : [];
            errorBox.text(errors.length ? errors[0][0] : (response.message || labels.error || 'Unable to load expenses.')).prop('hidden', false);
        }
        function loadPage(page) {
            var sequence = ++listSequence;
            errorBox.prop('hidden', true);
            $('#finance-details-table tbody').empty().append($('<tr>').append($('<td colspan="8">').text(labels.loading || 'Loading...')));
            $('#finance-details-prev, #finance-details-next').prop('disabled', true);
            $.ajax({
                url: listUrl, data: {page: page}, dataType: 'json',
                success: function(result) {
                    if (sequence !== listSequence) return;
                    currentPage = result.page;
                    lastPage = result.last_page;
                    var body = $('#finance-details-table tbody').empty();
                    (result.rows || []).forEach(function(row) {
                        var tr = $('<tr>');
                        [row.transaction_date, row.ref_no, row.location_name, labels[row.source] || row.source,
                            row.category_name === '__uncategorized__' ? (labels.none || '') : row.category_name,
                            money(row.amount), row.additional_notes].forEach(function(value) {
                            tr.append($('<td>').text(value == null ? '' : value));
                        });
                        var edit = $('<td>');
                        if (row.edit_url) edit.append($('<a class="finance-edit-expense finance-btn">').attr('href', row.edit_url).text(labels.edit || 'Edit'));
                        tr.append(edit);
                        body.append(tr);
                    });
                    if (!result.rows || !result.rows.length) body.append($('<tr>').append($('<td colspan="8">').text(labels.empty || 'No expenses.')));
                    $('#finance-details-count').text((labels.total || 'Total') + ': ' + result.total);
                    $('#finance-details-total').text(money(result.amount));
                    $('#finance-details-page').text(currentPage + ' / ' + lastPage);
                },
                error: function(xhr) { if (sequence === listSequence) showError(xhr); },
                complete: function() {
                    if (sequence !== listSequence) return;
                    $('#finance-details-prev').prop('disabled', currentPage <= 1);
                    $('#finance-details-next').prop('disabled', currentPage >= lastPage);
                },
            });
        }
        function subcategories(selected) {
            var parent = String($('#finance-edit-category').val() || '');
            var select = $('#finance-edit-subcategory').empty().append($('<option>').val('').text(labels.none || 'None'));
            categories.filter(function(category) { return parent && String(category.parent_id) === parent; }).forEach(function(category) {
                select.append($('<option>').val(category.id).text(category.name));
            });
            select.val(selected == null ? '' : String(selected));
        }
        $(document).on('click', '.finance-expense-details', function(event) {
            event.preventDefault();
            listUrl = $(this).attr('href');
            currentPage = lastPage = 1;
            form.prop('hidden', true);
            successBox.prop('hidden', true);
            modal.modal('show');
            loadPage(1);
        });
        $('#finance-details-prev').on('click', function() { if (currentPage > 1) loadPage(currentPage - 1); });
        $('#finance-details-next').on('click', function() { if (currentPage < lastPage) loadPage(currentPage + 1); });
        modal.on('click', '.finance-edit-expense', function(event) {
            event.preventDefault();
            var sequence = ++editSequence;
            errorBox.prop('hidden', true);
            successBox.prop('hidden', true);
            $.ajax({
                url: $(this).attr('href'), data: {report_lang: options.reportLanguage || 'km'}, dataType: 'json',
                success: function(result) {
                    if (sequence !== editSequence) return;
                    var expense = result.expense;
                    categories = result.categories || [];
                    form.attr('action', result.update_url).prop('hidden', false);
                    $('#finance-edit-ref').val(expense.ref_no || '');
                    $('#finance-edit-date').val(String(expense.transaction_date).replace(' ', 'T').slice(0, 19));
                    $('#finance-edit-amount').val(expense.final_total);
                    $('#finance-edit-note').val(expense.additional_notes || '');
                    $('#finance-edit-version').val(expense.updated_at);
                    var roots = $('#finance-edit-category').empty().append($('<option>').val('').text(labels.none || 'None'));
                    categories.filter(function(category) { return category.parent_id === null; }).forEach(function(category) {
                        roots.append($('<option>').val(category.id).text(category.name));
                    });
                    roots.val(expense.expense_category_id == null ? '' : String(expense.expense_category_id));
                    subcategories(expense.expense_sub_category_id);
                    form[0].scrollIntoView({behavior: 'smooth', block: 'nearest'});
                },
                error: function(xhr) { if (sequence === editSequence) showError(xhr); },
            });
        });
        $('#finance-edit-category').on('change', function() { subcategories(null); });
        $('#finance-edit-cancel').on('click', function() { ++editSequence; form.prop('hidden', true); });
        form.on('submit', function(event) {
            event.preventDefault();
            errorBox.prop('hidden', true);
            var buttons = form.find('button').prop('disabled', true);
            $.ajax({
                method: 'PUT', url: form.attr('action'), data: form.serialize(), dataType: 'json',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                success: function(result) {
                    if (!result.success) { showError({responseJSON: {message: result.msg}}); return; }
                    changed = true;
                    form.prop('hidden', true);
                    successBox.text(result.msg).prop('hidden', false);
                    if (!modal.hasClass('in')) refreshDashboard();
                    else loadPage(currentPage);
                },
                error: showError,
                complete: function() { buttons.prop('disabled', false); },
            });
        });
        modal.on('hidden.bs.modal', function() {
            ++editSequence;
            ++listSequence;
            if (changed) refreshDashboard();
        });
    }

    window.initFinancialDashboard = function(options) {
        var panel = document.getElementById('finance-filter-panel');
        if (!panel) return;
        options = options || {};
        initFinancialExpenseDetails(options);
        var preferenceKey = 'financial-dashboard.filters-open';
        try {
            var saved = window.localStorage.getItem(preferenceKey);
            if (saved !== null) panel.open = saved === 'true';
        } catch (error) {}
        panel.addEventListener('toggle', function() {
            try { window.localStorage.setItem(preferenceKey, String(panel.open)); } catch (error) {}
        });
        $('#finance-print-orientation').on('change', function() {
            var link = document.getElementById('finance-print');
            if (!link) return;
            var url = new URL(link.href, window.location.href);
            url.searchParams.set('orientation', this.value);
            link.href = url.toString();
        });

        var categories = $('#finance-expense-categories');
        categories.on('change', function() {
            $('#finance-expense-filter-applied').val('1');
            $('.finance-excluded-category').remove();
        });
        $('#finance-expenses-all').on('click', function() {
            categories.val(categories.find('option').map(function() { return this.value; }).get()).trigger('change');
        });
        $('#finance-expenses-none').on('click', function() { categories.val([]).trigger('change'); });
        $('#finance-open-category-filter').on('click', function() {
            panel.open = true;
            panel.scrollIntoView({behavior: 'smooth', block: 'start'});
            if ($.fn.select2 && categories.data('select2')) categories.select2('open');
            else categories.trigger('focus');
        });

        var range = $('#finance-date-range');
        var start = $('#finance-start');
        var end = $('#finance-end');
        if ($.fn.daterangepicker && typeof window.moment === 'function') {
            var format = typeof window.moment_date_format === 'string' ? window.moment_date_format : 'DD/MM/YYYY';
            var startDate = window.moment(start.val(), 'YYYY-MM-DD');
            var endDate = window.moment(end.val(), 'YYYY-MM-DD');
            range.daterangepicker($.extend(true, {}, window.dateRangeSettings || {}, options.dateSettings || {}, {
                ranges: options.ranges || {}, startDate: startDate, endDate: endDate,
                showDropdowns: true, autoUpdateInput: false, locale: {format: format},
            }), function(first, last) {
                start.val(first.format('YYYY-MM-DD'));
                end.val(last.format('YYYY-MM-DD'));
                range.val(first.format(format) + ' ~ ' + last.format(format));
            });
            range.val(startDate.format(format) + ' ~ ' + endDate.format(format));
        } else {
            // Keep date filtering usable if the shared date-picker assets fail to load.
            range.closest('.finance-field').hide();
            start.add(end).attr('type', 'date').addClass('form-control').prop('required', true);
            end.attr('min', start.val());
            start.on('change', function() { end.attr('min', this.value); });
        }
    };
})(window, jQuery);
