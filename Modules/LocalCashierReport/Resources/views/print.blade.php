<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Local Cashier Report</title>
    <style>
        body { font-family: {!! $khmerFontFamily !!}; font-size: 14px; color: #111; }
        h2, h3, h4 { margin: 0 0 8px; }
        .meta { margin-bottom: 6px; }
        .meta b { display: inline-block; min-width: 150px; }
        .text-right { text-align: right; }
        .due-negative { color: #cc0000; font-weight: 700; }
        .name-main { color: #1b62d1; font-weight: 700; }
        .section { margin-top: 16px; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        .sheet-theme th, .sheet-theme td { border: 1px dashed #000; padding: 6px 8px; }
        .sheet-theme thead th { background: #d9edf7; font-weight: 700; }
        .sheet-theme tbody tr.row-sale { background: #fde2ea; }
        .sheet-theme tfoot tr.row-total, .sheet-theme tfoot tr.row-summary { background: #dff0d8; font-weight: 700; }
        .classic-theme th, .classic-theme td { border: 1px solid #d9d9d9; padding: 6px 8px; }
        .classic-theme thead th { background: #f5f7fa; font-weight: 700; }
        .classic-theme tbody tr { background: #fff; }
        .classic-theme tfoot tr { background: #f7f7f7; font-weight: 700; }
        .summary-grid { display: table; width: 100%; table-layout: fixed; border-spacing: 8px 0; }
        .summary-col { display: table-cell; vertical-align: top; }
    </style>
</head>
@php
    $styleMode = $filters['style_mode'] ?? 'classic_plain';
    $isClassic = in_array($styleMode, ['classic', 'classic_plain'], true);
    $themeClass = $isClassic ? 'classic-theme' : 'sheet-theme';
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
@endphp
<body onload="window.print()" class="{{ $themeClass }}">
    <h2>Local Cashier Report</h2>
    <div class="meta"><b>Business:</b> {{ $businessName }}</div>
    <div class="meta"><b>Date Range:</b> {{ \Carbon\Carbon::parse($filters['start_date'])->format('Y-m-d') }} ~ {{ \Carbon\Carbon::parse($filters['end_date'])->format('Y-m-d') }}</div>
    <div class="meta"><b>Locations:</b> {{ !empty($selectedLocations) ? implode(', ', $selectedLocations) : 'All' }}</div>
    <div class="meta"><b>Style Option:</b> {{ ucfirst(str_replace('_', ' ', $styleMode)) }}</div>
    <div class="meta"><b>Generated:</b> {{ now()->format('Y-m-d H:i:s') }}</div>

    @if($styleMode === 'view_report')
        <div class="section">
            <h3>View Report</h3>
            <table>
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
                            <td class="text-right">{{ $fmtStrict($row['expenses'] ?? 0) }}</td>
                            <td class="text-right">{{ $fmtStrict($row['actual_income'] ?? 0) }}</td>
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
                        <th class="text-right">{{ $fmtStrict($report['grand_expenses'] ?? 0) }}</th>
                        <th class="text-right">{{ $fmtStrict($report['grand_actual_income'] ?? 0) }}</th>
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
    @elseif($styleMode === 'business_location_report')
        <div class="section">
            <h3>Business Location Report</h3>
            <table>
                <thead>
                    <tr>
                        <th>Business Location</th>
                        <th class="text-right">Grand Total</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                        @endforeach
                        <th class="text-right">Total Payment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($report['rows_by_location'] ?? []) as $row)
                        <tr class="row-sale">
                            <td class="name-main">{{ $row['location_name'] }}</td>
                            <td class="text-right">{{ $fmt($row['total'] ?? null) }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td class="text-right">{{ $fmt($row['paid'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="row-total">
                        <th class="text-right">Grand Total</th>
                        <th class="text-right">{{ $fmt($report['grand_total'] ?? null) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_paid'] ?? null) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="text-right">Expenses</th>
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="text-right">Actual Income</th>
                        <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? 0) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? 0) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="section">
            <h3>Old Dashboard</h3>
            <table style="margin-bottom:10px;">
                <thead>
                    <tr>
                        <th>Grand Total</th>
                        <th>Expenses</th>
                        <th>Actual Income</th>
                        <th>Due</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-summary">
                        <td class="text-right">{{ $fmt($report['grand_total'] ?? null) }}</td>
                        <td class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</td>
                        <td class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</td>
                        <td class="text-right @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</td>
                    </tr>
                </tbody>
            </table>

            <table>
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
                    @foreach($report['rows'] as $row)
                        <tr class="row-sale">
                            <td class="name-main">{{ $row['cashier_name'] }}</td>
                            <td>{{ $row['location_qty_text'] }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td class="text-right">{{ $fmt($row['total']) }}</td>
                            <td class="text-right @if($row['due'] != 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="row-total">
                        <th colspan="{{ 2 + count($report['payment_columns']) }}" class="text-right">Grand Total</th>
                        <th class="text-right">{{ $fmt($report['grand_total']) }}</th>
                        <th class="text-right @if($report['grand_due'] != 0) due-negative @endif">{{ $fmt($report['grand_due']) }}</th>
                    </tr>
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
                </tfoot>
            </table>
        </div>

        <div class="section">
            <h3>Summary</h3>
            <div class="summary-grid">
                <div class="summary-col">
                    <h4>Summary by User/Cashier</h4>
                    <table>
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach(($report['summary_user'] ?? []) as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th>Total</th><th class="text-right">{{ $fmt(data_get($report, 'summary_totals.user.amount', 0)) }}</th><th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.user.qty', 0), 2), '0'), '.') }}</th></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="summary-col">
                    <h4>Summary by Location</h4>
                    <table>
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach(($report['summary_location'] ?? []) as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th>Total</th><th class="text-right">{{ $fmt(data_get($report, 'summary_totals.location.amount', 0)) }}</th><th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.location.qty', 0), 2), '0'), '.') }}</th></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="summary-col">
                    <h4>Summary by Brand</h4>
                    <table>
                        <thead><tr><th>Name</th><th class="text-right">Amount</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            @foreach(($report['summary_brand'] ?? []) as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2), '0'), '.') }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th>Total</th><th class="text-right">{{ $fmt(data_get($report, 'summary_totals.brand.amount', 0)) }}</th><th class="text-right">{{ rtrim(rtrim(number_format((float) data_get($report, 'summary_totals.brand.qty', 0), 2), '0'), '.') }}</th></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="summary-col">
                    <h4>Summary by Payment Method</h4>
                    <table>
                        <thead><tr><th>Name</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach(($report['summary_payment'] ?? []) as $r)
                                <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fmt($r['amount']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th>Total</th><th class="text-right">{{ $fmt(data_get($report, 'summary_totals.payment.amount', 0)) }}</th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
</body>
</html>
