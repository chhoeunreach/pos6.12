<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Support\Facades\Http;

class TelegramAlertService
{
    public function send(string $title, array $payload = []): bool
    {
        if (! config('smartstockinventory.telegram.enabled')) {
            return false;
        }

        $token = (string) config('smartstockinventory.telegram.bot_token');
        $chatId = (string) config('smartstockinventory.telegram.chat_id');
        if ($token === '' || $chatId === '') {
            return false;
        }

        $message = "*{$title}*\n";
        foreach ($payload as $k => $v) {
            $message .= "{$k}: {$v}\n";
        }

        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);

        return $resp->successful();
    }
}
