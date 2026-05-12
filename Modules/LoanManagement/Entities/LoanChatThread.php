<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanChatThread extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_chat_threads';

    protected $fillable = [
        'thread_number', 'customer_id', 'staff_id', 'loan_id', 'subject', 'type', 'status', 'priority',
        'last_message_at', 'closed_at', 'closed_by', 'created_by_type', 'created_by_id',
    ];

    public function messages()
    {
        return $this->hasMany(LoanChatMessage::class, 'thread_id');
    }

    public function participants()
    {
        return $this->hasMany(LoanChatParticipant::class, 'thread_id');
    }
}

