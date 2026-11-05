@extends('layouts.app')

@section('title', 'Branch Financial Performance Comparison')

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Branch Financial Performance Comparison'])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.reports.branch-comparison') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control" />
                </div>
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control" />
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Compare Branches</button>
            </form>
        </div>
    </div>

    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Comparative Financial Metrics by Branch</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-bordered table-striped tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Branch Location</th>
                            <th class="tw-text-right">Net Sales</th>
                            <th class="tw-text-right">COGS</th>
                            <th class="tw-text-right">Gross Profit</th>
                            <th class="tw-text-right">Gross Margin</th>
                            <th class="tw-text-right">Operating Expenses</th>
                            <th class="tw-text-right">Net Operating Profit</th>
                            <th class="tw-text-right">Net Margin</th>
                            <th class="tw-text-right">Total Inflow</th>
                            <th class="tw-text-right">Total Outflow</th>
                            <th class="tw-text-right">Net Cash Flow</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $c)
                            <tr>
                                <td class="tw-font-bold">{{ $c['branch_name'] }}</td>
                                <td class="tw-text-right tw-font-semibold">${{ number_format($c['net_sales'], 2) }}</td>
                                <td class="tw-text-right tw-text-gray-600">${{ number_format($c['cogs'], 2) }}</td>
                                <td class="tw-text-right tw-text-teal-700 tw-font-semibold">${{ number_format($c['gross_profit'], 2) }}</td>
                                <td class="tw-text-right"><span class="badge bg-teal">{{ $c['gross_margin'] }}%</span></td>
                                <td class="tw-text-right tw-text-red-600">${{ number_format($c['operating_expenses'], 2) }}</td>
                                <td class="tw-text-right tw-font-bold {{ $c['net_profit'] >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($c['net_profit'], 2) }}
                                </td>
                                <td class="tw-text-right"><span class="badge {{ $c['net_margin'] >= 10 ? 'bg-green' : 'bg-yellow' }}">{{ $c['net_margin'] }}%</span></td>
                                <td class="tw-text-right tw-text-green-700">+${{ number_format($c['total_inflow'], 2) }}</td>
                                <td class="tw-text-right tw-text-red-600">-${{ number_format($c['total_outflow'], 2) }}</td>
                                <td class="tw-text-right tw-font-semibold {{ $c['net_cash_flow'] >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($c['net_cash_flow'], 2) }}
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

