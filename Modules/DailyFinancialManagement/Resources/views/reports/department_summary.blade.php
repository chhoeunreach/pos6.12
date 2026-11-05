@extends('layouts.app')

@section('title', 'Department Financial Summary')

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Department Financial Performance'])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.reports.department-summary') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control" />
                </div>
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control" />
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>

    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Department Operational Breakdown</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-bordered table-striped tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Department ID</th>
                            <th class="tw-text-right">Generated Revenue</th>
                            <th class="tw-text-right">Incurred Expenses</th>
                            <th class="tw-text-right">Net Contribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $d)
                            <tr>
                                <td class="tw-font-bold">Department #{{ $d->department_id }}</td>
                                <td class="tw-text-right tw-text-green-700 tw-font-semibold">${{ number_format($d->total_revenue, 2) }}</td>
                                <td class="tw-text-right tw-text-red-600">-${{ number_format($d->total_expense, 2) }}</td>
                                <td class="tw-text-right tw-font-bold {{ ($d->total_revenue - $d->total_expense) >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($d->total_revenue - $d->total_expense, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="tw-text-center tw-text-gray-400 tw-py-6">No departmental entries for selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

