<?php

namespace Modules\HrSellManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class HrSellNote extends Model
{
    protected $table = 'hr_sell_notes';
    protected $guarded = ['id'];
    protected $casts = ['next_follow_up_date' => 'date'];
}
