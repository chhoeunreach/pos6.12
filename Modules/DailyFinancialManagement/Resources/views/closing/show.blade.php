@extends('layouts.app')

@section('title', 'Daily Closing Statement - ' . $closing->closing_date->format('Y-m-d'))

@section('content')
<div class="content">
    <div class="tw-mb-4 tw-flex tw-justify-between tw-items-center no-print">
        <a href="{{ route('daily-financial.closing.index') }}" class="btn btn-default">
            <i class="fa fa-arrow-left"></i> Back to Closings List
        </a>
        <div class="tw-flex tw-gap-2">
            <button onclick="window.print();" class="btn btn-primary">
                <i class="fa fa-print"></i> Print Closing Statement
            </button>
            @if ($closing->closing_status !== 'locked')
                <form action="{{ route('daily-financial.closing.lock', $closing->id) }}" method="POST" onsubmit="return confirm('Lock this daily closing? All transactions for this date and branch will be frozen permanently.');">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-lock"></i> Lock Financial Period
                    </button>
                </form>
            @else
                <span class="btn btn-default disabled"><i class="fa fa-lock"></i> Period Locked</span>
            @endif
        </div>
    </div>

    <!-- Official Printable Document -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-p-8 tw-bg-white print:tw-p-0 print:tw-shadow-none">
        <!-- Document Header -->
        <div class="tw-text-center tw-border-b tw-border-gray-200 tw-pb-6 tw-mb-6">
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-uppercase">KNEAYERNG PHONE SHOP</h2>
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-700 tw-mt-1">DAILY FINANCIAL CLOSING STATEMENT</h3>
            <p class="tw-text-sm tw-text-gray-500">
                Branch: <strong>{{ $closing->location ? $closing->location->name : 'Consolidated' }}</strong> &bull;
                Date: <strong>{{ $closing->closing_date->format('l, d F Y') }}</strong> &bull;
                Status: <span class="badge {{ $closing->closing_status === 'locked' ? 'bg-navy' : 'bg-green' }}">{{ strtoupper($closing->closing_status) }}</span>
            </p>
        </div>

        <div class="row">
            <!-- P&L Table -->
            <div class="col-md-6 col-xs-12">
                <h4 class="tw-font-bold tw-text-sm tw-text-gray-700 tw-uppercase tw-border-b tw-pb-2 tw-mb-4">
                    1. Profit & Loss Summary (Accrual Performance)
                </h4>
                <table class="table table-bordered tw-text-sm">
                    <tr>
                        <td>Gross POS Retail Sales</td>
                        <td class="tw-text-right tw-font-semibold">${{ number_format($closing->total_pos_sales, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Sales Returns & Refunds</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($closing->total_pos_returns, 2) }}</td>
                    </tr>
                    <tr class="tw-bg-gray-100">
                        <td class="tw-font-bold">Net Sales Revenue</td>
                        <td class="tw-text-right tw-font-bold">${{ number_format($closing->total_net_sales, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Cost of Goods Sold (COGS)</td>
                        <td class="tw-text-right tw-text-gray-600">-${{ number_format($closing->total_cogs, 2) }}</td>
                    </tr>
                    <tr class="tw-bg-teal-50">
                        <td class="tw-font-bold">Gross Commercial Profit</td>
                        <td class="tw-text-right tw-font-bold tw-text-teal-900">${{ number_format($closing->total_gross_profit, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Installment Interest Recognized</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($closing->total_loan_interest, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Other Recognized Income</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($closing->total_manual_income, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Operating Expenses (OPEX)</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($closing->total_operating_expenses, 2) }}</td>
                    </tr>
                    <tr class="tw-bg-green-100">
                        <td class="tw-font-bold tw-text-base">Net Operating Profit</td>
                        <td class="tw-text-right tw-font-bold tw-text-base tw-text-green-900">${{ number_format($closing->net_profit, 2) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Cash & Bank Reconciliation -->
            <div class="col-md-6 col-xs-12">
                <h4 class="tw-font-bold tw-text-sm tw-text-gray-700 tw-uppercase tw-border-b tw-pb-2 tw-mb-4">
                    2. Cash & Bank Balance Reconciliation
                </h4>
                <table class="table table-bordered tw-text-sm">
                    <tr class="tw-bg-gray-50">
                        <th colspan="2">Cash in Hand (Vaults & Drawers)</th>
                    </tr>
                    <tr>
                        <td>Opening Cash Balance</td>
                        <td class="tw-text-right">${{ number_format($closing->opening_cash_balance, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Cash Inflow (Sales & Debt Recoveries)</td>
                        <td class="tw-text-right tw-text-green-700">+${{ number_format($closing->total_cash_inflow, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Cash Outflow (Expenses & Supplier Payments)</td>
                        <td class="tw-text-right tw-text-red-600">-${{ number_format($closing->total_cash_outflow, 2) }}</td>
                    </tr>
                    <tr class="tw-bg-yellow-50">
                        <td class="tw-font-bold">Expected Cash Balance</td>
                        <td class="tw-text-right tw-font-bold">${{ number_format($closing->expected_cash_balance, 2) }}</td>
                    </tr>
                    <tr class="tw-bg-yellow-100">
                        <td class="tw-font-bold">Actual Counted Cash</td>
                        <td class="tw-text-right tw-font-bold tw-text-base">${{ number_format($closing->actual_cash_counted, 2) }}</td>
                    </tr>
                    <tr class="{{ $closing->cash_difference == 0 ? 'tw-bg-green-50' : 'tw-bg-red-50' }}">
                        <td class="tw-font-bold">Cash Variance (Surplus / Shortage)</td>
                        <td class="tw-text-right tw-font-bold {{ $closing->cash_difference == 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                            {{ $closing->cash_difference >= 0 ? '+$' : '-$' }}{{ number_format(abs($closing->cash_difference), 2) }}
                        </td>
                    </tr>

                    <tr class="tw-bg-gray-50">
                        <th colspan="2">Bank & Electronic Accounts</th>
                    </tr>
                    <tr>
                        <td>Expected Bank Total</td>
                        <td class="tw-text-right">${{ number_format($closing->expected_bank_balance, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Actual Reconciled Bank Total</td>
                        <td class="tw-text-right tw-font-bold">${{ number_format($closing->actual_bank_balance, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Bank Variance</td>
                        <td class="tw-text-right tw-font-bold {{ $closing->bank_difference == 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                            {{ $closing->bank_difference >= 0 ? '+$' : '-$' }}{{ number_format(abs($closing->bank_difference), 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        @if ($closing->closing_notes)
            <div class="tw-mt-4 tw-p-4 tw-bg-gray-50 tw-rounded-lg tw-border tw-border-gray-200">
                <span class="tw-text-xs tw-font-bold tw-text-gray-500 tw-uppercase">Closing Remarks / Discrepancy Explanation:</span>
                <p class="tw-text-sm tw-text-gray-800 tw-mt-1">{{ $closing->closing_notes }}</p>
            </div>
        @endif

        <!-- Sign-Off Section -->
        <div class="tw-grid tw-grid-cols-3 tw-gap-8 tw-mt-12 tw-pt-8 tw-border-t tw-border-gray-300 tw-text-center">
            <div>
                <p class="tw-text-xs tw-font-bold tw-text-gray-500 tw-uppercase">Prepared By (Cashier)</p>
                <div class="tw-h-16"></div>
                <p class="tw-font-semibold tw-text-gray-900">{{ $closing->closer ? $closing->closer->user_full_name : 'Cashier' }}</p>
                <span class="tw-text-xs tw-text-gray-400">{{ $closing->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div>
                <p class="tw-text-xs tw-font-bold tw-text-gray-500 tw-uppercase">Reviewed By (Accountant)</p>
                <div class="tw-h-16"></div>
                <p class="tw-font-semibold tw-text-gray-900">Accounting Officer</p>
                <span class="tw-text-xs tw-text-gray-400">Signature & Date</span>
            </div>
            <div>
                <p class="tw-text-xs tw-font-bold tw-text-gray-500 tw-uppercase">Approved By (Branch Manager / Director)</p>
                <div class="tw-h-16"></div>
                <p class="tw-font-semibold tw-text-gray-900">{{ $closing->approver ? $closing->approver->user_full_name : 'Pending Approval' }}</p>
                <span class="tw-text-xs tw-text-gray-400">{{ $closing->approved_at ? $closing->approved_at->format('Y-m-d H:i') : '' }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

