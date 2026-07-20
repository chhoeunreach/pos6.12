<?php

namespace Modules\LoanManagement\Jobs;

use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Entities\LoanFile;
use Modules\LoanManagement\Entities\LoanTelegramChatMessage;
use Modules\LoanManagement\Services\TelegramSettingsService;

class RelayChatMessageToTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected int $messageId, protected string $chatId)
    {
    }

    public function handle(): void
    {
        $message = LoanTelegramChatMessage::query()->find($this->messageId);
        if (! $message) {
            return;
        }

        $telegram = new TelegramBotService(TelegramSettingsService::botToken());

        $logId = $this->logAttempt($message);

        try {
            $this->deliver($telegram, $message);
            $this->markLog($logId, 'sent', null);
        } catch (\Throwable $e) {
            Log::warning('Telegram chat relay failed', ['message_id' => $this->messageId, 'error' => $e->getMessage()]);
            $this->markLog($logId, 'failed', $e->getMessage());
            throw $e;
        }
    }

    protected function deliver(TelegramBotService $telegram, LoanTelegramChatMessage $message): void
    {
        $result = match ($message->message_type) {
            'image' => $telegram->sendPhotoToChat($this->chatId, $this->localFilePath($message), (string) ($message->message ?? '') ?: null),
            'file', 'audio' => $telegram->sendDocumentToChat($this->chatId, $this->localFilePath($message), (string) ($message->message ?? '') ?: null, $message->file_name),
            'location' => $telegram->sendLocationToChat($this->chatId, (float) $message->latitude, (float) $message->longitude),
            default => $telegram->sendMessageToChat($this->chatId, (string) ($message->message ?? '')),
        };

        $telegramMessageId = (int) ($result['message_id'] ?? 0);
        if ($telegramMessageId > 0) {
            $message->metadata = array_merge((array) ($message->metadata ?? []), [
                'telegram_chat_id' => $this->chatId,
                'telegram_message_id' => $telegramMessageId,
                'telegram_sent_at' => now()->toIso8601String(),
            ]);
            $message->save();
        }
    }

    protected function localFilePath(LoanTelegramChatMessage $message): string
    {
        if (empty($message->file_id)) {
            throw new \RuntimeException('Chat message has no attached file to relay.');
        }

        $file = LoanFile::query()->find($message->file_id);
        if (! $file || empty($file->path)) {
            throw new \RuntimeException('Attached file could not be resolved for Telegram relay.');
        }

        $path = Storage::disk($file->disk ?? 'public')->path($file->path);
        if (! is_readable($path)) {
            throw new \RuntimeException('Attached file is not readable at: '.$path);
        }

        return $path;
    }

    protected function logAttempt(LoanTelegramChatMessage $message): ?int
    {
        if (! DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_telegram_notifications')) {
            return null;
        }

        return DB::connection('mysql_loan')->table('loan_telegram_notifications')->insertGetId([
            'customer_id' => $message->thread->customer_id ?? null,
            'loan_id' => null,
            'event_code' => 'chat_message_relay',
            'chat_id' => $this->chatId,
            'message' => (string) ($message->message ?? $message->message_type),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function markLog(?int $logId, string $status, ?string $error): void
    {
        if ($logId === null) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_telegram_notifications')->where('id', $logId)->update([
            'status' => $status,
            'response_payload' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
