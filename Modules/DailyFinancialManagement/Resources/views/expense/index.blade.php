@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.expense_management'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.expense_management')])

    <!-- Filters -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.expense.index') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
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
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Department</label>
                    <select name="department_id" class="form-control select2">
                        <option value="">-- All Departments --</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" {{ request('department_id') == $d->id ? 'selected' : '' }}>{{ $d->dept_name }}</option>
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
                        <option value="reviewed" {{ request('approval_status') == 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                        <option value="approved" {{ request('approval_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="reversed" {{ request('approval_status') == 'reversed' ? 'selected' : '' }}>Reversed</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('daily-financial.expense.index') }}" class="btn btn-default">Reset</a>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border tw-flex tw-justify-between tw-items-center">
            <h3 class="box-title tw-text-base tw-font-bold tw-text-gray-900">
                Operating Expenses (Total: ${{ number_format($totalAmount, 2) }})
            </h3>
            <div class="box-tools">
                <a href="{{ route('daily-financial.expense.create') }}" class="btn btn-danger btn-sm">
                    <i class="fa fa-plus"></i> {{ __('dailyfinancialmanagement::lang.add_expense') }}
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
                            <th>Payment Method</th>
                            <th class="tw-text-right">Amount</th>
                            <th class="tw-text-center">Status</th>
                            <th class="tw-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $exp)
                            <tr>
                                <td>{{ $exp->transaction_date->format('Y-m-d') }}</td>
                                <td class="tw-font-mono tw-font-semibold">
                                    <a href="{{ route('daily-financial.expense.show', $exp->id) }}">{{ $exp->ref_no }}</a>
                                </td>
                                <td>{{ $exp->category ? $exp->category->display_name : 'N/A' }}</td>
                                <td>{{ $exp->location ? $exp->location->name : 'N/A' }}</td>
                                <td>
                                    {{ Str::limit($exp->description, 35) }}
                                    @if ($exp->attachments->count())
                                        <i class="fa fa-paperclip tw-text-blue-500" title="Has attachment"></i>
                                    @endif
                                </td>
                                <td><span class="label label-default">{{ strtoupper($exp->payment_method) }}</span></td>
                                <td class="tw-text-right tw-font-bold tw-text-red-600">
                                    -${{ number_format($exp->amount_in_base_currency, 2) }}
                                    @if ($exp->currency === 'KHR')
                                        <small class="tw-text-gray-400 tw-block">({{ number_format($exp->amount) }} ៛)</small>
                                    @endif
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $exp->approval_status === 'approved' ? 'bg-green' : ($exp->approval_status === 'pending' ? 'bg-orange' : ($exp->approval_status === 'reviewed' ? 'bg-blue' : 'bg-gray')) }}">
                                        {{ ucfirst($exp->approval_status) }}
                                    </span>
                                </td>
                                <td class="tw-text-center">
                                    <a href="{{ route('daily-financial.expense.show', $exp->id) }}" class="btn btn-xs btn-default" title="View details">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="tw-text-center tw-text-gray-400 tw-py-6">No expense records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $expenses->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

