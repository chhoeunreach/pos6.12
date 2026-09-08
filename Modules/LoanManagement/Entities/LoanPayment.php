<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPayment extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_payments';

    protected $fillable = [
        'payment_ref_no',
        'loan_id',
        'customer_id',
        'schedule_id',
        'received_by',
        'received_by_name_snapshot',
        'channel',
        'payment_type',
        'amount',
        'penalty_amount',
        'discount_amount',
        'paid_at',
        'status',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LoanCustomer::class, 'customer_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanPaymentSchedule::class, 'schedule_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(LoanPaymentDetail::class, 'payment_id');
    }
}
