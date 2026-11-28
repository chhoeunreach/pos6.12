<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\Api\SocialFeedController;

Route::prefix('social')->name('social.web.')->group(function () {
    Route::get('/health', [SocialFeedController::class, 'health'])->name('health');
});
