<?php

namespace Modules\NotificationCenter\Entities;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $table = 'notification_center_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForModule($query, $moduleType)
    {
        return $query->where('module_type', $moduleType);
    }
}
