<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\System;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpenseListReportController extends Controller
{
    private const DETAIL_ROW_LIMIT = 5000;

    public function __construct(private Util $util)
    {
    }

    public function index(Request $request)
    {
        $this->abortIfUninstalled();
        abort_unless(
            $request->user()->can('local_cashier_report.view') || $request->user()->can('expense_report.view'),
            403
        );

        $businessId = (int) session('user.business_id');
        $locations = $this->getAccessibleLocations($businessId);
        $accessibleLocationIds = $locations->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $filters = $this->validatedFilters($request, $this->defaultLocationIds($request, $businessId, $accessibleLocationIds), $accessibleLocationIds);
        $cashiers = $this->getCashiers($businessId, $filters['location_ids']);
        $categories = DB::table('expense_categories')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id');

        $staticPaymentColumns = $this->getStaticPaymentColumns();
        $paymentTypes = $this->util->payment_types(null, false, $businessId);
        $paymentColumns = array_keys($paymentTypes);

        $expenseTxnQuery = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'expense')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$filters['start_date'], $filters['end_date']])
            ->whereIn('t.location_id', $filters['location_ids'])
            ->when(! empty($filters['user_ids']), function ($query) use ($filters) {
                $query->whereIn('t.created_by', $filters['user_ids']);
            })
            ->when(! empty($filters['expense_category_id']), function ($query) use ($filters) {
                $query->where('t.expense_category_id', $filters['expense_category_id']);
            })
            ->when(! empty($filters['payment_status']), function ($query) use ($filters) {
                $query->where('t.payment_status', $filters['payment_status']);
            });

        $expenseTxnIds = $expenseTxnQuery->pluck('t.id')->all();

        $expenseData = $this->getExpenseDetailRows($expenseTxnIds, $paymentTypes, $paymentColumns, self::DETAIL_ROW_LIMIT);
        $expenseRows = collect($expenseData['rows']);

        $staticPaymentAmount = function ($row, $column) {
            $payments = (array) data_get($row, 'payments', []);
            $sources = (array) data_get($column, 'source_methods', []);
            $amount = 0.0;
            foreach ($sources as $source) {
                $amount += (float) ($payments[$source] ?? 0);
            }

            return $amount;
        };

        $paymentMethodTotals = [];
        foreach ($staticPaymentColumns as $column) {
            $paymentMethodTotals[$column['key'] ?? ''] = $expenseRows->sum(fn ($row) => $staticPaymentAmount($row, $column));
        }

        $summary = [
            'total_count' => $expenseData['total'],
            'displayed_count' => $expenseRows->count(),
            'total_amount' => $expenseRows->sum(fn ($row) => (float) ($row['amount'] ?? 0)),
            'total_paid' => $expenseRows->sum(fn ($row) => (float) ($row['paid'] ?? 0)),
            'total_due' => $expenseRows->sum(fn ($row) => (float) ($row['due'] ?? 0)),
            'payment_totals' => $paymentMethodTotals,
        ];

        return view('localcashierreport::expenses_list', [
            'businessName' => (string) session('business.name', config('app.name')),
            'filters' => $filters,
            'locations' => $locations,
            'cashiers' => $cashiers,
            'categories' => $categories,
            'paymentStatuses' => config('localcashierreport.payment_statuses', ['paid', 'partial', 'due']),
            'currencySymbol' => $this->currencySymbol(),
            'khmerFontFamily' => config('localcashierreport.khmer_font_family'),
            'staticPaymentColumns' => $staticPaymentColumns,
            'expenseRows' => $expenseRows,
            'summary' => $summary,
        ]);
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
        if ($value === null || abs($value) < 0.00001) {
            return '$ -';
        }

        if ($value < 0) {
            return '$ (' . number_format(abs($value), 2) . ')';
        }

        return '$ ' . number_format($value, 2);
    }

    private function validatedFilters(Request $request, array $defaultLocationIds, array $accessibleLocationIds): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'expense_category_id' => 'nullable|integer',
            'payment_status' => 'nullable|in:paid,partial,due',
        ]);

        $accessibleLocationIds = array_values(array_unique(array_map('intval', $accessibleLocationIds)));
        $defaultLocationIds = array_values(array_intersect(array_unique(array_map('intval', $defaultLocationIds)), $accessibleLocationIds));
        $requestedLocationIds = ! empty($validated['location_ids']) ? array_values(array_unique(array_map('intval', $validated['location_ids']))) : [];
        $locationIds = ! empty($requestedLocationIds) ? array_values(array_intersect($requestedLocationIds, $accessibleLocationIds)) : $defaultLocationIds;
        $locationIds = ! empty($locationIds) ? $locationIds : $accessibleLocationIds;

        return [
            'start_date' => ! empty($validated['start_date']) ? Carbon::parse($validated['start_date'])->format('Y-m-d') : $today,
            'end_date' => ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->format('Y-m-d') : $today,
            'location_ids' => $locationIds,
            'user_ids' => ! empty($validated['user_ids']) ? array_values(array_unique($validated['user_ids'])) : [],
            'expense_category_id' => ! empty($validated['expense_category_id']) ? (int) $validated['expense_category_id'] : null,
            'payment_status' => $validated['payment_status'] ?? '',
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

        return $query->get();
    }

    private function getStaticPaymentColumns(): array
    {
        $columns = config('localcashierreport.all_sale_static_payment_columns', []);

        return is_array($columns) ? $columns : [];
    }

    private function currencySymbol(): string
    {
        return (string) data_get(session('currency'), 'symbol', '$');
    }

    private function abortIfUninstalled(): void
    {
        abort_if(empty(System::getProperty('localcashierreport_version')), 404);
    }
}
