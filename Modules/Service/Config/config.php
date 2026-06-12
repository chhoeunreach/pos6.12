<?php

return [
    'name' => 'Service',
    'module_version' => '1.0.0',
    'route_prefix' => env('SERVICE_ROUTE_PREFIX', 'service'),
    'route_name_prefix' => 'service.',
    'database_connection' => env('SERVICE_DB_CONNECTION', 'service'),
    'excluded_main_modules' => [],
    'middleware' => [
        'web',
    ],
];

