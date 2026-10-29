<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanQuotation extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_quotations';

    protected $fillable = [
        'quotation_no',
        'customer_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'customer_address_snapshot',
        'business_location_id',
        'location_name_snapshot',
        'quotation_date',
        'valid_until',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'down_payment',
        'loan_amount',
        'interest_rate',
        'interest_type',
        'duration_months',
        'payment_frequency',
        'first_due_date',
        'installment_amount',
        'total_interest',
        'total_payable',
        'terms',
        'note',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'converted_at',
        'converted_loan_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'loan_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'first_due_date' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LoanCustomer::class, 'customer_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LoanBusinessLocation::class, 'business_location_id');
    }

    public function convertedLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'converted_loan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanQuotationItem::class, 'quotation_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanQuotationSchedule::class, 'quotation_id')->orderBy('installment_no');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LoanUser::class, 'created_by');
    }
}
