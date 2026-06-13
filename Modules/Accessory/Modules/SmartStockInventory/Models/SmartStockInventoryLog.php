<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartStockInventoryLog extends Model
{
    protected $table = 'smart_stock_inventory_logs';
    protected $guarded = ['id'];
    public $timestamps = false;
}
