<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartStockActionLog extends Model
{
    use SoftDeletes;

    protected $table = 'smart_stock_action_logs';
    protected $guarded = ['id'];
}
