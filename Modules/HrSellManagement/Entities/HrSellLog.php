<?php

namespace Modules\HrSellManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class HrSellLog extends Model
{
    protected $table = 'hr_sell_logs';
    protected $guarded = ['id'];
}
