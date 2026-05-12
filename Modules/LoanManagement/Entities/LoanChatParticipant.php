<?php

namespace Modules\LoanManagement\Entities;

class LoanChatParticipant extends BaseLoanModel
{
    protected $table = 'loan_chat_participants';

    protected $fillable = [
        'thread_id', 'participant_type', 'participant_id', 'participant_name_snapshot',
        'last_read_at', 'joined_at', 'left_at',
    ];

    public function thread()
    {
        return $this->belongsTo(LoanChatThread::class, 'thread_id');
    }
}

