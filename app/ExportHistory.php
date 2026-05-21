<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExportHistory extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'type',
        'filters',
        'format',
        'filename',
        'status',
        'progress',
        'total_rows',
        'processed_rows',
        'path',
        'error_message',
        'download_expires_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'download_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
