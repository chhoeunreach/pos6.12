@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង' : 'Dashboard Reports')

@php
    $t = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$ '.number_format((float) ($value ?? 0), 2);
    $number = fn ($value) => number_format((float) ($value ?? 0), 0);
    $cards = $payload['cards'] ?? [];
    $period = $filters['period'] ?? 'daily';
    $periodLabel = $payload['collectionPeriodLabel'] ?? $t('Date', 'ថ្ងៃ');
    $periodTitle = ['daily' => $t('Daily', 'ប្រចាំថ្ងៃ'), 'monthly' => $t('Monthly', 'ប្រចាំខែ'), 'yearly' => $t('Yearly', 'ប្រចាំឆ្នាំ')][$period] ?? $t('Daily', 'ប្រចាំថ្ងៃ');
    $formatPeriod = function ($value) use ($period) {
        if (empty($value)) {
            return '-';
        }
        if ($period === 'monthly') {
            return \Carbon\Carbon::createFromFormat('Y-m', (string) $value)->format('m-Y');
        }
        if ($period === 'yearly') {
            return (string) $value;
        }
        return \Carbon\Carbon::parse($value)->format('d-m-Y');
    };
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
    .lm-recent-panel-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .lm-recent-panel-heading {
        margin: 0 0 8px;
        color: #334155;
        font-size: 14px;
        font-weight: 700;
    }
    .lm-payment-method-summary th {
        background: #dbeafe;
        color: #0f172a;
        text-align: center;
        vertical-align: middle !important;
    }
    .lm-payment-method-summary td {
        vertical-align: middle !important;
    }
    .lm-payment-method-summary tfoot th {
        background: #eff6ff;
    }
    .lm-report-print-title {
        display: none;
    }
    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            background: #fff !important;
            color: #111827 !important;
        }
        .lm-sidebar,
        .lm-header,
        .lm-breadcrumb-wrap,
        .lm-report-tabs,
        .lm-report-filter-box,
        .lm-report-no-print,
        .main-footer,
        .no-print {
            display: none !important;
        }
        .lm-main,
        .lm-content,
        .lm-workspace,
        .content,
        .container-fluid {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
        }
        .content-header {
            padding: 0 0 8px !important;
        }
        .lm-report-print-title {
            display: block;
            margin: 0 0 10px;
            text-align: center;
        }
        .lm-report-print-title h2 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 700;
        }
        .lm-report-print-title p {
            margin: 0;
            font-size: 11px;
            color: #4b5563;
        }
        .info-box,
        .box {
            border: 1px solid #d1d5db !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        .box-header,
        .box-body {
            padding: 8px !important;
        }
        .table > thead > tr > th,
        .table > tbody > tr > td {
            padding: 4px 5px !important;
            font-size: 10px !important;
            border-color: #d1d5db !important;
        }
        a[href]:after {
            content: "" !important;
        }
    }
    @media (max-width: 991px) {
        .lm-recent-panel-grid {
            grid-template-columns: 1fr;
        }
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

    <div class="lm-report-print-title">
        <h2>{{ $periodTitle }} {{ $t('Loan and Collection Report', 'របាយការណ៍កម្ចី និងការប្រមូលប្រាក់') }}</h2>
        <p>{{ \Carbon\Carbon::parse($filters['date_from'])->format('d-m-Y') }} - {{ \Carbon\Carbon::parse($filters['date_to'])->format('d-m-Y') }}</p>
    </div>

    <div class="box box-primary lm-report-filter-box">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $t('Filters', 'តម្រង') }}</h3>
        </div>
        <div class="box-body">
            <form method="GET" action="{{ route('loan-management.reports.dashboard') }}">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>{{ $t('Search', 'ស្វែងរក') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $t('Loan, invoice, customer, phone', 'កម្ចី វិក្កយបត្រ អតិថិជន ទូរស័ព្ទ') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
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
                            <label>{{ $t('Report Type', 'ប្រភេទរបាយការណ៍') }}</label>
                            <select name="period" class="form-control">
                                <option value="daily" {{ $period === 'daily' ? 'selected' : '' }}>{{ $t('Daily', 'ប្រចាំថ្ងៃ') }}</option>
                                <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>{{ $t('Monthly', 'ប្រចាំខែ') }}</option>
                                <option value="yearly" {{ $period === 'yearly' ? 'selected' : '' }}>{{ $t('Yearly', 'ប្រចាំឆ្នាំ') }}</option>
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
                        <button type="button" class="btn btn-success" onclick="window.print();"><i class="fa fa-print"></i> {{ $t('Print', 'បោះពុម្ព') }}</button>
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

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $t('Payment Summary by Type', 'សង្ខេបការបង់ប្រាក់តាមប្រភេទ') }}</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered lm-payment-method-summary">
                <thead>
                    <tr>
                        <th>{{ $t('Type', 'ប្រភេទ') }}</th>
                        <th>{{ $t('Cash', 'លុយសុទ្ធ') }}</th>
                        <th>ABA</th>
                        <th>ACLEDA</th>
                        <th>WING</th>
                        <th>E&amp;T</th>
                        <th>CARD</th>
                        <th>{{ $t('Other', 'ផ្សេងៗ') }}</th>
                        <th>{{ $t('Total', 'បង់សរុប') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payload['paymentMethodRows'] as $row)
                        <tr>
                            <td><strong>{{ $row['label'] }}</strong></td>
                            <td class="text-right">{{ $money($row['cash'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['aba'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['acleda'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['wing'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['et'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['card'] ?? 0) }}</td>
                            <td class="text-right">{{ $money($row['other'] ?? 0) }}</td>
                            <td class="text-right"><strong>{{ $money($row['total'] ?? 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">{{ $t('No payment summary found.', 'រកមិនឃើញសង្ខេបការបង់ប្រាក់') }}</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($payload['paymentMethodRows']))
                    <tfoot>
                        <tr>
                            <th>{{ $t('Total', 'សរុប') }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('cash')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('aba')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('acleda')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('wing')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('et')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('card')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('other')) }}</th>
                            <th class="text-right">{{ $money(collect($payload['paymentMethodRows'])->sum('total')) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid collapsed-box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Loan Report by Status', 'របាយការណ៍កម្ចីតាមស្ថានភាព') }}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse" title="{{ $t('Expand / Collapse', 'ពង្រីក / បង្រួម') }}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body table-responsive" style="display: none;">
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
            <div class="box box-solid collapsed-box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $periodTitle }} {{ $t('Collected Payment Report', 'របាយការណ៍ប្រមូលប្រាក់') }}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse" title="{{ $t('Expand / Collapse', 'ពង្រីក / បង្រួម') }}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body table-responsive" style="display: none;">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ $periodLabel }}</th>
                                <th class="text-right">{{ $t('Payments', 'ការបង់ប្រាក់') }}</th>
                                <th class="text-right">{{ $t('Amount', 'ចំនួនប្រាក់') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payload['collectionRows'] as $row)
                                <tr>
                                    <td>{{ $formatPeriod($row->period_key ?? null) }}</td>
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
        <div class="col-md-12 lm-report-no-print">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $t('Recent Activity', 'សកម្មភាពថ្មីៗ') }}</h3>
                </div>
                <div class="box-body">
                    <div class="lm-recent-panel-grid">
                        <div class="table-responsive">
                            <h4 class="lm-recent-panel-heading">{{ $t('Recent Loans', 'កម្ចីថ្មីៗ') }}</h4>
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

                        <div class="table-responsive">
                            <h4 class="lm-recent-panel-heading">{{ $t('Recent Collected Payments', 'ការបង់ប្រាក់ថ្មីៗ') }}</h4>
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
        </div>
    </div>
</section>
@endsection
