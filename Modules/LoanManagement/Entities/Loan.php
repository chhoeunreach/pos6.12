<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loans';

    protected $fillable = [
        'loan_number',
        'customer_id',
        'business_location_id',
        'business_location_name_snapshot',
        'staff_id',
        'staff_name_snapshot',
        'collector_id',
        'collector_name_snapshot',
        'source_type',
        'source_transaction_id',
        'source_invoice_no',
        'source_created_at',
        'stock_already_deducted',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'invoice_number_snapshot',
        'product_name_snapshot',
        'imei_snapshot',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'paid_amount',
        'penalty_amount',
        'discount_amount',
        'balance_amount',
        'down_payment',
        'installment_count',
        'payment_frequency',
        'loan_date',
        'first_due_date',
        'maturity_date',
        'status',
        'approved_at',
        'approved_by',
        'note',
        'meta_json',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'installment_count' => 'integer',
        'loan_date' => 'date',
        'first_due_date' => 'date',
        'maturity_date' => 'date',
        'approved_at' => 'datetime',
        'source_created_at' => 'datetime',
        'stock_already_deducted' => 'boolean',
        'meta_json' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LoanCustomer::class, 'customer_id');
    }

    public function businessLocation(): BelongsTo
    {
        return $this->belongsTo(LoanBusinessLocation::class, 'business_location_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanPaymentSchedule::class, 'loan_id')->orderBy('installment_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'loan_id')->orderByDesc('paid_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class, 'loan_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'approved']);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'pending']);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('status', ['completed', 'closed']);
    }
}
