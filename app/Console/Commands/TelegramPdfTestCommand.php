<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use App\Services\WkhtmltopdfPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TelegramPdfTestCommand extends Command
{
    /**
     * Example:
     * php artisan telegram:pdf-test --chat=-1001234567890 --caption="Test PDF"
     */
    protected $signature = 'telegram:pdf-test
        {--chat= : Telegram chat_id (group/supergroup/user/channel)}
        {--caption= : Optional caption for Telegram document}';

    protected $description = 'Generate a small PDF and send it to Telegram (tests PDF rendering + Telegram together)';

    public function handle(WkhtmltopdfPdfService $pdfService, TelegramBotService $telegram): int
    {
        $chatId = trim((string) $this->option('chat'));
        $caption = $this->option('caption');

        if ($chatId === '') {
            $this->error('Usage: php artisan telegram:pdf-test --chat=-100xxxxxxxxxx --caption="Optional caption"');
            return 1;
        }

        $tmpDir = storage_path('app/temp');
        if (! File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'telegram_pdf_test.pdf';

        try {
            $pdfService->saveViewToPdf('telegram.pdf_test', [
                'generated_at' => now()->toDateTimeString(),
            ], $pdfPath);

            $telegram->sendDocumentToChat($chatId, $pdfPath, $caption ?: 'Test PDF', basename($pdfPath));
            $this->info('Sent PDF.');
            return 0;
        } catch (\Exception $e) {
            Log::error('Telegram PDF test error: ' . $e->getMessage());
            $this->error($e->getMessage());
            return 1;
        } finally {
            if (File::exists($pdfPath)) {
                File::delete($pdfPath);
            }
        }
    }
}
