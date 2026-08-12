@extends('loanmanagement::layouts.app')
@section('title', $isKhmer ? 'សង្ខេបការបង់ប្រាក់តាមប្រភេទ' : 'Payment Summary by Type')

@php
    $t = fn ($en, $km) => $isKhmer ? $km : $en;
    $money = fn ($value) => '$ '.number_format((float) ($value ?? 0), 2);
@endphp

@section('loan_css')
@parent
<style>
    .lm-payment-summary-table th {
        background: #dbeafe;
        color: #0f172a;
        text-align: center;
        vertical-align: middle !important;
    }
    .lm-payment-summary-table td {
        vertical-align: middle !important;
    }
    .lm-payment-summary-table tfoot th {
        background: #eff6ff;
    }
    .lm-payment-print-title {
        display: none;
    }
    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        .lm-sidebar,
        .lm-header,
        .lm-breadcrumb-wrap,
        .lm-payment-filter-box,
        .lm-payment-no-print,
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
        .lm-payment-print-title {
            display: block;
            margin: 0 0 10px;
            text-align: center;
        }
        .lm-payment-print-title h2 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 700;
        }
        .lm-payment-print-title p {
            margin: 0;
            color: #4b5563;
            font-size: 11px;
        }
        .box {
            border: 1px solid #d1d5db !important;
            box-shadow: none !important;
        }
        .table > thead > tr > th,
        .table > tbody > tr > td,
        .table > tfoot > tr > th {
            padding: 5px 6px !important;
            border-color: #d1d5db !important;
            font-size: 11px !important;
        }
        a[href]:after {
            content: "" !important;
        }
    }
</style>
@endsection

@section('content_body')
<section class="content-header">
    <h1>{{ $t('Payment Summary by Type', 'សង្ខេបការបង់ប្រាក់តាមប្រភេទ') }}</h1>
</section>

<section class="content">
    <div class="lm-payment-print-title">
        <h2>{{ $t('Payment Summary by Type', 'សង្ខេបការបង់ប្រាក់តាមប្រភេទ') }}</h2>
        <p>
            {{ \Carbon\Carbon::parse($filters['date_from'])->format('d-m-Y') }}
            -
            {{ \Carbon\Carbon::parse($filters['date_to'])->format('d-m-Y') }}
        </p>
    </div>

    <div class="box box-primary lm-payment-filter-box">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $t('Filters', 'តម្រង') }}</h3>
        </div>
        <div class="box-body">
            <form method="GET" action="{{ route('loan-management.reports.payment-summary-by-type') }}">
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
                        <a href="{{ route('loan-management.reports.payment-summary-by-type') }}" class="btn btn-default"><i class="fa fa-refresh"></i></a>
                        <button type="button" class="btn btn-success" onclick="window.print();"><i class="fa fa-print"></i> {{ $t('Print', 'បោះពុម្ព') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">
                {{ $t('Payment Summary by Type', 'សង្ខេបការបង់ប្រាក់តាមប្រភេទ') }}
            </h3>
            <div class="box-tools pull-right lm-payment-no-print">
                <a href="{{ route('loan-management.reports.dashboard', request()->query()) }}" class="btn btn-box-tool" title="{{ $t('Back to Dashboard Reports', 'ត្រឡប់ទៅរបាយការណ៍ផ្ទាំងគ្រប់គ្រង') }}">
                    <i class="fa fa-arrow-left"></i>
                </a>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered lm-payment-summary-table">
                <thead>
                    <tr>
                        <th>{{ $t('Type', 'ប្រភេទ') }}</th>
                        <th class="text-right">{{ $t('Count', 'ចំនួន') }}</th>
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
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row['label'] }}</strong></td>
                            <td class="text-right">{{ number_format((float) ($row['count'] ?? 0), 0) }}</td>
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
                        <tr><td colspan="10" class="text-center text-muted">{{ $t('No payment summary found.', 'រកមិនឃើញសង្ខេបការបង់ប្រាក់') }}</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($rows))
                    <tfoot>
                        <tr>
                            <th>{{ $t('Total', 'សរុប') }}</th>
                            <th class="text-right">{{ number_format((float) collect($rows)->sum('count'), 0) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('cash')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('aba')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('acleda')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('wing')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('et')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('card')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('other')) }}</th>
                            <th class="text-right">{{ $money(collect($rows)->sum('total')) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</section>
@endsection
