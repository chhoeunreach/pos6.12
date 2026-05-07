<?php

namespace Modules\FixStockMismatch\Entities;

use Illuminate\Database\Eloquent\Model;

class StockMismatchFixLog extends Model
{
    protected $table = 'stock_mismatch_fix_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'old_qty' => 'float',
        'new_qty' => 'float',
        'difference' => 'float',
    ];
}

