<?php

namespace Modules\LoanManagement\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\LoanManagement\Entities\LoanChatMessage;

class LoanChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public LoanChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('loan-chat.thread.'.$this->message->thread_id)];
        $thread = $this->message->thread;
        if ($thread && $thread->customer_id) {
            $channels[] = new PrivateChannel('loan-chat.customer.'.$thread->customer_id);
        }
        if ($thread && $thread->staff_id) {
            $channels[] = new PrivateChannel('loan-chat.staff.'.$thread->staff_id);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'loan.chat.message.sent';
    }
}

