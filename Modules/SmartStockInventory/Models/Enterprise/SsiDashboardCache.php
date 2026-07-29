<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;

class SsiDashboardCache extends Model
{
    protected $table = 'ssi_dashboard_cache';
    protected $guarded = ['id'];
    protected $casts = [
        'payload' => 'array',
        'computed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
