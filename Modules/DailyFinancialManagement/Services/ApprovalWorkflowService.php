<?php

namespace Modules\DailyFinancialManagement\Services;

use App\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\DailyFinancialManagement\Entities\FinancialApproval;
use Modules\DailyFinancialManagement\Entities\FinancialApprovalSetting;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;

class ApprovalWorkflowService
{
    /**
     * Submit a draft transaction for approval.
     */
    public function submit(FinancialTransaction $transaction, User $actor, ?string $comments = null): FinancialTransaction
    {
        $this->guardNotLocked($transaction);

        if (! in_array($transaction->approval_status, ['draft', 'rejected'])) {
            throw new Exception("Transaction in state '{$transaction->approval_status}' cannot be submitted.");
        }

        DB::transaction(function () use ($transaction, $actor, $comments) {
            $oldStatus = $transaction->approval_status;
            $transaction->update(['approval_status' => 'pending']);

            FinancialApproval::create([
                'financial_transaction_id' => $transaction->id,
                'action' => 'submitted',
                'actor_id' => $actor->id,
                'actor_role' => $this->determineUserRole($actor, $transaction->business_id),
                'comments' => $comments,
                'created_at' => now(),
            ]);

            FinancialAuditLog::record(
                $transaction->business_id,
                'transaction_submitted',
                'FinancialTransaction',
                $transaction->id,
                ['approval_status' => $oldStatus],
                ['approval_status' => 'pending'],
                $comments
            );
        });

        return $transaction->fresh();
    }

    /**
     * Review a transaction (Accountant step).
     */
    public function review(FinancialTransaction $transaction, User $actor, ?string $comments = null): FinancialTransaction
    {
        $this->guardNotLocked($transaction);

        if ($transaction->approval_status !== 'pending') {
            throw new Exception("Only pending transactions can be marked as reviewed.");
        }

        DB::transaction(function () use ($transaction, $actor, $comments) {
            $transaction->update([
                'approval_status' => 'reviewed',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            FinancialApproval::create([
                'financial_transaction_id' => $transaction->id,
                'action' => 'reviewed',
                'actor_id' => $actor->id,
                'actor_role' => 'accountant',
                'comments' => $comments,
                'created_at' => now(),
            ]);

            FinancialAuditLog::record(
                $transaction->business_id,
                'transaction_reviewed',
                'FinancialTransaction',
                $transaction->id,
                ['approval_status' => 'pending'],
                ['approval_status' => 'reviewed', 'reviewed_by' => $actor->id],
                $comments
            );
        });

        return $transaction->fresh();
    }

    /**
     * Approve a transaction (Manager / Director step with limit checks).
     */
    public function approve(FinancialTransaction $transaction, User $actor, ?string $comments = null): FinancialTransaction
    {
        $this->guardNotLocked($transaction);

        if (! in_array($transaction->approval_status, ['pending', 'reviewed'])) {
            throw new Exception("Transaction in state '{$transaction->approval_status}' cannot be approved.");
        }

        $this->checkApprovalLimits($transaction, $actor);

        DB::transaction(function () use ($transaction, $actor, $comments) {
            $oldStatus = $transaction->approval_status;

            $transaction->update([
                'approval_status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            FinancialApproval::create([
                'financial_transaction_id' => $transaction->id,
                'action' => 'approved',
                'actor_id' => $actor->id,
                'actor_role' => $this->determineUserRole($actor, $transaction->business_id),
                'comments' => $comments,
                'created_at' => now(),
            ]);

            FinancialAuditLog::record(
                $transaction->business_id,
                'transaction_approved',
                'FinancialTransaction',
                $transaction->id,
                ['approval_status' => $oldStatus],
                ['approval_status' => 'approved', 'approved_by' => $actor->id],
                $comments
            );
        });

        return $transaction->fresh();
    }

    /**
     * Reject a transaction.
     */
    public function reject(FinancialTransaction $transaction, User $actor, string $reason): FinancialTransaction
    {
        $this->guardNotLocked($transaction);

        if (! in_array($transaction->approval_status, ['pending', 'reviewed'])) {
            throw new Exception("Transaction in state '{$transaction->approval_status}' cannot be rejected.");
        }

        DB::transaction(function () use ($transaction, $actor, $reason) {
            $oldStatus = $transaction->approval_status;

            $transaction->update([
                'approval_status' => 'rejected',
            ]);

            FinancialApproval::create([
                'financial_transaction_id' => $transaction->id,
                'action' => 'rejected',
                'actor_id' => $actor->id,
                'actor_role' => $this->determineUserRole($actor, $transaction->business_id),
                'comments' => $reason,
                'created_at' => now(),
            ]);

            FinancialAuditLog::record(
                $transaction->business_id,
                'transaction_rejected',
                'FinancialTransaction',
                $transaction->id,
                ['approval_status' => $oldStatus],
                ['approval_status' => 'rejected'],
                $reason
            );
        });

        return $transaction->fresh();
    }

    /**
     * Create an explicit reversal of an approved transaction.
     * Approved transactions must NEVER be silently edited or deleted.
     */
    public function reverse(FinancialTransaction $transaction, User $actor, string $reversalReason): FinancialTransaction
    {
        $this->guardNotLocked($transaction);

        if ($transaction->approval_status !== 'approved') {
            throw new Exception("Only approved transactions can be reversed.");
        }

        return DB::transaction(function () use ($transaction, $actor, $reversalReason) {
            // Mark original transaction as reversed
            $transaction->update(['approval_status' => 'reversed']);

            // Create reversal transaction
            $reversal = FinancialTransaction::create([
                'business_id' => $transaction->business_id,
                'business_location_id' => $transaction->business_location_id,
                'department_id' => $transaction->department_id,
                'category_id' => $transaction->category_id,
                'financial_account_id' => $transaction->financial_account_id,
                'transaction_type' => $transaction->transaction_type,
                'transaction_date' => now()->format('Y-m-d'),
                'ref_no' => 'REV-' . $transaction->ref_no,
                'source_type' => 'reversal',
                'source_id' => $transaction->id,
                'description' => "REVERSAL of #{$transaction->ref_no}: {$reversalReason}",
                'amount' => -$transaction->amount,
                'cogs_amount' => -$transaction->cogs_amount,
                'currency' => $transaction->currency,
                'exchange_rate' => $transaction->exchange_rate,
                'amount_in_base_currency' => -$transaction->amount_in_base_currency,
                'payment_method' => $transaction->payment_method,
                'approval_status' => 'approved',
                'is_operating' => $transaction->is_operating,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'reversal_of_id' => $transaction->id,
                'meta_json' => [
                    'original_transaction_id' => $transaction->id,
                    'reversal_reason' => $reversalReason,
                ],
            ]);

            FinancialApproval::create([
                'financial_transaction_id' => $transaction->id,
                'action' => 'reversed',
                'actor_id' => $actor->id,
                'actor_role' => $this->determineUserRole($actor, $transaction->business_id),
                'comments' => $reversalReason,
                'created_at' => now(),
            ]);

            FinancialAuditLog::record(
                $transaction->business_id,
                'transaction_reversed',
                'FinancialTransaction',
                $transaction->id,
                ['approval_status' => 'approved'],
                ['approval_status' => 'reversed', 'reversal_id' => $reversal->id],
                $reversalReason
            );

            return $reversal;
        });
    }

    protected function checkApprovalLimits(FinancialTransaction $transaction, User $actor): void
    {
        $businessId = $transaction->business_id;
        $isAdmin = $actor->hasRole('Admin#' . $businessId);

        if ($isAdmin) {
            return;
        }

        $settings = FinancialApprovalSetting::where('business_id', $businessId)
            ->where(function ($q) use ($transaction) {
                $q->where('business_location_id', $transaction->business_location_id)
                  ->orWhereNull('business_location_id');
            })
            ->first();

        $amount = (float) $transaction->amount_in_base_currency;
        $role = $this->determineUserRole($actor, $businessId);

        if ($role === 'cashier' && $settings && $amount > (float) $settings->cashier_limit) {
            throw new Exception("Cashier approval limit exceeded (\${$settings->cashier_limit}). Requires Branch Manager approval.");
        }

        if ($role === 'branch_manager' && $settings && $amount > (float) $settings->branch_manager_limit) {
            throw new Exception("Branch Manager approval limit exceeded (\${$settings->branch_manager_limit}). Requires Director approval.");
        }
    }

    protected function determineUserRole(User $actor, int $businessId): string
    {
        if ($actor->hasRole('Admin#' . $businessId)) {
            return 'owner_director';
        }

        if ($actor->can('daily_financial.approvals.approve')) {
            return 'branch_manager';
        }

        if ($actor->can('daily_financial.approvals.review')) {
            return 'accountant';
        }

        return 'cashier';
    }

    protected function guardNotLocked(FinancialTransaction $transaction): void
    {
        if ($transaction->is_locked) {
            throw new Exception("Financial transaction #{$transaction->ref_no} is locked under a daily closing and cannot be altered.");
        }
    }
}

