<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Exports\ArrayExport;
use App\System;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\WkhtmltopdfPdfService;
use Modules\LocalCashierReport\Support\ReportLanguage;
use Modules\LocalCashierReport\Support\TelegramReport;

class LocalCashierReportController extends Controller
{
    private const DETAIL_ROW_LIMIT = 1000;
    private const COLLECTION_PAYMENT_GROUP = 'Collection Payment';
    public function __construct(private Util $util)
    {
    }

    public function index(Request $request)
    {
        $this->abortIfUninstalled();
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $accessibleLocationIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $defaultLocationIds = $request->input('style_mode') === 'branch_dashboard'
            ? $accessibleLocationIds
            : $this->defaultLocationIds($request, $businessId, $accessibleLocationIds);
        $filters = $this->validatedFilters($request, $defaultLocationIds, $accessibleLocationIds);
        $cashiers = $this->getCashiers($businessId, $filters['location_ids']);
        $report = $this->getReportData($filters);

        return view('localcashierreport::index', [
            'businessName' => (string) session('business.name', config('app.name')),
            'filters' => $filters,
            'locations' => $locations,
            'cashiers' => $cashiers,
            'paymentStatuses' => config('localcashierreport.payment_statuses'),
            'qtyTypes' => config('localcashierreport.qty_types'),
            'currencySymbol' => $this->currencySymbol(),
            'khmerFontFamily' => config('localcashierreport.khmer_font_family'),
            'staticPaymentColumns' => $this->getStaticPaymentColumns(),
            'report' => $report,
            'telegramReport' => TelegramReport::build(
                $report,
                $filters,
                (string) session('business.name', config('app.name')),
                $locations->whereIn('id', $filters['location_ids'])->pluck('name')->all(),
                $this->getStaticPaymentColumns()
            ),
        ]);
    }

    public function export(Request $request)
    {
        $this->abortIfUninstalled();
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $accessibleLocationIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $defaultLocationIds = $request->input('style_mode') === 'branch_dashboard'
            ? $accessibleLocationIds
            : $this->defaultLocationIds($request, $businessId, $accessibleLocationIds);
        $filters = $this->validatedFilters($request, $defaultLocationIds, $accessibleLocationIds);
        $report = $this->getReportData($filters);

        $rows = [];
        foreach ($report['rows'] as $row) {
            $line = [
                ReportLanguage::text('cashier_user') => $row['cashier_name'],
                ReportLanguage::text('business_location_qty') => $row['location_qty_text'],
            ];
            foreach ($report['payment_columns'] as $method) {
                $line[ReportLanguage::payment($method, (string) ($report['payment_labels'][$method] ?? $method))] = $this->formatCurrency($row['payments'][$method] ?? null);
            }
            $line[ReportLanguage::text('total')] = $this->formatCurrency($row['total']);
            $line[ReportLanguage::text('due')] = $this->formatCurrency($row['due']);
            $rows[] = $line;
        }

        $fileName = 'local_cashier_report_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ArrayExport($rows), $fileName);
    }

    public function print(Request $request)
    {
        $this->abortIfUninstalled();
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $accessibleLocationIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $defaultLocationIds = $request->input('style_mode') === 'branch_dashboard'
            ? $accessibleLocationIds
            : $this->defaultLocationIds($request, $businessId, $accessibleLocationIds);
        $filters = $this->validatedFilters($request, $defaultLocationIds, $accessibleLocationIds);
        $report = $this->getReportData($filters);

        $selectedLocations = $locations->whereIn('id', $filters['location_ids'])->pluck('name')->all();

        return view('localcashierreport::print', [
            'businessName' => (string) session('business.name', config('app.name')),
            'filters' => $filters,
            'selectedLocations' => $selectedLocations,
            'currencySymbol' => $this->currencySymbol(),
            'khmerFontFamily' => config('localcashierreport.khmer_font_family'),
            'staticPaymentColumns' => $this->getStaticPaymentColumns(),
            'report' => $report,
        ]);
    }

    public function pdf(Request $request)
    {
        $this->abortIfUninstalled();
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $accessibleLocationIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $styleMode = (string) $request->input('style_mode', 'branch_dashboard');
        $defaultLocationIds = $styleMode === 'branch_dashboard'
            ? $accessibleLocationIds
            : $this->defaultLocationIds($request, $businessId, $accessibleLocationIds);
        $filters = $this->validatedFilters($request, $defaultLocationIds, $accessibleLocationIds);
        $report = $this->getReportData($filters);
        $selectedLocations = $locations->whereIn('id', $filters['location_ids'])->pluck('name')->all();

        $html = view('localcashierreport::pdf_branch', [
            'businessName' => (string) session('business.name', config('app.name')),
            'filters' => $filters,
            'selectedLocations' => $selectedLocations,
            'currencySymbol' => $this->currencySymbol(),
            'staticPaymentColumns' => $this->getStaticPaymentColumns(),
            'report' => $report,
        ])->render();

        $fileName = 'local_cashier_branch_report_' . now()->format('Ymd_His') . '.pdf';
        $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $tempPdfPath = $tempDir . DIRECTORY_SEPARATOR . 'local_cashier_branch_' . Str::random(12) . '.pdf';

        try {
            $this->writeBranchPdfToPath($html, $tempPdfPath);
            $pdfContent = (string) file_get_contents($tempPdfPath);
        } finally {
            if (is_file($tempPdfPath)) {
                @unlink($tempPdfPath);
            }
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Build Branch PDF HTML and report payload safely for CLI / scheduled jobs without an HTTP user session.
     */
    public function buildBranchPdfPayload(
        int $businessId,
        string $startDate,
        string $endDate,
        string $reportLang = 'km',
        ?string $businessName = null
    ): array {
        $locations = $this->getAccessibleLocations($businessId, true);
        $accessibleLocationIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $reportLang = in_array($reportLang, ['en', 'km'], true) ? $reportLang : 'km';

        $cliRequest = Request::create('/local-cashier-report/pdf', 'GET', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'style_mode' => 'branch_dashboard',
            'report_lang' => $reportLang,
        ]);

        $filters = $this->validatedFilters($cliRequest, $accessibleLocationIds, $accessibleLocationIds);
        $filters['business_id'] = $businessId;
        $report = $this->getReportData($filters);
        $selectedLocations = $locations->whereIn('id', $filters['location_ids'])->pluck('name')->all();

        $resolvedBusinessName = $businessName;
        if ($resolvedBusinessName === null || trim($resolvedBusinessName) === '') {
            $resolvedBusinessName = (string) (DB::table('business')->where('id', $businessId)->value('name') ?: config('app.name'));
        }

        $currencySymbol = '$';
        $currencyId = DB::table('business')->where('id', $businessId)->value('currency_id');
        if ($currencyId) {
            $dbSymbol = DB::table('currencies')->where('id', (int) $currencyId)->value('symbol');
            if (! empty($dbSymbol)) {
                $currencySymbol = (string) $dbSymbol;
            }
        }

        $previousLang = request('report_lang');
        request()->merge(['report_lang' => $reportLang]);
        try {
            $html = view('localcashierreport::pdf_branch', [
                'businessName' => $resolvedBusinessName,
                'filters' => $filters,
                'selectedLocations' => $selectedLocations,
                'currencySymbol' => $currencySymbol,
                'staticPaymentColumns' => $this->getStaticPaymentColumns(),
                'report' => $report,
            ])->render();
        } finally {
            if ($previousLang === null) {
                request()->request->remove('report_lang');
            } else {
                request()->merge(['report_lang' => $previousLang]);
            }
        }

        return [
            'html' => $html,
            'report' => $report,
            'filters' => $filters,
            'businessName' => $resolvedBusinessName,
            'selectedLocations' => $selectedLocations,
        ];
    }

    /**
     * Render Branch PDF HTML to a destination PDF file path using WkhtmltopdfPdfService.
     */
    public function writeBranchPdfToPath(string $html, string $outputPath): void
    {
        /** @var WkhtmltopdfPdfService $pdfService */
        $pdfService = app(WkhtmltopdfPdfService::class);
        $pdfService->saveHtmlToPdf($html, $outputPath, [
            'page-size' => 'A4',
            'orientation' => 'Landscape',
            'encoding' => 'utf-8',
            'enable-local-file-access' => true,
            'margin-top' => '8mm',
            'margin-right' => '8mm',
            'margin-bottom' => '10mm',
            'margin-left' => '8mm',
        ]);
    }

    public function getAccessibleLocations(int $businessId, bool $ignoreAuthPermissions = false)
    {
        $user = $ignoreAuthPermissions ? null : auth()->user();
        $permitted = $user ? $user->permitted_locations($businessId) : 'all';

        $locations = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->where('is_active', 1)
            ->when($permitted !== 'all', function ($query) use ($permitted) {
                $query->whereIn('id', (array) $permitted);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $moduleLocations = collect()
            ->merge($this->getModuleLocationsWithSales(
                (string) config('accessory.database_connection', 'accessory'),
                'Accessory',
                $businessId,
                $permitted
            ))
            ->merge($this->getModuleLocationsWithSales(
                (string) config('service.database_connection', 'service'),
                'Service',
                $businessId,
                $permitted
            ));

        return $locations
            ->merge($moduleLocations)
            ->unique(fn ($location) => (int) $location->id . '|' . (string) $location->name)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function getModuleLocationsWithSales(string $connection, string $label, int $businessId, $permitted)
    {
        if (! $this->hasRequiredReportTables($connection, ['business_locations', 'transactions'])) {
            return collect();
        }

        try {
            $db = DB::connection($connection);

            $salesLocationIds = $db->table('transactions as t')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->when($permitted !== 'all', function ($query) use ($permitted) {
                    $query->whereIn('t.location_id', (array) $permitted);
                })
                ->distinct()
                ->pluck('t.location_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            if (empty($salesLocationIds)) {
                return collect();
            }

            return $db->table('business_locations')
                ->whereIn('id', $salesLocationIds)
                ->when(Schema::connection($connection)->hasColumn('business_locations', 'is_active'), function ($query) {
                    $query->where('is_active', 1);
                })
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function ($location) use ($label) {
                    $location->id = $this->moduleLocationFilterId((int) $location->id, strtolower($label));
                    $location->name = trim((string) $location->name) . ' (' . $label . ')';

                    return $location;
                });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function moduleLocationFilterId(int $locationId, string $modulePrefix): int
    {
        // Negative IDs keep independently numbered module locations distinct from main locations.
        return -($locationId * 2 + ($modulePrefix === 'service' ? 1 : 0));
    }

    private function moduleLocationIds(array $selectedIds, string $modulePrefix): array
    {
        $parity = $modulePrefix === 'service' ? 1 : 0;

        return array_values(array_map(
            fn ($id) => intdiv(-(int) $id, 2),
            array_filter($selectedIds, fn ($id) => (int) $id < -1 && (-(int) $id % 2) === $parity)
        ));
    }

    public function getCashiers(int $businessId, array $locationIds = [])
    {
        // Show every user in the business so the cashier filter is never missing a
        // user just because they have no sale in the currently selected locations.
        $cashiers = DB::table('users as u')
            ->where('u.business_id', $businessId)
            ->whereNull('u.deleted_at')
            ->select(
                'u.id',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as name")
            )
            ->orderBy('u.first_name')
            ->get();

        foreach (['accessory', 'service'] as $source) {
            $moduleLocationIds = $this->moduleLocationIds($locationIds, $source);
            $connection = (string) config($source . '.database_connection', $source);
            if (empty($moduleLocationIds) || ! $this->hasRequiredReportTables($connection, ['users'])) {
                continue;
            }

            $moduleCashiers = DB::connection($connection)->table('users as u')
                ->where('u.business_id', 1)
                ->when(Schema::connection($connection)->hasColumn('users', 'deleted_at'), function ($query) {
                    $query->whereNull('u.deleted_at');
                })
                ->select('u.id', DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as name"))
                ->get()
                ->map(function ($cashier) use ($source) {
                    $cashier->id = $this->moduleCashierFilterId((int) $cashier->id, $source);
                    $cashier->name .= ' (' . ucfirst($source) . ')';

                    return $cashier;
                });
            $cashiers = $cashiers->merge($moduleCashiers);
        }

        return $cashiers->sortBy('name')->values();
    }

    private function moduleCashierFilterId(int $userId, string $source): int
    {
        // Each database numbers its users independently, just like module locations.
        return -($userId * 2 + ($source === 'service' ? 1 : 0));
    }

    private function moduleCashierIds(array $selectedIds, string $source): array
    {
        $parity = $source === 'service' ? 1 : 0;

        return array_values(array_map(
            fn ($id) => intdiv(-(int) $id, 2),
            array_filter($selectedIds, fn ($id) => (int) $id < -1 && (-(int) $id % 2) === $parity)
        ));
    }

    public function getReportData(array $filters): array
    {
        $businessId = (int) ($filters['business_id'] ?? session('user.business_id'));
        $paymentColumns = [];

        $baseTransactions = DB::table('transactions as t')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                $query->where('t.payment_status', $filters['payment_status']);
            })
            ->when(! empty($filters['payment_methods']), function ($query) use ($filters) {
                $query->whereExists(function ($sub) use ($filters) {
                    $sub->select(DB::raw(1))
                        ->from('transaction_payments as tpf')
                        ->whereColumn('tpf.transaction_id', 't.id')
                        ->whereIn('tpf.method', $filters['payment_methods']);
                });
            })
            ->when(! empty($filters['customer_group']) && $filters['customer_group'] !== self::COLLECTION_PAYMENT_GROUP, function ($query) use ($filters) {
                $query->whereRaw(
                    "CASE
                        WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                        WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                        ELSE ?
                    END = ?",
                    ['រំលស់', 'រំលស់', 'អ៊ីអន', 'អ៊ីអន', 'លក់', $filters['customer_group']]
                );
            })
            ->when(! empty($filters['customer_group']) && $filters['customer_group'] === self::COLLECTION_PAYMENT_GROUP, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when(! empty($filters['brand_ids']), function ($query) use ($filters) {
                $brandIds = collect($filters['brand_ids'])->map(fn ($id) => (int) $id)->values();
                $hasNoBrand = $brandIds->contains(0);
                $normalBrandIds = $brandIds->filter(fn ($id) => $id > 0)->values()->all();
                $query->whereExists(function ($sub) use ($normalBrandIds, $hasNoBrand) {
                    $sub->select(DB::raw(1))
                        ->from('transaction_sell_lines as tslf')
                        ->join('products as pf', 'pf.id', '=', 'tslf.product_id')
                        ->whereColumn('tslf.transaction_id', 't.id')
                        ->where(function ($w) use ($normalBrandIds, $hasNoBrand) {
                            if (! empty($normalBrandIds)) {
                                $w->whereIn('pf.brand_id', $normalBrandIds);
                            }
                            if ($hasNoBrand) {
                                $w->orWhereNull('pf.brand_id');
                            }
                        });
                });
            })
            ->select(
                't.id',
                't.created_by',
                't.location_id',
                't.final_total',
                DB::raw("COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') as customer_group_name")
            )
            ->get();

        $paymentTypes = $this->util->payment_types(null, false, $businessId);
        $loanPaymentData = $this->getLoanPaymentData($filters, $paymentTypes);
        $customerDuePaymentData = $this->getCustomerDuePaymentData($filters, $paymentTypes, self::DETAIL_ROW_LIMIT);
        $dueCustomerData = $this->getDueCustomerData($filters, self::DETAIL_ROW_LIMIT);

        $transactionIds = $baseTransactions->pluck('id')->all();
        $cashierIds = $baseTransactions->pluck('created_by')->unique()->values()->all();
        $locationIds = $baseTransactions->pluck('location_id')->unique()->values()->all();

        $paymentRows = DB::table('transaction_payments as tp')
            ->whereIn('tp.transaction_id', $transactionIds)
            ->select('tp.transaction_id', 'tp.method', DB::raw('SUM(tp.amount) as amount'))
            ->groupBy('tp.transaction_id', 'tp.method')
            ->get();

        $paymentByTransaction = [];
        $methodsWithAmount = [];
        foreach ($paymentRows as $p) {
            $txnId = (int) $p->transaction_id;
            $method = (string) $p->method;
            $amount = (float) $p->amount;
            $paymentByTransaction[$txnId][$method] = $amount;
            if (abs($amount) > 0.00001) {
                $methodsWithAmount[$method] = true;
            }
        }

        $qtyByCashierLocation = [];
        $qtyByTransaction = [];
        if ($filters['qty_type'] === 'invoice_count') {
            $invoiceCountRows = $baseTransactions
                ->groupBy(fn ($t) => $t->created_by . '_' . $t->location_id)
                ->map(fn ($items) => count($items));
            foreach ($invoiceCountRows as $key => $qty) {
                $qtyByCashierLocation[$key] = (float) $qty;
            }
            foreach ($baseTransactions as $t) {
                $qtyByTransaction[(int) $t->id] = 1.0;
            }
        } else {
            $sellQtyRows = DB::table('transaction_sell_lines as tsl')
                ->whereIn('tsl.transaction_id', $transactionIds)
                ->select('tsl.transaction_id', DB::raw('SUM(tsl.quantity) as qty'))
                ->groupBy('tsl.transaction_id')
                ->get()
                ->keyBy('transaction_id');

            foreach ($baseTransactions as $t) {
                $key = $t->created_by . '_' . $t->location_id;
                $qtyByCashierLocation[$key] = ($qtyByCashierLocation[$key] ?? 0) + (float) ($sellQtyRows[$t->id]->qty ?? 0);
                $qtyByTransaction[(int) $t->id] = (float) ($sellQtyRows[$t->id]->qty ?? 0);
            }
        }

        foreach ($loanPaymentData['methods'] as $method) {
            $methodsWithAmount[$method] = true;
        }
        foreach ($customerDuePaymentData['methods'] as $method) {
            $methodsWithAmount[$method] = true;
        }

        foreach ([
            'accessory' => (string) config('accessory.database_connection', 'accessory'),
            'service' => (string) config('service.database_connection', 'service'),
        ] as $modulePrefix => $moduleConnection) {
            $moduleFilters = $filters;
            $moduleFilters['location_ids'] = $this->moduleLocationIds($filters['location_ids'], $modulePrefix);
            foreach ($this->getModulePaymentMethodsWithAmount($moduleConnection, $moduleFilters) as $method) {
                $methodsWithAmount[$method] = true;
            }
        }

        $cashierIds = array_values(array_unique(array_merge($cashierIds, $loanPaymentData['cashier_ids'], $customerDuePaymentData['cashier_ids'])));
        $locationIds = array_values(array_unique(array_merge($locationIds, $loanPaymentData['location_ids'], $customerDuePaymentData['location_ids'])));
        $cashierMap = DB::table('users')
            ->whereIn('id', $cashierIds)
            ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"))
            ->pluck('name', 'id');
        $locationMap = DB::table('business_locations')->whereIn('id', array_values(array_unique(array_merge($filters['location_ids'], $locationIds))))->pluck('name', 'id');

        $paymentColumns = $this->buildPaymentColumns(array_keys($methodsWithAmount), $paymentTypes);
        $customerDuePaymentData['rows'] = $this->normalizeCustomerDuePaymentRows($customerDuePaymentData['rows'], $paymentColumns);
        $customerDuePaymentData['summary_rows'] = $this->normalizeCustomerDuePaymentRows($customerDuePaymentData['summary_rows'], $paymentColumns);

        $rowsByCashier = [];
        $rowsByLocation = [];
        foreach ($baseTransactions as $t) {
            $cashierId = (int) $t->created_by;
            $locationId = (int) $t->location_id;
            if (! isset($rowsByCashier[$cashierId])) {
                $rowsByCashier[$cashierId] = [
                    'cashier_id' => $cashierId,
                    'cashier_name' => (string) ($cashierMap[$cashierId] ?? 'N/A'),
                    'location_qty_map' => [],
                    'payments' => [],
                    'customer_groups' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }
            if (! isset($rowsByLocation[$locationId])) {
                $rowsByLocation[$locationId] = [
                    'location_id' => $locationId,
                    'location_name' => (string) ($locationMap[$locationId] ?? 'N/A'),
                    'qty_total' => 0.0,
                    'payments' => [],
                    'customer_groups' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }

            $customerGroupName = trim((string) ($t->customer_group_name ?? ''));
            $customerGroupKey = $customerGroupName === 'រំលស់'
                ? 'installment'
                : ($customerGroupName === 'អ៊ីអន' ? 'aeon' : 'normal');
            $customerGroupLabel = $customerGroupKey === 'installment'
                ? 'រំលស់'
                : ($customerGroupKey === 'aeon' ? 'អ៊ីអន' : 'លក់');
            $customerGroupSort = $customerGroupKey === 'aeon'
                ? 2
                : ($customerGroupKey === 'installment' ? 3 : 1);
            if (! isset($rowsByLocation[$locationId]['customer_groups'][$customerGroupKey])) {
                $rowsByLocation[$locationId]['customer_groups'][$customerGroupKey] = [
                    'name' => $customerGroupLabel,
                    'sort' => $customerGroupSort,
                    'qty_total' => 0.0,
                    'payments' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }
            if (! isset($rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey])) {
                $rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey] = [
                    'name' => $customerGroupLabel,
                    'sort' => $customerGroupSort,
                    'location_qty_map' => [],
                    'payments' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }

            $rowsByCashier[$cashierId]['total'] += (float) $t->final_total;
            $rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey]['total'] += (float) $t->final_total;
            $rowsByLocation[$locationId]['total'] += (float) $t->final_total;
            $rowsByLocation[$locationId]['customer_groups'][$customerGroupKey]['total'] += (float) $t->final_total;

            $locKey = $cashierId . '_' . $locationId;
            if (isset($qtyByCashierLocation[$locKey])) {
                $rowsByCashier[$cashierId]['location_qty_map'][$locationId] = $qtyByCashierLocation[$locKey];
            } elseif (! array_key_exists($locationId, $rowsByCashier[$cashierId]['location_qty_map'])) {
                $rowsByCashier[$cashierId]['location_qty_map'][$locationId] = 0.0;
            }
            $txnQty = (float) ($qtyByTransaction[(int) $t->id] ?? 0);
            $rowsByLocation[$locationId]['qty_total'] += $txnQty;
            $rowsByLocation[$locationId]['customer_groups'][$customerGroupKey]['qty_total'] += $txnQty;
            $rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey]['location_qty_map'][$locationId] = ($rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey]['location_qty_map'][$locationId] ?? 0) + $txnQty;

            $txnPayments = $paymentByTransaction[(int) $t->id] ?? [];
            foreach ($txnPayments as $method => $amount) {
                $rowsByCashier[$cashierId]['payments'][$method] = ($rowsByCashier[$cashierId]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByCashier[$cashierId]['paid'] += (float) $amount;
                $rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey]['payments'][$method] = ($rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByCashier[$cashierId]['customer_groups'][$customerGroupKey]['paid'] += (float) $amount;
                $rowsByLocation[$locationId]['payments'][$method] = ($rowsByLocation[$locationId]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByLocation[$locationId]['paid'] += (float) $amount;
                $rowsByLocation[$locationId]['customer_groups'][$customerGroupKey]['payments'][$method] = ($rowsByLocation[$locationId]['customer_groups'][$customerGroupKey]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByLocation[$locationId]['customer_groups'][$customerGroupKey]['paid'] += (float) $amount;
            }
        }

        foreach ($loanPaymentData['cashier_groups'] as $cashierId => $loanGroupRow) {
            $cashierId = (int) $cashierId;
            if (! isset($rowsByCashier[$cashierId])) {
                $rowsByCashier[$cashierId] = [
                    'cashier_id' => $cashierId,
                    'cashier_name' => (string) ($cashierMap[$cashierId] ?? 'N/A'),
                    'location_qty_map' => [],
                    'payments' => [],
                    'customer_groups' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }

            $rowsByCashier[$cashierId]['customer_groups']['loan_payment'] = $loanGroupRow;
            foreach (($loanGroupRow['location_qty_map'] ?? []) as $locationId => $qty) {
                $rowsByCashier[$cashierId]['location_qty_map'][$locationId] =
                    ($rowsByCashier[$cashierId]['location_qty_map'][$locationId] ?? 0) + (float) $qty;
            }
            foreach (($loanGroupRow['payments'] ?? []) as $method => $amount) {
                $rowsByCashier[$cashierId]['payments'][$method] = ($rowsByCashier[$cashierId]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByCashier[$cashierId]['paid'] += (float) $amount;
            }
        }

        foreach ($loanPaymentData['location_groups'] as $locationId => $loanGroupRow) {
            $locationId = (int) $locationId;
            if (! isset($rowsByLocation[$locationId])) {
                $rowsByLocation[$locationId] = [
                    'location_id' => $locationId,
                    'location_name' => (string) ($locationMap[$locationId] ?? 'N/A'),
                    'qty_total' => 0.0,
                    'payments' => [],
                    'customer_groups' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }

            $rowsByLocation[$locationId]['customer_groups']['loan_payment'] = $loanGroupRow;
            foreach (($loanGroupRow['payments'] ?? []) as $method => $amount) {
                $rowsByLocation[$locationId]['payments'][$method] = ($rowsByLocation[$locationId]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByLocation[$locationId]['paid'] += (float) $amount;
            }
        }

        $includeCustomerDuePayments = empty($filters['customer_group']) || $filters['customer_group'] === 'Customer Payment';
        if ($includeCustomerDuePayments) {
            foreach ($customerDuePaymentData['summary_rows'] as $customerDuePaymentRow) {
                $cashierId = (int) ($customerDuePaymentRow['cashier_id'] ?? 0);
                $locationId = (int) ($customerDuePaymentRow['location_id'] ?? 0);
                $amount = (float) ($customerDuePaymentRow['amount'] ?? 0);
                $method = (string) ($customerDuePaymentRow['method'] ?? 'cash');

                if ($cashierId <= 0 || $locationId <= 0 || abs($amount) < 0.00001) {
                    continue;
                }

                if (! isset($rowsByCashier[$cashierId])) {
                    $rowsByCashier[$cashierId] = [
                        'cashier_id' => $cashierId,
                        'cashier_name' => (string) ($cashierMap[$cashierId] ?? ($customerDuePaymentRow['cashier_name'] ?? 'N/A')),
                        'location_qty_map' => [],
                        'payments' => [],
                        'customer_groups' => [],
                        'total' => 0.0,
                        'paid' => 0.0,
                        'due' => 0.0,
                    ];
                }
                if (! isset($rowsByLocation[$locationId])) {
                    $rowsByLocation[$locationId] = [
                        'location_id' => $locationId,
                        'location_name' => (string) ($locationMap[$locationId] ?? ($customerDuePaymentRow['location_name'] ?? 'N/A')),
                        'qty_total' => 0.0,
                        'payments' => [],
                        'customer_groups' => [],
                        'total' => 0.0,
                        'paid' => 0.0,
                        'due' => 0.0,
                    ];
                }
                if (! isset($rowsByCashier[$cashierId]['customer_groups']['customer_payment'])) {
                    $rowsByCashier[$cashierId]['customer_groups']['customer_payment'] = [
                        'name' => 'Customer Payment',
                        'sort' => 5,
                        'location_qty_map' => [],
                        'payments' => [],
                        'total' => 0.0,
                        'paid' => 0.0,
                        'due' => 0.0,
                    ];
                }
                if (! isset($rowsByLocation[$locationId]['customer_groups']['customer_payment'])) {
                    $rowsByLocation[$locationId]['customer_groups']['customer_payment'] = [
                        'name' => 'Customer Payment',
                        'sort' => 5,
                        'qty_total' => 0.0,
                        'payments' => [],
                        'total' => 0.0,
                        'paid' => 0.0,
                        'due' => 0.0,
                    ];
                }

                $rowsByCashier[$cashierId]['payments'][$method] = ($rowsByCashier[$cashierId]['payments'][$method] ?? 0) + $amount;
                $rowsByCashier[$cashierId]['paid'] += $amount;
                $rowsByCashier[$cashierId]['customer_due_payment_total'] = ($rowsByCashier[$cashierId]['customer_due_payment_total'] ?? 0) + $amount;
                if (! array_key_exists($locationId, $rowsByCashier[$cashierId]['location_qty_map'])) {
                    $rowsByCashier[$cashierId]['location_qty_map'][$locationId] = 0.0;
                }
                if (! array_key_exists($locationId, $rowsByCashier[$cashierId]['customer_groups']['customer_payment']['location_qty_map'])) {
                    $rowsByCashier[$cashierId]['customer_groups']['customer_payment']['location_qty_map'][$locationId] = 0.0;
                }
                $rowsByCashier[$cashierId]['customer_groups']['customer_payment']['payments'][$method] = ($rowsByCashier[$cashierId]['customer_groups']['customer_payment']['payments'][$method] ?? 0) + $amount;
                $rowsByCashier[$cashierId]['customer_groups']['customer_payment']['paid'] += $amount;

                $rowsByLocation[$locationId]['payments'][$method] = ($rowsByLocation[$locationId]['payments'][$method] ?? 0) + $amount;
                $rowsByLocation[$locationId]['paid'] += $amount;
                $rowsByLocation[$locationId]['customer_due_payment_total'] = ($rowsByLocation[$locationId]['customer_due_payment_total'] ?? 0) + $amount;
                $rowsByLocation[$locationId]['customer_groups']['customer_payment']['payments'][$method] = ($rowsByLocation[$locationId]['customer_groups']['customer_payment']['payments'][$method] ?? 0) + $amount;
                $rowsByLocation[$locationId]['customer_groups']['customer_payment']['paid'] += $amount;
            }
        }

        $rows = [];
        $grandTotal = 0.0;
        $grandDue = 0.0;
        $userSummary = [];
        foreach ($rowsByCashier as $cashierRow) {
            $cashierRow['location_qty_text'] = $this->formatLocationQty($cashierRow['location_qty_map'], $locationMap);
            $cashierRow['qty_total'] = array_sum($cashierRow['location_qty_map']);
            $cashierPaidForDue = (float) $cashierRow['paid'] - (float) ($cashierRow['customer_due_payment_total'] ?? 0);
            $cashierRow['due'] = (float) $cashierRow['total'] - $cashierPaidForDue;

            foreach ($paymentColumns as $method) {
                if (! isset($cashierRow['payments'][$method])) {
                    $cashierRow['payments'][$method] = null;
                }
            }
            foreach ($cashierRow['customer_groups'] as &$customerGroupRow) {
                $customerGroupRow['location_qty_text'] = $this->formatLocationQty($customerGroupRow['location_qty_map'], $locationMap);
                $customerGroupRow['qty_total'] = array_sum($customerGroupRow['location_qty_map']);
                $customerGroupRow['due'] = in_array((int) ($customerGroupRow['sort'] ?? 0), [4, 5], true)
                    ? 0.0
                    : (float) $customerGroupRow['total'] - (float) $customerGroupRow['paid'];
                foreach ($paymentColumns as $method) {
                    if (! isset($customerGroupRow['payments'][$method])) {
                        $customerGroupRow['payments'][$method] = null;
                    }
                }
            }
            unset($customerGroupRow);
            uasort($cashierRow['customer_groups'], fn ($a, $b) => ($a['sort'] ?? 1) <=> ($b['sort'] ?? 1));

            $rows[] = $cashierRow;
            $userSummary[] = [
                'id' => (int) ($cashierRow['cashier_id'] ?? 0),
                'name' => $cashierRow['cashier_name'],
                'amount' => (float) $cashierRow['total'] + (float) ($cashierRow['customer_due_payment_total'] ?? 0),
                'qty' => (float) $cashierRow['qty_total'],
            ];
            $grandTotal += (float) $cashierRow['total'];
            $grandDue += (float) $cashierRow['due'];
        }

        usort($rows, fn ($a, $b) => strcmp($a['cashier_name'], $b['cashier_name']));

        $locationRows = [];
        foreach ($rowsByLocation as $locationRow) {
            $locationPaidForDue = (float) $locationRow['paid'] - (float) ($locationRow['customer_due_payment_total'] ?? 0);
            $locationRow['due'] = (float) $locationRow['total'] - $locationPaidForDue;
            foreach ($paymentColumns as $method) {
                if (! isset($locationRow['payments'][$method])) {
                    $locationRow['payments'][$method] = null;
                }
            }
            foreach ($locationRow['customer_groups'] as &$customerGroupRow) {
                $customerGroupRow['due'] = in_array((int) ($customerGroupRow['sort'] ?? 0), [4, 5], true)
                    ? 0.0
                    : (float) $customerGroupRow['total'] - (float) $customerGroupRow['paid'];
                foreach ($paymentColumns as $method) {
                    if (! isset($customerGroupRow['payments'][$method])) {
                        $customerGroupRow['payments'][$method] = null;
                    }
                }
            }
            unset($customerGroupRow);
            uasort($locationRow['customer_groups'], fn ($a, $b) => ($a['sort'] ?? 1) <=> ($b['sort'] ?? 1));
            $locationRows[] = $locationRow;
        }
        usort($locationRows, fn ($a, $b) => strcmp($a['location_name'], $b['location_name']));

        $locationSummaryMap = [];
        foreach ($rowsByCashier as $cashierRow) {
            foreach ($cashierRow['location_qty_map'] as $locId => $qty) {
                if (! isset($locationSummaryMap[$locId])) {
                    $locationSummaryMap[$locId] = ['id' => (int) $locId, 'name' => (string) ($locationMap[$locId] ?? 'N/A'), 'amount' => 0.0, 'qty' => 0.0];
                }
                $locationSummaryMap[$locId]['qty'] += (float) $qty;
            }
        }
        foreach ($baseTransactions as $t) {
            $locId = (int) $t->location_id;
            if (! isset($locationSummaryMap[$locId])) {
                $locationSummaryMap[$locId] = ['id' => (int) $locId, 'name' => (string) ($locationMap[$locId] ?? 'N/A'), 'amount' => 0.0, 'qty' => 0.0];
            }
            $locationSummaryMap[$locId]['amount'] += (float) $t->final_total;
        }
        if ($includeCustomerDuePayments) {
            foreach ($customerDuePaymentData['summary_rows'] as $customerDuePaymentRow) {
                $locId = (int) ($customerDuePaymentRow['location_id'] ?? 0);
                if ($locId <= 0) {
                    continue;
                }
                if (! isset($locationSummaryMap[$locId])) {
                    $locationSummaryMap[$locId] = ['id' => $locId, 'name' => (string) ($locationMap[$locId] ?? ($customerDuePaymentRow['location_name'] ?? 'N/A')), 'amount' => 0.0, 'qty' => 0.0];
                }
                $locationSummaryMap[$locId]['amount'] += (float) ($customerDuePaymentRow['amount'] ?? 0);
            }
        }
        $locationSummary = array_values($locationSummaryMap);

        $customerGroupSummaryMap = [];
        foreach ($rowsByCashier as $cashierRow) {
            foreach (($cashierRow['customer_groups'] ?? []) as $customerGroupRow) {
                $name = (string) ($customerGroupRow['name'] ?? 'លក់');
                if (! isset($customerGroupSummaryMap[$name])) {
                    $customerGroupSummaryMap[$name] = [
                        'name' => $name,
                        'sort' => (int) ($customerGroupRow['sort'] ?? 1),
                        'amount' => 0.0,
                        'qty' => 0.0,
                    ];
                }

                $customerGroupSummaryMap[$name]['amount'] += (float) (($customerGroupRow['total'] ?? 0) > 0 ? $customerGroupRow['total'] : ($customerGroupRow['paid'] ?? 0));
                $customerGroupSummaryMap[$name]['qty'] += (float) ($customerGroupRow['qty_total'] ?? array_sum($customerGroupRow['location_qty_map'] ?? []));
            }
        }
        $customerGroupSummary = array_values($customerGroupSummaryMap);
        usort($customerGroupSummary, fn ($a, $b) => ($a['sort'] ?? 1) <=> ($b['sort'] ?? 1));

        $brandSummaryQuery = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->whereIn('tsl.transaction_id', $transactionIds)
            ->groupBy('p.brand_id', 'b.name')
            ->select(
                'p.brand_id as brand_id',
                DB::raw("COALESCE(NULLIF(TRIM(b.name), ''), 'No Brand') as name"),
                DB::raw('SUM(tsl.quantity) as sold_qty'),
                DB::raw('COUNT(DISTINCT tsl.transaction_id) as invoice_qty'),
                DB::raw('SUM((tsl.quantity * tsl.unit_price_before_discount) - COALESCE(tsl.line_discount_amount, 0)) as amount')
            )
            ->get();

        $brandSummary = $brandSummaryQuery->map(function ($row) use ($filters) {
            return [
                'id' => isset($row->brand_id) ? (int) $row->brand_id : 0,
                'name' => (string) ($row->name ?? 'No Brand'),
                'amount' => (float) ($row->amount ?? 0),
                'qty' => (float) (($filters['qty_type'] ?? 'sold_quantity') === 'invoice_count'
                    ? ($row->invoice_qty ?? 0)
                    : ($row->sold_qty ?? 0)),
            ];
        })->values()->all();

        $paymentSummaryMap = [];
        $paymentQtySummaryMap = [];
        foreach ($rowsByCashier as $cashierRow) {
            foreach ($cashierRow['payments'] as $method => $amount) {
                $paymentSummaryMap[$method] = ($paymentSummaryMap[$method] ?? 0) + (float) $amount;
            }
        }
        foreach ($baseTransactions as $t) {
            $txnId = (int) $t->id;
            $txnQty = (float) ($qtyByTransaction[$txnId] ?? 0);
            $txnMethods = array_keys($paymentByTransaction[$txnId] ?? []);
            foreach ($txnMethods as $method) {
                $paymentQtySummaryMap[$method] = ($paymentQtySummaryMap[$method] ?? 0) + $txnQty;
            }
        }
        $paymentSummary = [];
        foreach ($paymentSummaryMap as $method => $amount) {
            $paymentSummary[] = [
                'name' => (string) ($paymentTypes[$method] ?? $method),
                'amount' => (float) $amount,
                'qty' => (float) ($paymentQtySummaryMap[$method] ?? 0),
            ];
        }

        $summaryTotals = [
            'user' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $userSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $userSummary)),
            ],
            'location' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $locationSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $locationSummary)),
            ],
            'customer_group' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $customerGroupSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $customerGroupSummary)),
            ],
            'brand' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $brandSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $brandSummary)),
            ],
            'payment' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $paymentSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $paymentSummary)),
            ],
        ];

        $expenseQuery = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'expense')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->select('t.created_by', DB::raw('SUM(t.final_total) as amount'))
            ->groupBy('t.created_by')
            ->pluck('amount', 'created_by');
        $expenseByLocationQuery = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'expense')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->select('t.location_id', DB::raw('SUM(t.final_total) as amount'))
            ->groupBy('t.location_id')
            ->pluck('amount', 'location_id');

        $expenseTxnIds = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'expense')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->pluck('t.id')
            ->all();

        $expensePaymentSummaryMap = [];
        $expensePaymentByLocation = [];
        if (! empty($expenseTxnIds)) {
            $expensePaymentRows = DB::table('transaction_payments as tp')
                ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                ->whereIn('tp.transaction_id', $expenseTxnIds)
                ->select('t.location_id', 'tp.method', DB::raw('SUM(tp.amount) as amount'))
                ->groupBy('t.location_id', 'tp.method')
                ->get();

            foreach ($expensePaymentRows as $row) {
                $locId = (int) $row->location_id;
                $method = (string) $row->method;
                $amount = (float) $row->amount;
                $expensePaymentSummaryMap[$method] = ($expensePaymentSummaryMap[$method] ?? 0) + $amount;
                $expensePaymentByLocation[$locId][$method] = ($expensePaymentByLocation[$locId][$method] ?? 0) + $amount;
            }
        }

        $expenseDetailResult = $this->getExpenseDetailRows($expenseTxnIds, $paymentTypes, $paymentColumns, self::DETAIL_ROW_LIMIT);
        $accessorySaleDetailResult = $this->getModuleSaleDetailRows(
            (string) config('accessory.database_connection', 'accessory'),
            'accessory',
            $filters,
            $paymentColumns,
            self::DETAIL_ROW_LIMIT
        );
        $serviceSaleDetailResult = $this->getModuleSaleDetailRows(
            (string) config('service.database_connection', 'service'),
            'service',
            $filters,
            $paymentColumns,
            self::DETAIL_ROW_LIMIT
        );

        $accessorySaleSummaryRows = $accessorySaleDetailResult['total'] > count($accessorySaleDetailResult['rows'])
            ? $this->getModuleSaleDetailRows(
                (string) config('accessory.database_connection', 'accessory'),
                'accessory',
                $filters,
                $paymentColumns,
                null
            )['rows']
            : $accessorySaleDetailResult['rows'];
        $serviceSaleSummaryRows = $serviceSaleDetailResult['total'] > count($serviceSaleDetailResult['rows'])
            ? $this->getModuleSaleDetailRows(
                (string) config('service.database_connection', 'service'),
                'service',
                $filters,
                $paymentColumns,
                null
            )['rows']
            : $serviceSaleDetailResult['rows'];
        $moduleSaleSummaryRows = collect($accessorySaleSummaryRows ?? [])->merge($serviceSaleSummaryRows ?? []);
        $this->mergeModuleSummaryRows(
            $moduleSaleSummaryRows,
            $filters,
            $userSummary,
            $locationSummary,
            $customerGroupSummary,
            $brandSummary
        );
        $moduleSaleTotals = $this->moduleSaleTotals($moduleSaleSummaryRows, $paymentColumns);
        $grandTotal += $moduleSaleTotals['total'];
        $grandDue += $moduleSaleTotals['due'];
        foreach ($moduleSaleTotals['payments'] as $method => $amount) {
            $paymentSummaryMap[$method] = ($paymentSummaryMap[$method] ?? 0) + (float) $amount;
        }
        foreach ($moduleSaleTotals['payment_qty'] as $method => $qty) {
            $paymentQtySummaryMap[$method] = ($paymentQtySummaryMap[$method] ?? 0) + (float) $qty;
        }
        $summaryTotals = $this->summaryTotals($userSummary, $locationSummary, $customerGroupSummary, $brandSummary, $paymentSummary);

        $sellReturnQuery = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell_return')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->select('t.created_by', DB::raw('SUM(t.final_total) as amount'))
            ->groupBy('t.created_by')
            ->pluck('amount', 'created_by');
        $sellReturnByLocationQuery = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell_return')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->select('t.location_id', DB::raw('SUM(t.final_total) as amount'))
            ->groupBy('t.location_id')
            ->pluck('amount', 'location_id');

        $grandPaid = 0.0;
        $grandExpenses = (float) $expenseQuery->sum();
        $grandSellReturn = (float) $sellReturnQuery->sum();
        $paymentWithExpenses = $paymentSummaryMap;
        $actualIncomeByPayment = [];

        foreach ($rows as &$row) {
            $cashierId = (int) $row['cashier_id'];
            $expenses = (float) ($expenseQuery[$cashierId] ?? 0);
            $sellReturn = (float) ($sellReturnQuery[$cashierId] ?? 0);
            $actualIncome = (float) $row['paid'] - $expenses - $sellReturn;
            $row['expenses'] = $expenses;
            $row['sell_return'] = $sellReturn;
            $row['actual_income'] = $actualIncome;

            $grandPaid += (float) $row['paid'];
        }
        unset($row);
        $grandPaid += $moduleSaleTotals['paid'];
        $grandActualIncome = $grandPaid - $grandExpenses - $grandSellReturn;
        foreach ($locationRows as &$row) {
            $locationId = (int) $row['location_id'];
            $expenses = (float) ($expenseByLocationQuery[$locationId] ?? 0);
            $sellReturn = (float) ($sellReturnByLocationQuery[$locationId] ?? 0);
            $actualIncome = (float) $row['paid'] - $expenses - $sellReturn;
            $row['expenses'] = $expenses;
            $row['sell_return'] = $sellReturn;
            $row['actual_income'] = $actualIncome;
        }
        unset($row);

        $paymentWithExpenses['expenses'] = $grandExpenses;
        $paymentSummary = [];
        foreach ($paymentSummaryMap as $method => $amount) {
            $paymentSummary[] = [
                'name' => (string) ($paymentTypes[$method] ?? $method),
                'amount' => (float) $amount,
                'qty' => (float) ($paymentQtySummaryMap[$method] ?? 0),
            ];
        }
        $summaryTotals = $this->summaryTotals($userSummary, $locationSummary, $customerGroupSummary, $brandSummary, $paymentSummary);
        foreach ($paymentColumns as $method) {
            $sellPaidByMethod = (float) ($paymentSummaryMap[$method] ?? 0);
            $expenseByMethod = (float) ($expensePaymentSummaryMap[$method] ?? 0);
            $actualIncomeByPayment[$method] = $sellPaidByMethod - $expenseByMethod;
        }

        $sellLineQuery = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->leftJoin('business_locations as l', 'l.id', '=', 't.location_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->leftJoin('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('purchase_lines as pl', 'pl.id', '=', 'tsl.lot_no_line_id')
            ->whereIn('tsl.transaction_id', $transactionIds)
            ->select(
                'tsl.transaction_id',
                'tsl.quantity',
                'tsl.unit_price_before_discount',
                'tsl.unit_price_inc_tax',
                'tsl.line_discount_amount',
                DB::raw('((tsl.quantity * tsl.unit_price_before_discount) - COALESCE(tsl.line_discount_amount,0)) as line_total'),
                'p.name as product_name',
                'v.sub_sku',
                'pl.lot_number',
                't.id as txn_id',
                't.transaction_date',
                't.invoice_no',
                't.created_by',
                't.location_id',
                't.final_total',
                't.additional_notes',
                't.staff_note',
                'c.mobile as customer_phone',
                DB::raw("COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') as customer_group_name")
            )
            ->orderBy('l.name')
            ->orderBy('t.transaction_date', 'desc')
            ->orderBy('t.id', 'desc');

        $sellLineTotal = (clone $sellLineQuery)->count();
        $sellLineRows = $sellLineQuery
            ->limit(self::DETAIL_ROW_LIMIT)
            ->get();

        $detailRows = [];
        foreach ($sellLineRows as $line) {
            $txnId = (int) $line->txn_id;
            $paid = 0.0;
            $paymentCols = [];
            foreach ($paymentColumns as $method) {
                $amount = (float) ($paymentByTransaction[$txnId][$method] ?? 0);
                $paymentCols[$method] = $amount;
                $paid += $amount;
            }

            $customerGroupName = trim((string) ($line->customer_group_name ?? ''));
            $customerGroupLabel = $customerGroupName === 'រំលស់'
                ? 'រំលស់'
                : ($customerGroupName === 'អ៊ីអន' ? 'អ៊ីអន' : 'លក់');
            $customerGroupSort = $customerGroupName === 'អ៊ីអន'
                ? 2
                : ($customerGroupName === 'រំលស់' ? 3 : 1);
            $sellNote = trim((string) ($line->additional_notes ?? ''));
            $staffNoteLast4 = substr(trim((string) ($line->staff_note ?? '')), -4);
            $itText = trim($sellNote . ($sellNote !== '' && $staffNoteLast4 !== '' ? '-' : '') . $staffNoteLast4);

            $detailRows[] = [
                'transaction_id' => $txnId,
                'date' => Carbon::parse($line->transaction_date)->format('Y-m-d H:i'),
                'invoice_no' => (string) ($line->invoice_no ?: ('#' . $txnId)),
                'i_t' => $itText !== '' ? $itText : '-',
                'cashier_id' => (int) $line->created_by,
                'cashier_name' => (string) ($cashierMap[(int) $line->created_by] ?? 'N/A'),
                'sell_note_number' => $this->numericText($sellNote),
                'location_name' => (string) ($locationMap[$line->location_id] ?? 'N/A'),
                'customer_name' => (string) ($line->customer_name ?? 'Walk-In Customer'),
                'phone_number' => $this->resolveCustomerPhone($line->customer_phone ?? null, $line->staff_note ?? null),
                'customer_group_name' => $customerGroupLabel,
                'customer_group_sort' => $customerGroupSort,
                'sku' => (string) ($line->sub_sku ?? '-'),
                'lot_number' => (string) ($line->lot_number ?? '-'),
                'product_name' => (string) ($line->product_name ?? '-'),
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) ($line->unit_price_before_discount ?? $line->unit_price_inc_tax ?? 0),
                'line_total' => (float) $line->line_total,
                'discount' => (float) ($line->line_discount_amount ?? 0),
                'final_total' => (float) $line->final_total,
                'paid' => $paid,
                'payments' => $paymentCols,
                'due' => (float) $line->final_total - $paid,
            ];
        }

        usort($detailRows, function ($a, $b) {
            return [
                $a['location_name'] ?? '',
                $a['customer_group_sort'],
                $a['customer_name'],
                $b['date'],
            ] <=> [
                $b['location_name'] ?? '',
                $b['customer_group_sort'],
                $b['customer_name'],
                $a['date'],
            ];
        });

        $groupedDetailRows = [];
        $lastCustomerGroup = null;
        $paymentShownTransactions = [];
        foreach ($detailRows as $row) {
            if ($lastCustomerGroup !== $row['customer_group_name']) {
                $groupedDetailRows[] = [
                    'row_type' => 'customer_group_separator',
                    'customer_group_name' => $row['customer_group_name'],
                ];
                $lastCustomerGroup = $row['customer_group_name'];
            }

            if (($row['row_source'] ?? 'sell') !== 'loan_payment') {
                $txnId = (int) ($row['transaction_id'] ?? 0);
                if (isset($paymentShownTransactions[$txnId])) {
                    $row['paid'] = 0.0;
                    $row['due'] = 0.0;
                    foreach ($paymentColumns as $method) {
                        $row['payments'][$method] = 0.0;
                    }
                } else {
                    $paymentShownTransactions[$txnId] = true;
                }
            }

            $row['row_type'] = 'sale';
            $groupedDetailRows[] = $row;
        }
        $collectionPaymentDetailRows = $this->normalizeCollectionPaymentRows($loanPaymentData['detail_rows'] ?? [], $paymentColumns, $cashierMap, $locationMap, $paymentTypes);
        $moduleDashboardRows = collect();
        foreach (['#accessory_sales_detail_tab' => $accessorySaleSummaryRows, '#service_sales_detail_tab' => $serviceSaleSummaryRows] as $tabTarget => $moduleRows) {
            $moduleDashboardRows = $moduleDashboardRows->merge(
                collect($moduleRows)->groupBy(fn ($row) => (string) ($row['location_name'] ?? 'N/A'))
                    ->map(function ($rows, $locationName) use ($tabTarget, $filters, $paymentColumns) {
                        $payments = [];
                        foreach ($paymentColumns as $method) {
                            $payments[$method] = $rows->sum(fn ($row) => (float) data_get($row, 'payments.' . $method, 0));
                        }

                        return [
                            'label' => $locationName,
                            'tab_target' => $tabTarget,
                            'qty_total' => ($filters['qty_type'] ?? 'sold_quantity') === 'sold_quantity'
                                ? $rows->sum(fn ($row) => (float) ($row['quantity'] ?? 0))
                                : $rows->pluck('transaction_id')->filter()->unique()->count(),
                            'total' => $rows->sum(fn ($row) => (float) ($row['line_total'] ?? 0)),
                            'paid' => $rows->sum(fn ($row) => (float) ($row['paid'] ?? 0)),
                            'due' => $rows->sum(fn ($row) => (float) ($row['due'] ?? 0)),
                            'payments' => $payments,
                        ];
                    })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()
            );
        }

        $branchDashboards = $this->buildBranchDashboards(
            $locationRows,
            $accessorySaleSummaryRows,
            $serviceSaleSummaryRows,
            $expenseByLocationQuery->all(),
            $expensePaymentByLocation,
            $sellReturnByLocationQuery->all(),
            $paymentColumns,
            $filters,
            $locationMap->all()
        );

        return [
            'module_dashboard_rows' => $moduleDashboardRows->values()->all(),
            'branch_dashboards' => $branchDashboards,
            'rows' => $rows,
            'rows_by_location' => $locationRows,
            'payment_columns' => $paymentColumns,
            'payment_labels' => $paymentTypes,
            'grand_total' => $grandTotal,
            'grand_paid' => $grandPaid,
            'grand_expenses' => $grandExpenses,
            'grand_sell_return' => $grandSellReturn,
            'grand_actual_income' => $grandActualIncome,
            'grand_due' => $grandDue,
            'customer_due_payment_total' => $includeCustomerDuePayments ? (float) ($customerDuePaymentData['total'] ?? 0) : 0.0,
            'collection_payment_total' => (float) ($loanPaymentData['total'] ?? 0),
            'payment_with_expenses' => $paymentWithExpenses,
            'expense_payment_summary' => $expensePaymentSummaryMap,
            'actual_income_payment_summary' => $actualIncomeByPayment,
            'summary_user' => $userSummary,
            'summary_location' => $locationSummary,
            'summary_customer_group' => $customerGroupSummary,
            'summary_brand' => $brandSummary,
            'summary_payment' => $paymentSummary,
            'summary_totals' => $summaryTotals,
            'detail_rows' => $groupedDetailRows,
            'collection_payment_detail_rows' => $collectionPaymentDetailRows,
            'customer_due_payment_detail_rows' => $customerDuePaymentData['rows'],
            'due_customer_detail_rows' => $dueCustomerData['rows'],
            'expense_detail_rows' => $expenseDetailResult['rows'],
            'accessory_sale_detail_rows' => $accessorySaleDetailResult['rows'],
            'service_sale_detail_rows' => $serviceSaleDetailResult['rows'],
            'detail_meta' => [
                'limit' => self::DETAIL_ROW_LIMIT,
                'main_total' => $sellLineTotal,
                'main_displayed' => count($detailRows),
                'collection_payment_total' => $loanPaymentData['detail_total'],
                'collection_payment_displayed' => count($collectionPaymentDetailRows),
                'customer_due_payment_total' => $customerDuePaymentData['detail_total'],
                'customer_due_payment_displayed' => count($customerDuePaymentData['rows']),
                'due_customer_total' => $dueCustomerData['detail_total'],
                'due_customer_displayed' => count($dueCustomerData['rows']),
                'expense_total' => $expenseDetailResult['total'],
                'expense_displayed' => count($expenseDetailResult['rows']),
                'accessory_total' => $accessorySaleDetailResult['total'],
                'accessory_displayed' => count($accessorySaleDetailResult['rows']),
                'service_total' => $serviceSaleDetailResult['total'],
                'service_displayed' => count($serviceSaleDetailResult['rows']),
            ],
        ];
    }

    private function mergeModuleSummaryRows($moduleRows, array $filters, array &$userSummary, array &$locationSummary, array &$customerGroupSummary, array &$brandSummary): void
    {
        $qtyType = (string) ($filters['qty_type'] ?? 'sold_quantity');
        $counted = [
            'user' => [],
            'location' => [],
            'customer_group' => [],
            'brand' => [],
        ];

        $upsert = function (&$rows, string $key, array $base, float $amount, float $qty) {
            if (! isset($rows[$key])) {
                $rows[$key] = $base + ['amount' => 0.0, 'qty' => 0.0];
            }

            $rows[$key]['amount'] += $amount;
            $rows[$key]['qty'] += $qty;
        };

        $userMap = [];
        foreach ($userSummary as $row) {
            $key = ((int) ($row['id'] ?? 0)) !== 0 ? 'id:' . (int) $row['id'] : 'name:' . strtolower((string) ($row['name'] ?? 'N/A'));
            $userMap[$key] = $row;
        }

        $locationMap = [];
        foreach ($locationSummary as $row) {
            $key = ((int) ($row['id'] ?? 0)) > 0 ? 'main:id:' . (int) $row['id'] : 'main:name:' . strtolower((string) ($row['name'] ?? 'N/A'));
            $locationMap[$key] = $row;
        }

        $customerGroupMap = [];
        foreach ($customerGroupSummary as $row) {
            $key = strtolower((string) ($row['name'] ?? 'áž›áž€áŸ‹'));
            $customerGroupMap[$key] = $row;
        }

        $brandMap = [];
        foreach ($brandSummary as $row) {
            $key = strtolower((string) ($row['name'] ?? 'No Brand'));
            $brandMap[$key] = $row;
        }

        foreach ($moduleRows as $row) {
            if (($row['row_type'] ?? 'sale') !== 'sale') {
                continue;
            }

            $transactionKey = (string) ($row['module_prefix'] ?? 'module') . ':' . (int) ($row['transaction_id'] ?? 0);
            $amount = (float) ($row['line_total'] ?? 0);
            $soldQty = (float) ($row['quantity'] ?? 0);

            $userKey = ((int) ($row['cashier_id'] ?? 0)) !== 0 ? 'id:' . (int) $row['cashier_id'] : 'name:' . strtolower((string) ($row['cashier_name'] ?? 'N/A'));
            $userQty = $qtyType === 'invoice_count'
                ? (isset($counted['user'][$userKey][$transactionKey]) ? 0.0 : 1.0)
                : $soldQty;
            $counted['user'][$userKey][$transactionKey] = true;
            $upsert($userMap, $userKey, [
                'id' => (int) ($row['cashier_id'] ?? 0),
                'name' => (string) ($row['cashier_name'] ?? 'N/A'),
            ], $amount, $userQty);

            $modulePrefix = (string) ($row['module_prefix'] ?? 'module');
            $locationName = (string) ($row['location_name'] ?? 'N/A');
            if (in_array($modulePrefix, ['accessory', 'service'], true)) {
                $moduleLabel = ucfirst($modulePrefix);
                if (! str_contains(strtolower($locationName), strtolower($moduleLabel))) {
                    $locationName .= ' (' . $moduleLabel . ')';
                }
            }

            $locationKey = ((int) ($row['location_id'] ?? 0)) > 0
                ? $modulePrefix . ':id:' . (int) $row['location_id']
                : $modulePrefix . ':name:' . strtolower($locationName);
            $locationQty = $qtyType === 'invoice_count'
                ? (isset($counted['location'][$locationKey][$transactionKey]) ? 0.0 : 1.0)
                : $soldQty;
            $counted['location'][$locationKey][$transactionKey] = true;
            $upsert($locationMap, $locationKey, [
                'id' => $this->moduleLocationFilterId((int) ($row['location_id'] ?? 0), $modulePrefix),
                'name' => $locationName,
            ], $amount, $locationQty);

            $customerGroupName = (string) ($row['customer_group_name'] ?? 'áž›áž€áŸ‹');
            $customerGroupKey = strtolower($customerGroupName);
            $customerGroupQty = $qtyType === 'invoice_count'
                ? (isset($counted['customer_group'][$customerGroupKey][$transactionKey]) ? 0.0 : 1.0)
                : $soldQty;
            $counted['customer_group'][$customerGroupKey][$transactionKey] = true;
            $upsert($customerGroupMap, $customerGroupKey, [
                'name' => $customerGroupName,
                'sort' => ['áž›áž€áŸ‹' => 1, 'áž¢áŸŠáž¸áž¢áž“' => 2, 'ážšáŸ†áž›ážŸáŸ‹' => 3, 'áž”áž„áŸ‹áž”áŸ’ážšáž¶áž€áŸ‹' => 4][$customerGroupName] ?? 1,
            ], $amount, $customerGroupQty);

            $brandName = (string) ($row['brand_name'] ?? 'No Brand');
            $brandKey = strtolower($brandName);
            $brandQty = $qtyType === 'invoice_count'
                ? (isset($counted['brand'][$brandKey][$transactionKey]) ? 0.0 : 1.0)
                : $soldQty;
            $counted['brand'][$brandKey][$transactionKey] = true;
            $upsert($brandMap, $brandKey, [
                'id' => (int) ($row['brand_id'] ?? 0),
                'name' => $brandName,
            ], $amount, $brandQty);
        }

        $userSummary = array_values($userMap);
        usort($userSummary, fn ($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        $locationSummary = array_values($locationMap);
        usort($locationSummary, fn ($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        $customerGroupSummary = array_values($customerGroupMap);
        usort($customerGroupSummary, fn ($a, $b) => ($a['sort'] ?? 1) <=> ($b['sort'] ?? 1));
        $brandSummary = array_values($brandMap);
        usort($brandSummary, fn ($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    }

    private function summaryTotals(array $userSummary, array $locationSummary, array $customerGroupSummary, array $brandSummary, array $paymentSummary): array
    {
        return [
            'user' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $userSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $userSummary)),
            ],
            'location' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $locationSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $locationSummary)),
            ],
            'customer_group' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $customerGroupSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $customerGroupSummary)),
            ],
            'brand' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $brandSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $brandSummary)),
            ],
            'payment' => [
                'amount' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $paymentSummary)),
                'qty' => array_sum(array_map(fn ($r) => (float) ($r['qty'] ?? 0), $paymentSummary)),
            ],
        ];
    }

    private function moduleSaleTotals($moduleRows, array $paymentColumns): array
    {
        $totals = [
            'total' => 0.0,
            'paid' => 0.0,
            'due' => 0.0,
            'payments' => [],
            'payment_qty' => [],
        ];

        foreach ($moduleRows as $row) {
            if (($row['row_type'] ?? 'sale') !== 'sale') {
                continue;
            }

            $totals['total'] += (float) ($row['line_total'] ?? 0);
            $totals['paid'] += (float) ($row['paid'] ?? 0);
            $totals['due'] += (float) ($row['due'] ?? 0);

            foreach ($paymentColumns as $method) {
                $amount = (float) data_get($row, 'payments.' . $method, 0);
                if (abs($amount) < 0.00001) {
                    continue;
                }

                $totals['payments'][$method] = ($totals['payments'][$method] ?? 0) + $amount;
                $totals['payment_qty'][$method] = ($totals['payment_qty'][$method] ?? 0) + (float) ($row['quantity'] ?? 0);
            }
        }

        return $totals;
    }

    private function getModuleSaleDetailRows(string $connection, string $modulePrefix, array $filters, array $paymentColumns, ?int $limit): array
    {
        if (! empty($filters['user_ids'])) {
            $filters['user_ids'] = $this->moduleCashierIds($filters['user_ids'], $modulePrefix);
            if (empty($filters['user_ids'])) {
                return ['rows' => [], 'total' => 0];
            }
        }
        $filters['location_ids'] = $this->moduleLocationIds($filters['location_ids'], $modulePrefix);
        if (empty($filters['location_ids'])) {
            return ['rows' => [], 'total' => 0];
        }

        if (! $this->hasRequiredReportTables($connection, ['transactions', 'transaction_sell_lines'])) {
            return ['rows' => [], 'total' => 0];
        }

        $db = DB::connection($connection);
        $businessId = 1;

        $transactionIds = $db->table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                $query->where('t.payment_status', $filters['payment_status']);
            })
            ->when(! empty($filters['payment_methods']) && $this->hasRequiredReportTables($connection, ['transaction_payments']), function ($query) use ($filters) {
                $query->whereExists(function ($sub) use ($filters) {
                    $sub->select(DB::raw(1))
                        ->from('transaction_payments as tpf')
                        ->whereColumn('tpf.transaction_id', 't.id')
                        ->whereIn('tpf.method', $filters['payment_methods']);
                });
            })
            ->pluck('t.id')
            ->all();

        if (empty($transactionIds)) {
            return ['rows' => [], 'total' => 0];
        }

        $locationMap = $this->hasRequiredReportTables($connection, ['business_locations'])
            ? $db->table('business_locations')->pluck('name', 'id')
            : collect();
        $hasLocationTable = $this->hasRequiredReportTables($connection, ['business_locations']);

        $query = $db->table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->when($hasLocationTable, function ($query) {
                $query->leftJoin('business_locations as l', 'l.id', '=', 't.location_id');
            })
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->leftJoin('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->whereIn('tsl.transaction_id', $transactionIds)
            ->select(
                'tsl.transaction_id',
                'tsl.quantity',
                'tsl.unit_price_before_discount',
                'tsl.unit_price_inc_tax',
                'tsl.line_discount_amount',
                DB::raw('((tsl.quantity * tsl.unit_price_before_discount) - COALESCE(tsl.line_discount_amount,0)) as line_total'),
                'p.name as product_name',
                'v.sub_sku',
                't.id as txn_id',
                't.transaction_date',
                't.invoice_no',
                't.created_by',
                't.location_id',
                't.final_total',
                't.additional_notes',
                't.staff_note',
                'p.brand_id',
                'c.mobile as customer_phone',
                DB::raw("COALESCE(NULLIF(TRIM(b.name), ''), 'No Brand') as brand_name"),
                DB::raw("COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') as customer_group_name")
            )
            ->when($hasLocationTable, function ($query) {
                $query->orderBy('l.name');
            }, function ($query) {
                $query->orderBy('t.location_id');
            })
            ->orderBy('t.transaction_date', 'desc')
            ->orderBy('t.id', 'desc');

        $total = (clone $query)->count();
        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = $query->get();

        $paymentByTransaction = [];
        $displayTransactionIds = $rows->pluck('txn_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (! empty($displayTransactionIds) && $this->hasRequiredReportTables($connection, ['transaction_payments'])) {
            $paymentRows = $db->table('transaction_payments as tp')
                ->whereIn('tp.transaction_id', $displayTransactionIds)
                ->select('tp.transaction_id', 'tp.method', DB::raw('SUM(tp.amount) as amount'))
                ->groupBy('tp.transaction_id', 'tp.method')
                ->get();

            foreach ($paymentRows as $paymentRow) {
                $paymentByTransaction[(int) $paymentRow->transaction_id][(string) $paymentRow->method] = (float) $paymentRow->amount;
            }
        }

        $cashierMap = collect();
        $displayCashierIds = $rows->pluck('created_by')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if (! empty($displayCashierIds) && $this->hasRequiredReportTables($connection, ['users'])) {
            $cashierMap = $db->table('users')
                ->whereIn('id', $displayCashierIds)
                ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"))
                ->pluck('name', 'id');
        }

        $detailRows = [];
        $paymentShownTransactions = [];
        foreach ($rows as $line) {
            $txnId = (int) $line->txn_id;
            $paid = 0.0;
            $paymentCols = [];
            foreach ($paymentColumns as $method) {
                $amount = (float) ($paymentByTransaction[$txnId][$method] ?? 0);
                $paymentCols[$method] = $amount;
                $paid += $amount;
            }

            $isDuplicateTransactionLine = isset($paymentShownTransactions[$txnId]);
            if ($isDuplicateTransactionLine) {
                $paid = 0.0;
                foreach ($paymentColumns as $method) {
                    $paymentCols[$method] = 0.0;
                }
            } else {
                $paymentShownTransactions[$txnId] = true;
            }

            $customerGroupName = trim((string) ($line->customer_group_name ?? ''));
            $customerGroupLabel = $customerGroupName === 'រំលស់'
                ? 'រំលស់'
                : ($customerGroupName === 'អ៊ីអន' ? 'អ៊ីអន' : 'លក់');
            $sellNote = trim((string) ($line->additional_notes ?? ''));
            $staffNoteLast4 = substr(trim((string) ($line->staff_note ?? '')), -4);
            $itText = trim($sellNote . ($sellNote !== '' && $staffNoteLast4 !== '' ? '-' : '') . $staffNoteLast4);

            $detailRows[] = [
                'row_type' => 'sale',
                'row_source' => $modulePrefix . '_sale',
                'module_prefix' => $modulePrefix,
                'transaction_id' => $txnId,
                'date' => Carbon::parse($line->transaction_date)->format('Y-m-d H:i'),
                'invoice_no' => (string) ($line->invoice_no ?: ('#' . $txnId)),
                'i_t' => $itText !== '' ? $itText : '-',
                'cashier_id' => $this->moduleCashierFilterId((int) $line->created_by, $modulePrefix),
                'cashier_name' => (string) ($cashierMap[(int) $line->created_by] ?? 'N/A') . ' (' . ucfirst($modulePrefix) . ')',
                'sell_note_number' => $this->numericText($sellNote),
                'location_id' => (int) $line->location_id,
                'location_name' => (string) ($locationMap[$line->location_id] ?? 'N/A'),
                'customer_name' => (string) ($line->customer_name ?? 'Walk-In Customer'),
                'phone_number' => $this->resolveCustomerPhone($line->customer_phone ?? null, $line->staff_note ?? null),
                'customer_group_name' => $customerGroupLabel,
                'brand_id' => isset($line->brand_id) ? (int) $line->brand_id : 0,
                'brand_name' => (string) ($line->brand_name ?? 'No Brand'),
                'sku' => (string) ($line->sub_sku ?? '-'),
                'lot_number' => (string) ($line->lot_number ?? '-'),
                'product_name' => (string) ($line->product_name ?? '-'),
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) ($line->unit_price_before_discount ?? $line->unit_price_inc_tax ?? 0),
                'line_total' => (float) $line->line_total,
                'discount' => (float) ($line->line_discount_amount ?? 0),
                'paid' => $paid,
                'payments' => $paymentCols,
                'due' => $isDuplicateTransactionLine ? 0.0 : (float) $line->final_total - $paid,
            ];
        }

        return ['rows' => $detailRows, 'total' => $total];
    }

    private function getModulePaymentMethodsWithAmount(string $connection, array $filters): array
    {
        if (! $this->hasRequiredReportTables($connection, ['transactions', 'transaction_payments'])) {
            return [];
        }

        try {
            return DB::connection($connection)
                ->table('transaction_payments as tp')
                ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                ->where('t.business_id', 1)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
                ->whereIn('t.location_id', $filters['location_ids'])
                ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                    $query->whereIn('t.created_by', $filters['user_ids']);
                })
                ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                    $query->where('t.payment_status', $filters['payment_status']);
                })
                ->when(! empty($filters['payment_methods']), function ($query) use ($filters) {
                    $query->whereIn('tp.method', $filters['payment_methods']);
                })
                ->whereNotNull('tp.method')
                ->where('tp.method', '<>', '')
                ->select('tp.method', DB::raw('SUM(tp.amount) as amount'))
                ->groupBy('tp.method')
                ->havingRaw('ABS(SUM(tp.amount)) > 0.00001')
                ->pluck('tp.method')
                ->map(fn ($method) => (string) $method)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasRequiredReportTables(string $connection, array $tables): bool
    {
        try {
            foreach ($tables as $table) {
                if (! Schema::connection($connection)->hasTable($table)) {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    private function getCustomerDuePaymentData(array $filters, array $paymentTypes, int $limit): array
    {
        $empty = [
            'rows' => [],
            'summary_rows' => [],
            'detail_total' => 0,
            'methods' => [],
            'method_totals' => [],
            'cashier_ids' => [],
            'location_ids' => [],
            'total' => 0.0,
        ];

        if (! empty($filters['customer_group']) && $filters['customer_group'] !== 'Customer Payment') {
            return $empty;
        }

        $businessId = (int) session('user.business_id');

        $query = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('contacts as pc', 'pc.id', '=', 'tp.payment_for')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as pcg', 'pcg.id', '=', 'pc.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->leftJoin('business_locations as l', 'l.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 'tp.created_by')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(tp.paid_on)'), [$filters['start_date'], $filters['end_date']])
            ->whereRaw('DATE(t.transaction_date) < DATE(tp.paid_on)')
            ->whereIn('t.location_id', $filters['location_ids'])
            ->where('tp.amount', '>', 0)
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('tp.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['payment_methods']), function ($query) use ($filters) {
                $query->whereIn('tp.method', $filters['payment_methods']);
            })
            ->select(
                'tp.id as payment_id',
                'tp.transaction_id',
                'tp.paid_on',
                'tp.method',
                'tp.amount',
                'tp.payment_ref_no',
                'tp.note',
                'tp.created_by',
                't.invoice_no',
                't.transaction_date',
                't.final_total',
                't.location_id',
                't.staff_note',
                'l.name as location_name',
                DB::raw("COALESCE(NULLIF(TRIM(pc.mobile), ''), NULLIF(TRIM(c.mobile), '')) as customer_phone"),
                DB::raw("COALESCE(NULLIF(TRIM(pcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') as customer_group_name"),
                DB::raw("COALESCE(NULLIF(TRIM(pc.name), ''), NULLIF(TRIM(CONCAT(COALESCE(pc.first_name,''), ' ', COALESCE(pc.last_name,''))), ''), NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_name")
            )
            ->orderBy('tp.paid_on', 'desc')
            ->orderBy('tp.id', 'desc');

        $total = (clone $query)->count();
        $summaryRows = (clone $query)->get();
        $rows = $summaryRows->take($limit);
        $data = $empty;
        $data['detail_total'] = $total;
        if ($summaryRows->isEmpty()) {
            return $data;
        }

        $transactionIds = $summaryRows->pluck('transaction_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $paidBeforeByTransaction = DB::table('transaction_payments as tp')
            ->whereIn('tp.transaction_id', $transactionIds)
            ->whereRaw("DATE(tp.paid_on) < ?", [$filters['start_date']])
            ->select('tp.transaction_id', DB::raw('SUM(tp.amount) as amount'))
            ->groupBy('tp.transaction_id')
            ->pluck('amount', 'transaction_id');

        $displayedPaymentIds = $rows->pluck('payment_id')->map(fn ($id) => (int) $id)->flip();
        foreach ($summaryRows as $row) {
            $customerGroupName = trim((string) ($row->customer_group_name ?? ''));
            $method = (string) ($row->method ?? 'cash');
            $amount = (float) ($row->amount ?? 0);
            $transactionId = (int) ($row->transaction_id ?? 0);
            $invoiceTotal = (float) ($row->final_total ?? 0);
            $previousPaid = (float) ($paidBeforeByTransaction[$transactionId] ?? 0);
            $previousDue = max($invoiceTotal - $previousPaid, 0);
            $remainingDue = max($previousDue - $amount, 0);

            $paymentRow = [
                'payment_id' => (int) ($row->payment_id ?? 0),
                'transaction_id' => $transactionId,
                'date' => ! empty($row->paid_on) ? Carbon::parse($row->paid_on)->format('Y-m-d H:i') : '-',
                'receipt_no' => (string) ($row->payment_ref_no ?: ('#PMT' . ($row->payment_id ?? ''))),
                'invoice_no' => (string) ($row->invoice_no ?: ('#' . $transactionId)),
                'invoice_date' => ! empty($row->transaction_date) ? Carbon::parse($row->transaction_date)->format('Y-m-d H:i') : '-',
                'customer_name' => (string) ($row->customer_name ?? 'Walk-In Customer'),
                'customer_group_name' => $customerGroupName,
                'phone_number' => $this->resolveCustomerPhone($row->customer_phone ?? null, $row->staff_note ?? null),
                'location_id' => (int) ($row->location_id ?? 0),
                'location_name' => (string) ($row->location_name ?? 'N/A'),
                'cashier_id' => (int) ($row->created_by ?? 0),
                'cashier_name' => trim((string) ($row->cashier_name ?? '')) ?: 'N/A',
                'method' => $method,
                'method_label' => (string) ($paymentTypes[$method] ?? $method),
                'amount' => $amount,
                'invoice_total' => $invoiceTotal,
                'previous_due' => $previousDue,
                'remaining_due' => $remainingDue,
                'note' => (string) ($row->note ?? ''),
            ];
            $data['summary_rows'][] = $paymentRow;
            if ($displayedPaymentIds->has((int) ($row->payment_id ?? 0))) {
                $data['rows'][] = $paymentRow;
            }
            $data['methods'][$method] = $method;
            $data['method_totals'][$method] = ($data['method_totals'][$method] ?? 0) + $amount;
            $data['cashier_ids'][(int) ($row->created_by ?? 0)] = (int) ($row->created_by ?? 0);
            $data['location_ids'][(int) ($row->location_id ?? 0)] = (int) ($row->location_id ?? 0);
            $data['total'] += $amount;
        }

        $data['methods'] = array_values($data['methods']);
        $data['cashier_ids'] = array_values(array_filter($data['cashier_ids']));
        $data['location_ids'] = array_values(array_filter($data['location_ids']));

        return $data;
    }

    private function normalizeCustomerDuePaymentRows(array $rows, array $paymentColumns): array
    {
        return array_map(function ($row) use ($paymentColumns) {
            $payments = [];
            foreach ($paymentColumns as $method) {
                $payments[$method] = $method === ($row['method'] ?? '') ? (float) ($row['amount'] ?? 0) : 0.0;
            }
            $row['payments'] = $payments;

            return $row;
        }, $rows);
    }

    private function getDueCustomerData(array $filters, int $limit): array
    {
        $empty = [
            'rows' => [],
            'detail_total' => 0,
        ];

        if (($filters['customer_group'] ?? '') === self::COLLECTION_PAYMENT_GROUP) {
            return $empty;
        }

        $businessId = (int) session('user.business_id');
        $paidSubquery = DB::table('transaction_payments')
            ->select(
                'transaction_id',
                DB::raw('SUM(IF(is_return = 1, -1 * amount, amount)) as paid_amount')
            )
            ->groupBy('transaction_id');

        $query = DB::table('transactions as t')
            ->leftJoinSub($paidSubquery, 'paid', function ($join) {
                $join->on('paid.transaction_id', '=', 't.id');
            })
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->leftJoin('business_locations as l', 'l.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.location_id', $filters['location_ids'])
            ->whereRaw('(t.final_total - COALESCE(paid.paid_amount, 0)) > 0.00001')
            ->whereRaw(
                "CASE
                    WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                    WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                    ELSE ?
                END NOT IN (?, ?)",
                ['រំលស់', 'រំលស់', 'អ៊ីអន', 'អ៊ីអន', 'លក់', 'រំលស់', 'អ៊ីអន']
            )
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                $query->where('t.payment_status', $filters['payment_status']);
            })
            ->when(! empty($filters['customer_group']) && $filters['customer_group'] !== 'Customer Payment', function ($query) use ($filters) {
                $query->whereRaw(
                    "CASE
                        WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                        WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                        ELSE ?
                    END = ?",
                    ['រំលស់', 'រំលស់', 'អ៊ីអន', 'អ៊ីអន', 'លក់', $filters['customer_group']]
                );
            })
            ->select(
                't.id as transaction_id',
                't.invoice_no',
                't.transaction_date',
                't.final_total',
                't.payment_status',
                't.staff_note',
                't.additional_notes',
                't.location_id',
                't.created_by',
                DB::raw('COALESCE(paid.paid_amount, 0) as paid_amount'),
                'l.name as location_name',
                DB::raw("COALESCE(NULLIF(TRIM(c.mobile), ''), NULLIF(TRIM(c.landline), ''), NULLIF(TRIM(c.alternate_number), '')) as customer_phone"),
                DB::raw("COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_name")
            )
            ->orderBy('t.transaction_date', 'asc')
            ->orderBy('t.id', 'asc');

        $total = (clone $query)->count();
        $rows = (clone $query)->limit($limit)->get();
        $today = Carbon::today();

        return [
            'detail_total' => $total,
            'rows' => $rows->map(function ($row) use ($today) {
                $invoiceDate = Carbon::parse($row->transaction_date);
                $payByDate = $invoiceDate->copy()->addMonth();
                $daysRemaining = $today->diffInDays($payByDate, false);
                $status = $daysRemaining < 0
                    ? 'Overdue'
                    : ($daysRemaining <= 7 ? 'Due soon' : 'Remind staff');

                return [
                    'transaction_id' => (int) $row->transaction_id,
                    'date' => $invoiceDate->format('Y-m-d H:i'),
                    'invoice_no' => (string) ($row->invoice_no ?: ('#' . $row->transaction_id)),
                    'customer_name' => (string) ($row->customer_name ?? 'Walk-In Customer'),
                    'phone_number' => $this->resolveCustomerPhone($row->customer_phone ?? null, $row->staff_note ?? null),
                    'location_id' => (int) ($row->location_id ?? 0),
                    'location_name' => (string) ($row->location_name ?? 'N/A'),
                    'cashier_id' => (int) ($row->created_by ?? 0),
                    'cashier_name' => trim((string) ($row->cashier_name ?? '')) ?: 'N/A',
                    'invoice_total' => (float) ($row->final_total ?? 0),
                    'amount_paid' => (float) ($row->paid_amount ?? 0),
                    'remaining_due' => max((float) ($row->final_total ?? 0) - (float) ($row->paid_amount ?? 0), 0),
                    'pay_by_date' => $payByDate->format('Y-m-d'),
                    'days_remaining' => $daysRemaining,
                    'reminder_status' => $status,
                    'payment_status' => (string) ($row->payment_status ?? '-'),
                    'note' => (string) ($row->additional_notes ?? ''),
                ];
            })->all(),
        ];
    }

    private function normalizeCollectionPaymentRows(array $rows, array $paymentColumns, $cashierMap, $locationMap, array $paymentTypes): array
    {
        return array_map(function ($row) use ($paymentColumns, $cashierMap, $locationMap, $paymentTypes) {
            $method = (string) ($row['method'] ?? 'cash');
            $payments = [];
            foreach ($paymentColumns as $paymentColumn) {
                $payments[$paymentColumn] = $paymentColumn === $method ? (float) ($row['amount'] ?? 0) : 0.0;
            }

            $loanNumber = trim((string) ($row['loan_number'] ?? ''));
            $paymentRef = trim((string) ($row['payment_ref'] ?? ''));

            return [
                'payment_id' => (int) ($row['payment_id'] ?? 0),
                'date' => ! empty($row['date']) ? Carbon::parse($row['date'])->format('Y-m-d H:i') : '-',
                'receipt_no' => $paymentRef !== '' ? $paymentRef : ('#LP' . ($row['payment_id'] ?? '')),
                'customer_name' => (string) ($row['customer_name'] ?? 'Loan Customer'),
                'loan_number' => $loanNumber !== '' ? $loanNumber : '-',
                'location_id' => (int) ($row['location_id'] ?? 0),
                'location_name' => (string) ($locationMap[(int) ($row['location_id'] ?? 0)] ?? 'N/A'),
                'cashier_id' => (int) ($row['cashier_id'] ?? 0),
                'cashier_name' => (string) ($cashierMap[(int) ($row['cashier_id'] ?? 0)] ?? 'N/A'),
                'method' => $method,
                'method_label' => (string) ($paymentTypes[$method] ?? $method),
                'payments' => $payments,
                'amount' => (float) ($row['amount'] ?? 0),
            ];
        }, $rows);
    }

    private function getExpenseDetailRows(array $expenseTxnIds, array $paymentTypes, array $paymentColumns, int $limit): array
    {
        if (empty($expenseTxnIds)) {
            return ['rows' => [], 'total' => 0];
        }

        $query = DB::table('transactions as t')
            ->leftJoin('business_locations as l', 'l.id', '=', 't.location_id')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 't.expense_category_id')
            ->leftJoin('users as created_by_user', 'created_by_user.id', '=', 't.created_by')
            ->leftJoin('users as expense_for_user', 'expense_for_user.id', '=', 't.expense_for')
            ->whereIn('t.id', $expenseTxnIds)
            ->select(
                't.id',
                't.transaction_date',
                't.ref_no',
                't.final_total',
                't.payment_status',
                't.additional_notes',
                'l.name as location_name',
                'ec.name as category_name',
                DB::raw("TRIM(CONCAT(COALESCE(created_by_user.first_name,''), ' ', COALESCE(created_by_user.last_name,''))) as created_by_name"),
                DB::raw("TRIM(CONCAT(COALESCE(expense_for_user.first_name,''), ' ', COALESCE(expense_for_user.last_name,''))) as expense_for_name")
            )
            ->orderBy('l.name')
            ->orderBy('t.transaction_date', 'desc')
            ->orderBy('t.id', 'desc');

        $total = (clone $query)->count();
        $rows = $query
            ->limit($limit)
            ->get();

        $paymentsByTransaction = [];
        $displayTransactionIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (! empty($displayTransactionIds)) {
            $paymentRows = DB::table('transaction_payments as tp')
                ->whereIn('tp.transaction_id', $displayTransactionIds)
                ->select('tp.transaction_id', 'tp.method', DB::raw('SUM(tp.amount) as amount'))
                ->groupBy('tp.transaction_id', 'tp.method')
                ->get();

            foreach ($paymentRows as $paymentRow) {
                $transactionId = (int) $paymentRow->transaction_id;
                $method = (string) $paymentRow->method;
                $amount = (float) $paymentRow->amount;

                if (! isset($paymentsByTransaction[$transactionId])) {
                    $paymentsByTransaction[$transactionId] = [
                        'paid' => 0.0,
                        'methods' => [],
                    ];
                }

                $paymentsByTransaction[$transactionId]['paid'] += $amount;
                $paymentsByTransaction[$transactionId]['methods'][] = [
                    'key' => $method,
                    'label' => (string) ($paymentTypes[$method] ?? $method),
                    'amount' => $amount,
                ];
            }
        }

        return [
            'rows' => $rows->map(function ($row) use ($paymentsByTransaction, $paymentColumns) {
            $transactionId = (int) $row->id;
            $paymentInfo = $paymentsByTransaction[$transactionId] ?? ['paid' => 0.0, 'methods' => []];
            $paid = (float) ($paymentInfo['paid'] ?? 0);
            $total = (float) ($row->final_total ?? 0);
            $paymentAmounts = [];
            foreach ($paymentColumns as $method) {
                $paymentAmounts[$method] = 0.0;
            }
            foreach ($paymentInfo['methods'] ?? [] as $methodRow) {
                $methodLabel = (string) ($methodRow['key'] ?? '');
                if ($methodLabel !== '') {
                    $paymentAmounts[$methodLabel] = ($paymentAmounts[$methodLabel] ?? 0) + (float) ($methodRow['amount'] ?? 0);
                }
            }
            $methodText = collect($paymentInfo['methods'] ?? [])
                ->map(fn ($method) => $method['label'] . ': ' . $this->formatCurrency((float) $method['amount']))
                ->implode(', ');

            return [
                'transaction_id' => $transactionId,
                'date' => ! empty($row->transaction_date) ? Carbon::parse($row->transaction_date)->format('Y-m-d H:i') : '-',
                'ref_no' => (string) ($row->ref_no ?: ('#' . $transactionId)),
                'created_by_name' => trim((string) ($row->created_by_name ?? '')) ?: 'N/A',
                'expense_for_name' => trim((string) ($row->expense_for_name ?? '')) ?: '-',
                'location_name' => (string) ($row->location_name ?? 'N/A'),
                'category_name' => (string) ($row->category_name ?? 'Uncategorized'),
                'payment_status' => (string) ($row->payment_status ?? '-'),
                'payment_methods' => $methodText !== '' ? $methodText : '-',
                'payment_method_rows' => $paymentInfo['methods'] ?? [],
                'payments' => $paymentAmounts,
                'amount' => $total,
                'paid' => $paid,
                'due' => $total - $paid,
                'note' => (string) ($row->additional_notes ?? ''),
            ];
            })->values()->all(),
            'total' => $total,
        ];
    }

    public function formatCurrency(?float $value): string
    {
        if ($value === null) {
            return '$ -';
        }

        if (abs($value) < 0.00001) {
            return '$ -';
        }

        if ($value < 0) {
            return '$ (' . number_format(abs($value), 2) . ')';
        }

        return '$ ' . number_format($value, 2);
    }

    public function formatLocationQty(array $locationQtyMap, $locationMap): string
    {
        if (empty($locationQtyMap)) {
            return '-';
        }

        $parts = [];
        foreach ($locationQtyMap as $locationId => $qty) {
            $name = (string) ($locationMap[$locationId] ?? 'N/A');
            $formattedQty = rtrim(rtrim(number_format((float) $qty, 2), '0'), '.');
            $parts[] = (float) $qty > 0 ? $name . ' (' . $formattedQty . ')' : $name;
        }

        return implode(', ', $parts);
    }

    private function getLoanPaymentData(array $filters, array $paymentTypes): array
    {
        $empty = [
            'cashier_groups' => [],
            'location_groups' => [],
            'method_totals' => [],
            'methods' => [],
            'cashier_ids' => [],
            'location_ids' => [],
            'detail_rows' => [],
            'detail_total' => 0,
            'total' => 0.0,
        ];

        if (! empty($filters['customer_group']) && $filters['customer_group'] !== self::COLLECTION_PAYMENT_GROUP) {
            return $empty;
        }

        if (! $this->loanTableExists('loan_payments') || ! $this->loanTableExists('loans')) {
            return $empty;
        }

        $dateColumn = $this->loanColumnExists('loan_payments', 'paid_date') ? 'paid_date' : ($this->loanColumnExists('loan_payments', 'paid_at') ? 'paid_at' : null);
        if ($dateColumn === null) {
            return $empty;
        }

        $amountColumn = $this->loanColumnExists('loan_payments', 'total_paid_base')
            ? 'total_paid_base'
            : ($this->loanColumnExists('loan_payments', 'total_paid') ? 'total_paid' : 'amount');
        $userColumn = $this->loanColumnExists('loan_payments', 'received_by') ? 'received_by' : ($this->loanColumnExists('loan_payments', 'created_by') ? 'created_by' : null);
        $methodColumn = $this->loanColumnExists('loan_payments', 'channel')
            ? 'channel'
            : ($this->loanColumnExists('loan_payments', 'payment_method_snapshot') ? 'payment_method_snapshot' : null);
        $locationExpressions = [];
        if ($this->loanColumnExists('loans', 'main_location_id')) {
            $locationExpressions[] = 'l.main_location_id';
        }
        $joinLoanBusinessLocations = $this->loanTableExists('loan_business_locations')
            && $this->loanColumnExists('loans', 'business_location_id')
            && $this->loanColumnExists('loan_business_locations', 'main_location_id');
        if ($joinLoanBusinessLocations) {
            $locationExpressions[] = 'lbl.main_location_id';
        }
        if ($this->loanColumnExists('loans', 'business_location_id')) {
            $locationExpressions[] = 'l.business_location_id';
        }
        if (empty($locationExpressions)) {
            return $empty;
        }
        $locationExpression = 'COALESCE(' . implode(', ', $locationExpressions) . ')';

        $rows = DB::connection('mysql_loan')->table('loan_payments as p')
            ->join('loans as l', 'l.id', '=', 'p.loan_id')
            ->when($joinLoanBusinessLocations, fn ($query) => $query->leftJoin('loan_business_locations as lbl', 'lbl.id', '=', 'l.business_location_id'))
            ->whereBetween(DB::raw('DATE(p.' . $dateColumn . ')'), [$filters['start_date'], $filters['end_date']])
            ->whereIn(DB::raw($locationExpression), $filters['location_ids'])
            ->when(! empty($filters['user_ids']) && $userColumn !== null, fn ($query) => $query->whereIn('p.' . $userColumn, $filters['user_ids']))
            ->when($this->loanColumnExists('loan_payments', 'status'), fn ($query) => $query->where(function ($statusQuery) {
                $statusQuery->whereIn('p.status', ['paid', 'confirmed', ''])
                    ->orWhereNull('p.status');
            }))
            ->when($this->loanColumnExists('loans', 'loan_date') && $this->loanColumnExists('loans', 'down_payment'), function ($query) use ($dateColumn, $amountColumn) {
                $query->where(function ($paymentQuery) use ($dateColumn, $amountColumn) {
                    $paymentQuery->whereNull('l.down_payment')
                        ->orWhere('l.down_payment', '<=', 0)
                        ->orWhereRaw('DATE(p.' . $dateColumn . ') <> DATE(l.loan_date)')
                        ->orWhereRaw('ABS(p.' . $amountColumn . ' - l.down_payment) > 0.0001');
                });
            })
            ->when($this->loanColumnExists('loan_payments', 'deleted_at'), fn ($query) => $query->whereNull('p.deleted_at'))
            ->when($this->loanColumnExists('loans', 'deleted_at'), fn ($query) => $query->whereNull('l.deleted_at'))
            ->selectRaw(($userColumn ? 'p.' . $userColumn : '0') . ' as cashier_id')
            ->selectRaw($locationExpression . ' as location_id')
            ->selectRaw(($methodColumn ? 'p.' . $methodColumn : "'cash'") . ' as method')
            ->selectRaw('SUM(p.' . $amountColumn . ') as amount')
            ->selectRaw('COUNT(*) as qty')
            ->groupBy('cashier_id', 'location_id', 'method')
            ->get();

        $detailRowsQuery = DB::connection('mysql_loan')->table('loan_payments as p')
            ->join('loans as l', 'l.id', '=', 'p.loan_id')
            ->when($joinLoanBusinessLocations, fn ($query) => $query->leftJoin('loan_business_locations as lbl', 'lbl.id', '=', 'l.business_location_id'))
            ->whereBetween(DB::raw('DATE(p.' . $dateColumn . ')'), [$filters['start_date'], $filters['end_date']])
            ->whereIn(DB::raw($locationExpression), $filters['location_ids'])
            ->when(! empty($filters['user_ids']) && $userColumn !== null, fn ($query) => $query->whereIn('p.' . $userColumn, $filters['user_ids']))
            ->when($this->loanColumnExists('loan_payments', 'status'), fn ($query) => $query->where(function ($statusQuery) {
                $statusQuery->whereIn('p.status', ['paid', 'confirmed', ''])
                    ->orWhereNull('p.status');
            }))
            ->when($this->loanColumnExists('loans', 'loan_date') && $this->loanColumnExists('loans', 'down_payment'), function ($query) use ($dateColumn, $amountColumn) {
                $query->where(function ($paymentQuery) use ($dateColumn, $amountColumn) {
                    $paymentQuery->whereNull('l.down_payment')
                        ->orWhere('l.down_payment', '<=', 0)
                        ->orWhereRaw('DATE(p.' . $dateColumn . ') <> DATE(l.loan_date)')
                        ->orWhereRaw('ABS(p.' . $amountColumn . ' - l.down_payment) > 0.0001');
                });
            })
            ->when($this->loanColumnExists('loan_payments', 'deleted_at'), fn ($query) => $query->whereNull('p.deleted_at'))
            ->when($this->loanColumnExists('loans', 'deleted_at'), fn ($query) => $query->whereNull('l.deleted_at'))
            ->selectRaw('p.id as payment_id')
            ->selectRaw('p.' . $dateColumn . ' as paid_date')
            ->selectRaw(($userColumn ? 'p.' . $userColumn : '0') . ' as cashier_id')
            ->selectRaw($locationExpression . ' as location_id')
            ->selectRaw(($methodColumn ? 'p.' . $methodColumn : "'cash'") . ' as method')
            ->selectRaw('p.' . $amountColumn . ' as amount')
            ->selectRaw(($this->loanColumnExists('loan_payments', 'customer_name_snapshot') ? 'p.customer_name_snapshot' : ($this->loanColumnExists('loans', 'customer_name_snapshot') ? 'l.customer_name_snapshot' : "'Loan Customer'")) . ' as customer_name')
            ->selectRaw(($this->loanColumnExists('loan_payments', 'loan_number_snapshot') ? 'p.loan_number_snapshot' : ($this->loanColumnExists('loans', 'loan_number') ? 'l.loan_number' : 'l.id')) . ' as loan_number')
            ->selectRaw(($this->loanColumnExists('loan_payments', 'receipt_number') ? 'p.receipt_number' : ($this->loanColumnExists('loan_payments', 'payment_ref_no') ? 'p.payment_ref_no' : 'p.id')) . ' as payment_ref')
            ->orderBy('paid_date')
            ->orderBy('p.id');

        $loanDetailTotal = (clone $detailRowsQuery)->count();
        $detailRows = $detailRowsQuery
            ->limit(self::DETAIL_ROW_LIMIT)
            ->get();

        $data = $empty;
        $data['detail_total'] = $loanDetailTotal;
        foreach ($detailRows as $row) {
            $method = $this->normalizeLoanPaymentMethod((string) ($row->method ?? 'cash'), $paymentTypes);
            if (! empty($filters['payment_methods']) && ! in_array($method, $filters['payment_methods'], true)) {
                continue;
            }

            $data['detail_rows'][] = [
                'payment_id' => (int) ($row->payment_id ?? 0),
                'date' => $row->paid_date ?? null,
                'cashier_id' => (int) ($row->cashier_id ?? 0),
                'location_id' => (int) ($row->location_id ?? 0),
                'method' => $method,
                'amount' => (float) ($row->amount ?? 0),
                'customer_name' => (string) ($row->customer_name ?? 'Loan Customer'),
                'loan_number' => (string) ($row->loan_number ?? ''),
                'payment_ref' => (string) ($row->payment_ref ?? ''),
            ];
        }

        foreach ($rows as $row) {
            $cashierId = (int) ($row->cashier_id ?? 0);
            $locationId = (int) ($row->location_id ?? 0);
            if ($cashierId <= 0 || $locationId <= 0) {
                continue;
            }

            $method = $this->normalizeLoanPaymentMethod((string) ($row->method ?? 'cash'), $paymentTypes);
            if (! empty($filters['payment_methods']) && ! in_array($method, $filters['payment_methods'], true)) {
                continue;
            }

            $amount = (float) ($row->amount ?? 0);
            $qty = (float) ($row->qty ?? 0);
            if (abs($amount) < 0.00001 && $qty <= 0) {
                continue;
            }

            if (! isset($data['cashier_groups'][$cashierId])) {
                $data['cashier_groups'][$cashierId] = [
                    'name' => self::COLLECTION_PAYMENT_GROUP,
                    'sort' => 4,
                    'location_qty_map' => [],
                    'payments' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }
            if (! isset($data['location_groups'][$locationId])) {
                $data['location_groups'][$locationId] = [
                    'name' => self::COLLECTION_PAYMENT_GROUP,
                    'sort' => 4,
                    'qty_total' => 0.0,
                    'payments' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }

            $data['cashier_groups'][$cashierId]['location_qty_map'][$locationId] = ($data['cashier_groups'][$cashierId]['location_qty_map'][$locationId] ?? 0) + $qty;
            $data['cashier_groups'][$cashierId]['payments'][$method] = ($data['cashier_groups'][$cashierId]['payments'][$method] ?? 0) + $amount;
            $data['cashier_groups'][$cashierId]['paid'] += $amount;
            $data['location_groups'][$locationId]['qty_total'] += $qty;
            $data['location_groups'][$locationId]['payments'][$method] = ($data['location_groups'][$locationId]['payments'][$method] ?? 0) + $amount;
            $data['location_groups'][$locationId]['paid'] += $amount;
            $data['method_totals'][$method] = ($data['method_totals'][$method] ?? 0) + $amount;
            if (empty($filters['customer_group']) || $filters['customer_group'] === self::COLLECTION_PAYMENT_GROUP) {
                $data['methods'][$method] = $method;
            }
            $data['cashier_ids'][$cashierId] = $cashierId;
            $data['location_ids'][$locationId] = $locationId;
            if (empty($filters['customer_group']) || $filters['customer_group'] === self::COLLECTION_PAYMENT_GROUP) {
                $data['total'] += $amount;
            }
        }

        $data['methods'] = array_values($data['methods']);
        $data['cashier_ids'] = array_values($data['cashier_ids']);
        $data['location_ids'] = array_values($data['location_ids']);

        return $data;
    }

    private function normalizeLoanPaymentMethod(string $method, array $paymentTypes): string
    {
        $method = trim($method) ?: 'cash';
        if (array_key_exists($method, $paymentTypes)) {
            return $method;
        }

        $lower = strtolower($method);
        if (array_key_exists($lower, $paymentTypes)) {
            return $lower;
        }

        foreach ($paymentTypes as $key => $label) {
            if (strtolower((string) $label) === $lower) {
                return (string) $key;
            }
        }

        return $lower;
    }

    private function loanTableExists(string $table): bool
    {
        try {
            return Schema::connection('mysql_loan')->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loanColumnExists(string $table, string $column): bool
    {
        try {
            return Schema::connection('mysql_loan')->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveCustomerPhone($contactMobile, $staffNote): string
    {
        $contactMobile = $this->normalizePhoneText($contactMobile);
        if ($contactMobile !== '') {
            return $contactMobile;
        }

        $staffNote = (string) ($staffNote ?? '');
        if (preg_match('/\bMobile\s*:\s*([^\r\n]+)/i', $staffNote, $matches)) {
            return $this->normalizePhoneText($matches[1] ?? '');
        }

        $plainStaffNote = $this->normalizePhoneText($staffNote);
        if (preg_match('/^\+?[0-9][0-9\s().-]{5,}$/', $plainStaffNote)) {
            return $plainStaffNote;
        }

        return '';
    }

    private function normalizePhoneText($value): string
    {
        $value = trim((string) ($value ?? ''));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value, " \t\n\r\0\x0B:-*");

        return in_array(strtolower($value), ['', 'null', 'n/a', 'na', '-'], true) ? '' : $value;
    }

    private function validatedFilters(Request $request, array $defaultLocationIds, array $accessibleLocationIds): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer',
            'location_filter_applied' => 'nullable|boolean',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'integer',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string',
            'customer_group' => 'nullable|string',
            'payment_status' => 'nullable|in:paid,partial,due',
            'qty_type' => 'nullable|in:invoice_count,sold_quantity',
            'style_mode' => 'nullable|in:sheet,classic,classic_plain,view_report,business_location_report,branch_dashboard',
        ]);

        $accessibleLocationIds = array_values(array_unique(array_map('intval', $accessibleLocationIds)));
        $defaultLocationIds = array_values(array_intersect(array_unique(array_map('intval', $defaultLocationIds)), $accessibleLocationIds));
        $requestedLocationIds = ! empty($validated['location_ids']) ? array_values(array_unique(array_map('intval', $validated['location_ids']))) : [];
        $hasLocationSelection = ! empty($requestedLocationIds) || ! empty($validated['location_filter_applied']);
        $locationIds = $hasLocationSelection ? array_values(array_intersect($requestedLocationIds, $accessibleLocationIds)) : $defaultLocationIds;
        if (! $hasLocationSelection && empty($locationIds)) {
            $locationIds = $accessibleLocationIds;
        }

        return [
            'start_date' => ! empty($validated['start_date']) ? Carbon::parse($validated['start_date'])->format('Y-m-d') : $today,
            'end_date' => ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->format('Y-m-d') : $today,
            'location_ids' => $locationIds,
            'user_ids' => ! empty($validated['user_ids']) ? array_values(array_unique($validated['user_ids'])) : [],
            'brand_ids' => ! empty($validated['brand_ids']) ? array_values(array_unique($validated['brand_ids'])) : [],
            'payment_methods' => ! empty($validated['payment_methods']) ? array_values(array_unique($validated['payment_methods'])) : [],
            'customer_group' => trim((string) ($validated['customer_group'] ?? '')),
            'payment_status' => $validated['payment_status'] ?? '',
            'qty_type' => $validated['qty_type'] ?? 'sold_quantity',
            'style_mode' => $validated['style_mode'] ?? 'classic_plain',
        ];
    }

    private function defaultLocationIds(Request $request, int $businessId, array $accessibleLocationIds): array
    {
        $accessibleLocationIds = array_values(array_unique(array_map('intval', $accessibleLocationIds)));
        if (empty($accessibleLocationIds)) {
            return [];
        }

        if (Schema::hasTable('cash_registers')) {
            $registerLocationId = DB::table('cash_registers')
                ->where('business_id', $businessId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'open')
                ->orderByDesc('id')
                ->value('location_id');

            if (! empty($registerLocationId) && in_array((int) $registerLocationId, $accessibleLocationIds, true)) {
                return [(int) $registerLocationId];
            }
        }

        $permittedLocations = $request->user()->permitted_locations($businessId);
        if ($permittedLocations !== 'all') {
            $permittedLocationIds = array_values(array_unique(array_map('intval', (array) $permittedLocations)));
            $defaultLocationIds = array_values(array_intersect($permittedLocationIds, $accessibleLocationIds));

            if (! empty($defaultLocationIds)) {
                return $defaultLocationIds;
            }
        }

        return $accessibleLocationIds;
    }

    private function buildPaymentColumns(array $methodsWithAmount, array $paymentTypes): array
    {
        return array_keys($paymentTypes);
    }

    private function getStaticPaymentColumns(): array
    {
        $columns = config('localcashierreport.all_sale_static_payment_columns', []);

        return is_array($columns) ? $columns : [];
    }

    private function resolveStaticPaymentAmount(array $row, array $column): float
    {
        $payments = (array) ($row['payments'] ?? []);
        $sources = (array) ($column['source_methods'] ?? []);
        $amount = 0.0;
        foreach ($sources as $source) {
            $amount += (float) ($payments[$source] ?? 0);
        }

        return $amount;
    }

    private function numericText($value): string
    {
        $number = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';
        $number = ltrim($number, '0');

        return $number !== '' ? $number : '';
    }

    private function currencySymbol(): string
    {
        return (string) data_get(session('currency'), 'symbol', '$');
    }

    private function abortIfUninstalled(): void
    {
        abort_if(empty(System::getProperty('localcashierreport_version')), 404);
    }

    /**
     * Normalize any location name (POS main, -Accessory, -Service, -Expense) to its branch prefix before '-'.
     */
    private function extractBranchIdentity(string $locationName): array
    {
        $clean = preg_replace('/\s*\((Accessory|Service)\)$/iu', '', $locationName);
        $clean = preg_replace('/\p{Cf}/u', '', (string) $clean);
        $clean = trim(preg_replace('/\s+/u', ' ', (string) $clean));
        $prefix = trim((string) explode('-', $clean, 2)[0]);
        $name = $prefix !== '' ? $prefix : ($clean !== '' ? $clean : 'N/A');

        return [
            'key' => mb_strtolower($name),
            'name' => $name,
        ];
    }

    /**
     * Build independent per-branch Dashboard payloads grouped by '-' branch prefix across all sources.
     */
    private function buildBranchDashboards(
        array $locationRows,
        array $accessoryRows,
        array $serviceRows,
        array $expenseByLocation,
        array $expensePaymentByLocation,
        array $sellReturnByLocation,
        array $paymentColumns,
        array $filters,
        array $locationMap
    ): array {
        $branches = [];
        $zeroPayments = [];
        foreach ($paymentColumns as $method) {
            $zeroPayments[$method] = 0.0;
        }

        $ensureBranch = function (string $locationName) use (&$branches, $zeroPayments): string {
            $identity = $this->extractBranchIdentity($locationName);
            $key = $identity['key'];
            if (! isset($branches[$key])) {
                $branches[$key] = [
                    'branch_key' => $key,
                    'branch_name' => $identity['name'],
                    'location_ids' => [],
                    'location_labels' => [],
                    'customer_groups' => [],
                    'module_rows' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                    'payments' => $zeroPayments,
                    'expenses' => 0.0,
                    'expense_payments' => $zeroPayments,
                    'sell_return' => 0.0,
                    'actual_income' => 0.0,
                    'actual_income_payments' => $zeroPayments,
                ];
            }

            return $key;
        };

        $addBranchLocationTag = function (string $branchKey, int $filterLocationId, string $label) use (&$branches): void {
            if ($filterLocationId !== 0 && ! in_array($filterLocationId, $branches[$branchKey]['location_ids'], true)) {
                $branches[$branchKey]['location_ids'][] = $filterLocationId;
            }
            $label = trim($label);
            if ($label !== '' && ! in_array($label, $branches[$branchKey]['location_labels'], true)) {
                $branches[$branchKey]['location_labels'][] = $label;
            }
        };

        foreach ($locationRows as $locationRow) {
            $locId = (int) ($locationRow['location_id'] ?? 0);
            $locName = (string) ($locationRow['location_name'] ?? 'N/A');
            $branchKey = $ensureBranch($locName);
            $addBranchLocationTag($branchKey, $locId, $locName);

            foreach (($locationRow['customer_groups'] ?? []) as $groupKey => $groupRow) {
                $groupName = (string) ($groupRow['name'] ?? 'លក់');
                $sort = (int) ($groupRow['sort'] ?? 1);
                $rowKey = $sort . '|' . $locId . '|' . $groupName;
                $payments = $zeroPayments;
                foreach ($paymentColumns as $method) {
                    $payments[$method] = (float) ($groupRow['payments'][$method] ?? 0);
                }

                $branches[$branchKey]['customer_groups'][$rowKey] = [
                    'row_kind' => 'main',
                    'name' => $groupName,
                    'sort' => $sort,
                    'location_id' => $locId,
                    'location_name' => $locName,
                    'label' => $locName,
                    'qty_total' => (float) ($groupRow['qty_total'] ?? 0),
                    'total' => (float) ($groupRow['total'] ?? 0),
                    'paid' => (float) ($groupRow['paid'] ?? 0),
                    'due' => in_array($groupName, ['រំលស់', 'អ៊ីអន'], true) ? 0.0 : (float) ($groupRow['due'] ?? 0),
                    'payments' => $payments,
                    'tab_target' => null,
                ];
            }

            $branches[$branchKey]['total'] += (float) ($locationRow['total'] ?? 0);
            $branches[$branchKey]['paid'] += (float) ($locationRow['paid'] ?? 0);
            foreach ($paymentColumns as $method) {
                $branches[$branchKey]['payments'][$method] += (float) ($locationRow['payments'][$method] ?? 0);
            }
        }

        // Group Accessory & Service sale rows into their matching branch prefix (before '-')
        foreach ([
            'accessory' => ['#accessory_sales_detail_tab', $accessoryRows],
            'service' => ['#service_sales_detail_tab', $serviceRows],
        ] as $modulePrefix => [$tabTarget, $moduleRows]) {
            if (empty($moduleRows)) {
                continue;
            }
            $groupedByLocation = collect($moduleRows)->groupBy(fn ($row) => (int) ($row['location_id'] ?? 0));
            foreach ($groupedByLocation as $rawLocId => $locRows) {
                $firstRow = $locRows->first();
                $locName = (string) ($firstRow['location_name'] ?? 'N/A');
                $branchKey = $ensureBranch($locName);
                $filterLocId = $this->moduleLocationFilterId((int) $rawLocId, $modulePrefix);
                $addBranchLocationTag($branchKey, $filterLocId, $locName . ' (' . ucfirst($modulePrefix) . ')');

                $qty = ($filters['qty_type'] ?? 'sold_quantity') === 'sold_quantity'
                    ? (float) $locRows->sum(fn ($r) => (float) ($r['quantity'] ?? 0))
                    : (float) $locRows->pluck('transaction_id')->filter()->unique()->count();
                $total = (float) $locRows->sum(fn ($r) => (float) ($r['line_total'] ?? 0));
                $paid = (float) $locRows->sum(fn ($r) => (float) ($r['paid'] ?? 0));
                $due = (float) $locRows->sum(fn ($r) => (float) ($r['due'] ?? 0));
                $payments = $zeroPayments;
                foreach ($paymentColumns as $method) {
                    $amt = (float) $locRows->sum(fn ($r) => (float) data_get($r, 'payments.' . $method, 0));
                    $payments[$method] = $amt;
                    $branches[$branchKey]['payments'][$method] += $amt;
                }

                $branches[$branchKey]['total'] += $total;
                $branches[$branchKey]['paid'] += $paid;
                $branches[$branchKey]['due'] += $due;
                $branches[$branchKey]['module_rows'][] = [
                    'row_kind' => $modulePrefix,
                    'name' => 'លក់',
                    'sort' => 1,
                    'location_id' => $filterLocId,
                    'location_name' => $locName,
                    'label' => $locName,
                    'qty_total' => $qty,
                    'total' => $total,
                    'paid' => $paid,
                    'due' => $due,
                    'payments' => $payments,
                    'tab_target' => $tabTarget,
                ];
            }
        }

        // Main POS expenses & sell returns by location (rolled into branch prefix)
        $extraLocationIds = array_unique(array_merge(array_keys($expenseByLocation), array_keys($sellReturnByLocation)));
        if (! empty($extraLocationIds)) {
            $missingIds = array_diff($extraLocationIds, array_keys($locationMap));
            if (! empty($missingIds)) {
                $extraMap = DB::table('business_locations')->whereIn('id', $missingIds)->pluck('name', 'id')->all();
                $locationMap = $locationMap + $extraMap;
            }
        }
        foreach ($expenseByLocation as $locId => $expenseAmount) {
            $locId = (int) $locId;
            $locName = (string) ($locationMap[$locId] ?? 'N/A');
            $branchKey = $ensureBranch($locName);
            $addBranchLocationTag($branchKey, $locId, $locName);
            $branches[$branchKey]['expenses'] += (float) $expenseAmount;
            foreach ($paymentColumns as $method) {
                $branches[$branchKey]['expense_payments'][$method] += (float) ($expensePaymentByLocation[$locId][$method] ?? 0);
            }
        }
        foreach ($sellReturnByLocation as $locId => $returnAmount) {
            $locId = (int) $locId;
            $locName = (string) ($locationMap[$locId] ?? 'N/A');
            $branchKey = $ensureBranch($locName);
            $addBranchLocationTag($branchKey, $locId, $locName);
            $branches[$branchKey]['sell_return'] += (float) $returnAmount;
        }

        // Module (Accessory / Service) expenses & sell returns if recorded in their separate DBs
        foreach (['accessory', 'service'] as $modulePrefix) {
            $modLocationIds = $this->moduleLocationIds($filters['location_ids'] ?? [], $modulePrefix);
            if (empty($modLocationIds)) {
                continue;
            }
            $connection = (string) config($modulePrefix . '.database_connection', $modulePrefix);
            if (! $this->hasRequiredReportTables($connection, ['transactions', 'business_locations'])) {
                continue;
            }
            try {
                $modDb = DB::connection($connection);
                $modLocNames = $modDb->table('business_locations')->whereIn('id', $modLocationIds)->pluck('name', 'id')->all();
                $modUserIds = ! empty($filters['user_ids']) ? $this->moduleCashierIds($filters['user_ids'], $modulePrefix) : [];

                $modExpenseRows = $modDb->table('transactions as t')
                    ->where('t.business_id', 1)
                    ->whereIn('t.type', ['expense', 'sell_return'])
                    ->where('t.status', 'final')
                    ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
                    ->whereIn('t.location_id', $modLocationIds)
                    ->when(! empty($modUserIds), fn ($q) => $q->whereIn('t.created_by', $modUserIds))
                    ->select('t.location_id', 't.type', DB::raw('SUM(t.final_total) as amount'))
                    ->groupBy('t.location_id', 't.type')
                    ->get();

                foreach ($modExpenseRows as $row) {
                    $rawLocId = (int) $row->location_id;
                    $locName = (string) ($modLocNames[$rawLocId] ?? 'N/A');
                    $branchKey = $ensureBranch($locName);
                    $filterLocId = $this->moduleLocationFilterId($rawLocId, $modulePrefix);
                    $addBranchLocationTag($branchKey, $filterLocId, $locName . ' (' . ucfirst($modulePrefix) . ')');

                    if ($row->type === 'expense') {
                        $branches[$branchKey]['expenses'] += (float) $row->amount;
                    } else {
                        $branches[$branchKey]['sell_return'] += (float) $row->amount;
                    }
                }

                if ($this->hasRequiredReportTables($connection, ['transaction_payments'])) {
                    $modExpensePayments = $modDb->table('transaction_payments as tp')
                        ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                        ->where('t.business_id', 1)
                        ->where('t.type', 'expense')
                        ->where('t.status', 'final')
                        ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
                        ->whereIn('t.location_id', $modLocationIds)
                        ->when(! empty($modUserIds), fn ($q) => $q->whereIn('t.created_by', $modUserIds))
                        ->select('t.location_id', 'tp.method', DB::raw('SUM(tp.amount) as amount'))
                        ->groupBy('t.location_id', 'tp.method')
                        ->get();

                    foreach ($modExpensePayments as $row) {
                        $locName = (string) ($modLocNames[(int) $row->location_id] ?? 'N/A');
                        $branchKey = $ensureBranch($locName);
                        $method = (string) $row->method;
                        if (array_key_exists($method, $branches[$branchKey]['expense_payments'])) {
                            $branches[$branchKey]['expense_payments'][$method] += (float) $row->amount;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Keep branch dashboard resilient if optional module connection is unavailable.
            }
        }

        // Finalize row sorting, due, and actual income per branch (never combined across branches)
        foreach ($branches as &$branch) {
            $dueSum = 0.0;
            $rows = array_values($branch['customer_groups']);
            foreach ($rows as $r) {
                if (! in_array((int) ($r['sort'] ?? 0), [2, 3], true)) {
                    $dueSum += (float) ($r['due'] ?? 0);
                }
            }
            foreach ($branch['module_rows'] as $mr) {
                $dueSum += (float) ($mr['due'] ?? 0);
                $rows[] = $mr;
            }
            usort($rows, function ($a, $b) {
                $orderA = [
                    (int) ($a['sort'] ?? 99),
                    ($a['row_kind'] ?? 'main') === 'main' ? 0 : (($a['row_kind'] ?? '') === 'accessory' ? 1 : 2),
                    (string) ($a['label'] ?? ''),
                ];
                $orderB = [
                    (int) ($b['sort'] ?? 99),
                    ($b['row_kind'] ?? 'main') === 'main' ? 0 : (($b['row_kind'] ?? '') === 'accessory' ? 1 : 2),
                    (string) ($b['label'] ?? ''),
                ];

                return $orderA <=> $orderB;
            });

            $branch['rows'] = $rows;
            $branch['due'] = $dueSum;
            $branch['actual_income'] = (float) $branch['paid'] - (float) $branch['expenses'] - (float) $branch['sell_return'];
            foreach ($paymentColumns as $method) {
                $branch['actual_income_payments'][$method] =
                    (float) ($branch['payments'][$method] ?? 0) - (float) ($branch['expense_payments'][$method] ?? 0);
            }
            unset($branch['customer_groups'], $branch['module_rows']);
        }
        unset($branch);

        uasort($branches, fn ($a, $b) => strnatcasecmp((string) $a['branch_name'], (string) $b['branch_name']));

        return array_values($branches);
    }
}
