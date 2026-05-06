<?php

use App\Http\Controllers\StockTransferPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone'])->group(function () {
    Route::get('pdf/debug', [StockTransferPdfController::class, 'debug'])->name('pdf.debug');
    Route::get('stock-transfers/{id}/pdf', [StockTransferPdfController::class, 'download'])->name('stock_transfers.pdf');
    Route::post('stock-transfers/{id}/telegram', [StockTransferPdfController::class, 'sendToTelegram'])->name('stock_transfers.telegram_pdf');
});
