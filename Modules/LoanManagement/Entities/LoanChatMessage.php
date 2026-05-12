<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanChatMessage extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_chat_messages';

    protected $fillable = [
        'thread_id', 'sender_type', 'sender_id', 'sender_name_snapshot', 'message', 'message_type',
        'file_id', 'latitude', 'longitude', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function thread()
    {
        return $this->belongsTo(LoanChatThread::class, 'thread_id');
    }
}

