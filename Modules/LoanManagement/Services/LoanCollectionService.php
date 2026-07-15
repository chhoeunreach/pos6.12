<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Support\LoanCollectionConstants;

class LoanCollectionService
{
    protected string $connection = 'mysql_loan';

    public function filters($request): array
    {
        return [
            'collection_status' => $request->input('collection_status'),
            'overdue_bucket' => $request->input('overdue_bucket'),
            'collector_id' => $request->input('collector_id'),
            'business_location_id' => $request->input('business_location_id'),
            'risk_level' => $request->input('risk_level'),
            'payment_status' => $request->input('payment_status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'skip_level' => $request->input('skip_level'),
            'legal_status' => $request->input('legal_status'),
        ];
    }

    public function pageDefinition(string $slug): array
    {
        $map = [
            'new-loans' => ['title' => 'New Loans', 'where' => ['status' => ['draft', 'pending']]],
            'active-loans' => ['title' => 'Active Loans', 'where' => ['collection_status' => ['active', 'due_today', 'partial_payment']]],
            'due-today' => ['title' => 'Due Today', 'where' => ['collection_status' => ['due_today']]],
            'partial-payments' => ['title' => 'Partial Payments', 'where' => ['collection_status' => ['partial_payment']]],
            'closed-accounts' => ['title' => 'Closed Accounts', 'where' => ['collection_status' => ['closed']]],
            'overdue-accounts' => ['title' => 'Overdue Accounts', 'where' => ['collection_status' => ['overdue']]],
            'promise-to-pay' => ['title' => 'Promise To Pay', 'where' => ['collection_status' => ['ptp']]],
            'broken-promise' => ['title' => 'Broken Promise', 'where' => ['collection_status' => ['broken_ptp']]],
            'field-visit-required' => ['title' => 'Field Visit Required', 'where' => ['collection_status' => ['field_visit_required']]],
            'skip-customers' => ['title' => 'Skip Customers', 'khmer' => LoanCollectionConstants::KHMER['skip_customers'], 'where' => ['collection_status' => ['skip_customer']]],
            'delinquent-accounts' => ['title' => 'Delinquent Accounts', 'where' => ['collection_status' => ['delinquent']]],
            'recovery-management' => ['title' => 'Recovery Management', 'where' => ['collection_status' => ['recovery']]],
            'debt-collection' => ['title' => 'Debt Collection', 'khmer' => LoanCollectionConstants::KHMER['debt_collection'], 'where' => ['collection_status' => ['debt_collection']]],
            'high-risk-customers' => ['title' => 'High Risk Customers', 'where' => ['risk_level' => ['high_risk', 'critical']]],
            'fraud-risk' => ['title' => 'Fraud Risk', 'khmer' => LoanCollectionConstants::KHMER['fraud_risk'], 'where' => ['risk_level' => ['fraud_risk']]],
            'legal-cases' => ['title' => 'Legal Cases', 'khmer' => LoanCollectionConstants::KHMER['legal_cases'], 'where' => ['collection_status' => ['legal']]],
            'blacklisted-customers' => ['title' => 'Blacklisted Customers', 'where' => ['collection_status' => ['blacklisted']]],
            'repossessions' => ['title' => 'Repossessions', 'khmer' => LoanCollectionConstants::KHMER['repossessions'], 'where' => ['collection_status' => ['repossession']]],
            'contact-history' => ['title' => 'Contact History', 'where' => []],
            'voice-calls' => ['title' => 'Voice Calls', 'where' => []],
            'notifications' => ['title' => 'Notifications', 'where' => []],
            'sms-telegram-logs' => ['title' => 'SMS/Telegram Logs', 'where' => []],
        ];

        return $map[$slug] ?? ['title' => str($slug)->replace('-', ' ')->title()->value(), 'where' => []];
    }

    public function dashboardCards(array $filters = []): array
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $loans = $this->applyFilters(DB::connection($this->connection)->table('loans'), $filters);
        $hasCollectionStatus = $this->hasLoanColumn('collection_status');

        return [
            'due_today' => $hasCollectionStatus
                ? (int) (clone $loans)->where('collection_status', 'due_today')->count()
                : $this->scheduleLoanCount('=', $today),
            'overdue_accounts' => $hasCollectionStatus
                ? (int) (clone $loans)->whereIn('collection_status', ['overdue', 'delinquent'])->count()
                : $this->scheduleLoanCount('<', $today),
            'skip_customers' => $hasCollectionStatus ? (int) (clone $loans)->where('collection_status', 'skip_customer')->count() : 0,
            'broken_ptp' => $hasCollectionStatus ? (int) (clone $loans)->where('collection_status', 'broken_ptp')->count() : 0,
            'field_visits_today' => $this->fieldVisitsTodayCount($loans, $today),
            'collection_amount_today' => $this->collectionAmountToday(),
            'recovery_cases' => $hasCollectionStatus ? (int) (clone $loans)->where('collection_status', 'recovery')->count() : 0,
            'legal_cases' => $hasCollectionStatus ? (int) (clone $loans)->where('collection_status', 'legal')->count() : 0,
            'high_risk_customers' => $this->hasLoanColumn('risk_level') ? (int) (clone $loans)->whereIn('risk_level', ['high_risk', 'critical'])->count() : 0,
            'repossessions' => $hasCollectionStatus ? (int) (clone $loans)->where('collection_status', 'repossession')->count() : 0,
        ];
    }

    public function loansForPage(string $slug, array $filters = [])
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return collect();
        }

        $definition = $this->pageDefinition($slug);
        $query = $this->loanQuery();
        $this->applyPageDefinition($query, $slug, $definition);

        $query = $this->applyFilters($query, $filters, 'l');
        $this->applyCollectionOrdering($query);

        return $query->paginate(30)->appends(array_filter($filters));
    }

    public function reportRows(string $report, array $filters = [])
    {
        return $this->loansForPage($this->reportToPage($report), $filters);
    }

    public function options(): array
    {
        return [
            'statuses' => LoanCollectionConstants::STATUSES,
            'riskLevels' => LoanCollectionConstants::RISK_LEVELS,
            'buckets' => LoanCollectionConstants::OVERDUE_BUCKETS,
            'skipLevels' => ['soft_skip' => 'Soft Skip', 'hard_skip' => 'Hard Skip'],
            'reports' => LoanCollectionConstants::REPORTS,
            'locations' => $this->distinctOptions('business_location_id', 'Location #'),
            'collectors' => $this->distinctOptions('assigned_collector_id', 'Collector #'),
        ];
    }

    public function runAutomation(): array
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return ['updated' => 0];
        }

        $updated = 0;
        $loans = DB::connection($this->connection)->table('loans')
            ->whereNotIn('status', ['closed', 'completed', 'cancelled', 'written_off'])
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            $payload = $this->automationPayload($loan);
            if (! empty($payload)) {
                DB::connection($this->connection)->table('loans')->where('id', $loan->id)->update($payload + ['updated_at' => now()]);
                $updated++;
            }
        }

        return ['updated' => $updated];
    }

    protected function automationPayload(object $loan): array
    {
        $daysPastDue = $this->daysPastDue((int) $loan->id);
        $payload = [
            'days_past_due' => $daysPastDue,
            'overdue_bucket' => $this->bucket($daysPastDue),
            'collection_status' => $loan->collection_status ?: ($loan->status ?: 'active'),
            'risk_level' => $loan->risk_level ?: 'normal',
        ];

        if (! empty($loan->ptp_date) && in_array($loan->ptp_status, ['active', null, ''], true) && Carbon::parse($loan->ptp_date)->lt(Carbon::today())) {
            $payload['collection_status'] = 'broken_ptp';
            $payload['ptp_status'] = 'broken';
            $payload['broken_ptp_count'] = ((int) ($loan->broken_ptp_count ?? 0)) + 1;
        } elseif ($daysPastDue > 180) {
            $payload['collection_status'] = 'legal';
            $payload['risk_level'] = 'critical';
        } elseif ($daysPastDue > 90) {
            $payload['collection_status'] = 'debt_collection';
            $payload['risk_level'] = 'critical';
        } elseif ((int) ($loan->contact_attempt_count ?? 0) >= 6 && empty($loan->last_contact_result)) {
            $payload['collection_status'] = 'skip_customer';
            $payload['skip_level'] = 'hard_skip';
            $payload['risk_level'] = 'hard_skip';
        } elseif ((int) ($loan->contact_attempt_count ?? 0) >= 3 && empty($loan->last_contact_result)) {
            $payload['collection_status'] = 'skip_customer';
            $payload['skip_level'] = 'soft_skip';
            $payload['risk_level'] = 'soft_skip';
        } elseif ($daysPastDue > 30) {
            $payload['collection_status'] = 'overdue';
            $payload['risk_level'] = 'high_risk';
        } elseif ($daysPastDue > 7) {
            $payload['collection_status'] = 'overdue';
        } elseif ($this->isDueToday((int) $loan->id)) {
            $payload['collection_status'] = 'due_today';
        } elseif (($loan->status ?? null) === 'completed') {
            $payload['collection_status'] = 'closed';
        }

        $payload['collection_priority'] = $this->priority($payload['collection_status'], $payload['risk_level'], $daysPastDue);
        $payload['recovery_score'] = min(100, max(0, $daysPastDue + ((int) ($loan->contact_attempt_count ?? 0) * 5)));

        return array_filter($payload, fn ($value) => $value !== null);
    }

    protected function loanQuery()
    {
        return DB::connection($this->connection)->table('loans as l')
            ->selectRaw('l.*');
    }

    protected function applyPageDefinition($query, string $slug, array $definition): void
    {
        $statusValues = (array) (($definition['where']['collection_status'] ?? []));

        if (! empty($statusValues)) {
            $query->where(function ($q) use ($slug, $statusValues) {
                if ($this->hasLoanColumn('collection_status')) {
                    $q->whereIn('l.collection_status', $statusValues);
                }

                $this->orWhereDerivedCollectionPage($q, $slug);
            });

            return;
        }

        foreach (($definition['where'] ?? []) as $column => $values) {
            if ($this->hasLoanColumn($column)) {
                $query->whereIn('l.'.$column, (array) $values);
            }
        }

        match ($slug) {
            'active-loans' => $this->whereActiveLoans($query),
            'closed-accounts' => $this->whereClosedAccounts($query),
            default => null,
        };
    }

    protected function orWhereDerivedCollectionPage($query, string $slug): void
    {
        match ($slug) {
            'active-loans' => $this->orWhereActiveLoans($query),
            'closed-accounts' => $this->orWhereClosedAccounts($query),
            'overdue-accounts', 'delinquent-accounts', 'recovery-management', 'debt-collection' => $this->orWhereHasSchedule($query, '<', Carbon::today()->toDateString()),
            'due-today' => $this->orWhereHasSchedule($query, '=', Carbon::today()->toDateString()),
            'partial-payments' => $this->orWherePartialPayment($query),
            'promise-to-pay' => $this->orWherePromiseToPay($query, false),
            'broken-promise' => $this->orWherePromiseToPay($query, true),
            'field-visit-required' => $this->orWhereFieldVisitRequired($query),
            default => null,
        };
    }

    protected function orWhereActiveLoans($query): void
    {
        $query->orWhere(function ($q) {
            $this->whereActiveLoans($q);
        });
    }

    protected function orWhereClosedAccounts($query): void
    {
        $query->orWhere(function ($q) {
            $this->whereClosedAccounts($q);
        });
    }

    protected function whereActiveLoans($query): void
    {
        if (! $this->hasLoanColumn('status') && ! $this->hasLoanColumn('balance_amount')) {
            return;
        }

        $query->where(function ($q) {
            if ($this->hasLoanColumn('status')) {
                $q->whereIn('l.status', ['active', 'approved']);
            }

            if ($this->hasLoanColumn('balance_amount')) {
                $q->orWhere('l.balance_amount', '>', 0);
            }
        });
    }

    protected function whereClosedAccounts($query): void
    {
        if (! $this->hasLoanColumn('status') && ! $this->hasLoanColumn('balance_amount')) {
            return;
        }

        $query->where(function ($q) {
            if ($this->hasLoanColumn('status')) {
                $q->whereIn('l.status', ['closed', 'completed']);
            }

            if ($this->hasLoanColumn('balance_amount')) {
                $q->orWhere('l.balance_amount', '<=', 0);
            }
        });
    }

    protected function orWherePartialPayment($query): void
    {
        if ($this->hasLoanColumn('paid_amount') || $this->hasLoanColumn('balance_amount')) {
            $query->orWhere(function ($q) {
                if ($this->hasLoanColumn('paid_amount')) {
                    $q->where('l.paid_amount', '>', 0);
                }

                if ($this->hasLoanColumn('balance_amount')) {
                    $q->where('l.balance_amount', '>', 0);
                }
            });
        }

        $this->orWhereHasSchedule($query, null, null, ['partial']);
    }

    protected function orWherePromiseToPay($query, bool $broken): void
    {
        if (! $this->hasLoanColumn('ptp_date')) {
            return;
        }

        $query->orWhere(function ($q) use ($broken) {
            if ($broken) {
                $q->whereDate('l.ptp_date', '<', Carbon::today()->toDateString());
                if ($this->hasLoanColumn('ptp_status')) {
                    $q->where(function ($statusQuery) {
                        $statusQuery->whereNull('l.ptp_status')
                            ->orWhereIn('l.ptp_status', ['', 'active', 'broken']);
                    });
                }
            } else {
                $q->whereDate('l.ptp_date', '>=', Carbon::today()->toDateString());
                if ($this->hasLoanColumn('ptp_status')) {
                    $q->where(function ($statusQuery) {
                        $statusQuery->whereNull('l.ptp_status')
                            ->orWhereIn('l.ptp_status', ['', 'active']);
                    });
                }
            }
        });
    }

    protected function orWhereFieldVisitRequired($query): void
    {
        if (! $this->hasLoanColumn('field_visit_required') && ! $this->hasLoanColumn('next_followup_at')) {
            return;
        }

        $query->orWhere(function ($q) {
            if ($this->hasLoanColumn('field_visit_required')) {
                $q->where('l.field_visit_required', 1);
            }

            if ($this->hasLoanColumn('next_followup_at')) {
                $q->orWhereDate('l.next_followup_at', '<=', Carbon::today()->toDateString());
            }
        });
    }

    protected function orWhereHasSchedule($query, ?string $operator, ?string $date, array $statuses = ['pending', 'unpaid', 'partial', 'late']): void
    {
        if (! $this->canReadSchedules()) {
            return;
        }

        $query->orWhereExists(function ($schedule) use ($operator, $date, $statuses) {
            $schedule->selectRaw('1')
                ->from('loan_payment_schedules as s')
                ->whereColumn('s.loan_id', 'l.id');

            if ($operator !== null && $date !== null) {
                $schedule->whereDate('s.due_date', $operator, $date);
            }

            if ($this->hasScheduleColumn('status') && ! empty($statuses)) {
                $schedule->whereIn('s.status', $statuses);
            }

            if ($this->hasScheduleColumn('deleted_at')) {
                $schedule->whereNull('s.deleted_at');
            }
        });
    }

    protected function applyCollectionOrdering($query): void
    {
        if ($this->hasLoanColumn('collection_priority')) {
            $query->orderByDesc('l.collection_priority');
        }

        if ($this->hasLoanColumn('days_past_due')) {
            $query->orderByDesc('l.days_past_due');
        }

        $query->orderByDesc('l.id');
    }

    protected function scheduleLoanCount(string $operator, string $date): int
    {
        if (! $this->canReadSchedules()) {
            return 0;
        }

        $query = DB::connection($this->connection)->table('loan_payment_schedules')
            ->whereDate('due_date', $operator, $date);

        if ($this->hasScheduleColumn('status')) {
            $query->whereIn('status', ['pending', 'unpaid', 'partial', 'late']);
        }

        if ($this->hasScheduleColumn('deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->distinct('loan_id')->count('loan_id');
    }

    protected function fieldVisitsTodayCount($loans, string $today): int
    {
        if (! $this->hasLoanColumn('field_visit_required') && ! $this->hasLoanColumn('next_followup_at')) {
            return 0;
        }

        $query = clone $loans;

        if ($this->hasLoanColumn('field_visit_required') && $this->hasLoanColumn('next_followup_at')) {
            return (int) $query->where('field_visit_required', 1)->whereDate('next_followup_at', '<=', $today)->count();
        }

        if ($this->hasLoanColumn('field_visit_required')) {
            return (int) $query->where('field_visit_required', 1)->count();
        }

        return (int) $query->whereDate('next_followup_at', '<=', $today)->count();
    }

    protected function hasLoanColumn(string $column): bool
    {
        return $this->hasColumn('loans', $column);
    }

    protected function hasScheduleColumn(string $column): bool
    {
        return $this->hasColumn('loan_payment_schedules', $column);
    }

    protected function canReadSchedules(): bool
    {
        return $this->hasTable('loan_payment_schedules')
            && $this->hasScheduleColumn('loan_id')
            && $this->hasScheduleColumn('due_date');
    }

    protected function hasTable(string $table): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function applyFilters($query, array $filters, string $alias = '') 
    {
        $prefix = $alias ? $alias.'.' : '';
        foreach (['collection_status', 'overdue_bucket', 'risk_level', 'skip_level'] as $field) {
            if (! empty($filters[$field]) && Schema::connection($this->connection)->hasColumn('loans', $field)) {
                $query->where($prefix.$field, $filters[$field]);
            }
        }
        if (! empty($filters['collector_id']) && Schema::connection($this->connection)->hasColumn('loans', 'assigned_collector_id')) {
            $query->where($prefix.'assigned_collector_id', $filters['collector_id']);
        }
        if (! empty($filters['business_location_id']) && Schema::connection($this->connection)->hasColumn('loans', 'business_location_id')) {
            $query->where($prefix.'business_location_id', $filters['business_location_id']);
        }
        if (! empty($filters['legal_status']) && Schema::connection($this->connection)->hasColumn('loans', 'legal_stage')) {
            $query->where($prefix.'legal_stage', $filters['legal_status']);
        }
        if (! empty($filters['payment_status']) && Schema::connection($this->connection)->hasColumn('loans', 'payment_status')) {
            $query->where($prefix.'payment_status', $filters['payment_status']);
        }
        if (! empty($filters['date_from']) && Schema::connection($this->connection)->hasColumn('loans', 'loan_date')) {
            $query->whereDate($prefix.'loan_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to']) && Schema::connection($this->connection)->hasColumn('loans', 'loan_date')) {
            $query->whereDate($prefix.'loan_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function distinctOptions(string $column, string $prefix): array
    {
        if (! Schema::connection($this->connection)->hasTable('loans') || ! Schema::connection($this->connection)->hasColumn('loans', $column)) {
            return [];
        }

        return DB::connection($this->connection)->table('loans')
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn ($id) => [$id => $prefix.$id])
            ->all();
    }

    protected function collectionAmountToday(): float
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payments')) {
            return 0.0;
        }

        $dateColumn = Schema::connection($this->connection)->hasColumn('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at';
        $amountColumn = Schema::connection($this->connection)->hasColumn('loan_payments', 'total_paid_base') ? 'total_paid_base' : 'amount';

        return (float) DB::connection($this->connection)->table('loan_payments')
            ->whereDate($dateColumn, Carbon::today()->toDateString())
            ->sum($amountColumn);
    }

    protected function daysPastDue(int $loanId): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return 0;
        }

        $date = DB::connection($this->connection)->table('loan_payment_schedules')
            ->where('loan_id', $loanId)
            ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->orderBy('due_date')
            ->value('due_date');

        return $date ? Carbon::parse($date)->diffInDays(Carbon::today()) : 0;
    }

    protected function isDueToday(int $loanId): bool
    {
        return Schema::connection($this->connection)->hasTable('loan_payment_schedules')
            && DB::connection($this->connection)->table('loan_payment_schedules')
                ->where('loan_id', $loanId)
                ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                ->whereDate('due_date', Carbon::today()->toDateString())
                ->exists();
    }

    protected function bucket(int $days): string
    {
        return match (true) {
            $days <= 0 => 'current',
            $days <= 7 => '1_7',
            $days <= 30 => '8_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            $days <= 180 => '91_180',
            default => '180_plus',
        };
    }

    protected function priority(string $status, string $risk, int $days): int
    {
        $base = in_array($risk, ['critical', 'fraud_risk', 'hard_skip'], true) ? 80 : 20;
        $base += in_array($status, ['legal', 'debt_collection', 'broken_ptp', 'skip_customer'], true) ? 20 : 0;

        return min(100, $base + min(40, (int) floor($days / 5)));
    }

    protected function reportToPage(string $report): string
    {
        return match ($report) {
            'skip-customers' => 'skip-customers',
            'recovery' => 'recovery-management',
            'ptp-compliance' => 'promise-to-pay',
            'broken-promise' => 'broken-promise',
            'legal-cases' => 'legal-cases',
            'repossession' => 'repossessions',
            'risk-analysis' => 'high-risk-customers',
            default => 'overdue-accounts',
        };
    }
}
