<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanChatMessage;
use Modules\LoanManagement\Entities\LoanChatParticipant;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Events\LoanChatMessageSent;
use Modules\LoanManagement\Events\LoanChatThreadAssigned;
use Modules\LoanManagement\Events\LoanChatThreadClosed;

class LoanChatService
{
    public function createThread(array $data): LoanChatThread
    {
        return DB::connection('mysql_loan')->transaction(function () use ($data) {
            $thread = LoanChatThread::query()->create([
                'thread_number' => $this->generateThreadNumber(),
                'customer_id' => $data['customer_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'loan_id' => $data['loan_id'] ?? null,
                'subject' => $data['subject'] ?? null,
                'type' => $data['type'] ?? 'customer_staff',
                'status' => $data['status'] ?? 'open',
                'priority' => $data['priority'] ?? 'normal',
                'created_by_type' => $data['created_by_type'],
                'created_by_id' => $data['created_by_id'],
            ]);
            foreach (($data['participants'] ?? []) as $p) {
                $this->addParticipant($thread, $p['type'], (int) $p['id'], $p['name'] ?? null);
            }
            return $thread;
        });
    }

    public function addParticipant($thread, $type, $id, ?string $name = null): LoanChatParticipant
    {
        return LoanChatParticipant::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'participant_type' => $type, 'participant_id' => $id],
            ['participant_name_snapshot' => $name, 'joined_at' => now()]
        );
    }

    public function sendMessage($thread, $senderType, $senderId, array $data): LoanChatMessage
    {
        $message = DB::connection('mysql_loan')->transaction(function () use ($thread, $senderType, $senderId, $data) {
            $msg = LoanChatMessage::query()->create([
                'thread_id' => $thread->id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'sender_name_snapshot' => $data['sender_name_snapshot'] ?? null,
                'message' => $data['message'] ?? null,
                'message_type' => $data['message_type'] ?? 'text',
                'file_id' => $data['file_id'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);
            $thread->last_message_at = now();
            $thread->status = in_array($thread->status, ['closed'], true) ? 'open' : $thread->status;
            $thread->save();
            return $msg;
        });
        $this->broadcastMessage($message);
        $this->notifyParticipants($message);
        return $message;
    }

    public function markAsRead($thread, $participantType, $participantId): void
    {
        LoanChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('participant_type', $participantType)
            ->where('participant_id', $participantId)
            ->update(['last_read_at' => now(), 'updated_at' => now()]);

        LoanChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('sender_type', '!=', $participantType)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => now(), 'updated_at' => now()]);
    }

    public function assignStaff($thread, $staffId): LoanChatThread
    {
        $thread->staff_id = $staffId;
        $thread->save();
        $this->addParticipant($thread, 'staff', (int) $staffId);
        event(new LoanChatThreadAssigned($thread));
        return $thread;
    }

    public function closeThread($thread, $closedBy): LoanChatThread
    {
        $thread->status = 'closed';
        $thread->closed_at = now();
        $thread->closed_by = $closedBy;
        $thread->save();
        event(new LoanChatThreadClosed($thread));
        return $thread;
    }

    public function reopenThread($thread): LoanChatThread
    {
        $thread->status = 'open';
        $thread->closed_at = null;
        $thread->closed_by = null;
        $thread->save();
        return $thread;
    }

    public function getCustomerThreads($customerId)
    {
        return LoanChatThread::query()->where('customer_id', $customerId)->orderByDesc('last_message_at')->orderByDesc('id')->get();
    }

    public function getStaffThreads($staffId)
    {
        return LoanChatThread::query()->where(function ($q) use ($staffId) {
            $q->where('staff_id', $staffId)
                ->orWhereExists(function ($sub) use ($staffId) {
                    $sub->select(DB::raw(1))
                        ->from('loan_chat_participants as p')
                        ->whereColumn('p.thread_id', 'loan_chat_threads.id')
                        ->where('p.participant_type', 'staff')
                        ->where('p.participant_id', $staffId);
                });
        })->orderByDesc('last_message_at')->orderByDesc('id')->get();
    }

    public function broadcastMessage($message): void
    {
        event(new LoanChatMessageSent($message->load('thread')));
    }

    protected function notifyParticipants(LoanChatMessage $message): void
    {
        $thread = $message->thread;
        if (! $thread) return;

        if (DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_telegram_notifications')) {
            DB::connection('mysql_loan')->table('loan_telegram_notifications')->insert([
                'customer_id' => $thread->customer_id,
                'loan_id' => $thread->loan_id,
                'chat_id' => config('services.telegram.chat_id', ''),
                'message' => 'New chat message on '.$thread->thread_number,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function generateThreadNumber(): string
    {
        do {
            $n = 'CHAT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            $exists = LoanChatThread::query()->where('thread_number', $n)->exists();
        } while ($exists);
        return $n;
    }
}
