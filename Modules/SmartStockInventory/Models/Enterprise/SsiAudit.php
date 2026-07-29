<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SsiAudit extends Model
{
    use SoftDeletes;

    protected $table = 'ssi_audits';
    protected $guarded = ['id'];
    protected $casts = [
        'scope' => 'array',
        'settings' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SsiAuditItem::class, 'audit_id');
    }

    public function scans()
    {
        return $this->hasMany(SsiAuditScan::class, 'audit_id');
    }

    public function approvals()
    {
        return $this->hasMany(SsiApproval::class, 'audit_id');
    }

    public function investigations()
    {
        return $this->hasMany(SsiInvestigation::class, 'audit_id');
    }
}
