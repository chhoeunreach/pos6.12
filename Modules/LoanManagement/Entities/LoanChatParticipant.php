<?php

namespace Modules\LoanManagement\Entities;

class LoanChatParticipant extends BaseLoanModel
{
    protected $table = 'loan_chat_participants';

    protected $fillable = [
        'thread_id', 'participant_type', 'participant_id', 'participant_name_snapshot',
        'participant_avatar_snapshot', 'last_read_at', 'joined_at', 'left_at',
        'is_active', 'is_owner', 'unread_count',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_active' => 'boolean',
        'is_owner' => 'boolean',
        'unread_count' => 'integer',
    ];

    public function thread()
    {
        return $this->belongsTo(LoanChatThread::class, 'thread_id');
    }
}
