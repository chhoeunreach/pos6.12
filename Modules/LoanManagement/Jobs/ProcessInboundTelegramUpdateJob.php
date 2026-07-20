<?php

namespace Modules\LoanManagement\Jobs;

use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Entities\LoanTelegramChatThread;
use Modules\LoanManagement\Services\TelegramChatService;
use Modules\LoanManagement\Services\TelegramSettingsService;

class ProcessInboundTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected array $update)
    {
    }

    public function handle(TelegramChatService $chatService): void
    {
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        if (! is_array($message) || empty($message['chat']['id'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $telegram = new TelegramBotService(TelegramSettingsService::botToken());

        if (str_starts_with($text, '/start')) {
            $this->handleLinking($telegram, $chatId, trim(substr($text, 6)), $message);
            return;
        }

        $customer = LoanCustomer::query()->where('telegram_chat_id', $chatId)->first();
        if (! $customer) {
            $this->safeReply($telegram, $chatId, 'Your Telegram account is not linked yet. Please ask staff for a connection link.');
            return;
        }

        $thread = $chatService->findOrCreateThread((int) $customer->id);
        $this->persistInboundMessage($chatService, $telegram, $thread, $customer, $message);
    }

    protected function handleLinking(TelegramBotService $telegram, string $chatId, string $token, array $message): void
    {
        if ($token === '') {
            $this->safeReply($telegram, $chatId, 'Welcome. Please use the connection link provided by staff to link your account.');
            return;
        }

        $alreadyLinked = LoanCustomer::query()->where('telegram_chat_id', $chatId)->first();

        $customer = LoanCustomer::query()
            ->where('telegram_link_token', $token)
            ->where('telegram_link_token_expires_at', '>', now())
            ->first();

        if (! $customer) {
            if ($alreadyLinked) {
                $this->safeReply($telegram, $chatId, 'You are already connected. This link is no longer needed.');
                return;
            }
            $this->safeReply($telegram, $chatId, 'This link has expired. Please ask staff for a new connection link.');
            return;
        }

        // Defense-in-depth: this chat_id is already linked to a *different* customer. The unguessable
        // token is the real control here, but never silently steal a link from another customer.
        $conflict = LoanCustomer::query()
            ->where('telegram_chat_id', $chatId)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($conflict) {
            Log::warning('Telegram link rejected: chat_id already linked to a different customer', [
                'chat_id' => $chatId,
                'attempted_customer_id' => $customer->id,
            ]);
            $this->safeReply($telegram, $chatId, 'This Telegram account is already connected to another customer.');
            return;
        }

        $customer->telegram_chat_id = $chatId;
        $customer->telegram_username = (string) ($message['from']['username'] ?? '') ?: null;
        $customer->telegram_linked_at = now();
        $customer->telegram_link_token = null;
        $customer->telegram_link_token_expires_at = null;
        $customer->save();

        $this->safeReply($telegram, $chatId, 'Your Telegram account is now connected. You can chat with our support team here.');
    }

    protected function persistInboundMessage(
        TelegramChatService $chatService,
        TelegramBotService $telegram,
        LoanTelegramChatThread $thread,
        LoanCustomer $customer,
        array $message
    ): void {
        $senderType = 'customer';
        $senderId = (int) $customer->id;
        $caption = (string) ($message['caption'] ?? '');
        $tempPath = null;

        try {
            if (! empty($message['photo'])) {
                $photos = $message['photo'];
                $largest = end($photos);
                [$file, $tempPath] = $this->downloadTelegramFile($telegram, (string) $largest['file_id'], 'jpg');
                $chatService->sendImageMessage($thread, $senderType, $senderId, $file, $caption ?: null);
            } elseif (! empty($message['document'])) {
                $doc = $message['document'];
                [$file, $tempPath] = $this->downloadTelegramFile($telegram, (string) $doc['file_id'], null, (string) ($doc['file_name'] ?? null));
                $chatService->sendFileMessage($thread, $senderType, $senderId, $file, 'file', $caption ?: null);
            } elseif (! empty($message['voice']) || ! empty($message['audio'])) {
                $audio = $message['voice'] ?? $message['audio'];
                [$file, $tempPath] = $this->downloadTelegramFile($telegram, (string) $audio['file_id'], 'ogg');
                $chatService->sendAudioMessage($thread, $senderType, $senderId, $file, (int) ($audio['duration'] ?? 0) ?: null, $caption ?: null);
            } elseif (! empty($message['location'])) {
                $loc = $message['location'];
                $chatService->sendLocationMessage($thread, $senderType, $senderId, (float) $loc['latitude'], (float) $loc['longitude']);
            } elseif (trim((string) ($message['text'] ?? '')) !== '') {
                $chatService->sendTextMessage($thread, $senderType, $senderId, (string) $message['text']);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to persist inbound Telegram chat message', ['error' => $e->getMessage()]);
        } finally {
            if ($tempPath && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @return array{0: UploadedFile, 1: string} the wrapped file plus its temp path (for caller cleanup)
     */
    protected function downloadTelegramFile(TelegramBotService $telegram, string $fileId, ?string $fallbackExtension, ?string $fallbackName = null): array
    {
        $meta = $telegram->getFile($fileId);
        $remotePath = (string) $meta['file_path'];
        $localPath = $telegram->downloadFile($remotePath);

        $filename = $fallbackName ?: basename($remotePath);
        if ($fallbackExtension && ! str_contains($filename, '.')) {
            $filename .= '.'.$fallbackExtension;
        }

        $mime = mime_content_type($localPath) ?: 'application/octet-stream';

        return [new UploadedFile($localPath, $filename, $mime, null, true), $localPath];
    }

    protected function safeReply(TelegramBotService $telegram, string $chatId, string $message): void
    {
        try {
            $telegram->sendMessageToChat($chatId, $message);
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram reply', ['error' => $e->getMessage()]);
        }
    }
}
