<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\LoanManagement\Services\TelegramSettingsService;

class SetLoanChatTelegramWebhookCommand extends Command
{
    /**
     * Examples:
     * php artisan telegram:set-loan-chat-webhook https://your-public-host.example.com
     */
    protected $signature = 'telegram:set-loan-chat-webhook {url : Public base URL the app is reachable at, e.g. https://example.com}';

    protected $description = 'Register the Loan Management Telegram bot webhook (setWebhook) for the customer chat bridge';

    public function handle(): int
    {
        $token = TelegramSettingsService::botToken();
        $secret = TelegramSettingsService::webhookSecret();

        if ($token === '') {
            $this->error('Telegram bot token is not configured. Set it under System Settings > Telegram Bot.');
            return 1;
        }
        if ($secret === '') {
            $this->error('Telegram webhook secret is not configured. Set it under System Settings > Telegram Bot.');
            return 1;
        }

        $base = rtrim((string) $this->argument('url'), '/');
        $webhookUrl = $base.'/webhook/loan-telegram';

        $response = Http::timeout(15)->asForm()->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
            'secret_token' => $secret,
        ]);

        if ($response->failed()) {
            $this->error('setWebhook failed: HTTP '.$response->status().' - '.$response->body());
            return 1;
        }

        TelegramSettingsService::markWebhookRegistered($webhookUrl);

        $this->info('Webhook registered: '.$webhookUrl);
        $this->line($response->body());
        return 0;
    }
}
