<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\BusinessLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialApprovalSetting extends Model
{
    protected $table = 'financial_approval_settings';

    protected $fillable = [
        'business_id',
        'business_location_id',
        'cashier_limit',
        'branch_manager_limit',
        'accountant_limit',
        'general_manager_limit',
        'require_accountant_review',
        'require_manager_approval',
        'auto_approve_pos_sync',
    ];

    protected $casts = [
        'cashier_limit' => 'decimal:4',
        'branch_manager_limit' => 'decimal:4',
        'accountant_limit' => 'decimal:4',
        'general_manager_limit' => 'decimal:4',
        'require_accountant_review' => 'boolean',
        'require_manager_approval' => 'boolean',
        'auto_approve_pos_sync' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }
}

