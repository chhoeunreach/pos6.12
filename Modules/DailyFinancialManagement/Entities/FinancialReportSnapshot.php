<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\BusinessLocation;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReportSnapshot extends Model
{
    protected $table = 'financial_report_snapshots';

    protected $fillable = [
        'business_id',
        'business_location_id',
        'report_type',
        'period_start',
        'period_end',
        'metrics_json',
        'generated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metrics_json' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}

