@php
    $reportLang = \Modules\LocalCashierReport\Support\ReportLanguage::current();
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key, $reportLang);
    $paymentLabel = fn ($method) => \Modules\LocalCashierReport\Support\ReportLanguage::payment((string) $method, (string) ($report['payment_labels'][$method] ?? $method));
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
    $paymentColumns = $report['payment_columns'] ?? [];
    $branchDashboards = $report['branch_dashboards'] ?? [];

    $overallTotal = 0.0;
    $overallPaid = 0.0;
    $overallExpenses = 0.0;
    $overallSellReturn = 0.0;
    $overallActualIncome = 0.0;
    $overallDue = 0.0;
    $overallPayments = [];
    $overallExpensePayments = [];
    $overallActualIncomePayments = [];
    foreach ($paymentColumns as $method) {
        $overallPayments[$method] = 0.0;
        $overallExpensePayments[$method] = 0.0;
        $overallActualIncomePayments[$method] = 0.0;
    }
    foreach ($branchDashboards as $b) {
        $overallTotal += (float) ($b['total'] ?? 0);
        $overallPaid += (float) ($b['paid'] ?? 0);
        $overallExpenses += (float) ($b['expenses'] ?? 0);
        $overallSellReturn += (float) ($b['sell_return'] ?? 0);
        $overallActualIncome += (float) ($b['actual_income'] ?? 0);
        $overallDue += (float) ($b['due'] ?? 0);
        foreach ($paymentColumns as $method) {
            $overallPayments[$method] += (float) ($b['payments'][$method] ?? 0);
            $overallExpensePayments[$method] += (float) ($b['expense_payments'][$method] ?? 0);
            $overallActualIncomePayments[$method] += (float) ($b['actual_income_payments'][$method] ?? 0);
        }
    }
@endphp
<!doctype html>
<html lang="{{ $reportLang === 'km' ? 'km' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('branch_pdf_title') }}</title>
    <style>
        body {
            font-family: khmerfont, dejavusans, sans-serif;
            font-size: 10px;
            color: #111827;
            line-height: 1.45;
        }
        h1 {
            margin: 0 0 6px;
            font-size: 16px;
            color: #0f172a;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .meta-table td {
            padding: 5px 8px;
            font-size: 9.5px;
            border: 1px solid #e2e8f0;
        }
        .meta-label {
            font-weight: bold;
            color: #334155;
            width: 15%;
        }
        .hint-box {
            margin-bottom: 10px;
            padding: 5px 8px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            font-size: 9.5px;
        }
        .branch-block {
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
            padding: 8px;
        }
        .branch-title {
            font-size: 12.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .branch-sources {
            font-size: 9px;
            color: #475569;
            margin-bottom: 6px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .kpi-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            width: 25%;
            background: #f8fafc;
        }
        .kpi-table td.kpi-paid { background: #f0fdf4; }
        .kpi-table td.kpi-expense { background: #fff7ed; }
        .kpi-table td.kpi-net { background: #eff6ff; }
        .kpi-label {
            font-size: 8.5px;
            color: #475569;
            display: block;
        }
        .kpi-val {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.data-table th,
        table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 9.5px;
        }
        table.data-table thead th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
        }
        tr.group-sep td {
            background: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
        }
        tr.row-total th,
        tr.row-total td {
            background: #e2e8f0;
            font-weight: bold;
        }
        tr.row-summary th,
        tr.row-summary td {
            background: #f8fafc;
            font-weight: bold;
        }
        tr.row-actual-income th,
        tr.row-actual-income td {
            background: #dcfce7;
            font-weight: bold;
            color: #14532d;
        }
        .overall-block {
            margin-top: 16px;
            border: 2px solid #1d4ed8;
            padding: 9px;
            page-break-inside: avoid;
        }
        .overall-heading {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 6px;
            border-bottom: 1px solid #bfdbfe;
            padding-bottom: 4px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .name-main { color: #1d4ed8; font-weight: bold; }
        .due-negative { color: #b91c1c; font-weight: bold; }
        .kind-badge {
            font-size: 8.5px;
            color: #3730a3;
        }
    </style>
</head>
<body>
    <h1>{{ $t('branch_pdf_title') }}</h1>

    <table class="meta-table">
        <tr>
            <td class="meta-label">{{ $t('business') }}</td>
            <td>{{ $businessName }}</td>
            <td class="meta-label">{{ $t('date_range') }}</td>
            <td>{{ \Carbon\Carbon::parse($filters['start_date'])->format('Y-m-d') }} ~ {{ \Carbon\Carbon::parse($filters['end_date'])->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="meta-label">{{ $t('locations') }}</td>
            <td>{{ count($branchDashboards) }} {{ $t('branch') }} ({{ count($selectedLocations) }} {{ $t('locations') }})</td>
            <td class="meta-label">{{ $t('generated') }}</td>
            <td>{{ now()->format('Y-m-d H:i:s') }}</td>
        </tr>
    </table>

    <div class="hint-box">{{ $t('branch_no_combine_hint') }}</div>

    @forelse($branchDashboards as $branch)
        @php $lastBranchGroup = null; @endphp
        <div class="branch-block">
            <div class="branch-title">{{ $branch['branch_name'] }}</div>
            <div class="branch-sources">
                {{ implode(' | ', $branch['location_labels'] ?? []) }}
            </div>

            <table class="kpi-table">
                <tr>
                    <td>
                        <span class="kpi-label">{{ $t('total_sale') }}</span><br>
                        <span class="kpi-val">{{ $fmtStrict($branch['total'] ?? 0) }}</span>
                    </td>
                    <td class="kpi-paid">
                        <span class="kpi-label">{{ $t('total') }} ({{ $t('paid') }})</span><br>
                        <span class="kpi-val">{{ $fmtStrict($branch['paid'] ?? 0) }}</span>
                    </td>
                    <td class="kpi-expense">
                        <span class="kpi-label">{{ $t('expenses') }}</span><br>
                        <span class="kpi-val">{{ $fmtStrict($branch['expenses'] ?? 0) }}</span>
                    </td>
                    <td class="kpi-net">
                        <span class="kpi-label">{{ $t('actual_income') }}</span><br>
                        <span class="kpi-val @if(($branch['actual_income'] ?? 0) < 0) due-negative @endif">{{ $fmtStrict($branch['actual_income'] ?? 0) }}</span>
                    </td>
                </tr>
            </table>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ $t('business_location_qty') }}</th>
                        <th class="text-right">{{ $t('total_price') }}</th>
                        @foreach($paymentColumns as $method)
                            <th class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th class="text-right">{{ $t('total') }}</th>
                        <th class="text-right">{{ $t('due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($branch['rows'] ?? []) as $bRow)
                        @php
                            $bGroup = (string) ($bRow['name'] ?? 'លក់');
                            $bRowKind = (string) ($bRow['row_kind'] ?? 'main');
                            $bQtyText = rtrim(rtrim(number_format((float) ($bRow['qty_total'] ?? 0), 2), '0'), '.');
                        @endphp
                        @if($lastBranchGroup !== $bGroup)
                            <tr class="group-sep">
                                <td colspan="{{ 4 + count($paymentColumns) }}">{{ \Modules\LocalCashierReport\Support\ReportLanguage::label($bGroup) }}</td>
                            </tr>
                            @php $lastBranchGroup = $bGroup; @endphp
                        @endif
                        <tr>
                            <td class="name-main">
                                {{ $bRow['label'] }} ({{ $bQtyText }})
                                @if($bRowKind !== 'main')
                                    <span class="kind-badge">[{{ ucfirst($bRowKind) }}]</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $fmt($bRow['total'] ?? null) }}</td>
                            @foreach($paymentColumns as $method)
                                <td class="text-right">{{ $fmt($bRow['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td class="text-right">{{ $fmt($bRow['paid'] ?? null) }}</td>
                            <td class="text-right @if(! in_array($bGroup, ['រំលស់', 'អ៊ីអន'], true) && ($bRow['due'] ?? 0) != 0) due-negative @endif">
                                {{ in_array($bGroup, ['រំលស់', 'អ៊ីអន'], true) ? '$ -' : $fmt($bRow['due'] ?? null) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + count($paymentColumns) }}" class="text-center">{{ $t('no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="row-total">
                        <th class="text-right">{{ $t('grand_total') }}</th>
                        <th class="text-right">{{ $fmt($branch['total'] ?? null) }}</th>
                        @foreach($paymentColumns as $method)
                            <th class="text-right">{{ $fmt($branch['payments'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($branch['paid'] ?? null) }}</th>
                        <th class="text-right @if(($branch['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($branch['due'] ?? null) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="text-right">{{ $t('expenses') }}</th>
                        <th class="text-right">$ -</th>
                        @foreach($paymentColumns as $method)
                            <th class="text-right">{{ $fmt($branch['expense_payments'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($branch['expenses'] ?? null) }}</th>
                        <th class="text-right">$ -</th>
                    </tr>
                    <tr class="row-actual-income">
                        <th class="text-right">{{ $t('actual_income') }}</th>
                        <th class="text-right">$ -</th>
                        @foreach($paymentColumns as $method)
                            <th class="text-right">{{ $fmt($branch['actual_income_payments'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($branch['actual_income'] ?? null) }}</th>
                        <th class="text-right @if(($branch['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($branch['due'] ?? null) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <div class="branch-block text-center">{{ $t('no_data') }}</div>
    @endforelse

    <div class="overall-block">
        <div class="overall-heading">{{ $t('overall_combined_summary') }}</div>

        <table class="kpi-table">
            <tr>
                <td>
                    <span class="kpi-label">{{ $t('total_sale') }}</span><br>
                    <span class="kpi-val">{{ $fmtStrict($overallTotal) }}</span>
                </td>
                <td class="kpi-paid">
                    <span class="kpi-label">{{ $t('total') }} ({{ $t('paid') }})</span><br>
                    <span class="kpi-val">{{ $fmtStrict($overallPaid) }}</span>
                </td>
                <td class="kpi-expense">
                    <span class="kpi-label">{{ $t('expenses') }}</span><br>
                    <span class="kpi-val">{{ $fmtStrict($overallExpenses) }}</span>
                </td>
                <td class="kpi-net">
                    <span class="kpi-label">{{ $t('actual_total_income') }}</span><br>
                    <span class="kpi-val @if($overallActualIncome < 0) due-negative @endif">{{ $fmtStrict($overallActualIncome) }}</span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ $t('branch') }}</th>
                    <th class="text-right">{{ $t('total_price') }}</th>
                    @foreach($paymentColumns as $method)
                        <th class="text-right">{{ $paymentLabel($method) }}</th>
                    @endforeach
                    <th class="text-right">{{ $t('total') }} ({{ $t('paid') }})</th>
                    <th class="text-right">{{ $t('expenses') }}</th>
                    <th class="text-right">{{ $t('actual_income') }}</th>
                    <th class="text-right">{{ $t('due') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($branchDashboards as $branch)
                    <tr>
                        <td class="name-main">{{ $branch['branch_name'] }}</td>
                        <td class="text-right">{{ $fmt($branch['total'] ?? null) }}</td>
                        @foreach($paymentColumns as $method)
                            <td class="text-right">{{ $fmt($branch['payments'][$method] ?? null) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($branch['paid'] ?? null) }}</td>
                        <td class="text-right">{{ $fmt($branch['expenses'] ?? null) }}</td>
                        <td class="text-right @if(($branch['actual_income'] ?? 0) < 0) due-negative @endif">{{ $fmtStrict($branch['actual_income'] ?? 0) }}</td>
                        <td class="text-right @if(($branch['due'] ?? 0) != 0) due-negative @endif">{{ $fmt($branch['due'] ?? null) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 6 + count($paymentColumns) }}" class="text-center">{{ $t('no_data') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <th class="text-right">{{ $t('grand_total') }}</th>
                    <th class="text-right">{{ $fmt($overallTotal) }}</th>
                    @foreach($paymentColumns as $method)
                        <th class="text-right">{{ $fmt($overallPayments[$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">{{ $fmt($overallPaid) }}</th>
                    <th class="text-right">{{ $fmt($overallExpenses) }}</th>
                    <th class="text-right">{{ $fmtStrict($overallActualIncome) }}</th>
                    <th class="text-right @if($overallDue != 0) due-negative @endif">{{ $fmt($overallDue) }}</th>
                </tr>
                <tr class="row-summary">
                    <th class="text-right">{{ $t('expenses') }}</th>
                    <th class="text-right">$ -</th>
                    @foreach($paymentColumns as $method)
                        <th class="text-right">{{ $fmt($overallExpensePayments[$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">$ -</th>
                    <th class="text-right">{{ $fmt($overallExpenses) }}</th>
                    <th class="text-right">$ -</th>
                    <th class="text-right">$ -</th>
                </tr>
                <tr class="row-actual-income">
                    <th class="text-right">{{ $t('actual_total_income') }}</th>
                    <th class="text-right">$ -</th>
                    @foreach($paymentColumns as $method)
                        <th class="text-right">{{ $fmt($overallActualIncomePayments[$method] ?? null) }}</th>
                    @endforeach
                    <th class="text-right">{{ $fmt($overallPaid) }}</th>
                    <th class="text-right">{{ $fmt($overallExpenses) }}</th>
                    <th class="text-right @if($overallActualIncome < 0) due-negative @endif">{{ $fmtStrict($overallActualIncome) }}</th>
                    <th class="text-right @if($overallDue != 0) due-negative @endif">{{ $fmt($overallDue) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
