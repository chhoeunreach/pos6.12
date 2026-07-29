<?php

namespace Modules\SmartStockInventory\Models\Enterprise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SsiInvestigation extends Model
{
    use SoftDeletes;

    protected $table = 'ssi_investigations';
    protected $guarded = ['id'];
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'attachments' => 'array',
        'payload' => 'array',
    ];
}
