@extends('layouts.app')
@section('title', 'Local Cashier Report')

@section('content')
<style>
    @font-face {
        font-family: 'KhmerFont';
        src: url('{{ asset("fonts/khmer/NotoSansKhmer-Regular.ttf") }}') format('truetype'),
             url('{{ asset("fonts/khmer/KhmerOSbattambang.ttf") }}') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
</style>
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Local Cashier Report</h1>
    @php
        $baseQuery = request()->query();
        $classicPlainQuery = array_merge($baseQuery, ['style_mode' => 'classic_plain']);
        $viewReportQuery = array_merge($baseQuery, ['style_mode' => 'view_report']);
        $businessLocationQuery = array_merge($baseQuery, ['style_mode' => 'business_location_report']);
    @endphp
    <div style="margin-top:10px;">
        <a href="{{ route('local-cashier-report.index') . '?' . http_build_query($classicPlainQuery) }}"
           class="btn report-tab-btn {{ ($filters['style_mode'] ?? 'classic_plain') === 'classic_plain' ? 'btn-primary' : 'btn-default' }}">
            Dashboard
        </a>
        <a href="{{ route('local-cashier-report.index') . '?' . http_build_query($viewReportQuery) }}"
           class="btn report-tab-btn {{ ($filters['style_mode'] ?? 'classic_plain') === 'view_report' ? 'btn-primary' : 'btn-default' }}">
            View Report
        </a>
        <a href="{{ route('local-cashier-report.index') . '?' . http_build_query($businessLocationQuery) }}"
           class="btn report-tab-btn {{ ($filters['style_mode'] ?? 'classic_plain') === 'business_location_report' ? 'btn-primary' : 'btn-default' }}">
            Business Location Report
        </a>
    </div>
</section>

<section class="content no-print {{ in_array($filters['style_mode'], ['classic','classic_plain','business_location_report']) ? 'classic-theme' : 'sheet-theme' }}" id="local_cashier_report_app" style="font-family: {{ $khmerFontFamily }};">
    @php
        $fmt = function ($value) {
            if ($value === null || abs((float) $value) < 0.00001) {
                return '$ -';
            }
            if ((float) $value < 0) {
                return '$ (' . number_format(abs((float) $value), 2) . ')';
            }
            return '$ ' . number_format((float) $value, 2);
        };
        $fmtStrict = function ($value) {
            $number = (float) ($value ?? 0);
            if ($number < 0) {
                return '$ (' . number_format(abs($number), 2) . ')';
            }
            return '$ ' . number_format($number, 2);
        };
        $expenseRows = collect($report['expense_detail_rows'] ?? []);
        $accessorySaleRows = collect($report['accessory_sale_detail_rows'] ?? []);
        $serviceSaleRows = collect($report['service_sale_detail_rows'] ?? []);
        $allSaleRows = collect($report['detail_rows'] ?? [])
            ->filter(fn ($row) => ($row['row_type'] ?? 'sale') === 'sale')
            ->merge($accessorySaleRows)
            ->merge($serviceSaleRows)
            ->values();
        $moduleDashboardSummary = function ($rows, $tabTarget) use ($report, $filters) {
            $rows = collect($rows ?? []);

            return $rows
                ->groupBy(fn ($row) => (string) ($row['location_name'] ?? 'N/A'))
                ->map(function ($locationRows, $locationName) use ($tabTarget, $report, $filters) {
                    $payments = [];
                    foreach ($report['payment_columns'] as $method) {
                        $payments[$method] = $locationRows->sum(fn ($row) => (float) data_get($row, 'payments.' . $method, 0));
                    }

                    return [
                        'label' => $locationName,
                        'tab_target' => $tabTarget,
                        'qty_total' => ($filters['qty_type'] ?? 'invoice_count') === 'sold_quantity'
                            ? $locationRows->sum(fn ($row) => (float) ($row['quantity'] ?? 0))
                            : $locationRows->pluck('transaction_id')->filter()->unique()->count(),
                        'total' => $locationRows->sum(fn ($row) => (float) ($row['line_total'] ?? 0)),
                        'paid' => $locationRows->sum(fn ($row) => (float) ($row['paid'] ?? 0)),
                        'due' => $locationRows->sum(fn ($row) => (float) ($row['due'] ?? 0)),
                        'payments' => $payments,
                    ];
                })
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        };
        $moduleDashboardRows = collect()
            ->merge($moduleDashboardSummary($accessorySaleRows, '#accessory_sales_detail_tab'))
            ->merge($moduleDashboardSummary($serviceSaleRows, '#service_sales_detail_tab'))
            ->values()
            ->all();
    @endphp
    <div class="local-filter-wrap">
        <a href="{{ route('local-cashier-report.index', ['style_mode' => 'classic_plain']) }}" class="btn btn-sm local-filter-reset">
            <i class="fa fa-refresh"></i> Reset
        </a>
    @component('components.filters', ['title' => __('report.filters')])
        <form method="get" action="{{ route('local-cashier-report.index') }}" class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Date Range</label>
                    <input type="text" id="date_range_picker" class="form-control" readonly>
                    <input type="hidden" name="start_date" id="start_date" value="{{ $filters['start_date'] }}">
                    <input type="hidden" name="end_date" id="end_date" value="{{ $filters['end_date'] }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Business Location</label>
                    <input type="text" id="location_preview" class="form-control" readonly
                           value="{{ $locations->whereIn('id', $filters['location_ids'])->pluck('name')->implode(', ') }}">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#location_modal" style="margin-top:6px;">
                        Select Locations
                    </button>
                    <select name="location_ids[]" id="location_ids_hidden" class="form-control" multiple style="display:none;">
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @if(in_array($location->id, $filters['location_ids'])) selected @endif>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Cashier/User</label>
                    <input type="text" id="cashier_preview" class="form-control" readonly
                           value="{{ $cashiers->whereIn('id', $filters['user_ids'])->pluck('name')->implode(', ') }}">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#cashier_modal" style="margin-top:6px;">
                        Select Cashiers
                    </button>
                    <select name="user_ids[]" id="user_ids_hidden" class="form-control" multiple style="display:none;">
                        @foreach($cashiers as $cashier)
                            <option value="{{ $cashier->id }}" @if(in_array($cashier->id, $filters['user_ids'])) selected @endif>{{ $cashier->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status" class="form-control">
                        <option value="">All</option>
                        @foreach($paymentStatuses as $status)
                            <option value="{{ $status }}" @if($filters['payment_status'] === $status) selected @endif>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Qty Type</label>
                    <select name="qty_type" class="form-control">
                        @foreach($qtyTypes as $key => $label)
                            <option value="{{ $key }}" @if($filters['qty_type'] === $key) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="{{ route('local-cashier-report.export', request()->query()) }}" class="btn btn-success">Export Excel</a>
                <a href="{{ route('local-cashier-report.print', request()->query()) }}" target="_blank" class="btn btn-info">Print</a>
            </div>
        </form>
    @endcomponent
    </div>

    @if($filters['style_mode'] === 'view_report')
    <div class="table-responsive">
        <table class="table sheet-table report-view-table" id="local_cashier_report_table">
            <thead>
                <tr>
                    <th>Cashier/User</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                    @endforeach
                    <th class="text-right">Total Paid</th>
                    <th class="text-right">Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['rows'] as $row)
                    @php
                        $userDetailQuery = array_merge(request()->query(), [
                            'style_mode' => 'classic_plain',
                            'user_ids' => [(int) ($row['cashier_id'] ?? 0)],
                        ]);
                    @endphp
                    <tr class="row-sale">
                        <td class="name-main">
                            <a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userDetailQuery) . '#local_cashier_sales_detail_table' }}">
                                {{ $row['cashier_name'] }}
                            </a>
                            <a class="qty-badge qty-badge-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userDetailQuery) . '#local_cashier_sales_detail_table' }}">
                                (Qty: {{ rtrim(rtrim(number_format((float) ($row['qty_total'] ?? 0), 2), '0'), '.') }})
                            </a>
                        </td>
                        @foreach($report['payment_columns'] as $method)
                            @php
                                $userPaymentDetailQuery = array_merge(request()->query(), [
                                    'style_mode' => 'classic_plain',
                                    'user_ids' => [(int) ($row['cashier_id'] ?? 0)],
                                    'payment_methods' => [(string) $method],
                                ]);
                            @endphp
                            <td class="text-right">
                                <a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userPaymentDetailQuery) . '#local_cashier_sales_detail_table' }}">
                                    {{ $fmt($row['payments'][$method] ?? null) }}
                                </a>
                            </td>
                        @endforeach
                        <td class="text-right">{{ $fmt($row['paid'] ?? null) }}</td>
                        <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due'] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <th>Grand Total</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">{{ $fmt($report['grand_paid'] ?? null) }}</th>
                    <th class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                </tr>
                <tr class="row-summary">
                    <th>Expenses</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">$ -</th>
                    <th class="text-right">{{ $fmtStrict($report['grand_expenses'] ?? 0) }}</th>
                </tr>
                <tr class="row-summary">
                    <th>Actual Total Income (Paid - Expenses - Sell Return)</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">$ -</th>
                    <th class="text-right">{{ $fmtStrict($report['grand_actual_income'] ?? 0) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
    @elseif($filters['style_mode'] === 'business_location_report')
    <div class="table-responsive">
        <table class="table sheet-table business-location-table">
            <thead>
                <tr>
                    <th>Business Location</th>
                    <th class="text-right">Grand Total</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                    @endforeach
                    <th class="text-right">Due</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows_by_location'] as $row)
                    <tr class="row-sale">
                        <td class="name-main">
                            {{ $row['location_name'] }}
                            @php
                                $detailQuery = array_merge(request()->query(), [
                                    'style_mode' => 'classic_plain',
                                    'location_ids' => [(int) $row['location_id']],
                                ]);
                            @endphp
                            <a class="qty-badge qty-badge-link"
                               href="{{ route('local-cashier-report.index') . '?' . http_build_query($detailQuery) . '#local_cashier_sales_detail_table' }}">
                                (Qty: {{ rtrim(rtrim(number_format((float) ($row['qty_total'] ?? 0), 2), '0'), '.') }})
                            </a>
                        </td>
                        <td class="text-right">{{ $fmt($row['total']) }}</td>
                        @foreach($report['payment_columns'] as $method)
                            @php
                                $locationPaymentDetailQuery = array_merge(request()->query(), [
                                    'style_mode' => 'classic_plain',
                                    'location_ids' => [(int) ($row['location_id'] ?? 0)],
                                    'payment_methods' => [(string) $method],
                                ]);
                            @endphp
                            <td class="text-right">
                                <a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($locationPaymentDetailQuery) . '#local_cashier_sales_detail_table' }}">
                                    {{ $fmt($row['payments'][$method] ?? null) }}
                                </a>
                            </td>
                        @endforeach
                        <td class="text-right @if(($row['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($row['due'] ?? null) }}</td>
                    </tr>
                    @foreach(collect($row['customer_groups'] ?? [])->sortBy(function ($customerGroupRow) {
                        $name = (string) ($customerGroupRow['name'] ?? 'លក់');
                        return ['លក់' => 1, 'អ៊ីអន' => 2, 'រំលស់' => 3, 'Collection Payment' => 4][$name] ?? (int) ($customerGroupRow['sort'] ?? 99);
                    })->values() as $customerGroupRow)
                        <tr class="customer-group-breakdown-row {{ ($customerGroupRow['name'] ?? '') === 'រំលស់' ? 'installment-breakdown-row' : (($customerGroupRow['name'] ?? '') === 'អ៊ីអន' ? 'aeon-breakdown-row' : (in_array($customerGroupRow['name'] ?? '', ['Collection Payment', 'Customer Payment']) ? 'loan-payment-breakdown-row' : 'normal-breakdown-row')) }}">
                            <td class="name-main customer-group-breakdown-name">
                                <span class="customer-group-breakdown-label">{{ $customerGroupRow['name'] ?? 'លក់' }}</span>
                                <span class="qty-badge">(Qty: {{ rtrim(rtrim(number_format((float) ($customerGroupRow['qty_total'] ?? 0), 2), '0'), '.') }})</span>
                            </td>
                            <td class="text-right">{{ $fmt($customerGroupRow['total'] ?? null) }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td class="text-right">{{ $fmt($customerGroupRow['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td class="text-right @if(($customerGroupRow['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($customerGroupRow['due'] ?? null) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($report['payment_columns']) }}" class="text-center">No data found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <th class="text-right">Grand Total</th>
                    <th class="text-right">{{ $fmt($report['grand_total'] ?? null) }}</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                </tr>
                <tr class="row-summary">
                    <th class="text-right">Expenses</th>
                    <th class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">$ -</th>
                </tr>
                <tr class="row-summary">
                    <th class="text-right">Actual Income</th>
                    <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? 0) }}</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">$ -</th>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="table-responsive">
        <table class="table sheet-table">
            <thead>
                <tr>
                    <th>Business Location (Qty)</th>
                    <th class="text-right">Total Price</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                    @endforeach
                    <th class="text-right">Total</th>
                    <th class="text-right">Due</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $dashboardCashierGroupRows = collect($report['rows'] ?? [])->flatMap(function ($cashierRow) {
                        return collect($cashierRow['customer_groups'] ?? [])->values()->map(function ($customerGroupRow) use ($cashierRow) {
                            $customerGroupRow['cashier_id'] = (int) ($cashierRow['cashier_id'] ?? 0);
                            $customerGroupRow['cashier_name'] = (string) ($cashierRow['cashier_name'] ?? 'N/A');
                            return $customerGroupRow;
                        });
                    })->sortBy(function ($customerGroupRow) {
                        $name = trim((string) ($customerGroupRow['name'] ?? 'លក់'));
                        $groupSort = ['លក់' => 1, 'អ៊ីអន' => 2, 'រំលស់' => 3, 'Collection Payment' => 4, 'Customer Payment' => 5][$name] ?? (int) ($customerGroupRow['sort'] ?? 99);
                        return sprintf('%02d-%s-%s', $groupSort, $customerGroupRow['cashier_name'] ?? '', $customerGroupRow['location_qty_text'] ?? '');
                    })->values();
                    $dashboardDueTotal = $dashboardCashierGroupRows
                        ->reject(fn ($row) => in_array((int) ($row['sort'] ?? 0), [2, 3], true))
                        ->sum(fn ($row) => (float) ($row['due'] ?? 0));
                    $hasNormalDashboardGroup = $dashboardCashierGroupRows
                        ->contains(fn ($row) => (int) ($row['sort'] ?? 0) === 1);
                    $lastDashboardCustomerGroup = null;
                @endphp
                @forelse($dashboardCashierGroupRows as $customerGroupRow)
                    @php
                        $dashboardCustomerGroup = (string) ($customerGroupRow['name'] ?? 'លក់');
                        $dashboardDetailQuery = array_merge(request()->query(), [
                            'style_mode' => 'classic_plain',
                            'user_ids' => [(int) ($customerGroupRow['cashier_id'] ?? 0)],
                            'customer_group' => $dashboardCustomerGroup,
                        ]);
                    @endphp
                    @if($lastDashboardCustomerGroup !== $dashboardCustomerGroup)
                        <tr class="dashboard-customer-group-separator {{ $dashboardCustomerGroup === 'រំលស់' ? 'installment-separator' : ($dashboardCustomerGroup === 'អ៊ីអន' ? 'aeon-separator' : ($dashboardCustomerGroup === 'Collection Payment' ? 'loan-payment-separator' : ($dashboardCustomerGroup === 'Customer Payment' ? 'customer-payment-separator' : 'normal-separator'))) }}">
                            <td colspan="{{ 4 + count($report['payment_columns']) }}">{{ $dashboardCustomerGroup }}</td>
                        </tr>
                        @php $lastDashboardCustomerGroup = $dashboardCustomerGroup; @endphp
                    @endif
                        <tr class="cashier-group-breakdown-row {{ ($customerGroupRow['name'] ?? '') === 'រំលស់' ? 'installment-breakdown-row' : (($customerGroupRow['name'] ?? '') === 'អ៊ីអន' ? 'aeon-breakdown-row' : (($customerGroupRow['name'] ?? '') === 'Collection Payment' ? 'loan-payment-breakdown-row' : (($customerGroupRow['name'] ?? '') === 'Customer Payment' ? 'customer-payment-breakdown-row' : 'normal-breakdown-row'))) }}">
                            <td class="name-main">
                                {{ $customerGroupRow['location_qty_text'] ?? '-' }}
                            </td>
                            <td class="text-right">
                                <a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($dashboardDetailQuery) . '#local_cashier_sales_detail_table' }}">
                                    {{ $fmt($customerGroupRow['total'] ?? null) }}
                                </a>
                            </td>
                            @foreach($report['payment_columns'] as $method)
                                @php
                                    $dashboardPaymentDetailQuery = array_merge($dashboardDetailQuery, [
                                        'payment_methods' => [(string) $method],
                                    ]);
                                @endphp
                                <td class="text-right">
                                    <a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($dashboardPaymentDetailQuery) . '#local_cashier_sales_detail_table' }}">
                                        {{ $fmt($customerGroupRow['payments'][$method] ?? null) }}
                                    </a>
                                </td>
                            @endforeach
                            <td class="text-right">{{ $fmt($customerGroupRow['paid'] ?? null) }}</td>
                            <td class="text-right @if(! in_array($dashboardCustomerGroup, ['រំលស់', 'អ៊ីអន'], true) && ($customerGroupRow['due'] ?? 0) != 0) due-negative @endif">
                                {{ in_array($dashboardCustomerGroup, ['រំលស់', 'អ៊ីអន'], true) ? '$ -' : $fmt($customerGroupRow['due'] ?? null) }}
                            </td>
                        </tr>
                        @php
                            $nextDashboardCustomerGroup = $dashboardCashierGroupRows->get($loop->index + 1);
                            $isLastNormalDashboardGroup = (int) ($customerGroupRow['sort'] ?? 0) === 1
                                && (int) data_get($nextDashboardCustomerGroup, 'sort', 0) !== 1;
                        @endphp
                        @if($isLastNormalDashboardGroup)
                            @foreach($moduleDashboardRows as $moduleDashboardRow)
                                <tr class="cashier-group-breakdown-row normal-breakdown-row module-dashboard-breakdown-row">
                                    <td class="name-main cashier-group-breakdown-name">
                                        <a class="summary-link local-detail-tab-link" href="{{ $moduleDashboardRow['tab_target'] }}" data-target-tab="{{ $moduleDashboardRow['tab_target'] }}">
                                            {{ $moduleDashboardRow['label'] }} ({{ rtrim(rtrim(number_format((float) ($moduleDashboardRow['qty_total'] ?? 0), 2), '0'), '.') }})
                                        </a>
                                    </td>
                                    <td class="text-right">
                                        <a class="summary-link local-detail-tab-link" href="{{ $moduleDashboardRow['tab_target'] }}" data-target-tab="{{ $moduleDashboardRow['tab_target'] }}">
                                            {{ $fmt($moduleDashboardRow['total'] ?? null) }}
                                        </a>
                                    </td>
                                    @foreach($report['payment_columns'] as $method)
                                        <td class="text-right">
                                            <a class="summary-link local-detail-tab-link" href="{{ $moduleDashboardRow['tab_target'] }}" data-target-tab="{{ $moduleDashboardRow['tab_target'] }}">
                                                {{ $fmt($moduleDashboardRow['payments'][$method] ?? null) }}
                                            </a>
                                        </td>
                                    @endforeach
                                    <td class="text-right">{{ $fmt($moduleDashboardRow['paid'] ?? null) }}</td>
                                    <td class="text-right @if(($moduleDashboardRow['due'] ?? 0) != 0) due-negative @endif">
                                        {{ $fmt($moduleDashboardRow['due'] ?? null) }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                @empty
                    @if(empty($moduleDashboardRows))
                        <tr>
                            <td colspan="{{ 4 + count($report['payment_columns']) }}" class="text-center">No data found.</td>
                        </tr>
                    @endif
                @endforelse
                @if(! $hasNormalDashboardGroup)
                    @foreach($moduleDashboardRows as $moduleDashboardRow)
                        <tr class="cashier-group-breakdown-row normal-breakdown-row module-dashboard-breakdown-row">
                            <td class="name-main cashier-group-breakdown-name">
                                <a class="summary-link local-detail-tab-link" href="{{ $moduleDashboardRow['tab_target'] }}" data-target-tab="{{ $moduleDashboardRow['tab_target'] }}">
                                    {{ $moduleDashboardRow['label'] }} ({{ rtrim(rtrim(number_format((float) ($moduleDashboardRow['qty_total'] ?? 0), 2), '0'), '.') }})
                                </a>
                            </td>
                            <td class="text-right">
                                <a class="summary-link local-detail-tab-link" href="{{ $moduleDashboardRow['tab_target'] }}" data-target-tab="{{ $moduleDashboardRow['tab_target'] }}">
                                    {{ $fmt($moduleDashboardRow['total'] ?? null) }}
                                </a>
                            </td>
                            @foreach($report['payment_columns'] as $method)
                                <td class="text-right">
                                    <a class="summary-link local-detail-tab-link" href="{{ $moduleDashboardRow['tab_target'] }}" data-target-tab="{{ $moduleDashboardRow['tab_target'] }}">
                                        {{ $fmt($moduleDashboardRow['payments'][$method] ?? null) }}
                                    </a>
                                </td>
                            @endforeach
                            <td class="text-right">{{ $fmt($moduleDashboardRow['paid'] ?? null) }}</td>
                            <td class="text-right @if(($moduleDashboardRow['due'] ?? 0) != 0) due-negative @endif">
                                {{ $fmt($moduleDashboardRow['due'] ?? null) }}
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <th class="text-right">Grand Total</th>
                    <th class="text-right">{{ $fmt($report['grand_total']) }}</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">{{ $fmt($report['grand_paid']) }}</th>
                    <th class="text-right @if($dashboardDueTotal != 0) due-negative @endif">{{ $fmt($dashboardDueTotal) }}</th>
                </tr>
                @if(($filters['style_mode'] ?? 'sheet') === 'classic_plain')
                    <tr class="row-summary">
                        <th class="text-right">Expenses</th>
                        <th class="text-right">$ -</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="text-right">Actual Income</th>
                        <th class="text-right">$ -</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th class="text-right @if($dashboardDueTotal != 0) due-negative @endif">{{ $fmt($dashboardDueTotal) }}</th>
                    </tr>
                @endif
            </tfoot>
        </table>
    </div>
    @endif

    @if($filters['style_mode'] === 'classic')
        <hr>
        <h4 class="section-title">Summary</h4>
        <div class="row">
            <div class="col-md-3">
                <h4>User/Cashier</h4>
                <table class="table table-bordered table-condensed summary-table" id="sum_user">
                    <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        @foreach($report['summary_user'] as $r)
                            @php
                                $userDetailQuery = array_merge(request()->query(), [
                                    'style_mode' => 'classic_plain',
                                    'user_ids' => [(int) ($r['id'] ?? 0)],
                                ]);
                            @endphp
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.user.amount', 0)) }}</th>
                            <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.user.qty', 0), 2), '0'), '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="col-md-3">
                <h4>Location</h4>
                <table class="table table-bordered table-condensed summary-table" id="sum_location">
                    <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        @foreach($report['summary_location'] as $r)
                            @php
                                $locationDetailQuery = array_merge(request()->query(), [
                                    'style_mode' => 'classic_plain',
                                    'location_ids' => [(int) ($r['id'] ?? 0)],
                                ]);
                            @endphp
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($locationDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($locationDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.location.amount', 0)) }}</th>
                            <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.location.qty', 0), 2), '0'), '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="col-md-3">
                <h4>Brand</h4>
                <table class="table table-bordered table-condensed summary-table" id="sum_brand">
                    <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        @foreach($report['summary_brand'] as $r)
                            @php
                                $brandDetailQuery = array_merge(request()->query(), [
                                    'style_mode' => 'classic_plain',
                                    'brand_ids' => [(int) ($r['id'] ?? 0)],
                                ]);
                            @endphp
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($brandDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($brandDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.brand.amount', 0)) }}</th>
                            <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.brand.qty', 0), 2), '0'), '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    @if($filters['style_mode'] === 'classic_plain')
        <hr>
        <div class="summary-collapse-header">
            <h4 class="section-title">Summary</h4>
            <button type="button" class="btn btn-default btn-sm summary-collapse-toggle" data-toggle="collapse" data-target="#summary_cards_section" aria-expanded="true" aria-controls="summary_cards_section">
                <i class="fa fa-chevron-up"></i> Hide Summary
            </button>
        </div>
        <div class="collapse in" id="summary_cards_section">
        <div class="summary-kpi-grid">
            <div class="summary-kpi-card">
                <div class="kpi-label">Total Sale</div>
                <div class="kpi-value">{{ $fmt($report['grand_total'] ?? null) }}</div>
            </div>
            <div class="summary-kpi-card">
                <div class="kpi-label">Actual Income</div>
                <div class="kpi-value">{{ $fmt($report['grand_actual_income'] ?? null) }}</div>
            </div>
            <div class="summary-kpi-card">
                <div class="kpi-label">Customer Payment</div>
                <div class="kpi-value">{{ $fmt($report['customer_due_payment_total'] ?? null) }}</div>
            </div>
            <div class="summary-kpi-card">
                <div class="kpi-label">Collection Payment</div>
                <div class="kpi-value">{{ $fmt($report['collection_payment_total'] ?? null) }}</div>
            </div>
            <div class="summary-kpi-card">
                <div class="kpi-label">Expenses</div>
                <div class="kpi-value">{{ $fmt($report['grand_expenses'] ?? null) }}</div>
            </div>
            <div class="summary-kpi-card">
                <div class="kpi-label">Due</div>
                <div class="kpi-value @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="summary-panel">
                    <h4>User/Cashier</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_user_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_user'] as $r)
                                @php
                                    $userDetailQuery = array_merge(request()->query(), [
                                        'style_mode' => 'classic_plain',
                                        'user_ids' => [(int) ($r['id'] ?? 0)],
                                    ]);
                                @endphp
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($userDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.user.amount', 0)) }}</th>
                                <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.user.qty', 0), 2), '0'), '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-panel">
                    <h4>Location</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_location_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_location'] as $r)
                                @php
                                    $locationDetailQuery = array_merge(request()->query(), [
                                        'style_mode' => 'classic_plain',
                                        'location_ids' => [(int) ($r['id'] ?? 0)],
                                    ]);
                                @endphp
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($locationDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($locationDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.location.amount', 0)) }}</th>
                                <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.location.qty', 0), 2), '0'), '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-panel">
                    <h4>Customer Group</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_customer_group_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_customer_group'] ?? [] as $r)
                                @php
                                    $customerGroupDetailQuery = array_merge(request()->query(), [
                                        'style_mode' => 'classic_plain',
                                        'customer_group' => (string) ($r['name'] ?? ''),
                                    ]);
                                @endphp
                                <tr>
                                    <td><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($customerGroupDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $r['name'] }}</a></td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($customerGroupDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($customerGroupDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.customer_group.amount', 0)) }}</th>
                                <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.customer_group.qty', 0), 2), '0'), '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-panel">
                    <h4>Brand</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_brand_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_brand'] as $r)
                                @php
                                    $brandDetailQuery = array_merge(request()->query(), [
                                        'style_mode' => 'classic_plain',
                                        'brand_ids' => [(int) ($r['id'] ?? 0)],
                                    ]);
                                @endphp
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($brandDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ $fmt($r['amount']) }}</a></td>
                                    <td class="text-right"><a class="summary-link" href="{{ route('local-cashier-report.index') . '?' . http_build_query($brandDetailQuery) . '#local_cashier_sales_detail_table' }}">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.brand.amount', 0)) }}</th>
                                <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.brand.qty', 0), 2), '0'), '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        </div>
        <hr>
        @php
            $expenseRows = collect($report['expense_detail_rows'] ?? []);
            $collectionPaymentRows = collect($report['collection_payment_detail_rows'] ?? []);
            $dueCustomerRows = collect($report['due_customer_detail_rows'] ?? []);
            $accessorySaleRows = collect($report['accessory_sale_detail_rows'] ?? []);
            $serviceSaleRows = collect($report['service_sale_detail_rows'] ?? []);
            $detailMeta = $report['detail_meta'] ?? [];
            $detailLimit = (int) ($detailMeta['limit'] ?? 1000);
            $hasLimitedDetails = collect([
                ['label' => 'cashier sales', 'displayed' => $detailMeta['main_displayed'] ?? 0, 'total' => $detailMeta['main_total'] ?? 0],
                ['label' => 'accessory sales', 'displayed' => $detailMeta['accessory_displayed'] ?? 0, 'total' => $detailMeta['accessory_total'] ?? 0],
                ['label' => 'service sales', 'displayed' => $detailMeta['service_displayed'] ?? 0, 'total' => $detailMeta['service_total'] ?? 0],
                ['label' => 'collection payments', 'displayed' => $detailMeta['collection_payment_displayed'] ?? 0, 'total' => $detailMeta['collection_payment_total'] ?? 0],
                ['label' => 'due customers', 'displayed' => $detailMeta['due_customer_displayed'] ?? 0, 'total' => $detailMeta['due_customer_total'] ?? 0],
                ['label' => 'expenses', 'displayed' => $detailMeta['expense_displayed'] ?? 0, 'total' => $detailMeta['expense_total'] ?? 0],
            ])->filter(fn ($row) => (int) $row['total'] > (int) $row['displayed'])->values();
        @endphp
        @if($hasLimitedDetails->isNotEmpty())
            <div class="alert alert-warning">
                Detail tables are limited to {{ number_format($detailLimit) }} rows per section for faster loading.
                Use narrower filters or export/print for full detail.
            </div>
        @endif
        <div class="box box-primary">
            <div class="box-header">
                <ul class="nav nav-tabs local-detail-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#all_sale_detail_tab" aria-controls="all_sale_detail_tab" role="tab" data-toggle="tab">All Sale</a>
                    </li>
                    <li role="presentation">
                        <a href="#cashier_sales_detail_tab" aria-controls="cashier_sales_detail_tab" role="tab" data-toggle="tab">Product Sale</a>
                    </li>
                    <li role="presentation">
                        <a href="#accessory_sales_detail_tab" aria-controls="accessory_sales_detail_tab" role="tab" data-toggle="tab">Accessory sales</a>
                    </li>
                    <li role="presentation">
                        <a href="#service_sales_detail_tab" aria-controls="service_sales_detail_tab" role="tab" data-toggle="tab">Service sales</a>
                    </li>
                    <li role="presentation">
                        <a href="#collection_payments_detail_tab" aria-controls="collection_payments_detail_tab" role="tab" data-toggle="tab">Collection Payment</a>
                    </li>
                    <li role="presentation">
                        <a href="#cashier_expenses_detail_tab" aria-controls="cashier_expenses_detail_tab" role="tab" data-toggle="tab">Expenses list</a>
                    </li>
                    <li role="presentation">
                        <a href="#customer_due_payments_detail_tab" aria-controls="customer_due_payments_detail_tab" role="tab" data-toggle="tab">Due Customer</a>
                    </li>
                </ul>
                <div class="table-meta">
                    <span>{{ number_format($detailMeta['main_displayed'] ?? count($report['detail_rows'] ?? [])) }} / {{ number_format($detailMeta['main_total'] ?? count($report['detail_rows'] ?? [])) }} rows</span>
                    <span>{{ count($report['summary_user'] ?? []) }} cashiers</span>
                    <span>{{ number_format($allSaleRows->count()) }} all sales</span>
                    <span>{{ number_format($detailMeta['accessory_displayed'] ?? $accessorySaleRows->count()) }} / {{ number_format($detailMeta['accessory_total'] ?? $accessorySaleRows->count()) }} accessory sales</span>
                    <span>{{ number_format($detailMeta['service_displayed'] ?? $serviceSaleRows->count()) }} / {{ number_format($detailMeta['service_total'] ?? $serviceSaleRows->count()) }} service sales</span>
                    <span>{{ number_format($detailMeta['collection_payment_displayed'] ?? $collectionPaymentRows->count()) }} / {{ number_format($detailMeta['collection_payment_total'] ?? $collectionPaymentRows->count()) }} collection payments</span>
                    <span>{{ number_format($detailMeta['due_customer_displayed'] ?? $dueCustomerRows->count()) }} / {{ number_format($detailMeta['due_customer_total'] ?? $dueCustomerRows->count()) }} due customers</span>
                    <span>{{ number_format($detailMeta['expense_displayed'] ?? $expenseRows->count()) }} / {{ number_format($detailMeta['expense_total'] ?? $expenseRows->count()) }} expenses</span>
                </div>
            </div>
            <div class="box-body">
                <div class="tab-content local-detail-tab-content">
                    <div role="tabpanel" class="tab-pane active" id="all_sale_detail_tab">
                        @include('localcashierreport::partials.module_sales_detail_table', [
                            'tableId' => 'local_cashier_all_sale_detail_table',
                            'rows' => $allSaleRows,
                            'report' => $report,
                            'fmt' => $fmt,
                            'isAllSaleTable' => true,
                        ])
                    </div>
                    <div role="tabpanel" class="tab-pane" id="cashier_sales_detail_tab">
                @php
                    $cashierSaleRowsForFilters = collect($report['detail_rows'] ?? [])
                        ->filter(fn ($detailRow) => ($detailRow['row_type'] ?? 'sale') === 'sale');
                    $cashierSaleLocations = $cashierSaleRowsForFilters->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                    $cashierSaleCashiers = $cashierSaleRowsForFilters->pluck('cashier_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                @endphp
                <div class="sale-table-filter-toggle">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#local_cashier_sales_detail_table_filters" aria-expanded="false" aria-controls="local_cashier_sales_detail_table_filters">
                        <i class="fa fa-filter"></i> Filters
                    </button>
                </div>
                <div class="collapse" id="local_cashier_sales_detail_table_filters">
                    <div class="row all-sale-table-filters">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label>Location</label>
                                <select class="form-control select2 all-sale-location-filter" data-table-id="local_cashier_sales_detail_table" multiple data-placeholder="All locations">
                                    @foreach($cashierSaleLocations as $locationName)
                                        <option value="{{ $locationName }}">{{ $locationName }}</option>
                                    @endforeach
                                </select>
                                <div class="all-sale-filter-actions">
                                    <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-location-filter" data-table-id="local_cashier_sales_detail_table">Select All</button>
                                    <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-location-filter" data-table-id="local_cashier_sales_detail_table">Clear</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label>Cashier</label>
                                <select class="form-control select2 all-sale-cashier-filter" data-table-id="local_cashier_sales_detail_table" multiple data-placeholder="All cashiers">
                                    @foreach($cashierSaleCashiers as $cashierName)
                                        <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                                    @endforeach
                                </select>
                                <div class="all-sale-filter-actions">
                                    <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_sales_detail_table">Select All</button>
                                    <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_sales_detail_table">Clear</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped ajax_view" id="local_cashier_sales_detail_table" style="width:100%;">
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>I-T</th>
                            <th class="all-sale-location-column">Location</th>
                            <th>Customer</th>
                            <th>Group</th>
                            <th>Lot</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Line Total</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Total Paid</th>
                            @foreach($report['payment_columns'] as $method)
                                <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                            @endforeach
                            <th class="text-right">Due</th>
                            <th class="all-sale-cashier-column">User/Cashier</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($report['detail_rows'] as $row)
                            @if(($row['row_type'] ?? 'sale') === 'customer_group_separator')
                                <tr class="customer-group-separator {{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment-separator' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon-separator' : (in_array($row['customer_group_name'] ?? '', ['Collection Payment', 'Customer Payment']) ? 'loan-payment-separator' : 'normal-separator')) }}">
                                    <td></td>
                                    <td class="group-separator-label">{{ $row['customer_group_name'] ?? 'លក់' }}</td>
                                    <td class="group-separator-note">{{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'Installment' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'AEON' : (($row['customer_group_name'] ?? '') === 'Collection Payment' ? 'Collection payment' : (($row['customer_group_name'] ?? '') === 'Customer Payment' ? 'Customer payment' : 'Sale'))) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    @foreach($report['payment_columns'] as $method)
                                        <td></td>
                                    @endforeach
                                    <td></td>
                                    <td></td>
                                </tr>
                                @continue
                            @endif
                            <tr class="{{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment-customer-row' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon-customer-row' : (in_array($row['customer_group_name'] ?? '', ['Collection Payment', 'Customer Payment']) ? 'loan-payment-customer-row' : 'normal-customer-row')) }}">
                                <td>
                                    @if(($row['row_source'] ?? 'sell') !== 'loan_payment')
                                        @canany(['sell.view', 'direct_sell.view', 'view_own_sell_only'])
                                            <a class="btn btn-xs btn-default btn-modal action-icon-btn action-view"
                                               href="#"
                                               data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$row['transaction_id']]) }}"
                                               data-container=".view_modal"
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @endcanany
                                        @can('sell.update')
                                            <a class="btn btn-xs btn-primary action-icon-btn action-edit" href="{{ action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row['transaction_id']]) }}" target="_blank" title="Edit POS">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                        @endcan
                                        @cannot('sell.update')
                                            @can('direct_sell.update')
                                                <a class="btn btn-xs btn-primary action-icon-btn action-edit" href="{{ action([\App\Http\Controllers\SellController::class, 'edit'], [$row['transaction_id']]) }}" target="_blank" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                            @endcan
                                        @endcannot
                                    @endif
                                </td>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['invoice_no'] }}</td>
                                <td>{{ $row['i_t'] ?? '-' }}</td>
                                <td>{{ $row['location_name'] }}</td>
                                <td>{{ $row['customer_name'] ?? '-' }}</td>
                                <td>
                                    <span class="customer-group-pill {{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon' : (in_array($row['customer_group_name'] ?? '', ['Collection Payment', 'Customer Payment']) ? 'loan-payment' : 'normal')) }}">
                                        {{ $row['customer_group_name'] ?? 'លក់' }}
                                    </span>
                                </td>
                                <td>{{ $row['lot_number'] ?? '-' }}</td>
                                <td>{{ $row['sku'] }}</td>
                                <td>{{ $row['product_name'] }}</td>
                                <td class="text-right">{{ is_null($row['quantity'] ?? null) ? '' : rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                                <td class="text-right">{{ is_null($row['unit_price'] ?? null) ? '' : $fmt($row['unit_price']) }}</td>
                                <td class="text-right">{{ is_null($row['line_total'] ?? null) ? '' : $fmt($row['line_total']) }}</td>
                                <td class="text-right">{{ is_null($row['discount'] ?? null) ? '' : $fmt($row['discount']) }}</td>
                                <td class="text-right">{{ $fmt($row['paid']) }}</td>
                                @foreach($report['payment_columns'] as $method)
                                    <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                                @endforeach
                                <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                                <td>{{ $row['cashier_name'] ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        @php
                            $detailTotalRows = collect($report['detail_rows'] ?? [])
                                ->filter(fn ($detailRow) => ($detailRow['row_type'] ?? 'sale') === 'sale');
                            $detailPaymentTotals = [];
                            foreach ($report['payment_columns'] as $method) {
                                $detailPaymentTotals[$method] = $detailTotalRows->sum(fn ($detailRow) => (float) data_get($detailRow, 'payments.' . $method, 0));
                            }
                        @endphp
                        <tfoot>
                            <tr class="detail-total-row">
                                <th colspan="10" class="text-right">Total</th>
                                <th class="text-right">{{ rtrim(rtrim(number_format($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['quantity'] ?? 0)), 2), '0'), '.') }}</th>
                                <th class="text-right">{{ $fmt($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['unit_price'] ?? 0))) }}</th>
                                <th class="text-right">{{ $fmt($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['line_total'] ?? 0))) }}</th>
                                <th class="text-right">{{ $fmt($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['discount'] ?? 0))) }}</th>
                                <th class="text-right">{{ $fmt($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['paid'] ?? 0))) }}</th>
                                @foreach($report['payment_columns'] as $method)
                                    <th class="text-right">{{ $fmt($detailPaymentTotals[$method] ?? 0) }}</th>
                                @endforeach
                                <th class="text-right @if($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['due'] ?? 0)) < 0) due-negative @endif">
                                    {{ $fmt($detailTotalRows->sum(fn ($detailRow) => (float) ($detailRow['due'] ?? 0))) }}
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="accessory_sales_detail_tab">
                        @include('localcashierreport::partials.module_sales_detail_table', [
                            'tableId' => 'local_cashier_accessory_sales_detail_table',
                            'rows' => $accessorySaleRows,
                            'report' => $report,
                            'fmt' => $fmt,
                        ])
                    </div>
                    <div role="tabpanel" class="tab-pane" id="service_sales_detail_tab">
                        @include('localcashierreport::partials.module_sales_detail_table', [
                            'tableId' => 'local_cashier_service_sales_detail_table',
                            'rows' => $serviceSaleRows,
                            'report' => $report,
                            'fmt' => $fmt,
                        ])
                    </div>
                    <div role="tabpanel" class="tab-pane" id="collection_payments_detail_tab">
                        @php
                            $collectionPaymentLocations = $collectionPaymentRows->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                            $collectionPaymentCashiers = $collectionPaymentRows->pluck('cashier_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                        @endphp
                        <div class="sale-table-filter-toggle">
                            <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#local_cashier_collection_payment_table_filters" aria-expanded="false" aria-controls="local_cashier_collection_payment_table_filters">
                                <i class="fa fa-filter"></i> Filters
                            </button>
                        </div>
                        <div class="collapse" id="local_cashier_collection_payment_table_filters">
                            <div class="row all-sale-table-filters">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Location</label>
                                        <select class="form-control select2 all-sale-location-filter" data-table-id="local_cashier_collection_payment_table" multiple data-placeholder="All locations">
                                            @foreach($collectionPaymentLocations as $locationName)
                                                <option value="{{ $locationName }}">{{ $locationName }}</option>
                                            @endforeach
                                        </select>
                                        <div class="all-sale-filter-actions">
                                            <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-location-filter" data-table-id="local_cashier_collection_payment_table">Select All</button>
                                            <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-location-filter" data-table-id="local_cashier_collection_payment_table">Clear</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Cashier</label>
                                        <select class="form-control select2 all-sale-cashier-filter" data-table-id="local_cashier_collection_payment_table" multiple data-placeholder="All cashiers">
                                            @foreach($collectionPaymentCashiers as $cashierName)
                                                <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                                            @endforeach
                                        </select>
                                        <div class="all-sale-filter-actions">
                                            <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_collection_payment_table">Select All</button>
                                            <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_collection_payment_table">Clear</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped ajax_view" id="local_cashier_collection_payment_table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Receipt No</th>
                                        <th>Customer</th>
                                        <th>Loan No</th>
                                        <th class="all-sale-location-column">Location</th>
                                        <th class="all-sale-cashier-column">Cashier</th>
                                        <th>Method</th>
                                        @foreach($report['payment_columns'] as $method)
                                            <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                                        @endforeach
                                        <th class="text-right">Amount Paid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($collectionPaymentRows as $row)
                                        <tr>
                                            <td>{{ $row['date'] }}</td>
                                            <td>{{ $row['receipt_no'] }}</td>
                                            <td>{{ $row['customer_name'] }}</td>
                                            <td>{{ $row['loan_number'] }}</td>
                                            <td>{{ $row['location_name'] }}</td>
                                            <td>{{ $row['cashier_name'] }}</td>
                                            <td>{{ $row['method_label'] }}</td>
                                            @foreach($report['payment_columns'] as $method)
                                                <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                                            @endforeach
                                            <td class="text-right">{{ $fmt($row['amount']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="detail-total-row">
                                        <th colspan="7" class="text-right">Total</th>
                                        @foreach($report['payment_columns'] as $method)
                                            <th class="text-right">{{ $fmt($collectionPaymentRows->sum(fn ($row) => (float) data_get($row, 'payments.' . $method, 0))) }}</th>
                                        @endforeach
                                        <th class="text-right">{{ $fmt($collectionPaymentRows->sum(fn ($row) => (float) ($row['amount'] ?? 0))) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="customer_due_payments_detail_tab">
                        @php
                            $dueCustomerLocations = $dueCustomerRows->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                            $dueCustomerCashiers = $dueCustomerRows->pluck('cashier_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                        @endphp
                        <div class="sale-table-filter-toggle">
                            <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#local_cashier_customer_due_payment_table_filters" aria-expanded="false" aria-controls="local_cashier_customer_due_payment_table_filters">
                                <i class="fa fa-filter"></i> Filters
                            </button>
                        </div>
                        <div class="collapse" id="local_cashier_customer_due_payment_table_filters">
                            <div class="row all-sale-table-filters">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Location</label>
                                        <select class="form-control select2 all-sale-location-filter" data-table-id="local_cashier_customer_due_payment_table" multiple data-placeholder="All locations">
                                            @foreach($dueCustomerLocations as $locationName)
                                                <option value="{{ $locationName }}">{{ $locationName }}</option>
                                            @endforeach
                                        </select>
                                        <div class="all-sale-filter-actions">
                                            <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-location-filter" data-table-id="local_cashier_customer_due_payment_table">Select All</button>
                                            <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-location-filter" data-table-id="local_cashier_customer_due_payment_table">Clear</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Cashier</label>
                                        <select class="form-control select2 all-sale-cashier-filter" data-table-id="local_cashier_customer_due_payment_table" multiple data-placeholder="All cashiers">
                                            @foreach($dueCustomerCashiers as $cashierName)
                                                <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                                            @endforeach
                                        </select>
                                        <div class="all-sale-filter-actions">
                                            <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_customer_due_payment_table">Select All</button>
                                            <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_customer_due_payment_table">Clear</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped ajax_view" id="local_cashier_customer_due_payment_table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Invoice Date</th>
                                        <th>Invoice No</th>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th class="all-sale-location-column">Location</th>
                                        <th class="all-sale-cashier-column">Cashier</th>
                                        <th>Payment Status</th>
                                        <th class="text-right">Invoice Total</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Remaining Due</th>
                                        <th>Must Pay By</th>
                                        <th>Reminder</th>
                                        <th>Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dueCustomerRows as $row)
                                        <tr>
                                            <td>
                                                @canany(['sell.view', 'direct_sell.view', 'view_own_sell_only'])
                                                    <a class="btn btn-xs btn-default btn-modal action-icon-btn action-view"
                                                       href="#"
                                                       data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$row['transaction_id']]) }}"
                                                       data-container=".view_modal"
                                                       title="View Invoice">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                @endcanany
                                            </td>
                                            <td>{{ $row['date'] }}</td>
                                            <td>{{ $row['invoice_no'] }}</td>
                                            <td>{{ $row['customer_name'] }}</td>
                                            <td>{{ $row['phone_number'] }}</td>
                                            <td>{{ $row['location_name'] }}</td>
                                            <td>{{ $row['cashier_name'] }}</td>
                                            <td>{{ ucfirst($row['payment_status']) }}</td>
                                            <td class="text-right">{{ $fmt($row['invoice_total']) }}</td>
                                            <td class="text-right">{{ $fmt($row['amount_paid']) }}</td>
                                            <td class="text-right @if(($row['remaining_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($row['remaining_due']) }}</td>
                                            <td>{{ $row['pay_by_date'] }}</td>
                                            <td>
                                                <span class="label {{ ($row['reminder_status'] ?? '') === 'Overdue' ? 'label-danger' : ((($row['reminder_status'] ?? '') === 'Due soon') ? 'label-warning' : 'label-info') }}">
                                                    {{ $row['reminder_status'] }}
                                                </span>
                                            </td>
                                            <td class="text-right @if(($row['days_remaining'] ?? 0) < 0) due-negative @endif">{{ $row['days_remaining'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="detail-total-row">
                                        <th colspan="8" class="text-right">Total</th>
                                        <th class="text-right">{{ $fmt($dueCustomerRows->sum(fn ($row) => (float) ($row['invoice_total'] ?? 0))) }}</th>
                                        <th class="text-right">{{ $fmt($dueCustomerRows->sum(fn ($row) => (float) ($row['amount_paid'] ?? 0))) }}</th>
                                        <th class="text-right">{{ $fmt($dueCustomerRows->sum(fn ($row) => (float) ($row['remaining_due'] ?? 0))) }}</th>
                                        <th colspan="3"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="cashier_expenses_detail_tab">
                        @php
                            $expenseLocations = $expenseRows->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                            $expenseCashiers = $expenseRows->pluck('created_by_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
                            $staticPaymentAmount = function ($row, $column) {
                                $payments = (array) data_get($row, 'payments', []);
                                $sources = (array) data_get($column, 'source_methods', []);
                                $amount = 0.0;
                                foreach ($sources as $source) {
                                    $amount += (float) ($payments[$source] ?? 0);
                                }
                                return $amount;
                            };
                        @endphp
                        <div class="sale-table-filter-toggle">
                            <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#local_cashier_expenses_detail_table_filters" aria-expanded="false" aria-controls="local_cashier_expenses_detail_table_filters">
                                <i class="fa fa-filter"></i> Filters
                            </button>
                        </div>
                        <div class="collapse" id="local_cashier_expenses_detail_table_filters">
                            <div class="row all-sale-table-filters">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Location</label>
                                        <select class="form-control select2 all-sale-location-filter" data-table-id="local_cashier_expenses_detail_table" multiple data-placeholder="All locations">
                                            @foreach($expenseLocations as $locationName)
                                                <option value="{{ $locationName }}">{{ $locationName }}</option>
                                            @endforeach
                                        </select>
                                        <div class="all-sale-filter-actions">
                                            <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-location-filter" data-table-id="local_cashier_expenses_detail_table">Select All</button>
                                            <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-location-filter" data-table-id="local_cashier_expenses_detail_table">Clear</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Cashier/User</label>
                                        <select class="form-control select2 all-sale-cashier-filter" data-table-id="local_cashier_expenses_detail_table" multiple data-placeholder="All cashiers">
                                            @foreach($expenseCashiers as $cashierName)
                                                <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                                            @endforeach
                                        </select>
                                        <div class="all-sale-filter-actions">
                                            <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_expenses_detail_table">Select All</button>
                                            <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-cashier-filter" data-table-id="local_cashier_expenses_detail_table">Clear</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped ajax_view" id="local_cashier_expenses_detail_table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Date</th>
                                        <th>Ref No</th>
                                        <th class="all-sale-cashier-column">Cashier/User</th>
                                        <th>Expense For</th>
                                        <th class="all-sale-location-column">Location</th>
                                        <th>Category</th>
                                        <th>Payment Status</th>
                                        @foreach($staticPaymentColumns as $column)
                                            <th class="text-right">{{ $column['label'] ?? '' }}</th>
                                        @endforeach
                                        <th class="text-right">Amount</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Due</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expenseRows as $row)
                                        <tr>
                                            <td>
                                                @can('expense.edit')
                                                    <a class="btn btn-xs btn-primary action-icon-btn action-edit"
                                                       href="{{ action([\App\Http\Controllers\ExpenseController::class, 'edit'], [$row['transaction_id']]) }}"
                                                       target="_blank"
                                                       title="Edit Expense">
                                                        <i class="fas fa-pen"></i>
                                                    </a>
                                                @endcan
                                            </td>
                                            <td>{{ $row['date'] }}</td>
                                            <td>{{ $row['ref_no'] }}</td>
                                            <td>{{ $row['created_by_name'] }}</td>
                                            <td>{{ $row['expense_for_name'] }}</td>
                                            <td>{{ $row['location_name'] }}</td>
                                            <td>{{ $row['category_name'] }}</td>
                                            <td>{{ ucfirst($row['payment_status']) }}</td>
                                            @foreach($staticPaymentColumns as $column)
                                                <td class="text-right">{{ $fmt($staticPaymentAmount($row, $column)) }}</td>
                                            @endforeach
                                            <td class="text-right">{{ $fmt($row['amount']) }}</td>
                                            <td class="text-right">{{ $fmt($row['paid']) }}</td>
                                            <td class="text-right @if(($row['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                                            <td>{{ $row['note'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="detail-total-row">
                                        @php
                                            $expensePaymentStaticTotals = [];
                                            foreach ($staticPaymentColumns as $column) {
                                                $expensePaymentStaticTotals[$column['key'] ?? ''] = $expenseRows->sum(fn ($row) => $staticPaymentAmount($row, $column));
                                            }
                                        @endphp
                                        <th colspan="8" class="text-right">Total</th>
                                        @foreach($staticPaymentColumns as $column)
                                            <th class="text-right">{{ $fmt($expensePaymentStaticTotals[$column['key'] ?? ''] ?? 0) }}</th>
                                        @endforeach
                                        <th class="text-right">{{ $fmt($expenseRows->sum(fn ($row) => (float) ($row['amount'] ?? 0))) }}</th>
                                        <th class="text-right">{{ $fmt($expenseRows->sum(fn ($row) => (float) ($row['paid'] ?? 0))) }}</th>
                                        <th class="text-right @if($expenseRows->sum(fn ($row) => (float) ($row['due'] ?? 0)) != 0) due-negative @endif">
                                            {{ $fmt($expenseRows->sum(fn ($row) => (float) ($row['due'] ?? 0))) }}
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>

<div class="modal fade" id="location_modal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="locationModalLabel">Select Business Locations</h4>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:10px;">
                    <button type="button" class="btn btn-xs btn-primary" id="select_all_locations">Select All</button>
                    <button type="button" class="btn btn-xs btn-default" id="deselect_all_locations">Deselect All</button>
                </div>
                @foreach($locations as $location)
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" class="location-checkbox" value="{{ $location->id }}"
                                   @if(in_array($location->id, $filters['location_ids'])) checked @endif>
                            {{ $location->name }}
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="apply_locations">Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cashier_modal" tabindex="-1" role="dialog" aria-labelledby="cashierModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cashierModalLabel">Select Cashiers</h4>
            </div>
            <div class="modal-body">
                @foreach($cashiers as $cashier)
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" class="cashier-checkbox" value="{{ $cashier->id }}"
                                   @if(in_array($cashier->id, $filters['user_ids'])) checked @endif>
                            {{ $cashier->name }}
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="apply_cashiers">Apply</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script>
    $(function () {
        $('#local_cashier_report_app .select2').select2();
        const $startDate = $('#start_date');
        const $endDate = $('#end_date');
        const $dr = $('#date_range_picker');
        const start = $startDate.val() ? moment($startDate.val(), 'YYYY-MM-DD') : moment();
        const end = $endDate.val() ? moment($endDate.val(), 'YYYY-MM-DD') : moment();

        $dr.daterangepicker(
            $.extend(true, {}, dateRangeSettings, {
                startDate: start,
                endDate: end
            }),
            function (s, e) {
                $dr.val(s.format(moment_date_format) + ' ~ ' + e.format(moment_date_format));
                $startDate.val(s.format('YYYY-MM-DD'));
                $endDate.val(e.format('YYYY-MM-DD'));
            }
        );
        $dr.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));

        $(document).on('input', '.summary-filter', function () {
            var term = ($(this).val() || '').toLowerCase();
            var target = $(this).data('target');
            $(target).find('tbody tr').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(term) !== -1);
            });
        });

        $('#apply_locations').on('click', function () {
            var selectedIds = [];
            var selectedNames = [];
            $('.location-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
                selectedNames.push($(this).closest('label').text().trim());
            });

            $('#location_ids_hidden option').prop('selected', false);
            selectedIds.forEach(function (id) {
                $('#location_ids_hidden option[value="' + id + '"]').prop('selected', true);
            });

            $('#location_preview').val(selectedNames.join(', '));
            $('#location_modal').modal('hide');
        });

        $('#select_all_locations').on('click', function () {
            $('.location-checkbox').prop('checked', true);
        });

        $('#deselect_all_locations').on('click', function () {
            $('.location-checkbox').prop('checked', false);
        });

        $('#apply_cashiers').on('click', function () {
            var selectedIds = [];
            var selectedNames = [];
            $('.cashier-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
                selectedNames.push($(this).closest('label').text().trim());
            });

            $('#user_ids_hidden option').prop('selected', false);
            selectedIds.forEach(function (id) {
                $('#user_ids_hidden option[value="' + id + '"]').prop('selected', true);
            });

            $('#cashier_preview').val(selectedNames.join(', '));
            $('#cashier_modal').modal('hide');
        });

        if ($.fn.DataTable && $('#local_cashier_report_table').length) {
            $('#local_cashier_report_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                pagingType: 'full_numbers',
                dom: 'Bfrtip',
                buttons: ['pageLength', 'colvis', 'excel', 'print']
            });
        }
        function localCashierCopyCellFormatter(data, row, column, node) {
            var $node = $(node);
            var isDateCell = $node.find('.date-action-text').length > 0;
            var text = '';

            if (isDateCell) {
                text = $node.find('.date-action-text').first().text();
            } else {
                text = $('<div>').html(data === null || data === undefined ? '' : data).text();
            }

            text = (text || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();

            if (text === '-' || /^[^\dA-Za-z]?\s*-\s*$/.test(text)) {
                return '';
            }

            var headerText = $node.closest('table').find('thead th').eq(column).text().replace(/\s+/g, ' ').trim().toLowerCase();
            var isoDate = text.match(/\b\d{4}-\d{2}-\d{2}\b/);
            var isDateTimeText = /^\d{4}-\d{2}-\d{2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(text);
            if ((isDateCell || headerText.indexOf('date') !== -1 || isDateTimeText) && isoDate) {
                return isoDate[0];
            }

            return text;
        }

        function localCashierCopyExportOptions(columns) {
            return {
                columns: columns || ':visible',
                format: {
                    body: localCashierCopyCellFormatter
                }
            };
        }

        function localCashierCopyButton(options) {
            options = options || {};

            var button = {
                extend: 'copy',
                text: 'Copy',
                className: 'btn btn-sm btn-outline-primary',
                exportOptions: localCashierCopyExportOptions(options.columns || ':visible')
            };

            if (options.withoutHeader) {
                button.header = false;
                button.title = null;
            }

            return button;
        }

        function localCashierAdjustVisibleDataTables() {
            if (! $.fn.DataTable) {
                return;
            }

            setTimeout(function () {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().draw(false);
            }, 0);

            setTimeout(function () {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().draw(false);
            }, 250);
        }

        if ($.fn.DataTable && $('#local_cashier_sales_detail_table').length) {
            $('#local_cashier_sales_detail_table').DataTable({
                paging: true,
                searching: true,
                ordering: false,
                info: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                pagingType: 'full_numbers',
                scrollX: true,
                responsive: false,
                dom: "<'row'<'col-sm-4'l><'col-sm-4 text-center'B><'col-sm-4'f>>rt<'row'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    zeroRecords: 'No data available in table',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 to 0 of 0 entries'
                },
                columnDefs: [
                    { targets: 'never-visible', visible: false, searchable: true }
                ],
                buttons: [
                    { extend: 'copy', text: 'Copy', className: 'btn btn-sm btn-outline-primary', exportOptions: localCashierCopyExportOptions(':visible') },
                    { extend: 'csv', text: 'Export CSV', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'excel', text: 'Export Excel', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'print', text: 'Print', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'colvis', text: 'Column visibility', className: 'btn btn-sm btn-outline-primary', columns: ':not(.never-visible)' },
                    { extend: 'pdf', text: 'Export PDF', className: 'btn btn-sm btn-outline-primary' }
                ]
            });
        }
        if ($.fn.DataTable && $('#local_cashier_expenses_detail_table').length) {
            $('#local_cashier_expenses_detail_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[5, 'asc']],
                info: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                pagingType: 'full_numbers',
                scrollX: true,
                responsive: false,
                dom: "<'row'<'col-sm-4'l><'col-sm-4 text-center'B><'col-sm-4'f>>rt<'row'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    zeroRecords: 'No data available in table',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 to 0 of 0 entries'
                },
                buttons: [
                    localCashierCopyButton({
                        withoutHeader: true,
                        columns: function(index, data, node) {
                            return index !== 0 && $(node).is(':visible');
                        }
                    }),
                    { extend: 'csv', text: 'Export CSV', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'excel', text: 'Export Excel', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'print', text: 'Print', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'colvis', text: 'Column visibility', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'pdf', text: 'Export PDF', className: 'btn btn-sm btn-outline-primary' }
                ]
            });
        }
        if ($.fn.DataTable && $('#local_cashier_customer_due_payment_table').length) {
            $('#local_cashier_customer_due_payment_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[13, 'asc'], [1, 'asc']],
                info: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                pagingType: 'full_numbers',
                scrollX: true,
                responsive: false,
                dom: "<'row'<'col-sm-4'l><'col-sm-4 text-center'B><'col-sm-4'f>>rt<'row'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    zeroRecords: 'No data available in table',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 to 0 of 0 entries'
                },
                buttons: [
                    localCashierCopyButton({
                        withoutHeader: true,
                        columns: function(index, data, node) {
                            return index !== 0 && $(node).is(':visible');
                        }
                    }),
                    { extend: 'csv', text: 'Export CSV', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'excel', text: 'Export Excel', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'print', text: 'Print', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'colvis', text: 'Column visibility', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'pdf', text: 'Export PDF', className: 'btn btn-sm btn-outline-primary' }
                ]
            });
        }
        if ($.fn.DataTable && $('#local_cashier_collection_payment_table').length) {
            $('#local_cashier_collection_payment_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[4, 'asc'], [0, 'desc']],
                info: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                pagingType: 'full_numbers',
                scrollX: true,
                responsive: false,
                dom: "<'row'<'col-sm-4'l><'col-sm-4 text-center'B><'col-sm-4'f>>rt<'row'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    zeroRecords: 'No data available in table',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 to 0 of 0 entries'
                },
                buttons: [
                    localCashierCopyButton({
                        withoutHeader: true,
                        columns: function(index, data, node) {
                            return $(node).is(':visible');
                        }
                    }),
                    { extend: 'csv', text: 'Export CSV', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'excel', text: 'Export Excel', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'print', text: 'Print', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'colvis', text: 'Column visibility', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'pdf', text: 'Export PDF', className: 'btn btn-sm btn-outline-primary' }
                ]
            });
        }
        if ($.fn.DataTable && $('.local-module-sales-detail-table').length) {
            $('.local-module-sales-detail-table').each(function () {
                var $moduleTable = $(this);
                var locationOrderColumn = $moduleTable.find('thead th.all-sale-location-column').index();
                if (locationOrderColumn < 0) {
                    locationOrderColumn = $moduleTable.find('thead th.sale-location-column').index();
                }
                var isAllSaleDetailTable = $moduleTable.attr('id') === 'local_cashier_all_sale_detail_table';
                var moduleCopyButton = localCashierCopyButton({
                    withoutHeader: isAllSaleDetailTable
                });

                $(this).DataTable({
                    paging: true,
                    searching: true,
                    ordering: true,
                    order: locationOrderColumn >= 0 ? [[locationOrderColumn, 'asc']] : [],
                    info: true,
                    autoWidth: false,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    pagingType: 'full_numbers',
                    scrollX: ! isAllSaleDetailTable,
                    responsive: false,
                    dom: "<'row'<'col-sm-4'l><'col-sm-4 text-center'B><'col-sm-4'f>>rt<'row'<'col-sm-6'i><'col-sm-6'p>>",
                    language: {
                        search: 'Search:',
                        lengthMenu: 'Show _MENU_ entries',
                        zeroRecords: 'No data available in table',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        infoEmpty: 'Showing 0 to 0 of 0 entries'
                    },
                    columnDefs: [
                        { targets: 'never-visible', visible: false, searchable: true }
                    ],
                    buttons: [
                        moduleCopyButton,
                        { extend: 'csv', text: 'Export CSV', className: 'btn btn-sm btn-outline-primary', exportOptions: { columns: ':visible' } },
                        { extend: 'excel', text: 'Export Excel', className: 'btn btn-sm btn-outline-primary', exportOptions: { columns: ':visible' } },
                        { extend: 'print', text: 'Print', className: 'btn btn-sm btn-outline-primary', exportOptions: { columns: ':visible' } },
                        { extend: 'colvis', text: 'Column visibility', className: 'btn btn-sm btn-outline-primary', columns: ':not(.never-visible)' },
                        { extend: 'pdf', text: 'Export PDF', className: 'btn btn-sm btn-outline-primary', exportOptions: { columns: ':visible' } }
                    ]
                });
            });
        }
        function escapeDataTableRegex(value) {
            return $.fn.dataTable.util.escapeRegex(value || '');
        }

        function exactMatchAnyRegex(values) {
            values = $.isArray(values) ? values : (values ? [values] : []);
            values = values.filter(function (value) {
                return value !== null && value !== undefined && value !== '';
            });

            return values.length ? '^(' + values.map(escapeDataTableRegex).join('|') + ')$' : '';
        }

        function localCashierColumnIndexByClass(table, className) {
            var foundIndex = -1;

            table.columns().every(function (index) {
                if (foundIndex === -1 && $(this.header()).hasClass(className)) {
                    foundIndex = index;
                }
            });

            return foundIndex;
        }

        function applyAllSaleFilters(tableId) {
            var table = $('#' + tableId).DataTable();
            var locations = $('.all-sale-location-filter[data-table-id="' + tableId + '"]').val();
            var cashiers = $('.all-sale-cashier-filter[data-table-id="' + tableId + '"]').val();
            var locationColumn = localCashierColumnIndexByClass(table, 'all-sale-location-column');
            var cashierColumn = localCashierColumnIndexByClass(table, 'all-sale-cashier-column');

            if (locationColumn >= 0) {
                table.column(locationColumn).search(exactMatchAnyRegex(locations), true, false);
            }
            if (cashierColumn >= 0) {
                table.column(cashierColumn).search(exactMatchAnyRegex(cashiers), true, false);
            }

            table.draw();
        }

        $(document).on('change', '.all-sale-location-filter, .all-sale-cashier-filter', function () {
            applyAllSaleFilters($(this).data('table-id'));
        });
        $(document).on('click', '.all-sale-select-all, .all-sale-clear-select', function () {
            var tableId = $(this).data('table-id');
            var $select = $($(this).data('target') + '[data-table-id="' + tableId + '"]');
            var values = $(this).hasClass('all-sale-select-all')
                ? $select.find('option').map(function () { return this.value; }).get()
                : [];

            $select.val(values).trigger('change');
        });
        $(document).on('shown.bs.collapse hidden.bs.collapse', '.local-detail-tab-content .collapse', function () {
            var isOpen = $(this).hasClass('in');
            var $button = $('[data-toggle="collapse"][data-target="#' + this.id + '"]');
            $button.html('<i class="fa fa-filter"></i> ' + (isOpen ? 'Hide filters' : 'Filters'));
        });
        $(document).on('shown.bs.collapse hidden.bs.collapse', '#classic_summary_section, #classic_plain_summary_section, #summary_cards_section', function () {
            var isOpen = $(this).hasClass('in');
            var $button = $('[data-toggle="collapse"][data-target="#' + this.id + '"]');
            $button.html('<i class="fa fa-chevron-' + (isOpen ? 'up' : 'down') + '"></i> ' + (isOpen ? 'Hide Summary' : 'Show Summary'));
        });
        var dateActionCloseTimer = null;
        $(document)
            .on('mouseenter', '.date-action-wrap', function () {
                clearTimeout(dateActionCloseTimer);
                $('.date-action-wrap').not(this).removeClass('is-open');
                $(this).addClass('is-open');
            })
            .on('mouseleave', '.date-action-wrap', function () {
                var $wrap = $(this);
                dateActionCloseTimer = setTimeout(function () {
                    if (! $wrap.hasClass('is-pinned')) {
                        $wrap.removeClass('is-open');
                    }
                }, 700);
            });
        $(document).on('click', '.date-action-text', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $wrap = $(this).closest('.date-action-wrap');
            $('.date-action-wrap').not($wrap).removeClass('is-open is-pinned');
            $wrap.toggleClass('is-open is-pinned');
        });
        $(document).on('click', '.date-action-pill', function () {
            $(this).closest('.date-action-wrap').removeClass('is-open is-pinned');
        });
        $(document).on('click', function (e) {
            if (! $(e.target).closest('.date-action-wrap').length) {
                $('.date-action-wrap').removeClass('is-open is-pinned');
            }
        });
        if (window.location.hash && $('.local-detail-tabs a[href="' + window.location.hash + '"]').length) {
            $('.local-detail-tabs a[href="' + window.location.hash + '"]').tab('show');
        }
        localCashierAdjustVisibleDataTables();
        $(document).on('click', '.local-detail-tab-link', function (e) {
            var targetTab = $(this).data('target-tab');
            if (! targetTab || ! $('.local-detail-tabs a[href="' + targetTab + '"]').length) {
                return;
            }

            e.preventDefault();
            $('.local-detail-tabs a[href="' + targetTab + '"]').tab('show');

            if ($('.local-detail-tabs').length) {
                $('html, body').animate({
                    scrollTop: $('.local-detail-tabs').offset().top - 80
                }, 250);
            }
        });
        $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
            localCashierAdjustVisibleDataTables();

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search + $(this).attr('href'));
            }
        });
        $(window).on('load resize', localCashierAdjustVisibleDataTables);
    });
</script>
<style>
#local_cashier_report_app .sheet-table {
    border-collapse: collapse;
    width: 100%;
    font-size: 15px;
    line-height: 1.35;
}
#local_cashier_report_app.sheet-theme .sheet-table th,
#local_cashier_report_app.sheet-theme .sheet-table td {
    border: 1px dashed #000 !important;
    vertical-align: middle;
    padding: 8px 10px;
}
#local_cashier_report_app.sheet-theme .sheet-table thead th {
    background: #d9edf7;
    font-weight: 700;
    font-size: 16px;
    letter-spacing: 0.2px;
}
#local_cashier_report_app.sheet-theme .sheet-table tbody tr.row-sale {
    background: #fde2ea;
}
#local_cashier_report_app.sheet-theme .sheet-table tfoot tr.row-total {
    background: #dff0d8;
}
#local_cashier_report_app.sheet-theme .sheet-table tfoot tr.row-summary {
    background: #dff0d8;
}
#local_cashier_report_app.sheet-theme .sheet-table .name-main {
    color: #1b62d1;
    font-weight: 700;
}
#local_cashier_report_app.sheet-theme .sheet-table .due-negative {
    color: #cc0000;
    font-weight: 700;
}
#local_cashier_report_app.classic-theme .sheet-table th,
#local_cashier_report_app.classic-theme .sheet-table td {
    border: 1px solid #d9d9d9 !important;
    vertical-align: middle;
    padding: 8px 10px;
}
#local_cashier_report_app.classic-theme .sheet-table thead th {
    background: #f5f7fa;
    font-weight: 700;
    font-size: 15px;
}
#local_cashier_report_app.classic-theme .sheet-table tbody tr.row-sale {
    background: #fff;
}
#local_cashier_report_app.classic-theme .sheet-table tfoot tr.row-total {
    background: #f7f7f7;
}
#local_cashier_report_app.classic-theme .sheet-table .name-main {
    color: #111;
    font-weight: 600;
}
#local_cashier_report_app.classic-theme .sheet-table .due-negative {
    color: #d9534f;
    font-weight: 700;
}
#local_cashier_report_app .report-view-table {
    font-size: 14px;
}
#local_cashier_report_app .report-view-table thead th {
    font-size: 15px !important;
    font-weight: 700 !important;
    background: #d9edf7 !important;
}
#local_cashier_report_app .report-view-table td,
#local_cashier_report_app .report-view-table th {
    border: 1px dashed #000 !important;
    padding: 7px 10px;
}
#local_cashier_report_app .report-view-table tbody tr.row-sale {
    background: #fde2ea;
}
#local_cashier_report_app .report-view-table tfoot tr.row-total {
    background: #d9edf7;
}
#local_cashier_report_app .report-view-table tfoot tr.row-summary {
    background: #dff0d8;
}
#local_cashier_report_app .btn {
    border-radius: 8px;
}
#local_cashier_report_app .form-control {
    height: 38px;
    font-size: 14px;
    border-radius: 8px;
}
#local_cashier_report_app .dataTables_wrapper .dataTables_filter input,
#local_cashier_report_app .dataTables_wrapper .dataTables_length select {
    border: 1px solid #cfd6df;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 13px;
}
#local_cashier_report_app .dataTables_wrapper .dt-buttons .btn {
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 13px;
}
#local_cashier_report_app .section-title {
    margin: 16px 0 12px;
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}
#local_cashier_report_app .summary-collapse-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 16px 0 12px;
}
#local_cashier_report_app .summary-collapse-header .section-title {
    margin: 0;
}
#local_cashier_report_app .summary-collapse-toggle {
    white-space: nowrap;
}
#local_cashier_report_app .local-filter-wrap {
    position: relative;
}
#local_cashier_report_app .local-filter-reset {
    position: absolute;
    top: 7px;
    left: 100px;
    z-index: 2;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    padding: 5px 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
}
#local_cashier_report_app .local-filter-reset:hover,
#local_cashier_report_app .local-filter-reset:focus {
    background: #e0f2fe;
    border-color: #38bdf8;
    color: #075985;
    text-decoration: none;
}
#local_cashier_report_app .local-filter-reset i {
    margin-right: 4px;
}
#local_cashier_report_app .summary-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    grid-gap: 14px;
    margin-bottom: 18px;
}
#local_cashier_report_app .summary-kpi-card {
    position: relative;
    overflow: hidden;
    border-radius: 14px;
    padding: 14px 16px;
    border: 1px solid #d9e4f5;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
}
#local_cashier_report_app .summary-kpi-card::after {
    content: '';
    position: absolute;
    right: -28px;
    top: -28px;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
}
#local_cashier_report_app .summary-kpi-card .kpi-label {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: .4px;
    position: relative;
    z-index: 1;
}
#local_cashier_report_app .summary-kpi-card .kpi-value {
    margin-top: 4px;
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    position: relative;
    z-index: 1;
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(1) {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(2) {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(3) {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(4) {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(5) {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
}
#local_cashier_report_app .summary-kpi-grid .summary-kpi-card:nth-child(6) {
    background: linear-gradient(135deg, #be123c 0%, #9f1239 100%);
}
#local_cashier_report_app .summary-panel {
    background: #fff;
    border: 1px solid #dbe5f2;
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}
#local_cashier_report_app .summary-panel h4 {
    margin: 4px 0 12px;
    font-size: 17px;
    color: #1e3a8a;
    border-left: 4px solid #2563eb;
    padding-left: 10px;
}
#local_cashier_report_app .summary-panel .summary-filter {
    margin-bottom: 10px;
}
#local_cashier_report_app .summary-table thead th {
    background: #eff6ff;
}
#local_cashier_report_app .summary-table tbody tr:nth-child(odd) td {
    background: #fcfdff;
}
#local_cashier_report_app .summary-table tbody tr:nth-child(even) td {
    background: #f7fbff;
}
#local_cashier_report_app .summary-table tfoot th {
    background: #e0ecff;
}
#local_cashier_report_app .summary-link {
    color: #1d4ed8;
    font-weight: 700;
    text-decoration: none;
    border-bottom: 1px dashed #93c5fd;
}
#local_cashier_report_app .summary-link:hover {
    color: #1e40af;
    border-bottom-color: #1e40af;
}
#local_cashier_report_app .box-header .table-meta {
    margin-top: 8px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
#local_cashier_report_app .box-header .table-meta span {
    background: #eef2ff;
    color: #1e3a8a;
    border-radius: 999px;
    padding: 2px 10px;
    font-size: 12px;
    font-weight: 600;
}
#local_cashier_report_app .local-detail-tabs {
    border-bottom: 1px solid #cbd5e1;
}
#local_cashier_report_app .local-detail-tabs > li > a {
    border-radius: 6px 6px 0 0;
    color: #334155;
    font-weight: 700;
    padding: 9px 14px;
}
#local_cashier_report_app .local-detail-tabs > li.active > a,
#local_cashier_report_app .local-detail-tabs > li.active > a:focus,
#local_cashier_report_app .local-detail-tabs > li.active > a:hover {
    background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
    border-color: #1d4ed8 #1d4ed8 transparent;
    color: #fff;
}
#local_cashier_report_app .local-detail-tabs > li > a:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}
#local_cashier_report_app .local-detail-tab-content {
    padding-top: 12px;
}
#local_cashier_report_app .sale-table-filter-toggle {
    margin-bottom: 8px;
}
#local_cashier_report_app .all-sale-table-filters {
    margin: 0 0 10px 0;
    padding: 10px 10px 4px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
}
#local_cashier_report_app .all-sale-table-filters label {
    color: #334155;
    font-weight: 700;
}
#local_cashier_report_app .all-sale-table-filters .select2-container {
    width: 100% !important;
}
#local_cashier_report_app .all-sale-filter-actions {
    margin-top: 6px;
}
#local_cashier_report_app .all-sale-filter-actions .btn + .btn {
    margin-left: 4px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dt-buttons {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dataTables_filter {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dataTables_filter {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dataTables_filter input {
    width: 220px;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dataTables_filter input {
    width: 220px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dataTables_length select {
    min-width: 78px;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dataTables_length select {
    min-width: 78px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons {
    text-align: center;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dt-buttons {
    text-align: center;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons .btn {
    border: 1px solid #c7cfdb;
    background: #fff;
    color: #5d6b82;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dt-buttons .btn {
    border: 1px solid #c7cfdb;
    background: #fff;
    color: #5d6b82;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons .btn:hover {
    background: #f5f8fc;
}
#local_cashier_report_app #local_cashier_expenses_detail_table_wrapper .dt-buttons .btn:hover {
    background: #f5f8fc;
}
#local_cashier_report_app #local_cashier_accessory_sales_detail_table_wrapper .dt-buttons,
#local_cashier_report_app #local_cashier_service_sales_detail_table_wrapper .dt-buttons {
    margin-bottom: 8px;
    text-align: center;
}
#local_cashier_report_app #local_cashier_accessory_sales_detail_table_wrapper .dataTables_filter,
#local_cashier_report_app #local_cashier_service_sales_detail_table_wrapper .dataTables_filter {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_accessory_sales_detail_table_wrapper .dataTables_filter input,
#local_cashier_report_app #local_cashier_service_sales_detail_table_wrapper .dataTables_filter input {
    width: 220px;
}
#local_cashier_report_app #local_cashier_accessory_sales_detail_table_wrapper .dataTables_length select,
#local_cashier_report_app #local_cashier_service_sales_detail_table_wrapper .dataTables_length select {
    min-width: 78px;
}
#local_cashier_report_app #local_cashier_accessory_sales_detail_table_wrapper .dt-buttons .btn,
#local_cashier_report_app #local_cashier_service_sales_detail_table_wrapper .dt-buttons .btn {
    border: 1px solid #c7cfdb;
    background: #fff;
    color: #5d6b82;
}
#local_cashier_report_app #local_cashier_accessory_sales_detail_table_wrapper .dt-buttons .btn:hover,
#local_cashier_report_app #local_cashier_service_sales_detail_table_wrapper .dt-buttons .btn:hover {
    background: #f5f8fc;
}
#local_cashier_report_app #local_cashier_sales_detail_table th,
#local_cashier_report_app #local_cashier_sales_detail_table td {
    white-space: nowrap;
    vertical-align: middle;
}
#local_cashier_report_app #local_cashier_expenses_detail_table th,
#local_cashier_report_app #local_cashier_expenses_detail_table td {
    white-space: nowrap;
    vertical-align: middle;
}
#local_cashier_report_app .local-module-sales-detail-table th,
#local_cashier_report_app .local-module-sales-detail-table td {
    white-space: nowrap;
    vertical-align: middle;
}
#local_cashier_report_app #local_cashier_sales_detail_table thead th {
    background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    font-weight: 700;
}
#local_cashier_report_app #local_cashier_expenses_detail_table thead th {
    background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    font-weight: 700;
}
#local_cashier_report_app .local-module-sales-detail-table thead th {
    background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    font-weight: 700;
}
#local_cashier_report_app #local_cashier_sales_detail_table tfoot th {
    background: #e2e8f0;
    border-top: 2px solid #0f172a;
    color: #0f172a;
    font-weight: 800;
    white-space: nowrap;
}
#local_cashier_report_app #local_cashier_expenses_detail_table tfoot th {
    background: #e2e8f0;
    border-top: 2px solid #0f172a;
    color: #0f172a;
    font-weight: 800;
    white-space: nowrap;
}
#local_cashier_report_app .local-module-sales-detail-table tfoot th {
    background: #e2e8f0;
    border-top: 2px solid #0f172a;
    color: #0f172a;
    font-weight: 800;
    white-space: nowrap;
}
#local_cashier_report_app #local_cashier_sales_detail_table td .btn {
    margin-right: 4px;
}
#local_cashier_report_app #local_cashier_expenses_detail_table td .btn {
    margin-right: 4px;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr:nth-child(odd) td {
    background: #fcfdff;
}
#local_cashier_report_app #local_cashier_expenses_detail_table tbody tr:nth-child(odd) td {
    background: #fcfdff;
}
#local_cashier_report_app .local-module-sales-detail-table tbody tr:nth-child(odd) td {
    background: #fcfdff;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr:hover td {
    background: #eaf3ff;
}
#local_cashier_report_app #local_cashier_expenses_detail_table tbody tr:hover td {
    background: #eaf3ff;
}
#local_cashier_report_app .local-module-sales-detail-table tbody tr:hover td {
    background: #eaf3ff;
}
#local_cashier_report_app #local_cashier_all_sale_detail_table {
    table-layout: auto;
    width: 100% !important;
}
#local_cashier_report_app .all-sale-fit-table-wrapper {
    overflow-x: visible;
}
#local_cashier_report_app #local_cashier_all_sale_detail_table th,
#local_cashier_report_app #local_cashier_all_sale_detail_table td {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: normal;
    font-size: 12px;
    line-height: 1.25;
    padding: 5px 6px !important;
}
#local_cashier_report_app #local_cashier_all_sale_detail_table td.text-right,
#local_cashier_report_app #local_cashier_all_sale_detail_table th.text-right {
    white-space: nowrap;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.customer-group-separator td {
    background: #f1f5f9 !important;
    border-top: 3px solid #0f172a !important;
    border-bottom: 1px solid #cbd5e1 !important;
    color: #334155;
    font-weight: 700;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.customer-group-separator.installment-separator td {
    background: #fff7ed !important;
    border-top-color: #f59e0b !important;
    color: #92400e;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.customer-group-separator.aeon-separator td {
    background: #eff6ff !important;
    border-top-color: #2563eb !important;
    color: #1e40af;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.customer-group-separator.loan-payment-separator td {
    background: #ecfdf5 !important;
    border-top-color: #059669 !important;
    color: #065f46;
}
#local_cashier_report_app #local_cashier_sales_detail_table .group-separator-label {
    font-size: 14px;
    white-space: nowrap;
}
#local_cashier_report_app #local_cashier_sales_detail_table .group-separator-note {
    font-size: 12px;
    color: #64748b;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.installment-customer-row td {
    background: #fffbeb;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.installment-customer-row:hover td {
    background: #fef3c7;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.aeon-customer-row td {
    background: #eff6ff;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.aeon-customer-row:hover td {
    background: #dbeafe;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.loan-payment-customer-row td {
    background: #ecfdf5;
}
#local_cashier_report_app #local_cashier_sales_detail_table tbody tr.loan-payment-customer-row:hover td {
    background: #d1fae5;
}
#local_cashier_report_app .customer-group-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
}
#local_cashier_report_app .customer-group-pill.installment {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
}
#local_cashier_report_app .customer-group-pill.normal {
    background: #e0f2fe;
    border-color: #7dd3fc;
    color: #075985;
}
#local_cashier_report_app .customer-group-pill.aeon {
    background: #dbeafe;
    border-color: #60a5fa;
    color: #1d4ed8;
}
#local_cashier_report_app .customer-group-pill.loan-payment {
    background: #d1fae5;
    border-color: #34d399;
    color: #047857;
}
#local_cashier_report_app .action-icon-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
}
#local_cashier_report_app .action-icon-btn i {
    font-size: 12px;
}
#local_cashier_report_app .expense-payment-list {
    min-width: 220px;
    white-space: normal !important;
}
#local_cashier_report_app .expense-payment-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 2px 4px 2px 0;
    padding: 2px 8px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1e40af;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
#local_cashier_report_app .expense-payment-pill strong {
    color: #0f172a;
    font-weight: 800;
}
#local_cashier_report_app .action-view {
    border-color: #c9d3e6;
    color: #4b5b79;
    background: #fff;
}
#local_cashier_report_app .action-edit {
    background: #2f6feb;
    border-color: #2f6feb;
    color: #fff;
}
#local_cashier_report_app .date-action-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding-bottom: 8px;
    margin-bottom: -8px;
    z-index: 2;
}
#local_cashier_report_app .date-action-text {
    border-bottom: 1px dotted #94a3b8;
    cursor: default;
}
#local_cashier_report_app .date-action-popover {
    position: absolute;
    left: 0;
    top: 100%;
    margin-top: 0;
    z-index: 1000;
    display: none;
    gap: 6px;
    align-items: center;
    padding: 6px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
    white-space: nowrap;
}
#local_cashier_report_app .date-action-wrap:hover .date-action-popover,
#local_cashier_report_app .date-action-wrap.is-open .date-action-popover,
#local_cashier_report_app .date-action-popover:hover {
    display: inline-flex;
}
#local_cashier_report_app .date-action-popover::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: -8px;
    height: 8px;
}
#local_cashier_report_app .date-action-popover::before {
    content: "";
    position: absolute;
    left: 14px;
    top: -6px;
    width: 10px;
    height: 10px;
    background: #fff;
    border-left: 1px solid #cbd5e1;
    border-top: 1px solid #cbd5e1;
    transform: rotate(45deg);
}
#local_cashier_report_app .date-action-pill {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
}
#local_cashier_report_app .date-action-pill:hover {
    color: #1e40af;
    background: #dbeafe;
    text-decoration: none;
}
#local_cashier_report_app .date-action-edit {
    border-color: #bbf7d0;
    background: #ecfdf5;
    color: #047857;
}
#local_cashier_report_app .date-action-edit:hover {
    background: #d1fae5;
    color: #065f46;
}
#local_cashier_report_app .dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 8px !important;
    margin: 0 2px;
}
#local_cashier_report_app .dataTables_scrollBody {
    max-height: none !important;
    height: auto !important;
    overflow-y: visible !important;
}
#local_cashier_report_app .dataTables_scroll {
    overflow-y: visible !important;
}

/* Global friendly UI across all tabs */
#local_cashier_report_app .table-responsive {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 8px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
}
#local_cashier_report_app .sheet-table,
#local_cashier_report_app .summary-table {
    border-radius: 10px;
    overflow: hidden;
}
#local_cashier_report_app .sheet-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
}
#local_cashier_report_app .sheet-table tbody tr:hover td {
    background: #f8fafc;
}
#local_cashier_report_app .row-total th,
#local_cashier_report_app .row-total td {
    font-weight: 700;
}
#local_cashier_report_app .row-summary th,
#local_cashier_report_app .row-summary td {
    font-weight: 600;
}
.content-header .report-tab-btn {
    border-radius: 999px;
    padding: 8px 16px;
    margin-right: 6px;
    border: 1px solid #cbd5e1;
}
.content-header .report-tab-btn.btn-default {
    background: #f8fafc;
    color: #334155;
}
.content-header .report-tab-btn.btn-primary {
    background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
    border-color: #1e40af;
}

@media (max-width: 991px) {
    #local_cashier_report_app .summary-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    #local_cashier_report_app .table-responsive {
        border-radius: 10px;
        padding: 6px;
    }
    #local_cashier_report_app .sheet-table th,
    #local_cashier_report_app .sheet-table td {
        font-size: 13px;
        padding: 6px 8px !important;
    }
    .content-header .report-tab-btn {
        margin-bottom: 6px;
    }
}
@media (max-width: 576px) {
    #local_cashier_report_app .summary-kpi-grid {
        grid-template-columns: 1fr;
    }
    #local_cashier_report_app .summary-panel h4,
    #local_cashier_report_app .section-title {
        font-size: 16px;
    }
}

/* Business Location Report visual refresh */
#local_cashier_report_app .business-location-table {
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
    border: 1px solid #dbe5f2;
}
#local_cashier_report_app .business-location-table thead th {
    background: linear-gradient(90deg, #0ea5e9 0%, #2563eb 100%) !important;
    color: #fff;
    font-weight: 700;
    border-color: #1f4fc4 !important;
}
#local_cashier_report_app .business-location-table tbody tr.row-sale:nth-child(odd) {
    background: #f8fbff;
}
#local_cashier_report_app .business-location-table tbody tr.row-sale:nth-child(even) {
    background: #eef6ff;
}
#local_cashier_report_app .business-location-table tbody tr.row-sale:hover {
    background: #dbeafe !important;
    transition: background-color .18s ease;
}
#local_cashier_report_app .business-location-table tbody tr.customer-group-breakdown-row td {
    background: #f8fafc;
    border-top: 1px solid #cbd5e1;
    color: #334155;
}
#local_cashier_report_app .business-location-table tbody tr.installment-breakdown-row td {
    background: #fff7ed;
    border-top: 2px solid #f59e0b;
    color: #92400e;
}
#local_cashier_report_app .business-location-table tbody tr.aeon-breakdown-row td {
    background: #eff6ff;
    border-top: 2px solid #2563eb;
    color: #1e40af;
}
#local_cashier_report_app .business-location-table tbody tr.loan-payment-breakdown-row td {
    background: #ecfdf5;
    border-top: 2px solid #059669;
    color: #065f46;
}
#local_cashier_report_app .business-location-table .customer-group-breakdown-name {
    padding-left: 24px;
}
#local_cashier_report_app .business-location-table .customer-group-breakdown-label {
    display: inline-block;
    min-width: 120px;
    font-weight: 700;
}
#local_cashier_report_app .sheet-table tbody tr.cashier-group-breakdown-row td {
    background: #f8fafc;
    border-top: 1px solid #cbd5e1;
    color: #334155;
}
#local_cashier_report_app .sheet-table tbody tr.module-dashboard-breakdown-row td {
    background: #f0f9ff;
    border-top: 1px solid #7dd3fc;
}
#local_cashier_report_app .sheet-table tbody tr.module-dashboard-breakdown-row:hover td {
    background: #e0f2fe;
}
#local_cashier_report_app .sheet-table tbody tr.cashier-group-breakdown-row.installment-breakdown-row td {
    background: #fff7ed;
    border-top: 2px solid #f59e0b;
    color: #92400e;
}
#local_cashier_report_app .sheet-table tbody tr.cashier-group-breakdown-row.aeon-breakdown-row td {
    background: #eff6ff;
    border-top: 2px solid #2563eb;
    color: #1e40af;
}
#local_cashier_report_app .sheet-table tbody tr.cashier-group-breakdown-row.loan-payment-breakdown-row td {
    background: #ecfdf5;
    border-top: 2px solid #059669;
    color: #065f46;
}
#local_cashier_report_app .sheet-table tbody tr.cashier-group-breakdown-row.customer-payment-breakdown-row td {
    background: #ecfdf5;
    border-top: 2px solid #059669;
    color: #065f46;
}
#local_cashier_report_app .sheet-table tbody tr.dashboard-customer-group-separator td {
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
    color: #0f172a;
    font-weight: 800;
    padding: 10px 12px;
}
#local_cashier_report_app .sheet-table tbody tr.dashboard-customer-group-separator.installment-separator td {
    background: #fff7ed;
    color: #9a3412;
}
#local_cashier_report_app .sheet-table tbody tr.dashboard-customer-group-separator.aeon-separator td {
    background: #eff6ff;
    color: #1e40af;
}
#local_cashier_report_app .sheet-table tbody tr.dashboard-customer-group-separator.loan-payment-separator td {
    background: #ecfdf5;
    color: #065f46;
}
#local_cashier_report_app .sheet-table tbody tr.dashboard-customer-group-separator.customer-payment-separator td {
    background: #ecfdf5;
    color: #065f46;
}
#local_cashier_report_app .sheet-table .cashier-group-breakdown-name {
    padding-left: 12px;
}
#local_cashier_report_app .sheet-table .customer-group-breakdown-location {
    color: inherit;
    font-weight: 600;
}
#local_cashier_report_app .sheet-table .customer-group-breakdown-location-group {
    margin-right: 8px;
    font-weight: 700;
}
#local_cashier_report_app .business-location-table tbody .name-main {
    color: #0f172a;
    font-weight: 700;
}
#local_cashier_report_app .business-location-table .qty-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #bae6fd;
}
#local_cashier_report_app .business-location-table .qty-badge-link {
    text-decoration: none;
}
#local_cashier_report_app .business-location-table .qty-badge-link:hover {
    background: #bae6fd;
    color: #0c4a6e;
}
#local_cashier_report_app .business-location-table tfoot tr.row-total {
    background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%) !important;
    color: #fff;
}
#local_cashier_report_app .business-location-table tfoot tr.row-summary {
    background: #fff7ed !important;
}
#local_cashier_report_app .business-location-table tfoot tr.row-summary th,
#local_cashier_report_app .business-location-table tfoot tr.row-summary td {
    border-top: 1px solid #fed7aa !important;
}
</style>
@endsection
