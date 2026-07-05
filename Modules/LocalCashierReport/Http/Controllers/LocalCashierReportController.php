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
use Maatwebsite\Excel\Facades\Excel;

class LocalCashierReportController extends Controller
{
    private const DETAIL_ROW_LIMIT = 1000;
    private const COLLECTION_PAYMENT_GROUP = 'Collection Payment';
    private const INSTALLMENT_CUSTOMER_GROUP = '\u069e\u06a6\u06be\u06a2';

    public function __construct(private Util $util)
    {
    }

    public function index(Request $request)
    {
        $this->abortIfUninstalled();
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
            'staticPaymentColumns' => $this->getStaticPaymentColumns(),
            'report' => $report,
        ]);
    }

    public function export(Request $request)
    {
        $this->abortIfUninstalled();
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $filters = $this->validatedFilters($request, $locations->pluck('id')->all());
        $report = $this->getReportData($filters);

        $rows = [];
        foreach ($report['rows'] as $row) {
            $line = [
                'Cashier/User' => $row['cashier_name'],
                'Cashier Full Name' => $row['cashier_full_name'] ?? $row['cashier_name'],
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
        $this->abortIfUninstalled();
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
            'staticPaymentColumns' => $this->getStaticPaymentColumns(),
            'report' => $report,
        ]);
    }

    public function getReportData(array $filters, array $additionalFilters = [], array $limit = null)
    {
        $detailRows = collect();
        $detailRows = $detailRows->concat($this->getCashierSalesRows($filters, $additionalFilters, $limit, $detailRows));

        $userCashiers = collect($filters['user_ids'] ?? []);
        $locationCashiers = $this->getCashierSalesRows($filters, $additionalFilters, $limit, $detailRows)->pluck('cashier_id');
        $accessorySaleDetails = $this->getAccessorySaleDetailRows($filters, $additionalFilters);
        $serviceSaleDetails = $this->getServiceSaleDetailRows($filters, $additionalFilters);
        $collectionPaymentData = $this->getCollectionPaymentData($filters, $additionalFilters);
        $customerDuePaymentData = $this->getCustomerDuePaymentData($filters, $additionalFilters);
        $expenseDetailResult = $this->getExpenseDetailResult($filters, $additionalFilters);

        $salesRows = $detailRows->where('row_type', 'sale');
        $sellLineTotal = $salesRows->sum('line_total');
        $paidTotal = $salesRows->sum('paid');
        $dueTotal = $salesRows->sum('due');
        $sellReturnRows = $detailRows->where('row_type', 'sell_return');
        $sellReturnTotal = $sellReturnRows->sum('line_total');
        $sellReturnPaidTotal = $sellReturnRows->sum('paid');
        $sellReturnDueTotal = $sellReturnRows->sum('due');
        $grandTotal = $sellLineTotal + $sellReturnTotal;
        $grandPaid = $paidTotal + $sellReturnPaidTotal;
        $grandDue = $dueTotal + $sellReturnDueTotal;
        $actualIncome = ($paidTotal + $sellReturnPaidTotal) - ($detailRows->where('row_type', 'expense')->sum('paid') + $expenseDetailResult['total']);
        $grandActualIncome = $actualIncome - $sellReturnTotal;
        $grandExpenses = $expenseDetailResult['total'];

        $rows = $this->buildReportRows($detailRows, $additionalFilters, $userCashiers);

        return [
            'rows' => $rows,
            'rows_by_location' => $this->buildLocationRows($detailRows, $additionalFilters, $userCashiers),
            'payment_columns' => $this->getPaymentColumns($filters, $additionalFilters),
            'payment_labels' => $this->getPaymentLabels($filters, $additionalFilters),
            'grand_total' => $grandTotal,
            'grand_paid' => $grandPaid,
            'grand_expenses' => $grandExpenses,
            'grand_sell_return' => $sellReturnTotal,
            'grand_actual_income' => $grandActualIncome,
            'grand_due' => $grandDue,
            'customer_due_payment_total' => $customerDuePaymentData['total'] ?? 0.0,
            'collection_payment_total' => $collectionPaymentData['total'] ?? 0.0,
            'payment_with_expenses' => $this->calculatePaymentWithExpenses($detailRows, $filters, $additionalFilters),
            'expense_payment_summary' => $this->calculatePaymentSummary($detailRows->where('row_type', 'expense'), $filters, $additionalFilters),
            'actual_income_payment_summary' => $this->calculatePaymentSummary($detailRows->whereIn('row_type', ['sell', 'sell_return', 'expense']), $filters, $additionalFilters),
            'summary_user' => $this->buildSummaryRows($detailRows, $additionalFilters, $userCashiers, 'user'),
            'summary_location' => $this->buildSummaryRows($detailRows, $additionalFilters, $userCashiers, 'location'),
            'summary_customer_group' => $this->buildSummaryRows($detailRows, $additionalFilters, $userCashiers, 'customer_group'),
            'summary_brand' => $this->buildSummaryRows($detailRows, $additionalFilters, $userCashiers, 'brand'),
            'summary_payment' => $this->buildSummaryRows($detailRows, $additionalFilters, $userCashiers, 'payment'),
            'summary_totals' => $this->buildSummaryTotals($detailRows, $additionalFilters),
            'detail_rows' => $this->buildDetailRows($detailRows, $filters, $additionalFilters),
            'collection_payment_detail_rows' => $this->normalizeCollectionPaymentRows($collectionPaymentData['detail_rows'] ?? [], $this->getPaymentColumns($filters, $additionalFilters), $this->getCashiersMap($filters, $additionalFilters, $locationCashiers)),
            'customer_due_payment_detail_rows' => $customerDuePaymentData['rows'] ?? [],
            'expense_detail_rows' => $expenseDetailResult['rows'] ?? [],
            'accessory_sale_detail_rows' => $accessorySaleDetails['rows'] ?? [],
            'service_sale_detail_rows' => $serviceSaleDetails['rows'] ?? [],
            'detail_meta' => [
                'limit' => self::DETAIL_ROW_LIMIT,
                'main_total' => $sellLineTotal,
                'main_displayed' => $detailRows->count(),
                'collection_payment_total' => $collectionPaymentData['detail_total'] ?? 0,
                'collection_payment_displayed' => 0,
                'customer_due_payment_total' => $customerDuePaymentData['detail_total'] ?? 0,
                'customer_due_payment_displayed' => count($customerDuePaymentData['rows'] ?? []),
                'expense_total' => $expenseDetailResult['total'] ?? 0,
                'expense_displayed' => count($expenseDetailResult['rows'] ?? []),
                'accessory_total' => $accessorySaleDetails['total'] ?? 0,
                'accessory_displayed' => 0,
                'service_total' => $serviceSaleDetails['total'] ?? 0,
                'service_displayed' => 0,
            ],
        ];
    }

    private function getCashierSalesRows(array $filters, array $additionalFilters = [], array $limit = null, $detailRows = null)
    {
        $businessId = session('user.business_id');
        $query = DB::table('transactions as t')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->leftJoin('transaction_payments as tp', 't.id', '=', 'tp.transaction_id')
            ->leftJoin('contacts as pc', 't.contact_id', '=', 'pc.id')
            ->leftJoin('business as b', 't.business_id', '=', 'b.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->leftJoin('user_contact_access as uca', 't.created_by', '=', 'uca.user_id')
            ->leftJoin('contacts as cc', 'uca.contact_id', '=', 'cc.id')
            ->where('t.business_id', $businessId)
            ->where('t.created_by', '!=', 0)
            ->where('t.type', 'sell')
            ->when(! empty($filters['location_ids']), function ($query) use ($filters) {
                $query->whereIn('t.location_id', $filters['location_ids']);
            })
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['payment_methods']), function ($query) use ($filters) {
                $query->whereIn('tp.method', $filters['payment_methods']);
            })
            ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                $query->where('t.payment_status', $filters['payment_status']);
            })
            ->when(! empty($filters['customer_group']), function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('ccg.name', $filters['customer_group'])
                        ->orWhere('pc.customer_group', $filters['customer_group']);
                });
            })
            ->when(! empty($filters['brand_ids']), function ($query) use ($filters) {
                $query->whereIn('c.brand', $filters['brand_ids']);
            })
            ->when(! empty($filters['start_date']), function ($query) use ($filters) {
                $query->whereDate('t.transaction_date', '>=', $filters['start_date']);
            })
            ->when(! empty($filters['end_date']), function ($query) use ($filters) {
                $query->whereDate('t.transaction_date', '<=', $filters['end_date']);
            })
            ->when($detailRows, function ($query) use ($limit) {
                $transactionIds = $detailRows->pluck('transaction_id')->filter()->unique();
                $query->whereIn('t.id', $transactionIds);
            })
            ->select(
                't.id',
                't.invoice_no',
                't.transaction_date',
                't.final_total',
                't.paid',
                't.due',
                't.type',
                't.row_id',
                't.created_by',
                'bl.name as location_name',
                'bl.id as location_id',
                'b.name as business_name',
                DB::raw("COALESCE(NULLIF(TRIM(pc.name), ''), NULLIF(TRIM(CONCAT(COALESCE(pc.first_name,''), ' ', COALESCE(pc.last_name,''))), ''), NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_full_name"),
                DB::raw("u.first_name as cashier_first_name"),
                DB::raw("u.last_name as cashier_last_name"),
                't.customer_group',
                'b.sku_prefix as sku_prefix',
            )
            ->orderBy('t.transaction_date', 'desc')
            ->orderBy('t.id', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $rows = $query->get();

        return $rows->map(function ($row) {
            $row->payments = $this->getTransactionPayments($row->transaction_id, $businessId);
            return $row;
        });
    }

    private function getCashierSalesDetailRows(array $filters, $additionalFilters = [], $limit = null)
    {
        $businessId = session('user.business_id');
        $query = DB::table('transactions as t')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->leftJoin('transaction_payments as tp', 't.id', '=', 'tp.transaction_id')
            ->leftJoin('contacts as pc', 't.contact_id', '=', 'pc.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->leftJoin('business as b', 't.business_id', '=', 'b.id')
            ->where('t.business_id', $businessId)
            ->where('t.created_by', '!=', 0)
            ->where('t.type', 'sell')
            ->when(! empty($filters['location_ids']), function ($query) use ($filters) {
                $query->whereIn('t.location_id', $filters['location_ids']);
            })
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['payment_methods']), function ($query) use ($filters) {
                $query->whereIn('tp.method', $filters['payment_methods']);
            })
            ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                $query->where('t.payment_status', $filters['payment_status']);
            })
            ->when(! empty($filters['customer_group']), function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('c.customer_group', $filters['customer_group'])
                        ->orWhere('pc.customer_group', $filters['customer_group']);
                });
            })
            ->when(! empty($filters['brand_ids']), function ($query) use ($filters) {
                $query->whereIn('b.brand', $filters['brand_ids']);
            })
            ->when(! empty($filters['start_date']), function ($query) use ($filters) {
                $query->whereDate('t.transaction_date', '>=', $filters['start_date']);
            })
            ->when(! empty($filters['end_date']), function ($query) use ($filters) {
                $query->whereDate('t.transaction_date', '<=', $filters['end_date']);
            })
            ->select(
                't.id as transaction_id',
                't.invoice_no',
                't.transaction_date',
                't.type',
                't.staff_note',
                't.final_total',
                't.paid',
                't.due',
                't.payment_status',
                't.location_id',
                'bl.name as location_name',
                't.business_location_id',
                'b.name as business_name',
                DB::raw("COALESCE(NULLIF(TRIM(pc.name), ''), NULLIF(TRIM(CONCAT(COALESCE(pc.first_name,''), ' ', COALESCE(pc.last_name,''))), ''), NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''), 'Walk-In Customer') as customer_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_full_name"),
                DB::raw("u.first_name as cashier_first_name"),
                DB::raw("u.last_name as cashier_last_name"),
                'pc.customer_group as customer_group_name',
                't.customer_id',
                DB::raw("c.name as customer_name_full"),
                DB::raw("t.row_id"),
            )
            ->orderBy('t.transaction_date', 'desc')
            ->orderBy('t.id', 'desc');

        return $query->get();
    }

    private function getCashiers(array $businessId, array $locationIds = [])
    {
        $query = DB::table('users')
            ->where('business_id', $businessId)
            ->where('is_cmmsn_agnt', 0)
            ->select(
                'id',
                DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"),
                DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as full_name"),
                DB::raw("first_name as first_name"),
                DB::raw("last_name as last_name"),
            );

        if (! empty($locationIds)) {
            $query->whereIn('id', function ($query) use ($locationIds) {
                $query->select('user_id')
                    ->from('user_business_locations')
                    ->whereIn('location_id', $locationIds);
            });
        }

        return $query->get();
    }

    private function buildReportRows($detailRows, $additionalFilters, $userCashiers)
    {
        $rows = collect();
        $userCashiers = collect($userCashiers ?? []);

        $uniqueRows = $detailRows->where('row_type', 'sale')->map(function ($row) {
            return [
                'cashier_id' => (int) ($row['cashier_id'] ?? 0),
                'cashier_name' => (string) ($row['cashier_name'] ?? ''),
                'cashier_full_name' => (string) ($row['cashier_full_name'] ?? $row['cashier_name'] ?? ''),
                'location_id' => (int) ($row['location_id'] ?? 0),
                'location_name' => (string) ($row['location_name'] ?? ''),
                'user_ids' => $userCashiers->map(function ($user) {
                    return (int) $user->id;
                })->toArray(),
            ];
        })->unique(function ($item) {
            return $item['cashier_id'] . ':' . $item['location_id'];
        })->values();

        foreach ($uniqueRows as $item) {
            $row = [
                'cashier_id' => $item['cashier_id'],
                'cashier_name' => $item['cashier_name'],
                'cashier_full_name' => $item['cashier_full_name'],
                'location_id' => $item['location_id'],
                'location_name' => $item['location_name'],
                'qty_total' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('quantity'),
                'total' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('line_total'),
                'paid' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('paid'),
                'due' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('due'),
                'payments' => $this->aggregatePayments($detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])),
            ];

            $rows->push($row);
        }

        return $rows->values()->toArray();
    }

    private function buildLocationRows($detailRows, $additionalFilters, $userCashiers)
    {
        $rows = collect();

        $uniqueRows = $detailRows->where('row_type', 'sale')->map(function ($row) {
            return [
                'cashier_id' => (int) ($row['cashier_id'] ?? 0),
                'location_id' => (int) ($row['location_id'] ?? 0),
            ];
        })->unique(function ($item) {
            return $item['cashier_id'] . ':' . $item['location_id'];
        })->values();

        foreach ($uniqueRows as $item) {
            $rows->push([
                'cashier_id' => $item['cashier_id'],
                'location_id' => $item['location_id'],
                'location_name' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->pluck('location_name')
                    ->first(),
                'qty_total' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('quantity'),
                'total' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('line_total'),
                'paid' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('paid'),
                'due' => $detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])
                    ->sum('due'),
                'payments' => $this->aggregatePayments($detailRows->where('cashier_id', $item['cashier_id'])
                    ->where('location_id', $item['location_id'])),
            ]);
        }

        return $rows->values()->toArray();
    }

    private function aggregatePayments($rows)
    {
        $payments = [];

        foreach ($rows as $row) {
            if (isset($row['payments'])) {
                foreach ($row['payments'] as $method => $amount) {
                    $payments[$method] = ($payments[$method] ?? 0) + $amount;
                }
            }
        }

        return $payments;
    }

    private function getTransactionPayments($transactionId, $businessId)
    {
        $payments = DB::table('transaction_payments as tp')
            ->join('business_locations as bl', 'tp.location_id', '=', 'bl.id')
            ->where('tp.transaction_id', $transactionId)
            ->select(
                'tp.method',
                'tp.amount',
                'bl.name as location_name'
            )
            ->get()
            ->groupBy('method')
            ->map(function ($items) {
                return $items->sum('amount');
            })
            ->toArray();

        return $payments;
    }
}
