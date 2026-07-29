<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SsiAuditItem extends Model
{
    use SoftDeletes;

    protected $table = 'ssi_audit_items';
    protected $guarded = ['id'];
    protected $casts = [
        'expected_qty' => 'decimal:4',
        'counted_qty' => 'decimal:4',
        'difference_qty' => 'decimal:4',
        'counted_at' => 'datetime',
        'verified_at' => 'datetime',
        'recount_required' => 'boolean',
    ];

    public function audit()
    {
        return $this->belongsTo(SsiAudit::class, 'audit_id');
    }

    public function scans()
    {
        return $this->hasMany(SsiAuditScan::class, 'audit_item_id');
    }
}
