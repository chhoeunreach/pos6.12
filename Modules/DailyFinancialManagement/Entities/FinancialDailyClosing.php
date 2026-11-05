<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\BusinessLocation;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialDailyClosing extends Model
{
    protected $table = 'financial_daily_closings';

    protected $fillable = [
        'business_id',
        'business_location_id',
        'closing_date',
        'closing_status',
        'total_pos_sales',
        'total_pos_returns',
        'total_net_sales',
        'total_cogs',
        'total_gross_profit',
        'total_manual_income',
        'total_loan_interest',
        'total_operating_expenses',
        'net_profit',
        'opening_cash_balance',
        'total_cash_inflow',
        'total_cash_outflow',
        'expected_cash_balance',
        'actual_cash_counted',
        'cash_difference',
        'expected_bank_balance',
        'actual_bank_balance',
        'bank_difference',
        'closing_notes',
        'reconciliation_notes',
        'closed_by',
        'approved_by',
        'approved_at',
        'locked_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_pos_sales' => 'decimal:4',
        'total_pos_returns' => 'decimal:4',
        'total_net_sales' => 'decimal:4',
        'total_cogs' => 'decimal:4',
        'total_gross_profit' => 'decimal:4',
        'total_manual_income' => 'decimal:4',
        'total_loan_interest' => 'decimal:4',
        'total_operating_expenses' => 'decimal:4',
        'net_profit' => 'decimal:4',
        'opening_cash_balance' => 'decimal:4',
        'total_cash_inflow' => 'decimal:4',
        'total_cash_outflow' => 'decimal:4',
        'expected_cash_balance' => 'decimal:4',
        'actual_cash_counted' => 'decimal:4',
        'cash_difference' => 'decimal:4',
        'expected_bank_balance' => 'decimal:4',
        'actual_bank_balance' => 'decimal:4',
        'bank_difference' => 'decimal:4',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'daily_closing_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'daily_closing_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isLocked(): bool
    {
        return $this->closing_status === 'locked' || ! empty($this->locked_at);
    }
}

