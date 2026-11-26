<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use App\System;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;

class SendBranchCashierPdfToTelegram extends Command
{
    protected $signature = 'local-cashier-report:send-telegram-pdf
        {--date= : yesterday|today|YYYY-MM-DD (defaults to config telegram.local_cashier_branch_pdf.date_mode)}
        {--chat= : Telegram group chat_id override}
        {--business_id= : Business ID override}
        {--lang= : Report language (km|en)}
        {--dry-run : Generate PDF and print summary without sending to Telegram}';

    protected $description = 'Generate the Branch Income & Expenses PDF (with overall summary) and send it to the configured Telegram group';

    public function handle(
        LocalCashierReportController $reportController,
        TelegramBotService $telegram
    ): int {
        if (empty(System::getProperty('localcashierreport_version'))) {
            $this->error('LocalCashierReport module is not installed.');
            return 1;
        }

        $chatId = trim((string) ($this->option('chat') ?: config('telegram.local_cashier_branch_pdf.chat_id', '')));
        if ($chatId === '') {
            $this->error('Telegram chat_id is empty. Set LOCAL_CASHIER_TELEGRAM_CHAT_ID in .env or pass --chat=.');
            return 1;
        }

        $businessId = (int) ($this->option('business_id') ?: config('telegram.local_cashier_branch_pdf.business_id', 1));
        if ($businessId <= 0) {
            $businessId = 1;
        }

        $lang = (string) ($this->option('lang') ?: config('telegram.local_cashier_branch_pdf.report_lang', 'km'));
        $lang = in_array($lang, ['en', 'km'], true) ? $lang : 'km';

        try {
            $targetDate = $this->resolveTargetDate(
                (string) ($this->option('date') ?: config('telegram.local_cashier_branch_pdf.date_mode', 'yesterday'))
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $fileName = 'branch_income_expenses_' . $targetDate . '.pdf';
        $pdfPath = $tempDir . DIRECTORY_SEPARATOR . 'tg_branch_pdf_' . $targetDate . '_' . Str::random(10) . '.pdf';

        try {
            $payload = $reportController->buildBranchPdfPayload($businessId, $targetDate, $targetDate, $lang);
            $reportController->writeBranchPdfToPath($payload['html'], $pdfPath);

            if (! File::exists($pdfPath) || File::size($pdfPath) === 0) {
                throw new \RuntimeException('Generated Branch PDF file is empty.');
            }

            $caption = $this->buildTelegramCaption(
                $payload['businessName'],
                $targetDate,
                $payload['report']['branch_dashboards'] ?? []
            );

            if ((bool) $this->option('dry-run')) {
                $this->info('Dry run OK: PDF size=' . File::size($pdfPath) . ' bytes, chat_id=' . $chatId . ', date=' . $targetDate);
                $this->line($caption);
                return 0;
            }

            $telegram->sendDocumentToChat($chatId, $pdfPath, $caption, $fileName);
            $this->info('Sent Branch PDF (' . $fileName . ') to Telegram chat ' . $chatId . '.');

            return 0;
        } catch (\Throwable $e) {
            Log::error('SendBranchCashierPdfToTelegram failed: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'date' => $targetDate,
                'business_id' => $businessId,
            ]);
            $this->error($e->getMessage());
            return 1;
        } finally {
            if (File::exists($pdfPath)) {
                File::delete($pdfPath);
            }
        }
    }

    public function resolveTargetDate(string $dateOption): string
    {
        $normalized = strtolower(trim($dateOption));
        if ($normalized === '' || $normalized === 'yesterday') {
            return Carbon::yesterday()->format('Y-m-d');
        }
        if ($normalized === 'today') {
            return Carbon::today()->format('Y-m-d');
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $normalized);
            if ($parsed && $parsed->format('Y-m-d') === $normalized) {
                return $normalized;
            }
        } catch (\Throwable $e) {
            // Fall through to exception below.
        }

        throw new \InvalidArgumentException("Invalid --date value '{$dateOption}'. Expected yesterday, today, or YYYY-MM-DD.");
    }

    public function buildTelegramCaption(string $businessName, string $date, array $branchDashboards): string
    {
        $totalSale = 0.0;
        $totalPaid = 0.0;
        $totalExpenses = 0.0;
        $totalActualIncome = 0.0;
        $totalDue = 0.0;

        foreach ($branchDashboards as $branch) {
            $totalSale += (float) ($branch['total'] ?? 0);
            $totalPaid += (float) ($branch['paid'] ?? 0);
            $totalExpenses += (float) ($branch['expenses'] ?? 0);
            $totalActualIncome += (float) ($branch['actual_income'] ?? 0);
            $totalDue += (float) ($branch['due'] ?? 0);
        }

        $fmt = fn (float $amount): string => $amount < 0
            ? '$ (' . number_format(abs($amount), 2) . ')'
            : '$ ' . number_format($amount, 2);

        return implode("\n", [
            '<b>របាយការណ៍ចំណូល-ចំណាយតាមសាខា (PDF)</b>',
            'អាជីវកម្ម: ' . htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8'),
            'កាលបរិច្ឆេទ: <b>' . $date . '</b>',
            'ចំនួនសាខា: <b>' . count($branchDashboards) . '</b>',
            'ចំណូលលក់សរុប: <b>' . $fmt($totalSale) . '</b>',
            'ប្រាក់ទទួលសរុប: <b>' . $fmt($totalPaid) . '</b>',
            'ចំណាយសរុប: <b>' . $fmt($totalExpenses) . '</b>',
            'ចំណូលជាក់ស្ដែងសរុបរួម: <b>' . $fmt($totalActualIncome) . '</b>',
            'ជំពាក់សរុប: <b>' . $fmt($totalDue) . '</b>',
        ]);
    }
}
