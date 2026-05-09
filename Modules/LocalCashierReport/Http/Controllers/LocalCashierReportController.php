<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Exports\ArrayExport;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->select('t.id', 't.created_by', 't.location_id', 't.final_total')
            ->get();

        if ($baseTransactions->isEmpty()) {
            $paymentTypes = $this->util->payment_types(null, false, $businessId);
            $paymentColumns = $this->buildPaymentColumns([], $paymentTypes);

            return [
                'rows' => [],
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
        if ($filters['qty_type'] === 'invoice_count') {
            $invoiceCountRows = $baseTransactions
                ->groupBy(fn ($t) => $t->created_by . '_' . $t->location_id)
                ->map(fn ($items) => count($items));
            foreach ($invoiceCountRows as $key => $qty) {
                $qtyByCashierLocation[$key] = (float) $qty;
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
            }
        }

        $cashierMap = DB::table('users')
            ->whereIn('id', $cashierIds)
            ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"))
            ->pluck('name', 'id');
        $locationMap = DB::table('business_locations')->whereIn('id', $locationIds)->pluck('name', 'id');

        $paymentTypes = $this->util->payment_types(null, false, $businessId);
        $paymentColumns = $this->buildPaymentColumns(array_keys($methodsWithAmount), $paymentTypes);

        $rowsByCashier = [];
        foreach ($baseTransactions as $t) {
            $cashierId = (int) $t->created_by;
            if (! isset($rowsByCashier[$cashierId])) {
                $rowsByCashier[$cashierId] = [
                    'cashier_id' => $cashierId,
                    'cashier_name' => (string) ($cashierMap[$cashierId] ?? 'N/A'),
                    'location_qty_map' => [],
                    'payments' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                ];
            }

            $rowsByCashier[$cashierId]['total'] += (float) $t->final_total;

            $locKey = $cashierId . '_' . (int) $t->location_id;
            if (isset($qtyByCashierLocation[$locKey])) {
                $rowsByCashier[$cashierId]['location_qty_map'][(int) $t->location_id] = $qtyByCashierLocation[$locKey];
            }

            $txnPayments = $paymentByTransaction[(int) $t->id] ?? [];
            foreach ($txnPayments as $method => $amount) {
                $rowsByCashier[$cashierId]['payments'][$method] = ($rowsByCashier[$cashierId]['payments'][$method] ?? 0) + (float) $amount;
                $rowsByCashier[$cashierId]['paid'] += (float) $amount;
            }
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

            $rows[] = $cashierRow;
            $userSummary[] = [
                'name' => $cashierRow['cashier_name'],
                'amount' => (float) $cashierRow['total'],
                'qty' => (float) $cashierRow['qty_total'],
            ];
            $grandTotal += (float) $cashierRow['total'];
            $grandDue += (float) $cashierRow['due'];
        }

        usort($rows, fn ($a, $b) => strcmp($a['cashier_name'], $b['cashier_name']));

        $locationSummaryMap = [];
        foreach ($rowsByCashier as $cashierRow) {
            foreach ($cashierRow['location_qty_map'] as $locId => $qty) {
                if (! isset($locationSummaryMap[$locId])) {
                    $locationSummaryMap[$locId] = ['name' => (string) ($locationMap[$locId] ?? 'N/A'), 'amount' => 0.0, 'qty' => 0.0];
                }
                $locationSummaryMap[$locId]['qty'] += (float) $qty;
            }
        }
        foreach ($baseTransactions as $t) {
            $locId = (int) $t->location_id;
            if (! isset($locationSummaryMap[$locId])) {
                $locationSummaryMap[$locId] = ['name' => (string) ($locationMap[$locId] ?? 'N/A'), 'amount' => 0.0, 'qty' => 0.0];
            }
            $locationSummaryMap[$locId]['amount'] += (float) $t->final_total;
        }
        $locationSummary = array_values($locationSummaryMap);

        $paymentSummaryMap = [];
        foreach ($rowsByCashier as $cashierRow) {
            foreach ($cashierRow['payments'] as $method => $amount) {
                $paymentSummaryMap[$method] = ($paymentSummaryMap[$method] ?? 0) + (float) $amount;
            }
        }
        $paymentSummary = [];
        foreach ($paymentSummaryMap as $method => $amount) {
            $paymentSummary[] = ['name' => (string) ($paymentTypes[$method] ?? $method), 'amount' => (float) $amount];
        }

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

        $paymentWithExpenses['expenses'] = $grandExpenses;
        foreach ($paymentColumns as $method) {
            $sellPaidByMethod = (float) ($paymentSummaryMap[$method] ?? 0);
            $expenseByMethod = (float) ($expensePaymentSummaryMap[$method] ?? 0);
            $actualIncomeByPayment[$method] = $sellPaidByMethod - $expenseByMethod;
        }

        $sellLineRows = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
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
                't.final_total'
            )
            ->orderBy('t.transaction_date', 'desc')
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

            $detailRows[] = [
                'transaction_id' => $txnId,
                'date' => Carbon::parse($line->transaction_date)->format('Y-m-d H:i'),
                'invoice_no' => (string) ($line->invoice_no ?: ('#' . $txnId)),
                'cashier_name' => (string) ($cashierMap[$line->created_by] ?? 'N/A'),
                'location_name' => (string) ($locationMap[$line->location_id] ?? 'N/A'),
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

        return [
            'rows' => $rows,
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
            'summary_payment' => $paymentSummary,
            'detail_rows' => $detailRows,
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
            'payment_status' => 'nullable|in:paid,partial,due',
            'qty_type' => 'nullable|in:invoice_count,sold_quantity',
            'style_mode' => 'nullable|in:sheet,classic,classic_plain,view_report',
        ]);

        $locationIds = ! empty($validated['location_ids']) ? array_values(array_unique($validated['location_ids'])) : $defaultLocationIds;

        return [
            'start_date' => ! empty($validated['start_date']) ? Carbon::parse($validated['start_date'])->format('Y-m-d') : $today,
            'end_date' => ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->format('Y-m-d') : $today,
            'location_ids' => $locationIds,
            'user_ids' => ! empty($validated['user_ids']) ? array_values(array_unique($validated['user_ids'])) : [],
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
