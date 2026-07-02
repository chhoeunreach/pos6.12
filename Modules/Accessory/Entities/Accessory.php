<?php

namespace Modules\Accessory\Entities;

use Illuminate\Database\Eloquent\Model;

class Accessory extends Model
{
    protected $connection = 'mysql';

    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    protected $table = 'accessories';

    public function getImageUrlAttribute()
    {
        if (!empty($this->image)) {
            if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                return $this->image;
            }
            return url('uploads/img/' . $this->image);
        }
        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }
}
