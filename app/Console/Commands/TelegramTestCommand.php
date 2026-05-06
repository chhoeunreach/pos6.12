<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Examples:
     * php artisan telegram:test --chat=-1001234567890 --text="Test message"
     */
    protected $signature = 'telegram:test {--chat= : Telegram chat_id (group/supergroup/user)} {--text= : Message text to send}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test Telegram message to a chat_id using TELEGRAM_BOT_TOKEN';

    public function handle(): int
    {
        $chatId = trim((string) $this->option('chat'));
        $text = (string) $this->option('text');

        if ($chatId === '' || $text === '') {
            $this->error('Usage: php artisan telegram:test --chat=-100xxxxxxxxxx --text="Hello"');
            return 1;
        }

        try {
            $service = new TelegramBotService();
            $service->sendMessageToChat($chatId, $text);
            $this->info('Sent.');
            return 0;
        } catch (\Exception $e) {
            Log::error('Telegram test error: ' . $e->getMessage());
            $this->error($e->getMessage());
            return 1;
        }
    }
}

