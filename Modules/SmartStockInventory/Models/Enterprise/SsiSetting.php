<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;

class SsiSetting extends Model
{
    protected $table = 'ssi_settings';
    protected $guarded = ['id'];
    protected $casts = [
        'blind_count_default' => 'boolean',
        'require_recount_for_mismatch' => 'boolean',
        'auto_create_investigations' => 'boolean',
        'auto_stock_adjustment' => 'boolean',
        'recount_threshold' => 'decimal:4',
        'approval_levels' => 'array',
        'scanner_settings' => 'array',
        'report_settings' => 'array',
    ];
}
