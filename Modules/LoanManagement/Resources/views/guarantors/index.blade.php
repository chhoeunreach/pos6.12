@extends('loanmanagement::layouts.app')
@section('title', 'Loan Guarantors')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR GUARANTORS VIEW
       ========================================================= */
    .lm-guar-content {
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
</style>
@endsection

@section('content_body')
<div class="lm-guar-content">
    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $lmText('Loan Guarantors', 'អ្នកធានាកម្ចី') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $lmText('Manage loan guarantors, linked client contracts, and contact profiles', 'គ្រប់គ្រងព័ត៌មានអ្នកធានា កិច្ចសន្យាកម្ចី និងទំនាក់ទំនង') }}
            </small>
        </h1>
    </section>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible" style="border-radius: 8px; margin-bottom: 16px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-check-circle"></i> {{ is_array(session('status')) ? (session('status')['msg'] ?? 'Saved successfully.') : session('status') }}
        </div>
    @endif

    {{-- Ultimate POS Standard Filters Component (Hidden by default) --}}
    @component('components.filters', ['title' => $lmText('Filters', 'តម្រងស្វែងរក')])
        <form method="GET" action="{{ route('loan-management.guarantors.index') }}" id="loanGuarantorsFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Row 1, Col 1: Guarantor Name --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Guarantor Name:', 'ឈ្មោះអ្នកធានា:') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ request('name') }}" placeholder="{{ $lmText('Guarantor name...', 'ឈ្មោះអ្នកធានា...') }}">
                </div>

                {{-- Row 1, Col 2: Phone Number --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Phone Number:', 'លេខទូរស័ព្ទ:') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ request('phone') }}" placeholder="{{ $lmText('Phone number...', 'លេខទូរស័ព្ទ...') }}">
                </div>

                {{-- Row 1, Col 3: National ID / ID Card --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('National ID / ID Card:', 'អត្តសញ្ញាណប័ណ្ណ:') }}</label>
                    <input type="text" name="national_id" class="form-control" value="{{ request('national_id') }}" placeholder="{{ $lmText('ID Card Number...', 'លេខអត្តសញ្ញាណប័ណ្ណ...') }}">
                </div>

                {{-- Row 1, Col 4: Relationship --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Relationship:', 'ត្រូវជា:') }}</label>
                    <input type="text" name="relationship" class="form-control" value="{{ request('relationship') }}" placeholder="{{ $lmText('e.g. Spouse, Parent, Sibling...', 'ឧ. ប្តី/ប្រពន្ធ, ឪពុកម្តាយ, បងប្អូន...') }}">
                </div>

                {{-- Row 2, Col 1: Loan Number --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Loan Number:', 'លេខកិច្ចសន្យាកម្ចី:') }}</label>
                    <input type="text" name="loan_number" class="form-control" value="{{ request('loan_number') }}" placeholder="{{ $lmText('e.g. LN-0001', 'ឧ. LN-0001') }}">
                </div>

                {{-- Row 2, Col 2: Loan Status --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Loan Status:', 'ស្ថានភាពកម្ចី:') }}</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ $lmText('Active', 'សកម្ម') }}</option>
                        <option value="paid_off" {{ request('status') === 'paid_off' ? 'selected' : '' }}>{{ $lmText('Paid Off', 'បង់ផ្តាច់') }}</option>
                        <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>{{ $lmText('Overdue', 'ហួសកំណត់') }}</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ $lmText('Pending', 'រង់ចាំ') }}</option>
                    </select>
                </div>

                {{-- Row 2, Col 3: Action Buttons --}}
                <div class="lm-pos-filter-field">
                    <label>&nbsp;</label>
                    <div class="lm-pos-filter-actions">
                        <button type="submit" class="lm-btn-pos-filter">
                            <i class="fa fa-filter"></i> {{ $lmText('Filter', 'ចម្រាញ់') }}
                        </button>
                        <a href="{{ route('loan-management.guarantors.index') }}" class="lm-btn-pos-reset">
                            <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}
                        </a>
                    </div>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $lmText('All Loan Guarantors', 'អ្នកធានាកម្ចីទាំងអស់')])
        <div class="table-responsive">
            <table class="lm-table-dense table table-bordered table-striped table-hover" id="loanGuarantorsTable">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th style="width: 70px; text-align: center;" class="no-export">{{ $lmText('Actions', 'សកម្មភាព') }}</th>
                        <th>{{ $lmText('Guarantor Name', 'ឈ្មោះអ្នកធានា') }}</th>
                        <th>{{ $lmText('Phone', 'លេខទូរស័ព្ទ') }}</th>
                        <th>{{ $lmText('National ID / Card', 'អត្តសញ្ញាណប័ណ្ណ') }}</th>
                        <th>{{ $lmText('Relationship', 'ត្រូវជា') }}</th>
                        <th>{{ $lmText('Address', 'អាសយដ្ឋាន') }}</th>
                        <th>{{ $lmText('Linked Customer', 'អតិថិជនស្នើសុំ') }}</th>
                        <th>{{ $lmText('Loan Number', 'លេខកិច្ចសន្យា') }}</th>
                        <th style="text-align: center;">{{ $lmText('Loan Status', 'ស្ថានភាព') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guarantors as $g)
                        @php
                            $guarName = trim((string) ($g->name ?? $g->guarantor_name ?? ''));
                            $guarPhone = trim((string) ($g->phone ?? $g->guarantor_phone ?? ''));
                            $guarId = trim((string) ($g->national_id ?? $g->id_number ?? $g->guarantor_national_id ?? ''));
                            $guarRel = trim((string) ($g->relationship ?? $g->relation ?? $g->guarantor_relationship ?? ''));
                            $guarAddr = trim((string) ($g->address ?? $g->guarantor_address ?? ''));
                            $loanId = $g->loan_id ?? $g->id ?? null;
                        @endphp
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                @if(!empty($loanId))
                                    <a href="{{ route('loan-management.loans.view', $loanId) }}" class="btn btn-xs btn-info" title="{{ $lmText('View Loan Details', 'មើលកម្ចី') }}">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $guarName !== '' ? $guarName : '-' }}</strong>
                            </td>
                            <td><strong>{{ $guarPhone !== '' ? $guarPhone : '-' }}</strong></td>
                            <td><code>{{ $guarId !== '' ? $guarId : '-' }}</code></td>
                            <td>
                                @if($guarRel !== '')
                                    <span class="label label-default" style="font-size: 11px;">{{ $guarRel }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td style="font-size: 11.5px; color: #475569;">{{ $guarAddr !== '' ? $guarAddr : '-' }}</td>
                            <td>
                                @if(!empty($g->customer_name_snapshot))
                                    <strong>{{ $g->customer_name_snapshot }}</strong>
                                    @if(!empty($g->customer_phone_snapshot))
                                        <div style="font-size: 11px; color: #64748b;">{{ $g->customer_phone_snapshot }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($g->loan_number) && !empty($loanId))
                                    <a href="{{ route('loan-management.loans.view', $loanId) }}" style="font-weight: 700; color: #0284c7; text-decoration: none;">
                                        {{ $g->loan_number }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if(!empty($g->loan_status))
                                    @php
                                        $lst = strtolower((string)$g->loan_status);
                                        $lstClass = $lst === 'active' ? 'label-success' : ($lst === 'paid_off' ? 'label-info' : ($lst === 'overdue' ? 'label-danger' : 'label-warning'));
                                    @endphp
                                    <span class="label {{ $lstClass }}" style="font-size: 10px; text-transform: uppercase;">{{ $g->loan_status }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($guarantors instanceof \Illuminate\Contracts\Pagination\Paginator || $guarantors instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div style="margin-top: 10px;">
                {{ $guarantors->links() }}
            </div>
        @endif
    @endcomponent
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
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanGuarantorsTable')) {
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

            $('#loanGuarantorsTable').DataTable({
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
                    emptyTable: '{{ $lmText("No loan guarantors found.", "មិនមានទិន្នន័យអ្នកធានាកម្ចីទេ។") }}',
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
                    { targets: [0, 4, 8], className: 'text-center' }
                ]
            });
        }
    });
</script>
@endsection
