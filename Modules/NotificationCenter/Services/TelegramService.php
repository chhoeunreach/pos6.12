<?php

namespace Modules\NotificationCenter\Services;

use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?TelegramBotService $client = null;

    protected ?string $botToken = null;

    public function __construct()
    {
        $this->botToken = config('notificationcenter.telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
    }

    protected function client(): TelegramBotService
    {
        if ($this->client === null) {
            $this->client = new TelegramBotService($this->botToken);
        }

        return $this->client;
    }

    public function sendText(string $chatId, string $message): array
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $this->botToken === '') {
            return $this->failure('Telegram not configured or empty chat_id');
        }

        try {
            $this->client()->sendMessageToChat($chatId, $message);

            return $this->success('sent');
        } catch (\Exception $e) {
            Log::error('NotificationCenter Telegram sendText failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage());
        }
    }

    public function sendDocument(string $chatId, string $filePath, ?string $caption = null, ?string $filename = null): array
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $this->botToken === '') {
            return $this->failure('Telegram not configured or empty chat_id');
        }

        if (! is_readable($filePath)) {
            return $this->failure('File not readable: '.$filePath);
        }

        try {
            $this->client()->sendDocumentToChat($chatId, $filePath, $caption, $filename);

            return $this->success('sent');
        } catch (\Exception $e) {
            Log::error('NotificationCenter Telegram sendDocument failed', [
                'chat_id' => $chatId,
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage());
        }
    }

    public function sendPhoto(string $chatId, string $filePath, ?string $caption = null, ?string $filename = null): array
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $this->botToken === '') {
            return $this->failure('Telegram not configured or empty chat_id');
        }

        if (! is_readable($filePath)) {
            return $this->failure('File not readable: '.$filePath);
        }

        try {
            $this->client()->sendPhotoToChat($chatId, $filePath, $caption, $filename);

            return $this->success('sent');
        } catch (\Exception $e) {
            Log::error('NotificationCenter Telegram sendPhoto failed', [
                'chat_id' => $chatId,
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage());
        }
    }

    public function validateChatId(string $chatId): array
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $this->botToken === '') {
            return $this->failure('Empty chat_id or token');
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->post("https://api.telegram.org/bot{$this->botToken}/sendChatAction", [
                    'chat_id' => $chatId,
                    'action' => 'typing',
                ]);

            if ($response->successful()) {
                return $this->success('valid');
            }

            return $this->failure('HTTP '.$response->status().': '.$response->body());
        } catch (\Exception $e) {
            return $this->failure($e->getMessage());
        }
    }

    public function isValid(): bool
    {
        return $this->botToken !== '';
    }

    protected function success(string $status): array
    {
        return ['success' => true, 'status' => $status, 'error' => null];
    }

    protected function failure(string $error): array
    {
        return ['success' => false, 'status' => 'failed', 'error' => $error];
    }
}
