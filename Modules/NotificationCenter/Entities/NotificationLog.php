<?php

namespace Modules\NotificationCenter\Entities;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForModule($query, $moduleType)
    {
        return $query->where('module_type', $moduleType);
    }

    public function scopeForReference($query, $referenceType, $referenceId)
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }

    public function group()
    {
        return $this->belongsTo(NotificationGroup::class, 'group_id');
    }
}
