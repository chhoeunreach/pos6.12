<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanDashboardService
{
    protected string $connection = 'mysql_loan';

    public function getFilters($request): array
    {
        $now = Carbon::now();

        $paymentMethodId = $request->input('payment_method_id');

        return [
            'date_from' => $request->input('date_from', $now->copy()->startOfMonth()->toDateString()),
            'date_to' => $request->input('date_to', $now->copy()->endOfMonth()->toDateString()),
            'business_location_id' => $request->input('business_location_id'),
            'loan_status' => $request->input('loan_status'),
            'collector_id' => $request->input('collector_id'),
            'currency' => $request->input('currency'),
            'payment_method_id' => $paymentMethodId,
            'payment_method_snapshot' => $this->resolvePaymentMethodSnapshot($paymentMethodId),
        ];
    }

    public function getDashboardData($request): array
    {
        $filters = $this->getFilters($request);

        return [
            'cards' => $this->getSummaryCards($filters),
            'charts' => [
                'monthly_loan' => $this->getMonthlyLoanChart($filters),
                'monthly_collection' => $this->getMonthlyCollectionChart($filters),
                'loan_status' => $this->getLoanStatusChart($filters),
                'payment_method' => $this->getPaymentMethodChart($filters),
                'overdue_aging' => $this->getOverdueAgingChart($filters),
                'collector_performance' => $this->getCollectorPerformanceChart($filters),
                'customer_status' => $this->getCustomerStatusChart($filters),
                'daily_collection' => $this->getDailyCollectionChart($filters),
            ],
            'tables' => [
                'latest_loans' => $this->getLatestLoans($filters),
                'today_due_payments' => $this->getTodayDuePayments($filters),
                'overdue_customers' => $this->getOverdueCustomers($filters),
                'recent_payments' => $this->getRecentPayments($filters),
                'aba_transactions' => $this->getAbaTransactions($filters),
                'staff_latest_locations' => $this->getStaffLatestLocations($filters),
                'follow_up_customers' => $this->getFollowUpCustomers($filters),
                'blacklist_customers' => $this->getBlacklistCustomers($filters),
            ],
        ];
    }

    public function getSummaryCards($filters): array
    {
        $cards = [
            'total_loans' => 0, 'new_loans_this_month' => 0, 'active_loans' => 0, 'completed_loans' => 0,
            'pending_loans' => 0, 'rejected_loans' => 0, 'cancelled_loans' => 0, 'overdue_loans' => 0,
            'total_principal' => 0, 'total_payable' => 0, 'total_paid' => 0, 'total_balance' => 0,
            'today_collection' => 0, 'month_collection' => 0, 'penalty_collected' => 0, 'discount_given' => 0,
            'total_customers' => 0, 'active_customers' => 0, 'late_customers' => 0, 'follow_up_customers' => 0,
            'blacklist_customers' => 0, 'aba_pending' => 0, 'aba_paid' => 0, 'aba_failed' => 0,
            'collection_visits_today' => 0, 'staff_online' => 0, 'payment_proof_pending' => 0, 'id_card_scan_pending' => 0,
            'converted_sales' => 0, 'pending_sales_for_installment' => 0,
        ];

        if ($this->tableExists('loans')) {
            $q = $this->applyLoanFilters(DB::connection($this->connection)->table('loans'), $filters);
            $cards['total_loans'] = (int) (clone $q)->count();
            $cards['active_loans'] = (int) (clone $q)->where('status', 'active')->count();
            $cards['completed_loans'] = (int) (clone $q)->where('status', 'completed')->count();
            $cards['pending_loans'] = (int) (clone $q)->where('status', 'pending')->count();
            $cards['rejected_loans'] = (int) (clone $q)->where('status', 'rejected')->count();
            $cards['cancelled_loans'] = (int) (clone $q)->where('status', 'cancelled')->count();

            $cards['total_principal'] = (float) (clone $q)->sum('principal_amount');
            $cards['total_payable'] = (float) (clone $q)->sum('total_payable_amount');
            $cards['total_paid'] = (float) (clone $q)->sum('paid_amount');
            $cards['total_balance'] = max(0, $cards['total_payable'] - $cards['total_paid']);

            $cards['new_loans_this_month'] = (int) (clone $q)
                ->whereBetween('loan_date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
                ->count();
        }

        if ($this->tableExists('loan_payment_schedules')) {
            $overdue = $this->applyScheduleFilters(
                DB::connection($this->connection)->table('loan_payment_schedules as s')
                    ->join('loans as l', 'l.id', '=', 's.loan_id'),
                $filters
            )
                ->whereDate('s.due_date', '<', Carbon::today()->toDateString())
                ->whereIn('s.status', ['unpaid', 'partial', 'late']);

            $cards['overdue_loans'] = (int) (clone $overdue)->distinct('s.loan_id')->count('s.loan_id');
            $cards['late_customers'] = (int) (clone $overdue)->distinct('l.customer_id')->count('l.customer_id');
        }

        if ($this->tableExists('loan_payments')) {
            $paidQ = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
                ->where('status', 'paid');
            $cards['today_collection'] = (float) (clone $paidQ)->whereDate('paid_date', Carbon::today()->toDateString())->sum('total_paid_base');
            $cards['month_collection'] = (float) (clone $paidQ)->whereBetween('paid_date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])->sum('total_paid_base');
            $cards['penalty_collected'] = (float) (clone $paidQ)->sum('penalty_amount');
            $cards['discount_given'] = (float) (clone $paidQ)->sum('discount_amount');
            $cards['payment_proof_pending'] = (int) $this->getPaymentProofPendingCount($filters);
        }

        if ($this->tableExists('loan_customers')) {
            $cards['total_customers'] = (int) DB::connection($this->connection)->table('loan_customers')->count();
            $cards['active_customers'] = (int) DB::connection($this->connection)->table('loan_customers')
                ->where('status', 'active')->where(function ($q) {
                    $q->whereNull('blacklist_status')->orWhere('blacklist_status', 0);
                })->count();
            $cards['blacklist_customers'] = (int) DB::connection($this->connection)->table('loan_customers')->where('blacklist_status', 1)->count();
        }

        if ($this->tableExists('loan_customer_followups')) {
            $cards['follow_up_customers'] = (int) DB::connection($this->connection)->table('loan_customer_followups')
                ->whereIn('status', ['pending', 'today', 'in_progress'])
                ->whereDate('follow_up_date', '<=', Carbon::today()->toDateString())
                ->count();
        }

        if ($this->tableExists('loan_aba_transactions')) {
            $aba = DB::connection($this->connection)->table('loan_aba_transactions');
            $cards['aba_pending'] = (int) (clone $aba)->where('status', 'pending')->count();
            $cards['aba_paid'] = (int) (clone $aba)->where('status', 'paid')->count();
            $cards['aba_failed'] = (int) (clone $aba)->where('status', 'failed')->count();
        }

        if ($this->tableExists('loan_collection_visits')) {
            $cards['collection_visits_today'] = (int) DB::connection($this->connection)->table('loan_collection_visits')
                ->whereDate('visited_at', Carbon::today()->toDateString())
                ->count();
        }

        if ($this->tableExists('loan_staff_location_latest')) {
            $cards['staff_online'] = (int) DB::connection($this->connection)->table('loan_staff_location_latest')
                ->where('recorded_at', '>=', Carbon::now()->subMinutes(10)->toDateTimeString())
                ->count();
        }

        if ($this->tableExists('loan_id_card_scans')) {
            $cards['id_card_scan_pending'] = (int) DB::connection($this->connection)->table('loan_id_card_scans')->where('status', 'pending')->count();
        }

        if ($this->tableExists('loan_sell_transaction_links')) {
            $cards['converted_sales'] = (int) DB::connection($this->connection)->table('loan_sell_transaction_links')->count();
        }

        $pendingQ = DB::table('transactions')->where('type', 'sell')->where('status', 'final');
        if ($this->tableExists('loan_sell_transaction_links')) {
            $convertedIds = DB::connection($this->connection)->table('loan_sell_transaction_links')->pluck('transaction_id');
            if ($convertedIds->isNotEmpty()) {
                $pendingQ->whereNotIn('id', $convertedIds->all());
            }
        }
        $cards['pending_sales_for_installment'] = (int) $pendingQ->count();

        return $cards;
    }

    public function getMonthlyLoanChart($filters): array
    {
        if (! $this->tableExists('loans')) return ['labels' => [], 'count' => [], 'principal' => []];
        $rows = $this->applyLoanFilters(DB::connection($this->connection)->table('loans'), $filters)
            ->selectRaw("DATE_FORMAT(loan_date, '%Y-%m') as month_key, COUNT(*) as total_count, COALESCE(SUM(principal_amount),0) as total_principal")
            ->groupBy('month_key')->orderBy('month_key')->get();
        return ['labels' => $rows->pluck('month_key')->all(), 'count' => $rows->pluck('total_count')->map(fn ($v) => (int) $v)->all(), 'principal' => $rows->pluck('total_principal')->map(fn ($v) => (float) $v)->all()];
    }

    public function getMonthlyCollectionChart($filters): array
    {
        if (! $this->tableExists('loan_payments')) return ['labels' => [], 'amount' => []];
        $rows = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
            ->where('status', 'paid')
            ->selectRaw("DATE_FORMAT(paid_date, '%Y-%m') as month_key, COALESCE(SUM(total_paid_base),0) as total_amount")
            ->groupBy('month_key')->orderBy('month_key')->get();
        return ['labels' => $rows->pluck('month_key')->all(), 'amount' => $rows->pluck('total_amount')->map(fn ($v) => (float) $v)->all()];
    }

    public function getLoanStatusChart($filters): array
    {
        $statuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted'];
        if (! $this->tableExists('loans')) return ['labels' => $statuses, 'series' => array_fill(0, count($statuses), 0)];

        $rows = $this->applyLoanFilters(DB::connection($this->connection)->table('loans'), $filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        return ['labels' => $statuses, 'series' => collect($statuses)->map(fn ($s) => (int) ($rows[$s] ?? 0))->all()];
    }

    public function getPaymentMethodChart($filters): array
    {
        if (! $this->tableExists('loan_payment_details') || ! $this->tableExists('loan_payments')) return ['labels' => [], 'amount' => []];
        $rows = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payment_details as d')->join('loan_payments as p', 'p.id', '=', 'd.payment_id'), $filters, 'p')
            ->selectRaw('COALESCE(d.payment_method_snapshot, "Unknown") as label, COALESCE(SUM(d.amount_base),0) as amount')
            ->groupBy('label')->orderByDesc('amount')->get();

        return ['labels' => $rows->pluck('label')->all(), 'amount' => $rows->pluck('amount')->map(fn ($v) => (float) $v)->all()];
    }

    public function getOverdueAgingChart($filters): array
    {
        if (! $this->tableExists('loan_payment_schedules') || ! $this->tableExists('loans')) return ['labels' => ['1-7', '8-15', '16-30', '31-60', '60+'], 'series' => [0, 0, 0, 0, 0]];

        $rows = $this->applyScheduleFilters(DB::connection($this->connection)->table('loan_payment_schedules as s')->join('loans as l', 'l.id', '=', 's.loan_id'), $filters)
            ->whereDate('s.due_date', '<', Carbon::today()->toDateString())
            ->whereIn('s.status', ['unpaid', 'partial', 'late'])
            ->selectRaw('DATEDIFF(CURDATE(), s.due_date) as overdue_days, COALESCE(s.balance_amount, s.schedule_amount, 0) as balance_amount')
            ->get();

        $buckets = ['1-7' => 0, '8-15' => 0, '16-30' => 0, '31-60' => 0, '60+' => 0];
        foreach ($rows as $row) {
            $d = (int) $row->overdue_days;
            if ($d <= 7) $buckets['1-7']++;
            elseif ($d <= 15) $buckets['8-15']++;
            elseif ($d <= 30) $buckets['16-30']++;
            elseif ($d <= 60) $buckets['31-60']++;
            else $buckets['60+']++;
        }

        return ['labels' => array_keys($buckets), 'series' => array_values($buckets)];
    }

    public function getCollectorPerformanceChart($filters): array
    {
        if (! $this->tableExists('loans')) return [];

        $loans = $this->applyLoanFilters(DB::connection($this->connection)->table('loans'), $filters)
            ->selectRaw('COALESCE(collector_name_snapshot, CONCAT("Collector #", collector_id), "Unassigned") as collector_name, COUNT(*) as assigned_loans')
            ->groupBy('collector_name')->get()->keyBy('collector_name');

        $payments = collect();
        if ($this->tableExists('loan_payments')) {
            $payments = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
                ->where('status', 'paid')
                ->selectRaw('COALESCE(collected_by_name_snapshot, "Unknown") as collector_name, COALESCE(SUM(total_paid_base),0) as collected_amount')
                ->groupBy('collector_name')->get()->keyBy('collector_name');
        }

        $visits = collect();
        if ($this->tableExists('loan_collection_visits')) {
            $visits = DB::connection($this->connection)->table('loan_collection_visits')
                ->selectRaw('COALESCE(staff_name_snapshot, "Unknown") as collector_name, COUNT(*) as visit_count')
                ->groupBy('collector_name')->get()->keyBy('collector_name');
        }

        $names = $loans->keys()->merge($payments->keys())->merge($visits->keys())->unique()->values();

        return $names->map(function ($name) use ($loans, $payments, $visits) {
            return [
                'collector' => $name,
                'assigned_loans' => (int) ($loans[$name]->assigned_loans ?? 0),
                'collected_amount' => (float) ($payments[$name]->collected_amount ?? 0),
                'overdue_amount' => 0,
                'visit_count' => (int) ($visits[$name]->visit_count ?? 0),
            ];
        })->all();
    }

    public function getCustomerStatusChart($filters): array
    {
        if (! $this->tableExists('loan_customers')) return ['labels' => ['active', 'inactive', 'blacklist', 'late'], 'series' => [0, 0, 0, 0]];

        $active = (int) DB::connection($this->connection)->table('loan_customers')->where('status', 'active')->count();
        $inactive = (int) DB::connection($this->connection)->table('loan_customers')->where('status', 'inactive')->count();
        $blacklist = (int) DB::connection($this->connection)->table('loan_customers')->where('blacklist_status', 1)->count();
        $late = $this->getSummaryCards($filters)['late_customers'];

        return ['labels' => ['active', 'inactive', 'blacklist', 'late'], 'series' => [$active, $inactive, $blacklist, (int) $late]];
    }

    public function getDailyCollectionChart($filters): array
    {
        if (! $this->tableExists('loan_payments')) return ['labels' => [], 'amount' => []];

        $from = Carbon::parse($filters['date_from']);
        $to = Carbon::parse($filters['date_to']);

        $rows = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
            ->where('status', 'paid')
            ->selectRaw('DATE(paid_date) as paid_day, COALESCE(SUM(total_paid_base),0) as total_amount')
            ->groupBy('paid_day')->orderBy('paid_day')->get()->keyBy('paid_day');

        $labels = [];
        $amount = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $key;
            $amount[] = (float) ($rows[$key]->total_amount ?? 0);
        }

        return compact('labels', 'amount');
    }

    public function getLatestLoans($filters): array
    {
        if (! $this->tableExists('loans')) return [];
        return $this->applyLoanFilters(DB::connection($this->connection)->table('loans'), $filters)
            ->select('id', 'loan_number', 'customer_name_snapshot', 'customer_phone_snapshot', 'principal_amount', 'paid_amount', 'balance_amount', 'currency', 'status', 'loan_date')
            ->orderByDesc('loan_date')->limit(20)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getTodayDuePayments($filters): array
    {
        if (! $this->tableExists('loan_payment_schedules') || ! $this->tableExists('loans')) return [];
        return $this->applyScheduleFilters(DB::connection($this->connection)->table('loan_payment_schedules as s')->join('loans as l', 'l.id', '=', 's.loan_id'), $filters)
            ->whereDate('s.due_date', Carbon::today()->toDateString())
            ->selectRaw('l.id, l.loan_number, l.customer_name_snapshot as customer, l.customer_phone_snapshot as phone, s.due_date, COALESCE(s.schedule_amount,0) as schedule_amount, COALESCE(s.paid_amount,0) as paid_amount, COALESCE(s.balance_amount,0) as balance, COALESCE(l.collector_name_snapshot,"-") as collector')
            ->orderBy('s.due_date')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getOverdueCustomers($filters): array
    {
        if (! $this->tableExists('loan_payment_schedules') || ! $this->tableExists('loans')) return [];
        return $this->applyScheduleFilters(DB::connection($this->connection)->table('loan_payment_schedules as s')->join('loans as l', 'l.id', '=', 's.loan_id'), $filters)
            ->whereDate('s.due_date', '<', Carbon::today()->toDateString())
            ->whereIn('s.status', ['unpaid', 'partial', 'late'])
            ->selectRaw('l.id, l.loan_number, l.customer_name_snapshot as customer, l.customer_phone_snapshot as phone, DATEDIFF(CURDATE(), s.due_date) as overdue_days, COALESCE(s.balance_amount,0) as overdue_amount, COALESCE(l.collector_name_snapshot,"-") as collector, NULL as last_visit')
            ->orderByDesc('overdue_days')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getRecentPayments($filters): array
    {
        if (! $this->tableExists('loan_payments')) return [];
        return $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
            ->selectRaw('id, receipt_number, customer_name_snapshot, loan_number_snapshot as loan_number, COALESCE(total_paid_base,0) as paid_amount, payment_method_snapshot as payment_method, received_by_name_snapshot, paid_date')
            ->orderByDesc('paid_date')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getAbaTransactions($filters): array
    {
        if (! $this->tableExists('loan_aba_transactions')) return [];
        return DB::connection($this->connection)->table('loan_aba_transactions')
            ->selectRaw('id, tran_id, customer_name_snapshot as customer, amount, currency, status, created_at')
            ->orderByDesc('created_at')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getStaffLatestLocations($filters): array
    {
        if (! $this->tableExists('loan_staff_location_latest')) return [];
        $onlineCutoff = Carbon::now()->subMinutes(10)->toDateTimeString();
        return DB::connection($this->connection)->table('loan_staff_location_latest')
            ->selectRaw('id, staff_name_snapshot, latitude, longitude, battery_level, recorded_at')
            ->orderByDesc('recorded_at')->limit(100)->get()->map(function ($r) use ($onlineCutoff) {
                $a = (array) $r;
                $a['online_status'] = ($r->recorded_at >= $onlineCutoff) ? 'online' : 'offline';
                return $a;
            })->all();
    }

    public function getFollowUpCustomers($filters): array
    {
        if (! $this->tableExists('loan_customer_followups')) return [];
        return DB::connection($this->connection)->table('loan_customer_followups')
            ->selectRaw('id, customer_name_snapshot as customer, customer_phone_snapshot as phone, follow_up_date, follow_up_type, status, assigned_staff_name_snapshot as assigned_staff, note')
            ->whereDate('follow_up_date', '<=', Carbon::today()->toDateString())
            ->orderBy('follow_up_date')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getBlacklistCustomers($filters): array
    {
        if (! $this->tableExists('loan_customers')) return [];
        return DB::connection($this->connection)->table('loan_customers')
            ->selectRaw('id, customer_name as customer, phone, id_card_number, blacklist_reason, created_at')
            ->where('blacklist_status', 1)
            ->orderByDesc('created_at')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function tableExists(string $table): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function applyLoanFilters(Builder $query, array $filters, string $alias = 'loans'): Builder
    {
        $prefix = $alias.'.';
        $this->applyDateRange($query, $prefix.'loan_date', $filters['date_from'], $filters['date_to']);
        if (! empty($filters['business_location_id'])) $query->where($prefix.'business_location_id', $filters['business_location_id']);
        if (! empty($filters['loan_status'])) $query->where($prefix.'status', $filters['loan_status']);
        if (! empty($filters['collector_id'])) {
            $collectorId = $filters['collector_id'];
            $query->where(function ($q) use ($prefix, $collectorId) {
                $q->where($prefix.'collector_id', $collectorId)->orWhere($prefix.'assigned_to', $collectorId);
            });
        }
        if (! empty($filters['currency'])) $query->where($prefix.'currency', $filters['currency']);

        if (! auth()->user()->hasRole('Admin') && ! auth()->user()->hasRole('Manager')) {
            $uid = auth()->id();
            $query->where(function ($q) use ($prefix, $uid) {
                $q->where($prefix.'assigned_to', $uid)->orWhere($prefix.'collector_id', $uid);
            });
        }

        return $query;
    }

    protected function applyPaymentFilters(Builder $query, array $filters, string $alias = 'loan_payments'): Builder
    {
        $prefix = $alias.'.';
        $this->applyDateRange($query, $prefix.'paid_date', $filters['date_from'], $filters['date_to']);
        if (! empty($filters['currency'])) $query->where($prefix.'base_currency', $filters['currency']);
        if (! empty($filters['payment_method_snapshot'])) {
            if (str_contains(strtolower($alias), 'loan_payment_details') || str_contains(strtolower($alias), ' d')) {
                $query->where('d.payment_method_snapshot', $filters['payment_method_snapshot']);
            } elseif ($this->columnExists('loan_payments', 'payment_method_snapshot')) {
                $query->where($prefix.'payment_method_snapshot', $filters['payment_method_snapshot']);
            }
        }
        return $query;
    }

    protected function resolvePaymentMethodSnapshot(?string $paymentMethodId): ?string
    {
        if (empty($paymentMethodId)) {
            return null;
        }

        if (! Schema::hasTable('payment_methods')) {
            return null;
        }

        return DB::table('payment_methods')
            ->where('id', $paymentMethodId)
            ->value('name');
    }

    protected function applyScheduleFilters(Builder $query, array $filters): Builder
    {
        $this->applyDateRange($query, 's.due_date', $filters['date_from'], $filters['date_to']);
        if (! empty($filters['business_location_id'])) $query->where('l.business_location_id', $filters['business_location_id']);
        if (! empty($filters['loan_status'])) $query->where('l.status', $filters['loan_status']);
        if (! empty($filters['collector_id'])) {
            $collectorId = $filters['collector_id'];
            $query->where(function ($q) use ($collectorId) {
                $q->where('l.collector_id', $collectorId)->orWhere('l.assigned_to', $collectorId);
            });
        }
        if (! empty($filters['currency'])) $query->where('l.currency', $filters['currency']);

        if (! auth()->user()->hasRole('Admin') && ! auth()->user()->hasRole('Manager')) {
            $uid = auth()->id();
            $query->where(function ($q) use ($uid) {
                $q->where('l.assigned_to', $uid)->orWhere('l.collector_id', $uid);
            });
        }

        return $query;
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if (! empty($from)) $query->whereDate($column, '>=', $from);
        if (! empty($to)) $query->whereDate($column, '<=', $to);
    }

    protected function getPaymentProofPendingCount(array $filters): int
    {
        $requireProof = false;
        if ($this->tableExists('loan_settings')) {
            $value = DB::connection($this->connection)->table('loan_settings')->where('key', 'require_payment_proof')->value('value');
            $requireProof = in_array((string) $value, ['1', 'true', 'yes'], true);
        }

        $q = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters);
        if ($requireProof && $this->columnExists('loan_payments', 'proof_file_id')) {
            $q->where(function ($qq) {
                $qq->where('status', 'pending')->orWhereNull('proof_file_id');
            });
        } else {
            $q->where('status', 'pending');
        }

        return (int) $q->count();
    }
}
