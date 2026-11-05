@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.income_management'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.income_management')])

    <!-- Filters -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.income.index') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[150px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Start Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" />
                </div>
                <div class="tw-w-[150px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">End Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control" />
                </div>
                <div class="tw-min-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Branch</label>
                    <select name="location_id" class="form-control select2">
                        <option value="">-- All Branches --</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tw-min-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Category</label>
                    <select name="category_id" class="form-control select2">
                        <option value="">-- All Categories --</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tw-w-[130px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Status</label>
                    <select name="approval_status" class="form-control">
                        <option value="">-- All --</option>
                        <option value="draft" {{ request('approval_status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('approval_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('approval_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('daily-financial.income.index') }}" class="btn btn-default">Reset</a>
            </form>
        </div>
    </div>

    <!-- Income Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border tw-flex tw-justify-between tw-items-center">
            <h3 class="box-title tw-text-base tw-font-bold tw-text-gray-900">
                Income Transactions (Total: ${{ number_format($totalAmount, 2) }})
            </h3>
            <div class="box-tools">
                <a href="{{ route('daily-financial.income.create') }}" class="btn btn-success btn-sm">
                    <i class="fa fa-plus"></i> {{ __('dailyfinancialmanagement::lang.add_income') }}
                </a>
            </div>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Date</th>
                            <th>Ref No</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Description</th>
                            <th>Method / Account</th>
                            <th class="tw-text-right">Amount</th>
                            <th class="tw-text-center">Status</th>
                            <th class="tw-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($incomes as $inc)
                            <tr>
                                <td>{{ $inc->transaction_date->format('Y-m-d') }}</td>
                                <td class="tw-font-mono tw-font-semibold">
                                    <a href="{{ route('daily-financial.income.show', $inc->id) }}">{{ $inc->ref_no }}</a>
                                </td>
                                <td>{{ $inc->category ? $inc->category->display_name : 'Loan Interest' }}</td>
                                <td>{{ $inc->location ? $inc->location->name : 'N/A' }}</td>
                                <td>{{ Str::limit($inc->description, 35) }}</td>
                                <td>
                                    <span class="label label-info">{{ strtoupper($inc->payment_method) }}</span>
                                    <small class="tw-text-gray-500 tw-block">{{ $inc->account ? $inc->account->account_name : '' }}</small>
                                </td>
                                <td class="tw-text-right tw-font-bold tw-text-green-700">
                                    +${{ number_format($inc->amount_in_base_currency, 2) }}
                                    @if ($inc->currency === 'KHR')
                                        <small class="tw-text-gray-400 tw-block">({{ number_format($inc->amount) }} ៛)</small>
                                    @endif
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $inc->approval_status === 'approved' ? 'bg-green' : ($inc->approval_status === 'pending' ? 'bg-orange' : 'bg-gray') }}">
                                        {{ ucfirst($inc->approval_status) }}
                                    </span>
                                </td>
                                <td class="tw-text-center">
                                    <a href="{{ route('daily-financial.income.show', $inc->id) }}" class="btn btn-xs btn-default" title="View details">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="tw-text-center tw-text-gray-400 tw-py-6">No income records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $incomes->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

