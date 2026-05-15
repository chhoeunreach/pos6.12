<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Entities\LoanCustomer;
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

    public static function hasThreadColumn($column): bool
    {
        return Schema::connection('mysql_loan')->hasColumn('loan_chat_threads', $column);
    }

    public static function hasMessageColumn($column): bool
    {
        return Schema::connection('mysql_loan')->hasColumn('loan_chat_messages', $column);
    }

    public function listCustomerThreads(?int $customerId = null): Collection
    {
        $customerId = $customerId ?? (int) (auth(config('loanmanagement.customer_api_guard', 'customer_loan_api'))->id() ?? 0);
        $query = LoanChatThread::query()
            ->where('customer_id', $customerId)
            ->with(['customer', 'loan']);

        $this->applyThreadListOrdering($query);

        return $query->get();
    }

    public function listStaffThreads(?int $staffId = null, bool $admin = false): Collection
    {
        $staffId = $staffId ?? (int) (auth()->id() ?? 0);

        $q = LoanChatThread::query();
        if (! $admin) {
            $q->where('staff_id', $staffId);
        }

        $q->with(['customer', 'loan']);
        $this->applyThreadListOrdering($q);

        return $q->get();
    }

    public function createThread(array $data): LoanChatThread
    {
        return DB::connection('mysql_loan')->transaction(function () use ($data) {
            $payload = [
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
            ];

            if (self::hasThreadColumn('avatar_url')) {
                $payload['avatar_url'] = $data['avatar_url'] ?? null;
            }
            if (self::hasThreadColumn('is_pinned')) {
                $payload['is_pinned'] = (bool) ($data['is_pinned'] ?? false);
            }
            if (self::hasThreadColumn('is_muted')) {
                $payload['is_muted'] = (bool) ($data['is_muted'] ?? false);
            }

            $thread = LoanChatThread::query()->create($payload);
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
        $row->load(['participants', 'customer', 'loan']);
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

    public function sendTextMessage(LoanChatThread $thread, string $senderType, int $senderId, string $message, array $metadata = [], ?string $localUuid = null): LoanChatMessage
    {
        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'text',
            'message' => $message,
            'metadata' => $metadata,
            'local_uuid' => $localUuid,
        ]);
    }

    public function sendImageMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        UploadedFile $file,
        ?string $message = null,
        array $metadata = [],
        ?string $localUuid = null
    ): LoanChatMessage {
        return $this->sendFileMessage($thread, $senderType, $senderId, $file, 'image', $message, $metadata, $localUuid);
    }

    public function sendLocationMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        float $latitude,
        float $longitude,
        ?string $address = null,
        array $metadata = [],
        ?string $localUuid = null
    ): LoanChatMessage {
        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_address' => $address,
            'metadata' => $metadata,
            'local_uuid' => $localUuid,
        ]);
    }

    public function sendFileMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        UploadedFile $file,
        string $messageType,
        ?string $message = null,
        array $metadata = [],
        ?string $localUuid = null
    ): LoanChatMessage {
        if ($localUuid && self::hasMessageColumn('local_uuid') && ($existing = $this->findExistingLocalMessage($thread, $senderType, $senderId, $localUuid))) {
            return $existing;
        }

        $loanFile = $this->storeLoanFile($file, 'chat_'.$messageType, $senderId);

        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => $messageType,
            'message' => $message,
            'file_id' => $loanFile->id,
            'file_url' => $this->safeFileUrl($loanFile),
            'file_name' => $loanFile->original_name ?? null,
            'file_mime' => $loanFile->mime_type ?? null,
            'file_size' => (int) ($loanFile->size_bytes ?? $loanFile->size ?? 0) ?: null,
            'metadata' => $metadata,
            'local_uuid' => $localUuid,
        ]);
    }

    public function sendAudioMessage(
        LoanChatThread $thread,
        string $senderType,
        int $senderId,
        UploadedFile $file,
        ?int $durationSeconds = null,
        ?string $message = null,
        array $metadata = [],
        ?string $localUuid = null
    ): LoanChatMessage {
        if ($localUuid && self::hasMessageColumn('local_uuid') && ($existing = $this->findExistingLocalMessage($thread, $senderType, $senderId, $localUuid))) {
            return $existing;
        }

        $loanFile = $this->storeLoanFile($file, 'chat_audio', $senderId);
        $audioMetadata = array_merge($metadata, [
            'file_type' => 'chat_audio',
            'extension' => strtolower((string) ($loanFile->extension ?? $file->getClientOriginalExtension())),
            'duration_seconds' => $durationSeconds,
        ]);

        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'audio',
            'message' => $message ?? '',
            'file_id' => $loanFile->id,
            'file_url' => $this->safeFileUrl($loanFile),
            'file_name' => $loanFile->original_name ?? null,
            'file_mime' => $loanFile->mime_type ?? null,
            'file_size' => (int) ($loanFile->size_bytes ?? $loanFile->size ?? 0) ?: null,
            'audio_duration_seconds' => $durationSeconds,
            'metadata' => $audioMetadata,
            'local_uuid' => $localUuid,
        ]);
    }

    public function sendMessage(LoanChatThread $thread, string $senderType, int $senderId, array $data): LoanChatMessage
    {
        return $this->persistMessage($thread, $senderType, $senderId, $data);
    }

    public function markSeen(LoanChatThread $thread, string $viewerType): LoanChatThread
    {
        $column = $viewerType === 'customer' ? 'last_seen_customer_at' : 'last_seen_staff_at';
        if (! self::hasThreadColumn($column)) {
            return $thread;
        }

        $thread->{$column} = now();
        $thread->save();

        return $thread;
    }

    public function markTyping(LoanChatThread $thread, string $viewerType): LoanChatThread
    {
        $column = $viewerType === 'customer' ? 'typing_customer_at' : 'typing_staff_at';
        if (self::hasThreadColumn($column)) {
            $thread->{$column} = now();
            $thread->save();
        }

        $this->markSeen($thread, $viewerType);

        return $thread;
    }

    public function markDelivered($message): LoanChatMessage
    {
        $message = $message instanceof LoanChatMessage ? $message : LoanChatMessage::query()->findOrFail($message);
        if (! self::hasMessageColumn('delivered_at')) {
            return $message;
        }

        if (empty($message->delivered_at)) {
            $message->delivered_at = now();
            $message->save();
        }

        return $message;
    }

    public function markAsRead($thread, $participantType, $participantId): void
    {
        $this->markRead($thread, $participantType);

        LoanChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('participant_type', $participantType)
            ->where('participant_id', $participantId)
            ->update(['last_read_at' => now(), 'updated_at' => now()]);
    }

    public function markRead($thread, string $viewerType): void
    {
        $thread = $thread instanceof LoanChatThread ? $thread : LoanChatThread::query()->findOrFail($thread);
        $now = now();
        $readerType = $viewerType === 'customer' ? 'customer' : 'staff';

        $this->markSeen($thread, $readerType);

        $q = LoanChatMessage::query()->where('thread_id', $thread->id);
        if ($readerType === 'customer') {
            $q->where('sender_type', '!=', 'customer');
            if (self::hasMessageColumn('read_by_customer_at')) {
                $q->whereNull('read_by_customer_at');
            }

            $payload = [
                'is_read' => 1,
                'read_at' => $now,
                'updated_at' => $now,
            ];
            if (self::hasMessageColumn('read_by_customer_at')) {
                $payload['read_by_customer_at'] = $now;
            }
            $q->update($payload);
        } else {
            $q->where('sender_type', '=', 'customer');
            if (self::hasMessageColumn('read_by_staff_at')) {
                $q->whereNull('read_by_staff_at');
            }

            $payload = [
                'is_read' => 1,
                'read_at' => $now,
                'updated_at' => $now,
            ];
            if (self::hasMessageColumn('read_by_staff_at')) {
                $payload['read_by_staff_at'] = $now;
            }
            $q->update($payload);
        }

        $this->resetUnreadCount($thread, $readerType);
    }

    public function updateLastMessage(LoanChatThread $thread, LoanChatMessage $message): LoanChatThread
    {
        $thread->last_message_at = $message->created_at ?? now();
        $thread->last_message = $this->threadLastMessageSnapshot($message);
        $thread->last_message_type = $message->message_type;
        $thread->save();

        return $thread;
    }

    public function increaseUnreadCount(LoanChatThread $thread, string $senderType): LoanChatThread
    {
        if ($senderType === 'customer') {
            $thread->unread_staff_count = (int) ($thread->unread_staff_count ?? 0) + 1;
        } else {
            $thread->unread_customer_count = (int) ($thread->unread_customer_count ?? 0) + 1;
        }

        $thread->save();

        return $thread;
    }

    public function resetUnreadCount(LoanChatThread $thread, string $readerType): LoanChatThread
    {
        if ($readerType === 'customer') {
            $thread->unread_customer_count = 0;
        } else {
            $thread->unread_staff_count = 0;
        }

        $thread->save();

        return $thread;
    }

    public function assignStaff($thread, $staffId): LoanChatThread
    {
        $thread->staff_id = $staffId;
        $thread->save();
        $this->addParticipant($thread, 'staff', (int) $staffId);
        $this->broadcastChatEvent(new LoanChatThreadAssigned($thread), 'Chat thread assignment broadcast failed');
        return $thread;
    }

    public function closeThread($thread, $closedBy): LoanChatThread
    {
        $thread->status = 'closed';
        $thread->closed_at = now();
        $thread->closed_by = $closedBy;
        $thread->save();
        $this->broadcastChatEvent(new LoanChatThreadClosed($thread), 'Chat thread close broadcast failed');
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

    public function canDeleteThread(LoanChatThread $thread): bool
    {
        return $thread->messages()->count() === 0;
    }

    public function deleteEmptyThread(LoanChatThread $thread): void
    {
        if (! $this->canDeleteThread($thread)) {
            throw ValidationException::withMessages([
                'chat' => 'This chat already has messages and cannot be deleted. You can close it instead.',
            ]);
        }

        DB::connection('mysql_loan')->transaction(function () use ($thread) {
            $thread->participants()->delete();
            $thread->delete();
        });
    }

    public function getCustomerThreads($customerId)
    {
        return $this->listCustomerThreads((int) $customerId);
    }

    public function getStaffThreads($staffId)
    {
        return $this->listStaffThreads((int) $staffId, false);
    }

    public function getDisplayName($thread, $viewerType): string
    {
        $thread = $thread instanceof LoanChatThread ? $thread : LoanChatThread::query()->findOrFail($thread);

        if ($viewerType === 'customer') {
            $staff = $this->staffUser($thread);
            $name = $staff ? trim((string) (($staff->first_name ?? '').' '.($staff->last_name ?? ''))) : '';
            return $name ?: (string) ($staff->username ?? $staff->name ?? $thread->subject ?? 'Support');
        }

        $customer = $this->threadCustomer($thread);
        return (string) ($customer->name ?? $thread->subject ?? $thread->thread_number ?? 'Customer');
    }

    public function getAvatarUrl($thread, $viewerType): string
    {
        $thread = $thread instanceof LoanChatThread ? $thread : LoanChatThread::query()->findOrFail($thread);
        if (! empty($thread->avatar_url)) {
            return (string) $thread->avatar_url;
        }

        if ($viewerType !== 'customer') {
            $customer = $this->threadCustomer($thread);
            if ($customer && ! empty($customer->customer_photo_file_id)) {
                $file = LoanFile::query()->find($customer->customer_photo_file_id);
                return $file ? (string) ($this->safeFileUrl($file) ?? '') : '';
            }
        }

        return '';
    }

    public function getUnreadCount($thread, $viewerType): int
    {
        $thread = $thread instanceof LoanChatThread ? $thread : LoanChatThread::query()->findOrFail($thread);
        return $viewerType === 'customer'
            ? (int) ($thread->unread_customer_count ?? 0)
            : (int) ($thread->unread_staff_count ?? 0);
    }

    public function formatMessengerThread($thread, $viewerType): array
    {
        $thread = $thread instanceof LoanChatThread ? $thread : LoanChatThread::query()->findOrFail($thread);
        if (! $thread->relationLoaded('customer')) {
            $thread->loadMissing(['customer', 'loan']);
        }

        $oppositeSeenAt = $viewerType === 'customer'
            ? (self::hasThreadColumn('last_seen_staff_at') ? $thread->last_seen_staff_at : null)
            : (self::hasThreadColumn('last_seen_customer_at') ? $thread->last_seen_customer_at : null);
        $oppositeTypingAt = $viewerType === 'customer'
            ? (self::hasThreadColumn('typing_staff_at') ? $thread->typing_staff_at : null)
            : (self::hasThreadColumn('typing_customer_at') ? $thread->typing_customer_at : null);
        $customer = $this->threadCustomer($thread);
        $loanNumber = (string) ($thread->loan->loan_number ?? '');
        $phone = (string) ($customer->phone ?? $customer->login_phone ?? '');

        return [
            'id' => (int) $thread->id,
            'thread_number' => (string) ($thread->thread_number ?? ''),
            'display_name' => $this->getDisplayName($thread, $viewerType),
            'display_subtitle' => trim($phone.($phone && $loanNumber ? ' • ' : '').$loanNumber),
            'avatar_url' => $this->getAvatarUrl($thread, $viewerType),
            'status' => (string) ($thread->status ?? 'open'),
            'is_online' => $this->timestampWithin($oppositeSeenAt, 120),
            'is_pinned' => self::hasThreadColumn('is_pinned') ? (bool) ($thread->is_pinned ?? false) : false,
            'is_muted' => self::hasThreadColumn('is_muted') ? (bool) ($thread->is_muted ?? false) : false,
            'last_message' => $thread->last_message === null ? '' : (string) $thread->last_message,
            'last_message_type' => (string) ($thread->last_message_type ?? 'text'),
            'last_message_at' => $this->formatDate($thread->last_message_at),
            'unread_count' => $this->getUnreadCount($thread, $viewerType),
            'typing' => $this->timestampWithin($oppositeTypingAt, 10),
        ];
    }

    public function formatMessengerMessage($message, $viewerType, $viewerId): array
    {
        $message = $message instanceof LoanChatMessage ? $message : LoanChatMessage::query()->findOrFail($message);
        $isOwn = $this->isOwnMessage($message, $viewerType, (int) $viewerId);
        $file = null;

        if (! empty($message->file_id)) {
            $file = [
                'id' => (int) $message->file_id,
                'file_id' => (int) $message->file_id,
                'url' => (string) ($message->file_url ?? ''),
                'name' => (string) ($message->file_name ?? ''),
                'mime' => (string) ($message->file_mime ?? ''),
                'size' => (int) ($message->file_size ?? 0),
                'extension' => pathinfo((string) ($message->file_name ?? ''), PATHINFO_EXTENSION),
            ];
        }

        $readAt = $viewerType === 'customer'
            ? (self::hasMessageColumn('read_by_staff_at') ? $message->read_by_staff_at : $message->read_at)
            : (self::hasMessageColumn('read_by_customer_at') ? $message->read_by_customer_at : $message->read_at);
        if (! $isOwn) {
            $readAt = $viewerType === 'customer'
                ? (self::hasMessageColumn('read_by_customer_at') ? $message->read_by_customer_at : $message->read_at)
                : (self::hasMessageColumn('read_by_staff_at') ? $message->read_by_staff_at : $message->read_at);
        }

        return [
            'id' => (int) $message->id,
            'thread_id' => (int) $message->thread_id,
            'sender_type' => (string) ($message->sender_type ?? ''),
            'sender_id' => (int) ($message->sender_id ?? 0),
            'sender_name' => $this->messageSenderName($message),
            'sender_avatar_url' => $this->messageSenderAvatarUrl($message),
            'message' => (string) ($message->message ?? ''),
            'message_type' => (string) ($message->message_type ?? 'text'),
            'file' => $file ?? (object) [],
            'location' => $message->latitude === null || $message->longitude === null ? (object) [] : [
                'latitude' => (float) $message->latitude,
                'longitude' => (float) $message->longitude,
                'address' => $message->location_address === null ? null : (string) $message->location_address,
            ],
            'audio_duration_seconds' => (int) ($message->audio_duration_seconds ?? 0),
            'delivered_at' => $this->formatDate(self::hasMessageColumn('delivered_at') ? $message->delivered_at : null),
            'read_at' => $this->formatDate($readAt),
            'reaction' => self::hasMessageColumn('reaction') && $message->reaction !== null ? (string) $message->reaction : null,
            'reply_to_message_id' => self::hasMessageColumn('reply_to_message_id') && $message->reply_to_message_id !== null ? (int) $message->reply_to_message_id : null,
            'is_own' => $isOwn,
            'created_at' => $this->formatDate($message->created_at),
        ];
    }

    public function broadcastMessage($message): void
    {
        $this->broadcastChatEvent(
            new LoanChatMessageSent($message->load('thread')),
            'Chat broadcast failed'
        );
    }

    protected function broadcastChatEvent(object $event, string $warningMessage): void
    {
        if (! $this->chatBroadcastingEnabled()) {
            return;
        }

        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            Log::warning($warningMessage, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function chatBroadcastingEnabled(): bool
    {
        return (bool) (
            config('loan_management.chat.broadcasting_enabled')
            ?? config('loanmanagement.chat.broadcasting_enabled')
            ?? false
        );
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

    protected function applyThreadListOrdering($query): void
    {
        if (self::hasThreadColumn('is_pinned')) {
            $query->orderByDesc('is_pinned');
        }

        if (self::hasThreadColumn('last_message_at')) {
            $query->orderByDesc('last_message_at');
        }

        $query->orderByDesc('id');
    }

    protected function generateThreadNumber(): string
    {
        $next = ((int) (LoanChatThread::query()->max('id') ?? 0)) + 1;
        do {
            $n = 'CHT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $exists = LoanChatThread::query()->where('thread_number', $n)->exists();
            $next++;
        } while ($exists);

        return $n;
    }

    protected function persistMessage(LoanChatThread $thread, string $senderType, int $senderId, array $data): LoanChatMessage
    {
        $this->ensureSenderCanAccessThread($thread, $senderType, $senderId);

        if (! empty($data['local_uuid']) && self::hasMessageColumn('local_uuid')) {
            $existing = $this->findExistingLocalMessage($thread, $senderType, $senderId, $data['local_uuid']);

            if ($existing) {
                return $existing;
            }
        }

        $message = DB::connection('mysql_loan')->transaction(function () use ($thread, $senderType, $senderId, $data) {
            $payload = [
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
            ];

            if (self::hasMessageColumn('local_uuid')) {
                $payload['local_uuid'] = $data['local_uuid'] ?? null;
            }

            if (self::hasMessageColumn('delivered_at')) {
                $payload['delivered_at'] = $data['delivered_at'] ?? now();
            }
            if (self::hasMessageColumn('reaction')) {
                $payload['reaction'] = $data['reaction'] ?? null;
            }
            if (self::hasMessageColumn('reply_to_message_id')) {
                $payload['reply_to_message_id'] = $data['reply_to_message_id'] ?? null;
            }

            $msg = LoanChatMessage::query()->create($payload);

            $thread->status = in_array($thread->status, ['closed'], true) ? 'open' : $thread->status;
            if ($senderType === 'customer' && self::hasThreadColumn('last_seen_customer_at')) {
                $thread->last_seen_customer_at = now();
            } elseif ($senderType !== 'customer' && self::hasThreadColumn('last_seen_staff_at')) {
                $thread->last_seen_staff_at = now();
            }
            $thread->save();
            $this->updateLastMessage($thread, $msg);
            $this->increaseUnreadCount($thread, $senderType);

            if (! empty($msg->file_id)) {
                DB::connection('mysql_loan')->table('loan_files')->where('id', $msg->file_id)->update([
                    'fileable_id' => $msg->id,
                    'updated_at' => now(),
                ]);
            }

            return $msg;
        });

        $this->broadcastMessage($message);
        $this->notifyParticipants($message);
        return $message;
    }

    protected function findExistingLocalMessage(LoanChatThread $thread, string $senderType, int $senderId, string $localUuid): ?LoanChatMessage
    {
        if (! self::hasMessageColumn('local_uuid')) {
            return null;
        }

        return LoanChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('sender_type', $senderType)
            ->where('sender_id', $senderId)
            ->where('local_uuid', $localUuid)
            ->first();
    }

    protected function threadLastMessageSnapshot(LoanChatMessage $message): ?string
    {
        return match ($message->message_type) {
            'text' => (string) ($message->message ?? ''),
            'location' => $message->location_address ? 'Location: '.$message->location_address : 'Location shared',
            'image' => $message->file_name ? 'Image: '.$message->file_name : 'Image',
            'file' => $message->file_name ? 'File: '.$message->file_name : 'File',
            'audio' => 'Voice message',
            default => $message->message ? (string) $message->message : strtoupper((string) $message->message_type),
        };
    }

    protected function ensureSenderCanAccessThread(LoanChatThread $thread, string $senderType, int $senderId): void
    {
        if ($senderType === 'admin') {
            return;
        }

        if ($senderType === 'customer' && (int) ($thread->customer_id ?? 0) === $senderId) {
            return;
        }

        if ($senderType === 'staff' && (int) ($thread->staff_id ?? 0) === $senderId) {
            return;
        }

        throw new AuthorizationException('You are not allowed to send messages in this chat thread.');
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

    protected function threadCustomer(LoanChatThread $thread): ?LoanCustomer
    {
        if ($thread->relationLoaded('customer')) {
            return $thread->customer;
        }

        return $thread->customer_id ? LoanCustomer::query()->find($thread->customer_id) : null;
    }

    protected function staffUser(LoanChatThread $thread)
    {
        return $thread->staff_id && class_exists(\App\User::class)
            ? \App\User::query()->find($thread->staff_id)
            : null;
    }

    protected function timestampWithin($value, int $seconds): bool
    {
        if (empty($value)) {
            return false;
        }

        try {
            return Carbon::parse($value)->greaterThanOrEqualTo(now()->subSeconds($seconds));
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    protected function isOwnMessage(LoanChatMessage $message, string $viewerType, int $viewerId): bool
    {
        if ($viewerType === 'customer') {
            return $message->sender_type === 'customer' && (int) $message->sender_id === $viewerId;
        }

        return in_array($message->sender_type, self::STAFF_SIDE_TYPES, true)
            && (int) $message->sender_id === $viewerId;
    }

    protected function messageSenderName(LoanChatMessage $message): string
    {
        if (! empty($message->sender_name_snapshot)) {
            return (string) $message->sender_name_snapshot;
        }

        if ($message->sender_type === 'customer') {
            $customer = LoanCustomer::query()->find($message->sender_id);
            return (string) ($customer->name ?? 'Customer');
        }

        if (class_exists(\App\User::class)) {
            $user = \App\User::query()->find($message->sender_id);
            if ($user) {
                $name = trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));
                return $name ?: (string) ($user->username ?? $user->name ?? 'Staff');
            }
        }

        return $message->sender_type === 'admin' ? 'Admin' : 'Staff';
    }

    protected function messageSenderAvatarUrl(LoanChatMessage $message): string
    {
        if ($message->sender_type !== 'customer') {
            return '';
        }

        $customer = LoanCustomer::query()->find($message->sender_id);
        if (! $customer || empty($customer->customer_photo_file_id)) {
            return '';
        }

        $file = LoanFile::query()->find($customer->customer_photo_file_id);
        return $file ? (string) ($this->safeFileUrl($file) ?? '') : '';
    }

    protected function storeLoanFile(UploadedFile $file, string $category, ?int $uploadedBy = null): LoanFile
    {
        return app(LoanChatUploadService::class)->storeChatFile($file, $category, $uploadedBy);
    }

    protected function safeFileUrl(LoanFile $file): ?string
    {
        return app(LoanChatUploadService::class)->url($file);
    }
}
