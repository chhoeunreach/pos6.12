@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.cash_bank_accounts'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.cash_bank_accounts')])

    <!-- Summary Balances -->
    <div class="row tw-mb-6">
        <div class="col-md-6">
            <div class="info-box tw-rounded-xl tw-shadow-sm tw-border tw-border-yellow-200">
                <span class="info-box-icon bg-yellow tw-rounded-l-xl"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">Total Cash on Hand (Vaults & Registers)</span>
                    <span class="info-box-number tw-text-3xl tw-text-yellow-900">${{ number_format($totalCash, 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">Physical Currency Liquidity</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-box tw-rounded-xl tw-shadow-sm tw-border tw-border-purple-200">
                <span class="info-box-icon bg-purple tw-rounded-l-xl"><i class="fa fa-university"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text tw-font-semibold tw-text-gray-600">Total Bank & Digital Balances</span>
                    <span class="info-box-number tw-text-3xl tw-text-purple-900">${{ number_format($totalBank, 2) }}</span>
                    <div class="tw-text-xs tw-text-gray-500 tw-mt-1">ABA Bank, ACLEDA, Wing Accounts</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border tw-flex tw-justify-between tw-items-center">
            <h3 class="box-title tw-text-base tw-font-bold tw-text-gray-900">
                Financial Accounts & Registers
            </h3>
            <div class="box-tools tw-flex tw-gap-2">
                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#transferModal">
                    <i class="fa fa-exchange"></i> {{ __('dailyfinancialmanagement::lang.transfer_funds') }}
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addAccountModal">
                    <i class="fa fa-plus"></i> Add Account
                </button>
            </div>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Account Name</th>
                            <th>Type</th>
                            <th>Branch / Location</th>
                            <th>Bank / Provider</th>
                            <th>Account Number</th>
                            <th>Currency</th>
                            <th class="tw-text-right">Opening Balance</th>
                            <th class="tw-text-right">Current Balance</th>
                            <th class="tw-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $acc)
                            <tr>
                                <td class="tw-font-bold">
                                    <a href="{{ route('daily-financial.accounts.movements', $acc->id) }}">{{ $acc->account_name }}</a>
                                </td>
                                <td>
                                    <span class="label {{ in_array($acc->account_type, ['cash', 'petty_cash']) ? 'label-warning' : 'label-primary' }}">
                                        {{ strtoupper(str_replace('_', ' ', $acc->account_type)) }}
                                    </span>
                                </td>
                                <td>{{ $acc->location ? $acc->location->name : 'Consolidated / HQ' }}</td>
                                <td>{{ $acc->bank_name ?: '-' }}</td>
                                <td class="tw-font-mono">{{ $acc->account_number ?: '-' }}</td>
                                <td>{{ $acc->currency }}</td>
                                <td class="tw-text-right">${{ number_format($acc->opening_balance, 2) }}</td>
                                <td class="tw-text-right tw-text-base tw-font-bold {{ $acc->current_balance >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($acc->current_balance, 2) }}
                                </td>
                                <td class="tw-text-center">
                                    <a href="{{ route('daily-financial.accounts.movements', $acc->id) }}" class="btn btn-xs btn-default" title="View Statement Ledger">
                                        <i class="fa fa-list"></i> Statement
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="tw-text-center tw-text-gray-400 tw-py-6">No accounts registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal">
    <div class="modal-dialog">
        <form action="{{ route('daily-financial.accounts.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Create Financial Account</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control" placeholder="e.g. Main Cash Vault - HQ, ABA Main 000123" required />
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Account Type <span class="text-danger">*</span></label>
                            <select name="account_type" class="form-control" required>
                                <option value="cash">Cash Vault / Drawer</option>
                                <option value="bank">Bank Account</option>
                                <option value="digital_wallet">Digital Wallet (Wing / TrueMoney)</option>
                                <option value="petty_cash">Petty Cash Fund</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Branch Location</label>
                            <select name="business_location_id" class="form-control select2">
                                <option value="">-- HQ / All Branches --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Bank Name (if applicable)</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g. ABA Bank, ACLEDA" />
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Account Number</label>
                            <input type="text" name="account_number" class="form-control" placeholder="e.g. 000 123 456" />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Currency <span class="text-danger">*</span></label>
                            <select name="currency" class="form-control" required>
                                <option value="USD">USD ($)</option>
                                <option value="KHR">KHR (៛)</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Opening Balance <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="opening_balance" value="0.00" class="form-control" required />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Fund Transfer Modal -->
<div class="modal fade" id="transferModal">
    <div class="modal-dialog">
        <form action="{{ route('daily-financial.accounts.transfer') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Transfer Funds Between Accounts</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>From Account (Source) <span class="text-danger">*</span></label>
                        <select name="from_account_id" class="form-control select2" required>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->account_name }} [Balance: ${{ number_format($a->current_balance, 2) }}]</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>To Account (Destination) <span class="text-danger">*</span></label>
                        <select name="to_account_id" class="form-control select2" required>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->account_name }} [Balance: ${{ number_format($a->current_balance, 2) }}]</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Transfer Amount ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required placeholder="0.00" />
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="form-control" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Transfer Reference (Optional)</label>
                        <input type="text" name="ref_no" class="form-control" placeholder="e.g. DEPOSIT-ABA-01" />
                    </div>

                    <div class="form-group">
                        <label>Transfer Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="e.g. End of day cash deposit to bank..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Confirm Fund Transfer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

