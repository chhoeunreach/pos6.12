@extends('loanmanagement::layouts.app')
@section('title', (session('user.language', config('app.locale')) === 'km') ? 'អតិថិជនបង់រំលស់' : 'Installment Customers')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $stats = $stats ?? [
        'total' => 0,
        'active' => 0,
        'with_loans' => 0,
        'can_login' => 0,
        'blacklisted' => 0,
    ];
@endphp

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       HIGH-END PROFESSIONAL STYLE FOR INSTALLMENT CUSTOMERS
       ========================================================= */
    .lm-cust-page {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Page Header */
    .lm-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .lm-page-title-wrap h1 {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .lm-page-subtitle {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }
    .lm-page-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* KPI Summary Cards */
    .lm-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    @media (max-width: 1200px) {
        .lm-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .lm-kpi-grid { grid-template-columns: 1fr; }
    }
    .lm-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 12px rgba(15,23,42,0.02);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .lm-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(15,23,42,0.06);
    }
    .lm-kpi-icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .lm-kpi-blue { background: #eff6ff; color: #2563eb; }
    .lm-kpi-green { background: #ecfdf5; color: #059669; }
    .lm-kpi-purple { background: #f5f3ff; color: #7c3aed; }
    .lm-kpi-sky { background: #f0f9ff; color: #0284c7; }
    .lm-kpi-rose { background: #fff1f2; color: #e11d48; }

    .lm-kpi-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 3px;
        display: block;
    }
    .lm-kpi-value {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        margin: 0;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px 16px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1100px) {
        .lm-pos-filter-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .lm-pos-filter-grid { grid-template-columns: 1fr; }
    }
    .lm-pos-filter-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .lm-pos-filter-field label {
        font-size: 12.5px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    .lm-pos-filter-field .form-control {
        height: 38px;
        padding: 6px 12px;
        font-size: 13px;
        color: #1e293b;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        outline: none;
        width: 100%;
        box-shadow: none;
        transition: all 0.15s ease;
    }
    .lm-pos-filter-field .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .lm-pos-filter-field select.form-control {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 32px;
        -webkit-appearance: none;
        appearance: none;
    }

    .lm-pos-filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
    }
    .lm-btn-filter-apply {
        height: 38px;
        padding: 0 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        background: #2563eb;
        color: #fff;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 6px rgba(37,99,235,0.25);
        transition: background .15s ease;
    }
    .lm-btn-filter-apply:hover { background: #1d4ed8; color: #fff; }
    .lm-btn-filter-reset {
        height: 38px;
        padding: 0 14px;
        border-radius: 8px;
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
        transition: all .15s ease;
    }
    .lm-btn-filter-reset:hover { background: #e2e8f0; color: #0f172a; text-decoration: none; }

    /* DataTable Container Card */
    .lm-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 12px rgba(15,23,42,0.03);
        overflow: hidden;
    }

    /* DataTable Header Toolbar */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 14px 18px !important;
        background: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .lm-dt-left-group {
        display: inline-flex !important;
        align-items: center !important;
        gap: 12px !important;
        flex-wrap: wrap !important;
    }
    .lm-dt-length-select-wrap {
        display: inline-flex !important;
        align-items: center !important;
    }
    .lm-dt-length-select-wrap label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        margin: 0 !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        color: #475569 !important;
        white-space: nowrap !important;
    }
    .lm-dt-length-select-wrap select {
        height: 33px !important;
        padding: 2px 26px 2px 9px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 12.5px !important;
        font-weight: 700 !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        outline: none !important;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .lm-dt-length-select-wrap select:focus {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
    }
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        flex-wrap: wrap !important;
    }
    .lm-dt-buttons .btn {
        border-radius: 6px !important;
        padding: 6px 13px !important;
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
    .lm-dt-search input {
        height: 36px !important;
        min-width: 240px !important;
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 6px 12px !important;
        font-size: 13px !important;
        outline: none !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transition: all 0.15s ease !important;
    }
    .lm-dt-search input:focus {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
    }

    /* Table Design */
    .lm-table-dense {
        width: 100% !important;
        margin-bottom: 0 !important;
        font-size: 13px;
        border-collapse: separate;
        border-spacing: 0;
    }
    .lm-table-dense thead th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: .3px;
        padding: 12px 14px;
        border-top: none !important;
        border-bottom: 1.5px solid #cbd5e1 !important;
        white-space: nowrap;
    }
    .lm-table-dense tbody td {
        padding: 12px 14px;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-table-dense tbody tr:hover {
        background-color: #f8fafc;
    }

    /* Customer Info Cell */
    .lm-cust-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lm-cust-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid #cbd5e1;
        flex-shrink: 0;
    }
    .lm-cust-avatar-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
        border: 1.5px solid #bfdbfe;
    }
    .lm-cust-names {
        line-height: 1.3;
    }
    .lm-cust-primary {
        font-weight: 700;
        color: #0f172a;
        font-size: 13.5px;
        text-decoration: none;
    }
    .lm-cust-primary:hover {
        color: #2563eb;
        text-decoration: underline;
    }
    .lm-cust-secondary {
        font-size: 11.5px;
        color: #64748b;
        margin-top: 1px;
    }

    /* Badges */
    .lm-badge-code {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 2px 7px;
        font-family: monospace;
        font-size: 11px;
        font-weight: 600;
    }
    .lm-badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .lm-badge-active { background: #dcfce7; color: #15803d; }
    .lm-badge-inactive { background: #f1f5f9; color: #475569; }
    .lm-badge-pending { background: #fef3c7; color: #b45309; }
    .lm-badge-suspended { background: #fee2e2; color: #b91c1c; }
    .lm-badge-blacklist { background: #ffe4e6; color: #e11d48; border: 1px solid #fecdd3; }

    /* Action Buttons */
    .lm-action-btn-group {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .lm-btn-action {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #475569;
        font-size: 12px;
        transition: all .15s ease;
        text-decoration: none;
    }
    .lm-btn-action:hover {
        background: #f8fafc;
        color: #0f172a;
        border-color: #94a3b8;
    }
    .lm-btn-action-primary:hover {
        background: #eff6ff;
        color: #2563eb;
        border-color: #93c5fd;
    }
    .lm-btn-action-danger:hover {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fca5a5;
    }

    /* Contact Links */
    .lm-phone-link {
        color: #0f172a;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .lm-phone-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }
</style>
@endsection

@section('content_body')
<div class="lm-cust-page">
    {{-- Page Header --}}
    <div class="lm-page-header">
        <div class="lm-page-title-wrap">
            <h1>
                <i class="fa fa-users text-primary"></i>
                {{ $lmText('Installment Customers', 'អតិថិជនបង់រំលស់') }}
            </h1>
            <p class="lm-page-subtitle">
                {{ $lmText('Manage installment client accounts, KYC verification, contracts, and portal access', 'គ្រប់គ្រងព័ត៌មានអតិថិជនបង់រំលស់ ការផ្ទៀងផ្ទាត់ KYC កិច្ចសន្យា និងសិទ្ធិចូលប្រើប្រាស់') }}
            </p>
        </div>
        <div class="lm-page-actions">
            @if(Route::has('loan-management.loans.create-standalone-modal'))
                <button type="button" class="btn btn-success btn-sm lm-standalone-loan-trigger"
                        data-url="{{ route('loan-management.loans.create-standalone-modal') }}"
                        data-target="#standaloneLoanModal"
                        style="font-weight: 700; padding: 7px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);">
                    <i class="fa fa-plus-circle"></i>
                    <span>{{ $lmText('New Installment', 'បង្កើតកម្ចីរំលស់ថ្មី') }}</span>
                </button>
            @endif

            <a href="{{ route('loan-management.customers.create') }}" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 7px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(37,99,235,0.25);">
                <i class="fa fa-user-plus"></i>
                <span>{{ $lmText('Add Customer', 'បន្ថែមអតិថិជនថ្មី') }}</span>
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible" style="border-radius: 8px; margin-bottom: 20px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-check-circle"></i> {{ is_array(session('status')) ? (session('status')['msg'] ?? 'Saved successfully.') : session('status') }}
        </div>
    @endif

    {{-- Top KPI Cards Bar --}}
    <div class="lm-kpi-grid">
        <div class="lm-kpi-card">
            <div class="lm-kpi-icon lm-kpi-blue">
                <i class="fa fa-users"></i>
            </div>
            <div>
                <span class="lm-kpi-label">{{ $lmText('Total Customers', 'អតិថិជនសរុប') }}</span>
                <h3 class="lm-kpi-value">{{ number_format($stats['total']) }}</h3>
            </div>
        </div>

        <div class="lm-kpi-card">
            <div class="lm-kpi-icon lm-kpi-green">
                <i class="fa fa-check-circle"></i>
            </div>
            <div>
                <span class="lm-kpi-label">{{ $lmText('Active Clients', 'អតិថិជនសកម្ម') }}</span>
                <h3 class="lm-kpi-value">{{ number_format($stats['active']) }}</h3>
            </div>
        </div>

        <div class="lm-kpi-card">
            <div class="lm-kpi-icon lm-kpi-purple">
                <i class="fa fa-file-text-o"></i>
            </div>
            <div>
                <span class="lm-kpi-label">{{ $lmText('With Agreements', 'មានកិច្ចសន្យារំលស់') }}</span>
                <h3 class="lm-kpi-value">{{ number_format($stats['with_loans']) }}</h3>
            </div>
        </div>

        <div class="lm-kpi-card">
            <div class="lm-kpi-icon lm-kpi-sky">
                <i class="fa fa-mobile" style="font-size: 26px;"></i>
            </div>
            <div>
                <span class="lm-kpi-label">{{ $lmText('Portal Login', 'គណនីចូលប្រព័ន្ធ') }}</span>
                <h3 class="lm-kpi-value">{{ number_format($stats['can_login']) }}</h3>
            </div>
        </div>

        <div class="lm-kpi-card">
            <div class="lm-kpi-icon lm-kpi-rose">
                <i class="fa fa-ban"></i>
            </div>
            <div>
                <span class="lm-kpi-label">{{ $lmText('Blacklisted', 'បញ្ជីខ្មៅ') }}</span>
                <h3 class="lm-kpi-value">{{ number_format($stats['blacklisted']) }}</h3>
            </div>
        </div>
    </div>

    @if(!$tableExists)
        <div class="alert alert-danger" style="border-radius: 8px;">`loan_customers` table not found in `mysql_loan` database.</div>
    @else
        {{-- Professional Filter Component --}}
        @component('components.filters', ['title' => $lmText('Advanced Customer Filters', 'តម្រងស្វែងរកកម្រិតខ្ពស់')])
            <form method="GET" action="{{ route('loan-management.customers.index') }}" id="loanCustomersFilterForm">
                <div class="lm-pos-filter-grid">
                    {{-- Customer Name --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Customer Name', 'ឈ្មោះអតិថិជន') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ request('name') }}" placeholder="{{ $lmText('Search Khmer or Latin name...', 'ឈ្មោះខ្មែរ ឬឡាតាំង...') }}">
                    </div>

                    {{-- Phone Number --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Phone Number', 'លេខទូរស័ព្ទ') }}</label>
                        <input type="text" name="phone" class="form-control" value="{{ request('phone') }}" placeholder="{{ $lmText('e.g. 012 345 678', 'ឧទាហរណ៍ 012 345 678') }}">
                    </div>

                    {{-- Customer Code / National ID --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Customer Code / National ID', 'កូដអតិថិជន / អត្តសញ្ញាណប័ណ្ណ') }}</label>
                        <input type="text" name="customer_code" class="form-control" value="{{ request('customer_code') }}" placeholder="{{ $lmText('Code or National ID...', 'កូដ ឬលេខអត្តសញ្ញាណប័ណ្ណ...') }}">
                    </div>

                    {{-- Business Location --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Business Branch / Location', 'សាខាអាជីវកម្ម') }}</label>
                        <select name="location_id" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All Locations', 'គ្រប់សាខាទាំងអស់') }}</option>
                            @foreach($locations as $locId => $locName)
                                <option value="{{ $locId }}" {{ request('location_id') == $locId ? 'selected' : '' }}>{{ $locName }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Account Status', 'ស្ថានភាពគណនី') }}</label>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All Statuses', 'គ្រប់ស្ថានភាព') }}</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ $lmText('Active', 'សកម្ម') }}</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ $lmText('Inactive', 'អសកម្ម') }}</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ $lmText('Pending', 'រង់ចាំ') }}</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ $lmText('Suspended', 'ផ្អាក') }}</option>
                        </select>
                    </div>

                    {{-- Portal Login --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Portal Login Access', 'សិទ្ធិចូលប្រព័ន្ធ Mobile') }}</label>
                        <select name="can_login" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All Access', 'ទាំងអស់') }}</option>
                            <option value="1" {{ request('can_login') === '1' ? 'selected' : '' }}>{{ $lmText('Allowed (Enabled)', 'អនុញ្ញាត (បើក)') }}</option>
                            <option value="0" {{ request('can_login') === '0' ? 'selected' : '' }}>{{ $lmText('Disabled', 'មិនអនុញ្ញាត (បិទ)') }}</option>
                        </select>
                    </div>

                    {{-- Blacklist Status --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Blacklist / Risk', 'ស្ថានភាពបញ្ជីខ្មៅ') }}</label>
                        <select name="blacklist_status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ $lmText('All Customers', 'ទាំងអស់') }}</option>
                            <option value="1" {{ request('blacklist_status') === '1' ? 'selected' : '' }}>{{ $lmText('Blacklisted Only', 'ក្នុងបញ្ជីខ្មៅ') }}</option>
                            <option value="0" {{ request('blacklist_status') === '0' ? 'selected' : '' }}>{{ $lmText('Clean / Normal', 'ធម្មតា (ស្អាត)') }}</option>
                        </select>
                    </div>

                    {{-- Per Page / Show Records --}}
                    <div class="lm-pos-filter-field">
                        <label>{{ $lmText('Show Per Page', 'បង្ហាញក្នុងមួយទំព័រ') }}</label>
                        <select name="per_page" class="form-control" onchange="this.form.submit()">
                            <option value="10" {{ request('per_page', 25) == 10 ? 'selected' : '' }}>10 {{ $lmText('entries', 'ជួរ') }}</option>
                            <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 {{ $lmText('entries (Default)', 'ជួរ (លំនាំដើម)') }}</option>
                            <option value="50" {{ request('per_page', 25) == 50 ? 'selected' : '' }}>50 {{ $lmText('entries', 'ជួរ') }}</option>
                            <option value="100" {{ request('per_page', 25) == 100 ? 'selected' : '' }}>100 {{ $lmText('entries', 'ជួរ') }}</option>
                            <option value="250" {{ request('per_page', 25) == 250 ? 'selected' : '' }}>250 {{ $lmText('entries', 'ជួរ') }}</option>
                        </select>
                    </div>

                    {{-- Filter & Reset Actions --}}
                    <div class="lm-pos-filter-field">
                        <label>&nbsp;</label>
                        <div class="lm-pos-filter-actions">
                            <button type="submit" class="lm-btn-filter-apply">
                                <i class="fa fa-filter"></i> {{ $lmText('Apply Filter', 'ស្វែងរក') }}
                            </button>
                            <a href="{{ route('loan-management.customers.index') }}" class="lm-btn-filter-reset">
                                <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        @endcomponent

        {{-- Main DataTable Card --}}
        <div class="lm-table-card">
            <div class="table-responsive">
                <table class="lm-table-dense table table-hover" id="loanCustomersTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th style="width: 110px; text-align: center;" class="no-export">{{ $lmText('Actions', 'សកម្មភាព') }}</th>
                            <th>{{ $lmText('Customer Identity & Name', 'អត្តសញ្ញាណ & ឈ្មោះអតិថិជន') }}</th>
                            <th>{{ $lmText('Contact Details', 'ទំនាក់ទំនង') }}</th>
                            <th>{{ $lmText('National ID & Address', 'អត្តសញ្ញាណប័ណ្ណ & អាសយដ្ឋាន') }}</th>
                            <th>{{ $lmText('Location / Branch', 'សាខា') }}</th>
                            <th style="text-align: center;">{{ $lmText('Portal', 'Mobile App') }}</th>
                            <th style="text-align: center;">{{ $lmText('GPS', 'GPS') }}</th>
                            <th style="text-align: center;">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                            <th style="text-align: center;">{{ $lmText('Risk / Blacklist', 'ហានិភ័យ') }}</th>
                            <th>{{ $lmText('Joined Date', 'កាលបរិច្ឆេទចូលរួម') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $index => $c)
                            @php
                                $khmerName = trim((string) ($c->khmer_name ?? ''));
                                $latinName = trim((string) ($c->name ?? $c->customer_name ?? ''));
                                $displayName = $khmerName ?: ($latinName ?: ('Customer #' . $c->id));
                                $status = strtolower((string)($c->status ?? 'active'));
                                $statusClass = match($status) {
                                    'active' => 'lm-badge-active',
                                    'suspended' => 'lm-badge-suspended',
                                    'pending' => 'lm-badge-pending',
                                    default => 'lm-badge-inactive'
                                };
                            @endphp
                            <tr>
                                <td style="text-align: center; color: #94a3b8; font-weight: 600;">
                                    {{ ($customers->currentPage() - 1) * $customers->perPage() + $index + 1 }}
                                </td>
                                <td style="text-align: center;">
                                    <div class="lm-action-btn-group">
                                        <a href="{{ route('loan-management.customers.show', $c->id) }}" class="lm-btn-action lm-btn-action-primary" title="{{ $lmText('View Profile & Loans', 'មើលប្រវត្តិរូប & កម្ចី') }}">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        @can('loan_management.edit')
                                            <a href="{{ route('loan-management.customers.edit', $c->id) }}" class="lm-btn-action" title="{{ $lmText('Edit Customer', 'កែប្រែព័ត៌មាន') }}">
                                                <i class="fa fa-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('loan_management.delete')
                                            <form method="POST" action="{{ route('loan-management.customers.destroy', $c->id) }}" style="display:inline;" onsubmit="return confirm('{{ $lmText('Are you sure you want to delete this customer record?', 'តើអ្នកប្រាកដជាចង់លុបអតិថិជននេះមែនទេ?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="lm-btn-action lm-btn-action-danger" title="{{ $lmText('Delete Customer', 'លុប') }}">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                <td>
                                    <div class="lm-cust-cell">
                                        @if(!empty($c->photo_url))
                                            <img src="{{ $c->photo_url }}" class="lm-cust-avatar" alt="Avatar">
                                        @else
                                            <div class="lm-cust-avatar-icon">
                                                <i class="fa fa-user"></i>
                                            </div>
                                        @endif
                                        <div class="lm-cust-names">
                                            <a href="{{ route('loan-management.customers.show', $c->id) }}" class="lm-cust-primary">
                                                {{ $displayName }}
                                            </a>
                                            @if($khmerName && $latinName && $khmerName !== $latinName)
                                                <div class="lm-cust-secondary">{{ $latinName }}</div>
                                            @endif
                                            <div style="margin-top: 3px;">
                                                <span class="lm-badge-code">
                                                    <i class="fa fa-hashtag" style="font-size: 9px; opacity: 0.6;"></i> {{ $c->customer_code ?: ('CUST-' . $c->id) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if(!empty($c->phone))
                                        <div>
                                            <a href="tel:{{ $c->phone }}" class="lm-phone-link">
                                                <i class="fa fa-phone text-success" style="font-size: 11px;"></i> {{ $c->phone }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                    @if(!empty($c->alternate_phone))
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                            <i class="fa fa-phone-square"></i> {{ $c->alternate_phone }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($c->id_card_number))
                                        <div style="font-weight: 700; color: #334155; font-size: 12px;">
                                            <i class="fa fa-id-card-o text-muted"></i> {{ $c->id_card_number }}
                                        </div>
                                    @endif
                                    @php
                                        $locAddr = array_filter([$c->village ?? '', $c->commune ?? '', $c->district ?? '', $c->province ?? '']);
                                        $fullAddr = !empty($c->address) ? $c->address : implode(', ', $locAddr);
                                    @endphp
                                    <div style="font-size: 11.5px; color: #64748b; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 2px;" title="{{ $fullAddr }}">
                                        {{ $fullAddr ?: '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 600; color: #475569;">
                                        {{ $c->business_location_name_snapshot ?? ($locations[$c->business_location_id ?? 0] ?? '-') }}
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    @if(!empty($c->can_login))
                                        <span class="label label-success" style="font-size: 10px; border-radius: 4px; padding: 3px 6px;">
                                            <i class="fa fa-check"></i> Enabled
                                        </span>
                                    @else
                                        <span class="label label-default" style="font-size: 10px; border-radius: 4px; padding: 3px 6px; color: #94a3b8;">
                                            Off
                                        </span>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    @if(!empty($c->allow_gps_tracking))
                                        <span class="label label-info" style="font-size: 10px; border-radius: 4px; padding: 3px 6px;">
                                            <i class="fa fa-map-marker"></i> On
                                        </span>
                                    @else
                                        <span class="label label-default" style="font-size: 10px; border-radius: 4px; padding: 3px 6px; color: #94a3b8;">
                                            Off
                                        </span>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    <span class="lm-badge-status {{ $statusClass }}">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; display: inline-block;"></span>
                                        {{ $c->status ?? 'Active' }}
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    @if(!empty($c->blacklist_status))
                                        <span class="lm-badge-status lm-badge-blacklist" title="Customer is flagged on blacklist">
                                            <i class="fa fa-ban"></i> Blacklist
                                        </span>
                                    @else
                                        <span style="font-size: 11px; color: #10b981; font-weight: 600;">
                                            <i class="fa fa-check-circle"></i> Clean
                                        </span>
                                    @endif
                                </td>
                                <td style="font-size: 11.5px; color: #64748b; white-space: nowrap;">
                                    {{ !empty($c->created_at) ? date('M d, Y', strtotime($c->created_at)) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px 20px;">
                                    <div style="color: #94a3b8; margin-bottom: 10px;">
                                        <i class="fa fa-user-times fa-3x"></i>
                                    </div>
                                    <h4 style="font-weight: 700; color: #475569; margin: 0 0 4px 0;">{{ $lmText('No installment customers found', 'រកមិនឃើញអតិថិជនបង់រំលស់ទេ') }}</h4>
                                    <p style="font-size: 12px; color: #94a3b8; margin: 0 0 14px 0;">{{ $lmText('Try adjusting your filters or register a new customer', 'សូមសាកល្បងផ្លាស់ប្តូរតម្រងស្វែងរក ឬចុចបន្ថែមអតិថិជនថ្មី') }}</p>
                                    <a href="{{ route('loan-management.customers.create') }}" class="btn btn-primary btn-sm" style="border-radius: 6px; font-weight: 600;">
                                        <i class="fa fa-plus-circle"></i> {{ $lmText('Create New Customer', 'បង្កើតអតិថិជនថ្មី') }}
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($customers instanceof \Illuminate\Contracts\Pagination\Paginator || $customers instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div style="padding: 14px 18px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                    <div style="font-size: 12.5px; color: #64748b;">
                        {{ $lmText('Showing', 'បង្ហាញ') }} <strong>{{ $customers->firstItem() ?? 0 }}</strong> {{ $lmText('to', 'ដល់') }} <strong>{{ $customers->lastItem() ?? 0 }}</strong> {{ $lmText('of', 'នៃ') }} <strong>{{ $customers->total() }}</strong> {{ $lmText('customers', 'អតិថិជន') }}
                    </div>
                    <div>
                        {{ $customers->links() }}
                    </div>
                </div>
            @endif
        </div>
    @endif
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
    function updateCustomerPageSize(perPage) {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage);
        url.searchParams.delete('page');
        return url.toString();
    }

    $(document).ready(function() {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loanCustomersTable')) {
            $('#loanCustomersTable').DataTable({
                dom: '<"lm-dt-top"<"lm-dt-left-group"<"#tablePerPageSlot">B>f>rt<"lm-dt-bottom"ip><"clear">',
                buttons: [
                    {
                        extend: 'csv',
                        text: '<i class="fa fa-file-text-o"></i> CSV',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':not(.no-export)' }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel-o"></i> Excel',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':not(.no-export)' }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print"></i> Print',
                        className: 'btn btn-default btn-sm',
                        exportOptions: { columns: ':not(.no-export)' }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fa fa-file-pdf-o"></i> PDF',
                        className: 'btn btn-default btn-sm',
                        orientation: 'landscape',
                        pageSize: 'A4',
                        exportOptions: { columns: ':not(.no-export)' }
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fa fa-columns"></i> Columns',
                        className: 'btn btn-default btn-sm'
                    }
                ],
                initComplete: function() {
                    $('#tablePerPageSlot').html(
                        '<div class="lm-dt-length-select-wrap">' +
                        '  <label>' +
                        '    <span>' + @json($lmText('Show:', 'បង្ហាញ:')) + '</span>' +
                        '    <select class="form-control" onchange="window.location.href = updateCustomerPageSize(this.value)">' +
                        '      <option value="10"' + ({{ request('per_page', 25) }} == 10 ? ' selected' : '') + '>10</option>' +
                        '      <option value="25"' + ({{ request('per_page', 25) }} == 25 ? ' selected' : '') + '>25 (Default)</option>' +
                        '      <option value="50"' + ({{ request('per_page', 25) }} == 50 ? ' selected' : '') + '>50</option>' +
                        '      <option value="100"' + ({{ request('per_page', 25) }} == 100 ? ' selected' : '') + '>100</option>' +
                        '      <option value="250"' + ({{ request('per_page', 25) }} == 250 ? ' selected' : '') + '>250</option>' +
                        '    </select>' +
                        '    <span>' + @json($lmText('entries', 'ជួរ')) + '</span>' +
                        '  </label>' +
                        '</div>'
                    );
                },
                paging: false,
                info: false,
                searching: true,
                ordering: true,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [1] }
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "{{ $lmText('Filter table rows...', 'ស្វែងរកក្នុងតារាង...') }}",
                    zeroRecords: "{{ $lmText('No matching records found', 'រកមិនឃើញទិន្នន័យត្រូវគ្នាទេ') }}",
                    emptyTable: "{{ $lmText('No data available in table', 'គ្មានទិន្នន័យក្នុងតារាងទេ') }}"
                }
            });
        }
    });
</script>
@endsection
