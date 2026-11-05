@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.daily_closing'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.daily_closing')])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border tw-flex tw-justify-between tw-items-center">
            <h3 class="box-title tw-text-base tw-font-bold">Daily Financial Closings Record</h3>
            <div class="box-tools">
                <a href="{{ route('daily-financial.closing.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-calendar-check-o"></i> {{ __('dailyfinancialmanagement::lang.run_daily_closing') }}
                </a>
            </div>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Closing Date</th>
                            <th>Branch</th>
                            <th class="tw-text-right">Net Sales</th>
                            <th class="tw-text-right">Gross Profit</th>
                            <th class="tw-text-right">OPEX</th>
                            <th class="tw-text-right">Net Profit</th>
                            <th class="tw-text-right">Expected Cash</th>
                            <th class="tw-text-right">Counted Cash</th>
                            <th class="tw-text-right">Variance</th>
                            <th class="tw-text-center">Status</th>
                            <th class="tw-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($closings as $c)
                            <tr>
                                <td class="tw-font-bold">
                                    <a href="{{ route('daily-financial.closing.show', $c->id) }}">{{ $c->closing_date->format('Y-m-d') }}</a>
                                </td>
                                <td>{{ $c->location ? $c->location->name : 'All' }}</td>
                                <td class="tw-text-right">${{ number_format($c->total_net_sales, 2) }}</td>
                                <td class="tw-text-right tw-text-teal-700">${{ number_format($c->total_gross_profit, 2) }}</td>
                                <td class="tw-text-right tw-text-red-600">${{ number_format($c->total_operating_expenses, 2) }}</td>
                                <td class="tw-text-right tw-font-bold {{ $c->net_profit >= 0 ? 'tw-text-green-700' : 'tw-text-red-600' }}">
                                    ${{ number_format($c->net_profit, 2) }}
                                </td>
                                <td class="tw-text-right">${{ number_format($c->expected_cash_balance, 2) }}</td>
                                <td class="tw-text-right tw-font-bold">${{ number_format($c->actual_cash_counted, 2) }}</td>
                                <td class="tw-text-right tw-font-bold {{ $c->cash_difference == 0 ? 'tw-text-green-600' : 'tw-text-red-600' }}">
                                    {{ $c->cash_difference >= 0 ? '+$' : '-$' }}{{ number_format(abs($c->cash_difference), 2) }}
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $c->closing_status === 'locked' ? 'bg-navy' : ($c->closing_status === 'approved' ? 'bg-green' : 'bg-orange') }}">
                                        {{ strtoupper($c->closing_status) }}
                                    </span>
                                </td>
                                <td class="tw-text-center">
                                    <a href="{{ route('daily-financial.closing.show', $c->id) }}" class="btn btn-xs btn-default">
                                        <i class="fa fa-file-text-o"></i> View Statement
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="tw-text-center tw-text-gray-400 tw-py-6">No daily closings recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $closings->links() }}
        </div>
    </div>
</div>
@endsection

