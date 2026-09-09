<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanDashboardService
{
    protected string $connection = 'mysql_loan';
    private array $schemaCache = [];

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

        $isRealtime = $request->boolean('realtime');
        $cacheKey = 'loan_dashboard_data_'.($isRealtime ? 'realtime_' : '').auth()->id().'_'.md5(json_encode($filters));
        $ttl = $isRealtime ? 15 : 300;

        return Cache::remember($cacheKey, $ttl, function () use ($filters) {
            $summary = $this->getSummaryCards($filters);

            return [
                'quick_cards' => $this->getQuickCards($filters, $summary),
                'cards' => $summary,
                'charts' => [
                    'monthly_loan' => $this->getMonthlyLoanChart($filters),
                    'monthly_collection' => $this->getMonthlyCollectionChart($filters),
                    'loan_status' => $this->getLoanStatusChart($filters),
                    'payment_method' => $this->getPaymentMethodChart($filters),
                    'overdue_aging' => $this->getOverdueAgingChart($filters),
                    'collector_performance' => $this->getCollectorPerformanceChart($filters),
                    'customer_status' => $this->getCustomerStatusChart($filters, $summary),
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
        });
    }

    public function searchLoansForDashboard(string $term, int $limit = 10, ?int $locationId = null): array
    {
        $term = trim($term);
        if (! $this->tableExists('loans')) {
            return [];
        }

        $query = $this->loanQueryWithCustomer('l');
        $customerNameExpr = $this->loanCustomerNameExpression('l');
        $customerPhoneExpr = $this->loanCustomerPhoneExpression('l');
        $loanNumberExpr = $this->columnExists('loans', 'loan_number') ? 'l.loan_number' : 'CAST(l.id AS CHAR)';
        $balanceExpr = $this->loanBalanceExpression('l');
        $paidExpr = $this->loanPaidExpression('l');
        $dueNowExpr = $this->installmentDueNowExpression('l');
        $locationNameExpr = $this->columnExists('loans', 'location_name_snapshot') ? 'l.location_name_snapshot' : 'NULL';
        $nextDueDate = $this->nextUncoveredDueDateExpression('l');
        $isOverdueExpr = $this->installmentOverdueExpression('l');

        $query->selectRaw("
                l.id,
                ".($this->columnExists('loans', 'customer_id') ? 'l.customer_id' : 'NULL')." as customer_id,
                ".($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'telegram_chat_id') ? 'c.telegram_chat_id' : 'NULL')." as telegram_chat_id,
                COALESCE(
                    ".($this->columnExists('loans', 'customer_photo_file_id') ? 'NULLIF(l.customer_photo_file_id, 0)' : 'NULL').",
                    ".($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'customer_photo_file_id') ? 'NULLIF(c.customer_photo_file_id, 0)' : 'NULL')."
                ) as customer_photo_file_id,
                ".($this->columnExists('loans', 'customer_photo_snapshot') ? 'l.customer_photo_snapshot' : 'NULL')." as customer_photo_snapshot,
                {$loanNumberExpr} as loan_number,
                {$customerNameExpr} as customer_name,
                {$customerPhoneExpr} as customer_phone,
                {$locationNameExpr} as location_name,
                {$dueNowExpr} as due_now_amount,
                {$paidExpr} as paid_amount,
                {$balanceExpr} as balance_amount,
                ".($this->columnExists('loans', 'status') ? 'l.status' : "'-'")." as status,
                {$nextDueDate} as next_due_date,
                {$isOverdueExpr} as is_overdue
            ");

        if (! empty($locationId) && $this->columnExists('loans', 'business_location_id')) {
            $query->where('l.business_location_id', $locationId);
        }

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like, $term, $customerNameExpr, $customerPhoneExpr) {
                $q->whereRaw($customerNameExpr.' LIKE ?', [$like])
                    ->orWhereRaw($customerPhoneExpr.' LIKE ?', [$like]);

                if ($this->columnExists('loans', 'loan_number')) {
                    $q->orWhere('l.loan_number', 'like', $like);
                }

                if (ctype_digit($term)) {
                    $q->orWhere('l.id', (int) $term);
                }
            });

            $query->orderByRaw('CASE WHEN '.($this->columnExists('loans', 'loan_number') ? 'l.loan_number' : 'CAST(l.id AS CHAR)').' = ? THEN 0 ELSE 1 END', [$term]);
        }

        $query->orderByDesc('l.id')
            ->limit(max(1, min($limit, 25)));

        return $query->get()->map(function ($row) {
            $balance = round((float) ($row->balance_amount ?? 0), 2);
            $dueNow = round((float) ($row->due_now_amount ?? 0), 2);
            $status = $row->status ?: '-';
            $isOverdue = (bool) ($row->is_overdue ?? false);
            $today = Carbon::today()->toDateString();
            $nextDue = ($balance > 0 && ! empty($row->next_due_date)) ? substr((string) $row->next_due_date, 0, 10) : null;

            if ($this->canReadPaymentSchedules()) {
                if ($balance <= 0) {
                    $status = 'completed';
                    $nextDue = null;
                } elseif ($isOverdue) {
                    $status = 'overdue';
                } elseif ($nextDue === $today || ($dueNow > 0 && $nextDue && $nextDue <= $today)) {
                    $status = 'due_today';
                } elseif (! empty($nextDue)) {
                    $status = 'upcoming';
                } else {
                    $status = 'active';
                }
            } elseif ($balance <= 0) {
                $status = 'completed';
                $nextDue = null;
            } elseif ($isOverdue) {
                $status = 'overdue';
            } elseif ($nextDue === $today || $dueNow > 0) {
                $status = 'due_today';
            }

            return [
                'id' => (int) $row->id,
                'customer_id' => (int) ($row->customer_id ?? 0),
                'telegram_linked' => ! empty($row->telegram_chat_id),
                'customer_photo_url' => $this->customerPhotoUrl((int) ($row->customer_photo_file_id ?? 0))
                    ?: $this->loanFilePublicUrl($row->customer_photo_snapshot ?? null)
                    ?: $this->latestCustomerPhotoUrl((int) ($row->customer_id ?? 0))
                    ?: $this->latestCustomerImageUrl((int) ($row->customer_id ?? 0)),
                'loan_number' => $row->loan_number ?: ('#'.$row->id),
                'customer_name' => $row->customer_name ?: '-',
                'customer_phone' => $row->customer_phone ?: '-',
                'location_name' => $row->location_name ?: null,
                'due_now_amount' => $dueNow,
                'paid_amount' => round((float) ($row->paid_amount ?? 0), 2),
                'balance_amount' => $balance,
                'status' => $status,
                'is_overdue' => $isOverdue,
                'next_due_date' => $nextDue,
            ];
        })->all();

    }

    protected function customerPhotoUrl(int $fileId): ?string
    {
        if ($fileId <= 0 || ! $this->tableExists('loan_files')) {
            return null;
        }

        $file = DB::connection($this->connection)->table('loan_files')->where('id', $fileId)->first();
        if (! $file || empty($file->path)) {
            return null;
        }

        return $this->loanFilePublicUrl($file->path, $file->disk ?? 'public')
            ?: url('loan-management/chat-files/'.$fileId);
    }

    protected function latestCustomerPhotoUrl(int $customerId): ?string
    {
        if ($customerId <= 0 || ! $this->tableExists('loan_files')) {
            return null;
        }

        $query = DB::connection($this->connection)->table('loan_files')
            ->where('fileable_type', \Modules\LoanManagement\Entities\LoanCustomer::class)
            ->where('fileable_id', $customerId)
            ->where('category', 'customer_photo')
            ->orderByDesc('id');

        if ($this->columnExists('loan_files', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $file = $query->first();
        if (! $file || empty($file->path)) {
            return null;
        }

        return $this->loanFilePublicUrl($file->path, $file->disk ?? 'public')
            ?: url('loan-management/chat-files/'.(int) $file->id);
    }

    protected function latestCustomerImageUrl(int $customerId): ?string
    {
        if ($customerId <= 0 || ! $this->tableExists('loan_files')) {
            return null;
        }

        $query = DB::connection($this->connection)->table('loan_files')
            ->where('fileable_type', \Modules\LoanManagement\Entities\LoanCustomer::class)
            ->where('fileable_id', $customerId)
            ->orderByRaw("CASE category WHEN 'customer_photo' THEN 0 WHEN 'document' THEN 1 WHEN 'id_front' THEN 2 ELSE 3 END")
            ->orderByDesc('id');

        if ($this->columnExists('loan_files', 'mime_type')) {
            $query->where('mime_type', 'like', 'image/%');
        }
        if ($this->columnExists('loan_files', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $file = $query->first();
        if (! $file || empty($file->path)) {
            return null;
        }

        return $this->loanFilePublicUrl($file->path, $file->disk ?? 'public')
            ?: url('loan-management/chat-files/'.(int) $file->id);
    }

    protected function loanFilePublicUrl(?string $path, ?string $disk = 'public'): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:image/'])) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        if (is_file(public_path($path))) {
            return asset($path);
        }

        $disk = $disk ?: 'public';

        if ($disk === 'public') {
            return asset('storage/'.$path);
        }

        return Storage::disk($disk)->exists($path) ? Storage::disk($disk)->url($path) : null;
    }

    public function searchSellsForDashboard(string $term, int $limit = 10): array
    {
        $term = trim($term);
        $installmentCustomerGroups = ['រំលស់', 'អ៊ីអន'];

        if (! Schema::hasTable('transactions') || ! Schema::hasTable('transaction_payments')) {
            return [];
        }

        $paidSub = DB::table('transaction_payments')
            ->selectRaw('transaction_id, COALESCE(SUM(amount),0) as paid_amount')
            ->groupBy('transaction_id');

        $query = DB::table('transactions as t')
            ->leftJoinSub($paidSub, 'tp', function ($join) {
                $join->on('tp.transaction_id', '=', 't.id');
            })
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where(function ($q) use ($installmentCustomerGroups) {
                $q->whereIn('tcg.name', $installmentCustomerGroups)
                    ->orWhere(function ($fallback) use ($installmentCustomerGroups) {
                        $fallback->whereNull('tcg.id')
                            ->whereIn('ccg.name', $installmentCustomerGroups);
                    });
            })
            ->selectRaw("
                t.id,
                t.transaction_date,
                t.invoice_no,
                COALESCE(c.name, '-') as customer_name,
                COALESCE(c.mobile, '-') as customer_phone,
                COALESCE(t.final_total, 0) as final_total,
                COALESCE(tp.paid_amount, 0) as paid_amount,
                (COALESCE(t.final_total, 0) - COALESCE(tp.paid_amount, 0)) as due_amount
            ");

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like, $term) {
                $q->where('t.invoice_no', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('c.mobile', 'like', $like);

                if (ctype_digit($term)) {
                    $q->orWhere('t.id', (int) $term);
                }
            });

            $query->orderByRaw('CASE WHEN t.invoice_no = ? THEN 0 ELSE 1 END', [$term]);
        }

        $rows = $query->orderByDesc('t.id')
            ->limit(max(1, min($limit, 25)))
            ->get();

        $linkedLoanIds = [];
        if ($this->tableExists('loans') && $this->columnExists('loans', 'source_transaction_id') && $rows->isNotEmpty()) {
            $linkedLoanIds = DB::connection($this->connection)->table('loans')
                ->whereIn('source_transaction_id', $rows->pluck('id')->all())
                ->pluck('id', 'source_transaction_id')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        return $rows->map(function ($row) use ($linkedLoanIds) {
            $linkedLoanId = $linkedLoanIds[$row->id] ?? null;

            return [
                'id' => (int) $row->id,
                'transaction_date' => $row->transaction_date,
                'invoice_no' => $row->invoice_no ?: ('Sell #'.$row->id),
                'customer_name' => $row->customer_name ?: '-',
                'customer_phone' => $row->customer_phone ?: '-',
                'final_total' => round((float) ($row->final_total ?? 0), 2),
                'paid_amount' => round((float) ($row->paid_amount ?? 0), 2),
                'due_amount' => round(max(0, (float) ($row->due_amount ?? 0)), 2),
                'linked_loan_id' => $linkedLoanId,
                'is_converted' => $linkedLoanId !== null && $linkedLoanId > 0,
            ];
        })->all();
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
            if ($this->columnExists('loans', 'total_payable_amount')) {
                $cards['total_payable'] = (float) (clone $q)->sum('total_payable_amount');
            } elseif ($this->columnExists('loans', 'total_payable')) {
                $cards['total_payable'] = (float) (clone $q)->sum('total_payable');
            } elseif ($this->columnExists('loans', 'total_amount')) {
                $cards['total_payable'] = (float) (clone $q)->sum('total_amount');
            } else {
                $principal = $this->columnExists('loans', 'principal_amount') ? (float) (clone $q)->sum('principal_amount') : 0.0;
                $interest = $this->columnExists('loans', 'interest_amount') ? (float) (clone $q)->sum('interest_amount') : 0.0;
                $cards['total_payable'] = $principal + $interest;
            }
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
                ->whereRaw($this->scheduleBalanceExpression('s').' > 0')
                ->when($this->columnExists('loan_payment_schedules', 'status'), function ($query) {
                    $query->where(function ($st) {
                        $st->whereIn('s.status', $this->openScheduleStatuses())
                            ->orWhereNull('s.status');
                    });
                });

            $cards['overdue_loans'] = (int) (clone $overdue)->distinct('s.loan_id')->count('s.loan_id');
            $cards['late_customers'] = (int) (clone $overdue)->distinct('l.customer_id')->count('l.customer_id');
        }

        if ($this->tableExists('loan_payments')) {
            $paidQ = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
                ->when($this->columnExists('loan_payments', 'status'), fn ($query) => $query->whereIn('status', ['paid', 'confirmed']));
            $paidDateCol = $this->paymentDateColumn();
            $paidAmountCol = $this->paymentAmountColumn();
            $cards['today_collection'] = (float) (clone $paidQ)->whereDate($paidDateCol, Carbon::today()->toDateString())->sum($paidAmountCol);
            $cards['month_collection'] = (float) (clone $paidQ)->whereBetween($paidDateCol, [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])->sum($paidAmountCol);
            $cards['penalty_collected'] = $this->sumExistingPaymentColumn(clone $paidQ, 'penalty_amount');
            $cards['discount_given'] = $this->sumExistingPaymentColumn(clone $paidQ, 'discount_amount');
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
            $fQ = DB::connection($this->connection)->table('loan_customer_followups')
                ->whereIn('status', ['pending', 'today', 'in_progress']);
            $fQ->whereDate($this->followUpDateColumn(), '<=', Carbon::today()->toDateString());
            $cards['follow_up_customers'] = (int) $fQ->count();
        }

        if ($this->tableExists('loan_aba_transactions')) {
            $aba = DB::connection($this->connection)->table('loan_aba_transactions');
            $cards['aba_pending'] = (int) (clone $aba)->where('status', 'pending')->count();
            $cards['aba_paid'] = (int) (clone $aba)->where('status', 'paid')->count();
            $cards['aba_failed'] = (int) (clone $aba)->where('status', 'failed')->count();
        }

        if ($this->tableExists('loan_collection_visits')) {
            $visitQ = DB::connection($this->connection)->table('loan_collection_visits');
            if ($this->columnExists('loan_collection_visits', 'visited_at')) {
                $visitQ->whereDate('visited_at', Carbon::today()->toDateString());
            } elseif ($this->columnExists('loan_collection_visits', 'created_at')) {
                $visitQ->whereDate('created_at', Carbon::today()->toDateString());
            } else {
                $visitQ->whereRaw('1 = 0');
            }
            $cards['collection_visits_today'] = (int) $visitQ->count();
        }

        if ($this->tableExists('loan_staff_location_latest')) {
            $cards['staff_online'] = (int) DB::connection($this->connection)->table('loan_staff_location_latest')
                ->where('recorded_at', '>=', Carbon::now()->subMinutes(10)->toDateTimeString())
                ->count();
        }

        if ($this->tableExists('loan_id_card_scans')) {
            $cards['id_card_scan_pending'] = (int) DB::connection($this->connection)->table('loan_id_card_scans')->where('status', 'pending')->count();
        }

        return $cards;
    }

    public function getQuickCards(array $filters, ?array $summary = null): array
    {
        $summary = $summary ?? $this->getSummaryCards($filters);
        $pendingVisits = 0;
        $unreadChats = 0;

        if ($this->tableExists('loan_collection_visits')) {
            $pendingQ = DB::connection($this->connection)->table('loan_collection_visits');
            if ($this->columnExists('loan_collection_visits', 'result')) {
                $pendingQ->whereIn('result', ['pending', 'follow_up', 'rescheduled']);
            } elseif ($this->columnExists('loan_collection_visits', 'status')) {
                $pendingQ->whereIn('status', ['pending', 'follow_up', 'rescheduled']);
            } elseif ($this->columnExists('loan_collection_visits', 'visited_at')) {
                $pendingQ->whereNull('visited_at');
            } else {
                $pendingQ->whereRaw('1 = 0');
            }
            $pendingVisits = (int) $pendingQ->count();
        }

        $unreadChats = $this->unreadChatCount();

        $collection = [
            'due_today' => $this->dueTodayLoanCount($filters),
            'overdue_accounts' => $this->overdueLoanCount($filters),
            'skip_customers' => 0,
            'broken_ptp' => $this->brokenPtpLoanCount($filters),
            'field_visits_today' => 0,
            'collection_amount_today' => $this->todayCollectionAmount($filters),
            'recovery_cases' => 0,
            'legal_cases' => 0,
            'high_risk_customers' => 0,
            'repossessions' => 0,
        ];
        if ($this->tableExists('loans') && $this->columnExists('loans', 'collection_status')) {
            $loanQ = $this->applyLoanDimensionFilters(DB::connection($this->connection)->table('loans'), $filters);
            $collection['due_today'] = max($collection['due_today'], (int) (clone $loanQ)->where('collection_status', 'due_today')->count());
            $collection['skip_customers'] = (int) (clone $loanQ)->where('collection_status', 'skip_customer')->count();
            $collection['broken_ptp'] = max($collection['broken_ptp'], (int) (clone $loanQ)->where('collection_status', 'broken_ptp')->count());
            $collection['recovery_cases'] = (int) (clone $loanQ)->where('collection_status', 'recovery')->count();
            $collection['legal_cases'] = (int) (clone $loanQ)->where('collection_status', 'legal')->count();
            $collection['repossessions'] = (int) (clone $loanQ)->where('collection_status', 'repossession')->count();
            if ($this->columnExists('loans', 'risk_level')) {
                $collection['high_risk_customers'] = (int) (clone $loanQ)->whereIn('risk_level', ['high_risk', 'critical'])->count();
            }
            if ($this->columnExists('loans', 'field_visit_required') && $this->columnExists('loans', 'next_followup_at')) {
                $collection['field_visits_today'] = (int) (clone $loanQ)->where('field_visit_required', 1)->whereDate('next_followup_at', '<=', now()->toDateString())->count();
            }
        }

        $pendingLoanCount = 0;
        if ($this->tableExists('loans')) {
            $pendingLoanQuery = DB::connection($this->connection)->table('loans')
                ->whereIn('status', ['pending', 'draft', 'pending_approval']);
            if ($this->columnExists('loans', 'deleted_at')) {
                $pendingLoanQuery->whereNull('deleted_at');
            }
            $pendingLoanCount = (int) $pendingLoanQuery->count();
        }

        return array_merge([
            'total_loans' => $this->countAllInstallments(),
            'pending_requests' => $pendingLoanCount,
            'active_loans' => (int) ($summary['active_loans'] ?? 0),
            'today_collection' => $collection['collection_amount_today'],
            'overdue_amount' => (float) ($summary['total_balance'] ?? 0),
            'late_customers' => (int) ($summary['late_customers'] ?? 0),
            'monthly_income' => (float) ($summary['month_collection'] ?? 0),
            'pending_visits' => $pendingVisits,
            'unread_chats' => $unreadChats,
            'active_collectors' => (int) ($summary['staff_online'] ?? 0),
            'blacklist_customers' => (int) ($summary['blacklist_customers'] ?? 0),
        ], $collection);
    }

    protected function unreadChatCount(): int
    {
        if ($this->tableExists('loan_telegram_chat_threads')
            && $this->columnExists('loan_telegram_chat_threads', 'unread_staff_count')) {
            return (int) DB::connection($this->connection)->table('loan_telegram_chat_threads')
                ->where('unread_staff_count', '>', 0)
                ->count();
        }

        if ($this->tableExists('loan_chat_messages')) {
            return (int) DB::connection($this->connection)->table('loan_chat_messages')
                ->where(function ($q) {
                    $q->whereNull('is_read')->orWhere('is_read', 0);
                })
                ->count();
        }

        return 0;
    }

    protected function countAllInstallments(): int
    {
        if (! $this->tableExists('loans')) {
            return 0;
        }

        $query = DB::connection($this->connection)->table('loans');
        if ($this->columnExists('loans', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function dueTodayLoanCount(array $filters): int
    {
        if (! $this->tableExists('loan_payment_schedules') || ! $this->tableExists('loans')) {
            return 0;
        }

        return (int) $this->applyScheduleFilters($this->scheduleQueryWithLoanCustomer(), $filters, false)
            ->whereDate('s.due_date', Carbon::today()->toDateString())
            ->when($this->columnExists('loan_payment_schedules', 'status'), fn ($query) => $query->whereIn('s.status', $this->openScheduleStatuses()))
            ->whereRaw($this->scheduleBalanceExpression('s').' > 0')
            ->distinct('s.loan_id')
            ->count('s.loan_id');
    }

    protected function overdueLoanCount(array $filters): int
    {
        if (! $this->tableExists('loans')) {
            return 0;
        }

        $query = $this->applyLoanDimensionFilters($this->loanQueryWithCustomer('l'), $filters, 'l');
        $this->whereOverdueInstallment($query);

        return (int) $query->distinct('l.id')->count('l.id');
    }

    protected function brokenPtpLoanCount(array $filters): int
    {
        if (! $this->tableExists('loans')) {
            return 0;
        }

        $query = $this->applyLoanDimensionFilters(DB::connection($this->connection)->table('loans'), $filters);

        $query->where(function ($where) {
            $hasCondition = false;

            if ($this->columnExists('loans', 'ptp_status')) {
                $where->orWhere('ptp_status', 'broken');
                $hasCondition = true;
            }

            if ($this->columnExists('loans', 'broken_ptp_count')) {
                $where->orWhere('broken_ptp_count', '>', 0);
                $hasCondition = true;
            }

            if ($this->columnExists('loans', 'collection_status')) {
                $where->orWhere('collection_status', 'broken_ptp');
                $hasCondition = true;
            }

            if ($this->columnExists('loans', 'ptp_date')) {
                $where->orWhere(function ($ptp) {
                    $ptp->whereDate('ptp_date', '<', Carbon::today()->toDateString());
                    if ($this->columnExists('loans', 'ptp_status')) {
                        $ptp->whereIn('ptp_status', ['open', 'pending', 'promised']);
                    }
                    if ($this->columnExists('loans', 'status')) {
                        $ptp->whereNotIn('status', ['completed', 'closed', 'paid', 'cancelled', 'rejected']);
                    }
                });
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $where->whereRaw('1 = 0');
            }
        });

        return (int) $query->count();
    }

    protected function todayCollectionAmount(array $filters): float
    {
        if (! $this->tableExists('loan_payments')) {
            return 0.0;
        }

        $query = $this->applyPaymentDimensionFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
            ->whereDate($this->paymentDateColumn(), Carbon::today()->toDateString())
            ->when($this->columnExists('loan_payments', 'status'), fn ($q) => $q->whereIn('status', ['paid', 'confirmed']));

        return (float) $query->sum($this->paymentAmountColumn());
    }

    protected function openScheduleStatuses(): array
    {
        return ['pending', 'unpaid', 'partial', 'late'];
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
            ->when($this->columnExists('loan_payments', 'status'), fn ($query) => $query->whereIn('status', ['paid', 'confirmed']))
            ->selectRaw("DATE_FORMAT(".$this->paymentDateColumn().", '%Y-%m') as month_key, COALESCE(SUM(".$this->paymentAmountColumn()."),0) as total_amount")
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
            ->whereRaw($this->scheduleBalanceExpression('s').' > 0')
            ->when($this->columnExists('loan_payment_schedules', 'status'), function ($query) {
                $query->where(function ($st) {
                    $st->whereIn('s.status', $this->openScheduleStatuses())
                        ->orWhereNull('s.status');
                });
            })
            ->selectRaw('DATEDIFF(CURDATE(), s.due_date) as overdue_days, '.$this->scheduleBalanceExpression('s').' as balance_amount')
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

        $loans = $this->applyLoanFilters(DB::connection($this->connection)->table('loans as l'), $filters, 'l')
            ->selectRaw($this->loanCollectorExpression('l').' as collector_name, COUNT(*) as assigned_loans')
            ->groupBy('collector_name')->get()->keyBy('collector_name');

        $payments = collect();
        if ($this->tableExists('loan_payments')) {
            $payments = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
                ->when($this->columnExists('loan_payments', 'status'), fn ($query) => $query->whereIn('status', ['paid', 'confirmed']))
                ->selectRaw('COALESCE('.$this->paymentCollectorColumn().', "Unknown") as collector_name, COALESCE(SUM('.$this->paymentAmountColumn().'),0) as collected_amount')
                ->groupBy('collector_name')->get()->keyBy('collector_name');
        }

        $visits = collect();
        if ($this->tableExists('loan_collection_visits')) {
            $visits = $this->collectionVisitQuery()
                ->selectRaw($this->collectionVisitStaffExpression().' as collector_name, COUNT(*) as visit_count')
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

    public function getCustomerStatusChart($filters, ?array $summary = null): array
    {
        if (! $this->tableExists('loan_customers')) return ['labels' => ['active', 'inactive', 'blacklist', 'late'], 'series' => [0, 0, 0, 0]];

        $active = (int) DB::connection($this->connection)->table('loan_customers')->where('status', 'active')->count();
        $inactive = (int) DB::connection($this->connection)->table('loan_customers')->where('status', 'inactive')->count();
        $blacklist = (int) DB::connection($this->connection)->table('loan_customers')->where('blacklist_status', 1)->count();
        $late = (int) ($summary['late_customers'] ?? $this->getSummaryCards($filters)['late_customers'] ?? 0);

        return ['labels' => ['active', 'inactive', 'blacklist', 'late'], 'series' => [$active, $inactive, $blacklist, (int) $late]];
    }

    public function getDailyCollectionChart($filters): array
    {
        if (! $this->tableExists('loan_payments')) return ['labels' => [], 'amount' => []];

        $from = Carbon::parse($filters['date_from']);
        $to = Carbon::parse($filters['date_to']);

        $rows = $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
            ->when($this->columnExists('loan_payments', 'status'), fn ($query) => $query->whereIn('status', ['paid', 'confirmed']))
            ->selectRaw('DATE('.$this->paymentDateColumn().') as paid_day, COALESCE(SUM('.$this->paymentAmountColumn().'),0) as total_amount')
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

        $query = $this->loanQueryWithCustomer('l');

        return $this->applyLoanFilters($query, $filters, 'l')
            ->selectRaw(implode(', ', [
                'l.id',
                'l.loan_number',
                $this->loanCustomerNameExpression('l').' as customer_name_snapshot',
                $this->loanCustomerPhoneExpression('l').' as customer_phone_snapshot',
                'l.principal_amount',
                'l.paid_amount',
                $this->loanBalanceExpression('l').' as balance_amount',
                'l.currency',
                'l.status',
                'l.loan_date',
            ]))
            ->orderByDesc('l.loan_date')->limit(20)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getTodayDuePayments($filters): array
    {
        if (! $this->tableExists('loan_payment_schedules') || ! $this->tableExists('loans')) return [];
        return $this->applyScheduleFilters($this->scheduleQueryWithLoanCustomer(), $filters)
            ->whereDate('s.due_date', Carbon::today()->toDateString())
            ->selectRaw('l.id, l.loan_number, '.$this->loanCustomerNameExpression('l').' as customer, '.$this->loanCustomerPhoneExpression('l').' as phone, s.due_date, '.$this->scheduleAmountExpression('s').' as schedule_amount, '.$this->schedulePaidExpression('s').' as paid_amount, '.$this->scheduleBalanceExpression('s').' as balance, '.$this->loanCollectorExpression('l').' as collector')
            ->orderBy('s.due_date')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getOverdueCustomers($filters, $limit = 250): array
    {
        if (! $this->tableExists('loans')) return [];

        $overdueDateExpr = $this->overdueScheduleDateExpression();
        $overdueDaysExpr = 'CASE WHEN '.$overdueDateExpr.' IS NOT NULL THEN GREATEST(DATEDIFF(CURDATE(), '.$overdueDateExpr.'), 1) '
            .($this->columnExists('loans', 'days_past_due') ? 'WHEN COALESCE(l.days_past_due, 0) > 0 THEN l.days_past_due ' : '')
            .'ELSE 1 END';
        $balanceExpr = $this->loanBalanceExpression('l');
        $dueNowExpr = $this->overdueScheduleDueNowExpression();
        $dateToPayExpr = 'COALESCE('.$overdueDateExpr.', '.($this->columnExists('loans', 'due_date') ? 'l.due_date' : 'NULL').')';

        $query = $this->applyLoanDimensionFilters($this->loanQueryWithCustomer('l'), $filters, 'l');
        $this->whereOverdueInstallment($query);

        return $query
            ->selectRaw('l.id, '.($this->columnExists('loans', 'loan_number') ? 'l.loan_number' : 'CAST(l.id as CHAR)').' as loan_number, '.($this->columnExists('loans', 'customer_id') ? 'l.customer_id' : 'NULL').' as customer_id, '.$this->loanCustomerNameExpression('l').' as customer, '.$this->loanCustomerPhoneExpression('l').' as phone, '.$this->loanCustomerProfessionExpression('l').' as profession, '.$this->loanCustomerOccupationExpression('l').' as occupation, '.$this->loanCustomerWorkplaceExpression('l').' as workplace, COALESCE('.($this->columnExists('loans', 'customer_photo_file_id') ? 'NULLIF(l.customer_photo_file_id, 0)' : 'NULL').', '.($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'customer_photo_file_id') ? 'NULLIF(c.customer_photo_file_id, 0)' : 'NULL').') as customer_photo_file_id, '.($this->columnExists('loans', 'customer_photo_snapshot') ? 'l.customer_photo_snapshot' : 'NULL').' as customer_photo_snapshot, '.$dateToPayExpr.' as date_to_pay, '.$overdueDaysExpr.' as overdue_days, '.($this->columnExists('loans', 'paid_amount') ? 'COALESCE(l.paid_amount, 0)' : '0').' as total_paid, '.$dueNowExpr.' as total_not_yet_paid, '.$balanceExpr.' as pay_off_now, '.$dueNowExpr.' as overdue_amount, '.$this->loanCollectorExpression('l').' as collector, NULL as last_visit')
            ->orderByDesc('overdue_days')->limit($limit)->get()->map(function ($row) {
                $data = (array) $row;
                $data['customer_photo_url'] = $this->customerPhotoUrl((int) ($row->customer_photo_file_id ?? 0))
                    ?: $this->loanFilePublicUrl($row->customer_photo_snapshot ?? null)
                    ?: $this->latestCustomerPhotoUrl((int) ($row->customer_id ?? 0))
                    ?: $this->latestCustomerImageUrl((int) ($row->customer_id ?? 0));

                return $data;
            })->all();
    }

    public function getRecentPayments($filters): array
    {
        if (! $this->tableExists('loan_payments')) return [];
        return $this->applyPaymentFilters(DB::connection($this->connection)->table('loan_payments'), $filters)
            ->selectRaw($this->recentPaymentSelect())
            ->orderByDesc($this->paymentDateColumn())->limit(50)->get()->map(fn ($r) => (array) $r)->all();
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
        return $this->staffLocationQuery()
            ->selectRaw('s.id, '.$this->staffLocationNameExpression().' as staff_name_snapshot, s.latitude, s.longitude, s.battery_level, s.recorded_at')
            ->orderByDesc('recorded_at')->limit(100)->get()->map(function ($r) use ($onlineCutoff) {
                $a = (array) $r;
                $a['online_status'] = ($r->recorded_at >= $onlineCutoff) ? 'online' : 'offline';
                return $a;
            })->all();
    }

    public function getFollowUpCustomers($filters): array
    {
        if (! $this->tableExists('loan_customer_followups')) return [];
        $dateCol = $this->followUpDateColumn();
        return $this->followUpQuery()
            ->selectRaw(implode(', ', [
                'f.id',
                $this->followUpCustomerNameExpression().' as customer',
                $this->followUpCustomerPhoneExpression().' as phone',
                'f.'.$dateCol.' as follow_up_date',
                $this->followUpTypeExpression().' as follow_up_type',
                'f.status',
                $this->followUpStaffExpression().' as assigned_staff',
                'f.note',
            ]))
            ->whereDate('f.'.$dateCol, '<=', Carbon::today()->toDateString())
            ->orderBy('f.'.$dateCol)->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function getBlacklistCustomers($filters): array
    {
        if (! $this->tableExists('loan_customers')) return [];
        return DB::connection($this->connection)->table('loan_customers')
            ->selectRaw('id, '.$this->customerNameExpression().' as customer, phone, id_card_number, blacklist_reason, created_at')
            ->where('blacklist_status', 1)
            ->orderByDesc('created_at')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function tableExists(string $table): bool
    {
        $key = 'table:'.$table;
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }

        try {
            return $this->schemaCache[$key] = Schema::connection($this->connection)->hasTable($table);
        } catch (\Throwable $e) {
            return $this->schemaCache[$key] = false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        $key = 'column:'.$table.'.'.$column;
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }

        try {
            return $this->schemaCache[$key] = Schema::connection($this->connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return $this->schemaCache[$key] = false;
        }
    }

    protected function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function qualifiedExistingColumn(string $table, string $alias, array $columns, string $default = '0'): string
    {
        $column = $this->firstExistingColumn($table, $columns);

        return $column ? $alias.'.'.$column : $default;
    }

    protected function scheduleAmountExpression(string $alias): string
    {
        $prefix = $alias ? $alias.'.' : '';
        $parts = [];
        if ($this->columnExists('loan_payment_schedules', 'amount_due')) $parts[] = 'NULLIF('.$prefix.'amount_due, 0)';
        if ($this->columnExists('loan_payment_schedules', 'schedule_amount')) $parts[] = 'NULLIF('.$prefix.'schedule_amount, 0)';
        if ($this->columnExists('loan_payment_schedules', 'total')) $parts[] = 'NULLIF('.$prefix.'total, 0)';
        if ($this->columnExists('loan_payment_schedules', 'principal_due') && $this->columnExists('loan_payment_schedules', 'interest_due')) {
            $parts[] = 'NULLIF(COALESCE('.$prefix.'principal_due, 0) + COALESCE('.$prefix.'interest_due, 0), 0)';
        }
        if ($this->columnExists('loan_payment_schedules', 'principal_amount')) $parts[] = 'NULLIF('.$prefix.'principal_amount, 0)';

        return ! empty($parts) ? 'COALESCE('.implode(', ', $parts).', 0)' : '0';
    }

    protected function schedulePaidExpression(string $alias): string
    {
        $prefix = $alias ? $alias.'.' : '';
        $parts = [];
        if ($this->columnExists('loan_payment_schedules', 'amount_paid')) $parts[] = 'NULLIF('.$prefix.'amount_paid, 0)';
        if ($this->columnExists('loan_payment_schedules', 'paid_amount')) $parts[] = 'NULLIF('.$prefix.'paid_amount, 0)';

        return ! empty($parts) ? 'COALESCE('.implode(', ', $parts).', 0)' : '0';
    }

    protected function scheduleBalanceExpression(string $alias): string
    {
        $prefix = $alias ? $alias.'.' : '';
        $parts = [];
        if ($this->columnExists('loan_payment_schedules', 'balance_amount')) $parts[] = 'NULLIF('.$prefix.'balance_amount, 0)';
        if ($this->columnExists('loan_payment_schedules', 'amount_balance')) $parts[] = 'NULLIF('.$prefix.'amount_balance, 0)';
        $parts[] = 'GREATEST(('.$this->scheduleAmountExpression($alias).') - ('.$this->schedulePaidExpression($alias).'), 0)';

        return 'COALESCE('.implode(', ', $parts).')';
    }

    protected function loanBalanceExpression(string $alias): string
    {
        $balanceColumn = $this->firstExistingColumn('loans', ['balance_amount', 'amount_balance']);
        $total = $this->loanTotalExpression($alias);
        $paid = $this->loanPaidExpression($alias);
        $fallback = 'GREATEST(('.$total.') - ('.$paid.'), 0)';

        return $balanceColumn
            ? 'COALESCE('.$alias.'.'.$balanceColumn.', '.$fallback.')'
            : $fallback;
    }

    protected function loanTotalExpression(string $alias): string
    {
        return 'COALESCE('.$this->qualifiedExistingColumn('loans', $alias, ['total_payable', 'total_payable_amount', 'total_amount', 'principal_amount']).', 0)';
    }

    protected function loanPaidExpression(string $alias): string
    {
        return 'COALESCE('.$this->qualifiedExistingColumn('loans', $alias, ['paid_amount', 'amount_paid']).', 0)';
    }

    protected function loanPrincipalExpression(string $alias): string
    {
        return 'COALESCE('.$this->qualifiedExistingColumn('loans', $alias, ['principal_amount', 'financed_amount', 'loan_amount', 'amount'], '0').', 0)';
    }

    protected function loanOneTimeInterestExpression(string $alias): string
    {
        return 'COALESCE('.$this->qualifiedExistingColumn('loans', $alias, ['interest_amount', 'outstanding_interest', 'interest_due'], '0').', 0)';
    }

    protected function payOffNowExpression(string $loanAlias, string $scheduleAlias): string
    {
        return '(('.$this->loanPrincipalExpression($loanAlias).') + ('.$this->scheduleBalanceExpression($scheduleAlias).') + ('.$this->loanOneTimeInterestExpression($loanAlias).'))';
    }

    protected function applyLoanFilters(Builder $query, array $filters, string $alias = 'loans'): Builder
    {
        $prefix = $alias.'.';
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        if ($this->columnExists('loans', 'loan_date')) {
            $this->applyDateRange($query, $prefix.'loan_date', $from, $to);
        } elseif ($this->columnExists('loans', 'created_at')) {
            $this->applyDateRange($query, $prefix.'created_at', $from, $to);
        }

        return $this->applyLoanDimensionFilters($query, $filters, $alias);
    }

    protected function isFullAccessUser($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Manager'))) {
            return true;
        }

        if (method_exists($user, 'can')) {
            if ($user->can('superadmin')) {
                return true;
            }

            if ($user->can('access_all_locations') || $user->can('loan_management.view_all') || $user->can('loan_management.all')) {
                return true;
            }
        }

        return false;
    }

    protected function applyLoanDimensionFilters(Builder $query, array $filters, string $alias = 'loans'): Builder
    {
        $prefix = $alias.'.';
        if (! empty($filters['business_location_id']) && $this->columnExists('loans', 'business_location_id')) $query->where($prefix.'business_location_id', $filters['business_location_id']);
        if (! empty($filters['loan_status']) && $this->columnExists('loans', 'status')) $query->where($prefix.'status', $filters['loan_status']);
        if (! empty($filters['collector_id']) && ($this->columnExists('loans', 'collector_id') || $this->columnExists('loans', 'assigned_to'))) {
            $collectorId = $filters['collector_id'];
            $query->where(function ($q) use ($prefix, $collectorId) {
                if ($this->columnExists('loans', 'collector_id')) {
                    $q->where($prefix.'collector_id', $collectorId);
                }
                if ($this->columnExists('loans', 'assigned_to')) {
                    $q->orWhere($prefix.'assigned_to', $collectorId);
                }
            });
        }
        if (! empty($filters['currency']) && $this->columnExists('loans', 'currency')) $query->where($prefix.'currency', $filters['currency']);

        $user = auth()->user();
        if ($user && ! $this->isFullAccessUser($user) && ($this->columnExists('loans', 'assigned_to') || $this->columnExists('loans', 'collector_id'))) {
            $uid = auth()->id();
            $query->where(function ($q) use ($prefix, $uid) {
                if ($this->columnExists('loans', 'assigned_to')) {
                    $q->where($prefix.'assigned_to', $uid);
                }
                if ($this->columnExists('loans', 'collector_id')) {
                    $q->orWhere($prefix.'collector_id', $uid);
                }
            });
        }

        return $query;
    }

    protected function applyPaymentFilters(Builder $query, array $filters, string $alias = 'loan_payments'): Builder
    {
        $prefix = $alias.'.';
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        $this->applyDateRange($query, $prefix.$this->paymentDateColumn(), $from, $to);
        return $this->applyPaymentDimensionFilters($query, $filters, $alias);
    }

    protected function applyPaymentDimensionFilters(Builder $query, array $filters, string $alias = 'loan_payments'): Builder
    {
        $prefix = $alias.'.';
        if (! empty($filters['currency']) && $this->columnExists('loan_payments', 'base_currency')) $query->where($prefix.'base_currency', $filters['currency']);
        if (! empty($filters['payment_method_snapshot'])) {
            if ((str_contains(strtolower($alias), 'loan_payment_details') || str_contains(strtolower($alias), ' d')) && $this->columnExists('loan_payment_details', 'payment_method_snapshot')) {
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

    protected function applyScheduleFilters(Builder $query, array $filters, bool $includeDateRange = true): Builder
    {
        if ($includeDateRange) {
            $from = $filters['date_from'] ?? null;
            $to = $filters['date_to'] ?? null;
            $this->applyDateRange($query, 's.due_date', $from, $to);
        }
        return $this->applyScheduleDimensionFilters($query, $filters);
    }

    protected function applyScheduleDimensionFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['business_location_id']) && $this->columnExists('loans', 'business_location_id')) $query->where('l.business_location_id', $filters['business_location_id']);
        if (! empty($filters['loan_status']) && $this->columnExists('loans', 'status')) $query->where('l.status', $filters['loan_status']);
        if (! empty($filters['collector_id']) && ($this->columnExists('loans', 'collector_id') || $this->columnExists('loans', 'assigned_to'))) {
            $collectorId = $filters['collector_id'];
            $query->where(function ($q) use ($collectorId) {
                if ($this->columnExists('loans', 'collector_id')) {
                    $q->where('l.collector_id', $collectorId);
                }
                if ($this->columnExists('loans', 'assigned_to')) {
                    $q->orWhere('l.assigned_to', $collectorId);
                }
            });
        }
        if (! empty($filters['currency']) && $this->columnExists('loans', 'currency')) $query->where('l.currency', $filters['currency']);

        $user = auth()->user();
        if ($user && ! $this->isFullAccessUser($user)) {
            $uid = auth()->id();
            $query->where(function ($q) use ($uid) {
                if ($this->columnExists('loans', 'assigned_to')) {
                    $q->where('l.assigned_to', $uid);
                }
                if ($this->columnExists('loans', 'collector_id')) {
                    $q->orWhere('l.collector_id', $uid);
                }
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

    protected function paymentDateColumn(): string
    {
        return $this->columnExists('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at';
    }

    protected function paymentAmountColumn(): string
    {
        if ($this->columnExists('loan_payments', 'total_paid_base')) {
            return 'total_paid_base';
        }
        if ($this->columnExists('loan_payments', 'amount')) {
            return 'amount';
        }
        return 'amount';
    }

    protected function loanQueryWithCustomer(string $loanAlias = 'l'): Builder
    {
        $query = DB::connection($this->connection)->table('loans as '.$loanAlias);

        if ($this->columnExists('loans', 'deleted_at')) {
            $query->whereNull($loanAlias.'.deleted_at');
        }

        if ($this->canJoinLoanCustomers()) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', $loanAlias.'.customer_id');
        }

        return $query;
    }

    protected function whereOverdueInstallment(Builder $query): void
    {
        $query->where(function ($q) {
            if ($this->canReadPaymentSchedules()) {
                $q->whereRaw($this->installmentOverdueExpression('l').' = 1');
                return;
            }

            if ($this->columnExists('loans', 'collection_status')) {
                $q->whereIn('l.collection_status', ['overdue', 'delinquent', 'late'])
                    ->whereRaw($this->loanBalanceExpression('l').' > 0');
                return;
            }

            if ($this->columnExists('loans', 'status')) {
                $q->whereIn('l.status', ['overdue', 'delinquent', 'late'])
                    ->whereRaw($this->loanBalanceExpression('l').' > 0');
                return;
            }

            $q->whereRaw('1 = 0');
        });

        if ($this->columnExists('loans', 'status')) {
            $query->whereNotIn('l.status', ['closed', 'completed', 'cancelled', 'paid']);
        }
        $query->whereRaw($this->loanBalanceExpression('l').' > 0');
    }

    protected function orWhereOverdueSchedule(Builder $query): void
    {
        $query->orWhereExists(function ($schedule) {
            $schedule->selectRaw('1')
                ->from('loan_payment_schedules as s')
                ->whereColumn('s.loan_id', 'l.id')
                ->whereDate('s.due_date', '<', Carbon::today()->toDateString())
                ->whereRaw($this->scheduleBalanceExpression('s').' > 0');

            if ($this->columnExists('loan_payment_schedules', 'status')) {
                $schedule->whereIn('s.status', $this->openScheduleStatuses());
            }

            if ($this->columnExists('loan_payment_schedules', 'deleted_at')) {
                $schedule->whereNull('s.deleted_at');
            }
        });
    }

    protected function installmentOverdueExpression(string $loanAlias): string
    {
        if (! $this->canReadPaymentSchedules()) {
            if (! $this->columnExists('loans', 'collection_status')) {
                return '0';
            }

            return "(CASE WHEN {$loanAlias}.collection_status IN ('overdue','delinquent') AND ".$this->loanBalanceExpression($loanAlias).' > 0 THEN 1 ELSE 0 END)';
        }

        return '(CASE WHEN ('.$this->scheduledDueThroughExpression($loanAlias, 'DATE_SUB(CURDATE(), INTERVAL 1 DAY)').' - '.$this->loanPaidExpression($loanAlias).') > 0 THEN 1 ELSE 0 END)';
    }

    protected function installmentDueNowExpression(string $loanAlias): string
    {
        if (! $this->canReadPaymentSchedules()) {
            return $this->loanBalanceExpression($loanAlias);
        }

        return 'GREATEST(('.$this->scheduledDueThroughExpression($loanAlias, 'CURDATE()').') - ('.$this->loanPaidExpression($loanAlias).'), 0)';
    }

    protected function nextUncoveredDueDateExpression(string $loanAlias): string
    {
        $fallbackDates = [];
        if ($this->columnExists('loans', 'next_due_date')) {
            $fallbackDates[] = $loanAlias.'.next_due_date';
        }
        if ($this->columnExists('loans', 'first_due_date')) {
            $fallbackDates[] = $loanAlias.'.first_due_date';
        }
        $fallbackExpr = ! empty($fallbackDates) ? 'COALESCE('.implode(', ', $fallbackDates).')' : 'NULL';

        if (! $this->canReadPaymentSchedules()) {
            return $fallbackExpr;
        }

        $unpaidConditions = [];
        if ($this->columnExists('loan_payment_schedules', 'amount_balance')) {
            $unpaidConditions[] = 's.amount_balance > 0';
        }
        if ($this->columnExists('loan_payment_schedules', 'balance_amount')) {
            $unpaidConditions[] = 's.balance_amount > 0';
        }

        $balanceCondition = ! empty($unpaidConditions)
            ? '('.implode(' OR ', $unpaidConditions).')'
            : ($this->scheduledDueThroughExpression($loanAlias, 's.due_date', 'sx').' > '.$this->loanPaidExpression($loanAlias));

        $conditions = [
            's.loan_id = '.$loanAlias.'.id',
            $balanceCondition,
        ];

        if ($this->columnExists('loan_payment_schedules', 'status')) {
            $conditions[] = "(s.status IS NULL OR s.status NOT IN ('paid','completed','cancelled','void','deleted'))";
        }

        if ($this->columnExists('loan_payment_schedules', 'deleted_at')) {
            $conditions[] = 's.deleted_at IS NULL';
        }

        $scheduleSubquery = '(SELECT MIN(s.due_date) FROM loan_payment_schedules s WHERE '.implode(' AND ', $conditions).')';

        return 'CASE WHEN '.$this->loanBalanceExpression($loanAlias).' > 0 THEN COALESCE('.$scheduleSubquery.', '.$fallbackExpr.') ELSE NULL END';
    }



    protected function scheduledDueThroughExpression(string $loanAlias, string $dateExpression, string $scheduleAlias = 's'): string
    {
        $conditions = [
            $scheduleAlias.'.loan_id = '.$loanAlias.'.id',
            $scheduleAlias.'.due_date <= '.$dateExpression,
        ];

        if ($this->columnExists('loan_payment_schedules', 'status')) {
            $conditions[] = "(".$scheduleAlias.".status IS NULL OR ".$scheduleAlias.".status NOT IN ('cancelled','void','deleted'))";
        }

        if ($this->columnExists('loan_payment_schedules', 'deleted_at')) {
            $conditions[] = $scheduleAlias.'.deleted_at IS NULL';
        }

        return '(SELECT COALESCE(SUM('.$this->scheduleAmountExpression($scheduleAlias).'), 0) FROM loan_payment_schedules '.$scheduleAlias.' WHERE '.implode(' AND ', $conditions).')';
    }

    protected function canReadPaymentSchedules(): bool
    {
        return $this->tableExists('loan_payment_schedules')
            && $this->columnExists('loan_payment_schedules', 'loan_id')
            && $this->columnExists('loan_payment_schedules', 'due_date');
    }

    protected function overdueScheduleDateExpression(): string
    {
        if (! $this->canReadPaymentSchedules()) {
            return 'NULL';
        }

        $conditions = [
            's.loan_id = l.id',
            's.due_date < CURDATE()',
            $this->scheduledDueThroughExpression('l', 's.due_date', 'sx').' > '.$this->loanPaidExpression('l'),
        ];

        if ($this->columnExists('loan_payment_schedules', 'status')) {
            $conditions[] = "(s.status IS NULL OR s.status NOT IN ('cancelled', 'void', 'deleted'))";
        }

        if ($this->columnExists('loan_payment_schedules', 'deleted_at')) {
            $conditions[] = 's.deleted_at IS NULL';
        }

        return '(SELECT MIN(s.due_date) FROM loan_payment_schedules s WHERE '.implode(' AND ', $conditions).')';
    }

    protected function overdueScheduleDueNowExpression(): string
    {
        return $this->installmentDueNowExpression('l');
    }

    protected function scheduleQueryWithLoanCustomer(): Builder
    {
        $query = DB::connection($this->connection)->table('loan_payment_schedules as s')
            ->join('loans as l', 'l.id', '=', 's.loan_id');

        if ($this->columnExists('loan_payment_schedules', 'deleted_at')) {
            $query->whereNull('s.deleted_at');
        }
        if ($this->columnExists('loans', 'deleted_at')) {
            $query->whereNull('l.deleted_at');
        }

        if ($this->canJoinLoanCustomers()) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
        }

        return $query;
    }

    protected function canJoinLoanCustomers(): bool
    {
        return $this->tableExists('loan_customers')
            && $this->columnExists('loans', 'customer_id')
            && $this->columnExists('loan_customers', 'id');
    }

    protected function loanCustomerNameExpression(string $loanAlias): string
    {
        if ($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'khmer_name')) {
            $fallback = $this->columnExists('loans', 'customer_name_snapshot')
                ? $loanAlias.'.customer_name_snapshot'
                : ($this->columnExists('loan_customers', 'name')
                    ? 'c.name'
                    : 'CONCAT("Customer #", '.$loanAlias.'.customer_id)');

            return 'COALESCE(NULLIF(c.khmer_name, ""), '.$fallback.')';
        }
        if ($this->columnExists('loans', 'customer_name_snapshot')) {
            return $loanAlias.'.customer_name_snapshot';
        }
        if ($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'name')) {
            return 'COALESCE(c.name, CONCAT("Customer #", '.$loanAlias.'.customer_id))';
        }
        if ($this->columnExists('loans', 'customer_id')) {
            return 'CONCAT("Customer #", '.$loanAlias.'.customer_id)';
        }

        return 'NULL';
    }

    protected function loanCustomerPhoneExpression(string $loanAlias): string
    {
        if ($this->columnExists('loans', 'customer_phone_snapshot')) {
            return $loanAlias.'.customer_phone_snapshot';
        }
        if ($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'phone')) {
            return 'c.phone';
        }

        return 'NULL';
    }

    protected function loanCustomerProfessionExpression(string $loanAlias): string
    {
        $candidates = [];

        if ($this->canJoinLoanCustomers()) {
            if ($this->columnExists('loan_customers', 'occupation')) {
                $candidates[] = 'NULLIF(TRIM(c.occupation), "")';
            }
            if ($this->columnExists('loan_customers', 'workplace')) {
                $candidates[] = 'NULLIF(TRIM(c.workplace), "")';
            }
            if ($this->columnExists('loan_customers', 'employer_name')) {
                $candidates[] = 'NULLIF(TRIM(c.employer_name), "")';
            }
        }

        if ($this->columnExists('loans', 'occupation')) {
            $candidates[] = "NULLIF(TRIM({$loanAlias}.occupation), '')";
        }
        if ($this->columnExists('loans', 'workplace')) {
            $candidates[] = "NULLIF(TRIM({$loanAlias}.workplace), '')";
        }
        if ($this->columnExists('loans', 'employer_name')) {
            $candidates[] = "NULLIF(TRIM({$loanAlias}.employer_name), '')";
        }

        if (! empty($candidates)) {
            return 'COALESCE('.implode(', ', $candidates).', "-")';
        }

        return '"-"';
    }

    protected function loanCustomerOccupationExpression(string $loanAlias): string
    {
        $candidates = [];
        if ($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'occupation')) {
            $candidates[] = 'NULLIF(TRIM(c.occupation), "")';
        }
        if ($this->columnExists('loans', 'occupation')) {
            $candidates[] = "NULLIF(TRIM({$loanAlias}.occupation), '')";
        }

        return ! empty($candidates) ? 'COALESCE('.implode(', ', $candidates).', NULL)' : 'NULL';
    }

    protected function loanCustomerWorkplaceExpression(string $loanAlias): string
    {
        $candidates = [];
        if ($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'workplace')) {
            $candidates[] = 'NULLIF(TRIM(c.workplace), "")';
        }
        if ($this->canJoinLoanCustomers() && $this->columnExists('loan_customers', 'employer_name')) {
            $candidates[] = 'NULLIF(TRIM(c.employer_name), "")';
        }
        if ($this->columnExists('loans', 'workplace')) {
            $candidates[] = "NULLIF(TRIM({$loanAlias}.workplace), '')";
        }
        if ($this->columnExists('loans', 'employer_name')) {
            $candidates[] = "NULLIF(TRIM({$loanAlias}.employer_name), '')";
        }

        return ! empty($candidates) ? 'COALESCE('.implode(', ', $candidates).', NULL)' : 'NULL';
    }

    protected function loanCollectorExpression(string $loanAlias): string
    {
        if ($this->columnExists('loans', 'collector_name_snapshot')) {
            return 'COALESCE('.$loanAlias.'.collector_name_snapshot, "-")';
        }
        if ($this->columnExists('loans', 'collector_id') && $this->columnExists('loans', 'assigned_to')) {
            return 'COALESCE(CONCAT("Collector #", '.$loanAlias.'.collector_id), CONCAT("Collector #", '.$loanAlias.'.assigned_to), "-")';
        }
        if ($this->columnExists('loans', 'collector_id')) {
            return 'COALESCE(CONCAT("Collector #", '.$loanAlias.'.collector_id), "-")';
        }
        if ($this->columnExists('loans', 'assigned_to')) {
            return 'COALESCE(CONCAT("Collector #", '.$loanAlias.'.assigned_to), "-")';
        }

        return '"-"';
    }

    protected function followUpQuery(): Builder
    {
        $query = DB::connection($this->connection)->table('loan_customer_followups as f');

        if ($this->canJoinFollowUpCustomers()) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', 'f.customer_id');
        }
        if ($this->canJoinFollowUpStaff()) {
            $query->leftJoin('loan_users as u', 'u.id', '=', 'f.staff_id');
        }

        return $query;
    }

    protected function followUpDateColumn(): string
    {
        if ($this->columnExists('loan_customer_followups', 'follow_up_date')) {
            return 'follow_up_date';
        }
        if ($this->columnExists('loan_customer_followups', 'followup_at')) {
            return 'followup_at';
        }

        return 'created_at';
    }

    protected function followUpCustomerNameExpression(): string
    {
        if ($this->columnExists('loan_customer_followups', 'customer_name_snapshot')) {
            return 'f.customer_name_snapshot';
        }
        if ($this->canJoinFollowUpCustomers() && $this->columnExists('loan_customers', 'name')) {
            return 'COALESCE(c.name, CONCAT("Customer #", f.customer_id))';
        }

        return 'CONCAT("Customer #", f.customer_id)';
    }

    protected function followUpCustomerPhoneExpression(): string
    {
        if ($this->columnExists('loan_customer_followups', 'customer_phone_snapshot')) {
            return 'f.customer_phone_snapshot';
        }
        if ($this->canJoinFollowUpCustomers() && $this->columnExists('loan_customers', 'phone')) {
            return 'c.phone';
        }

        return 'NULL';
    }

    protected function followUpTypeExpression(): string
    {
        return $this->columnExists('loan_customer_followups', 'follow_up_type') ? 'f.follow_up_type' : 'f.status';
    }

    protected function followUpStaffExpression(): string
    {
        if ($this->columnExists('loan_customer_followups', 'assigned_staff_name_snapshot')) {
            return 'f.assigned_staff_name_snapshot';
        }
        if ($this->canJoinFollowUpStaff() && $this->columnExists('loan_users', 'name')) {
            return 'COALESCE(u.name, CONCAT("Staff #", f.staff_id))';
        }
        if ($this->columnExists('loan_customer_followups', 'staff_id')) {
            return 'CONCAT("Staff #", f.staff_id)';
        }
        if ($this->columnExists('loan_customer_followups', 'assigned_staff_id')) {
            return 'CONCAT("Staff #", f.assigned_staff_id)';
        }

        return 'NULL';
    }

    protected function canJoinFollowUpCustomers(): bool
    {
        return $this->tableExists('loan_customers')
            && $this->columnExists('loan_customer_followups', 'customer_id')
            && $this->columnExists('loan_customers', 'id');
    }

    protected function canJoinFollowUpStaff(): bool
    {
        return $this->tableExists('loan_users')
            && $this->columnExists('loan_customer_followups', 'staff_id')
            && $this->columnExists('loan_users', 'id');
    }

    protected function customerNameExpression(): string
    {
        if ($this->columnExists('loan_customers', 'customer_name')) {
            return 'customer_name';
        }
        if ($this->columnExists('loan_customers', 'name')) {
            return 'name';
        }

        return 'CONCAT("Customer #", id)';
    }

    protected function collectionVisitQuery(): Builder
    {
        $query = DB::connection($this->connection)->table('loan_collection_visits as v');

        if ($this->canJoinCollectionVisitStaff()) {
            $query->leftJoin('loan_users as u', 'u.id', '=', 'v.staff_id');
        }

        return $query;
    }

    protected function collectionVisitStaffExpression(): string
    {
        if ($this->columnExists('loan_collection_visits', 'staff_name_snapshot')) {
            return 'COALESCE(v.staff_name_snapshot, "Unknown")';
        }
        if ($this->canJoinCollectionVisitStaff() && $this->columnExists('loan_users', 'name')) {
            return 'COALESCE(u.name, CONCAT("Staff #", v.staff_id))';
        }
        if ($this->columnExists('loan_collection_visits', 'staff_id')) {
            return 'CONCAT("Staff #", v.staff_id)';
        }

        return '"Unknown"';
    }

    protected function canJoinCollectionVisitStaff(): bool
    {
        return $this->tableExists('loan_users')
            && $this->columnExists('loan_collection_visits', 'staff_id')
            && $this->columnExists('loan_users', 'id');
    }

    protected function staffLocationQuery(): Builder
    {
        $query = DB::connection($this->connection)->table('loan_staff_location_latest as s');

        if ($this->canJoinStaffLocationUser()) {
            $query->leftJoin('loan_users as u', 'u.id', '=', 's.user_id');
        }

        return $query;
    }

    protected function staffLocationNameExpression(): string
    {
        if ($this->columnExists('loan_staff_location_latest', 'staff_name_snapshot')) {
            return 'COALESCE(s.staff_name_snapshot, "Unknown")';
        }
        if ($this->canJoinStaffLocationUser() && $this->columnExists('loan_users', 'name')) {
            return 'COALESCE(u.name, CONCAT("Staff #", s.user_id))';
        }
        if ($this->columnExists('loan_staff_location_latest', 'user_id')) {
            return 'CONCAT("Staff #", s.user_id)';
        }

        return '"Unknown"';
    }

    protected function canJoinStaffLocationUser(): bool
    {
        return $this->tableExists('loan_users')
            && $this->columnExists('loan_staff_location_latest', 'user_id')
            && $this->columnExists('loan_users', 'id');
    }

    protected function sumExistingPaymentColumn(Builder $query, string $column): float
    {
        if (! $this->columnExists('loan_payments', $column)) {
            return 0.0;
        }

        return (float) $query->sum($column);
    }

    protected function recentPaymentSelect(): string
    {
        return implode(', ', [
            'id',
            $this->paymentReceiptExpression().' as receipt_number',
            $this->paymentDisplayColumn('customer_name_snapshot').' as customer_name_snapshot',
            $this->paymentDisplayColumn('loan_number_snapshot').' as loan_number',
            'COALESCE('.$this->paymentAmountColumn().',0) as paid_amount',
            $this->paymentMethodColumn().' as payment_method',
            $this->paymentCollectorColumn().' as received_by_name_snapshot',
            $this->paymentDateColumn().' as paid_date',
        ]);
    }

    protected function paymentReceiptExpression(): string
    {
        if ($this->columnExists('loan_payments', 'receipt_number') && $this->columnExists('loan_payments', 'payment_ref_no')) {
            return 'COALESCE(receipt_number, payment_ref_no, CONCAT("PMT-", id))';
        }
        if ($this->columnExists('loan_payments', 'receipt_number')) {
            return 'COALESCE(receipt_number, CONCAT("PMT-", id))';
        }
        if ($this->columnExists('loan_payments', 'payment_ref_no')) {
            return 'COALESCE(payment_ref_no, CONCAT("PMT-", id))';
        }

        return 'CONCAT("PMT-", id)';
    }

    protected function paymentDisplayColumn(string $column): string
    {
        return $this->columnExists('loan_payments', $column) ? $column : 'NULL';
    }

    protected function paymentMethodColumn(): string
    {
        if ($this->columnExists('loan_payments', 'payment_method_snapshot')) {
            return 'payment_method_snapshot';
        }
        if ($this->columnExists('loan_payments', 'channel')) {
            return 'channel';
        }

        return 'NULL';
    }

    protected function paymentCollectorColumn(): string
    {
        if ($this->columnExists('loan_payments', 'received_by_name_snapshot')) {
            return 'received_by_name_snapshot';
        }
        if ($this->columnExists('loan_payments', 'collected_by_name_snapshot')) {
            return 'collected_by_name_snapshot';
        }

        return 'NULL';
    }
}
