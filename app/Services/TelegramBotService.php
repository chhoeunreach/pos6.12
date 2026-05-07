<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramBotService
{
    protected string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? (string) config('telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''));
    }

    protected function baseUrl(): string
    {
        return "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Build payload for sendDocument.
     */
    protected function sendDocumentPayload(string $chat_id, ?string $caption): array
    {
        $payload = [
            'chat_id' => $chat_id,
            'parse_mode' => 'HTML',
        ];

        if ($caption !== null && trim($caption) !== '') {
            $payload['caption'] = $caption;
        }

        return $payload;
    }

    public function sendMessageToChat(string $chat_id, string $message): void
    {
        $chat_id = trim($chat_id);
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }
        if ($chat_id === '') {
            throw new \RuntimeException('Telegram config error: chat_id is empty');
        }

        $response = Http::timeout(15)->retry(2, 250)->asForm()->post($this->baseUrl() . '/sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Telegram sendMessage failed: HTTP ' . $response->status() . ' - ' . $response->body());
        }
    }

    public function sendDocumentToChat(string $chat_id, string $file_path, ?string $caption = null, ?string $filename = null): void
    {
        $chat_id = trim($chat_id);
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }
        if ($chat_id === '') {
            throw new \RuntimeException('Telegram config error: chat_id is empty');
        }

        $filename = $filename ?: basename($file_path);

        if (! is_readable($file_path)) {
            throw new \RuntimeException('Telegram sendDocument failed: file not readable: ' . $file_path);
        }

        $handle = fopen($file_path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Telegram sendDocument failed: unable to open file: ' . $file_path);
        }

        try {
            $payload = $this->sendDocumentPayload($chat_id, $caption);

            $response = Http::timeout(30)->retry(2, 250)->attach(
                'document',
                $handle,
                $filename
            )->post($this->baseUrl() . '/sendDocument', $payload);
        } finally {
            fclose($handle);
        }

        if ($response->failed()) {
            throw new \RuntimeException('Telegram sendDocument failed: HTTP ' . $response->status() . ' - ' . $response->body());
        }
    }
}
