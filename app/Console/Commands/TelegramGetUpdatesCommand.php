<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramGetUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Examples:
     * php artisan telegram:updates
     */
    protected $signature = 'telegram:updates {--limit=5 : Number of updates to fetch}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch Telegram bot getUpdates (useful to find group chat_id)';

    public function handle(): int
    {
        $token = (string) env('TELEGRAM_BOT_TOKEN', '');
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is empty in .env');
            return 1;
        }

        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? min($limit, 100) : 5;

        try {
            $response = Http::timeout(15)->retry(2, 250)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                'limit' => $limit,
            ]);

            if ($response->failed()) {
                $msg = 'Telegram getUpdates failed: HTTP ' . $response->status() . ' - ' . $response->body();
                Log::error($msg);
                $this->error($msg);
                return 1;
            }

            $this->line($response->body());
            return 0;
        } catch (\Exception $e) {
            Log::error('Telegram getUpdates error: ' . $e->getMessage());
            $this->error($e->getMessage());
            return 1;
        }
    }
}

