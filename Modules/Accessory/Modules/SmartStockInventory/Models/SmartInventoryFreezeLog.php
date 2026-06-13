<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartInventoryFreezeLog extends Model
{
    protected $table = 'smart_inventory_freeze_logs';
    protected $guarded = ['id'];
    protected $casts = ['released_at' => 'datetime'];
}