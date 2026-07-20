<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Entities\LoanTelegramChatMessage;
use Modules\LoanManagement\Entities\LoanTelegramChatThread;
use Modules\LoanManagement\Jobs\RelayChatMessageToTelegramJob;

/**
 * Storage + send/receive logic for the Telegram customer-chat bridge. Fully independent from
 * LoanChatService/loan_chat_threads (the staff's own internal Live Chat tool) - nothing here
 * ever reads from or writes to those tables.
 */
class TelegramChatService
{
    public function findOrCreateThread(int $customerId): LoanTelegramChatThread
    {
        $existing = LoanTelegramChatThread::query()
            ->where('customer_id', $customerId)
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return LoanTelegramChatThread::query()->create([
            'customer_id' => $customerId,
            'status' => 'open',
            'unread_staff_count' => 0,
            'unread_customer_count' => 0,
        ]);
    }

    public function sendTextMessage(LoanTelegramChatThread $thread, string $senderType, int $senderId, string $message): LoanTelegramChatMessage
    {
        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'text',
            'message' => $message,
        ]);
    }

    public function sendImageMessage(LoanTelegramChatThread $thread, string $senderType, int $senderId, UploadedFile $file, ?string $caption = null): LoanTelegramChatMessage
    {
        return $this->sendFileMessage($thread, $senderType, $senderId, $file, 'image', $caption);
    }

    public function sendFileMessage(LoanTelegramChatThread $thread, string $senderType, int $senderId, UploadedFile $file, string $messageType, ?string $caption = null): LoanTelegramChatMessage
    {
        $loanFile = app(LoanChatUploadService::class)->storeChatFile($file, 'telegram_'.$messageType, $senderId);
        $url = app(LoanChatUploadService::class)->url($loanFile);

        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => $messageType,
            'message' => $caption,
            'file_id' => $loanFile->id,
            'file_url' => $url,
            'file_name' => $loanFile->original_name ?? null,
            'file_mime' => $loanFile->mime_type ?? null,
            'file_size' => (int) ($loanFile->size_bytes ?? $loanFile->size ?? 0) ?: null,
        ]);
    }

    public function sendAudioMessage(LoanTelegramChatThread $thread, string $senderType, int $senderId, UploadedFile $file, ?int $durationSeconds = null, ?string $caption = null): LoanTelegramChatMessage
    {
        $loanFile = app(LoanChatUploadService::class)->storeChatFile($file, 'telegram_audio', $senderId);
        $url = app(LoanChatUploadService::class)->url($loanFile);

        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'audio',
            'message' => $caption,
            'file_id' => $loanFile->id,
            'file_url' => $url,
            'file_name' => $loanFile->original_name ?? null,
            'file_mime' => $loanFile->mime_type ?? null,
            'file_size' => (int) ($loanFile->size_bytes ?? $loanFile->size ?? 0) ?: null,
            'audio_duration_seconds' => $durationSeconds,
        ]);
    }

    public function sendLocationMessage(LoanTelegramChatThread $thread, string $senderType, int $senderId, float $latitude, float $longitude, ?string $address = null): LoanTelegramChatMessage
    {
        return $this->persistMessage($thread, $senderType, $senderId, [
            'message_type' => 'location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_address' => $address,
        ]);
    }

    public function markRead(LoanTelegramChatThread $thread, string $viewerType): void
    {
        $now = now();
        $query = LoanTelegramChatMessage::query()->where('thread_id', $thread->id)->where('is_read', false);

        if ($viewerType === 'customer') {
            $query->where('sender_type', '!=', 'customer');
        } else {
            $query->where('sender_type', 'customer');
        }
        $query->update(['is_read' => true, 'read_at' => $now, 'updated_at' => $now]);

        if ($viewerType === 'customer') {
            $thread->unread_customer_count = 0;
        } else {
            $thread->unread_staff_count = 0;
        }
        $thread->save();
    }

    /**
     * Contacts for the staff sidebar: existing threads (with last message/unread info) plus
     * customer-only rows for customers who don't have a thread yet. Optionally scoped to a
     * set of permitted loan_business_locations ids (null = unrestricted).
     */
    public function listContactsForStaff(string $search = '', ?array $locationIds = null): Collection
    {
        $threads = LoanTelegramChatThread::query()
            ->where('status', 'open')
            ->with('customer')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $existingCustomerIds = [];
        $rows = collect();

        foreach ($threads as $thread) {
            $customer = $thread->customer;
            if (! $customer) {
                continue;
            }
            if ($locationIds !== null && ! $this->customerWithinLocations($customer, $locationIds)) {
                continue;
            }
            if ($search !== '' && ! $this->customerMatchesSearch($customer, $search)) {
                continue;
            }

            $existingCustomerIds[(int) $customer->id] = true;
            $name = (string) ($customer->khmer_name ?? $customer->name ?? 'Customer');
            $rows->push([
                'id' => (int) $thread->id,
                'customer_id' => (int) $customer->id,
                'display_name' => $name,
                'customer_name' => $name,
                'customer_phone' => (string) ($customer->phone ?? $customer->login_phone ?? ''),
                'last_message' => (string) ($thread->last_message ?? ''),
                'last_message_type' => (string) ($thread->last_message_type ?? 'text'),
                'last_message_at' => $thread->last_message_at?->format('Y-m-d H:i:s'),
                'unread_count' => (int) ($thread->unread_staff_count ?? 0),
                'telegram_linked' => ! empty($customer->telegram_chat_id),
                'is_customer_only' => false,
            ]);
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_customers')) {
            return $rows;
        }

        $query = DB::connection('mysql_loan')->table('loan_customers');
        if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($locationIds !== null && Schema::connection('mysql_loan')->hasColumn('loan_customers', 'business_location_id')) {
            $query->where(function ($inner) use ($locationIds) {
                $inner->whereNull('business_location_id')->orWhereIn('business_location_id', $locationIds);
            });
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                foreach (['name', 'khmer_name', 'phone', 'login_phone', 'customer_code'] as $column) {
                    if (Schema::connection('mysql_loan')->hasColumn('loan_customers', $column)) {
                        $inner->orWhere($column, 'like', '%'.$search.'%');
                    }
                }
            });
        }

        $customers = $query->orderByDesc('id')->limit(300)->get();
        foreach ($customers as $customer) {
            if (isset($existingCustomerIds[(int) $customer->id])) {
                continue;
            }

            $name = (string) ($customer->khmer_name ?? $customer->name ?? 'Customer');
            $rows->push([
                'id' => null,
                'customer_id' => (int) $customer->id,
                'display_name' => $name,
                'customer_name' => $name,
                'customer_phone' => (string) ($customer->phone ?? $customer->login_phone ?? ''),
                'last_message' => '',
                'last_message_type' => 'text',
                'last_message_at' => null,
                'unread_count' => 0,
                'telegram_linked' => ! empty($customer->telegram_chat_id),
                'is_customer_only' => true,
            ]);
        }

        return $rows;
    }

    public function formatThread(LoanTelegramChatThread $thread): array
    {
        $thread->loadMissing(['customer', 'messages']);
        $customer = $thread->customer;

        return [
            'id' => (int) $thread->id,
            'customer_id' => (int) $thread->customer_id,
            'display_name' => (string) ($customer->khmer_name ?? $customer->name ?? 'Customer'),
            'status' => (string) $thread->status,
            'messages' => $thread->messages->map(fn ($m) => $this->formatMessage($m))->values()->all(),
        ];
    }

    public function formatMessage(LoanTelegramChatMessage $message): array
    {
        $file = null;
        if (! empty($message->file_id)) {
            $file = [
                'url' => (string) ($message->file_url ?? ''),
                'name' => (string) ($message->file_name ?? ''),
            ];
        }

        return [
            'id' => (int) $message->id,
            'thread_id' => (int) $message->thread_id,
            'sender_type' => (string) $message->sender_type,
            'sender_id' => (int) $message->sender_id,
            'sender_name' => (string) ($message->sender_name_snapshot ?? ''),
            'message' => (string) ($message->message ?? ''),
            'message_type' => (string) $message->message_type,
            'file' => $file ?? (object) [],
            'latitude' => $message->latitude,
            'longitude' => $message->longitude,
            'audio_duration_seconds' => $message->audio_duration_seconds,
            'is_own' => in_array($message->sender_type, ['staff', 'admin'], true),
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function persistMessage(LoanTelegramChatThread $thread, string $senderType, int $senderId, array $data): LoanTelegramChatMessage
    {
        $message = DB::connection('mysql_loan')->transaction(function () use ($thread, $senderType, $senderId, $data) {
            $msg = LoanTelegramChatMessage::query()->create(array_merge([
                'thread_id' => $thread->id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'sender_name_snapshot' => $data['sender_name_snapshot'] ?? $this->resolveSenderName($senderType, $senderId),
            ], $data));

            $thread->last_message = $this->lastMessageSnapshot($msg);
            $thread->last_message_type = $msg->message_type;
            $thread->last_message_at = $msg->created_at ?? now();
            if ($senderType === 'customer') {
                $thread->unread_staff_count = (int) ($thread->unread_staff_count ?? 0) + 1;
            } else {
                $thread->unread_customer_count = (int) ($thread->unread_customer_count ?? 0) + 1;
            }
            $thread->save();

            return $msg;
        });

        $this->relayToTelegramIfOutbound($message, $thread);

        return $message;
    }

    protected function relayToTelegramIfOutbound(LoanTelegramChatMessage $message, LoanTelegramChatThread $thread): void
    {
        if (! in_array($message->sender_type, ['staff', 'admin'], true)) {
            // Inbound (from Telegram) messages never get relayed back out - no echo loop.
            return;
        }

        $customer = LoanCustomer::query()->find($thread->customer_id);
        if (! $customer || empty($customer->telegram_chat_id)) {
            return;
        }

        try {
            RelayChatMessageToTelegramJob::dispatch((int) $message->id, (string) $customer->telegram_chat_id);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch Telegram chat relay job', ['error' => $e->getMessage()]);
        }
    }

    protected function resolveSenderName(string $senderType, int $senderId): string
    {
        if ($senderType === 'customer') {
            $customer = LoanCustomer::query()->find($senderId);
            return (string) ($customer->khmer_name ?? $customer->name ?? 'Customer');
        }

        if (class_exists(\App\User::class)) {
            $user = \App\User::query()->find($senderId);
            if ($user) {
                $name = trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));
                return $name ?: (string) ($user->username ?? $user->name ?? 'Staff');
            }
        }

        return $senderType === 'admin' ? 'Admin' : 'Staff';
    }

    protected function lastMessageSnapshot(LoanTelegramChatMessage $message): string
    {
        return match ($message->message_type) {
            'text' => (string) ($message->message ?? ''),
            'location' => 'Location shared',
            'image' => $message->file_name ? 'Image: '.$message->file_name : 'Image',
            'file' => $message->file_name ? 'File: '.$message->file_name : 'File',
            'audio' => 'Voice message',
            default => (string) ($message->message ?? strtoupper($message->message_type)),
        };
    }

    protected function customerWithinLocations(LoanCustomer $customer, array $locationIds): bool
    {
        $locationId = $customer->business_location_id ?? null;
        return $locationId === null || in_array((int) $locationId, $locationIds, true);
    }

    protected function customerMatchesSearch(LoanCustomer $customer, string $search): bool
    {
        $needle = mb_strtolower($search);
        foreach ([$customer->name, $customer->khmer_name, $customer->phone, $customer->login_phone, $customer->customer_code] as $field) {
            if ($field && str_contains(mb_strtolower((string) $field), $needle)) {
                return true;
            }
        }
        return false;
    }
}
