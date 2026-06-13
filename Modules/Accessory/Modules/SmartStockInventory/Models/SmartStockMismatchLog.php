<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartStockMismatchLog extends Model
{
    protected $table = 'smart_stock_mismatch_logs';
    protected $guarded = ['id'];
}
