<?php

return [
    'name' => 'NotificationCenter',
    'module_version' => '1.0',
    'pid' => 99,

    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    'queue_enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),

    'pdf_engine' => env('NOTIFICATION_PDF_ENGINE', 'wkhtmltopdf'),

    'temp_folder' => storage_path('app/notification-temp'),

    'cleanup_days' => env('NOTIFICATION_CLEANUP_DAYS', 7),

    'retry_attempts' => env('NOTIFICATION_RETRY_ATTEMPTS', 3),
];
