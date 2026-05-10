<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartStockInventoryLine extends Model
{
    use SoftDeletes;

    protected $table = 'smart_stock_inventory_lines';
    protected $guarded = ['id'];
}
