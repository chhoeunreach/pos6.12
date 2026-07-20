<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanTelegramChatThread extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_telegram_chat_threads';

    protected $fillable = [
        'customer_id', 'status', 'last_message', 'last_message_type', 'last_message_at',
        'unread_staff_count', 'unread_customer_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_staff_count' => 'integer',
        'unread_customer_count' => 'integer',
    ];

    public function messages()
    {
        return $this->hasMany(LoanTelegramChatMessage::class, 'thread_id');
    }

    public function customer()
    {
        return $this->belongsTo(LoanCustomer::class, 'customer_id');
    }
}
