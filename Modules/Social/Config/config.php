<?php

return [
    'name' => 'Social',
    'version' => '1.0.0',
    'db_connection' => 'mysql_feed',
    'database_name' => env('DB_FEED_DATABASE', 'social'),
    'cache_ttl_seconds' => (int) env('SOCIAL_FEED_CACHE_TTL', 45),
    'media_disk' => env('FEED_MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),
];
