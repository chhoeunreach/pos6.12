@php
    $printLanguage = \Modules\LocalCashierReport\Support\ReportLanguage::current();
    $printText = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key, $printLanguage);
    $portrait = ($printOrientation ?? 'landscape') === 'portrait';
    $paperWidth = $portrait ? 210 : 297;
    $paperHeight = $portrait ? 297 : 210;
    $tableFont = max(5.5, min($portrait ? 7 : 8, ($portrait ? 7 : 8) * 6 / max(6, count($report['branches']))));
    $portraitTableFont = max(5.5, min(7, 42 / max(6, count($report['branches']))));
    $landscapeTableFont = max(5.5, min(8, 48 / max(6, count($report['branches']))));
    $returnQuery = $filters + ['report_lang' => $printLanguage, 'location_filter_applied' => 1];
@endphp
<!doctype html>
<html lang="{{ $printLanguage }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $printText('financial_dashboard') }} · {{ $businessName }} · {{ $filters['start_date'] }} — {{ $filters['end_date'] }}</title>
    @yield('css')
    <style>
        @font-face{font-family:FinancePrintIcons;src:url('{{ asset('fonts/vendor/@fortawesome/fontawesome-free/webfa-solid-900.woff2') }}') format('woff2');font-style:normal;font-weight:900;font-display:swap}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#e8edf3;color:#20364e;font-family:FinanceKhmer,Arial,sans-serif}
        .finance-print-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;width:{{ $paperWidth }}mm;max-width:100%;margin:18px auto;padding:0 12px;font-size:12px}
        .finance-print-toolbar button,.finance-print-toolbar a{display:inline-block;padding:9px 13px;border:1px solid #cad6e4;border-radius:6px;background:#fff;color:#102d52;text-decoration:none;cursor:pointer;font:inherit}
        .finance-print-toolbar button{background:#102d52;color:#fff;border-color:#102d52}
        .finance-print-toolbar form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .finance-print-toolbar select{padding:8px;border:1px solid #cad6e4;border-radius:6px;background:#fff;color:#102d52;font:inherit}
        .finance-print-sheet{width:{{ $paperWidth }}mm;min-height:{{ $paperHeight }}mm;margin:0 auto 24px;padding:10mm 10mm 14mm;background:#fff;box-shadow:0 3px 20px #102d5220}
        .finance-print-context{padding:0 0 3mm;font-size:7pt;color:#687c91;border-bottom:1px solid #e1e8ef;margin-bottom:3mm}
        .finance-no-print,.finance-print-sheet .finance-actions,.finance-print-sheet .finance-topline,.finance-print-sheet .finance-active-filters{display:none!important}
        .finance-print-sheet .financial-dashboard{width:100%!important;background:#fff;min-height:0;padding:0}
        .finance-print-sheet .finance-data-link,.finance-print-sheet .finance-table thead a{color:inherit;text-decoration:none;pointer-events:none}
        .finance-print-sheet .finance-table thead a{color:#fff}
        .finance-print-sheet .finance-topline{display:block;margin-bottom:4mm;break-after:avoid}
        .finance-print-sheet .finance-eyebrow{font-size:7pt;letter-spacing:1px;margin:0 0 1mm}
        .finance-print-sheet .finance-topline h1{font-size:{{ $portrait ? 15 : 18 }}pt;line-height:1.5;margin:0}
        .finance-print-sheet .finance-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:2.5mm;margin-bottom:4mm;break-inside:avoid}
        .finance-print-sheet .finance-kpi{padding:3mm;border-radius:2mm;min-height:26mm;box-shadow:none;break-inside:avoid}
        .finance-print-sheet .finance-kpi-label{font-size:{{ $portrait ? 6.5 : 7.5 }}pt;line-height:1.6;gap:1mm}
        .finance-print-sheet .finance-kpi-label small{font-size:6pt;white-space:normal;display:block}
        .finance-print-sheet .finance-kpi-label i{font-size:8pt}
        .finance-print-sheet .finance-kpi-value{font-size:{{ $portrait ? 15 : 19 }}pt;margin-top:2mm;line-height:1.3;white-space:nowrap}
        .finance-print-sheet .finance-kpi-value small{font-size:8pt}
        .finance-print-sheet .finance-top-name{font-size:{{ $portrait ? 11 : 14 }}pt;white-space:normal;overflow-wrap:break-word;line-height:1.6}
        .finance-print-sheet .finance-kpi-foot{font-size:6pt;line-height:1.7;margin-top:1.5mm}
        .finance-print-sheet .finance-workspace{display:grid!important;grid-template-columns:minmax(0,1fr) {{ $portrait ? 42 : 60 }}mm!important;gap:4mm;align-items:start;margin-bottom:4mm}
        .finance-print-sheet .finance-panel{min-width:0;overflow:visible!important;border-radius:1.5mm;box-shadow:none;margin:0}
        .finance-print-sheet .finance-panel-heading{padding:2.5mm 3mm;break-after:avoid}
        .finance-print-sheet .finance-panel-heading h2{font-size:{{ $portrait ? 7 : 9 }}pt;line-height:1.7}
        .finance-print-sheet .finance-panel-heading span,.finance-print-sheet .finance-panel-heading h2 small{font-size:6pt}
        .finance-print-sheet .finance-table-wrap{width:100%;overflow:visible!important;max-height:none}
        .finance-print-sheet .finance-table{width:100%!important;table-layout:fixed;border-collapse:collapse;font-size:{{ $tableFont }}pt}
        .finance-print-sheet .finance-table th,.finance-print-sheet .finance-table td{min-width:0!important;max-width:none!important;padding:1mm 1.2mm;font-size:{{ $tableFont }}pt;line-height:1.5;border-bottom:0.2mm solid #e1e8ef;vertical-align:middle;font-variant-numeric:tabular-nums}
        .finance-print-sheet .finance-table td{white-space:nowrap!important;letter-spacing:-0.15px}
        .finance-print-sheet .finance-table th:first-child,.finance-print-sheet .finance-table td:first-child{width:27%;position:static;white-space:normal!important;overflow-wrap:break-word}
        .finance-print-sheet .finance-table thead{display:table-header-group}
        .finance-print-sheet .finance-table thead th{font-size:{{ $tableFont }}pt;padding-top:2mm;padding-bottom:2mm;white-space:normal!important;overflow-wrap:break-word}
        .finance-print-sheet .finance-table thead th strong{font-size:{{ $tableFont }}pt;margin-top:1mm}
        .finance-print-sheet .finance-table thead th small{font-size:5.5pt}
        .finance-print-sheet .finance-table tr{break-inside:avoid;page-break-inside:avoid}
        .finance-print-sheet .finance-section-row{break-after:avoid}
        .finance-print-sheet .finance-totals{break-inside:avoid;page-break-inside:avoid}
        .finance-print-sheet .finance-table .finance-result th,.finance-print-sheet .finance-table .finance-result td{padding-top:1.8mm;padding-bottom:1.8mm}
        .finance-print-sheet .finance-chart-panel{break-inside:auto}
        .finance-print-sheet .finance-chart{display:block;padding:3mm 3mm 1mm}
        .finance-print-sheet .finance-chart-total{margin:3mm;padding:2mm;gap:1mm;font-size:6.5pt;flex-wrap:wrap}
        .finance-print-sheet .finance-chart-total strong{font-size:{{ $portrait ? 9 : 11 }}pt;white-space:nowrap}
        .finance-print-sheet .finance-chart-item{margin-bottom:3mm;break-inside:avoid}
        .finance-print-sheet .finance-bar-label{font-size:{{ $portrait ? 6.5 : 7.5 }}pt;line-height:1.6;gap:1mm;margin-bottom:1mm}
        .finance-print-sheet .finance-bar-label span:first-child{max-width:76%;overflow-wrap:break-word}
        .finance-print-sheet .finance-bar-track{height:2mm}
        .finance-print-sheet .finance-chart-item-actions{font-size:6pt;line-height:1.6}
        .finance-print-sheet .finance-chart-note{padding:0 3mm 3mm;font-size:6pt;line-height:1.8}
        .finance-print-sheet .finance-note{font-size:7pt;line-height:1.9;margin:4mm 0 3mm;break-inside:avoid}
        .finance-print-sheet .finance-footer{border-top:0.2mm solid #d5dfea;padding-top:2mm;font-size:6.5pt;line-height:1.8;break-inside:avoid}
        .finance-print-sheet .finance-warning,.finance-print-sheet .finance-expense-scope{padding:2mm 3mm;font-size:7pt;line-height:1.8;margin-bottom:3mm;break-inside:avoid}
        .finance-print-sheet .finance-filter-chip{display:none!important}
        .finance-print-sheet .fa{font-family:FinancePrintIcons;font-style:normal;font-weight:900}
        .finance-print-sheet .fa-line-chart:before{content:"\f201"}.finance-print-sheet .fa-dollar:before{content:"\f155"}.finance-print-sheet .fa-cubes:before{content:"\f1b3"}.finance-print-sheet .fa-map-marker:before{content:"\f041"}.finance-print-sheet .fa-pie-chart:before{content:"\f200"}
        @media print{
            @page{size:auto;margin:10mm 10mm 14mm;
                @bottom-right{content:counter(page) " / " counter(pages);font-family:Arial,sans-serif;font-size:8pt;color:#687c91}
            }
            html,body{width:auto!important;height:auto!important;overflow:visible!important;background:#fff!important}
            .finance-print-toolbar{display:none!important}
            .finance-print-sheet{width:100%;min-height:0;margin:0;padding:0;box-shadow:none}
            .finance-print-sheet *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
        }
        @media print and (orientation:portrait){
            .finance-print-sheet .finance-workspace{grid-template-columns:minmax(0,1fr) 42mm!important}
            .finance-print-sheet .finance-kpi-value{font-size:15pt}.finance-print-sheet .finance-top-name{font-size:11pt}
            .finance-print-sheet .finance-kpi-label,.finance-print-sheet .finance-bar-label{font-size:6.5pt}
            .finance-print-sheet .finance-table,.finance-print-sheet .finance-table th,.finance-print-sheet .finance-table td,.finance-print-sheet .finance-table thead th,.finance-print-sheet .finance-table thead th strong{font-size:{{ $portraitTableFont }}pt}
        }
        @media print and (orientation:landscape){
            .finance-print-sheet .finance-workspace{grid-template-columns:minmax(0,1fr) 60mm!important}
            .finance-print-sheet .finance-kpi-value{font-size:19pt}.finance-print-sheet .finance-top-name{font-size:14pt}
            .finance-print-sheet .finance-kpi-label,.finance-print-sheet .finance-bar-label{font-size:7.5pt}
            .finance-print-sheet .finance-table,.finance-print-sheet .finance-table th,.finance-print-sheet .finance-table td,.finance-print-sheet .finance-table thead th,.finance-print-sheet .finance-table thead th strong{font-size:{{ $landscapeTableFont }}pt}
        }
        .finance-print-help{width:{{ $paperWidth }}mm;max-width:100%;margin:0 auto 12px;padding:0 12px;font-size:11px;color:#52657c;line-height:1.8}
        @media print{.finance-print-help{display:none!important}}
    </style>
</head>
<body>
    <div class="finance-print-toolbar">
        @unless(request()->boolean('print_embed'))<a href="{{ route('reports.financial-dashboard', $returnQuery) }}">← {{ $printText('financial_dashboard') }}</a>@endunless
        <form method="get" action="{{ route('reports.financial-dashboard') }}">
            @foreach($returnQuery as $key => $value)
                @if(is_array($value))@foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
                @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            <input type="hidden" name="print" value="1">
            @if(request()->boolean('print_embed'))<input type="hidden" name="print_embed" value="1">@endif
            <label for="finance-preview-orientation">{{ $printText('print_orientation') }}</label>
            <select id="finance-preview-orientation" name="orientation"><option value="landscape" @selected(($printOrientation ?? 'landscape') === 'landscape')>{{ $printText('landscape') }}</option><option value="portrait" @selected(($printOrientation ?? 'landscape') === 'portrait')>{{ $printText('portrait') }}</option></select>
            <button type="submit">{{ $printText('apply') }}</button>
        </form>
        <button type="button" id="finance-print-preview">{{ $printText('print') }} / PDF</button>
    </div>
    <p class="finance-print-help">{{ $printText('finance_print_browser_help') }}</p>
    <main class="finance-print-sheet">
        <div class="finance-print-context">{{ $filters['start_date'] }} — {{ $filters['end_date'] }} · {{ $printText('finance_source') }}: {{ $printText('finance_source_'.$filters['source']) }}</div>
        @yield('content')
    </main>
    <script>
        (function () {
            var printing = false;
            function printReport() {
                if (printing) return;
                printing = true;
                var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
                ready.then(function () { window.print(); }).finally(function () { printing = false; });
            }
            document.getElementById('finance-print-preview').addEventListener('click', printReport);
        })();
    </script>
</body>
</html>
