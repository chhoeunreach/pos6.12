@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.approvals'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.approvals')])

    <!-- Tabs & Settings Shortcut -->
    <div class="tw-flex tw-justify-between tw-items-center tw-mb-4 no-print">
        <div class="btn-group">
            <a href="{{ route('daily-financial.approvals.index', ['status' => 'pending']) }}" class="btn btn-sm {{ $statusFilter === 'pending' ? 'btn-primary' : 'btn-default' }}">
                Pending Review / Approval
            </a>
            <a href="{{ route('daily-financial.approvals.index', ['status' => 'approved']) }}" class="btn btn-sm {{ $statusFilter === 'approved' ? 'btn-primary' : 'btn-default' }}">
                Approved History
            </a>
            <a href="{{ route('daily-financial.approvals.index', ['status' => 'all']) }}" class="btn btn-sm {{ $statusFilter === 'all' ? 'btn-primary' : 'btn-default' }}">
                All Records
            </a>
        </div>

        <a href="{{ route('daily-financial.approvals.settings') }}" class="btn btn-sm btn-default">
            <i class="fa fa-cogs"></i> Approval Limits & Settings
        </a>
    </div>

    <!-- Approvals Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Financial Approvals Queue</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Date</th>
                            <th>Ref No</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th class="tw-text-right">Amount</th>
                            <th class="tw-text-center">Status</th>
                            <th class="tw-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            <tr>
                                <td>{{ $tx->transaction_date->format('Y-m-d') }}</td>
                                <td class="tw-font-mono tw-font-bold">
                                    <a href="{{ route($tx->transaction_type === 'income' ? 'daily-financial.income.show' : 'daily-financial.expense.show', $tx->id) }}">
                                        {{ $tx->ref_no }}
                                    </a>
                                </td>
                                <td>
                                    <span class="label {{ $tx->transaction_type === 'income' ? 'label-success' : 'label-danger' }}">
                                        {{ strtoupper($tx->transaction_type) }}
                                    </span>
                                </td>
                                <td>{{ $tx->category ? $tx->category->display_name : 'General' }}</td>
                                <td>{{ $tx->location ? $tx->location->name : 'N/A' }}</td>
                                <td>{{ Str::limit($tx->description, 35) }}</td>
                                <td>{{ $tx->creator ? $tx->creator->user_full_name : 'User' }}</td>
                                <td class="tw-text-right tw-font-bold {{ $tx->transaction_type === 'income' ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($tx->amount_in_base_currency, 2) }}
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $tx->approval_status === 'approved' ? 'bg-green' : ($tx->approval_status === 'pending' ? 'bg-orange' : ($tx->approval_status === 'reviewed' ? 'bg-blue' : 'bg-gray')) }}">
                                        {{ ucfirst($tx->approval_status) }}
                                    </span>
                                </td>
                                <td class="tw-text-center">
                                    <div class="btn-group">
                                        @if ($tx->approval_status === 'pending')
                                            <form action="{{ route('daily-financial.approvals.action', $tx->id) }}" method="POST" class="tw-inline">
                                                @csrf
                                                <input type="hidden" name="action" value="review" />
                                                <button type="submit" class="btn btn-xs btn-warning" title="Review (Accountant)"><i class="fa fa-eye"></i></button>
                                            </form>
                                            <form action="{{ route('daily-financial.approvals.action', $tx->id) }}" method="POST" class="tw-inline">
                                                @csrf
                                                <input type="hidden" name="action" value="approve" />
                                                <button type="submit" class="btn btn-xs btn-success" title="Approve (Manager)"><i class="fa fa-check"></i></button>
                                            </form>
                                        @elseif ($tx->approval_status === 'reviewed')
                                            <form action="{{ route('daily-financial.approvals.action', $tx->id) }}" method="POST" class="tw-inline">
                                                @csrf
                                                <input type="hidden" name="action" value="approve" />
                                                <button type="submit" class="btn btn-xs btn-success" title="Final Approve"><i class="fa fa-check"></i></button>
                                            </form>
                                        @endif
                                        <a href="{{ route($tx->transaction_type === 'income' ? 'daily-financial.income.show' : 'daily-financial.expense.show', $tx->id) }}" class="btn btn-xs btn-default">
                                            <i class="fa fa-file-text-o"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="tw-text-center tw-text-gray-400 tw-py-6">No transactions in approval queue.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $transactions->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

