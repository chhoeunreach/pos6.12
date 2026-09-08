@extends('loanmanagement::layouts.app')
@section('title', 'Installment Schedule')

@php
    $isKhmer = $isKhmer ?? session('user.language', config('app.locale')) === 'km';
    $t = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $dateRangeDisplay = $dateFrom && $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('m-d-Y').' - '.\Carbon\Carbon::parse($dateTo)->format('m-d-Y')
        : '';
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    .ls-page { color: #172033; }
    .ls-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; margin-bottom: 16px; }
    .ls-title { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 0; color: #111827; }
    .ls-subtitle { margin: 4px 0 0; color: #64748b; font-size: 13px; }
    .ls-header-actions { display: inline-flex; gap: 8px; flex-wrap: wrap; }
    .ls-cards { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; margin-bottom: 14px; }
    .ls-card { background: #fff; border: 1px solid #dfe7f1; border-radius: 8px; padding: 13px; min-height: 86px; display: flex; align-items: center; gap: 12px; box-shadow: 0 9px 20px rgba(15, 23, 42, .055); }
    .ls-card-icon { width: 42px; height: 42px; border-radius: 8px; display: grid; place-items: center; flex: 0 0 42px; color: #fff; background: #2563eb; }
    .ls-card small { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 700; }
    .ls-card strong { display: block; color: #0f172a; font-size: 19px; font-weight: 800; line-height: 1.15; }
    .ls-card.is-green .ls-card-icon { background: #059669; }
    .ls-card.is-amber .ls-card-icon { background: #d97706; }
    .ls-card.is-red .ls-card-icon { background: #dc2626; }
    .ls-panel { background: #fff; border: 1px solid #dfe7f1; border-radius: 8px; box-shadow: 0 9px 20px rgba(15, 23, 42, .055); margin-bottom: 14px; overflow: hidden; }
    .ls-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 15px; border-bottom: 1px solid #edf2f7; }
    .ls-panel-title { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; }
    .ls-filter-body { padding: 15px; }
    .ls-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; align-items: end; }
    .ls-filter-date { grid-column: span 2; }
    .ls-filter-grid label { color: #475569; font-size: 12px; font-weight: 700; margin-bottom: 5px; }
    .ls-filter-grid .form-control { height: 37px; border-color: #d3dde9; border-radius: 6px; box-shadow: none; }
    .ls-filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .ls-toolbar-pill { border-radius: 999px; background: #eff6ff; color: #2563eb; padding: 5px 10px; font-size: 12px; font-weight: 800; }
    .ls-table-wrap { padding: 15px; overflow-x: auto; }
    .ls-table { min-width: 1550px; width: 100% !important; margin: 0; }
    .ls-table thead th { background: #f8fafc; border-bottom: 1px solid #dfe7f1 !important; color: #334155; font-size: 12px; text-transform: uppercase; white-space: nowrap; }
    .ls-table tbody td { vertical-align: middle !important; white-space: nowrap; }
    .ls-status { display: inline-block; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 800; background: #eef2ff; color: #4338ca; }
    .ls-status.is-paid { background: #dcfce7; color: #166534; }
    .ls-status.is-open { background: #fef3c7; color: #92400e; }
    .ls-status.is-overdue { background: #fee2e2; color: #991b1b; }
    .ls-actions { display: inline-flex; gap: 6px; }
    .ls-dt-panel .dataTables_wrapper .row:first-child { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 16px; }
    .ls-dt-panel .dataTables_wrapper .row:first-child:before,
    .ls-dt-panel .dataTables_wrapper .row:first-child:after { display: none; }
    .ls-dt-panel .dataTables_length { text-align: left; white-space: nowrap; }
    .ls-dt-panel .dataTables_length label { font-weight: 500; color: #172033; }
    .ls-dt-panel .dataTables_length select { height: 36px; min-width: 84px; border: 1px solid #cfd8e5; box-shadow: none; margin: 0 6px; }
    .ls-dt-panel .dt-buttons { display: inline-flex; justify-content: center; gap: 8px; flex-wrap: wrap; float: none; }
    .ls-dt-panel .dt-buttons .btn,
    .ls-dt-panel .dt-button { border: 1px solid #b8c3d3 !important; border-radius: 8px !important; background: #fff !important; color: #64748b !important; font-weight: 700; padding: 5px 12px !important; box-shadow: none !important; }
    .ls-dt-panel .dataTables_filter { text-align: right; }
    .ls-dt-panel .dataTables_filter label { width: 100%; margin: 0; }
    .ls-dt-panel .dataTables_filter input { height: 36px; width: 240px !important; border: 1px solid #cfd8e5; border-radius: 0; box-shadow: none; margin-left: 0; }
    .ls-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 0 15px 15px; }
    .ls-pagination-info { color: #64748b; font-size: 13px; font-weight: 600; }
    .ls-pagination-links .pagination { margin: 0; }
    @media (max-width: 1200px) { .ls-cards { grid-template-columns: repeat(2, 1fr); } .ls-filter-grid { grid-template-columns: repeat(2, 1fr); } .ls-dt-panel .dataTables_wrapper .row:first-child { display: block; } .ls-dt-panel .dataTables_length, .ls-dt-panel .dt-buttons, .ls-dt-panel .dataTables_filter { text-align: left; margin-bottom: 8px; } }
    @media (max-width: 767px) { .ls-header { flex-direction: column; align-items: flex-start; } .ls-cards, .ls-filter-grid { grid-template-columns: 1fr; } .ls-filter-date { grid-column: auto; } }
</style>
@endsection

@section('content_body')
<section class="content-header ls-page">
    <div class="ls-header">
        <div>
            <h1 class="ls-title">{{ $t('Installment Schedule', 'កាលវិភាគកម្ចី') }}</h1>
            <p class="ls-subtitle">{{ $t('Track installment due dates, balances, overdue days, and collection actions.', 'តាមដានថ្ងៃត្រូវបង់ សមតុល្យ ថ្ងៃហួសកំណត់ និងសកម្មភាពប្រមូលប្រាក់។') }}</p>
        </div>
        <div class="ls-header-actions">
            <div class="btn-group" style="margin-right: 6px;">
                <button type="button" class="btn btn-primary btn-sm active" style="font-weight: 600;"><i class="fa fa-list"></i> {{ $t('Table View', 'ទម្រង់តារាង') }}</button>
                <a href="{{ route('loan-management.schedules.calendar') }}" class="btn btn-default btn-sm" style="font-weight: 600;"><i class="fa fa-calendar"></i> {{ $t('Calendar View', 'ទម្រង់ប្រតិទិន') }}</a>
            </div>
            <a href="{{ route('loan-management.loans') }}" class="btn btn-default btn-sm"><i class="fa fa-list"></i> {{ $t('All Installments', 'កម្ចីទាំងអស់') }}</a>
            <a href="{{ route('loan-management.loans.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> {{ $t('New Installment', 'កម្ចីថ្មី') }}</a>
        </div>
    </div>
</section>

<section class="content ls-page">
    <div class="ls-cards">
        <div class="ls-card">
            <span class="ls-card-icon"><i class="fa fa-calendar"></i></span>
            <span><small>{{ $t('Schedules', 'កាលវិភាគ') }}</small><strong>{{ $number($summary['count'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card is-amber">
            <span class="ls-card-icon"><i class="fa fa-clock-o"></i></span>
            <span><small>{{ $t('Open', 'មិនទាន់បង់') }}</small><strong>{{ $number($summary['open'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card is-green">
            <span class="ls-card-icon"><i class="fa fa-check"></i></span>
            <span><small>{{ $t('Paid', 'បានបង់') }}</small><strong>{{ $number($summary['paid'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card is-red">
            <span class="ls-card-icon"><i class="fa fa-warning"></i></span>
            <span><small>{{ $t('Overdue', 'ហួសកំណត់') }}</small><strong>{{ $number($summary['overdue'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card">
            <span class="ls-card-icon"><i class="fa fa-calendar-check-o"></i></span>
            <span><small>{{ $t('Due Today', 'ត្រូវបង់ថ្ងៃនេះ') }}</small><strong>{{ $number($summary['due_today'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card">
            <span class="ls-card-icon"><i class="fa fa-money"></i></span>
            <span><small>{{ $t('Amount Due', 'ចំនួនត្រូវបង់') }}</small><strong>{{ $money($summary['due_total'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card is-green">
            <span class="ls-card-icon"><i class="fa fa-credit-card"></i></span>
            <span><small>{{ $t('Paid Amount', 'ចំនួនបានបង់') }}</small><strong>{{ $money($summary['paid_total'] ?? 0) }}</strong></span>
        </div>
        <div class="ls-card is-red">
            <span class="ls-card-icon"><i class="fa fa-balance-scale"></i></span>
            <span><small>{{ $t('Balance', 'សមតុល្យ') }}</small><strong>{{ $money($summary['balance_total'] ?? 0) }}</strong></span>
        </div>
    </div>

    <div class="ls-panel">
        <div class="ls-panel-head">
            <h3 class="ls-panel-title"><i class="fa fa-filter"></i> {{ $t('Filters', 'តម្រង') }}</h3>
            <button type="button" class="btn btn-default btn-sm" data-ls-toggle data-toggle="collapse" data-target="#loanScheduleFilters" aria-expanded="false" aria-controls="loanScheduleFilters">
                <span data-ls-label>{{ $t('Expand', 'បើក') }}</span> <i class="fa fa-chevron-down"></i>
            </button>
        </div>
        <div class="ls-filter-body collapse" id="loanScheduleFilters">
            <form method="GET">
                <div class="ls-filter-grid">
                    <div class="ls-filter-date">
                        <label>{{ $t('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                        <input type="text" name="date_range" id="loanScheduleDateRange" value="{{ $dateRangeDisplay }}" class="form-control" placeholder="{{ $t('Select due date range', 'ជ្រើសរើសចន្លោះថ្ងៃត្រូវបង់') }}" autocomplete="off">
                        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                        <input type="hidden" name="date_to" value="{{ $dateTo }}">
                    </div>
                    <div>
                        <label>{{ $t('Location', 'ទីតាំង') }}</label>
                        <select name="location_id" class="form-control">
                            <option value="">{{ $t('All locations', 'គ្រប់ទីតាំង') }}</option>
                            @foreach($locations as $key => $name)
                                <option value="{{ $key }}" @selected(($filters['location_id'] ?? '') === $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>{{ $t('Schedule Status', 'ស្ថានភាពកាលវិភាគ') }}</label>
                        <select name="status" class="form-control">
                            <option value="">{{ $t('All statuses', 'គ្រប់ស្ថានភាព') }}</option>
                            @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>{{ $t('Installment Status', 'ស្ថានភាពកម្ចី') }}</label>
                        <select name="loan_status" class="form-control">
                            <option value="">{{ $t('All loan statuses', 'គ្រប់ស្ថានភាពកម្ចី') }}</option>
                            @foreach($loanStatusOptions as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['loan_status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>{{ $t('Collector', 'អ្នកប្រមូល') }}</label>
                        <input type="text" name="collector" value="{{ $filters['collector'] ?? '' }}" class="form-control" placeholder="{{ $t('Collector name or ID', 'ឈ្មោះ ឬលេខសម្គាល់') }}">
                    </div>
                    <div>
                        <label>{{ $t('Search', 'ស្វែងរក') }}</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="{{ $t('Installment, invoice, customer, phone', 'កម្ចី វិក្កយបត្រ អតិថិជន ទូរស័ព្ទ') }}">
                    </div>
                    <div>
                        <label>{{ $t('Rows Per Page', 'ចំនួនក្នុងមួយទំព័រ') }}</label>
                        <select name="per_page" class="form-control">
                            @foreach([25, 50, 100, 200] as $size)
                                <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ls-filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> {{ $t('Apply', 'អនុវត្ត') }}</button>
                        <a href="{{ route('loan-management.schedules.index') }}" class="btn btn-default">{{ $t('Reset', 'កំណត់ឡើងវិញ') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="ls-panel ls-dt-panel">
        <div class="ls-panel-head">
            <h3 class="ls-panel-title"><i class="fa fa-table"></i> {{ $t('Schedule Data', 'ទិន្នន័យកាលវិភាគ') }}</h3>
            <span class="ls-toolbar-pill">
                {{ $number(method_exists($rows, 'total') ? $rows->total() : (method_exists($rows, 'count') ? $rows->count() : count($rows))) }} {{ $t('records', 'ទិន្នន័យ') }}
            </span>
        </div>
        <div class="ls-table-wrap">
            <table class="table table-bordered table-hover ls-table" id="loanScheduleTable">
                <thead>
                    <tr>
                        <th>{{ $t('Action', 'សកម្មភាព') }}</th>
                        <th>{{ $t('Due Date', 'ថ្ងៃត្រូវបង់') }}</th>
                        <th>{{ $t('Installment #', 'លេខរំលស់') }}</th>
                        <th>{{ $t('Installment #', 'លេខកម្ចី') }}</th>
                        <th>{{ $t('Invoice', 'វិក្កយបត្រ') }}</th>
                        <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                        <th>{{ $t('Phone', 'ទូរស័ព្ទ') }}</th>
                        <th>{{ $t('Location', 'ទីតាំង') }}</th>
                        <th>{{ $t('Collector', 'អ្នកប្រមូល') }}</th>
                        <th>{{ $t('Frequency', 'ប្រភេទបង់') }}</th>
                        <th>{{ $t('Schedule Status', 'ស្ថានភាព') }}</th>
                        <th>{{ $t('Installment Status', 'ស្ថានភាពកម្ចី') }}</th>
                        <th class="text-right">{{ $t('Principal', 'ប្រាក់ដើម') }}</th>
                        <th class="text-right">{{ $t('Interest', 'ការប្រាក់') }}</th>
                        <th class="text-right">{{ $t('Amount Due', 'ត្រូវបង់') }}</th>
                        <th class="text-right">{{ $t('Paid', 'បានបង់') }}</th>
                        <th class="text-right">{{ $t('Balance', 'សមតុល្យ') }}</th>
                        <th class="text-right">{{ $t('DPD', 'ថ្ងៃហួស') }}</th>
                        <th>{{ $t('Paid At', 'ថ្ងៃបានបង់') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $status = strtolower((string) ($row->schedule_status ?? 'pending'));
                            $statusClass = (float) ($row->balance_amount ?? 0) <= 0 || in_array($status, ['paid', 'confirmed', 'completed'], true)
                                ? 'is-paid'
                                : ((int) ($row->overdue_days ?? 0) > 0 ? 'is-overdue' : 'is-open');
                        @endphp
                        <tr>
                            <td>
                                <span class="ls-actions">
                                    <a href="{{ route('loan-management.loans.view', $row->loan_id) }}" class="btn btn-default btn-xs"><i class="fa fa-eye"></i> {{ $t('View', 'មើល') }}</a>
                                    <button type="button" class="btn btn-primary btn-xs btn-modal" data-href="{{ route('loan-management.loans.payment.create', ['loan' => $row->loan_id, 'schedule_id' => $row->id]) }}" data-container=".view_modal"><i class="fa fa-money"></i> {{ $t('Pay', 'បង់') }}</button>
                                </span>
                            </td>
                            <td>{{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('d M Y') : '-' }}</td>
                            <td><strong>{{ $row->installment_no }}</strong></td>
                            <td><strong>{{ $row->loan_number }}</strong></td>
                            <td>{{ $row->invoice_no ?: '-' }}</td>
                            <td>{{ $row->customer_name ?: '-' }}</td>
                            <td>{{ $row->customer_phone ?: '-' }}</td>
                            <td>{{ $row->location_name ?: '-' }}</td>
                            <td>{{ $row->collector_name ?: '-' }}</td>
                            <td>{{ $row->payment_frequency ? ucwords(str_replace('_', ' ', (string) $row->payment_frequency)) : '-' }}</td>
                            <td><span class="ls-status {{ $statusClass }}">{{ $row->schedule_status ? ucwords(str_replace('_', ' ', (string) $row->schedule_status)) : $t('Pending', 'រង់ចាំ') }}</span></td>
                            <td>{{ $row->loan_status ? ucwords(str_replace('_', ' ', (string) $row->loan_status)) : '-' }}</td>
                            <td class="text-right">{{ $money($row->principal_amount) }}</td>
                            <td class="text-right">{{ $money($row->interest_amount) }}</td>
                            <td class="text-right">{{ $money($row->amount_due) }}</td>
                            <td class="text-right">{{ $money($row->paid_amount) }}</td>
                            <td class="text-right">{{ $money($row->balance_amount) }}</td>
                            <td class="text-right">{{ $number($row->overdue_days) }}</td>
                            <td>{{ $row->paid_at ? \Carbon\Carbon::parse($row->paid_at)->format('d M Y') : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(method_exists($rows, 'links'))
            <div class="ls-pagination">
                <div class="ls-pagination-info">
                    {{ $t('Showing', 'បង្ហាញ') }}
                    {{ $number($rows->firstItem() ?? 0) }}
                    {{ $t('to', 'ដល់') }}
                    {{ $number($rows->lastItem() ?? 0) }}
                    {{ $t('of', 'នៃ') }}
                    {{ $number($rows->total()) }}
                </div>
                <div class="ls-pagination-links">
                    {{ $rows->links() }}
                </div>
            </div>
        @endif
    </div>
</section>
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
    jQuery(function ($) {
        var $dateRange = $('#loanScheduleDateRange');
        var $filterForm = $dateRange.closest('form');
        var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
        var dateRangeSettings = window.dateRangeSettings ? $.extend(true, {}, window.dateRangeSettings) : {};

        if (window.moment && $.fn.daterangepicker && $dateRange.length) {
            var startDate = @json($dateFrom) ? moment(@json($dateFrom)) : moment();
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
                    applyLabel: @json($t('Apply', 'អនុវត្ត')),
                    cancelLabel: @json($t('Clear', 'សម្អាត')),
                    customRangeLabel: @json($t('Custom Range', 'ជ្រើសរើសផ្ទាល់')),
                    toLabel: '~'
                })
            }));

            $dateRange
                .on('apply.daterangepicker', function (event, picker) {
                    $(this).val(picker.startDate.format(displayDateFormat) + ' - ' + picker.endDate.format(displayDateFormat));
                    $filterForm.find('[name="date_from"]').val(picker.startDate.format('YYYY-MM-DD'));
                    $filterForm.find('[name="date_to"]').val(picker.endDate.format('YYYY-MM-DD'));
                })
                .on('cancel.daterangepicker', function () {
                    $(this).val('');
                    $filterForm.find('[name="date_from"], [name="date_to"]').val('');
                });
        }

        $('#loanScheduleFilters')
            .on('shown.bs.collapse', function () {
                var button = $('[data-ls-toggle]');
                button.attr('aria-expanded', 'true');
                button.find('[data-ls-label]').text(@json($t('Collapse', 'បិទ')));
                button.find('i').attr('class', 'fa fa-chevron-up');
            })
            .on('hidden.bs.collapse', function () {
                var button = $('[data-ls-toggle]');
                button.attr('aria-expanded', 'false');
                button.find('[data-ls-label]').text(@json($t('Expand', 'បើក')));
                button.find('i').attr('class', 'fa fa-chevron-down');
            });

        if (!$.fn.DataTable || $.fn.DataTable.isDataTable('#loanScheduleTable')) {
            return;
        }

        var tableButtons = [];
        if ($.fn.dataTable.Buttons) {
            tableButtons = [
                {extend: 'copy', text: '<i class="fa fa-copy"></i> Copy', className: 'btn btn-default btn-sm', exportOptions: {columns: ':visible'}},
                {extend: 'csv', text: '<i class="fa fa-file-text-o"></i> Export CSV', className: 'btn btn-default btn-sm', exportOptions: {columns: ':visible'}},
                {extend: 'excel', text: '<i class="fa fa-file-excel-o"></i> Export Excel', className: 'btn btn-default btn-sm', exportOptions: {columns: ':visible'}},
                {extend: 'print', text: '<i class="fa fa-print"></i> Print', className: 'btn btn-default btn-sm', exportOptions: {columns: ':visible', stripHtml: true}},
                {extend: 'colvis', text: '<i class="fa fa-columns"></i> Column visibility', className: 'btn btn-default btn-sm'},
                {extend: 'pdf', text: '<i class="fa fa-file-pdf-o"></i> Export PDF', className: 'btn btn-default btn-sm', orientation: 'landscape', pageSize: 'A4', exportOptions: {columns: ':visible'}}
            ];
        }

        $('#loanScheduleTable').DataTable({
            dom: '<"row margin-bottom-20 text-center"<"col-sm-8"B><"col-sm-4"f> r>tip',
            buttons: tableButtons,
            paging: false,
            info: false,
            order: [[1, 'asc']],
            autoWidth: false,
            scrollX: true,
            language: { search: '', searchPlaceholder: 'Search ...' },
            columnDefs: [
                {targets: [12, 13, 14, 15, 16, 17], className: 'text-right'},
                {targets: [0], orderable: false}
            ]
        });
    });
</script>
@endsection
