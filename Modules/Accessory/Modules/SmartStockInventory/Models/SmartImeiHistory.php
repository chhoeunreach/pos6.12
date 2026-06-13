<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartImeiHistory extends Model
{
    use SoftDeletes;

    protected $table = 'smart_imei_histories';
    protected $guarded = ['id'];
    public $timestamps = false;
}
