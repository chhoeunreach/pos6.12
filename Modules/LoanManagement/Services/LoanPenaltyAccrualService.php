<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LoanPenaltyAccrualService
{
    protected string $conn = 'mysql_loan';

    /**
     * Run daily penalty accrual and DPD calculation across all active loans.
     */
    public function accrueDaily(bool $dryRun = false): array
    {
        $today = Carbon::today();
        $summary = [
            'loans_checked' => 0,
            'loans_updated' => 0,
            'schedules_overdue' => 0,
            'penalties_added_count' => 0,
            'total_penalties_accrued' => 0.0,
        ];

        if (! Schema::connection($this->conn)->hasTable('loans') || ! Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
            return $summary;
        }

        $activeLoans = DB::connection($this->conn)->table('loans')
            ->whereIn('status', ['active', 'overdue', 'defaulted'])
            ->whereNull('deleted_at')
            ->get();

        $summary['loans_checked'] = $activeLoans->count();

        foreach ($activeLoans as $loan) {
            $result = $this->accrueLoan($loan, $today, $dryRun);
            if ($result['updated']) {
                $summary['loans_updated']++;
                $summary['schedules_overdue'] += $result['overdue_schedules'];
                $summary['penalties_added_count'] += $result['penalties_count'];
                $summary['total_penalties_accrued'] += $result['penalties_amount'];
            }
        }

        return $summary;
    }

    /**
     * Accrue penalties and update DPD for a single loan.
     */
    public function accrueLoan(object $loan, Carbon $today, bool $dryRun = false): array
    {
        return DB::connection($this->conn)->transaction(function () use ($loan, $today, $dryRun) {
            $currentLoan = DB::connection($this->conn)->table('loans')->where('id', $loan->id)->lockForUpdate()->first();
            if (! $currentLoan || ! empty($currentLoan->deleted_at) || ! in_array($currentLoan->status, ['active', 'overdue', 'defaulted'], true)) {
                return ['updated' => false, 'overdue_schedules' => 0, 'penalties_count' => 0, 'penalties_amount' => 0.0];
            }

            return $this->accrueLockedLoan($currentLoan, $today, $dryRun);
        });
    }

    protected function accrueLockedLoan(object $loan, Carbon $today, bool $dryRun): array
    {
        $loanId = (int) $loan->id;
        $schedules = DB::connection($this->conn)->table('loan_payment_schedules')
            ->where('loan_id', $loanId)
            ->whereNull('deleted_at')
            ->orderBy('installment_no')
            ->lockForUpdate()
            ->get();

        if ($schedules->isEmpty()) {
            return ['updated' => false, 'overdue_schedules' => 0, 'penalties_count' => 0, 'penalties_amount' => 0.0];
        }

        $maxDpd = 0;
        $overdueCount = 0;
        $penaltiesAdded = 0;
        $penaltiesAmount = 0.0;
        $hasPenaltiesTable = Schema::connection($this->conn)->hasTable('loan_penalties');

        foreach ($schedules as $sched) {
            $dueDate = Carbon::parse($sched->due_date);
            $amountBalance = (float) ($sched->amount_balance ?? ($sched->amount_due - ($sched->amount_paid ?? 0)));

            if ($dueDate->lt($today) && $amountBalance > 0.01 && ($sched->status ?? '') !== 'paid') {
                $overdueCount++;
                $dpd = (int) $today->diffInDays($dueDate, true);
                if ($dpd > $maxDpd) {
                    $maxDpd = $dpd;
                }

                $dailyPenaltyRate = max(0, (float) ($loan->daily_penalty_rate ?? config('loanmanagement.daily_penalty_rate', 0.50)));
                abort_unless($hasPenaltiesTable || $dailyPenaltyRate == 0, 409, 'Penalty history is required before accruing penalties.');

                // Check if penalty was already accrued today for this schedule
                $alreadyAccruedToday = false;
                if ($hasPenaltiesTable) {
                    $alreadyAccruedToday = DB::connection($this->conn)->table('loan_penalties')
                        ->where('loan_id', $loanId)
                        ->where('schedule_id', $sched->id)
                        ->whereDate('applied_at', $today->toDateString())
                        ->exists();
                }

                if (! $alreadyAccruedToday && $dailyPenaltyRate > 0) {
                    $penaltiesAdded++;
                    $penaltiesAmount += $dailyPenaltyRate;

                    if (! $dryRun) {
                        $newPenaltyDue = round(((float) ($sched->penalty_due ?? 0)) + $dailyPenaltyRate, 2);
                        $newAmountDue = round(((float) ($sched->principal_due ?? 0)) + ((float) ($sched->interest_due ?? 0)) + $newPenaltyDue, 2);
                        $newBalance = max(0, round($newAmountDue - ((float) ($sched->amount_paid ?? 0)), 2));

                        DB::connection($this->conn)->table('loan_payment_schedules')
                            ->where('id', $sched->id)
                            ->update(array_intersect_key([
                                'penalty_due' => $newPenaltyDue,
                                'amount_due' => $newAmountDue,
                                'amount_balance' => $newBalance,
                                'balance_amount' => $newBalance,
                                'schedule_amount' => $newAmountDue,
                                'status' => 'overdue',
                                'updated_at' => now(),
                            ], array_flip(Schema::connection($this->conn)->getColumnListing('loan_payment_schedules'))));

                        if ($hasPenaltiesTable) {
                            DB::connection($this->conn)->table('loan_penalties')->insert([
                                'loan_id' => $loanId,
                                'schedule_id' => $sched->id,
                                'amount' => $dailyPenaltyRate,
                                'reason' => "Daily penalty for installment #{$sched->installment_no} ({$dpd} DPD)",
                                'applied_at' => $today,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                } elseif (! $dryRun && ($sched->status ?? '') !== 'overdue') {
                    // Update status to overdue even if penalty already applied
                    DB::connection($this->conn)->table('loan_payment_schedules')
                        ->where('id', $sched->id)
                        ->update(['status' => 'overdue', 'updated_at' => now()]);
                }
            }
        }

        // Aggregate loan level DPD, bucket, and balances
        if (! $dryRun) {
            $updatedSchedules = DB::connection($this->conn)->table('loan_payment_schedules')
                ->where('loan_id', $loanId)
                ->whereNull('deleted_at')
                ->get();

            $totalPenalty = (float) $updatedSchedules->sum('penalty_due');
            $totalBalance = (float) $updatedSchedules->sum('amount_balance');

            $bucket = 'current';
            if ($maxDpd > 90) $bucket = '90_plus';
            elseif ($maxDpd > 60) $bucket = '61_90';
            elseif ($maxDpd > 30) $bucket = '31_60';
            elseif ($maxDpd > 0) $bucket = '1_30';

            $collectionStatus = $loan->collection_status ?? 'active';
            $riskLevel = $loan->risk_level ?? 'normal';

            if ($maxDpd > 90) {
                $collectionStatus = 'debt_collection';
                $riskLevel = 'critical';
            } elseif ($maxDpd > 30) {
                $collectionStatus = 'overdue';
                $riskLevel = 'high_risk';
            } elseif ($maxDpd > 0) {
                $collectionStatus = 'overdue';
            }

            $loanPayload = [
                'penalty_amount' => $totalPenalty,
                'balance_amount' => $totalBalance,
                'updated_at' => now(),
            ];

            if (Schema::connection($this->conn)->hasColumn('loans', 'days_past_due')) {
                $loanPayload['days_past_due'] = $maxDpd;
            }
            if (Schema::connection($this->conn)->hasColumn('loans', 'overdue_bucket')) {
                $loanPayload['overdue_bucket'] = $bucket;
            }
            if (Schema::connection($this->conn)->hasColumn('loans', 'collection_status')) {
                $loanPayload['collection_status'] = $collectionStatus;
            }
            if (Schema::connection($this->conn)->hasColumn('loans', 'risk_level')) {
                $loanPayload['risk_level'] = $riskLevel;
            }

            DB::connection($this->conn)->table('loans')
                ->where('id', $loanId)
                ->update($loanPayload);
        }

        return [
            'updated' => true,
            'overdue_schedules' => $overdueCount,
            'penalties_count' => $penaltiesAdded,
            'penalties_amount' => $penaltiesAmount,
        ];
    }
}
