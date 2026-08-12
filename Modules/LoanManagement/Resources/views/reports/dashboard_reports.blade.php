@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង' : 'Dashboard Reports')

@php
    $t = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$ '.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
    $cards = $payload['cards'] ?? [];
@endphp

@section('loan_css')
@parent
<style>
    .lm-report-tabs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        margin-bottom: 12px;
    }
    .lm-report-tab {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 8px 15px;
        border-radius: 8px;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }
    .lm-report-tab:hover,
    .lm-report-tab:focus {
        color: #0f172a;
        text-decoration: none;
    }
    .lm-report-tab.is-active {
        background: #0f172a;
        color: #fff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
    }
</style>
@endsection

@section('content_body')
<section class="content-header">
    <h1>{{ $t('Dashboard Reports', 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង') }}</h1>
</section>

<section class="content">
    <div class="lm-report-tabs" role="navigation" aria-label="Loan dashboard pages">
        <a href="{{ route('loan-management.dashboard') }}" class="lm-report-tab">{{ $t('Dashboard', 'ផ្ទាំងគ្រប់គ្រង') }}</a>
        <a href="{{ route('loan-management.reports.dashboard') }}" class="lm-report-tab is-active">{{ $t('Dashboard Reports', 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង') }}</a>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $t('Filters', 'តម្រង') }}</h3>
        </div>
        <div class="box-body">
            <form method="GET" action="{{ route('loan-management.reports.dashboard') }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{ $t('Search', 'ស្វែងរក') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $t('Loan, invoice, customer, phone', 'កម្ចី វិក្កយបត្រ អតិថិជន ទូរស័ព្ទ') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{ $t('Location', 'ទីតាំង') }}</label>
                            <select name="location_id" class="form-control">
                                <option value="">{{ $t('All', 'ទាំងអស់') }}</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}" {{ (string) ($filters['location_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>{{ $t('Date From', 'ចាប់ពីថ្ងៃ') }}</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>{{ $t('Date To', 'ដល់ថ្ងៃ') }}</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2" style="padding-top:25px;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> {{ $t('Filter', 'ចម្រោះ') }}</button>
                        <a href="{{ route('loan-management.reports.dashboard') }}" class="btn btn-default"><i class="fa fa-refresh"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Loans', 'កម្ចី') }}</span>
                    <span class="info-box-number">{{ $number($cards['loan_count'] ?? 0) }}</span>
                    <small>{{ $money($cards['principal_total'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Collected Payments', 'ប្រាក់ប្រមូលបាន') }}</span>
                    <span class="info-box-number">{{ $money($cards['collection_total'] ?? 0) }}</span>
                    <small>{{ $number($cards['payment_count'] ?? 0) }} {{ $t('payments', 'ការបង់ប្រាក់') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-blue"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Loan/Deposit Payments', 'ប្រាក់កក់កម្ចី') }}</span>
                    <span class="info-box-number">{{ $money($cards['deposit_total'] ?? 0) }}</span>
                    <small>{{ $t('Total paid', 'សរុបបានបង់') }} {{ $money($cards['payment_total'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-balance-scale"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $t('Outstanding Balance', 'សមតុល្យនៅសល់') }}</span>
                    <span class="info-box-number">{{ $money($cards['balance_total'] ?? 0) }}</span>
                    <small>{{ $t('Loan total', 'សរុបកម្ចី') }} {{ $money($cards['loan_total'] ?? 0) }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Loan Report by Status', 'របាយការណ៍កម្ចីតាមស្ថានភាព') }}</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ $t('Status', 'ស្ថានភាព') }}</th>
                                <th class="text-right">{{ $t('Loans', 'កម្ចី') }}</th>
                                <th class="text-right">{{ $t('Principal', 'ប្រាក់ដើម') }}</th>
                                <th class="text-right">{{ $t('Paid', 'បានបង់') }}</th>
                                <th class="text-right">{{ $t('Balance', 'សមតុល្យ') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['loanStatusRows'] as $row)
                                <tr>
                                    <td><span class="label label-default">{{ ucfirst(str_replace('_', ' ', $row->status ?? 'unknown')) }}</span></td>
                                    <td class="text-right">{{ $number($row->loan_count ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->principal_total ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->paid_total ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->balance_total ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ $t('No loan data found.', 'រកមិនឃើញទិន្នន័យកម្ចី') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Collected Payment by Day', 'ការប្រមូលប្រាក់តាមថ្ងៃ') }}</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ $t('Date', 'ថ្ងៃ') }}</th>
                                <th class="text-right">{{ $t('Payments', 'ការបង់ប្រាក់') }}</th>
                                <th class="text-right">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['dailyCollectionRows'] as $row)
                                <tr>
                                    <td>{{ ! empty($row->paid_day) ? \Carbon\Carbon::parse($row->paid_day)->format('d-m-Y') : '-' }}</td>
                                    <td class="text-right">{{ $number($row->payment_count ?? 0) }}</td>
                                    <td class="text-right">{{ $money($row->payment_total ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">{{ $t('No collection data found.', 'រកមិនឃើញទិន្នន័យប្រមូលប្រាក់') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Recent Loans', 'កម្ចីថ្មីៗ') }}</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>{{ $t('Loan #', 'លេខកម្ចី') }}</th>
                                <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                                <th>{{ $t('Date', 'ថ្ងៃ') }}</th>
                                <th class="text-right">{{ $t('Balance', 'សមតុល្យ') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['recentLoans'] as $loan)
                                <tr>
                                    <td>
                                        <a href="{{ route('loan-management.loans.view', $loan->id) }}">{{ $loan->loan_number ?? ('#'.$loan->id) }}</a>
                                        <br><small class="text-muted">{{ ucfirst($loan->status ?? '-') }}</small>
                                    </td>
                                    <td>{{ $loan->customer_name ?: '-' }}</td>
                                    <td>{{ ! empty($loan->loan_date) ? \Carbon\Carbon::parse($loan->loan_date)->format('d-m-Y') : '-' }}</td>
                                    <td class="text-right">{{ $money($loan->balance_amount ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">{{ $t('No recent loans found.', 'រកមិនឃើញកម្ចីថ្មីៗ') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Recent Collected Payments', 'ការបង់ប្រាក់ថ្មីៗ') }}</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>{{ $t('Receipt', 'បង្កាន់ដៃ') }}</th>
                                <th>{{ $t('Loan #', 'លេខកម្ចី') }}</th>
                                <th>{{ $t('Customer', 'អតិថិជន') }}</th>
                                <th class="text-right">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['recentPayments'] as $payment)
                                <tr>
                                    <td>
                                        <a href="{{ route('loan-management.payments.show', $payment->id) }}">{{ $payment->receipt_number ?? ('#'.$payment->id) }}</a>
                                        <br><small class="text-muted">{{ ! empty($payment->paid_date) ? \Carbon\Carbon::parse($payment->paid_date)->format('d-m-Y') : '-' }}</small>
                                    </td>
                                    <td>{{ $payment->loan_number ?? '-' }}</td>
                                    <td>{{ $payment->customer_name ?: '-' }}</td>
                                    <td class="text-right">{{ $money($payment->amount ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">{{ $t('No recent payments found.', 'រកមិនឃើញការបង់ប្រាក់ថ្មីៗ') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
