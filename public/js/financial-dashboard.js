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
        var listUrl, page = 1, lastPage = 1, sequence = 0, edited = false, costMode = false, searchTimer = null, sort = 'date', direction = 'desc';
        var controls = $('#finance-sales-controls'), controlsHome = $('<div>').insertBefore(controls);
        function xml(value) {
            return String(value == null ? '—' : value).replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/g, '').replace(/[&<>"']/g, function(c) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;'}[c];
            });
        }
        function download(blob, extension) {
            var url = window.URL.createObjectURL(blob), link = document.createElement('a');
            link.href = url;
            link.download = 'cost-of-goods-sold.' + extension;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(function() { window.URL.revokeObjectURL(url); }, 1000);
        }
        function excel(rows) {
            var zip = new window.JSZip();
            zip.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            zip.file('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            zip.file('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Cost of goods sold" sheetId="1" r:id="rId1"/></sheets></workbook>');
            zip.file('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            zip.file('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' + rows.map(function(row, index) {
                return '<row r="' + (index + 1) + '">' + row.map(function(value, col) {
                    var ref = String.fromCharCode(65 + col) + (index + 1);
                    return typeof value === 'number' && isFinite(value) ? '<c r="' + ref + '"><v>' + value + '</v></c>' : '<c r="' + ref + '" t="inlineStr"><is><t xml:space="preserve">' + xml(value) + '</t></is></c>';
                }).join('') + '</row>';
            }).join('') + '</sheetData></worksheet>');
            var settings = {type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'};
            return zip.generateAsync ? zip.generateAsync(settings) : Promise.resolve(zip.generate(settings));
        }
        modal.on('click', '.finance-cost-export', function() {
            if (!costMode || !listUrl) return;
            var format = $(this).attr('data-format'), requestSequence = sequence;
            var printWindow = format === 'print' ? window.open('', '_blank') : null;
            function error(message) { $('#finance-sales-error').text(message || labels.salesError || 'Unable to load sales.').prop('hidden', false); }
            if (format === 'print' && !printWindow) { error(labels.salesError); return; }
            var title = $('#finance-sales-title').text();
            var context = $('#finance-sales-basis').parent().text() + ($('#finance-cost-search').val() ? ' · ' + $('#finance-cost-search').val() : '');
            if (printWindow) printWindow.document.body.textContent = labels.loading || 'Loading...';
            var buttons = modal.find('.finance-cost-export').prop('disabled', true);
            $('#finance-sales-error').prop('hidden', true);
            $.ajax({url: listUrl, dataType: 'json', data: {page: 1, per_page: -1, sort: sort, direction: direction, q: $('#finance-cost-search').val() || ''},
                success: function(result) {
                    if (requestSequence !== sequence) { if (printWindow) printWindow.close(); return; }
                    var headings = $('#finance-sales-cost-head th').map(function() { return $(this).clone().find('.finance-sort-indicator').remove().end().text().trim(); }).get();
                    var rows = (result.rows || []).map(function(row) {
                        return [row.sku || '—', row.lots || '—', (row.product || '—') + (row.variation_name && row.variation_name !== 'DUMMY' ? ' · ' + row.variation_name : '')].concat(['units', 'purchase_price', 'selling_price', 'amount', 'profit_loss'].map(function(key) { return row[key] == null ? '—' : Number(row[key]); }));
                    });
                    rows.unshift(headings);
                    rows.push([labels.total || 'Total', '', '', Number(result.units || 0), '', '', result.amount == null ? '—' : Number(result.amount), result.profit_loss == null ? '—' : Number(result.profit_loss)]);
                    if (format === 'excel') {
                        try {
                            excel(rows).then(function(blob) { if (requestSequence === sequence) download(blob, 'xlsx'); }).catch(function() { error(); });
                        } catch (exception) { error(); }
                    } else if (format === 'csv') {
                        var csv = rows.map(function(row) { return row.map(function(value) {
                            var text = String(value);
                            if (typeof value !== 'number' && /^[\s\u0000-\u001f]*[=+@-]/.test(text)) text = "'" + text;
                            return '"' + text.replace(/"/g, '""') + '"';
                        }).join(','); }).join('\r\n');
                        download(new Blob(['\ufeff' + csv], {type: 'text/csv;charset=utf-8;'}), 'csv');
                    } else if (printWindow && !printWindow.closed) {
                        var doc = printWindow.document;
                        doc.title = title;
                        doc.documentElement.lang = options.reportLanguage || 'km';
                        doc.body.textContent = '';
                        $(doc.head).append($('<meta charset="utf-8">'), $('<style>').text('@page{size:landscape;margin:12mm}body{font:12px Arial,sans-serif;color:#173b67}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccd6e0;padding:8px;text-align:left}td:nth-child(n+4){text-align:right}thead,tfoot{background:#edf3fb}tr{break-inside:avoid}'));
                        $(doc.body).append($('<h2>').text(title), $('<p>').text(context));
                        var table = $('<table>'), head = $('<thead>').appendTo(table), body = $('<tbody>').appendTo(table), foot = $('<tfoot>').appendTo(table);
                        rows.forEach(function(row, index) {
                            var tr = $('<tr>').appendTo(index === 0 ? head : (index === rows.length - 1 ? foot : body));
                            row.forEach(function(value) { tr.append($(index === 0 ? '<th>' : '<td>').text(value)); });
                        });
                        $(doc.body).append(table);
                        printWindow.focus();
                        printWindow.print();
                    }
                },
                error: function(xhr) { if (printWindow) printWindow.close(); if (requestSequence === sequence) error((xhr.responseJSON || {}).message); },
                complete: function() { buttons.prop('disabled', false); }
            });
        });
        function load(nextPage) {
            var requestSequence = ++sequence;
            $('#finance-sales-table').attr('aria-busy', 'true');
            $('#finance-sales-error').prop('hidden', true);
            $('#finance-sales-summary, #finance-sales-page').text('');
            var body = $('#finance-sales-table tbody').empty();
            body.append($('<tr>').append($('<td>').attr('colspan', costMode ? 8 : 7).text(labels.loading || 'Loading...')));
            $('#finance-sales-first, #finance-sales-prev, #finance-sales-next, #finance-sales-last').prop('disabled', true);
            $.ajax({url: listUrl, data: {page: nextPage, per_page: Number($('#finance-sales-page-size').val() || 25), sort: sort, direction: direction, q: costMode ? ($('#finance-cost-search').val() || '') : ''}, dataType: 'json',
                success: function(result) {
                    if (requestSequence !== sequence) return;
                    $('#finance-sales-table').attr('aria-busy', 'false');
                    page = result.page;
                    lastPage = result.last_page;
                    body.empty();
                    function price(value) {
                        return value === null || value === undefined ? '—' : (options.currencySymbol || '$') + Number(value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                    (result.rows || []).forEach(function(row) {
                        var tr = $('<tr>');
                        if (costMode) {
                            tr.append($('<td>').append($('<code>').text(row.sku || '—')));
                            var lots = $('<td>');
                            if (row.lots) String(row.lots).split(',').forEach(function(lot) { lots.append($('<span class="finance-lot-tag">').text(lot.trim())); });
                            else lots.text('—');
                            tr.append(lots);
                            var product = $('<td class="finance-cost-product">').text((row.product || '—') + (row.variation_name && row.variation_name !== 'DUMMY' ? ' · ' + row.variation_name : ''));
                            var meta = $('<small class="finance-cost-meta">');
                            meta.append($('<span class="finance-cost-badge">').text(labels[row.source] || row.source));
                            meta.append(document.createTextNode([row.invoice_no, row.transaction_date].filter(Boolean).join(' · ')));
                            product.append(meta);
                            tr.append(product);
                            [row.units === null ? '—' : Number(row.units).toLocaleString(), price(row.purchase_price), price(row.selling_price), price(row.amount), price(row.profit_loss)].forEach(function(value, index) {
                                tr.append($('<td>').text(value).css('white-space', 'nowrap'));
                            });
                            if (Array.isArray(row.purchase_edit_links)) {
                                if (row.purchase_edit_links.length) {
                                    var purchaseCell = tr.find('td').eq(4).empty();
                                    var differentPurchaseCost = row.purchase_edit_links.some(function(purchase) {
                                        return Object.prototype.hasOwnProperty.call(purchase, 'purchase_price') &&
                                            (purchase.purchase_price === null || row.purchase_price === null || Math.abs(Number(purchase.purchase_price) - Number(row.purchase_price)) > 0.00005);
                                    });
                                    if (differentPurchaseCost) purchaseCell.append($('<small class="finance-cost-meta">')
                                        .text((labels.costUsed || 'Cost used') + ': ' + price(row.purchase_price)));
                                    row.purchase_edit_links.forEach(function(purchase, index) {
                                        if (index) purchaseCell.append($('<br>'));
                                        purchaseCell.append($('<a class="finance-cost-price-edit-link">')
                                            .attr('href', purchase.url).attr('title', purchase.lot + ' · ' + purchase.reference)
                                            .text((differentPurchaseCost ? (labels.latestPurchase || 'Latest purchase') + ': ' : '') +
                                                price(Object.prototype.hasOwnProperty.call(purchase, 'purchase_price') ? purchase.purchase_price : row.purchase_price) + ' ✎'));
                                        purchaseCell.append($('<small class="finance-cost-meta">').text(purchase.lot + ' · ' + purchase.reference));
                                    });
                                }
                            } else if (row.cost_edit_url) {
                                var cell = tr.find('td').eq(4);
                                cell.empty().append($('<button type="button" class="finance-cost-price-edit">')
                                    .attr('data-url', row.cost_edit_url).attr('data-price', row.purchase_price == null ? '' : row.purchase_price)
                                    .attr('data-version', row.cost_version || '').attr('title', labels.edit || 'Edit')
                                    .text(price(row.purchase_price) + ' ✎'));
                            }
                            if (row.estimated > 0) tr.find('td').eq(4).append($('<small class="finance-cost-estimate">').text(labels.estimated || 'Estimated cost'));
                            if (row.profit_loss !== null) tr.find('td').last().addClass(Number(row.profit_loss) < 0 ? 'finance-profit-negative' : 'finance-profit-positive');
                            if (Number(row.units) < 0) tr.addClass('finance-cost-return');
                        } else {
                            [row.transaction_date, row.invoice_no, row.customer_name, row.location_name,
                                row.units === null ? '—' : Number(row.units).toLocaleString(), price(row.amount)].forEach(function(value) {
                                tr.append($('<td>').text(value == null ? '' : value));
                            });
                            var actions = $('<td>');
                            if (row.edit_url) actions.append($('<a class="finance-sales-edit finance-btn" target="_blank" rel="noopener">').attr('href', row.edit_url).text(labels.edit || 'Edit'));
                            tr.append(actions);
                        }
                        body.append(tr);
                    });
                    if (!result.rows || !result.rows.length) body.append($('<tr>').append($('<td>').attr('colspan', costMode ? 8 : 7).text(labels.empty || 'No data.')));
                    $('#finance-sales-summary').text((labels.total || 'Total') + ': ' + result.total + ' · ' + (costMode ? (labels.cost || 'Cost') + ': ' + (result.amount === null ? '—' : (options.currencySymbol || '$') + Number(result.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : Number(result.units).toLocaleString() + ' ' + (labels.units || 'units')));
                    if (costMode) {
                        $('#finance-cost-stat-qty, #finance-cost-foot-qty').text(Number(result.units || 0).toLocaleString());
                        $('#finance-cost-stat-total, #finance-cost-foot-total').text(price(result.amount));
                        $('#finance-cost-stat-profit, #finance-cost-foot-profit').text(price(result.profit_loss))
                            .removeClass('finance-profit-positive finance-profit-negative').addClass(result.profit_loss == null ? '' : (Number(result.profit_loss) < 0 ? 'finance-profit-negative' : 'finance-profit-positive'));
                        var estimated = result.estimated_count || 0, missing = result.missing_count || 0;
                        $('#finance-cost-quality').prop('hidden', !estimated && !missing).text((labels.costQuality || ':estimated estimated costs; :missing missing costs.').replace(':estimated', estimated).replace(':missing', missing));
                    }
                    var size = Number($('#finance-sales-page-size').val() || 25);
                    var first = result.total ? (size === -1 ? 1 : (page - 1) * size + 1) : 0;
                    var last = result.total ? first + (result.rows || []).length - 1 : 0;
                    $('#finance-sales-range').text((labels.showing || 'Showing :from–:to of :total items').replace(':from', first).replace(':to', last).replace(':total', result.total));
                    $('#finance-sales-first').prop('disabled', page <= 1);
                    $('#finance-sales-last').prop('disabled', page >= lastPage);
                    $('#finance-sales-page').text('/ ' + lastPage);
                    $('#finance-sales-page-input').val(page).attr('max', lastPage);
                    $('#finance-sales-prev').prop('disabled', page <= 1);
                    $('#finance-sales-next').prop('disabled', page >= lastPage);
                },
                error: function(xhr) {
                    if (requestSequence !== sequence) return;
                    $('#finance-sales-table').attr('aria-busy', 'false');
                    body.empty();
                    $('#finance-sales-error').text((xhr.responseJSON || {}).message || labels.salesError || 'Unable to load sales.').prop('hidden', false);
                }
            });
        }
        $(document).on('click', '.finance-sales-details', function(event) {
            event.preventDefault();
            listUrl = $(this).attr('href');
            var nextCostMode = $(this).attr('data-cost-details') === '1';
            if (nextCostMode !== costMode) { sort = nextCostMode ? 'sku' : 'date'; direction = nextCostMode ? 'asc' : 'desc'; }
            costMode = nextCostMode;
            clearTimeout(searchTimer);
            $('#finance-cost-search').val('');
            modal.toggleClass('finance-cost-mode', costMode);
            if (costMode) controls.appendTo(modal.find('.finance-cost-tools'));
            else controls.insertAfter(controlsHome);
            modal.find('.finance-cost-export-actions').prop('hidden', !costMode);
            modal.find('.finance-cost-tools, .finance-cost-stats, #finance-cost-totals').prop('hidden', !costMode);
            $('#finance-cost-quality').prop('hidden', true);
            $('#finance-cost-stat-qty, #finance-cost-stat-total, #finance-cost-stat-profit, #finance-cost-foot-qty, #finance-cost-foot-total, #finance-cost-foot-profit').text('—');
            $('#finance-sales-normal-head').prop('hidden', costMode);
            $('#finance-sales-cost-head').prop('hidden', !costMode);
            modal.find('.finance-sales-sort').each(function() {
                var active = $(this).attr('data-sort') === sort;
                $(this).closest('th').attr('aria-sort', active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
                $(this).find('.finance-sort-indicator').text(active ? (direction === 'asc' ? '▲' : '▼') : '↕');
            });
            $('#finance-sales-title').text(costMode ? (labels.costTitle || 'Cost of goods sold details') : (labels.salesTitle || 'POS sales list'));
            $('#finance-sales-amount-label').text(costMode ? (labels.cost || 'Cost') : (labels.amount || 'Amount'));
            $('#finance-sales-basis').text(costMode ? labels.costLineBasis : labels.unitsBasis);
            page = lastPage = 1;
            modal.modal('show');
            load(1);
        });
        if (typeof window.initPurchaseLotPriceModal === 'function') window.initPurchaseLotPriceModal({
            modal: '#finance-purchase-price-modal', trigger: '.finance-cost-price-edit', labels: {error: labels.salesError},
            saved: function() {
                edited = true;
                if (modal.hasClass('in')) load(page);
                else if (typeof options.refreshDashboard === 'function') options.refreshDashboard();
                else window.location.reload();
            }
        });
        $('#finance-cost-search').on('input', function() {
            clearTimeout(searchTimer);
            ++sequence;
            searchTimer = setTimeout(function() { load(1); }, 300);
        });
        $('#finance-cost-clear-search').on('click', function() { clearTimeout(searchTimer); $('#finance-cost-search').val(''); load(1); });
        $('#finance-sales-first').on('click', function() { if (page > 1) load(1); });
        $('#finance-sales-last').on('click', function() { if (page < lastPage) load(lastPage); });
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
        var purchaseModal = $('#finance-purchase-edit-modal'), purchaseFrame = $('#finance-purchase-edit-frame');
        modal.on('click', '.finance-cost-price-edit-link', function(event) {
            event.preventDefault();
            $('#finance-purchase-edit-title').text((labels.edit || 'Edit') + ' · ' + $(this).attr('title'));
            $('#finance-purchase-edit-loading').prop('hidden', false);
            purchaseFrame.attr('src', $(this).attr('href'));
            purchaseModal.modal('show');
        });
        purchaseFrame.on('load', function() {
            if ($(this).attr('src') === 'about:blank') return;
            $('#finance-purchase-edit-loading').prop('hidden', true);
            // Keep the original purchase form and its scripts in their own document.
            try {
                var doc = this.contentDocument;
                if (doc && !doc.getElementById('finance-purchase-embed-style')) {
                    var style = doc.createElement('style');
                    style.id = 'finance-purchase-embed-style';
                    style.textContent = '.main-header,.main-sidebar,.main-footer,.control-sidebar,#main_app_header,.side-bar{display:none!important}.content-wrapper{margin-left:0!important}.thetop>main{width:100%!important}';
                    doc.head.appendChild(style);
                }
            } catch (error) { /* The browser keeps cross-origin documents isolated. */ }
        });
        purchaseModal.on('shown.bs.modal', function() { $('.modal-backdrop').last().css('z-index', 1060); });
        purchaseModal.on('hidden.bs.modal', function() {
            purchaseFrame.attr('src', 'about:blank');
            edited = true;
            if (modal.hasClass('in')) {
                $('body').addClass('modal-open');
                var instance = modal.data('bs.modal');
                if (instance && typeof instance.enforceFocus === 'function') instance.enforceFocus();
                load(page);
            } else if (typeof options.refreshDashboard === 'function') options.refreshDashboard();
            else window.location.reload();
        });
        modal.on('click', '.finance-sales-edit', function() { edited = true; });
        $(window).on('focus', function() { if (edited && modal.hasClass('in') && !$('#finance-purchase-price-modal, #finance-purchase-edit-modal').hasClass('in')) load(page); });
        modal.on('hidden.bs.modal', function() {
            clearTimeout(searchTimer);
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

    function initFinancialPosReport(options) {
        var modal = $('#finance-pos-report-modal');
        var frame = $('#finance-pos-report-frame');
        if (!modal.length) return;
        $(document).on('click', '.finance-pos-report-details', function(event) {
            event.preventDefault();
            var url = new URL($(this).attr('href'), window.location.href);
            if (url.origin !== window.location.origin) return;
            $('#finance-pos-report-loading').prop('hidden', false);
            frame.attr('src', url.toString());
            url.searchParams.delete('finance_embed');
            $('#finance-pos-report-tab').attr('href', url.toString());
            modal.modal('show');
        });
        frame.on('load', function() { $('#finance-pos-report-loading').prop('hidden', true); });
        window.addEventListener('message', function(event) {
            if (event.origin === window.location.origin && event.source === frame[0].contentWindow && event.data && event.data.type === 'financial-purchase-cost-updated') modal.data('purchaseChanged', true);
        });
        modal.on('hidden.bs.modal', function() {
            frame.attr('src', 'about:blank');
            if (modal.data('purchaseChanged')) {
                modal.removeData('purchaseChanged');
                if (typeof options.refreshDashboard === 'function') options.refreshDashboard();
                else window.location.reload();
            }
        });
    }

    window.initFinancialDashboard = function(options) {
        var panel = document.getElementById('finance-filter-panel');
        if (!panel) return;
        options = options || {};
        initFinancialExpenseDetails(options);
        initFinancialSalesDetails(options);
        initFinancialSelection(options);
        initFinancialPosReport(options);
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
