<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImportFailure extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_history_id',
        'row_number',
        'raw_data',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'created_at' => 'datetime',
    ];
}
