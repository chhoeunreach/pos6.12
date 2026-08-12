<?php

namespace App\Http\Controllers;

use App\Jobs\SendStockTransferPdfJob;
use App\Services\TelegramBotService;
use App\Services\WkhtmltopdfPdfService;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class StockTransferPdfController extends Controller
{
    public function debug(Request $request, WkhtmltopdfPdfService $pdfService)
    {
        return [
            'php_extensions' => [
                'curl' => extension_loaded('curl'),
                'openssl' => extension_loaded('openssl'),
            ],
            'telegram' => [
                'bot_token_present' => trim((string) config('telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''))) !== '',
            ],
            'wkhtmltopdf_enabled' => $pdfService->isEnabled(),
            'wkhtmltopdf_binary' => $pdfService->binaryPath(),
            'wkhtmltopdf_version' => $pdfService->versionString(),
            'storage_fonts' => [
                'KhmerOSbattambang' => File::exists(storage_path('fonts/KhmerOSbattambang.ttf')),
                'NotoSansKhmer-Regular' => File::exists(storage_path('fonts/NotoSansKhmer-Regular.ttf')),
            ],
        ];
    }

    public function download($id, Request $request, WkhtmltopdfPdfService $pdfService)
    {
        $business_id = $request->session()->get('user.business_id');

        $sell_transfer = Transaction::where('business_id', $business_id)
            ->where('id', $id)
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

        $purchase_transfer = Transaction::where('business_id', $business_id)
            ->where('transfer_parent_id', $sell_transfer->id)
            ->where('type', 'purchase_transfer')
            ->with('location')
            ->firstOrFail();

        $location_details = ['sell' => $sell_transfer->location, 'purchase' => $purchase_transfer->location];

        $lot_n_exp_enabled = false;
        if ($request->session()->get('business.enable_lot_number') == 1 || $request->session()->get('business.enable_product_expiry') == 1) {
            $lot_n_exp_enabled = true;
        }

        $pdfFilename = $this->safeFilename((string) $sell_transfer->ref_no) . '.pdf';
        $pdfPath = storage_path('app/public/invoices/' . $pdfFilename);
        $pdfService->saveViewToPdf('pdf.stock_transfer', compact('sell_transfer', 'location_details', 'lot_n_exp_enabled'), $pdfPath);

        return response()->download($pdfPath, $pdfFilename)->deleteFileAfterSend(false);
    }

    public function sendToTelegram($id, Request $request)
    {
        $chat_id = trim((string) $request->input('chat_id', ''));
        if ($chat_id === '') {
            return ['success' => false, 'msg' => 'chat_id is required'];
        }

        $business_id = $request->session()->get('user.business_id');

        $lot_n_exp_enabled = false;
        if ($request->session()->get('business.enable_lot_number') == 1 || $request->session()->get('business.enable_product_expiry') == 1) {
            $lot_n_exp_enabled = true;
        }

        SendStockTransferPdfJob::dispatch((int) $id, (int) $business_id, $chat_id, $lot_n_exp_enabled, $lot_n_exp_enabled);

        return ['success' => true, 'msg' => 'PDF generation and Telegram send queued.'];
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
