<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\Admin\SocialAdminController;
use Modules\Social\Http\Controllers\Api\SocialFeedController;

Route::prefix('social')->name('social.web.')->group(function () {
    Route::get('/health', [SocialFeedController::class, 'health'])->name('health');
});

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('social/admin')
    ->as('social.admin.')
    ->group(function () {
        Route::get('/', [SocialAdminController::class, 'postsIndex'])->name('posts.index');
        Route::post('/posts', [SocialAdminController::class, 'storePost'])->name('posts.store');
        Route::post('/posts/seed-sample', [SocialAdminController::class, 'seedSamplePosts'])->name('posts.seed_sample');
        Route::post('/posts/{post}/pin', [SocialAdminController::class, 'togglePin'])->name('posts.pin');
        Route::post('/posts/{post}/status', [SocialAdminController::class, 'toggleStatus'])->name('posts.status');
        Route::delete('/posts/{post}', [SocialAdminController::class, 'destroyPost'])->name('posts.destroy');

        Route::get('/reports', [SocialAdminController::class, 'reportsIndex'])->name('reports.index');
        Route::post('/reports/{report}/resolve', [SocialAdminController::class, 'resolveReport'])->name('reports.resolve');

        Route::get('/settings', [SocialAdminController::class, 'settingsIndex'])->name('settings.index');
        Route::post('/settings', [SocialAdminController::class, 'settingsUpdate'])->name('settings.update');
    });
