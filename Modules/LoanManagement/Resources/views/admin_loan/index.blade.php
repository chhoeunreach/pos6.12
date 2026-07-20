@php
    $isKhmer = $isKhmer ?? session('user.language', config('app.locale')) === 'km';
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
    $years = range((int) now()->format('Y'), 2000);
    $adminRows = collect($payload['adminRows'] ?? [])->map(function ($row) {
        return [
            'id' => (string) $row['year'],
            'year' => (int) $row['year'],
            'registered' => [
                'customers' => (int) ($row['registered']['customers'] ?? 0),
                'loanAmount' => (float) ($row['registered']['loan_amount'] ?? 0),
                'interest' => (float) ($row['registered']['interest'] ?? 0),
                'totalInterest' => (float) ($row['registered']['total_interest'] ?? 0),
            ],
            'generalPaid' => [
                'principalPaid' => (float) ($row['general_paid']['principal_paid'] ?? 0),
                'interestPaid' => (float) ($row['general_paid']['interest_paid'] ?? 0),
                'interestDeducted' => (float) ($row['general_paid']['interest_deducted'] ?? 0),
                'penaltiesReceived' => (float) ($row['general_paid']['penalties_received'] ?? 0),
            ],
            'paidOff' => [
                'settledCustomers' => (int) ($row['paid_off']['settled_customers'] ?? 0),
                'settledPrincipal' => (float) ($row['paid_off']['settled_principal'] ?? 0),
                'settledInterest' => (float) ($row['paid_off']['settled_interest'] ?? 0),
                'settledPenalties' => (float) ($row['paid_off']['settled_penalties'] ?? 0),
                'prepaymentDiscount' => (float) ($row['paid_off']['prepayment_discount'] ?? 0),
            ],
            'activeOngoing' => [
                'activeCustomers' => (int) ($row['active']['active_customers'] ?? 0),
                'activePrincipal' => (float) ($row['active']['active_principal'] ?? 0),
                'activeMonthlyInterest' => (float) ($row['active']['active_monthly_interest'] ?? 0),
                'activeTotalInterest' => (float) ($row['active']['active_total_interest'] ?? 0),
            ],
            'badDebt' => [
                'badCustomers' => (int) ($row['bad_debt']['bad_customers'] ?? 0),
                'badPrincipal' => (float) ($row['bad_debt']['bad_principal'] ?? 0),
                'badInterest' => (float) ($row['bad_debt']['bad_interest'] ?? 0),
                'badTotal' => (float) ($row['bad_debt']['bad_total'] ?? 0),
            ],
        ];
    })->values();
@endphp
<!doctype html>
<html lang="{{ $isKhmer ? 'km' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $text('Admin Loan', 'រដ្ឋបាលកម្ចី') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('modules/loanmanagement/admin-loan-app/assets/index-tfrm5V5v.css') }}">
    <style>
        html,
        body {
            margin: 0;
            min-height: 100%;
            background: #f8fafc;
        }
        html[lang="km"],
        html[lang="km"] body,
        html[lang="km"] #admin-loan-react-root,
        html[lang="km"] #admin-loan-react-root *,
        html[lang="km"] .admin-loan-filter,
        html[lang="km"] .admin-loan-filter * {
            font-family: 'Kantumruy Pro', 'Noto Sans Khmer', 'Khmer OS Battambang', Arial, sans-serif !important;
        }
        .admin-loan-filter {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .05);
            backdrop-filter: blur(10px);
        }
        .admin-loan-filter-grid {
            display: grid;
            grid-template-columns: 130px 130px 220px minmax(220px, 1fr) auto auto auto;
            gap: 8px;
            align-items: end;
            max-width: 1280px;
            margin: 0 auto;
        }
        .admin-loan-filter label {
            display: block;
            margin: 0 0 3px;
            font: 700 11px/1.2 Arial, sans-serif;
            color: #475569;
        }
        .admin-loan-filter select,
        .admin-loan-filter input {
            width: 100%;
            height: 30px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 4px 8px;
            font: 500 12px/1.2 Arial, sans-serif;
            color: #0f172a;
            background: #fff;
            box-sizing: border-box;
        }
        .admin-loan-filter button,
        .admin-loan-filter a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            border-radius: 7px;
            padding: 0 11px;
            font: 700 12px/1 Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .admin-loan-filter button {
            color: #fff;
            background: #2563eb;
            border-color: #2563eb;
        }
        .admin-loan-filter a {
            color: #334155;
            background: #fff;
            border-color: #cbd5e1;
        }
        .admin-language-switch {
            display: inline-flex;
            height: 30px;
            padding: 2px;
            border-radius: 8px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            gap: 2px;
        }
        .admin-language-switch button {
            height: 24px;
            padding: 0 9px;
            border-radius: 6px;
            border: 0;
            color: #475569;
            background: transparent;
            font-size: 11px;
        }
        .admin-language-switch button.active {
            background: #059669;
            color: #fff;
            box-shadow: 0 2px 6px rgba(5, 150, 105, .18);
        }
        #admin-loan-react-root #header-settings > div:last-child {
            display: none !important;
        }
        #admin-loan-react-root #add-new-record-btn,
        #admin-loan-react-root #add-new-month-record-btn,
        #admin-loan-react-root [id^="edit-btn-"],
        #admin-loan-react-root [id^="delete-btn-"],
        #admin-loan-react-root [id^="edit-month-btn-"],
        #admin-loan-react-root [id^="delete-month-btn-"] {
            display: none !important;
        }
        @media (max-width: 1200px) {
            .admin-loan-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 767px) {
            .admin-loan-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-loan-filter">
        <form method="GET" action="{{ route('loan-management.admin-loan') }}" id="adminLoanFilter">
            <div class="admin-loan-filter-grid">
                <div>
                    <label>{{ $text('Start Year', 'ឆ្នាំចាប់ផ្តើម') }}</label>
                    <select name="start_year">
                        @foreach($years as $year)
                            <option value="{{ $year }}" {{ (int) $filters['start_year'] === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ $text('End Year', 'ឆ្នាំបញ្ចប់') }}</label>
                    <select name="end_year">
                        @foreach($years as $year)
                            <option value="{{ $year }}" {{ (int) $filters['end_year'] === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ $text('Location', 'សាខា') }}</label>
                    <select name="location_id">
                        <option value="">{{ $text('All Locations', 'សាខាទាំងអស់') }}</option>
                        @foreach($locations as $id => $name)
                            <option value="{{ $id }}" {{ (string) $filters['location_id'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ $text('Search', 'ស្វែងរក') }}</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="{{ $text('Loan #, invoice, customer, phone', 'លេខកម្ចី វិក្កយបត្រ អតិថិជន ទូរស័ព្ទ') }}">
                </div>
                <button type="submit">{{ $text('Filter', 'ចម្រោះ') }}</button>
                <a href="{{ route('loan-management.admin-loan') }}">{{ $text('Reset', 'កំណត់ឡើងវិញ') }}</a>
                <div class="admin-language-switch" aria-label="Language">
                    <button type="button" class="{{ $isKhmer ? '' : 'active' }}" data-admin-language="en">EN</button>
                    <button type="button" class="{{ $isKhmer ? 'active' : '' }}" data-admin-language="km">ខ្មែរ</button>
                </div>
            </div>
        </form>
        <form method="POST" action="{{ route('loan-management.language.switch') }}" id="adminLanguageForm" style="display:none;">
            @csrf
            <input type="hidden" name="language" id="adminLanguageInput" value="{{ $isKhmer ? 'km' : 'en' }}">
        </form>
    </div>
    <div id="admin-loan-react-root">
        <div id="root"></div>
    </div>
    <script>
        (function () {
            var yearlyRecords = @json($adminRows);
            localStorage.setItem('khnar_yeung_ledger', JSON.stringify(yearlyRecords));
            localStorage.setItem('khnar_yeung_monthly_ledger', JSON.stringify([]));
        })();
    </script>
    <script type="module" src="{{ asset('modules/loanmanagement/admin-loan-app/assets/index-BpfyckyY.js') }}"></script>
    <script>
        (function () {
            var form = document.getElementById('adminLoanFilter');
            if (form) {
                form.addEventListener('change', function (event) {
                    if (event.target && event.target.tagName === 'SELECT' && event.target.name !== 'language') {
                        form.submit();
                    }
                });
            }

            var targetLanguage = '{{ $isKhmer ? 'km' : 'en' }}';
            var languageForm = document.getElementById('adminLanguageForm');
            var languageInput = document.getElementById('adminLanguageInput');
            document.querySelectorAll('[data-admin-language]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var nextLanguage = button.getAttribute('data-admin-language');
                    if (!languageForm || !languageInput || nextLanguage === targetLanguage) {
                        return;
                    }
                    languageInput.value = nextLanguage;
                    languageForm.submit();
                });
            });

            function syncBodyLanguage(language) {
                document.documentElement.setAttribute('lang', language);
            }

            var attempts = 0;
            var syncReactLanguage = setInterval(function () {
                attempts += 1;
                var button = document.getElementById(targetLanguage === 'km' ? 'language-kh-btn' : 'language-en-btn');
                if (button) {
                    button.click();
                    syncBodyLanguage(targetLanguage);
                    clearInterval(syncReactLanguage);
                }
                if (attempts > 20) {
                    clearInterval(syncReactLanguage);
                }
            }, 150);
        })();
</script>
</body>
</html>
