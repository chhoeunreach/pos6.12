@extends('layouts.app')

@section('title', 'Pending Transactions Report')

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Pending Transactions & Unclosed Items'])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border tw-flex tw-justify-between tw-items-center">
            <h3 class="box-title tw-text-base tw-font-bold text-warning">
                <i class="fa fa-clock-o"></i> Unapproved Financial Items ({{ $pending->count() }} records)
            </h3>
            <div class="box-tools">
                <a href="{{ route('daily-financial.approvals.index') }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-check-square-o"></i> Open Approval Queue
                </a>
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
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Description</th>
                            <th>Submitted By</th>
                            <th class="tw-text-right">Amount</th>
                            <th class="tw-text-center">Current Status</th>
                            <th class="tw-text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $p)
                            <tr>
                                <td>{{ $p->transaction_date->format('Y-m-d') }}</td>
                                <td class="tw-font-mono tw-font-bold">{{ $p->ref_no }}</td>
                                <td>
                                    <span class="label {{ $p->transaction_type === 'income' ? 'label-success' : 'label-danger' }}">
                                        {{ strtoupper($p->transaction_type) }}
                                    </span>
                                </td>
                                <td>{{ $p->category ? $p->category->display_name : 'N/A' }}</td>
                                <td>{{ $p->location ? $p->location->name : 'N/A' }}</td>
                                <td>{{ Str::limit($p->description, 40) }}</td>
                                <td>{{ $p->creator ? $p->creator->user_full_name : 'User' }}</td>
                                <td class="tw-text-right tw-font-bold {{ $p->transaction_type === 'income' ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($p->amount_in_base_currency, 2) }}
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $p->approval_status === 'reviewed' ? 'bg-blue' : 'bg-orange' }}">
                                        {{ ucfirst($p->approval_status) }}
                                    </span>
                                </td>
                                <td class="tw-text-center">
                                    <a href="{{ route($p->transaction_type === 'income' ? 'daily-financial.income.show' : 'daily-financial.expense.show', $p->id) }}" class="btn btn-xs btn-default">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="tw-text-center tw-text-gray-400 tw-py-6">Great! There are no pending unapproved transactions.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

