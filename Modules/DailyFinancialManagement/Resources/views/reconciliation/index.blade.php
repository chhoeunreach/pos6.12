@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.cash_reconciliation'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.cash_reconciliation')])

    <!-- Date & Location Bar -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.reconciliation.index') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[180px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Reconciliation Date</label>
                    <input type="date" name="date" value="{{ $date }}" class="form-control" />
                </div>
                <div class="tw-min-w-[200px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Branch Location</label>
                    <select name="location_id" class="form-control select2">
                        <option value="">-- All Branches --</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" {{ $locationId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> Check Balances</button>
            </form>
        </div>
    </div>

    <!-- Liquid Assets Overview Cards -->
    <div class="row tw-mb-6">
        <div class="col-md-4">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-yellow tw-rounded-l-xl"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">System Cash Balance</span>
                    <span class="info-box-number tw-text-2xl tw-text-yellow-900">${{ number_format($reconciliation['summary']['expected_cash'], 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">Inflows: ${{ number_format($reconciliation['summary']['cash_inflow'], 2) }} &bull; Outflows: ${{ number_format($reconciliation['summary']['cash_outflow'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-purple tw-rounded-l-xl"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">System Bank Balance</span>
                    <span class="info-box-number tw-text-2xl tw-text-purple-900">${{ number_format($reconciliation['summary']['expected_bank'], 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">Inflows: ${{ number_format($reconciliation['summary']['bank_inflow'], 2) }} &bull; Outflows: ${{ number_format($reconciliation['summary']['bank_outflow'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box tw-rounded-xl tw-shadow-sm">
                <span class="info-box-icon bg-green tw-rounded-l-xl"><i class="fa fa-shield"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">Total Liquid Reserves</span>
                    <span class="info-box-number tw-text-2xl tw-text-green-900">${{ number_format($reconciliation['summary']['total_liquid_assets'], 2) }}</span>
                    <div class="tw-text-xs tw-text-green-700 tw-mt-1">Verified Net Cash & Bank</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Balance Breakdown Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Individual Account Reconciliations for {{ $date }}</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Account Name</th>
                            <th>Bank / Provider</th>
                            <th>Account No</th>
                            <th class="tw-text-right">Opening Balance</th>
                            <th class="tw-text-right">Today Inflow</th>
                            <th class="tw-text-right">Today Outflow</th>
                            <th class="tw-text-right">Expected Closing</th>
                            <th class="tw-text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_merge($reconciliation['cash_accounts'], $reconciliation['bank_accounts']) as $acc)
                            <tr>
                                <td class="tw-font-bold">{{ $acc['name'] }}</td>
                                <td>{{ $acc['bank_name'] ?: 'Cash Drawer' }}</td>
                                <td class="tw-font-mono">{{ $acc['account_number'] ?: '-' }}</td>
                                <td class="tw-text-right">${{ number_format($acc['opening_balance'], 2) }}</td>
                                <td class="tw-text-right tw-text-green-700">+${{ number_format($acc['inflow'], 2) }}</td>
                                <td class="tw-text-right tw-text-red-600">-${{ number_format($acc['outflow'], 2) }}</td>
                                <td class="tw-text-right tw-font-bold tw-text-base">${{ number_format($acc['expected_closing'], 2) }}</td>
                                <td class="tw-text-center">
                                    <button type="button" class="btn btn-xs btn-primary open-reconcile-modal" data-id="{{ $acc['id'] }}" data-name="{{ $acc['name'] }}" data-system="{{ $acc['expected_closing'] }}">
                                        <i class="fa fa-balance-scale"></i> Reconcile
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal to Record Physical Count for Account -->
<div class="modal fade" id="reconcileModal">
    <div class="modal-dialog">
        <form action="{{ route('daily-financial.reconciliation.store') }}" method="POST">
            @csrf
            <input type="hidden" name="financial_account_id" id="modal_account_id" />
            <input type="hidden" name="reconciliation_date" value="{{ $date }}" />
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Reconcile Account: <span id="modal_account_name"></span></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Expected System Balance ($)</label>
                        <input type="text" id="modal_system_balance" class="form-control" readonly />
                    </div>
                    <div class="form-group">
                        <label>Actual Physical Counted / Bank Statement Balance ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="physical_balance" id="modal_physical_balance" class="form-control input-lg" required />
                    </div>
                    <div class="form-group">
                        <label>Variance / Discrepancy Explanation</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Explain difference if any..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Reconciliation</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        $('.open-reconcile-modal').on('click', function () {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const sys = $(this).data('system');

            $('#modal_account_id').val(id);
            $('#modal_account_name').text(name);
            $('#modal_system_balance').val('$' + parseFloat(sys).toFixed(2));
            $('#modal_physical_balance').val(parseFloat(sys).toFixed(2));

            $('#reconcileModal').modal('show');
        });
    });
</script>
@endsection

