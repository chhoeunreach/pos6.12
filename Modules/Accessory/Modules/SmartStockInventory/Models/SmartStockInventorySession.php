<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartStockInventorySession extends Model
{
    use SoftDeletes;

    protected $table = 'smart_stock_inventory_sessions';
    protected $guarded = ['id'];
    protected $casts = ['completed_at' => 'datetime'];
}
