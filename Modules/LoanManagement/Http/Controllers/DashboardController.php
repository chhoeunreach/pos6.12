<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    protected function allow(string $permission): void
    {
        abort_unless(
            auth()->user()->can($permission) || auth()->user()->can('loan_management.view'),
            403,
            'Unauthorized action.'
        );
    }

    public function index()
    {
        $this->allow('loan_management.dashboard.view');

        return view('loanmanagement::dashboard.index');
    }

    public function placeholder(Request $request, string $page)
    {
        $this->allow('loan_management.view');

        $payload = $this->buildPagePayload($page);
        return view('loanmanagement::dashboard.placeholder', [
            'page' => $page,
            'payload' => $payload,
        ]);
    }

    public function overdue()
    {
        $this->allow('loan_management.overdue.view');

        return view('loanmanagement::overdue.index');
    }

    public function yearlyLoanSummary(Request $request)
    {
        $this->allow('loan_management.view');

        $filters = $this->yearlySummaryFilters($request);
        $payload = $this->buildYearlyLoanSummary($filters);

        if ($request->input('export') === 'csv') {
            return $this->downloadYearlyLoanSummaryCsv($payload, $filters);
        }

        return view('loanmanagement::reports.yearly_loan_summary', [
            'filters' => $filters,
            'payload' => $payload,
            'locations' => $this->loanReportLocationOptions(),
            'isKhmer' => $this->loanReportIsKhmer(),
        ]);
    }

    public function adminLoan(Request $request)
    {
        $this->allow('loan_management.view');

        $filters = $this->yearlySummaryFilters($request);
        $payload = $this->buildYearlyLoanSummary($filters);
        $payload['adminRows'] = $this->adminLoanRows($payload['rows']);
        $payload['adminTotals'] = $this->adminLoanTotals($payload['adminRows']);

        return view('loanmanagement::admin_loan.index', [
            'filters' => $filters,
            'payload' => $payload,
            'locations' => $this->loanReportLocationOptions(),
            'isKhmer' => $this->loanReportIsKhmer(),
        ]);
    }

    protected function buildPagePayload(string $page): array
    {
        $conn = DB::connection('mysql_loan');
        $data = ['summary' => [], 'columns' => [], 'rows' => []];

        switch ($page) {
            case 'Guarantors':
                $table = 'loan_guarantors';
                $data['columns'] = ['id', 'name', 'phone', 'relationship', 'workplace', 'customer_id', 'loan_id', 'created_at'];
                break;
            case 'Blacklist':
                $table = 'loan_customers';
                $data['columns'] = ['id', 'customer_code', 'name', 'phone', 'blacklist_status', 'blacklist_reason', 'updated_at'];
                break;
            case 'Installment Schedules':
                $table = 'loan_payment_schedules';
                $data['columns'] = ['id', 'loan_id', 'installment_no', 'due_date', 'amount_due', 'amount_paid', 'amount_balance', 'status'];
                break;
            case 'Monthly Payments':
            case 'Payments':
            case 'Payment History':
                $table = 'loan_payments';
                $data['columns'] = ['id', 'payment_ref_no', 'loan_id', 'customer_id', 'channel', 'amount', 'status', 'paid_at'];
                break;
            case 'Collection Visits':
                $table = 'loan_collection_visits';
                $data['columns'] = ['id', 'loan_id', 'customer_id', 'collector_name_snapshot', 'result', 'status', 'visited_at'];
                break;
            case 'ABA Transactions':
                $table = 'loan_aba_payway_transactions';
                $data['columns'] = ['id', 'merchant_ref_no', 'loan_id', 'customer_id', 'amount', 'currency', 'status', 'created_at'];
                break;
            case 'Reports':
                $table = 'loans';
                $data['columns'] = ['id', 'loan_number', 'customer_name_snapshot', 'status', 'principal_amount', 'paid_amount', 'balance_amount', 'loan_date'];
                break;
            case 'Import Excel':
                $table = 'loan_import_batches';
                $data['columns'] = ['id', 'batch_code', 'file_name', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'created_at'];
                break;
            default:
                $table = null;
                break;
        }

        if (empty($table) || ! Schema::connection('mysql_loan')->hasTable($table)) {
            $data['summary'] = ['table' => $table, 'total' => 0];
            return $data;
        }

        $available = Schema::connection('mysql_loan')->getColumnListing($table);
        $select = array_values(array_intersect($data['columns'], $available));
        if (empty($select)) {
            $select = ['id'];
        }

        $q = $conn->table($table);
        if ($page === 'Blacklist' && in_array('blacklist_status', $available, true)) {
            $q->where('blacklist_status', 1);
        }
        if ($page === 'Monthly Payments') {
            if (in_array('payment_type', $available, true)) {
                $q->where('payment_type', 'monthly');
            } else {
                if (in_array('schedule_id', $available, true)) {
                    $q->whereNotNull('schedule_id');
                }
                foreach (['receipt_number', 'payment_ref_no', 'reference_number', 'payment_number'] as $column) {
                    if (in_array($column, $available, true)) {
                        $q->where($column, 'not like', 'IMP-DOWN-%');
                    }
                }
            }
        }

        $data['summary'] = ['table' => $table, 'total' => (int) (clone $q)->count()];
        $data['rows'] = $q->select($select)->orderByDesc('id')->limit(100)->get()->map(fn ($r) => (array) $r)->all();
        $data['columns'] = $select;

        return $data;
    }

    protected function yearlySummaryFilters(Request $request): array
    {
        $currentYear = (int) now()->format('Y');
        $startYear = (int) $request->input('start_year', $currentYear - 4);
        $endYear = (int) $request->input('end_year', $currentYear);

        if ($startYear < 2000 || $startYear > 2100) {
            $startYear = $currentYear - 4;
        }
        if ($endYear < 2000 || $endYear > 2100) {
            $endYear = $currentYear;
        }
        if ($startYear > $endYear) {
            [$startYear, $endYear] = [$endYear, $startYear];
        }

        return [
            'start_year' => $startYear,
            'end_year' => min($endYear, $startYear + 25),
            'location_id' => $request->filled('location_id') ? trim((string) $request->input('location_id')) : null,
            'search' => trim((string) $request->input('search', '')),
        ];
    }

    protected function buildYearlyLoanSummary(array $filters): array
    {
        $years = range((int) $filters['start_year'], (int) $filters['end_year']);
        $rows = [];
        foreach ($years as $year) {
            $rows[$year] = $this->emptyYearlySummaryRow($year);
        }

        if (! Schema::connection('mysql_loan')->hasTable('loans')) {
            return [
                'rows' => array_values($rows),
                'totals' => $this->sumYearlySummaryRows($rows),
                'cards' => $this->yearlySummaryCards($rows),
            ];
        }

        $loanRows = $this->yearlyLoanAggregates($filters);
        foreach ($loanRows as $row) {
            $year = (int) $row->report_year;
            if (! isset($rows[$year])) {
                continue;
            }
            $rows[$year]['loan_count'] = (int) ($row->loan_count ?? 0);
            $rows[$year]['principal_total'] = (float) ($row->principal_total ?? 0);
            $rows[$year]['interest_total'] = (float) ($row->interest_total ?? 0);
            $rows[$year]['loan_total'] = (float) ($row->loan_total ?? 0);
            $rows[$year]['loan_paid_total'] = (float) ($row->loan_paid_total ?? 0);
            $rows[$year]['loan_balance_total'] = (float) ($row->loan_balance_total ?? 0);
            $rows[$year]['paid_customer_count'] = (int) ($row->paid_customer_count ?? 0);
            $rows[$year]['closed_count'] = (int) ($row->closed_count ?? 0);
            $rows[$year]['closed_principal_total'] = (float) ($row->closed_principal_total ?? 0);
            $rows[$year]['closed_interest_total'] = (float) ($row->closed_interest_total ?? 0);
            $rows[$year]['closed_loan_total'] = (float) ($row->closed_loan_total ?? 0);
            $rows[$year]['closed_paid_total'] = (float) ($row->closed_paid_total ?? 0);
            $rows[$year]['closed_balance_total'] = (float) ($row->closed_balance_total ?? 0);
            $rows[$year]['bad_count'] = (int) ($row->bad_count ?? 0);
            $rows[$year]['bad_principal_total'] = (float) ($row->bad_principal_total ?? 0);
            $rows[$year]['bad_interest_total'] = (float) ($row->bad_interest_total ?? 0);
            $rows[$year]['bad_loan_total'] = (float) ($row->bad_loan_total ?? 0);
            $rows[$year]['bad_paid_total'] = (float) ($row->bad_paid_total ?? 0);
            $rows[$year]['bad_balance_total'] = (float) ($row->bad_balance_total ?? 0);
        }

        foreach ($this->yearlyScheduleAggregates($filters) as $row) {
            $year = (int) $row->report_year;
            if (! isset($rows[$year])) {
                continue;
            }
            $rows[$year]['schedule_count'] = (int) ($row->schedule_count ?? 0);
            $rows[$year]['schedule_principal_total'] = (float) ($row->schedule_principal_total ?? 0);
            $rows[$year]['schedule_interest_total'] = (float) ($row->schedule_interest_total ?? 0);
            $rows[$year]['schedule_due_total'] = (float) ($row->schedule_due_total ?? 0);
            $rows[$year]['schedule_paid_total'] = (float) ($row->schedule_paid_total ?? 0);
            $rows[$year]['schedule_balance_total'] = (float) ($row->schedule_balance_total ?? 0);
            $rows[$year]['overdue_count'] = (int) ($row->overdue_count ?? 0);
            $rows[$year]['overdue_balance_total'] = (float) ($row->overdue_balance_total ?? 0);
        }

        foreach ($this->yearlyPaymentAggregates($filters) as $row) {
            $year = (int) $row->report_year;
            if (! isset($rows[$year])) {
                continue;
            }
            $rows[$year]['payment_count'] = (int) ($row->payment_count ?? 0);
            $rows[$year]['collection_payment_total'] = (float) ($row->collection_payment_total ?? 0);
            $rows[$year]['deposit_payment_total'] = (float) ($row->deposit_payment_total ?? 0);
            $rows[$year]['payment_total'] = (float) ($row->payment_total ?? 0);
        }

        return [
            'rows' => array_values($rows),
            'totals' => $this->sumYearlySummaryRows($rows),
            'cards' => $this->yearlySummaryCards($rows),
        ];
    }

    protected function emptyYearlySummaryRow(int $year): array
    {
        return [
            'year' => $year,
            'loan_count' => 0,
            'principal_total' => 0.0,
            'interest_total' => 0.0,
            'loan_total' => 0.0,
            'loan_paid_total' => 0.0,
            'loan_balance_total' => 0.0,
            'paid_customer_count' => 0,
            'closed_count' => 0,
            'closed_principal_total' => 0.0,
            'closed_interest_total' => 0.0,
            'closed_loan_total' => 0.0,
            'closed_paid_total' => 0.0,
            'closed_balance_total' => 0.0,
            'bad_count' => 0,
            'bad_principal_total' => 0.0,
            'bad_interest_total' => 0.0,
            'bad_loan_total' => 0.0,
            'bad_paid_total' => 0.0,
            'bad_balance_total' => 0.0,
            'schedule_count' => 0,
            'schedule_principal_total' => 0.0,
            'schedule_interest_total' => 0.0,
            'schedule_due_total' => 0.0,
            'schedule_paid_total' => 0.0,
            'schedule_balance_total' => 0.0,
            'payment_count' => 0,
            'collection_payment_total' => 0.0,
            'deposit_payment_total' => 0.0,
            'payment_total' => 0.0,
            'overdue_count' => 0,
            'overdue_balance_total' => 0.0,
        ];
    }

    protected function yearlyLoanAggregates(array $filters)
    {
        $columns = Schema::connection('mysql_loan')->getColumnListing('loans');
        $dateColumn = $this->firstLoanReportColumn('loans', ['loan_date', 'created_at'], $columns);
        if (! $dateColumn) {
            return collect();
        }

        $principalExpr = $this->coalesceSql('loans', 'l', ['principal_amount', 'financed_amount']);
        $interestExpr = $this->coalesceSql('loans', 'l', ['interest_amount']);
        $loanTotalExpr = $this->coalesceSql('loans', 'l', ['total_amount', 'total_payable_amount']);
        $paidExpr = $this->coalesceSql('loans', 'l', ['paid_amount']);
        $balanceExpr = $this->coalesceSql('loans', 'l', ['balance_amount']);
        $closedCondition = $this->closedLoanConditionSql('l');

        $joinCustomers = Schema::connection('mysql_loan')->hasTable('loan_customers')
            && in_array('customer_id', $columns, true)
            && Schema::connection('mysql_loan')->hasColumn('loan_customers', 'id');
        $customerBlacklist = $joinCustomers && Schema::connection('mysql_loan')->hasColumn('loan_customers', 'blacklist_status');
        $badCondition = $this->badLoanConditionSql('l', $columns, $customerBlacklist ? 'c' : null);

        $query = DB::connection('mysql_loan')->table('loans as l');
        if ($joinCustomers) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
        }
        $this->applyYearlyLoanFilters($query, $filters, 'l', $dateColumn);

        return $query
            ->selectRaw('YEAR(l.'.$dateColumn.') as report_year')
            ->selectRaw('COUNT(*) as loan_count')
            ->selectRaw('COALESCE(SUM('.$principalExpr.'), 0) as principal_total')
            ->selectRaw('COALESCE(SUM('.$interestExpr.'), 0) as interest_total')
            ->selectRaw('COALESCE(SUM('.$loanTotalExpr.'), 0) as loan_total')
            ->selectRaw('COALESCE(SUM('.$paidExpr.'), 0) as loan_paid_total')
            ->selectRaw('COALESCE(SUM('.$balanceExpr.'), 0) as loan_balance_total')
            ->selectRaw('SUM(CASE WHEN '.$paidExpr.' > 0 THEN 1 ELSE 0 END) as paid_customer_count')
            ->selectRaw('SUM(CASE WHEN '.$closedCondition.' THEN 1 ELSE 0 END) as closed_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$closedCondition.' THEN '.$principalExpr.' ELSE 0 END), 0) as closed_principal_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$closedCondition.' THEN '.$interestExpr.' ELSE 0 END), 0) as closed_interest_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$closedCondition.' THEN '.$loanTotalExpr.' ELSE 0 END), 0) as closed_loan_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$closedCondition.' THEN '.$paidExpr.' ELSE 0 END), 0) as closed_paid_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$closedCondition.' THEN '.$balanceExpr.' ELSE 0 END), 0) as closed_balance_total')
            ->selectRaw('SUM(CASE WHEN '.$badCondition.' THEN 1 ELSE 0 END) as bad_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$badCondition.' THEN '.$principalExpr.' ELSE 0 END), 0) as bad_principal_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$badCondition.' THEN '.$interestExpr.' ELSE 0 END), 0) as bad_interest_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$badCondition.' THEN '.$loanTotalExpr.' ELSE 0 END), 0) as bad_loan_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$badCondition.' THEN '.$paidExpr.' ELSE 0 END), 0) as bad_paid_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$badCondition.' THEN '.$balanceExpr.' ELSE 0 END), 0) as bad_balance_total')
            ->groupByRaw('YEAR(l.'.$dateColumn.')')
            ->get();
    }

    protected function yearlyScheduleAggregates(array $filters)
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            return collect();
        }

        $columns = Schema::connection('mysql_loan')->getColumnListing('loan_payment_schedules');
        $dateColumn = $this->firstLoanReportColumn('loan_payment_schedules', ['due_date', 'created_at'], $columns);
        if (! $dateColumn) {
            return collect();
        }

        $balanceExpr = $this->coalesceSql('loan_payment_schedules', 's', ['balance_amount', 'amount_balance'], '0');
        $statusExpr = in_array('status', $columns, true) ? 'LOWER(COALESCE(s.status, ""))' : '""';
        $overdueCase = 'CASE WHEN ('.$balanceExpr.' > 0 AND ('.$statusExpr.' IN ("late", "overdue") OR s.'.$dateColumn.' < CURDATE())) THEN 1 ELSE 0 END';

        $query = DB::connection('mysql_loan')->table('loan_payment_schedules as s')
            ->join('loans as l', 'l.id', '=', 's.loan_id');
        $this->applyYearlyLoanFilters($query, $filters, 'l', 'loan_date', 's', $dateColumn);

        return $query
            ->selectRaw('YEAR(s.'.$dateColumn.') as report_year')
            ->selectRaw('COUNT(*) as schedule_count')
            ->selectRaw($this->sumSql('loan_payment_schedules', 's', ['principal_amount', 'principal_due', 'principal', 'installment_value']).' as schedule_principal_total')
            ->selectRaw($this->sumSql('loan_payment_schedules', 's', ['interest_amount', 'interest_due', 'interest', 'benefit_value']).' as schedule_interest_total')
            ->selectRaw($this->sumSql('loan_payment_schedules', 's', ['schedule_amount', 'amount_due', 'total']).' as schedule_due_total')
            ->selectRaw($this->sumSql('loan_payment_schedules', 's', ['paid_amount', 'amount_paid', 'paid_value']).' as schedule_paid_total')
            ->selectRaw($this->sumSql('loan_payment_schedules', 's', ['balance_amount', 'amount_balance']).' as schedule_balance_total')
            ->selectRaw('SUM('.$overdueCase.') as overdue_count')
            ->selectRaw('SUM(CASE WHEN '.$overdueCase.' = 1 THEN '.$balanceExpr.' ELSE 0 END) as overdue_balance_total')
            ->groupByRaw('YEAR(s.'.$dateColumn.')')
            ->get();
    }

    protected function yearlyPaymentAggregates(array $filters)
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            return collect();
        }

        $columns = Schema::connection('mysql_loan')->getColumnListing('loan_payments');
        $dateColumn = $this->firstLoanReportColumn('loan_payments', ['paid_date', 'payment_date', 'paid_at', 'created_at'], $columns);
        if (! $dateColumn) {
            return collect();
        }

        $amountExpr = $this->coalesceSql('loan_payments', 'p', ['total_paid_base', 'total_paid', 'amount_base', 'amount'], '0');
        $typeExpr = in_array('payment_type', $columns, true) ? 'LOWER(COALESCE(p.payment_type, ""))' : '""';
        $collectionCase = 'CASE WHEN '.$typeExpr.' = "monthly" OR ('.$typeExpr.' = "" AND '.(in_array('schedule_id', $columns, true) ? 'p.schedule_id IS NOT NULL' : '0').') THEN '.$amountExpr.' ELSE 0 END';
        $depositCase = 'CASE WHEN '.$typeExpr.' IN ("loan", "initial", "down_payment", "downpayment", "deposit") OR ('.$typeExpr.' = "" AND '.(in_array('schedule_id', $columns, true) ? 'p.schedule_id IS NULL' : '0').') THEN '.$amountExpr.' ELSE 0 END';

        $query = DB::connection('mysql_loan')->table('loan_payments as p')
            ->join('loans as l', 'l.id', '=', 'p.loan_id');
        $this->applyYearlyLoanFilters($query, $filters, 'l', 'loan_date', 'p', $dateColumn);

        if (in_array('status', $columns, true)) {
            $query->whereRaw('LOWER(COALESCE(p.status, "")) NOT IN ("cancelled", "canceled", "failed", "void", "deleted")');
        }

        return $query
            ->selectRaw('YEAR(p.'.$dateColumn.') as report_year')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('SUM('.$collectionCase.') as collection_payment_total')
            ->selectRaw('SUM('.$depositCase.') as deposit_payment_total')
            ->selectRaw('SUM('.$amountExpr.') as payment_total')
            ->groupByRaw('YEAR(p.'.$dateColumn.')')
            ->get();
    }

    protected function applyYearlyLoanFilters($query, array $filters, string $loanAlias, string $loanDateColumn, ?string $dataAlias = null, ?string $dataDateColumn = null): void
    {
        $dateAlias = $dataAlias ?: $loanAlias;
        $dateColumn = $dataDateColumn ?: $loanDateColumn;

        $query->whereYear($dateAlias.'.'.$dateColumn, '>=', (int) $filters['start_year'])
            ->whereYear($dateAlias.'.'.$dateColumn, '<=', (int) $filters['end_year']);

        if (Schema::connection('mysql_loan')->hasColumn('loans', 'deleted_at')) {
            $query->whereNull($loanAlias.'.deleted_at');
        }
        if ($dataAlias && Schema::connection('mysql_loan')->hasColumn($dataAlias === 's' ? 'loan_payment_schedules' : 'loan_payments', 'deleted_at')) {
            $query->whereNull($dataAlias.'.deleted_at');
        }

        if (! empty($filters['location_id'])) {
            $locationFilter = $this->parseYearlyLocationFilter((string) $filters['location_id']);
            if (! empty($locationFilter)) {
                $canFilterLocation =
                    (! empty($locationFilter['loan_location_id']) && Schema::connection('mysql_loan')->hasColumn('loans', 'business_location_id'))
                    || (! empty($locationFilter['main_location_id']) && Schema::connection('mysql_loan')->hasColumn('loans', 'main_location_id'))
                    || (! empty($locationFilter['legacy_id']) && (
                        Schema::connection('mysql_loan')->hasColumn('loans', 'business_location_id')
                        || Schema::connection('mysql_loan')->hasColumn('loans', 'main_location_id')
                    ))
                    || (! empty($locationFilter['name']) && (
                        Schema::connection('mysql_loan')->hasColumn('loans', 'location_name_snapshot')
                        || Schema::connection('mysql_loan')->hasColumn('loans', 'business_location_name_snapshot')
                    ));

                if (! $canFilterLocation) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($where) use ($loanAlias, $locationFilter) {
                    if (! empty($locationFilter['loan_location_id']) && Schema::connection('mysql_loan')->hasColumn('loans', 'business_location_id')) {
                        $where->orWhere($loanAlias.'.business_location_id', (int) $locationFilter['loan_location_id']);
                    }
                    if (! empty($locationFilter['main_location_id']) && Schema::connection('mysql_loan')->hasColumn('loans', 'main_location_id')) {
                        $where->orWhere($loanAlias.'.main_location_id', (int) $locationFilter['main_location_id']);
                    }
                    if (! empty($locationFilter['legacy_id'])) {
                        if (Schema::connection('mysql_loan')->hasColumn('loans', 'business_location_id')) {
                            $where->orWhere($loanAlias.'.business_location_id', (int) $locationFilter['legacy_id']);
                        }
                        if (Schema::connection('mysql_loan')->hasColumn('loans', 'main_location_id')) {
                            $where->orWhere($loanAlias.'.main_location_id', (int) $locationFilter['legacy_id']);
                        }
                    }
                    if (! empty($locationFilter['name'])) {
                        foreach (['location_name_snapshot', 'business_location_name_snapshot'] as $column) {
                            if (Schema::connection('mysql_loan')->hasColumn('loans', $column)) {
                                $where->orWhere($loanAlias.'.'.$column, $locationFilter['name']);
                            }
                        }
                    }
                });
            }
        }

        if ($filters['search'] !== '') {
            $like = '%'.$filters['search'].'%';
            $searchColumns = array_values(array_filter(['loan_number', 'source_invoice_no', 'customer_name_snapshot', 'customer_phone_snapshot'], fn ($column) => Schema::connection('mysql_loan')->hasColumn('loans', $column)));
            if (! empty($searchColumns)) {
                $query->where(function ($where) use ($loanAlias, $like, $searchColumns) {
                    foreach ($searchColumns as $column) {
                        $where->orWhere($loanAlias.'.'.$column, 'like', $like);
                    }
                });
            }
        }
    }

    protected function firstLoanReportColumn(string $table, array $candidates, ?array $columns = null): ?string
    {
        $columns = $columns ?: Schema::connection('mysql_loan')->getColumnListing($table);

        foreach ($candidates as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return null;
    }

    protected function coalesceSql(string $table, string $alias, array $columns, string $fallback = '0'): string
    {
        $available = Schema::connection('mysql_loan')->hasTable($table)
            ? Schema::connection('mysql_loan')->getColumnListing($table)
            : [];
        $parts = [];
        foreach ($columns as $column) {
            if (in_array($column, $available, true)) {
                $parts[] = $alias.'.'.$column;
            }
        }

        return $parts ? 'COALESCE('.implode(', ', $parts).', '.$fallback.')' : $fallback;
    }

    protected function sumSql(string $table, string $alias, array $columns): string
    {
        return 'COALESCE(SUM('.$this->coalesceSql($table, $alias, $columns).'), 0)';
    }

    protected function closedLoanCountSql(string $alias): string
    {
        return 'SUM(CASE WHEN '.$this->closedLoanConditionSql($alias).' THEN 1 ELSE 0 END)';
    }

    protected function closedLoanConditionSql(string $alias): string
    {
        if (! Schema::connection('mysql_loan')->hasColumn('loans', 'status')) {
            return '0';
        }

        return 'LOWER(COALESCE('.$alias.'.status, "")) IN ("completed", "closed", "paid", "paid_off", "pay off", "payoff")';
    }

    protected function badLoanConditionSql(string $alias, array $columns, ?string $customerAlias = null): string
    {
        $conditions = [];

        if (in_array('blacklist_status', $columns, true)) {
            $conditions[] = 'COALESCE('.$alias.'.blacklist_status, 0) = 1';
        }
        if (in_array('blacklisted_at', $columns, true)) {
            $conditions[] = $alias.'.blacklisted_at IS NOT NULL';
        }
        if (in_array('written_off_at', $columns, true)) {
            $conditions[] = $alias.'.written_off_at IS NOT NULL';
        }
        if (in_array('collection_status', $columns, true)) {
            $conditions[] = 'LOWER(COALESCE('.$alias.'.collection_status, "")) IN ("blacklisted", "delinquent", "legal", "debt_collection", "recovery", "write_off", "written_off")';
        }
        if (in_array('risk_level', $columns, true)) {
            $conditions[] = 'LOWER(COALESCE('.$alias.'.risk_level, "")) IN ("high", "high_risk", "critical", "fraud_risk", "hard_skip")';
        }
        if ($customerAlias) {
            $conditions[] = 'COALESCE('.$customerAlias.'.blacklist_status, 0) = 1';
        }

        return $conditions ? '('.implode(' OR ', $conditions).')' : '0';
    }

    protected function sumYearlySummaryRows(array $rows): array
    {
        $totals = $this->emptyYearlySummaryRow(0);
        $totals['year'] = 'Total';
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                if ($key === 'year') {
                    continue;
                }
                $totals[$key] += $row[$key] ?? 0;
            }
        }

        return $totals;
    }

    protected function yearlySummaryCards(array $rows): array
    {
        $totals = $this->sumYearlySummaryRows($rows);

        return [
            ['label' => $this->loanReportText('Registered Installment Customers', 'អតិថិជនចុះឈ្មោះរំលស់'), 'value' => number_format((float) $totals['loan_count'], 0), 'icon' => 'fa fa-users', 'tone' => 'teal'],
            ['label' => $this->loanReportText('Registered Principal', 'ប្រាក់ដើមចុះឈ្មោះ'), 'value' => '$'.number_format((float) $totals['principal_total'], 2), 'icon' => 'fa fa-credit-card', 'tone' => 'blue'],
            ['label' => $this->loanReportText('Paid Total Customers', 'អតិថិជនបានបង់ទូរទៅ'), 'value' => number_format((float) $totals['paid_customer_count'], 0), 'icon' => 'fa fa-check-circle-o', 'tone' => 'green'],
            ['label' => $this->loanReportText('Paid Off Customers', 'អតិថិជនបានបង់ផ្ដាច់'), 'value' => number_format((float) $totals['closed_count'], 0), 'icon' => 'fa fa-check-square-o', 'tone' => 'orange'],
            ['label' => $this->loanReportText('Bad Customers', 'អតិថិជនរំលស់ខូច'), 'value' => number_format((float) $totals['bad_count'], 0), 'icon' => 'fa fa-warning', 'tone' => 'red'],
            ['label' => $this->loanReportText('Bad Balance', 'សមតុល្យអតិថិជនខូច'), 'value' => '$'.number_format((float) $totals['bad_balance_total'], 2), 'icon' => 'fa fa-line-chart', 'tone' => 'purple'],
        ];
    }

    protected function adminLoanRows(array $rows): array
    {
        return array_map(function (array $row) {
            $activeCount = max(0, (int) $row['loan_count'] - (int) $row['closed_count'] - (int) $row['bad_count']);

            return [
                'year' => $row['year'],
                'registered' => [
                    'customers' => (int) $row['loan_count'],
                    'loan_amount' => (float) $row['principal_total'],
                    'interest' => (float) $row['interest_total'],
                    'total_interest' => (float) $row['loan_total'],
                ],
                'general_paid' => [
                    'principal_paid' => (float) $row['collection_payment_total'],
                    'interest_paid' => (float) $row['deposit_payment_total'],
                    'interest_deducted' => 0.0,
                    'penalties_received' => max(0, (float) $row['payment_total'] - (float) $row['collection_payment_total'] - (float) $row['deposit_payment_total']),
                ],
                'paid_off' => [
                    'settled_customers' => (int) $row['closed_count'],
                    'settled_principal' => (float) $row['closed_principal_total'],
                    'settled_interest' => (float) $row['closed_interest_total'],
                    'settled_penalties' => 0.0,
                    'prepayment_discount' => max(0, (float) $row['closed_balance_total']),
                ],
                'active' => [
                    'active_customers' => $activeCount,
                    'active_principal' => max(0, (float) $row['principal_total'] - (float) $row['closed_principal_total'] - (float) $row['bad_principal_total']),
                    'active_monthly_interest' => max(0, (float) $row['interest_total'] - (float) $row['closed_interest_total'] - (float) $row['bad_interest_total']),
                    'active_total_interest' => max(0, (float) $row['loan_balance_total'] - (float) $row['bad_balance_total']),
                ],
                'bad_debt' => [
                    'bad_customers' => (int) $row['bad_count'],
                    'bad_principal' => (float) $row['bad_principal_total'],
                    'bad_interest' => (float) $row['bad_interest_total'],
                    'bad_total' => (float) $row['bad_balance_total'],
                ],
            ];
        }, $rows);
    }

    protected function adminLoanTotals(array $rows): array
    {
        $totals = [
            'registered' => ['customers' => 0, 'loan_amount' => 0.0, 'interest' => 0.0, 'total_interest' => 0.0],
            'general_paid' => ['principal_paid' => 0.0, 'interest_paid' => 0.0, 'interest_deducted' => 0.0, 'penalties_received' => 0.0],
            'paid_off' => ['settled_customers' => 0, 'settled_principal' => 0.0, 'settled_interest' => 0.0, 'settled_penalties' => 0.0, 'prepayment_discount' => 0.0],
            'active' => ['active_customers' => 0, 'active_principal' => 0.0, 'active_monthly_interest' => 0.0, 'active_total_interest' => 0.0],
            'bad_debt' => ['bad_customers' => 0, 'bad_principal' => 0.0, 'bad_interest' => 0.0, 'bad_total' => 0.0],
        ];

        foreach ($rows as $row) {
            foreach ($totals as $group => $columns) {
                foreach ($columns as $key => $value) {
                    $totals[$group][$key] += $row[$group][$key] ?? 0;
                }
            }
        }

        $totals['settlement_rate'] = $totals['registered']['customers'] > 0
            ? ($totals['paid_off']['settled_customers'] / $totals['registered']['customers']) * 100
            : 0;
        $totals['bad_debt_ratio'] = $totals['registered']['loan_amount'] > 0
            ? ($totals['bad_debt']['bad_principal'] / $totals['registered']['loan_amount']) * 100
            : 0;

        return $totals;
    }

    protected function parseYearlyLocationFilter(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (strpos($value, ':') !== false) {
            $filter = [];
            foreach (explode('|', $value) as $part) {
                [$key, $raw] = array_pad(explode(':', $part, 2), 2, null);
                if ($key === 'loan' && ctype_digit((string) $raw)) {
                    $filter['loan_location_id'] = (int) $raw;
                } elseif ($key === 'main' && ctype_digit((string) $raw)) {
                    $filter['main_location_id'] = (int) $raw;
                } elseif ($key === 'name' && $raw !== null) {
                    $name = trim(rawurldecode((string) $raw));
                    if ($name !== '') {
                        $filter['name'] = $name;
                    }
                }
            }

            return $filter;
        }

        if (ctype_digit($value)) {
            return ['legacy_id' => (int) $value];
        }

        return [];
    }

    protected function loanReportLocationOptions(): array
    {
        $options = [];

        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $hasMainLocationId = Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id');

            $options = DB::connection('mysql_loan')
                ->table('loan_business_locations')
                ->selectRaw('id, '.($hasMainLocationId ? 'main_location_id' : 'NULL as main_location_id').', COALESCE(NULLIF(name, ""), CONCAT("Location #", id)) as name')
                ->when(Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($row) {
                    $key = 'loan:'.(int) $row->id;
                    if (! empty($row->main_location_id)) {
                        $key .= '|main:'.(int) $row->main_location_id;
                    }
                    if (! empty($row->name)) {
                        $key .= '|name:'.rawurlencode((string) $row->name);
                    }

                    return [$key => $row->name];
                })
                ->all();
        }

        if (Schema::connection('mysql_loan')->hasTable('loans')) {
            $loanColumns = Schema::connection('mysql_loan')->getColumnListing('loans');
            foreach (['location_name_snapshot', 'business_location_name_snapshot'] as $column) {
                if (! in_array($column, $loanColumns, true)) {
                    continue;
                }

                DB::connection('mysql_loan')
                    ->table('loans')
                    ->selectRaw('DISTINCT '.$column.' as name')
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->orderBy($column)
                    ->get()
                    ->each(function ($row) use (&$options) {
                        $name = trim((string) ($row->name ?? ''));
                        if ($name === '') {
                            return;
                        }
                        if (in_array($name, $options, true)) {
                            return;
                        }
                        $key = 'name:'.rawurlencode($name);
                        if (! array_key_exists($key, $options)) {
                            $options[$key] = $name;
                        }
                    });
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    protected function downloadYearlyLoanSummaryCsv(array $payload, array $filters)
    {
        $columns = [
            $this->loanReportText('Year', 'ឆ្នាំ'),
            $this->loanReportText('Registered Count', 'ចំនួនចុះឈ្មោះ'), $this->loanReportText('Registered Principal', 'ប្រាក់ដើមចុះឈ្មោះ'), $this->loanReportText('Registered Interest', 'ការប្រាក់ចុះឈ្មោះ'), $this->loanReportText('Registered Total', 'សរុបចុះឈ្មោះ'),
            $this->loanReportText('Paid Customer Count', 'ចំនួនអតិថិជនបានបង់'), $this->loanReportText('Collection Payments', 'បង់ប្រចាំខែ'), $this->loanReportText('Deposit Payments', 'ប្រាក់កក់'), $this->loanReportText('Paid Total', 'បានបង់ទូរទៅ'),
            $this->loanReportText('Paid Off Count', 'ចំនួនបង់ផ្ដាច់'), $this->loanReportText('Paid Off Principal', 'ប្រាក់ដើមបង់ផ្ដាច់'), $this->loanReportText('Paid Off Interest', 'ការប្រាក់បង់ផ្ដាច់'), $this->loanReportText('Paid Off Total', 'សរុបបង់ផ្ដាច់'), $this->loanReportText('Paid Off Paid', 'បានបង់ផ្ដាច់'), $this->loanReportText('Paid Off Balance', 'សមតុល្យបង់ផ្ដាច់'),
            $this->loanReportText('Bad Count', 'ចំនួនអតិថិជនខូច'), $this->loanReportText('Bad Principal', 'ប្រាក់ដើមអតិថិជនខូច'), $this->loanReportText('Bad Interest', 'ការប្រាក់អតិថិជនខូច'), $this->loanReportText('Bad Total', 'សរុបអតិថិជនខូច'), $this->loanReportText('Bad Paid', 'បានបង់អតិថិជនខូច'), $this->loanReportText('Bad Balance', 'សមតុល្យអតិថិជនខូច'),
        ];
        $lines = [$columns];
        foreach ($payload['rows'] as $row) {
            $lines[] = [
                $row['year'],
                $row['loan_count'], $row['principal_total'], $row['interest_total'], $row['loan_total'],
                $row['paid_customer_count'], $row['collection_payment_total'], $row['deposit_payment_total'], $row['payment_total'],
                $row['closed_count'], $row['closed_principal_total'], $row['closed_interest_total'], $row['closed_loan_total'], $row['closed_paid_total'], $row['closed_balance_total'],
                $row['bad_count'], $row['bad_principal_total'], $row['bad_interest_total'], $row['bad_loan_total'], $row['bad_paid_total'], $row['bad_balance_total'],
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'yearly-loan-summary-'.$filters['start_year'].'-'.$filters['end_year'].'.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function loanReportText(string $english, string $khmer): string
    {
        return $this->loanReportIsKhmer() ? $khmer : $english;
    }

    protected function loanReportIsKhmer(): bool
    {
        return session('user.language', config('app.locale')) === 'km';
    }
}
