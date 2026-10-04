@php
    $reportLang = \Modules\LocalCashierReport\Support\ReportLanguage::current();
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key, $reportLang);
    $paymentLabel = fn ($method) => \Modules\LocalCashierReport\Support\ReportLanguage::payment((string) $method, (string) ($report['payment_labels'][$method] ?? $method));
    $languageQuery = function ($language) {
        return array_merge(request()->query(), ['report_lang' => $language]);
    };
    $returnUrl = route('local-cashier-report.index') . '?' . http_build_query(request()->query());
@endphp
<!doctype html>
<html lang="{{ $reportLang === 'km' ? 'km' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('local_cashier_report') }}</title>
    <style>
        @font-face {
            font-family: 'KhmerFont';
            src: url('{{ asset("fonts/khmer/NotoSansKhmer-Regular.ttf") }}') format('truetype'),
                 url('{{ asset("fonts/khmer/KhmerOSbattambang.ttf") }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
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
        .sheet-theme tfoot tr.row-total, .sheet-theme tfoot tr.row-summary,
        .sheet-theme tbody.table-totals tr.row-total, .sheet-theme tbody.table-totals tr.row-summary { background: #dff0d8; font-weight: 700; }
        .classic-theme th, .classic-theme td { border: 1px solid #d9d9d9; padding: 6px 8px; }
        .classic-theme thead th { background: #f5f7fa; font-weight: 700; }
        .classic-theme tbody tr { background: #fff; }
        .classic-theme tfoot tr, .classic-theme tbody.table-totals tr { background: #f7f7f7; font-weight: 700; }
        .print-toolbar { margin-bottom: 14px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .print-toolbar a, .print-toolbar button, .print-toolbar select { border: 1px solid #999; background: #fff; color: #111; display: inline-block; padding: 6px 12px; text-decoration: none; cursor: pointer; font-size: 13px; border-radius: 4px; line-height: 1.4; }
        .print-toolbar .active { background: #1b62d1; color: #fff; border-color: #1b62d1; }
        .btn-print-main { background: #1b62d1 !important; color: #fff !important; border-color: #1b62d1 !important; font-weight: 700; }
        .btn-print-main:hover { background: #144ba3 !important; }
        .btn-colvis { background: #f8f9fa !important; font-weight: 600; }
        .btn-colvis:hover { background: #e9ecef !important; }
        .btn-close-print { background: #f8f9fa !important; color: #555 !important; }
        .btn-close-print:hover { background: #e2e6ea !important; color: #111 !important; }
        .colvis-dropdown { position: relative; display: inline-block; }
        .colvis-menu { display: none; position: absolute; top: 100%; left: 0; margin-top: 4px; background: #ffffff; border: 1px solid #c0c0c0; box-shadow: 0 4px 14px rgba(0,0,0,0.2); border-radius: 4px; padding: 10px; z-index: 9999; min-width: 270px; max-height: 420px; overflow-y: auto; text-align: left; }
        .colvis-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 6px; margin-bottom: 8px; }
        .colvis-header strong { font-size: 13px; color: #333; }
        .colvis-actions { display: flex; gap: 4px; }
        .colvis-actions .btn-xs { font-size: 11px; padding: 2px 6px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 3px; cursor: pointer; }
        .colvis-actions .btn-xs:hover { background: #e2e2e2; }
        .colvis-item { display: flex; align-items: center; padding: 5px 6px; cursor: pointer; font-size: 13px; user-select: none; border-radius: 3px; }
        .colvis-item:hover { background: #f0f5ff; }
        .colvis-item input[type="checkbox"] { margin-right: 8px; cursor: pointer; transform: scale(1.1); }
        @media print {
            .no-print { display: none !important; }
            thead { display: table-header-group; }
            tbody.table-totals tr, tfoot tr { page-break-inside: avoid; break-inside: avoid; }
            tr { page-break-inside: avoid; break-inside: avoid; }
        }
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

    $colList = [];
    if ($styleMode === 'view_report') {
        $colList[] = ['id' => 'cashier_user', 'label' => $t('cashier_user'), 'default' => true];
        foreach ($report['payment_columns'] as $m) {
            $colList[] = ['id' => 'pay_' . $m, 'label' => $paymentLabel($m), 'default' => true];
        }
        $colList[] = ['id' => 'expenses', 'label' => $t('expenses'), 'default' => true];
        $colList[] = ['id' => 'actual_income', 'label' => $t('actual_income'), 'default' => true];
        $colList[] = ['id' => 'due', 'label' => $t('due'), 'default' => false];
    } elseif ($styleMode === 'business_location_report') {
        $colList[] = ['id' => 'business_location', 'label' => $t('business_location'), 'default' => true];
        $colList[] = ['id' => 'grand_total', 'label' => $t('grand_total'), 'default' => true];
        foreach ($report['payment_columns'] as $m) {
            $colList[] = ['id' => 'pay_' . $m, 'label' => $paymentLabel($m), 'default' => true];
        }
        $colList[] = ['id' => 'total_payment', 'label' => $t('total_payment'), 'default' => true];
    } else {
        $colList[] = ['id' => 'business_location_qty', 'label' => $t('business_location_qty'), 'default' => true];
        $colList[] = ['id' => 'total_price', 'label' => $t('total_price'), 'default' => true];
        foreach ($report['payment_columns'] as $m) {
            $colList[] = ['id' => 'pay_' . $m, 'label' => $paymentLabel($m), 'default' => true];
        }
        $colList[] = ['id' => 'total', 'label' => $t('total'), 'default' => true];
        $colList[] = ['id' => 'due', 'label' => $t('due'), 'default' => true];
    }
    $dashboardRows = collect($report['rows_by_location'] ?? [])->flatMap(function ($locationRow) {
        return collect($locationRow['customer_groups'] ?? [])->values()->map(function ($row) use ($locationRow) {
            $row['location_name'] = $locationRow['location_name'] ?? 'N/A';
            return $row;
        });
    })->merge(collect($report['module_dashboard_rows'] ?? [])->map(function ($row) {
        $row['location_name'] = $row['label'];
        $row['name'] = 'លក់';
        $row['sort'] = 1;
        return $row;
    }))->sortBy(fn ($row) => sprintf('%02d-%s', (int) ($row['sort'] ?? 99), $row['location_name']))->values();
    $dashboardDue = $dashboardRows->reject(fn ($row) => in_array((int) ($row['sort'] ?? 0), [2, 3], true))
        ->sum(fn ($row) => (float) ($row['due'] ?? 0));
@endphp
<body onload="initAndPrint()" class="{{ $themeClass }}">
    <div class="print-toolbar no-print">
        <button type="button" class="btn-print-main" onclick="window.print()">🖨️ {{ $t('print') }}</button>
        <div class="colvis-dropdown">
            <button type="button" class="btn-colvis" id="colvis_toggle_btn" onclick="toggleColvisMenu(event)">
                👁️ {{ $t('column_visibility') }} ▾
            </button>
            <div class="colvis-menu" id="colvis_menu">
                <div class="colvis-header">
                    <strong>{{ $t('columns') }}</strong>
                    <div class="colvis-actions">
                        <button type="button" class="btn-xs" onclick="setAllColumns(true)">{{ $t('all') }}</button>
                        <button type="button" class="btn-xs" onclick="setAllColumns(false)">{{ $t('none') }}</button>
                        <button type="button" class="btn-xs" onclick="resetDefaultColumns()">{{ $t('reset') }}</button>
                    </div>
                </div>
                <div class="colvis-list">
                    @foreach($colList as $c)
                        <label class="colvis-item">
                            <input type="checkbox" id="cb_col_{{ $c['id'] }}" onchange="toggleColumn('{{ $c['id'] }}')">
                            <span>{{ $c['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <select aria-label="{{ $t('language') }}" onchange="window.location.href = this.value">
            <option value="{{ route('local-cashier-report.print', $languageQuery('en')) }}" @if($reportLang === 'en') selected @endif>{{ $t('english') }}</option>
            <option value="{{ route('local-cashier-report.print', $languageQuery('km')) }}" @if($reportLang === 'km') selected @endif>ខ្មែរ</option>
        </select>
        <button type="button" class="btn-close-print" onclick="window.close()">✕ {{ $t('close') }}</button>
    </div>
    <h2>{{ $t('local_cashier_report') }}</h2>
    <div class="meta"><b>{{ $t('business') }}:</b> {{ $businessName }}</div>
    <div class="meta"><b>{{ $t('date_range') }}:</b> {{ \Carbon\Carbon::parse($filters['start_date'])->format('Y-m-d') }} ~ {{ \Carbon\Carbon::parse($filters['end_date'])->format('Y-m-d') }}</div>
    <div class="meta"><b>{{ $t('style_option') }}:</b> {{ $t($styleMode === 'view_report' ? 'view_report' : ($styleMode === 'business_location_report' ? 'business_location_report' : 'old_dashboard')) }}</div>
    <div class="meta"><b>{{ $t('generated') }}:</b> {{ now()->format('Y-m-d H:i:s') }}</div>

    @if($styleMode === 'view_report')
        <div class="section">
            <h3>{{ $t('view_report') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th data-col="cashier_user">{{ $t('cashier_user') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th data-col="expenses" class="text-right">{{ $t('expenses') }}</th>
                        <th data-col="actual_income" class="text-right">{{ $t('actual_income') }}</th>
                        <th data-col="due" class="text-right">{{ $t('due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['rows'] as $row)
                        <tr class="row-sale">
                            <td data-col="cashier_user" class="name-main">{{ $row['cashier_name'] }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td data-col="pay_{{ $method }}" class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td data-col="expenses" class="text-right">{{ $fmtStrict($row['expenses'] ?? 0) }}</td>
                            <td data-col="actual_income" class="text-right">{{ $fmtStrict($row['actual_income'] ?? 0) }}</td>
                            <td data-col="due" class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tbody class="table-totals">
                    <tr class="row-total">
                        <th data-col="cashier_user">{{ $t('grand_total') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="expenses" class="text-right">{{ $fmtStrict($report['grand_expenses'] ?? 0) }}</th>
                        <th data-col="actual_income" class="text-right">{{ $fmtStrict($report['grand_actual_income'] ?? 0) }}</th>
                        <th data-col="due" class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="summary-leading-th text-right" colspan="{{ count($report['payment_columns']) + 1 }}">{{ $t('expenses') }}</th>
                        <th data-col="expenses" class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th data-col="actual_income" class="text-right">$ -</th>
                        <th data-col="due" class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="summary-leading-th text-right" colspan="{{ count($report['payment_columns']) + 1 }}">{{ $t('actual_total_income') }}</th>
                        <th data-col="expenses" class="text-right">$ -</th>
                        <th data-col="actual_income" class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th data-col="due" class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary summary-due-row" data-col="due">
                        <th class="summary-leading-th text-right" colspan="{{ count($report['payment_columns']) + 1 }}">{{ $t('due') }}</th>
                        <th data-col="expenses" class="text-right">$ -</th>
                        <th data-col="actual_income" class="text-right">$ -</th>
                        <th data-col="due" class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    @elseif($styleMode === 'business_location_report')
        <div class="section">
            <h3>{{ $t('business_location_report') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th data-col="business_location">{{ $t('business_location') }}</th>
                        <th data-col="grand_total" class="text-right">{{ $t('grand_total') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th data-col="total_payment" class="text-right">{{ $t('total_payment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($report['rows_by_location'] ?? []) as $row)
                        <tr class="row-sale">
                            <td data-col="business_location" class="name-main">{{ $row['location_name'] }}</td>
                            <td data-col="grand_total" class="text-right">{{ $fmt($row['total'] ?? null) }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td data-col="pay_{{ $method }}" class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td data-col="total_payment" class="text-right">{{ $fmt($row['paid'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tbody class="table-totals">
                    <tr class="row-total">
                        <th data-col="business_location" class="text-right">{{ $t('grand_total') }}</th>
                        <th data-col="grand_total" class="text-right">{{ $fmt($report['grand_total'] ?? null) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total_payment" class="text-right">{{ $fmt($report['grand_paid'] ?? null) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th data-col="business_location" class="text-right">{{ $t('expenses') }}</th>
                        <th data-col="grand_total" class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total_payment" class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th data-col="business_location" class="text-right">{{ $t('actual_income') }}</th>
                        <th data-col="grand_total" class="text-right">{{ $fmt($report['grand_actual_income'] ?? 0) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total_payment" class="text-right">{{ $fmt($report['grand_actual_income'] ?? 0) }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        <div class="section">
            <h3>{{ $t('old_dashboard') }}</h3>
            <table style="margin-bottom:10px;">
                <thead>
                    <tr>
                        <th data-col="grand_total">{{ $t('grand_total') }}</th>
                        <th data-col="expenses">{{ $t('expenses') }}</th>
                        <th data-col="actual_income">{{ $t('actual_income') }}</th>
                        <th data-col="due">{{ $t('due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-summary">
                        <td data-col="grand_total" class="text-right">{{ $fmt($report['grand_total'] ?? null) }}</td>
                        <td data-col="expenses" class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</td>
                        <td data-col="actual_income" class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</td>
                        <td data-col="due" class="text-right @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</td>
                    </tr>
                </tbody>
            </table>

            <table>
                <thead>
                    <tr>
                        <th data-col="business_location_qty">{{ $t('business_location_qty') }}</th>
                        <th data-col="total_price" class="text-right">{{ $t('total_price') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $t('total') }}</th>
                        <th data-col="due" class="text-right">{{ $t('due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastDashboardGroup = null; @endphp
                    @foreach($dashboardRows as $row)
                        @if($lastDashboardGroup !== ($row['name'] ?? 'លក់'))
                            <tr class="row-summary">
                                <th colspan="{{ count($report['payment_columns']) + 4 }}">{{ \Modules\LocalCashierReport\Support\ReportLanguage::label((string) ($row['name'] ?? 'លក់')) }}</th>
                            </tr>
                            @php $lastDashboardGroup = $row['name'] ?? 'លក់'; @endphp
                        @endif
                        <tr class="row-sale">
                            <td data-col="business_location_qty" class="name-main">{{ $row['location_name'] }} ({{ rtrim(rtrim(number_format((float) ($row['qty_total'] ?? 0), 2), '0'), '.') }})</td>
                            <td data-col="total_price" class="text-right">{{ $fmt($row['total'] ?? null) }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td data-col="pay_{{ $method }}" class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td data-col="total" class="text-right">{{ $fmt($row['paid'] ?? null) }}</td>
                            <td data-col="due" class="text-right @if(! in_array((int) ($row['sort'] ?? 0), [2, 3], true) && ($row['due'] ?? 0) != 0) due-negative @endif">{{ in_array((int) ($row['sort'] ?? 0), [2, 3], true) ? '$ -' : $fmt($row['due'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tbody class="table-totals">
                    <tr class="row-total">
                        <th data-col="business_location_qty" class="text-right">{{ $t('grand_total') }}</th>
                        <th data-col="total_price" class="text-right">{{ $fmt($report['grand_total']) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $fmt($report['grand_paid']) }}</th>
                        <th data-col="due" class="text-right @if($dashboardDue != 0) due-negative @endif">{{ $fmt($dashboardDue) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th data-col="business_location_qty" class="text-right">{{ $t('expenses') }}</th>
                        <th data-col="total_price" class="text-right">$ -</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th data-col="due" class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th data-col="business_location_qty" class="text-right">{{ $t('actual_income') }}</th>
                        <th data-col="total_price" class="text-right">$ -</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th data-col="due" class="text-right @if($dashboardDue != 0) due-negative @endif">{{ $fmt($dashboardDue) }}</th>
                    </tr>
                </tbody>
            </table>
        </div>

    @endif
    <script>
        var defaultCols = @json(collect($colList)->pluck('default', 'id'));
        var paymentColsList = @json($report['payment_columns'] ?? []);
        var storageKey = 'colvis_local_cashier_' + @json($styleMode);
        var colState = {};

        function getColState() {
            try {
                var saved = localStorage.getItem(storageKey);
                if (saved) {
                    var parsed = JSON.parse(saved);
                    return Object.assign({}, defaultCols, parsed);
                }
            } catch (e) {}
            return Object.assign({}, defaultCols);
        }

        function saveColState(state) {
            try {
                localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (e) {}
        }

        function applyColState(state) {
            var styleEl = document.getElementById('colvis_dynamic_style');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'colvis_dynamic_style';
                document.head.appendChild(styleEl);
            }
            var css = '';
            for (var colId in state) {
                if (state[colId] === false) {
                    css += '[data-col="' + colId + '"] { display: none !important; }\n';
                }
            }
            styleEl.textContent = css;

            // Recalculate leading colspan for summary rows in view_report
            var leadingThs = document.querySelectorAll('.summary-leading-th');
            if (leadingThs.length > 0) {
                var count = 0;
                if (state['cashier_user'] !== false) count++;
                paymentColsList.forEach(function (m) {
                    if (state['pay_' + m] !== false) count++;
                });
                leadingThs.forEach(function (th) {
                    th.colSpan = Math.max(1, count);
                });
            }

            // Sync checkboxes
            for (var id in state) {
                var cb = document.getElementById('cb_col_' + id);
                if (cb) {
                    cb.checked = (state[id] !== false);
                }
            }
        }

        function toggleColumn(colId) {
            var cb = document.getElementById('cb_col_' + colId);
            if (!cb) return;
            colState[colId] = cb.checked;
            saveColState(colState);
            applyColState(colState);
        }

        function setAllColumns(show) {
            for (var id in colState) {
                colState[id] = !!show;
            }
            saveColState(colState);
            applyColState(colState);
        }

        function resetDefaultColumns() {
            colState = Object.assign({}, defaultCols);
            saveColState(colState);
            applyColState(colState);
        }

        function toggleColvisMenu(e) {
            e.stopPropagation();
            var menu = document.getElementById('colvis_menu');
            if (menu) {
                menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
            }
        }

        document.addEventListener('click', function (e) {
            var menu = document.getElementById('colvis_menu');
            var btn = document.getElementById('colvis_toggle_btn');
            if (menu && !menu.contains(e.target) && e.target !== btn) {
                menu.style.display = 'none';
            }
        });

        // Initialize column visibility
        colState = getColState();
        applyColState(colState);

        function initAndPrint() {
            colState = getColState();
            applyColState(colState);
            window.setTimeout(function () {
                window.print();
            }, 100);
        }
    </script>
</body>
</html>
