@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.run_daily_closing'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Daily Financial Closing Wizard'])

    @if ($pendingTransactionsCount > 0)
        <div class="alert alert-warning alert-dismissible tw-rounded-xl tw-shadow-sm">
            <h4><i class="icon fa fa-warning"></i> Warning: Pending Transactions Found</h4>
            There are <strong>{{ $pendingTransactionsCount }}</strong> unapproved transactions for this branch and date. Please review and approve them in the <a href="{{ route('daily-financial.approvals.index') }}" class="tw-underline tw-font-bold">Approvals Queue</a> before finalizing the daily closing.
        </div>
    @endif

    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.closing.create') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[180px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Closing Date</label>
                    <input type="date" name="closing_date" value="{{ $closingDate }}" class="form-control" />
                </div>
                <div class="tw-min-w-[200px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Branch Location</label>
                    <select name="location_id" class="form-control select2">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" {{ $locationId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> Load System Metrics</button>
            </form>
        </div>
    </div>

    <form action="{{ route('daily-financial.closing.store') }}" method="POST">
        @csrf
        <input type="hidden" name="closing_date" value="{{ $closingDate }}" />
        <input type="hidden" name="business_location_id" value="{{ $locationId }}" />

        <div class="row">
            <!-- Step 1 & 2: Theoretical Metrics -->
            <div class="col-md-6">
                <!-- P&L Summary Card -->
                <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6">
                    <div class="box-header with-border">
                        <h3 class="box-title tw-font-bold">Step 1: Computed Commercial P&L for {{ $closingDate }}</h3>
                    </div>
                    <div class="box-body tw-p-4">
                        <table class="table table-bordered tw-text-sm">
                            <tr>
                                <td>Gross POS Sales</td>
                                <td class="tw-text-right tw-font-semibold">${{ number_format($pnl['gross_sales'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Sales Returns</td>
                                <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['sales_returns'], 2) }}</td>
                            </tr>
                            <tr class="tw-bg-blue-50">
                                <td class="tw-font-bold">Net Sales Revenue</td>
                                <td class="tw-text-right tw-font-bold tw-text-blue-900">${{ number_format($pnl['net_sales'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Cost of Goods Sold (COGS)</td>
                                <td class="tw-text-right tw-text-gray-600">-${{ number_format($pnl['cogs'], 2) }}</td>
                            </tr>
                            <tr class="tw-bg-teal-50">
                                <td class="tw-font-bold">Gross Commercial Profit</td>
                                <td class="tw-text-right tw-font-bold tw-text-teal-900">${{ number_format($pnl['gross_profit'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Recognized Loan Interest Income</td>
                                <td class="tw-text-right tw-text-green-700">+${{ number_format($pnl['loan_interest_income'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Operating Expenses (OPEX)</td>
                                <td class="tw-text-right tw-text-red-600">-${{ number_format($pnl['operating_expenses'], 2) }}</td>
                            </tr>
                            <tr class="tw-bg-green-50">
                                <td class="tw-font-bold tw-text-base">Net Operating Profit (EBIT)</td>
                                <td class="tw-text-right tw-font-bold tw-text-base tw-text-green-800">${{ number_format($pnl['net_profit'], 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Expected Cash & Bank Balance Card -->
                <div class="box box-solid tw-rounded-xl tw-shadow-sm">
                    <div class="box-header with-border">
                        <h3 class="box-title tw-font-bold">Step 2: System Calculated Balances</h3>
                    </div>
                    <div class="box-body tw-p-4">
                        <div class="tw-grid tw-grid-cols-2 tw-gap-4">
                            <div class="tw-p-4 tw-bg-yellow-50 tw-rounded-lg tw-border tw-border-yellow-200">
                                <span class="tw-text-xs tw-text-yellow-800 tw-font-bold tw-uppercase">Expected Cash in Hand</span>
                                <p class="tw-text-2xl tw-font-bold tw-text-yellow-900 tw-mt-1" id="expected-cash-display">
                                    ${{ number_format($reconciliation['summary']['expected_cash'], 2) }}
                                </p>
                                <span class="tw-text-xs tw-text-yellow-700">Opening: ${{ number_format($reconciliation['summary']['opening_cash'], 2) }} &bull; In: ${{ number_format($reconciliation['summary']['cash_inflow'], 2) }} &bull; Out: ${{ number_format($reconciliation['summary']['cash_outflow'], 2) }}</span>
                            </div>

                            <div class="tw-p-4 tw-bg-purple-50 tw-rounded-lg tw-border tw-border-purple-200">
                                <span class="tw-text-xs tw-text-purple-800 tw-font-bold tw-uppercase">Expected Bank Total</span>
                                <p class="tw-text-2xl tw-font-bold tw-text-purple-900 tw-mt-1">
                                    ${{ number_format($reconciliation['summary']['expected_bank'], 2) }}
                                </p>
                                <span class="tw-text-xs tw-text-purple-700">Inflows: ${{ number_format($reconciliation['summary']['bank_inflow'], 2) }} &bull; Outflows: ${{ number_format($reconciliation['summary']['bank_outflow'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3 & 4: Physical Count & Cash Calculator -->
            <div class="col-md-6">
                <div class="box box-solid tw-rounded-xl tw-shadow-sm">
                    <div class="box-header with-border">
                        <h3 class="box-title tw-font-bold">Step 3: Physical Cash Counting (Denominations)</h3>
                    </div>
                    <div class="box-body tw-p-4">
                        <h4 class="tw-text-xs tw-font-bold tw-text-gray-500 tw-uppercase tw-mb-2">USD Banknotes ($)</h4>
                        <div class="row">
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">$100 notes</label>
                                <input type="number" min="0" name="denominations[usd_100]" class="form-control denom-input" data-val="100" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">$50 notes</label>
                                <input type="number" min="0" name="denominations[usd_50]" class="form-control denom-input" data-val="50" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">$20 notes</label>
                                <input type="number" min="0" name="denominations[usd_20]" class="form-control denom-input" data-val="20" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">$10 notes</label>
                                <input type="number" min="0" name="denominations[usd_10]" class="form-control denom-input" data-val="10" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">$5 notes</label>
                                <input type="number" min="0" name="denominations[usd_5]" class="form-control denom-input" data-val="5" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">$1 notes</label>
                                <input type="number" min="0" name="denominations[usd_1]" class="form-control denom-input" data-val="1" placeholder="0" />
                            </div>
                        </div>

                        <h4 class="tw-text-xs tw-font-bold tw-text-gray-500 tw-uppercase tw-mb-2 tw-mt-2">Khmer Riel Banknotes (៛) - Rate 4,100</h4>
                        <div class="row">
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">50,000 ៛</label>
                                <input type="number" min="0" name="denominations[khr_50000]" class="form-control denom-khr" data-khr="50000" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">20,000 ៛</label>
                                <input type="number" min="0" name="denominations[khr_20000]" class="form-control denom-khr" data-khr="20000" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">10,000 ៛</label>
                                <input type="number" min="0" name="denominations[khr_10000]" class="form-control denom-khr" data-khr="10000" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">5,000 ៛</label>
                                <input type="number" min="0" name="denominations[khr_5000]" class="form-control denom-khr" data-khr="5000" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">2,000 ៛</label>
                                <input type="number" min="0" name="denominations[khr_2000]" class="form-control denom-khr" data-khr="2000" placeholder="0" />
                            </div>
                            <div class="col-xs-4 form-group">
                                <label class="tw-text-xs">1,000 ៛</label>
                                <input type="number" min="0" name="denominations[khr_1000]" class="form-control denom-khr" data-khr="1000" placeholder="0" />
                            </div>
                        </div>

                        <hr />

                        <div class="form-group">
                            <label class="tw-font-bold">Total Physical Cash Counted ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="actual_cash_counted" id="actual_cash_counted" class="form-control input-lg tw-font-bold tw-text-lg" required value="{{ $reconciliation['summary']['expected_cash'] }}" />
                            <small class="text-muted">Auto-calculated from banknote counts above, or enter custom total.</small>
                        </div>

                        <div class="form-group">
                            <label class="tw-font-bold">Actual Total Bank Balance ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="actual_bank_balance" class="form-control input-lg tw-font-bold tw-text-lg" required value="{{ $reconciliation['summary']['expected_bank'] }}" />
                            <small class="text-muted">Reconciled with ABA Bank and ACLEDA statements.</small>
                        </div>

                        <div class="form-group">
                            <label class="tw-font-semibold">Closing & Reconciliation Remarks</label>
                            <textarea name="closing_notes" rows="3" class="form-control" placeholder="Explain any cash variance, notes on closing..."></textarea>
                        </div>
                    </div>

                    <div class="box-footer tw-p-4 tw-bg-gray-50 tw-flex tw-justify-between">
                        <a href="{{ route('daily-financial.closing.index') }}" class="btn btn-default">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa fa-check-circle"></i> Save Daily Closing Summary
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const usdInputs = document.querySelectorAll('.denom-input');
        const khrInputs = document.querySelectorAll('.denom-khr');
        const totalCashInput = document.getElementById('actual_cash_counted');

        function recalcCash() {
            let totalUsd = 0;
            usdInputs.forEach(input => {
                const count = parseInt(input.value) || 0;
                const multiplier = parseFloat(input.getAttribute('data-val')) || 0;
                totalUsd += count * multiplier;
            });

            let totalKhr = 0;
            khrInputs.forEach(input => {
                const count = parseInt(input.value) || 0;
                const multiplier = parseFloat(input.getAttribute('data-khr')) || 0;
                totalKhr += count * multiplier;
            });

            const khrInUsd = totalKhr / 4100.0;
            const grandTotal = totalUsd + khrInUsd;

            if (grandTotal > 0) {
                totalCashInput.value = grandTotal.toFixed(2);
            }
        }

        usdInputs.forEach(i => i.addEventListener('input', recalcCash));
        khrInputs.forEach(i => i.addEventListener('input', recalcCash));
    });
</script>
@endsection

