<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\Loan;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Entities\LoanItem;
use Modules\LoanManagement\Entities\LoanPaymentSchedule;
use Modules\LoanManagement\Entities\LoanQuotation;
use Modules\LoanManagement\Entities\LoanQuotationItem;
use Modules\LoanManagement\Entities\LoanQuotationSchedule;

class LoanQuotationService
{
    protected string $conn = 'mysql_loan';

    public function generateQuotationNumber(?int $locationId = null): string
    {
        $prefix = 'QTN-' . date('Ym') . '-';
        $last = DB::connection($this->conn)->table('loan_quotations')
            ->where('quotation_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('quotation_no');

        if ($last) {
            $num = (int) substr($last, strlen($prefix));
            $next = str_pad($num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $next = '0001';
        }

        return $prefix . $next;
    }

    public function calculateSchedule(
        float $principal,
        float $interestRate,
        string $interestType,
        int $duration,
        string $frequency,
        string $firstDueDate
    ): array {
        $schedules = [];
        if ($principal <= 0 || $duration <= 0) {
            return $schedules;
        }

        $startDate = Carbon::parse($firstDueDate ?: now()->toDateString());
        $remainingBalance = $principal;
        $monthlyRate = ($interestRate / 100);

        if ($interestType === 'flat_rate' || $interestType === 'flat') {
            $totalInterest = $principal * $monthlyRate * $duration;
            $monthlyPrincipal = round($principal / $duration, 2);
            $monthlyInterest = round($totalInterest / $duration, 2);
            $monthlyInstallment = $monthlyPrincipal + $monthlyInterest;

            for ($i = 1; $i <= $duration; $i++) {
                $dueDate = clone $startDate;
                if ($frequency === 'daily') {
                    $dueDate->addDays($i - 1);
                } elseif ($frequency === 'weekly') {
                    $dueDate->addWeeks($i - 1);
                } else {
                    $dueDate->addMonthsNoOverflow($i - 1);
                }

                if ($i === $duration) {
                    $monthlyPrincipal = round($remainingBalance, 2);
                    $monthlyInstallment = $monthlyPrincipal + $monthlyInterest;
                    $remainingBalance = 0;
                } else {
                    $remainingBalance = max(0, $remainingBalance - $monthlyPrincipal);
                }

                $schedules[] = [
                    'installment_no' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'principal_amount' => $monthlyPrincipal,
                    'interest_amount' => $monthlyInterest,
                    'schedule_amount' => $monthlyInstallment,
                    'balance_amount' => round($remainingBalance, 2),
                ];
            }
        } else {
            // Declining balance / Equal Principal
            $monthlyPrincipal = round($principal / $duration, 2);

            for ($i = 1; $i <= $duration; $i++) {
                $dueDate = clone $startDate;
                if ($frequency === 'daily') {
                    $dueDate->addDays($i - 1);
                } elseif ($frequency === 'weekly') {
                    $dueDate->addWeeks($i - 1);
                } else {
                    $dueDate->addMonthsNoOverflow($i - 1);
                }

                $interest = round($remainingBalance * $monthlyRate, 2);
                if ($i === $duration) {
                    $monthlyPrincipal = round($remainingBalance, 2);
                    $installment = $monthlyPrincipal + $interest;
                    $remainingBalance = 0;
                } else {
                    $installment = $monthlyPrincipal + $interest;
                    $remainingBalance = max(0, $remainingBalance - $monthlyPrincipal);
                }

                $schedules[] = [
                    'installment_no' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'principal_amount' => $monthlyPrincipal,
                    'interest_amount' => $interest,
                    'schedule_amount' => $installment,
                    'balance_amount' => round($remainingBalance, 2),
                ];
            }
        }

        return $schedules;
    }

    public function create(array $data, ?int $userId = null): LoanQuotation
    {
        return DB::connection($this->conn)->transaction(function () use ($data, $userId) {
            $quotationNo = ! empty($data['quotation_no']) ? $data['quotation_no'] : $this->generateQuotationNumber($data['business_location_id'] ?? null);

            $quotation = LoanQuotation::create([
                'quotation_no' => $quotationNo,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name_snapshot' => $data['customer_name_snapshot'] ?? '',
                'customer_phone_snapshot' => $data['customer_phone_snapshot'] ?? '',
                'customer_address_snapshot' => $data['customer_address_snapshot'] ?? '',
                'business_location_id' => $data['business_location_id'] ?? 1,
                'location_name_snapshot' => $data['location_name_snapshot'] ?? '',
                'quotation_date' => $data['quotation_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(30)->toDateString(),
                'status' => $data['status'] ?? 'draft',
                'subtotal' => (float) ($data['subtotal'] ?? 0),
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'tax_amount' => (float) ($data['tax_amount'] ?? 0),
                'total_amount' => (float) ($data['total_amount'] ?? 0),
                'down_payment' => (float) ($data['down_payment'] ?? 0),
                'loan_amount' => (float) ($data['loan_amount'] ?? 0),
                'interest_rate' => (float) ($data['interest_rate'] ?? 0),
                'interest_type' => $data['interest_type'] ?? 'flat_rate',
                'duration_months' => (int) ($data['duration_months'] ?? 12),
                'payment_frequency' => $data['payment_frequency'] ?? 'monthly',
                'first_due_date' => $data['first_due_date'] ?? now()->addMonth()->toDateString(),
                'installment_amount' => (float) ($data['installment_amount'] ?? 0),
                'total_interest' => (float) ($data['total_interest'] ?? 0),
                'total_payable' => (float) ($data['total_payable'] ?? 0),
                'terms' => $data['terms'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $userId ?: auth()->id(),
            ]);

            // Save items
            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (empty($item['product_name'])) {
                        continue;
                    }
                    LoanQuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $item['product_id'] ?? null,
                        'product_name_snapshot' => $item['product_name'],
                        'sku_snapshot' => $item['sku'] ?? null,
                        'serial_number_snapshot' => $item['serial_number'] ?? null,
                        'description' => $item['description'] ?? null,
                        'photo_path' => $item['photo_path'] ?? null,
                        'quantity' => (float) ($item['quantity'] ?? 1),
                        'unit_price' => (float) ($item['unit_price'] ?? 0),
                        'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                        'line_total' => (float) ($item['line_total'] ?? 0),
                    ]);
                }
            }

            // Save schedules
            $schedules = $this->calculateSchedule(
                (float) $quotation->loan_amount,
                (float) $quotation->interest_rate,
                $quotation->interest_type,
                (int) $quotation->duration_months,
                $quotation->payment_frequency,
                $quotation->first_due_date ? $quotation->first_due_date->toDateString() : now()->toDateString()
            );

            foreach ($schedules as $sched) {
                LoanQuotationSchedule::create([
                    'quotation_id' => $quotation->id,
                    'installment_no' => $sched['installment_no'],
                    'due_date' => $sched['due_date'],
                    'principal_amount' => $sched['principal_amount'],
                    'interest_amount' => $sched['interest_amount'],
                    'schedule_amount' => $sched['schedule_amount'],
                    'balance_amount' => $sched['balance_amount'],
                ]);
            }

            return $quotation;
        });
    }

    public function update(LoanQuotation $quotation, array $data, ?int $userId = null): LoanQuotation
    {
        return DB::connection($this->conn)->transaction(function () use ($quotation, $data, $userId) {
            $quotation = LoanQuotation::lockForUpdate()->findOrFail($quotation->id);
            abort_if($quotation->status === 'converted' || $quotation->converted_loan_id, 409, 'Converted quotations cannot be edited.');

            // Rebuild the proposal and schedule atomically without changing its identity or status.
            $items = $data['items'];
            $subtotal = 0;
            foreach ($items as &$item) {
                $item['line_total'] = round(max(0, (float) $item['quantity'] * (float) $item['unit_price'] - (float) ($item['discount_amount'] ?? 0)), 2);
                $subtotal += $item['line_total'];
            }
            unset($item);
            $data['subtotal'] = $subtotal;
            $data['total_amount'] = round(max(0, $subtotal - (float) $quotation->discount_amount + (float) $quotation->tax_amount), 2);
            $schedules = $this->calculateSchedule(
                (float) $data['loan_amount'], (float) $data['interest_rate'], $data['interest_type'],
                (int) $data['duration_months'], $data['payment_frequency'], $data['first_due_date']
            );
            $data['total_interest'] = round(array_sum(array_column($schedules, 'interest_amount')), 2);
            $data['total_payable'] = round(array_sum(array_column($schedules, 'schedule_amount')), 2);
            $data['installment_amount'] = count($schedules) ? round($data['total_payable'] / count($schedules), 2) : 0;
            unset($data['items']);
            $quotation->fill($data);
            $quotation->updated_by = $userId ?: auth()->id();
            $quotation->save();
            $quotation->items()->delete();
            foreach ($items as $item) {
                $quotation->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'product_name_snapshot' => $item['product_name'],
                    'sku_snapshot' => $item['sku'] ?? null,
                    'serial_number_snapshot' => $item['serial_number'] ?? null,
                    'description' => $item['description'] ?? null,
                    'photo_path' => $item['photo_path'] ?? null,
                    'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0, 'line_total' => $item['line_total'],
                ]);
            }
            $quotation->schedules()->delete();
            $quotation->schedules()->createMany($schedules);

            return $quotation->fresh(['items', 'schedules']);
        });
    }

    public function convertToLoan(LoanQuotation $quotation, ?int $userId = null): Loan
    {
        if ($quotation->status === 'converted' && $quotation->converted_loan_id) {
            $existing = Loan::find($quotation->converted_loan_id);
            if ($existing) {
                return $existing;
            }
        }

        return DB::connection($this->conn)->transaction(function () use ($quotation, $userId) {
            $quotation = LoanQuotation::with(['items', 'schedules'])->lockForUpdate()->findOrFail($quotation->id);
            if ($quotation->converted_loan_id) {
                return Loan::findOrFail($quotation->converted_loan_id);
            }
            // Find or create customer
            $customerId = $quotation->customer_id;
            if (! $customerId && $quotation->customer_phone_snapshot) {
                $customer = LoanCustomer::firstOrCreate(
                    ['phone' => $quotation->customer_phone_snapshot],
                    [
                        'name' => $quotation->customer_name_snapshot ?: 'Customer ' . $quotation->customer_phone_snapshot,
                        'address' => $quotation->customer_address_snapshot,
                        'status' => 'active',
                        'created_by' => $userId ?: auth()->id(),
                    ]
                );
                $customerId = $customer->id;
            }

            // Generate unique loan number
            $loanNumber = 'LN-' . date('Ym') . '-' . str_pad(Loan::count() + 1, 4, '0', STR_PAD_LEFT);

            // Create Loan
            $loan = Loan::create([
                'loan_number' => $loanNumber,
                'customer_id' => $customerId,
                'business_location_id' => $quotation->business_location_id ?: 1,
                'business_location_name_snapshot' => $quotation->location_name_snapshot,
                'staff_id' => $userId ?: auth()->id(),
                'source_type' => 'quotation',
                'source_invoice_no' => $quotation->quotation_no,
                'customer_name_snapshot' => $quotation->customer_name_snapshot,
                'customer_phone_snapshot' => $quotation->customer_phone_snapshot,
                'principal_amount' => $quotation->loan_amount,
                'interest_amount' => $quotation->total_interest,
                'total_amount' => $quotation->total_payable,
                'paid_amount' => 0,
                'balance_amount' => $quotation->total_payable,
                'down_payment' => $quotation->down_payment,
                'installment_count' => $quotation->duration_months,
                'payment_frequency' => $quotation->payment_frequency,
                'loan_date' => now()->toDateString(),
                'first_due_date' => $quotation->first_due_date ? $quotation->first_due_date->toDateString() : now()->addMonth()->toDateString(),
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $userId ?: auth()->id(),
                'note' => 'Converted from Quotation ' . $quotation->quotation_no . '. ' . ($quotation->note ?? ''),
            ]);

            // Copy items to loan_items
            foreach ($quotation->items as $item) {
                if (Schema::connection($this->conn)->hasTable('loan_items')) {
                    LoanItem::create([
                        'loan_id' => $loan->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name_snapshot,
                        'product_name_snapshot' => $item->product_name_snapshot,
                        'sku' => $item->sku_snapshot,
                        'serial_number' => $item->serial_number_snapshot,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->line_total,
                    ]);
                }
            }

            // Copy schedules to loan_payment_schedules
            foreach ($quotation->schedules as $sched) {
                if (Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
                    LoanPaymentSchedule::create([
                        'loan_id' => $loan->id,
                        'installment_no' => $sched->installment_no,
                        'due_date' => $sched->due_date,
                        'schedule_amount' => $sched->schedule_amount,
                        'principal_amount' => $sched->principal_amount,
                        'principal_due' => $sched->principal_amount,
                        'interest_amount' => $sched->interest_amount,
                        'interest_due' => $sched->interest_amount,
                        'amount_due' => $sched->schedule_amount,
                        'paid_amount' => 0,
                        'amount_paid' => 0,
                        'balance_amount' => $sched->schedule_amount,
                        'amount_balance' => $sched->schedule_amount,
                        'status' => 'unpaid',
                    ]);
                }
            }

            // Mark quotation converted
            $quotation->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_loan_id' => $loan->id,
                'updated_by' => $userId ?: auth()->id(),
            ]);

            return $loan;
        });
    }

    public function duplicate(LoanQuotation $quotation, ?int $userId = null): LoanQuotation
    {
        return DB::connection($this->conn)->transaction(function () use ($quotation, $userId) {
            $newQuote = $quotation->replicate([
                'quotation_no',
                'status',
                'sent_at',
                'accepted_at',
                'rejected_at',
                'converted_at',
                'converted_loan_id',
            ]);

            $newQuote->quotation_no = $this->generateQuotationNumber($quotation->business_location_id);
            $newQuote->status = 'draft';
            $newQuote->quotation_date = now()->toDateString();
            $newQuote->valid_until = now()->addDays(30)->toDateString();
            $newQuote->created_by = $userId ?: auth()->id();
            $newQuote->save();

            foreach ($quotation->items as $item) {
                $newItem = $item->replicate();
                $newItem->quotation_id = $newQuote->id;
                $newItem->save();
            }

            foreach ($quotation->schedules as $sched) {
                $newSched = $sched->replicate();
                $newSched->quotation_id = $newQuote->id;
                $newSched->save();
            }

            return $newQuote;
        });
    }
}
