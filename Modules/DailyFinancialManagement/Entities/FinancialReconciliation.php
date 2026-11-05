<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReconciliation extends Model
{
    protected $table = 'financial_reconciliations';

    protected $fillable = [
        'daily_closing_id',
        'financial_account_id',
        'reconciliation_date',
        'system_balance',
        'physical_balance',
        'difference',
        'denominations_json',
        'notes',
        'reconciled_by',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'system_balance' => 'decimal:4',
        'physical_balance' => 'decimal:4',
        'difference' => 'decimal:4',
        'denominations_json' => 'array',
    ];

    public function dailyClosing(): BelongsTo
    {
        return $this->belongsTo(FinancialDailyClosing::class, 'daily_closing_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}

