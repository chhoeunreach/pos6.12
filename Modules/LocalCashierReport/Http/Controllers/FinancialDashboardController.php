<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\System;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;
use Modules\LocalCashierReport\Support\ReportLanguage;

class FinancialDashboardController extends Controller
{
    public function __construct(private LocalCashierReportController $cashierReport, private FinancialDashboardReport $report)
    {
    }

    public function index(Request $request)
    {
        [$businessId, $locations, $filters] = $this->context($request);
        $report = $this->report->build($businessId, $locations, $filters, $request->boolean('refresh'));
        if ($request->input('download') === 'csv') {
            return $this->csv($report, $filters);
        }
        if ($request->input('download') === 'word') {
            return app(\Modules\LocalCashierReport\Services\FinancialDashboardWordExport::class)->download($report, $filters,
                (string) session('business.name', config('app.name')), (string) data_get(session('currency'), 'symbol', '$'));
        }

        return view('localcashierreport::financial_dashboard', [
            'businessName' => (string) session('business.name', config('app.name')),
            'locations' => $locations, 'filters' => $filters, 'report' => $report,
            'currencySymbol' => (string) data_get(session('currency'), 'symbol', '$'),
            'printMode' => $request->boolean('print'),
            'printOrientation' => $request->input('orientation', 'landscape'),
        ]);
    }

    public function context(Request $request): array
    {
        abort_unless($request->user()->can('profit_loss_report.view'), 403);
        abort_if(empty(System::getProperty('localcashierreport_version')), 404);
        $data = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'location_ids' => 'nullable|array', 'location_ids.*' => 'integer',
            'location_filter_applied' => 'nullable|boolean',
            'source' => 'nullable|in:all,main,accessory,service',
            'expense_filter_applied' => 'nullable|boolean',
            'expense_categories' => 'nullable|array|max:1000',
            'expense_categories.*' => 'string|max:255',
            'excluded_expense_categories' => 'nullable|array|max:1000',
            'excluded_expense_categories.*' => 'string|max:255',
            'report_lang' => 'nullable|in:km,en', 'download' => 'nullable|in:csv,word',
            'print' => 'nullable|boolean',
            'orientation' => 'nullable|in:portrait,landscape',
            'refresh' => 'nullable|boolean',
        ]);
        $businessId = (int) session('user.business_id');
        $locations = $this->cashierReport->getAccessibleLocations($businessId);
        $allowedIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->all();
        // Ignore invisible formatting characters and spacing differences in
        // location labels, including zero-width spaces in Khmer names.
        $normalizeLocation = static function (string $name): string {
            $name = preg_replace('/\p{Cf}/u', '', $name);
            $name = preg_replace('/\s*-\s*/u', '-', $name);

            return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        };
        $excludedNames = array_map($normalizeLocation, config('localcashierreport.financial_dashboard_default_excluded_locations', []));
        $defaultIds = $locations->reject(fn ($location) => in_array($normalizeLocation($location->name), $excludedNames, true))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $today = Carbon::today()->format('Y-m-d');
        $filters = [
            'source' => $data['source'] ?? 'all',
            'expense_filter_applied' => array_key_exists('expense_filter_applied', $data)
                ? (int) $request->boolean('expense_filter_applied') : (isset($data['expense_categories']) ? 1 : 0),
            'expense_categories' => array_values(array_unique($data['expense_categories'] ?? [])),
            'excluded_expense_categories' => array_values(array_unique($data['excluded_expense_categories'] ?? [])),
            'start_date' => $data['start_date'] ?? $today,
            'end_date' => $data['end_date'] ?? $today,
            'location_ids' => $request->boolean('location_filter_applied') || isset($data['location_ids'])
                ? array_values(array_intersect(array_map('intval', $data['location_ids'] ?? []), $allowedIds)) : $defaultIds,
        ];
        abort_if($filters['end_date'] < $filters['start_date'], 422, 'End date must be on or after start date.');

        return [$businessId, $locations, $filters];
    }

    private function csv(array $report, array $filters)
    {
        $t = fn ($key) => ReportLanguage::text($key);

        return response()->streamDownload(function () use ($report, $filters, $t) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            $safe = fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/u', $value) ? "'".$value : $value;
            $write = fn ($row) => fputcsv($file, array_map($safe, $row));
            $write([$t('financial_dashboard'), $filters['start_date'], $filters['end_date']]);
            $write([$t('finance_source'), $t('finance_source_'.$filters['source'])]);
            if ($report['expense_filter_active']) {
                $write([$t('finance_expense_filter_note')]);
            }
            $write([$t('metric'), ...array_column($report['branches'], 'name'), $t('total')]);
            foreach (array_diff(FinancialDashboardReport::METRICS, ['sales', 'returns']) as $metric) {
                $row = [$t('finance_'.$metric)];
                foreach ([...array_values($report['branches']), $report['total']] as $branch) {
                    $row[] = $branch[$metric] === null ? $t('not_available') : round($branch[$metric], 2);
                }
                $write($row);
            }
            $write([]);
            $write([$t('expenses')]);
            foreach ($report['expenses'] as $expense) {
                $row = [$expense['name'] === '__uncategorized__' ? $t('uncategorized') : $expense['name']];
                foreach (array_keys($report['branches']) as $key) {
                    $row[] = round($expense['values'][$key] ?? 0, 2);
                }
                $row[] = round($expense['total'], 2);
                $write($row);
            }
            $write([$t('finance_basis')]);
            if ($report['total']['missing_revenue']) $write([$t('finance_missing_selling_prices')]);
            if ($report['total']['missing_costs']) $write([$t('finance_missing_costs')]);
            if ($report['total']['estimated_costs']) $write([$t('finance_estimated_costs')]);
            if ($report['unavailable']) $write([$t('finance_unavailable')]);
            fclose($file);
        }, 'financial_dashboard_'.$filters['start_date'].'_'.$filters['end_date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
