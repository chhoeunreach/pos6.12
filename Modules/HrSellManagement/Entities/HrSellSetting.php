<?php

namespace Modules\HrSellManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class HrSellSetting extends Model
{
    protected $table = 'hr_sell_settings';
    protected $guarded = ['id'];
    protected $casts = [
        'require_approval' => 'boolean',
        'approval_levels' => 'array',
    ];
}
