<?php

namespace Modules\Accessory\Entities;

use Illuminate\Database\Eloquent\Model;

class AccessoryPurchase extends Model
{
    protected $connection = 'mysql';

    protected $table = 'accessory_purchases';

    protected $guarded = ['id'];

    protected $casts = [
        'transaction_date' => 'datetime',
        'total_cost' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(AccessoryPurchaseItem::class, 'accessory_purchase_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Contact::class, 'supplier_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'deleted');
    }
}
