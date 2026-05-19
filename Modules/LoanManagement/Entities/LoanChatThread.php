<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanChatThread extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_chat_threads';

    protected $fillable = [
        'thread_number', 'customer_id', 'staff_id', 'assigned_staff_id', 'assigned_team', 'loan_id',
        'subject', 'display_name', 'display_subtitle', 'type', 'status', 'priority',
        'avatar_url', 'is_group', 'is_closed', 'is_pinned', 'is_muted',
        'last_message', 'last_message_type', 'last_message_at', 'unread_customer_count', 'unread_staff_count',
        'last_seen_customer_at', 'last_seen_staff_at', 'typing_customer_at', 'typing_staff_at',
        'last_sender_type', 'last_sender_name', 'closed_at', 'closed_by', 'closed_reason',
        'created_by_type', 'created_by_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'unread_customer_count' => 'integer',
        'unread_staff_count' => 'integer',
        'is_pinned' => 'boolean',
        'is_muted' => 'boolean',
        'is_group' => 'boolean',
        'is_closed' => 'boolean',
        'last_seen_customer_at' => 'datetime',
        'last_seen_staff_at' => 'datetime',
        'typing_customer_at' => 'datetime',
        'typing_staff_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(LoanChatMessage::class, 'thread_id');
    }

    public function participants()
    {
        return $this->hasMany(LoanChatParticipant::class, 'thread_id');
    }

    public function customer()
    {
        return $this->belongsTo(LoanCustomer::class, 'customer_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}
