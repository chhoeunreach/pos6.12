<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\Loan;

class CustomerLoanSummaryService
{
    protected string $connection = 'mysql_loan';

    public function buildLoanSummary(Loan $loan): array
    {
        $productPrice = $this->productPrice($loan);
        $totalInterest = $this->totalInterest($loan);
        $totalPaid = $this->totalPaidAmount($loan);
        $totalLoanAmount = $productPrice + $totalInterest;
        $paidPrincipal = $this->calculatePaidPrincipal($loan);
        $paidInterest = $this->calculatePaidInterest($loan);
        $remainingPrincipal = max(0, $productPrice - $paidPrincipal);
        $remainingMonths = $this->calculateRemainingMonths($loan);
        $badge = $this->resolveStatusBadge($loan);
        $product = $this->productSnapshot($loan);

        return [
            'id' => (int) $loan->id,
            'loan_number' => (string) ($loan->loan_number ?? ''),
            'product_name' => $product['name'],
            'product_price' => $productPrice,
            'imei_or_serial' => $product['imei_or_serial'],
            'total_paid_amount' => $totalPaid,
            'remaining_balance' => max(0, $totalLoanAmount - $totalPaid),
            'monthly_payment_amount' => $this->calculateMonthlyPayment($loan),
            'monthly_principal' => $this->calculateMonthlyPrincipal($loan),
            'monthly_interest' => $this->monthlyInterest($loan),
            'payoff_this_month_amount' => $this->calculatePayoffThisMonth($loan),
            'payoff_by_full_schedule_amount' => $this->calculatePayoffByFullSchedule($loan),
            'total_loan_amount' => $totalLoanAmount,
            'total_interest' => $totalInterest,
            'paid_principal' => $paidPrincipal,
            'paid_interest' => $paidInterest,
            'remaining_principal' => $remainingPrincipal,
            'remaining_months' => $remainingMonths,
            'total_installment_months' => $this->totalInstallmentMonths($loan),
            'paid_month_count' => $this->paidMonthCount($loan),
            'loan_status' => $badge['status'],
            'loan_status_label' => $badge['label'],
            'loan_status_color' => $badge['color'],
            'currency' => (string) ($loan->currency ?? 'USD'),
        ];
    }

    public function calculateMonthlyPrincipal(Loan $loan): float
    {
        return round($this->productPrice($loan) / max(1, $this->totalInstallmentMonths($loan)), 2);
    }

    public function calculateMonthlyPayment(Loan $loan): float
    {
        return round($this->calculateMonthlyPrincipal($loan) + $this->monthlyInterest($loan), 2);
    }

    public function calculatePayoffThisMonth(Loan $loan): float
    {
        $remainingPrincipal = max(0, $this->productPrice($loan) - $this->calculatePaidPrincipal($loan));

        return $remainingPrincipal <= 0 ? 0.0 : round($remainingPrincipal + $this->monthlyInterest($loan), 2);
    }

    public function calculatePayoffByFullSchedule(Loan $loan): float
    {
        $remainingPrincipal = max(0, $this->productPrice($loan) - $this->calculatePaidPrincipal($loan));

        return round($remainingPrincipal + ($this->monthlyInterest($loan) * $this->calculateRemainingMonths($loan)), 2);
    }

    public function calculatePaidPrincipal(Loan $loan): float
    {
        $paidPrincipal = $this->schedules($loan)->sum(function ($schedule) {
            $paid = $this->moneyValue($schedule, ['paid_amount', 'amount_paid']);
            $principal = $this->moneyValue($schedule, ['principal_amount', 'principal_due']);
            $interest = $this->moneyValue($schedule, ['interest_amount', 'interest_due']);

            return min($principal, max(0, $paid - min($paid, $interest)));
        });

        if ($paidPrincipal <= 0) {
            $paidPrincipal = $this->calculateMonthlyPrincipal($loan) * $this->paidMonthCount($loan);
        }

        if ($paidPrincipal <= 0) {
            $paidPrincipal = max(0, $this->totalPaidAmount($loan) - $this->calculatePaidInterest($loan));
        }

        return round(min($this->productPrice($loan), $paidPrincipal), 2);
    }

    public function calculatePaidInterest(Loan $loan): float
    {
        $paidInterest = $this->schedules($loan)->sum(function ($schedule) {
            $paid = $this->moneyValue($schedule, ['paid_amount', 'amount_paid']);
            $interest = $this->moneyValue($schedule, ['interest_amount', 'interest_due']);

            return min($paid, $interest);
        });

        return round(min($paidInterest, $this->totalInterest($loan)), 2);
    }

    public function calculateRemainingMonths(Loan $loan): int
    {
        return max(0, $this->totalInstallmentMonths($loan) - $this->paidMonthCount($loan));
    }

    public function resolveStatusBadge(Loan $loan): array
    {
        $status = strtolower((string) ($loan->status ?? ''));
        $remainingBalance = max(0, $this->productPrice($loan) + $this->totalInterest($loan) - $this->totalPaidAmount($loan));

        if ($remainingBalance <= 0 || in_array($status, ['closed', 'paid', 'completed'], true)) {
            return $this->badge('closed');
        }

        $schedules = $this->schedules($loan);
        $today = Carbon::today()->toDateString();
        $openSchedules = $schedules->filter(function ($schedule) {
            return $this->scheduleBalance($schedule) > 0
                || in_array(strtolower((string) ($schedule->status ?? '')), ['pending', 'unpaid', 'partial', 'late'], true);
        });

        if ($status === 'overdue'
            || $status === 'late'
            || $openSchedules->contains(fn ($schedule) => strtolower((string) ($schedule->status ?? '')) === 'late')
            || $openSchedules->contains(fn ($schedule) => ! empty($schedule->due_date) && (string) $schedule->due_date < $today)) {
            return $this->badge('overdue');
        }

        if ($openSchedules->contains(fn ($schedule) => ! empty($schedule->due_date) && (string) $schedule->due_date === $today)) {
            return $this->badge('due_today');
        }

        if ($openSchedules->contains(fn ($schedule) => strtolower((string) ($schedule->status ?? '')) === 'partial')) {
            return $this->badge('partial_paid');
        }

        return $this->badge('current');
    }

    public function totalPaidAmount(Loan $loan): float
    {
        $paid = $this->moneyValue($loan, ['paid_amount']);
        if ($paid > 0 || ! $this->hasTable('loan_payments')) {
            return round($paid, 2);
        }

        return round((float) DB::connection($this->connection)->table('loan_payments')
            ->where('loan_id', $loan->id)
            ->whereIn('status', ['confirmed', 'paid', 'completed'])
            ->sum('amount'), 2);
    }

    public function totalInstallmentMonths(Loan $loan): int
    {
        foreach (['installment_count', 'duration_months', 'term_months'] as $field) {
            $value = (int) ($loan->{$field} ?? 0);
            if ($value > 0) {
                return $value;
            }
        }

        $count = $this->schedules($loan)->count();

        return $count > 0 ? $count : 1;
    }

    public function paidMonthCount(Loan $loan): int
    {
        $paid = $this->schedules($loan)->filter(function ($schedule) {
            $status = strtolower((string) ($schedule->status ?? ''));

            return in_array($status, ['paid', 'closed', 'completed'], true) || $this->scheduleBalance($schedule) <= 0;
        })->count();

        if ($paid > 0) {
            return (int) $paid;
        }

        $monthlyPayment = $this->calculateMonthlyPayment($loan);

        return $monthlyPayment > 0 ? min($this->totalInstallmentMonths($loan), (int) floor($this->totalPaidAmount($loan) / $monthlyPayment)) : 0;
    }

    protected function productPrice(Loan $loan): float
    {
        $principal = $this->moneyValue($loan, ['principal_amount', 'total_payable_amount']);
        if ($principal > 0) {
            return round($principal, 2);
        }

        $itemTotal = $this->items($loan)->sum(fn ($item) => $this->moneyValue($item, ['line_total', 'total_price', 'unit_price']));

        return round($itemTotal, 2);
    }

    protected function totalInterest(Loan $loan): float
    {
        $interest = $this->moneyValue($loan, ['interest_amount', 'total_interest']);
        if ($interest > 0) {
            return round($interest, 2);
        }

        $scheduleInterest = $this->schedules($loan)->sum(fn ($schedule) => $this->moneyValue($schedule, ['interest_amount', 'interest_due']));
        if ($scheduleInterest > 0) {
            return round($scheduleInterest, 2);
        }

        $totalAmount = $this->moneyValue($loan, ['total_amount']);

        return round(max(0, $totalAmount - $this->productPrice($loan)), 2);
    }

    protected function monthlyInterest(Loan $loan): float
    {
        $months = max(1, $this->totalInstallmentMonths($loan));

        return round($this->totalInterest($loan) / $months, 2);
    }

    protected function productSnapshot(Loan $loan): array
    {
        $item = $this->items($loan)->first();

        return [
            'name' => (string) ($item->product_name_snapshot ?? $item->product_name ?? $loan->product_name_snapshot ?? ''),
            'imei_or_serial' => (string) ($item->imei_snapshot ?? $item->serial_number_snapshot ?? $item->serial_no ?? $item->imei_no ?? $loan->imei_snapshot ?? ''),
        ];
    }

    protected function schedules(Loan $loan): Collection
    {
        if (! $this->hasTable('loan_payment_schedules')) {
            return collect();
        }

        return DB::connection($this->connection)->table('loan_payment_schedules')
            ->where('loan_id', $loan->id)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    protected function items(Loan $loan): Collection
    {
        if (! $this->hasTable('loan_items')) {
            return collect();
        }

        return DB::connection($this->connection)->table('loan_items')
            ->where('loan_id', $loan->id)
            ->orderBy('id')
            ->get();
    }

    protected function scheduleBalance($schedule): float
    {
        $balance = $this->moneyValue($schedule, ['balance_amount', 'amount_balance']);
        if ($balance > 0) {
            return $balance;
        }

        return max(0, $this->moneyValue($schedule, ['schedule_amount', 'amount_due']) - $this->moneyValue($schedule, ['paid_amount', 'amount_paid']));
    }

    protected function moneyValue($source, array $fields): float
    {
        foreach ($fields as $field) {
            if (isset($source->{$field}) && $source->{$field} !== '') {
                return (float) $source->{$field};
            }
        }

        return 0.0;
    }

    protected function badge(string $status): array
    {
        $badges = [
            'current' => ['label' => 'Current', 'color' => 'green'],
            'due_today' => ['label' => 'Due Today', 'color' => 'yellow'],
            'overdue' => ['label' => 'Overdue', 'color' => 'red'],
            'partial_paid' => ['label' => 'Partial Paid', 'color' => 'orange'],
            'closed' => ['label' => 'Closed', 'color' => 'gray'],
        ];

        return [
            'status' => $status,
            'label' => $badges[$status]['label'] ?? 'Current',
            'color' => $badges[$status]['color'] ?? 'green',
        ];
    }

    protected function hasTable(string $table): bool
    {
        return Schema::connection($this->connection)->hasTable($table);
    }
}
