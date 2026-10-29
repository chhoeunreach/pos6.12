@extends('loanmanagement::layouts.app')
@section('title', 'Payment Methods Settings')

@section('loan_css')
<style>
    /* ========================================================
       ENTERPRISE PAYMENT METHODS - MODERN EXECUTIVE REDESIGN
       ======================================================== */
    .lm-settings-wrapper {
        color: #0f172a;
        padding-bottom: 60px;
    }

    /* Page Header */
    .lm-settings-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }
    .lm-settings-header-left h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .lm-settings-header-left p {
        margin: 4px 0 0;
        font-size: 13px;
        color: #64748b;
    }
    .lm-settings-header-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .lm-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
    }
    .lm-chip i { font-size: 13px; color: var(--lm-primary, #6366f1); }
    .lm-chip-success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .lm-chip-success i { color: #059669; }

    /* Search Bar */
    .lm-settings-searchbar {
        position: relative;
        margin-bottom: 20px;
    }
    .lm-settings-searchbar input {
        width: 100%;
        height: 46px;
        padding: 0 44px 0 46px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #fff;
        font-size: 14px;
        color: #0f172a;
        box-shadow: 0 1px 3px rgba(15,23,42,0.04);
        transition: all 0.2s ease;
    }
    .lm-settings-searchbar input:focus {
        border-color: var(--lm-primary, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }
    .lm-settings-searchbar .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 16px;
        pointer-events: none;
    }
    .lm-settings-searchbar .clear-search {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 14px;
        display: none;
    }

    /* Main Container Shell */
    .lm-settings-shell {
        display: grid;
        grid-template-columns: 290px minmax(0, 1fr);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    /* Nav Sidebar */
    .lm-settings-nav {
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        padding: 20px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .lm-nav-group-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        padding: 6px 12px 8px;
        margin: 0;
    }
    .lm-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        margin-bottom: 4px;
        border-radius: 8px;
        color: #475569;
        font-size: 13.5px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }
    .lm-nav-item:hover {
        background: #f1f5f9;
        color: #0f172a;
        text-decoration: none;
    }
    .lm-nav-item.active {
        background: #fff;
        color: var(--lm-primary, #6366f1);
        border-color: #e2e8f0;
        box-shadow: 0 2px 6px rgba(15,23,42,0.04);
        font-weight: 800;
    }
    .lm-nav-item i {
        font-size: 16px;
        width: 20px;
        text-align: center;
        color: #64748b;
        transition: color 0.15s ease;
    }
    .lm-nav-item.active i {
        color: var(--lm-primary, #6366f1);
    }
    .lm-nav-item .nav-badge {
        margin-left: auto;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #e2e8f0;
        color: #475569;
        font-weight: 700;
    }
    .lm-nav-item.active .nav-badge {
        background: var(--lm-primary-50, #eef2ff);
        color: var(--lm-primary, #6366f1);
    }
    .lm-nav-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 14px 6px;
    }

    /* Content Area */
    .lm-settings-content {
        padding: 32px 36px;
        min-height: 640px;
    }

    /* Section Head */
    .lm-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }
    .lm-section-head h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
    }
    .lm-section-head p {
        margin: 4px 0 0;
        font-size: 13px;
        color: #64748b;
    }

    /* Method Cards Grid */
    .lm-methods-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }

    .lm-method-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(15,23,42,0.03);
        transition: all 0.2s ease;
        position: relative;
    }
    .lm-method-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(15,23,42,0.06);
    }
    .lm-method-card.inactive {
        background: #f8fafc;
        opacity: 0.75;
    }

    .lm-method-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .lm-method-header-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
    }
    .lm-method-icon-circle {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--lm-primary-50, #eef2ff);
        color: var(--lm-primary, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }

    /* Inputs inside Card */
    .lm-field-label {
        display: block;
        margin-bottom: 6px;
        color: #334155;
        font-size: 12px;
        font-weight: 800;
    }
    .lm-input {
        width: 100%;
        height: 40px;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 13.5px;
        box-shadow: none;
        transition: all 0.15s ease;
    }
    .lm-input:focus {
        border-color: var(--lm-primary, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }

    .lm-method-row-2 {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 90px;
        gap: 10px;
        margin-top: 10px;
    }

    /* Usage Stats Badge */
    .lm-method-stats {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
        font-size: 12px;
        color: #64748b;
    }
    .lm-method-stats strong {
        color: #0f172a;
        font-size: 13px;
    }

    /* Add Card */
    .lm-add-method-card {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        padding: 22px;
        margin-bottom: 24px;
        transition: all 0.15s ease;
    }
    .lm-add-method-card:hover {
        border-color: var(--lm-primary, #6366f1);
        background: #fff;
    }

    /* Switch */
    .lm-switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 22px;
        flex-shrink: 0;
    }
    .lm-switch input { opacity: 0; width: 0; height: 0; }
    .lm-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .2s;
        border-radius: 34px;
    }
    .lm-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .2s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    .lm-switch input:checked + .lm-slider {
        background-color: var(--lm-primary, #6366f1);
    }
    .lm-switch input:checked + .lm-slider:before {
        transform: translateX(18px);
    }

    /* Sticky Bottom Action Bar */
    .lm-sticky-actions {
        position: sticky;
        bottom: 16px;
        z-index: 100;
        margin-top: 24px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 14px 24px;
        box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .lm-save-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }
    .lm-save-status .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
    }

    @media (max-width: 1024px) {
        .lm-settings-shell { grid-template-columns: 1fr; }
        .lm-settings-nav {
            border-right: 0;
            border-bottom: 1px solid #e2e8f0;
            flex-direction: row;
            overflow-x: auto;
            padding: 12px;
            gap: 6px;
        }
        .lm-nav-item { white-space: nowrap; margin-bottom: 0; }
        .lm-nav-divider, .lm-nav-group-title { display: none; }
        .lm-methods-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content_body')
@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $methodUsage = collect($usage ?? []);
    $totalPayments = $methodUsage->sum('payments_count');
    $totalAmount = $methodUsage->sum('total_amount');
    $activeCount = $paymentMethods->where('is_active', 1)->count();
@endphp

<div class="lm-settings-wrapper">

    <!-- Top Header -->
    <div class="lm-settings-header">
        <div class="lm-settings-header-left">
            <h1><i class="fa fa-credit-card" style="color:var(--lm-primary); margin-right:8px;"></i> {{ $lmText('Payment Methods Configuration', 'ការកំណត់វិធីសាស្ត្របង់ប្រាក់') }}</h1>
            <p>{{ $lmText('Manage collection payment channels, banking options, display sorting, and status controls.', 'គ្រប់គ្រងច្រកបង់ប្រាក់ ជម្រើសធនាគារ លំដាប់បង្ហាញ និងស្ថានភាពដំណើរការ។') }}</p>
        </div>
        <div class="lm-settings-header-chips">
            <span class="lm-chip"><i class="fa fa-list-ol"></i> {{ number_format($paymentMethods->count()) }} {{ $lmText('Methods Total', 'វិធីសរុប') }}</span>
            <span class="lm-chip lm-chip-success"><i class="fa fa-check-circle"></i> {{ number_format($activeCount) }} {{ $lmText('Active Channels', 'កំពុងដំណើរការ') }}</span>
            <span class="lm-chip"><i class="fa fa-money"></i> {{ number_format($totalPayments) }} {{ $lmText('Payments Processed', 'ប្រតិបត្តិការបង់') }}</span>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="lm-settings-searchbar">
        <i class="fa fa-search search-icon"></i>
        <input type="search" id="paymentSettingsSearch" placeholder="{{ $lmText('Search payment methods by name or code (e.g. Cash, ABA, Wing, Bank Transfer)...', 'ស្វែងរកវិធីបង់ប្រាក់តាមឈ្មោះ ឬកូដ (ឧ. Cash, ABA, Wing, Bank Transfer)...') }}" autocomplete="off">
        <button type="button" class="clear-search" id="clearSearchBtn" aria-label="Clear search"><i class="fa fa-times-circle"></i></button>
    </div>

    @php
        $loanSessionStatus = session('status');
        $loanSessionStatusMessage = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'msg') : $loanSessionStatus;
        $loanSessionStatusSuccess = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'success', 1) : 1;
    @endphp
    @if($loanSessionStatusMessage)
        <div class="alert alert-{{ $loanSessionStatusSuccess ? 'success' : 'danger' }} alert-dismissible" style="border-radius:10px; margin-bottom:18px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-{{ $loanSessionStatusSuccess ? 'check-circle' : 'exclamation-circle' }}"></i> {{ $loanSessionStatusMessage }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius:10px; margin-bottom:18px;">
            <i class="fa fa-exclamation-triangle"></i> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('loan-management.settings.payment-methods.update') }}" id="paymentMethodsForm">
        @csrf

        <div class="lm-settings-shell">
            <!-- Shared Sidebar Navigation Suite -->
            @include('loanmanagement::settings.partials.settings_nav', [
                'activeTab' => 'tab-payment',
                'isSinglePage' => false,
                'activeCount' => $activeCount,
                'settings' => \Modules\LoanManagement\Services\BusinessSettingsService::get(),
            ])

            <!-- Main Content Area -->
            <main class="lm-settings-content">
                <div class="lm-section-head">
                    <div>
                        <h2>{{ $lmText('Custom Collection Payment Channels', 'ច្រកប្រមូលប្រាក់ និងវិធីទូទាត់') }}</h2>
                        <p>{{ $lmText('Configure display names, channel identifiers, and enable or disable methods for cashiers and field agents.', 'កំណត់ឈ្មោះបង្ហាញ កូដសម្គាល់ និងបើក/បិទវិធីបង់ប្រាក់សម្រាប់បេឡា និងភ្នាក់ងារ។') }}</p>
                    </div>
                </div>

                <!-- Payment Methods Grid -->
                <div class="lm-methods-grid" id="paymentMethodsGrid">
                    @forelse($paymentMethods as $index => $method)
                        @php
                            $usageRow = $methodUsage->get($method->name, ['payments_count' => 0, 'total_amount' => 0]);
                            $number = $index + 1;
                            $isActive = !empty($method->is_active);
                            $code = $method->code ?? '';
                        @endphp
                        <div class="lm-method-card {{ $isActive ? '' : 'inactive' }}" data-method-card>
                            <div class="lm-method-topline">
                                <div class="lm-method-header-title">
                                    <div class="lm-method-icon-circle">
                                        @if(stripos($method->name, 'aba') !== false)
                                            <i class="fa fa-bank"></i>
                                        @elseif(stripos($method->name, 'cash') !== false)
                                            <i class="fa fa-money"></i>
                                        @elseif(stripos($method->name, 'card') !== false)
                                            <i class="fa fa-credit-card"></i>
                                        @else
                                            <i class="fa fa-exchange"></i>
                                        @endif
                                    </div>
                                    <span>#{{ $number }} {{ $method->name }}</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:11.5px; font-weight:700; color:{{ $isActive ? '#059669' : '#94a3b8' }};">
                                        {{ $isActive ? $lmText('Active', 'ដំណើរការ') : $lmText('Inactive', 'បិទ') }}
                                    </span>
                                    <label class="lm-switch" title="Toggle active status">
                                        <input type="hidden" name="methods[{{ $method->id }}][is_active]" value="0">
                                        <input type="checkbox" name="methods[{{ $method->id }}][is_active]" value="1" {{ $isActive ? 'checked' : '' }} onchange="this.closest('.lm-method-card').classList.toggle('inactive', !this.checked)">
                                        <span class="lm-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div style="margin-top:10px;">
                                <label class="lm-field-label">{{ $lmText('Display Name', 'ឈ្មោះបង្ហាញលើប្រព័ន្ធ') }}</label>
                                <input type="text" name="methods[{{ $method->id }}][name]" class="lm-input" value="{{ $method->name }}" required maxlength="191" placeholder="e.g. ABA Bank / Cash">
                            </div>

                            <div class="lm-method-row-2">
                                <div>
                                    <label class="lm-field-label">{{ $lmText('Channel Code', 'កូដសម្គាល់') }}</label>
                                    <input type="text" name="methods[{{ $method->id }}][code]" class="lm-input" value="{{ $code }}" maxlength="60" placeholder="e.g. aba, cash">
                                </div>
                                <div>
                                    <label class="lm-field-label">{{ $lmText('Sort Order', 'លំដាប់') }}</label>
                                    <input type="number" name="methods[{{ $method->id }}][sort_order]" class="lm-input" value="{{ $method->sort_order ?? 0 }}" min="0" max="999">
                                </div>
                            </div>

                            <div class="lm-method-stats">
                                <span>
                                    <i class="fa fa-history" style="margin-right:4px;"></i>
                                    <strong>{{ number_format($usageRow['payments_count'] ?? 0) }}</strong> {{ $lmText('payments', 'ប្រតិបត្តិការ') }}
                                </span>
                                <span>
                                    <strong>${{ number_format((float) ($usageRow['total_amount'] ?? 0), 2) }}</strong> {{ $lmText('collected', 'ប្រមូលបាន') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="lm-col-full text-center" style="padding:40px 20px; color:#64748b;">
                            <i class="fa fa-credit-card" style="font-size:36px; opacity:0.3; margin-bottom:10px;"></i>
                            <p>{{ $lmText('No payment channels configured yet.', 'មិនទាន់មានវិធីបង់ប្រាក់នៅឡើយ។') }}</p>
                        </div>
                    @endforelse
                </div>

                <!-- Add New Payment Method Card -->
                <div class="lm-add-method-card">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                        <div style="width:32px; height:32px; border-radius:8px; background:var(--lm-primary-50, #eef2ff); color:var(--lm-primary, #6366f1); display:flex; align-items:center; justify-content:center; font-size:14px;">
                            <i class="fa fa-plus"></i>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:15px; font-weight:800; color:#0f172a;">{{ $lmText('Add New Payment Channel', 'បន្ថែមវិធីបង់ប្រាក់ថ្មី') }}</h3>
                            <p style="margin:2px 0 0; font-size:12px; color:#64748b;">{{ $lmText('Create an additional payment option for field agents or loan cashiers.', 'បង្កើតជម្រើសបង់ប្រាក់បន្ថែមសម្រាប់បុគ្គលិក ឬអតិថិជន។') }}</p>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:minmax(0, 1.5fr) minmax(0, 1fr) 100px; gap:14px;">
                        <div>
                            <label class="lm-field-label">{{ $lmText('Method Name', 'ឈ្មោះវិធីបង់ប្រាក់') }}</label>
                            <input type="text" name="new_method[name]" class="lm-input" placeholder="e.g. ACLEDA Unity / Wing / Bakong">
                        </div>
                        <div>
                            <label class="lm-field-label">{{ $lmText('System Code', 'កូដប្រព័ន្ធ') }}</label>
                            <input type="text" name="new_method[code]" class="lm-input" placeholder="e.g. acleda_bank">
                        </div>
                        <div>
                            <label class="lm-field-label">{{ $lmText('Sort Order', 'លំដាប់') }}</label>
                            <input type="number" name="new_method[sort_order]" class="lm-input" value="{{ $paymentMethods->count() + 1 }}" min="0">
                        </div>
                    </div>
                </div>

                <!-- Sticky Bottom Action Bar -->
                <div class="lm-sticky-actions">
                    <div class="lm-save-status">
                        <span class="dot"></span>
                        <span id="saveStatusText">{{ $lmText('Ready to save payment methods', 'រួចរាល់សម្រាប់ការរក្សាទុក') }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <a href="{{ route('loan-management.dashboard') }}" class="btn btn-default" style="border-radius:8px; font-weight:700; padding:8px 16px;">
                            {{ $lmText('Cancel', 'បោះបង់') }}
                        </a>
                        <button type="submit" class="btn btn-primary" id="savePaymentBtn" style="border-radius:8px; font-weight:800; padding:8px 22px;">
                            <i class="fa fa-save" style="margin-right:6px;"></i> {{ $lmText('Update Payment Settings', 'រក្សាទុកការកំណត់') }}
                        </button>
                    </div>
                </div>

                <!-- Legacy Data Reference -->
                @if($legacyRows->isNotEmpty())
                    <details style="margin-top:28px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px;">
                        <summary style="cursor:pointer; font-weight:700; color:#475569; font-size:13px;">
                            <i class="fa fa-database" style="margin-right:6px;"></i> {{ $lmText('Legacy Payment Method Rows (Audit Archive)', 'ទិន្នន័យប្រវត្តិវិធីបង់ប្រាក់ចាស់ (Audit Archive)') }}
                        </summary>
                        <div style="margin-top:12px; overflow-x:auto;">
                            <table class="table table-bordered table-striped" style="margin:0; font-size:12.5px; background:#fff;">
                                <thead>
                                    <tr>
                                        <th style="width:80px;">ID</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($legacyRows as $row)
                                        <tr>
                                            <td>{{ $row->id ?? '-' }}</td>
                                            <td>{{ $row->name ?? '-' }}</td>
                                            <td><span class="label label-{{ !empty($row->is_active) ? 'success' : 'default' }}">{{ !empty($row->is_active) ? 'Active' : 'Inactive' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif

            </main>
        </div>
    </form>
</div>
@endsection

@section('loan_js')
<script>
    (function () {
        var searchInput = document.getElementById('paymentSettingsSearch');
        var clearBtn = document.getElementById('clearSearchBtn');
        var methodCards = document.querySelectorAll('[data-method-card]');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var query = this.value.trim().toLowerCase();
                if (clearBtn) {
                    clearBtn.style.display = query.length > 0 ? 'block' : 'none';
                }

                methodCards.forEach(function (card) {
                    var inputs = Array.from(card.querySelectorAll('input')).map(function (i) { return i.value; }).join(' ');
                    var text = (card.textContent + ' ' + inputs).toLowerCase();
                    card.style.display = text.indexOf(query) !== -1 ? '' : 'none';
                });
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    searchInput.value = '';
                    clearBtn.style.display = 'none';
                    methodCards.forEach(function (card) { card.style.display = ''; });
                    searchInput.focus();
                });
            }
        }

        var form = document.getElementById('paymentMethodsForm');
        var saveBtn = document.getElementById('savePaymentBtn');
        if (form && saveBtn) {
            form.addEventListener('submit', function () {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right:6px;"></i> Saving...';
            });
        }
    })();
</script>
@endsection
