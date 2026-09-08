@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'របាយការណ៍បណ្តាញបង់ប្រាក់ និងវិធីសាស្ត្រទូទាត់' : 'Payment Channels & Methods Report')

@php
    $t = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$ ' . number_format((float) ($value ?? 0), 2);
    $totals = [
        'count' => collect($rows)->sum('count'),
        'total' => collect($rows)->sum('total'),
        'cash' => collect($rows)->sum('cash'),
        'aba' => collect($rows)->sum('aba'),
        'acleda' => collect($rows)->sum('acleda'),
        'wing' => collect($rows)->sum('wing'),
        'et' => collect($rows)->sum('et'),
        'card' => collect($rows)->sum('card'),
        'other' => collect($rows)->sum('other'),
    ];
    $digitalTotal = $totals['aba'] + $totals['acleda'] + $totals['wing'] + $totals['et'] + $totals['card'];

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
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR FINANCIAL REPORTS
       ========================================================= */
    .lm-finance-report-page {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

    /* KPI Cards */
    .lm-finance-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .lm-finance-card {
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
    .lm-finance-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .lm-finance-icon {
        width: 44px;
        height: 44px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .lm-finance-green .lm-finance-icon { background: #ecfdf5; color: #16a34a; }
    .lm-finance-blue .lm-finance-icon { background: #eff6ff; color: #2563eb; }
    .lm-finance-cyan .lm-finance-icon { background: #ecfeff; color: #0891b2; }
    .lm-finance-orange .lm-finance-icon { background: #fff7ed; color: #ea580c; }
    .lm-finance-slate .lm-finance-icon { background: #f8fafc; color: #475569; }
    .lm-finance-copy small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 2px;
    }
    .lm-finance-copy strong {
        display: block;
        font-size: 19px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }

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

    /* Professional Payment Type Badges */
    .lm-type-badge {
        font-size: 11.5px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        line-height: 1.2;
        letter-spacing: 0.2px;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .lm-type-monthly { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .lm-type-payoff { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .lm-type-deposit, .lm-type-loan { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .lm-type-advance { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
    .lm-type-penalty { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }
    .lm-type-default { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

    /* Table Typography & Styling */
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
    .lm-table-dense tfoot th {
        background: #eff6ff !important;
        color: #0f172a !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        padding: 10px 12px !important;
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

    @media (max-width: 1200px) {
        .lm-finance-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .lm-finance-summary-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content_body')
<div class="lm-finance-report-page">

    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $t('Payment Summary by Channel & Type', 'សង្ខេបការបង់ប្រាក់តាមប្រភព និងប្រភេទ') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $t('Financial breakdown across cash, banking, and digital installment collection channels', 'របាយការណ៍ហិរញ្ញវត្ថុបង់ប្រាក់តាមសាច់ប្រាក់ ធនាគារ និងកាបូបឌីជីថល') }}
            </small>
        </h1>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('loan-management.reports.dashboard', request()->query()) }}" class="btn btn-default btn-sm" style="font-weight: 600; border-radius: 6px;">
                <i class="fa fa-dashboard"></i> {{ $t('Dashboard Reports', 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង') }}
            </a>
            <button type="button" class="btn btn-success btn-sm" onclick="window.print();" style="font-weight: 600; border-radius: 6px;">
                <i class="fa fa-print"></i> {{ $t('Print Report', 'បោះពុម្ព') }}
            </button>
        </div>
    </section>

    {{-- 5 KPI Metric Cards --}}
    <div class="lm-finance-summary-grid">
        <div class="lm-finance-card lm-finance-green">
            <div class="lm-finance-icon"><i class="fa fa-money"></i></div>
            <div class="lm-finance-copy">
                <small>{{ $t('Total Collected', 'ប្រមូលបានសរុប') }}</small>
                <strong>{{ $money($totals['total']) }}</strong>
            </div>
        </div>
        <div class="lm-finance-card lm-finance-blue">
            <div class="lm-finance-icon"><i class="fa fa-list"></i></div>
            <div class="lm-finance-copy">
                <small>{{ $t('Transactions', 'ប្រតិបត្តិការ') }}</small>
                <strong>{{ number_format((float) $totals['count'], 0) }}</strong>
            </div>
        </div>
        <div class="lm-finance-card lm-finance-cyan">
            <div class="lm-finance-icon"><i class="fa fa-credit-card"></i></div>
            <div class="lm-finance-copy">
                <small>{{ $t('Digital Channels', 'បង់តាមឌីជីថល') }}</small>
                <strong>{{ $money($digitalTotal) }}</strong>
            </div>
        </div>
        <div class="lm-finance-card lm-finance-orange">
            <div class="lm-finance-icon"><i class="fa fa-briefcase"></i></div>
            <div class="lm-finance-copy">
                <small>{{ $t('Cash', 'លុយសុទ្ធ') }}</small>
                <strong>{{ $money($totals['cash']) }}</strong>
            </div>
        </div>
        <div class="lm-finance-card lm-finance-slate">
            <div class="lm-finance-icon"><i class="fa fa-ellipsis-h"></i></div>
            <div class="lm-finance-copy">
                <small>{{ $t('Other', 'ផ្សេងៗ') }}</small>
                <strong>{{ $money($totals['other']) }}</strong>
            </div>
        </div>
    </div>

    {{-- Ultimate POS Standard Collapsible Filters Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.reports.payment-summary-by-type') }}" id="paymentSummaryFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Date Range --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $t('Date Range', 'ចន្លោះថ្ងៃ') }}</label>
                    <input type="text" name="date_range" id="paymentSummaryDateRange" value="{{ $dateRangeDisplay }}" class="form-control" placeholder="{{ $t('Select date range', 'ជ្រើសរើសចន្លោះថ្ងៃ') }}" autocomplete="off">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                </div>

                {{-- Location --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $t('Location / Branch', 'សាខា / ទីតាំង') }}</label>
                    <select name="location_id" class="form-control">
                        <option value="">{{ $t('All Locations', 'គ្រប់សាខា') }}</option>
                        @foreach($locations as $id => $name)
                            <option value="{{ $id }}" {{ (string) ($filters['location_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search Keyword --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $t('Search Keyword', 'ស្វែងរក') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $t('Installment #, invoice, customer...', 'កម្ចី វិក្កយបត្រ អតិថិជន...') }}">
                </div>

                {{-- Action Buttons --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $t('Apply', 'អនុវត្ត') }}
                    </button>
                    <a href="{{ route('loan-management.reports.payment-summary-by-type') }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $t('Reset', 'សម្អាត') }}
                    </a>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $t('Financial Payment Channel Summary', 'របាយការណ៍សង្ខេបការបង់ប្រាក់តាមប្រភពនីមួយៗ')])
        <div class="table-responsive">
            <table class="lm-table-dense table table-bordered table-striped table-hover" id="paymentSummaryTable" style="width: 100%; margin-bottom: 0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th>{{ $t('Payment Type', 'ប្រភេទការបង់') }}</th>
                        <th class="text-right">{{ $t('Count', 'ចំនួន') }}</th>
                        <th class="text-right">{{ $t('Cash', 'លុយសុទ្ធ') }}</th>
                        <th class="text-right">ABA</th>
                        <th class="text-right">ACLEDA</th>
                        <th class="text-right">WING</th>
                        <th class="text-right">E&amp;T</th>
                        <th class="text-right">CARD</th>
                        <th class="text-right">{{ $t('Other', 'ផ្សេងៗ') }}</th>
                        <th class="text-right">{{ $t('Total Collected', 'បង់សរុប') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $rowKey = strtolower(trim((string)($row['type'] ?? $row['label'] ?? '')));
                            $typeClass = match(true) {
                                str_contains($rowKey, 'pay') || str_contains($rowKey, 'off') => 'lm-type-payoff',
                                str_contains($rowKey, 'deposit') || str_contains($rowKey, 'loan') || str_contains($rowKey, 'down') => 'lm-type-deposit',
                                str_contains($rowKey, 'advance') => 'lm-type-advance',
                                str_contains($rowKey, 'penalty') => 'lm-type-penalty',
                                default => 'lm-type-monthly',
                            };
                            $typeIcon = match(true) {
                                str_contains($rowKey, 'pay') || str_contains($rowKey, 'off') => 'fa fa-check-circle',
                                str_contains($rowKey, 'deposit') || str_contains($rowKey, 'loan') || str_contains($rowKey, 'down') => 'fa fa-bookmark',
                                str_contains($rowKey, 'advance') => 'fa fa-forward',
                                str_contains($rowKey, 'penalty') => 'fa fa-exclamation-circle',
                                default => 'fa fa-calendar-check-o',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="lm-type-badge {{ $typeClass }}">
                                    <i class="{{ $typeIcon }}"></i> {{ $row['label'] }}
                                </span>
                            </td>
                            <td class="text-right">{{ number_format((float) ($row['count'] ?? 0), 0) }}</td>
                            <td class="text-right">{{ $money($row['cash'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['aba'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['acleda'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['wing'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['et'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['card'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['other'] ?? 0) }}</td>
                            <td class="text-right"><strong style="color: #0284c7;">{{ $money($row['total'] ?? 0) }}</strong></td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
                @if(!empty($rows))
                    <tfoot>
                        <tr>
                            <th>{{ $t('Grand Total', 'សរុបរួម') }}</th>
                            <th class="text-right">{{ number_format((float) $totals['count'], 0) }}</th>
                            <th class="text-right">{{ $money($totals['cash']) }}</th>
                            <th class="text-right">{{ $money($totals['aba']) }}</th>
                            <th class="text-right">{{ $money($totals['acleda']) }}</th>
                            <th class="text-right">{{ $money($totals['wing']) }}</th>
                            <th class="text-right">{{ $money($totals['et']) }}</th>
                            <th class="text-right">{{ $money($totals['card']) }}</th>
                            <th class="text-right">{{ $money($totals['other']) }}</th>
                            <th class="text-right" style="color: #0284c7;">{{ $money($totals['total']) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
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
    var $dateRange = $('#paymentSummaryDateRange');
    var $filterForm = $('#paymentSummaryFilterForm');
    var displayDateFormat = window.moment_date_format || 'MM-DD-YYYY';
    var dateRangeSettings = window.dateRangeSettings ? $.extend(true, {}, window.dateRangeSettings) : {};

    if (window.moment && $.fn.daterangepicker && $dateRange.length) {
        var startDate = @json($dateFrom) ? moment(@json($dateFrom)) : moment().startOf('month');
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
                $filterForm.submit();
            })
            .on('cancel.daterangepicker', function () {
                $(this).val('');
                $filterForm.find('[name="date_from"], [name="date_to"]').val('');
                $filterForm.submit();
            });
    }

    // Initialize DataTables with Ultimate POS standard toolbar
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#paymentSummaryTable')) {
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

        $('#paymentSummaryTable').DataTable({
            dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
            buttons: tableButtons,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $t('All', 'ទាំងអស់') }}"]],
            order: [[1, 'desc']],
            autoWidth: false,
            language: {
                search: '',
                searchPlaceholder: 'Search ...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: '{{ $t("No payment summary found.", "រកមិនឃើញសង្ខេបការបង់ប្រាក់") }}',
                info: '{{ $t("Showing _START_ to _END_ of _TOTAL_ entries", "បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ") }}',
                infoEmpty: '{{ $t("Showing 0 to 0 of 0 entries", "បង្ហាញ 0 នៃ 0 ធាតុ") }}',
                infoFiltered: '({{ $t("filtered from _MAX_ total entries", "ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប") }})',
                paginate: {
                    first: '{{ $t("First", "ដំបូង") }}',
                    last: '{{ $t("Last", "ចុងក្រោយ") }}',
                    next: '{{ $t("Next", "បន្ទាប់") }}',
                    previous: '{{ $t("Previous", "មុន") }}'
                }
            },
            columnDefs: [
                { targets: [1, 2, 3, 4, 5, 6, 7, 8, 9], className: 'text-right' }
            ]
        });
    }
});
</script>
@endsection
