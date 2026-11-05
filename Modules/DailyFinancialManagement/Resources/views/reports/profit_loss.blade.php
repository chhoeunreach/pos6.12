@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.profit_and_loss'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.profit_and_loss')])

    <!-- Filters & Export -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4 tw-flex tw-flex-wrap tw-items-end tw-justify-between tw-gap-4">
            <form action="{{ route('daily-financial.reports.profit-loss') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
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
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Run P&L</button>
            </form>

            <div class="tw-flex tw-gap-2">
                <a href="{{ route('daily-financial.reports.export.excel', ['type' => 'profit_loss', 'start_date' => $startDate, 'end_date' => $endDate, 'location_id' => $locationId]) }}" class="btn btn-success">
                    <i class="fa fa-file-excel-o"></i> Export Excel
                </a>
                <button onclick="window.print();" class="btn btn-default">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Official P&L Statement -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-p-8 tw-bg-white print:tw-p-0 print:tw-shadow-none">
        <div class="tw-text-center tw-border-b tw-border-gray-200 tw-pb-6 tw-mb-6">
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-uppercase">KNEAYERNG PHONE SHOP</h2>
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-700 tw-mt-1">STATEMENT OF PROFIT AND LOSS</h3>
            <p class="tw-text-sm tw-text-gray-500">
                Period: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong> &bull;
                Currency: <strong>USD ($)</strong>
            </p>
        </div>

        <table class="table table-bordered tw-text-sm">
            <tr class="tw-bg-gray-50">
                <th class="tw-w-3/4">Account Item / Category</th>
                <th class="tw-text-right tw-w-1/4">Amount ($)</th>
            </tr>
            <tr>
                <td class="tw-pl-4">Gross POS Sales Revenue</td>
                <td class="tw-text-right tw-font-semibold">${{ number_format($pnl['gross_sales'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-8 tw-text-gray-600">Less: Customer Discounts Fixed & Percentage</td>
                <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['sales_discounts'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-8 tw-text-gray-600">Less: Sales Returns & Refunds</td>
                <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['sales_returns'], 2) }}</td>
            </tr>
            <tr class="tw-bg-blue-50">
                <td class="tw-font-bold tw-text-base">NET SALES REVENUE</td>
                <td class="tw-text-right tw-font-bold tw-text-base tw-text-blue-900">${{ number_format($pnl['net_sales'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-4">Cost of Goods Sold (COGS - Inventory Purchase Cost)</td>
                <td class="tw-text-right tw-text-gray-700">-${{ number_format($pnl['cogs'], 2) }}</td>
            </tr>
            <tr class="tw-bg-teal-50">
                <td class="tw-font-bold tw-text-base">GROSS PROFIT</td>
                <td class="tw-text-right tw-font-bold tw-text-base tw-text-teal-900">
                    ${{ number_format($pnl['gross_profit'], 2) }}
                    <span class="badge bg-teal tw-ml-2">{{ $pnl['gross_margin_percent'] }}%</span>
                </td>
            </tr>

            <!-- Other Income Section -->
            <tr class="tw-bg-gray-50">
                <th colspan="2" class="tw-text-green-800">Other Operating & Financing Income</th>
            </tr>
            <tr>
                <td class="tw-pl-8">Recognized Installment Interest Income</td>
                <td class="tw-text-right tw-text-green-700">+${{ number_format($pnl['loan_interest_income'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-8">Other Manual Operating Income</td>
                <td class="tw-text-right tw-text-green-700">+${{ number_format($pnl['manual_income'], 2) }}</td>
            </tr>
            <tr class="tw-bg-green-50">
                <td class="tw-font-bold">Total Recognized Operating Income</td>
                <td class="tw-text-right tw-font-bold tw-text-green-800">+${{ number_format($pnl['total_recognized_income'], 2) }}</td>
            </tr>

            <!-- Operating Expenses Section -->
            <tr class="tw-bg-gray-50">
                <th colspan="2" class="tw-text-red-800">Operating Expenses (OPEX Breakdown)</th>
            </tr>
            @forelse ($expenseBreakdown as $eb)
                <tr>
                    <td class="tw-pl-8">{{ $eb->category ? $eb->category->display_name : 'General Expenses' }} ({{ $eb->count }} vouchers)</td>
                    <td class="tw-text-right tw-text-red-600">-${{ number_format($eb->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="tw-pl-8">Total Operating Expenses</td>
                    <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['operating_expenses'], 2) }}</td>
                </tr>
            @endforelse
            <tr class="tw-bg-red-50">
                <td class="tw-font-bold">TOTAL OPERATING EXPENSES</td>
                <td class="tw-text-right tw-font-bold tw-text-red-800">-${{ number_format($pnl['operating_expenses'], 2) }}</td>
            </tr>

            <!-- Bottom Line Net Operating Profit -->
            <tr class="tw-bg-green-100">
                <td class="tw-font-bold tw-text-lg tw-text-green-950">NET OPERATING PROFIT (EBIT)</td>
                <td class="tw-text-right tw-font-bold tw-text-xl tw-text-green-950">
                    ${{ number_format($pnl['net_profit'], 2) }}
                    <span class="badge bg-green tw-ml-2">{{ $pnl['net_margin_percent'] }}%</span>
                </td>
            </tr>
        </table>
    </div>
</div>
@endsection

