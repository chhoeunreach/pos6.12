<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAccountMovement extends Model
{
    protected $table = 'financial_account_movements';

    protected $fillable = [
        'business_id',
        'financial_account_id',
        'financial_transaction_id',
        'movement_type',
        'movement_date',
        'amount',
        'balance_before',
        'balance_after',
        'related_account_id',
        'ref_no',
        'source_type',
        'source_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function relatedAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'related_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

