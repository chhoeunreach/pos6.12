<?php

namespace Modules\NotificationCenter\Entities;

use Illuminate\Database\Eloquent\Model;

class NotificationGroup extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'send_text' => 'boolean',
        'send_pdf' => 'boolean',
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

    public function scopeForLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeForDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    public function scopeForLocationName($query, string $locationName)
    {
        return $query->where(function ($q) use ($locationName) {
            $q->where('location_name', $locationName)
              ->orWhere('location_name', 'like', '%'.$locationName.'%')
              ->orWhere('name', 'like', '%'.$locationName.'%');
        });
    }
}
