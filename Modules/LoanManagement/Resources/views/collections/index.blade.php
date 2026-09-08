@extends('loanmanagement::layouts.app')
@section('title', $definition['title'] ?? 'Due Today')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR OPERATIONS / DUE TODAY
       ========================================================= */
    .lm-col-content {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
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

    .lm-table-dense {
        width: 100% !important;
        margin-bottom: 0 !important;
        font-size: 12.5px;
    }
    .lm-table-dense th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.2px;
        padding: 9px 10px;
        border-bottom: 1px solid #cbd5e1 !important;
    }
    .lm-table-dense td {
        padding: 8px 10px;
        vertical-align: middle !important;
    }
    /* Executive KPI Cards */
    .lm-col-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    @media (max-width: 1200px) {
        .lm-col-summary-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-col-summary-grid { grid-template-columns: 1fr; }
    }
    .lm-col-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .lm-col-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }
    .lm-col-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .lm-col-copy {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .lm-col-copy small {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #64748b;
    }
    .lm-col-copy strong {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }
    .lm-col-copy span {
        font-size: 11.5px;
        color: #94a3b8;
    }
    .lm-col-blue .lm-col-icon { background: #eff6ff; color: #2563eb; }
    .lm-col-red .lm-col-icon { background: #fef2f2; color: #dc2626; }
    .lm-col-amber .lm-col-icon { background: #fffbeb; color: #d97706; }
    .lm-col-green .lm-col-icon { background: #f0fdf4; color: #16a34a; }
    .lm-col-purple .lm-col-icon { background: #faf5ff; color: #9333ea; }
</style>
@endsection

@section('content_body')
<div class="lm-col-content">
    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $definition['title'] ?? 'Due Today' }}
            @if(!empty($definition['khmer']))
                <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">{{ $definition['khmer'] }}</small>
            @endif
        </h1>
    </section>

    {{-- 4 Executive KPI Cards --}}
    @if(!empty($metrics))
    <div class="lm-col-summary-grid">
        <div class="lm-col-card lm-col-blue">
            <div class="lm-col-icon"><i class="fa fa-users"></i></div>
            <div class="lm-col-copy">
                <small>{{ $lmText('Total in Queue', 'គណនីក្នុងជួរនេះ') }}</small>
                <strong>{{ number_format((int)($metrics['total_accounts'] ?? 0)) }}</strong>
                <span>{{ $lmText('Matching case records', 'ទិន្នន័យកិច្ចសន្យាត្រូវលក្ខខណ្ឌ') }}</span>
            </div>
        </div>

        <div class="lm-col-card lm-col-red">
            <div class="lm-col-icon"><i class="fa fa-shield"></i></div>
            <div class="lm-col-copy">
                <small>{{ $lmText('Balance at Risk', 'សមតុល្យហានិភ័យ') }}</small>
                <strong>${{ number_format((float)($metrics['total_balance'] ?? 0), 2) }}</strong>
                <span>{{ $lmText('Outstanding receivables', 'ប្រាក់នៅសល់មិនទាន់ប្រមូល') }}</span>
            </div>
        </div>

        <div class="lm-col-card lm-col-amber">
            <div class="lm-col-icon"><i class="fa fa-clock-o"></i></div>
            <div class="lm-col-copy">
                <small>{{ $lmText('Overdue Aging (Avg)', 'មធ្យមភាគថ្ងៃហួស') }}</small>
                <strong>{{ $metrics['avg_dpd'] ?? 0 }} {{ $lmText('Days', 'ថ្ងៃ') }}</strong>
                <span>{{ $lmText('Max', 'អតិបរមា') }}: {{ $metrics['max_dpd'] ?? 0 }} {{ $lmText('days past due', 'ថ្ងៃហួសកាលកំណត់') }}</span>
            </div>
        </div>

        @if(!empty($metrics['ptp_amount']) && $metrics['ptp_amount'] > 0)
        <div class="lm-col-card lm-col-green">
            <div class="lm-col-icon"><i class="fa fa-handshake-o"></i></div>
            <div class="lm-col-copy">
                <small>{{ $lmText('PTP Commitments', 'សន្យាបង់ប្រាក់សរុប') }}</small>
                <strong>${{ number_format((float)($metrics['ptp_amount'] ?? 0), 2) }}</strong>
                <span>{{ $lmText('Promised settlement value', 'ទឹកប្រាក់អតិថិជនសន្យាសង') }}</span>
            </div>
        </div>
        @else
        <div class="lm-col-card lm-col-purple">
            <div class="lm-col-icon"><i class="fa fa-exclamation-triangle"></i></div>
            <div class="lm-col-copy">
                <small>{{ $lmText('High Risk Cases', 'ករណីហានិភ័យខ្ពស់') }}</small>
                <strong>{{ number_format((int)($metrics['high_risk_count'] ?? 0)) }}</strong>
                <span>{{ $lmText('Priority follow-up required', 'ត្រូវការតាមដានជាបន្ទាន់') }}</span>
            </div>
        </div>
        @endif
    </div>
    @endif

    @include('loanmanagement::collections.partials.filters')
    @include('loanmanagement::collections.partials.loan_table')
</div>
@endsection

@section('loan_js')
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
    $(document).ready(function() {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanCollectionTable')) {
            var tableButtons = [];
            if ($.fn.dataTable.Buttons) {
                tableButtons = [
                    {
                        extend: 'copy',
                        text: 'Copy',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fa fa-file-text-o"></i> Export CSV',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel-o"></i> Export Excel',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print"></i> Print',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':visible:not(.no-export)', stripHtml: true }
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
                        exportOptions: { columns: ':visible:not(.no-export)' }
                    }
                ];
            }

            $('#loanCollectionTable').DataTable({
                dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
                buttons: tableButtons,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $lmText('All', 'ទាំងអស់') }}"]],
                order: [[1, 'asc']],
                autoWidth: false,
                language: {
                    search: '',
                    searchPlaceholder: 'Search ...',
                    lengthMenu: 'Show _MENU_ entries',
                    emptyTable: '{{ $lmText("No records found.", "មិនមានទិន្នន័យទេ។") }}',
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
                    { targets: [0], orderable: false, className: 'no-export' },
                    { targets: [0, 4, 5, 7], className: 'text-center' },
                    { targets: [9], className: 'text-right' }
                ]
            });
        }
    });
</script>
@endsection
