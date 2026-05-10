<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartInventoryRecount extends Model
{
    protected $table = 'smart_inventory_recounts';
    protected $guarded = ['id'];
    protected $casts = ['recount_date' => 'datetime'];
}