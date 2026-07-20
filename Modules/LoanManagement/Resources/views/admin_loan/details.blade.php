@php
    $isKhmer = $isKhmer ?? session('user.language', config('app.locale')) === 'km';
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
    $groupLabels = [
        'all' => $text('All Loans', 'កម្ចីទាំងអស់'),
        'registered' => $text('Registered Installments', 'អតិថិជនចុះឈ្មោះរំលស់'),
        'generalPaid' => $text('General Installments Paid', 'អតិថិជនរំលស់បានបង់ទូរទៅ'),
        'paidOff' => $text('Settled / Fully Paid-Off', 'អតិថិជនរំលស់បានបង់ផ្ដាច់'),
        'active' => $text('Active / Ongoing Installments', 'អតិថិជនរំលស់កំពុងដំណើរការ'),
        'badDebt' => $text('Defaulted / Bad Debt', 'អតិថិជនរំលស់ខូច'),
    ];
    $groupTitle = $groupLabels[$group] ?? $groupLabels['all'];
    $money = fn ($value) => '$'.number_format((float) $value, 2);
    $canEditLoan = auth()->user() && auth()->user()->can('loan_management.edit');
@endphp
<!doctype html>
<html lang="{{ $isKhmer ? 'km' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $groupTitle }} - {{ $year }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&display=swap">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef3f8;
            color: #111827;
            font-family: Arial, sans-serif;
        }
        html[lang="km"] body,
        html[lang="km"] button,
        html[lang="km"] input,
        html[lang="km"] select {
            font-family: 'Kantumruy Pro', 'Noto Sans Khmer', 'Khmer OS Battambang', Arial, sans-serif;
        }
        .page {
            width: 100%;
            padding: 14px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid #d7e0eb;
            border-radius: 8px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbfe 100%);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }
        .title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
            color: #0f172a;
        }
        .meta {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
        }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 32px;
            padding: 0 12px;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }
        .btn-primary {
            border-color: #1d4ed8;
            background: #1d4ed8;
            color: #fff;
        }
        .btn-success {
            border-color: #047857;
            background: #047857;
            color: #fff;
        }
        .table-wrap {
            margin-top: 12px;
            border: 1px solid #d7e0eb;
            border-radius: 8px;
            background: #fff;
            overflow: auto;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
        }
        table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 10px 9px;
            background: #172033;
            color: #fff;
            border-right: 1px solid rgba(255, 255, 255, .12);
            font-size: 11px;
            font-weight: 800;
            text-align: left;
            white-space: nowrap;
        }
        td {
            padding: 8px 9px;
            border-right: 1px solid #e5ebf3;
            border-bottom: 1px solid #edf2f7;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td { background: #fbfdff; }
        tbody tr.loan-row:hover td { background: #edf7ff; }
        tbody tr.loan-row.is-open td {
            background: #eaf4ff;
            border-bottom-color: #c9def5;
        }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .center { text-align: center; }
        .loan-link {
            color: #1d4ed8;
            font-weight: 800;
            text-decoration: none;
        }
        .status {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-size: 11px;
            font-weight: 800;
            text-transform: capitalize;
        }
        .status.completed,
        .status.closed,
        .status.paid,
        .status.paid_off {
            background: #dcfce7;
            color: #166534;
        }
        .status.active,
        .status.approved {
            background: #dbeafe;
            color: #1e40af;
        }
        .status.defaulted,
        .status.rejected,
        .status.cancelled {
            background: #ffe4e6;
            color: #9f1239;
        }
        .empty {
            padding: 36px;
            text-align: center;
            color: #64748b;
            font-weight: 700;
        }
        .row-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .edit-toggle {
            border: 0;
            background: transparent;
            color: #047857;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
            padding: 0;
        }
        .full-edit-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            background: rgba(15, 23, 42, .62);
            padding: 14px;
        }
        .full-edit-modal.is-open {
            display: flex;
        }
        .full-edit-dialog {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            overflow: hidden;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        }
        .full-edit-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #dbe4ef;
            background: #f8fafc;
        }
        .full-edit-title {
            color: #0f172a;
            font-size: 14px;
            font-weight: 900;
        }
        .full-edit-close {
            height: 30px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #fff;
            color: #334155;
            padding: 0 12px;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
        }
        .full-edit-frame {
            width: 100%;
            height: 100%;
            border: 0;
            background: #f4f7fb;
        }
        @media (max-width: 767px) {
            .page { padding: 8px; }
            .header { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="header">
            <div>
                <h1 class="title">{{ $groupTitle }} - {{ $year }}</h1>
                <div class="meta">
                    {{ $text('Showing full loan data from Admin Loan table', 'បង្ហាញទិន្នន័យកម្ចីពេញពីតារាងរដ្ឋបាលកម្ចី') }}
                    · {{ number_format($loans->count()) }} {{ $text('records', 'ជួរ') }}
                </div>
            </div>
            <div class="actions">
                @if($group === 'active')
                    <a class="btn btn-success" href="{{ route('loan-management.export.download', ['type' => 'active_loans', 'date_from' => $year.'-01-01', 'date_to' => $year.'-12-31']) }}">{{ $text('Export Active/Ongoing', 'នាំចេញកំពុងដំណើរការ') }}</a>
                    <a class="btn btn-primary" href="{{ route('loan-management.import.index', ['type' => 'active_loans']) }}" target="_blank">{{ $text('Import Active/Ongoing', 'នាំចូលកំពុងដំណើរការ') }}</a>
                @endif
                <a class="btn" href="{{ route('loan-management.admin-loan', request()->only(['start_year', 'end_year', 'location_id', 'search'])) }}">{{ $text('Back to Admin Loan', 'ត្រឡប់ទៅរដ្ឋបាលកម្ចី') }}</a>
                <a class="btn" href="{{ route('loan-management.loans') }}" target="_blank">{{ $text('Open Loan List', 'បើកបញ្ជីកម្ចី') }}</a>
            </div>
        </section>

        <section class="table-wrap">
            @if($loans->isEmpty())
                <div class="empty">{{ $text('No loans found for this selection.', 'រកមិនឃើញកម្ចីសម្រាប់ជម្រើសនេះទេ។') }}</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="center">#</th>
                            <th>{{ $text('Loan #', 'លេខកម្ចី') }}</th>
                            <th>{{ $text('Date', 'កាលបរិច្ឆេទ') }}</th>
                            <th>{{ $text('Customer', 'អតិថិជន') }}</th>
                            <th>{{ $text('Phone', 'ទូរស័ព្ទ') }}</th>
                            <th>{{ $text('Location', 'សាខា') }}</th>
                            <th class="num">{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                            <th class="num">{{ $text('Paid', 'បានបង់') }}</th>
                            <th class="num">{{ $text('Balance', 'សមតុល្យ') }}</th>
                            <th>{{ $text('Status', 'ស្ថានភាព') }}</th>
                            <th>{{ $text('Action', 'សកម្មភាព') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loans as $loan)
                            @php $status = strtolower((string) ($loan->status ?? 'pending')); @endphp
                            <tr class="loan-row" data-loan-row="{{ $loan->id }}">
                                <td class="center">{{ $loop->iteration }}</td>
                                <td><a class="loan-link" href="{{ route('loan-management.loans.view', $loan->id) }}" target="_blank">{{ $loan->loan_number ?: ('Loan #'.$loan->id) }}</a></td>
                                <td data-cell="loan_date">{{ $loan->loan_date ? \Illuminate\Support\Carbon::parse($loan->loan_date)->format('Y-m-d') : '-' }}</td>
                                <td data-cell="customer_name_snapshot">{{ $loan->customer_name ?: '-' }}</td>
                                <td data-cell="customer_phone_snapshot">{{ $loan->customer_phone ?: '-' }}</td>
                                <td data-cell="location_name_snapshot">{{ $loan->location_name ?: '-' }}</td>
                                <td class="num" data-cell="principal_amount">{{ $money($loan->principal_amount) }}</td>
                                <td class="num" data-cell="paid_amount">{{ $money($loan->paid_amount) }}</td>
                                <td class="num" data-cell="balance_amount">{{ $money($loan->balance_amount) }}</td>
                                <td data-cell="status"><span class="status {{ e($status) }}">{{ $status ?: '-' }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        @if($canEditLoan)
                                            <button type="button" class="edit-toggle" data-edit-modal-url="{{ route('loan-management.loans.edit', ['loan' => $loan->id, '_lm_modal' => 1]) }}" data-edit-modal-title="{{ ($loan->loan_number ?: ('Loan #'.$loan->id)) }}">{{ $text('Edit', 'កែ') }}</button>
                                        @endif
                                        <a class="loan-link" href="{{ route('loan-management.loans.view', $loan->id) }}" target="_blank">{{ $text('View', 'មើល') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>

    <div class="full-edit-modal" id="fullEditModal" aria-hidden="true">
        <div class="full-edit-dialog">
            <div class="full-edit-head">
                <div class="full-edit-title" id="fullEditTitle">{{ $text('Edit Loan', 'កែកម្ចី') }}</div>
                <button type="button" class="full-edit-close" id="fullEditClose">{{ $text('Close', 'បិទ') }}</button>
            </div>
            <iframe class="full-edit-frame" id="fullEditFrame" title="{{ $text('Edit Loan', 'កែកម្ចី') }}"></iframe>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('fullEditModal');
            var frame = document.getElementById('fullEditFrame');
            var title = document.getElementById('fullEditTitle');

            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-edit-modal-url]');
                if (!button) return;
                event.preventDefault();
                title.textContent = '{{ $text('Edit Loan', 'កែកម្ចី') }} - ' + (button.getAttribute('data-edit-modal-title') || '');
                frame.src = button.getAttribute('data-edit-modal-url');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            });

            function closeFullEditModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                frame.src = 'about:blank';
                document.body.style.overflow = '';
                window.location.reload();
            }

            document.getElementById('fullEditClose').addEventListener('click', closeFullEditModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeFullEditModal();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeFullEditModal();
                }
            });
        })();
    </script>
</body>
</html>
