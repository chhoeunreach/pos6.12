<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartImeiHistory extends Model
{
    protected $table = 'smart_imei_histories';
    protected $guarded = ['id'];
    public $timestamps = false;
}
