<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\BusinessLocation;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'financial_transactions';

    protected $fillable = [
        'business_id',
        'business_location_id',
        'department_id',
        'category_id',
        'financial_account_id',
        'transaction_type',
        'transaction_date',
        'ref_no',
        'source_type',
        'source_id',
        'description',
        'amount',
        'cogs_amount',
        'currency',
        'exchange_rate',
        'amount_in_base_currency',
        'payment_method',
        'approval_status',
        'is_operating',
        'is_locked',
        'daily_closing_id',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'reversal_of_id',
        'meta_json',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:4',
        'cogs_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:4',
        'amount_in_base_currency' => 'decimal:4',
        'is_operating' => 'boolean',
        'is_locked' => 'boolean',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function dailyClosing(): BelongsTo
    {
        return $this->belongsTo(FinancialDailyClosing::class, 'daily_closing_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialTransactionAttachment::class, 'financial_transaction_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(FinancialApproval::class, 'financial_transaction_id')->orderBy('created_at', 'desc');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinancialAccountMovement::class, 'financial_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'reversal_of_id');
    }
}

