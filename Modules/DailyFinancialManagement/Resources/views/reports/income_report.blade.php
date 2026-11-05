@extends('layouts.app')

@section('title', 'Daily Income Financial Report')

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Consolidated Income Report'])

    <!-- Filter Bar -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4 tw-flex tw-flex-wrap tw-items-end tw-justify-between tw-gap-4">
            <form action="{{ route('daily-financial.reports.income') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control" />
                </div>
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control" />
                </div>
                <div class="tw-min-w-[180px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Branch</label>
                    <select name="location_id" class="form-control select2">
                        <option value="">-- All Branches --</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" {{ $locationId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Generate Report</button>
            </form>

            <button onclick="window.print();" class="btn btn-default">
                <i class="fa fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Income Report Document -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-p-6 tw-bg-white print:tw-p-0 print:tw-shadow-none">
        <div class="tw-text-center tw-border-b tw-border-gray-200 tw-pb-6 tw-mb-6">
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-uppercase">KNEAYERNG PHONE SHOP</h2>
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-700 tw-mt-1">DETAILED INCOME STATEMENT</h3>
            <p class="tw-text-sm tw-text-gray-500">
                Period: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong> &bull;
                Total Income Recognized: <strong class="tw-text-green-700">${{ number_format($totalAmount, 2) }}</strong>
            </p>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped tw-text-sm">
                <thead>
                    <tr class="tw-bg-gray-50">
                        <th>Date</th>
                        <th>Ref No</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th>Source</th>
                        <th>Method</th>
                        <th>Account</th>
                        <th class="tw-text-right">Amount (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incomes as $inc)
                        <tr>
                            <td>{{ $inc->transaction_date->format('Y-m-d') }}</td>
                            <td class="tw-font-mono">{{ $inc->ref_no }}</td>
                            <td>{{ $inc->category ? $inc->category->display_name : 'Loan Interest' }}</td>
                            <td>{{ $inc->location ? $inc->location->name : 'N/A' }}</td>
                            <td><span class="label label-default">{{ strtoupper($inc->source_type) }}</span></td>
                            <td>{{ strtoupper($inc->payment_method) }}</td>
                            <td>{{ $inc->account ? $inc->account->account_name : '-' }}</td>
                            <td class="tw-text-right tw-font-bold tw-text-green-700">+${{ number_format($inc->amount_in_base_currency, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="tw-text-center tw-text-gray-400 tw-py-6">No income records in the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="tw-bg-green-50">
                        <th colspan="7" class="tw-text-right">TOTAL RECOGNIZED INCOME:</th>
                        <th class="tw-text-right tw-text-base tw-font-bold tw-text-green-900">+${{ number_format($totalAmount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="tw-mt-4">
            {{ $incomes->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

