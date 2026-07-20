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

    /**
     * Build payload for sendPhoto.
     */
    protected function sendPhotoPayload(string $chat_id, ?string $caption): array
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

    protected function resultFromResponse($response, string $action): array
    {
        if ($response->failed()) {
            throw new \RuntimeException('Telegram '.$action.' failed: HTTP ' . $response->status() . ' - ' . $response->body());
        }

        $json = (array) ($response->json() ?? []);
        if (array_key_exists('ok', $json) && $json['ok'] !== true) {
            throw new \RuntimeException('Telegram '.$action.' failed: ' . ($json['description'] ?? $response->body()));
        }

        return (array) ($json['result'] ?? []);
    }

    public function sendMessageToChat(string $chat_id, string $message): array
    {
        $chat_id = trim($chat_id);
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }
        if ($chat_id === '') {
            throw new \RuntimeException('Telegram config error: chat_id is empty');
        }

        $response = Http::timeout(5)->retry(1, 250)->asForm()->post($this->baseUrl() . '/sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        return $this->resultFromResponse($response, 'sendMessage');
    }

    public function sendDocumentToChat(string $chat_id, string $file_path, ?string $caption = null, ?string $filename = null): array
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

        return $this->resultFromResponse($response, 'sendDocument');
    }

    public function sendLocationToChat(string $chat_id, float $latitude, float $longitude): array
    {
        $chat_id = trim($chat_id);
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }
        if ($chat_id === '') {
            throw new \RuntimeException('Telegram config error: chat_id is empty');
        }

        $response = Http::timeout(5)->retry(1, 250)->asForm()->post($this->baseUrl() . '/sendLocation', [
            'chat_id' => $chat_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        return $this->resultFromResponse($response, 'sendLocation');
    }

    /**
     * Resolve a Telegram file_id to its downloadable file_path via the getFile API.
     */
    public function getFile(string $file_id): array
    {
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }

        $response = Http::timeout(10)->retry(2, 250)->get($this->baseUrl() . '/getFile', [
            'file_id' => $file_id,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Telegram getFile failed: HTTP ' . $response->status() . ' - ' . $response->body());
        }

        $result = (array) ($response->json('result') ?? []);
        if (empty($result['file_path'])) {
            throw new \RuntimeException('Telegram getFile failed: file_path missing in response');
        }

        return $result;
    }

    /**
     * Download a Telegram file (resolved via getFile) to a local temp path and return that path.
     */
    public function downloadFile(string $file_path, ?string $destinationDir = null): string
    {
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }

        $destinationDir = $destinationDir ?: storage_path('app/temp');
        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $url = "https://api.telegram.org/file/bot{$this->token}/{$file_path}";
        $response = Http::timeout(30)->retry(2, 250)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException('Telegram file download failed: HTTP ' . $response->status());
        }

        $localPath = rtrim($destinationDir, '/') . '/tg_' . uniqid() . '_' . basename($file_path);
        file_put_contents($localPath, $response->body());

        return $localPath;
    }

    public function sendPhotoToChat(string $chat_id, string $file_path, ?string $caption = null, ?string $filename = null): array
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
            throw new \RuntimeException('Telegram sendPhoto failed: file not readable: ' . $file_path);
        }

        $handle = fopen($file_path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Telegram sendPhoto failed: unable to open file: ' . $file_path);
        }

        try {
            $response = Http::timeout(30)->retry(2, 250)->attach(
                'photo',
                $handle,
                $filename
            )->post($this->baseUrl() . '/sendPhoto', $this->sendPhotoPayload($chat_id, $caption));
        } finally {
            fclose($handle);
        }

        return $this->resultFromResponse($response, 'sendPhoto');
    }

    public function editMessageText(string $chat_id, int $message_id, string $message): array
    {
        $chat_id = trim($chat_id);
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }
        if ($chat_id === '') {
            throw new \RuntimeException('Telegram config error: chat_id is empty');
        }

        $response = Http::timeout(5)->retry(1, 250)->asForm()->post($this->baseUrl() . '/editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        return $this->resultFromResponse($response, 'editMessageText');
    }

    public function deleteMessage(string $chat_id, int $message_id): array
    {
        $chat_id = trim($chat_id);
        if ($this->token === '') {
            throw new \RuntimeException('Telegram config error: TELEGRAM_BOT_TOKEN is empty');
        }
        if ($chat_id === '') {
            throw new \RuntimeException('Telegram config error: chat_id is empty');
        }

        $response = Http::timeout(5)->retry(1, 250)->asForm()->post($this->baseUrl() . '/deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ]);

        return $this->resultFromResponse($response, 'deleteMessage');
    }
}
