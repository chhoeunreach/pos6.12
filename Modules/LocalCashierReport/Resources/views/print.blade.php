@php
    $reportLang = in_array(request('report_lang'), ['en', 'km'], true) ? request('report_lang') : 'en';
    $translations = [
        'en' => [
            'local_cashier_report' => 'Cashier Report',
            'business' => 'Business',
            'date_range' => 'Date Range',
            'locations' => 'Locations',
            'style_option' => 'Style Option',
            'generated' => 'Generated',
            'all' => 'All',
            'view_report' => 'View Report',
            'business_location_report' => 'Business Location Report',
            'old_dashboard' => 'Dashboard',
            'cashier_user' => 'Cashier/User',
            'business_location' => 'Business Location',
            'business_location_qty' => 'Business Location (Qty)',
            'expenses' => 'Expenses',
            'actual_income' => 'Actual Income',
            'actual_total_income' => 'Actual Total Income (Paid - Expenses - Sell Return)',
            'due' => 'Due',
            'total_paid' => 'Total Paid',
            'total_payment' => 'Total Payment',
            'grand_total' => 'Grand Total',
            'total' => 'Total',
            'summary' => 'Summary',
            'summary_by_user' => 'Summary by User/Cashier',
            'summary_by_location' => 'Summary by Location',
            'summary_by_brand' => 'Summary by Brand',
            'summary_by_payment' => 'Summary by Payment Method',
            'name' => 'Name',
            'amount' => 'Amount',
            'qty' => 'Qty',
            'total_price' => 'Total Price',
            'print' => 'Print',
            'english' => 'English',
            'khmer' => 'Khmer',
            'cash' => 'Cash',
            'card' => 'Card',
            'other' => 'Other',
            'cut' => 'Cut',
            'monthly' => 'Monthly',
        ],
        'km' => [
            'local_cashier_report' => 'របាយការណ៍បេឡាករ',
            'business' => 'អាជីវកម្ម',
            'date_range' => 'ចន្លោះកាលបរិច្ឆេទ',
            'locations' => 'ទីតាំង',
            'style_option' => 'ជម្រើសបង្ហាញ',
            'generated' => 'បានបង្កើត',
            'all' => 'ទាំងអស់',
            'view_report' => 'មើលរបាយការណ៍',
            'business_location_report' => 'របាយការណ៍តាមទីតាំងអាជីវកម្ម',
            'old_dashboard' => 'ផ្ទាំងសង្ខេប',
            'cashier_user' => 'បេឡាករ/អ្នកប្រើ',
            'business_location' => 'ទីតាំងអាជីវកម្ម',
            'business_location_qty' => 'ទីតាំងអាជីវកម្ម (ចំនួន)',
            'expenses' => 'ចំណាយ',
            'actual_income' => 'ចំណូលជាក់ស្តែង',
            'actual_total_income' => 'ចំណូលសរុបជាក់ស្តែង (ប្រាក់បានបង់ - ចំណាយ - ត្រឡប់លក់)',
            'due' => 'ជំពាក់',
            'total_paid' => 'បានបង់សរុប',
            'total_payment' => 'ការទូទាត់សរុប',
            'grand_total' => 'សរុបរួម',
            'total' => 'សរុប',
            'summary' => 'សេចក្តីសង្ខេប',
            'summary_by_user' => 'សង្ខេបតាមបេឡាករ/អ្នកប្រើ',
            'summary_by_location' => 'សង្ខេបតាមទីតាំង',
            'summary_by_brand' => 'សង្ខេបតាមម៉ាក',
            'summary_by_payment' => 'សង្ខេបតាមវិធីបង់ប្រាក់',
            'name' => 'ឈ្មោះ',
            'amount' => 'ចំនួនទឹកប្រាក់',
            'qty' => 'ចំនួន',
            'total_price' => 'តម្លៃសរុប',
            'print' => 'បោះពុម្ព',
            'english' => 'អង់គ្លេស',
            'khmer' => 'ខ្មែរ',
            'cash' => 'សាច់ប្រាក់',
            'card' => 'កាត',
            'other' => 'ផ្សេងៗ',
            'cut' => 'កាត់',
            'monthly' => 'បង់ប្រចាំខែ',
        ],
    ];
    $t = fn ($key) => $translations[$reportLang][$key] ?? $translations['en'][$key] ?? $key;
    $paymentLabel = function ($method) use ($report, $t) {
        $label = (string) ($report['payment_labels'][$method] ?? $method);
        $key = strtolower(str_replace([' ', '-'], '_', $label));

        return $t($key) !== $key ? $t($key) : $label;
    };
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
        tfoot { display: table-row-group; }
        .sheet-theme th, .sheet-theme td { border: 1px dashed #000; padding: 6px 8px; }
        .sheet-theme thead th { background: #d9edf7; font-weight: 700; }
        .sheet-theme tbody tr.row-sale { background: #fde2ea; }
        .sheet-theme tfoot tr.row-total, .sheet-theme tfoot tr.row-summary { background: #dff0d8; font-weight: 700; }
        .classic-theme th, .classic-theme td { border: 1px solid #d9d9d9; padding: 6px 8px; }
        .classic-theme thead th { background: #f5f7fa; font-weight: 700; }
        .classic-theme tbody tr { background: #fff; }
        .classic-theme tfoot tr { background: #f7f7f7; font-weight: 700; }
        .print-toolbar { margin-bottom: 14px; }
        .print-toolbar a, .print-toolbar button, .print-toolbar select { border: 1px solid #999; background: #fff; color: #111; display: inline-block; padding: 6px 10px; text-decoration: none; cursor: pointer; font-size: 13px; }
        .print-toolbar .active { background: #1b62d1; color: #fff; border-color: #1b62d1; }
        @media print {
            .no-print { display: none !important; }
            tfoot { display: table-row-group !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; break-inside: avoid; }
            tfoot tr { page-break-inside: avoid; break-inside: avoid; }
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
@endphp
<body onload="window.print()" class="{{ $themeClass }}">
    <div class="print-toolbar no-print">
        <button type="button" onclick="window.print()">{{ $t('print') }}</button>
        <select aria-label="Language" onchange="window.location.href = this.value">
            <option value="{{ route('local-cashier-report.print', $languageQuery('en')) }}" @if($reportLang === 'en') selected @endif>English</option>
            <option value="{{ route('local-cashier-report.print', $languageQuery('km')) }}" @if($reportLang === 'km') selected @endif>ខ្មែរ</option>
        </select>
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
                        <th>{{ $t('cashier_user') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th class="text-right">{{ $t('expenses') }}</th>
                        <th class="text-right">{{ $t('actual_income') }}</th>
                        <th class="text-right">{{ $t('due') }}</th>
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
                        <th>{{ $t('total_paid') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmtStrict($report['grand_expenses'] ?? 0) }}</th>
                        <th class="text-right">{{ $fmtStrict($report['grand_actual_income'] ?? 0) }}</th>
                        <th class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th colspan="{{ count($report['payment_columns']) + 1 }}" class="text-right">{{ $t('expenses') }}</th>
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th class="text-right">$ -</th>
                        <th class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th colspan="{{ count($report['payment_columns']) + 1 }}" class="text-right">{{ $t('actual_total_income') }}</th>
                        <th class="text-right">$ -</th>
                        <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th colspan="{{ count($report['payment_columns']) + 1 }}" class="text-right">{{ $t('due') }}</th>
                        <th class="text-right">$ -</th>
                        <th class="text-right">$ -</th>
                        <th class="text-right @if(($report['grand_due'] ?? 0) < 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @elseif($styleMode === 'business_location_report')
        <div class="section">
            <h3>{{ $t('business_location_report') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>{{ $t('business_location') }}</th>
                        <th class="text-right">{{ $t('grand_total') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th class="text-right">{{ $t('total_payment') }}</th>
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
                        <th class="text-right">{{ $t('grand_total') }}</th>
                        <th class="text-right">{{ $fmt($report['grand_total'] ?? null) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_paid'] ?? null) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="text-right">{{ $t('expenses') }}</th>
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? 0) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th class="text-right">{{ $t('actual_income') }}</th>
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
            <h3>{{ $t('old_dashboard') }}</h3>
            <table style="margin-bottom:10px;">
                <thead>
                    <tr>
                        <th>{{ $t('grand_total') }}</th>
                        <th>{{ $t('expenses') }}</th>
                        <th>{{ $t('actual_income') }}</th>
                        <th>{{ $t('due') }}</th>
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
                        <th>{{ $t('cashier_user') }}</th>
                        <th>{{ $t('business_location_qty') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th class="text-right">{{ $t('total') }}</th>
                        <th class="text-right">{{ $t('due') }}</th>
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
                        <th colspan="{{ 2 + count($report['payment_columns']) }}" class="text-right">{{ $t('grand_total') }}</th>
                        <th class="text-right">{{ $fmt($report['grand_total']) }}</th>
                        <th class="text-right @if($report['grand_due'] != 0) due-negative @endif">{{ $fmt($report['grand_due']) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th colspan="2" class="text-right">{{ $t('expenses') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th colspan="2" class="text-right">{{ $t('actual_income') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th class="text-right @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

    @endif
    <script>
        (function () {
            var returnUrl = @json($returnUrl);
            var returning = false;

            function returnToSystem() {
                if (returning) {
                    return;
                }

                returning = true;
                if (window.opener && !window.opener.closed) {
                    window.opener.focus();
                    window.close();
                }

                window.setTimeout(function () {
                    window.location.replace(returnUrl);
                }, 150);
            }

            window.addEventListener('afterprint', returnToSystem);
        })();
    </script>
</body>
</html>
