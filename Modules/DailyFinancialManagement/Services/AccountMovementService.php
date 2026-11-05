<?php

namespace Modules\DailyFinancialManagement\Services;

use App\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialAccountMovement;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;

class AccountMovementService
{
    /**
     * Transfer funds between two financial accounts.
     */
    public function transfer(
        int $businessId,
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        string $date,
        ?string $refNo = null,
        ?string $notes = null,
        ?User $actor = null
    ): array {
        if ($fromAccountId === $toAccountId) {
            throw new Exception("Source and destination accounts must be different.");
        }

        if ($amount <= 0) {
            throw new Exception("Transfer amount must be strictly greater than zero.");
        }

        return DB::transaction(function () use ($businessId, $fromAccountId, $toAccountId, $amount, $date, $refNo, $notes, $actor) {
            $fromAccount = FinancialAccount::where('business_id', $businessId)->lockForUpdate()->findOrFail($fromAccountId);
            $toAccount = FinancialAccount::where('business_id', $businessId)->lockForUpdate()->findOrFail($toAccountId);

            $transferRef = $refNo ?: ('TRF-' . strtoupper(uniqid()));

            // Outflow from source account
            $fromBefore = (float) $fromAccount->current_balance;
            $fromAfter = $fromBefore - $amount;

            $outflow = FinancialAccountMovement::create([
                'business_id' => $businessId,
                'financial_account_id' => $fromAccount->id,
                'financial_transaction_id' => null,
                'movement_type' => 'transfer_out',
                'movement_date' => $date,
                'amount' => $amount,
                'balance_before' => $fromBefore,
                'balance_after' => $fromAfter,
                'related_account_id' => $toAccount->id,
                'ref_no' => $transferRef,
                'source_type' => 'transfer',
                'notes' => $notes ?: "Transfer to {$toAccount->account_name}",
                'created_by' => $actor ? $actor->id : auth()->id(),
            ]);

            $fromAccount->update(['current_balance' => $fromAfter]);

            // Inflow into destination account
            $toBefore = (float) $toAccount->current_balance;
            $toAfter = $toBefore + $amount;

            $inflow = FinancialAccountMovement::create([
                'business_id' => $businessId,
                'financial_account_id' => $toAccount->id,
                'financial_transaction_id' => null,
                'movement_type' => 'transfer_in',
                'movement_date' => $date,
                'amount' => $amount,
                'balance_before' => $toBefore,
                'balance_after' => $toAfter,
                'related_account_id' => $fromAccount->id,
                'ref_no' => $transferRef,
                'source_type' => 'transfer',
                'notes' => $notes ?: "Transfer from {$fromAccount->account_name}",
                'created_by' => $actor ? $actor->id : auth()->id(),
            ]);

            $toAccount->update(['current_balance' => $toAfter]);

            FinancialAuditLog::record(
                $businessId,
                'account_transfer',
                'FinancialAccount',
                $fromAccount->id,
                ['balance' => $fromBefore],
                ['transferred_to' => $toAccount->id, 'amount' => $amount],
                "Transferred \${$amount} from {$fromAccount->account_name} to {$toAccount->account_name} (Ref: {$transferRef})"
            );

            return [
                'success' => true,
                'transfer_ref' => $transferRef,
                'outflow' => $outflow,
                'inflow' => $inflow,
                'from_account' => $fromAccount->fresh(),
                'to_account' => $toAccount->fresh(),
            ];
        });
    }
}

