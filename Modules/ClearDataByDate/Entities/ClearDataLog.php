<?php

namespace Modules\ClearDataByDate\Entities;

use Illuminate\Database\Eloquent\Model;

class ClearDataLog extends Model
{
    protected $table = 'clear_data_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'selected_modules' => 'array',
        'preview_counts' => 'array',
        'total_deleted' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
    ];
}

