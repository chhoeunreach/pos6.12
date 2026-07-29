<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;

class SsiAuditScan extends Model
{
    protected $table = 'ssi_audit_scans';
    protected $guarded = ['id'];
    protected $casts = [
        'quantity' => 'decimal:4',
        'scanned_at' => 'datetime',
        'metadata' => 'array',
    ];
}
