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
        [$hrBranches, $hrSellTypes, $hrSellers, $hrDepartments] = $this->filterOptions();

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
        [$hrBranches, $hrSellTypes, $hrSellers, $hrDepartments] = $this->filterOptions();

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

            $rows = $paginate
                ? $rowsQuery->paginate(50, ['*'], 'hr_report_page')->appends($request->query())
                : $rowsQuery->get();

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

            $lineRows = $this->emptyRows($request, 100, 'staff_lines_page');
            if ($request->boolean('show_lines') || ! $paginate) {
                $linesQuery = (clone $base)
                    ->selectRaw($periodExpr . ' as period_label')
                    ->select(
                        'sor.id as report_id',
                        'sor.invoice_no',
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
                    ->orderByDesc('sor.created_at')
                    ->orderByDesc('sor.id')
                    ->orderBy('sol.id');

                $lineRows = $paginate
                    ? $linesQuery->paginate(100, ['*'], 'staff_lines_page')->appends($request->query())
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

            return [$this->emptyRows($request, 50, 'staff_summary_page'), $this->emptyRows($request, 100, 'staff_lines_page'), $this->emptySummary(), $request->input('period') === 'monthly' ? 'monthly' : 'daily', collect(), collect()];
        }
    }

    private function baseStaffLineQuery(Request $request, string $periodExpr, array $lineExpressions)
    {
        return DB::connection('hr')
            ->table('sell_out_reports as sor')
            ->join('sell_out_report_lines as sol', 'sol.sell_out_report_id', '=', 'sor.id')
            ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
            ->when($request->filled('start_date'), fn ($q) => $q->where('sor.created_at', '>=', $request->input('start_date') . ' 00:00:00'))
            ->when($request->filled('end_date'), fn ($q) => $q->where('sor.created_at', '<=', $request->input('end_date') . ' 23:59:59'))
            ->when($request->filled('branch_name'), fn ($q) => $q->whereRaw('TRIM(sor.branch_name) = ?', [$request->input('branch_name')]))
            ->when($request->filled('sell_type'), function ($q) use ($request) {
                $q->whereIn('sor.service_type', $this->sellTypeValues($request->input('sell_type')));
            })
            ->when($request->filled('department_id') && $this->canFilterByDepartment(), function ($q) use ($request) {
                $q->where('u.department_id', $request->input('department_id'));
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

    private function hrLineAmountExpressions(): array
    {
        $qtyColumn = $this->firstHrLineColumn(['qty', 'quantity', 'sale_qty', 'product_qty']);
        $priceColumn = $this->firstHrLineColumn(['unit_price', 'price', 'sell_price', 'selling_price', 'product_price']);
        $totalColumn = $this->firstHrLineColumn(['line_total', 'total', 'total_amount', 'subtotal', 'amount']);

        $qtyExpr = $qtyColumn ? 'COALESCE(sol.' . $qtyColumn . ', 0)' : '1';
        $reportLineCountExpr = "(SELECT COUNT(*) FROM sell_out_report_lines as sol_count WHERE sol_count.sell_out_report_id = sor.id)";
        $totalExpr = $totalColumn
            ? 'COALESCE(sol.' . $totalColumn . ', 0)'
            : ($priceColumn ? '(' . $qtyExpr . ' * COALESCE(sol.' . $priceColumn . ', 0))' : '(COALESCE(sor.total_amount, 0) / NULLIF(' . $reportLineCountExpr . ', 0))');
        $priceExpr = $priceColumn
            ? 'COALESCE(sol.' . $priceColumn . ', 0)'
            : ($totalColumn ? '(' . $totalExpr . ' / NULLIF(' . $qtyExpr . ', 0))' : '0');

        return [
            'qty' => $qtyExpr,
            'price' => $priceExpr,
            'total' => $totalExpr,
            'serial' => "NULLIF(TRIM(CONCAT_WS(' / ', NULLIF(sol.serial_number, ''), NULLIF(sol.imei, ''), NULLIF(sol.imei2, ''), NULLIF(sol.primary_identifier, ''))), '')",
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
            ->when($request->filled('branch_name'), fn ($q) => $q->whereRaw('TRIM(sor.branch_name) = ?', [$request->input('branch_name')]))
            ->when($request->filled('sell_type'), function ($q) use ($request) {
                $sellType = $request->input('sell_type');
                $q->whereIn('sor.service_type', $this->sellTypeValues($sellType));
            })
            ->when($request->filled('department_id') && $this->canFilterByDepartment(), function ($q) use ($request) {
                $q->where('u.department_id', $request->input('department_id'));
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

    private function filterOptions(): array
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

            $departments = $this->hrDepartments();

            return [$branches, $sellTypes, $sellers, $departments];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sell report filters: ' . $e->getMessage());

            return [collect(), collect(), collect(), collect()];
        }
    }

    private function hrDepartments()
    {
        $labelColumn = $this->departmentLabelColumn();

        if (! $this->canFilterByDepartment() || empty($labelColumn)) {
            return collect();
        }

        return DB::connection('hr')
            ->table('departments')
            ->select('id', $labelColumn)
            ->whereNotNull($labelColumn)
            ->where($labelColumn, '!=', '')
            ->orderBy($labelColumn)
            ->pluck($labelColumn, 'id');
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

    private function sellTypeValues(?string $type): array
    {
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
