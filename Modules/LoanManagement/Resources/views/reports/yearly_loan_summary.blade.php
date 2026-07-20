@extends('loanmanagement::layouts.app')
@section('title', (session('user.language', config('app.locale')) === 'km') ? 'របាយការណ៍សង្ខេបកម្ចីប្រចាំឆ្នាំ' : 'Yearly Loan Summary')

@php
    $isKhmer = $isKhmer ?? session('user.language', config('app.locale')) === 'km';
    $bi = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
    $yearOptions = range((int) now()->format('Y'), 2000);
    $yearlyLoanDetailFilterPayload = [
        'start_year' => $filters['start_year'],
        'end_year' => $filters['end_year'],
        'location_id' => $filters['location_id'],
        'search' => $filters['search'],
    ];
@endphp

@section('loan_css')
<style>
    .yls-wrap {
        color: #1f2937;
    }
    .content-header.yls-wrap {
        padding: 10px 15px 4px;
    }
    .content.yls-wrap {
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .yls-header {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        margin-bottom: 8px;
    }
    .yls-title {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
    }
    .yls-subtitle {
        margin: 2px 0 0;
        color: #64748b;
        font-size: 12px;
        display: none;
    }
    .yls-card-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(150px, 1fr));
        gap: 8px;
        margin-bottom: 8px;
    }
    .yls-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        padding: 6px 8px;
        display: flex;
        gap: 8px;
        align-items: center;
        min-height: 48px;
        box-shadow: 0 3px 10px rgba(15, 23, 42, .05);
    }
    .yls-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        font-size: 17px;
        flex: 0 0 32px;
    }
    .yls-card small {
        display: block;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 2px;
        font-size: 11px;
        line-height: 1.2;
    }
    .yls-card strong {
        font-size: 14px;
        line-height: 1.2;
        word-break: break-word;
    }
    .yls-tone-teal { color: #0f9696; background: #ecfeff; border: 1px solid #bae6fd; }
    .yls-tone-blue { color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; }
    .yls-tone-green { color: #16803d; background: #f0fdf4; border: 1px solid #bbf7d0; }
    .yls-tone-orange { color: #ea580c; background: #fff7ed; border: 1px solid #fed7aa; }
    .yls-tone-purple { color: #6d28d9; background: #f5f3ff; border: 1px solid #ddd6fe; }
    .yls-tone-red { color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; }
    .yls-filter-box,
    .yls-table-box {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        padding: 6px;
        margin-bottom: 6px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .05);
    }
    .yls-table-box .table-responsive {
        overflow-x: auto;
    }
    .yls-filter-row {
        display: grid;
        grid-template-columns: 130px 130px 190px minmax(220px, 1fr) auto auto;
        gap: 6px;
        align-items: end;
    }
    .yls-filter-row label {
        margin-bottom: 3px;
        font-size: 11px;
        color: #475569;
    }
    .yls-filter-row .form-control {
        height: 28px;
        padding: 4px 8px;
        font-size: 12px;
    }
    .yls-filter-row .btn,
    .yls-header .btn {
        padding: 4px 9px;
        font-size: 12px;
        line-height: 1.4;
    }
    .yls-table {
        width: 100%;
        min-width: 1280px;
        margin-bottom: 0;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
    }
    .yls-table thead th {
        text-align: center;
        vertical-align: middle !important;
        white-space: normal;
        font-size: 10px;
        line-height: 1.15;
        border-color: #dbe3ef !important;
        padding: 4px 4px !important;
        font-weight: 700;
    }
    .yls-table tbody td,
    .yls-table tfoot th {
        vertical-align: middle !important;
        white-space: nowrap;
        font-size: 10px;
        padding: 4px 4px !important;
        border-color: #e6edf5 !important;
    }
    .yls-table th:first-child,
    .yls-table td:first-child {
        width: 38px;
    }
    .yls-table th:nth-child(2),
    .yls-table td:nth-child(2) {
        width: 56px;
    }
    .yls-table thead tr:first-child th {
        color: #fff;
        height: 28px;
        border-bottom: 0 !important;
        box-shadow: inset 0 -2px 0 rgba(255, 255, 255, .35);
    }
    .yls-table thead tr:nth-child(2) th {
        height: 28px;
    }
    .yls-group-label {
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    .yls-group-loans { background: #0f766e; color: #fff; }
    .yls-group-payments { background: #15803d; color: #fff; }
    .yls-group-closed { background: #c2410c; color: #fff; }
    .yls-group-risk { background: #dc2626; color: #fff; }
    .yls-table thead tr:nth-child(2) th:nth-child(n+1):nth-child(-n+4),
    .yls-table tbody td:nth-child(n+3):nth-child(-n+6),
    .yls-table tfoot th:nth-child(n+2):nth-child(-n+5) {
        background: #f0fdfa;
    }
    .yls-table thead tr:nth-child(2) th:nth-child(n+5):nth-child(-n+8),
    .yls-table tbody td:nth-child(n+7):nth-child(-n+10),
    .yls-table tfoot th:nth-child(n+6):nth-child(-n+9) {
        background: #f0fdf4;
    }
    .yls-table thead tr:nth-child(2) th:nth-child(n+9):nth-child(-n+14),
    .yls-table tbody td:nth-child(n+11):nth-child(-n+16),
    .yls-table tfoot th:nth-child(n+10):nth-child(-n+15) {
        background: #fff7ed;
    }
    .yls-table thead tr:nth-child(2) th:nth-child(n+15):nth-child(-n+20),
    .yls-table tbody td:nth-child(n+17):nth-child(-n+22),
    .yls-table tfoot th:nth-child(n+16):nth-child(-n+21) {
        background: #fef2f2;
    }
    .yls-table tbody tr:hover td {
        filter: brightness(.98);
    }
    .yls-table tbody tr {
        cursor: pointer;
    }
    .yls-table tbody tr:hover td:first-child {
        box-shadow: inset 3px 0 0 #2563eb;
    }
    .yls-total-row {
        background: #e2e8f0;
        border-top: 2px solid #94a3b8;
        box-shadow: inset 0 2px 0 #64748b;
    }
    .yls-generated {
        margin-top: 6px;
        color: #64748b;
        font-size: 11px;
    }
    .yls-loan-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none;
        background: rgba(15, 23, 42, .62);
        padding: 18px;
    }
    .yls-loan-modal.is-open {
        display: flex;
    }
    .yls-loan-modal-dialog {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
    }
    .yls-loan-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid #dbe4ef;
        background: #f8fafc;
    }
    .yls-loan-modal-title {
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
    }
    .yls-loan-modal-close {
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #fff;
        color: #334155;
        height: 30px;
        padding: 0 11px;
        font-size: 12px;
        font-weight: 800;
    }
    .yls-loan-modal iframe {
        width: 100%;
        height: 100%;
        border: 0;
        background: #eef3f8;
    }
    @media (max-width: 1200px) {
        .yls-card-grid { grid-template-columns: repeat(3, minmax(150px, 1fr)); }
        .yls-filter-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .yls-header { align-items: flex-start; flex-direction: column; }
        .yls-card-grid { grid-template-columns: 1fr; }
        .yls-filter-row { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content_body')
<section class="content-header yls-wrap">
    <div class="yls-header">
        <div>
            <h1 class="yls-title">{{ $bi('Yearly Loan Summary', 'របាយការណ៍សង្ខេបកម្ចីប្រចាំឆ្នាំ') }}</h1>
            <p class="yls-subtitle">{{ $bi('Annual loan, schedule, collection, deposit, and overdue totals.', 'សរុបកម្ចី កាលវិភាគ ការប្រមូលប្រាក់ ប្រាក់កក់ និងហួសកំណត់ប្រចាំឆ្នាំ') }}</p>
        </div>
        <a class="btn btn-success"
           href="{{ route('loan-management.reports.yearly-loan-summary', array_merge(request()->query(), ['export' => 'csv'])) }}">
            <i class="fa fa-file-excel-o"></i> {{ $bi('Export Excel', 'នាំចេញ Excel') }}
        </a>
    </div>
</section>

<section class="content yls-wrap">
    <div class="yls-card-grid">
        @foreach($payload['cards'] as $card)
            <div class="yls-card">
                <div class="yls-card-icon yls-tone-{{ $card['tone'] }}">
                    <i class="{{ $card['icon'] }}"></i>
                </div>
                <div>
                    <small>{{ $card['label'] }}</small>
                    <strong>{{ $card['value'] }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    <div class="yls-filter-box">
        <form method="GET" action="{{ route('loan-management.reports.yearly-loan-summary') }}" id="ylsFilterForm">
            <div class="yls-filter-row">
                <div class="form-group" style="margin:0;">
                    <label>{{ $bi('Start Year', 'ឆ្នាំចាប់ផ្តើម') }}</label>
                    <select name="start_year" class="form-control">
                        @foreach($yearOptions as $year)
                            <option value="{{ $year }}" {{ (int) $filters['start_year'] === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>{{ $bi('End Year', 'ឆ្នាំបញ្ចប់') }}</label>
                    <select name="end_year" class="form-control">
                        @foreach($yearOptions as $year)
                            <option value="{{ $year }}" {{ (int) $filters['end_year'] === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>{{ $bi('Location', 'សាខា') }}</label>
                    <select name="location_id" class="form-control">
                        <option value="">{{ $bi('All Locations', 'សាខាទាំងអស់') }}</option>
                        @foreach($locations as $id => $name)
                            <option value="{{ $id }}" {{ (string) $filters['location_id'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>{{ $bi('Search', 'ស្វែងរក') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ $bi('Loan #, invoice, customer, phone', 'លេខកម្ចី វិក្កយបត្រ អតិថិជន ទូរស័ព្ទ') }}">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> {{ $bi('Filter', 'ចម្រោះ') }}</button>
                <a href="{{ route('loan-management.reports.yearly-loan-summary') }}" class="btn btn-default"><i class="fa fa-refresh"></i> {{ $bi('Reset', 'កំណត់ឡើងវិញ') }}</a>
            </div>
        </form>
    </div>

    <div class="yls-table-box">
        <div class="table-responsive">
            <table class="table table-bordered table-hover yls-table">
                <thead>
                    <tr>
                        <th rowspan="2">{{ $bi('No.', 'ល.រ') }}</th>
                        <th rowspan="2">{{ $bi('Year', 'ឆ្នាំ') }}</th>
                        <th colspan="4" class="yls-group-loans" title="{{ $bi('Registered Installment Customers', 'អតិថិជនចុះឈ្មោះរំលស់') }}"><i class="fa fa-users"></i> <span class="yls-group-label">{{ $bi('Registered', 'ចុះឈ្មោះ') }}</span></th>
                        <th colspan="4" class="yls-group-payments" title="{{ $bi('Installment Customers Paid Total', 'អតិថិជនរំលស់បានបង់ទូរទៅ') }}"><i class="fa fa-money"></i> <span class="yls-group-label">{{ $bi('Paid Total', 'បានបង់សរុប') }}</span></th>
                        <th colspan="6" class="yls-group-closed" title="{{ $bi('Paid Off Installment Customers', 'អតិថិជនរំលស់បានបង់ផ្ដាច់') }}"><i class="fa fa-check-circle-o"></i> <span class="yls-group-label">{{ $bi('Paid Off', 'បង់ផ្ដាច់') }}</span></th>
                        <th colspan="6" class="yls-group-risk" title="{{ $bi('Bad Installment Customers', 'អតិថិជនរំលស់ខូច') }}"><i class="fa fa-warning"></i> <span class="yls-group-label">{{ $bi('Bad / Risk', 'ខូច / ហានិភ័យ') }}</span></th>
                    </tr>
                    <tr>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $bi('Interest', 'ការប្រាក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Collection', 'ប្រមូលប្រាក់') }}</th>
                        <th>{{ $bi('Deposit', 'ប្រាក់កក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $bi('Interest', 'ការប្រាក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Paid', 'បានបង់') }}</th>
                        <th>{{ $bi('Balance', 'សមតុល្យ') }}</th>
                        <th>{{ $bi('Count', 'ចំនួន') }}</th>
                        <th>{{ $bi('Principal', 'ប្រាក់ដើម') }}</th>
                        <th>{{ $bi('Interest', 'ការប្រាក់') }}</th>
                        <th>{{ $bi('Total', 'សរុប') }}</th>
                        <th>{{ $bi('Paid', 'បានបង់') }}</th>
                        <th>{{ $bi('Balance', 'សមតុល្យ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payload['rows'] as $row)
                        <tr data-loan-detail-year="{{ $row['year'] }}" title="{{ $bi('Click to view loan details', 'ចុចដើម្បីមើលព័ត៌មានលម្អិតកម្ចី') }}">
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="text-center"><strong>{{ $row['year'] }}</strong></td>
                            <td class="text-right">{{ $number($row['loan_count']) }}</td>
                            <td class="text-right">{{ $money($row['principal_total']) }}</td>
                            <td class="text-right">{{ $money($row['interest_total']) }}</td>
                            <td class="text-right">{{ $money($row['loan_total']) }}</td>
                            <td class="text-right">{{ $number($row['paid_customer_count']) }}</td>
                            <td class="text-right">{{ $money($row['collection_payment_total']) }}</td>
                            <td class="text-right">{{ $money($row['deposit_payment_total']) }}</td>
                            <td class="text-right">{{ $money($row['payment_total']) }}</td>
                            <td class="text-right">{{ $number($row['closed_count']) }}</td>
                            <td class="text-right">{{ $money($row['closed_principal_total']) }}</td>
                            <td class="text-right">{{ $money($row['closed_interest_total']) }}</td>
                            <td class="text-right">{{ $money($row['closed_loan_total']) }}</td>
                            <td class="text-right">{{ $money($row['closed_paid_total']) }}</td>
                            <td class="text-right">{{ $money($row['closed_balance_total']) }}</td>
                            <td class="text-right">{{ $number($row['bad_count']) }}</td>
                            <td class="text-right">{{ $money($row['bad_principal_total']) }}</td>
                            <td class="text-right">{{ $money($row['bad_interest_total']) }}</td>
                            <td class="text-right">{{ $money($row['bad_loan_total']) }}</td>
                            <td class="text-right">{{ $money($row['bad_paid_total']) }}</td>
                            <td class="text-right">{{ $money($row['bad_balance_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php($total = $payload['totals'])
                    <tr class="yls-total-row">
                        <th colspan="2" class="text-center">{{ $bi('Total', 'សរុប') }}</th>
                        <th class="text-right">{{ $number($total['loan_count']) }}</th>
                        <th class="text-right">{{ $money($total['principal_total']) }}</th>
                        <th class="text-right">{{ $money($total['interest_total']) }}</th>
                        <th class="text-right">{{ $money($total['loan_total']) }}</th>
                        <th class="text-right">{{ $number($total['paid_customer_count']) }}</th>
                        <th class="text-right">{{ $money($total['collection_payment_total']) }}</th>
                        <th class="text-right">{{ $money($total['deposit_payment_total']) }}</th>
                        <th class="text-right">{{ $money($total['payment_total']) }}</th>
                        <th class="text-right">{{ $number($total['closed_count']) }}</th>
                        <th class="text-right">{{ $money($total['closed_principal_total']) }}</th>
                        <th class="text-right">{{ $money($total['closed_interest_total']) }}</th>
                        <th class="text-right">{{ $money($total['closed_loan_total']) }}</th>
                        <th class="text-right">{{ $money($total['closed_paid_total']) }}</th>
                        <th class="text-right">{{ $money($total['closed_balance_total']) }}</th>
                        <th class="text-right">{{ $number($total['bad_count']) }}</th>
                        <th class="text-right">{{ $money($total['bad_principal_total']) }}</th>
                        <th class="text-right">{{ $money($total['bad_interest_total']) }}</th>
                        <th class="text-right">{{ $money($total['bad_loan_total']) }}</th>
                        <th class="text-right">{{ $money($total['bad_paid_total']) }}</th>
                        <th class="text-right">{{ $money($total['bad_balance_total']) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="yls-generated">
            <i class="fa fa-clock-o"></i> {{ $bi('Generated', 'បានបង្កើត') }} {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>
</section>

<div class="yls-loan-modal" id="ylsLoanModal" aria-hidden="true">
    <div class="yls-loan-modal-dialog">
        <div class="yls-loan-modal-head">
            <div class="yls-loan-modal-title" id="ylsLoanModalTitle">{{ $bi('Loan Details', 'ព័ត៌មានលម្អិតកម្ចី') }}</div>
            <button type="button" class="yls-loan-modal-close" id="ylsLoanModalClose">{{ $bi('Close', 'បិទ') }}</button>
        </div>
        <iframe id="ylsLoanModalFrame" title="{{ $bi('Loan Details', 'ព័ត៌មានលម្អិតកម្ចី') }}"></iframe>
    </div>
</div>
@endsection

@section('loan_js')
<script>
    (function ($) {
        var $form = $('#ylsFilterForm');
        $form.on('change', 'select', function () {
            $form.trigger('submit');
        });

        var detailUrl = @json(route('loan-management.admin-loan.details'));
        var filters = @json($yearlyLoanDetailFilterPayload);
        var labels = {
            all: @json($bi('All Loans', 'កម្ចីទាំងអស់')),
            registered: @json($bi('Registered Installments', 'អតិថិជនចុះឈ្មោះរំលស់')),
            generalPaid: @json($bi('General Installments Paid', 'អតិថិជនរំលស់បានបង់ទូរទៅ')),
            paidOff: @json($bi('Paid Off', 'បង់ផ្ដាច់')),
            badDebt: @json($bi('Bad / Risk', 'ខូច / ហានិភ័យ'))
        };

        function groupForCell(index) {
            if (index >= 2 && index <= 5) return 'registered';
            if (index >= 6 && index <= 9) return 'generalPaid';
            if (index >= 10 && index <= 15) return 'paidOff';
            if (index >= 16 && index <= 21) return 'badDebt';
            return 'all';
        }

        function openLoanModal(year, group) {
            var params = new URLSearchParams();
            params.set('year', year);
            params.set('group', group);
            Object.keys(filters).forEach(function (key) {
                if (filters[key] !== null && filters[key] !== undefined && String(filters[key]) !== '') {
                    params.set(key, filters[key]);
                }
            });

            $('#ylsLoanModalTitle').text((labels[group] || labels.all) + ' - ' + year);
            $('#ylsLoanModalFrame').attr('src', detailUrl + '?' + params.toString());
            $('#ylsLoanModal').addClass('is-open').attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
        }

        function closeLoanModal() {
            $('#ylsLoanModal').removeClass('is-open').attr('aria-hidden', 'true');
            $('#ylsLoanModalFrame').attr('src', 'about:blank');
            $('body').css('overflow', '');
        }

        $('.yls-table tbody').on('click', 'td', function (event) {
            if ($(event.target).closest('a, button, input, select, textarea').length) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            var $row = $(this).closest('tr');
            var year = $row.data('loan-detail-year');
            if (!year) {
                return;
            }
            openLoanModal(year, groupForCell(this.cellIndex));
        });

        $('#ylsLoanModalClose').on('click', closeLoanModal);
        $('#ylsLoanModal').on('click', function (event) {
            if (event.target === this) {
                closeLoanModal();
            }
        });
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closeLoanModal();
            }
        });
    })(jQuery);
</script>
@endsection
