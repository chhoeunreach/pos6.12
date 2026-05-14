<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanChatMessage;
use Modules\LoanManagement\Entities\LoanChatParticipant;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Entities\LoanFile;
use Modules\LoanManagement\Events\LoanChatMessageSent;
use Modules\LoanManagement\Events\LoanChatThreadAssigned;
use Modules\LoanManagement\Events\LoanChatThreadClosed;

class LoanChatService
{
    public const STAFF_SIDE_TYPES = ['staff', 'admin'];

    public function listCustomerThreads(?int $customerId = null): Collection
    {
        $customerId = $customerId ?? (int) (auth(config('loanmanagement.customer_api_guard', 'customer_loan_api'))->id() ?? 0);
        return LoanChatThread::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    public function listStaffThreads(?int $staffId = null, bool $admin = false): Collection
    {
        $staffId = $staffId ?? (int) (auth()->id() ?? 0);

        $q = LoanChatThread::query();
        if (! $admin) {
            $q->where('staff_id', $staffId);
        }

        return $q->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

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
                'unread_customer_count' => 0,
                'unread_staff_count' => 0,
                'created_by_type' => $data['created_by_type'],
                'created_by_id' => $data['created_by_id'],
            ]);
            foreach (($data['participants'] ?? []) as $p) {
                $this->addParticipant($thread, $p['type'], (int) $p['id'], $p['name'] ?? null);
            }

            if (! empty($thread->customer_id)) {
                $this->addParticipant($thread, 'customer', (int) $thread->customer_id);
            }
            if (! empty($thread->staff_id)) {
                $this->addParticipant($thread, 'staff', (int) $thread->staff_id);
            }

            return $thread;
        });
    }

    public function showThread(LoanChatThread|int $thread, bool $withMessages = true): LoanChatThread
    {
        $row = $thread instanceof LoanChatThread ? $thread : LoanChatThread::query()->findOrFail($thread);
        $row->load(['participants']);
        if ($withMessages) {
            $row->setRelation('messages', $row->messages()->orderBy('created_at')->orderBy('id')->get());
        }
        return $row;
    }

    public function addParticipant($thread, $type, $id, ?string $name = null): LoanChatParticipant
    {
        return LoanChatParticipant::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'participant_type' => $type, 'participant_id' => $id],
            ['participant_name_snapshot' => $name, 'joined_at' => now()]
        );
    }

    public function sendTextMessage(LoanChatThread $thread, string $senderType, int $senderId, string $message, array $metadata = []): LoanChatMessage
    {
        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'text',
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    public function sendLocationMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        float $latitude,
        float $longitude,
        ?string $address = null,
        array $metadata = []
    ): LoanChatMessage {
        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_address' => $address,
            'metadata' => $metadata,
        ]);
    }

    public function sendFileMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        UploadedFile $file,
        string $messageType,
        ?string $message = null,
        array $metadata = []
    ): LoanChatMessage {
        $loanFile = $this->storeLoanFile($file, 'chat_'.$messageType, $senderId);

        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => $messageType,
            'message' => $message,
            'file_id' => $loanFile->id,
            'file_url' => $this->safeFileUrl($loanFile),
            'file_name' => $loanFile->original_name ?? null,
            'file_mime' => $loanFile->mime_type ?? null,
            'file_size' => (int) ($loanFile->size_bytes ?? 0) ?: null,
            'metadata' => $metadata,
        ]);
    }

    public function sendAudioMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        UploadedFile $file,
        ?int $durationSeconds = null,
        ?string $message = null,
        array $metadata = []
    ): LoanChatMessage {
        $loanFile = $this->storeLoanFile($file, 'chat_audio', $senderId);

        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'audio',
            'message' => $message,
            'file_id' => $loanFile->id,
            'file_url' => $this->safeFileUrl($loanFile),
            'file_name' => $loanFile->original_name ?? null,
            'file_mime' => $loanFile->mime_type ?? null,
            'file_size' => (int) ($loanFile->size_bytes ?? 0) ?: null,
            'audio_duration_seconds' => $durationSeconds,
            'metadata' => $metadata,
        ]);
    }

    public function sendMessage(LoanChatThread $thread, string $senderType, int $senderId, array $data): LoanChatMessage
    {
        return $this->persistMessage($thread, $senderType, $senderId, $data);
    }

    public function markAsRead($thread, $participantType, $participantId): void
    {
        LoanChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('participant_type', $participantType)
            ->where('participant_id', $participantId)
            ->update(['last_read_at' => now(), 'updated_at' => now()]);

        $q = LoanChatMessage::query()->where('thread_id', $thread->id)->where('is_read', 0);
        if ($participantType === 'customer') {
            $q->where('sender_type', '!=', 'customer');
        } else {
            $q->where('sender_type', '=', 'customer');
        }
        $q->update(['is_read' => 1, 'read_at' => now(), 'updated_at' => now()]);

        $this->recalculateUnreadCounters($thread);
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
        return $this->listCustomerThreads((int) $customerId);
    }

    public function getStaffThreads($staffId)
    {
        return $this->listStaffThreads((int) $staffId, false);
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

    protected function persistMessage(LoanChatThread $thread, string $senderType, int $senderId, array $data): LoanChatMessage
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
                'file_url' => $data['file_url'] ?? null,
                'file_name' => $data['file_name'] ?? null,
                'file_mime' => $data['file_mime'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'audio_duration_seconds' => $data['audio_duration_seconds'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'location_address' => $data['location_address'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $thread->last_message_at = now();
            $thread->last_message = $this->threadLastMessageSnapshot($msg);
            $thread->last_message_type = $msg->message_type;
            $thread->status = in_array($thread->status, ['closed'], true) ? 'open' : $thread->status;

            if ($senderType === 'customer') {
                $thread->unread_staff_count = (int) ($thread->unread_staff_count ?? 0) + 1;
            } else {
                $thread->unread_customer_count = (int) ($thread->unread_customer_count ?? 0) + 1;
            }

            $thread->save();
            return $msg;
        });

        $this->broadcastMessage($message);
        $this->notifyParticipants($message);
        return $message;
    }

    protected function threadLastMessageSnapshot(LoanChatMessage $message): ?string
    {
        return match ($message->message_type) {
            'text' => (string) ($message->message ?? ''),
            'location' => $message->location_address ? 'Location: '.$message->location_address : 'Location shared',
            'image' => $message->file_name ? 'Image: '.$message->file_name : 'Image',
            'file' => $message->file_name ? 'File: '.$message->file_name : 'File',
            'audio' => $message->file_name ? 'Audio: '.$message->file_name : 'Audio',
            default => $message->message ? (string) $message->message : strtoupper((string) $message->message_type),
        };
    }

    protected function recalculateUnreadCounters(LoanChatThread $thread): void
    {
        $unreadFromCustomer = LoanChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('sender_type', 'customer')
            ->where('is_read', 0)
            ->count();
        $unreadFromStaff = LoanChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('sender_type', '!=', 'customer')
            ->where('is_read', 0)
            ->count();

        $thread->unread_staff_count = (int) $unreadFromCustomer;
        $thread->unread_customer_count = (int) $unreadFromStaff;
        $thread->save();
    }

    protected function storeLoanFile(UploadedFile $file, string $category, ?int $uploadedBy = null): LoanFile
    {
        $disk = 'public';
        $path = $file->store('loan-management/chat/'.date('Y/m'), $disk);

        $payload = [
            'fileable_type' => 'loan_chat',
            'fileable_id' => 0,
            'category' => $category,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = Schema::connection('mysql_loan')->hasTable('loan_files')
            ? Schema::connection('mysql_loan')->getColumnListing('loan_files')
            : [];
        $safe = array_intersect_key($payload, array_flip($columns));

        $id = DB::connection('mysql_loan')->table('loan_files')->insertGetId($safe);

        return LoanFile::query()->findOrFail($id);
    }

    protected function safeFileUrl(LoanFile $file): ?string
    {
        $disk = $file->disk ?? 'public';
        $path = $file->path ?? null;
        if (! $path) return null;
        return Storage::disk($disk)->url($path);
    }
}
