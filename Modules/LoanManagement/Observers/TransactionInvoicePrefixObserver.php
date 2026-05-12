<?php

namespace Modules\LoanManagement\Observers;

use App\BusinessLocation;
use App\Transaction;
use Illuminate\Support\Facades\Schema;

class TransactionInvoicePrefixObserver
{
    public function created(Transaction $transaction): void
    {
        $this->applyPrefix($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->applyPrefix($transaction);
    }

    private function applyPrefix(Transaction $transaction): void
    {
        if ($transaction->type !== 'sell' || empty($transaction->invoice_no) || empty($transaction->location_id)) {
            return;
        }

        if (! Schema::hasColumn('business_locations', 'invoice_prefix')) {
            return;
        }

        $prefix = BusinessLocation::where('business_id', $transaction->business_id)
            ->where('id', $transaction->location_id)
            ->value('invoice_prefix');

        $prefix = trim((string) $prefix);
        if ($prefix === '') {
            return;
        }

        $normalizedPrefix = $prefix.'-';
        if (strpos((string) $transaction->invoice_no, $normalizedPrefix) === 0) {
            return;
        }

        $transaction->updateQuietly([
            'invoice_no' => $normalizedPrefix.$transaction->invoice_no,
        ]);
    }
}
