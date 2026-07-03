<?php

namespace Modules\Accessory\Entities;

use Illuminate\Database\Eloquent\Model;

class AccessoryPurchaseItem extends Model
{
    protected $connection = 'mysql';

    protected $table = 'accessory_purchase_items';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'subtotal' => 'float',
    ];

    public function purchase()
    {
        return $this->belongsTo(AccessoryPurchase::class, 'accessory_purchase_id');
    }

    public function accessory()
    {
        return $this->belongsTo(Accessory::class, 'accessory_id');
    }
}
