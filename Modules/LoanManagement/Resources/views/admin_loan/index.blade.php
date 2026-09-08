@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    $isKhmer = $isKhmer ?? session('user.language', config('app.locale')) === 'km';
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
    $years = range((int) now()->format('Y'), 2000);
    $moduleCssPath = module_path('LoanManagement', 'Resources/assets/css/loan-management.css');
    $moduleJsPath = module_path('LoanManagement', 'Resources/assets/js/loan-management.js');
    $adminLoanCssAsset = 'modules/loanmanagement/admin-loan-app/assets/index-tfrm5V5v.css';
    $adminLoanJsAsset = 'modules/loanmanagement/admin-loan-app/assets/index-BpfyckyY.js';
    $adminLoanCssPath = public_path($adminLoanCssAsset);
    $adminLoanJsPath = public_path($adminLoanJsAsset);
    $adminLoanCssExists = file_exists($adminLoanCssPath);
    $adminLoanJsExists = file_exists($adminLoanJsPath);
    $loanBadgeCounts = LoanMenuHelper::badgeCounts();
    $loanLanguage = session('user.language', config('app.locale'));
    $adminRows = collect($payload['adminRows'] ?? [])->sortBy('year')->map(function ($row) {
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
    $adminMonthlyRows = collect($payload['adminMonthlyRows'] ?? [])->sortBy(function ($row) {
        return sprintf('%04d-%02d', (int) $row['year'], (int) $row['month']);
    })->map(function ($row) {
        return [
            'id' => (string) ($row['id'] ?? sprintf('%04d-%02d', (int) $row['year'], (int) $row['month'])),
            'year' => (int) $row['year'],
            'month' => (int) $row['month'],
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
    $adminLoanFilterPayload = [
        'start_year' => $filters['start_year'],
        'end_year' => $filters['end_year'],
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'location_id' => $filters['location_id'],
        'search' => $filters['search'],
    ];
    $money = fn ($value) => '$'.number_format((float) $value, 2);
    $monthNames = [
        1 => $text('January', 'មករា'),
        2 => $text('February', 'កុម្ភៈ'),
        3 => $text('March', 'មីនា'),
        4 => $text('April', 'មេសា'),
        5 => $text('May', 'ឧសភា'),
        6 => $text('June', 'មិថុនា'),
        7 => $text('July', 'កក្កដា'),
        8 => $text('August', 'សីហា'),
        9 => $text('September', 'កញ្ញា'),
        10 => $text('October', 'តុលា'),
        11 => $text('November', 'វិច្ឆិកា'),
        12 => $text('December', 'ធ្នូ'),
    ];
@endphp
<!doctype html>
<html lang="{{ $isKhmer ? 'km' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $text('Admin Installment', 'រដ្ឋបាលកម្ចី') }}</title>
    @include('layouts.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&display=swap">
    @if ($adminLoanCssExists)
        <link rel="stylesheet" href="{{ asset($adminLoanCssAsset) }}?v={{ filemtime($adminLoanCssPath) }}">
    @endif
    @if (file_exists($moduleCssPath))
        <style>{!! file_get_contents($moduleCssPath) !!}</style>
    @endif
    <style>
        html,
        body {
            margin: 0;
            min-height: 100%;
            background: #f8fafc;
        }
        body.admin-loan-page {
            overflow: hidden;
        }
        .admin-loan-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
            background: #f8fafc;
        }
        .admin-loan-main {
            flex: 1 1 auto;
            min-width: 0;
            height: 100vh;
            overflow: auto;
            background: #f8fafc;
        }
        body.lm-sidebar-collapsed .admin-loan-main {
            width: calc(100% - var(--lm-sidebar-rail-width));
        }
        .admin-loan-sidebar-toggle {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 950;
            display: none;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 10px 25px rgba(37, 99, 235, .25);
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
        #admin-loan-react-root #global-header > div,
        #admin-loan-react-root #main-application-shell > main,
        #admin-loan-react-root #global-footer > div {
            width: 100% !important;
            max-width: none !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
        }
        #admin-loan-react-root #main-application-shell > main {
            padding-top: 10px !important;
            padding-bottom: 12px !important;
            gap: 12px !important;
        }
        #admin-loan-react-root #quick-status-ribbon,
        #admin-loan-react-root #regulatory-warning-card {
            border-radius: 10px !important;
            padding: 12px !important;
        }
        #admin-loan-react-root #tabs-navigation-container button {
            position: relative !important;
            min-height: 44px !important;
            padding: 9px 14px !important;
            border: 1px solid transparent !important;
            border-bottom-width: 3px !important;
            border-radius: 10px 10px 0 0 !important;
            background: transparent !important;
            color: #475569 !important;
            font-weight: 800 !important;
            white-space: nowrap !important;
        }
        #admin-loan-react-root #tabs-navigation-container button:hover {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
            border-bottom-color: #94a3b8 !important;
            color: #0f172a !important;
        }
        #admin-loan-react-root #tabs-navigation-container button[class*="border-emerald-600"] {
            background: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            border-bottom-color: #059669 !important;
            color: #047857 !important;
            box-shadow: inset 0 -3px 0 #059669, 0 8px 18px rgba(5, 150, 105, .12) !important;
        }
        #admin-loan-react-root #tabs-navigation-container button[class*="border-emerald-600"] svg {
            color: #059669 !important;
            stroke-width: 2.5 !important;
        }
        #admin-loan-react-root #tabs-navigation-container button[class*="border-emerald-600"]::after {
            content: "";
            position: absolute;
            left: 12px;
            right: 12px;
            bottom: -5px;
            height: 3px;
            border-radius: 999px;
            background: #059669;
        }
        #admin-loan-react-root .admin-loan-search-wrap-fixed {
            position: relative !important;
        }
        #admin-loan-react-root .admin-loan-search-wrap-fixed > span {
            position: absolute !important;
            inset: 0 auto 0 0 !important;
            z-index: 2 !important;
            display: inline-flex !important;
            width: 36px !important;
            align-items: center !important;
            justify-content: center !important;
            padding-left: 0 !important;
            color: #94a3b8 !important;
            pointer-events: none !important;
        }
        #admin-loan-react-root .admin-loan-search-wrap-fixed > span svg {
            width: 16px !important;
            height: 16px !important;
        }
        #admin-loan-react-root .admin-loan-search-input-fixed {
            height: 38px !important;
            padding-left: 38px !important;
            padding-right: 14px !important;
            line-height: 1.4 !important;
            text-indent: 0 !important;
        }
        #admin-loan-react-root [class~="min-w-[2000px]"] {
            min-width: max-content !important;
        }
        #admin-loan-react-root table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            table-layout: auto !important;
            width: max-content !important;
            min-width: 100% !important;
            background: #fff !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(1),
        #admin-loan-react-root tbody td:nth-child(1),
        #admin-loan-react-root tfoot td:nth-child(1) {
            width: 52px !important;
            min-width: 52px !important;
            max-width: 52px !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(2),
        #admin-loan-react-root tbody td:nth-child(2),
        #admin-loan-react-root tfoot td:nth-child(2) {
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(3),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(16),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(20),
        #admin-loan-react-root tbody td:nth-child(3),
        #admin-loan-react-root tbody td:nth-child(16),
        #admin-loan-react-root tbody td:nth-child(20),
        #admin-loan-react-root tfoot td:nth-child(3),
        #admin-loan-react-root tfoot td:nth-child(16),
        #admin-loan-react-root tfoot td:nth-child(20) {
            width: auto !important;
            min-width: max-content !important;
            max-width: none !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(n+4),
        #admin-loan-react-root tbody td:nth-child(n+4),
        #admin-loan-react-root tfoot td:nth-child(n+4) {
            width: auto !important;
            min-width: max-content !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(6),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(15),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(19),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(23),
        #admin-loan-react-root tbody td:nth-child(6),
        #admin-loan-react-root tbody td:nth-child(15),
        #admin-loan-react-root tbody td:nth-child(19),
        #admin-loan-react-root tbody td:nth-child(23),
        #admin-loan-react-root tfoot td:nth-child(6),
        #admin-loan-react-root tfoot td:nth-child(15),
        #admin-loan-react-root tfoot td:nth-child(19),
        #admin-loan-react-root tfoot td:nth-child(23) {
            min-width: 0 !important;
        }
        #admin-loan-react-root thead tr:first-child th {
            padding: 8px 6px !important;
            border-right: 1px solid rgba(255, 255, 255, .18) !important;
            color: #fff !important;
            font-size: clamp(9px, .58vw, 11px) !important;
            font-weight: 800 !important;
            line-height: 1.25 !important;
            letter-spacing: 0 !important;
            text-align: center !important;
            vertical-align: middle !important;
            white-space: normal !important;
            overflow-wrap: anywhere !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(1),
        #admin-loan-react-root thead tr:first-child th:nth-child(2) {
            background: #1f2937 !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(3) {
            background: #0f766e !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(4) {
            background: #334155 !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(5) {
            background: #1d4ed8 !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(6) {
            background: #92400e !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(7) {
            background: #9f1239 !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th {
            padding: 6px 4px !important;
            background: #f8fafc !important;
            color: #1f2937 !important;
            border-right: 1px solid #dbe4ef !important;
            border-bottom: 1px solid #cfd9e6 !important;
            font-size: clamp(7px, .46vw, 9px) !important;
            font-weight: 800 !important;
            line-height: 1.2 !important;
            letter-spacing: 0 !important;
            text-align: center !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
            overflow-wrap: normal !important;
            word-break: normal !important;
            max-width: none !important;
        }
        #admin-loan-react-root tbody td,
        #admin-loan-react-root tfoot td {
            padding: 7px 8px !important;
            border-right: 1px solid #e3eaf3 !important;
            border-bottom: 1px solid #e8eef5 !important;
            font-size: clamp(10px, .58vw, 12px) !important;
            font-weight: 600 !important;
            line-height: 1.35 !important;
            color: #111827 !important;
            text-align: right !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
            font-variant-numeric: tabular-nums !important;
        }
        #admin-loan-react-root tbody td:nth-child(1),
        #admin-loan-react-root tbody td:nth-child(2),
        #admin-loan-react-root tfoot td:nth-child(1),
        #admin-loan-react-root tfoot td:nth-child(2) {
            text-align: center !important;
            font-size: clamp(11px, .64vw, 12px) !important;
        }
        #admin-loan-react-root tbody td:nth-child(n+4),
        #admin-loan-react-root tfoot td:nth-child(n+4) {
            font-size: clamp(9px, .54vw, 11px) !important;
        }
        #admin-loan-react-root tbody td:nth-child(3),
        #admin-loan-react-root tbody td:nth-child(16),
        #admin-loan-react-root tbody td:nth-child(20),
        #admin-loan-react-root tfoot td:nth-child(3),
        #admin-loan-react-root tfoot td:nth-child(16),
        #admin-loan-react-root tfoot td:nth-child(20) {
            font-size: clamp(10px, .58vw, 12px) !important;
        }
        #admin-loan-react-root tbody tr:nth-child(odd) td {
            background: #ffffff !important;
        }
        #admin-loan-react-root tbody tr:nth-child(even) td {
            background: #fbfdff !important;
        }
        #admin-loan-react-root tbody tr:hover td {
            background: #edf7ff !important;
        }
        #admin-loan-react-root tbody tr {
            cursor: pointer !important;
        }
        #admin-loan-react-root tbody tr:hover td:first-child {
            box-shadow: inset 3px 0 0 #2563eb !important;
        }
        #admin-loan-react-root table th:last-child,
        #admin-loan-react-root table td:last-child {
            display: none !important;
        }
        #admin-loan-react-root tfoot td {
            background: #101827 !important;
            color: #fff !important;
            font-weight: 900 !important;
            border-color: rgba(255, 255, 255, .12) !important;
        }
        #admin-loan-react-root thead tr:first-child th:nth-child(3),
        #admin-loan-react-root thead tr:first-child th:nth-child(4),
        #admin-loan-react-root thead tr:first-child th:nth-child(5),
        #admin-loan-react-root thead tr:first-child th:nth-child(6),
        #admin-loan-react-root thead tr:first-child th:nth-child(7) {
            box-shadow: inset 0 -3px 0 rgba(255, 255, 255, .16) !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(n+3):nth-child(-n+6),
        #admin-loan-react-root tbody td:nth-child(n+3):nth-child(-n+6),
        #admin-loan-react-root tfoot td:nth-child(n+3):nth-child(-n+6) {
            background-color: #f7fffc !important;
            border-right-color: #d7eee7 !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(n+7):nth-child(-n+10),
        #admin-loan-react-root tbody td:nth-child(n+7):nth-child(-n+10),
        #admin-loan-react-root tfoot td:nth-child(n+7):nth-child(-n+10) {
            background-color: #fbfcfe !important;
            border-right-color: #e2e8f0 !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(n+11):nth-child(-n+15),
        #admin-loan-react-root tbody td:nth-child(n+11):nth-child(-n+15),
        #admin-loan-react-root tfoot td:nth-child(n+11):nth-child(-n+15) {
            background-color: #f6faff !important;
            border-right-color: #d8e7fb !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(n+16):nth-child(-n+19),
        #admin-loan-react-root tbody td:nth-child(n+16):nth-child(-n+19),
        #admin-loan-react-root tfoot td:nth-child(n+16):nth-child(-n+19) {
            background-color: #fffdf5 !important;
            border-right-color: #f4e5bd !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(n+20):nth-child(-n+23),
        #admin-loan-react-root tbody td:nth-child(n+20):nth-child(-n+23),
        #admin-loan-react-root tfoot td:nth-child(n+20):nth-child(-n+23) {
            background-color: #fff8f9 !important;
            border-right-color: #f4d5da !important;
        }
        #admin-loan-react-root tbody tr:nth-child(even) td:nth-child(n+3):nth-child(-n+6) {
            background-color: #eefbf7 !important;
        }
        #admin-loan-react-root tbody tr:nth-child(even) td:nth-child(n+7):nth-child(-n+10) {
            background-color: #f4f7fb !important;
        }
        #admin-loan-react-root tbody tr:nth-child(even) td:nth-child(n+11):nth-child(-n+15) {
            background-color: #eef6ff !important;
        }
        #admin-loan-react-root tbody tr:nth-child(even) td:nth-child(n+16):nth-child(-n+19) {
            background-color: #fff8e6 !important;
        }
        #admin-loan-react-root tbody tr:nth-child(even) td:nth-child(n+20):nth-child(-n+23) {
            background-color: #fff0f2 !important;
        }
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(3),
        #admin-loan-react-root tbody td:nth-child(3),
        #admin-loan-react-root tfoot td:nth-child(3),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(7),
        #admin-loan-react-root tbody td:nth-child(7),
        #admin-loan-react-root tfoot td:nth-child(7),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(11),
        #admin-loan-react-root tbody td:nth-child(11),
        #admin-loan-react-root tfoot td:nth-child(11),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(16),
        #admin-loan-react-root tbody td:nth-child(16),
        #admin-loan-react-root tfoot td:nth-child(16),
        #admin-loan-react-root thead tr:nth-child(2) th:nth-child(20),
        #admin-loan-react-root tbody td:nth-child(20),
        #admin-loan-react-root tfoot td:nth-child(20) {
            border-left: 2px solid rgba(15, 23, 42, .18) !important;
        }
        #admin-loan-react-root tfoot td:nth-child(n+3) {
            background-color: #101827 !important;
        }
        #admin-loan-react-root #add-new-record-btn,
        #admin-loan-react-root #add-new-month-record-btn,
        #admin-loan-react-root [id^="edit-btn-"],
        #admin-loan-react-root [id^="delete-btn-"],
        #admin-loan-react-root [id^="edit-month-btn-"],
        #admin-loan-react-root [id^="delete-month-btn-"] {
            display: none !important;
        }
        .admin-loan-detail-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .62);
            backdrop-filter: blur(6px);
        }
        .admin-loan-detail-modal.is-open {
            display: flex;
        }
        .admin-loan-detail-dialog {
            width: min(1680px, 98vw);
            height: min(920px, 94vh);
            overflow: hidden;
            border: 1px solid rgba(203, 213, 225, .92);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
        }
        .admin-loan-detail-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            height: 48px;
            padding: 0 14px 0 18px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .admin-loan-detail-title {
            min-width: 0;
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .admin-loan-detail-close {
            height: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            background: #fff;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }
        .admin-loan-detail-frame {
            display: block;
            width: 100%;
            height: calc(100% - 48px);
            border: 0;
            background: #fff;
        }
        .admin-loan-fallback {
            padding: 14px 16px 18px;
        }
        .admin-loan-fallback-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .admin-loan-fallback-title {
            margin: 0;
            color: #0f172a;
            font-size: 19px;
            font-weight: 900;
            line-height: 1.25;
        }
        .admin-loan-fallback-subtitle {
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }
        .admin-loan-fallback-actions {
            display: inline-flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .admin-loan-fallback-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 0 12px;
            background: #fff;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }
        .admin-loan-fallback-action.primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
        }
        .admin-loan-fallback-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .admin-loan-fallback-metric {
            border: 1px solid #dbe4ef;
            border-radius: 8px;
            padding: 12px;
            background: #fff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .05);
        }
        .admin-loan-fallback-metric span {
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .admin-loan-fallback-metric strong {
            display: block;
            margin-top: 5px;
            color: #0f172a;
            font-size: 19px;
            font-weight: 900;
            line-height: 1.15;
        }
        .admin-loan-fallback-table {
            max-height: calc(100vh - 252px);
            overflow: auto;
            border: 1px solid #d7e0eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
        }
        .admin-loan-fallback-table .empty {
            padding: 36px;
            text-align: center;
            color: #64748b;
            font-weight: 800;
        }
        .admin-loan-fallback-tabs {
            display: inline-flex;
            gap: 4px;
            margin-bottom: 10px;
            border: 1px solid #d7e0eb;
            border-radius: 8px;
            padding: 3px;
            background: #eef3f8;
        }
        .admin-loan-fallback-tab {
            height: 30px;
            border: 0;
            border-radius: 6px;
            padding: 0 12px;
            background: transparent;
            color: #475569;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
        }
        .admin-loan-fallback-tab.is-active {
            background: #fff;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
        }
        .admin-loan-fallback-pane[hidden] {
            display: none !important;
        }
        #admin-loan-react-root .admin-loan-fallback-table table th:last-child,
        #admin-loan-react-root .admin-loan-fallback-table table td:last-child {
            display: table-cell !important;
        }
        @media (max-width: 1200px) {
            .admin-loan-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            #admin-loan-react-root #global-header > div,
            #admin-loan-react-root #main-application-shell > main,
            #admin-loan-react-root #global-footer > div {
                width: 100% !important;
                max-width: 100% !important;
            }
            #admin-loan-react-root [class~="min-w-[2000px]"] {
                min-width: max-content !important;
            }
            .admin-loan-fallback-head {
                flex-direction: column;
            }
            .admin-loan-fallback-actions {
                justify-content: flex-start;
            }
            .admin-loan-fallback-metrics {
                grid-template-columns: 1fr;
            }
            .admin-loan-fallback {
                padding: 10px;
            }
        }
        @media (max-width: 767px) {
            .admin-loan-sidebar-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .admin-loan-filter {
                padding-left: 58px;
            }
            .admin-loan-filter-grid {
                grid-template-columns: 1fr;
            }
            #admin-loan-react-root #global-header > div {
                padding-top: 10px !important;
                padding-bottom: 10px !important;
                gap: 10px !important;
            }
            #admin-loan-react-root #main-application-shell > main {
                padding-top: 10px !important;
                padding-bottom: 12px !important;
            }
            #admin-loan-react-root [class~="min-w-[2000px]"] {
                min-width: max-content !important;
            }
        }
    </style>
</head>
<body class="admin-loan-page loan-management-page lm-language-{{ $loanLanguage }} hold-transition skin-blue-light sidebar-mini tw-font-sans tw-antialiased tw-text-gray-900 tw-bg-gray-100">
    <div class="admin-loan-shell lm-app" id="loanManagementApp">
        @include('loanmanagement::layouts.sidebar', ['loanBadgeCounts' => $loanBadgeCounts])
        <main class="admin-loan-main" id="loanManagementMain">
            <button type="button" class="admin-loan-sidebar-toggle" id="loanSidebarToggle" aria-label="Toggle sidebar">
                <i class="fa fa-bars"></i>
            </button>
            <div class="admin-loan-filter">
                <form method="GET" action="{{ route('loan-management.admin-loan') }}" id="adminLoanFilter">
                    <div class="admin-loan-filter-grid">
                        <div>
                            <label>{{ $text('Date From', 'ពីថ្ងៃ') }}</label>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? ($filters['start_year'].'-01-01') }}">
                        </div>
                        <div>
                            <label>{{ $text('Date To', 'ដល់ថ្ងៃ') }}</label>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? ($filters['end_year'].'-12-31') }}">
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
                            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="{{ $text('Installment #, invoice, customer, phone', 'លេខកម្ចី វិក្កយបត្រ អតិថិជន ទូរស័ព្ទ') }}">
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
                @if ($adminLoanJsExists)
                    <div id="root"></div>
                @else
                    <section class="admin-loan-fallback">
                        <div class="admin-loan-fallback-head">
                            <div>
                                <h1 class="admin-loan-fallback-title">{{ $text('Installment Applications', 'ពាក្យស្នើសុំកម្ចី') }}</h1>
                                <div class="admin-loan-fallback-subtitle">
                                    {{ $text('Yearly installment summary by loan status and collection performance.', 'សង្ខេបកម្ចីរំលស់ប្រចាំឆ្នាំតាមស្ថានភាពកម្ចី និងការប្រមូលប្រាក់។') }}
                                </div>
                            </div>
                            <div class="admin-loan-fallback-actions">
                                <a class="admin-loan-fallback-action primary" href="{{ route('loan-management.loans.create') }}">
                                    <i class="fa fa-plus-circle"></i>&nbsp;{{ $text('New Installment', 'កម្ចីថ្មី') }}
                                </a>
                                <a class="admin-loan-fallback-action" href="{{ route('loan-management.admin-loan.export', $adminLoanFilterPayload) }}">
                                    <i class="fa fa-file-excel-o"></i>&nbsp;{{ $text('Export XLSX', 'នាំចេញ XLSX') }}
                                </a>
                            </div>
                        </div>

                        <div class="admin-loan-fallback-metrics">
                            <div class="admin-loan-fallback-metric">
                                <span>{{ $text('Registered Customers', 'អតិថិជនបានចុះឈ្មោះ') }}</span>
                                <strong>{{ number_format((int) ($payload['adminTotals']['registered']['customers'] ?? 0)) }}</strong>
                            </div>
                            <div class="admin-loan-fallback-metric">
                                <span>{{ $text('Registered Principal', 'ប្រាក់ដើមបានចុះឈ្មោះ') }}</span>
                                <strong>{{ $money($payload['adminTotals']['registered']['loan_amount'] ?? 0) }}</strong>
                            </div>
                            <div class="admin-loan-fallback-metric">
                                <span>{{ $text('Active Customers', 'អតិថិជនកំពុងដំណើរការ') }}</span>
                                <strong>{{ number_format((int) ($payload['adminTotals']['active']['active_customers'] ?? 0)) }}</strong>
                            </div>
                            <div class="admin-loan-fallback-metric">
                                <span>{{ $text('Bad Debt Ratio', 'អនុបាតកម្ចីខូច') }}</span>
                                <strong>{{ number_format((float) ($payload['adminTotals']['bad_debt_ratio'] ?? 0), 2) }}%</strong>
                            </div>
                        </div>

                        <div class="admin-loan-fallback-tabs" role="tablist" aria-label="{{ $text('Installment application reports', 'របាយការណ៍ពាក្យស្នើសុំកម្ចី') }}">
                            <button type="button" class="admin-loan-fallback-tab is-active" data-admin-loan-fallback-tab="yearly">{{ $text('Yearly', 'ប្រចាំឆ្នាំ') }}</button>
                            <button type="button" class="admin-loan-fallback-tab" data-admin-loan-fallback-tab="monthly">{{ $text('Monthly', 'ប្រចាំខែ') }}</button>
                        </div>

                        <div class="admin-loan-fallback-pane" data-admin-loan-fallback-pane="yearly">
                            <div class="admin-loan-fallback-table" id="yearly-report-table-card">
                                @if ($adminRows->isEmpty())
                                    <div class="empty">{{ $text('No loan applications found for these filters.', 'រកមិនឃើញពាក្យស្នើសុំកម្ចីសម្រាប់លក្ខខណ្ឌនេះទេ។') }}</div>
                                @else
                                    <table>
                                        <thead>
                                            <tr>
                                                <th rowspan="2">{{ $text('#', 'ល.រ') }}</th>
                                                <th rowspan="2">{{ $text('Year', 'ឆ្នាំ') }}</th>
                                                <th colspan="4">{{ $text('Registered Installments', 'អតិថិជនចុះឈ្មោះរំលស់') }}</th>
                                                <th colspan="4">{{ $text('General Installments Paid', 'អតិថិជនរំលស់បានបង់ទូទៅ') }}</th>
                                                <th colspan="5">{{ $text('Settled / Fully Paid-Off', 'អតិថិជនរំលស់បានបង់ផ្ដាច់') }}</th>
                                                <th colspan="4">{{ $text('Active / Ongoing Installments', 'អតិថិជនរំលស់កំពុងដំណើរការ') }}</th>
                                                <th colspan="4">{{ $text('Defaulted / Bad Debt', 'អតិថិជនរំលស់ខូច') }}</th>
                                            </tr>
                                            <tr>
                                                <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                                <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                                <th>{{ $text('Interest', 'ការប្រាក់') }}</th>
                                                <th>{{ $text('Total', 'សរុប') }}</th>
                                                <th>{{ $text('Principal Paid', 'ប្រាក់ដើមបានបង់') }}</th>
                                                <th>{{ $text('Interest Paid', 'ការប្រាក់បានបង់') }}</th>
                                                <th>{{ $text('Discount', 'បញ្ចុះតម្លៃ') }}</th>
                                                <th>{{ $text('Penalty', 'ពិន័យ') }}</th>
                                                <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                                <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                                <th>{{ $text('Interest', 'ការប្រាក់') }}</th>
                                                <th>{{ $text('Penalty', 'ពិន័យ') }}</th>
                                                <th>{{ $text('Discount', 'បញ្ចុះតម្លៃ') }}</th>
                                                <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                                <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                                <th>{{ $text('Monthly Interest', 'ការប្រាក់ប្រចាំខែ') }}</th>
                                                <th>{{ $text('Total Balance', 'សមតុល្យសរុប') }}</th>
                                                <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                                <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                                <th>{{ $text('Interest', 'ការប្រាក់') }}</th>
                                                <th>{{ $text('Total', 'សរុប') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($adminRows as $row)
                                                <tr title="{{ $text('Click any amount to view detail', 'ចុចលើចំនួនណាមួយដើម្បីមើលលម្អិត') }}">
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $row['year'] }}</td>
                                                    <td>{{ number_format($row['registered']['customers']) }}</td>
                                                    <td>{{ $money($row['registered']['loanAmount']) }}</td>
                                                    <td>{{ $money($row['registered']['interest']) }}</td>
                                                    <td>{{ $money($row['registered']['totalInterest']) }}</td>
                                                    <td>{{ $money($row['generalPaid']['principalPaid']) }}</td>
                                                    <td>{{ $money($row['generalPaid']['interestPaid']) }}</td>
                                                    <td>{{ $money($row['generalPaid']['interestDeducted']) }}</td>
                                                    <td>{{ $money($row['generalPaid']['penaltiesReceived']) }}</td>
                                                    <td>{{ number_format($row['paidOff']['settledCustomers']) }}</td>
                                                    <td>{{ $money($row['paidOff']['settledPrincipal']) }}</td>
                                                    <td>{{ $money($row['paidOff']['settledInterest']) }}</td>
                                                    <td>{{ $money($row['paidOff']['settledPenalties']) }}</td>
                                                    <td>{{ $money($row['paidOff']['prepaymentDiscount']) }}</td>
                                                    <td>{{ number_format($row['activeOngoing']['activeCustomers']) }}</td>
                                                    <td>{{ $money($row['activeOngoing']['activePrincipal']) }}</td>
                                                    <td>{{ $money($row['activeOngoing']['activeMonthlyInterest']) }}</td>
                                                    <td>{{ $money($row['activeOngoing']['activeTotalInterest']) }}</td>
                                                    <td>{{ number_format($row['badDebt']['badCustomers']) }}</td>
                                                    <td>{{ $money($row['badDebt']['badPrincipal']) }}</td>
                                                    <td>{{ $money($row['badDebt']['badInterest']) }}</td>
                                                    <td>{{ $money($row['badDebt']['badTotal']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>

                        <div class="admin-loan-fallback-pane" data-admin-loan-fallback-pane="monthly" hidden>
                            <div class="admin-loan-fallback-table" id="monthly-report-table-card">
                                @if ($adminMonthlyRows->isEmpty())
                                    <div class="empty">{{ $text('No monthly loan application data found for these filters.', 'រកមិនឃើញទិន្នន័យពាក្យស្នើសុំកម្ចីប្រចាំខែសម្រាប់លក្ខខណ្ឌនេះទេ។') }}</div>
                                @else
                                    <table>
                                    <thead>
                                        <tr>
                                            <th rowspan="2">{{ $text('#', 'ល.រ') }}</th>
                                            <th rowspan="2">{{ $text('Month', 'ខែ') }}</th>
                                            <th colspan="4">{{ $text('Registered Installments', 'អតិថិជនចុះឈ្មោះរំលស់') }}</th>
                                            <th colspan="4">{{ $text('General Installments Paid', 'អតិថិជនរំលស់បានបង់ទូទៅ') }}</th>
                                            <th colspan="5">{{ $text('Settled / Fully Paid-Off', 'អតិថិជនរំលស់បានបង់ផ្ដាច់') }}</th>
                                            <th colspan="4">{{ $text('Active / Ongoing Installments', 'អតិថិជនរំលស់កំពុងដំណើរការ') }}</th>
                                            <th colspan="4">{{ $text('Defaulted / Bad Debt', 'អតិថិជនរំលស់ខូច') }}</th>
                                        </tr>
                                        <tr>
                                            <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                            <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                            <th>{{ $text('Interest', 'ការប្រាក់') }}</th>
                                            <th>{{ $text('Total', 'សរុប') }}</th>
                                            <th>{{ $text('Principal Paid', 'ប្រាក់ដើមបានបង់') }}</th>
                                            <th>{{ $text('Interest Paid', 'ការប្រាក់បានបង់') }}</th>
                                            <th>{{ $text('Discount', 'បញ្ចុះតម្លៃ') }}</th>
                                            <th>{{ $text('Penalty', 'ពិន័យ') }}</th>
                                            <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                            <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                            <th>{{ $text('Interest', 'ការប្រាក់') }}</th>
                                            <th>{{ $text('Penalty', 'ពិន័យ') }}</th>
                                            <th>{{ $text('Discount', 'បញ្ចុះតម្លៃ') }}</th>
                                            <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                            <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                            <th>{{ $text('Monthly Interest', 'ការប្រាក់ប្រចាំខែ') }}</th>
                                            <th>{{ $text('Total Balance', 'សមតុល្យសរុប') }}</th>
                                            <th>{{ $text('Customers', 'អតិថិជន') }}</th>
                                            <th>{{ $text('Principal', 'ប្រាក់ដើម') }}</th>
                                            <th>{{ $text('Interest', 'ការប្រាក់') }}</th>
                                            <th>{{ $text('Total', 'សរុប') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($adminMonthlyRows as $row)
                                            <tr title="{{ $text('Click any amount to view detail', 'ចុចលើចំនួនណាមួយដើម្បីមើលលម្អិត') }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $monthNames[$row['month']] ?? $row['month'] }} {{ $row['year'] }}</td>
                                                <td>{{ number_format($row['registered']['customers']) }}</td>
                                                <td>{{ $money($row['registered']['loanAmount']) }}</td>
                                                <td>{{ $money($row['registered']['interest']) }}</td>
                                                <td>{{ $money($row['registered']['totalInterest']) }}</td>
                                                <td>{{ $money($row['generalPaid']['principalPaid']) }}</td>
                                                <td>{{ $money($row['generalPaid']['interestPaid']) }}</td>
                                                <td>{{ $money($row['generalPaid']['interestDeducted']) }}</td>
                                                <td>{{ $money($row['generalPaid']['penaltiesReceived']) }}</td>
                                                <td>{{ number_format($row['paidOff']['settledCustomers']) }}</td>
                                                <td>{{ $money($row['paidOff']['settledPrincipal']) }}</td>
                                                <td>{{ $money($row['paidOff']['settledInterest']) }}</td>
                                                <td>{{ $money($row['paidOff']['settledPenalties']) }}</td>
                                                <td>{{ $money($row['paidOff']['prepaymentDiscount']) }}</td>
                                                <td>{{ number_format($row['activeOngoing']['activeCustomers']) }}</td>
                                                <td>{{ $money($row['activeOngoing']['activePrincipal']) }}</td>
                                                <td>{{ $money($row['activeOngoing']['activeMonthlyInterest']) }}</td>
                                                <td>{{ $money($row['activeOngoing']['activeTotalInterest']) }}</td>
                                                <td>{{ number_format($row['badDebt']['badCustomers']) }}</td>
                                                <td>{{ $money($row['badDebt']['badPrincipal']) }}</td>
                                                <td>{{ $money($row['badDebt']['badInterest']) }}</td>
                                                <td>{{ $money($row['badDebt']['badTotal']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </main>
    </div>
    <div class="admin-loan-detail-modal" id="adminLoanDetailModal" aria-hidden="true">
        <div class="admin-loan-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="adminLoanDetailTitle">
            <div class="admin-loan-detail-head">
                <div class="admin-loan-detail-title" id="adminLoanDetailTitle">{{ $text('Installment Details', 'ព័ត៌មានលម្អិតកម្ចី') }}</div>
                <button type="button" class="admin-loan-detail-close" id="adminLoanDetailClose">{{ $text('Close', 'បិទ') }}</button>
            </div>
            <iframe class="admin-loan-detail-frame" id="adminLoanDetailFrame" title="{{ $text('Installment Details', 'ព័ត៌មានលម្អិតកម្ចី') }}"></iframe>
        </div>
    </div>
    <script>
        (function () {
            var yearlyRecords = @json($adminRows);
            var monthlyRecords = @json($adminMonthlyRows);
            localStorage.setItem('khnar_yeung_ledger', JSON.stringify(yearlyRecords));
            localStorage.setItem('khnar_yeung_monthly_ledger', JSON.stringify(monthlyRecords));
        })();
    </script>
    @if ($adminLoanJsExists)
        <script type="module" src="{{ asset($adminLoanJsAsset) }}?v={{ filemtime($adminLoanJsPath) }}"></script>
    @endif
    <script>
        (function () {
            var form = document.getElementById('adminLoanFilter');
            if (form) {
                form.addEventListener('change', function (event) {
                    if (event.target && (event.target.tagName === 'SELECT' || event.target.type === 'date') && event.target.name !== 'language') {
                        form.submit();
                    }
                });
            }

            var targetLanguage = '{{ $isKhmer ? 'km' : 'en' }}';
            var adminLoanDetailsUrl = @json(route('loan-management.admin-loan.details'));
            var adminLoanExportUrl = @json(route('loan-management.admin-loan.export', $adminLoanFilterPayload));
            var adminLoanFilters = @json($adminLoanFilterPayload);
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

            function syncExportButton() {
                var exportButton = document.getElementById('export-csv-btn');
                if (!exportButton) {
                    return;
                }
                exportButton.id = 'export-xlsx-btn';
                exportButton.setAttribute('data-export-xlsx-url', adminLoanExportUrl);
                var label = exportButton.querySelector('span');
                if (label) {
                    label.textContent = targetLanguage === 'km' ? 'នាំចេញ XLSX' : 'Export XLSX';
                }
            }

            var exportSyncAttempts = 0;
            var exportButtonSync = setInterval(function () {
                exportSyncAttempts += 1;
                syncExportButton();
                if (document.getElementById('export-xlsx-btn') || exportSyncAttempts > 30) {
                    clearInterval(exportButtonSync);
                }
            }, 150);

            function fixAdminLoanSearchFields() {
                ['year-search-input', 'monthly-search-input'].forEach(function (id) {
                    var input = document.getElementById(id);
                    if (!input) {
                        return;
                    }
                    input.classList.add('admin-loan-search-input-fixed');
                    if (input.parentElement) {
                        input.parentElement.classList.add('admin-loan-search-wrap-fixed');
                    }
                });
            }

            var searchFixAttempts = 0;
            var searchFixSync = setInterval(function () {
                searchFixAttempts += 1;
                fixAdminLoanSearchFields();
                if (searchFixAttempts > 60) {
                    clearInterval(searchFixSync);
                }
            }, 150);
            document.addEventListener('click', function () {
                setTimeout(fixAdminLoanSearchFields, 100);
            }, true);
            document.addEventListener('focusin', fixAdminLoanSearchFields, true);
            document.addEventListener('input', fixAdminLoanSearchFields, true);

            document.addEventListener('click', function (event) {
                var tab = event.target && event.target.closest ? event.target.closest('[data-admin-loan-fallback-tab]') : null;
                if (!tab) {
                    return;
                }
                var target = tab.getAttribute('data-admin-loan-fallback-tab');
                document.querySelectorAll('[data-admin-loan-fallback-tab]').forEach(function (button) {
                    button.classList.toggle('is-active', button === tab);
                });
                document.querySelectorAll('[data-admin-loan-fallback-pane]').forEach(function (pane) {
                    pane.hidden = pane.getAttribute('data-admin-loan-fallback-pane') !== target;
                });
            });

            document.addEventListener('click', function (event) {
                var button = event.target && event.target.closest ? event.target.closest('#export-xlsx-btn, #export-csv-btn') : null;
                if (!button) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                window.location.href = button.getAttribute('data-export-xlsx-url') || adminLoanExportUrl;
            }, true);

            function adminLoanGroupForCellIndex(index) {
                if (index >= 2 && index <= 5) return 'registered';
                if (index >= 6 && index <= 9) return 'generalPaid';
                if (index >= 10 && index <= 14) return 'paidOff';
                if (index >= 15 && index <= 18) return 'active';
                if (index >= 19 && index <= 22) return 'badDebt';
                return 'all';
            }

            function adminLoanGroupLabel(group) {
                var labels = {
                    all: targetLanguage === 'km' ? 'កម្ចីទាំងអស់' : 'All Installments',
                    registered: targetLanguage === 'km' ? 'អតិថិជនចុះឈ្មោះរំលស់' : 'Registered Installments',
                    generalPaid: targetLanguage === 'km' ? 'អតិថិជនរំលស់បានបង់ទូរទៅ' : 'General Installments Paid',
                    paidOff: targetLanguage === 'km' ? 'អតិថិជនរំលស់បានបង់ផ្ដាច់' : 'Settled / Fully Paid Off',
                    active: targetLanguage === 'km' ? 'អតិថិជនរំលស់កំពុងដំណើរការ' : 'Active / Ongoing Installments',
                    badDebt: targetLanguage === 'km' ? 'អតិថិជនរំលស់ខូច' : 'Defaulted / Bad Debt'
                };
                return labels[group] || labels.all;
            }

            function adminLoanMonthFromText(text) {
                var normalized = (text || '').toLowerCase();
                var monthNames = [
                    ['january', 'មករា'],
                    ['february', 'កុម្ភៈ'],
                    ['march', 'មីនា'],
                    ['april', 'មេសា'],
                    ['may', 'ឧសភា'],
                    ['june', 'មិថុនា'],
                    ['july', 'កក្កដា'],
                    ['august', 'សីហា'],
                    ['september', 'កញ្ញា'],
                    ['october', 'តុលា'],
                    ['november', 'វិច្ឆិកា'],
                    ['december', 'ធ្នូ']
                ];
                for (var i = 0; i < monthNames.length; i++) {
                    if (normalized.indexOf(monthNames[i][0]) !== -1 || normalized.indexOf(monthNames[i][1]) !== -1) {
                        return i + 1;
                    }
                }
                return null;
            }

            function openAdminLoanDetailModal(year, group, month) {
                var params = new URLSearchParams();
                params.set('year', String(year));
                params.set('group', group);
                if (month) {
                    params.set('month', String(month));
                }
                Object.keys(adminLoanFilters).forEach(function (key) {
                    if (adminLoanFilters[key] !== null && adminLoanFilters[key] !== undefined && String(adminLoanFilters[key]) !== '') {
                        params.set(key, adminLoanFilters[key]);
                    }
                });

                document.getElementById('adminLoanDetailTitle').textContent = adminLoanGroupLabel(group) + ' - ' + year + (month ? '-' + String(month).padStart(2, '0') : '');
                document.getElementById('adminLoanDetailFrame').setAttribute('src', adminLoanDetailsUrl + '?' + params.toString());
                document.getElementById('adminLoanDetailModal').classList.add('is-open');
                document.getElementById('adminLoanDetailModal').setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeAdminLoanDetailModal() {
                document.getElementById('adminLoanDetailModal').classList.remove('is-open');
                document.getElementById('adminLoanDetailModal').setAttribute('aria-hidden', 'true');
                document.getElementById('adminLoanDetailFrame').setAttribute('src', 'about:blank');
                document.body.style.overflow = '';
            }

            document.addEventListener('mouseover', function (event) {
                var row = event.target && event.target.closest ? event.target.closest('#admin-loan-react-root tbody tr') : null;
                if (!row || row.dataset.adminLoanHinted === '1') {
                    return;
                }
                row.dataset.adminLoanHinted = '1';
                row.title = targetLanguage === 'km' ? 'ចុចដើម្បីមើលទិន្នន័យកម្ចីពេញ' : 'Click to view full loan data';
            });

            document.addEventListener('click', function (event) {
                if (!event.target || !event.target.closest) {
                    return;
                }
                if (event.target.closest('a, button, input, select, textarea')) {
                    return;
                }

                var cell = event.target.closest('#admin-loan-react-root tbody td');
                var row = cell ? cell.closest('tr') : null;
                if (!cell || !row) {
                    return;
                }

                var yearCell = row.cells && row.cells.length > 1 ? row.cells[1] : null;
                var year = yearCell ? parseInt((yearCell.textContent || '').replace(/\D+/g, ''), 10) : NaN;
                if (!year || Number.isNaN(year)) {
                    return;
                }
                var month = row.closest('#monthly-report-table-card') ? adminLoanMonthFromText(yearCell.textContent || '') : null;

                event.preventDefault();
                event.stopPropagation();
                openAdminLoanDetailModal(year, adminLoanGroupForCellIndex(cell.cellIndex), month);
            }, true);

            document.getElementById('adminLoanDetailClose').addEventListener('click', closeAdminLoanDetailModal);
            document.getElementById('adminLoanDetailModal').addEventListener('click', function (event) {
                if (event.target === this) {
                    closeAdminLoanDetailModal();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAdminLoanDetailModal();
                }
            });
	        })();
	</script>
	@if (file_exists($moduleJsPath))
	    <script>{!! file_get_contents($moduleJsPath) !!}</script>
	@endif
	</body>
	</html>
