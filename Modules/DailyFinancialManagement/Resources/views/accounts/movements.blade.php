@extends('layouts.app')

@section('title', 'Statement - ' . $account->account_name)

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Statement Ledger: ' . $account->account_name])

    <!-- Account Details Card -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-body tw-p-6 tw-flex tw-flex-col md:tw-flex-row md:tw-items-center md:tw-justify-between tw-gap-4">
            <div>
                <h2 class="tw-text-xl tw-font-bold tw-text-gray-900">{{ $account->account_name }}</h2>
                <p class="tw-text-sm tw-text-gray-500">
                    Type: <span class="label label-primary">{{ strtoupper($account->account_type) }}</span> &bull;
                    Currency: <strong>{{ $account->currency }}</strong> &bull;
                    Bank: <strong>{{ $account->bank_name ?: 'N/A' }}</strong> &bull;
                    Account No: <strong>{{ $account->account_number ?: 'N/A' }}</strong>
                </p>
            </div>
            <div class="tw-text-right">
                <span class="tw-text-xs tw-text-gray-500 tw-uppercase">Live Running Balance</span>
                <p class="tw-text-3xl tw-font-bold {{ $account->current_balance >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                    ${{ number_format($account->current_balance, 2) }}
                </p>
            </div>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Transaction Movements & Running Balance</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Date</th>
                            <th>Ref No</th>
                            <th>Type</th>
                            <th>Description / Notes</th>
                            <th>Related Account</th>
                            <th class="tw-text-right">Balance Before</th>
                            <th class="tw-text-right">Movement Amount</th>
                            <th class="tw-text-right">Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $m)
                            <tr>
                                <td>{{ $m->movement_date->format('Y-m-d') }}</td>
                                <td class="tw-font-mono tw-font-semibold">{{ $m->ref_no ?: '-' }}</td>
                                <td>
                                    <span class="label {{ in_array($m->movement_type, ['inflow', 'transfer_in']) ? 'label-success' : 'label-danger' }}">
                                        {{ strtoupper(str_replace('_', ' ', $m->movement_type)) }}
                                    </span>
                                </td>
                                <td>{{ $m->notes ?: '-' }}</td>
                                <td>{{ $m->relatedAccount ? $m->relatedAccount->account_name : '-' }}</td>
                                <td class="tw-text-right">${{ number_format($m->balance_before, 2) }}</td>
                                <td class="tw-text-right tw-font-bold {{ in_array($m->movement_type, ['inflow', 'transfer_in']) ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    {{ in_array($m->movement_type, ['inflow', 'transfer_in']) ? '+$' : '-$' }}{{ number_format($m->amount, 2) }}
                                </td>
                                <td class="tw-text-right tw-font-semibold">${{ number_format($m->balance_after, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="tw-text-center tw-text-gray-400 tw-py-6">No movements recorded for this account.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $movements->links() }}
        </div>
    </div>
</div>
@endsection

