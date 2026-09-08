@extends('loanmanagement::layouts.app')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $dateRangeDisplay = $dateFrom && $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('m-d-Y').' - '.\Carbon\Carbon::parse($dateTo)->format('m-d-Y')
        : '';
@endphp

@section('title', $lmText('Audit & Activity Logs', 'កំណត់ហេតុសកម្មភាព'))

@section('loan_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR AUDIT & ACTIVITY LOGS
       ========================================================= */
    .lm-audit-content {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* KPI Summary Cards */
    .lm-audit-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    .lm-audit-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .lm-audit-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .lm-audit-icon {
        width: 44px;
        height: 44px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .lm-audit-blue .lm-audit-icon { background: #eff6ff; color: #2563eb; }
    .lm-audit-green .lm-audit-icon { background: #ecfdf5; color: #16a34a; }
    .lm-audit-indigo .lm-audit-icon { background: #eef2ff; color: #4f46e5; }
    .lm-audit-amber .lm-audit-icon { background: #fffbeb; color: #d97706; }
    .lm-audit-copy small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 2px;
    }
    .lm-audit-copy strong {
        display: block;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1.5fr auto;
        gap: 14px 18px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1024px) {
        .lm-pos-filter-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-pos-filter-grid { grid-template-columns: 1fr; }
        .lm-audit-stats-grid { grid-template-columns: 1fr; }
    }
    .lm-pos-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .lm-pos-filter-field label {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }
    .lm-pos-filter-field .form-control {
        height: 38px;
        padding: 6px 12px;
        font-size: 13px;
        color: #1e293b;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        outline: none;
        width: 100%;
        box-shadow: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .lm-pos-filter-field .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }
    .lm-pos-filter-field select.form-control {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 28px;
        -webkit-appearance: none;
        appearance: none;
    }

    .lm-pos-filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
    }
    .lm-btn-pos-filter {
        height: 38px;
        padding: 0 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        background: #0284c7;
        color: #fff;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-btn-pos-filter:hover { background: #0369a1; }
    .lm-btn-pos-reset {
        height: 38px;
        padding: 0 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    .lm-btn-pos-reset:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }

    /* Ultimate POS DataTables Toolbar Layout */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        background: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .lm-dt-length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
        font-weight: 500 !important;
        font-size: 13px !important;
        color: #475569 !important;
    }
    .lm-dt-length select {
        height: 34px !important;
        padding: 2px 28px 2px 10px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 13px !important;
        color: #1e293b !important;
        background-color: #fff !important;
        outline: none !important;
    }
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        flex-wrap: wrap !important;
    }
    .lm-dt-buttons .btn {
        border-radius: 6px !important;
        padding: 6px 12px !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        transition: all 0.15s ease !important;
    }
    .lm-dt-buttons .btn:hover {
        background: #f8fafc !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .lm-dt-search {
        margin: 0 !important;
    }
    .lm-dt-search label {
        margin: 0 !important;
        display: block !important;
    }
    .lm-dt-search input {
        height: 34px !important;
        min-width: 220px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 6px 12px !important;
        font-size: 13px !important;
        outline: none !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transition: border-color 0.15s ease !important;
    }
    .lm-dt-search input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
    }

    /* Table Styling */
    .lm-table-dense {
        margin-bottom: 0 !important;
        border-collapse: collapse !important;
    }
    .lm-table-dense th {
        font-size: 12.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
        color: #475569 !important;
        background: #f8fafc !important;
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 1px solid #cbd5e1 !important;
        padding: 10px 12px !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
    }
    .lm-table-dense td {
        font-size: 13px !important;
        color: #1e293b !important;
        padding: 10px 12px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-table-dense tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* Bottom Info & Pagination */
    .lm-dt-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        background: #ffffff !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-dt-info {
        font-size: 13px !important;
        color: #64748b !important;
        padding: 0 !important;
    }
    .lm-dt-pagination .pagination {
        margin: 0 !important;
    }
    .lm-dt-pagination .pagination > li > a {
        border-radius: 4px !important;
        margin: 0 2px !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
    }
    .lm-dt-pagination .pagination > .active > a {
        background-color: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
    }
</style>
@endsection

@section('content_body')
<div class="lm-audit-content">

    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $lmText('Audit & Activity Logs', 'កំណត់ហេតុសកម្មភាពប្រព័ន្ធ') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $lmText('Audit trail, loan events, customer records, payments, and system operations', 'តាមដានប្រវត្តិកែប្រែកម្ចី អតិថិជន ការទូទាត់ និងប្រតិបត្តិការប្រព័ន្ធ') }}
            </small>
        </h1>
    </section>

    {{-- 4 KPI Metric Cards --}}
    <div class="lm-audit-stats-grid">
        <div class="lm-audit-card lm-audit-blue">
            <div class="lm-audit-icon"><i class="fa fa-history"></i></div>
            <div class="lm-audit-copy">
                <small>{{ $lmText('Total Activity', 'សកម្មភាពសរុប') }}</small>
                <strong>{{ number_format($summary['total']) }}</strong>
            </div>
        </div>
        <div class="lm-audit-card lm-audit-green">
            <div class="lm-audit-icon"><i class="fa fa-database"></i></div>
            <div class="lm-audit-copy">
                <small>{{ $lmText('Recorded Logs', 'កំណត់ត្រាក្នុងប្រព័ន្ធ') }}</small>
                <strong>{{ number_format($summary['recorded']) }}</strong>
            </div>
        </div>
        <div class="lm-audit-card lm-audit-indigo">
            <div class="lm-audit-icon"><i class="fa fa-file-text-o"></i></div>
            <div class="lm-audit-copy">
                <small>{{ $lmText('Installment Events', 'ព្រឹត្តិការណ៍កម្ចី') }}</small>
                <strong>{{ number_format($summary['loans']) }}</strong>
            </div>
        </div>
        <div class="lm-audit-card lm-audit-amber">
            <div class="lm-audit-icon"><i class="fa fa-money"></i></div>
            <div class="lm-audit-copy">
                <small>{{ $lmText('Payments Logged', 'ប្រតិបត្តិការបង់ប្រាក់') }}</small>
                <strong>{{ number_format($summary['payments']) }}</strong>
            </div>
        </div>
    </div>

    {{-- Ultimate POS Standard Collapsible Filters Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.activity-logs.index') }}" id="loanActivityLogFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Search Keyword --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Search Keyword', 'ស្វែងរក') }}</label>
                    <input type="text" class="form-control" name="search" value="{{ $filters['search'] }}" placeholder="{{ $lmText('Installment #, customer, actor, IP...', 'កម្ចី អតិថិជន អ្នកប្រើប្រាស់ IP...') }}">
                </div>

                {{-- Activity Type --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Activity Type', 'ប្រភេទសកម្មភាព') }}</label>
                    <select class="form-control" name="event">
                        <option value="">{{ $lmText('All Activity Types', 'គ្រប់ប្រភេទទាំងអស់') }}</option>
                        @foreach($eventOptions as $event)
                            <option value="{{ $event }}" {{ $filters['event'] === $event ? 'selected' : '' }}>{{ $event }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date Range --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                    <input type="text" name="date_range" id="activityLogDateRange" value="{{ $dateRangeDisplay }}" class="form-control" placeholder="{{ $lmText('Select date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" autocomplete="off">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                </div>

                {{-- Action Buttons --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $lmText('Apply', 'អនុវត្ត') }}
                    </button>
                    <a href="{{ route('loan-management.activity-logs.index') }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'សម្អាត') }}
                    </a>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $lmText('Audit & Activity Timeline', 'កំណត់ហេតុសកម្មភាពប្រព័ន្ធ')])
        <div class="table-responsive">
            <table class="lm-table-dense table table-bordered table-striped table-hover" id="loanActivityLogsTable" style="width: 100%; margin-bottom: 0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th style="width: 150px;">{{ $lmText('Date & Time', 'កាលបរិច្ឆេទ & ម៉ោង') }}</th>
                        <th style="width: 170px;">{{ $lmText('Activity / Event', 'សកម្មភាព') }}</th>
                        <th style="width: 160px;">{{ $lmText('Reference', 'យោង') }}</th>
                        <th>{{ $lmText('Details', 'ព័ត៌មានលម្អិត') }}</th>
                        <th style="width: 140px;">{{ $lmText('User / Actor', 'អ្នកប្រើប្រាស់') }}</th>
                        <th style="width: 90px; text-align: center;">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="white-space: nowrap;">
                                <span style="font-weight: 700; color: #0f172a; font-size: 12.5px;">{{ $log['occurred_at'] }}</span>
                                <div style="font-size: 11px; color: #94a3b8; font-family: ui-monospace, monospace;">{{ $log['source'] }}</div>
                            </td>
                            <td>
                                <span class="label label-info" style="display: inline-block; padding: 4px 8px; font-size: 11px; border-radius: 4px;">
                                    {{ $log['event'] }}
                                </span>
                            </td>
                            <td style="font-weight: 700; color: #334155; font-size: 12.5px;">
                                {{ $log['reference'] }}
                            </td>
                            <td style="color: #334155; font-size: 12.5px; word-break: break-word;">
                                {{ $log['details'] }}
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #1e293b;">{{ $log['actor'] }}</span>
                            </td>
                            <td style="text-align: center;">
                                <span class="label label-{{ strtolower($log['status'] ?? '') === 'success' || strtolower($log['status'] ?? '') === 'active' || strtolower($log['status'] ?? '') === 'paid' ? 'success' : 'default' }}" style="display: inline-block; padding: 4px 8px; font-size: 10.5px; border-radius: 4px;">
                                    {{ $log['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div style="margin-top: 10px;">
                {{ $logs->links() }}
            </div>
        @endif
    @endcomponent

</div>
@endsection

@section('loan_js')
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1/daterangepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function(){
    // Date Range Picker initialization
    var $dateRange = $('#activityLogDateRange');
    var $filterForm = $('#loanActivityLogFilterForm');
    var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
    var dateRangeSettings = window.dateRangeSettings ? $.extend(true, {}, window.dateRangeSettings) : {};

    if (window.moment && $.fn.daterangepicker && $dateRange.length) {
        var startDate = @json($dateFrom) ? moment(@json($dateFrom)) : moment().subtract(29, 'days');
        var endDate = @json($dateTo) ? moment(@json($dateTo)) : moment();
        var fyStart = (typeof financial_year !== 'undefined' && financial_year.start && moment(financial_year.start).isValid()) ? moment(financial_year.start) : moment().startOf('year');
        var fyEnd = (typeof financial_year !== 'undefined' && financial_year.end && moment(financial_year.end).isValid()) ? moment(financial_year.end) : moment().endOf('year');

        $dateRange.daterangepicker($.extend(true, {}, dateRangeSettings, {
            autoUpdateInput: false,
            showDropdowns: true,
            linkedCalendars: false,
            startDate: startDate,
            endDate: endDate,
            parentEl: 'body',
            opens: 'right',
            drops: 'auto',
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'This Year': [moment().startOf('year'), moment().endOf('year')],
                'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                'Current financial year': [fyStart.clone(), fyEnd.clone()],
                'Last financial year': [fyStart.clone().subtract(1, 'year'), fyEnd.clone().subtract(1, 'year')]
            },
            locale: $.extend(true, {}, dateRangeSettings.locale || {}, {
                format: displayDateFormat,
                separator: ' - ',
                applyLabel: @json($lmText('Apply', 'អនុវត្ត')),
                cancelLabel: @json($lmText('Clear', 'សម្អាត')),
                customRangeLabel: @json($lmText('Custom Range', 'ជ្រើសរើសផ្ទាល់')),
                toLabel: '~'
            })
        }));

        $dateRange
            .on('apply.daterangepicker', function (event, picker) {
                $(this).val(picker.startDate.format(displayDateFormat) + ' - ' + picker.endDate.format(displayDateFormat));
                $filterForm.find('[name="date_from"]').val(picker.startDate.format('YYYY-MM-DD'));
                $filterForm.find('[name="date_to"]').val(picker.endDate.format('YYYY-MM-DD'));
                $filterForm.submit();
            })
            .on('cancel.daterangepicker', function () {
                $(this).val('');
                $filterForm.find('[name="date_from"], [name="date_to"]').val('');
                $filterForm.submit();
            });
    }

    // Initialize DataTables with Ultimate POS standard toolbar
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanActivityLogsTable')) {
        var tableButtons = [];
        if ($.fn.dataTable.Buttons) {
            tableButtons = [
                {
                    extend: 'copy',
                    text: 'Copy',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'csv',
                    text: '<i class="fa fa-file-text-o"></i> Export CSV',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Export Excel',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible', stripHtml: true }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fa fa-columns"></i> Column visibility',
                    className: 'btn btn-default btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> Export PDF <i class="fa fa-caret-down" style="margin-left:2px;"></i>',
                    className: 'btn btn-default btn-sm',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: { columns: ':visible' }
                }
            ];
        }

        $('#loanActivityLogsTable').DataTable({
            dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
            buttons: tableButtons,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $lmText('All', 'ទាំងអស់') }}"]],
            order: [[0, 'desc']],
            autoWidth: false,
            language: {
                search: '',
                searchPlaceholder: 'Search ...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: '{{ $lmText("No activity records found.", "មិនមានកំណត់ហេតុសកម្មភាពទេ។") }}',
                info: '{{ $lmText("Showing _START_ to _END_ of _TOTAL_ entries", "បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ") }}',
                infoEmpty: '{{ $lmText("Showing 0 to 0 of 0 entries", "បង្ហាញ 0 នៃ 0 ធាតុ") }}',
                infoFiltered: '({{ $lmText("filtered from _MAX_ total entries", "ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប") }})',
                paginate: {
                    first: '{{ $lmText("First", "ដំបូង") }}',
                    last: '{{ $lmText("Last", "ចុងក្រោយ") }}',
                    next: '{{ $lmText("Next", "បន្ទាប់") }}',
                    previous: '{{ $lmText("Previous", "មុន") }}'
                }
            },
            columnDefs: [
                { targets: [5], className: 'text-center' }
            ]
        });
    }
});
</script>
@endsection
