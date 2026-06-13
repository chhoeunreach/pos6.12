<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartLotHistory extends Model
{
    use SoftDeletes;

    protected $table = 'smart_lot_histories';
    protected $guarded = ['id'];
    public $timestamps = false;
}
