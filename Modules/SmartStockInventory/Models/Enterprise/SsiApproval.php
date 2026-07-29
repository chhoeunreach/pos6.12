<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;

class SsiApproval extends Model
{
    protected $table = 'ssi_approvals';
    protected $guarded = ['id'];
    protected $casts = [
        'approved_at' => 'datetime',
    ];
}
