@extends('layouts.app')

@section('title', 'Monthly Financial Performance Summary')

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Annual & Monthly Financial Performance (' . $year . ')'])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.reports.monthly') }}" method="GET" class="tw-flex tw-items-end tw-gap-4">
                <div class="tw-w-[150px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Year</label>
                    <select name="year" class="form-control select2">
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> View Annual Breakdown</button>
            </form>
        </div>
    </div>

    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">12-Month Performance Trend for {{ $year }}</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-bordered table-striped tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Month</th>
                            <th class="tw-text-right">Net Sales</th>
                            <th class="tw-text-right">Gross Profit</th>
                            <th class="tw-text-right">Operating Expenses</th>
                            <th class="tw-text-right">Net Profit</th>
                            <th class="tw-text-right">Total Cash Inflow</th>
                            <th class="tw-text-right">Total Cash Outflow</th>
                            <th class="tw-text-right">Net Cash Flow</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($months as $m)
                            <tr>
                                <td class="tw-font-bold">{{ $m['month_name'] }}</td>
                                <td class="tw-text-right tw-font-semibold">${{ number_format($m['net_sales'], 2) }}</td>
                                <td class="tw-text-right tw-text-teal-700">${{ number_format($m['gross_profit'], 2) }}</td>
                                <td class="tw-text-right tw-text-red-600">${{ number_format($m['operating_expenses'], 2) }}</td>
                                <td class="tw-text-right tw-font-bold {{ $m['net_profit'] >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($m['net_profit'], 2) }}
                                </td>
                                <td class="tw-text-right tw-text-green-700">+${{ number_format($m['inflows'], 2) }}</td>
                                <td class="tw-text-right tw-text-red-600">-${{ number_format($m['outflows'], 2) }}</td>
                                <td class="tw-text-right tw-font-semibold {{ $m['net_cash_flow'] >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($m['net_cash_flow'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

