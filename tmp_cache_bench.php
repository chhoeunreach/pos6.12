<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;

$locations = collect(DB::table('business_locations')->where('business_id', 1)->get());
$filters = [
    'source' => 'all',
    'start_date' => '2026-06-30',
    'end_date' => '2026-11-05',
    'location_ids' => $locations->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'expense_filter_applied' => 0,
    'expense_categories' => [],
    'excluded_expense_categories' => [],
];

$report = app(FinancialDashboardReport::class);

$t = microtime(true);
$cold = $report->build(1, $locations, $filters, true);
$coldMs = round((microtime(true) - $t) * 1000);

$t = microtime(true);
$warm = $report->build(1, $locations, $filters);
$warmMs = round((microtime(true) - $t) * 1000, 2);

printf("cold=%dms  warm=%.2fms  same=%s  ttl=%d\n", $coldMs, $warmMs, $cold == $warm ? 'yes' : 'no', (int) config('localcashierreport.financial_dashboard_cache_ttl'));
