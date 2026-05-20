<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Exports\ArrayExport;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class LocalCashierReportController extends Controller
{
    public function __construct(private Util $util)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $filters = $this->validatedFilters($request, $locations->pluck('id')->all());
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
            'report' => $report,
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $filters = $this->validatedFilters($request, $locations->pluck('id')->all());
        $report = $this->getReportData($filters);

        $rows = [];
        foreach ($report['rows'] as $row) {
            $line = [
                'Cashier/User' => $row['cashier_name'],
                'Business Location (Qty)' => $row['location_qty_text'],
            ];
            foreach ($report['payment_columns'] as $method) {
                $line[$report['payment_labels'][$method] ?? $method] = $this->formatCurrency($row['payments'][$method] ?? null);
            }
            $line['Total'] = $this->formatCurrency($row['total']);
            $line['Due'] = $this->formatCurrency($row['due']);
            $rows[] = $line;
        }

        $fileName = 'local_cashier_report_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ArrayExport($rows), $fileName);
    }

    public function print(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $filters = $this->validatedFilters($request, $locations->pluck('id')->all());
        $report = $this->getReportData($filters);

        $selectedLocations = $locations->whereIn('id', $filters['location_ids'])->pluck('name')->all();

        return view('localcashierreport::print', [
            'businessName' => (string) session('business.name', config('app.name')),
            'filters' => $filters,
            'selectedLocations' => $selectedLocations,
            'currencySymbol' => $this->currencySymbol(),
            'khmerFontFamily' => config('localcashierreport.khmer_font_family'),
            'report' => $report,
        ]);
    }

    public function getAccessibleLocations(int $businessId)
    {
        $permitted = auth()->user()->permitted_locations($businessId);

        return DB::table('business_locations')
            ->where('business_id', $businessId)
            ->when($permitted !== 'all', function ($query) use ($permitted) {
                $query->whereIn('id', (array) $permitted);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getCashiers(int $businessId, array $locationIds = [])
    {
        $query = DB::table('users as u')
            ->where('u.business_id', $businessId)
            ->where('u.status', 'active')
            ->select(
                'u.id',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as name")
            )
            ->orderBy('u.first_name');

        if (! empty($locationIds)) {
            $query->whereExists(function ($sub) use ($businessId, $locationIds) {
                $sub->select(DB::raw(1))
                    ->from('transactions as t')
                    ->whereColumn('t.created_by', 'u.id')
                    ->where('t.business_id', $businessId)
                    ->where('t.type', 'sell')
                    ->where('t.status', 'final')
                    ->whereIn('t.location_id', $locationIds);
            });
        }

        return $query->get();
    }

    public function getReportData(array $filters): array
    {
        $businessId = (int) session('user.business_id');

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
            ->when(! empty($filters['customer_group']) && $filters['customer_group'] !== 'បង់ប្រាក់', function ($query) use ($filters) {
                $query->whereRaw(
                    "CASE
                        WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                        WHEN COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') = ? THEN ?
                        ELSE ?
                    END = ?",
                    ['រំលស់', 'រំលស់', 'អ៊ីអន', 'អ៊ីអន', 'លក់', $filters['customer_group']]
                );
            })
            ->when(! empty($filters['customer_group']) && $filters['customer_group'] === 'បង់ប្រាក់', function ($query) {
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

        if ($baseTransactions->isEmpty() && empty($loanPaymentData['detail_rows'])) {
            $paymentColumns = $this->buildPaymentColumns($loanPaymentData['methods'] ?? [], $paymentTypes);

            return [
                'rows' => [],
                'rows_by_location' => [],
                'payment_columns' => $paymentColumns,
                'payment_labels' => $paymentTypes,
                'grand_total' => 0.0,
                'grand_paid' => 0.0,
                'grand_expenses' => 0.0,
                'grand_sell_return' => 0.0,
                'grand_actual_income' => 0.0,
                'grand_due' => 0.0,
                'payment_with_expenses' => [],
                'expense_payment_summary' => [],
                'actual_income_payment_summary' => [],
                'detail_rows' => [],
                'summary_user' => [],
                'summary_location' => [],
                'summary_customer_group' => [],
                'summary_brand' => [],
                'summary_payment' => [],
            ];
        }

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

        $cashierIds = array_values(array_unique(array_merge($cashierIds, $loanPaymentData['cashier_ids'])));
        $locationIds = array_values(array_unique(array_merge($locationIds, $loanPaymentData['location_ids'])));
        $cashierMap = DB::table('users')
            ->whereIn('id', $cashierIds)
            ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"))
            ->pluck('name', 'id');
        $locationMap = DB::table('business_locations')->whereIn('id', array_values(array_unique(array_merge($filters['location_ids'], $locationIds))))->pluck('name', 'id');

        $paymentColumns = $this->buildPaymentColumns(array_keys($methodsWithAmount), $paymentTypes);

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
        }

        $rows = [];
        $grandTotal = 0.0;
        $grandDue = 0.0;
        $userSummary = [];
        foreach ($rowsByCashier as $cashierRow) {
            $cashierRow['location_qty_text'] = $this->formatLocationQty($cashierRow['location_qty_map'], $locationMap);
            $cashierRow['qty_total'] = array_sum($cashierRow['location_qty_map']);
            $cashierRow['due'] = (float) $cashierRow['total'] - (float) $cashierRow['paid'];

            foreach ($paymentColumns as $method) {
                if (! isset($cashierRow['payments'][$method])) {
                    $cashierRow['payments'][$method] = null;
                }
            }
            foreach ($cashierRow['customer_groups'] as &$customerGroupRow) {
                $customerGroupRow['location_qty_text'] = $this->formatLocationQty($customerGroupRow['location_qty_map'], $locationMap);
                $customerGroupRow['qty_total'] = array_sum($customerGroupRow['location_qty_map']);
                $customerGroupRow['due'] = (int) ($customerGroupRow['sort'] ?? 0) === 4
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
                'id' => (int) $cashierId,
                'name' => $cashierRow['cashier_name'],
                'amount' => (float) $cashierRow['total'],
                'qty' => (float) $cashierRow['qty_total'],
            ];
            $grandTotal += (float) $cashierRow['total'];
            $grandDue += (float) $cashierRow['due'];
        }

        usort($rows, fn ($a, $b) => strcmp($a['cashier_name'], $b['cashier_name']));

        $locationRows = [];
        foreach ($rowsByLocation as $locationRow) {
            $locationRow['due'] = (float) $locationRow['total'] - (float) $locationRow['paid'];
            foreach ($paymentColumns as $method) {
                if (! isset($locationRow['payments'][$method])) {
                    $locationRow['payments'][$method] = null;
                }
            }
            foreach ($locationRow['customer_groups'] as &$customerGroupRow) {
                $customerGroupRow['due'] = (int) ($customerGroupRow['sort'] ?? 0) === 4
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
                'qty' => (float) (($filters['qty_type'] ?? 'invoice_count') === 'invoice_count'
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
        if (! empty($expenseTxnIds)) {
            $expensePaymentRows = DB::table('transaction_payments as tp')
                ->whereIn('tp.transaction_id', $expenseTxnIds)
                ->select('tp.method', DB::raw('SUM(tp.amount) as amount'))
                ->groupBy('tp.method')
                ->get();

            foreach ($expensePaymentRows as $row) {
                $expensePaymentSummaryMap[(string) $row->method] = (float) $row->amount;
            }
        }

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
        $grandExpenses = 0.0;
        $grandSellReturn = 0.0;
        $grandActualIncome = 0.0;
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
            $grandExpenses += $expenses;
            $grandSellReturn += $sellReturn;
            $grandActualIncome += $actualIncome;
        }
        unset($row);
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
        $grandPaid += (float) ($loanPaymentData['total'] ?? 0);
        $grandActualIncome += (float) ($loanPaymentData['total'] ?? 0);
        foreach (($loanPaymentData['method_totals'] ?? []) as $method => $amount) {
            $paymentSummaryMap[$method] = ($paymentSummaryMap[$method] ?? 0) + (float) $amount;
            $paymentWithExpenses[$method] = ($paymentWithExpenses[$method] ?? 0) + (float) $amount;
        }
        foreach ($paymentColumns as $method) {
            $sellPaidByMethod = (float) ($paymentSummaryMap[$method] ?? 0);
            $expenseByMethod = (float) ($expensePaymentSummaryMap[$method] ?? 0);
            $actualIncomeByPayment[$method] = $sellPaidByMethod - $expenseByMethod;
        }

        $sellLineRows = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->leftJoin('products as p', 'p.id', '=', 'tsl.product_id')
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
                DB::raw("COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), '') as customer_group_name")
            )
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
                'location_name' => (string) ($locationMap[$line->location_id] ?? 'N/A'),
                'customer_name' => (string) ($line->customer_name ?? 'Walk-In Customer'),
                'customer_group_name' => $customerGroupLabel,
                'customer_group_sort' => $customerGroupSort,
                'sku' => (string) ($line->sub_sku ?? '-'),
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

        foreach (($loanPaymentData['detail_rows'] ?? []) as $loanPaymentRow) {
            $paymentCols = [];
            foreach ($paymentColumns as $method) {
                $paymentCols[$method] = $method === ($loanPaymentRow['method'] ?? '') ? (float) ($loanPaymentRow['amount'] ?? 0) : 0.0;
            }

            $loanNumber = trim((string) ($loanPaymentRow['loan_number'] ?? ''));
            $paymentRef = trim((string) ($loanPaymentRow['payment_ref'] ?? ''));

            $detailRows[] = [
                'row_source' => 'loan_payment',
                'transaction_id' => 0,
                'date' => ! empty($loanPaymentRow['date']) ? Carbon::parse($loanPaymentRow['date'])->format('Y-m-d H:i') : '-',
                'invoice_no' => $paymentRef !== '' ? $paymentRef : ($loanNumber !== '' ? $loanNumber : ('#LP' . ($loanPaymentRow['payment_id'] ?? ''))),
                'i_t' => $loanNumber !== '' ? $loanNumber : '-',
                'location_name' => (string) ($locationMap[$loanPaymentRow['location_id'] ?? 0] ?? 'N/A'),
                'customer_name' => (string) ($loanPaymentRow['customer_name'] ?? 'Loan Customer'),
                'customer_group_name' => 'បង់ប្រាក់',
                'customer_group_sort' => 4,
                'sku' => '-',
                'product_name' => 'Monthly installment payment',
                'quantity' => null,
                'unit_price' => null,
                'line_total' => null,
                'discount' => null,
                'final_total' => null,
                'paid' => (float) ($loanPaymentRow['amount'] ?? 0),
                'payments' => $paymentCols,
                'due' => 0.0,
            ];
        }

        usort($detailRows, function ($a, $b) {
            return [$a['customer_group_sort'], $a['customer_name'], $b['date']]
                <=> [$b['customer_group_sort'], $b['customer_name'], $a['date']];
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

        return [
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
            $parts[] = $name . ' (' . rtrim(rtrim(number_format((float) $qty, 2), '0'), '.') . ')';
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
            'total' => 0.0,
        ];

        if (! empty($filters['customer_group']) && $filters['customer_group'] !== 'បង់ប្រាក់') {
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

        $detailRows = DB::connection('mysql_loan')->table('loan_payments as p')
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
            ->orderBy('p.id')
            ->get();

        $data = $empty;
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
                    'name' => 'បង់ប្រាក់',
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
                    'name' => 'បង់ប្រាក់',
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
            if (empty($filters['customer_group']) || $filters['customer_group'] === 'បង់ប្រាក់') {
                $data['methods'][$method] = $method;
            }
            $data['cashier_ids'][$cashierId] = $cashierId;
            $data['location_ids'][$locationId] = $locationId;
            if (empty($filters['customer_group']) || $filters['customer_group'] === 'បង់ប្រាក់') {
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

    private function validatedFilters(Request $request, array $defaultLocationIds): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'integer',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string',
            'customer_group' => 'nullable|string',
            'payment_status' => 'nullable|in:paid,partial,due',
            'qty_type' => 'nullable|in:invoice_count,sold_quantity',
            'style_mode' => 'nullable|in:sheet,classic,classic_plain,view_report,business_location_report',
        ]);

        $locationIds = ! empty($validated['location_ids']) ? array_values(array_unique($validated['location_ids'])) : $defaultLocationIds;

        return [
            'start_date' => ! empty($validated['start_date']) ? Carbon::parse($validated['start_date'])->format('Y-m-d') : $today,
            'end_date' => ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->format('Y-m-d') : $today,
            'location_ids' => $locationIds,
            'user_ids' => ! empty($validated['user_ids']) ? array_values(array_unique($validated['user_ids'])) : [],
            'brand_ids' => ! empty($validated['brand_ids']) ? array_values(array_unique($validated['brand_ids'])) : [],
            'payment_methods' => ! empty($validated['payment_methods']) ? array_values(array_unique($validated['payment_methods'])) : [],
            'customer_group' => trim((string) ($validated['customer_group'] ?? '')),
            'payment_status' => $validated['payment_status'] ?? '',
            'qty_type' => $validated['qty_type'] ?? 'invoice_count',
            'style_mode' => $validated['style_mode'] ?? 'classic_plain',
        ];
    }

    private function buildPaymentColumns(array $methodsWithAmount, array $paymentTypes): array
    {
        $common = config('localcashierreport.common_payment_method_keys', ['cash', 'custom_pay_1', 'custom_pay_2', 'custom_pay_3', 'custom_pay_4', 'card', 'other']);
        $columns = array_values(array_unique(array_merge($common, $methodsWithAmount)));

        return array_values(array_filter($columns, fn ($m) => array_key_exists($m, $paymentTypes) || in_array($m, $methodsWithAmount, true)));
    }

    private function currencySymbol(): string
    {
        return (string) data_get(session('currency'), 'symbol', '$');
    }
}
