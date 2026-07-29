<?php

namespace Modules\HrSellManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class HrSellApproval extends Model
{
    protected $table = 'hr_sell_approvals';
    protected $guarded = ['id'];
    protected $casts = ['approved_at' => 'datetime'];
}
