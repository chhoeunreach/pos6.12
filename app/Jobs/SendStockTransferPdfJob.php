<?php

namespace App\Jobs;

use App\Services\TelegramBotService;
use App\Services\WkhtmltopdfPdfService;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SendStockTransferPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected int $transactionId;
    protected int $businessId;
    protected string $chatId;
    protected bool $lotEnabled;
    protected bool $expiryEnabled;

    public function __construct(int $transactionId, int $businessId, string $chatId, bool $lotEnabled, bool $expiryEnabled)
    {
        $this->transactionId = $transactionId;
        $this->businessId = $businessId;
        $this->chatId = $chatId;
        $this->lotEnabled = $lotEnabled;
        $this->expiryEnabled = $expiryEnabled;
    }

    public function handle(WkhtmltopdfPdfService $pdfService, TelegramBotService $telegram): void
    {
        $sell_transfer = Transaction::where('business_id', $this->businessId)
            ->where('id', $this->transactionId)
            ->where('type', 'sell_transfer')
            ->with(
                'sell_lines',
                'sell_lines.product',
                'sell_lines.variations',
                'sell_lines.lot_details',
                'sell_lines.sell_line_purchase_lines.purchase_line',
                'sell_lines.sub_unit',
                'location',
                'sell_lines.product.unit'
            )
            ->firstOrFail();

        $purchase_transfer = Transaction::where('business_id', $this->businessId)
            ->where('transfer_parent_id', $sell_transfer->id)
            ->where('type', 'purchase_transfer')
            ->with('location')
            ->firstOrFail();

        $location_details = ['sell' => $sell_transfer->location, 'purchase' => $purchase_transfer->location];
        $lot_n_exp_enabled = $this->lotEnabled || $this->expiryEnabled;

        $tmpDir = storage_path('app/temp');
        if (! File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $safeRefNo = $this->safeFilename((string) $sell_transfer->ref_no);
        $pdfFilename = $safeRefNo . '.pdf';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $pdfFilename;

        try {
            $pdfService->saveViewToPdf('pdf.stock_transfer', compact('sell_transfer', 'location_details', 'lot_n_exp_enabled'), $pdfPath);
            $telegram->sendDocumentToChat($this->chatId, $pdfPath, 'វិក្កយបត្រ', basename($pdfPath));
        } finally {
            if (File::exists($pdfPath)) {
                File::delete($pdfPath);
            }
        }
    }

    protected function safeFilename(string $filename): string
    {
        $filename = trim($filename);
        $filename = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $filename);
        $filename = preg_replace('/\s+/', ' ', $filename);
        $filename = trim($filename, " .\t\n\r\0\x0B");

        return $filename !== '' ? $filename : 'stock-transfer';
    }
}
