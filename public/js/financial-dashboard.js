(function(window, $) {
    'use strict';

    function initFinancialExpenseDetails(options) {
        var modal = $('#finance-expense-details-modal');
        if (!modal.length) return;
        var editor = $('#finance-expense-edit-modal');
        var editorOpen = false;
        var reloadList = false;
        var labels = options.labels || {};
        var listUrl = '';
        var currentPage = 1;
        var expenseSort = 'date', expenseDirection = 'desc';
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
        function showError(xhr, editing) {
            var response = xhr && xhr.responseJSON || {};
            var errors = response.errors ? Object.values(response.errors) : [];
            (editing ? $('#finance-edit-error') : errorBox).text(errors.length ? errors[0][0] : (response.message || labels.error || 'Unable to load expenses.')).prop('hidden', false);
        }
        function loadPage(page) {
            var sequence = ++listSequence;
            errorBox.prop('hidden', true);
            $('#finance-details-table tbody').empty().append($('<tr>').append($('<td colspan="8">').text(labels.loading || 'Loading...')));
            $('#finance-details-prev, #finance-details-next').prop('disabled', true);
            $.ajax({
                url: listUrl, data: {page: page, per_page: Number($('#finance-details-page-size').val() || 25), sort: expenseSort, direction: expenseDirection}, dataType: 'json',
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
                    $('#finance-details-page').text('/ ' + lastPage);
                    $('#finance-details-page-input').val(currentPage).attr('max', lastPage);
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
        $('#finance-details-page-size').on('change', function() { loadPage(1); });
        $('#finance-details-page-input').on('change', function() { loadPage(Math.max(1, Math.min(lastPage, Number(this.value) || 1))); });
        $('#finance-details-refresh').on('click', function() { loadPage(currentPage); });
        modal.on('click', '.finance-expense-sort', function() {
            var nextSort = $(this).attr('data-sort');
            expenseDirection = expenseSort === nextSort ? (expenseDirection === 'asc' ? 'desc' : 'asc') : 'asc';
            expenseSort = nextSort;
            modal.find('.finance-expense-sort').each(function() {
                var active = $(this).attr('data-sort') === expenseSort;
                $(this).closest('th').attr('aria-sort', active ? (expenseDirection === 'asc' ? 'ascending' : 'descending') : 'none');
                $(this).find('span').text(active ? (expenseDirection === 'asc' ? '▲' : '▼') : '↕');
            });
            loadPage(1);
        });
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
                    $('#finance-edit-error').prop('hidden', true);
                    editorOpen = true;
                    modal.one('hidden.bs.modal', function() { editor.modal('show'); });
                    modal.modal('hide');
                },
                error: function(xhr) { if (sequence === editSequence) showError(xhr); },
            });
        });
        $('#finance-edit-category').on('change', function() { subcategories(null); });
        $('#finance-edit-cancel').on('click', function() { editor.modal('hide'); });
        form.on('submit', function(event) {
            event.preventDefault();
            errorBox.prop('hidden', true);
            $('#finance-edit-error').prop('hidden', true);
            var buttons = editor.find('button').prop('disabled', true);
            $.ajax({
                method: 'PUT', url: form.attr('action'), data: form.serialize(), dataType: 'json',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                success: function(result) {
                    if (!result.success) { showError({responseJSON: {message: result.msg}}, true); return; }
                    changed = true;
                    form.prop('hidden', true);
                    successBox.text(result.msg).prop('hidden', false);
                    reloadList = true;
                    editor.modal('hide');
                },
                error: function(xhr) { showError(xhr, true); },
                complete: function() { buttons.prop('disabled', false); },
            });
        });
        editor.on('hidden.bs.modal', function() {
            ++editSequence;
            editorOpen = false;
            form.prop('hidden', true);
            modal.modal('show');
            if (reloadList) { reloadList = false; loadPage(currentPage); }
        });
        modal.on('hidden.bs.modal', function() {
            if (editorOpen) return;
            ++editSequence;
            ++listSequence;
            if (changed) refreshDashboard();
        });
    }

    function initFinancialSalesDetails(options) {
        var modal = $('#finance-sales-modal');
        if (!modal.length) return;
        var labels = options.labels || {};
        var listUrl, page = 1, lastPage = 1, sequence = 0, edited = false, costMode = false, sort = 'date', direction = 'desc';
        function load(nextPage) {
            var requestSequence = ++sequence;
            $('#finance-sales-error').prop('hidden', true);
            $('#finance-sales-summary, #finance-sales-page').text('');
            var body = $('#finance-sales-table tbody').empty();
            body.append($('<tr>').append($('<td>').attr('colspan', costMode ? 8 : 7).text(labels.loading || 'Loading...')));
            $('#finance-sales-prev, #finance-sales-next').prop('disabled', true);
            $.ajax({url: listUrl, data: {page: nextPage, per_page: Number($('#finance-sales-page-size').val() || 25), sort: sort, direction: direction}, dataType: 'json',
                success: function(result) {
                    if (requestSequence !== sequence) return;
                    page = result.page;
                    lastPage = result.last_page;
                    body.empty();
                    (result.rows || []).forEach(function(row) {
                        var tr = $('<tr>');
                        [row.transaction_date, row.invoice_no, row.customer_name, row.location_name,
                            row.units === null ? '—' : Number(row.units).toLocaleString(), row.amount === null ? '—' : (options.currencySymbol || '$') + Number(row.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})].forEach(function(value) {
                            tr.append($('<td>').text(value == null ? '' : value));
                        });
                        if (costMode && row.estimated > 0) tr.find('td').last().append($('<small>').text(' (' + (labels.estimated || 'Estimated cost') + ')'));
                        if (costMode) tr.append($('<td>').text(labels[row.source] || row.source));
                        var actions = $('<td>');
                        if (row.edit_url) actions.append($('<a class="finance-sales-edit finance-btn" target="_blank" rel="noopener">').attr('href', row.edit_url).text(labels.edit || 'Edit'));
                        tr.append(actions);
                        body.append(tr);
                    });
                    if (!result.rows || !result.rows.length) body.append($('<tr>').append($('<td>').attr('colspan', costMode ? 8 : 7).text(labels.empty || 'No data.')));
                    $('#finance-sales-summary').text((labels.total || 'Total') + ': ' + result.total + ' · ' + (costMode ? (labels.cost || 'Cost') + ': ' + (result.amount === null ? '—' : (options.currencySymbol || '$') + Number(result.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : Number(result.units).toLocaleString() + ' ' + (labels.units || 'units')));
                    $('#finance-sales-page').text('/ ' + lastPage);
                    $('#finance-sales-page-input').val(page).attr('max', lastPage);
                    $('#finance-sales-prev').prop('disabled', page <= 1);
                    $('#finance-sales-next').prop('disabled', page >= lastPage);
                },
                error: function(xhr) {
                    if (requestSequence !== sequence) return;
                    body.empty();
                    $('#finance-sales-error').text((xhr.responseJSON || {}).message || labels.salesError || 'Unable to load sales.').prop('hidden', false);
                }
            });
        }
        $(document).on('click', '.finance-sales-details', function(event) {
            event.preventDefault();
            listUrl = $(this).attr('href');
            costMode = $(this).attr('data-cost-details') === '1';
            $('#finance-sales-title').text(costMode ? (labels.costTitle || 'Cost of goods sold details') : (labels.salesTitle || 'POS sales list'));
            $('#finance-sales-amount-label').text(costMode ? (labels.cost || 'Cost') : (labels.amount || 'Amount'));
            $('#finance-sales-basis').text(costMode ? labels.costBasis : labels.unitsBasis);
            modal.find('.finance-cost-source').prop('hidden', !costMode);
            page = lastPage = 1;
            modal.modal('show');
            load(1);
        });
        $('#finance-sales-prev').on('click', function() { if (page > 1) load(page - 1); });
        $('#finance-sales-next').on('click', function() { if (page < lastPage) load(page + 1); });
        modal.on('click', '.finance-sales-sort', function() {
            var nextSort = $(this).attr('data-sort');
            direction = sort === nextSort ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
            sort = nextSort;
            modal.find('.finance-sales-sort').each(function() {
                var active = $(this).attr('data-sort') === sort;
                $(this).closest('th').attr('aria-sort', active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
                $(this).find('.finance-sort-indicator').text(active ? (direction === 'asc' ? '▲' : '▼') : '↕');
            });
            load(1);
        });
        $('#finance-sales-page-size').on('change', function() { load(1); });
        $('#finance-sales-page-input').on('change', function() { load(Math.max(1, Math.min(lastPage, Number(this.value) || 1))); });
        $('#finance-sales-refresh').on('click', function() { load(page); });
        modal.on('click', '.finance-sales-edit', function() { edited = true; });
        $(window).on('focus', function() { if (edited && modal.hasClass('in')) load(page); });
        modal.on('hidden.bs.modal', function() {
            ++sequence;
            if (edited) {
                if (typeof options.refreshDashboard === 'function') options.refreshDashboard();
                else window.location.reload();
            }
        });
    }

    function initFinancialSelection(options) {
        var modal = $('#finance-selection-modal');
        if (!modal.length) return;
        var activeSelect = null, activeTrigger = null;
        var labels = options.labels || {};
        function updateTrigger(trigger) {
            var selected = $('#' + trigger.attr('data-select')).find('option:selected');
            var names = selected.map(function() { return $(this).text(); }).get();
            trigger.text(names.length ? (names.length <= 2 ? names.join(', ') : names.length + ' ' + (labels.selected || 'selected')) : (labels.none || 'None'));
            trigger.attr('title', names.join(', '));
        }
        function count() {
            $('#finance-selection-count').text(modal.find('input[type="checkbox"]:checked').length + ' ' + (labels.selected || 'selected'));
        }
        $('.finance-selection-trigger').each(function() {
            var trigger = $(this);
            updateTrigger(trigger);
            $('#' + trigger.attr('data-select')).on('change', function() { updateTrigger(trigger); });
        });
        $(document).on('click', '.finance-selection-trigger', function() {
            activeTrigger = $(this);
            activeSelect = $('#' + activeTrigger.attr('data-select'));
            $('#finance-selection-title').text(activeTrigger.attr('data-title'));
            $('#finance-selection-search').val('');
            var list = $('#finance-selection-options').empty();
            activeSelect.find('option').each(function() {
                var row = $('<label class="finance-selection-option">');
                row.append($('<input type="checkbox">').val(this.value).prop('checked', this.selected));
                row.append($('<span>').text($(this).text()));
                list.append(row);
            });
            count();
            modal.modal('show');
        });
        $('#finance-selection-search').on('input', function() {
            var term = this.value.trim().toLocaleLowerCase();
            modal.find('.finance-selection-option').each(function() { $(this).prop('hidden', $(this).text().toLocaleLowerCase().indexOf(term) === -1); });
        });
        modal.on('change', 'input[type="checkbox"]', count);
        $('#finance-selection-all').on('click', function() { modal.find('input[type="checkbox"]').prop('checked', true); count(); });
        $('#finance-selection-none').on('click', function() { modal.find('input[type="checkbox"]').prop('checked', false); count(); });
        $('#finance-selection-apply').on('click', function() {
            if (!activeSelect) return;
            var values = modal.find('input[type="checkbox"]:checked').map(function() { return this.value; }).get();
            activeSelect.val(values).trigger('change');
            modal.modal('hide');
        });
        modal.on('shown.bs.modal', function() { $('#finance-selection-search').trigger('focus'); });
        modal.on('hidden.bs.modal', function() { if (activeTrigger) activeTrigger.trigger('focus'); });
    }

    window.initFinancialDashboard = function(options) {
        var panel = document.getElementById('finance-filter-panel');
        if (!panel) return;
        options = options || {};
        initFinancialExpenseDetails(options);
        initFinancialSalesDetails(options);
        initFinancialSelection(options);
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
            if ($('#finance-selection-modal').length) { $('#finance-choose-expenses').trigger('click'); return; }
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
