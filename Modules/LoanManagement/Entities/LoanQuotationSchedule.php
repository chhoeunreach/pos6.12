<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanQuotationSchedule extends BaseLoanModel
{
    protected $table = 'loan_quotation_schedules';

    protected $fillable = [
        'quotation_id',
        'installment_no',
        'due_date',
        'principal_amount',
        'interest_amount',
        'schedule_amount',
        'balance_amount',
    ];

    protected $casts = [
        'due_date' => 'date',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'schedule_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(LoanQuotation::class, 'quotation_id');
    }
}
