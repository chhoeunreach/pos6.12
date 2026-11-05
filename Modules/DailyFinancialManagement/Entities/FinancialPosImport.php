<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPosImport extends Model
{
    protected $table = 'financial_pos_imports';

    protected $fillable = [
        'business_id',
        'source_module',
        'sync_type',
        'date_from',
        'date_to',
        'records_processed',
        'records_imported',
        'records_skipped',
        'records_failed',
        'status',
        'error_message',
        'executed_by',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}

