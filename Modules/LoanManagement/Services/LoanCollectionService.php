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

        return [
            'due_today' => (int) (clone $loans)->where('collection_status', 'due_today')->count(),
            'overdue_accounts' => (int) (clone $loans)->whereIn('collection_status', ['overdue', 'delinquent'])->count(),
            'skip_customers' => (int) (clone $loans)->where('collection_status', 'skip_customer')->count(),
            'broken_ptp' => (int) (clone $loans)->where('collection_status', 'broken_ptp')->count(),
            'field_visits_today' => (int) (clone $loans)->where('field_visit_required', 1)->whereDate('next_followup_at', '<=', $today)->count(),
            'collection_amount_today' => $this->collectionAmountToday(),
            'recovery_cases' => (int) (clone $loans)->where('collection_status', 'recovery')->count(),
            'legal_cases' => (int) (clone $loans)->where('collection_status', 'legal')->count(),
            'high_risk_customers' => (int) (clone $loans)->whereIn('risk_level', ['high_risk', 'critical'])->count(),
            'repossessions' => (int) (clone $loans)->where('collection_status', 'repossession')->count(),
        ];
    }

    public function loansForPage(string $slug, array $filters = [])
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return collect();
        }

        $definition = $this->pageDefinition($slug);
        $query = $this->loanQuery();
        foreach (($definition['where'] ?? []) as $column => $values) {
            if (Schema::connection($this->connection)->hasColumn('loans', $column)) {
                $query->whereIn('l.'.$column, (array) $values);
            }
        }

        return $this->applyFilters($query, $filters, 'l')
            ->orderByDesc('l.collection_priority')
            ->orderByDesc('l.days_past_due')
            ->orderByDesc('l.id')
            ->paginate(30)
            ->appends(array_filter($filters));
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
