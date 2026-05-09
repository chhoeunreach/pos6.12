<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Local Cashier Report</title>
    <style>
        .sheet-theme table { border-collapse: collapse; width: 100%; font-size: 16px; }
        .sheet-theme th, .sheet-theme td { border: 1px dashed #000; padding: 6px 8px; }
        .sheet-theme thead th { background: #d9edf7; font-weight: 700; font-size: 17px; }
        .sheet-theme tbody tr { background: #fde2ea; }
        .sheet-theme tfoot tr { background: #dff0d8; font-weight: 700; }

        .classic-theme table { border-collapse: collapse; width: 100%; font-size: 16px; }
        .classic-theme th, .classic-theme td { border: 1px solid #d9d9d9; padding: 6px 8px; }
        .classic-theme thead th { background: #f5f7fa; font-weight: 700; font-size: 17px; }
        .classic-theme tbody tr { background: #fff; }
        .classic-theme tfoot tr { background: #f7f7f7; font-weight: 700; }

        body { font-family: {!! $khmerFontFamily !!}; font-size: 16px; color: #111; }
        .meta { margin-bottom: 10px; }
        .meta b { display: inline-block; min-width: 140px; }
        .text-right { text-align: right; }
        .due-negative { color: #cc0000; font-weight: 700; }
        .name-main { color: #1b62d1; font-weight: 700; }
    </style>
</head>
<body onload="window.print()" class="{{ ($filters['style_mode'] ?? 'sheet') === 'classic' ? 'classic-theme' : 'sheet-theme' }}">
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
    <h2>Local Cashier Report</h2>
    <div class="meta"><b>Business:</b> {{ $businessName }}</div>
    <div class="meta"><b>Date Range:</b> {{ \Carbon\Carbon::parse($filters['start_date'])->format('Y-m-d') }} ~ {{ \Carbon\Carbon::parse($filters['end_date'])->format('Y-m-d') }}</div>
    <div class="meta"><b>Locations:</b> {{ !empty($selectedLocations) ? implode(', ', $selectedLocations) : 'All' }}</div>
    <div class="meta"><b>Generated:</b> {{ now()->format('Y-m-d H:i:s') }}</div>

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
                <tr>
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
            <tr>
                <th colspan="{{ 2 + count($report['payment_columns']) }}" class="text-right">Grand Total</th>
                <th class="text-right">{{ $fmt($report['grand_total']) }}</th>
                <th class="text-right @if($report['grand_due'] != 0) due-negative @endif">{{ $fmt($report['grand_due']) }}</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
