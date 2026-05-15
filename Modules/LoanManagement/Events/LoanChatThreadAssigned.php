<?php

namespace Modules\LoanManagement\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\LoanManagement\Entities\LoanChatThread;

class LoanChatThreadAssigned implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public LoanChatThread $thread)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('loan-chat.thread.'.$this->thread->id)];
        if ($this->thread->staff_id) {
            $channels[] = new PrivateChannel('loan-chat.staff.'.$this->thread->staff_id);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'loan.chat.thread.assigned';
    }

    public function broadcastWhen(): bool
    {
        return (bool) (
            config('loan_management.chat.broadcasting_enabled')
            ?? config('loanmanagement.chat.broadcasting_enabled')
            ?? false
        );
    }
}
