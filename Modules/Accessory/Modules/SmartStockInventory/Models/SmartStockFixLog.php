<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartStockFixLog extends Model
{
    use SoftDeletes;

    protected $table = 'smart_stock_fix_logs';
    protected $guarded = ['id'];
}
