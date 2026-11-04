@extends(!empty($printMode) ? 'localcashierreport::financial_print_layout' : 'layouts.app')
@php
    $language = \Modules\LocalCashierReport\Support\ReportLanguage::current();
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key, $language);
    $fmt = fn ($value) => $value === null ? '—' : ($value < 0 ? '(' . $currencySymbol . number_format(abs($value), 2) . ')' : $currencySymbol . number_format($value, 2));
    $query = $filters + ['report_lang' => $language, 'location_filter_applied' => 1];
    $financeLink = fn ($overrides = []) => route('reports.financial-dashboard', array_merge($query, $overrides));
    $expenseDetailsLink = fn ($overrides = []) => route('reports.financial-expenses.index', array_merge($query, $overrides));
    $canViewExpenseDetails = auth()->user()->can('all_expense.access') || auth()->user()->can('view_own_expense');
    $clearExpenses = ['expense_filter_applied' => 0, 'expense_categories' => [], 'excluded_expense_categories' => []];
    $focusExpense = fn ($names, $scope = []) => $financeLink(array_merge($scope, ['expense_filter_applied' => 1, 'expense_categories' => (array) $names, 'excluded_expense_categories' => []]));
    $expenseChoices = array_values(array_unique([...$report['expense_options'], ...$filters['expense_categories'], ...$filters['excluded_expense_categories']]));
    $selectedExpenses = array_values(array_diff($filters['expense_filter_applied'] ? $filters['expense_categories'] : $report['expense_options'], $filters['excluded_expense_categories']));
    $total = $report['total'];
    $units = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    $performanceMetrics = ['main_sales', 'accessory_sales', 'service_sales'];
    $performanceMetrics = [...$performanceMetrics, 'net_sales', 'cost', 'gross_profit', 'gross_margin'];
    $chartRows = array_values(array_filter($report['expenses'], fn ($row) => $row['total'] > 0));
    if (count($chartRows) > 8) {
        $rest = array_slice($chartRows, 8);
        $chartRows = array_slice($chartRows, 0, 8);
        $chartRows[] = ['name' => '__other__', 'share' => array_sum(array_column($rest, 'share')), 'total' => array_sum(array_column($rest, 'total')), 'category_names' => array_column($rest, 'name')];
    }
@endphp
@section('title', $t('financial_dashboard'))

@section('css')
<style>
@font-face{font-family:FinanceKhmer;src:url('{{ asset('fonts/khmer/NotoSansKhmer-Regular.ttf') }}') format('truetype');font-display:swap}
.financial-dashboard{--navy:#102d52;--ink:#20364e;--muted:#687c91;--line:#e1e8ef;--paper:#fff;--blue:#2264b0;font-family:FinanceKhmer,Arial,sans-serif;color:var(--ink);padding:22px;background:#f2f5f9;min-height:85vh}
.finance-topline{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:22px;flex-wrap:wrap}.finance-eyebrow{font-size:11px;letter-spacing:1.6px;color:var(--muted);text-transform:uppercase;margin:0 0 7px}.finance-topline h1{font-size:27px;font-weight:700;color:var(--navy);margin:0;line-height:1.7}.finance-topline p{color:var(--muted);margin:5px 0 0}.finance-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.finance-btn{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid #d5dfea;border-radius:7px;color:var(--ink);padding:9px 13px;font-size:12px;min-height:38px}.finance-btn:hover{background:#edf3fa;color:var(--navy)}.finance-btn.primary{background:var(--navy);border-color:var(--navy);color:#fff}.finance-languages{display:flex;gap:3px;background:#e8edf4;padding:3px;border-radius:7px;margin-right:7px}.finance-languages a{padding:6px 10px;color:var(--muted);border-radius:5px;font-size:12px}.finance-languages a.active{background:#fff;color:var(--navy);box-shadow:0 1px 3px #142d4b18}
.finance-filter{background:#fff;border:1px solid var(--line);border-radius:10px;padding:17px 20px;margin-bottom:20px;display:flex;align-items:flex-end;flex-wrap:wrap;gap:14px}.finance-field{min-width:150px;flex:1}.finance-field.locations{min-width:230px;flex:2}.finance-field label{display:block;font-size:12px;color:var(--muted);font-weight:500;margin-bottom:7px}.finance-field .form-control{border-radius:6px;min-height:38px;border-color:#d5dfea}.finance-filter .select2-container{width:100%!important}.finance-filter .select2-selection--multiple{border-color:#d5dfea;min-height:38px;border-radius:6px}.finance-filter-footer{flex-basis:100%;font-size:11px;color:var(--muted)}
.finance-panel{border:1px solid var(--line);border-radius:10px;background:#fff;overflow:hidden;margin-bottom:20px}.finance-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--line)}.finance-panel-heading h2{font-size:15px;font-weight:700;line-height:1.7;margin:0;color:var(--navy)}.finance-panel-heading span{font-size:11px;color:var(--muted)}.finance-table-wrap{overflow:auto}.finance-table{width:100%;border-collapse:collapse;font-size:12px}.finance-table th,.finance-table td{padding:11px 16px;border-bottom:1px solid #e7edf3;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.finance-table th:first-child,.finance-table td:first-child{text-align:left;min-width:230px;position:sticky;left:0;background:inherit;z-index:1}.finance-table thead{background:var(--navy);color:#fff}.finance-table thead th{font-size:11px;font-weight:500;padding-top:13px;padding-bottom:13px}.finance-table tbody tr{background:#fff}.finance-table tbody tr:nth-child(even){background:#f6f8fb}.finance-table td:last-child{font-weight:700;background:#edf2f8}.finance-table .finance-subtotal{background:#e8eff8!important;color:#183e6f;font-weight:700}.finance-table .finance-result{background:#102d52!important;color:#fff;font-weight:700}.finance-table .finance-result td:last-child{background:#193f6b}.finance-table .negative{color:#b33c4a}.finance-table .finance-result .negative{color:#ffd0d5}.finance-table .finance-margin{font-size:11px;color:var(--muted)}.finance-table tbody tr:last-child td{border-bottom:0}
.finance-lower{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);gap:20px}.finance-expense-scroll{max-height:490px;overflow:auto}.finance-chart{padding:19px 20px 8px}.finance-bar-row{margin-bottom:19px}.finance-bar-label{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;font-size:11px;margin-bottom:7px}.finance-bar-label span:first-child{max-width:75%;line-height:1.8}.finance-bar-label strong{white-space:nowrap;font-variant-numeric:tabular-nums;font-weight:600}.finance-bar-track{height:8px;border-radius:3px;background:#e9eef4;overflow:hidden}.finance-bar-fill{height:100%;background:var(--navy);border-radius:3px}.finance-bar-row:nth-child(2n) .finance-bar-fill{background:#3876b5}.finance-bar-row:nth-child(3n) .finance-bar-fill{background:#c25261}.finance-chart-note{padding:0 20px 18px;color:var(--muted);font-size:10px;line-height:1.9}.finance-empty{text-align:center;padding:45px 20px;color:var(--muted);line-height:2}.finance-note{font-size:11px;line-height:2;color:var(--muted);margin:0 0 20px}.finance-warning{border-left:3px solid #c18a25;padding:11px 14px;background:#fff7e6;border-radius:5px;margin-bottom:15px;font-size:12px;line-height:1.9}.finance-footer{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;color:var(--muted);font-size:10px;margin-top:4px}.finance-screen-reader{position:absolute;left:-9999px}
@media(max-width:1050px){.finance-lower{grid-template-columns:1fr}.finance-topline h1{font-size:23px}.financial-dashboard{padding:15px}.finance-branch-cards{grid-template-columns:repeat(auto-fit,minmax(145px,1fr))}}
@media print{@page{size:auto;margin:10mm}.main-header,.main-sidebar,.main-footer,.control-sidebar,.finance-no-print{display:none!important}.content-wrapper{margin:0!important;background:#fff!important}.financial-dashboard{padding:0;min-height:0;background:#fff;color:#111}.finance-topline{margin-bottom:12px}.finance-topline h1{font-size:20px}.finance-branch-cards{display:flex;gap:6px}.finance-branch{flex:1;padding:8px}.finance-branch-count{font-size:18px}.finance-panel{break-inside:auto;page-break-inside:auto;border-radius:0;margin-bottom:12px}.finance-table-wrap,.finance-expense-scroll{overflow:visible;max-height:none}.finance-table{font-size:9px}.finance-table th,.finance-table td{padding:6px 7px;white-space:normal}.finance-table th:first-child,.finance-table td:first-child{position:static;min-width:150px}.finance-table thead{display:table-header-group}.finance-table tr{break-inside:avoid}.finance-lower{display:block}.finance-chart-panel{break-inside:avoid}.finance-chart{display:grid;grid-template-columns:1fr 1fr;gap:0 24px}.finance-chart-note{padding-bottom:10px}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}}

.finance-topline{margin-bottom:14px}.finance-topline h1{font-size:23px}.finance-eyebrow{margin-bottom:3px}.finance-filter{padding:13px 17px;gap:12px;margin-bottom:16px}.finance-global-label{font-size:11px;font-weight:700;align-self:center;color:var(--ink);padding-right:8px}.finance-field{min-width:135px}.finance-field label{font-size:10px;margin-bottom:5px}.finance-field.locations{min-width:200px}.finance-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.finance-kpi{padding:17px 18px;border-radius:8px;box-shadow:0 3px 7px #102d5210;min-height:135px;color:#fff;position:relative;overflow:hidden}.finance-kpi-profit{background:linear-gradient(120deg,#168f7e,#24a38f)}.finance-kpi-revenue{background:linear-gradient(120deg,#163d70,#24568a)}.finance-kpi-units{background:linear-gradient(120deg,#553180,#744ca0)}.finance-kpi-location{background:linear-gradient(120deg,#e9eef3,#dce4eb);color:var(--navy);border:1px solid #ced9e4}.finance-kpi-label{font-size:11px;line-height:1.8;display:flex;justify-content:space-between;align-items:flex-start;gap:8px}.finance-kpi-label small{font-size:9px;white-space:nowrap;opacity:.85}.finance-kpi-label i{opacity:.65;margin-top:4px}.finance-kpi-value{font-size:clamp(22px,2.2vw,32px);font-weight:700;font-variant-numeric:tabular-nums;margin-top:13px;line-height:1.5;white-space:nowrap}.finance-kpi-value small{font-size:14px;font-weight:500}.finance-kpi-foot{font-size:10px;opacity:.75;margin-top:8px}.finance-top-name{font-size:21px;white-space:normal;line-height:1.6}.finance-workspace{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:16px;align-items:start;margin-bottom:18px}.finance-workspace .finance-panel{margin-bottom:0;border-radius:8px}.finance-panel-heading{padding:13px 15px}.finance-panel-heading h2{font-size:13px}.finance-panel-heading h2 small{display:block;font-weight:400;font-size:10px;color:var(--muted);margin-top:3px}.finance-table{font-size:11px}.finance-table th,.finance-table td{padding:8px 10px}.finance-table th:first-child,.finance-table td:first-child{min-width:205px;max-width:260px;white-space:normal;line-height:1.8}.finance-table thead th{padding:12px 10px}.finance-table thead th a{color:#fff}.finance-table thead th strong{display:block;font-weight:600;margin-top:5px;font-size:12px}.finance-table thead th small{display:block;font-size:9px;opacity:.75;margin-top:6px}.finance-table .finance-section-row,.finance-table .finance-section-row>th{background:#183e6f!important;color:#fff!important}.finance-section-row th{font-weight:700;padding-top:12px;padding-bottom:12px}.finance-chart{padding:16px 15px 6px}.finance-bar-row{margin-bottom:17px}.finance-bar-label{font-size:10px}.finance-bar-track{height:9px;background:#e0e5eb}.finance-bar-fill{border-radius:0;background:#174b81}.finance-bar-row:first-child .finance-bar-fill,.finance-bar-row:nth-child(4n) .finance-bar-fill{background:#c62e46}.finance-chart-note{padding:0 15px 15px;font-size:9px}
@media(max-width:1100px){.finance-workspace{grid-template-columns:minmax(0,1fr) 230px}.finance-kpi{padding:13px;min-height:125px}.finance-kpi-label small{display:block}.finance-kpi-value{font-size:23px}.finance-top-name{font-size:18px}.finance-kpis{gap:10px}}@media(max-width:820px){.finance-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-workspace{grid-template-columns:1fr}.finance-chart{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}.finance-global-label{width:100%}}@media(max-width:440px){.finance-kpi-value{font-size:20px}.finance-kpi-label{font-size:10px}.finance-top-name{font-size:16px}.finance-chart{grid-template-columns:1fr}}
@media print{.finance-kpis{gap:8px;margin-bottom:12px}.finance-kpi{padding:10px;min-height:95px;box-shadow:none}.finance-kpi-value{font-size:22px}.finance-top-name{font-size:18px}.finance-workspace{display:grid;grid-template-columns:minmax(0,1fr) 210px;gap:10px}.finance-chart{display:block}.finance-table{font-size:8px}.finance-table th,.finance-table td{padding:5px 6px}.finance-table th:first-child,.finance-table td:first-child{min-width:150px;max-width:190px}.finance-chart-panel{break-inside:auto}.finance-kpi-foot{font-size:8px}}

.finance-filter-panel{border:1px solid var(--line);background:#fff;border-radius:9px;margin-bottom:16px}.finance-filter-panel summary{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px 17px;cursor:pointer;font-size:12px;font-weight:600;list-style:none}.finance-filter-panel summary::-webkit-details-marker{display:none}.finance-filter-summary{font-size:11px;font-weight:400;color:var(--muted)}.finance-filter-panel[open] summary{border-bottom:1px solid var(--line)}.finance-filter-panel[open] summary .fa-chevron-down{transform:rotate(180deg)}.finance-filter-panel .finance-filter{border:0;margin:0;border-radius:0 0 9px 9px}.finance-category-tools{display:flex;gap:12px;flex-wrap:wrap;font-size:10px;margin-top:7px}.finance-category-tools button{border:0;background:transparent;color:var(--blue);padding:0}.finance-active-filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}.finance-active-filters:empty{display:none}.finance-filter-chip{padding:5px 10px;background:#e3edf9;color:#234f81;border-radius:15px;font-size:11px}.finance-expense-scope{padding:9px 12px;border-left:3px solid #3876b5;background:#eaf1fa;font-size:11px;line-height:1.8}.finance-data-link{color:inherit}.finance-data-link:hover,.finance-data-link:focus{color:var(--blue);text-decoration:underline}.finance-result .finance-data-link:hover{color:#fff}.finance-remove-category{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;border-radius:50%;color:#a0394c;font-size:17px;vertical-align:middle}.finance-remove-category:hover{background:#fde8ec;color:#962238}.finance-bar-row.finance-data-link{display:block;color:var(--ink)}.finance-bar-row.finance-data-link:hover{text-decoration:none}.finance-bar-row:hover .finance-bar-track{outline:1px solid #84a6cb}.finance-filter-panel .finance-field:has(#finance-date-range){min-width:235px}
@media print{.finance-data-link{color:inherit!important;text-decoration:none!important}.finance-filter-chip{display:none}}
.finance-chart-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 15px;font-size:10px}.finance-chart-controls .finance-btn{font-size:10px;padding:6px 9px;min-height:30px}.finance-chart-total{margin:0 15px 12px;padding:12px;background:#edf2f8;border-radius:6px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:11px}.finance-chart-total strong{font-variant-numeric:tabular-nums;font-size:16px}.finance-chart-item{margin-bottom:16px}.finance-chart-item .finance-bar-row{margin-bottom:3px}.finance-chart-item-actions{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:10px}.finance-chart-item:nth-child(2n) .finance-bar-fill{background:#3876b5}.finance-chart-item:first-child .finance-bar-fill,.finance-chart-item:nth-child(4n) .finance-bar-fill{background:#c62e46}
#finance-expense-details-modal .modal-dialog{width:96%;max-width:1150px}#finance-expense-details-modal .modal-content{font-size:12px}#finance-details-table th,#finance-details-table td{font-size:11px;vertical-align:middle}#finance-details-table td{max-width:260px;word-break:break-word}.finance-edit-form{background:#f1f5fa;padding:15px;border:1px solid var(--line);border-radius:7px;margin-bottom:16px}.finance-edit-form h4{font-size:14px;margin:0 0 15px}.finance-edit-form label{font-size:11px}.finance-details-summary,.finance-details-pagination{display:flex;justify-content:space-between;align-items:center;gap:15px;margin:12px 0}.finance-chart-details{display:block;font-size:11px;margin:0 15px 10px}.finance-section-row .finance-btn{font-size:10px;padding:4px 9px;min-height:26px;margin-left:10px}
@media print{#main_app_header,.side-bar,.main-footer,#receipt_section,.print_section,.finance-topline,.finance-active-filters{display:none!important}.thetop,.thetop>main,#scrollable-container{display:block!important;height:auto!important;max-height:none!important;min-height:0!important;overflow:visible!important;width:100%!important}.financial-dashboard{width:100%!important}.finance-kpis{grid-template-columns:repeat(4,minmax(0,1fr))!important}}
</style>
@endsection

@section('content')
<section class="financial-dashboard" lang="{{ $language }}">
    <div class="finance-topline">
        <div><div class="finance-eyebrow">{{ $businessName }}</div><h1>{{ $t('financial_dashboard') }}</h1></div>
        <div class="finance-actions finance-no-print">
            <div class="finance-languages">
                <a class="{{ $language === 'km' ? 'active' : '' }}" href="{{ route('reports.financial-dashboard', array_merge($query, ['report_lang' => 'km'])) }}">ខ្មែរ</a>
                <a class="{{ $language === 'en' ? 'active' : '' }}" href="{{ route('reports.financial-dashboard', array_merge($query, ['report_lang' => 'en'])) }}">EN</a>
            </div>
            <a class="finance-btn" href="{{ route('reports.financial-dashboard', $query + ['download' => 'csv']) }}"><i class="fa fa-download" aria-hidden="true"></i> {{ $t('export_csv') }}</a>
            <label class="finance-screen-reader" for="finance-print-orientation">{{ $t('print_orientation') }}</label>
            <select class="finance-btn" id="finance-print-orientation" aria-label="{{ $t('print_orientation') }}"><option value="landscape" @selected(($printOrientation ?? 'landscape') === 'landscape')>{{ $t('landscape') }}</option><option value="portrait" @selected(($printOrientation ?? 'landscape') === 'portrait')>{{ $t('portrait') }}</option></select>
            <a class="finance-btn primary" id="finance-print" href="{{ $financeLink(['print' => 1, 'orientation' => $printOrientation ?? 'landscape']) }}" target="_blank" rel="noopener"><i class="fa fa-print" aria-hidden="true"></i> {{ $t('print') }}</a>
        </div>
    </div>
    <details class="finance-filter-panel finance-no-print" id="finance-filter-panel" open>
        <summary><span><i class="fa fa-sliders" aria-hidden="true"></i> {{ $t('global_filters') }}</span><span class="finance-filter-summary">{{ $filters['start_date'] }} — {{ $filters['end_date'] }} <i class="fa fa-chevron-down" aria-hidden="true"></i></span></summary>
        <form class="finance-filter" id="finance-filter-form" method="get" action="{{ route('reports.financial-dashboard') }}">
            <input type="hidden" name="report_lang" value="{{ $language }}"><input type="hidden" name="location_filter_applied" value="1">
            <input type="hidden" id="finance-start" name="start_date" value="{{ $filters['start_date'] }}" aria-label="{{ $t('from_date') }}">
            <input type="hidden" id="finance-end" name="end_date" value="{{ $filters['end_date'] }}" aria-label="{{ $t('to_date') }}">
            <div class="finance-field"><label for="finance-date-range">{{ $t('date_range') }}</label><div class="input-group"><span class="input-group-addon"><i class="fa fa-calendar" aria-hidden="true"></i></span><input class="form-control" id="finance-date-range" value="{{ $filters['start_date'] }} ~ {{ $filters['end_date'] }}" readonly></div></div>
            <div class="finance-field locations"><label for="finance-locations">{{ $t('locations') }}</label><select class="form-control select2" id="finance-locations" name="location_ids[]" multiple>
                @foreach($locations as $location)<option value="{{ $location->id }}" @selected(in_array((int) $location->id, $filters['location_ids'], true))>{{ $location->name }}</option>@endforeach
            </select></div>
            <div class="finance-field"><label for="finance-source">{{ $t('finance_source') }}</label><select class="form-control" id="finance-source" name="source">@foreach(['all', 'main', 'accessory', 'service'] as $source)<option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $t('finance_source_'.$source) }}</option>@endforeach</select></div>
            <div class="finance-field locations"><label for="finance-expense-categories">{{ $t('expense_breakdown') }}</label>
                <input type="hidden" id="finance-expense-filter-applied" name="expense_filter_applied" value="{{ $filters['expense_filter_applied'] }}">
                @foreach($filters['excluded_expense_categories'] as $excluded)<input type="hidden" class="finance-excluded-category" name="excluded_expense_categories[]" value="{{ $excluded }}">@endforeach
                <select class="form-control select2" id="finance-expense-categories" name="expense_categories[]" multiple>
                    @foreach($expenseChoices as $name)<option value="{{ $name }}" @selected(in_array($name, $selectedExpenses, true))>{{ $name === '__uncategorized__' ? $t('uncategorized') : $name }}</option>@endforeach
                </select>
                <div class="finance-category-tools"><button type="button" id="finance-expenses-all">{{ $t('select_all') }}</button><button type="button" id="finance-expenses-none">{{ $t('deselect_all') }}</button><a href="{{ $financeLink($clearExpenses) }}">{{ $t('finance_restore_expenses') }}</a></div>
            </div>
            <button class="finance-btn primary" type="submit"><i class="fa fa-filter" aria-hidden="true"></i> {{ $t('view_report') }}</button>
            <a class="finance-btn" href="{{ route('reports.financial-dashboard', ['report_lang' => $language]) }}">{{ $t('reset') }}</a>
            <div class="finance-filter-footer">{{ $t('finance_branch_hint') }} {{ $t('finance_click_filter') }}</div>
        </form>
    </details>
    <div class="finance-active-filters">
        @if($filters['source'] !== 'all')<a class="finance-filter-chip" href="{{ $financeLink(['source' => 'all']) }}">{{ $t('finance_source_'.$filters['source']) }} <span aria-hidden="true">×</span></a>@endif
        @if($report['expense_filter_active'])<div class="finance-expense-scope">{{ $t('finance_expense_filter_note') }} <a href="{{ $financeLink($clearExpenses) }}">{{ $t('finance_restore_expenses') }}</a></div>@endif
        @foreach($filters['excluded_expense_categories'] as $name)<a class="finance-filter-chip" href="{{ $financeLink(['excluded_expense_categories' => array_values(array_diff($filters['excluded_expense_categories'], [$name]))]) }}">{{ $t('finance_excluded') }}: {{ $name === '__uncategorized__' ? $t('uncategorized') : $name }} <span aria-hidden="true">×</span></a>@endforeach
    </div>
    @if($errors->any())<div class="finance-warning" role="alert">{{ $errors->first() }}</div>@endif
    @if($report['unavailable'])<div class="finance-warning" role="alert">{{ $t('finance_unavailable') }}</div>@endif
    @if($total['missing_costs'])<div class="finance-warning" role="status">{{ $t('finance_missing_costs') }}</div>@endif
    @if($total['estimated_costs'])<div class="finance-warning" role="status">{{ $t('finance_estimated_costs') }} ({{ $total['estimated_costs'] }})</div>@endif

    <div class="finance-kpis">
        <div class="finance-kpi finance-kpi-profit"><div class="finance-kpi-label"><span>{{ $t('finance_gross_profit') }} <small>{{ $language === 'km' ? '(Gross Profit)' : '' }}</small></span><i class="fa fa-line-chart" aria-hidden="true"></i></div><div class="finance-kpi-value">{{ $fmt($total['gross_profit']) }}</div><div class="finance-kpi-foot">{{ $t('gross_profit_formula') }}</div></div>
        <div class="finance-kpi finance-kpi-revenue"><div class="finance-kpi-label"><span>{{ $t('total_revenue') }} <small>{{ $language === 'km' ? '(Total Revenue)' : '' }}</small></span><i class="fa fa-dollar" aria-hidden="true"></i></div><div class="finance-kpi-value">{{ $fmt($total['net_sales']) }}</div><div class="finance-kpi-foot">{{ \Carbon\Carbon::parse($filters['start_date'])->format('d M') }} — {{ \Carbon\Carbon::parse($filters['end_date'])->format('d M Y') }}</div></div>
        <div class="finance-kpi finance-kpi-units"><div class="finance-kpi-label"><span>{{ $t('total_units_sold') }} <small>{{ $language === 'km' ? '(Total Units Sold)' : '' }}</small></span><i class="fa fa-cubes" aria-hidden="true"></i></div><div class="finance-kpi-value">{{ $units($total['units']) }} <small>{{ $t('units') }}</small></div><div class="finance-kpi-foot">{{ $t('units_net_returns') }}</div></div>
        <div class="finance-kpi finance-kpi-location"><div class="finance-kpi-label"><span>{{ $t('top_location') }} <small>{{ $language === 'km' ? '(Top Location)' : '' }}</small></span><i class="fa fa-map-marker" aria-hidden="true"></i></div><div class="finance-kpi-value finance-top-name">@if($report['top_location'])<a class="finance-data-link" href="{{ $financeLink(['location_ids' => $report['top_location']['location_ids']]) }}">{{ $report['top_location']['name'] }}</a>@else — @endif</div><div class="finance-kpi-foot">{{ $report['top_location'] ? $units($report['top_location']['units']).' '.$t('units') : ($total['units'] === null ? $t('not_available') : $t('no_sales')) }}</div></div>
    </div>

    @if(empty($report['branches']))
        <div class="finance-panel finance-empty">{{ $t('finance_no_locations') }}</div>
    @else
    <div class="finance-workspace">
        <div class="finance-panel finance-main-panel">
            <div class="finance-panel-heading"><h2>{{ $t('financial_performance') }}</h2><span>{{ $currencySymbol }} · {{ $t('currency') }}</span></div>
            <div class="finance-table-wrap"><table class="finance-table finance-combined-table">
                <thead><tr><th scope="col"><div>{{ $t('financial_overview') }}</div><small>{{ $filters['start_date'] }} — {{ $filters['end_date'] }}</small></th>
                    @foreach($report['branches'] as $branch)<th scope="col"><a href="{{ route('reports.financial-dashboard', array_merge($query, ['location_ids' => $branch['location_ids']])) }}">{{ $branch['name'] }}<strong>{{ $units($branch['units']) }} {{ $t('units') }}</strong></a></th>@endforeach
                    <th scope="col">{{ $t('total') }}<strong>{{ $units($total['units']) }} {{ $t('units') }}</strong></th></tr></thead>
                <tbody>
                @foreach($performanceMetrics as $metric)
                    <tr class="{{ in_array($metric, ['net_sales', 'gross_profit']) ? 'finance-subtotal' : (str_contains($metric, 'margin') ? 'finance-margin' : '') }}">
                        <th scope="row">{{ $t('finance_'.$metric) }}</th>
                        @foreach([...array_values($report['branches']), $total] as $branch)@include('localcashierreport::partials.financial_metric_cell')@endforeach
                    </tr>
                @endforeach
                <tr class="finance-section-row"><th colspan="{{ count($report['branches']) + 2 }}">{{ $t('expense_breakdown') }} @if($canViewExpenseDetails)<a class="finance-expense-details finance-btn finance-no-print" href="{{ $expenseDetailsLink() }}">{{ $t('finance_expense_details') }}</a>@endif</th></tr>
                @if($report['unavailable'])<tr><td colspan="{{ count($report['branches']) + 2 }}">{{ $t('finance_unavailable') }}</td></tr>
                @else
                    @forelse($report['expenses'] as $expense)<tr><th scope="row"><a class="finance-data-link" href="{{ $focusExpense($expense['name']) }}" title="{{ $t('finance_click_filter') }}">{{ $expense['name'] === '__uncategorized__' ? $t('uncategorized') : $expense['name'] }}</a> <a class="finance-remove-category finance-no-print" href="{{ $financeLink(['excluded_expense_categories' => array_values(array_unique([...$filters['excluded_expense_categories'], $expense['name']]))]) }}" aria-label="{{ $t('finance_remove_category') }}: {{ $expense['name'] }}" title="{{ $t('finance_remove_category') }}">×</a></th>
                    @foreach($report['branches'] as $key => $expenseBranch)<td><a class="finance-data-link {{ $canViewExpenseDetails ? 'finance-expense-details' : '' }}" href="{{ $canViewExpenseDetails ? $expenseDetailsLink(['location_ids' => $expenseBranch['location_ids'], 'expense_filter_applied' => 1, 'expense_categories' => [$expense['name']], 'excluded_expense_categories' => []]) : $focusExpense($expense['name'], ['location_ids' => $expenseBranch['location_ids']]) }}">{{ $fmt($expense['values'][$key] ?? 0) }}</a></td>@endforeach<td><a class="finance-data-link {{ $canViewExpenseDetails ? 'finance-expense-details' : '' }}" href="{{ $canViewExpenseDetails ? $expenseDetailsLink(['expense_filter_applied' => 1, 'expense_categories' => [$expense['name']], 'excluded_expense_categories' => []]) : $focusExpense($expense['name']) }}">{{ $fmt($expense['total']) }}</a></td></tr>
                    @empty<tr><td colspan="{{ count($report['branches']) + 2 }}">{{ $t('finance_no_expenses') }}</td></tr>@endforelse
                @endif
                </tbody>
                <tbody class="finance-totals">
                @foreach(['expenses', 'net_profit', 'margin'] as $metric)
                    <tr class="{{ $metric === 'net_profit' ? 'finance-result' : ($metric === 'expenses' ? 'finance-subtotal' : 'finance-margin') }}"><th scope="row">{{ $t('finance_'.$metric) }}</th>@foreach([...array_values($report['branches']), $total] as $branch)@include('localcashierreport::partials.financial_metric_cell')@endforeach</tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <aside class="finance-panel finance-chart-panel">
            <div class="finance-panel-heading"><h2>{{ $t('expense_mix') }}<small>{{ $t('expense_share') }}</small></h2><i class="fa fa-pie-chart" aria-hidden="true"></i></div>
            <div class="finance-chart-controls finance-no-print">
                <button type="button" class="finance-btn" id="finance-open-category-filter"><i class="fa fa-filter" aria-hidden="true"></i> {{ $t('finance_filter_categories') }}</button>
                <a href="{{ $financeLink($clearExpenses) }}">{{ $t('finance_restore_expenses') }}</a>
            </div>
            @if($canViewExpenseDetails)<a class="finance-expense-details finance-chart-details finance-no-print" href="{{ $expenseDetailsLink() }}">{{ $t('finance_expense_details') }}</a>@endif
            <div class="finance-chart-total"><span>{{ $t('finance_expenses') }}</span><strong>{{ $fmt($total['expenses']) }}</strong></div>
            @if($report['expense_filter_active'])<p class="finance-chart-note">{{ $t('finance_expense_filter_note') }}</p>@endif
            @if($report['unavailable'])<div class="finance-empty">{{ $t('finance_unavailable') }}</div>
            @elseif(empty($chartRows))<div class="finance-empty">{{ $t('finance_no_expenses') }}</div>
            @else<div class="finance-chart">
                @foreach($chartRows as $expense)
                    <div class="finance-chart-item">
                        <a class="finance-bar-row finance-data-link" href="{{ $focusExpense($expense['category_names'] ?? [$expense['name']]) }}" title="{{ $t('finance_click_filter') }}"><div class="finance-bar-label"><span>{{ $expense['name'] === '__other__' ? $t('other') : ($expense['name'] === '__uncategorized__' ? $t('uncategorized') : $expense['name']) }}</span><strong>{{ number_format($expense['share'], 1) }}%</strong></div><div class="finance-bar-track" role="img" aria-label="{{ number_format($expense['share'], 1) }}%"><div class="finance-bar-fill" style="width:{{ min(100, max(0, $expense['share'])) }}%"></div></div></a>
                        <div class="finance-chart-item-actions"><span>@if($canViewExpenseDetails)<a class="finance-expense-details finance-data-link" href="{{ $expenseDetailsLink(['expense_filter_applied' => 1, 'expense_categories' => $expense['category_names'] ?? [$expense['name']], 'excluded_expense_categories' => []]) }}">{{ $fmt($expense['total']) }}</a>@else{{ $fmt($expense['total']) }}@endif</span><a class="finance-remove-category finance-no-print" href="{{ $financeLink(['excluded_expense_categories' => array_values(array_unique([...$filters['excluded_expense_categories'], ...($expense['category_names'] ?? [$expense['name']])]))]) }}" title="{{ $t('finance_remove_category') }}" aria-label="{{ $t('finance_remove_category') }}">×</a></div>
                    </div>
                @endforeach
            </div><div class="finance-chart-note">{{ $t('finance_chart_note') }}</div>@endif
        </aside>
    </div>
    @endif
<div class="modal fade finance-no-print" id="finance-expense-details-modal" tabindex="-1" role="dialog" aria-labelledby="finance-expense-modal-title">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="{{ $t('close') }}">×</button><h4 class="modal-title" id="finance-expense-modal-title">{{ $t('finance_expense_details') }}</h4></div>
        <div class="modal-body">
            <div id="finance-details-error" class="alert alert-danger" role="alert" hidden></div>
            <div id="finance-details-success" class="alert alert-success" role="status" hidden></div>
            <form id="finance-details-edit-form" class="finance-edit-form" hidden>
                <h4>{{ $t('edit') }} · {{ $t('expenses') }}</h4>
                <input type="hidden" name="report_lang" value="{{ $language }}">
                <input type="hidden" name="updated_at" id="finance-edit-version">
                <div class="row">
                    <div class="col-sm-4 form-group"><label for="finance-edit-ref">{{ $t('ref_no') }}</label><input class="form-control" id="finance-edit-ref" name="ref_no" maxlength="191"></div>
                    <div class="col-sm-4 form-group"><label for="finance-edit-date">{{ $t('date') }}</label><input class="form-control" id="finance-edit-date" type="datetime-local" step="1" name="transaction_date" required></div>
                    <div class="col-sm-4 form-group"><label for="finance-edit-amount">{{ $t('amount') }} ({{ $currencySymbol }})</label><input class="form-control" id="finance-edit-amount" type="number" step="0.0001" min="0" max="999999999999" name="final_total" required></div>
                    <div class="col-sm-6 form-group"><label for="finance-edit-category">{{ $t('expense_category') }}</label><select class="form-control" id="finance-edit-category" name="expense_category_id"></select></div>
                    <div class="col-sm-6 form-group"><label for="finance-edit-subcategory">{{ $t('finance_subcategory') }}</label><select class="form-control" id="finance-edit-subcategory" name="expense_sub_category_id"></select></div>
                    <div class="col-sm-12 form-group"><label for="finance-edit-note">{{ $t('finance_expense_note') }}</label><textarea class="form-control" rows="3" id="finance-edit-note" name="additional_notes" maxlength="10000"></textarea></div>
                </div>
                <button class="finance-btn primary" type="submit">{{ $t('finance_save_expense') }}</button><button class="finance-btn" type="button" id="finance-edit-cancel">{{ $t('cancel') }}</button>
            </form>
            <div class="finance-details-summary"><span id="finance-details-count"></span><strong id="finance-details-total"></strong></div>
            <div class="table-responsive"><table class="table table-striped table-bordered" id="finance-details-table"><thead><tr>
                <th>{{ $t('date') }}</th><th>{{ $t('ref_no') }}</th><th>{{ $t('location') }}</th><th>{{ $t('finance_source') }}</th><th>{{ $t('expense_category') }}</th><th>{{ $t('amount') }}</th><th>{{ $t('finance_expense_note') }}</th><th>{{ $t('edit') }}</th>
            </tr></thead><tbody></tbody></table></div>
            <div class="finance-details-pagination"><button type="button" class="finance-btn" id="finance-details-prev">{{ $t('previous') }}</button><span id="finance-details-page"></span><button type="button" class="finance-btn" id="finance-details-next">{{ $t('next') }}</button></div>
        </div>
        <div class="modal-footer"><button type="button" class="finance-btn" data-dismiss="modal">{{ $t('close') }}</button></div>
    </div></div>
</div>
    <p class="finance-note">{{ $t('finance_basis') }}</p>
    <div class="finance-footer"><span>{{ $businessName }} · {{ $t('financial_dashboard') }}</span><span>{{ $t('generated') }}: {{ now()->format('d M Y H:i') }}</span></div>
</section>
@endsection

@section('javascript')
<script src="{{ asset('js/financial-dashboard.js?v=' . $asset_v . '&m=' . filemtime(public_path('js/financial-dashboard.js'))) }}"></script>
<script>
$(function () {
    @php $currentReportLang = $language; @endphp
    @include('localcashierreport::partials.table_language')
    window.initFinancialDashboard({dateSettings: reportDateSettings, ranges: reportDateRanges,
        currencySymbol: @json($currencySymbol), reportLanguage: @json($language),
        labels: {loading: @json($t('loading')), error: @json($t('finance_expense_error')), edit: @json($t('edit')),
            total: @json($t('total')), none: @json($t('none')), empty: @json($t('no_data')),
            main: @json($t('finance_source_main')), accessory: @json($t('finance_source_accessory')), service: @json($t('finance_source_service'))}
    });
});
</script>
@endsection
