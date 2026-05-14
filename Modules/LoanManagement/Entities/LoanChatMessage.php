<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanChatMessage extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_chat_messages';

    protected $fillable = [
        'thread_id', 'sender_type', 'sender_id', 'sender_name_snapshot', 'message', 'message_type',
        'file_id', 'file_url', 'file_name', 'file_mime', 'file_size',
        'audio_duration_seconds',
        'latitude', 'longitude', 'location_address',
        'is_read', 'read_at', 'metadata', 'local_uuid',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
        'audio_duration_seconds' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function thread()
    {
        return $this->belongsTo(LoanChatThread::class, 'thread_id');
    }

    public function file()
    {
        return $this->belongsTo(LoanFile::class, 'file_id');
    }
}
