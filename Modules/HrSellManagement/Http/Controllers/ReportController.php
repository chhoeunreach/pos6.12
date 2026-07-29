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
        [$hrBranches, $hrSellTypes, $hrSellers] = $this->filterOptions();

        return view('hrsellmanagement::reports.index', compact('rows', 'summary', 'hrBranches', 'hrSellTypes', 'hrSellers'));
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

            return [$branches, $sellTypes, $sellers];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sell report filters: ' . $e->getMessage());

            return [collect(), collect(), collect()];
        }
    }

    private function sellTypeMap(): array
    {
        return [
            'sell' => ['label' => 'Sell / លក់', 'values' => ['sell', 'Sell', 'លក់', 'Sell / លក់', 'Sell/លក់']],
            'buy_in' => ['label' => 'Buy In / ទិញចូល', 'values' => ['buy in', 'Buy In', 'buy_in', 'buyin', 'ទិញចូល', 'Buy In / ទិញចូល', 'Buy In/ទិញចូល', 'ទិញចូល / Buy In', 'ទិញចូល/Buy In']],
            'repair' => ['label' => 'Repair / ជួសជុល', 'values' => ['repair', 'Repair', 'ជួសជុល', 'Repair / ជួសជុល', 'Repair/ជួសជុល', 'ជួសជុល / Repair', 'ជួសជុល/Repair']],
            'material' => ['label' => 'Material / សម្ភារ', 'values' => ['material', 'Material', 'materials', 'Materials', 'សម្ភារ', 'Material / សម្ភារ', 'Material/សម្ភារ', 'សម្ភារ / Material', 'សម្ភារ/Material']],
            'iron' => ['label' => 'Iron / អ៊ុត', 'values' => ['iron', 'Iron', 'អ៊ុត', 'Iron / អ៊ុត', 'Iron/អ៊ុត', 'អ៊ុត / Iron', 'អ៊ុត/Iron']],
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

    private function emptyRows(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 50, (int) $request->input('hr_report_page', 1), [
            'path' => $request->url(),
            'pageName' => 'hr_report_page',
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
