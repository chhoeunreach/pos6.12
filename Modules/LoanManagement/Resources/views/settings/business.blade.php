@extends('loanmanagement::layouts.app')
@section('title', 'Business Settings')

@section('loan_css')
<style>
    .ultimate-settings-page { color: #111827; }
    .ultimate-settings-title {
        margin: 0 0 24px;
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
    }
    .ultimate-settings-search {
        max-width: 1280px;
        margin: 0 auto 18px;
        display: grid;
        grid-template-columns: 48px minmax(0, 1fr) 42px;
        border: 1px solid #cfd8e3;
        background: #fff;
        box-shadow: 0 2px 5px rgba(15, 23, 42, .04);
    }
    .ultimate-settings-search span,
    .ultimate-settings-search button {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4b5563;
        font-size: 18px;
    }
    .ultimate-settings-search span { border-right: 1px solid #d8e0ea; }
    .ultimate-settings-search button {
        border: 0;
        border-left: 1px solid #d8e0ea;
        background: #fff;
        color: #6b7280;
    }
    .ultimate-settings-search input {
        height: 44px;
        border: 0;
        padding: 0 16px;
        outline: 0;
        color: #111827;
        font-size: 15px;
    }
    .ultimate-settings-card {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        min-height: 560px;
        border: 1px solid #dde3ea;
        border-radius: 4px;
        background: #fff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .08);
        overflow: hidden;
    }
    .ultimate-settings-tabs {
        padding: 24px 0;
        background: #fff;
        border-right: 1px solid #e5e7eb;
    }
    .ultimate-settings-tab {
        height: 58px;
        margin: 0 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ddd;
        border-bottom: 0;
        color: #4b5563;
        background: #fff;
        font-size: 18px;
        font-weight: 800;
    }
    .ultimate-settings-tab:last-child { border-bottom: 1px solid #ddd; }
    .ultimate-settings-tab.active {
        color: #111827;
        background: #f8fafc;
        box-shadow: inset 4px 0 0 var(--lm-primary, #2563eb);
    }
    .ultimate-settings-tab i {
        margin-left: 8px;
        color: #22c1dc;
        font-size: 15px;
    }
    .ultimate-settings-content { padding: 36px 42px; }
    .ultimate-section-title {
        margin: 0 0 18px;
        color: #28345f;
        font-size: 21px;
        font-weight: 500;
    }
    .ultimate-business-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(220px, 1fr));
        gap: 22px 34px;
    }
    .ultimate-field-full { grid-column: 1 / -1; }
    .ultimate-field label {
        display: block;
        margin-bottom: 8px;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }
    .ultimate-input {
        width: 100%;
        height: 42px;
        border: 1px solid #cfd8e3;
        border-radius: 0;
        padding: 8px 12px;
        color: #4b5563;
        background: #fff;
        box-shadow: none;
    }
    .ultimate-input:focus {
        border-color: var(--lm-primary, #2563eb);
        outline: 0;
        box-shadow: 0 0 0 3px rgba(var(--lm-primary-rgb, 37, 99, 235), .12);
    }
    .ultimate-input-group {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr);
        width: 100%;
    }
    .ultimate-input-icon {
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #cfd8e3;
        border-right: 0;
        background: #f8fafc;
        color: #4b5563;
        font-size: 16px;
    }
    .ultimate-input-group .ultimate-input {
        min-width: 0;
    }
    .ultimate-input-file {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 154px;
    }
    .ultimate-input-file input[type="text"] {
        background: #fff;
    }
    .ultimate-file-button {
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px solid var(--lm-primary, #2563eb);
        background: var(--lm-primary, #2563eb);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }
    .ultimate-file-button input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .ultimate-info {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        margin-left: 6px;
        border-radius: 50%;
        background: #22c1dc;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
    }
    textarea.ultimate-input {
        min-height: 118px;
        resize: vertical;
        line-height: 1.5;
    }
    .ultimate-file-row {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 14px;
        align-items: center;
    }
    .ultimate-logo-box {
        width: 96px;
        height: 96px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #cfd8e3;
        background: #f8fafc;
        color: var(--lm-primary, #2563eb);
        font-size: 30px;
        overflow: hidden;
    }
    .ultimate-logo-box img,
    .ultimate-preview-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .ultimate-help {
        margin-top: 7px;
        color: #6b7280;
        font-size: 12px;
    }
    .ultimate-background-preview {
        min-height: 190px;
        border: 1px solid #cfd8e3;
        background:
            linear-gradient(135deg, rgba(var(--lm-primary-rgb, 37, 99, 235), .80), rgba(15, 23, 42, .82)),
            #1f2937;
        background-size: cover;
        background-position: center;
        position: relative;
        overflow: hidden;
    }
    .ultimate-background-preview.has-image {
        background-image:
            linear-gradient(135deg, rgba(15, 23, 42, .68), rgba(var(--lm-primary-rgb, 37, 99, 235), .34)),
            var(--lm-login-background);
    }
    .ultimate-background-preview div {
        position: absolute;
        left: 18px;
        right: 18px;
        bottom: 16px;
        color: #fff;
    }
    .ultimate-background-preview strong {
        display: block;
        font-size: 20px;
        font-weight: 800;
    }
    .ultimate-background-preview span {
        display: block;
        margin-top: 5px;
        color: rgba(255,255,255,.82);
        font-size: 12px;
    }
    .ultimate-color-row {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr);
        gap: 10px;
    }
    .ultimate-color-row input[type="color"] {
        width: 58px;
        height: 42px;
        padding: 4px;
        border: 1px solid #cfd8e3;
        background: #fff;
    }
    .ultimate-swatches {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }
    .ultimate-swatch {
        width: 34px;
        height: 34px;
        border: 2px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 0 1px #cfd8e3;
        cursor: pointer;
    }
    .ultimate-divider {
        margin: 30px 0;
        border-top: 1px solid #6b7280;
    }
    .ultimate-preview-grid {
        display: grid;
        grid-template-columns: 330px minmax(0, 1fr);
        gap: 18px;
        align-items: stretch;
    }
    .ultimate-preview-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border: 1px solid #d8e0ea;
        background: #f8fafc;
    }
    .ultimate-preview-icon {
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--lm-primary, #2563eb);
        color: #fff;
        overflow: hidden;
    }
    .ultimate-preview-brand strong {
        display: block;
        color: #111827;
        font-size: 16px;
    }
    .ultimate-preview-brand span {
        display: block;
        margin-top: 3px;
        color: #6b7280;
        font-size: 12px;
    }
    .ultimate-login-mini {
        min-height: 210px;
        padding: 18px;
        border: 1px solid #d8e0ea;
        background:
            linear-gradient(135deg, rgba(15, 23, 42, .76), rgba(var(--lm-primary-rgb, 37, 99, 235), .38)),
            #172033;
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: flex-end;
    }
    .ultimate-login-mini.has-image {
        background-image:
            linear-gradient(135deg, rgba(15, 23, 42, .76), rgba(var(--lm-primary-rgb, 37, 99, 235), .38)),
            var(--lm-login-background);
    }
    .ultimate-login-box {
        width: 100%;
        padding: 14px;
        background: rgba(255,255,255,.94);
        box-shadow: 0 12px 28px rgba(15, 23, 42, .15);
    }
    .ultimate-login-box strong {
        display: block;
        color: #111827;
        font-size: 14px;
    }
    .ultimate-login-box span {
        display: block;
        margin-top: 4px;
        color: #6b7280;
        font-size: 12px;
    }
    .ultimate-login-button {
        height: 8px;
        margin-top: 12px;
        border-radius: 999px;
        background: var(--lm-primary, #2563eb);
    }
    .ultimate-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 28px;
        padding-top: 18px;
        border-top: 1px solid #e5e7eb;
    }
    @media (max-width: 1199px) {
        .ultimate-preview-grid { grid-template-columns: 1fr; }
        .ultimate-business-grid { grid-template-columns: repeat(2, minmax(240px, 1fr)); }
    }
    @media (max-width: 991px) {
        .ultimate-settings-card { grid-template-columns: 1fr; }
        .ultimate-settings-tabs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0;
            padding: 16px;
            border-right: 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .ultimate-settings-tab {
            margin: 0;
            border: 1px solid #ddd;
        }
        .ultimate-settings-content { padding: 24px 18px; }
        .ultimate-business-grid { grid-template-columns: 1fr; }
        .ultimate-file-row { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content_body')
@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $themePresets = ['#6366f1', '#2563eb', '#0891b2', '#059669', '#dc2626', '#7c3aed', '#111827'];
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::logoUrl();
    $loginBackgroundUrl = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl();
    $monthOptions = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    $dateFormatOptions = [
        'd-m-Y' => 'dd-mm-yyyy',
        'm-d-Y' => 'mm-dd-yyyy',
        'Y-m-d' => 'yyyy-mm-dd',
        'd/m/Y' => 'dd/mm/yyyy',
        'm/d/Y' => 'mm/dd/yyyy',
    ];
    $currencySymbolMap = ['USD' => '$', 'KHR' => '៛', 'THB' => '฿'];
    $savedCurrencyCode = old('currency_code', $settings['currency_code']);
    $savedCurrencySymbol = old('currency_symbol', $settings['currency_symbol'] ?: ($currencySymbolMap[$savedCurrencyCode] ?? $savedCurrencyCode));
    $settingsTabs = [
        'Business' => route('loan-management.settings.business'),
        'CMS' => route('loan-management.settings.cms'),
        'Payment' => route('loan-management.settings.payment-methods'),
    ];
@endphp

<div class="ultimate-settings-page" @if($loginBackgroundUrl) style="--lm-login-background: url('{{ $loginBackgroundUrl }}');" @endif>
    <h1 class="ultimate-settings-title">Business Settings</h1>

    <div class="ultimate-settings-search">
        <span><i class="fa fa-search"></i></span>
        <input type="search" id="businessSettingsSearch" placeholder="Search">
        <button type="button" aria-label="Search options"><i class="fa fa-caret-down"></i></button>
    </div>

    @php
        $loanSessionStatus = session('status');
        $loanSessionStatusMessage = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'msg') : $loanSessionStatus;
        $loanSessionStatusSuccess = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'success', 1) : 1;
    @endphp
    @if($loanSessionStatusMessage)
        <div class="alert alert-{{ $loanSessionStatusSuccess ? 'success' : 'danger' }}">
            {{ $loanSessionStatusMessage }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('loan-management.settings.business.update') }}" enctype="multipart/form-data" id="businessSettingsForm">
        @csrf
        <div class="ultimate-settings-card">
            <aside class="ultimate-settings-tabs">
                @foreach($settingsTabs as $tab => $route)
                    <a href="{{ $route }}" class="ultimate-settings-tab {{ $tab === 'Business' ? 'active' : '' }}">
                        {{ $tab }}
                    </a>
                @endforeach
            </aside>

            <main class="ultimate-settings-content">
                <h2 class="ultimate-section-title">{{ $lmText('Business:', 'អាជីវកម្ម៖') }}</h2>

                <div class="ultimate-business-grid">
                    <div class="ultimate-field" data-business-field>
                        <label for="businessNameInput">{{ $lmText('Business Name', 'ឈ្មោះអាជីវកម្ម') }}:*</label>
                        <input type="text" id="businessNameInput" name="business_name" class="ultimate-input"
                               value="{{ old('business_name', $settings['business_name']) }}" required maxlength="80">
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="startDateInput">{{ $lmText('Start Date', 'ថ្ងៃចាប់ផ្តើម') }}:</label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-calendar"></i></span>
                            <input type="date" id="startDateInput" name="start_date" class="ultimate-input"
                                   value="{{ old('start_date', $settings['start_date']) }}">
                        </div>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="defaultProfitInput">{{ $lmText('Default profit percent', 'ភាគរយចំណេញលំនាំដើម') }}:* <span class="ultimate-info" title="Used as the default profit percent for new items or installment calculations.">i</span></label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-plus-circle"></i></span>
                            <input type="number" id="defaultProfitInput" name="default_profit_percent" class="ultimate-input"
                                   value="{{ old('default_profit_percent', $settings['default_profit_percent']) }}" required min="0" max="1000" step="0.01">
                        </div>
                    </div>

                    <div class="ultimate-field" data-business-field>
                        <label for="currencyCodeInput">{{ $lmText('Currency', 'រូបិយប័ណ្ណ') }}:</label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-money"></i></span>
                            <select id="currencyCodeInput" name="currency_code" class="ultimate-input" required>
                                @foreach($currencies as $currency)
                                    @php
                                        $currencyCode = strtoupper((string) ($currency->code ?? ''));
                                        $currencyName = (string) ($currency->name ?? $currencyCode);
                                    @endphp
                                    @if($currencyCode !== '')
                                        <option value="{{ $currencyCode }}" data-symbol="{{ $currencySymbolMap[$currencyCode] ?? $currencyCode }}" {{ $savedCurrencyCode === $currencyCode ? 'selected' : '' }}>
                                            {{ $currencyName }}({{ $currencyCode }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="currencySymbolPlacementInput">{{ $lmText('Currency Symbol Placement', 'ទីតាំងនិមិត្តសញ្ញារូបិយប័ណ្ណ') }}:</label>
                        <select id="currencySymbolPlacementInput" name="currency_symbol_placement" class="ultimate-input" required>
                            <option value="before" {{ old('currency_symbol_placement', $settings['currency_symbol_placement']) === 'before' ? 'selected' : '' }}>{{ $lmText('Before amount', 'មុនចំនួនទឹកប្រាក់') }}</option>
                            <option value="after" {{ old('currency_symbol_placement', $settings['currency_symbol_placement']) === 'after' ? 'selected' : '' }}>{{ $lmText('After amount', 'ក្រោយចំនួនទឹកប្រាក់') }}</option>
                        </select>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="timeZoneInput">{{ $lmText('Time zone', 'តំបន់ពេលវេលា') }}:</label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-clock-o"></i></span>
                            <select id="timeZoneInput" name="time_zone" class="ultimate-input" required>
                                @foreach($timezones as $timezone)
                                    <option value="{{ $timezone }}" {{ old('time_zone', $settings['time_zone']) === $timezone ? 'selected' : '' }}>{{ $timezone }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="ultimate-field" data-business-field>
                        <label for="logoInput">{{ $lmText('Upload Logo', 'បង្ហោះរូបសញ្ញា') }}:</label>
                        <div class="ultimate-input-file">
                            <input type="text" class="ultimate-input" id="logoFileName" value="{{ $businessLogoUrl ? basename((string) $settings['logo_path']) : '' }}" readonly>
                            <label class="ultimate-file-button" for="logoInput">
                                <i class="fa fa-folder-open"></i> {{ $lmText('Browse..', 'រើសឯកសារ..') }}
                                <input type="file" id="logoInput" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
                            </label>
                        </div>
                        <div class="ultimate-help">{{ $lmText('Previous logo (if exists) will be replaced.', 'រូបសញ្ញាចាស់នឹងត្រូវបានជំនួស។') }}</div>
                        @if($businessLogoUrl)
                            <label class="ultimate-help" style="font-weight:600;color:#374151;">
                                <input type="checkbox" name="remove_logo" value="1">
                                {{ $lmText('Remove current logo', 'លុបរូបសញ្ញាបច្ចុប្បន្ន') }}
                            </label>
                        @endif
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="fyStartMonthInput">{{ $lmText('Financial year start month', 'ខែចាប់ផ្តើមឆ្នាំហិរញ្ញវត្ថុ') }}: <span class="ultimate-info" title="Used for financial year date shortcuts and reports.">i</span></label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-calendar"></i></span>
                            <select id="fyStartMonthInput" name="fy_start_month" class="ultimate-input" required>
                                @foreach($monthOptions as $monthNumber => $monthName)
                                    <option value="{{ $monthNumber }}" {{ (int) old('fy_start_month', $settings['fy_start_month']) === $monthNumber ? 'selected' : '' }}>{{ $monthName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="stockAccountingMethodInput">{{ $lmText('Stock Accounting Method', 'វិធីសាស្ត្រគណនាស្តុក') }}:* <span class="ultimate-info" title="Used when product stock costing is enabled.">i</span></label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-calculator"></i></span>
                            <select id="stockAccountingMethodInput" name="stock_accounting_method" class="ultimate-input" required>
                                <option value="fifo" {{ old('stock_accounting_method', $settings['stock_accounting_method']) === 'fifo' ? 'selected' : '' }}>FIFO (First In First Out)</option>
                                <option value="lifo" {{ old('stock_accounting_method', $settings['stock_accounting_method']) === 'lifo' ? 'selected' : '' }}>LIFO (Last In First Out)</option>
                                <option value="avco" {{ old('stock_accounting_method', $settings['stock_accounting_method']) === 'avco' ? 'selected' : '' }}>AVCO (Average Cost)</option>
                            </select>
                        </div>
                    </div>

                    <div class="ultimate-field" data-business-field>
                        <label for="transactionEditDaysInput">{{ $lmText('Transaction Edit Days', 'ចំនួនថ្ងៃអាចកែប្រតិបត្តិការ') }}:* <span class="ultimate-info" title="Transactions older than this can be protected from editing.">i</span></label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-pencil-square-o"></i></span>
                            <input type="number" id="transactionEditDaysInput" name="transaction_edit_days" class="ultimate-input"
                                   value="{{ old('transaction_edit_days', $settings['transaction_edit_days']) }}" required min="0" max="3650" step="1">
                        </div>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="dateFormatInput">{{ $lmText('Date Format', 'ទម្រង់កាលបរិច្ឆេទ') }}:*</label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-calendar"></i></span>
                            <select id="dateFormatInput" name="date_format" class="ultimate-input" required>
                                @foreach($dateFormatOptions as $formatValue => $formatLabel)
                                    <option value="{{ $formatValue }}" {{ old('date_format', $settings['date_format']) === $formatValue ? 'selected' : '' }}>{{ $formatLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="timeFormatInput">{{ $lmText('Time Format', 'ទម្រង់ម៉ោង') }}:*</label>
                        <div class="ultimate-input-group">
                            <span class="ultimate-input-icon"><i class="fa fa-clock-o"></i></span>
                            <select id="timeFormatInput" name="time_format" class="ultimate-input" required>
                                <option value="24" {{ (string) old('time_format', $settings['time_format']) === '24' ? 'selected' : '' }}>24 Hour</option>
                                <option value="12" {{ (string) old('time_format', $settings['time_format']) === '12' ? 'selected' : '' }}>12 Hour</option>
                            </select>
                        </div>
                    </div>

                    <div class="ultimate-field" data-business-field>
                        <label for="currencyPrecisionInput">{{ $lmText('Currency precision', 'ចំនួនខ្ទង់ទសភាគរូបិយប័ណ្ណ') }}:* <span class="ultimate-info" title="Controls decimal places shown for money values.">i</span></label>
                        <select id="currencyPrecisionInput" name="currency_precision" class="ultimate-input" required>
                            @for($precision = 0; $precision <= 4; $precision++)
                                <option value="{{ $precision }}" {{ (int) old('currency_precision', $settings['currency_precision']) === $precision ? 'selected' : '' }}>{{ $precision }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="quantityPrecisionInput">{{ $lmText('Quantity precision', 'ចំនួនខ្ទង់ទសភាគបរិមាណ') }}:* <span class="ultimate-info" title="Controls decimal places shown for quantities.">i</span></label>
                        <select id="quantityPrecisionInput" name="quantity_precision" class="ultimate-input" required>
                            @for($precision = 0; $precision <= 4; $precision++)
                                <option value="{{ $precision }}" {{ (int) old('quantity_precision', $settings['quantity_precision']) === $precision ? 'selected' : '' }}>{{ $precision }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="currencySymbolInput">{{ $lmText('Currency Symbol', 'និមិត្តសញ្ញារូបិយប័ណ្ណ') }}:</label>
                        <input type="text" id="currencySymbolInput" name="currency_symbol" class="ultimate-input"
                               value="{{ $savedCurrencySymbol }}" maxlength="10">
                    </div>

                    <div class="ultimate-field" data-business-field>
                        <label for="systemNameInput">{{ $lmText('System Name', 'ឈ្មោះប្រព័ន្ធ') }}:*</label>
                        <input type="text" id="systemNameInput" name="system_name" class="ultimate-input"
                               value="{{ old('system_name', $settings['system_name']) }}" required maxlength="80">
                    </div>
                    <div class="ultimate-field" data-business-field>
                        <label for="systemSubtitleInput">{{ $lmText('System Subtitle', 'អត្ថបទរងប្រព័ន្ធ') }}</label>
                        <input type="text" id="systemSubtitleInput" name="system_subtitle" class="ultimate-input"
                               value="{{ old('system_subtitle', $settings['system_subtitle']) }}" maxlength="120">
                    </div>
                </div>

                <div class="ultimate-divider"></div>

                <h2 class="ultimate-section-title">{{ $lmText('Public CMS & Portal Access:', 'CMS សាធារណៈ និងការចូលប្រើប្រាស់៖') }}</h2>
                <div class="ultimate-business-grid">
                    <div class="ultimate-field ultimate-field-full" data-business-field>
                        <label for="cmsEnabledInput">{{ $lmText('Homepage CMS Module', 'ម៉ូឌុល CMS ទំព័រដើម') }}</label>
                        <input type="hidden" name="cms_enabled" value="0">
                        <label class="ultimate-help" style="display:flex;align-items:center;gap:10px;font-weight:700;color:#111827;">
                            <input type="checkbox" id="cmsEnabledInput" name="cms_enabled" value="1" {{ old('cms_enabled', $settings['cms_enabled'] ?? true) ? 'checked' : '' }}>
                            {{ $lmText('Enable public homepage CMS', 'បើក CMS ទំព័រដើមសាធារណៈ') }}
                        </label>
                        <div class="ultimate-help">
                            {{ $lmText('When disabled, visitors opening the homepage are sent to the admin login page. The CMS editor remains available for admins.', 'ពេលបិទ អ្នកចូលទំព័រដើមនឹងទៅទំព័រចូលប្រើអ្នកគ្រប់គ្រង។ អ្នកគ្រប់គ្រងនៅតែអាចកែ CMS បាន។') }}
                        </div>
                    </div>

                    <div class="ultimate-field ultimate-field-full" data-business-field>
                        <label>{{ $lmText('Customer Portal & Login Control', 'ការគ្រប់គ្រងច្រកចូលអតិថិជន') }}</label>
                        <input type="hidden" name="customer_login_enabled" value="0">
                        <label class="ultimate-help" style="display:flex;align-items:center;gap:10px;font-weight:700;color:#111827;margin-bottom:6px;">
                            <input type="checkbox" id="customerLoginEnabledInput" name="customer_login_enabled" value="1" {{ old('customer_login_enabled', $settings['customer_login_enabled'] ?? true) ? 'checked' : '' }}>
                            {{ $lmText('Enable Customer Login & Registration Portal', 'បើកដំណើរការទំព័រចូលប្រើ និងចុះឈ្មោះរបស់អតិថិជន') }}
                        </label>
                        <div class="ultimate-help">
                            {{ $lmText('When enabled, customers can sign in to view installments, make requests, and check payment schedules.', 'នៅពេលបើក អតិថិជនអាចចូលមើលតារាងបង់ប្រាក់ ធ្វើសំណើរំលស់ និងពិនិត្យទិន្នន័យបាន។') }}
                        </div>
                    </div>

                    <div class="ultimate-field ultimate-field-full" data-business-field>
                        <label>{{ $lmText('Demo Logins Visibility', 'ការបង្ហាញគណនី Demo លើទំព័រចូលប្រើ') }}</label>
                        
                        <input type="hidden" name="demo_customer_login_enabled" value="0">
                        <label class="ultimate-help" style="display:flex;align-items:center;gap:10px;font-weight:700;color:#111827;margin-bottom:8px;">
                            <input type="checkbox" id="demoCustomerLoginInput" name="demo_customer_login_enabled" value="1" {{ old('demo_customer_login_enabled', $settings['demo_customer_login_enabled'] ?? true) ? 'checked' : '' }}>
                            {{ $lmText('Show Demo Customer Login Widget (1-Click Auto-Fill)', 'បង្ហាញប្រអប់ Demo គណនីអតិថិជន (1-Click Auto-Fill) លើទំព័រចូលប្រើអតិថិជន') }}
                        </label>

                        <input type="hidden" name="demo_admin_login_enabled" value="0">
                        <label class="ultimate-help" style="display:flex;align-items:center;gap:10px;font-weight:700;color:#111827;">
                            <input type="checkbox" id="demoAdminLoginInput" name="demo_admin_login_enabled" value="1" {{ old('demo_admin_login_enabled', $settings['demo_admin_login_enabled'] ?? false) ? 'checked' : '' }}>
                            {{ $lmText('Show Demo Admin Credentials on Admin Login', 'បង្ហាញព័ត៌មាន Demo អ្នកគ្រប់គ្រងនៅលើទំព័រចូលប្រើ Admin') }}
                        </label>
                        <div class="ultimate-help" style="margin-top:6px;">
                            {{ $lmText('Disable these options in live production to protect system credentials.', 'បិទជម្រើសទាំងនេះនៅពេលដំណើរការផ្លូវការ (Production) ដើម្បីសុវត្ថិភាពប្រព័ន្ធ។') }}
                        </div>
                    </div>
                </div>

                <div class="ultimate-divider"></div>

                <h2 class="ultimate-section-title">{{ $lmText('Logo and login page:', 'រូបសញ្ញា និងទំព័រចូលប្រើ៖') }}</h2>
                <div class="ultimate-business-grid">
                    <div class="ultimate-field" data-business-field>
                        <label>{{ $lmText('Logo preview', 'មើលរូបសញ្ញា') }}</label>
                        <div class="ultimate-file-row">
                            <div class="ultimate-logo-box" id="logoPreviewBox">
                                @if($businessLogoUrl)
                                    <img src="{{ $businessLogoUrl }}" alt="{{ $settings['business_name'] }}" id="logoPreviewImage">
                                @else
                                    <i class="fa fa-building-o" id="logoPreviewIcon"></i>
                                @endif
                            </div>
                            <div>
                                <div class="ultimate-help">{{ $lmText('Use the Upload Logo field in the Business section above. PNG, JPG, WEBP, or GIF. Maximum 2 MB.', 'ប្រើវាលបង្ហោះរូបសញ្ញាក្នុងផ្នែកអាជីវកម្មខាងលើ។ PNG, JPG, WEBP ឬ GIF។ ទំហំអតិបរមា 2 MB។') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="ultimate-field" data-business-field>
                        <label for="themeColorInput">{{ $lmText('Theme Color', 'ពណ៌រចនាប័ទ្ម') }}</label>
                        <div class="ultimate-color-row">
                            <input type="color" id="themeColorPicker" value="{{ old('theme_color', $settings['theme_color']) }}">
                            <input type="text" id="themeColorInput" name="theme_color" class="ultimate-input"
                                   value="{{ old('theme_color', $settings['theme_color']) }}" required maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                        <div class="ultimate-swatches" aria-label="Theme presets">
                            @foreach($themePresets as $preset)
                                <button type="button" class="ultimate-swatch" data-color="{{ $preset }}" style="background: {{ $preset }};" aria-label="{{ $preset }}"></button>
                            @endforeach
                        </div>
                    </div>

                    <div class="ultimate-field ultimate-field-full" data-business-field>
                        <label for="loginBackgroundInput">{{ $lmText('Login Background Photo', 'រូបភាពផ្ទៃខាងក្រោយចូលប្រើ') }}</label>
                        <div class="ultimate-background-preview {{ $loginBackgroundUrl ? 'has-image' : '' }}" id="loginBackgroundPreview">
                            <div>
                                <strong id="backgroundPreviewBusinessName">{{ old('business_name', $settings['business_name']) }}</strong>
                                <span>{{ $lmText('Use a professional business photo for the sign-in screen.', 'ប្រើរូបថតអាជីវកម្មដែលមានលក្ខណៈវិជ្ជាជីវៈសម្រាប់ទំព័រចូលប្រើ។') }}</span>
                            </div>
                        </div>
                        <input type="file" id="loginBackgroundInput" name="login_background" class="ultimate-input" style="margin-top:10px;" accept="image/png,image/jpeg,image/webp">
                        <div class="ultimate-help">{{ $lmText('Landscape JPG, PNG, or WEBP. Maximum 5 MB.', 'រូបភាពផ្តេក JPG, PNG ឬ WEBP។ ទំហំអតិបរមា 5 MB។') }}</div>
                        @if($loginBackgroundUrl)
                            <label class="ultimate-help" style="font-weight:600;color:#374151;">
                                <input type="checkbox" name="remove_login_background" value="1">
                                {{ $lmText('Remove current background', 'លុបផ្ទៃខាងក្រោយបច្ចុប្បន្ន') }}
                            </label>
                        @endif
                    </div>
                </div>

                <div class="ultimate-divider"></div>

                <h2 class="ultimate-section-title">{{ $lmText('Customer invoice message:', 'សារវិក្កយបត្រអតិថិជន៖') }}</h2>
                <div class="ultimate-business-grid">
                    <div class="ultimate-field ultimate-field-full" data-business-field>
                        <label for="invoiceMessageTemplateInput">{{ $lmText('Invoice Message Template', 'គំរូសារវិក្កយបត្រ') }}:*</label>
                        <textarea id="invoiceMessageTemplateInput" name="invoice_message_template" class="ultimate-input" required maxlength="2000">{{ old('invoice_message_template', $settings['invoice_message_template']) }}</textarea>
                        <div class="ultimate-help">
                            {{ $lmText('Available placeholders: {Customer Name}, {Business Name}', 'អាចប្រើបាន៖ {Customer Name}, {Business Name}') }}
                        </div>
                        <div class="ultimate-help" style="white-space:pre-wrap;border:1px solid #d8e0ea;background:#f8fafc;padding:10px;color:#374151;" id="invoiceMessageTemplatePreview"></div>
                    </div>
                </div>

                <div class="ultimate-divider"></div>

                <h2 class="ultimate-section-title">{{ $lmText('Live preview:', 'មើលគំរូ៖') }}</h2>
                <div class="ultimate-preview-grid">
                    <div class="ultimate-preview-brand">
                        <div class="ultimate-preview-icon" id="sidebarLogoPreview">
                            @if($businessLogoUrl)
                                <img src="{{ $businessLogoUrl }}" alt="{{ $settings['business_name'] }}">
                            @else
                                <i class="fa fa-folder-open"></i>
                            @endif
                        </div>
                        <div>
                            <strong id="previewBusinessName">{{ old('business_name', $settings['business_name']) }}</strong>
                            <span id="previewSystemName">{{ old('system_name', $settings['system_name']) }}</span>
                        </div>
                    </div>
                    <div class="ultimate-login-mini {{ $loginBackgroundUrl ? 'has-image' : '' }}" id="loginMiniPreview">
                        <div class="ultimate-login-box">
                            <strong id="previewLoginTitle">{{ old('system_name', $settings['system_name']) }}</strong>
                            <span id="previewLoginSubtitle">{{ old('system_subtitle', $settings['system_subtitle']) }}</span>
                            <div class="ultimate-login-button"></div>
                        </div>
                    </div>
                </div>

                <div class="ultimate-actions">
                    <a href="{{ route('loan-management.dashboard') }}" class="btn btn-default">{{ $lmText('Cancel', 'បោះបង់') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> {{ $lmText('Update Settings', 'ធ្វើបច្ចុប្បន្នភាពការកំណត់') }}
                    </button>
                </div>
            </main>
        </div>
    </form>
</div>
@endsection

@section('loan_js')
<script>
    (function () {
        var savedBusinessName = @json($settings['business_name']);
        var savedSystemName = @json($settings['system_name']);
        var savedSystemSubtitle = @json($settings['system_subtitle']);
        var businessInput = document.getElementById('businessNameInput');
        var systemInput = document.getElementById('systemNameInput');
        var subtitleInput = document.getElementById('systemSubtitleInput');
        var invoiceTemplateInput = document.getElementById('invoiceMessageTemplateInput');
        var invoiceTemplatePreview = document.getElementById('invoiceMessageTemplatePreview');
        var colorInput = document.getElementById('themeColorInput');
        var colorPicker = document.getElementById('themeColorPicker');
        var logoInput = document.getElementById('logoInput');
        var logoFileName = document.getElementById('logoFileName');
        var currencyCodeInput = document.getElementById('currencyCodeInput');
        var currencySymbolInput = document.getElementById('currencySymbolInput');
        var loginBackgroundInput = document.getElementById('loginBackgroundInput');
        var loginBackgroundPreview = document.getElementById('loginBackgroundPreview');
        var loginMiniPreview = document.getElementById('loginMiniPreview');
        var logoPreviewBox = document.getElementById('logoPreviewBox');
        var sidebarLogoPreview = document.getElementById('sidebarLogoPreview');
        var previewBusiness = document.getElementById('previewBusinessName');
        var previewSystem = document.getElementById('previewSystemName');
        var previewLoginTitle = document.getElementById('previewLoginTitle');
        var previewLoginSubtitle = document.getElementById('previewLoginSubtitle');
        var backgroundPreviewBusinessName = document.getElementById('backgroundPreviewBusinessName');
        var search = document.getElementById('businessSettingsSearch');
        var searchableFields = document.querySelectorAll('[data-business-field]');

        function hexToRgb(color) {
            var normalized = color.replace('#', '');
            return [
                parseInt(normalized.substring(0, 2), 16),
                parseInt(normalized.substring(2, 4), 16),
                parseInt(normalized.substring(4, 6), 16)
            ];
        }

        function rgbToHex(r, g, b) {
            return '#' + [r, g, b].map(function (value) {
                var hex = Math.max(0, Math.min(255, Math.round(value))).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        }

        function mixWithWhite(rgb, ratio) {
            return rgbToHex(
                rgb[0] * (1 - ratio) + 255 * ratio,
                rgb[1] * (1 - ratio) + 255 * ratio,
                rgb[2] * (1 - ratio) + 255 * ratio
            );
        }

        function setPreviewColor(color) {
            if (!/^#[0-9A-Fa-f]{6}$/.test(color || '')) {
                return;
            }
            var rgb = hexToRgb(color);
            document.documentElement.style.setProperty('--lm-primary', color);
            document.documentElement.style.setProperty('--lm-primary-dark', rgbToHex(rgb[0] * .82, rgb[1] * .82, rgb[2] * .82));
            document.documentElement.style.setProperty('--lm-primary-light', mixWithWhite(rgb, .25));
            document.documentElement.style.setProperty('--lm-primary-50', mixWithWhite(rgb, .92));
            document.documentElement.style.setProperty('--lm-primary-100', mixWithWhite(rgb, .84));
            document.documentElement.style.setProperty('--lm-primary-200', mixWithWhite(rgb, .70));
            document.documentElement.style.setProperty('--lm-primary-rgb', rgb.join(', '));
            document.documentElement.style.setProperty('--lm-sidebar-active', color);
        }

        function syncPreview() {
            previewBusiness.textContent = businessInput.value || savedBusinessName;
            previewSystem.textContent = systemInput.value || savedSystemName;
            previewLoginTitle.textContent = systemInput.value || savedSystemName;
            previewLoginSubtitle.textContent = subtitleInput.value || savedSystemSubtitle;
            backgroundPreviewBusinessName.textContent = businessInput.value || savedBusinessName;
            if (invoiceTemplatePreview && invoiceTemplateInput) {
                invoiceTemplatePreview.textContent = (invoiceTemplateInput.value || '')
                    .split('{Customer Name}').join('Customer Name')
                    .split('{Business Name}').join(businessInput.value || savedBusinessName);
            }
            setPreviewColor(colorInput.value);
        }

        [businessInput, systemInput, subtitleInput, colorInput, invoiceTemplateInput].forEach(function (input) {
            if (!input) {
                return;
            }
            input.addEventListener('input', syncPreview);
        });

        if (currencyCodeInput && currencySymbolInput) {
            currencyCodeInput.addEventListener('change', function () {
                var selected = currencyCodeInput.options[currencyCodeInput.selectedIndex];
                if (selected && selected.getAttribute('data-symbol')) {
                    currencySymbolInput.value = selected.getAttribute('data-symbol');
                }
            });
        }

        if (colorPicker) {
            colorPicker.addEventListener('input', function () {
                colorInput.value = colorPicker.value;
                syncPreview();
            });
        }

        if (colorInput) {
            colorInput.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(colorInput.value) && colorPicker) {
                    colorPicker.value = colorInput.value;
                }
            });
        }

        document.querySelectorAll('.ultimate-swatch').forEach(function (button) {
            button.addEventListener('click', function () {
                colorInput.value = button.getAttribute('data-color');
                colorPicker.value = colorInput.value;
                syncPreview();
            });
        });

        if (logoInput) {
            logoInput.addEventListener('change', function () {
                var file = logoInput.files && logoInput.files[0];
                if (!file || !file.type.match(/^image\//)) {
                    return;
                }
                if (logoFileName) {
                    logoFileName.value = file.name;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    var image = '<img src="' + event.target.result + '" alt="">';
                    logoPreviewBox.innerHTML = image;
                    sidebarLogoPreview.innerHTML = image;
                };
                reader.readAsDataURL(file);
            });
        }

        if (loginBackgroundInput) {
            loginBackgroundInput.addEventListener('change', function () {
                var file = loginBackgroundInput.files && loginBackgroundInput.files[0];
                if (!file || !file.type.match(/^image\//)) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    document.documentElement.style.setProperty('--lm-login-background', 'url("' + event.target.result + '")');
                    loginBackgroundPreview.classList.add('has-image');
                    loginMiniPreview.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            });
        }

        if (search) {
            search.addEventListener('input', function () {
                var needle = search.value.toLowerCase();
                searchableFields.forEach(function (field) {
                    field.style.display = field.textContent.toLowerCase().indexOf(needle) === -1 ? 'none' : '';
                });
            });
        }

        syncPreview();
    })();
</script>
@endsection
