@extends('loanmanagement::layouts.app')
@section('title', 'Payment Settings')

@section('loan_css')
<style>
    .ultimate-settings-page {
        color: #111827;
    }
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
    .ultimate-settings-search span {
        display: flex;
        align-items: center;
        justify-content: center;
        border-right: 1px solid #d8e0ea;
        color: #4b5563;
        font-size: 18px;
    }
    .ultimate-settings-search input {
        height: 44px;
        border: 0;
        padding: 0 16px;
        outline: 0;
        color: #111827;
        font-size: 15px;
    }
    .ultimate-settings-search button {
        border: 0;
        border-left: 1px solid #d8e0ea;
        background: #fff;
        color: #6b7280;
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
    .ultimate-settings-tab:last-child {
        border-bottom: 1px solid #ddd;
    }
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
    .ultimate-settings-content {
        padding: 36px 42px;
    }
    .ultimate-section-title {
        margin: 0;
        color: #28345f;
        font-size: 21px;
        font-weight: 500;
    }
    .ultimate-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 22px;
    }
    .ultimate-section-kicker {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .ultimate-section-copy {
        margin: 7px 0 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.45;
    }
    .ultimate-summary-pill {
        min-width: 150px;
        padding: 10px 13px;
        border: 1px solid #dbe3ee;
        border-radius: 4px;
        background: #f8fafc;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        text-align: right;
    }
    .ultimate-summary-pill strong {
        display: block;
        color: #0f172a;
        font-size: 18px;
        line-height: 1.1;
    }
    .ultimate-payment-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(220px, 1fr));
        gap: 16px;
    }
    .ultimate-payment-field {
        min-width: 0;
        border: 1px solid #dde5ef;
        border-radius: 6px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
        overflow: hidden;
    }
    .ultimate-payment-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px 0;
    }
    .ultimate-payment-field label {
        display: block;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }
    .ultimate-payment-number {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-width: 0;
    }
    .ultimate-payment-number i {
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #eef6ff;
        color: #2563eb;
        font-size: 12px;
    }
    .ultimate-payment-body {
        padding: 12px 14px 14px;
    }
    .ultimate-input {
        width: 100%;
        height: 42px;
        border: 1px solid #cfd8e3;
        border-radius: 3px;
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
    .ultimate-method-usage {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 11px;
        padding-top: 10px;
        border-top: 1px solid #eef2f7;
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
    }
    .ultimate-method-usage strong {
        color: #334155;
        font-size: 13px;
    }
    .ultimate-add-row {
        margin-top: 22px;
        padding: 18px;
        border: 1px dashed #b9c7d8;
        border-radius: 6px;
        background: #fbfdff;
    }
    .ultimate-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 28px;
        padding-top: 18px;
        border-top: 1px solid #e5e7eb;
    }
    .ultimate-legacy {
        margin-top: 18px;
    }
    .ultimate-legacy summary {
        cursor: pointer;
        color: #374151;
        font-weight: 700;
    }
    @media (max-width: 1199px) {
        .ultimate-payment-grid {
            grid-template-columns: repeat(2, minmax(220px, 1fr));
        }
    }
    @media (max-width: 991px) {
        .ultimate-settings-card {
            grid-template-columns: 1fr;
        }
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
        .ultimate-settings-content {
            padding: 24px 18px;
        }
        .ultimate-payment-grid {
            grid-template-columns: 1fr;
        }
        .ultimate-section-head {
            display: block;
        }
        .ultimate-summary-pill {
            margin-top: 12px;
            text-align: left;
        }
    }
</style>
@endsection

@section('content_body')
@php
    $methodUsage = collect($usage ?? []);
    $totalPayments = $methodUsage->sum('payments_count');
    $totalAmount = $methodUsage->sum('total_amount');
    $settingsTabs = [
        'Business' => route('loan-management.settings.business'),
        'CMS' => route('loan-management.settings.cms'),
        'Payment' => route('loan-management.settings.payment-methods'),
    ];
@endphp

<div class="ultimate-settings-page">
    <h1 class="ultimate-settings-title">Business Settings</h1>

    <div class="ultimate-settings-search">
        <span><i class="fa fa-search"></i></span>
        <input type="search" id="paymentSettingsSearch" placeholder="Search">
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

    <form method="POST" action="{{ route('loan-management.settings.payment-methods.update') }}">
        @csrf
        <div class="ultimate-settings-card">
            <aside class="ultimate-settings-tabs">
                @foreach($settingsTabs as $tab => $route)
                    <a href="{{ $route }}" class="ultimate-settings-tab {{ $tab === 'Payment' ? 'active' : '' }}">
                        {{ $tab }}
                    </a>
                @endforeach
            </aside>

            <main class="ultimate-settings-content">
                <div class="ultimate-section-head">
                    <div>
                        <span class="ultimate-section-kicker">Payment configuration</span>
                        <h2 class="ultimate-section-title">Custom Payment Labels</h2>
                        <p class="ultimate-section-copy">Rename payment labels, set display order, and keep only the methods your team uses active.</p>
                    </div>
                    <div class="ultimate-summary-pill">
                        <strong>{{ number_format($paymentMethods->count()) }}</strong>
                        configured methods
                    </div>
                </div>

                <div class="ultimate-payment-grid" id="paymentMethodGrid">
                    @forelse($paymentMethods as $index => $method)
                        @php
                            $usageRow = $methodUsage->get($method->name, ['payments_count' => 0, 'total_amount' => 0]);
                            $number = $index + 1;
                            $isActive = !empty($method->is_active);
                        @endphp
                        <div class="ultimate-payment-field" data-payment-field>
                            <div class="ultimate-payment-topline">
                                <label class="ultimate-payment-number">
                                    <i class="fa fa-credit-card"></i>
                                    Custom Payment {{ $number }}
                                </label>
                            </div>
                            <div class="ultimate-payment-body">
                                <input type="text" name="methods[{{ $method->id }}][name]" class="ultimate-input" value="{{ $method->name }}" required>
                                <input type="hidden" name="methods[{{ $method->id }}][sort_order]" value="{{ $method->sort_order ?? 0 }}">
                                <input type="hidden" name="methods[{{ $method->id }}][code]" value="{{ $method->code ?? '' }}">
                                <input type="hidden" name="methods[{{ $method->id }}][is_active]" value="{{ $isActive ? 1 : 0 }}">
                                <div class="ultimate-method-usage">
                                    <span>
                                        <strong>{{ number_format($usageRow['payments_count'] ?? 0) }}</strong>
                                        payment{{ (int) ($usageRow['payments_count'] ?? 0) === 1 ? '' : 's' }}
                                    </span>
                                    <span>{{ number_format((float) ($usageRow['total_amount'] ?? 0), 2) }} collected</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">No payment methods found.</p>
                    @endforelse
                </div>

                <div class="ultimate-add-row">
                    <div class="ultimate-section-head">
                        <div>
                            <span class="ultimate-section-kicker">New method</span>
                            <h2 class="ultimate-section-title">Add Custom Payment</h2>
                        </div>
                    </div>
                    <div class="ultimate-payment-grid">
                        <div class="ultimate-payment-field">
                            <div class="ultimate-payment-topline">
                                <label class="ultimate-payment-number">
                                    <i class="fa fa-plus"></i>
                                    Custom Payment {{ $paymentMethods->count() + 1 }}
                                </label>
                            </div>
                            <div class="ultimate-payment-body">
                                <input type="text" name="new_method[name]" class="ultimate-input" placeholder="Add new payment method">
                                <input type="hidden" name="new_method[sort_order]" value="99">
                                <input type="hidden" name="new_method[code]" value="">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ultimate-actions">
                    <a href="{{ route('loan-management.dashboard') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update Settings</button>
                </div>

                <div class="text-muted" style="margin-top:10px;">
                    {{ number_format($paymentMethods->count()) }} methods - {{ number_format($totalPayments) }} loan payments - {{ number_format($totalAmount, 2) }} collected
                </div>
            </main>
        </div>
    </form>

    @if($legacyRows->isNotEmpty())
        <details class="ultimate-legacy">
            <summary>Legacy Payment Method Data</summary>
            <div class="box box-default" style="margin-top:10px;">
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th style="width:80px;">ID</th><th>Name</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($legacyRows as $row)
                                <tr>
                                    <td>{{ $row->id ?? '-' }}</td>
                                    <td>{{ $row->name ?? '-' }}</td>
                                    <td>{{ isset($row->is_active) ? (!empty($row->is_active) ? 'Active' : 'Inactive') : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    @endif
</div>
@endsection

@section('loan_js')
<script>
    (function () {
        var search = document.getElementById('paymentSettingsSearch');
        var fields = document.querySelectorAll('[data-payment-field]');

        if (!search) {
            return;
        }

        search.addEventListener('input', function () {
            var needle = search.value.toLowerCase();
            fields.forEach(function (field) {
                var values = Array.from(field.querySelectorAll('input')).map(function (input) {
                    return input.value || input.placeholder || '';
                }).join(' ');
                var haystack = (field.textContent + ' ' + values).toLowerCase();
                field.style.display = haystack.indexOf(needle) === -1 ? 'none' : '';
            });
        });
    })();
</script>
@endsection
