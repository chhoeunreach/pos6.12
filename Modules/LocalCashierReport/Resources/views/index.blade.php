@extends('layouts.app')
@section('title', 'Local Cashier Report')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Local Cashier Report</h1>
    @php
        $baseQuery = request()->query();
        $classicPlainQuery = array_merge($baseQuery, ['style_mode' => 'classic_plain']);
        $viewReportQuery = array_merge($baseQuery, ['style_mode' => 'view_report']);
    @endphp
    <div style="margin-top:10px;">
        <a href="{{ route('local-cashier-report.index') . '?' . http_build_query($classicPlainQuery) }}"
           class="btn {{ ($filters['style_mode'] ?? 'classic_plain') === 'classic_plain' ? 'btn-primary' : 'btn-default' }}">
            Dashboard
        </a>
        <a href="{{ route('local-cashier-report.index') . '?' . http_build_query($viewReportQuery) }}"
           class="btn {{ ($filters['style_mode'] ?? 'classic_plain') === 'view_report' ? 'btn-primary' : 'btn-default' }}">
            View Report
        </a>
    </div>
</section>

<section class="content no-print {{ in_array($filters['style_mode'], ['classic','classic_plain']) ? 'classic-theme' : 'sheet-theme' }}" id="local_cashier_report_app" style="font-family: {{ $khmerFontFamily }};">
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
    @endphp
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
                <a href="{{ route('local-cashier-report.index', ['style_mode' => 'classic_plain']) }}" class="btn btn-default">Reset</a>
                <a href="{{ route('local-cashier-report.export', request()->query()) }}" class="btn btn-success">Export Excel</a>
                <a href="{{ route('local-cashier-report.print', request()->query()) }}" target="_blank" class="btn btn-info">Print</a>
            </div>
        </form>
    @endcomponent

    @if($filters['style_mode'] === 'view_report')
    <div class="table-responsive">
        <table class="table sheet-table report-view-table" id="local_cashier_report_table">
            <thead>
                <tr>
                    <th>Cashier/User</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                    @endforeach
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Actual Income</th>
                    <th class="text-right">Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['rows'] as $row)
                    <tr class="row-sale">
                        <td class="name-main">{{ $row['cashier_name'] }}</td>
                        @foreach($report['payment_columns'] as $method)
                            <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($row['expenses'] ?? null) }}</td>
                        <td class="text-right">{{ $fmt($row['actual_income'] ?? null) }}</td>
                        <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due'] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <th>Total Paid</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                    <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                    <th class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                </tr>
                <tr class="row-summary">
                    <th colspan="{{ count($report['payment_columns']) + 1 }}" class="text-right">Expenses</th>
                    <th class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                    <th class="text-right">$ -</th>
                    <th class="text-right">$ -</th>
                </tr>
                <tr class="row-summary">
                    <th colspan="{{ count($report['payment_columns']) + 1 }}" class="text-right">Actual Total Income (Paid - Expenses - Sell Return)</th>
                    <th class="text-right">$ -</th>
                    <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                    <th class="text-right">$ -</th>
                </tr>
                <tr class="row-summary">
                    <th colspan="{{ count($report['payment_columns']) + 1 }}" class="text-right">Due</th>
                    <th class="text-right">$ -</th>
                    <th class="text-right">$ -</th>
                    <th class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="table-responsive">
        <table class="table sheet-table">
            <thead>
                <tr>
                    <th>Cashier/User</th>
                    <th>Business Location (Qty)</th>
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                    @endforeach
                    <th class="text-right">Total</th>
                    <th class="text-right">Due</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr class="row-sale">
                        <td class="name-main">{{ $row['cashier_name'] }}</td>
                        <td>{{ $row['location_qty_text'] }}</td>
                        @foreach($report['payment_columns'] as $method)
                            <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($row['total']) }}</td>
                        <td class="text-right @if($row['due'] != 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + count($report['payment_columns']) }}" class="text-center">No data found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <th colspan="{{ 2 + count($report['payment_columns']) }}" class="text-right">Grand Total</th>
                    <th class="text-right">{{ $fmt($report['grand_total']) }}</th>
                    <th class="text-right @if($report['grand_due'] != 0) due-negative @endif">{{ $fmt($report['grand_due']) }}</th>
                </tr>
                @if(($filters['style_mode'] ?? 'sheet') === 'classic_plain')
                    <tr class="row-summary">
                        <th colspan="2" class="text-right">Expenses</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th colspan="2" class="text-right">Actual Income</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th class="text-right @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                    </tr>
                @endif
            </tfoot>
        </table>
    </div>
    @endif

    @if($filters['style_mode'] === 'classic')
        <hr>
        <div class="row">
            <div class="col-md-4">
                <h4>Summary by User/Cashier</h4>
                <table class="table table-bordered table-condensed summary-table" id="sum_user">
                    <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        @foreach($report['summary_user'] as $r)
                            <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
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
            <div class="col-md-4">
                <h4>Summary by Location</h4>
                <table class="table table-bordered table-condensed summary-table" id="sum_location">
                    <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        @foreach($report['summary_location'] as $r)
                            <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
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
            <div class="col-md-4">
                <h4>Summary by Payment Method</h4>
                <table class="table table-bordered table-condensed summary-table" id="sum_payment">
                    <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        @foreach($report['summary_payment'] as $r)
                            <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format((float) ($r['qty'] ?? 0), 2), '0'), '.') }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.payment.amount', 0)) }}</th>
                            <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.payment.qty', 0), 2), '0'), '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    @if($filters['style_mode'] === 'classic_plain')
        <hr>
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
                <div class="kpi-label">Expenses</div>
                <div class="kpi-value">{{ $fmt($report['grand_expenses'] ?? null) }}</div>
            </div>
            <div class="summary-kpi-card">
                <div class="kpi-label">Due</div>
                <div class="kpi-value @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</div>
            </div>
        </div>
        <h4 class="section-title">Summary</h4>
        <div class="row">
            <div class="col-md-4">
                <div class="summary-panel">
                    <h4>Summary by User/Cashier</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_user_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_user'] as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
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
            <div class="col-md-4">
                <div class="summary-panel">
                    <h4>Summary by Location</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_location_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_location'] as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
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
            <div class="col-md-4">
                <div class="summary-panel">
                    <h4>Summary by Payment Method</h4>
                    <table class="table table-bordered table-condensed summary-table" id="sum_payment_plain">
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach($report['summary_payment'] as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format((float) ($r['qty'] ?? 0), 2), '0'), '.') }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right">{{ $fmt(data_get($report, 'summary_totals.payment.amount', 0)) }}</th>
                                <th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.payment.qty', 0), 2), '0'), '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <hr>
        <div class="box box-primary">
            <div class="box-header">
                <h4 class="box-title">All cashier sales</h4>
                <div class="table-meta">
                    <span>{{ count($report['detail_rows'] ?? []) }} rows</span>
                    <span>{{ count($report['summary_user'] ?? []) }} cashiers</span>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped ajax_view" id="local_cashier_sales_detail_table" style="width:100%;">
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Cashier/User</th>
                            <th>Location</th>
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
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($report['detail_rows'] as $row)
                            <tr>
                                <td>
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
                                </td>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['invoice_no'] }}</td>
                                <td>{{ $row['cashier_name'] }}</td>
                                <td>{{ $row['location_name'] }}</td>
                                <td>{{ $row['sku'] }}</td>
                                <td>{{ $row['product_name'] }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                                <td class="text-right">{{ $fmt($row['unit_price']) }}</td>
                                <td class="text-right">{{ $fmt($row['line_total']) }}</td>
                                <td class="text-right">{{ $fmt($row['discount']) }}</td>
                                <td class="text-right">{{ $fmt($row['paid']) }}</td>
                                @foreach($report['payment_columns'] as $method)
                                    <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                                @endforeach
                                <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
        if ($.fn.DataTable && $('#local_cashier_sales_detail_table').length) {
            $('#local_cashier_sales_detail_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
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
                    { extend: 'csv', text: 'Export CSV', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'excel', text: 'Export Excel', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'print', text: 'Print', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'colvis', text: 'Column visibility', className: 'btn btn-sm btn-outline-primary' },
                    { extend: 'pdf', text: 'Export PDF', className: 'btn btn-sm btn-outline-primary' }
                ]
            });
        }
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
#local_cashier_report_app .summary-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    grid-gap: 12px;
    margin-bottom: 14px;
}
#local_cashier_report_app .summary-kpi-card {
    background: linear-gradient(135deg, #f8fbff 0%, #edf4ff 100%);
    border: 1px solid #dbe7f9;
    border-radius: 12px;
    padding: 12px 14px;
}
#local_cashier_report_app .summary-kpi-card .kpi-label {
    font-size: 12px;
    color: #5b6472;
    text-transform: uppercase;
    letter-spacing: .4px;
}
#local_cashier_report_app .summary-kpi-card .kpi-value {
    margin-top: 4px;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}
#local_cashier_report_app .summary-panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
#local_cashier_report_app .summary-panel h4 {
    margin: 4px 0 10px;
    font-size: 17px;
}
#local_cashier_report_app .summary-panel .summary-filter {
    margin-bottom: 10px;
}
#local_cashier_report_app .summary-table thead th {
    background: #f8fafc;
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
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dataTables_filter {
    margin-bottom: 8px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dataTables_filter input {
    width: 220px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dataTables_length select {
    min-width: 78px;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons {
    text-align: center;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons .btn {
    border: 1px solid #c7cfdb;
    background: #fff;
    color: #5d6b82;
}
#local_cashier_report_app #local_cashier_sales_detail_table_wrapper .dt-buttons .btn:hover {
    background: #f5f8fc;
}
#local_cashier_report_app #local_cashier_sales_detail_table th,
#local_cashier_report_app #local_cashier_sales_detail_table td {
    white-space: nowrap;
    vertical-align: middle;
}
#local_cashier_report_app #local_cashier_sales_detail_table thead th {
    background: #f5f7fa;
    font-weight: 700;
}
#local_cashier_report_app #local_cashier_sales_detail_table td .btn {
    margin-right: 4px;
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
#local_cashier_report_app .dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 8px !important;
    margin: 0 2px;
}
</style>
@endsection
