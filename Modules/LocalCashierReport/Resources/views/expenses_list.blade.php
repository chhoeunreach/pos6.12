@extends('layouts.app')
@php
    $currentReportLang = \Modules\LocalCashierReport\Support\ReportLanguage::current();
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key, $currentReportLang);
@endphp
@section('title', $t('expenses_list'))

@section('content')
<style>
    @font-face {
        font-family: 'KhmerFont';
        src: url('{{ asset("fonts/khmer/NotoSansKhmer-Regular.ttf") }}') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
</style>
@php
    $fmt = function ($value) {
        if ($value === null || abs((float) $value) < 0.00001) {
            return '$ -';
        }
        if ((float) $value < 0) {
            return '$ (' . number_format(abs((float) $value), 2) . ')';
        }
        return '$ ' . number_format((float) $value, 2);
    };
    $fmtStrict = function ($value) {
        $number = (float) ($value ?? 0);
        if ($number < 0) {
            return '$ (' . number_format(abs($number), 2) . ')';
        }
        return '$ ' . number_format($number, 2);
    };

    $staticPaymentAmount = function ($row, $column) {
        $payments = (array) data_get($row, 'payments', []);
        $sources = (array) data_get($column, 'source_methods', []);
        $amount = 0.0;
        foreach ($sources as $source) {
            $amount += (float) ($payments[$source] ?? 0);
        }
        return $amount;
    };

    $expenseLocations = $expenseRows->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $expenseCashiers = $expenseRows->pluck('created_by_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ $t('expenses_list') }}
    </h1>
</section>

<!-- Main content -->
<section class="content no-print" id="local_cashier_report_app" style="font-family: {{ $khmerFontFamily }};">
    <div class="local-filter-wrap">
        <a href="{{ route('local-cashier-report.expenses-list', ['report_lang' => $currentReportLang]) }}" class="btn btn-sm local-filter-reset">
            <i class="fa fa-refresh"></i> {{ $t('reset') }}
        </a>
    </div>

    @component('components.filters', ['title' => $t('filters')])
        <form method="get" action="{{ route('local-cashier-report.expenses-list', ['report_lang' => $currentReportLang]) }}" class="row" id="expenses_filter_form">
            <input type="hidden" name="report_lang" value="{{ $currentReportLang }}">
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>{{ $t('date_range') }}</label>
                    <input type="text" id="date_range_picker" class="form-control" readonly>
                    <input type="hidden" name="start_date" id="start_date" value="{{ $filters['start_date'] }}">
                    <input type="hidden" name="end_date" id="end_date" value="{{ $filters['end_date'] }}">
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>{{ $t('business_location') }}</label>
                    <input type="text" id="location_preview" class="form-control" readonly
                           value="{{ $locations->whereIn('id', $filters['location_ids'])->pluck('name')->implode(', ') }}">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#location_modal" style="margin-top:6px;">
                        {{ $t('select_locations') }}
                    </button>
                    <select name="location_ids[]" id="location_ids_hidden" class="form-control" multiple style="display:none;">
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @if(in_array($location->id, $filters['location_ids'])) selected @endif>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>{{ $t('cashier_user') }}</label>
                    <input type="text" id="cashier_preview" class="form-control" readonly
                           value="{{ $cashiers->whereIn('id', $filters['user_ids'])->pluck('name')->implode(', ') }}">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#cashier_modal" style="margin-top:6px;">
                        {{ $t('select_cashiers') }}
                    </button>
                    <select name="user_ids[]" id="user_ids_hidden" class="form-control" multiple style="display:none;">
                        @foreach($cashiers as $cashier)
                            <option value="{{ $cashier->id }}" @if(in_array($cashier->id, $filters['user_ids'])) selected @endif>{{ $cashier->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>{{ $t('expense_category') }}</label>
                    <select name="expense_category_id" id="expense_category_id" class="form-control select2" style="width: 100%;">
                        <option value="">{{ $t('all_categories') }}</option>
                        @foreach($categories as $catId => $catName)
                            <option value="{{ $catId }}" @if(($filters['expense_category_id'] ?? null) == $catId) selected @endif>{{ $catName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>{{ $t('payment_status') }}</label>
                    <select name="payment_status" id="payment_status" class="form-control select2" style="width: 100%;">
                        <option value="">{{ $t('all_payment_statuses') }}</option>
                        @foreach($paymentStatuses as $status)
                            <option value="{{ $status }}" @if(($filters['payment_status'] ?? '') === $status) selected @endif>{{ $t($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-3 col-sm-6" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-filter"></i> {{ $t('apply_filters') }}
                </button>
                <a href="{{ route('local-cashier-report.expenses-list', ['report_lang' => $currentReportLang]) }}" class="btn btn-default">
                    {{ $t('reset') }}
                </a>
            </div>
        </form>
    @endcomponent

    <!-- KPI Summary Grid -->
    <div class="summary-kpi-grid">
        <div class="summary-kpi-card">
            <div class="kpi-label">{{ $t('total_expenses') }}</div>
            <div class="kpi-value">{{ $fmt($summary['total_amount'] ?? 0) }}</div>
        </div>
        <div class="summary-kpi-card">
            <div class="kpi-label">{{ $t('total_paid') }}</div>
            <div class="kpi-value">{{ $fmt($summary['total_paid'] ?? 0) }}</div>
        </div>
        <div class="summary-kpi-card">
            <div class="kpi-label">{{ $t('total_due') }}</div>
            <div class="kpi-value">{{ $fmt($summary['total_due'] ?? 0) }}</div>
        </div>
        <div class="summary-kpi-card">
            <div class="kpi-label">{{ $t('expense_count') }}</div>
            <div class="kpi-value">{{ number_format($summary['displayed_count'] ?? 0) }} / {{ number_format($summary['total_count'] ?? 0) }}</div>
        </div>
    </div>

    <!-- Expenses Table Card -->
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title" style="font-weight: 700;">
                <i class="fa fa-list-alt text-primary"></i> {{ $t('expenses_list') }}
            </h3>
            <div class="box-tools pull-right">
                <span class="badge bg-primary" style="font-size: 13px; padding: 6px 12px; border-radius: 999px;">
                    {{ number_format($summary['displayed_count'] ?? 0) }} {{ $t('rows') }}
                </span>
            </div>
        </div>
        <div class="box-body">
            <div class="sale-table-filter-toggle">
                <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#local_cashier_expenses_detail_table_filters" aria-expanded="false" aria-controls="local_cashier_expenses_detail_table_filters">
                    <i class="fa fa-filter"></i> {{ $t('filters') }}
                </button>
            </div>
            <div class="collapse" id="local_cashier_expenses_detail_table_filters">
                <div class="row all-sale-table-filters">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>{{ $t('location') }}</label>
                            <select class="form-control select2 all-sale-location-filter" data-table-id="local_cashier_expenses_detail_table" multiple data-placeholder="{{ $t('all_locations') }}">
                                @foreach($expenseLocations as $locationName)
                                    <option value="{{ $locationName }}">{{ $locationName }}</option>
                                @endforeach
                            </select>
                            <div class="all-sale-filter-actions">
                                <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-location-filter" data-table-id="local_cashier_expenses_detail_table">{{ $t('select_all') }}</button>
                                <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-location-filter" data-table-id="local_cashier_expenses_detail_table">{{ $t('clear') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>{{ $t('cashier_user') }}</label>
                            <select class="form-control select2 all-sale-cashier-filter" data-table-id="local_cashier_expenses_detail_table" multiple data-placeholder="{{ $t('all_cashiers') }}">
                                @foreach($expenseCashiers as $cashierName)
                                    <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                                @endforeach
                            </select>
                            <div class="all-sale-filter-actions">
                                <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_expenses_detail_table">{{ $t('select_all') }}</button>
                                <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_expenses_detail_table">{{ $t('clear') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped ajax_view" id="local_cashier_expenses_detail_table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>{{ $t('action') }}</th>
                            <th>{{ $t('date') }}</th>
                            <th>{{ $t('ref_no') }}</th>
                            <th class="all-sale-cashier-column">{{ $t('cashier_user') }}</th>
                            <th>{{ $t('expense_for') }}</th>
                            <th class="all-sale-location-column">{{ $t('location') }}</th>
                            <th>{{ $t('category') }}</th>
                            <th>{{ $t('payment_status') }}</th>
                            @foreach($staticPaymentColumns as $column)
                                <th class="text-right">{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment((string) ($column['key'] ?? ''), (string) ($column['label'] ?? '')) }}</th>
                            @endforeach
                            <th class="text-right">{{ $t('amount') }}</th>
                            <th class="text-right">{{ $t('paid') }}</th>
                            <th class="text-right">{{ $t('due') }}</th>
                            <th>{{ $t('note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenseRows as $row)
                            <tr>
                                <td>
                                    @can('expense.edit')
                                        <a class="btn btn-xs btn-primary action-icon-btn action-edit"
                                           href="{{ action([\App\Http\Controllers\ExpenseController::class, 'edit'], [$row['transaction_id']]) }}"
                                           target="_blank"
                                           title="{{ $t('edit_expense') }}">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endcan
                                </td>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['ref_no'] }}</td>
                                <td>{{ $row['created_by_name'] }}</td>
                                <td>{{ $row['expense_for_name'] }}</td>
                                <td>{{ $row['location_name'] }}</td>
                                <td>{{ $row['category_name'] }}</td>
                                <td>{{ $t($row['payment_status']) }}</td>
                                @foreach($staticPaymentColumns as $column)
                                    <td class="text-right">{{ $fmt($staticPaymentAmount($row, $column)) }}</td>
                                @endforeach
                                <td class="text-right">{{ $fmt($row['amount']) }}</td>
                                <td class="text-right">{{ $fmt($row['paid']) }}</td>
                                <td class="text-right @if(($row['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                                <td>{{ $row['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="detail-total-row">
                            @php
                                $expensePaymentStaticTotals = [];
                                foreach ($staticPaymentColumns as $column) {
                                    $expensePaymentStaticTotals[$column['key'] ?? ''] = $expenseRows->sum(fn ($row) => $staticPaymentAmount($row, $column));
                                }
                            @endphp
                            <th colspan="8" class="text-right">{{ $t('total') }}</th>
                            @foreach($staticPaymentColumns as $column)
                                <th class="text-right">{{ $fmt($expensePaymentStaticTotals[$column['key'] ?? ''] ?? 0) }}</th>
                            @endforeach
                            <th class="text-right">{{ $fmt($expenseRows->sum(fn ($row) => (float) ($row['amount'] ?? 0))) }}</th>
                            <th class="text-right">{{ $fmt($expenseRows->sum(fn ($row) => (float) ($row['paid'] ?? 0))) }}</th>
                            <th class="text-right @if($expenseRows->sum(fn ($row) => (float) ($row['due'] ?? 0)) != 0) due-negative @endif">
                                {{ $fmt($expenseRows->sum(fn ($row) => (float) ($row['due'] ?? 0))) }}
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Location Modal -->
<div class="modal fade" id="location_modal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ $t('close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="locationModalLabel">{{ $t('select_business_locations') }}</h4>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:10px;">
                    <button type="button" class="btn btn-xs btn-primary" id="select_all_locations">{{ $t('select_all') }}</button>
                    <button type="button" class="btn btn-xs btn-default" id="deselect_all_locations">{{ $t('deselect_all') }}</button>
                    <input type="text" id="location_search" class="form-control input-sm pull-right" style="width:200px; display:inline-block;" placeholder="{{ $t('search_location') }}">
                </div>
                <div style="max-height:350px; overflow-y:auto;">
                    @foreach($locations as $location)
                        <div class="checkbox location-item">
                            <label>
                                <input type="checkbox" class="location-checkbox" value="{{ $location->id }}"
                                       @if(in_array($location->id, $filters['location_ids'])) checked @endif>
                                {{ $location->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ $t('close') }}</button>
                <button type="button" class="btn btn-primary" id="apply_locations">{{ $t('apply') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Cashier Modal -->
<div class="modal fade" id="cashier_modal" tabindex="-1" role="dialog" aria-labelledby="cashierModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ $t('close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cashierModalLabel">{{ $t('select_cashiers_users') }}</h4>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:10px;">
                    <button type="button" class="btn btn-xs btn-primary" id="select_all_cashiers">{{ $t('select_all') }}</button>
                    <button type="button" class="btn btn-xs btn-default" id="deselect_all_cashiers">{{ $t('deselect_all') }}</button>
                    <input type="text" id="cashier_search" class="form-control input-sm pull-right" style="width:200px; display:inline-block;" placeholder="{{ $t('search_cashier') }}">
                </div>
                <div style="max-height:350px; overflow-y:auto;">
                    @foreach($cashiers as $cashier)
                        <div class="checkbox cashier-item">
                            <label>
                                <input type="checkbox" class="cashier-checkbox" value="{{ $cashier->id }}"
                                       @if(in_array($cashier->id, $filters['user_ids'])) checked @endif>
                                {{ $cashier->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ $t('close') }}</button>
                <button type="button" class="btn btn-primary" id="apply_cashiers">{{ $t('apply') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script>
    $(function () {
        @include('localcashierreport::partials.table_language')
        $('#local_cashier_report_app .select2').select2({ language: reportSelect2Language });

        const $startDate = $('#start_date');
        const $endDate = $('#end_date');
        const $dr = $('#date_range_picker');
        const start = $startDate.val() ? moment($startDate.val(), 'YYYY-MM-DD') : moment();
        const end = $endDate.val() ? moment($endDate.val(), 'YYYY-MM-DD') : moment();

        $dr.daterangepicker(
            $.extend(true, {}, $.extend({}, dateRangeSettings, { ranges: reportDateRanges }), reportDateSettings, {
                ranges: reportDateRanges,
                startDate: start,
                endDate: end
            }),
            function (s, e) {
                $dr.val(s.format(moment_date_format) + ' ~ ' + e.format(moment_date_format));
                $startDate.val(s.format('YYYY-MM-DD'));
                $endDate.val(e.format('YYYY-MM-DD'));
            }
        );
        $dr.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));

        // Location Modal Handlers
        $('#location_search').on('input', function () {
            var term = ($(this).val() || '').toLowerCase();
            $('.location-item').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(term) !== -1);
            });
        });
        $('#select_all_locations').on('click', function () {
            $('.location-checkbox:visible').prop('checked', true);
        });
        $('#deselect_all_locations').on('click', function () {
            $('.location-checkbox:visible').prop('checked', false);
        });
        $('#apply_locations').on('click', function () {
            var selectedIds = [];
            var selectedNames = [];
            $('.location-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
                selectedNames.push($(this).closest('label').text().trim());
            });

            $('#location_ids_hidden option').prop('selected', false);
            selectedIds.forEach(function (id) {
                $('#location_ids_hidden option[value="' + id + '"]').prop('selected', true);
            });

            $('#location_preview').val(selectedNames.join(', '));
            $('#location_modal').modal('hide');
        });

        // Cashier Modal Handlers
        $('#cashier_search').on('input', function () {
            var term = ($(this).val() || '').toLowerCase();
            $('.cashier-item').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(term) !== -1);
            });
        });
        $('#select_all_cashiers').on('click', function () {
            $('.cashier-checkbox:visible').prop('checked', true);
        });
        $('#deselect_all_cashiers').on('click', function () {
            $('.cashier-checkbox:visible').prop('checked', false);
        });
        $('#apply_cashiers').on('click', function () {
            var selectedIds = [];
            var selectedNames = [];
            $('.cashier-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
                selectedNames.push($(this).closest('label').text().trim());
            });

            $('#user_ids_hidden option').prop('selected', false);
            selectedIds.forEach(function (id) {
                $('#user_ids_hidden option[value="' + id + '"]').prop('selected', true);
            });

            $('#cashier_preview').val(selectedNames.join(', '));
            $('#cashier_modal').modal('hide');
        });

        function localCashierCopyCellFormatter(data, row, column, node) {
            var text = $('<div>').html(data === null || data === undefined ? '' : data).text();
            text = (text || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();

            if (text === '-' || /^[^\dA-Za-z]?\s*-\s*$/.test(text)) {
                return '';
            }

            var headerText = $(node).closest('table').find('thead th').eq(column).text().replace(/\s+/g, ' ').trim().toLowerCase();
            var isoDate = text.match(/\b\d{4}-\d{2}-\d{2}\b/);
            var isDateTimeText = /^\d{4}-\d{2}-\d{2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(text);
            if ((headerText.indexOf('date') !== -1 || isDateTimeText) && isoDate) {
                return isoDate[0];
            }

            return text;
        }

        function localCashierCopyExportOptions(columns) {
            return {
                columns: columns || ':visible',
                format: {
                    body: localCashierCopyCellFormatter
                }
            };
        }

        function localCashierCopyButton(options) {
            options = options || {};

            var button = {
                extend: 'copy',
                text: @json($t('copy')),
                className: 'btn btn-sm btn-outline-primary',
                exportOptions: localCashierCopyExportOptions(options.columns || ':visible')
            };

            if (options.withoutHeader) {
                button.header = false;
                button.title = null;
            }

            return button;
        }

        if ($.fn.DataTable && $('#local_cashier_expenses_detail_table').length) {
            $('#local_cashier_expenses_detail_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[5, 'asc'], [1, 'desc']],
                info: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, @json($t('all'))]],
                pagingType: 'full_numbers',
                scrollX: true,
                responsive: false,
                dom: "<'row'<'col-sm-4'l><'col-sm-4 text-center'B><'col-sm-4'f>>rt<'row'<'col-sm-6'i><'col-sm-6'p>>",
                language: reportDataTableLanguage,
                buttons: [
                    localCashierCopyButton({
                        withoutHeader: true,
                        columns: function(index, data, node) {
                            return index !== 0 && $(node).is(':visible');
                        }
                    }),
                    { extend: 'csv', text: @json($t('export_csv')), className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'excel', text: @json($t('export_excel')), className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'print', text: @json($t('print')), className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'colvis', text: @json($t('column_visibility')), className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'pdf', text: @json($t('export_pdf')), className: 'btn btn-sm btn-outline-primary' }
                ]
            });
        }

        function escapeDataTableRegex(value) {
            return $.fn.dataTable.util.escapeRegex(value || '');
        }

        function exactMatchAnyRegex(values) {
            values = $.isArray(values) ? values : (values ? [values] : []);
            values = values.filter(function (value) {
                return value !== null && value !== undefined && value !== '';
            });

            return values.length ? '^(' + values.map(escapeDataTableRegex).join('|') + ')$' : '';
        }

        function localCashierColumnIndexByClass(table, className) {
            var foundIndex = -1;

            table.columns().every(function (index) {
                if (foundIndex === -1 && $(this.header()).hasClass(className)) {
                    foundIndex = index;
                }
            });

            return foundIndex;
        }

        function applyAllSaleFilters(tableId) {
            var table = $('#' + tableId).DataTable();
            var locations = $('.all-sale-location-filter[data-table-id="' + tableId + '"]').val();
            var cashiers = $('.all-sale-cashier-filter[data-table-id="' + tableId + '"]').val();
            var locationColumn = localCashierColumnIndexByClass(table, 'all-sale-location-column');
            var cashierColumn = localCashierColumnIndexByClass(table, 'all-sale-cashier-column');

            if (locationColumn >= 0) {
                table.column(locationColumn).search(exactMatchAnyRegex(locations), true, false);
            }
            if (cashierColumn >= 0) {
                table.column(cashierColumn).search(exactMatchAnyRegex(cashiers), true, false);
            }

            table.draw();
        }

        $(document).on('change', '.all-sale-location-filter, .all-sale-cashier-filter', function () {
            applyAllSaleFilters($(this).data('table-id'));
        });

        $(document).on('click', '.all-sale-select-all, .all-sale-clear-select', function () {
            var tableId = $(this).data('table-id');
            var $select = $($(this).data('target') + '[data-table-id="' + tableId + '"]');
            var values = $(this).hasClass('all-sale-select-all')
                ? $select.find('option').map(function () { return this.value; }).get()
                : [];

            $select.val(values).trigger('change');
        });

        $(document).on('shown.bs.collapse hidden.bs.collapse', '#local_cashier_expenses_detail_table_filters', function () {
            var isOpen = $(this).hasClass('in');
            var $button = $('[data-toggle="collapse"][data-target="#' + this.id + '"]');
            $button.html('<i class="fa fa-filter"></i> ' + (isOpen ? @json($t('hide_filters')) : @json($t('filters'))));
        });
    });
</script>
<style>
#local_cashier_report_app .local-filter-wrap {
    position: relative;
}
#local_cashier_report_app .local-filter-reset {
    position: absolute;
    top: 7px;
    left: 100px;
    z-index: 2;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    padding: 5px 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
}
#local_cashier_report_app .local-filter-reset:hover,
#local_cashier_report_app .local-filter-reset:focus {
    background: #e0f2fe;
    border-color: #38bdf8;
    color: #075985;
    text-decoration: none;
}
#local_cashier_report_app .local-filter-reset i {
    margin-right: 4px;
}
#local_cashier_report_app .summary-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    grid-gap: 14px;
    margin-bottom: 18px;
}
#local_cashier_report_app .summary-kpi-card {
    position: relative;
    overflow: hidden;
    border-radius: 14px;
    padding: 16px 18px;
    border: 1px solid #d9e4f5;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
}
#local_cashier_report_app .summary-kpi-card::after {
    content: '';
    position: absolute;
    right: -28px;
    top: -28px;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.25);
}
#local_cashier_report_app .summary-kpi-card .kpi-label {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: .4px;
    position: relative;
    z-index: 1;
}
#local_cashier_report_app .summary-kpi-card .kpi-value {
    margin-top: 6px;
    font-size: 26px;
    font-weight: 700;
    color: #fff;
    position: relative;
    z-index: 1;
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(1) {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(2) {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(3) {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(4) {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
}

#local_cashier_report_app .due-negative {
    color: #c026d3;
    font-weight: 700;
}
#local_cashier_report_app .action-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid transparent;
    padding: 0;
}
#local_cashier_report_app .action-edit {
    background: #2563eb;
    border-color: #1d4ed8;
    color: #fff;
}
#local_cashier_report_app .action-edit:hover {
    background: #1d4ed8;
    color: #fff;
}
#local_cashier_report_app .sale-table-filter-toggle {
    margin-bottom: 8px;
}
#local_cashier_report_app .all-sale-table-filters {
    margin: 0 0 10px 0;
    padding: 10px 10px 4px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    border-radius: 8px;
}
#local_cashier_report_app .all-sale-table-filters label {
    color: #334155;
    font-weight: 700;
}
#local_cashier_report_app .all-sale-table-filters .select2-container {
    width: 100% !important;
}
#local_cashier_report_app .all-sale-filter-actions {
    margin-top: 6px;
}
#local_cashier_report_app .all-sale-filter-actions .btn + .btn {
    margin-left: 4px;
}

#local_cashier_report_app .table-responsive {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
}
#local_cashier_report_app #local_cashier_expenses_detail_table thead th {
    background: #f1f5f9;
    color: #1e293b;
    font-weight: 700;
    white-space: nowrap;
}
#local_cashier_report_app #local_cashier_expenses_detail_table tfoot tr.detail-total-row th {
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
    font-weight: 800;
    font-size: 14px;
}

#local_cashier_report_app .dataTables_wrapper .dt-buttons .btn {
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 13px;
    margin-right: 4px;
}
#local_cashier_report_app .dataTables_wrapper .dataTables_filter input,
#local_cashier_report_app .dataTables_wrapper .dataTables_length select {
    border: 1px solid #cfd6df;
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 13px;
}
</style>
@endsection
