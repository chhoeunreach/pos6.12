@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.executive_report'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.executive_report')])

    <!-- Filters & Export Toolbar -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4 tw-flex tw-flex-wrap tw-items-end tw-justify-between tw-gap-4">
            <form action="{{ route('daily-financial.reports.executive') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[180px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Report Date</label>
                    <input type="date" name="date" value="{{ $date }}" class="form-control" />
                </div>
                <div class="tw-min-w-[200px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Branch</label>
                    <select name="location_id" class="form-control select2">
                        <option value="">-- Consolidated (All Branches) --</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" {{ $locationId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> Generate</button>
            </form>

            <div class="tw-flex tw-gap-2">
                <a href="{{ route('daily-financial.reports.export.excel', ['type' => 'profit_loss', 'start_date' => $date, 'end_date' => $date, 'location_id' => $locationId]) }}" class="btn btn-success">
                    <i class="fa fa-file-excel-o"></i> Export Excel
                </a>
                <a href="{{ route('daily-financial.reports.export.pdf', ['type' => 'executive', 'date' => $date, 'location_id' => $locationId]) }}" class="btn btn-danger">
                    <i class="fa fa-file-pdf-o"></i> Download PDF
                </a>
                <button onclick="window.print();" class="btn btn-default">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Official Report Document Paper -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-p-8 tw-bg-white print:tw-p-0 print:tw-shadow-none">
        <div class="tw-text-center tw-border-b tw-border-gray-200 tw-pb-6 tw-mb-6">
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-uppercase">KNEAYERNG PHONE SHOP</h2>
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-700 tw-mt-1">DAILY EXECUTIVE FINANCIAL REPORT</h3>
            <p class="tw-text-sm tw-text-gray-500">
                Reporting Date: <strong>{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</strong> &bull;
                Scope: <strong>{{ $locationId ? ($branches->firstWhere('id', $locationId)->name ?? 'Branch') : 'All Branches Consolidated' }}</strong>
            </p>
        </div>

        <!-- 3 Pillars Grid -->
        <div class="row">
            <!-- Pillar 1: Profit and Loss Statement -->
            <div class="col-md-6 col-xs-12">
                <h4 class="tw-font-bold tw-text-sm tw-text-blue-900 tw-uppercase tw-border-b tw-border-blue-200 tw-pb-2 tw-mb-3">
                    <i class="fa fa-line-chart"></i> 1. Commercial Profit & Loss Statement
                </h4>
                <table class="table table-bordered tw-text-sm">
                    <tr>
                        <td>Gross Sales Revenue (POS)</td>
                        <td class="tw-text-right tw-font-semibold">${{ number_format($pnl['gross_sales'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Less: Sales Discounts</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['sales_discounts'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Less: Sales Returns</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['sales_returns'], 2) }}</td>
                    </tr>
                    <tr class="tw-bg-gray-100">
                        <td class="tw-font-bold">Net Sales Revenue</td>
                        <td class="tw-text-right tw-font-bold">${{ number_format($pnl['net_sales'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Cost of Goods Sold (COGS)</td>
                        <td class="tw-text-right tw-text-gray-600">-${{ number_format($pnl['cogs'], 2) }}</td>
                    </tr>
                    <tr class="tw-bg-teal-50">
                        <td class="tw-font-bold">Gross Commercial Profit</td>
                        <td class="tw-text-right tw-font-bold tw-text-teal-900">
                            ${{ number_format($pnl['gross_profit'], 2) }}
                            <small class="tw-block tw-text-xs">Margin: {{ $pnl['gross_margin_percent'] }}%</small>
                        </td>
                    </tr>
                    <tr>
                        <td>Recognized Loan Interest Income</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($pnl['loan_interest_income'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Recognized Other Operating Income</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($pnl['manual_income'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Operating Expenses (OPEX)</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['operating_expenses'], 2) }}</td>
                    </tr>
                    <tr class="tw-bg-green-100">
                        <td class="tw-font-bold tw-text-base">Net Operating Profit (EBIT)</td>
                        <td class="tw-text-right tw-font-bold tw-text-base tw-text-green-900">
                            ${{ number_format($pnl['net_profit'], 2) }}
                            <small class="tw-block tw-text-xs">Net Margin: {{ $pnl['net_margin_percent'] }}%</small>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Pillar 2: Cash Flow Statement -->
            <div class="col-md-6 col-xs-12">
                <h4 class="tw-font-bold tw-text-sm tw-text-indigo-900 tw-uppercase tw-border-b tw-border-indigo-200 tw-pb-2 tw-mb-3">
                    <i class="fa fa-exchange"></i> 2. Daily Cash Flow (Liquidity Movements)
                </h4>
                <table class="table table-bordered tw-text-sm">
                    <tr class="tw-bg-gray-50">
                        <th colspan="2" class="tw-text-green-800">Cash Inflows</th>
                    </tr>
                    <tr>
                        <td>POS Cash Collections</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($cashFlow['pos_cash_collections'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>POS Bank & QR Collections (ABA, Wing)</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($cashFlow['pos_bank_collections'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Installment Principal Collected (Debt Recovery)</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($cashFlow['loan_principal_collections'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Installment Interest Collected</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($cashFlow['loan_interest_collections'], 2) }}</td>
                    </tr>
                    <tr class="tw-bg-green-50">
                        <td class="tw-font-bold">Total Cash Inflow</td>
                        <td class="tw-text-right tw-font-bold tw-text-green-800">+${{ number_format($cashFlow['total_inflows'], 2) }}</td>
                    </tr>

                    <tr class="tw-bg-gray-50">
                        <th colspan="2" class="tw-text-red-800">Cash Outflows</th>
                    </tr>
                    <tr>
                        <td>Operating Expenses Paid</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($cashFlow['operating_expense_outflows'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Inventory / Stock Purchases Paid</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($cashFlow['inventory_purchase_outflows'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Other Outflows / Advances</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($cashFlow['other_outflows'], 2) }}</td>
                    </tr>
                    <tr class="tw-bg-red-50">
                        <td class="tw-font-bold">Total Cash Outflow</td>
                        <td class="tw-text-right tw-font-bold tw-text-red-800">-${{ number_format($cashFlow['total_outflows'], 2) }}</td>
                    </tr>

                    <tr class="tw-bg-blue-100">
                        <td class="tw-font-bold tw-text-base">Net Cash Movement (Inflow - Outflow)</td>
                        <td class="tw-text-right tw-font-bold tw-text-base {{ $cashFlow['net_cash_flow'] >= 0 ? 'tw-text-green-900' : 'tw-text-red-900' }}">
                            {{ $cashFlow['net_cash_flow'] >= 0 ? '+$' : '-$' }}{{ number_format(abs($cashFlow['net_cash_flow']), 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Pillar 3: Cash & Bank Liquid Balances -->
        <div class="tw-mt-8">
            <h4 class="tw-font-bold tw-text-sm tw-text-gray-800 tw-uppercase tw-border-b tw-pb-2 tw-mb-3">
                <i class="fa fa-university"></i> 3. Liquid Asset Reserves (Reconciliation Summary)
            </h4>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
                <div class="tw-p-4 tw-bg-yellow-50 tw-rounded-lg tw-border tw-border-yellow-200">
                    <span class="tw-text-xs tw-text-yellow-800 tw-font-bold tw-uppercase">Physical Cash Balance</span>
                    <p class="tw-text-2xl tw-font-bold tw-text-yellow-900 tw-mt-1">${{ number_format($reconciliation['summary']['expected_cash'], 2) }}</p>
                    <span class="tw-text-xs tw-text-yellow-700">Vaults & Drawers</span>
                </div>
                <div class="tw-p-4 tw-bg-purple-50 tw-rounded-lg tw-border tw-border-purple-200">
                    <span class="tw-text-xs tw-text-purple-800 tw-font-bold tw-uppercase">Bank & Digital Balances</span>
                    <p class="tw-text-2xl tw-font-bold tw-text-purple-900 tw-mt-1">${{ number_format($reconciliation['summary']['expected_bank'], 2) }}</p>
                    <span class="tw-text-xs tw-text-purple-700">ABA Bank, ACLEDA, Wing</span>
                </div>
                <div class="tw-p-4 tw-bg-green-50 tw-rounded-lg tw-border tw-border-green-200">
                    <span class="tw-text-xs tw-text-green-800 tw-font-bold tw-uppercase">Total Liquid Reserves</span>
                    <p class="tw-text-2xl tw-font-bold tw-text-green-900 tw-mt-1">${{ number_format($reconciliation['summary']['total_liquid_assets'], 2) }}</p>
                    <span class="tw-text-xs tw-text-green-700">Combined Verified Liquidity</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

