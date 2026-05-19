<?php

namespace Modules\LoanManagement\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerLoanSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->int('id'),
            'loan_number' => $this->string('loan_number'),
            'product_name' => $this->string('product_name'),
            'product_price' => $this->money('product_price'),
            'imei_or_serial' => $this->string('imei_or_serial'),
            'total_paid_amount' => $this->money('total_paid_amount'),
            'remaining_balance' => $this->money('remaining_balance'),
            'monthly_payment_amount' => $this->money('monthly_payment_amount'),
            'monthly_principal' => $this->money('monthly_principal'),
            'monthly_interest' => $this->money('monthly_interest'),
            'payoff_this_month_amount' => $this->money('payoff_this_month_amount'),
            'payoff_by_full_schedule_amount' => $this->money('payoff_by_full_schedule_amount'),
            'total_loan_amount' => $this->money('total_loan_amount'),
            'total_interest' => $this->money('total_interest'),
            'paid_principal' => $this->money('paid_principal'),
            'paid_interest' => $this->money('paid_interest'),
            'remaining_principal' => $this->money('remaining_principal'),
            'remaining_months' => $this->int('remaining_months'),
            'total_installment_months' => $this->int('total_installment_months'),
            'paid_month_count' => $this->int('paid_month_count'),
            'loan_status' => $this->string('loan_status'),
            'loan_status_label' => $this->string('loan_status_label'),
            'loan_status_color' => $this->string('loan_status_color'),
            'currency' => $this->string('currency', 'USD'),
        ];
    }

    protected function money(string $key): string
    {
        return number_format((float) ($this->resource[$key] ?? 0), 2, '.', '');
    }

    protected function int(string $key): int
    {
        return (int) ($this->resource[$key] ?? 0);
    }

    protected function string(string $key, string $default = ''): string
    {
        $value = $this->resource[$key] ?? $default;

        return $value === null ? $default : (string) $value;
    }

    protected function bool(string $key): bool
    {
        return (bool) ($this->resource[$key] ?? false);
    }
}
