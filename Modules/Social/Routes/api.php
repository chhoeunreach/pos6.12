<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\Api\SocialFeedController;
use Modules\Social\Http\Middleware\AuthenticateSocialActor;

Route::prefix('social')->name('social.')->group(function () {
    Route::get('/health', [SocialFeedController::class, 'health'])->name('health');

    Route::prefix('public')->middleware(['throttle:60,1'])->group(function () {
        Route::get('/feed', [SocialFeedController::class, 'publicIndex'])->name('public.feed');
        Route::get('/feed/{post}', [SocialFeedController::class, 'publicShow'])->name('public.feed.show');
        Route::get('/feed/{post}/comments', [SocialFeedController::class, 'publicComments'])->name('public.feed.comments');
        Route::post('/feed/{post}/view', [SocialFeedController::class, 'recordView'])->name('public.feed.view');
    });

    // Unified Social REST routes: public reads without login, interactive actions gated for customers & staff.
    Route::prefix('posts')->group(function () {
        Route::middleware(['throttle:60,1'])->group(function () {
            Route::get('/', [SocialFeedController::class, 'publicIndex'])->name('posts.index');
            Route::get('/{post}', [SocialFeedController::class, 'publicShow'])->whereNumber('post')->name('posts.show');
            Route::get('/{post}/comments', [SocialFeedController::class, 'publicComments'])->whereNumber('post')->name('posts.comments');
            Route::post('/{post}/view', [SocialFeedController::class, 'recordView'])->whereNumber('post')->name('posts.view');
        });

        Route::middleware(AuthenticateSocialActor::class)->group(function () {
            Route::post('/', [SocialFeedController::class, 'actorStore'])->name('posts.store');
            Route::delete('/{post}', [SocialFeedController::class, 'actorDestroy'])->whereNumber('post')->name('posts.destroy');
            Route::post('/{post}/like', [SocialFeedController::class, 'toggleLike'])->whereNumber('post')->name('posts.like');
            Route::post('/{post}/bookmark', [SocialFeedController::class, 'toggleBookmark'])->whereNumber('post')->name('posts.bookmark');
            Route::post('/{post}/share', [SocialFeedController::class, 'sharePost'])->whereNumber('post')->name('posts.share');
            Route::post('/{post}/comments', [SocialFeedController::class, 'storeComment'])->whereNumber('post')->name('posts.comments.store');
            Route::post('/follow', [SocialFeedController::class, 'toggleFollow'])->name('posts.follow');
            Route::post('/report', [SocialFeedController::class, 'report'])->name('posts.report');
            Route::post('/block', [SocialFeedController::class, 'blockUser'])->name('posts.block');
            Route::post('/presign-upload', [SocialFeedController::class, 'presignUpload'])->name('posts.presign');
        });
    });

    Route::prefix('customer')->middleware('auth:customer_loan_api')->group(function () {
        Route::post('/feed', [SocialFeedController::class, 'customerStore'])->name('customer.feed.store');
        Route::delete('/feed/{post}', [SocialFeedController::class, 'customerDestroy'])->name('customer.feed.destroy');
        Route::post('/feed/{post}/like', [SocialFeedController::class, 'toggleLike'])->name('customer.feed.like');
        Route::post('/feed/{post}/bookmark', [SocialFeedController::class, 'toggleBookmark'])->name('customer.feed.bookmark');
        Route::post('/feed/{post}/share', [SocialFeedController::class, 'sharePost'])->name('customer.feed.share');
        Route::post('/feed/{post}/comments', [SocialFeedController::class, 'storeComment'])->name('customer.feed.comments.store');
        Route::post('/feed/follow', [SocialFeedController::class, 'toggleFollow'])->name('customer.feed.follow');
        Route::post('/feed/report', [SocialFeedController::class, 'report'])->name('customer.feed.report');
        Route::post('/feed/block', [SocialFeedController::class, 'blockUser'])->name('customer.feed.block');
        Route::post('/feed/presign-upload', [SocialFeedController::class, 'presignUpload'])->name('customer.feed.presign');
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('/feed', [SocialFeedController::class, 'staffIndex'])->name('staff.feed.index');
        Route::post('/feed', [SocialFeedController::class, 'store'])->name('staff.feed.store');
        Route::delete('/feed/{post}', [SocialFeedController::class, 'destroy'])->name('staff.feed.destroy');
        Route::post('/feed/presign-upload', [SocialFeedController::class, 'presignUpload'])->name('staff.feed.presign');
        Route::get('/feed/reports', [SocialFeedController::class, 'reports'])->name('staff.feed.reports');
        Route::post('/feed/reports/{report}/resolve', [SocialFeedController::class, 'resolveReport'])->name('staff.feed.reports.resolve');
    });
});
