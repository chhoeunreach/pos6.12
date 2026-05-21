<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImportHistory extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'type',
        'filename',
        'stored_path',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'status',
        'progress',
        'queue_batch_id',
        'duplicate_mode',
        'metadata',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function failures()
    {
        return $this->hasMany(ImportFailure::class);
    }
}
