<?php

use Illuminate\Support\Facades\Route;
use Modules\WarrantyCardPrint\Http\Controllers\WarrantyCardPrintController;

Route::middleware([
    'web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin',
])->prefix('warranty-card-print')->group(function () {
    Route::get('/', [WarrantyCardPrintController::class, 'create'])->name('warranty-card-print.create');
});
