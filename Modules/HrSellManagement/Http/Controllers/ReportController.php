<?php

namespace Modules\HrSellManagement\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->canReport(), 403);

        [$rows, $summary] = $this->reportData($request, true);
        [$hrBranches, $hrSellTypes, $hrSellers, $hrDepartments] = $this->filterOptions($request);

        return view('hrsellmanagement::reports.index', compact('rows', 'summary', 'hrBranches', 'hrSellTypes', 'hrSellers', 'hrDepartments'));
    }

    public function export(Request $request)
    {
        abort_unless($this->canReport(), 403);

        [$rows] = $this->reportData($request, false);
        $exportRows = $rows->map(fn ($row) => [
            'Invoice' => $row->invoice_no,
            'Date' => $row->created_at,
            'Branch' => $row->branch_name,
            'Customer' => $row->customer_name,
            'Phone' => $row->customer_phone,
            'Seller Username' => $row->staff_code,
            'Seller' => $row->staff_name ?: $row->seller_name,
            'Sell Type' => $this->sellTypeLabel($row->service_type),
            'Products' => str_replace('|||', ', ', (string) $row->product_names),
            'Serial / IMEI' => str_replace('|||', ', ', (string) $row->serial_numbers),
            'Total Qty' => $row->total_qty,
            'Total Amount' => $row->total_amount,
            'Note' => $row->note,
            'Created At' => $row->created_at,
        ])->all();

        return Excel::download(new ArrayExport($exportRows), 'hr_sell_report_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function staff(Request $request)
    {
        abort_unless($this->canReport(), 403);

        $request = $this->withDefaultStaffReportDates($request);
        [$summaryRows, $lineRows, $totals, $period, $topSellers, $trafficRows] = $this->staffReportData($request, true);
        [$hrBranches, $hrSellTypes, $hrSellers, $hrDepartments] = $this->filterOptions($request);

        return view('hrsellmanagement::reports.staff', compact('summaryRows', 'lineRows', 'totals', 'period', 'topSellers', 'trafficRows', 'hrBranches', 'hrSellTypes', 'hrSellers', 'hrDepartments'));
    }

    public function staffExport(Request $request)
    {
        abort_unless($this->canReport(), 403);

        $request = $this->withDefaultStaffReportDates($request);
        $staffReport = $this->staffReportData($request, false);
        $summaryRows = $staffReport[0];
        $lineRows = $staffReport[1];
        $period = $staffReport[3];
        $type = $request->input('export_type') === 'lines' ? 'lines' : 'summary';

        if ($type === 'lines') {
            $exportRows = $lineRows->map(fn ($row) => [
                'Period' => $row->period_label,
                'Date' => $row->created_at,
                'Invoice' => $row->invoice_no,
                'Branch' => $row->branch_name,
                'Seller Username' => $row->staff_code,
                'Seller' => $row->staff_name,
                'Phone number' => $row->customer_phone ?? '',
                'Sell Type' => $this->sellTypeLabel($row->service_type),
                'Product' => $row->product_name,
                'SKU' => $row->sku,
                'Serial / IMEI' => $row->serial_identifier,
                'Qty' => $row->qty,
                'Price' => $row->unit_price,
                'Total' => $row->line_total,
            ])->all();
        } else {
            $exportRows = $summaryRows->map(fn ($row) => [
                'Period' => $row->period_label,
                'Seller Username' => $row->staff_code,
                'Seller' => $row->staff_name,
                'Branch' => $row->branch_name,
                'Invoice Qty' => $row->sale_count,
                'Products Qty' => $row->total_qty,
                'Average Price' => $row->average_price,
                'Total' => $row->sale_total,
            ])->all();
        }

        return Excel::download(new ArrayExport($exportRows), 'hr_staff_sell_' . $period . '_' . $type . '_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function commission(Request $request)
    {
        abort_unless($this->canReport(), 403);

        $request = $this->withDefaultStaffReportDates($request);
        [$commissionRows, $commissionTotals, $commissionColumns] = $this->commissionReportData($request, true);
        [$hrBranches, $hrSellTypes, $hrSellers, $hrDepartments] = $this->filterOptions($request);

        return view('hrsellmanagement::reports.commission', compact('commissionRows', 'commissionTotals', 'commissionColumns', 'hrBranches', 'hrSellTypes', 'hrSellers', 'hrDepartments'));
    }

    public function commissionExport(Request $request)
    {
        abort_unless($this->canReport(), 403);

        $request = $this->withDefaultStaffReportDates($request);
        $commissionReport = $this->commissionReportData($request, false);
        $commissionRows = $commissionReport[0];
        $commissionColumns = $commissionReport[2];
        $commissionPeriod = $commissionReport[3];

        $exportRows = $commissionRows->map(function ($row) use ($commissionColumns, $commissionPeriod) {
            $exportRow = [
                ($commissionPeriod === 'monthly' ? 'Month' : 'Date') => $row->sale_period ?? '',
                'Username' => $row->staff_code,
                'Staff' => $row->staff_name,
                'Branch' => $row->branch_name,
                'Office Time' => $row->office_time ?? '-',
                'Start - End Time' => $row->office_time_range ?? '-',
                'Total Hour / Day' => $row->total_hour_day ?? '-',
                'Time Work' => $row->time_work ?? '-',
            ];

            foreach ($commissionColumns as $column) {
                $exportRow[$column['label']] = $row->{$column['key'] . '_total'} ?? 0;

                if ($column['has_commission']) {
                    $exportRow[$column['label'] . ' Commission'] = $row->{$column['key'] . '_commission_total'} ?? 0;
                }
            }

            $exportRow['Total Amount'] = $row->commission_total ?? 0;

            return $exportRow;
        })->all();

        return Excel::download(new ArrayExport($exportRows), 'hr_commission_report_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function edit($report_id)
    {
        abort_unless($this->canEditReport(), 403);

        $report = $this->findReport($report_id);
        [$hrBranches, $hrSellTypes] = $this->filterOptions();
        $selectedSellType = $this->normalizeSellTypeKey($report->service_type);

        if ($selectedSellType !== '' && ! $hrSellTypes->has($selectedSellType)) {
            $hrSellTypes->put($selectedSellType, $this->sellTypeLabel($report->service_type));
        }

        return view('hrsellmanagement::reports.partials.edit_modal', compact('report', 'hrBranches', 'hrSellTypes', 'selectedSellType'));
    }

    public function update(Request $request, $report_id)
    {
        abort_unless($this->canEditReport(), 403);

        $report = $this->findReport($report_id);
        $data = $request->validate([
            'invoice_no' => 'nullable|string|max:191',
            'original_invoice_no' => 'nullable|string|max:191',
            'created_at' => 'required|date',
            'branch_name' => 'nullable|string|max:191',
            'customer_name' => 'nullable|string|max:191',
            'customer_phone' => 'nullable|string|max:50',
            'seller_name' => 'nullable|string|max:191',
            'service_type' => 'nullable|string|max:191',
            'total_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:3000',
        ]);

        $updates = [
            'invoice_no' => $data['invoice_no'] ?? null,
            'original_invoice_no' => $data['original_invoice_no'] ?? null,
            'created_at' => date('Y-m-d H:i:s', strtotime($data['created_at'])),
            'branch_name' => $data['branch_name'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'seller_name' => $data['seller_name'] ?? null,
            'service_type' => $this->sellTypeLabel($data['service_type'] ?? $report->service_type),
            'total_amount' => $data['total_amount'] ?? 0,
            'note' => $data['note'] ?? null,
        ];

        if (Schema::connection('hr')->hasColumn('sell_out_reports', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::connection('hr')->table('sell_out_reports')->where('id', $report->id)->update($updates);
        $this->logReportAction('report_updated', $report->id, $report, $updates, $request);

        if ($request->ajax()) {
            return response()->json(['success' => 1, 'msg' => 'HR sell report updated']);
        }

        return redirect()->route('hr-sell.reports.index', $request->query())->with('status', ['success' => 1, 'msg' => 'HR sell report updated']);
    }

    public function destroy(Request $request, $report_id)
    {
        abort_unless($this->canDeleteReport(), 403);

        $report = $this->findReport($report_id);

        DB::connection('hr')->transaction(function () use ($report) {
            if (Schema::connection('hr')->hasTable('sell_out_report_photos')) {
                DB::connection('hr')->table('sell_out_report_photos')->where('sell_out_report_id', $report->id)->delete();
            }

            if (Schema::connection('hr')->hasTable('sell_out_report_lines')) {
                DB::connection('hr')->table('sell_out_report_lines')->where('sell_out_report_id', $report->id)->delete();
            }

            DB::connection('hr')->table('sell_out_reports')->where('id', $report->id)->delete();
        });

        $this->logReportAction('report_deleted', $report->id, $report, null, $request);

        if ($request->ajax()) {
            return response()->json(['success' => 1, 'msg' => 'HR sell report deleted']);
        }

        return back()->with('status', ['success' => 1, 'msg' => 'HR sell report deleted']);
    }

    public function destroyLine(Request $request, $line_id)
    {
        abort_unless($this->canDeleteReport(), 403);
        abort_unless(Schema::connection('hr')->hasTable('sell_out_report_lines'), 404);

        $line = DB::connection('hr')
            ->table('sell_out_report_lines as sol')
            ->join('sell_out_reports as sor', 'sor.id', '=', 'sol.sell_out_report_id')
            ->where('sol.id', $line_id)
            ->select('sol.*', 'sor.id as report_id')
            ->first();

        abort_if(empty($line), 404);

        DB::connection('hr')->transaction(function () use ($line) {
            DB::connection('hr')->table('sell_out_report_lines')->where('id', $line->id)->delete();

            $totalColumn = $this->firstHrLineColumn(['line_total', 'total', 'total_amount', 'subtotal', 'amount']);
            $priceColumn = $this->firstHrLineColumn(['unit_price', 'price', 'sell_price', 'selling_price', 'product_price']);
            $qtyColumn = $this->firstHrLineColumn(['qty', 'quantity', 'sale_qty', 'product_qty']);

            $remainingTotalQuery = DB::connection('hr')
                ->table('sell_out_report_lines')
                ->where('sell_out_report_id', $line->report_id);

            if ($totalColumn) {
                $remainingTotal = $remainingTotalQuery->sum($totalColumn);
            } elseif ($priceColumn && $qtyColumn) {
                $remainingTotal = (clone $remainingTotalQuery)
                    ->selectRaw('COALESCE(SUM(COALESCE(' . $qtyColumn . ', 0) * COALESCE(' . $priceColumn . ', 0)), 0) as total')
                    ->value('total');
            } else {
                $remainingTotal = 0;
            }

            $reportUpdates = ['total_amount' => $remainingTotal];
            if (Schema::connection('hr')->hasColumn('sell_out_reports', 'updated_at')) {
                $reportUpdates['updated_at'] = now();
            }

            DB::connection('hr')
                ->table('sell_out_reports')
                ->where('id', $line->report_id)
                ->update($reportUpdates);
        });

        $this->logReportAction('report_line_deleted', (int) $line->report_id, $line, null, $request);

        return response()->json(['success' => 1, 'msg' => 'Sale line deleted']);
    }

    private function reportData(Request $request, bool $paginate): array
    {
        try {
            $baseQuery = $this->baseReportQuery($request);

            $summary = (array) (clone $baseQuery)->selectRaw('
                COUNT(*) as sale_count,
                COUNT(DISTINCT NULLIF(TRIM(sor.customer_phone), "")) as customer_count,
                COALESCE(SUM(sor.total_amount), 0) as sale_total,
                COALESCE(AVG(sor.total_amount), 0) as average_sale
            ')->first();

            $summary['total_qty'] = (float) DB::connection('hr')
                ->table('sell_out_report_lines as sol')
                ->joinSub((clone $baseQuery)->select('sor.id'), 'filtered_reports', function ($join) {
                    $join->on('filtered_reports.id', '=', 'sol.sell_out_report_id');
                })
                ->sum('sol.qty');

            $rowsQuery = $this->selectReportRows($baseQuery)
                ->orderByDesc('sor.created_at')
                ->orderByDesc('sor.id');

            $reportPerPage = $this->reportPerPage($request);
            if ($paginate && $reportPerPage === 'all') {
                $allRows = $rowsQuery->get();
                $rows = new LengthAwarePaginator($allRows, $allRows->count(), max($allRows->count(), 1), 1, [
                    'path' => $request->url(),
                    'pageName' => 'hr_report_page',
                    'query' => $request->query(),
                ]);
            } else {
                $rows = $paginate
                    ? $rowsQuery->paginate($reportPerPage, ['*'], 'hr_report_page')->appends($request->query())
                    : $rowsQuery->get();
            }

            $rowCollection = method_exists($rows, 'getCollection') ? $rows->getCollection() : $rows;
            $rowCollection->transform(function ($row) {
                $row->service_type_label = $this->sellTypeLabel($row->service_type);

                return $row;
            });

            return [$rows, $summary];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sell report data: ' . $e->getMessage());

            return [$this->emptyRows($request), $this->emptySummary()];
        }
    }

    private function reportPerPage(Request $request)
    {
        $perPage = (string) $request->input('hr_report_per_page', '50');
        $allowed = ['25', '50', '100', '200', '500', 'all'];

        return in_array($perPage, $allowed, true) ? ($perPage === 'all' ? 'all' : (int) $perPage) : 50;
    }

    private function staffReportData(Request $request, bool $paginate): array
    {
        try {
            $period = $request->input('period') === 'monthly' ? 'monthly' : 'daily';
            $periodExpr = $period === 'monthly'
                ? "DATE_FORMAT(sor.created_at, '%Y-%m')"
                : 'DATE(sor.created_at)';
            $lineExpressions = $this->hrLineAmountExpressions();
            $base = $this->baseStaffLineQuery($request, $periodExpr, $lineExpressions);

            $totals = (array) (clone $base)->selectRaw('
                COUNT(DISTINCT sor.id) as sale_count,
                COUNT(sol.id) as line_count,
                COALESCE(SUM(' . $lineExpressions['qty'] . '), 0) as total_qty,
                COALESCE(SUM(' . $lineExpressions['total'] . '), 0) as sale_total
            ')->first();
            $totals['average_price'] = (float) ($totals['total_qty'] ?? 0) > 0
                ? (float) ($totals['sale_total'] ?? 0) / (float) $totals['total_qty']
                : 0;

            $topSellers = (clone $base)
                ->selectRaw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name)), 'unknown') as seller_key")
                ->selectRaw("NULLIF(TRIM(u.username), '') as staff_code")
                ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as staff_name")
                ->selectRaw('COUNT(DISTINCT sor.id) as sale_count')
                ->selectRaw('COUNT(sol.id) as line_count')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['qty'] . '), 0) as total_qty')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['total'] . '), 0) as sale_total')
                ->groupBy(
                    DB::raw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name)), 'unknown')"),
                    DB::raw("NULLIF(TRIM(u.username), '')"),
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown')")
                )
                ->orderByDesc('sale_total')
                ->orderByDesc('sale_count')
                ->limit(10)
                ->get()
                ->values()
                ->map(function ($row, $index) {
                    $row->rank = $index + 1;
                    $row->average_price = (float) $row->total_qty > 0 ? (float) $row->sale_total / (float) $row->total_qty : 0;

                    return $row;
                });

            $trafficRows = (clone $base)
                ->selectRaw($periodExpr . ' as period_label')
                ->selectRaw('COUNT(DISTINCT sor.id) as sale_count')
                ->selectRaw('COUNT(sol.id) as line_count')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['qty'] . '), 0) as total_qty')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['total'] . '), 0) as sale_total')
                ->groupBy(DB::raw($periodExpr))
                ->orderBy('period_label')
                ->get()
                ->map(function ($row) {
                    $row->average_price = (float) $row->total_qty > 0 ? (float) $row->sale_total / (float) $row->total_qty : 0;

                    return $row;
                });

            $summaryQuery = (clone $base)
                ->selectRaw($periodExpr . ' as period_label')
                ->selectRaw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name)), 'unknown') as seller_key")
                ->selectRaw("NULLIF(TRIM(u.username), '') as staff_code")
                ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as staff_name")
                ->selectRaw("COALESCE(NULLIF(TRIM(sor.branch_name), ''), 'Unknown') as branch_name")
                ->selectRaw('COUNT(DISTINCT sor.id) as sale_count')
                ->selectRaw('COUNT(sol.id) as line_count')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['qty'] . '), 0) as total_qty')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['total'] . '), 0) as sale_total')
                ->groupBy(
                    DB::raw($periodExpr),
                    DB::raw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name)), 'unknown')"),
                    DB::raw("NULLIF(TRIM(u.username), '')"),
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown')"),
                    DB::raw("COALESCE(NULLIF(TRIM(sor.branch_name), ''), 'Unknown')")
                )
                ->orderByDesc('period_label')
                ->orderByDesc('sale_total');

            $summaryRows = $summaryQuery->get();

            $summaryCollection = method_exists($summaryRows, 'getCollection') ? $summaryRows->getCollection() : $summaryRows;
            $summaryCollection->transform(function ($row) use ($request, $period) {
                $row->average_price = (float) $row->total_qty > 0 ? (float) $row->sale_total / (float) $row->total_qty : 0;
                $periodLabel = $row->period_label ?? ($request->input('start_date') ?: now()->toDateString());
                $row->period_label = $periodLabel;
                $detailQuery = $request->except(['staff_summary_page', 'staff_lines_page']);
                $detailQuery['show_lines'] = 1;
                $detailQuery['seller_key'] = $row->seller_key;

                if (! empty($row->branch_name) && $row->branch_name !== 'Unknown') {
                    $detailQuery['branch_name'] = $row->branch_name;
                }

                if ($period === 'monthly') {
                    $month = preg_match('/^\d{4}-\d{2}$/', $periodLabel)
                        ? \Carbon\Carbon::createFromFormat('Y-m', $periodLabel)
                        : \Carbon\Carbon::parse($periodLabel);
                    $detailQuery['start_date'] = $month->copy()->startOfMonth()->toDateString();
                    $detailQuery['end_date'] = $month->copy()->endOfMonth()->toDateString();
                } else {
                    $detailQuery['start_date'] = $periodLabel;
                    $detailQuery['end_date'] = $periodLabel;
                }

                $row->detail_url = route('hr-sell.reports.staff', $detailQuery) . '#hr_staff_sell_lines';

                return $row;
            });

            $linePerPage = $this->staffLinePerPage($request);
            $lineRows = $this->emptyRows($request, $linePerPage, 'staff_lines_page');
            if ($request->boolean('show_lines') || ! $paginate) {
                $linesQuery = (clone $base)
                    ->selectRaw($periodExpr . ' as period_label')
                    ->select(
                        'sol.id as line_id',
                        'sor.id as report_id',
                        'sor.invoice_no',
                        'sor.customer_phone',
                        'sor.branch_name',
                        'sor.created_at',
                        'sor.service_type',
                        'sol.product_name',
                        'sol.sku'
                    )
                    ->selectRaw("NULLIF(TRIM(u.username), '') as staff_code")
                    ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as staff_name")
                    ->selectRaw($lineExpressions['serial'] . ' as serial_identifier')
                    ->selectRaw($lineExpressions['qty'] . ' as qty')
                    ->selectRaw($lineExpressions['price'] . ' as unit_price')
                    ->selectRaw($lineExpressions['total'] . ' as line_total')
                    ->orderBy('staff_name')
                    ->orderBy('sor.branch_name')
                    ->orderBy('sor.service_type')
                    ->orderByDesc('sor.created_at')
                    ->orderByDesc('sor.id')
                    ->orderBy('sol.id');

                $lineRows = $paginate
                    ? $linesQuery->paginate($linePerPage, ['*'], 'staff_lines_page')->appends($request->query())
                    : $linesQuery->get();

                $lineCollection = method_exists($lineRows, 'getCollection') ? $lineRows->getCollection() : $lineRows;
                $lineCollection->transform(function ($row) {
                    $row->service_type_label = $this->sellTypeLabel($row->service_type);
                    $row->detail_url = route('hr-sell.sales.pos_detail', [$row->report_id]);

                    return $row;
                });
            }

            return [$summaryRows, $lineRows, $totals, $period, $topSellers, $trafficRows];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR staff sell report data: ' . $e->getMessage());

            return [$this->emptyRows($request, 50, 'staff_summary_page'), $this->emptyRows($request, $this->staffLinePerPage($request), 'staff_lines_page'), $this->emptySummary(), $request->input('period') === 'monthly' ? 'monthly' : 'daily', collect(), collect()];
        }
    }

    private function staffLinePerPage(Request $request): int
    {
        $perPage = (int) $request->input('staff_lines_per_page', 100);
        $allowed = [25, 50, 100, 200, 500];

        return in_array($perPage, $allowed, true) ? $perPage : 100;
    }

    private function commissionReportData(Request $request, bool $paginate): array
    {
        $applyCommissionConditions = $this->applyCommissionConditions($request);
        $commissionColumns = $this->commissionColumns($applyCommissionConditions);
        $period = $this->commissionPeriod($request);
        $periodExpr = $period === 'monthly' ? "DATE_FORMAT(sor.created_at, '%Y-%m')" : 'DATE(sor.created_at)';

        try {
            $lineExpressions = $this->hrLineAmountExpressions();
            $base = $this->baseStaffLineQuery($request, $periodExpr, $lineExpressions)
                ->whereIn('sor.service_type', $this->commissionServiceTypeValues($commissionColumns));

            if ($applyCommissionConditions) {
                $base->whereNotNull('sor.customer_phone')
                    ->whereRaw('TRIM(sor.customer_phone) != ""');
            }

            $totalsQuery = (clone $base)
                ->selectRaw('COUNT(DISTINCT sor.id) as sale_count')
                ->selectRaw('COUNT(sol.id) as line_count')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['qty'] . '), 0) as total_qty')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['total'] . '), 0) as total_amount');

            $totals = (array) $totalsQuery->first();

            $rowsQuery = (clone $base)
                ->selectRaw($periodExpr . ' as sale_period')
                ->selectRaw('sor.user_id as user_id')
                ->selectRaw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name)), 'unknown') as seller_key")
                ->selectRaw("NULLIF(TRIM(u.username), '') as staff_code")
                ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as staff_name")
                ->selectRaw("COALESCE(NULLIF(TRIM(sor.branch_name), ''), 'Unknown') as branch_name")
                ->selectRaw($this->officeMinutesExpression() . ' as office_minutes')
                ->selectRaw('COUNT(DISTINCT sor.id) as sale_count')
                ->selectRaw('COUNT(sol.id) as line_count')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['qty'] . '), 0) as total_qty')
                ->selectRaw('COALESCE(SUM(' . $lineExpressions['total'] . '), 0) as total_amount');

            foreach ($commissionColumns as $column) {
                if ($column['has_commission']) {
                    $baseExpr = $this->commissionBaseExpression($column, $lineExpressions);
                    $rawExpr = $this->commissionRawExpression($column, $lineExpressions);

                    $rowsQuery
                        ->selectRaw(
                            $rawExpr . ' as ' . $column['key'] . '_raw_total',
                            $column['values']
                        )
                        ->selectRaw(
                            $baseExpr . ' as ' . $column['key'] . '_total',
                            $this->commissionBaseExpressionBindings($column)
                        )
                        ->selectRaw(
                            '(' . $baseExpr . ' * ' . $column['commission_rate'] . ') as ' . $column['key'] . '_commission_total',
                            $this->commissionBaseExpressionBindings($column)
                        );
                } else {
                    $rowsQuery->selectRaw(
                        'COALESCE(SUM(CASE WHEN sor.service_type IN (' . $this->placeholders($column['values']) . ') THEN ' . $lineExpressions['total'] . ' ELSE 0 END), 0) as ' . $column['key'] . '_total',
                        $column['values']
                    );
                }
            }

            $rowsQuery
                ->groupBy(
                    DB::raw($periodExpr),
                    DB::raw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name)), 'unknown')"),
                    'sor.user_id',
                    DB::raw("NULLIF(TRIM(u.username), '')"),
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown')"),
                    DB::raw("COALESCE(NULLIF(TRIM(sor.branch_name), ''), 'Unknown')")
                )
                ->havingRaw($this->commissionHavingExpression($commissionColumns, $lineExpressions), $this->commissionExpressionBindings($commissionColumns))
                ->orderBy('sale_period')
                ->orderBy('staff_name')
                ->orderBy('branch_name');

            $commissionTotalRows = (clone $rowsQuery)->get();
            foreach ($commissionColumns as $column) {
                $totals[$column['key'] . '_total'] = $commissionTotalRows->sum(fn ($row) => (float) ($row->{$column['key'] . '_total'} ?? 0));

                if ($column['has_commission']) {
                    $totals[$column['key'] . '_raw_total'] = $commissionTotalRows->sum(fn ($row) => (float) ($row->{$column['key'] . '_raw_total'} ?? 0));
                    $totals[$column['key'] . '_commission_total'] = $commissionTotalRows->sum(fn ($row) => (float) ($row->{$column['key'] . '_commission_total'} ?? 0));
                }
            }
            $totals['commission_total'] = $this->sumCommissionTotals((object) $totals, $commissionColumns);

            $commissionPerPage = $this->commissionPerPage($request);
            if ($paginate && $commissionPerPage === 'all') {
                $allRows = $rowsQuery->get();
                $rows = new LengthAwarePaginator($allRows, $allRows->count(), max($allRows->count(), 1), 1, [
                    'path' => $request->url(),
                    'pageName' => 'commission_page',
                    'query' => $request->query(),
                ]);
            } else {
                $rows = $paginate
                    ? $rowsQuery->paginate($commissionPerPage, ['*'], 'commission_page')->appends($request->query())
                    : $rowsQuery->get();
            }

            $rowCollection = method_exists($rows, 'getCollection') ? $rows->getCollection() : $rows;
            $officeTimes = $this->officeTimesByUser($rowCollection, $request, $period);
            $rowCollection->transform(function ($row) use ($request, $commissionColumns, $officeTimes, $period) {
                $row->commission_total = $this->sumCommissionTotals($row, $commissionColumns);
                $row->detail_urls = $this->commissionDetailUrls($row, $request, $commissionColumns);
                $fallbackMinutes = max(0, (int) ($row->office_minutes ?? 0));
                $officeTime = $officeTimes[$this->officeTimeKey($row, $period)] ?? null;
                $officeMinutes = (int) ($officeTime['minutes'] ?? $fallbackMinutes);
                $row->office_time = ! empty($officeTime['time']) ? $officeTime['time'] : '-';
                $row->office_time_range = ! empty($officeTime['range']) ? $officeTime['range'] : '-';
                $row->total_hour_day = ! empty($officeTime['hours']) ? $officeTime['hours'] : $this->formatTotalHourDay($fallbackMinutes);
                $row->time_work = $officeMinutes >= 480 ? 'Full time' : 'Part-time';

                return $row;
            });

            return [$rows, $totals, $commissionColumns, $period];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR commission report data: ' . $e->getMessage());

            return [$this->emptyRows($request, $this->commissionPerPage($request) === 'all' ? 1 : $this->commissionPerPage($request), 'commission_page'), $this->emptyCommissionSummary($commissionColumns), $commissionColumns, $period];
        }
    }

    private function commissionPeriod(Request $request): string
    {
        return $request->input('commission_period') === 'monthly' ? 'monthly' : 'daily';
    }

    private function commissionPerPage(Request $request)
    {
        $perPage = (string) $request->input('commission_per_page', '50');
        $allowed = ['25', '50', '100', '200', '500', 'all'];

        return in_array($perPage, $allowed, true) ? ($perPage === 'all' ? 'all' : (int) $perPage) : 50;
    }

    private function formatTotalHourDay(int $minutes): string
    {
        return intdiv($minutes, 60) . ' hour';
    }

    private function officeTimesByUser($rows, Request $request, string $period): array
    {
        if (! Schema::connection('hr')->hasTable('essentials_user_shifts') || ! Schema::connection('hr')->hasTable('essentials_shifts')) {
            return [];
        }

        $userIds = collect($rows)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $saleDates = collect($rows)
            ->pluck('sale_period')
            ->filter()
            ->values();

        if ($saleDates->isEmpty()) {
            return [];
        }

        $startDate = $period === 'monthly' ? $saleDates->min() . '-01' : $saleDates->min();
        $endDate = $period === 'monthly'
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $saleDates->max() . '-01')->endOfMonth()->toDateString()
            : $saleDates->max();
        $durationExpression = "CASE WHEN es.start_time IS NOT NULL AND es.end_time IS NOT NULL THEN CASE WHEN TIME_TO_SEC(es.end_time) >= TIME_TO_SEC(es.start_time) THEN FLOOR((TIME_TO_SEC(es.end_time) - TIME_TO_SEC(es.start_time)) / 60) ELSE FLOOR((TIME_TO_SEC(es.end_time) + 86400 - TIME_TO_SEC(es.start_time)) / 60) END ELSE NULL END";

        $assignmentsByUser = DB::connection('hr')
            ->table('essentials_user_shifts as eus')
            ->join('essentials_shifts as es', 'es.id', '=', 'eus.essentials_shift_id')
            ->select('eus.user_id', 'eus.start_date', 'eus.end_date')
            ->selectRaw("NULLIF(TRIM(es.name), '') as office_time_name")
            ->selectRaw("CASE WHEN es.start_time IS NOT NULL AND es.end_time IS NOT NULL THEN CONCAT(DATE_FORMAT(es.start_time, '%l:%i%p'), '-', DATE_FORMAT(es.end_time, '%l:%i%p')) ELSE NULL END as office_time_range")
            ->selectRaw("COALESCE({$durationExpression}, 0) as office_minutes")
            ->whereIn('eus.user_id', $userIds)
            ->where('eus.start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('eus.end_date')
                    ->orWhere('eus.end_date', '>=', $startDate);
            })
            ->get()
            ->groupBy(fn ($assignment) => (int) $assignment->user_id);

        return collect($rows)
            ->mapWithKeys(function ($row) use ($assignmentsByUser, $period) {
                $userId = (int) ($row->user_id ?? 0);
                [$periodStartDate, $periodEndDate] = $this->officeTimePeriodBounds($row->sale_period ?? null, $period);

                if ($userId <= 0 || empty($periodStartDate) || empty($periodEndDate)) {
                    return [];
                }

                $matches = ($assignmentsByUser[$userId] ?? collect())
                    ->filter(fn ($assignment) => $assignment->start_date <= $periodEndDate && (empty($assignment->end_date) || $assignment->end_date >= $periodStartDate));

                if ($matches->isEmpty()) {
                    return [];
                }

                $time = $matches->pluck('office_time_name')->filter()->unique()->implode(', ');
                $range = $matches->pluck('office_time_range')->filter()->unique()->implode(', ');
                $hours = $matches
                    ->pluck('office_minutes')
                    ->filter(fn ($minutes) => (int) $minutes > 0)
                    ->map(fn ($minutes) => $this->formatTotalHourDay((int) $minutes))
                    ->unique()
                    ->implode(', ');

                return [$this->officeTimeKey($row, $period) => [
                    'time' => $time,
                    'range' => $range,
                    'hours' => $hours,
                    'minutes' => (int) $matches->max('office_minutes'),
                ]];
            })
            ->all();
    }

    private function officeTimeKey(object $row, string $period): string
    {
        return (int) ($row->user_id ?? 0) . '|' . $period . '|' . ($row->sale_period ?? '');
    }

    private function officeTimePeriodBounds(?string $salePeriod, string $period): array
    {
        if (empty($salePeriod)) {
            return [null, null];
        }

        if ($period === 'monthly') {
            $month = \Carbon\Carbon::createFromFormat('Y-m-d', $salePeriod . '-01');

            return [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        }

        return [$salePeriod, $salePeriod];
    }

    private function applyCommissionConditions(Request $request): bool
    {
        return $request->input('commission_condition_mode', 'with_condition') !== 'no_condition';
    }

    private function commissionColumns(bool $applyConditions = true): array
    {
        return collect(['iron', 'material', 'repair', 'sell'])
            ->map(function ($key) use ($applyConditions) {
                return [
                    'key' => $key,
                    'label' => $this->sellTypeLabel($key),
                    'short_label' => [
                        'iron' => 'Iron',
                        'material' => 'Mat.',
                        'repair' => 'Repair',
                        'sell' => 'Sell',
                    ][$key] ?? $this->sellTypeLabel($key),
                    'values' => $this->sellTypeValues($key),
                    'has_commission' => in_array($key, ['iron', 'material', 'repair', 'sell'], true),
                    'commission_basis' => $key === 'sell' ? 'qty' : 'invoice',
                    'commission_rate' => in_array($key, ['material', 'sell'], true) ? 0.25 : 0.20,
                    'minimum_invoice_total' => $applyConditions && $key === 'material' ? 10 : null,
                    'sell_qty_thresholds' => $applyConditions && $key === 'sell' ? ['part_time' => 50, 'full_time' => 100] : null,
                ];
            })
            ->all();
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    private function commissionServiceTypeValues(array $commissionColumns): array
    {
        return collect($commissionColumns)
            ->filter(fn ($column) => $column['has_commission'])
            ->flatMap(fn ($column) => $column['values'])
            ->unique()
            ->values()
            ->all();
    }

    private function commissionBaseExpression(array $column, array $lineExpressions): string
    {
        $condition = 'sor.service_type IN (' . $this->placeholders($column['values']) . ')';

        if (! empty($column['minimum_invoice_total'])) {
            $condition .= ' AND ' . $this->invoiceLineTotalExpression() . ' >= ' . (float) $column['minimum_invoice_total'];
        }

        if ($column['commission_basis'] === 'qty') {
            if (! empty($column['sell_qty_thresholds'])) {
                $qtyExpression = 'COALESCE(SUM(CASE WHEN ' . $condition . ' THEN ' . $lineExpressions['qty'] . ' ELSE 0 END), 0)';
                $fullTimeMinimum = (float) $column['sell_qty_thresholds']['full_time'];
                $partTimeMinimum = (float) $column['sell_qty_thresholds']['part_time'];

                return 'CASE WHEN ' . $this->officeMinutesExpression() . ' >= 480 '
                    . 'THEN CASE WHEN ' . $qtyExpression . ' >= ' . $fullTimeMinimum . ' THEN ' . $qtyExpression . ' ELSE 0 END '
                    . 'ELSE CASE WHEN ' . $qtyExpression . ' >= ' . $partTimeMinimum . ' THEN ' . $qtyExpression . ' ELSE 0 END END';
            }

            return 'COALESCE(SUM(CASE WHEN ' . $condition . ' THEN ' . $lineExpressions['qty'] . ' ELSE 0 END), 0)';
        }

        return 'COUNT(DISTINCT CASE WHEN ' . $condition . ' THEN sor.id END)';
    }

    private function commissionBaseExpressionBindings(array $column): array
    {
        $repeat = ! empty($column['sell_qty_thresholds']) ? 4 : 1;

        return collect(range(1, $repeat))
            ->flatMap(fn () => $column['values'])
            ->values()
            ->all();
    }

    private function commissionRawExpression(array $column, array $lineExpressions): string
    {
        $condition = 'sor.service_type IN (' . $this->placeholders($column['values']) . ')';

        if ($column['commission_basis'] === 'qty') {
            return 'COALESCE(SUM(CASE WHEN ' . $condition . ' THEN ' . $lineExpressions['qty'] . ' ELSE 0 END), 0)';
        }

        return 'COUNT(DISTINCT CASE WHEN ' . $condition . ' THEN sor.id END)';
    }

    private function invoiceLineTotalExpression(): string
    {
        $lineExpressions = $this->hrLineAmountExpressions('sol_invoice_total');

        return '(SELECT COALESCE(SUM(' . $lineExpressions['total'] . '), 0) FROM sell_out_report_lines as sol_invoice_total WHERE sol_invoice_total.sell_out_report_id = sor.id)';
    }

    private function officeMinutesExpression(): string
    {
        return 'TIMESTAMPDIFF(MINUTE, MIN(sor.created_at), MAX(sor.created_at))';
    }

    private function commissionHavingExpression(array $commissionColumns, array $lineExpressions): string
    {
        $parts = collect($commissionColumns)
            ->filter(fn ($column) => $column['has_commission'])
            ->map(fn ($column) => '(' . $this->commissionBaseExpression($column, $lineExpressions) . ' * ' . $column['commission_rate'] . ')')
            ->all();

        return '(' . implode(' + ', $parts) . ') > 0';
    }

    private function commissionExpressionBindings(array $commissionColumns): array
    {
        return collect($commissionColumns)
            ->filter(fn ($column) => $column['has_commission'])
            ->flatMap(fn ($column) => $this->commissionBaseExpressionBindings($column))
            ->values()
            ->all();
    }

    private function sumCommissionTotals(object $row, array $commissionColumns): float
    {
        return collect($commissionColumns)
            ->filter(fn ($column) => $column['has_commission'])
            ->sum(fn ($column) => (float) ($row->{$column['key'] . '_commission_total'} ?? 0));
    }

    private function commissionDetailUrls(object $row, Request $request, array $commissionColumns): array
    {
        $baseQuery = $request->except(['commission_page', 'staff_summary_page', 'staff_lines_page']);

        return collect($commissionColumns)
            ->mapWithKeys(function ($column) use ($baseQuery, $row) {
                $detailQuery = array_merge($baseQuery, [
                    'seller_key' => $row->seller_key,
                    'sell_type' => $column['key'],
                    'show_lines' => 1,
                ]);

                if (! empty($row->branch_name) && $row->branch_name !== 'Unknown') {
                    $detailQuery['branch_name'] = $row->branch_name;
                } else {
                    unset($detailQuery['branch_name']);
                }

                if (! empty($row->sale_period)) {
                    if (preg_match('/^\d{4}-\d{2}$/', $row->sale_period)) {
                        $month = \Carbon\Carbon::createFromFormat('Y-m-d', $row->sale_period . '-01');
                        $detailQuery['start_date'] = $month->copy()->startOfMonth()->toDateString();
                        $detailQuery['end_date'] = $month->copy()->endOfMonth()->toDateString();
                    } else {
                        $detailQuery['start_date'] = $row->sale_period;
                        $detailQuery['end_date'] = $row->sale_period;
                    }
                }

                return [$column['key'] => route('hr-sell.reports.staff', $detailQuery) . '#hr_staff_sell_lines'];
            })
            ->all();
    }

    private function baseStaffLineQuery(Request $request, string $periodExpr, array $lineExpressions)
    {
        return DB::connection('hr')
            ->table('sell_out_reports as sor')
            ->join('sell_out_report_lines as sol', 'sol.sell_out_report_id', '=', 'sor.id')
            ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
            ->when($request->filled('start_date'), fn ($q) => $q->where('sor.created_at', '>=', $request->input('start_date') . ' 00:00:00'))
            ->when($request->filled('end_date'), fn ($q) => $q->where('sor.created_at', '<=', $request->input('end_date') . ' 23:59:59'))
            ->when($this->selectedBranchNames($request), fn ($q) => $q->whereIn(DB::raw('TRIM(sor.branch_name)'), $this->selectedBranchNames($request)))
            ->when($request->filled('sell_type'), function ($q) use ($request) {
                $q->whereIn('sor.service_type', $this->sellTypeValues($request->input('sell_type')));
            })
            ->when($this->selectedDepartmentIds($request) && $this->canFilterByDepartment(), function ($q) use ($request) {
                $q->whereIn('u.department_id', $this->selectedDepartmentIds($request));
            })
            ->when($request->filled('seller_key'), function ($q) use ($request) {
                $sellerKey = $request->input('seller_key');
                if (str_starts_with($sellerKey, 'seller:')) {
                    $q->whereRaw('TRIM(sor.seller_name) = ?', [substr($sellerKey, 7)]);
                } elseif ($this->looksLikeUsername($sellerKey)) {
                    $q->whereRaw('TRIM(u.username) = ?', [$sellerKey]);
                } else {
                    $q->where('sor.user_id', $sellerKey);
                }
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('sor.invoice_no', 'like', $search)
                        ->orWhere('sor.customer_phone', 'like', $search)
                        ->orWhere('sor.customer_name', 'like', $search)
                        ->orWhere('sor.seller_name', 'like', $search)
                        ->orWhere('u.username', 'like', $search)
                        ->orWhere('u.name', 'like', $search)
                        ->orWhere('sol.product_name', 'like', $search)
                        ->orWhere('sol.sku', 'like', $search)
                        ->orWhere('sol.serial_number', 'like', $search)
                        ->orWhere('sol.imei', 'like', $search)
                        ->orWhere('sol.imei2', 'like', $search)
                        ->orWhere('sol.primary_identifier', 'like', $search);
                });
            });
    }

    private function hrLineAmountExpressions(string $lineAlias = 'sol'): array
    {
        $qtyColumn = $this->firstHrLineColumn(['qty', 'quantity', 'sale_qty', 'product_qty']);
        $priceColumn = $this->firstHrLineColumn(['unit_price', 'price', 'sell_price', 'selling_price', 'product_price']);
        $totalColumn = $this->firstHrLineColumn(['line_total', 'total', 'total_amount', 'subtotal', 'amount']);

        $qtyExpr = $qtyColumn ? 'COALESCE(' . $lineAlias . '.' . $qtyColumn . ', 0)' : '1';
        $reportLineCountExpr = "(SELECT COUNT(*) FROM sell_out_report_lines as sol_count WHERE sol_count.sell_out_report_id = sor.id)";
        $totalExpr = $totalColumn
            ? 'COALESCE(' . $lineAlias . '.' . $totalColumn . ', 0)'
            : ($priceColumn ? '(' . $qtyExpr . ' * COALESCE(' . $lineAlias . '.' . $priceColumn . ', 0))' : '(COALESCE(sor.total_amount, 0) / NULLIF(' . $reportLineCountExpr . ', 0))');
        $priceExpr = $priceColumn
            ? 'COALESCE(' . $lineAlias . '.' . $priceColumn . ', 0)'
            : ($totalColumn ? '(' . $totalExpr . ' / NULLIF(' . $qtyExpr . ', 0))' : '0');

        return [
            'qty' => $qtyExpr,
            'price' => $priceExpr,
            'total' => $totalExpr,
            'serial' => "NULLIF(TRIM(CONCAT_WS(' / ', NULLIF(" . $lineAlias . ".serial_number, ''), NULLIF(" . $lineAlias . ".imei, ''), NULLIF(" . $lineAlias . ".imei2, ''), NULLIF(" . $lineAlias . ".primary_identifier, ''))), '')",
        ];
    }

    private function firstHrLineColumn(array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::connection('hr')->hasColumn('sell_out_report_lines', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function withDefaultStaffReportDates(Request $request): Request
    {
        if ($request->filled('start_date') || $request->filled('end_date')) {
            return $request;
        }

        $period = $request->input('period') === 'monthly' ? 'monthly' : 'daily';
        $request->merge([
            'start_date' => $period === 'monthly' ? now()->startOfMonth()->toDateString() : now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        return $request;
    }

    private function selectReportRows($query)
    {
        return $query->select(
            'sor.id',
            'sor.invoice_no',
            'sor.original_invoice_no',
            'sor.customer_phone',
            'sor.customer_name',
            'sor.seller_name',
            'sor.branch_name',
            'sor.total_amount',
            'sor.created_at',
            'sor.service_type',
            'sor.note',
            'u.username as staff_code',
            DB::raw('COALESCE(u.name, sor.seller_name) as staff_name'),
            DB::raw("(SELECT COALESCE(SUM(sol_qty.qty), 0) FROM sell_out_report_lines as sol_qty WHERE sol_qty.sell_out_report_id = sor.id) as total_qty"),
            DB::raw("(SELECT COUNT(*) FROM sell_out_report_lines as sol_count WHERE sol_count.sell_out_report_id = sor.id) as line_count"),
            DB::raw("(SELECT GROUP_CONCAT(NULLIF(TRIM(sol.product_name), '') ORDER BY sol.id SEPARATOR '|||') FROM sell_out_report_lines as sol WHERE sol.sell_out_report_id = sor.id) as product_names"),
            DB::raw("(SELECT GROUP_CONCAT(NULLIF(TRIM(CONCAT_WS(' / ', NULLIF(sol_serial.serial_number, ''), NULLIF(sol_serial.imei, ''), NULLIF(sol_serial.imei2, ''), NULLIF(sol_serial.primary_identifier, ''))), '') ORDER BY sol_serial.id SEPARATOR '|||') FROM sell_out_report_lines as sol_serial WHERE sol_serial.sell_out_report_id = sor.id) as serial_numbers")
        );
    }

    private function baseReportQuery(Request $request)
    {
        return DB::connection('hr')
            ->table('sell_out_reports as sor')
            ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
            ->when($request->filled('start_date'), fn ($q) => $q->where('sor.created_at', '>=', $request->input('start_date') . ' 00:00:00'))
            ->when($request->filled('end_date'), fn ($q) => $q->where('sor.created_at', '<=', $request->input('end_date') . ' 23:59:59'))
            ->when($this->selectedBranchNames($request), fn ($q) => $q->whereIn(DB::raw('TRIM(sor.branch_name)'), $this->selectedBranchNames($request)))
            ->when($request->filled('sell_type'), function ($q) use ($request) {
                $sellType = $request->input('sell_type');
                $q->whereIn('sor.service_type', $this->sellTypeValues($sellType));
            })
            ->when($this->selectedDepartmentIds($request) && $this->canFilterByDepartment(), function ($q) use ($request) {
                $q->whereIn('u.department_id', $this->selectedDepartmentIds($request));
            })
            ->when($request->filled('seller_key'), function ($q) use ($request) {
                $sellerKey = $request->input('seller_key');
                if (str_starts_with($sellerKey, 'seller:')) {
                    $q->whereRaw('TRIM(sor.seller_name) = ?', [substr($sellerKey, 7)]);
                } elseif ($this->looksLikeUsername($sellerKey)) {
                    $q->whereRaw('TRIM(u.username) = ?', [$sellerKey]);
                } else {
                    $q->where('sor.user_id', $sellerKey);
                }
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('sor.invoice_no', 'like', $search)
                        ->orWhere('sor.original_invoice_no', 'like', $search)
                        ->orWhere('sor.customer_phone', 'like', $search)
                        ->orWhere('sor.customer_name', 'like', $search)
                        ->orWhere('sor.seller_name', 'like', $search)
                        ->orWhere('sor.note', 'like', $search)
                        ->orWhere('u.username', 'like', $search)
                        ->orWhere('u.name', 'like', $search)
                        ->orWhereExists(function ($lineQuery) use ($search) {
                            $lineQuery->select(DB::raw(1))
                                ->from('sell_out_report_lines as sol_search')
                                ->whereColumn('sol_search.sell_out_report_id', 'sor.id')
                                ->where(function ($lineSearch) use ($search) {
                                    $lineSearch->where('sol_search.product_name', 'like', $search)
                                        ->orWhere('sol_search.sku', 'like', $search)
                                        ->orWhere('sol_search.serial_number', 'like', $search)
                                        ->orWhere('sol_search.imei', 'like', $search)
                                        ->orWhere('sol_search.imei2', 'like', $search)
                                        ->orWhere('sol_search.primary_identifier', 'like', $search);
                                });
                        });
                });
            });
    }

    private function filterOptions(?Request $request = null): array
    {
        try {
            $branches = DB::connection('hr')
                ->table('sell_out_reports')
                ->selectRaw('DISTINCT TRIM(branch_name) as branch_name')
                ->whereNotNull('branch_name')
                ->where('branch_name', '!=', '')
                ->orderBy('branch_name')
                ->pluck('branch_name', 'branch_name');

            $sellTypes = DB::connection('hr')
                ->table('sell_out_reports')
                ->select('service_type')
                ->distinct()
                ->whereNotNull('service_type')
                ->where('service_type', '!=', '')
                ->orderBy('service_type')
                ->pluck('service_type')
                ->mapWithKeys(fn ($type) => [$this->normalizeSellTypeKey($type) => $this->sellTypeLabel($type)])
                ->unique();

            $sellers = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->selectRaw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name))) as seller_key")
                ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as seller_name")
                ->selectRaw("NULLIF(TRIM(u.username), '') as username")
                ->where(function ($q) {
                    $q->whereNotNull('sor.user_id')
                        ->orWhere(function ($query) {
                            $query->whereNotNull('sor.seller_name')->where('sor.seller_name', '!=', '');
                        });
                })
                ->groupBy('seller_key', 'seller_name', 'username')
                ->orderBy('seller_name')
                ->get()
                ->mapWithKeys(function ($seller) {
                    $label = trim(($seller->username ? $seller->username . ' - ' : '') . $seller->seller_name);

                    return [$seller->seller_key => $label ?: 'Unknown'];
                });

            $departments = $this->hrDepartments($request ? $this->selectedBranchNames($request) : []);

            return [$branches, $sellTypes, $sellers, $departments];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sell report filters: ' . $e->getMessage());

            return [collect(), collect(), collect(), collect()];
        }
    }

    private function hrDepartments(array $branchNames = [])
    {
        $labelColumn = $this->departmentLabelColumn();

        if (! $this->canFilterByDepartment() || empty($labelColumn)) {
            return collect();
        }

        $query = DB::connection('hr')
            ->table('departments')
            ->when(! empty($branchNames), function ($q) use ($branchNames) {
                $q->whereExists(function ($exists) use ($branchNames) {
                    $exists->select(DB::raw(1))
                        ->from('users as department_users')
                        ->join('sell_out_reports as department_reports', 'department_reports.user_id', '=', 'department_users.id')
                        ->whereColumn('department_users.department_id', 'departments.id')
                        ->whereIn(DB::raw('TRIM(department_reports.branch_name)'), $branchNames);
                });
            })
            ->select('id', $labelColumn)
            ->whereNotNull($labelColumn)
            ->where($labelColumn, '!=', '')
            ->orderBy($labelColumn);

        return $query->pluck($labelColumn, 'id');
    }

    private function canFilterByDepartment(): bool
    {
        static $canFilter;

        if ($canFilter !== null) {
            return $canFilter;
        }

        try {
            $schema = Schema::connection('hr');

            $canFilter = $schema->hasTable('departments')
                && $schema->hasTable('users')
                && $schema->hasColumn('users', 'department_id')
                && ! empty($this->departmentLabelColumn());
        } catch (\Throwable $e) {
            $canFilter = false;
        }

        return $canFilter;
    }

    private function selectedDepartmentIds(Request $request): array
    {
        return collect((array) $request->input('department_id', []))
            ->filter(fn ($departmentId) => $departmentId !== null && $departmentId !== '')
            ->map(fn ($departmentId) => (string) $departmentId)
            ->values()
            ->all();
    }

    private function selectedBranchNames(Request $request): array
    {
        return collect((array) $request->input('branch_name', []))
            ->filter(fn ($branchName) => $branchName !== null && trim((string) $branchName) !== '')
            ->map(fn ($branchName) => trim((string) $branchName))
            ->values()
            ->all();
    }

    private function departmentLabelColumn(): ?string
    {
        static $labelColumn;

        if ($labelColumn !== null) {
            return $labelColumn;
        }

        try {
            $schema = Schema::connection('hr');

            foreach (['dept_name', 'name', 'department_name', 'title'] as $column) {
                if ($schema->hasColumn('departments', $column)) {
                    return $labelColumn = $column;
                }
            }
        } catch (\Throwable $e) {
            return $labelColumn = '';
        }

        return $labelColumn = '';
    }

    private function sellTypeMap(): array
    {
        return [
            'sell' => ['label' => 'Sell / លក់', 'values' => ['sell', 'Sell', 'លក់', 'Sell / លក់', 'Sell/លក់']],
            'buy_in' => ['label' => 'Buy In / ទិញចូល', 'values' => ['buy in', 'Buy In', 'buy_in', 'buyin', 'ទិញចូល', 'Buy In / ទិញចូល', 'Buy In/ទិញចូល', 'ទិញចូល / Buy In', 'ទិញចូល/Buy In']],
            'repair' => ['label' => 'Repair / ជួសជុល', 'values' => ['repair', 'Repair', 'ជួសជុល', 'Repair / ជួសជុល', 'Repair/ជួសជុល', 'ជួសជុល / Repair', 'ជួសជុល/Repair']],
            'material' => ['label' => 'Material / សម្ភារ', 'values' => ['material', 'Material', 'materials', 'Materials', 'សម្ភារ', 'Material / សម្ភារ', 'Material/សម្ភារ', 'សម្ភារ / Material', 'សម្ភារ/Material']],
            'iron' => ['label' => 'Iron / អ៊ុត', 'values' => ['iron', 'Iron', 'អ៊ុត', 'Iron / អ៊ុត', 'Iron/អ៊ុត', 'អ៊ុត / Iron', 'អ៊ុត/Iron', 'Scots', 'scots']],
            'icloud_cus' => ['label' => 'iCloud Cus', 'values' => ['icloud cus', 'iCloud Cus', 'icloud_cus', 'icloudcus']],
        ];
    }

    private function normalizeSellTypeKey(?string $type): string
    {
        $normalized = mb_strtolower(trim((string) $type));

        foreach ($this->sellTypeMap() as $key => $config) {
            if (in_array($normalized, array_map(fn ($value) => mb_strtolower($value), $config['values']), true)) {
                return $key;
            }
        }

        return trim((string) $type);
    }

    private function sellTypeLabel(?string $type): string
    {
        $key = $this->normalizeSellTypeKey($type);
        $map = $this->sellTypeMap();

        return $map[$key]['label'] ?? ($type ?: '-');
    }

    private function sellTypeValues($type): array
    {
        if (is_array($type)) {
            return collect($type)
                ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
                ->flatMap(fn ($value) => $this->sellTypeValues((string) $value))
                ->unique()
                ->values()
                ->all();
        }

        $key = $this->normalizeSellTypeKey($type);
        $map = $this->sellTypeMap();

        return $map[$key]['values'] ?? [$type];
    }

    private function looksLikeUsername(string $sellerKey): bool
    {
        return (bool) preg_match('/^[A-Za-z]+[A-Za-z0-9_-]*\d+[A-Za-z0-9_-]*$/', $sellerKey);
    }

    private function emptyRows(Request $request, int $perPage = 50, string $pageName = 'hr_report_page'): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, (int) $request->input($pageName, 1), [
            'path' => $request->url(),
            'pageName' => $pageName,
            'query' => $request->query(),
        ]);
    }

    private function emptySummary(): array
    {
        return [
            'sale_count' => 0,
            'customer_count' => 0,
            'sale_total' => 0,
            'average_sale' => 0,
            'total_qty' => 0,
            'line_count' => 0,
            'average_price' => 0,
        ];
    }

    private function emptyCommissionSummary(array $commissionColumns): array
    {
        $summary = [
            'sale_count' => 0,
            'line_count' => 0,
            'total_qty' => 0,
            'total_amount' => 0,
            'commission_total' => 0,
        ];

        foreach ($commissionColumns as $column) {
            $summary[$column['key'] . '_raw_total'] = 0;
            $summary[$column['key'] . '_total'] = 0;

            if ($column['has_commission']) {
                $summary[$column['key'] . '_commission_total'] = 0;
            }
        }

        return $summary;
    }

    private function findReport($report_id)
    {
        $report = DB::connection('hr')
            ->table('sell_out_reports')
            ->where('id', $report_id)
            ->first();

        abort_if(empty($report), 404);

        return $report;
    }

    private function logReportAction(string $action, int $reportId, $oldData = null, $newData = null, ?Request $request = null): void
    {
        try {
            DB::table('hr_sell_logs')->insert([
                'business_id' => (int) session('user.business_id'),
                'hr_sell_record_id' => null,
                'action' => $action,
                'user_id' => auth()->id(),
                'user_name' => optional(auth()->user())->username ?: optional(auth()->user())->name,
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => $newData ? json_encode($newData) : null,
                'ip_address' => $request?->ip(),
                'note' => 'HR sell report ID: ' . $reportId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Unable to write HR Sell report log: ' . $e->getMessage());
        }
    }

    private function canReport(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.report') || $user->can('superadmin') || $user->can('business_settings.access');
    }

    private function canEditReport(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.report.edit') || $user->can('hr_sell.update') || $user->can('superadmin') || $user->can('business_settings.access');
    }

    private function canDeleteReport(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.report.delete') || $user->can('superadmin') || $user->can('business_settings.access');
    }
}
