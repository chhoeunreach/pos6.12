<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('report.telecom_ssd_report') }}</title>
    <style>
        @font-face { font-family: 'Telecom Khmer'; src: url('{{ asset('fonts/KhmerOSbattambang.ttf') }}') format('truetype'); font-display: block; }
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: 'Times New Roman', 'Telecom Khmer', serif; color: #000; font-size: 10px; margin: 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { border: 1px solid #000; padding: 7px 3px; text-align: center; vertical-align: middle; font-size: 12px; line-height: 1.25; }
        .khmer-heading th { font-family: 'Telecom Khmer', serif; font-size: 10px; font-weight: bold; line-height: 1.8; }
        td { border: 1px dotted #000; padding: 5px 3px; overflow-wrap: anywhere; vertical-align: middle; min-height: 24px; }
        .number { text-align: right; }
        td:first-child, td:nth-child(3) { text-align: center; }
        thead { display: table-header-group; }
        tbody tr { height: 26px; break-inside: avoid; }
        .print-actions { display: flex; gap: 12px; align-items: center; margin: 16px 0; font-family: Arial, sans-serif; }
        @media print { .print-actions { display: none; } }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">{{ __('Print') }}</button></div>
    <table>
        <colgroup>
            @foreach($columnWidths as $width)
                <col style="width: {{ $width / array_sum($columnWidths) * 100 }}%;">
            @endforeach
        </colgroup>
        <thead>
            <tr class="khmer-heading">@foreach($khmerHeadings as $heading)<th lang="km">{{ $heading }}</th>@endforeach</tr>
            <tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($report->dataRow($row, $loop->iteration) as $column => $value)
                        <td @if(in_array($column, [7, 8, 9], true)) class="number" @endif>{{ in_array($column, [8, 9], true) ? number_format((float) $value, 2) : $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <script>window.addEventListener('load', function() {
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function() { window.print(); }); }
        else { window.print(); }
    });</script>
</body>
</html>
