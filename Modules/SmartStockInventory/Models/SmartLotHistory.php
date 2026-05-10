<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartLotHistory extends Model
{
    protected $table = 'smart_lot_histories';
    protected $guarded = ['id'];
    public $timestamps = false;
}
