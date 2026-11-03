<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only statement built from current loan records and posted payment records. */
class LoanCustomerLedgerService
{
    protected string $connection = 'mysql_loan';

    public function statement(int $customer, array $filters = []): array
    {
        $db = DB::connection($this->connection);
        $schema = Schema::connection($this->connection);
        $loans = collect();
        if ($schema->hasTable('loans')) {
            $query = $db->table('loans')->where('customer_id', $customer);
            if ($schema->hasColumn('loans', 'deleted_at')) $query->whereNull('deleted_at');
            $loans = $query->orderBy('id')->get()->keyBy('id');
        }
        $payments = collect();
        if ($schema->hasTable('loan_payments')) {
            $columns = $schema->getColumnListing('loan_payments');
            $query = $db->table('loan_payments');
            $hasLoan = in_array('loan_id', $columns, true);
            $hasCustomer = in_array('customer_id', $columns, true);
            if ($hasLoan || $hasCustomer) {
                $query->where(function ($q) use ($hasLoan, $hasCustomer, $loans, $customer) {
                    if ($hasLoan) $q->whereIn('loan_id', $loans->keys());
                    if ($hasCustomer) {
                        // A payment associated with another customer's loan must not leak into this statement.
                        $direct = function ($q) use ($customer, $hasLoan) {
                            $q->where('customer_id', $customer);
                            if ($hasLoan) $q->where(fn ($q) => $q->whereNull('loan_id')->orWhere('loan_id', 0));
                        };
                        if ($hasLoan) $q->orWhere($direct); else $q->where($direct);
                    }
                });
                if (in_array('deleted_at', $columns, true)) $query->whereNull('deleted_at');
                if (in_array('status', $columns, true)) $query->whereRaw("LOWER(COALESCE(status, 'confirmed')) NOT IN ('cancelled', 'canceled', 'failed', 'void', 'deleted', 'rejected', 'pending', 'draft')");
                $payments = $query->orderBy('id')->get();
            }
        }
        $loanFilter = (int) ($filters['loan_id'] ?? 0);
        abort_if($loanFilter && ! $loans->has($loanFilter), 404);
        $events = [];
        $credited = [];
        $lastPaymentDates = [];
        foreach ($payments as $payment) {
            $loan = $loans->get($payment->loan_id ?? 0);
            if ($loanFilter && (! $loan || (int) $loan->id !== $loanFilter)) continue;
            $currency = strtoupper(trim((string) ($loan->currency ?? (isset($payment->total_paid_base) && !isset($payment->total_paid) ? 'USD' : ($payment->currency ?? 'USD'))))) ?: 'USD';
            $amount = $this->paymentAmount($payment, $loan, $currency);
            $date = $this->date($payment->paid_date ?? $payment->paid_at ?? $payment->created_at ?? null);
            $events[] = ['date' => $date, 'rank' => 1, 'id' => $payment->id, 'type' => 'payment', 'currency' => $currency,
                'loan_id' => $loan->id ?? null, 'loan_number' => $loan->loan_number ?? '—',
                'reference' => ($payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? '') ?: '#'.$payment->id,
                'description' => $amount < 0 ? 'Payment reversal' : 'Payment received', 'method' => $payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? '',
                'debit' => max(0, -$amount), 'credit' => max(0, $amount)];
            if ($loan) {
                $credited[$loan->id] = ($credited[$loan->id] ?? 0) + $amount;
                $lastPaymentDates[$loan->id] = max($lastPaymentDates[$loan->id] ?? '', $date ?? '');
            }
        }
        foreach ($loans as $loan) {
            if ($loanFilter && (int) $loan->id !== $loanFilter) continue;
            // A posted receipt remains a customer credit even before approval or after cancellation.
            // Unposted contracts themselves do not create a receivable.
            if (in_array(strtolower((string) ($loan->status ?? 'active')), ['draft', 'pending', 'cancelled', 'canceled', 'rejected'], true)) continue;
            $currency = strtoupper(trim((string) ($loan->currency ?? 'USD'))) ?: 'USD';
            $date = $this->date($loan->loan_date ?? $loan->created_at ?? null);
            // Scheduled total excludes the upfront deposit in this module.
            $charge = $this->minor($loan->total_amount ?? $loan->total_payable_amount ?? $loan->principal_amount ?? 0) + $this->minor($loan->down_payment ?? 0);
            $events[] = ['date' => $date, 'rank' => 0, 'id' => $loan->id, 'type' => 'loan', 'currency' => $currency,
                'loan_id' => $loan->id, 'loan_number' => $loan->loan_number ?? '#'.$loan->id, 'reference' => $loan->loan_number ?? '#'.$loan->id,
                'description' => 'Installment charge', 'method' => '', 'debit' => max(0, $charge), 'credit' => max(0, -$charge)];
            if (isset($loan->balance_amount)) {
                $difference = $this->minor($loan->balance_amount) - ($charge - ($credited[$loan->id] ?? 0));
                if ($difference !== 0) {
                    // Imported balances, waived fees, accruals and amended terms are explicitly reconciled;
                    // never invent an extra receipt or silently change the recorded payment amount.
                    $adjustmentDate = max($date ?? '', $lastPaymentDates[$loan->id] ?? '', $this->date($loan->updated_at ?? null) ?? '') ?: null;
                    $events[] = ['date' => $adjustmentDate, 'rank' => 2, 'id' => $loan->id, 'type' => 'adjustment', 'currency' => $currency,
                        'loan_id' => $loan->id, 'loan_number' => $loan->loan_number ?? '#'.$loan->id, 'reference' => $loan->loan_number ?? '#'.$loan->id,
                        'description' => 'Recorded balance adjustment', 'method' => '', 'debit' => max(0, $difference), 'credit' => max(0, -$difference)];
                }
            }
        }
        usort($events, fn ($a, $b) => [$a['date'] ?? '', $a['rank'], $a['id']] <=> [$b['date'] ?? '', $b['rank'], $b['id']]);
        $summaries = []; $rows = []; $undated = 0;
        foreach ($events as $event) {
            $currency = $event['currency'];
            if (!empty($filters['currency']) && strtoupper($filters['currency']) !== $currency) continue;
            $summary = $summaries[$currency] ?? ['currency' => $currency, 'opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0, 'count' => 0];
            if ($event['date'] === null && (!empty($filters['start_date']) || !empty($filters['end_date']))) { $undated++; continue; }
            if (!empty($filters['end_date']) && $event['date'] > $filters['end_date']) continue;
            $movement = $event['debit'] - $event['credit'];
            if (!empty($filters['start_date']) && $event['date'] < $filters['start_date']) {
                $summary['opening'] += $movement;
                $summary['closing'] += $movement;
            } else {
                $summary['debit'] += $event['debit']; $summary['credit'] += $event['credit'];
                $summary['closing'] += $movement; $summary['count']++;
                $event['balance'] = $summary['closing'];
                $rows[] = $event;
            }
            $summaries[$currency] = $summary;
        }
        ksort($summaries);
        return ['rows' => collect($rows), 'summaries' => collect($summaries)->values(), 'loans' => $loans->values(),
            'currencies' => collect($events)->pluck('currency')->unique()->sort()->values(), 'undated' => $undated,
            'has_adjustments' => collect($rows)->contains('type', 'adjustment')];
    }

    protected function paymentAmount(object $payment, ?object $loan, string $currency): int
    {
        $paymentCurrency = strtoupper(trim((string) ($payment->currency ?? $currency))) ?: $currency;
        if (isset($payment->total_paid) && $paymentCurrency === $currency) return $this->minor($payment->total_paid);
        if (isset($payment->total_paid_base)) {
            $rate = (float) ($loan->exchange_rate ?? 1);
            return $this->minor((float) $payment->total_paid_base / ($rate > 0 ? $rate : 1));
        }
        if ($paymentCurrency !== $currency && isset($payment->total_paid)) {
            $rate = (float) ($loan->exchange_rate ?? 1);
            return $this->minor((float) $payment->total_paid * (float) ($payment->exchange_rate ?? 1) / ($rate > 0 ? $rate : 1));
        }
        return $this->minor($payment->amount ?? $payment->total_paid ?? 0);
    }

    protected function minor($amount): int { return (int) round((float) $amount * 100); }
    protected function date($value): ?string
    {
        if (!$value) return null;
        try { return Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable $exception) { return null; }
    }
}
