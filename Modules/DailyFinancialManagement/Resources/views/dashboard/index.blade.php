@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.dashboard'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.dashboard')])

    <!-- Filters Bar -->
    <div class="box box-solid no-print tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.dashboard') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-flex-1 tw-min-w-[180px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600 tw-uppercase">{{ __('dailyfinancialmanagement::lang.branch') }}</label>
                    <select name="location_id" class="form-control select2 tw-w-full">
                        <option value="">-- All Active Branches --</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $locationId == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600 tw-uppercase">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control" />
                </div>

                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600 tw-uppercase">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control" />
                </div>

                <div class="tw-flex tw-gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-filter"></i> {{ __('dailyfinancialmanagement::lang.filter') }}
                    </button>
                    <a href="{{ route('daily-financial.dashboard') }}" class="btn btn-default">
                        {{ __('dailyfinancialmanagement::lang.reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive KPI Row 1: Core Commercial Performance -->
    <div class="row">
        <!-- Net Sales Revenue -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm tw-border tw-border-blue-100">
                <span class="info-box-icon bg-blue tw-rounded-l-xl"><i class="fa fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.total_net_sales') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-blue-900">${{ number_format($pnl['net_sales'], 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">
                        Gross: ${{ number_format($pnl['gross_sales'], 2) }} &bull; Ret: ${{ number_format($pnl['sales_returns'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Gross Profit & COGS -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm tw-border tw-border-teal-100">
                <span class="info-box-icon bg-teal tw-rounded-l-xl"><i class="fa fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.gross_profit') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-teal-900">${{ number_format($pnl['gross_profit'], 2) }}</span>
                    <div class="tw-text-xs tw-text-teal-600 tw-mt-1">
                        Margin: <strong>{{ $pnl['gross_margin_percent'] }}%</strong> &bull; COGS: ${{ number_format($pnl['cogs'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Operating Expenses (OPEX) -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm tw-border tw-border-red-100">
                <span class="info-box-icon bg-red tw-rounded-l-xl"><i class="fa fa-credit-card"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.total_operating_expenses') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-red-900">${{ number_format($pnl['operating_expenses'], 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">
                        Salaries, Rent, Utilities, Logistics
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Operating Profit -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm tw-border tw-border-green-100">
                <span class="info-box-icon bg-green tw-rounded-l-xl"><i class="fa fa-trophy"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.net_profit') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-green-900">${{ number_format($pnl['net_profit'], 2) }}</span>
                    <div class="tw-text-xs tw-text-green-700 tw-mt-1">
                        Net Margin: <strong>{{ $pnl['net_margin_percent'] }}%</strong> &bull; EBIT
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Executive KPI Row 2: Liquidity, Collections & Controls -->
    <div class="row">
        <!-- Cash Balance -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-yellow tw-rounded-l-xl"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.cash_balance') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-yellow-900">${{ number_format($cashBalance, 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">Physical Vault & Registers</div>
                </div>
            </div>
        </div>

        <!-- Bank Balance -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-purple tw-rounded-l-xl"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.bank_balance') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-purple-900">${{ number_format($bankBalance, 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">ABA, ACLEDA, Wing</div>
                </div>
            </div>
        </div>

        <!-- Total Actual Cash Inflows (Collections) -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-aqua tw-rounded-l-xl"><i class="fa fa-sign-in"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.total_collections') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-blue-900">${{ number_format($cashFlow['total_inflows'], 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">
                        POS: ${{ number_format($cashFlow['pos_cash_collections'] + $cashFlow['pos_bank_collections'], 2) }} &bull; Loan: ${{ number_format($cashFlow['loan_principal_collections'] + $cashFlow['loan_interest_collections'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Approvals & Reconciliation Variance -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-orange tw-rounded-l-xl"><i class="fa fa-shield"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">{{ __('dailyfinancialmanagement::lang.pending_approvals') }}</span>
                    <span class="info-box-number tw-text-2xl tw-text-orange-900">{{ $pendingCount }} <small>items (${{ number_format($pendingAmount, 2) }})</small></span>
                    <div class="tw-text-xs {{ $reconciliationDiff != 0 ? 'tw-text-red-600 tw-font-bold' : 'tw-text-green-600' }} tw-mt-1">
                        Cash Variance: {{ $reconciliationDiff >= 0 ? '+$' : '-$' }}{{ number_format(abs($reconciliationDiff), 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounting vs Cash Flow Separation Banner -->
    <div class="box box-solid tw-bg-gradient-to-r tw-from-blue-900 tw-to-indigo-900 tw-text-white tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-body tw-p-6">
            <h3 class="tw-text-lg tw-font-bold tw-text-white tw-mb-2">
                <i class="fa fa-info-circle tw-mr-1"></i> {{ __('dailyfinancialmanagement::lang.accounting_vs_cashflow') }}
            </h3>
            <p class="tw-text-sm tw-text-blue-200 tw-max-w-4xl">
                <strong>Accounting Rule:</strong> Installment principal collections ($<strong>{{ number_format($cashFlow['loan_principal_collections'], 2) }}</strong>) and inventory purchases ($<strong>{{ number_format($cashFlow['inventory_purchase_outflows'], 2) }}</strong>) represent working capital cash movements and are NOT commercial revenue or operating expenses. Only recognized installment interest ($<strong>{{ number_format($pnl['loan_interest_income'], 2) }}</strong>) is included in P&L Profit.
            </p>
            <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-4 tw-mt-4 tw-pt-4 tw-border-t tw-border-blue-800">
                <div>
                    <span class="tw-text-xs tw-text-blue-300 tw-uppercase">P&L Net Profit (Accrual)</span>
                    <p class="tw-text-xl tw-font-bold tw-text-green-400">${{ number_format($pnl['net_profit'], 2) }}</p>
                </div>
                <div>
                    <span class="tw-text-xs tw-text-blue-300 tw-uppercase">Net Cash Flow (Liquidity)</span>
                    <p class="tw-text-xl tw-font-bold {{ $cashFlow['net_cash_flow'] >= 0 ? 'tw-text-green-400' : 'tw-text-red-400' }}">
                        ${{ number_format($cashFlow['net_cash_flow'], 2) }}
                    </p>
                </div>
                <div>
                    <span class="tw-text-xs tw-text-blue-300 tw-uppercase">Loan Principal Inflow</span>
                    <p class="tw-text-xl tw-font-bold tw-text-blue-200">${{ number_format($cashFlow['loan_principal_collections'], 2) }}</p>
                </div>
                <div>
                    <span class="tw-text-xs tw-text-blue-300 tw-uppercase">Inventory Cash Outflow</span>
                    <p class="tw-text-xl tw-font-bold tw-text-orange-300">${{ number_format($cashFlow['inventory_purchase_outflows'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Performance Comparison Cards -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold tw-text-gray-900">
                <i class="fa fa-building-o tw-mr-1"></i> {{ __('dailyfinancialmanagement::lang.branch_performance') }}
            </h3>
            <div class="box-tools pull-right">
                <a href="{{ route('daily-financial.reports.branch-comparison') }}" class="btn btn-xs btn-default">
                    View Full Comparison <i class="fa fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Branch</th>
                            <th class="tw-text-right">Net Sales</th>
                            <th class="tw-text-right">Gross Profit</th>
                            <th class="tw-text-right">OPEX</th>
                            <th class="tw-text-right">Net Profit</th>
                            <th class="tw-text-right">Net Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branchPerformance as $bp)
                            @if ($bp['net_sales'] > 0 || $bp['operating_expenses'] > 0)
                                <tr>
                                    <td class="tw-font-medium">{{ $bp['name'] }}</td>
                                    <td class="tw-text-right tw-font-semibold">${{ number_format($bp['net_sales'], 2) }}</td>
                                    <td class="tw-text-right tw-text-teal-700">${{ number_format($bp['gross_profit'], 2) }}</td>
                                    <td class="tw-text-right tw-text-red-600">${{ number_format($bp['operating_expenses'], 2) }}</td>
                                    <td class="tw-text-right tw-font-bold {{ $bp['net_profit'] >= 0 ? 'tw-text-green-700' : 'tw-text-red-700' }}">
                                        ${{ number_format($bp['net_profit'], 2) }}
                                    </td>
                                    <td class="tw-text-right">
                                        <span class="badge {{ $bp['net_margin_percent'] >= 10 ? 'bg-green' : 'bg-yellow' }}">
                                            {{ $bp['net_margin_percent'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="tw-text-center tw-text-gray-400 tw-py-4">No branch transactions for selected dates.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Financial Transactions Feed -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold tw-text-gray-900">
                <i class="fa fa-list-alt tw-mr-1"></i> Recent Financial Transactions
            </h3>
            <div class="box-tools pull-right">
                <a href="{{ route('daily-financial.expense.index') }}" class="btn btn-xs btn-default">Expenses</a>
                <a href="{{ route('daily-financial.income.index') }}" class="btn btn-xs btn-default">Incomes</a>
            </div>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Date</th>
                            <th>Ref No</th>
                            <th>Type</th>
                            <th>Branch</th>
                            <th>Description</th>
                            <th class="tw-text-right">Amount</th>
                            <th class="tw-text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTransactions as $tx)
                            <tr>
                                <td>{{ $tx->transaction_date->format('Y-m-d') }}</td>
                                <td class="tw-font-mono tw-font-semibold">{{ $tx->ref_no }}</td>
                                <td>
                                    @if ($tx->transaction_type === 'pos_sale')
                                        <span class="label label-primary">POS Sale</span>
                                    @elseif ($tx->transaction_type === 'income')
                                        <span class="label label-success">Income</span>
                                    @elseif ($tx->transaction_type === 'expense')
                                        <span class="label label-danger">Expense</span>
                                    @elseif ($tx->transaction_type === 'loan_interest')
                                        <span class="label label-info">Loan Interest</span>
                                    @elseif ($tx->transaction_type === 'loan_principal')
                                        <span class="label label-default">Loan Principal</span>
                                    @else
                                        <span class="label label-default">{{ $tx->transaction_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $tx->location ? $tx->location->name : 'N/A' }}</td>
                                <td>{{ Str::limit($tx->description, 40) }}</td>
                                <td class="tw-text-right tw-font-bold {{ in_array($tx->transaction_type, ['expense', 'pos_return']) ? 'tw-text-red-600' : 'tw-text-green-700' }}">
                                    {{ in_array($tx->transaction_type, ['expense', 'pos_return']) ? '-$' : '+$' }}{{ number_format($tx->amount_in_base_currency, 2) }}
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $tx->approval_status === 'approved' ? 'bg-green' : ($tx->approval_status === 'pending' ? 'bg-orange' : 'bg-gray') }}">
                                        {{ ucfirst($tx->approval_status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="tw-text-center tw-text-gray-400 tw-py-4">No recent transactions recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

