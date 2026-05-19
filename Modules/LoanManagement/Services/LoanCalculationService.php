<?php

namespace Modules\LoanManagement\Services;

use Modules\LoanManagement\Entities\Loan;

class LoanCalculationService
{
    protected $customerLoanSummaryService;

    public function __construct(CustomerLoanSummaryService $customerLoanSummaryService)
    {
        $this->customerLoanSummaryService = $customerLoanSummaryService;
    }

    public function summary(Loan $loan): array
    {
        return $this->customerLoanSummaryService->buildLoanSummary($loan);
    }

    public function monthlyPrincipal(Loan $loan): float
    {
        return $this->customerLoanSummaryService->calculateMonthlyPrincipal($loan);
    }

    public function monthlyPayment(Loan $loan): float
    {
        return $this->customerLoanSummaryService->calculateMonthlyPayment($loan);
    }

    public function payoffThisMonth(Loan $loan): float
    {
        return $this->customerLoanSummaryService->calculatePayoffThisMonth($loan);
    }

    public function payoffByFullSchedule(Loan $loan): float
    {
        return $this->customerLoanSummaryService->calculatePayoffByFullSchedule($loan);
    }

    public function paidPrincipal(Loan $loan): float
    {
        return $this->customerLoanSummaryService->calculatePaidPrincipal($loan);
    }

    public function paidInterest(Loan $loan): float
    {
        return $this->customerLoanSummaryService->calculatePaidInterest($loan);
    }

    public function remainingMonths(Loan $loan): int
    {
        return $this->customerLoanSummaryService->calculateRemainingMonths($loan);
    }

    public function statusBadge(Loan $loan): array
    {
        return $this->customerLoanSummaryService->resolveStatusBadge($loan);
    }

    public function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
