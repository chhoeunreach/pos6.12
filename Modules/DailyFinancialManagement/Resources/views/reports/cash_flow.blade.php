@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.cash_flow'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.cash_flow')])

    <!-- Filters & Export -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4 tw-flex tw-flex-wrap tw-items-end tw-justify-between tw-gap-4">
            <form action="{{ route('daily-financial.reports.cash-flow') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
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
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Run Cash Flow</button>
            </form>

            <button onclick="window.print();" class="btn btn-default">
                <i class="fa fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Official Cash Flow Document -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-p-8 tw-bg-white print:tw-p-0 print:tw-shadow-none">
        <div class="tw-text-center tw-border-b tw-border-gray-200 tw-pb-6 tw-mb-6">
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-uppercase">KNEAYERNG PHONE SHOP</h2>
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-700 tw-mt-1">STATEMENT OF CASH FLOWS</h3>
            <p class="tw-text-sm tw-text-gray-500">
                Period: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong> &bull;
                Currency: <strong>USD ($)</strong>
            </p>
        </div>

        <table class="table table-bordered tw-text-sm">
            <!-- Inflows -->
            <tr class="tw-bg-green-50">
                <th colspan="2" class="tw-text-green-900 tw-text-base">1. Cash Inflows (Collections & Receipts)</th>
            </tr>
            <tr>
                <td class="tw-pl-6">POS Retail Cash Collections (In-Store Cash)</td>
                <td class="tw-text-right tw-font-semibold tw-text-green-700">+${{ number_format($cashFlow['pos_cash_collections'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-6">POS Bank & Digital Collections (ABA Bank, ACLEDA, Wing)</td>
                <td class="tw-text-right tw-font-semibold tw-text-green-700">+${{ number_format($cashFlow['pos_bank_collections'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-6">
                    Installment Principal Collections (Customer Debt Recovery)
                    <small class="tw-block tw-text-xs tw-text-gray-500">* Balance Sheet Debt Recovery &bull; Not Sales Revenue</small>
                </td>
                <td class="tw-text-right tw-font-semibold tw-text-green-700">+${{ number_format($cashFlow['loan_principal_collections'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-6">Installment Interest & Penalty Collections</td>
                <td class="tw-text-right tw-font-semibold tw-text-green-700">+${{ number_format($cashFlow['loan_interest_collections'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-6">Other Manual Cash Receipts</td>
                <td class="tw-text-right tw-font-semibold tw-text-green-700">+${{ number_format($cashFlow['other_inflows'], 2) }}</td>
            </tr>
            <tr class="tw-bg-green-100">
                <td class="tw-font-bold tw-text-green-950">TOTAL CASH & BANK INFLOWS</td>
                <td class="tw-text-right tw-font-bold tw-text-base tw-text-green-950">+${{ number_format($cashFlow['total_inflows'], 2) }}</td>
            </tr>

            <!-- Outflows -->
            <tr class="tw-bg-red-50">
                <th colspan="2" class="tw-text-red-900 tw-text-base">2. Cash Outflows (Disbursements & Working Capital)</th>
            </tr>
            <tr>
                <td class="tw-pl-6">Operating Expenses Paid (Salaries, Rent, Utilities, Logistics)</td>
                <td class="tw-text-right tw-font-semibold tw-text-red-600">-${{ number_format($cashFlow['operating_expense_outflows'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-6">
                    Inventory & Merchandise Purchases (Phone Stock / Accessories)
                    <small class="tw-block tw-text-xs tw-text-gray-500">* Working Capital Outflow &bull; Not Operating Expense</small>
                </td>
                <td class="tw-text-right tw-font-semibold tw-text-red-600">-${{ number_format($cashFlow['inventory_purchase_outflows'], 2) }}</td>
            </tr>
            <tr>
                <td class="tw-pl-6">Other Disbursements / Staff Advances / Fixed Assets</td>
                <td class="tw-text-right tw-font-semibold tw-text-red-600">-${{ number_format($cashFlow['other_outflows'], 2) }}</td>
            </tr>
            <tr class="tw-bg-red-100">
                <td class="tw-font-bold tw-text-red-950">TOTAL CASH & BANK OUTFLOWS</td>
                <td class="tw-text-right tw-font-bold tw-text-base tw-text-red-950">-${{ number_format($cashFlow['total_outflows'], 2) }}</td>
            </tr>

            <!-- Net Cash Flow -->
            <tr class="tw-bg-blue-100">
                <td class="tw-font-bold tw-text-lg tw-text-blue-950">NET CASH FLOW (Net Change in Liquid Reserves)</td>
                <td class="tw-text-right tw-font-bold tw-text-xl {{ $cashFlow['net_cash_flow'] >= 0 ? 'tw-text-green-900' : 'tw-text-red-900' }}">
                    {{ $cashFlow['net_cash_flow'] >= 0 ? '+$' : '-$' }}{{ number_format(abs($cashFlow['net_cash_flow']), 2) }}
                </td>
            </tr>
        </table>
    </div>
</div>
@endsection

