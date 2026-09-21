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
    $paymentLabel = function ($method) use ($report, $reportLang) {
        $raw = (string) ($report['payment_labels'][$method] ?? $method);
        $key = strtolower(trim((string) $method));
        $rawLower = strtolower($raw);

        if ($key === 'custom_pay_1' || strpos($rawLower, 'wing') !== false || strpos($raw, 'វីង') !== false) {
            return $reportLang === 'km' ? 'វីង' : 'WING';
        }
        if ($key === 'custom_pay_2' || strpos($rawLower, 'aba') !== false || strpos($raw, 'អេប៊ីអេ') !== false) {
            return $reportLang === 'km' ? 'អេប៊ីអេ' : 'ABA';
        }
        if ($key === 'custom_pay_3' || strpos($rawLower, 'acleda') !== false || strpos($raw, 'អេស៊ីលីដា') !== false) {
            return $reportLang === 'km' ? 'អេស៊ីលីដា' : 'ACLEDA';
        }
        if ($key === 'custom_pay_4' || strpos($rawLower, 'true') !== false || strpos($raw, 'ទ្រូម៉ានី') !== false) {
            return $reportLang === 'km' ? 'ទ្រូម៉ានី' : 'TRUE MONEY';
        }
        if ($key === 'custom_pay_5' || strpos($rawLower, 'emoney') !== false || strpos($rawLower, 'e-money') !== false || strpos($raw, 'អ៊ីម៉ានី') !== false) {
            return $reportLang === 'km' ? 'អ៊ីម៉ានី' : 'E-MONEY';
        }
        if ($key === 'custom_pay_6' || strpos($raw, 'កាត់អីវ៉ាន់') !== false || strpos($rawLower, 'cut') !== false) {
            return $reportLang === 'km' ? 'កាត់អីវ៉ាន់' : 'CUT';
        }
        if ($key === 'custom_pay_7' || strpos($raw, 'បង់ប្រចាំខែ') !== false || strpos($rawLower, 'monthly') !== false) {
            return $reportLang === 'km' ? 'បង់ប្រចាំខែ' : 'MONTHLY';
        }
        if ($key === 'cash' || strpos($rawLower, 'cash') !== false || strpos($raw, 'សាច់ប្រាក់') !== false) {
            return $reportLang === 'km' ? 'សាច់ប្រាក់' : 'CASH';
        }
        if ($key === 'card' || strpos($rawLower, 'card') !== false || strpos($raw, 'កាត') !== false) {
            return $reportLang === 'km' ? 'កាត' : 'CARD';
        }
        if ($key === 'cheque' || strpos($rawLower, 'cheque') !== false || strpos($raw, 'សែក') !== false) {
            return $reportLang === 'km' ? 'សែក' : 'CHEQUE';
        }
        if ($key === 'bank_transfer' || strpos($rawLower, 'bank') !== false || strpos($raw, 'ផ្ទេរប្រាក់') !== false) {
            return $reportLang === 'km' ? 'ផ្ទេរប្រាក់' : 'BANK TRANSFER';
        }
        if ($key === 'other' || strpos($rawLower, 'other') !== false || strpos($raw, 'ផ្សេងៗ') !== false) {
            return $reportLang === 'km' ? 'ផ្សេងៗ' : 'OTHER';
        }

        if (preg_match('/^([^\(]+)\s*\((.+)\)$/u', $raw, $matches)) {
            return $reportLang === 'km' ? trim($matches[1]) : strtoupper(trim($matches[2]));
        }

        return $raw;
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
        $colList[] = ['id' => 'cashier_user', 'label' => $t('cashier_user'), 'default' => true];
        $colList[] = ['id' => 'business_location_qty', 'label' => $t('business_location_qty'), 'default' => true];
        foreach ($report['payment_columns'] as $m) {
            $colList[] = ['id' => 'pay_' . $m, 'label' => $paymentLabel($m), 'default' => true];
        }
        $colList[] = ['id' => 'total', 'label' => $t('total'), 'default' => true];
        $colList[] = ['id' => 'due', 'label' => $t('due'), 'default' => false];
    }
@endphp
<body onload="initAndPrint()" class="{{ $themeClass }}">
    <div class="print-toolbar no-print">
        <button type="button" class="btn-print-main" onclick="window.print()">🖨️ {{ $t('print') }}</button>
        <div class="colvis-dropdown">
            <button type="button" class="btn-colvis" id="colvis_toggle_btn" onclick="toggleColvisMenu(event)">
                👁️ {{ $reportLang === 'km' ? 'បង្ហាញ/លាក់ ជួរឈរ' : 'Column Visibility' }} ▾
            </button>
            <div class="colvis-menu" id="colvis_menu">
                <div class="colvis-header">
                    <strong>{{ $reportLang === 'km' ? 'ជួរឈរ' : 'Columns' }}</strong>
                    <div class="colvis-actions">
                        <button type="button" class="btn-xs" onclick="setAllColumns(true)">{{ $reportLang === 'km' ? 'ទាំងអស់' : 'All' }}</button>
                        <button type="button" class="btn-xs" onclick="setAllColumns(false)">{{ $reportLang === 'km' ? 'លាក់' : 'None' }}</button>
                        <button type="button" class="btn-xs" onclick="resetDefaultColumns()">{{ $reportLang === 'km' ? 'ដើម' : 'Reset' }}</button>
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
        <select aria-label="Language" onchange="window.location.href = this.value">
            <option value="{{ route('local-cashier-report.print', $languageQuery('en')) }}" @if($reportLang === 'en') selected @endif>English</option>
            <option value="{{ route('local-cashier-report.print', $languageQuery('km')) }}" @if($reportLang === 'km') selected @endif>ខ្មែរ</option>
        </select>
        <button type="button" class="btn-close-print" onclick="window.close()">✕ {{ $reportLang === 'km' ? 'បិទ' : 'Close' }}</button>
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
                        <th data-col="cashier_user">{{ $t('cashier_user') }}</th>
                        <th data-col="business_location_qty">{{ $t('business_location_qty') }}</th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $paymentLabel($method) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $t('total') }}</th>
                        <th data-col="due" class="text-right">{{ $t('due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['rows'] as $row)
                        <tr class="row-sale">
                            <td data-col="cashier_user" class="name-main">{{ $row['cashier_name'] }}</td>
                            <td data-col="business_location_qty">{{ $row['location_qty_text'] }}</td>
                            @foreach($report['payment_columns'] as $method)
                                <td data-col="pay_{{ $method }}" class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                            @endforeach
                            <td data-col="total" class="text-right">{{ $fmt($row['total']) }}</td>
                            <td data-col="due" class="text-right @if($row['due'] != 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tbody class="table-totals">
                    <tr class="row-total">
                        <th data-col="cashier_user" class="text-right">{{ $t('grand_total') }}</th>
                        <th data-col="business_location_qty"></th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['payment_with_expenses'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $fmt($report['grand_total']) }}</th>
                        <th data-col="due" class="text-right @if($report['grand_due'] != 0) due-negative @endif">{{ $fmt($report['grand_due']) }}</th>
                    </tr>
                    <tr class="row-summary">
                        <th data-col="cashier_user" class="text-right">{{ $t('expenses') }}</th>
                        <th data-col="business_location_qty"></th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['expense_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $fmt($report['grand_expenses'] ?? null) }}</th>
                        <th data-col="due" class="text-right">$ -</th>
                    </tr>
                    <tr class="row-summary">
                        <th data-col="cashier_user" class="text-right">{{ $t('actual_income') }}</th>
                        <th data-col="business_location_qty"></th>
                        @foreach($report['payment_columns'] as $method)
                            <th data-col="pay_{{ $method }}" class="text-right">{{ $fmt($report['actual_income_payment_summary'][$method] ?? null) }}</th>
                        @endforeach
                        <th data-col="total" class="text-right">{{ $fmt($report['grand_actual_income'] ?? null) }}</th>
                        <th data-col="due" class="text-right @if(($report['grand_due'] ?? 0) != 0) due-negative @endif">{{ $fmt($report['grand_due'] ?? null) }}</th>
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
