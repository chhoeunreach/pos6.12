<?php

use Illuminate\Support\Facades\Route;

Route::prefix('notification-center')->group(function () {
    Route::get('/install', 'InstallController@index');
    Route::post('/install', 'InstallController@install');
    Route::get('/install/uninstall', 'InstallController@uninstall');
    Route::get('/install/update', 'InstallController@update');
});

Route::middleware(['auth', 'language', 'AdminSidebarMenu'])->prefix('notification-center')->group(function () {
    Route::get('groups/import-template', 'NotificationGroupController@downloadTemplate')
        ->name('notificationcenter.groups.download-template');
    Route::get('groups/export', 'NotificationGroupController@export')
        ->name('notificationcenter.groups.export');
    Route::post('groups/import-preview', 'NotificationGroupController@importPreview')
        ->name('notificationcenter.groups.import-preview');
    Route::post('groups/import-confirm', 'NotificationGroupController@importConfirm')
        ->name('notificationcenter.groups.import-confirm');

    Route::resource('groups', 'NotificationGroupController', [
        'as' => 'notificationcenter',
        'only' => ['index', 'create', 'store', 'edit', 'update', 'destroy'],
        'names' => [
            'index' => 'notificationcenter.groups.index',
            'create' => 'notificationcenter.groups.create',
            'store' => 'notificationcenter.groups.store',
            'edit' => 'notificationcenter.groups.edit',
            'update' => 'notificationcenter.groups.update',
            'destroy' => 'notificationcenter.groups.destroy',
        ],
    ]);

    Route::post('groups/{group}/test', 'NotificationGroupController@test')
        ->name('notificationcenter.groups.test');

    Route::resource('templates', 'NotificationTemplateController', [
        'as' => 'notificationcenter',
        'names' => [
            'index' => 'notificationcenter.templates.index',
            'create' => 'notificationcenter.templates.create',
            'store' => 'notificationcenter.templates.store',
            'edit' => 'notificationcenter.templates.edit',
            'update' => 'notificationcenter.templates.update',
            'destroy' => 'notificationcenter.templates.destroy',
        ],
    ]);

    Route::resource('logs', 'NotificationLogController', [
        'as' => 'notificationcenter',
        'only' => ['index'],
        'names' => [
            'index' => 'notificationcenter.logs.index',
        ],
    ]);

    Route::post('logs/{log}/retry', 'NotificationLogController@retry')
        ->name('notificationcenter.logs.retry');

    Route::get('settings', 'NotificationSettingController@index')
        ->name('notificationcenter.settings.index');
    Route::post('settings', 'NotificationSettingController@update')
        ->name('notificationcenter.settings.update');
});
