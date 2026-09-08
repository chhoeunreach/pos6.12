@extends('loanmanagement::layouts.app')
@section('title', 'Overdue Accounts & Delinquency Management')

@php
    $tabs = [
        'late_loans' => 'Overdue Contracts',
        'today_due' => 'Due Today (Urgent)',
    ];
    $activeTab = $tab ?? request('tab', 'late_loans');
    $isKhmer = $isKhmer ?? (session('user.language', config('app.locale')) === 'km');
    $bi = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$'.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* Executive Overdue Dashboard Styling */
    .od-page {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #0f172a;
    }

    /* Hero Banner */
    .od-hero {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 22px;
        margin-bottom: 18px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .od-hero-title-group {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .od-hero-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .od-hero-title {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        line-height: 1.25;
    }
    .od-hero-subtitle {
        font-size: 12px;
        color: #64748b;
        margin: 2px 0 0 0;
    }
    .od-hero-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    /* Executive KPI Cards */
    .od-cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 18px;
    }
    @media (max-width: 1200px) {
        .od-cards { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .od-cards { grid-template-columns: 1fr; }
    }

    .od-card {
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 15px 16px 13px 16px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 124px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none !important;
        color: inherit !important;
    }
    .od-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.1);
        border-color: #cbd5e1;
    }

    .od-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .od-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }
    .od-card-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .od-card-metric {
        font-size: 24px;
        font-weight: 800;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        margin: 4px 0;
    }
    .od-card-sub {
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* Card Themes */
    .od-card-overdue {
        border-top: 3.5px solid #ef4444 !important;
        background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%) !important;
    }
    .od-card-overdue .od-card-metric { color: #dc2626; }
    .od-card-overdue .od-card-icon { background: #fee2e2; color: #dc2626; }
    .od-card-overdue .od-card-sub { color: #dc2626; }

    .od-card-today {
        border-top: 3.5px solid #f59e0b !important;
        background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%) !important;
    }
    .od-card-today .od-card-metric { color: #d97706; }
    .od-card-today .od-card-icon { background: #fef3c7; color: #d97706; }
    .od-card-today .od-card-sub { color: #b45309; }

    .od-card-balance {
        border-top: 3.5px solid #d97706 !important;
    }
    .od-card-balance .od-card-metric { color: #d97706; }
    .od-card-balance .od-card-icon { background: #ffedd5; color: #ea580c; }
    .od-card-balance .od-card-sub { color: #c2410c; }

    .od-card-collected {
        border-top: 3.5px solid #2563eb !important;
    }
    .od-card-collected .od-card-metric { color: #2563eb; }
    .od-card-collected .od-card-icon { background: #eff6ff; color: #2563eb; }
    .od-card-collected .od-card-sub { color: #1d4ed8; }

    /* Modern Tabs */
    .od-nav-tabs {
        display: inline-flex;
        background: #f1f5f9;
        border-radius: 9px;
        padding: 4px;
        gap: 4px;
        margin-bottom: 16px;
        border: 1px solid #e2e8f0;
    }
    .od-tab-btn {
        padding: 7px 16px;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 700;
        color: #475569;
        text-decoration: none !important;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .od-tab-btn:hover {
        color: #0f172a;
        background: rgba(255,255,255,0.6);
    }
    .od-tab-btn.is-active {
        background: #ffffff;
        color: #0284c7;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    /* Table Container */
    .od-table-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }
    .od-table-box-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .od-table-box-title {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    /* DataTable Overrides */
    .ir-loan-link {
        font-weight: 700;
        color: #0284c7;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        background: #f0f9ff;
        padding: 2px 7px;
        border-radius: 5px;
        border: 1px solid #bae6fd;
    }
    .ir-customer-cell { display: flex; flex-direction: column; gap: 1px; }
    .ir-customer-name { color: #0f172a; font-weight: 700; }
    .ir-customer-phone { font-size: 11px; color: #64748b; }
    .ir-due-overdue {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #dc2626;
        background: #fef2f2;
        padding: 2px 7px;
        border-radius: 5px;
        border: 1px solid #fecaca;
    }
    .ir-due-today {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #d97706;
        background: #fffbeb;
        padding: 2px 7px;
        border-radius: 5px;
        border: 1px solid #fde68a;
    }
    .ir-money-progress { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
    .ir-mini-bar { width: 65px; height: 4px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
    .ir-mini-fill { height: 100%; background: #10b981; border-radius: 999px; }
</style>
@endsection

@section('content_body')
<div class="od-page">

    {{-- Hero Banner --}}
    <div class="od-hero">
        <div class="od-hero-title-group">
            <div class="od-hero-icon">
                <i class="fa fa-exclamation-circle"></i>
            </div>
            <div>
                <h1 class="od-hero-title">{{ $bi('Overdue Accounts & Collections Risk', 'កម្ចីហួសកំណត់ និងការគ្រប់គ្រងហានិភ័យ') }}</h1>
                <p class="od-hero-subtitle">{{ $bi('Monitor past-due schedules, loans at risk, and actionable daily recovery', 'តាមដានកាលវិភាគហួសកំណត់ កម្ចីមានហានិភ័យ និងការប្រមូលប្រចាំថ្ងៃ') }}</p>
            </div>
        </div>
        <div class="od-hero-actions">
            <a href="{{ route('loan-management.reports.index') }}" class="btn btn-default btn-sm" style="font-weight:700; border-radius:7px;">
                <i class="fa fa-line-chart" style="color:#0284c7;"></i> {{ $bi('Full Portfolio Reports', 'របាយការណ៍ផលប័ត្រពេញលេញ') }}
            </a>
            <button type="button" class="btn btn-primary btn-sm" id="btnRefreshOverdue" style="font-weight:700; border-radius:7px; background:#0284c7; border-color:#0284c7;">
                <i class="fa fa-refresh"></i> {{ $bi('Refresh', 'ផ្ទុកឡើងវិញ') }}
            </button>
        </div>
    </div>

    {{-- 4 Essential Risk KPI Cards --}}
    <div class="od-cards">
        {{-- Card 1: Overdue Contracts --}}
        <a href="{{ route('loan-management.overdue.index', ['tab' => 'late_loans']) }}" class="od-card od-card-overdue">
            <div class="od-card-top">
                <span class="od-card-label">{{ $bi('Overdue Accounts', 'កម្ចីហួសកំណត់') }}</span>
                <span class="od-card-icon"><i class="fa fa-warning"></i></span>
            </div>
            <div class="od-card-metric">{{ $number($summary['overdue'] ?? 0) }}</div>
            <div class="od-card-sub">
                <i class="fa fa-shield"></i> {{ $money($summary['overdue_balance'] ?? 0) }} {{ $bi('balance at risk', 'ប្រឈមហានិភ័យ') }}
            </div>
        </a>

        {{-- Card 2: Due Today --}}
        <a href="{{ route('loan-management.overdue.index', ['tab' => 'today_due']) }}" class="od-card od-card-today">
            <div class="od-card-top">
                <span class="od-card-label">{{ $bi('Due Today (Urgent)', 'ដល់ថ្ងៃបង់ថ្ងៃនេះ') }}</span>
                <span class="od-card-icon"><i class="fa fa-calendar-check-o"></i></span>
            </div>
            <div class="od-card-metric">{{ $number($summary['due_today'] ?? 0) }}</div>
            <div class="od-card-sub">
                <i class="fa fa-bolt"></i> {{ $bi('Requires collector follow-up today', 'ត្រូវតាមដានថ្ងៃនេះ') }}
            </div>
        </a>

        {{-- Card 3: Total Outstanding Debt --}}
        <div class="od-card od-card-balance">
            <div class="od-card-top">
                <span class="od-card-label">{{ $bi('Outstanding Balance', 'សមតុល្យនៅសល់') }}</span>
                <span class="od-card-icon"><i class="fa fa-balance-scale"></i></span>
            </div>
            <div class="od-card-metric">{{ $money($summary['balance'] ?? 0) }}</div>
            <div class="od-card-sub">
                <i class="fa fa-clock-o"></i> {{ $bi('Total remaining receivables', 'បំណុលនៅសល់សរុប') }}
            </div>
        </div>

        {{-- Card 4: Total Collected --}}
        <div class="od-card od-card-collected">
            <div class="od-card-top">
                <span class="od-card-label">{{ $bi('Total Collected', 'បានបង់សរុប') }}</span>
                <span class="od-card-icon"><i class="fa fa-credit-card"></i></span>
            </div>
            <div class="od-card-metric">{{ $money($summary['paid'] ?? 0) }}</div>
            <div class="od-card-sub">
                <i class="fa fa-check-circle"></i> {{ $bi('Recovered to date', 'បានប្រមូលមកវិញ') }}
            </div>
        </div>
    </div>

    {{-- Tab Selector --}}
    <div class="od-nav-tabs">
        <a href="{{ route('loan-management.overdue.index', ['tab' => 'late_loans']) }}" class="od-tab-btn {{ $activeTab === 'late_loans' ? 'is-active' : '' }}">
            <i class="fa fa-exclamation-triangle" style="color:#dc2626;"></i> {{ $bi('Overdue Contracts', 'កម្ចីហួសកំណត់') }} ({{ $number($summary['overdue'] ?? 0) }})
        </a>
        <a href="{{ route('loan-management.overdue.index', ['tab' => 'today_due']) }}" class="od-tab-btn {{ $activeTab === 'today_due' ? 'is-active' : '' }}">
            <i class="fa fa-bell-o" style="color:#d97706;"></i> {{ $bi('Due Today (Urgent)', 'ដល់ថ្ងៃបង់ថ្ងៃនេះ') }} ({{ $number($summary['due_today'] ?? 0) }})
        </a>
    </div>

    {{-- Overdue DataTable Ledger Box --}}
    <div class="od-table-box">
        <div class="od-table-box-header">
            <h3 class="od-table-box-title">
                <i class="fa fa-list-alt" style="color:#ef4444;"></i>
                {{ $activeTab === 'today_due' ? $bi('Due Today Contracts', 'បញ្ជីកម្ចីដល់ថ្ងៃបង់ថ្ងៃនេះ') : $bi('Overdue Installment Contracts', 'បញ្ជីកម្ចីហួសកំណត់កាលបរិច្ឆេទ') }}
            </h3>
            <span class="badge" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; font-weight:700; padding:4px 10px;">
                <i class="fa fa-warning"></i> {{ $activeTab === 'today_due' ? $number($summary['due_today'] ?? 0) : $number($summary['overdue'] ?? 0) }} {{ $bi('Records', 'កំណត់ត្រា') }}
            </span>
        </div>

        <div class="table-responsive" style="padding: 10px 14px;">
            <table class="table table-hover table-striped" id="overdueTable" style="width: 100%;">
                <thead>
                    <tr style="background:#f8fafc; font-size:11px; text-transform:uppercase; color:#475569;">
                        <th>{{ $bi('Installment #', 'លេខកម្ចី') }}</th>
                        <th>{{ $bi('Date', 'ថ្ងៃ') }}</th>
                        <th>{{ $bi('Invoice', 'វិក្កយបត្រ') }}</th>
                        <th>{{ $bi('Customer', 'អតិថិជន') }}</th>
                        <th class="text-right">{{ $bi('Total', 'សរុប') }}</th>
                        <th class="text-right">{{ $bi('Paid', 'បានបង់') }}</th>
                        <th class="text-right">{{ $bi('Balance at Risk', 'សមតុល្យប្រឈម') }}</th>
                        <th>{{ $bi('Due Date', 'កាលបរិច្ឆេទ') }}</th>
                        <th>{{ $bi('Collector', 'អ្នកប្រមូល') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('loan_js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script>
$(document).ready(function () {
    var isKhmer = {{ $isKhmer ? 'true' : 'false' }};
    var overdueTable = $('#overdueTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[7, 'asc']],
        ajax: {
            url: "{{ route('loan-management.overdue.index') }}",
            data: function (d) {
                d.tab = "{{ $activeTab }}";
            }
        },
        columns: [
            { data: 'loan_number', name: 'l.loan_number' },
            { data: 'loan_date', name: 'l.loan_date' },
            { data: 'invoice_no', name: 'l.source_invoice_no' },
            { data: 'customer_name', name: 'l.customer_name_snapshot' },
            { data: 'total_amount', name: 'l.total_amount', className: 'text-right' },
            { data: 'paid_amount', name: 'l.paid_amount', className: 'text-right' },
            { data: 'balance_amount', name: 'l.balance_amount', className: 'text-right' },
            { data: 'next_due_date', name: 'next_due_date' },
            { data: 'collector_name', name: 'l.collector_name_snapshot' }
        ],
        language: {
            processing: '<i class="fa fa-spinner fa-spin" style="color:#dc2626;"></i> ' + (isKhmer ? 'កំពុងទាញយក...' : 'Loading overdue records...'),
            search: '',
            searchPlaceholder: isKhmer ? 'ស្វែងរក...' : 'Search overdue...',
            emptyTable: isKhmer ? 'មិនមានទិន្នន័យហួសកំណត់ទេ' : 'No overdue accounts found.'
        }
    });

    $('#btnRefreshOverdue').on('click', function () {
        var $btn = $(this);
        $btn.find('i').addClass('fa-spin');
        overdueTable.ajax.reload(function () {
            $btn.find('i').removeClass('fa-spin');
        });
    });
});
</script>
@endsection
